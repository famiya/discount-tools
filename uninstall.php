<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options.
 */
function discount_tools_delete_options() {
	delete_option( 'discount_tools_version' );
	delete_option( 'discount_tools_settings' );
	delete_option( 'discount_tools_db_version' );
}

/**
 * Drop custom database tables.
 */
function discount_tools_drop_tables() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'dt_rules',
		$wpdb->prefix . 'dt_conditions',
		$wpdb->prefix . 'dt_rule_meta',
		$wpdb->prefix . 'dt_usage_log',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional table drop during uninstall.
	}
}

/**
 * Clear all transients and cached data.
 */
function discount_tools_clear_cache() {
	global $wpdb;

	// Delete all transients with our prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_discount_tools_%'
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_timeout_discount_tools_%'
		)
	);

	// Clear object cache.
	wp_cache_flush();
}

// Perform uninstall actions.
discount_tools_delete_options();
discount_tools_drop_tables();
discount_tools_clear_cache();
