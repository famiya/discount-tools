<?php
/**
 * Condition Repository
 *
 * Data access layer for rule conditions.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 */

/**
 * Condition repository class.
 *
 * Handles CRUD operations and queries for rule conditions.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Condition_Repository {

	/**
	 * WordPress database object.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    wpdb
	 */
	private $wpdb;

	/**
	 * Conditions table name.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table_conditions;

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
		$this->wpdb             = $wpdb;
		$this->table_conditions = $wpdb->prefix . 'dt_conditions';
		$this->cache            = Discount_Tools_Cache_Manager::get_instance();
	}

	/**
	 * Find a condition by ID.
	 *
	 * @since  1.0.0
	 * @param  int $id Condition ID.
	 * @return Discount_Tools_Condition|null Condition object or null.
	 */
	public function find( $id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_conditions} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		return $this->hydrate_condition( $row );
	}

	/**
	 * Find conditions by rule ID.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return array        Array of Discount_Tools_Condition objects.
	 */
	public function find_by_rule_id( $rule_id ) {
		// Check cache first
		$cached = $this->cache->get_conditions( $rule_id );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_conditions} WHERE rule_id = %d ORDER BY group_id, id", $rule_id ), ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		$conditions = array();
		foreach ( $rows as $row ) {
			$conditions[] = $this->hydrate_condition( $row );
		}

		// Cache the results
		$this->cache->cache_conditions( $rule_id, $conditions );

		return $conditions;
	}

	/**
	 * Find conditions by type.
	 *
	 * @since  1.0.0
	 * @param  int    $rule_id Rule ID.
	 * @param  string $type    Condition type.
	 * @return array           Array of Discount_Tools_Condition objects.
	 */
	public function find_by_type( $rule_id, $type ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_conditions} WHERE rule_id = %d AND condition_type = %s ORDER BY group_id, id", $rule_id, $type ), ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		$conditions = array();
		foreach ( $rows as $row ) {
			$conditions[] = $this->hydrate_condition( $row );
		}

		return $conditions;
	}

	/**
	 * Find conditions by group ID.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id  Rule ID.
	 * @param  int $group_id Group ID.
	 * @return array         Array of Discount_Tools_Condition objects.
	 */
	public function find_by_group( $rule_id, $group_id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table_conditions} WHERE rule_id = %d AND group_id = %d ORDER BY id", $rule_id, $group_id ), ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		$conditions = array();
		foreach ( $rows as $row ) {
			$conditions[] = $this->hydrate_condition( $row );
		}

		return $conditions;
	}

	/**
	 * Create a new condition.
	 *
	 * @since  1.0.0
	 * @param  array $data {
	 *     Condition data.
	 *
	 *     @type int    $rule_id  Rule ID.
	 *     @type string $type     Condition type.
	 *     @type string $operator Operator.
	 *     @type mixed  $value    Condition value.
	 *     @type int    $group_id Group ID.
	 * }
	 * @return int|false Created condition ID or false on failure.
	 */
	public function create( $data ) {
		// Prepare data for insertion
		$value = $data['value'];
		if ( is_array( $value ) ) {
			$value = json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		
		$insert_data = array(
			'rule_id'        => $data['rule_id'],
			'condition_type' => $data['type'],
			'operator'       => $data['operator'],
			'value'          => $value,
			'group_id'       => isset( $data['group_id'] ) ? $data['group_id'] : 0,
		);

		$format = array(
			'%d', // rule_id
			'%s', // condition_type
			'%s', // operator
			'%s', // value
			'%d', // group_id
		);

		$result = $this->wpdb->insert( $this->table_conditions, $insert_data, $format );

		if ( ! $result ) {
			return false;
		}

		$condition_id = $this->wpdb->insert_id;

		// Clear cache
		$this->clear_cache( $data['rule_id'] );

		return $condition_id;
	}

	/**
	 * Create multiple conditions at once.
	 *
	 * @since  1.0.0
	 * @param  int   $rule_id    Rule ID.
	 * @param  array $conditions Array of condition data arrays.
	 * @return array|false       Array of created condition IDs or false on failure.
	 */
	public function create_batch( $rule_id, $conditions ) {
		$condition_ids = array();

		foreach ( $conditions as $condition_data ) {
			$condition_data['rule_id'] = $rule_id;
			$condition_id = $this->create( $condition_data );

			if ( false === $condition_id ) {
				return false;
			}

			$condition_ids[] = $condition_id;
		}

		return $condition_ids;
	}

	/**
	 * Update a condition.
	 *
	 * @since  1.0.0
	 * @param  int   $id   Condition ID.
	 * @param  array $data Updated data.
	 * @return bool        True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$update_data = array();
		$format = array();

		// Build update data
		$allowed_fields = array(
			'type'     => 'condition_type', // Map 'type' to 'condition_type' column
			'operator' => 'operator',
			'group_id' => 'group_id',
		);

		foreach ( $allowed_fields as $field => $column_name ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $column_name ] = $data[ $field ];
				$format[] = ( $field === 'group_id' ) ? '%d' : '%s';
			}
		}

		// Handle value separately (needs JSON encoding)
		if ( isset( $data['value'] ) ) {
			$update_data['value'] = is_array( $data['value'] ) ? json_encode( $data['value'] ) : $data['value'];
			$format[] = '%s';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table_conditions,
			$update_data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Get rule_id to clear cache
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rule_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT rule_id FROM {$this->table_conditions} WHERE id = %d",
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Clear cache
		$this->clear_cache( $rule_id );

		return true;
	}

	/**
	 * Delete a condition.
	 *
	 * @since  1.0.0
	 * @param  int $id Condition ID.
	 * @return bool    True on success, false on failure.
	 */
	public function delete( $id ) {
		// Get rule_id before deleting
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$rule_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT rule_id FROM {$this->table_conditions} WHERE id = %d",
				$id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = $this->wpdb->delete(
			$this->table_conditions,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $rule_id );

		return true;
	}

	/**
	 * Delete all conditions for a rule.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return bool         True on success, false on failure.
	 */
	public function delete_by_rule_id( $rule_id ) {
		$result = $this->wpdb->delete(
			$this->table_conditions,
			array( 'rule_id' => $rule_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $rule_id );

		return true;
	}

	/**
	 * Delete conditions by group.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id  Rule ID.
	 * @param  int $group_id Group ID.
	 * @return bool          True on success, false on failure.
	 */
	public function delete_by_group( $rule_id, $group_id ) {
		$result = $this->wpdb->delete(
			$this->table_conditions,
			array(
				'rule_id'  => $rule_id,
				'group_id' => $group_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Clear cache
		$this->clear_cache( $rule_id );

		return true;
	}

	/**
	 * Count conditions for a rule.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return int          Number of conditions.
	 */
	public function count_by_rule_id( $rule_id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		return (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_conditions} WHERE rule_id = %d", $rule_id ) );
	}

	/**
	 * Get distinct group IDs for a rule.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return array        Array of group IDs.
	 */
	public function get_group_ids( $rule_id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is set from $wpdb->prefix in constructor.
		$results = $this->wpdb->get_col( $this->wpdb->prepare( "SELECT DISTINCT group_id FROM {$this->table_conditions} WHERE rule_id = %d ORDER BY group_id", $rule_id ) );

		return array_map( 'intval', $results );
	}

	/**
	 * Replace all conditions for a rule.
	 *
	 * Useful for bulk updates where you want to replace all conditions at once.
	 *
	 * @since  1.0.0
	 * @param  int   $rule_id    Rule ID.
	 * @param  array $conditions Array of condition data arrays.
	 * @return array|false       Array of created condition IDs or false on failure.
	 */
	public function replace_all( $rule_id, $conditions ) {
		// Delete existing conditions
		$this->delete_by_rule_id( $rule_id );

		// Create new conditions
		return $this->create_batch( $rule_id, $conditions );
	}

	/**
	 * Hydrate a condition object from database row.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $row Database row.
	 * @return Discount_Tools_Condition Condition object.
	 */
	private function hydrate_condition( $row ) {
		$condition = new Discount_Tools_Condition();

		$condition->set_id( (int) $row['id'] );
		$condition->set_rule_id( (int) $row['rule_id'] );
		$condition->set_type( $row['condition_type'] );
		$condition->set_operator( $row['operator'] );
		
		// Decode JSON value
		$value = json_decode( $row['value'], true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$condition->set_value( $value );
		} else {
			$condition->set_value( $row['value'] );
		}
		
		$condition->set_group_id( (int) $row['group_id'] );

		return $condition;
	}

	/**
	 * Clear cache for a rule's conditions.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Rule ID.
	 * @return void
	 */
	private function clear_cache( $rule_id ) {
		$this->cache->delete_conditions( $rule_id );
	}
}
