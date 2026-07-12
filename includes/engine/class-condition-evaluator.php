<?php
/**
 * Condition Evaluator
 *
 * Evaluates rule conditions against current context.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 */

/**
 * Condition evaluator class.
 *
 * Evaluates all condition types with AND/OR logic support.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Condition_Evaluator {

	/**
	 * Current evaluation context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $context = array();

	/**
	 * Evaluate conditions for a rule.
	 *
	 * @since  1.0.0
	 * @param  array $conditions Array of Discount_Tools_Condition objects.
	 * @param  array $context    Evaluation context (product, cart, user, etc).
	 * @return bool              True if all conditions pass.
	 */
	public function evaluate( $conditions, $context = array() ) {
		$this->context = $context;

		// No conditions = always pass
		if ( empty( $conditions ) ) {
			return true;
		}

		// Group conditions by group_id
		$groups = $this->group_conditions( $conditions );

		// Evaluate each group (AND logic between groups - all groups must pass)
		foreach ( $groups as $group_conditions ) {
			if ( ! $this->evaluate_group( $group_conditions ) ) {
				return false; // Short-circuit: any group fails = rule fails
			}
		}

		return true; // All groups passed
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Condition $condition Condition object.
	 * @param  array                    $context   Evaluation context.
	 * @return bool                     True if condition passes.
	 */
	public function evaluate_condition( $condition, $context = array() ) {
		$this->context = array_merge( $this->context, $context );

		$type = $condition->get_type();
		$operator = $condition->get_operator();
		$expected_value = $condition->get_value();

		// Handle coupon_activation with email restriction
		if ( 'coupon_activation' === $type ) {
			
			// Check if value is structured (with email_list)
			if ( is_array( $expected_value ) && isset( $expected_value['coupon_codes'] ) && isset( $expected_value['email_list'] ) ) {
				$coupon_codes = $expected_value['coupon_codes'];
				$email_list = $expected_value['email_list'];
				
				// 獲取當前活躍的優惠碼
				$active_coupons = $this->get_active_coupons();
				
				// 檢查每個活躍優惠碼是否符合條件
				$valid_coupons = array();
				foreach ( $coupon_codes as $index => $code ) {
					$code_upper = strtoupper( $code );
					
					// 檢查此優惠碼是否在活躍列表中
					if ( ! in_array( $code_upper, $active_coupons ) ) {
						continue;
					}
					
					// 獲取對應的郵箱限制（如果有）
					$email_restriction = isset( $email_list[ $index ] ) ? trim( $email_list[ $index ] ) : '';
					
					// 如果沒有郵箱限制，此優惠碼對所有人有效
					if ( empty( $email_restriction ) ) {
						$valid_coupons[] = $code_upper;
						continue;
					}
					
					// 有郵箱限制，檢查當前用戶郵箱
					$customer_email = $this->get_customer_email();
					if ( ! empty( $customer_email ) && strtolower( $customer_email ) === strtolower( $email_restriction ) ) {
						$valid_coupons[] = $code_upper;
					}
				}
				
				// 如果有任何有效的優惠碼，條件通過
				return ! empty( $valid_coupons );
			}
			
			// Normalize coupon codes to uppercase for case-insensitive comparison
			if ( is_array( $expected_value ) ) {
				$expected_value = array_map( 'strtoupper', $expected_value );
			} else {
				$expected_value = strtoupper( $expected_value );
			}
			
		}

		// 注意：移除了錯誤的 not_in 條件跳過邏輯
		// not_in 條件應該正常評估，而不是被跳過
		// 項目過濾是在規則應用階段處理的，不是在條件評估階段

		// Get actual value based on condition type
		$actual_value = $this->get_actual_value( $type );
		
		if ( 'coupon_activation' === $type ) {
		}

		// Compare using operator
		$result = $this->compare( $actual_value, $operator, $expected_value );
		
		if ( 'coupon_activation' === $type ) {
		}
		
		return $result;
	}

	/**
	 * Group conditions by group_id.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $conditions Array of condition objects.
	 * @return array Grouped conditions.
	 */
	private function group_conditions( $conditions ) {
		$groups = array();

		foreach ( $conditions as $condition ) {
			$group_id = $condition->get_group_id();
			if ( ! isset( $groups[ $group_id ] ) ) {
				$groups[ $group_id ] = array();
			}
			$groups[ $group_id ][] = $condition;
		}

		return $groups;
	}

	/**
	 * Evaluate conditions within a group (AND logic).
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $conditions Array of condition objects.
	 * @return bool              True if all conditions in group pass.
	 */
	private function evaluate_group( $conditions ) {
		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate_condition( $condition ) ) {
				return false; // Short-circuit: any condition fails = group fails
			}
		}
		return true;
	}

	/**
	 * Get actual value based on condition type.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $type Condition type.
	 * @return mixed        Actual value.
	 */
	private function get_actual_value( $type ) {
		switch ( $type ) {
			case 'product':
				return $this->get_product_id();

			case 'product_category':
				return $this->get_product_categories();

			case 'product_tag':
				return $this->get_product_tags();

			case 'brand':
				return $this->get_product_brands();

			case 'product_attribute':
				return $this->get_product_attributes();

			case 'cart_total':
				return $this->get_cart_total();

			case 'cart_quantity':
				return $this->get_cart_quantity();

			case 'user_role':
				return $this->get_user_role();

			case 'user_email':
				return $this->get_user_email();

			case 'user_logged_in':
				return $this->is_user_logged_in();

			case 'shipping_state':
				return $this->get_shipping_state();

			case 'shipping_city':
				return $this->get_shipping_city();

			case 'shipping_postcode':
				return $this->get_shipping_postcode();

			case 'payment_method':
				return $this->get_payment_method();

			case 'purchase_history':
				return $this->get_purchase_history();

			case 'date_time':
				return $this->get_current_datetime();

		case 'day_of_week':
			return $this->get_day_of_week();

		case 'coupon_activation':
			$active = $this->get_active_coupons();
			return $active;

		default:
			return null;
		}
	}

	/**
	 * Compare actual value with expected value using operator.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed  $actual   Actual value.
	 * @param  string $operator Comparison operator.
	 * @param  mixed  $expected Expected value.
	 * @return bool             Comparison result.
	 */
	private function compare( $actual, $operator, $expected ) {
		switch ( $operator ) {
			case 'equals':
				return $this->op_equals( $actual, $expected );

			case 'not_equals':
				return ! $this->op_equals( $actual, $expected );

			case 'in':
				return $this->op_in( $actual, $expected );

			case 'not_in':
				return ! $this->op_in( $actual, $expected );

			case 'contains':
				return $this->op_contains( $actual, $expected );

			case 'not_contains':
				return ! $this->op_contains( $actual, $expected );

			case 'starts_with':
				return $this->op_starts_with( $actual, $expected );

			case 'ends_with':
				return $this->op_ends_with( $actual, $expected );

			case 'greater_than':
				return $this->op_greater_than( $actual, $expected );

			case 'greater_or_equal':
				return $this->op_greater_or_equal( $actual, $expected );

			case 'less_than':
				return $this->op_less_than( $actual, $expected );

			case 'less_or_equal':
				return $this->op_less_or_equal( $actual, $expected );

			case 'between':
				return $this->op_between( $actual, $expected );

			case 'not_between':
				return ! $this->op_between( $actual, $expected );

			case 'is_empty':
				return $this->op_is_empty( $actual );

			case 'is_not_empty':
				return ! $this->op_is_empty( $actual );

			default:
				return false;
		}
	}

	/**
	 * Get product ID from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return int|null Product ID.
	 */
	private function get_product_id() {
		if ( isset( $this->context['product_id'] ) ) {
			return intval( $this->context['product_id'] );
		}

		if ( isset( $this->context['product'] ) && is_a( $this->context['product'], 'WC_Product' ) ) {
			return $this->context['product']->get_id();
		}

		// For cart rules: return array of all product IDs in cart
		if ( isset( $this->context['rule_type'] ) && $this->context['rule_type'] === 'cart' &&
		     isset( $this->context['cart_items'] ) && is_array( $this->context['cart_items'] ) ) {
			$product_ids = array();
			foreach ( $this->context['cart_items'] as $item ) {
				if ( isset( $item['product_id'] ) ) {
					$product_ids[] = intval( $item['product_id'] );
				}
			}
			return $product_ids;
		}

		return null;
	}

	/**
	 * Get product categories from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Category IDs.
	 */
	private function get_product_categories() {
		$product_id = $this->get_product_id();
		if ( ! $product_id ) {
			return array();
		}

		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Get product tags from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Tag IDs.
	 */
	private function get_product_tags() {
		$product_id = $this->get_product_id();
		if ( ! $product_id ) {
			return array();
		}

		$terms = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Get product brands from context.
	 *
	 * @since  1.0.5
	 * @access private
	 * @return array Brand IDs.
	 */
	private function get_product_brands() {
		// If a specific product_id is in context, always use that product's brand.
		// cart_brands contains ALL brands in the cart and must not override per-item checks.
		$product_id = $this->get_product_id();
		if ( $product_id ) {
			$brand_taxonomy = $this->get_brand_taxonomy();
			if ( ! $brand_taxonomy ) {
				return array();
			}
			$terms = wp_get_post_terms( $product_id, $brand_taxonomy, array( 'fields' => 'ids' ) );
			return is_array( $terms ) ? $terms : array();
		}

		// No specific product: fall back to cart_brands (cart-level brand check).
		if ( isset( $this->context['cart_brands'] ) && is_array( $this->context['cart_brands'] ) ) {
			return $this->context['cart_brands'];
		}

		return array();
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
	 * Get product attributes from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Attribute values.
	 */
	private function get_product_attributes() {
		if ( ! isset( $this->context['product'] ) || ! is_a( $this->context['product'], 'WC_Product' ) ) {
			return array();
		}

		$product = $this->context['product'];
		$attributes = $product->get_attributes();

		$values = array();
		foreach ( $attributes as $attribute ) {
			if ( is_a( $attribute, 'WC_Product_Attribute' ) ) {
				$values = array_merge( $values, $attribute->get_options() );
			}
		}

		return $values;
	}

	/**
	 * Get cart total from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return float Cart total.
	 */
	private function get_cart_total() {
		if ( isset( $this->context['cart_total'] ) ) {
			return floatval( $this->context['cart_total'] );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			return floatval( WC()->cart->get_subtotal() );
		}

		return 0;
	}

	/**
	 * Get cart quantity from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return int Cart quantity.
	 */
	private function get_cart_quantity() {
		if ( isset( $this->context['cart_quantity'] ) ) {
			return intval( $this->context['cart_quantity'] );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			return intval( WC()->cart->get_cart_contents_count() );
		}

		return 0;
	}

	/**
	 * Get user role from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string|null User role.
	 */
	private function get_user_role() {
		if ( isset( $this->context['user_role'] ) ) {
			return $this->context['user_role'];
		}

		$user = $this->get_current_user();
		if ( $user && ! empty( $user->roles ) ) {
			return $user->roles[0];
		}

		return 'guest';
	}

	/**
	 * Get user email from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string User email.
	 */
	private function get_user_email() {
		if ( isset( $this->context['user_email'] ) ) {
			return $this->context['user_email'];
		}

		$user = $this->get_current_user();
		return $user ? $user->user_email : '';
	}

	/**
	 * Check if user is logged in.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return bool True if logged in.
	 */
	private function is_user_logged_in() {
		if ( isset( $this->context['user_logged_in'] ) ) {
			return (bool) $this->context['user_logged_in'];
		}

		return is_user_logged_in();
	}

	/**
	 * Get shipping state from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string State code.
	 */
	private function get_shipping_state() {
		if ( isset( $this->context['shipping_state'] ) ) {
			return $this->context['shipping_state'];
		}

		if ( function_exists( 'WC' ) && WC()->customer ) {
			return WC()->customer->get_shipping_state();
		}

		return '';
	}

	/**
	 * Get shipping city from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string City name.
	 */
	private function get_shipping_city() {
		if ( isset( $this->context['shipping_city'] ) ) {
			return $this->context['shipping_city'];
		}

		if ( function_exists( 'WC' ) && WC()->customer ) {
			return WC()->customer->get_shipping_city();
		}

		return '';
	}

	/**
	 * Get shipping postcode from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string Postcode.
	 */
	private function get_shipping_postcode() {
		if ( isset( $this->context['shipping_postcode'] ) ) {
			return $this->context['shipping_postcode'];
		}

		if ( function_exists( 'WC' ) && WC()->customer ) {
			return WC()->customer->get_shipping_postcode();
		}

		return '';
	}

	/**
	 * Get payment method from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string Payment method ID.
	 */
	private function get_payment_method() {
		if ( isset( $this->context['payment_method'] ) ) {
			return $this->context['payment_method'];
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			return WC()->session->get( 'chosen_payment_method', '' );
		}

		return '';
	}

	/**
	 * Get purchase history from context.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Purchase data.
	 */
	private function get_purchase_history() {
		if ( isset( $this->context['purchase_history'] ) ) {
			return $this->context['purchase_history'];
		}

		$user = $this->get_current_user();
		if ( ! $user ) {
			return array(
				'order_count' => 0,
				'total_spent' => 0,
			);
		}

		// Get customer order count and total spent
		if ( function_exists( 'wc_get_customer_order_count' ) ) {
			$order_count = wc_get_customer_order_count( $user->ID );
			$total_spent = wc_get_customer_total_spent( $user->ID );
		} else {
			$order_count = 0;
			$total_spent = 0;
		}

		return array(
			'order_count' => $order_count,
			'total_spent' => $total_spent,
		);
	}

	/**
	 * Get current datetime.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string Current datetime (Y-m-d H:i:s).
	 */
	private function get_current_datetime() {
		if ( isset( $this->context['current_time'] ) ) {
			return $this->context['current_time'];
		}

		return current_time( 'mysql' );
	}

	/**
	 * Get current day of week.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return int Day of week (0=Sunday, 6=Saturday).
	 */
	private function get_day_of_week() {
		if ( isset( $this->context['day_of_week'] ) ) {
			return intval( $this->context['day_of_week'] );
		}

		return intval( current_time( 'w' ) );
	}

	/**
	 * Get active coupon codes.
	 *
	 * Returns array of active coupon codes from session.
	 *
	 * @since  1.1.0
	 * @access private
	 * @return array Array of active coupon codes (uppercase).
	 */
	private function get_active_coupons() {
		// 檢查優惠券整合類是否存在
		if ( ! class_exists( '\Discount_Tools\Engine\Coupon_Integration' ) ) {
			return array();
		}

		// 嘗試從全域變數獲取主插件實例
		global $discount_tools;
		if ( $discount_tools && method_exists( $discount_tools, 'get_coupon_integration' ) ) {
			$coupon_integration = $discount_tools->get_coupon_integration();
			if ( $coupon_integration ) {
				return $coupon_integration->get_active_coupons();
			}
		}

		// 備用方案：創建新實例（但這會導致重複 hook 註冊）
		$coupon_integration = new \Discount_Tools\Engine\Coupon_Integration();
		return $coupon_integration->get_active_coupons();
	}

	/**
	 * Get customer email address.
	 *
	 * @since  1.1.0
	 * @access private
	 * @return string Customer email address or empty string.
	 */
	private function get_customer_email() {
		// Try to get email from WooCommerce session (for logged-in and guest customers)
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$email = WC()->customer->get_billing_email();
			if ( ! empty( $email ) ) {
				return $email;
			}
		}

		// Try to get from current user
		$user = $this->get_current_user();
		if ( $user && $user->ID > 0 ) {
			return $user->user_email;
		}

		return '';
	}

	/**
	 * Get current user.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return WP_User|false User object or false.
	 */
	private function get_current_user() {
		if ( isset( $this->context['user'] ) ) {
			return $this->context['user'];
		}

		return wp_get_current_user();
	}

	/**
	 * Operator: Equals.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value.
	 * @param  mixed $expected Expected value.
	 * @return bool            Comparison result.
	 */
	private function op_equals( $actual, $expected ) {
		return $actual == $expected;
	}

	/**
	 * Operator: In array.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value (can be scalar or array).
	 * @param  array $expected Expected values.
	 * @return bool            True if actual is in expected.
	 */
	private function op_in( $actual, $expected ) {
		if ( ! is_array( $expected ) ) {
			$expected = array( $expected );
		}

		if ( is_array( $actual ) ) {
			// Check if any actual value is in expected
			return ! empty( array_intersect( $actual, $expected ) );
		}

		return in_array( $actual, $expected );
	}

	/**
	 * Operator: Contains.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value (string or array).
	 * @param  mixed $expected Expected value.
	 * @return bool            True if actual contains expected.
	 */
	private function op_contains( $actual, $expected ) {
		if ( is_array( $actual ) ) {
			return in_array( $expected, $actual );
		}

		return strpos( (string) $actual, (string) $expected ) !== false;
	}

	/**
	 * Operator: Starts with.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $actual   Actual value.
	 * @param  string $expected Expected value.
	 * @return bool             True if actual starts with expected.
	 */
	private function op_starts_with( $actual, $expected ) {
		$actual = (string) $actual;
		$expected = (string) $expected;
		return substr( $actual, 0, strlen( $expected ) ) === $expected;
	}

	/**
	 * Operator: Ends with.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $actual   Actual value.
	 * @param  string $expected Expected value.
	 * @return bool             True if actual ends with expected.
	 */
	private function op_ends_with( $actual, $expected ) {
		$actual = (string) $actual;
		$expected = (string) $expected;
		$length = strlen( $expected );
		if ( $length == 0 ) {
			return true;
		}
		return substr( $actual, -$length ) === $expected;
	}

	/**
	 * Operator: Greater than.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value.
	 * @param  mixed $expected Expected value.
	 * @return bool            Comparison result.
	 */
	private function op_greater_than( $actual, $expected ) {
		return floatval( $actual ) > floatval( $expected );
	}

	/**
	 * Operator: Greater than or equal.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value.
	 * @param  mixed $expected Expected value.
	 * @return bool            Comparison result.
	 */
	private function op_greater_or_equal( $actual, $expected ) {
		return floatval( $actual ) >= floatval( $expected );
	}

	/**
	 * Operator: Less than.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value.
	 * @param  mixed $expected Expected value.
	 * @return bool            Comparison result.
	 */
	private function op_less_than( $actual, $expected ) {
		return floatval( $actual ) < floatval( $expected );
	}

	/**
	 * Operator: Less than or equal.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value.
	 * @param  mixed $expected Expected value.
	 * @return bool            Comparison result.
	 */
	private function op_less_or_equal( $actual, $expected ) {
		return floatval( $actual ) <= floatval( $expected );
	}

	/**
	 * Operator: Between.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual   Actual value.
	 * @param  array $expected Expected range [min, max].
	 * @return bool            True if actual is between min and max.
	 */
	private function op_between( $actual, $expected ) {
		if ( ! is_array( $expected ) || count( $expected ) < 2 ) {
			return false;
		}

		$actual = floatval( $actual );
		$min = floatval( $expected[0] );
		$max = floatval( $expected[1] );

		return $actual >= $min && $actual <= $max;
	}

	/**
	 * Operator: Is empty.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $actual Actual value.
	 * @return bool          True if empty.
	 */
	private function op_is_empty( $actual ) {
		return empty( $actual );
	}
}
