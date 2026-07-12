<?php
/**
 * Fired during plugin activation
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables, sets default options, and performs initial setup.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Check WordPress version.
		if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
			wp_die(
				esc_html__( 'Discount Tools requires WordPress version 5.8 or higher.', 'discount-tools' )
			);
		}

		// Check PHP version.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			wp_die(
				esc_html__( 'Discount Tools requires PHP version 7.4 or higher.', 'discount-tools' )
			);
		}

		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_die(
				esc_html__( 'Discount Tools requires WooCommerce to be installed and active.', 'discount-tools' )
			);
		}

		// Load database schema class.
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/database/schema.php';

		// Create database tables.
		Discount_Tools_Schema::create_tables();

		// Store plugin version.
		add_option( 'discount_tools_version', DISCOUNT_TOOLS_VERSION );

		// Set default options.
		self::set_default_options();

		// Set activation timestamp.
		add_option( 'discount_tools_activated_at', current_time( 'mysql' ) );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		$defaults = array(
			'enabled'                => true,
			'default_priority'       => 10,
			'cache_duration'         => 3600,
			'show_discount_table'    => true,
			'show_cart_discount'     => true,
			'show_checkout_savings'  => true,
			'table_position'         => 'before_add_to_cart',
			'enable_logging'         => false,
			'debug_mode'             => false,
		);

		add_option( 'discount_tools_settings', $defaults );
	}
}
