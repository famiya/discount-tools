<?php
/**
 * Product Hooks
 *
 * Integrates with WooCommerce product price hooks.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 */

/**
 * Product hooks class.
 *
 * Applies discount rules to product prices.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Product_Hooks {

	/**
	 * Rule engine instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Rule_Engine
	 */
	private $rule_engine;

	/**
	 * Cache for discounted prices to avoid recalculation.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $price_cache = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->rule_engine = new Discount_Tools_Rule_Engine();
	}

	/**
	 * Register hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_hooks() {
		// Check if plugin is enabled
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-settings.php';
		if ( ! \Discount_Tools\Admin\Settings::get( 'enable_plugin', true ) ) {
			return;
		}

		// Only apply on frontend
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Product price hooks
		add_filter( 'woocommerce_product_get_price', array( $this, 'get_discounted_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'get_regular_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'get_sale_price' ), 10, 2 );

		// Variable product hooks
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'get_discounted_price' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'get_regular_price' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'get_sale_price' ), 10, 2 );

		// Price HTML hooks - Custom display with strikethrough when show_on_product_page enabled
		add_filter( 'woocommerce_get_price_html', array( $this, 'get_price_html' ), 10, 2 );

		// Cart item price hooks
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_discount_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );

		// Display savings on product page
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_product_savings' ), 15 );
	}

	/**
	 * Get discounted price.
	 *
	 * @since  1.0.0
	 * @param  float      $price   Original price.
	 * @param  WC_Product $product Product object.
	 * @return float               Discounted price.
	 */
	public function get_discounted_price( $price, $product ) {
		// Skip if no price
		if ( empty( $price ) || $price <= 0 ) {
			return $price;
		}

		// Skip if in admin (unless AJAX)
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price;
		}

		// Check if discount rules should be disabled due to Coupon Integration settings
		if ( $this->should_disable_discount_rules() ) {
			return $price;
		}

		$product_id = $product->get_id();

		// Check cache
		$cache_key = $this->get_cache_key( $product_id, $price );
		if ( isset( $this->price_cache[ $cache_key ] ) ) {
			return $this->price_cache[ $cache_key ];
		}

		// Build context
		$context = $this->build_product_context( $product );

		// Apply discount
		$discounted_price = $this->rule_engine->apply_product_discount(
			$product_id,
			$price,
			1,
			$context
		);

		// Cache the result
		$this->price_cache[ $cache_key ] = $discounted_price;

		return $discounted_price;
	}

	/**
	 * Get regular price (keep original).
	 *
	 * @since  1.0.0
	 * @param  float      $price   Regular price.
	 * @param  WC_Product $product Product object.
	 * @return float               Regular price.
	 */
	public function get_regular_price( $price, $product ) {
		// Always return original regular price without any discount
		// This ensures the strike-through price shows the correct original price
		return $price;
	}

	/**
	 * Modify product sale price to enable strikethrough display.
	 * Returns discounted price when discount rules apply (if show_on_product_page enabled).
	 * WooCommerce will display: ~~regular_price~~ sale_price
	 *
	 * @param mixed      $price   The product's sale price.
	 * @param WC_Product $product Product object.
	 * @return mixed Modified sale price (discounted price or empty).
	 */
	public function get_sale_price( $price, $product ) {
		// If product has native WooCommerce sale price, keep it
		if ( ! empty( $price ) && $price > 0 ) {
			return $price;
		}
		
		// Check if we should display discount on product page
		$product_id = $product->get_id();
		$context = $this->build_product_context( $product );
		$rules = $this->rule_engine->get_applicable_rules( 'product', $product_id, $context );
		
		// Check if any rule has show_on_product_page enabled
		$show_discount = false;
		if ( ! empty( $rules ) ) {
			foreach ( $rules as $rule ) {
				$display_setting = $rule->get_meta_value( 'display_show_on_product_page', '1' );
				if ( filter_var( $display_setting, FILTER_VALIDATE_BOOLEAN ) ) {
					$show_discount = true;
					break;
				}
			}
		}
		
		if ( ! $show_discount ) {
			return ''; // No discount to display
		}
		
		// Get original price from metadata
		if ( $product->is_type( 'variation' ) ) {
			$original_price = get_post_meta( $product_id, '_price', true );
		} else {
			$original_price = get_post_meta( $product_id, '_price', true );
		}
		
		if ( empty( $original_price ) || $original_price <= 0 ) {
			return '';
		}
		
		// Calculate discount using rule engine
		$context = $this->build_product_context( $product );
		$discount_info = $this->rule_engine->calculate_product_discount(
			$product_id,
			$original_price,
			1,
			$context
		);
		
		// If there's a discount, return the discounted price
		if ( ! empty( $discount_info ) && $discount_info['total_discount'] > 0 ) {
			$discounted_price = $original_price - $discount_info['total_discount'];
			return $discounted_price;
		}
		
		return ''; // No discount, let WooCommerce handle normally
	}	/**
	 * Modify price HTML to show discount.
	 *
	 * @since  1.0.0
	 * @param  string     $price_html Price HTML.
	 * @param  WC_Product $product    Product object.
	 * @return string                 Modified price HTML.
	 */
	public function get_price_html( $price_html, $product ) {
		// Skip in admin
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price_html;
		}

		$product_id = $product->get_id();
		
		// Get original price from metadata to avoid recursive calls
		if ( $product->is_type( 'variation' ) ) {
			$original_price = get_post_meta( $product_id, '_price', true );
		} else {
			$original_price = get_post_meta( $product_id, '_price', true );
		}

		if ( empty( $original_price ) || $original_price <= 0 ) {
			return $price_html;
		}

		// Get applicable rules
		$context = $this->build_product_context( $product );
		$rules = $this->rule_engine->get_applicable_rules( 'product', $product_id, $context );
		
		// Check if any rule has show_on_product_page enabled
		$show_discount = false;
		if ( ! empty( $rules ) ) {
			foreach ( $rules as $rule ) {
				$display_setting = $rule->get_meta_value( 'display_show_on_product_page', '1' );
				if ( filter_var( $display_setting, FILTER_VALIDATE_BOOLEAN ) ) {
					$show_discount = true;
					break;
				}
			}
		}
		
		// If show_on_product_page is disabled, return original price HTML
		if ( ! $show_discount ) {
			return $price_html;
		}

		// Calculate discount
		$discount_info = $this->rule_engine->calculate_product_discount(
			$product_id,
			$original_price,
			1,
			$context
		);

		// If no discount, return original price HTML
		if ( empty( $discount_info ) || $discount_info['total_discount'] <= 0 ) {
			return $price_html;
		}

		// Build discount HTML with strikethrough
		$discounted_price = $original_price - $discount_info['total_discount'];

		$html = '<del>' . wc_price( $original_price ) . '</del> ';
		$html .= '<ins>' . wc_price( $discounted_price ) . '</ins>';

		return $html;
	}

	/**
	 * Add discount data to cart item.
	 *
	 * @since  1.0.0
	 * @param  array $cart_item_data Cart item data.
	 * @param  int   $product_id     Product ID.
	 * @param  int   $variation_id   Variation ID.
	 * @return array                 Modified cart item data.
	 */
	public function add_cart_item_discount_data( $cart_item_data, $product_id, $variation_id ) {
		$id = $variation_id ? $variation_id : $product_id;
		$product = wc_get_product( $id );

		if ( ! $product ) {
			return $cart_item_data;
		}

		$price = $product->get_price();
		$context = $this->build_product_context( $product );

		// Calculate discount
		$discount_info = $this->rule_engine->calculate_product_discount(
			$id,
			$price,
			1,
			$context
		);

		if ( $discount_info['total_discount'] > 0 ) {
			$cart_item_data['discount_tools_applied'] = true;
			$cart_item_data['discount_tools_original_price'] = $discount_info['original_price'];
			$cart_item_data['discount_tools_discount'] = $discount_info['total_discount'];
			$cart_item_data['discount_tools_rules'] = $discount_info['rules_applied'];
		}

		return $cart_item_data;
	}

	/**
	 * Get cart item from session.
	 *
	 * @since  1.0.0
	 * @param  array $cart_item Session data.
	 * @param  array $values    Cart item values.
	 * @return array            Cart item.
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( isset( $values['discount_tools_applied'] ) ) {
			$cart_item['discount_tools_applied'] = $values['discount_tools_applied'];
			$cart_item['discount_tools_original_price'] = $values['discount_tools_original_price'];
			$cart_item['discount_tools_discount'] = $values['discount_tools_discount'];
			$cart_item['discount_tools_rules'] = $values['discount_tools_rules'];
		}

		return $cart_item;
	}

	/**
	 * Display product savings on single product page.
	 * 
	 * NOTE: This method no longer outputs "You save" or "Applied Discounts" sections.
	 * Discount display is now handled by:
	 * 1. WooCommerce native price display (strikethrough + sale price) via get_price/get_sale_price hooks
	 * 2. Pink "SAVE HKD XX" badge via class-display.php display_savings_badge()
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function display_product_savings( $product ) {
		// This method intentionally left empty.
		// Discount information is now displayed through:
		// - WooCommerce's native price HTML (shows strikethrough original + sale price)
		// - Savings badge (handled by Display class)
		// Only display if rules have show_on_product_page enabled (checked by Display class)
		return;
	}

	/**
	 * Build product context for evaluation.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  WC_Product $product Product object.
	 * @return array               Context array.
	 */
	private function build_product_context( $product ) {
		$context = array(
			'product'    => $product,
			'product_id' => $product->get_id(),
		);

		// Add cart context if available
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$context['cart_total'] = WC()->cart->get_subtotal();
			$context['cart_quantity'] = WC()->cart->get_cart_contents_count();
		}

		// Add user context
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$context['user'] = $user;
			$context['user_logged_in'] = true;
			$context['user_role'] = ! empty( $user->roles ) ? $user->roles[0] : 'customer';
			$context['user_email'] = $user->user_email;
		} else {
			$context['user_logged_in'] = false;
			$context['user_role'] = 'guest';
		}

		// Add shipping context if available
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$context['shipping_state'] = WC()->customer->get_shipping_state();
			$context['shipping_city'] = WC()->customer->get_shipping_city();
			$context['shipping_postcode'] = WC()->customer->get_shipping_postcode();
		}

		// Add date/time context
		$context['current_time'] = current_time( 'mysql' );
		$context['day_of_week'] = intval( current_time( 'w' ) );

		// Allow filtering
		return apply_filters( 'discount_tools_product_context', $context, $product );
	}

	/**
	 * Get cache key for price.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int   $product_id Product ID.
	 * @param  float $price      Price.
	 * @return string            Cache key.
	 */
	private function get_cache_key( $product_id, $price ) {
		$user_id = get_current_user_id();
		return sprintf( 'product_%d_price_%s_user_%d', $product_id, $price, $user_id );
	}

	/**
	 * Clear price cache.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function clear_price_cache() {
		$this->price_cache = array();
	}

	/**
	 * Get discount info for a product.
	 *
	 * Public method for getting discount details.
	 *
	 * @since  1.0.0
	 * @param  int $product_id Product ID.
	 * @return array           Discount info.
	 */
	public function get_product_discount_info( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'has_discount' => false,
				'original_price' => 0,
				'discounted_price' => 0,
				'total_discount' => 0,
				'discount_percent' => 0,
				'rules_applied' => array(),
			);
		}

		$price = $product->get_price();
		$context = $this->build_product_context( $product );

		$discount_info = $this->rule_engine->calculate_product_discount(
			$product_id,
			$price,
			1,
			$context
		);

		$discount_info['has_discount'] = $discount_info['total_discount'] > 0;

		return $discount_info;
	}

	/**
	 * Check if discount rules should be disabled based on Coupon Integration settings.
	 *
	 * @since  1.1.0
	 * @return bool True if rules should be disabled, false otherwise.
	 */
	private function should_disable_discount_rules() {
		// Get settings
		$settings = get_option( 'discount_tools_settings', array() );
		$coupon_mode = isset( $settings['coupon_interaction_mode'] ) ? $settings['coupon_interaction_mode'] : 'both_active';

		// If mode is 'coupons_only', disable all discount rules completely
		if ( 'coupons_only' === $coupon_mode ) {
			return true;
		}

		return false;
	}
}
