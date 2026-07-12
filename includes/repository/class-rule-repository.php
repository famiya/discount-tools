<?php
/**
 * Rule Repository
 *
 * Data access layer for discount rules.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 */

/**
 * Rule repository class.
 *
 * Handles CRUD operations and queries for discount rules.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Rule_Repository implements Discount_Tools_Repository_Interface {

	/**
	 * WordPress database object.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    wpdb
	 */
	private $wpdb;

	/**
	 * Rules table name.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table_rules;

	/**
	 * Conditions table name.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table_conditions;

	/**
	 * Rule meta table name.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table_rule_meta;

	/**
	 * Cache manager instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Cache_Manager
	 */
	private $cache;

	/**
	 * Initialize the repository.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb               = $wpdb;
		$this->table_rules        = $wpdb->prefix . 'dt_rules';
		$this->table_conditions   = $wpdb->prefix . 'dt_conditions';
		$this->table_rule_meta    = $wpdb->prefix . 'dt_rule_meta';
		$this->cache              = Discount_Tools_Cache_Manager::get_instance();
	}

	/**
	 * Find a rule by ID.
	 *
	 * @since  1.0.0
	 * @param  int $id Rule ID.
	 * @return Discount_Tools_Rule|null Rule object or null.
	 */
	public function find( $id ) {
		// Check cache first
		$cached = $this->cache->get_rule( $id );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// Query database
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_rules} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		// Load rule with related data
		$rule = $this->hydrate_rule( $row );

		// Cache the result
		$this->cache->cache_rule( $id, $rule );

		return $rule;
	}

	/**
	 * Find all rules.
	 *
	 * @since  1.0.0
	 * @param  array $args {
	 *     Query arguments.
	 *
	 *     @type string $status       Rule status (active, paused, expired).
	 *     @type string $rule_type    Rule type (product, cart, category).
	 *     @type string $discount_type Discount type (percentage, fixed, price_override).
	 *     @type string $order_by     Order by field. Default 'priority'.
	 *     @type string $order        Order direction (ASC, DESC). Default 'DESC'.
	 *     @type int    $limit        Limit number of results.
	 *     @type int    $offset       Offset for pagination.
	 * }
	 * @return array Array of Discount_Tools_Rule objects.
	 */
	public function find_all( $args = array() ) {
		$defaults = array(
			'status'        => '',
			'rule_type'     => '',
			'discount_type' => '',
			'order_by'      => 'priority',
			'order'         => 'DESC',
			'limit'         => 0,
			'offset'        => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build cache key
		$cache_key = $this->cache->generate_key( 'rules_all', $args );
		$cached = $this->cache->get_rules_list( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// Build query
		$where_clauses = array();
		$where_values = array();

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['rule_type'] ) ) {
			$where_clauses[] = 'rule_type = %s';
			$where_values[] = $args['rule_type'];
		}

		if ( ! empty( $args['discount_type'] ) ) {
			$where_clauses[] = 'discount_type = %s';
			$where_values[] = $args['discount_type'];
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Order by
		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		if ( false === $order_by ) {
			$order_by = 'priority DESC';
		}

		// Limit and offset
		$limit_sql = '';
		if ( $args['limit'] > 0 ) {
			$limit_sql = $this->wpdb->prepare( 'LIMIT %d', $args['limit'] );
			
			if ( $args['offset'] > 0 ) {
				$limit_sql .= $this->wpdb->prepare( ' OFFSET %d', $args['offset'] );
			}
		}

		// Build final query
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$sql = "SELECT * FROM {$this->table_rules} {$where_sql} ORDER BY {$order_by} {$limit_sql}";

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
			$sql = $this->wpdb->prepare( $sql, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is prepared above when where_values is not empty.
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		// Hydrate rules
		$rules = array();
		foreach ( $rows as $row ) {
			$rules[] = $this->hydrate_rule( $row );
		}

		// Cache the results
		$this->cache->cache_rules_list( $cache_key, $rules );

		return $rules;
	}

	/**
	 * Find active rules.
	 *
	 * @since  1.0.0
	 * @param  array $args Query arguments.
	 * @return array       Array of active rule objects.
	 */
	public function find_active( $args = array() ) {
		$args['status'] = 'active';
		return $this->find_all( $args );
	}

	/**
	 * Find rules by type.
	 *
	 * @since  1.0.0
	 * @param  string $rule_type Rule type (product, cart, category).
	 * @param  array  $args      Additional query arguments.
	 * @return array             Array of rule objects.
	 */
	public function find_by_type( $rule_type, $args = array() ) {
		$args['rule_type'] = $rule_type;
		return $this->find_all( $args );
	}

	/**
	 * Find active rules by type with optimized query.
	 *
	 * This is the most common query pattern, so we optimize it with:
	 * 1. Composite index usage (rule_type, status, priority)
	 * 2. Longer cache duration (transient)
	 * 3. Minimal data loading
	 *
	 * @since  1.3.0
	 * @param  string $rule_type Rule type (product, cart, category).
	 * @return array             Array of active rule objects.
	 */
	public function find_active_by_type( $rule_type ) {
		// 使用更持久的 transient 緩存 (30分鐘)
		$cache_key = 'dt_active_rules_' . $rule_type;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// 使用複合索引優化的查詢
		// 索引 type_status_priority 覆蓋 WHERE 和 ORDER BY
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_rules} WHERE rule_type = %s AND status = %s ORDER BY priority DESC", $rule_type, 'active' ), ARRAY_A );

		if ( ! $rows ) {
			$result = array();
		} else {
			$result = array();
			foreach ( $rows as $row ) {
				$result[] = $this->hydrate_rule( $row );
			}
		}

		// 緩存 30 分鐘
		set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Find rules by priority range.
	 *
	 * @since  1.0.0
	 * @param  int   $min_priority Minimum priority.
	 * @param  int   $max_priority Maximum priority.
	 * @param  array $args         Additional query arguments.
	 * @return array               Array of rule objects.
	 */
	public function find_by_priority( $min_priority, $max_priority, $args = array() ) {
		$cache_key = 'rules_priority_' . $min_priority . '_' . $max_priority . '_' . md5( serialize( $args ) );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_rules} WHERE priority >= %d AND priority <= %d ORDER BY priority DESC", $min_priority, $max_priority ), ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		$rules = array();
		foreach ( $rows as $row ) {
			$rules[] = $this->hydrate_rule( $row );
		}

		set_transient( $cache_key, $rules, $this->cache_expiration );

		return $rules;
	}

	/**
	 * Create a new rule.
	 *
	 * @since  1.0.0
	 * @param  array $data {
	 *     Rule data.
	 *
	 *     @type string $name              Rule name.
	 *     @type string $description       Rule description.
	 *     @type string $discount_type     Discount type.
	 *     @type float  $discount_value    Discount value.
	 *     @type string $rule_type         Rule type.
	 *     @type string $status            Status.
	 *     @type int    $priority          Priority.
	 *     @type string $start_date        Start date (Y-m-d H:i:s).
	 *     @type string $end_date          End date (Y-m-d H:i:s).
	 *     @type int    $usage_limit       Usage limit.
	 *     @type int    $usage_limit_per_user Per-user usage limit.
	 *     @type int    $minimum_amount    Minimum amount.
	 *     @type int    $maximum_amount    Maximum amount.
	 * }
	 * @return int|false Created rule ID or false on failure.
	 */
	public function create( $data ) {
		// Prepare data for insertion
		$insert_data = array(
			'name'              => $data['name'],
			'description'       => isset( $data['description'] ) ? $data['description'] : '',
			'discount_type'     => $data['discount_type'],
			'discount_value'    => $data['discount_value'],
			'discount_subtype'  => isset( $data['discount_subtype'] ) ? $data['discount_subtype'] : 'simple',
			'rule_type'         => $data['rule_type'],
			'status'            => isset( $data['status'] ) ? $data['status'] : 'active',
			'priority'          => isset( $data['priority'] ) ? $data['priority'] : 10,
			'start_date'        => isset( $data['start_date'] ) ? $data['start_date'] : null,
			'end_date'          => isset( $data['end_date'] ) ? $data['end_date'] : null,
			'usage_limit'       => isset( $data['usage_limit'] ) ? $data['usage_limit'] : 0,
			'bxgy_buy_quantity' => isset( $data['bxgy_buy_quantity'] ) ? $data['bxgy_buy_quantity'] : null,
			'bxgy_get_quantity' => isset( $data['bxgy_get_quantity'] ) ? $data['bxgy_get_quantity'] : null,
			'bxgy_get_discount' => isset( $data['bxgy_get_discount'] ) ? $data['bxgy_get_discount'] : null,
			'bxgy_get_type'     => isset( $data['bxgy_get_type'] ) ? $data['bxgy_get_type'] : null,
			'apply_mode'        => isset( $data['apply_mode'] ) ? $data['apply_mode'] : 'first',
			'date_created'      => current_time( 'mysql' ),
			'date_modified'     => current_time( 'mysql' ),
		);

		$format = array(
			'%s', // name
			'%s', // description
			'%s', // discount_type
			'%f', // discount_value
			'%s', // discount_subtype
			'%s', // rule_type
			'%s', // status
			'%d', // priority
			'%s', // start_date
			'%s', // end_date
			'%d', // usage_limit
			'%d', // bxgy_buy_quantity
			'%d', // bxgy_get_quantity
			'%f', // bxgy_get_discount
			'%s', // bxgy_get_type
			'%s', // apply_mode
			'%s', // date_created
			'%s', // date_modified
		);

		$result = $this->wpdb->insert( $this->table_rules, $insert_data, $format );

		if ( ! $result ) {
			return false;
		}

		$rule_id = $this->wpdb->insert_id;

		// Clear cache
		$this->clear_cache();

		return $rule_id;
	}

	/**
	 * Update a rule.
	 *
	 * @since  1.0.0
	 * @param  int   $id   Rule ID.
	 * @param  array $data Updated data.
	 * @return bool        True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$update_data = array();
		$format = array();

		// Build update data
		$allowed_fields = array(
			'name'                  => '%s',
			'description'           => '%s',
			'discount_type'         => '%s',
			'discount_value'        => '%f',
			'discount_subtype'      => '%s',
			'rule_type'             => '%s',
			'status'                => '%s',
			'priority'              => '%d',
			'start_date'            => '%s',
			'end_date'              => '%s',
			'usage_limit'           => '%d',
			'usage_limit_per_user'  => '%d',
			'minimum_amount'        => '%f',
			'maximum_amount'        => '%f',
			'bxgy_buy_quantity'     => '%d',
			'bxgy_get_quantity'     => '%d',
			'bxgy_get_discount'     => '%f',
			'bxgy_get_type'         => '%s',
			'apply_mode'            => '%s',
		);

		foreach ( $allowed_fields as $field => $field_format ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$format[] = $field_format;
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table_rules,
			$update_data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $id );

		return true;
	}

	/**
	 * Delete a rule.
	 *
	 * @since  1.0.0
	 * @param  int $id Rule ID.
	 * @return bool    True on success, false on failure.
	 */
	public function delete( $id ) {
		// Delete associated conditions
		$this->wpdb->delete(
			$this->table_conditions,
			array( 'rule_id' => $id ),
			array( '%d' )
		);

		// Delete associated meta
		$this->wpdb->delete(
			$this->table_rule_meta,
			array( 'rule_id' => $id ),
			array( '%d' )
		);

		// Delete rule
		$result = $this->wpdb->delete(
			$this->table_rules,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $id );

		return true;
	}

	/**
	 * Count rules.
	 *
	 * @since  1.0.0
	 * @param  array $args Query arguments.
	 * @return int         Number of rules.
	 */
	public function count( $args = array() ) {
		$where_clauses = array();
		$where_values = array();

		if ( isset( $args['status'] ) && ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( isset( $args['rule_type'] ) && ! empty( $args['rule_type'] ) ) {
			$where_clauses[] = 'rule_type = %s';
			$where_values[] = $args['rule_type'];
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$sql = "SELECT COUNT(*) FROM {$this->table_rules} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
			$sql = $this->wpdb->prepare( $sql, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is prepared above when where_values is not empty.
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Increment usage count for a rule.
	 *
	 * @since  1.0.0
	 * @param  int $id Rule ID.
	 * @return bool    True on success, false on failure.
	 */
	public function increment_usage_count( $id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$result = $this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->table_rules} SET usage_count = usage_count + 1 WHERE id = %d", $id ) );

		if ( false === $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $id );

		return true;
	}

	/**
	 * Get rule meta.
	 *
	 * @since  1.0.0
	 * @param  int    $rule_id Rule ID.
	 * @param  string $meta_key Meta key. If empty, returns all meta.
	 * @return mixed            Meta value or array of meta values.
	 */
	public function get_meta( $rule_id, $meta_key = '' ) {
		if ( empty( $meta_key ) ) {
			// Get all meta
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
			$results = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT meta_key, meta_value FROM {$this->table_rule_meta} WHERE rule_id = %d", $rule_id ), ARRAY_A );

			if ( ! $results ) {
				return array();
			}

			$meta = array();
			foreach ( $results as $row ) {
				$meta[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] );
			}

			return $meta;
		}

		// Get specific meta
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$value = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT meta_value FROM {$this->table_rule_meta} WHERE rule_id = %d AND meta_key = %s", $rule_id, $meta_key ) );

		return maybe_unserialize( $value );
	}

	/**
	 * Update rule meta.
	 *
	 * @since  1.0.0
	 * @param  int    $rule_id    Rule ID.
	 * @param  string $meta_key   Meta key.
	 * @param  mixed  $meta_value Meta value.
	 * @return bool               True on success, false on failure.
	 */
	public function update_meta( $rule_id, $meta_key, $meta_value ) {
		$meta_value = maybe_serialize( $meta_value );

		// Check if meta exists
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_rule_meta} WHERE rule_id = %d AND meta_key = %s",
				$rule_id,
				$meta_key
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $exists ) {
			// Update existing meta
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Custom table columns, not wp_postmeta.
			$result = $this->wpdb->update(
				$this->table_rule_meta,
				array( 'meta_value' => $meta_value ),
				array(
					'rule_id'  => $rule_id,
					'meta_key' => $meta_key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		} else {
			// Insert new meta
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Custom table columns, not wp_postmeta.
			$result = $this->wpdb->insert(
				$this->table_rule_meta,
				array(
					'rule_id'    => $rule_id,
					'meta_key'   => $meta_key,
					'meta_value' => $meta_value,
				),
				array( '%d', '%s', '%s' )
			);
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		if ( false === $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $rule_id );

		return true;
	}

	/**
	 * Delete rule meta.
	 *
	 * @since  1.0.0
	 * @param  int    $rule_id  Rule ID.
	 * @param  string $meta_key Meta key.
	 * @return bool             True on success, false on failure.
	 */
	public function delete_meta( $rule_id, $meta_key ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Custom table column, not wp_postmeta.
		$result = $this->wpdb->delete(
			$this->table_rule_meta,
			array(
				'rule_id'  => $rule_id,
				'meta_key' => $meta_key,
			),
			array( '%d', '%s' )
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		if ( ! $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $rule_id );

		return true;
	}

	/**
	 * Hydrate a rule object from database row.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $row Database row.
	 * @return Discount_Tools_Rule Rule object.
	 */
	private function hydrate_rule( $row ) {
		$rule = new Discount_Tools_Rule();

		$rule->set_id( (int) $row['id'] );
		$rule->set_name( $row['name'] );
		$rule->set_description( $row['description'] );
		$rule->set_discount_type( $row['discount_type'] );
		$rule->set_discount_value( (float) $row['discount_value'] );
		$rule->set_rule_type( $row['rule_type'] );
		$rule->set_status( $row['status'] );
		$rule->set_priority( (int) $row['priority'] );
		$rule->set_start_date( $row['start_date'] );
		$rule->set_end_date( $row['end_date'] );
		$rule->set_usage_limit( (int) $row['usage_limit'] );
		$rule->set_usage_count( (int) $row['usage_count'] );
		
		// Load usage_limit_per_user
		if ( isset( $row['usage_limit_per_user'] ) ) {
			$rule->set_usage_limit_per_user( (int) $row['usage_limit_per_user'] );
		}
		
		// Load apply_mode from database (determines if rule is stackable)
		if ( isset( $row['apply_mode'] ) ) {
			$rule->set_apply_mode( $row['apply_mode'] );
		}

		// Load BXGY fields from database
		if ( isset( $row['discount_subtype'] ) ) {
			$rule->set_discount_subtype( $row['discount_subtype'] );
		}
		if ( isset( $row['bxgy_buy_quantity'] ) && ! is_null( $row['bxgy_buy_quantity'] ) ) {
			$rule->set_bxgy_buy_quantity( (int) $row['bxgy_buy_quantity'] );
		}
		if ( isset( $row['bxgy_get_quantity'] ) && ! is_null( $row['bxgy_get_quantity'] ) ) {
			$rule->set_bxgy_get_quantity( (int) $row['bxgy_get_quantity'] );
		}
		if ( isset( $row['bxgy_get_discount'] ) && ! is_null( $row['bxgy_get_discount'] ) ) {
			$rule->set_bxgy_get_discount( (float) $row['bxgy_get_discount'] );
		}
		if ( isset( $row['bxgy_get_type'] ) && ! is_null( $row['bxgy_get_type'] ) ) {
			$rule->set_bxgy_get_type( $row['bxgy_get_type'] );
		}

		// Load conditions
		$conditions = $this->load_conditions( $rule->get_id() );
		$rule->set_conditions( $conditions );
		
		// Load meta
		$meta = $this->get_meta( $rule->get_id() );
		$rule->set_meta( $meta );

		return $rule;
	}

	/**
	 * Load conditions for a rule.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Rule ID.
	 * @return array        Array of Discount_Tools_Condition objects.
	 */
	private function load_conditions( $rule_id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_conditions} WHERE rule_id = %d ORDER BY group_id, id", $rule_id ), ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		$conditions = array();
		foreach ( $rows as $row ) {
			$condition = new Discount_Tools_Condition();
			$condition->set_id( (int) $row['id'] );
			$condition->set_rule_id( (int) $row['rule_id'] );
			$condition->set_condition_type( $row['condition_type'] );
			$condition->set_operator( $row['operator'] );

			$value = json_decode( $row['value'], true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$value = $row['value'];
			}
			$condition->set_value( $value );
			$condition->set_group_id( (int) $row['group_id'] );

			$conditions[] = $condition;
		}

		return $conditions;
	}

	/**
	 * Clear cache.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Optional. Rule ID to clear specific cache.
	 * @return void
	 */
	private function clear_cache( $rule_id = 0 ) {
		if ( $rule_id > 0 ) {
			$this->cache->invalidate_rule( $rule_id );
		} else {
			$this->cache->flush_group( 'rules' );
		}

		// 清除 transient 緩存 (優化後的 find_active_by_type 使用)
		delete_transient( 'dt_active_rules_product' );
		delete_transient( 'dt_active_rules_cart' );
		delete_transient( 'dt_active_rules_bulk' );
		delete_transient( 'dt_active_rules_bogo' );
		delete_transient( 'dt_active_rules_role_based' );
	}
}
