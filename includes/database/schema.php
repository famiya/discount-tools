<?php
/**
 * Database Schema Definition
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/database
 */

/**
 * Database schema class.
 *
 * Defines all database tables and their structure.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/database
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Schema {

	/**
	 * Database version.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
const DB_VERSION = '1.3.0';

	/**
	 * Get the database schema SQL.
	 *
	 * @since  1.0.0
	 * @return array Array of SQL statements for dbDelta.
	 */
	public static function get_schema() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = array();

		// Table 1: Rules (銝餉??”).
		$tables['rules'] = "CREATE TABLE {$wpdb->prefix}dt_rules (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			discount_type varchar(50) NOT NULL,
			discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
			discount_subtype varchar(50) DEFAULT 'simple',
			bxgy_buy_quantity int(11) DEFAULT 0,
			bxgy_get_quantity int(11) DEFAULT 0,
			bxgy_get_discount decimal(10,2) DEFAULT 0.00,
			bxgy_get_type varchar(20) DEFAULT 'percentage',
			rule_type varchar(50) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			priority int(11) NOT NULL DEFAULT 10,
			start_date datetime DEFAULT NULL,
			end_date datetime DEFAULT NULL,
			usage_limit int(11) DEFAULT NULL,
			usage_limit_per_user int(11) DEFAULT NULL,
			usage_count int(11) NOT NULL DEFAULT 0,
			apply_mode varchar(50) DEFAULT 'first',
			date_created datetime NOT NULL,
			date_modified datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY rule_type (rule_type),
			KEY priority (priority),
			KEY discount_subtype (discount_subtype),
			KEY dates (start_date,end_date),
			KEY type_status_priority (rule_type, status, priority)
		) $charset_collate;";

		// Table 2: Conditions (璇辣銵?.
		$tables['conditions'] = "CREATE TABLE {$wpdb->prefix}dt_conditions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_id bigint(20) unsigned NOT NULL,
			condition_type varchar(100) NOT NULL,
			operator varchar(50) NOT NULL,
			value text NOT NULL,
			group_id int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY rule_id (rule_id),
			KEY condition_type (condition_type),
			KEY group_id (group_id),
			KEY rule_condition (rule_id,condition_type)
		) $charset_collate;";

		// Table 3: Rule Meta (閬???”).
		$tables['rule_meta'] = "CREATE TABLE {$wpdb->prefix}dt_rule_meta (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext,
			PRIMARY KEY  (id),
			KEY rule_id (rule_id),
			KEY meta_key (meta_key(191))
		) $charset_collate;";

		// Table 4: Usage Log (雿輻閮?銵?.
		$tables['usage_log'] = "CREATE TABLE {$wpdb->prefix}dt_usage_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			product_id bigint(20) unsigned DEFAULT NULL,
			discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			original_price decimal(10,2) NOT NULL DEFAULT 0.00,
			final_price decimal(10,2) NOT NULL DEFAULT 0.00,
			quantity int(11) NOT NULL DEFAULT 1,
			applied_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY rule_id (rule_id),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY product_id (product_id),
			KEY applied_at (applied_at)
		) $charset_collate;";

		return $tables;
	}

	/**
	 * Create all database tables.
	 *
	 * @since 1.0.0
	 */
	public static function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = self::get_schema();

		foreach ( $tables as $table ) {
			dbDelta( $table );
		}

		// Store database version.
		update_option( 'discount_tools_db_version', self::DB_VERSION );
	}

	/**
	 * Check if database upgrade is needed.
	 *
	 * @since  1.0.0
	 * @return bool True if upgrade needed, false otherwise.
	 */
	public static function needs_upgrade() {
		$current_version = get_option( 'discount_tools_db_version', '0' );
		return version_compare( $current_version, self::DB_VERSION, '<' );
	}

	/**
	 * Upgrade database schema.
	 *
	 * @since 1.0.0
	 */
	public static function upgrade() {
		$current_version = get_option( 'discount_tools_db_version', '0' );

		// Upgrade to 1.1.0 - Add BXGY support.
		if ( version_compare( $current_version, '1.1.0', '<' ) ) {
			self::upgrade_to_1_1_0();
		}

		// Upgrade to 1.2.0 - Add usage_limit_per_user support.
		if ( version_compare( $current_version, '1.2.0', '<' ) ) {
			self::upgrade_to_1_2_0();
		}

		// Upgrade to 1.3.0 - Add composite index for rule_type + status + priority.
		if ( version_compare( $current_version, '1.3.0', '<' ) ) {
			self::upgrade_to_1_3_0();
		}

		// Update to current version.
		update_option( 'discount_tools_db_version', self::DB_VERSION );
	}

	/**
	 * Upgrade to version 1.1.0.
	 *
	 * Adds Buy X Get Y (BXGY) support columns to rules table.
	 *
	 * @since 1.1.0
	 */
	private static function upgrade_to_1_1_0() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dt_rules';

		// Add new columns for BXGY support.
		$columns_to_add = array(
			"ADD COLUMN discount_subtype varchar(50) DEFAULT 'simple' AFTER discount_value",
			"ADD COLUMN bxgy_buy_quantity int(11) DEFAULT 0 AFTER discount_subtype",
			"ADD COLUMN bxgy_get_quantity int(11) DEFAULT 0 AFTER bxgy_buy_quantity",
			"ADD COLUMN bxgy_get_discount decimal(10,2) DEFAULT 0.00 AFTER bxgy_get_quantity",
			"ADD COLUMN bxgy_get_type varchar(20) DEFAULT 'percentage' AFTER bxgy_get_discount",
		);

		foreach ( $columns_to_add as $column_sql ) {
			// Check if column exists before adding.
			$column_name = self::extract_column_name( $column_sql );
			
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema inspection query.
			$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE '{$column_name}'" );

			if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema modification query.
			$wpdb->query( "ALTER TABLE {$table_name} {$column_sql}" );
			}
		}

		// Add index for discount_subtype if not exists.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema inspection query.
		$index_exists = $wpdb->get_results( "SHOW INDEX FROM {$table_name} WHERE Key_name = 'discount_subtype'" );
		
		if ( empty( $index_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema modification query.
			$wpdb->query( "ALTER TABLE {$table_name} ADD INDEX discount_subtype (discount_subtype)" );
		}

		// Add composite index for conditions table.
		$conditions_table = $wpdb->prefix . 'dt_conditions';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema inspection query.
		$condition_index_exists = $wpdb->get_results( "SHOW INDEX FROM {$conditions_table} WHERE Key_name = 'rule_condition'" );
		
		if ( empty( $condition_index_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema modification query.
			$wpdb->query( "ALTER TABLE {$conditions_table} ADD INDEX rule_condition (rule_id,condition_type)" );
		}

		// Initialize default settings for new features.
		$settings = get_option( 'discount_tools_settings', array() );
		
		if ( ! isset( $settings['rule_application_strategy'] ) ) {
			$settings['rule_application_strategy'] = 'priority';
		}
		
		if ( ! isset( $settings['coupon_interaction_mode'] ) ) {
			$settings['coupon_interaction_mode'] = 'both_active';
		}
		
		update_option( 'discount_tools_settings', $settings );

		// Log migration completion.
	}

	/**
	 * Upgrade to version 1.2.0.
	 *
	 * Adds usage_limit_per_user column to rules table.
	 *
	 * @since 1.2.0
	 */
	private static function upgrade_to_1_2_0() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dt_rules';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema inspection query.
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'usage_limit_per_user'" );

		if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema modification query.
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN usage_limit_per_user int(11) DEFAULT NULL AFTER usage_limit" );
		}

	}

	/**
	 * Upgrade to version 1.3.0.
	 *
	 * Adds composite index for rule_type + status + priority optimization.
	 *
	 * @since 1.3.0
	 */
	private static function upgrade_to_1_3_0() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dt_rules';

		// Add composite index for the most common query pattern
		// This optimizes: WHERE rule_type = ? AND status = ? ORDER BY priority DESC
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema inspection query.
		$index_exists = $wpdb->get_results( "SHOW INDEX FROM {$table_name} WHERE Key_name = 'type_status_priority'" );

		if ( empty( $index_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema modification query.
			$wpdb->query( "ALTER TABLE {$table_name} ADD INDEX type_status_priority (rule_type, status, priority)" );
		}

	}

	/**
	 * Extract column name from ALTER TABLE ADD COLUMN statement.
	 *
	 * @since  1.1.0
	 * @param  string $sql ALTER TABLE ADD COLUMN SQL statement.
	 * @return string Column name.
	 */
	private static function extract_column_name( $sql ) {
		// Extract column name from "ADD COLUMN column_name ...".
		preg_match( '/ADD COLUMN (\w+)/', $sql, $matches );
		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * Drop all plugin tables.
	 *
	 * @since 1.0.0
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'dt_rules',
			$wpdb->prefix . 'dt_conditions',
			$wpdb->prefix . 'dt_rule_meta',
			$wpdb->prefix . 'dt_usage_log',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional table drop.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'discount_tools_db_version' );
	}

	/**
	 * Check if all tables exist.
	 *
	 * @since  1.0.0
	 * @return bool True if all tables exist, false otherwise.
	 */
	public static function tables_exist() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'dt_rules',
			$wpdb->prefix . 'dt_conditions',
			$wpdb->prefix . 'dt_rule_meta',
			$wpdb->prefix . 'dt_usage_log',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection query.
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
				return false;
			}
		}

		return true;
	}
}
