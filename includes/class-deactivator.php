<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clears caches and performs cleanup, but preserves data.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear any cached discount data.
		self::clear_discount_cache();

		// Clear object cache.
		wp_cache_flush();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Note: We don't delete data on deactivation.
		// Data will only be deleted if user explicitly uninstalls the plugin.
	}

	/**
	 * Clear discount-related transients.
	 *
	 * @since 1.0.0
	 */
	private static function clear_discount_cache() {
		global $wpdb;

		// Delete all discount tools transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup of plugin transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_discount_tools_%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup of plugin transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_discount_tools_%'
			)
		);
	}
}
