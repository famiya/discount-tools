<?php
/**
 * Rule Engine
 *
 * Core engine that orchestrates discount rule application.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 */

/**
 * Rule engine class.
 *
 * Integrates all engine components to apply discount rules.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Rule_Engine {

	/**
	 * Calculator instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Calculator
	 */
	private $calculator;

	/**
	 * Condition evaluator instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Condition_Evaluator
	 */
	private $evaluator;

	/**
	 * Validator instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Validator
	 */
	private $validator;

	/**
	 * Priority manager instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Priority_Manager
	 */
	private $priority_manager;

	/**
	 * Rule repository instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Rule_Repository
	 */
	private $rule_repository;

	/**
	 * Debug mode flag.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    bool
	 */
	private $debug_mode = false;

	/**
	 * Debug log.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $debug_log = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->calculator = new Discount_Tools_Calculator();
		$this->evaluator = new Discount_Tools_Condition_Evaluator();
		$this->validator = new Discount_Tools_Validator();
		$this->priority_manager = new Discount_Tools_Priority_Manager();
		$this->rule_repository = new Discount_Tools_Rule_Repository();

		// Enable debug mode if WP_DEBUG is on
		$this->debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Apply discount rules to a product.
	 *
	 * @since  1.0.0
	 * @param  int   $product_id Product ID.
	 * @param  float $price      Original price.
	 * @param  int   $quantity   Quantity.
	 * @param  array $context    Additional context.
	 * @return float             Discounted price.
	 */
	public function apply_product_discount( $product_id, $price, $quantity = 1, $context = array() ) {
		$this->log( "=== Apply Product Discount ===" );
		$this->log( "Product ID: {$product_id}" );
		$this->log( "Original Price: {$price}" );
		$this->log( "Quantity: {$quantity}" );

		// Check if discount rules are disabled due to coupon interaction mode
		if ( $this->should_disable_discount_rules() ) {
			$this->log( "Discount rules disabled by Coupon Integration settings" );
			return $price;
		}

		// Get applicable rules
		$rules = $this->get_applicable_rules( 'product', $product_id, $context );
		$this->log( "Found " . count( $rules ) . " applicable rules" );

		if ( empty( $rules ) ) {
			$this->log( "No applicable rules, returning original price" );
			return $price;
		}

		// Get rule application strategy from settings
		$settings = get_option( 'discount_tools_settings', array() );
		$strategy = isset( $settings['rule_application_strategy'] ) ? $settings['rule_application_strategy'] : 'priority';
		$this->log( "Applying strategy: {$strategy}" );

		// Apply strategy to select which rules to use
		$context = array(
			'price'    => $price,
			'quantity' => $quantity,
		);
		$selected_rules = $this->priority_manager->apply_strategy( $rules, $strategy, $context );
		$this->log( count( $selected_rules ) . " rules selected by {$strategy} strategy" );

		if ( empty( $selected_rules ) ) {
			$this->log( "No rules selected by strategy, returning original price" );
			return $price;
		}

		// Apply selected rules
		$discounted_price = $price;
		$total_discount = 0;

		foreach ( $selected_rules as $rule ) {
			$this->log( "Applying rule: " . $rule->get_name() );

			// Calculate discount for this rule
			$discount_result = $this->calculator->calculate(
				$discounted_price,
				$rule->get_discount_type(),
				$rule->get_discount_value(),
				$quantity
			);

			// Extract discount amount from result
			$discount_amount = isset( $discount_result['discount_amount'] ) ? $discount_result['discount_amount'] : 0;

			// Apply constraints
			$discount_amount = $this->apply_constraints( $discount_amount, $rule );

			$this->log( "Discount amount: {$discount_amount}" );

			// Use unit_price from calculator - it already has discount applied
			// Do NOT subtract discount_amount again to avoid double-discounting
			$discounted_price = isset( $discount_result['unit_price'] ) ? $discount_result['unit_price'] : $discounted_price;
			$total_discount += $discount_amount;
		}

		// Ensure price is never negative
		$discounted_price = max( 0, $discounted_price );
		$this->log( "Final price: {$discounted_price} (saved: {$total_discount})" );

		return $discounted_price;
	}

	/**
	 * Apply discount rules to cart.
	 *
	 * @since  1.0.0
	 * @param  array $cart_items Cart items array.
	 * @param  array $context    Additional context.
	 * @return array             Discount details.
	 */
	public function apply_cart_discount( $cart_items, $context = array() ) {
		$this->log( "=== Apply Cart Discount ===" );
		$this->log( "Cart items: " . count( $cart_items ) );

		// Check if discount rules are disabled due to coupon interaction mode
		if ( $this->should_disable_discount_rules() ) {
			$this->log( "Discount rules disabled by Coupon Integration settings" );
			return array(
				'total_discount' => 0,
				'rules_applied'  => array(),
			);
		}

		// Calculate cart total (for reference only)
		// IMPORTANT: Exclude free products/gifts from other plugins
		$cart_total = 0;
		$cart_quantity = 0;
		foreach ( $cart_items as $item ) {
			// Skip items with 0 or negative price (free gifts from any plugin)
			if ( isset( $item['price'] ) && floatval( $item['price'] ) <= 0 ) {
				continue;
			}
			
			$cart_total += $item['price'] * $item['quantity'];
			$cart_quantity += $item['quantity'];
		}

		$this->log( "Cart total (unfiltered, excluding free gifts): {$cart_total}" );
		$this->log( "Cart quantity (unfiltered, excluding free gifts): {$cart_quantity}" );

		// Get all active cart rules (使用優化後的查詢方法)
		$all_rules = $this->rule_repository->find_active_by_type( 'cart' );
		$rules = array();
		foreach ( $all_rules as $rule ) {
			if ( ! $rule->is_within_date_range() || ! $rule->is_within_usage_limit() ) {
				continue;
			}
			
			$conditions = $rule->get_conditions();
			
			// For rules with not_in conditions (like Rule 6 & 7), we need to:
			// 1. Filter items first
			// 2. Calculate filtered cart total
			// 3. Evaluate conditions using filtered total for cart_total comparisons
			$has_not_in_conditions = false;
			if ( ! empty( $conditions ) ) {
				foreach ( $conditions as $condition ) {
					$condition_type = $condition->get_condition_type();
					$operator = $condition->get_operator();
					// Check if this is a not_in condition for categories or brands
					if ( $operator === 'not_in' && 
					     ( $condition_type === 'product_category' || $condition_type === 'brand' ) ) {
						$has_not_in_conditions = true;
						break;
					}
				}
			}
			
			if ( $has_not_in_conditions ) {
				// Filter items based on not_in conditions
				$filtered_items = $this->filter_cart_items_for_rule( $cart_items, $rule, $context );
				
				// Calculate filtered cart total
				$filtered_total = 0;
				foreach ( $filtered_items as $item ) {
					if ( isset( $item['discount_tools_free_product'] ) && $item['discount_tools_free_product'] === true ) {
						continue;
					}
					if ( isset( $item['line_total'] ) ) {
						$filtered_total += floatval( $item['line_total'] );
					} else {
						$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
						$qty = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;
						$filtered_total += $price * $qty;
					}
				}

				// Create evaluation context with filtered total
				$eval_context = $context;
				$eval_context['cart_total'] = $filtered_total;

				// For cart-level evaluation, skip item-level conditions (brand, product_category, product).
				// Those conditions are already handled by filter_cart_items_for_rule() above.
				// Evaluating them here against cart_brands (all brands) would cause false negatives.
				$item_level_types = array( 'product', 'brand', 'product_category', 'product_tag' );
				$cart_only_conditions = array_filter( $conditions, function( $c ) use ( $item_level_types ) {
					return ! in_array( $c->get_type(), $item_level_types, true );
				} );

				// Evaluate only cart-level conditions (e.g. cart_total >= 1000)
				if ( empty( $cart_only_conditions ) || $this->evaluator->evaluate( $cart_only_conditions, $eval_context ) ) {
					$rules[] = $rule;
				}
			} else {
				// Standard evaluation using original context
				if ( empty( $conditions ) || $this->evaluator->evaluate( $conditions, $context ) ) {
					$rules[] = $rule;
				}
			}
		}
		
		$this->log( "Found " . count( $rules ) . " applicable cart rules (after condition evaluation)" );

		if ( empty( $rules ) ) {
			$this->log( "No active cart rules after condition evaluation" );
			return array(
				'total_discount' => 0,
				'rules_applied'  => array(),
			);
		}

		// Get rule application strategy from settings
		$settings = get_option( 'discount_tools_settings', array() );
		$strategy = isset( $settings['rule_application_strategy'] ) ? $settings['rule_application_strategy'] : 'priority';

		// For 'highest' and 'lowest' strategies, we need to calculate filtered totals
		// for each rule BEFORE selecting which rule to apply
		if ( in_array( $strategy, array( 'highest', 'lowest' ), true ) ) {
			$rules_with_filtered_totals = array();
			
			foreach ( $rules as $rule ) {
				// Filter items for this rule
				$filtered_items = $this->filter_cart_items_for_rule( $cart_items, $rule, $context );
				
				// Ensure filtered_items is an array
				if ( ! is_array( $filtered_items ) ) {
					$filtered_items = array();
				}
				
				
				// Calculate filtered total
				$filtered_total = 0;
				$filtered_qty = 0;
				foreach ( $filtered_items as $item ) {
					$filtered_total += $item['price'] * $item['quantity'];
					$filtered_qty += $item['quantity'];
				}
				
				
				$this->log( "Rule {$rule->get_id()} ({$rule->get_name()}): filtered_total = {$filtered_total}" );
				
				// Store rule with its filtered context
				$rules_with_filtered_totals[] = array(
					'rule'           => $rule,
					'filtered_total' => $filtered_total,
					'filtered_qty'   => $filtered_qty,
					'filtered_items' => $filtered_items,
				);
			}
			
			// Now apply strategy using filtered totals
			$selected_rule_data = $this->select_rule_by_strategy( $rules_with_filtered_totals, $strategy );
			
			
			if ( empty( $selected_rule_data ) ) {
				return array(
					'total_discount' => 0,
					'rules_applied'  => array(),
				);
			}
			
			// Extract selected rules (strategy already filtered to one rule)
			$selected_rules = array( $selected_rule_data['rule'] );
			$this->log( "Selected rule by {$strategy} strategy: " . $selected_rule_data['rule']->get_name() . " (discount: " . $selected_rule_data['discount'] . ")" );
			
		} else {
			// For other strategies, use original logic
			$strategy_context = array(
				'cart_total' => $cart_total,
				'quantity'   => $cart_quantity,
			);
			$selected_rules = $this->priority_manager->apply_strategy( $rules, $strategy, $strategy_context );
			$this->log( count( $selected_rules ) . " rules selected by {$strategy} strategy" );

			if ( empty( $selected_rules ) ) {
				$this->log( "No rules selected by strategy" );
				return array(
					'total_discount' => 0,
					'rules_applied'  => array(),
				);
			}
		}

		// Apply cart rules
		$total_discount = 0;
		$rules_applied = array();

		// For 'highest' and 'lowest' strategies, we already have the filtered items
		// from the selection process. Use that data to avoid recalculating.
		$use_prefiltered_data = in_array( $strategy, array( 'highest', 'lowest' ), true ) && isset( $selected_rule_data );

		foreach ( $selected_rules as $rule ) {
			$this->log( "Applying cart rule: " . $rule->get_name() . " (ID: " . $rule->get_id() . ")" );

			// Log conditions for debugging
			$conditions = $rule->get_conditions();
			$this->log( "Rule has " . count($conditions) . " conditions:" );
			foreach ( $conditions as $cond ) {
				$this->log( "  - Type: " . $cond->get_type() . ", Operator: " . $cond->get_operator() . ", Value: " . json_encode($cond->get_value()) . ", Group: " . $cond->get_group_id() );
			}

			// Filter cart items based on rule conditions (handle not_in logic)
			if ( $use_prefiltered_data ) {
				// Use pre-calculated filtered items from strategy selection
				$filtered_items = $selected_rule_data['filtered_items'];
				$this->log( "Using pre-filtered items from strategy selection" );
			} else {
				// Calculate filtered items now
				$filtered_items = $this->filter_cart_items_for_rule( $cart_items, $rule, $context );
			}
			
			$this->log( "After filtering: " . count($filtered_items) . " items remain (original: " . count($cart_items) . ")" );
			
			if ( empty( $filtered_items ) ) {
				$this->log( "No items match rule conditions after filtering" );
				continue;
			}

			// Recalculate cart total and quantity with filtered items only
			// IMPORTANT: Exclude free products/gifts
			$filtered_cart_total = 0;
			$filtered_cart_quantity = 0;
			foreach ( $filtered_items as $item ) {
				// Skip items with 0 or negative price (free gifts)
				if ( isset( $item['price'] ) && floatval( $item['price'] ) <= 0 ) {
					continue;
				}
				
				$filtered_cart_total += $item['price'] * $item['quantity'];
				$filtered_cart_quantity += $item['quantity'];
			}

			$this->log( "Filtered cart total: {$filtered_cart_total} (original: {$cart_total})" );
			$this->log( "Filtered cart quantity: {$filtered_cart_quantity} (original: {$cart_quantity})" );

			// Note: Threshold conditions (cart_total, coupon_activation, etc.) were already
			// evaluated with ORIGINAL cart in the initial rule selection (lines 225-256).
			// The not_in filters only determine which items receive discount, not whether the rule applies.
			// This allows "Spend $600 total, get 10% off non-sale items" behavior.

		$this->log( "Applying discount to {$filtered_cart_total} (filtered from {$cart_total})" );

		// Check if this is a Buy X Get Y or Bundle discount type
		$discount_type = $rule->get_discount_type();
		if ( in_array( $discount_type, array( 'bxgy_same', 'bxgy_any' ), true ) ) {
			// Get BXGY configuration from rule meta
			$buy_qty = $rule->get_meta_value( 'bxgy_buy_quantity' );
			$get_qty = $rule->get_meta_value( 'bxgy_get_quantity' );
			$get_discount = $rule->get_meta_value( 'bxgy_get_discount' );
			$get_type = $rule->get_meta_value( 'bxgy_get_type' );
			
			// Set defaults if meta values are missing
			$buy_qty = ! empty( $buy_qty ) ? intval( $buy_qty ) : 2;
			$get_qty = ! empty( $get_qty ) ? intval( $get_qty ) : 1;
			$repeating = $rule->get_meta_value( 'bxgy_repeating' );
			$repeating = ( $repeating === '1' || $repeating === 1 || $repeating === true );
			
			$this->log( "BXGY Config - Buy: {$buy_qty}, Get: {$get_qty} (100% Free), Repeating: " . ( $repeating ? 'Yes' : 'No' ) . ", Mode: {$discount_type}" );
			
			// Calculate BXGY discount (always 100% free)
			$discount_result = $this->calculator->calculate_bxgy_discount(
				$filtered_items,
				$buy_qty,
				$get_qty,
				$repeating,
				$discount_type
			);
		} elseif ( $discount_type === 'bundle' ) {
			// Get Bundle configuration from rule meta
			$bundle_qty = $rule->get_meta_value( 'bundle_quantity' );
			$bundle_price = $rule->get_meta_value( 'bundle_price' );
			$repeating = $rule->get_meta_value( 'bundle_repeating' );
			
			// Set defaults if meta values are missing
			$bundle_qty = ! empty( $bundle_qty ) ? intval( $bundle_qty ) : 2;
			$bundle_price = ! empty( $bundle_price ) ? floatval( $bundle_price ) : 0;
			$repeating = ( $repeating === '1' || $repeating === 1 || $repeating === true );
			
			$this->log( "Bundle Config - Quantity: {$bundle_qty}, Price: {$bundle_price}, Repeating: " . ( $repeating ? 'Yes' : 'No' ) );
			
			// Calculate Bundle discount
			$discount_result = $this->calculator->calculate_bundle_discount(
				$filtered_items,
				$bundle_qty,
				$bundle_price,
				$repeating
			);
		} else {
			// Calculate standard discount on filtered items only
			$discount_result = $this->calculator->calculate_cart_discount(
				$filtered_items,
				$discount_type,
				$rule->get_discount_value()
			);
		}

		$discount_amount = $discount_result['discount_amount'];

		// Apply constraints
		$discount_amount = $this->apply_constraints( $discount_amount, $rule );

		$this->log( "Cart discount amount: {$discount_amount}" );

		$total_discount += $discount_amount;
		$rule_info = array(
			'rule_id'   => $rule->get_id(),
			'rule_name' => $rule->get_name(),
			'discount'  => $discount_amount,
			'type'      => $discount_type,
		);
		
		// Add bundle info if available
		if ( isset( $discount_result['bundle_info'] ) ) {
			$rule_info['bundle_info'] = $discount_result['bundle_info'];
		}
		
		$rules_applied[] = $rule_info;
		}

		$this->log( "Total cart discount: {$total_discount}" );

		return array(
			'total_discount' => $total_discount,
			'rules_applied'  => $rules_applied,
		);
	}

	/**
	 * Get applicable rules for a context.
	 *
	 * @since  1.0.0
	 * @param  string $rule_type Rule type (product or cart).
	 * @param  int    $product_id Product ID (for product rules).
	 * @param  array  $context   Evaluation context.
	 * @return array             Applicable rules.
	 */
	public function get_applicable_rules( $rule_type, $product_id = null, $context = array() ) {
		$this->log( "Getting applicable rules for type: {$rule_type}" );

		// 使用優化後的查詢方法 (帶複合索引和持久緩存)
		$active_rules = $this->rule_repository->find_active_by_type( $rule_type );

		$this->log( "Found " . count( $active_rules ) . " active rules" );

		// Add rule_type to context for condition evaluation
		// This allows not_in conditions on product/category/brand to be skipped for cart rules
		$context['rule_type'] = $rule_type;

		// Add product_id to context if provided
		if ( $product_id ) {
			$context['product_id'] = $product_id;
		}

		// Item-level condition types: evaluated per-item later, not at cart level.
		// Skipping them here prevents false negatives when e.g. SEIKO is in cart
		// but the rule should still apply to non-SEIKO items.
		$item_level_condition_types = array( 'product', 'brand', 'product_category', 'product_tag' );

		// Filter by conditions
		$applicable_rules = array();
		foreach ( $active_rules as $rule ) {
			// Check if rule is within date range
			if ( ! $rule->is_within_date_range() ) {
				$this->log( "Rule '{$rule->get_name()}' is outside date range" );
				continue;
			}

			// Check usage limit
			if ( ! $rule->is_within_usage_limit() ) {
				$this->log( "Rule '{$rule->get_name()}' has reached usage limit" );
				continue;
			}

			// Evaluate conditions.
			// When no specific product_id in context (cart-level check), skip item-level
			// conditions (brand, product_category, product, product_tag) – they will be
			// re-evaluated at per-item level in the caller (class-cart-hooks.php).
			$conditions = $rule->get_conditions();
			$cart_level_only = ! isset( $context['product_id'] );
			if ( $cart_level_only ) {
				$conditions_to_eval = array_filter( $conditions, function( $c ) use ( $item_level_condition_types ) {
					return ! in_array( $c->get_type(), $item_level_condition_types, true );
				} );
			} else {
				$conditions_to_eval = $conditions;
			}

			$eval_result = $this->evaluator->evaluate( $conditions_to_eval, $context );
			if ( $eval_result ) {
				$this->log( "Rule '{$rule->get_name()}' conditions passed" );
				$applicable_rules[] = $rule;
			} else {
				$this->log( "Rule '{$rule->get_name()}' conditions failed" );
			}
		}

		$this->log( count( $applicable_rules ) . " rules passed all checks" );
		return $applicable_rules;
	}

	/**
	 * Calculate discount for a product without applying.
	 *
	 * Used for price display and previews.
	 *
	 * @since  1.0.0
	 * @param  int   $product_id Product ID.
	 * @param  float $price      Original price.
	 * @param  int   $quantity   Quantity.
	 * @param  array $context    Additional context.
	 * @return array             Discount details.
	 */
	public function calculate_product_discount( $product_id, $price, $quantity = 1, $context = array() ) {
		$this->log( "=== Calculate Product Discount (Preview) ===" );

		// Get applicable rules
		$rules = $this->get_applicable_rules( 'product', $product_id, $context );

		if ( empty( $rules ) ) {
			return array(
				'original_price'   => $price,
				'discounted_price' => $price,
				'total_discount'   => 0,
				'discount_percent' => 0,
				'rules_applied'    => array(),
			);
		}

		// Sort and filter rules
		$sorted_rules = $this->priority_manager->sort_rules( $rules );
		$applicable_rules = $this->priority_manager->filter_applicable_rules( $sorted_rules );

		// Calculate discounts
		$discounted_price = $price;
		$total_discount = 0;
		$rules_applied = array();

		foreach ( $applicable_rules as $rule ) {
			$discount_result = $this->calculator->calculate(
				$discounted_price,
				$rule->get_discount_type(),
				$rule->get_discount_value(),
				$quantity
			);

			// Extract discount amount from result
			$discount_amount = isset( $discount_result['discount_amount'] ) ? $discount_result['discount_amount'] : 0;

			// Apply constraints
			$discount_amount = $this->apply_constraints( $discount_amount, $rule );

			// Use unit_price from calculator - it already has discount applied
			// Do NOT subtract discount_amount again to avoid double-discounting
			$discounted_price = isset( $discount_result['unit_price'] ) ? $discount_result['unit_price'] : $discounted_price;
			$total_discount += $discount_amount;

			$rules_applied[] = array(
				'rule_id'        => $rule->get_id(),
				'rule_name'      => $rule->get_name(),
				'discount_type'  => $rule->get_discount_type(),
				'discount_value' => $rule->get_discount_value(),
				'discount_amount' => $discount_amount,
			);
		}

		$discounted_price = max( 0, $discounted_price );
		$discount_percent = $price > 0 ? ( $total_discount / $price ) * 100 : 0;

		return array(
			'original_price'   => $price,
			'discounted_price' => $discounted_price,
			'total_discount'   => $total_discount,
			'discount_percent' => round( $discount_percent, 2 ),
			'rules_applied'    => $rules_applied,
		);
	}

	/**
	 * Validate a rule before saving.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return bool                      True if valid.
	 */
	public function validate_rule( $rule ) {
		$this->log( "Validating rule: " . $rule->get_name() );

		$result = $this->validator->validate( $rule );

		if ( ! $result ) {
			$errors = $this->validator->get_errors();
			$this->log( "Validation failed: " . count( $errors ) . " errors" );
			foreach ( $errors as $error ) {
				$this->log( "  - {$error['field']}: {$error['message']}" );
			}
		} else {
			$this->log( "Validation passed" );
		}

		return $result;
	}

	/**
	 * Get validation errors.
	 *
	 * @since  1.0.0
	 * @return array Validation errors.
	 */
	public function get_validation_errors() {
		return $this->validator->get_errors();
	}

	/**
	 * Get validation warnings.
	 *
	 * @since  1.0.0
	 * @return array Validation warnings.
	 */
	public function get_validation_warnings() {
		return $this->validator->get_warnings();
	}

	/**
	 * Apply discount constraints (max/min).
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  float               $discount_amount Calculated discount.
	 * @param  Discount_Tools_Rule $rule           Rule object.
	 * @return float                               Constrained discount.
	 */
	private function apply_constraints( $discount_amount, $rule ) {
		$max_discount = $rule->get_max_discount();
		$min_discount = $rule->get_min_discount();

		$original_amount = $discount_amount;

		// Apply max constraint
		if ( $max_discount !== null && $discount_amount > $max_discount ) {
			$discount_amount = $max_discount;
			$this->log( "Max discount constraint applied: {$original_amount} -> {$discount_amount}" );
		}

		// Apply min constraint
		if ( $min_discount !== null && $discount_amount < $min_discount ) {
			$discount_amount = $min_discount;
			$this->log( "Min discount constraint applied: {$original_amount} -> {$discount_amount}" );
		}

		return $discount_amount;
	}

	/**
	 * Enable debug mode.
	 *
	 * @since  1.0.0
	 * @param  bool $enable Enable or disable.
	 * @return void
	 */
	public function set_debug_mode( $enable ) {
		$this->debug_mode = $enable;
	}

	/**
	 * Get debug log.
	 *
	 * @since  1.0.0
	 * @return array Debug log entries.
	 */
	public function get_debug_log() {
		return $this->debug_log;
	}

	/**
	 * Clear debug log.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function clear_debug_log() {
		$this->debug_log = array();
	}

	/**
	 * Get condition evaluator instance.
	 *
	 * @since  1.0.6
	 * @return Discount_Tools_Condition_Evaluator
	 */
	public function get_evaluator() {
		return $this->evaluator;
	}

	/**
	 * Log debug message.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $message Message to log.
	 * @return void
	 */
	private function log( $message ) {
		if ( ! $this->debug_mode ) {
			return;
		}

		$entry = array(
			'time'    => current_time( 'mysql' ),
			'message' => $message,
		);

		$this->debug_log[] = $entry;
	}

	/**
	 * Get rule engine statistics.
	 *
	 * @since  1.0.0
	 * @return array Statistics.
	 */
	public function get_statistics() {
		$active_rules = $this->rule_repository->find_active();
		$all_rules = $this->rule_repository->find_all();

		$product_rules = $this->priority_manager->filter_by_type( $active_rules, 'product' );
		$cart_rules = $this->priority_manager->filter_by_type( $active_rules, 'cart' );

		$stackable_rules = $this->priority_manager->filter_stackable( $active_rules );
		$non_stackable_rules = $this->priority_manager->filter_non_stackable( $active_rules );

		return array(
			'total_rules'         => count( $all_rules ),
			'active_rules'        => count( $active_rules ),
			'product_rules'       => count( $product_rules ),
			'cart_rules'          => count( $cart_rules ),
			'stackable_rules'     => count( $stackable_rules ),
			'non_stackable_rules' => count( $non_stackable_rules ),
		);
	}

	/**
	 * Get rule engine status.
	 *
	 * @since  1.0.0
	 * @return array Status information.
	 */
	public function get_status() {
		return array(
			'calculator_loaded'  => isset( $this->calculator ),
			'evaluator_loaded'   => isset( $this->evaluator ),
			'validator_loaded'   => isset( $this->validator ),
			'priority_manager_loaded' => isset( $this->priority_manager ),
			'repository_loaded'  => isset( $this->rule_repository ),
			'debug_mode'         => $this->debug_mode,
			'log_entries'        => count( $this->debug_log ),
		);
	}

	/**
	 * Test rule conditions without applying.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $rule    Rule to test.
	 * @param  array               $context Test context.
	 * @return bool                         True if conditions pass.
	 */
	public function test_rule_conditions( $rule, $context = array() ) {
		$this->log( "Testing rule conditions: " . $rule->get_name() );

		$conditions = $rule->get_conditions();
		$result = $this->evaluator->evaluate( $conditions, $context );

		$this->log( "Test result: " . ( $result ? 'PASS' : 'FAIL' ) );

		return $result;
	}

	/**
	 * Get applicable rules summary.
	 *
	 * @since  1.0.0
	 * @param  string $rule_type Rule type.
	 * @param  int    $product_id Product ID (optional).
	 * @param  array  $context   Context.
	 * @return array             Summary information.
	 */
	public function get_applicable_rules_summary( $rule_type, $product_id = null, $context = array() ) {
		$rules = $this->get_applicable_rules( $rule_type, $product_id, $context );
		$sorted_rules = $this->priority_manager->sort_rules( $rules );
		$applicable_rules = $this->priority_manager->filter_applicable_rules( $sorted_rules );

		return array(
			'total_rules'       => count( $rules ),
			'applicable_rules'  => count( $applicable_rules ),
			'rules'             => array_map(
				function ( $rule ) {
					return array(
						'id'            => $rule->get_id(),
						'name'          => $rule->get_name(),
						'type'          => $rule->get_rule_type(),
						'discount_type' => $rule->get_discount_type(),
						'discount_value' => $rule->get_discount_value(),
						'priority'      => $rule->get_priority(),
						'stackable'     => $rule->is_stackable(),
					);
				},
				$applicable_rules
			),
		);
	}

	/**
	 * Bulk validate rules.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Validation results.
	 */
	public function bulk_validate_rules( $rules ) {
		$results = array();

		foreach ( $rules as $rule ) {
			$valid = $this->validator->validate( $rule );
			$results[] = array(
				'rule_id' => $rule->get_id(),
				'rule_name' => $rule->get_name(),
				'valid'   => $valid,
				'errors'  => $this->validator->get_errors(),
				'warnings' => $this->validator->get_warnings(),
			);
			$this->validator->clear();
		}

		return $results;
	}

	/**
	 * Filter cart items based on rule conditions.
	 * This handles "not_in" conditions by excluding matching items
	 * rather than failing the entire rule.
	 *
	 * @since  1.0.5
	 * @param  array $cart_items Cart items.
	 * @param  object $rule Rule object.
	 * @param  array $context Context.
	 * @return array Filtered cart items.
	 */
	public function filter_cart_items_for_rule( $cart_items, $rule, $context = array() ) {
		// Validate cart_items parameter
		if ( ! is_array( $cart_items ) ) {
			return array();
		}
		
		$conditions = $rule->get_conditions();
		
		// Ensure conditions is an array
		if ( ! is_array( $conditions ) ) {
			$conditions = array();
		}
		
		if ( empty( $conditions ) ) {
			return $cart_items;
		}

		// Separate inclusion/exclusion conditions from other conditions
		$inclusion_conditions = array();
		$exclusion_conditions = array();
		$regular_conditions = array();

		foreach ( $conditions as $condition ) {
			$operator = $condition->get_operator();
			$type = $condition->get_condition_type();

			// Check if this is a product-level filtering condition
			$is_product_filter = in_array( $type, array( 'product', 'product_category', 'brand' ), true );
			
			if ( $is_product_filter && $operator === 'in' ) {
				$inclusion_conditions[] = $condition;
			} elseif ( $is_product_filter && $operator === 'not_in' ) {
				$exclusion_conditions[] = $condition;
			} else {
				$regular_conditions[] = $condition;
			}
		}

		// If no filtering conditions, return all items
		if ( empty( $inclusion_conditions ) && empty( $exclusion_conditions ) ) {
			return $cart_items;
		}

		// Filter items based on inclusion and exclusion conditions
		$filtered_items = array();
		

		foreach ( $cart_items as $item ) {
			$should_include = true;
			$product_name = isset($item['data']) ? $item['data']->get_name() : 'Unknown';
			$line_total = isset($item['line_total']) ? $item['line_total'] : 0;

			// First, check inclusion conditions (if any)
			// If there are inclusion conditions, item MUST match at least one
			if ( ! empty( $inclusion_conditions ) ) {
				$matches_inclusion = false;
				
				foreach ( $inclusion_conditions as $condition ) {
					$type = $condition->get_condition_type();
					$values = $condition->get_value();
					
					// Ensure values is an array and convert to integers for comparison
					if ( ! is_array( $values ) ) {
						$values = array( $values );
					}
					$values = array_map( 'intval', $values );

					// Get item's actual value for this condition type
					$item_value = null;
					
					if ( isset( $item['product_id'] ) ) {
						$product_id = $item['product_id'];
						
						// For variations, use parent product ID for category/brand checks
						$check_id = $product_id;
						if ( isset( $item['variation_id'] ) && $item['variation_id'] > 0 ) {
							// This is a variation, get parent ID for taxonomy checks
							$variation = wc_get_product( $item['variation_id'] );
							if ( $variation && $variation->is_type( 'variation' ) ) {
								$parent_id = $variation->get_parent_id();
								if ( $parent_id > 0 ) {
									$check_id = $parent_id;
								}
							}
						}

						switch ( $type ) {
							case 'product':
								$item_value = array( intval( $product_id ) );
								break;

							case 'product_category':
								$terms = wp_get_post_terms( $check_id, 'product_cat', array( 'fields' => 'ids' ) );
								$item_value = is_array( $terms ) ? array_map( 'intval', $terms ) : array();
								break;

							case 'brand':
								$brand_taxonomy = $this->get_brand_taxonomy();
								if ( $brand_taxonomy ) {
									$terms = wp_get_post_terms( $check_id, $brand_taxonomy, array( 'fields' => 'ids' ) );
									$item_value = is_array( $terms ) ? array_map( 'intval', $terms ) : array();
								} else {
									$item_value = array();
								}
								break;
						}

						// Check if item value intersects with included values
						if ( is_array( $item_value ) && is_array( $values ) ) {
							$intersection = array_intersect( $item_value, $values );
							if ( ! empty( $intersection ) ) {
								$matches_inclusion = true;
								break; // Found a match
							}
						}
					}
				}
				
				// If there are inclusion conditions and item doesn't match, exclude it
				if ( ! $matches_inclusion ) {
					$should_include = false;
				}
			}

			// Then, check exclusion conditions (if item passed inclusion)
			if ( $should_include ) {
				foreach ( $exclusion_conditions as $condition ) {
					$type = $condition->get_condition_type();
					$values = $condition->get_value();
					
					// Ensure values is an array and convert to integers for comparison
					if ( ! is_array( $values ) ) {
						$values = array( $values );
					}
					$values = array_map( 'intval', $values );

					// Get item's actual value for this condition type
					$item_value = null;
					
					if ( isset( $item['product_id'] ) ) {
						$product_id = $item['product_id'];
						
						// For variations, use parent product ID for category/brand checks
						$check_id = $product_id;
						if ( isset( $item['variation_id'] ) && $item['variation_id'] > 0 ) {
							// This is a variation, get parent ID for taxonomy checks
							$variation = wc_get_product( $item['variation_id'] );
							if ( $variation && $variation->is_type( 'variation' ) ) {
								$parent_id = $variation->get_parent_id();
								if ( $parent_id > 0 ) {
									$check_id = $parent_id;
								}
							}
						}

						switch ( $type ) {
							case 'product':
								$item_value = array( intval( $product_id ) );
								break;

							case 'product_category':
								$terms = wp_get_post_terms( $check_id, 'product_cat', array( 'fields' => 'ids' ) );
								$item_value = is_array( $terms ) ? array_map( 'intval', $terms ) : array();
								break;

							case 'brand':
								// Get brand taxonomy
								$brand_taxonomy = $this->get_brand_taxonomy();
								if ( $brand_taxonomy ) {
									$terms = wp_get_post_terms( $check_id, $brand_taxonomy, array( 'fields' => 'ids' ) );
									$item_value = is_array( $terms ) ? array_map( 'intval', $terms ) : array();
								} else {
									$item_value = array();
								}
								break;
						}

						// Check if item value intersects with excluded values
						if ( is_array( $item_value ) && is_array( $values ) ) {
							$intersection = array_intersect( $item_value, $values );
							if ( ! empty( $intersection ) ) {
								$should_include = false;
								break; // This item is excluded
							}
						}
					}
				}
			}

			if ( $should_include ) {
				$filtered_items[] = $item;
			}
		}

		return $filtered_items;
	}

	/**
	 * Select rule by strategy from pre-filtered rules.
	 *
	 * @since  1.0.6
	 * @param  array  $rules_with_totals Array of rules with filtered totals.
	 * @param  string $strategy          Strategy: 'highest' or 'lowest'.
	 * @return array|null                Selected rule data or null.
	 */
	private function select_rule_by_strategy( $rules_with_totals, $strategy ) {
		if ( empty( $rules_with_totals ) ) {
			return null;
		}


		$calculator = new Discount_Tools_Calculator();
		$rules_with_discounts = array();

		// Calculate actual discount for each rule using filtered totals
		foreach ( $rules_with_totals as $rule_data ) {
			$rule = $rule_data['rule'];
			$filtered_total = $rule_data['filtered_total'];


			// Check if filtered total meets rule's cart_total condition
			$conditions = $rule->get_conditions();
			if ( ! is_array( $conditions ) ) {
				$conditions = array();
			}
			$meets_threshold = true;

			foreach ( $conditions as $condition ) {
				if ( $condition->get_type() === 'cart_total' ) {
					$threshold = $condition->get_value();
					$operator = $condition->get_operator();


					if ( $operator === 'greater_or_equal' && $filtered_total < $threshold ) {
						$meets_threshold = false;
						break;
					}
				}
			}

			// Skip rule if threshold not met
			if ( ! $meets_threshold || $filtered_total <= 0 ) {
				continue;
			}

			// Calculate discount
			$discount_result = $calculator->calculate(
				$filtered_total,
				$rule->get_discount_type(),
				$rule->get_discount_value(),
				1
			);

			$discount_amount = isset( $discount_result['discount_amount'] ) ? $discount_result['discount_amount'] : 0;

			if ( $discount_amount > 0 ) {
				$rules_with_discounts[] = array(
					'rule'           => $rule,
					'discount'       => $discount_amount,
					'filtered_total' => $filtered_total,
					'filtered_items' => $rule_data['filtered_items'],
				);
			}
		}

		// If no rules have discounts, return null
		if ( empty( $rules_with_discounts ) ) {
			return null;
		}

		// Sort by discount amount
		if ( $strategy === 'highest' ) {
			usort( $rules_with_discounts, function( $a, $b ) {
				return $b['discount'] - $a['discount'];
			});
		} else { // 'lowest'
			usort( $rules_with_discounts, function( $a, $b ) {
				return $a['discount'] - $b['discount'];
			});
		}

		// Return the first rule (highest or lowest depending on sort)
		return $rules_with_discounts[0];
	}

	/**
	 * Get brand taxonomy name.
	 *
	 * @since  1.0.5
	 * @return string|null Brand taxonomy name or null if not found.
	 */
	private function get_brand_taxonomy() {
		$taxonomies = array(
			'product_brand',
			'pwb-brand',
			'yith_product_brand',
		);

		foreach ( $taxonomies as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return null;
	}

	/**
	 * Check if discount rules should be disabled based on Coupon Integration settings.
	 *
	 * @since  1.1.0
	 * @return bool True if rules should be disabled, false otherwise.
	 */
	private function should_disable_discount_rules() {
		// Check if Coupon Integration class exists
		if ( ! class_exists( '\Discount_Tools\Engine\Coupon_Integration' ) ) {
			return false;
		}

		// Get Coupon Integration instance
		// Note: We can't easily get the instance from here, so we'll check the setting directly
		$settings = get_option( 'discount_tools_settings', array() );
		$coupon_mode = isset( $settings['coupon_interaction_mode'] ) ? $settings['coupon_interaction_mode'] : 'both_active';

		// If mode is 'coupons_only', check if any coupons are active
		if ( 'coupons_only' === $coupon_mode ) {
			// Check if WooCommerce session has active coupons
			if ( function_exists( 'WC' ) && WC()->session ) {
				$active_coupons = WC()->session->get( 'dt_active_coupons', array() );
				if ( ! empty( $active_coupons ) ) {
					return true; // Disable discount rules
				}
			}

			// Also check if WooCommerce cart has applied coupons
			if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
				$applied_coupons = WC()->cart->get_applied_coupons();
				if ( ! empty( $applied_coupons ) ) {
					return true; // Disable discount rules
				}
			}
		}

		return false;
	}
}
