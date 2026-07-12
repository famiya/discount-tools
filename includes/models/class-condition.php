<?php
/**
 * Condition Model Class
 *
 * Represents a single condition for a discount rule.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/models
 */

/**
 * Condition model class.
 *
 * Encapsulates condition logic without database operations.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/models
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Condition implements JsonSerializable {

	/**
	 * Condition ID.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Rule ID this condition belongs to.
	 *
	 * @var int
	 */
	private $rule_id = 0;

	/**
	 * Condition type.
	 *
	 * @var string
	 */
	private $condition_type = '';

	/**
	 * Comparison operator.
	 *
	 * @var string
	 */
	private $operator = 'equals';

	/**
	 * Condition value (can be scalar or array).
	 *
	 * @var mixed
	 */
	private $value = null;

	/**
	 * Group ID for OR logic (0 = default group).
	 *
	 * @var int
	 */
	private $group_id = 0;

	/**
	 * Supported condition types.
	 *
	 * @var array
	 */
	const CONDITION_TYPES = array(
		'product',           // Specific products.
		'product_category',  // Product categories.
		'product_tag',       // Product tags.
		'brand',             // Product brand (dynamic taxonomy).
		'product_attribute', // Product attributes.
		'cart_total',        // Cart total amount.
		'cart_quantity',     // Cart total quantity.
		'user_role',         // User role.
		'user_email',        // User email.
		'user_logged_in',    // User logged in status.
		'shipping_state',    // Shipping state.
		'shipping_city',     // Shipping city.
		'shipping_postcode', // Shipping postcode.
		'payment_method',    // Payment method.
		'purchase_history',  // Purchase history.
		'date_time',         // Date and time.
		'day_of_week',       // Day of week.
		'coupon_activation', // Coupon activation code.
	);

	/**
	 * Supported operators.
	 *
	 * @var array
	 */
	const OPERATORS = array(
		'equals',           // Equal to.
		'not_equals',       // Not equal to.
		'in',               // In array.
		'not_in',           // Not in array.
		'contains',         // Contains string.
		'not_contains',     // Does not contain string.
		'starts_with',      // Starts with string.
		'ends_with',        // Ends with string.
		'greater_than',     // Greater than.
		'greater_or_equal', // Greater than or equal to.
		'less_than',        // Less than.
		'less_or_equal',    // Less than or equal to.
		'between',          // Between two values.
		'not_between',      // Not between two values.
		'is_empty',         // Is empty.
		'is_not_empty',     // Is not empty.
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array $data Condition data.
	 */
	public function __construct( $data = array() ) {
		if ( ! empty( $data ) ) {
			$this->set_data( $data );
		}
	}

	/**
	 * Set condition data from array.
	 *
	 * @since 1.0.0
	 * @param array $data Condition data.
	 */
	public function set_data( $data ) {
		$properties = array( 'id', 'rule_id', 'condition_type', 'operator', 'value', 'group_id' );

		foreach ( $properties as $property ) {
			if ( isset( $data[ $property ] ) ) {
				$method = 'set_' . $property;
				if ( method_exists( $this, $method ) ) {
					$this->$method( $data[ $property ] );
				}
			}
		}
	}

	/**
	 * Get condition data as array.
	 *
	 * @since  1.0.0
	 * @return array Condition data.
	 */
	public function get_data() {
		return array(
			'id'             => $this->id,
			'rule_id'        => $this->rule_id,
			'condition_type' => $this->condition_type,
			'operator'       => $this->operator,
			'value'          => $this->value,
			'group_id'       => $this->group_id,
		);
	}

	/**
	 * JSON serialize.
	 *
	 * @since  1.0.0
	 * @return array Data to serialize.
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return $this->get_data();
	}

	// Getters and Setters.

	/**
	 * Get condition ID.
	 *
	 * @since  1.0.0
	 * @return int Condition ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set condition ID.
	 *
	 * @since 1.0.0
	 * @param int $id Condition ID.
	 */
	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	/**
	 * Get rule ID.
	 *
	 * @since  1.0.0
	 * @return int Rule ID.
	 */
	public function get_rule_id() {
		return $this->rule_id;
	}

	/**
	 * Set rule ID.
	 *
	 * @since 1.0.0
	 * @param int $id Rule ID.
	 */
	public function set_rule_id( $id ) {
		$this->rule_id = absint( $id );
	}

	/**
	 * Get condition type.
	 *
	 * @since  1.0.0
	 * @return string Condition type.
	 */
	public function get_condition_type() {
		return $this->condition_type;
	}

	/**
	 * Set condition type.
	 *
	 * @since 1.0.0
	 * @param string $type Condition type.
	 */
	public function set_condition_type( $type ) {
		if ( in_array( $type, self::CONDITION_TYPES, true ) ) {
			$this->condition_type = $type;
		}
	}

	/**
	 * Backward-compatible getter for condition type.
	 *
	 * @since 1.0.5
	 * @return string
	 */
	public function get_type() {
		return $this->get_condition_type();
	}

	/**
	 * Backward-compatible setter for condition type.
	 *
	 * @since 1.0.5
	 * @param string $type Condition type.
	 */
	public function set_type( $type ) {
		$this->set_condition_type( $type );
	}

	/**
	 * Get operator.
	 *
	 * @since  1.0.0
	 * @return string Operator.
	 */
	public function get_operator() {
		return $this->operator;
	}

	/**
	 * Set operator.
	 *
	 * @since 1.0.0
	 * @param string $operator Operator.
	 */
	public function set_operator( $operator ) {
		if ( in_array( $operator, self::OPERATORS, true ) ) {
			$this->operator = $operator;
		}
	}

	/**
	 * Get value.
	 *
	 * @since  1.0.0
	 * @return mixed Value.
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Set value.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value (can be scalar or array).
	 */
	public function set_value( $value ) {
		// If value is a JSON string, decode it.
		if ( is_string( $value ) && $this->is_json( $value ) ) {
			$this->value = json_decode( $value, true );
		} 
		// If value is a serialized string, unserialize it.
		elseif ( is_string( $value ) && $this->is_serialized( $value ) ) {
			$this->value = unserialize( $value );
		} else {
			$this->value = $value;
		}
	}

	/**
	 * Get group ID.
	 *
	 * @since  1.0.0
	 * @return int Group ID.
	 */
	public function get_group_id() {
		return $this->group_id;
	}

	/**
	 * Set group ID.
	 *
	 * @since 1.0.0
	 * @param int $id Group ID.
	 */
	public function set_group_id( $id ) {
		$this->group_id = absint( $id );
	}

	// Business Logic Methods.

	/**
	 * Check if a string is valid JSON.
	 *
	 * @since  1.0.0
	 * @param  string $string String to check.
	 * @return bool           True if valid JSON, false otherwise.
	 */
	private function is_json( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Check if a string is serialized.
	 *
	 * @since  1.0.6
	 * @param  string $string String to check.
	 * @return bool           True if serialized, false otherwise.
	 */
	private function is_serialized( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		// WordPress has a built-in function for this
		if ( function_exists( 'is_serialized' ) ) {
			return is_serialized( $string );
		}

		// Fallback check
		$data = @unserialize( $string );
		return $data !== false || $string === 'b:0;';
	}

	/**
	 * Get value as JSON string.
	 *
	 * @since  1.0.0
	 * @return string JSON encoded value.
	 */
	public function get_value_json() {
		return wp_json_encode( $this->value );
	}

	/**
	 * Validate condition data.
	 *
	 * @since  1.0.0
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate() {
		// Check required fields.
		if ( empty( $this->condition_type ) ) {
			return new WP_Error( 'empty_condition_type', __( 'Condition type is required.', 'discount-tools' ) );
		}

		if ( ! in_array( $this->condition_type, self::CONDITION_TYPES, true ) ) {
			return new WP_Error( 'invalid_condition_type', __( 'Invalid condition type.', 'discount-tools' ) );
		}

		if ( empty( $this->operator ) ) {
			return new WP_Error( 'empty_operator', __( 'Operator is required.', 'discount-tools' ) );
		}

		if ( ! in_array( $this->operator, self::OPERATORS, true ) ) {
			return new WP_Error( 'invalid_operator', __( 'Invalid operator.', 'discount-tools' ) );
		}

		// Check value for operators that require it.
		$operators_without_value = array( 'is_empty', 'is_not_empty' );
		if ( ! in_array( $this->operator, $operators_without_value, true ) ) {
			if ( null === $this->value || '' === $this->value ) {
				return new WP_Error( 'empty_value', __( 'Condition value is required.', 'discount-tools' ) );
			}
		}

		// Validate array operators.
		$array_operators = array( 'in', 'not_in', 'between', 'not_between' );
		if ( in_array( $this->operator, $array_operators, true ) ) {
			if ( ! is_array( $this->value ) ) {
				return new WP_Error( 'invalid_value_type', __( 'Value must be an array for this operator.', 'discount-tools' ) );
			}

			if ( empty( $this->value ) ) {
				return new WP_Error( 'empty_array', __( 'Value array cannot be empty.', 'discount-tools' ) );
			}
		}

		// Validate between operator.
		if ( in_array( $this->operator, array( 'between', 'not_between' ), true ) ) {
			if ( count( $this->value ) !== 2 ) {
				return new WP_Error( 'invalid_between', __( 'Between operator requires exactly 2 values.', 'discount-tools' ) );
			}
		}

		return true;
	}

	/**
	 * Evaluate condition against a context.
	 *
	 * This is a placeholder. Full implementation will be in Condition Evaluator (Task 8).
	 *
	 * @since  1.0.0
	 * @param  array $context Context data to evaluate against.
	 * @return bool           True if condition passes, false otherwise.
	 */
	public function evaluate( $context = array() ) {
		// Placeholder - will be implemented in Task 8 (Condition Evaluator).
		return true;
	}

	/**
	 * Get condition description in human-readable format.
	 *
	 * @since  1.0.0
	 * @return string Condition description.
	 */
	public function get_description() {
		$type_labels = array(
			'product'           => __( 'Product', 'discount-tools' ),
			'product_category'  => __( 'Product Category', 'discount-tools' ),
			'product_tag'       => __( 'Product Tag', 'discount-tools' ),
			'brand'             => __( 'Brand', 'discount-tools' ),
			'product_attribute' => __( 'Product Attribute', 'discount-tools' ),
			'cart_total'        => __( 'Cart Total', 'discount-tools' ),
			'cart_quantity'     => __( 'Cart Quantity', 'discount-tools' ),
			'user_role'         => __( 'User Role', 'discount-tools' ),
			'user_email'        => __( 'User Email', 'discount-tools' ),
			'user_logged_in'    => __( 'User Logged In', 'discount-tools' ),
			'shipping_state'    => __( 'Shipping State', 'discount-tools' ),
			'shipping_city'     => __( 'Shipping City', 'discount-tools' ),
			'shipping_postcode' => __( 'Shipping Postcode', 'discount-tools' ),
			'payment_method'    => __( 'Payment Method', 'discount-tools' ),
			'purchase_history'  => __( 'Purchase History', 'discount-tools' ),
			'date_time'         => __( 'Date/Time', 'discount-tools' ),
			'day_of_week'       => __( 'Day of Week', 'discount-tools' ),
		);

		$operator_labels = array(
			'equals'           => __( 'equals', 'discount-tools' ),
			'not_equals'       => __( 'does not equal', 'discount-tools' ),
			'in'               => __( 'is one of', 'discount-tools' ),
			'not_in'           => __( 'is not one of', 'discount-tools' ),
			'contains'         => __( 'contains', 'discount-tools' ),
			'not_contains'     => __( 'does not contain', 'discount-tools' ),
			'starts_with'      => __( 'starts with', 'discount-tools' ),
			'ends_with'        => __( 'ends with', 'discount-tools' ),
			'greater_than'     => __( 'is greater than', 'discount-tools' ),
			'greater_or_equal' => __( 'is greater than or equal to', 'discount-tools' ),
			'less_than'        => __( 'is less than', 'discount-tools' ),
			'less_or_equal'    => __( 'is less than or equal to', 'discount-tools' ),
			'between'          => __( 'is between', 'discount-tools' ),
			'not_between'      => __( 'is not between', 'discount-tools' ),
			'is_empty'         => __( 'is empty', 'discount-tools' ),
			'is_not_empty'     => __( 'is not empty', 'discount-tools' ),
		);

		$type_label     = isset( $type_labels[ $this->condition_type ] ) ? $type_labels[ $this->condition_type ] : $this->condition_type;
		$operator_label = isset( $operator_labels[ $this->operator ] ) ? $operator_labels[ $this->operator ] : $this->operator;

		$value_display = '';
		if ( ! in_array( $this->operator, array( 'is_empty', 'is_not_empty' ), true ) ) {
			if ( is_array( $this->value ) ) {
				$value_display = implode( ', ', $this->value );
			} else {
				$value_display = $this->value;
			}
		}

		if ( ! empty( $value_display ) ) {
			return sprintf( '%s %s %s', $type_label, $operator_label, $value_display );
		}

		return sprintf( '%s %s', $type_label, $operator_label );
	}

	/**
	 * Get supported condition types.
	 *
	 * @since  1.0.0
	 * @return array Condition types.
	 */
	public static function get_condition_types() {
		return self::CONDITION_TYPES;
	}

	/**
	 * Get supported operators.
	 *
	 * @since  1.0.0
	 * @return array Operators.
	 */
	public static function get_operators() {
		return self::OPERATORS;
	}

	/**
	 * Get operators for a specific condition type.
	 *
	 * @since  1.0.0
	 * @param  string $condition_type Condition type.
	 * @return array                  Available operators for this type.
	 */
	public static function get_operators_for_type( $condition_type ) {
		$operator_map = array(
			'product'           => array( 'in', 'not_in' ),
			'product_category'  => array( 'in', 'not_in' ),
			'product_tag'       => array( 'in', 'not_in' ),
			'brand'             => array( 'in', 'not_in' ),
			'product_attribute' => array( 'in', 'not_in', 'contains', 'not_contains' ),
			'cart_total'        => array( 'equals', 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'between', 'not_between' ),
			'cart_quantity'     => array( 'equals', 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'between', 'not_between' ),
			'user_role'         => array( 'in', 'not_in' ),
			'user_email'        => array( 'equals', 'not_equals', 'contains', 'not_contains', 'ends_with' ),
			'user_logged_in'    => array( 'equals' ),
			'shipping_state'    => array( 'in', 'not_in' ),
			'shipping_city'     => array( 'in', 'not_in', 'contains', 'not_contains' ),
			'shipping_postcode' => array( 'equals', 'not_equals', 'starts_with', 'contains' ),
			'payment_method'    => array( 'in', 'not_in' ),
			'purchase_history'  => array( 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'equals' ),
			'date_time'         => array( 'equals', 'greater_than', 'less_than', 'between' ),
			'day_of_week'       => array( 'in', 'not_in' ),
			'coupon_activation' => array( 'in' ),
		);

		return isset( $operator_map[ $condition_type ] ) ? $operator_map[ $condition_type ] : array( 'equals' );
	}
}
