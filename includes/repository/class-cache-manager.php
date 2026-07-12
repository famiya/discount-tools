<?php
/**
 * Cache Manager
 *
 * Centralized cache management for discount rules and calculations.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 */

/**
 * Cache manager class.
 *
 * Provides a unified interface for caching discount rules, conditions,
 * and calculation results using WordPress Object Cache API.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Cache_Manager {

	/**
	 * Cache group for rules.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $group_rules = 'discount_tools_rules';

	/**
	 * Cache group for conditions.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $group_conditions = 'discount_tools_conditions';

	/**
	 * Cache group for calculations.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $group_calculations = 'discount_tools_calculations';

	/**
	 * Cache group for meta.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $group_meta = 'discount_tools_meta';

	/**
	 * Default cache expiration time (in seconds).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $default_expiration = 3600; // 1 hour

	/**
	 * Cache statistics.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $stats = array(
		'hits'   => 0,
		'misses' => 0,
		'sets'   => 0,
		'deletes' => 0,
	);

	/**
	 * Singleton instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Cache_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since  1.0.0
	 * @return Discount_Tools_Cache_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Initialize cache groups for persistent object cache compatibility
		wp_cache_add_global_groups( array(
			$this->group_rules,
			$this->group_conditions,
			$this->group_calculations,
			$this->group_meta,
		) );
	}

	/**
	 * Get cached data.
	 *
	 * @since  1.0.0
	 * @param  string $key   Cache key.
	 * @param  string $group Cache group.
	 * @return mixed|false   Cached data or false if not found.
	 */
	public function get( $key, $group = '' ) {
		$group = $this->get_group( $group );
		$data = wp_cache_get( $key, $group );

		if ( false !== $data ) {
			$this->stats['hits']++;
		} else {
			$this->stats['misses']++;
		}

		return $data;
	}

	/**
	 * Set cached data.
	 *
	 * @since  1.0.0
	 * @param  string $key        Cache key.
	 * @param  mixed  $data       Data to cache.
	 * @param  string $group      Cache group.
	 * @param  int    $expiration Expiration time in seconds. Default 3600.
	 * @return bool               True on success, false on failure.
	 */
	public function set( $key, $data, $group = '', $expiration = 0 ) {
		$group = $this->get_group( $group );
		
		if ( 0 === $expiration ) {
			$expiration = $this->default_expiration;
		}

		$result = wp_cache_set( $key, $data, $group, $expiration );

		if ( $result ) {
			$this->stats['sets']++;
		}

		return $result;
	}

	/**
	 * Delete cached data.
	 *
	 * @since  1.0.0
	 * @param  string $key   Cache key.
	 * @param  string $group Cache group.
	 * @return bool          True on success, false on failure.
	 */
	public function delete( $key, $group = '' ) {
		$group = $this->get_group( $group );
		$result = wp_cache_delete( $key, $group );

		if ( $result ) {
			$this->stats['deletes']++;
		}

		return $result;
	}

	/**
	 * Flush all cache in a specific group.
	 *
	 * @since  1.0.0
	 * @param  string $group Cache group.
	 * @return bool          True on success, false on failure.
	 */
	public function flush_group( $group = '' ) {
		$group = $this->get_group( $group );

		// WordPress doesn't provide a built-in flush by group function,
		// so we use a version key strategy
		$version_key = $group . '_version';
		$current_version = wp_cache_get( $version_key, $group );
		
		if ( false === $current_version ) {
			$current_version = 0;
		}

		$new_version = $current_version + 1;
		$result = wp_cache_set( $version_key, $new_version, $group, 0 );

		// ?Œæ?æ¸…é™¤ transient ç·©å? (?¨æ–¼ find_active_by_type ?ªå?)
		if ( 'rules' === $group ) {
			$this->clear_rule_transients();
		}

		return $result;
	}

	/**
	 * Clear all rule-related transients.
	 *
	 * @since  1.3.0
	 * @access private
	 * @return void
	 */
	private function clear_rule_transients() {
		$rule_types = array( 'product', 'cart', 'bulk', 'bogo', 'role_based' );
		foreach ( $rule_types as $type ) {
			delete_transient( 'dt_active_rules_' . $type );
		}
	}

	/**
	 * Flush all discount tools cache.
	 *
	 * @since  1.0.0
	 * @return bool True on success.
	 */
	public function flush_all() {
		$this->flush_group( 'rules' );
		$this->flush_group( 'conditions' );
		$this->flush_group( 'calculations' );
		$this->flush_group( 'meta' );

		// Also clear any transients (for backward compatibility)
		$this->clear_transients();

		return true;
	}

	/**
	 * Get versioned cache key.
	 *
	 * This allows for group-wide cache invalidation by incrementing the version.
	 *
	 * @since  1.0.0
	 * @param  string $key   Base cache key.
	 * @param  string $group Cache group.
	 * @return string        Versioned cache key.
	 */
	public function get_versioned_key( $key, $group = '' ) {
		$group = $this->get_group( $group );
		$version_key = $group . '_version';
		$version = wp_cache_get( $version_key, $group );

		if ( false === $version ) {
			$version = 1;
			wp_cache_set( $version_key, $version, $group, 0 );
		}

		return $key . '_v' . $version;
	}

	/**
	 * Cache a rule.
	 *
	 * @since  1.0.0
	 * @param  int                  $rule_id Rule ID.
	 * @param  Discount_Tools_Rule  $rule    Rule object.
	 * @param  int                  $expiration Expiration time. Default 3600.
	 * @return bool                 True on success, false on failure.
	 */
	public function cache_rule( $rule_id, $rule, $expiration = 0 ) {
		$key = $this->get_versioned_key( 'rule_' . $rule_id, 'rules' );
		return $this->set( $key, $rule, 'rules', $expiration );
	}

	/**
	 * Get cached rule.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return Discount_Tools_Rule|false Rule object or false.
	 */
	public function get_rule( $rule_id ) {
		$key = $this->get_versioned_key( 'rule_' . $rule_id, 'rules' );
		return $this->get( $key, 'rules' );
	}

	/**
	 * Delete rule cache.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return bool         True on success, false on failure.
	 */
	public function delete_rule( $rule_id ) {
		$key = $this->get_versioned_key( 'rule_' . $rule_id, 'rules' );
		return $this->delete( $key, 'rules' );
	}

	/**
	 * Cache rules list.
	 *
	 * @since  1.0.0
	 * @param  string $cache_key   Cache key for this specific query.
	 * @param  array  $rules       Array of rule objects.
	 * @param  int    $expiration  Expiration time. Default 3600.
	 * @return bool                True on success, false on failure.
	 */
	public function cache_rules_list( $cache_key, $rules, $expiration = 0 ) {
		$key = $this->get_versioned_key( $cache_key, 'rules' );
		return $this->set( $key, $rules, 'rules', $expiration );
	}

	/**
	 * Get cached rules list.
	 *
	 * @since  1.0.0
	 * @param  string $cache_key Cache key for this specific query.
	 * @return array|false       Array of rules or false.
	 */
	public function get_rules_list( $cache_key ) {
		$key = $this->get_versioned_key( $cache_key, 'rules' );
		return $this->get( $key, 'rules' );
	}

	/**
	 * Cache conditions for a rule.
	 *
	 * @since  1.0.0
	 * @param  int   $rule_id     Rule ID.
	 * @param  array $conditions  Array of condition objects.
	 * @param  int   $expiration  Expiration time. Default 3600.
	 * @return bool               True on success, false on failure.
	 */
	public function cache_conditions( $rule_id, $conditions, $expiration = 0 ) {
		$key = $this->get_versioned_key( 'conditions_rule_' . $rule_id, 'conditions' );
		return $this->set( $key, $conditions, 'conditions', $expiration );
	}

	/**
	 * Get cached conditions.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return array|false  Array of conditions or false.
	 */
	public function get_conditions( $rule_id ) {
		$key = $this->get_versioned_key( 'conditions_rule_' . $rule_id, 'conditions' );
		return $this->get( $key, 'conditions' );
	}

	/**
	 * Delete conditions cache for a rule.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return bool         True on success, false on failure.
	 */
	public function delete_conditions( $rule_id ) {
		$key = $this->get_versioned_key( 'conditions_rule_' . $rule_id, 'conditions' );
		return $this->delete( $key, 'conditions' );
	}

	/**
	 * Cache a calculation result.
	 *
	 * @since  1.0.0
	 * @param  string $cache_key   Unique cache key for this calculation.
	 * @param  mixed  $result      Calculation result.
	 * @param  int    $expiration  Expiration time. Default 1800 (30 min).
	 * @return bool                True on success, false on failure.
	 */
	public function cache_calculation( $cache_key, $result, $expiration = 1800 ) {
		$key = $this->get_versioned_key( $cache_key, 'calculations' );
		return $this->set( $key, $result, 'calculations', $expiration );
	}

	/**
	 * Get cached calculation result.
	 *
	 * @since  1.0.0
	 * @param  string $cache_key Cache key.
	 * @return mixed|false       Calculation result or false.
	 */
	public function get_calculation( $cache_key ) {
		$key = $this->get_versioned_key( $cache_key, 'calculations' );
		return $this->get( $key, 'calculations' );
	}

	/**
	 * Delete calculation cache.
	 *
	 * @since  1.0.0
	 * @param  string $cache_key Cache key.
	 * @return bool              True on success, false on failure.
	 */
	public function delete_calculation( $cache_key ) {
		$key = $this->get_versioned_key( $cache_key, 'calculations' );
		return $this->delete( $key, 'calculations' );
	}

	/**
	 * Invalidate all caches related to a rule.
	 *
	 * Called when a rule is created, updated, or deleted.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return bool         True on success.
	 */
	public function invalidate_rule( $rule_id ) {
		// Delete specific rule cache
		$this->delete_rule( $rule_id );

		// Delete conditions cache
		$this->delete_conditions( $rule_id );

		// Invalidate all rule lists by incrementing version
		$this->flush_group( 'rules' );

		// Invalidate all calculations (since rule change affects results)
		$this->flush_group( 'calculations' );

		return true;
	}

	/**
	 * Get cache statistics.
	 *
	 * @since  1.0.0
	 * @return array Cache statistics.
	 */
	public function get_stats() {
		$total = $this->stats['hits'] + $this->stats['misses'];
		$hit_rate = $total > 0 ? ( $this->stats['hits'] / $total ) * 100 : 0;

		return array(
			'hits'     => $this->stats['hits'],
			'misses'   => $this->stats['misses'],
			'sets'     => $this->stats['sets'],
			'deletes'  => $this->stats['deletes'],
			'total'    => $total,
			'hit_rate' => round( $hit_rate, 2 ),
		);
	}

	/**
	 * Reset cache statistics.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function reset_stats() {
		$this->stats = array(
			'hits'    => 0,
			'misses'  => 0,
			'sets'    => 0,
			'deletes' => 0,
		);
	}

	/**
	 * Generate cache key for a query.
	 *
	 * @since  1.0.0
	 * @param  string $prefix Prefix for the key.
	 * @param  array  $args   Query arguments.
	 * @return string         Generated cache key.
	 */
	public function generate_key( $prefix, $args = array() ) {
		if ( empty( $args ) ) {
			return $prefix;
		}

		// Sort args for consistent keys
		ksort( $args );

		return $prefix . '_' . md5( serialize( $args ) );
	}

	/**
	 * Get cache group name.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $type Cache type (rules, conditions, calculations, meta).
	 * @return string       Full cache group name.
	 */
	private function get_group( $type ) {
		switch ( $type ) {
			case 'rules':
				return $this->group_rules;
			case 'conditions':
				return $this->group_conditions;
			case 'calculations':
				return $this->group_calculations;
			case 'meta':
				return $this->group_meta;
			default:
				return $this->group_rules;
		}
	}

	/**
	 * Clear transients (for backward compatibility).
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function clear_transients() {
		global $wpdb;

		// Delete all discount tools transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup of legacy transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_rule_%' 
			OR option_name LIKE '_transient_timeout_rule_%'
			OR option_name LIKE '_transient_rules_%'
			OR option_name LIKE '_transient_timeout_rules_%'
			OR option_name LIKE '_transient_conditions_%'
			OR option_name LIKE '_transient_timeout_conditions_%'"
		);
	}

	/**
	 * Warm up cache for active rules.
	 *
	 * Pre-loads frequently accessed rules into cache.
	 *
	 * @since  1.0.0
	 * @return int Number of rules cached.
	 */
	public function warmup() {
		// This would be called by Repository
		// after fetching active rules from database
		return 0;
	}

	/**
	 * Get cache info for debugging.
	 *
	 * @since  1.0.0
	 * @return array Cache information.
	 */
	public function get_cache_info() {
		$info = array(
			'object_cache_enabled' => wp_using_ext_object_cache(),
			'cache_type'           => $this->get_cache_type(),
			'groups'               => array(
				'rules'        => $this->group_rules,
				'conditions'   => $this->group_conditions,
				'calculations' => $this->group_calculations,
				'meta'         => $this->group_meta,
			),
			'default_expiration'   => $this->default_expiration,
			'stats'                => $this->get_stats(),
		);

		return $info;
	}

	/**
	 * Get cache type.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string Cache type (redis, memcached, database, none).
	 */
	private function get_cache_type() {
		if ( ! wp_using_ext_object_cache() ) {
			return 'database';
		}

		// Try to detect cache backend
		if ( class_exists( 'Redis' ) || class_exists( 'Predis\Client' ) ) {
			return 'redis';
		}

		if ( class_exists( 'Memcached' ) || class_exists( 'Memcache' ) ) {
			return 'memcached';
		}

		return 'unknown';
	}
}
