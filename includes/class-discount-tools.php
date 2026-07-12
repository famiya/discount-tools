<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Discount_Tools_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * The coupon integration instance.
	 *
	 * @since  1.1.0
	 * @access protected
	 * @var    \Discount_Tools\Engine\Coupon_Integration $coupon_integration The coupon integration instance.
	 */
	protected $coupon_integration;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( defined( 'DISCOUNT_TOOLS_VERSION' ) ) {
			$this->version = DISCOUNT_TOOLS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'discount-tools';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Discount_Tools_Loader. Orchestrates the hooks of the plugin.
	 * - Discount_Tools_i18n. Defines internationalization functionality.
	 * - Discount_Tools_Admin. Defines all hooks for the admin area.
	 * - Discount_Tools_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/class-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/class-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-admin.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-menu.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-settings.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/class-public.php';

		/**
		 * Load model classes.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/models/class-rule.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/models/class-discount.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/models/class-condition.php';

		/**
		 * Load repository classes.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/interface-repository.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-cache-manager.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-rule-repository.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-condition-repository.php';

		/**
		 * Load engine classes.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/engine/class-calculator.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/engine/class-condition-evaluator.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/engine/class-validator.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/engine/class-priority-manager.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/engine/class-rule-engine.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/engine/class-coupon-integration.php';

		/**
		 * Load WooCommerce integration classes.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/class-product-hooks.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/class-cart-hooks.php';
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/class-order-hooks.php';

		/**
		 * Load frontend display classes.
		 */
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/class-display.php';

		$this->loader = new Discount_Tools_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Discount_Tools_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function set_locale() {
		$plugin_i18n = new Discount_Tools_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
		
		// Check for database upgrades.
		$this->loader->add_action( 'plugins_loaded', $this, 'check_database_upgrade' );
	}

	/**
	 * Check and perform database upgrades if needed.
	 *
	 * @since 1.0.0
	 */
	public function check_database_upgrade() {
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/database/schema.php';

		if ( Discount_Tools_Schema::needs_upgrade() ) {
			Discount_Tools_Schema::upgrade();
		}
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Discount_Tools_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_dt_save_rule', $plugin_admin, 'ajax_save_rule' );
		$this->loader->add_action( 'wp_ajax_dt_delete_rule', $plugin_admin, 'ajax_delete_rule' );
		$this->loader->add_action( 'wp_ajax_dt_toggle_rule_status', $plugin_admin, 'ajax_toggle_rule_status' );

		// Register admin menu
		$menu = new Discount_Tools_Menu();
		$this->loader->add_action( 'admin_menu', $menu, 'register_menu' );

		// Register settings page (using singleton pattern)
		$settings = \Discount_Tools\Admin\Settings::get_instance();
		// Settings hooks are registered in the Settings class constructor
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_public_hooks() {
		$plugin_public = new Discount_Tools_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Register WooCommerce hooks after WooCommerce is fully loaded
		$this->loader->add_action( 'woocommerce_init', $this, 'init_woocommerce_hooks' );
	}

	/**
	 * Initialize WooCommerce hooks after WooCommerce is loaded.
	 *
	 * @since 1.0.5
	 */
	public function init_woocommerce_hooks() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$is_frontend_context = ! is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		// Frontend-heavy hooks are unnecessary for wp-admin product save/update requests.
		if ( $is_frontend_context ) {
			$product_hooks = new Discount_Tools_Product_Hooks();
			$product_hooks->register_hooks();

			$cart_hooks = new Discount_Tools_Cart_Hooks();
			$cart_hooks->register_hooks();

			// Register frontend display hooks
			$display = new \Discount_Tools\Frontend\Display();
			$display->register_hooks();

			// Initialize Coupon Integration (v1.1.0+)
			$this->coupon_integration = new \Discount_Tools\Engine\Coupon_Integration();
		}

		$order_hooks = new Discount_Tools_Order_Hooks();
		$order_hooks->register_hooks();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since  1.0.0
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since  1.0.0
	 * @return Discount_Tools_Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since  1.0.0
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Retrieve the coupon integration instance.
	 *
	 * @since  1.1.0
	 * @return \Discount_Tools\Engine\Coupon_Integration|null The coupon integration instance.
	 */
	public function get_coupon_integration() {
		return $this->coupon_integration;
	}
}
