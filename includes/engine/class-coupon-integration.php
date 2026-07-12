<?php
/**
 * Coupon Integration Module
 *
 * Manages integration between WooCommerce coupons and discount rules.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.1.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 */

namespace Discount_Tools\Engine;

use Discount_Tools\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coupon Integration class.
 *
 * Tracks active coupons, enforces interaction modes, and validates activation codes.
 *
 * @since      1.1.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Coupon_Integration {

	/**
	 * Session key for tracking active coupons
	 *
	 * @var string
	 */
	const SESSION_KEY = 'dt_active_coupons';

	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @since 1.1.0
	 */
	private function register_hooks() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Intercept coupon retrieval to create virtual coupons for activation codes
		add_filter( 'woocommerce_get_shop_coupon_data', array( $this, 'create_virtual_coupon' ), 10, 2 );

		// Track when coupons are applied
		add_action( 'woocommerce_applied_coupon', array( $this, 'track_applied_coupon' ), 10, 1 );

		// Track when coupons are removed
		add_action( 'woocommerce_removed_coupon', array( $this, 'track_removed_coupon' ), 10, 1 );

		// Disable coupons if mode is "discount_tools_only"
		add_filter( 'woocommerce_coupons_enabled', array( $this, 'maybe_disable_coupons' ), 10, 1 );

		// Validate coupon interaction mode
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon_mode' ), 10, 2 );

		// Force activation codes to be valid (override any filter that may return false)
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'force_valid_activation_code' ), 999, 2 );

	}

	/**
	 * Create virtual coupon for plugin activation codes
	 *
	 * @since 1.1.0
	 * @param false|array $data Coupon data (false if not found).
	 * @param string $code Coupon code.
	 * @return false|array Modified coupon data.
	 */
	public function create_virtual_coupon( $data, $code ) {
		// If coupon already exists, don't override
		if ( false !== $data ) {
			return $data;
		}

		// 如果代碼全是數字，直接返回（不可能是激活碼）
		if ( is_numeric( $code ) ) {
			return $data;
		}

		// Check if this is a plugin activation code
		if ( ! $this->is_plugin_activation_code( $code ) ) {
			return $data; // Not an activation code
		}

		// 創建虛擬優惠券資料
		// 這個優惠券的折扣為 0%，因為我們的插件會處理實際的折扣計算
		return array(
			'discount_type' => 'percent',
			'amount'        => '0',
			'individual_use' => false,
			'product_ids'   => array(),
			'exclude_product_ids' => array(),
			'usage_limit'   => 0, // 0 = 無限制（讓 WC 完全跳過使用次數驗證）
			'usage_limit_per_user' => 0, // 0 = 每用戶無限制
			'limit_usage_to_x_items' => '',
			'usage_count'   => 0,
			'expiry_date'   => '',
			'free_shipping' => false,
			'product_categories' => array(),
			'exclude_product_categories' => array(),
			'exclude_sale_items' => false,
			'minimum_amount' => '',
			'maximum_amount' => '',
			'customer_email' => array(),
		);
	}

	/**
	 * Track applied coupon in session
	 *
	 * @since 1.1.0
	 * @param string $coupon_code Applied coupon code.
	 */
        public function track_applied_coupon( $coupon_code ) {
                // 嘗試初始化 session 如果還沒有
                if ( ! WC()->session && function_exists( 'WC' ) ) {
                        WC()->initialize_session();
                }
                
                if ( ! WC()->session ) {
                        return;
                }

                $active_coupons = WC()->session->get( self::SESSION_KEY, array() );
                
                if ( ! in_array( $coupon_code, $active_coupons, true ) ) {
                        $active_coupons[] = strtoupper( $coupon_code ); // 標準化為大寫
                        WC()->session->set( self::SESSION_KEY, $active_coupons );
                }
        }	/**
	 * Remove coupon from session tracking
	 *
	 * @since 1.1.0
	 * @param string $coupon_code Removed coupon code.
	 */
	public function track_removed_coupon( $coupon_code ) {
		if ( ! WC()->session ) {
			return;
		}

		$active_coupons = WC()->session->get( self::SESSION_KEY, array() );
		$coupon_code    = strtoupper( $coupon_code ); // Normalize
		$key            = array_search( $coupon_code, $active_coupons, true );

		if ( false !== $key ) {
			unset( $active_coupons[ $key ] );
			WC()->session->set( self::SESSION_KEY, array_values( $active_coupons ) );
		}
	}

	/**
	 * Get currently active coupons
	 *
	 * @since 1.1.0
	 * @return array Array of coupon codes.
	 */
	public function get_active_coupons() {
		// Get coupons directly from WooCommerce cart
		if ( WC()->cart && method_exists( WC()->cart, 'get_applied_coupons' ) ) {
			$wc_coupons = WC()->cart->get_applied_coupons();
			// Normalize to uppercase
			$wc_coupons = array_map( 'strtoupper', $wc_coupons );
			return $wc_coupons;
		}
		
		// Fallback to session
		if ( ! WC()->session ) {
			return array();
		}

		$session_coupons = WC()->session->get( self::SESSION_KEY, array() );
		return $session_coupons;
	}

	/**
	 * Check if specific coupon is active
	 *
	 * @since 1.1.0
	 * @param string $coupon_code Coupon code to check.
	 * @return bool
	 */
	public function is_coupon_active( $coupon_code ) {
		$coupon_code = strtoupper( $coupon_code ); // Normalize
		return in_array( $coupon_code, $this->get_active_coupons(), true );
	}

	/**
	 * Maybe disable WooCommerce coupons
	 *
	 * @since 1.1.0
	 * @param bool $enabled Current enabled status.
	 * @return bool Modified enabled status.
	 */
	public function maybe_disable_coupons( $enabled ) {
		// NOTE: We intentionally do NOT globally disable the WooCommerce coupon
		// mechanism even in 'discount_tools_only' mode. This is because plugin
		// activation codes (e.g. TESTPAYME) rely on WC_Cart::apply_coupon() which
		// immediately returns false when wc_coupons_enabled() is false.
		//
		// Rejection of regular WC coupons is handled per-coupon in
		// validate_coupon_mode() via the woocommerce_coupon_is_valid filter.
		return $enabled;
	}

	/**
	 * Validate coupon based on interaction mode
	 *
	 * @since 1.1.0
	 * @param bool      $valid  Current validation status.
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return bool Modified validation status.
	 */
	public function validate_coupon_mode( $valid, $coupon ) {
		if ( ! $valid ) {
			return $valid; // Already invalid
		}

		$mode = Settings::get( 'coupon_interaction_mode', 'both_active' );

		if ( 'discount_tools_only' === $mode ) {
			// Check if this is a plugin-managed activation code
			if ( $this->is_plugin_activation_code( $coupon->get_code() ) ) {
				return true; // Allow plugin codes
			}

			// Reject regular WooCommerce coupons
			wc_add_notice(
				__( 'Coupons are currently disabled. Discount rules are active.', 'discount-tools' ),
				'error'
			);
			return false;
		}

		return $valid;
	}

	/**
	 * Check if code is a plugin activation code
	 *
	 * @since 1.1.0
	 * @param string $code Coupon code.
	 * @return bool
	 */
	private function is_plugin_activation_code( $code ) {
		global $wpdb;

		$code = strtoupper( $code ); // Normalize

		// Get all coupon_activation conditions
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query required.
		$conditions = $wpdb->get_results(
			"SELECT value FROM {$wpdb->prefix}dt_conditions 
			 WHERE condition_type = 'coupon_activation'",
			ARRAY_A
		);

		if ( empty( $conditions ) ) {
			return false;
		}

		// Check if code exists in any condition's coupon_codes array
		foreach ( $conditions as $condition ) {
			$value = json_decode( $condition['value'], true );
			
			if ( isset( $value['coupon_codes'] ) && is_array( $value['coupon_codes'] ) ) {
				$coupon_codes = array_map( 'strtoupper', $value['coupon_codes'] );
				if ( in_array( $code, $coupon_codes, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Force plugin activation codes to always be valid
	 * Runs at priority 999, after all other woocommerce_coupon_is_valid filters.
	 *
	 * @since 1.1.0
	 * @param bool       $valid  Current validation status.
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return bool
	 */
	public function force_valid_activation_code( $valid, $coupon ) {
		if ( is_object( $coupon ) && $this->is_plugin_activation_code( $coupon->get_code() ) ) {
			return true;
		}
		return $valid;
	}

	/**
	 * Check if discount rules should be disabled
	 *
	 * @since 1.1.0
	 * @return bool True if rules should be disabled.
	 */
	public function should_disable_rules() {
		$mode = Settings::get( 'coupon_interaction_mode', 'both_active' );

		// Disable rules if mode is "coupons_only"
		return 'coupons_only' === $mode;
	}
}
