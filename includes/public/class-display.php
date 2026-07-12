<?php
/**
 * Frontend Display Controller
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/public
 */

namespace Discount_Tools\Frontend;

use Discount_Tools_Rule_Engine as Rule_Engine;
use Discount_Tools_Rule_Repository as Rule_Repository;
use Discount_Tools\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display class
 */
class Display {

	/**
	 * Rule engine instance
	 *
	 * @var Rule_Engine
	 */
	private $rule_engine;

	/**
	 * Rule repository instance
	 *
	 * @var Rule_Repository
	 */
	private $rule_repository;

	/**
	 * Initialize the class
	 */
	public function __construct() {
		$this->rule_repository = new Rule_Repository();
		$this->rule_engine     = new Rule_Engine( $this->rule_repository );
	}

	/**
	 * Register hooks
	 */
	public function register_hooks() {
		// Check if plugin is enabled
		if ( ! Settings::get( 'enable_plugin', true ) ) {
			return;
		}

		// Get display position from settings
		$position = Settings::get( 'product_display_position', 'after_add_to_cart' );
		
		// Map position to WooCommerce hook and priority
		// WooCommerce standard priorities:
		// 40: woocommerce_template_single_add_to_cart (Add to Cart button)
		// 50: woocommerce_template_single_meta (Product Meta: SKU, Categories, Tags)
		$hook_config = array(
			'before_add_to_cart' => array(
				'hook' => 'woocommerce_before_add_to_cart_form',
				'priority' => 10,
			),
			'after_add_to_cart' => array(
				'hook' => 'woocommerce_after_add_to_cart_form',
				'priority' => 10,
			),
			'before_product_meta' => array(
				'hook' => 'woocommerce_single_product_summary',
				'priority' => 45, // Between add_to_cart (40) and meta (50)
			),
			'after_product_meta' => array(
				'hook' => 'woocommerce_single_product_summary',
				'priority' => 55, // After meta (50), before sharing (60)
			),
		);
		
		// Get the hook config or use default
		$config = isset( $hook_config[ $position ] ) ? $hook_config[ $position ] : array(
			'hook' => 'woocommerce_after_add_to_cart_form',
			'priority' => 10,
		);
		
		// Display discount table on product page with dynamic position
		add_action( $config['hook'], array( $this, 'display_discount_table' ), $config['priority'] );

		// Display savings badge
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_savings_badge' ), 6 );

		// Display cart rule hints on cart page (before cart table)
		add_action( 'woocommerce_before_cart', array( $this, 'display_cart_rule_hints' ), 10 );

		// Display discount info in cart
		add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'display_cart_discount' ), 10 );

		// Display savings summary on checkout
		add_action( 'woocommerce_review_order_before_order_total', array( $this, 'display_checkout_savings' ), 10 );
	}

	/**
	 * Display discount table on product page
	 */
	public function display_discount_table( $product = null ) {
		// woocommerce_after_add_to_cart_form doesn't pass product, get it globally
		if ( ! $product ) {
			global $product;
		}
		
		if ( ! $product ) {
			return;
		}

		// Check global setting first
		$global_show = Settings::get( 'show_discount_table_on_product', true );
		// Convert to boolean if needed (handle both '1' string and true boolean)
		$global_show = filter_var( $global_show, FILTER_VALIDATE_BOOLEAN );
		if ( ! $global_show ) {
			return;
		}

		// Get applicable rules for this product
		$rules = $this->get_applicable_rules( $product );

		if ( empty( $rules ) ) {
			return;
		}

		// Check if any rule allows product page display
		$show_on_product_page = false;
		foreach ( $rules as $rule ) {
			$display_setting = $rule->get_meta_value( 'display_show_on_product_page', '1' );
			// Convert to boolean (handle both '1' string and true boolean)
			if ( filter_var( $display_setting, FILTER_VALIDATE_BOOLEAN ) ) {
				$show_on_product_page = true;
				break;
			}
		}

		if ( ! $show_on_product_page ) {
			echo '<!-- Discount Tools: No rules allow product page display -->';
			return;
		}

		// Check if we should display the table
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$show_table = apply_filters( 'dt_show_discount_table', true, $product, $rules );

		if ( ! $show_table ) {
			echo '<!-- Discount Tools: Filter disabled display -->';
			return;
		}

		// Sort rules by priority (ascending: 1 = highest priority, displays first)
		usort( $rules, function( $a, $b ) {
			$priority_a = $a->get_priority();
			$priority_b = $b->get_priority();
			// Lower number = higher priority (e.g., 1 displays before 2)
			return $priority_a - $priority_b;
		});

		// Display discount table for all rules that have show_on_product_page enabled
		// This includes both product rules and cart rules
		if ( ! empty( $rules ) ) {
			$style = Settings::get( 'product_display_style', 'table' );
			include DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/partials/discount-table.php';
		}
	}

	/**
	 * Display savings badge
	 */
	public function display_savings_badge() {
		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		// Get applicable rules
		$rules = $this->get_applicable_rules( $product );

		if ( empty( $rules ) ) {
			return;
		}

		// Check if any rule allows product page display
		$show_on_product_page = false;
		foreach ( $rules as $rule ) {
			$display_setting = $rule->get_meta_value( 'display_show_on_product_page', '1' );
			if ( filter_var( $display_setting, FILTER_VALIDATE_BOOLEAN ) ) {
				$show_on_product_page = true;
				break;
			}
		}

		if ( ! $show_on_product_page ) {
			return;
		}

		// Calculate potential savings
		$original_price = $product->get_price();
		$product_id     = $product->get_id();
		$discount_result = $this->rule_engine->calculate_product_discount( $product_id, $original_price, 1 );

		if ( empty( $discount_result ) || $discount_result['total_discount'] <= 0 ) {
			return;
		}

		$total_discount = $discount_result['total_discount'];

		// Get badge text template
		$badge_text = Settings::get( 'default_badge_text', __( 'Save {discount}', 'discount-tools' ) );
		
		// Format discount
		if ( strpos( $badge_text, '{discount}' ) !== false ) {
			$discount_display = wc_price( $total_discount );
			$badge_text = str_replace( '{discount}', $discount_display, $badge_text );
		}

		// Display badge
		echo '<div class="dt-savings-badge">' . wp_kses_post( $badge_text ) . '</div>';
	}

	/**
	 * Get applicable rules for a product
	 *
	 * @param WC_Product $product Product object.
	 * @return array Array of Rule objects.
	 */
	private function get_applicable_rules( $product ) {
		// Get all active rules
		$all_rules = $this->rule_repository->find_active();

		if ( empty( $all_rules ) ) {
			return array();
		}

		$applicable_rules = array();

		foreach ( $all_rules as $rule ) {
			// Check if display on product page is enabled for this rule
			$show_on_product = $rule->get_meta_value( 'display_show_on_product_page', '1' );
			if ( ! filter_var( $show_on_product, FILTER_VALIDATE_BOOLEAN ) ) {
				continue;
			}
			
			// For cart rules, check if product qualifies
			if ( $rule->get_rule_type() === 'cart' ) {
				// Check if product matches rule conditions
				$product_qualifies = true;
				$conditions = $rule->get_conditions();
				
				foreach ( $conditions as $condition ) {
					$type = $condition->get_type();
					$operator = $condition->get_operator();
					$value = $condition->get_value();
					
					// Skip coupon_activation conditions on product pages (they can't be evaluated here)
					if ( $type === 'coupon_activation' ) {
						continue;
					}
					
					// Ensure value is array for comparison
					if ( ! is_array( $value ) ) {
						$value = array( $value );
					}
					
					// Check product inclusion (product must be in the list)
					if ( $type === 'product' && $operator === 'in' ) {
						$product_id = $product->get_id();
						if ( ! in_array( $product_id, array_map( 'intval', $value ) ) ) {
							$product_qualifies = false;
							break;
						}
					}
					
					// Check product exclusion
					if ( $type === 'product' && $operator === 'not_in' ) {
						$product_id = $product->get_id();
						if ( in_array( $product_id, array_map( 'intval', $value ) ) ) {
							$product_qualifies = false;
							break;
						}
					}
					
					// Check category inclusion
					if ( $type === 'product_category' && $operator === 'in' ) {
						$product_categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
						if ( empty( array_intersect( $product_categories, array_map( 'intval', $value ) ) ) ) {
							$product_qualifies = false;
							break;
						}
					}
					
					// Check category exclusion
					if ( $type === 'product_category' && $operator === 'not_in' ) {
						$product_categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
						if ( ! empty( array_intersect( $product_categories, array_map( 'intval', $value ) ) ) ) {
							$product_qualifies = false;
							break;
						}
					}
					
					// Check brand inclusion
					if ( $type === 'brand' && $operator === 'in' ) {
						$product_brands = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'ids' ) );
						if ( empty( array_intersect( $product_brands, array_map( 'intval', $value ) ) ) ) {
							$product_qualifies = false;
							break;
						}
					}
					
					// Check brand exclusion
					if ( $type === 'brand' && $operator === 'not_in' ) {
						$product_brands = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'ids' ) );
						if ( ! empty( array_intersect( $product_brands, array_map( 'intval', $value ) ) ) ) {
							$product_qualifies = false;
							break;
						}
					}
				}
				
				if ( $product_qualifies ) {
					$applicable_rules[] = $rule;
				}
				continue;
			}

			// For product rules, check if rule applies to this product
			// Must strictly match rule conditions
			$context = array(
				'product' => $product,
				'quantity' => 1,
			);

			if ( $this->rule_engine->test_rule_conditions( $rule, $context ) ) {
				$applicable_rules[] = $rule;
			}
		}

		return $applicable_rules;
	}

	/**
	 * Get discount tiers for display
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $rules Array of Rule objects.
	 * @return array Discount tiers.
	 */
	public function get_discount_tiers( $product, $rules ) {
		$tiers = array();
		
		// Get the base price from product metadata (bypass WooCommerce filters)
		// This prevents double-discounting when product hooks already apply discounts
		if ( $product->is_type( 'variation' ) ) {
			$base_price = get_post_meta( $product->get_id(), '_regular_price', true );
			if ( empty( $base_price ) ) {
				$base_price = get_post_meta( $product->get_id(), '_price', true );
			}
		} else {
			$base_price = get_post_meta( $product->get_id(), '_regular_price', true );
			if ( empty( $base_price ) ) {
				$base_price = get_post_meta( $product->get_id(), '_price', true );
			}
		}
		
		// Fallback to product methods if metadata not available
		if ( empty( $base_price ) ) {
			$base_price = $product->get_regular_price() ? $product->get_regular_price() : $product->get_price();
		}
		
		$base_price = floatval( $base_price );

		// Generate quantity tiers for display
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$quantities = apply_filters( 'dt_discount_table_quantities', array( 1, 2, 5, 10, 20, 50 ), $product );

		foreach ( $quantities as $qty ) {
			// Build context with simulated cart total for cart rules
			$simulated_cart_total = $base_price * $qty;
			$context = array(
				'product'      => $product,
				'quantity'     => $qty,
				'cart_total'   => $simulated_cart_total,
				'cart_quantity' => $qty,
				'product_id'   => $product->get_id(),
			);

			$discounted_price = $base_price;
			$discount_amount  = 0;
			$applied_rules    = array();

			foreach ( $rules as $rule ) {
				// For cart rules on product pages, skip condition checking
				// and show discount as promotional info
				$skip_conditions = ( $rule->get_rule_type() === 'cart' );
				
				if ( ! $skip_conditions && ! $this->rule_engine->test_rule_conditions( $rule, $context ) ) {
					continue;
				}

				$product_id = $product->get_id();
				
				// Calculate discount based on rule type
				if ( $rule->get_discount_type() === 'percentage' ) {
					$discount = ( $discounted_price * $rule->get_discount_value() ) / 100;
					$discount_amount += $discount;
					$discounted_price -= $discount;
					$applied_rules[] = $rule->get_name();
				} elseif ( $rule->get_discount_type() === 'fixed_amount' ) {
					$discount = min( $rule->get_discount_value(), $discounted_price );
					$discount_amount += $discount;
					$discounted_price -= $discount;
					$applied_rules[] = $rule->get_name();
				}
			}

			if ( $discount_amount > 0 ) {
				$tiers[] = array(
					'quantity'         => $qty,
					'original_price'   => $base_price,
					'discounted_price' => max( 0, $discounted_price ),
					'discount_amount'  => $discount_amount,
					'discount_percent' => ( $discount_amount / $base_price ) * 100,
					'savings'          => $base_price - max( 0, $discounted_price ),
					'rules'            => $applied_rules,
				);
			}
		}

		return $tiers;
	}

	/**
	 * Format discount for display
	 *
	 * @param float  $amount Discount amount.
	 * @param string $type Discount type.
	 * @return string Formatted discount.
	 */
	public function format_discount( $amount, $type = 'percentage' ) {
		if ( $type === 'percentage' ) {
			return round( $amount, 1 ) . '%';
		}

		return wc_price( $amount );
	}

	/**
	 * Display discount information in cart
	 */
	/**
	 * Display cart rule hints on cart page
	 * Shows promotional messages for cart-type discount rules that are actually applied
	 */
	public function display_cart_rule_hints() {
		// Get applied discounts from session (set by cart-hooks.php)
		$applied_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
		
		if ( empty( $applied_discounts ) || empty( $applied_discounts['rules_applied'] ) ) {
			return;
		}
		
		// Get cart
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}
		
		// Convert applied rules to rule objects
		$cart_rules = array();
		foreach ( $applied_discounts['rules_applied'] as $applied_rule ) {
			$rule_id = isset( $applied_rule['rule_id'] ) ? intval( $applied_rule['rule_id'] ) : 0;
			if ( $rule_id > 0 ) {
				$rule = $this->rule_repository->find( $rule_id );
				if ( $rule && $rule->is_active() ) {
					$cart_rules[] = $rule;
				}
			}
		}
		
		if ( empty( $cart_rules ) ) {
			return;
		}
		
		// Remove duplicate cart total/quantity evaluation logic
		// since rules have already been evaluated and applied by rule engine
		$qualified_rules = $cart_rules;
		
		// Sort by priority (lower number = higher priority)
		usort( $qualified_rules, function( $a, $b ) {
			$priority_a = $a->get_priority();
			$priority_b = $b->get_priority();
			return $priority_a - $priority_b;
		});
		
		// Set cart_rules to qualified rules for template
		$cart_rules = $qualified_rules;
		
		// Load the cart rule hint template
		include DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/partials/cart-rule-hint.php';
	}

	public function display_cart_discount() {
		// Load cart discount template
		include DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/partials/cart-discount.php';

		// Allow third-party extensions to add content
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'dt_after_cart_discount_display' );
	}

	/**
	 * Display savings summary on checkout page
	 */
	public function display_checkout_savings() {
		// Disabled per user request - they don't want this display
		return;
		
		// Prevent duplicate display
		static $displayed = false;
		if ( $displayed ) {
			return;
		}
		$displayed = true;

		// Load checkout savings template
		include DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/public/partials/checkout-savings.php';

		// Allow third-party extensions to add content
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'dt_after_checkout_savings_display' );
	}
}
