<?php
/**
 * Cart Hooks
 *
 * Integrates with WooCommerce cart hooks.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 */

/**
 * Cart hooks class.
 *
 * Applies cart-level discount rules.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Cart_Hooks {

	/**
	 * Rule engine instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Rule_Engine
	 */
	private $rule_engine;

	/**
	 * Applied discounts in current cart session.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $applied_discounts = array();

	/**
	 * Free products to add.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $free_products = array();

	/**
	 * Cart sync guard for removing/rebuilding free products.
	 *
	 * Prevents recursive cart_item_removed / cart_updated hook loops when
	 * free gifts are removed and re-added during cart recalculation.
	 *
	 * @since  1.0.7
	 * @access private
	 * @var    bool
	 */
	private $cart_syncing = false;

	/**
	 * Global stacking check result cache.
	 * 
	 * Stores the result of global stacking validation to avoid recalculating
	 * for each rule type (product rules and cart rules).
	 *
	 * @since  1.0.7
	 * @access private
	 * @var    array|null
	 */
	private $global_stacking_cache = null;

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

		// Cart calculation hooks
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_cart_discounts' ), 10 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_product_discounts_to_cart' ), 5, 1 ); // Apply product rules first
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'add_free_products' ), 10, 1 );

		// Display hooks
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'display_savings_summary' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'display_savings_summary' ) );
		
		// Remove discount hooks (for coupon-activation rules)
		add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'add_remove_link_to_discount' ), 10, 2 );
		add_filter( 'woocommerce_cart_totals_order_total_html', array( $this, 'add_remove_links_to_checkout_fees' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'handle_remove_discount' ), 10 );

		// WooCommerce coupon display hooks - add remove link to coupons
		add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'add_remove_link_to_coupon' ), 10, 3 );
		add_filter( 'woocommerce_coupon_discount_amount_html', array( $this, 'add_remove_link_to_coupon_checkout' ), 10, 2 );
		
		// Add JavaScript to handle custom checkout themes (SF Express, Flatsome, etc.)
		add_action( 'wp_footer', array( $this, 'add_coupon_remove_script_checkout' ), 100 );
		// Add JavaScript for cart page fee rows (SF Express custom cart template)
		add_action( 'wp_footer', array( $this, 'add_coupon_remove_script_cart' ), 100 );

		// AJAX: return current fee-name → coupon-code map (used by checkout JS)
		add_action( 'wp_ajax_dt_get_activation_fee_map', array( $this, 'ajax_get_activation_fee_map' ) );
		add_action( 'wp_ajax_nopriv_dt_get_activation_fee_map', array( $this, 'ajax_get_activation_fee_map' ) );

		// Bundle display hooks
		add_filter( 'woocommerce_cart_item_price', array( $this, 'display_bundle_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'display_bundle_cart_item_subtotal' ), 10, 3 );
		add_filter( 'woocommerce_package_rates', array( $this, 'apply_bundle_free_shipping_rates' ), 20, 2 );

		// BXGY display hooks
		add_filter( 'woocommerce_cart_item_price', array( $this, 'display_bxgy_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'display_bxgy_cart_item_subtotal' ), 10, 3 );

		// Fixed discount display hooks - show original price with strikethrough and discounted price in red
		add_filter( 'woocommerce_cart_item_price', array( $this, 'display_discounted_cart_item_price' ), 20, 3 );

		// AJAX hooks for dynamic updates
		add_action( 'woocommerce_add_to_cart', array( $this, 'clear_discount_cache' ), 10 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'handle_item_removed' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'clear_discount_cache' ), 10 );
		add_action( 'woocommerce_update_cart_action_cart_updated', array( $this, 'handle_cart_updated' ), 10 );
		add_action( 'woocommerce_cart_item_set_quantity', array( $this, 'clear_discount_cache' ), 10 );

		// Order hooks - track rule usage when order is placed
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_rule_usage_on_order' ), 10, 1 );
		add_action( 'woocommerce_payment_complete', array( $this, 'track_rule_usage_on_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'track_rule_usage_on_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'track_rule_usage_on_order' ), 10, 1 );

		// Fix: Clear stale 'coupon applied' notices when a coupon is applied during AJAX
		// (prevents the old 'applied' notice from appearing after the coupon is later removed)
		add_action( 'woocommerce_applied_coupon', array( $this, 'clear_coupon_applied_notice_in_ajax' ), 20 );

		// Fix: Also clear stale notices when a coupon is removed (belt-and-suspenders)
		add_action( 'woocommerce_removed_coupon', array( $this, 'clear_coupon_applied_notice_on_remove' ), 20 );

		// Fix: When any coupon is applied, clear disabled_discounts so coupon-activation rules
		// can fire again (prevents rules from being permanently stuck in the disabled state).
		add_action( 'woocommerce_applied_coupon', array( $this, 'clear_disabled_rules_on_coupon_apply' ), 5 );

		// AJAX: server-side coupon removal (avoids ?remove_coupon URL manipulation)
		add_action( 'wp_ajax_dt_remove_activation_coupon', array( $this, 'ajax_remove_activation_coupon' ) );
		add_action( 'wp_ajax_nopriv_dt_remove_activation_coupon', array( $this, 'ajax_remove_activation_coupon' ) );
	}

	/**
	 * AJAX handler: remove a WC coupon from the cart server-side.
	 *
	 * Used by the JS remove button to avoid ?remove_coupon URL manipulation,
	 * which could accumulate in the address bar and interfere with page refresh.
	 *
	 * @since  1.1.2
	 * @return void
	 */
	public function ajax_remove_activation_coupon() {
		check_ajax_referer( 'dt_remove_activation_coupon', '_wpnonce' );

		$coupon_code = isset( $_POST['coupon_code'] )
			? wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) )
			: '';

		if ( empty( $coupon_code ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
			return;
		}

		WC()->cart->remove_coupon( $coupon_code );
		WC()->cart->calculate_totals();

		wp_send_json_success();
	}

	/**
	 * Clear disabled discount rules when any WC coupon is applied.
	 *
	 * Prevents coupon-activation rules from remaining stuck in the disabled session
	 * list after a user removes and then re-applies the activating coupon.
	 *
	 * @since  1.1.2
	 * @param  string $coupon_code Applied coupon code.
	 * @return void
	 */
	public function clear_disabled_rules_on_coupon_apply( $coupon_code ) {
		if ( ! WC()->session ) {
			return;
		}
		$disabled = WC()->session->get( 'dt_disabled_discounts', array() );
		if ( empty( $disabled ) ) {
			return;
		}
		// Clear all disabled rules so that coupon-activation rules triggered by
		// this coupon (and any other rules) can be re-evaluated cleanly.
		WC()->session->set( 'dt_disabled_discounts', array() );
	}

	/**
	 * Clear the 'Coupon code applied successfully' WC session notice when applied during AJAX.
	 *
	 * When a coupon is applied via AJAX (e.g. SF Express checkout), WooCommerce stores the
	 * 'applied' notice in the session but never displays it (since AJAX doesn't render WC
	 * notices). This stale notice would later appear on the next full page load, causing
	 * 'Coupon applied successfully' to show even AFTER the coupon has been removed.
	 *
	 * @since  1.1.2
	 * @param  string $coupon_code Applied coupon code.
	 * @return void
	 */
	public function clear_coupon_applied_notice_in_ajax( $coupon_code ) {
		if ( ! wp_doing_ajax() ) {
			return;
		}
		$this->remove_wc_coupon_applied_notices();
	}

	/**
	 * Clear stale 'Coupon applied' notices when a coupon is explicitly removed.
	 *
	 * @since  1.1.2
	 * @param  string $coupon_code Removed coupon code.
	 * @return void
	 */
	public function clear_coupon_applied_notice_on_remove( $coupon_code ) {
		$this->remove_wc_coupon_applied_notices();
	}

	/**
	 * Remove 'Coupon code applied successfully' entries from the WC session notice queue.
	 *
	 * @since  1.1.2
	 * @return void
	 */
	private function remove_wc_coupon_applied_notices() {
		if ( ! WC()->session ) {
			return;
		}
		$notices = WC()->session->get( 'wc_notices', array() );
		if ( empty( $notices['success'] ) ) {
			return;
		}
		// WC stores notices as either plain strings or arrays with a 'notice' key.
		// We look for the built-in 'Coupon code applied successfully.' string in any locale.
		$search_strings = array(
			// WooCommerce default (English)
			'Coupon code applied successfully.',
			// Common Chinese Traditional translation
			'折價券使用成功',
			// Chinese Simplified variant
			'优惠券使用成功',
		);
		$changed = false;
		foreach ( $notices['success'] as $key => $notice ) {
			$msg = is_array( $notice ) ? ( isset( $notice['notice'] ) ? $notice['notice'] : '' ) : (string) $notice;
			foreach ( $search_strings as $search ) {
				if ( false !== strpos( $msg, $search ) ) {
					unset( $notices['success'][ $key ] );
					$changed = true;
					break;
				}
			}
		}
		if ( $changed ) {
			$notices['success'] = array_values( $notices['success'] );
			WC()->session->set( 'wc_notices', $notices );
		}
	}

	/**
	 * Apply cart-level discounts.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function apply_cart_discounts() {
		
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		// Check if discount rules should be disabled due to Coupon Integration settings
		if ( $this->should_disable_discount_rules() ) {
			return;
		}

		// Reset applied discounts
		$this->applied_discounts = array();

		// Reset dt_discount_rules so dt_ensure_discount_fees_display() sees a clean state.
		// If this run evaluates to no discount, it stays empty → backup function won't re-add stale fees.
		WC()->session->set( 'dt_discount_rules', array() );

		// Build cart context
		$context = $this->build_cart_context();

		// GLOBAL STACKING CHECK: Get allowed cart rules from global stacking validation
		$stacking_result = $this->check_global_stacking( $context );
		$allowed_cart_rules = $stacking_result['cart_rules'];

		// If no cart rules are allowed by global stacking logic, skip
		if ( empty( $allowed_cart_rules ) ) {
			return;
		}

		// Use the allowed cart rules from global stacking check
		// These rules have already been sorted by priority and filtered for stacking
		$rules = $allowed_cart_rules;
		// Check if any rule allows cart display
		$show_in_cart = false;
		foreach ( $rules as $rule ) {
			$display_setting = $rule->get_meta_value( 'display_show_in_cart', '1' );
			if ( $display_setting === '1' ) {
				$show_in_cart = true;
				break;
			}
		}

		// If all rules have cart display disabled, don't apply discount
		if ( ! $show_in_cart ) {
			return;
		}

		// Get cart items
		$cart_items = $this->get_cart_items_array();

		// Apply cart discount (will re-evaluate conditions with filtered cart)
		$discount_result = $this->rule_engine->apply_cart_discount( $cart_items, $context );


		if ( empty( $discount_result ) || $discount_result['total_discount'] <= 0 ) {
			return;
		}

			// Store applied discounts
		$this->applied_discounts = $discount_result;

		// Get disabled discounts from session
		$disabled_discounts = WC()->session->get( 'dt_disabled_discounts', array() );

		// Add individual fees for each rule - ONLY for percentage and fixed_amount types
		// BXGY, Bundle, etc. are already reflected in product prices, shouldn't show in cart totals
		if ( ! empty( $discount_result['rules_applied'] ) ) {
			foreach ( $discount_result['rules_applied'] as $applied_rule ) {
				$rule_name = isset( $applied_rule['rule_name'] ) ? $applied_rule['rule_name'] : '';
				$rule_id = isset( $applied_rule['rule_id'] ) ? intval( $applied_rule['rule_id'] ) : 0;
				
				// Skip if this discount has been removed by user
				if ( in_array( $rule_id, $disabled_discounts ) ) {
					continue;
				}
				
				// Check discount type
				$discount_type = isset( $applied_rule['type'] ) ? $applied_rule['type'] : 
								( isset( $applied_rule['discount_type'] ) ? $applied_rule['discount_type'] : '' );
				
				// Only add fees for percentage and fixed_amount discounts
				if ( in_array( $discount_type, array( 'percentage', 'fixed_amount' ) ) ) {
					$rule_discount = isset( $applied_rule['discount'] ) ? floatval( $applied_rule['discount'] ) : 0;
					
					if ( $rule_discount > 0 ) {
						$discount_amount = -1 * abs( $rule_discount );
						
				// Check if this rule has coupon_activation condition
				$has_coupon_condition = $this->rule_has_coupon_activation( $rule_id );
				
				WC()->cart->add_fee( $rule_name, $discount_amount, false );

				// Store rule ID with coupon condition flag for remove link display
				$this->store_discount_rule_id( $rule_name, $rule_id, $has_coupon_condition );
					}
				} else {
				}
			}
		}

		// NOTE: BXGY/Bundle hints are tracked by add_free_products() only when actually applied.
		// Do not inject them here from "applicable rules", otherwise banners may show false positives.

		// Merge with any rule hints already stored by price-based promotions
		$existing_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
		if ( ! empty( $existing_discounts['rules_applied'] ) ) {
			if ( empty( $discount_result['rules_applied'] ) ) {
				$discount_result['rules_applied'] = array();
			}

			$rules_by_id = array();
			foreach ( $discount_result['rules_applied'] as $applied_rule ) {
				if ( isset( $applied_rule['rule_id'] ) ) {
					$rules_by_id[ intval( $applied_rule['rule_id'] ) ] = $applied_rule;
				}
			}

			foreach ( $existing_discounts['rules_applied'] as $applied_rule ) {
				if ( ! isset( $applied_rule['rule_id'] ) ) {
					continue;
				}

				$rule_id = intval( $applied_rule['rule_id'] );
				if ( ! isset( $rules_by_id[ $rule_id ] ) ) {
					$rules_by_id[ $rule_id ] = $applied_rule;
				}
			}

			$discount_result['rules_applied'] = array_values( $rules_by_id );
			if ( isset( $existing_discounts['total_discount'] ) && floatval( $existing_discounts['total_discount'] ) > 0 ) {
				$discount_result['total_discount'] += floatval( $existing_discounts['total_discount'] );
			}
		}

		// Store in session for order processing
		WC()->session->set( 'discount_tools_cart_discounts', $discount_result );
	}

	/**
	 * Apply product-level discount rules to cart item prices.
	 * 
	 * This method applies Fixed Price, Percentage, and other product-type discounts
	 * directly to cart item prices before any cart-level discounts are calculated.
	 *
	 * @since  1.0.5
	 * @param  WC_Cart $cart Cart object.
	 * @return void
	 */
	public function apply_product_discounts_to_cart( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( $cart->is_empty() ) {
			return;
		}

		// Prevent infinite loops
		static $processing = false;
		if ( $processing ) {
			return;
		}
		$processing = true;

		// Build base context
		$base_context = $this->build_cart_context();
		$base_context['context_type'] = 'cart';

		// GLOBAL STACKING CHECK: Check all rules (product + cart) for stacking conflicts
		$stacking_result = $this->check_global_stacking( $base_context );
		$allowed_product_rules = $stacking_result['product_rules'];

		// If no product rules are allowed by global stacking logic, skip
		if ( empty( $allowed_product_rules ) ) {
			$processing = false;
			return;
		}

		// Process each cart item
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			// Skip free products from BXGY
			if ( isset( $cart_item['discount_tools_free_product'] ) && $cart_item['discount_tools_free_product'] === true ) {
				continue;
			}

			$product = $cart_item['data'];
			$product_id = $cart_item['product_id'];
			$variation_id = $cart_item['variation_id'];
			$quantity = $cart_item['quantity'];
			
			// Get original price from metadata to bypass WooCommerce product hooks
			// This prevents double-discounting when product hooks already modify get_price()
			$item_id = $variation_id ? $variation_id : $product_id;
			$original_price = get_post_meta( $item_id, '_regular_price', true );
			if ( empty( $original_price ) ) {
				$original_price = get_post_meta( $item_id, '_price', true );
			}
			if ( empty( $original_price ) ) {
				// Fallback to product method if metadata not available
				$original_price = $product->get_regular_price();
			}
			$original_price = floatval( $original_price );


			// Build product-specific context
			$product_context = array_merge( $base_context, array(
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'quantity' => $quantity,
				'price' => $original_price,
				'product' => $product,
			) );

			// Get applicable product rules for this specific product from global stacking check
			// Only consider rules that passed global stacking validation
			$all_rules = array();
			foreach ( $allowed_product_rules as $rule ) {
				// Check if this rule applies to this specific product
				$conditions = $rule->get_conditions();
				$evaluator = new Discount_Tools_Condition_Evaluator();
				
				if ( empty( $conditions ) || $evaluator->evaluate( $conditions, $product_context ) ) {
					$all_rules[] = $rule;
				}
			}

			if ( empty( $all_rules ) ) {
				continue;
			}

			// Sort by priority (should already be sorted, but just in case)
			$priority_manager = new Discount_Tools_Priority_Manager();
			$sorted_rules = $priority_manager->sort_rules( $all_rules );
			
			// No need to filter_applicable_rules here since global stacking already handled it
			// Just use all the rules that passed global check
			$applicable_rules = $sorted_rules;

			if ( empty( $applicable_rules ) ) {
				continue;
			}

			// Note: Global stacking check already determined if these rules can be applied together
			// If there's a non-stackable rule, only that one rule will be in $allowed_product_rules
			// So we don't need to re-check stacking logic here
			$has_non_stackable = $stacking_result['has_non_stackable'];

			// Calculate total discount from applicable rules
			$total_discount_per_item = 0;
			$applied_rule_info = array();

			foreach ( $applicable_rules as $rule ) {
				$discount_type = $rule->get_discount_type();
				$discount_value = floatval( $rule->get_discount_value() );

				$discount_amount = 0;

				switch ( $discount_type ) {
					case 'fixed_amount':
						// Fixed discount per item
						$discount_amount = $discount_value;
						break;

					case 'percentage':
						// Percentage discount
						$discount_amount = ( $original_price * $discount_value ) / 100;
						break;

					case 'fixed':
						// Fixed discount amount per item
						$discount_amount = $discount_value;
						break;

					default:
						continue 2;
				}

				// If there's a non-stackable rule, only use the first rule (highest priority)
				// If all rules are stackable, sum all discounts
				if ( $has_non_stackable ) {
					// Non-stackable mode: use only the first (highest priority) rule
					$total_discount_per_item = $discount_amount;
					$applied_rule_info = array(
						array(
							'rule_id' => $rule->get_id(),
							'rule_name' => $rule->get_name(),
							'discount' => $discount_amount,
						)
					);
					break; // Only apply first rule
				} else {
					// All stackable: sum all discounts
					$total_discount_per_item += $discount_amount;
					$applied_rule_info[] = array(
						'rule_id' => $rule->get_id(),
						'rule_name' => $rule->get_name(),
						'discount' => $discount_amount,
					);
				}
			}

			// Apply the discount
			if ( $total_discount_per_item > 0 ) {
				$new_price = max( 0, $original_price - $total_discount_per_item );
				$product->set_price( $new_price );

				// Store discount info in cart item meta for potential display
				$cart->cart_contents[$cart_item_key]['discount_tools_product_discount'] = $total_discount_per_item;
				$cart->cart_contents[$cart_item_key]['discount_tools_product_rules'] = $applied_rule_info;

			}
		}

		$processing = false;
	}

	/**
	 * Add free products to cart.
	 *
	 * @since  1.0.0
	 * @param  WC_Cart $cart Cart object.
	 * @return void
	 */
	public function add_free_products( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( $cart->is_empty() ) {
			return;
		}

		// Prevent infinite loops
		static $processing = false;
		if ( $processing ) {
			return;
		}
		$processing = true;
		
		// First, normalize prices for all BXGY-added products in cart.
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['discount_tools_free_product'] ) && $cart_item['discount_tools_free_product'] === true ) {
				$exchange_price = isset( $cart_item['discount_tools_exchange_price'] ) ? max( 0, floatval( $cart_item['discount_tools_exchange_price'] ) ) : 0;
				$cart_item['data']->set_price( $exchange_price );
			}
		}

	// Build context
	$context = $this->build_cart_context();

	// Get rules that offer free products
	$all_rules = $this->rule_engine->get_applicable_rules( 'cart', null, $context );

	if ( empty( $all_rules ) ) {
		$processing = false;
		return;
	}

	// Apply priority and stacking logic
	$priority_manager = new Discount_Tools_Priority_Manager();
	$sorted_rules = $priority_manager->sort_rules( $all_rules );
	$rules = $sorted_rules;

	if ( empty( $rules ) ) {
		$processing = false;
		return;
	}

	$free_products = array();
	$non_stackable_applied_groups = array();
	$cart_items_for_rule_filter = null;

	// Reset cart-rule adjusted prices to baseline before recalculating this request.
	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( isset( $cart_item['discount_tools_free_product'] ) && $cart_item['discount_tools_free_product'] === true ) {
			continue;
		}

		if (
			! empty( $cart_item['discount_tools_adjusted_by_cart_rules'] ) &&
			isset( $cart_item['discount_tools_base_price'] )
		) {
			$base_price = floatval( $cart_item['discount_tools_base_price'] );
			$cart->cart_contents[ $cart_item_key ]['data']->set_price( $base_price );
		}

		unset( $cart->cart_contents[ $cart_item_key ]['discount_tools_bundle'] );
		unset( $cart->cart_contents[ $cart_item_key ]['discount_tools_bxgy'] );
		unset( $cart->cart_contents[ $cart_item_key ]['discount_tools_adjusted_by_cart_rules'] );

		$cart->cart_contents[ $cart_item_key ]['discount_tools_base_price'] = floatval(
			$cart->cart_contents[ $cart_item_key ]['data']->get_price()
		);
	}

	// Check for BXGY and Bundle rules that auto-add gift products or modify prices
	foreach ( $rules as $rule ) {
		$discount_type = $rule->get_discount_type();
		$rule_group = in_array( $discount_type, array( 'bxgy_same', 'bxgy_any' ), true ) ? 'bxgy' : $discount_type;

		if ( ! $rule->is_stackable() && ! empty( $non_stackable_applied_groups[ $rule_group ] ) ) {
			continue;
		}

		$rule_applied = false;
		
		// Handle bundle: apply bundle price directly to cart item prices.
		if ( $discount_type === 'bundle' ) {
			$bundle_qty = intval( $rule->get_meta_value( 'bundle_quantity', 2 ) );
			$bundle_price = floatval( $rule->get_meta_value( 'bundle_price', 0 ) );
			$repeating = $rule->get_meta_value( 'bundle_repeating', 1 );
			$repeating = ( $repeating === '1' || $repeating === 1 || $repeating === true );

			if ( $bundle_qty <= 0 || $bundle_price < 0 ) {
				continue;
			}
			
			// Get cart items that match conditions
			if ( $cart_items_for_rule_filter === null ) {
				$cart_items_for_rule_filter = $this->get_cart_items_array();
			}
			$filtered_items = $this->rule_engine->filter_cart_items_for_rule( $cart_items_for_rule_filter, $rule, $context );
			
			// For Bundle rules with product conditions, filter to only qualifying products
			$conditions = $rule->get_conditions();
			$product_condition = null;
			foreach ( $conditions as $condition ) {
				if ( $condition->get_condition_type() === 'product' && $condition->get_operator() === 'in' ) {
					$product_condition = $condition;
					break;
				}
			}
			
			if ( $product_condition ) {
				$qualifying_product_ids = $product_condition->get_value();
				if ( ! is_array( $qualifying_product_ids ) ) {
					$qualifying_product_ids = array( $qualifying_product_ids );
				}
				$qualifying_product_ids = array_map( 'intval', $qualifying_product_ids );
				
				// Filter to only items with qualifying product IDs
				$filtered_items = array_filter( $filtered_items, function( $item ) use ( $qualifying_product_ids ) {
					return in_array( intval( $item['product_id'] ), $qualifying_product_ids );
				} );

				// If product condition includes more than 2 products, each product must have at least 1 quantity in cart.
				if ( count( $qualifying_product_ids ) > 2 ) {
					$qty_by_product = array();
					foreach ( $filtered_items as $item ) {
						$product_id = intval( $item['product_id'] );
						$qty_by_product[ $product_id ] = isset( $qty_by_product[ $product_id ] )
							? $qty_by_product[ $product_id ] + intval( $item['quantity'] )
							: intval( $item['quantity'] );
					}

					$all_required_present = true;
					foreach ( $qualifying_product_ids as $required_product_id ) {
						if ( empty( $qty_by_product[ $required_product_id ] ) ) {
							$all_required_present = false;
							break;
						}
					}

					if ( ! $all_required_present ) {
						continue;
					}
				}
			}

			if ( empty( $filtered_items ) ) {
				continue;
			}
			
			$unit_rows = array();
			$total_qty = 0;
			foreach ( $filtered_items as $item ) {
				$cart_item_key = $item['key'];
				$item_qty = intval( $item['quantity'] );
				if ( $item_qty <= 0 || ! isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ) {
					continue;
				}

				$product_obj = $cart->cart_contents[ $cart_item_key ]['data'];
				$unit_price = floatval( $product_obj->get_price() );
				if ( $unit_price <= 0 ) {
					$unit_price = floatval( $item['price'] );
				}

				for ( $i = 0; $i < $item_qty; $i++ ) {
					$unit_rows[] = array(
						'key' => $cart_item_key,
						'unit_price' => $unit_price,
					);
				}

				$total_qty += $item_qty;
			}

			if ( empty( $unit_rows ) || $total_qty < $bundle_qty ) {
				continue;
			}
			
			// Calculate bundle sets
			$complete_bundles = floor( $total_qty / $bundle_qty );
			$remaining_items = $total_qty % $bundle_qty;
			
			if ( ! $repeating && $complete_bundles > 0 ) {
				$complete_bundles = 1;
				$remaining_items = $total_qty - $bundle_qty;
			}

			if ( $complete_bundles <= 0 ) {
				continue;
			}

			$bundled_units = $complete_bundles * $bundle_qty;

			// Pick higher-priced units first for bundle allocation (best discount outcome).
			usort( $unit_rows, function( $a, $b ) {
				if ( $a['unit_price'] === $b['unit_price'] ) {
					return 0;
				}
				return ( $a['unit_price'] > $b['unit_price'] ) ? -1 : 1;
			} );

			$selected_units = array_slice( $unit_rows, 0, $bundled_units );
			if ( empty( $selected_units ) ) {
				continue;
			}

			$bundled_original_total = 0;
			foreach ( $selected_units as $selected_unit ) {
				$bundled_original_total += $selected_unit['unit_price'];
			}

			$bundle_target_total = $complete_bundles * $bundle_price;
			$bundle_effective_total = min( $bundle_target_total, $bundled_original_total );
			$bundle_discount_total = max( 0, $bundled_original_total - $bundle_effective_total );

			if ( $bundle_discount_total <= 0 ) {
				continue;
			}

			$discount_by_key = array();
			if ( $bundled_original_total > 0 ) {
				foreach ( $selected_units as $selected_unit ) {
					$key = $selected_unit['key'];
					$unit_discount = $bundle_discount_total * ( $selected_unit['unit_price'] / $bundled_original_total );
					$discount_by_key[ $key ] = isset( $discount_by_key[ $key ] )
						? $discount_by_key[ $key ] + $unit_discount
						: $unit_discount;
				}
			}
			
			// Apply distributed bundle discount to qualifying cart lines.
			foreach ( $filtered_items as $item ) {
				$cart_item_key = $item['key'];
				$qty = $item['quantity'];

				if ( ! isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ) {
					continue;
				}

				$product_obj = $cart->cart_contents[ $cart_item_key ]['data'];
				$original_unit_price = floatval( $product_obj->get_price() );
				if ( $original_unit_price <= 0 ) {
					$original_unit_price = floatval( $item['price'] );
				}

				$line_original_total = $original_unit_price * $qty;
				$line_discount = isset( $discount_by_key[ $cart_item_key ] ) ? $discount_by_key[ $cart_item_key ] : 0;
				$line_new_total = max( 0, $line_original_total - $line_discount );
				$new_unit_price = $qty > 0 ? ( $line_new_total / $qty ) : $original_unit_price;

				$cart->cart_contents[ $cart_item_key ]['data']->set_price( $new_unit_price );
				$cart->cart_contents[ $cart_item_key ]['discount_tools_bundle'] = array(
					'rule_id' => $rule->get_id(),
					'rule_name' => $rule->get_name(),
					'bundle_qty' => $bundle_qty,
					'bundle_price' => $bundle_price,
					'complete_bundles' => $complete_bundles,
					'remaining_items' => $remaining_items,
					'total_qty' => $total_qty,
					'original_price' => $original_unit_price,
					'discounted_price' => $new_unit_price,
					'repeating' => $repeating,
				);
				$cart->cart_contents[ $cart_item_key ]['discount_tools_adjusted_by_cart_rules'] = true;
			}

			$session_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
			if ( empty( $session_discounts ) ) {
				$session_discounts = array(
					'total_discount' => 0,
					'rules_applied'  => array(),
				);
			}

			$already_in_session = false;
			if ( ! empty( $session_discounts['rules_applied'] ) ) {
				foreach ( $session_discounts['rules_applied'] as $applied_rule ) {
					if ( isset( $applied_rule['rule_id'] ) && intval( $applied_rule['rule_id'] ) === $rule->get_id() ) {
						$already_in_session = true;
						break;
					}
				}
			}

			if ( ! $already_in_session ) {
				$session_discounts['rules_applied'][] = array(
					'rule_id'       => $rule->get_id(),
					'rule_name'     => $rule->get_name(),
					'discount_type' => 'bundle',
					'discount'      => $bundle_discount_total,
					'bundle_info'   => array(
						'bundle_qty'         => $bundle_qty,
						'bundle_price'       => $bundle_price,
						'complete_bundles'   => $complete_bundles,
						'remaining_items'    => $remaining_items,
						'total_qty'          => $total_qty,
						'original_total'     => $bundled_original_total,
						'bundled_units'      => $bundled_units,
					),
				);
				WC()->session->set( 'discount_tools_cart_discounts', $session_discounts );
			}

			$rule_applied = true;
		}
		
		// Handle bxgy_same: Store BXGY info for later display, don't modify price here
		if ( $discount_type === 'bxgy_same' ) {
			$buy_qty = intval( $rule->get_meta_value( 'bxgy_buy_quantity', 2 ) );
			$get_qty = intval( $rule->get_meta_value( 'bxgy_get_quantity', 1 ) );
			$repeating = $rule->get_meta_value( 'bxgy_repeating', 1 );
			$repeating = ( $repeating === '1' || $repeating === 1 || $repeating === true );
			
			// Get cart items that match conditions
			if ( $cart_items_for_rule_filter === null ) {
				$cart_items_for_rule_filter = $this->get_cart_items_array();
			}
			$filtered_items = $this->rule_engine->filter_cart_items_for_rule( $cart_items_for_rule_filter, $rule, $context );
			
			// For BXGY rules with product conditions, filter to only qualifying products
			$conditions = $rule->get_conditions();
			$product_condition = null;
			foreach ( $conditions as $condition ) {
				if ( $condition->get_condition_type() === 'product' && $condition->get_operator() === 'in' ) {
					$product_condition = $condition;
					break;
				}
			}
			
			if ( $product_condition ) {
				$qualifying_product_ids = $product_condition->get_value();
				if ( ! is_array( $qualifying_product_ids ) ) {
					$qualifying_product_ids = array( $qualifying_product_ids );
				}
				$qualifying_product_ids = array_map( 'intval', $qualifying_product_ids );
				
				// Filter to only items with qualifying product IDs
				$filtered_items = array_filter( $filtered_items, function( $item ) use ( $qualifying_product_ids ) {
					return in_array( intval( $item['product_id'] ), $qualifying_product_ids );
				} );
			}
			
			// For each qualifying product in cart, store BXGY discount info
			foreach ( $filtered_items as $item ) {
				$cart_item_key = $item['key'];
				$qty = $item['quantity'];
				
				// Get the original price before any discounts
				if ( isset( $cart->cart_contents[$cart_item_key]['data'] ) ) {
					$product = $cart->cart_contents[$cart_item_key]['data'];
					$original_price = floatval( $product->get_regular_price() );
					if ( $original_price === 0.0 ) {
						// Fallback to current price if regular price is 0
						$original_price = floatval( $product->get_price() );
					}
				} else {
					// Fallback to item price if data not found
					$original_price = $item['price'];
				}
				
				$free_qty = 0;
				if ( $repeating ) {
					// Repeating: floor(qty / buy_qty) * get_qty
					// Example: Buy 1 Get 1, qty=3 -> floor(3/1) * 1 = 3 free (wrong!)
					// Correct: floor(qty / (buy_qty + get_qty)) * get_qty
					$set_size = $buy_qty + $get_qty;
					$complete_sets = floor( $qty / $set_size );
					$free_qty = $complete_sets * $get_qty;
				} else {
					// One-time: if qty >= buy_qty, get_qty items are free (limited to get_qty)
					if ( $qty >= $buy_qty ) {
						$free_qty = min( $get_qty, $qty - $buy_qty );
					}
				}
				
					if ( $free_qty > 0 ) {
					// Calculate the effective price per item
					$paid_qty = $qty - $free_qty;
					$new_price = ( $paid_qty > 0 ) ? ( $original_price * $paid_qty ) / $qty : 0;

					// Store BXGY discount info using cart_contents reference
					if ( isset( $cart->cart_contents[ $cart_item_key ] ) ) {
						// Store discount info for display
						$cart->cart_contents[ $cart_item_key ]['discount_tools_bxgy'] = array(
							'rule_id' => $rule->get_id(),
							'rule_name' => $rule->get_name(),
							'buy_qty' => $buy_qty,
							'get_qty' => $get_qty,
							'free_qty' => $free_qty,
							'original_price' => $original_price,
							'repeating' => $repeating,
							'paid_qty' => $paid_qty,
						);
						
						// Set the new price for calculation
						$cart->cart_contents[ $cart_item_key ]['data']->set_price( $new_price );
						$cart->cart_contents[ $cart_item_key ]['discount_tools_adjusted_by_cart_rules'] = true;
						$rule_applied = true;
						
					}
				}
			}
		}
		
		// Handle bxgy_any type with gift products
		if ( $discount_type === 'bxgy_any' ) {
			$gift_products = $rule->get_meta_value( 'bxgy_gift_products', array() );
			$exchange_price = max( 0, floatval( $rule->get_meta_value( 'bxgy_exchange_price', 0 ) ) );
			
			// If gift products are specified, apply either auto-gift or manual exchange logic.
			if ( ! empty( $gift_products ) && is_array( $gift_products ) ) {
				$buy_qty = intval( $rule->get_meta_value( 'bxgy_buy_quantity', 2 ) );
				$get_qty = intval( $rule->get_meta_value( 'bxgy_get_quantity', 1 ) );
				$repeating = $rule->get_meta_value( 'bxgy_repeating', 1 );
				$repeating = ( $repeating === '1' || $repeating === 1 || $repeating === true );
				
			// Get cart items that match conditions
			if ( $cart_items_for_rule_filter === null ) {
				$cart_items_for_rule_filter = $this->get_cart_items_array();
			}
			$filtered_items = $this->rule_engine->filter_cart_items_for_rule( $cart_items_for_rule_filter, $rule, $context );
			
			// For BXGY rules, separate cart-level and item-level conditions
			$conditions = $rule->get_conditions();
			$item_conditions = array();
			$cart_conditions = array();
			
			foreach ( $conditions as $condition ) {
				$type = $condition->get_condition_type();
				// Item-level conditions: product, brand, category
				if ( in_array( $type, array( 'product', 'brand', 'product_category' ) ) ) {
					$item_conditions[] = $condition;
				} else {
					// Cart-level conditions: cart_total, user_role, etc.
					$cart_conditions[] = $condition;
				}
			}
			
			// Cart-level conditions already evaluated by get_applicable_rules()
			// We still need to re-check cart-level thresholds against qualifying items only.
			// Otherwise excluded categories (e.g. product_category not_in) may be counted in cart_total.
			// First evaluate item-level conditions if they exist.
			$qualifying_items = array();
			
			if ( empty( $item_conditions ) ) {
				// No item-level conditions, all filtered items qualify
				$qualifying_items = $filtered_items;
			} else {
				// Build list of qualifying items by evaluating item-level conditions
				foreach ( $filtered_items as $item ) {
					// Create item context for condition evaluation
					$item_context = $context;
					$item_context['product_id'] = $item['product_id'];
					
					// Get product to check brand/category
					$product = wc_get_product( $item['product_id'] );
					if ( $product ) {
						// Get brand - use 'cart_brands' key for evaluator
						$brand_terms = wp_get_post_terms( $item['product_id'], 'product_brand', array( 'fields' => 'ids' ) );
						if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
							$item_context['cart_brands'] = $brand_terms;
						}
						
						// Get categories - use 'cart_categories' key for evaluator
						$category_ids = $product->get_category_ids();
						if ( ! empty( $category_ids ) ) {
							$item_context['cart_categories'] = $category_ids;
						}
						
						// Evaluate only item-level conditions for this item
						if ( $this->rule_engine->get_evaluator()->evaluate( $item_conditions, $item_context ) ) {
							$qualifying_items[] = $item;
						}
					}
				}
			}

			// Re-evaluate cart-level conditions with totals from qualifying items only.
			if ( ! empty( $cart_conditions ) ) {
				$qualified_total = 0;
				$qualified_qty = 0;

				foreach ( $qualifying_items as $qualified_item ) {
					if ( isset( $qualified_item['discount_tools_free_product'] ) && $qualified_item['discount_tools_free_product'] === true ) {
						continue;
					}

					$item_qty = isset( $qualified_item['quantity'] ) ? intval( $qualified_item['quantity'] ) : 0;
					$item_price = isset( $qualified_item['price'] ) ? floatval( $qualified_item['price'] ) : 0;

					if ( $item_qty <= 0 || $item_price <= 0 ) {
						continue;
					}

					$qualified_qty += $item_qty;
					$qualified_total += $item_price * $item_qty;
				}

				$qualified_context = $context;
				$qualified_context['cart_total'] = $qualified_total;
				$qualified_context['cart_quantity'] = $qualified_qty;

				if ( ! $this->rule_engine->get_evaluator()->evaluate( $cart_conditions, $qualified_context ) ) {
					continue;
				}
			}
			
			// Calculate total qualifying quantity (exclude free products)
			$total_qty = 0;
			foreach ( $qualifying_items as $item ) {
				// Don't count items that are already free gifts
				if ( ! isset( $item['discount_tools_free_product'] ) || ! $item['discount_tools_free_product'] ) {
					$total_qty += $item['quantity'];
				}
			}
				
				
				// Calculate how many free items customer qualifies for
				$free_quantity = 0;
				
				if ( $repeating ) {
					// Repeating: Every buy_qty items earns get_qty free items
					// Example: Buy 1 Get 1 Free with 2 qualifying items = 2 free items
					// Formula: floor(total_qty / buy_qty) * get_qty
					$complete_sets = floor( $total_qty / $buy_qty );
					$free_quantity = $complete_sets * $get_qty;
				} else {
					// One-time: get_qty free items if qualified once
					if ( $total_qty >= $buy_qty ) {
						$free_quantity = $get_qty;
					}
				}
				
				
				// Exchange mode: customer must manually add exchange products.
				// We only adjust the price for eligible quantities already in cart.
				if ( $free_quantity > 0 ) {
					if ( $exchange_price > 0 ) {
						$remaining_exchange_qty = $free_quantity;
						$gift_product_ids = array_map( 'intval', $gift_products );
						$exchange_applied = false;

						foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
							if ( $remaining_exchange_qty <= 0 ) {
								break;
							}

							if ( isset( $cart_item['discount_tools_free_product'] ) && $cart_item['discount_tools_free_product'] === true ) {
								continue;
							}

							$item_product_id = isset( $cart_item['product_id'] ) ? intval( $cart_item['product_id'] ) : 0;
							if ( ! in_array( $item_product_id, $gift_product_ids, true ) ) {
								continue;
							}

							$item_qty = isset( $cart_item['quantity'] ) ? intval( $cart_item['quantity'] ) : 0;
							if ( $item_qty <= 0 ) {
								continue;
							}

							$exchange_qty = min( $item_qty, $remaining_exchange_qty );
							$remaining_exchange_qty -= $exchange_qty;

							$product_obj = isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ? $cart->cart_contents[ $cart_item_key ]['data'] : null;
							if ( ! $product_obj ) {
								continue;
							}

							// Use the product's normal selling price as base (sale price first),
							// then fallback to regular/current price.
							$original_price = floatval( $product_obj->get_sale_price() );
							if ( $original_price <= 0 ) {
								$original_price = floatval( $product_obj->get_regular_price() );
							}
							if ( $original_price <= 0 ) {
								$original_price = floatval( $product_obj->get_price() );
							}

							$normal_qty = $item_qty - $exchange_qty;
							$new_line_total = ( $normal_qty * $original_price ) + ( $exchange_qty * $exchange_price );
							$new_unit_price = $new_line_total / $item_qty;

							$cart->cart_contents[ $cart_item_key ]['data']->set_price( $new_unit_price );
							$cart->cart_contents[ $cart_item_key ]['discount_tools_adjusted_by_cart_rules'] = true;
							$exchange_applied = true;
						}

						if ( $exchange_applied ) {
							$rule_applied = true;
						}
					} else {
						// Gift mode: auto-add selected products when exchange price is 0.
						foreach ( $gift_products as $gift_product_id ) {
							$gift_product_id = intval( $gift_product_id );
							if ( $gift_product_id <= 0 ) {
								continue;
							}

							$free_products[] = array(
								'product_id' => $gift_product_id,
								'quantity'   => $free_quantity,
								'rule_id'    => $rule->get_id(),
								'rule_name'  => $rule->get_name(),
								'exchange_price' => 0,
							);
							$rule_applied = true;
						}
					}
				}
			}
		}

		if ( $rule_applied && ! $rule->is_stackable() ) {
			$non_stackable_applied_groups[ $rule_group ] = true;
		}
	}

	
	// Temporarily unhook to prevent recursion when adding/updating cart items
	remove_action( 'woocommerce_before_calculate_totals', array( $this, 'add_free_products' ), 10 );
	
	// Clear BXGY rules from session before re-adding
	$session_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
	if ( ! empty( $session_discounts['rules_applied'] ) ) {
		// Remove all BXGY rules from session
		$session_discounts['rules_applied'] = array_filter(
			$session_discounts['rules_applied'],
			function( $rule ) {
				// Check 'discount_type' key which is used when adding BXGY rules to session
				$type = isset( $rule['discount_type'] ) ? $rule['discount_type'] : ( isset( $rule['type'] ) ? $rule['type'] : '' );
				return ! in_array( $type, array( 'bxgy', 'bxgy_same', 'bxgy_any', 'bxgy_cheapest' ), true );
			}
		);
		WC()->session->set( 'discount_tools_cart_discounts', $session_discounts );
	}
	
	// First, remove ALL existing free products to prevent accumulation
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( isset( $cart_item['discount_tools_free_product'] ) &&
			 $cart_item['discount_tools_free_product'] === true ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		}
	}
	
	// Then add fresh free products with correct quantities and track which were successfully added
	$successfully_added = array();
	foreach ( $free_products as $free_product ) {
		$result = $this->add_free_product_to_cart( $free_product );
		if ( $result ) {
			$successfully_added[] = $free_product;
		}
	}
	
	// Normalize prices for newly added BXGY products.
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['discount_tools_free_product'] ) && $cart_item['discount_tools_free_product'] === true ) {
			$exchange_price = isset( $cart_item['discount_tools_exchange_price'] ) ? max( 0, floatval( $cart_item['discount_tools_exchange_price'] ) ) : 0;
			$cart_item['data']->set_price( $exchange_price );
		}
	}
	
	// Update session to include BXGY rules that successfully added free products
	if ( ! empty( $successfully_added ) ) {
		$session_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
		if ( empty( $session_discounts ) ) {
			$session_discounts = array(
				'total_discount' => 0,
				'rules_applied' => array(),
			);
		}
		
		// Add each BXGY rule that successfully provided free products to the session
		foreach ( $successfully_added as $free_product ) {
			$rule_id = $free_product['rule_id'];
			
			// Check if already in session
			$already_in_session = false;
			if ( ! empty( $session_discounts['rules_applied'] ) ) {
				foreach ( $session_discounts['rules_applied'] as $applied_rule ) {
					if ( $applied_rule['rule_id'] === $rule_id ) {
						$already_in_session = true;
						break;
					}
				}
			}
			
			if ( ! $already_in_session ) {
				$session_discounts['rules_applied'][] = array(
					'rule_id' => $rule_id,
					'rule_name' => $free_product['rule_name'],
					'discount_type' => 'bxgy_any',
					'discount' => 0,
				);
			}
		}
		
		WC()->session->set( 'discount_tools_cart_discounts', $session_discounts );
	}
	
	// Re-hook for future calls
	add_action( 'woocommerce_before_calculate_totals', array( $this, 'add_free_products' ), 10, 1 );

	$processing = false;
	}

	/**
	 * Add a free product to cart.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $free_product Free product data.
	 * @return void
	 */
	private function add_free_product_to_cart( $free_product ) {
		$product_id = $free_product['product_id'];
		$quantity = $free_product['quantity'];
		$rule_id = $free_product['rule_id'];
		$exchange_price = isset( $free_product['exchange_price'] ) ? max( 0, floatval( $free_product['exchange_price'] ) ) : 0;


		// Check if product exists and get product object
		$product = wc_get_product( $product_id );
		
		if ( ! $product ) {
			return false;
		}

		$variation_id = 0;
		$variation_attributes = array();

		// If this is a variable product, get the first available variation
		if ( $product->is_type( 'variable' ) ) {
			
			$variations = $product->get_available_variations();
			
			if ( empty( $variations ) ) {
				return false;
			}
			
			// Get first variation
			$first_variation = $variations[0];
			$variation_id = $first_variation['variation_id'];
			$variation_attributes = $first_variation['attributes'];
			
			// Check variation stock instead of parent product stock
			$variation_product = wc_get_product( $variation_id );
			if ( ! $variation_product ) {
				return false;
			}
			
			$in_stock = $variation_product->is_in_stock();
			$backorders = $variation_product->backorders_allowed();
			
			if ( ! $in_stock && ! $backorders ) {
				return false;
			}
		} else {
			// For simple products, check stock
			if ( ! $product->is_in_stock() && ! $product->backorders_allowed() ) {
				return false;
			}
		}

		// Add as new cart item with special meta
		$result = WC()->cart->add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			$variation_attributes,
			array(
				'discount_tools_free_product' => true,
				'discount_tools_rule_id' => $rule_id,
				'discount_tools_rule_name' => $free_product['rule_name'],
				'discount_tools_exchange_price' => $exchange_price,
			)
		);
		
		return $result !== false;
	}

	/**
	 * Set BXGY-added product price.
	 *
	 * This is handled in before_calculate_totals hook.
	 *
	 * @since  1.0.0
	 * @param  array $cart_item Cart item.
	 * @return void
	 */
	public function set_free_product_price( $cart_item ) {
		if ( isset( $cart_item['discount_tools_free_product'] ) && 
			 $cart_item['discount_tools_free_product'] === true ) {
			$exchange_price = isset( $cart_item['discount_tools_exchange_price'] ) ? max( 0, floatval( $cart_item['discount_tools_exchange_price'] ) ) : 0;
			$cart_item['data']->set_price( $exchange_price );
		}
	}

	/**
	 * Display savings summary in cart/checkout.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function display_savings_summary() {
		$discounts = WC()->session->get( 'discount_tools_cart_discounts' );

		if ( empty( $discounts ) ) {
			return;
		}

		// Check if any APPLIED rule has show_savings_message enabled
		// Only check rules that were actually applied to this cart
		$should_display = false;
		
		if ( ! empty( $discounts['rules_applied'] ) ) {
			foreach ( $discounts['rules_applied'] as $rule_data ) {
				$rule_id = isset( $rule_data['rule_id'] ) ? $rule_data['rule_id'] : 0;
				
				if ( $rule_id > 0 ) {
					// Check this specific rule's meta value
					global $wpdb;
					$meta_table = esc_sql( $wpdb->prefix . 'dt_rule_meta' );
					
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix; custom meta table query.
				$show_message = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$meta_table} 
					WHERE rule_id = %d AND meta_key = 'display_show_savings_message'",
					$rule_id
				) );
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					
					if ( $show_message === '1' ) {
						$should_display = true;
						break;
					}
				}
			}
		}

		// If no applied rule has savings message enabled, don't display
		if ( ! $should_display ) {
			return;
		}

		echo '<tr class="discount-tools-savings">';
		echo '<th>' . esc_html__( 'Total Savings', 'discount-tools' ) . '</th>';
		echo '<td>';

		$total_savings = 0;

		// Cart discounts
		if ( ! empty( $discounts ) && isset( $discounts['total_discount'] ) ) {
			$total_savings += $discounts['total_discount'];
		}

		// Display total
		echo '<strong class="savings-amount">' . wp_kses_post( wc_price( $total_savings ) ) . '</strong>';

		// Show breakdown
		if ( ! empty( $discounts['rules_applied'] ) ) {
			echo '<div class="savings-breakdown">';
			echo '<small>';
			foreach ( $discounts['rules_applied'] as $rule ) {
				$discount = isset( $rule['discount'] ) ? $rule['discount'] : 0;
				echo '<br>' . esc_html( $rule['rule_name'] ) . ': ' . wp_kses_post( wc_price( $discount ) );
			}
			echo '</small>';
			echo '</div>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Build cart context for evaluation.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Context array.
	 */
	private function build_cart_context() {
		$context = array();

		if ( ! WC()->cart ) {
			return $context;
		}

		// CRITICAL: Set rule_type so condition evaluator knows this is a cart rule
		// This allows not_in conditions on product/category/brand to be skipped during rule selection
		// (they will be applied later during item filtering)
		$context['rule_type'] = 'cart';

		// Cart totals - MANUALLY CALCULATE because we're in before_calculate_totals hook
		// At this stage, get_subtotal() may not have the correct value yet
		// IMPORTANT: Exclude free products (gifts) from cart total calculation
		// This includes gifts from other plugins (e.g., social login lite birthday gifts)
		$cart_total = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			// Skip free products/gifts - they shouldn't count toward minimum purchase requirements
			
			// Check 1: Our plugin's free product flag
			$is_free_product = isset( $cart_item['discount_tools_free_product'] ) && $cart_item['discount_tools_free_product'] === true;
			
			// Check 2: Actual price is 0 or negative (already processed free products)
			$product_price = floatval( $cart_item['data']->get_price() );
			
			// Check 3: Check for other plugins' free product flags (e.g., social login lite)
			$is_other_plugin_gift = isset( $cart_item['free_gift'] ) && $cart_item['free_gift'] === true;
			
			// Check 4: Check line_total is 0 (gift that's already been processed)
			$line_total = isset( $cart_item['line_total'] ) ? floatval( $cart_item['line_total'] ) : 0;
			
			if ( $is_free_product || $product_price <= 0 || $is_other_plugin_gift || $line_total <= 0 ) {
				continue; // Skip this item - it's a gift
			}
			
			$cart_total += $product_price * $cart_item['quantity'];
		}
		$context['cart_total'] = $cart_total;
		$context['cart_quantity'] = WC()->cart->get_cart_contents_count();
		$context['cart_weight'] = WC()->cart->get_cart_contents_weight();

		// Cart items with brand information
		$context['cart_items'] = array();
		$context['cart_brands'] = array(); // Track all brands in cart
		
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'];
			
			// Get brand taxonomy
			$brand_taxonomy = $this->get_brand_taxonomy();
			$brands = array();
			
			if ( $brand_taxonomy ) {
				$brand_terms = wp_get_post_terms( $product_id, $brand_taxonomy, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
					$brands = $brand_terms;
					$context['cart_brands'] = array_unique( array_merge( $context['cart_brands'], $brand_terms ) );
				}
			}
			
			$context['cart_items'][] = array(
				'product_id' => $product_id,
				'variation_id' => $cart_item['variation_id'],
				'quantity' => $cart_item['quantity'],
				'price' => $cart_item['data']->get_price(),
				'brands' => $brands, // Add brand IDs to each item
			);
		}

		// User context
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

		// Shipping context
		if ( WC()->customer ) {
			$context['shipping_state'] = WC()->customer->get_shipping_state();
			$context['shipping_city'] = WC()->customer->get_shipping_city();
			$context['shipping_postcode'] = WC()->customer->get_shipping_postcode();
		}

		// Payment method (if selected)
		if ( WC()->session ) {
			$context['payment_method'] = WC()->session->get( 'chosen_payment_method' );
		}

		// Date/time
		$context['current_time'] = current_time( 'mysql' );
		$context['day_of_week'] = intval( current_time( 'w' ) );

		return apply_filters( 'discount_tools_cart_context', $context );
	}

	/**
	 * Perform global stacking validation across ALL rule types (product + cart).
	 * 
	 * This method checks ALL applicable rules (product rules + cart rules) and enforces
	 * the stacking logic globally:
	 * - If ANY non-stackable rule exists, only the highest priority one is allowed
	 * - All other rules (including stackable ones) are excluded
	 * - If all rules are stackable, all are allowed
	 *
	 * @since  1.0.7
	 * @access private
	 * @param  array $context Cart context.
	 * @return array {
	 *     @type array $product_rules Filtered product rules that can be applied
	 *     @type array $cart_rules Filtered cart rules that can be applied
	 *     @type bool $has_non_stackable Whether any non-stackable rule exists
	 *     @type int|null $selected_rule_id ID of the selected non-stackable rule (if any)
	 * }
	 */
	private function check_global_stacking( $context = array() ) {
		// Return cached result if available
		if ( $this->global_stacking_cache !== null ) {
			return $this->global_stacking_cache;
		}

		$result = array(
			'product_rules' => array(),
			'cart_rules' => array(),
			'has_non_stackable' => false,
			'selected_rule_id' => null,
		);

		// Step 1: Collect ALL applicable product rules
		$product_rules = array();
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			// No cart items, no product rules
		} else {
			$cart_items = WC()->cart->get_cart();
			
			// Get product rules for each cart item
			foreach ( $cart_items as $cart_item ) {
				// Skip free products
				if ( isset( $cart_item['discount_tools_free_product'] ) && $cart_item['discount_tools_free_product'] === true ) {
					continue;
				}

				$product_id = $cart_item['product_id'];
				$variation_id = $cart_item['variation_id'];
				$product = $cart_item['data'];
				
				// Get original price
				$item_id = $variation_id ? $variation_id : $product_id;
				$original_price = get_post_meta( $item_id, '_regular_price', true );
				if ( empty( $original_price ) ) {
					$original_price = get_post_meta( $item_id, '_price', true );
				}
				if ( empty( $original_price ) ) {
					$original_price = $product->get_regular_price();
				}
				$original_price = floatval( $original_price );

				// Build product context
				$product_context = array_merge( $context, array(
					'product_id' => $product_id,
					'variation_id' => $variation_id,
					'quantity' => $cart_item['quantity'],
					'price' => $original_price,
					'product' => $product,
				) );

				// Get applicable product rules
				$rules = $this->rule_engine->get_applicable_rules( 'product', $product_id, $product_context );
				
				foreach ( $rules as $rule ) {
					// Avoid duplicates
					$found = false;
					foreach ( $product_rules as $existing_rule ) {
						if ( $existing_rule->get_id() === $rule->get_id() ) {
							$found = true;
							break;
						}
					}
					if ( ! $found ) {
						$product_rules[] = $rule;
					}
				}
			}
		}

		// Step 2: Collect ALL applicable cart rules
		$cart_rules = $this->rule_engine->get_applicable_rules( 'cart', null, $context );

		// Step 3: Combine all rules and sort by priority
		$all_rules = array_merge( $product_rules, $cart_rules );
		
		if ( empty( $all_rules ) ) {
			// No rules at all
			$this->global_stacking_cache = $result;
			return $result;
		}

		$priority_manager = new Discount_Tools_Priority_Manager();
		$sorted_all_rules = $priority_manager->sort_rules( $all_rules );

		// Step 4: Check for non-stackable rules
		$non_stackable_rule = null;
		foreach ( $sorted_all_rules as $rule ) {
			if ( ! $rule->is_stackable() ) {
				// Found first (highest priority) non-stackable rule
				$non_stackable_rule = $rule;
				$result['has_non_stackable'] = true;
				$result['selected_rule_id'] = $rule->get_id();
				break;
			}
		}

		// Step 5: Filter rules based on stacking logic
		if ( $non_stackable_rule !== null ) {
			// Non-stackable rule exists: ONLY apply this one rule, exclude all others
			$rule_type = $non_stackable_rule->get_rule_type();
			if ( $rule_type === 'product' ) {
				$result['product_rules'] = array( $non_stackable_rule );
				$result['cart_rules'] = array(); // Exclude all cart rules
			} else {
				$result['product_rules'] = array(); // Exclude all product rules
				$result['cart_rules'] = array( $non_stackable_rule );
			}
		} else {
			// All rules are stackable: allow all
			$result['product_rules'] = $product_rules;
			$result['cart_rules'] = $cart_rules;
		}

		// Cache the result
		$this->global_stacking_cache = $result;

		return $result;
	}

	/**
	 * Get cart items as array for rule engine.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Cart items.
	 */
	private function get_cart_items_array() {
		$items = array();

		if ( ! WC()->cart ) {
			return $items;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			// Skip free products from discount calculation
			if ( isset( $cart_item['discount_tools_free_product'] ) && 
				 $cart_item['discount_tools_free_product'] === true ) {
				continue;
			}

			$items[] = array(
				'key' => $cart_item_key,
				'product_id' => $cart_item['product_id'],
				'variation_id' => $cart_item['variation_id'],
				'quantity' => $cart_item['quantity'],
				'price' => $cart_item['data']->get_price(),
				'line_total' => isset( $cart_item['line_total'] ) ? $cart_item['line_total'] : ( $cart_item['data']->get_price() * $cart_item['quantity'] ),
			);
		}

		return $items;
	}

	/**
	 * Format discount label for cart display.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $discount_result Discount result.
	 * @return string                 Formatted label.
	 */
	private function format_discount_label( $discount_result ) {
		if ( empty( $discount_result['rules_applied'] ) ) {
			return __( 'Discount', 'discount-tools' );
		}

		// Filter: Only show percentage or fixed_amount discount types in cart totals
		// BXGY, Bundle, etc. are already reflected in product prices
		$cart_display_rules = array();
		foreach ( $discount_result['rules_applied'] as $applied_rule ) {
			// Check both 'type' (cart rules) and 'discount_type' (product rules)
			$discount_type = isset( $applied_rule['type'] ) ? $applied_rule['type'] : 
							( isset( $applied_rule['discount_type'] ) ? $applied_rule['discount_type'] : '' );
			if ( in_array( $discount_type, array( 'percentage', 'fixed_amount' ) ) ) {
				$cart_display_rules[] = $applied_rule;
			}
		}

		// If no displayable rules, don't show label in cart
		if ( empty( $cart_display_rules ) ) {
			return '';
		}

		$rules_count = count( $cart_display_rules );

		if ( $rules_count === 1 ) {
			$applied_rule = $cart_display_rules[0];
			$rule_name = $applied_rule['rule_name'];
			
			// For percentage discounts, just show the rule name
			if ( false && isset( $applied_rule['type'] ) && in_array( $applied_rule['type'], array( 'bxgy_same', 'bxgy_any' ), true ) ) {
				// Try to get BXGY configuration for better label
				$rule_id = isset( $applied_rule['rule_id'] ) ? $applied_rule['rule_id'] : 0;
				if ( $rule_id > 0 ) {
				$rule_repo = new Discount_Tools_Rule_Repository();
				$rule = $rule_repo->find( $rule_id );					if ( $rule ) {
						$buy_qty = $rule->get_meta_value( 'bxgy_buy_quantity' );
						$get_qty = $rule->get_meta_value( 'bxgy_get_quantity' );
						$get_discount = $rule->get_meta_value( 'bxgy_get_discount' );
						$get_type = $rule->get_meta_value( 'bxgy_get_type' );
						
						// Set defaults
						$buy_qty = ! empty( $buy_qty ) ? intval( $buy_qty ) : 2;
						$get_qty = ! empty( $get_qty ) ? intval( $get_qty ) : 1;
						$get_discount = ! empty( $get_discount ) ? floatval( $get_discount ) : 100.0;
						$get_type = ! empty( $get_type ) ? $get_type : 'percentage';
						
						// Format promotion description
						if ( $get_type === 'percentage' && floatval( $get_discount ) == 100.0 ) {
					// Buy X Get Y Free
						return sprintf(
							/* translators: %1$d: buy quantity, %2$d: get quantity, %3$s: rule name */
							__( 'Buy %1$d Get %2$d Free - %3$s', 'discount-tools' ),
							$buy_qty,
							$get_qty,
							$rule_name
						);
					} elseif ( $get_type === 'percentage' ) {
						// Buy X Get Y at X% Off
						return sprintf(
							/* translators: %1$d: buy quantity, %2$d: get quantity, %3$.0f: discount percentage, %4$s: rule name */
							__( 'Buy %1$d Get %2$d at %3$.0f%% Off - %4$s', 'discount-tools' ),
							$buy_qty,
							$get_qty,
							$get_discount,
							$rule_name
						);
					} else {
						// Buy X Get Y with $X Off
						return sprintf(
							/* translators: %1$d: buy quantity, %2$d: get quantity, %3$.2f: discount amount, %4$s: rule name */
							__( 'Buy %1$d Get %2$d with $%3$.2f Off - %4$s', 'discount-tools' ),
							$buy_qty,
							$get_qty,
							$get_discount,
							$rule_name
						);
						}
					}
				}
			}
			
			// Check if this is a Bundle discount type
			if ( isset( $applied_rule['type'] ) && $applied_rule['type'] === 'bundle' ) {
				// Try to get Bundle info for better label
				if ( isset( $applied_rule['bundle_info'] ) ) {
					$bundle_info = $applied_rule['bundle_info'];
					$bundle_qty = isset( $bundle_info['bundle_qty'] ) ? intval( $bundle_info['bundle_qty'] ) : 2;
					$bundle_price = isset( $bundle_info['bundle_price'] ) ? floatval( $bundle_info['bundle_price'] ) : 0;
					$complete_bundles = isset( $bundle_info['complete_bundles'] ) ? intval( $bundle_info['complete_bundles'] ) : 0;
					
					if ( $complete_bundles > 0 ) {
						// Format: "BUNDLE: 2 FOR $99.00" (no HTML, plain text for cart fee label)
						return sprintf(
							'BUNDLE: %d FOR %s%.2f',
							$bundle_qty,
							get_woocommerce_currency_symbol(),
							$bundle_price
						);
					}
				}
			}
			
			// Default: just show rule name
			return $rule_name;
		}

		// Multiple rules - show combined names
		$rule_names = array_column( $cart_display_rules, 'rule_name' );
		return implode( ' + ', $rule_names );
	}

	/**
	 * Handle item removed from cart.
	 * When a product is removed, also remove any related free products.
	 *
	 * @since  1.0.5
	 * @param  string $cart_item_key The cart item key being removed.
	 * @param  WC_Cart $cart Cart object.
	 * @return void
	 */
	public function handle_item_removed( $cart_item_key, $cart ) {
		if ( $this->cart_syncing ) {
			return;
		}

		$this->cart_syncing = true;

		// Remove all free products as they need to be recalculated
		// This ensures that if a qualifying product is removed, its free gift is also removed
		try {
			foreach ( $cart->get_cart() as $key => $item ) {
				if ( isset( $item['discount_tools_free_product'] ) && $item['discount_tools_free_product'] === true ) {
					$cart->remove_cart_item( $key );
				}
			}

			$this->clear_discount_cache();
		} finally {
			$this->cart_syncing = false;
		}
	}

	/**
	 * Handle cart updated (quantity changes).
	 * Recalculate free products based on new quantities.
	 *
	 * @since  1.0.5
	 * @param  WC_Cart $cart Cart object.
	 * @return void
	 */
	public function handle_cart_updated( $cart = null ) {
		if ( $this->cart_syncing ) {
			return;
		}

		if ( ! $cart && WC()->cart ) {
			$cart = WC()->cart;
		}
		
		if ( ! $cart ) {
			return;
		}

		$this->cart_syncing = true;
		
		// Remove all free products so they can be recalculated with new quantities
		try {
			foreach ( $cart->get_cart() as $key => $item ) {
				if ( isset( $item['discount_tools_free_product'] ) && $item['discount_tools_free_product'] === true ) {
					$cart->remove_cart_item( $key );
				}
			}

			$this->clear_discount_cache();
		} finally {
			$this->cart_syncing = false;
		}
	}

	/**
	 * Clear discount cache.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function clear_discount_cache() {
		$this->applied_discounts = array();
		$this->free_products = array();

		// Clear session data
		if ( WC()->session ) {
			WC()->session->set( 'discount_tools_cart_discounts', null );
		}
		
		// Clear WooCommerce fees to force recalculation
		// Don't call calculate_totals() here to avoid recursion
		if ( WC()->cart ) {
			// Remove our discount fees
			$fees = WC()->cart->get_fees();
			foreach ( $fees as $fee_key => $fee ) {
				if ( strpos( $fee->name, 'LESS' ) !== false || 
				     strpos( $fee->name, '折扣' ) !== false ||
				     strpos( $fee->id, 'discount_tools' ) !== false ) {
					WC()->cart->fees_api()->remove_fee( $fee_key );
				}
			}
		}
	}

	/**
	 * Get applied discounts.
	 *
	 * Public method to retrieve current discounts.
	 *
	 * @since  1.0.0
	 * @return array Applied discounts.
	 */
	public function get_applied_discounts() {
		$session_discounts = WC()->session->get( 'discount_tools_cart_discounts' );
		
		if ( ! empty( $session_discounts ) ) {
			return $session_discounts;
		}

		return $this->applied_discounts;
	}

	/**
	 * Get brand taxonomy for the site.
	 *
	 * @since  1.0.5
	 * @access private
	 * @return string|false Brand taxonomy name or false if not found.
	 */
	private function get_brand_taxonomy() {
		// Try to use Brand Detector if available
		if ( class_exists( 'Discount_Tools_Brand_Detector' ) ) {
			return Discount_Tools_Brand_Detector::get_brand_taxonomy();
		}

		// Fallback: Check common brand taxonomies
		$known_taxonomies = array(
			'product_brand',
			'yith_product_brand',
			'pwb-brand',
			'pa_brand',
		);

		foreach ( $known_taxonomies as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		// Search for custom brand taxonomies
		$product_taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $product_taxonomies as $taxonomy ) {
			if ( false !== stripos( $taxonomy->name, 'brand' ) ) {
				return $taxonomy->name;
			}
		}

		return false;
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

	/**
	 * Apply free shipping rates when a qualifying bundle rule enables free shipping.
	 *
	 * @since  1.0.7
	 * @param  array $rates   Shipping rates.
	 * @param  array $package Shipping package.
	 * @return array
	 */
	public function apply_bundle_free_shipping_rates( $rates, $package ) {
		if ( empty( $rates ) || ! WC()->cart ) {
			return $rates;
		}

		$context = $this->build_cart_context();
		$rules = $this->rule_engine->get_applicable_rules( 'cart', null, $context );
		if ( empty( $rules ) ) {
			return $rates;
		}

		$shipping_country = '';
		if ( WC()->customer ) {
			$shipping_country = WC()->customer->get_shipping_country();
			if ( empty( $shipping_country ) ) {
				$shipping_country = WC()->customer->get_billing_country();
			}
		}

		$enable_free_shipping = false;
		$shipping_rule_ids = array();
		foreach ( $rules as $rule ) {
			if ( $rule->get_discount_type() !== 'bundle' ) {
				continue;
			}

			$bundle_free_shipping = $rule->get_meta_value( 'bundle_free_shipping', 0 );
			$bundle_free_shipping = ( $bundle_free_shipping === '1' || $bundle_free_shipping === 1 || $bundle_free_shipping === true );
			if ( ! $bundle_free_shipping ) {
				continue;
			}

			$allowed_countries = $rule->get_meta_value( 'bundle_free_shipping_countries', array() );
			if ( ! is_array( $allowed_countries ) ) {
				$allowed_countries = array_filter( array_map( 'trim', explode( ',', strval( $allowed_countries ) ) ) );
			}

			$country_allowed = empty( $allowed_countries ) || in_array( $shipping_country, $allowed_countries, true );
			if ( ! $country_allowed ) {
				continue;
			}

			if ( $this->is_bundle_rule_qualified_for_cart( $rule, $context ) ) {
				$enable_free_shipping = true;
				$shipping_rule_ids[] = absint( $rule->get_id() );
			}
		}

		if ( WC()->session ) {
			WC()->session->set( 'dt_shipping_rule_ids', array_values( array_unique( array_filter( $shipping_rule_ids ) ) ) );
		}

		if ( ! $enable_free_shipping ) {
			return $rates;
		}

		foreach ( $rates as $rate_id => $rate ) {
			$rates[ $rate_id ]->cost = 0;
			if ( isset( $rates[ $rate_id ]->taxes ) && is_array( $rates[ $rate_id ]->taxes ) ) {
				foreach ( $rates[ $rate_id ]->taxes as $tax_key => $tax_value ) {
					$rates[ $rate_id ]->taxes[ $tax_key ] = 0;
				}
			}
		}

		return $rates;
	}

	/**
	 * Check whether a bundle rule is currently qualified for cart pricing.
	 *
	 * @since  1.0.7
	 * @param  Discount_Tools_Rule $rule    Bundle rule.
	 * @param  array               $context Cart context.
	 * @return bool
	 */
	private function is_bundle_rule_qualified_for_cart( $rule, $context ) {
		$bundle_qty = intval( $rule->get_meta_value( 'bundle_quantity', 2 ) );
		if ( $bundle_qty <= 0 ) {
			return false;
		}

		$cart_items = $this->get_cart_items_array();
		$filtered_items = $this->rule_engine->filter_cart_items_for_rule( $cart_items, $rule, $context );
		if ( empty( $filtered_items ) ) {
			return false;
		}

		$conditions = $rule->get_conditions();
		$product_condition = null;
		foreach ( $conditions as $condition ) {
			if ( $condition->get_condition_type() === 'product' && $condition->get_operator() === 'in' ) {
				$product_condition = $condition;
				break;
			}
		}

		if ( $product_condition ) {
			$qualifying_product_ids = $product_condition->get_value();
			if ( ! is_array( $qualifying_product_ids ) ) {
				$qualifying_product_ids = array( $qualifying_product_ids );
			}
			$qualifying_product_ids = array_map( 'intval', $qualifying_product_ids );

			$filtered_items = array_filter( $filtered_items, function( $item ) use ( $qualifying_product_ids ) {
				return in_array( intval( $item['product_id'] ), $qualifying_product_ids, true );
			} );

			if ( count( $qualifying_product_ids ) > 2 ) {
				$qty_by_product = array();
				foreach ( $filtered_items as $item ) {
					$product_id = intval( $item['product_id'] );
					$qty_by_product[ $product_id ] = isset( $qty_by_product[ $product_id ] )
						? $qty_by_product[ $product_id ] + intval( $item['quantity'] )
						: intval( $item['quantity'] );
				}

				foreach ( $qualifying_product_ids as $required_product_id ) {
					if ( empty( $qty_by_product[ $required_product_id ] ) ) {
						return false;
					}
				}
			}
		}

		$total_qty = 0;
		foreach ( $filtered_items as $item ) {
			$total_qty += intval( $item['quantity'] );
		}

		return $total_qty >= $bundle_qty;
	}

	/**
	 * Display Bundle cart item price with strikethrough for bundled items
	 *
	 * @since  1.0.0
	 * @param  string $price_html Original price HTML
	 * @param  array  $cart_item Cart item data
	 * @param  string $cart_item_key Cart item key
	 * @return string Modified price HTML
	 */
	public function display_bundle_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		// Check if this item has Bundle discount info
		if ( ! isset( $cart_item['discount_tools_bundle'] ) ) {
			return $price_html;
		}

		$bundle_info = $cart_item['discount_tools_bundle'];
		$original_price = isset( $bundle_info['original_price'] ) ? floatval( $bundle_info['original_price'] ) : 0;
		$discounted_price = isset( $bundle_info['discounted_price'] ) ? floatval( $bundle_info['discounted_price'] ) : floatval( $cart_item['data']->get_price() );

		if ( $original_price > 0 && $discounted_price < $original_price ) {
			return '<del>' . wc_price( $original_price ) . '</del> <span style="color:#dd3333;">' . wc_price( $discounted_price ) . '</span>';
		}

		return wc_price( $discounted_price );
	}
	
	/**
	 * Display Bundle cart item subtotal with correct bundled pricing
	 *
	 * @since  1.0.0
	 * @param  string $subtotal_html Original subtotal HTML
	 * @param  array  $cart_item Cart item data
	 * @param  string $cart_item_key Cart item key
	 * @return string Modified subtotal HTML
	 */
	public function display_bundle_cart_item_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		// Check if this item has Bundle discount info
		if ( ! isset( $cart_item['discount_tools_bundle'] ) ) {
			return $subtotal_html;
		}

		$qty = isset( $cart_item['quantity'] ) ? intval( $cart_item['quantity'] ) : 0;
		$line_total = floatval( $cart_item['data']->get_price() ) * $qty;
		return wc_price( $line_total );
	}

	/**
	 * Display BXGY discounted price in cart.
	 * Shows: ~~原價~~ 折扣單價 x 數量 (紅字)
	 *
	 * @since  1.0.0
	 * @param  string $price_html Original price HTML
	 * @param  array  $cart_item Cart item data
	 * @param  string $cart_item_key Cart item key
	 * @return string Modified price HTML
	 */
	public function display_bxgy_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		// Check if this cart item has BXGY discount info
		if ( ! isset( $cart_item['discount_tools_bxgy'] ) ) {
			return $price_html;
		}

		$bxgy = $cart_item['discount_tools_bxgy'];
		$original_price = $bxgy['original_price'];
		$free_qty = $bxgy['free_qty'];
		$buy_qty = $bxgy['buy_qty'];
		$get_qty = $bxgy['get_qty'];
		$qty = $cart_item['quantity'];

		// If no free items, show original price only
		if ( $free_qty === 0 ) {
			return wc_price( $original_price );
		}

		// Calculate discounted unit price
		$set_size = $buy_qty + $get_qty;
		$discounted_unit_price = $original_price / $set_size;
		
		// Calculate how many complete sets and remaining items
		$complete_sets = floor( $qty / $set_size );
		$remaining_qty = $qty % $set_size;
		$discounted_qty = $complete_sets * $set_size;

		// Show: ~~原價~~ 折扣單價 x 數量 (紅字, 小字體)
		$display = '<del>' . wc_price( $original_price ) . '</del> ';
		
		if ( $discounted_qty > 0 ) {
			$display .= '<span style="color: red;">' . wc_price( $discounted_unit_price ) . ' &times; ' . $discounted_qty . '</span>';
		}
		
		// Add remaining items at full price (if any)
		if ( $remaining_qty > 0 ) {
			if ( $discounted_qty > 0 ) {
				$display .= ', ';
			}
			$display .= '<span style="color: red; ">' . wc_price( $original_price ) . ' &times; ' . $remaining_qty . '</span>';
		}
		
		return $display;
	}

	/**
	 * Display discounted cart item price with original price strikethrough and discounted price in red.
	 * For fixed_amount/fixed_price discounts only.
	 *
	 * @since  1.0.5
	 * @param  string $price_html Original price HTML
	 * @param  array  $cart_item Cart item data
	 * @param  string $cart_item_key Cart item key
	 * @return string Modified price HTML
	 */
	public function display_discounted_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		// Skip if this is a BXGY or bundle item (already handled by other filters)
		if ( isset( $cart_item['discount_tools_bxgy'] ) || isset( $cart_item['bundled_by'] ) ) {
			return $price_html;
		}

		// Get product
		$product = $cart_item['data'];
		if ( ! $product ) {
			return $price_html;
		}

		// Get regular price from product metadata to bypass WooCommerce filters
		$product_id = $product->get_id();
		$regular_price = get_post_meta( $product_id, '_regular_price', true );
		
		if ( empty( $regular_price ) ) {
			$regular_price = get_post_meta( $product_id, '_price', true );
		}
		
		if ( empty( $regular_price ) ) {
			return $price_html;
		}
		
		$regular_price = floatval( $regular_price );
		$discounted_price = floatval( $product->get_price() );

		// Only show strikethrough + red price if there's an actual discount
		if ( $discounted_price >= $regular_price ) {
			return $price_html;
		}

		// Display: ~~Original Price~~ Discounted Price (in red)
		$display = '<del>' . wc_price( $regular_price ) . '</del> ';
		$display .= '<span style="color: #e74c3c; font-weight: bold;">' . wc_price( $discounted_price ) . '</span>';

		return $display;
	}

	/**
	 * Display BXGY discounted subtotal in cart.
	 * Shows the actual subtotal amount (what customer pays)
	 * Examples:
	 *   2 items (Buy 1 Get 1): HKD 115.00
	 *   3 items: HKD 230.00
	 *   4 items: HKD 230.00
	 *
	 * @since  1.0.0
	 * @param  string $subtotal_html Original subtotal HTML
	 * @param  array  $cart_item Cart item data
	 * @param  string $cart_item_key Cart item key
	 * @return string Modified subtotal HTML
	 */
	public function display_bxgy_cart_item_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		// Check if this cart item has BXGY discount info
		if ( ! isset( $cart_item['discount_tools_bxgy'] ) ) {
			return $subtotal_html;
		}

		$bxgy = $cart_item['discount_tools_bxgy'];
		$qty = $cart_item['quantity'];
		$free_qty = $bxgy['free_qty'];
		$original_price = $bxgy['original_price'];
		$buy_qty = $bxgy['buy_qty'];
		$get_qty = $bxgy['get_qty'];

		// If no free items, show original subtotal
		if ( $free_qty === 0 ) {
			return wc_price( $original_price * $qty );
		}

		// Calculate how many complete sets and remaining items
		$set_size = $buy_qty + $get_qty;
		$complete_sets = floor( $qty / $set_size );
		$remaining_qty = $qty % $set_size;
		
		// Calculate actual subtotal (what customer pays)
		// For Buy 1 Get 1: each complete set costs only buy_qty * original_price
		$actual_subtotal = ( $complete_sets * $buy_qty * $original_price ) + ( $remaining_qty * $original_price );

		// Just show the actual subtotal amount
		return wc_price( $actual_subtotal );
	}

	/**
	 * Check if a rule has coupon_activation condition.
	 *
	 * @since  1.0.7
	 * @param  int $rule_id Rule ID.
	 * @return bool True if rule has coupon_activation condition.
	 */
	private function rule_has_coupon_activation( $rule_id ) {
		$repository = new Discount_Tools_Rule_Repository();
		$rule = $repository->find( $rule_id );
		
		if ( ! $rule ) {
			return false;
		}
		
		$conditions = $rule->get_conditions();
		if ( empty( $conditions ) ) {
			return false;
		}
		
		foreach ( $conditions as $condition ) {
			if ( $condition->get_type() === 'coupon_activation' ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Get coupon codes from rule's coupon_activation condition.
	 *
	 * @since  1.0.7
	 * @param  int $rule_id Rule ID.
	 * @return array Array of coupon codes.
	 */
	private function get_rule_coupon_codes( $rule_id ) {
		$repository = new Discount_Tools_Rule_Repository();
		$rule = $repository->find( $rule_id );
		
		if ( ! $rule ) {
			return array();
		}
		
		$conditions = $rule->get_conditions();
		if ( empty( $conditions ) ) {
			return array();
		}
		
		// Find coupon_activation condition and extract coupon codes
		foreach ( $conditions as $condition ) {
			if ( $condition->get_type() === 'coupon_activation' ) {
				$value = $condition->get_value();
				
				// Handle structured format (with email_list)
				if ( is_array( $value ) && isset( $value['coupon_codes'] ) ) {
					return is_array( $value['coupon_codes'] ) ? $value['coupon_codes'] : array( $value['coupon_codes'] );
				}
				
				// Handle simple format (just coupon codes)
				return is_array( $value ) ? $value : array( $value );
			}
		}
		
		return array();
	}

	/**
	 * Store discount rule ID in session for later use (e.g., displaying remove links).
	 *
	 * @since  1.0.7
	 * @param  string $rule_name            Rule name/label.
	 * @param  int    $rule_id              Rule ID.
	 * @param  bool   $has_coupon_condition Whether rule has coupon_activation condition.
	 * @return void
	 */
	private function store_discount_rule_id( $rule_name, $rule_id, $has_coupon_condition = false ) {
		$stored_rules = WC()->session->get( 'dt_discount_rules', array() );
		$stored_rules[ $rule_name ] = array(
			'rule_id'              => intval( $rule_id ),
			'has_coupon_condition' => (bool) $has_coupon_condition,
		);
		WC()->session->set( 'dt_discount_rules', $stored_rules );
	}

	/**
	 * Add remove link to discount fee (only for coupon-activation rules).
	 *
	 * Filters the HTML output for cart/checkout fees to add remove functionality.
	 * Only adds remove link if the rule has a coupon_activation condition.
	 *
	 * @since  1.0.7
	 * @param  string $fee_html Fee HTML.
	 * @param  object $fee      Fee object.
	 * @return string Modified fee HTML with remove link.
	 */
	public function add_remove_link_to_discount( $fee_html, $fee ) {
		$fee_name = $fee->name;
		$stored_rules = WC()->session->get( 'dt_discount_rules', array() );
		
		// Check if this fee is a stored discount rule
		if ( ! isset( $stored_rules[ $fee_name ] ) ) {
			return $fee_html;
		}
		
		$rule_data = $stored_rules[ $fee_name ];
		
		// Handle both old format (direct rule_id) and new format (array with metadata)
		if ( is_array( $rule_data ) ) {
			$rule_id = isset( $rule_data['rule_id'] ) ? intval( $rule_data['rule_id'] ) : 0;
			$has_coupon_condition = isset( $rule_data['has_coupon_condition'] ) ? (bool) $rule_data['has_coupon_condition'] : false;
		} else {
			// Backward compatibility: old format was just the rule_id
			$rule_id = intval( $rule_data );
			$has_coupon_condition = $this->rule_has_coupon_activation( $rule_id );
		}
		
		// Only show remove link if rule has coupon_activation condition
		if ( ! $has_coupon_condition ) {
			return $fee_html;
		}
		
		// Generate remove link HTML
		$remove_link = $this->generate_remove_link( $rule_id );
		
		// Append remove link to fee HTML
		return $fee_html . $remove_link;
	}
	
	/**
	 * Add remove links to checkout page fees.
	 *
	 * @since  1.0.7
	 * @param  string $total_html Order total HTML.
	 * @return string Modified order total HTML.
	 */
	public function add_remove_links_to_checkout_fees( $total_html ) {
		if ( ! is_checkout() ) {
			return $total_html;
		}
		
		$stored_rules = WC()->session->get( 'dt_discount_rules', array() );
		if ( empty( $stored_rules ) ) {
			return $total_html;
		}
		
		// Inject remove links into the checkout review
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'display_checkout_remove_links' ) );
		
		return $total_html;
	}
	
	/**
	 * Display remove links for discounts on checkout page.
	 *
	 * @since  1.0.7
	 * @return void
	 */
	public function display_checkout_remove_links() {
		$stored_rules = WC()->session->get( 'dt_discount_rules', array() );
		if ( empty( $stored_rules ) ) {
			return;
		}
		
		// Get cart fees to match with stored rules
		$cart_fees = WC()->cart->get_fees();
		if ( empty( $cart_fees ) ) {
			return;
		}
		
		foreach ( $cart_fees as $fee ) {
			$fee_name = $fee->name;
			
			// Check if this fee has a stored rule
			if ( ! isset( $stored_rules[ $fee_name ] ) ) {
				continue;
			}
			
			$rule_data = $stored_rules[ $fee_name ];
			
			// Handle both old format (direct rule_id) and new format (array with metadata)
			if ( is_array( $rule_data ) ) {
				$rule_id = isset( $rule_data['rule_id'] ) ? intval( $rule_data['rule_id'] ) : 0;
				$has_coupon_condition = isset( $rule_data['has_coupon_condition'] ) ? (bool) $rule_data['has_coupon_condition'] : false;
			} else {
				// Backward compatibility
				$rule_id = intval( $rule_data );
				$has_coupon_condition = $this->rule_has_coupon_activation( $rule_id );
			}
			
			// Coupon-activation rules are handled by the JS dt-activation-remove button
			// injected via add_coupon_remove_script_checkout(). Rendering a PHP row here
			// would: (a) duplicate the fee row, and (b) use ?remove_dt_discount which adds
			// the rule to disabled_discounts — permanently preventing the fee from showing
			// again even after the coupon is re-applied.
			if ( $has_coupon_condition ) {
				continue;
			}
			
			// Generate remove link
			$remove_link = $this->generate_remove_link( $rule_id, $fee_name );
			
			// Display as a row in the order review table
			echo '<tr class="cart-discount">';
			echo '<th>' . esc_html( $fee_name ) . '</th>';
			echo '<td data-title="' . esc_attr( $fee_name ) . '">';
			echo wp_kses_post( wc_price( $fee->amount ) );
			echo ' ' . wp_kses_post( $remove_link );
			echo '</td>';
			echo '</tr>';
		}
	}
	
	/**
	 * Generate remove link HTML for a discount rule.
	 *
	 * @since  1.0.7
	 * @param  int    $rule_id   Rule ID.
	 * @param  string $fee_name  Optional fee name for display.
	 * @return string Remove link HTML.
	 */
	private function generate_remove_link( $rule_id, $fee_name = '' ) {
		// Generate nonce for security
		$nonce = wp_create_nonce( 'dt_remove_discount_' . $rule_id );
		
		// Build remove URL - redirect to current page
		$redirect_url = is_checkout() ? wc_get_checkout_url() : wc_get_cart_url();
		$remove_url = add_query_arg(
			array(
				'remove_dt_discount' => $rule_id,
				'_wpnonce'           => $nonce,
			),
			$redirect_url
		);
		
		// Add remove link - use custom class to avoid WC JS interference
		$remove_link = sprintf(
			' <a href="%s" class="dt-remove-discount" data-discount="%s">%s</a>',
			esc_url( $remove_url ),
			esc_attr( $rule_id ),
			/* translators: %s: discount name */
			esc_html__( '[移除]', 'discount-tools' )
		);
		
		return $remove_link;
	}

	/**
	 * Handle discount removal request.
	 *
	 * Processes the remove_dt_discount URL parameter to disable a specific discount
	 * for the current cart session.
	 *
	 * @since  1.0.7
	 * @return void
	 */
	public function handle_remove_discount() {
		if ( ! isset( $_GET['remove_dt_discount'] ) ) {
			return;
		}
		
		$rule_id = intval( $_GET['remove_dt_discount'] );
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		
		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, 'dt_remove_discount_' . $rule_id ) ) {
			wc_add_notice( __( '安全檢查失敗。', 'discount-tools' ), 'error' );
			$redirect_url = is_checkout() ? wc_get_checkout_url() : wc_get_cart_url();
			wp_safe_redirect( $redirect_url );
			exit;
		}
		
		// Get stored rules to find the rule name
		$stored_rules = WC()->session->get( 'dt_discount_rules', array() );
		$rule_name = '';
		
		foreach ( $stored_rules as $name => $data ) {
			// Handle both old format (direct rule_id) and new format (array)
			$stored_rule_id = is_array( $data ) ? intval( $data['rule_id'] ) : intval( $data );
			if ( $stored_rule_id === $rule_id ) {
				$rule_name = $name;
				break;
			}
		}
		
		// For rules with a coupon_activation condition: remove the linked WC coupon instead
		// of adding to disabled_discounts. This way, re-applying the coupon re-enables
		// the rule automatically without needing to clear session data.
		$has_coupon_condition = $this->rule_has_coupon_activation( $rule_id );

		if ( $has_coupon_condition ) {
			// Remove every WC coupon that activates this rule
			$coupon_codes = $this->get_rule_coupon_codes( $rule_id );
			foreach ( $coupon_codes as $code ) {
				WC()->cart->remove_coupon( strtolower( $code ) );
			}
		} else {
			// Add to disabled discounts list (non-coupon-activation rules)
			$disabled_discounts = WC()->session->get( 'dt_disabled_discounts', array() );
			if ( ! in_array( $rule_id, $disabled_discounts, true ) ) {
				$disabled_discounts[] = $rule_id;
				WC()->session->set( 'dt_disabled_discounts', $disabled_discounts );
			}
		}

		// Remove from stored discount rules
		if ( ! empty( $rule_name ) && isset( $stored_rules[ $rule_name ] ) ) {
			unset( $stored_rules[ $rule_name ] );
			WC()->session->set( 'dt_discount_rules', $stored_rules );
		}

		// Also purge from discount_tools_cart_discounts so that the
		// dt_ensure_discount_fees_display() fallback does not re-add the fee
		$cart_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
		if ( ! empty( $cart_discounts['rules_applied'] ) ) {
			$cart_discounts['rules_applied'] = array_values( array_filter(
				$cart_discounts['rules_applied'],
				function( $r ) use ( $rule_id ) {
					return ! ( isset( $r['rule_id'] ) && intval( $r['rule_id'] ) === $rule_id );
				}
			) );
			if ( empty( $cart_discounts['rules_applied'] ) ) {
				$cart_discounts['total_discount'] = 0;
			}
			WC()->session->set( 'discount_tools_cart_discounts', $cart_discounts );
		}

		// Clear stacking cache so calculate_totals() re-evaluates fresh (without the removed coupon)
		$this->global_stacking_cache = null;

		// Force recalculation
		WC()->cart->calculate_totals();
		
		// Show success message
		if ( ! empty( $rule_name ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: discount name */
					__( '折扣「%s」已從購物車中移除。', 'discount-tools' ),
					$rule_name
				),
				'success'
			);
		}
		
		// Redirect to appropriate page to refresh
		$redirect_url = is_checkout() ? wc_get_checkout_url() : wc_get_cart_url();
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Track rule usage when order is placed.
	 * 
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 */
	public function track_rule_usage_on_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $order->get_meta( '_dt_usage_count_tracked', true ) ) {
			return;
		}

		$rule_ids = array();

		if ( WC()->session ) {
			$cart_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
			if ( ! empty( $cart_discounts['rules_applied'] ) && is_array( $cart_discounts['rules_applied'] ) ) {
				foreach ( $cart_discounts['rules_applied'] as $rule_info ) {
					if ( isset( $rule_info['rule_id'] ) ) {
						$rule_ids[] = absint( $rule_info['rule_id'] );
					}
				}
			}

			$legacy_keys = array( 'dt_cart_rules_applied', 'dt_product_rules_applied' );
			foreach ( $legacy_keys as $legacy_key ) {
				$legacy_rules = WC()->session->get( $legacy_key, array() );
				if ( empty( $legacy_rules ) || ! is_array( $legacy_rules ) ) {
					continue;
				}

				foreach ( $legacy_rules as $rule_info ) {
					if ( isset( $rule_info['rule_id'] ) ) {
						$rule_ids[] = absint( $rule_info['rule_id'] );
					}
				}
			}

			$shipping_rule_ids = WC()->session->get( 'dt_shipping_rule_ids', array() );
			if ( is_array( $shipping_rule_ids ) ) {
				foreach ( $shipping_rule_ids as $shipping_rule_id ) {
					$rule_ids[] = absint( $shipping_rule_id );
				}
			}
		}

		$meta_rule_ids = $order->get_meta( '_dt_rule_ids', true );
		if ( is_array( $meta_rule_ids ) ) {
			foreach ( $meta_rule_ids as $meta_rule_id ) {
				$rule_ids[] = absint( $meta_rule_id );
			}
		}

		$cart_rules_meta = $order->get_meta( '_dt_cart_rules', true );
		if ( is_array( $cart_rules_meta ) ) {
			foreach ( $cart_rules_meta as $cart_rule ) {
				if ( is_array( $cart_rule ) && isset( $cart_rule['rule_id'] ) ) {
					$rule_ids[] = absint( $cart_rule['rule_id'] );
				}
			}
		}

		foreach ( $order->get_items() as $item ) {
			$item_rule_id = $item->get_meta( 'discount_tools_rule_id' );
			if ( ! empty( $item_rule_id ) ) {
				$rule_ids[] = absint( $item_rule_id );
			}

			$item_bundle = $item->get_meta( 'discount_tools_bundle' );
			if ( is_array( $item_bundle ) && isset( $item_bundle['rule_id'] ) ) {
				$rule_ids[] = absint( $item_bundle['rule_id'] );
			}

			$item_bxgy = $item->get_meta( 'discount_tools_bxgy' );
			if ( is_array( $item_bxgy ) && isset( $item_bxgy['rule_id'] ) ) {
				$rule_ids[] = absint( $item_bxgy['rule_id'] );
			}

			$item_rules = $item->get_meta( 'discount_tools_rules' );
			if ( is_array( $item_rules ) ) {
				foreach ( $item_rules as $item_rule ) {
					if ( isset( $item_rule['rule_id'] ) ) {
						$rule_ids[] = absint( $item_rule['rule_id'] );
					}
				}
			}
		}

		$order_cart_items = array();
		$paid_cart_total = 0;
		$paid_cart_quantity = 0;
		$free_product_ids = array();

		foreach ( $order->get_items() as $item ) {
			$quantity = max( 0, intval( $item->get_quantity() ) );
			if ( $quantity <= 0 ) {
				continue;
			}

			$item_total = floatval( $item->get_total() );
			$product_id = absint( $item->get_product_id() );
			$variation_id = absint( $item->get_variation_id() );

			if ( $item_total <= 0 ) {
				if ( $product_id > 0 ) {
					$free_product_ids[] = $product_id;
				}
				continue;
			}

			$order_cart_items[] = array(
				'key'          => strval( $item->get_id() ),
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'quantity'     => $quantity,
				'price'        => $item_total / $quantity,
				'line_total'   => $item_total,
				'data'         => $item->get_product(),
			);

			$paid_cart_total += $item_total;
			$paid_cart_quantity += $quantity;
		}

		$free_product_ids = array_values( array_unique( array_filter( $free_product_ids ) ) );
		$rule_eval_context = array(
			'cart_total'    => $paid_cart_total,
			'cart_quantity' => $paid_cart_quantity,
			'rule_type'     => 'cart',
			'cart_items'    => $order_cart_items,
		);

		$compare_numeric = function( $actual, $operator, $expected ) {
			$actual = floatval( $actual );
			$expected = floatval( $expected );
			switch ( $operator ) {
				case 'greater_or_equal':
					return $actual >= $expected;
				case 'greater_than':
					return $actual > $expected;
				case 'less_or_equal':
					return $actual <= $expected;
				case 'less_than':
					return $actual < $expected;
				case 'equals':
					return abs( $actual - $expected ) < 0.00001;
				default:
					return true;
			}
		};

		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-rule-repository.php';
		$rule_repository = new Discount_Tools_Rule_Repository();
		// 使用優化後的查詢方法 (帶複合索引和持久緩存)
		$cart_rules = $rule_repository->find_active_by_type( 'cart' );

		foreach ( $cart_rules as $rule ) {
			// 規則已經是 active 狀態，無需再次檢查

			$discount_type = $rule->get_discount_type();
			$filtered_items = $this->rule_engine->filter_cart_items_for_rule( $order_cart_items, $rule, $rule_eval_context );

			if ( 'bundle' === $discount_type ) {
				$bundle_qty = absint( $rule->get_meta_value( 'bundle_quantity', 0 ) );
				$bundle_price = floatval( $rule->get_meta_value( 'bundle_price', 0 ) );
				if ( $bundle_qty <= 0 || $bundle_price <= 0 || empty( $filtered_items ) ) {
					continue;
				}

				$total_filtered_qty = 0;
				$bundle_applied = false;
				$bundle_unit_price = $bundle_price / $bundle_qty;

				foreach ( $filtered_items as $filtered_item ) {
					$item_qty = max( 0, intval( $filtered_item['quantity'] ) );
					$total_filtered_qty += $item_qty;
					if ( $item_qty <= 0 ) {
						continue;
					}

					$line_total = isset( $filtered_item['line_total'] )
						? floatval( $filtered_item['line_total'] )
						: ( floatval( $filtered_item['price'] ) * $item_qty );
					$unit_total = $line_total / $item_qty;
					if ( $unit_total <= ( $bundle_unit_price + 0.01 ) ) {
						$bundle_applied = true;
					}
				}

				if ( $total_filtered_qty >= $bundle_qty && $bundle_applied ) {
					$rule_ids[] = absint( $rule->get_id() );
				}

				continue;
			}

			if ( ! in_array( $discount_type, array( 'bxgy_any', 'bxgy_same' ), true ) ) {
				continue;
			}

			$gift_products = $rule->get_meta_value( 'bxgy_gift_products', array() );
			if ( ! is_array( $gift_products ) ) {
				$gift_products = array_filter( array_map( 'trim', explode( ',', strval( $gift_products ) ) ) );
			}
			$gift_products = array_map( 'absint', $gift_products );
			if ( empty( $gift_products ) || empty( $free_product_ids ) ) {
				continue;
			}

			if ( empty( array_intersect( $gift_products, $free_product_ids ) ) ) {
				continue;
			}

			$exchange_price = max( 0, floatval( $rule->get_meta_value( 'bxgy_exchange_price', 0 ) ) );
			if ( $exchange_price > 0 ) {
				continue;
			}

			$buy_qty = max( 1, absint( $rule->get_meta_value( 'bxgy_buy_quantity', 1 ) ) );
			$total_filtered_qty = 0;
			foreach ( $filtered_items as $filtered_item ) {
				$total_filtered_qty += max( 0, intval( $filtered_item['quantity'] ) );
			}
			if ( $total_filtered_qty < $buy_qty ) {
				continue;
			}

			$conditions_ok = true;
			foreach ( $rule->get_conditions() as $condition ) {
				$condition_type = $condition->get_condition_type();
				if ( 'cart_total' === $condition_type ) {
					if ( ! $compare_numeric( $paid_cart_total, $condition->get_operator(), $condition->get_value() ) ) {
						$conditions_ok = false;
						break;
					}
				}
				if ( 'cart_quantity' === $condition_type ) {
					if ( ! $compare_numeric( $paid_cart_quantity, $condition->get_operator(), $condition->get_value() ) ) {
						$conditions_ok = false;
						break;
					}
				}
			}

			if ( $conditions_ok ) {
				$rule_ids[] = absint( $rule->get_id() );
			}
		}

		// Always infer cart-fee rules from order fee names.
		global $wpdb;
		$table_rules = esc_sql( $wpdb->prefix . 'dt_rules' );
		foreach ( $order->get_fees() as $fee_item ) {
			$fee_name = trim( $fee_item->get_name() );
			if ( '' === $fee_name ) {
				continue;
			}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix; custom rules table query.
		$matched_rule_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_rules} WHERE name = %s ORDER BY id DESC LIMIT 1",
				$fee_name
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( $matched_rule_id > 0 ) {
				$rule_ids[] = $matched_rule_id;
			}
		}

		$rule_ids = array_values( array_unique( array_filter( $rule_ids ) ) );
		if ( empty( $rule_ids ) ) {
			return;
		}

		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-rule-repository.php';
		$repository = new Discount_Tools_Rule_Repository();

		foreach ( $rule_ids as $rule_id ) {
			$repository->increment_usage_count( $rule_id );
		}

		// Store applied rules in order meta for future reference
		$order->update_meta_data( '_dt_applied_rules', $rule_ids );
		$order->update_meta_data( '_dt_usage_count_tracked', 1 );
		$order->save();
	}

	/**
	 * Add remove link to WooCommerce coupon display in cart.
	 *
	 * Adds a small "移除" (remove) link next to coupon codes in cart.
	 *
	 * @since  1.0.7
	 * @param  string     $coupon_html Coupon HTML.
	 * @param  WC_Coupon  $coupon      Coupon object.
	 * @param  string     $discount_amount_html Discount amount HTML.
	 * @return string Modified coupon HTML with remove link.
	 */
	public function add_remove_link_to_coupon( $coupon_html, $coupon, $discount_amount_html ) {
		$coupon_code = $coupon->get_code();
		$remove_link = $this->generate_coupon_remove_link( $coupon_code );
		return $coupon_html . $remove_link;
	}

	/**
	 * Add remove link to WooCommerce coupon display in checkout.
	 *
	 * @since  1.0.7
	 * @param  string     $discount_html Discount HTML.
	 * @param  WC_Coupon  $coupon        Coupon object.
	 * @return string Modified discount HTML with remove link.
	 */
	public function add_remove_link_to_coupon_checkout( $discount_html, $coupon ) {
		$coupon_code = $coupon->get_code();
		$remove_link = $this->generate_coupon_remove_link( $coupon_code, true );
		return $discount_html . $remove_link;
	}

	/**
	 * Generate remove link HTML for a coupon.
	 *
	 * Uses WooCommerce's standard ?remove_coupon parameter for compatibility.
	 *
	 * @since  1.0.7
	 * @param  string $coupon_code Coupon code.
	 * @param  bool   $is_checkout Whether this is for checkout page.
	 * @return string Remove link HTML.
	 */
	private function generate_coupon_remove_link( $coupon_code, $is_checkout = false ) {
		// Get current page URL
		if ( $is_checkout ) {
			$current_url = wc_get_checkout_url();
		} else {
			$current_url = wc_get_cart_url();
		}
		
		// WooCommerce uses ?remove_coupon=code parameter
		// This will be handled by WooCommerce's core functionality
		$remove_url = add_query_arg( 'remove_coupon', rawurlencode( $coupon_code ), $current_url );
		
		// Add remove link with red color and placed after amount
		$remove_link = sprintf(
			' <a href="%s" class="woocommerce-remove-coupon" data-coupon="%s" style="font-size: 0.85em; color: #dc3545; text-decoration: none; margin-left: 5px;">%s</a>',
			esc_url( $remove_url ),
			esc_attr( $coupon_code ),
			esc_html__( '[移除]', 'discount-tools' )
		);
		
		return $remove_link;
	}

	/**
	 * AJAX handler: return map of DT fee-name → activated coupon-code.
	 *
	 * Used by checkout JS to know which coupon to remove when user clicks
	 * the remove button on a DT-fee discount row.
	 *
	 * @since  1.1.1
	 * @return void
	 */
	public function ajax_get_activation_fee_map() {
		wp_send_json_success( $this->build_activation_fee_map() );
	}

	/**
	 * Build map: fee_name => coupon_code for all active DT coupon-activation rules.
	 *
	 * @since  1.1.1
	 * @return array e.g. ['TEST COUPON' => 'testpayme']
	 */
	private function build_activation_fee_map() {
		$map = array();

		if ( ! WC()->session || ! WC()->cart ) {
			return $map;
		}

		$stored_rules = WC()->session->get( 'dt_discount_rules', array() );
		if ( empty( $stored_rules ) ) {
			return $map;
		}

		$applied = WC()->cart->get_applied_coupons();
		if ( empty( $applied ) ) {
			return $map;
		}
		$applied_lower = array_map( 'strtolower', $applied );

		foreach ( $stored_rules as $fee_name => $rule_data ) {
			// Only process coupon-activation rules
			if ( is_array( $rule_data ) ) {
				if ( empty( $rule_data['has_coupon_condition'] ) ) {
					continue;
				}
				$rule_id = intval( $rule_data['rule_id'] );
			} else {
				// Old format: just rule_id, check manually
				$rule_id = intval( $rule_data );
				if ( ! $this->rule_has_coupon_activation( $rule_id ) ) {
					continue;
				}
			}

			$coupon_codes = $this->get_rule_coupon_codes( $rule_id );
			foreach ( $coupon_codes as $code ) {
				$code_lower = strtolower( $code );
				if ( in_array( $code_lower, $applied_lower, true ) ) {
					$map[ $fee_name ] = $code_lower;
					break;
				}
			}
		}

		return $map;
	}

	/**
	 * Add JavaScript to inject remove links in custom checkout themes.
	 * 
	 * Some themes (like SF Express Checkout) use custom templates that don't
	 * trigger WooCommerce's standard coupon display filters. This JavaScript
	 * finds coupon displays and adds remove links client-side.
	 *
	 * @since  1.0.7
	 * @return void
	 */
	public function add_coupon_remove_script_checkout() {
		// Only on checkout page
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		// Always output the script (coupon may be applied via AJAX after page load)
		$ajax_url    = admin_url( 'admin-ajax.php' );
		$nonce       = wp_create_nonce( 'dt_fee_map_nonce' );
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {

			var dtAjaxUrl      = <?php echo wp_json_encode( $ajax_url ); ?>;
			var dtMapNonce     = <?php echo wp_json_encode( $nonce ); ?>;
			var dtCheckoutUrl  = <?php echo wp_json_encode( wc_get_checkout_url() ); ?>;
			var dtRemoveCouponNonce = <?php echo wp_json_encode( wp_create_nonce( 'dt_remove_activation_coupon' ) ); ?>;
			var dtI18n = {
				removeTitle  : <?php echo wp_json_encode( __( '移除優惠券', 'discount-tools' ) ); ?>,
				removeText   : <?php echo wp_json_encode( __( '（移除）', 'discount-tools' ) ); ?>,
				removingText : <?php echo wp_json_encode( __( '移除中…', 'discount-tools' ) ); ?>
			};
			var pendingFetch = false;

			/**
			 * Fetch the fee→coupon map from server, then inject remove buttons.
			 */
			function refreshDTRemoveLinks() {
				if (pendingFetch) return;
				pendingFetch = true;

				$.post(dtAjaxUrl, { action: 'dt_get_activation_fee_map', _wpnonce: dtMapNonce })
					.done(function(res) {
						if (!res || !res.success || !res.data) return;
						var feeMap = res.data; // { 'TEST COUPON': 'testpayme', ... }

						// Get sf-express nonce if available
						var sfNonce = (typeof sf_express_checkout !== 'undefined' && sf_express_checkout.nonce)
							? sf_express_checkout.nonce : '';

						// Target fee rows rendered by sf-express:
						// <div class="total-row discount-total"> (no applied-coupon-row)
						$('.total-row.discount-total').not('.applied-coupon-row').each(function() {
							var $row = $(this);
							if ($row.find('.dt-activation-remove').length) return; // already processed

							var feeName = $row.children('span').first().text().trim();
							if (!feeMap.hasOwnProperty(feeName)) return;

							var couponCode = feeMap[feeName];
							// Use direct child span (not nested) so link appears after full price text
							var $priceSpan  = $row.children('span').last();

							$priceSpan.append(
								' <a href="#" class="dt-activation-remove"' +
								' data-coupon="' + couponCode + '"' +
								' data-sf-nonce="' + sfNonce + '"' +
								' title="' + dtI18n.removeTitle + '"' +
								' style="color:#666;text-decoration:none;margin-left:4px;">' + dtI18n.removeText + '</a>'
							);
						});
					})
					.always(function() { pendingFetch = false; });
			}

			/**
			 * Event delegation: handle click on DT-injected remove buttons.
			 * Delegates to SF Express's own remove endpoint when available,
			 * falls back to our own endpoint otherwise. Never navigates to URL.
			 */
			$(document).on('click', '.dt-activation-remove', function(e) {
				e.preventDefault();
				var $btn       = $(this);
				var couponCode = $btn.data('coupon');

				if (!couponCode) return;

				$btn.text( dtI18n.removingText );

				// Use SF Express's endpoint if available (it is the active checkout layer)
				var ajaxUrl, postData;
				if (typeof sf_express_checkout !== 'undefined' && sf_express_checkout.ajax_url) {
					ajaxUrl  = sf_express_checkout.ajax_url;
					postData = {
						action:      'sf_express_remove_coupon',
						coupon_code: couponCode,
						nonce:       sf_express_checkout.nonce
					};
				} else {
					ajaxUrl  = dtAjaxUrl;
					postData = {
						action:      'dt_remove_activation_coupon',
						coupon_code: couponCode,
						_wpnonce:    dtRemoveCouponNonce
					};
				}

				$.post(ajaxUrl, postData, function(res) {
					if (res && res.success) {
						// Trigger SF Express's refreshDiscountSection() via 'removed_coupon' event.
						// SF Express listens to this event and calls refreshDiscountSection() which
						// removes all .discount-total rows and re-fetches cart totals via AJAX.
						$(document.body).trigger('removed_coupon');
						// Also trigger updated_checkout so WooCommerce core recalculates.
						$(document.body).trigger('updated_checkout');
					} else {
						// AJAX returned failure - restore button text so user can retry
						$btn.text( dtI18n.removeText );
					}
				}).fail(function() {
					// Network error - restore button so user can retry
					$btn.text( dtI18n.removeText );
				});
			});

			// Run on page load (handles case where coupon was applied before page loaded)
			setTimeout(refreshDTRemoveLinks, 200);

			// Re-run after WooCommerce AJAX updates
			$(document.body).on('updated_checkout updated_cart_totals', function() {
				setTimeout(refreshDTRemoveLinks, 150);
			});

			// MutationObserver: detect when sf-express injects new discounts_html
			var observerTimer = null;
			var observer = new MutationObserver(function(mutations) {
				var relevant = mutations.some(function(m) {
					return Array.from(m.addedNodes).some(function(node) {
						if (node.nodeType !== 1) return false;
						return node.classList.contains('discount-total') ||
						       node.classList.contains('total-row') ||
						       node.querySelector && node.querySelector('.discount-total');
					});
				});
				if (relevant) {
					clearTimeout(observerTimer);
					observerTimer = setTimeout(refreshDTRemoveLinks, 150);
				}
			});

			var observeTargets = [
				'.order-review-section',
				'.woocommerce-checkout-review-order',
				'.order-total',
				'form.checkout',
				'body'
			];
			observeTargets.forEach(function(sel) {
				var el = document.querySelector(sel);
				if (el) observer.observe(el, { childList: true, subtree: true });
			});
		});
		</script>
		<?php
	}

	/**
	 * Inject remove links into SF Express custom cart table fee rows on the cart page.
	 * The SF Express cart template renders its own table without calling
	 * woocommerce_cart_totals_fee_html, so we use JS + dt_get_activation_fee_map.
	 */
	public function add_coupon_remove_script_cart() {
		if ( ! is_cart() ) {
			return;
		}
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'dt_fee_map_nonce' );
		$cart_url = wc_get_cart_url();
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var dtAjaxUrl  = <?php echo wp_json_encode( $ajax_url ); ?>;
			var dtMapNonce = <?php echo wp_json_encode( $nonce ); ?>;
			var dtCartUrl  = <?php echo wp_json_encode( $cart_url ); ?>;
			var dtI18n = {
				removeText: <?php echo wp_json_encode( __( '（移除）', 'discount-tools' ) ); ?>
			};

			function injectCartRemoveLinks() {
				$.post(dtAjaxUrl, { action: 'dt_get_activation_fee_map', _wpnonce: dtMapNonce })
					.done(function(res) {
						if (!res || !res.success || !res.data) return;
						var feeMap = res.data;
						$('tr.fee').each(function() {
							var $row = $(this);
							// Skip if any remove link already exists (e.g. injected by PHP via woocommerce_cart_totals_fee_html)
							if ($row.find('a[href*="remove_"]').length || $row.find('.dt-cart-remove-link, .dt-remove-discount').length) return;
							var feeName = $row.find('th').text().trim();
							if (!feeMap.hasOwnProperty(feeName)) return;
							var couponCode = feeMap[feeName];
							var sep = (dtCartUrl.indexOf('?') > -1) ? '&' : '?';
							var removeUrl = dtCartUrl + sep + 'remove_coupon=' + encodeURIComponent(couponCode);
							$row.find('td').first().append(
								' <a href="' + removeUrl + '" class="dt-cart-remove-link"' +
								' style="font-size:0.85em;color:#dc3545;text-decoration:none;margin-left:5px;">' +
								dtI18n.removeText + '</a>'
							);
						});
					});
			}

			injectCartRemoveLinks();
		});
		</script>
		<?php
	}
}