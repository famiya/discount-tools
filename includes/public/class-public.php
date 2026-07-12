<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for public-facing functionality.
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Public {

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
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * 判斷目前頁面是否需要載入前端資源。
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function should_enqueue_public_assets() {
		if ( is_admin() ) {
			return false;
		}

		if ( is_product() || is_cart() || is_checkout() ) {
			return true;
		}

		$settings = get_option( 'discount_tools_settings', array() );

		if ( ! empty( $settings['show_discount_table'] ) && is_singular( 'product' ) ) {
			return true;
		}

		if ( ! empty( $settings['show_cart_discount'] ) && is_cart() ) {
			return true;
		}

		if ( ! empty( $settings['show_checkout_savings'] ) && is_checkout() ) {
			return true;
		}

		return false;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		if ( ! $this->should_enqueue_public_assets() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			$this->plugin_name,
			DISCOUNT_TOOLS_PLUGIN_URL . 'assets/css/public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_enqueue_public_assets() ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			DISCOUNT_TOOLS_PLUGIN_URL . 'assets/js/public.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		// Localize script with public data.
		wp_localize_script(
			$this->plugin_name,
			'discountToolsPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'discount_tools_public_nonce' ),
			)
		);
	}

	/**
	 * Display discount table on product page.
	 *
	 * This will be fully implemented in Task 22.
	 *
	 * @since 1.0.0
	 */
	public function display_discount_table() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$settings = get_option( 'discount_tools_settings', array() );

		if ( empty( $settings['show_discount_table'] ) ) {
			return;
		}

		// Placeholder for discount table display.
		// Full implementation will be in Task 22.
		echo '<div class="discount-tools-table-placeholder">';
		echo '<!-- Discount table will be implemented in Task 22 -->';
		echo '</div>';
	}

	/**
	 * Display cart discount information.
	 *
	 * This will be fully implemented in Task 23.
	 *
	 * @since 1.0.0
	 */
	public function display_cart_discount() {
		$settings = get_option( 'discount_tools_settings', array() );

		if ( empty( $settings['show_cart_discount'] ) ) {
			return;
		}

		// Placeholder for cart discount display.
		// Full implementation will be in Task 23.
		echo '<div class="discount-tools-cart-placeholder">';
		echo '<!-- Cart discount display will be implemented in Task 23 -->';
		echo '</div>';
	}

	/**
	 * Display checkout savings summary.
	 *
	 * This will be fully implemented in Task 24.
	 *
	 * @since 1.0.0
	 */
	public function display_checkout_savings() {
		$settings = get_option( 'discount_tools_settings', array() );

		if ( empty( $settings['show_checkout_savings'] ) ) {
			return;
		}

		// Placeholder for checkout savings display.
		// Full implementation will be in Task 24.
		echo '<div class="discount-tools-checkout-placeholder">';
		echo '<!-- Checkout savings will be implemented in Task 24 -->';
		echo '</div>';
	}
}
