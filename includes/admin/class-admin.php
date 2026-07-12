<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for admin-specific functionality.
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Check whether current request is for a Discount Tools admin page.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_discount_tools_admin_page() {
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, nonce checked later.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return in_array(
			$page,
			array( 'discount-tools', 'discount-tools-settings', 'discount-tools-reports' ),
			true
		);
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Initialize AJAX handler
		$this->init_ajax_handler();
	}

	/**
	 * Initialize AJAX handler.
	 *
	 * @since 1.0.0
	 */
	private function init_ajax_handler() {
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-ajax-handler.php';
		$ajax_handler = new Discount_Tools_Ajax_Handler();
		$ajax_handler->register_actions();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		if ( ! $this->is_discount_tools_admin_page() ) {
			return;
		}

		$css_version = $this->version;
		$css_file    = DISCOUNT_TOOLS_PLUGIN_DIR . 'assets/css/admin.css';
		if ( file_exists( $css_file ) ) {
			$css_version = filemtime( $css_file );
		}

		wp_enqueue_style(
			$this->plugin_name,
			DISCOUNT_TOOLS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$css_version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_discount_tools_admin_page() ) {
			return;
		}

		// Determine version for cache busting
		$admin_js_version = $this->version;
		$admin_js_file    = DISCOUNT_TOOLS_PLUGIN_DIR . 'assets/js/admin.js';
		if ( file_exists( $admin_js_file ) ) {
			$admin_js_version = filemtime( $admin_js_file );
		}

		wp_enqueue_script(
			$this->plugin_name,
			DISCOUNT_TOOLS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$admin_js_version,
			false
		);

		// Localize script for AJAX
		wp_localize_script(
			$this->plugin_name,
			'dtAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'searchNonce'  => wp_create_nonce( 'dt-search-nonce' ),
				'conditionNonce' => wp_create_nonce( 'dt-condition-builder' ),
			)
		);

		// Enqueue condition builder script on rule editor pages.
		// Check if we're on the rule editor page.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$is_rule_editor = isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'discount-tools'
						  && isset( $_GET['action'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['action'] ) ), array( 'add', 'edit' ), true );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		
		if ( $is_rule_editor ) {
			$condition_builder_version = $this->version;
			$condition_builder_file    = DISCOUNT_TOOLS_PLUGIN_DIR . 'admin/js/condition-builder.js';
			if ( file_exists( $condition_builder_file ) ) {
				$condition_builder_version = filemtime( $condition_builder_file );
			}

			// Enqueue Select2 (WooCommerce includes it)
			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'select2' );
			wp_enqueue_script(
				$this->plugin_name . '-condition-builder',
				DISCOUNT_TOOLS_PLUGIN_URL . 'admin/js/condition-builder.js',
				array( 'jquery', 'select2' ),
				$condition_builder_version,
				false
			);

			// Build payment methods list for the condition builder dropdown (enabled gateways only).
			$payment_methods_list = array();
			if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
				foreach ( WC()->payment_gateways()->payment_gateways() as $gw_id => $gateway ) {
					if ( 'yes' !== $gateway->enabled ) {
						continue;
					}
					$payment_methods_list[] = array(
						'id'    => $gw_id,
						'title' => $gateway->get_title(),
					);
				}
			}

			// Localize condition builder script.
			wp_localize_script(
				$this->plugin_name . '-condition-builder',
				'dtConditionBuilder',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'dt-condition-builder' ),
					'paymentMethods' => $payment_methods_list,
					'i18n'           => array(
						'conditionGroup'      => __( 'Condition Group', 'discount-tools' ),
						'removeGroup'         => __( 'Remove Group', 'discount-tools' ),
						'addConditionAnd'     => __( 'Add Condition (AND)', 'discount-tools' ),
						'or'                  => __( 'OR', 'discount-tools' ),
						'minOneCondition'     => __( 'Each group must have at least one condition.', 'discount-tools' ),
						'minOneGroup'         => __( 'You must have at least one condition group.', 'discount-tools' ),
						'enterProductIds'     => __( 'Enter product IDs (e.g., 123, 456, 789)', 'discount-tools' ),
						'productIdsHelp'      => __( 'Comma-separated product IDs', 'discount-tools' ),
						'enterCategoryIds'    => __( 'Enter category IDs (e.g., 10, 20)', 'discount-tools' ),
						'categoryIdsHelp'     => __( 'Comma-separated category IDs', 'discount-tools' ),
						'searchProducts'      => __( 'Search products...', 'discount-tools' ),
						'searchCategories'    => __( 'Search categories...', 'discount-tools' ),
						'searchBrands'        => __( 'Search brands...', 'discount-tools' ),
						'minChars'            => __( 'Please enter 2 or more characters', 'discount-tools' ),
						'searching'           => __( 'Searching...', 'discount-tools' ),
						'noResults'           => __( 'No results found', 'discount-tools' ),
						'cartTotalHelp'       => __( 'Enter the cart subtotal amount', 'discount-tools' ),
						'cartQuantityHelp'    => __( 'Enter the total number of items', 'discount-tools' ),
						'userRoleHelp'        => __( 'Enter role slugs (e.g., customer, subscriber)', 'discount-tools' ),
						'userLoggedInHelp'    => __( 'Enter "yes" or "no"', 'discount-tools' ),						'selectPaymentMethod' => __( '選擇付款方式...', 'discount-tools' ),					),
				)
			);
		}

		// Localize script with admin data.
		wp_localize_script(
			$this->plugin_name,
			'discountToolsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'discount_tools_admin_nonce' ),
				'i18n'    => array(
					'activate'               => __( 'Activate', 'discount-tools' ),
					'deactivate'             => __( 'Deactivate', 'discount-tools' ),
					'active'                 => __( 'Active', 'discount-tools' ),
					'inactive'               => __( 'Inactive', 'discount-tools' ),
					'confirmDelete'          => __( 'Are you sure you want to delete this rule?', 'discount-tools' ),
					'confirmDeleteSingle'    => __( 'Are you sure you want to delete this rule?', 'discount-tools' ),
					/* translators: %d: number of rules to delete */
					'confirmDeleteMultiple'  => __( 'Are you sure you want to delete %d rules?', 'discount-tools' ),
					'saved'                  => __( 'Settings saved successfully.', 'discount-tools' ),
					'error'                  => __( 'An error occurred. Please try again.', 'discount-tools' ),
				),
			)
		);
	}

	/**
	 * Handle AJAX toggle rule status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_toggle_rule_status() {
		// Check nonce and capabilities.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to check_ajax_referer for verification.
		check_ajax_referer( 'toggle_rule_' . absint( isset( $_POST['rule_id'] ) ? wp_unslash( $_POST['rule_id'] ) : 0 ), 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'discount-tools' ) ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( wp_unslash( $_POST['rule_id'] ) ) : 0;
		$action  = isset( $_POST['toggle_action'] ) ? sanitize_text_field( wp_unslash( $_POST['toggle_action'] ) ) : '';

		if ( ! $rule_id || ! in_array( $action, array( 'activate', 'deactivate' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'discount-tools' ) ) );
		}

	// Get repository and rule.
	$repository = new Discount_Tools_Rule_Repository();
	$rule       = $repository->find( $rule_id );

	if ( ! $rule ) {
		wp_send_json_error( array( 'message' => __( 'Rule not found.', 'discount-tools' ) ) );
	}

	// Update status.
	$new_status = $action === 'activate' ? 'active' : 'inactive';
	
	// Update directly in database
	if ( $repository->update( $rule_id, array( 'status' => $new_status ) ) ) {
		$message = $action === 'activate' ?
			__( 'Rule activated successfully.', 'discount-tools' ) :
			__( 'Rule deactivated successfully.', 'discount-tools' );

		wp_send_json_success( array( 'message' => $message ) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to update rule status.', 'discount-tools' ) ) );
	}
}

	/**
	 * Add admin menu items.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		// Main menu.
		add_menu_page(
			__( 'Discount Tools', 'discount-tools' ),
			__( 'Discount Tools', 'discount-tools' ),
			'manage_woocommerce',
			'discount-tools',
			array( $this, 'display_admin_page' ),
			'dashicons-tag',
			56
		);

		// Rules submenu.
		add_submenu_page(
			'discount-tools',
			__( 'Discount Rules', 'discount-tools' ),
			__( 'Rules', 'discount-tools' ),
			'manage_woocommerce',
			'discount-tools',
			array( $this, 'display_admin_page' )
		);

		// Settings submenu.
		add_submenu_page(
			'discount-tools',
			__( 'Settings', 'discount-tools' ),
			__( 'Settings', 'discount-tools' ),
			'manage_woocommerce',
			'discount-tools-settings',
			array( $this, 'display_settings_page' )
		);

		// Reports submenu.
		add_submenu_page(
			'discount-tools',
			__( 'Reports', 'discount-tools' ),
			__( 'Reports', 'discount-tools' ),
			'manage_woocommerce',
			'discount-tools-reports',
			array( $this, 'display_reports_page' )
		);
	}

	/**
	 * Display the main admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_admin_page() {
		// This will be implemented in Task 15.
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Discount Rules', 'discount-tools' ) . '</h1>';
		echo '<p>' . esc_html__( 'Admin interface will be implemented in upcoming tasks.', 'discount-tools' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Display the settings page.
	 *
	 * @since 1.0.0
	 */
	public function display_settings_page() {
		// Settings page is now handled by Settings class.
		// This method is kept for backward compatibility but can be removed.
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Discount Tools Settings', 'discount-tools' ) . '</h1>';
		echo '<p>' . esc_html__( 'Please use the Settings link in the Discount Tools menu.', 'discount-tools' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Display the reports page.
	 *
	 * @since 1.0.0
	 */
	public function display_reports_page() {
		// This will be implemented in Task 21.
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Discount Reports', 'discount-tools' ) . '</h1>';
		echo '<p>' . esc_html__( 'Reports page will be implemented in upcoming tasks.', 'discount-tools' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'discount_tools_settings_group',
			'discount_tools_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input The settings input.
	 * @return array The sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['enabled'] ) ) {
			$sanitized['enabled'] = (bool) $input['enabled'];
		}

		if ( isset( $input['default_priority'] ) ) {
			$sanitized['default_priority'] = absint( $input['default_priority'] );
		}

		if ( isset( $input['cache_duration'] ) ) {
			$sanitized['cache_duration'] = absint( $input['cache_duration'] );
		}

		if ( isset( $input['show_discount_table'] ) ) {
			$sanitized['show_discount_table'] = (bool) $input['show_discount_table'];
		}

		if ( isset( $input['show_cart_discount'] ) ) {
			$sanitized['show_cart_discount'] = (bool) $input['show_cart_discount'];
		}

		if ( isset( $input['show_checkout_savings'] ) ) {
			$sanitized['show_checkout_savings'] = (bool) $input['show_checkout_savings'];
		}

		if ( isset( $input['table_position'] ) ) {
			$allowed_positions = array( 'before_add_to_cart', 'after_add_to_cart', 'after_price' );
			if ( in_array( $input['table_position'], $allowed_positions, true ) ) {
				$sanitized['table_position'] = sanitize_text_field( $input['table_position'] );
			}
		}

		if ( isset( $input['enable_logging'] ) ) {
			$sanitized['enable_logging'] = (bool) $input['enable_logging'];
		}

		if ( isset( $input['debug_mode'] ) ) {
			$sanitized['debug_mode'] = (bool) $input['debug_mode'];
		}

		return $sanitized;
	}
}
