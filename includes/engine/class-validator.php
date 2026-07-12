<?php
/**
 * Rule Validator
 *
 * Validates rule configuration and detects conflicts.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 */

/**
 * Rule validator class.
 *
 * Validates rules and detects potential conflicts.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Validator {

	/**
	 * Validation errors.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $errors = array();

	/**
	 * Validation warnings.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $warnings = array();

	/**
	 * Custom validation rules.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $custom_validators = array();

	/**
	 * Validate a rule.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return bool                      True if valid.
	 */
	public function validate( $rule ) {
		$this->errors = array();
		$this->warnings = array();

		// Run built-in validations
		$this->validate_basic_fields( $rule );
		$this->validate_discount_config( $rule );
		$this->validate_date_range( $rule );
		$this->validate_usage_limits( $rule );
		$this->validate_conditions( $rule );

		// Run custom validators
		$this->run_custom_validators( $rule );

		return empty( $this->errors );
	}

	/**
	 * Validate multiple rules for conflicts.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return bool         True if no conflicts.
	 */
	public function validate_rules( $rules ) {
		$this->errors = array();
		$this->warnings = array();

		// Check for overlapping rules
		$this->check_overlapping_rules( $rules );

		// Check for conflicting priorities
		$this->check_priority_conflicts( $rules );

		// Check for stackability issues
		$this->check_stackability_issues( $rules );

		return empty( $this->errors );
	}

	/**
	 * Get validation errors.
	 *
	 * @since  1.0.0
	 * @return array Validation errors.
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Get validation warnings.
	 *
	 * @since  1.0.0
	 * @return array Validation warnings.
	 */
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * Check if there are any errors.
	 *
	 * @since  1.0.0
	 * @return bool True if errors exist.
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	/**
	 * Check if there are any warnings.
	 *
	 * @since  1.0.0
	 * @return bool True if warnings exist.
	 */
	public function has_warnings() {
		return ! empty( $this->warnings );
	}

	/**
	 * Add a custom validator.
	 *
	 * @since  1.0.0
	 * @param  string   $name     Validator name.
	 * @param  callable $callback Validator callback.
	 * @return void
	 */
	public function add_validator( $name, $callback ) {
		if ( is_callable( $callback ) ) {
			$this->custom_validators[ $name ] = $callback;
		}
	}

	/**
	 * Remove a custom validator.
	 *
	 * @since  1.0.0
	 * @param  string $name Validator name.
	 * @return void
	 */
	public function remove_validator( $name ) {
		unset( $this->custom_validators[ $name ] );
	}

	/**
	 * Clear all errors and warnings.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function clear() {
		$this->errors = array();
		$this->warnings = array();
	}

	/**
	 * Validate basic rule fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return void
	 */
	private function validate_basic_fields( $rule ) {
		// Name is required
		if ( empty( $rule->get_name() ) ) {
			$this->add_error( 'name', __( 'Rule name is required.', 'discount-tools' ) );
		}

		// Discount type is required
		if ( empty( $rule->get_discount_type() ) ) {
			$this->add_error( 'discount_type', __( 'Discount type is required.', 'discount-tools' ) );
		}

		// Rule type is required
		if ( empty( $rule->get_rule_type() ) ) {
			$this->add_error( 'rule_type', __( 'Rule type is required.', 'discount-tools' ) );
		}

		// Priority should be non-negative
		if ( $rule->get_priority() < 0 ) {
			$this->add_error( 'priority', __( 'Priority must be a non-negative number.', 'discount-tools' ) );
		}
	}

	/**
	 * Validate discount configuration.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return void
	 */
	private function validate_discount_config( $rule ) {
		$discount_type = $rule->get_discount_type();
		$discount_value = $rule->get_discount_value();

		// Discount value is required
		if ( $discount_value === null || $discount_value === '' ) {
			$this->add_error( 'discount_value', __( 'Discount value is required.', 'discount-tools' ) );
			return;
		}

		// Validate based on discount type
		switch ( $discount_type ) {
			case 'percentage':
				if ( $discount_value < 0 || $discount_value > 100 ) {
					$this->add_error( 'discount_value', __( 'Percentage discount must be between 0 and 100.', 'discount-tools' ) );
				}
				if ( $discount_value > 100 ) {
					$this->add_warning( 'discount_value', __( 'Discount over 100% will result in negative prices.', 'discount-tools' ) );
				}
				break;

			case 'fixed_amount':
				if ( $discount_value < 0 ) {
					$this->add_error( 'discount_value', __( 'Fixed discount amount cannot be negative.', 'discount-tools' ) );
				}
				if ( $discount_value > 10000 ) {
					$this->add_warning( 'discount_value', __( 'Very large discount amount. Please verify.', 'discount-tools' ) );
				}
				break;

			case 'price_override':
				if ( $discount_value < 0 ) {
					$this->add_error( 'discount_value', __( 'Override price cannot be negative.', 'discount-tools' ) );
				}
				break;

			case 'bogo':
				// BOGO validation - discount_value should be 0 or the config should be in discount_config
				// For now, just validate that it's not negative if numeric
				if ( is_numeric( $discount_value ) && $discount_value < 0 ) {
					$this->add_error( 'discount_value', __( 'BOGO discount value cannot be negative.', 'discount-tools' ) );
				}
				break;

			case 'tiered':
			case 'bulk':
				if ( ! is_array( $discount_value ) ) {
					$this->add_error( 'discount_value', __( 'Tiered/bulk discount requires an array of tiers.', 'discount-tools' ) );
				} else {
					$this->validate_tiers( $discount_value );
				}
				break;

			case 'bundle':
				// Bundle discount - fixed price for set quantity
				if ( is_numeric( $discount_value ) && $discount_value < 0 ) {
					$this->add_error( 'discount_value', __( 'Bundle price cannot be negative.', 'discount-tools' ) );
				}
				break;

			case 'bxgy_same':
			case 'bxgy_any':
				// Buy X Get Y discount - discount_value can be 0 or percentage/amount for Y items
				if ( is_numeric( $discount_value ) && $discount_value < 0 ) {
					$this->add_error( 'discount_value', __( 'BXGY discount value cannot be negative.', 'discount-tools' ) );
				}
				break;

			default:
				$this->add_error( 'discount_type', __( 'Invalid discount type.', 'discount-tools' ) );
		}

		// Validate max discount
		$max_discount = $rule->get_max_discount();
		if ( $max_discount !== null && $max_discount < 0 ) {
			$this->add_error( 'max_discount', __( 'Max discount cannot be negative.', 'discount-tools' ) );
		}

		// Validate min discount
		$min_discount = $rule->get_min_discount();
		if ( $min_discount !== null && $min_discount < 0 ) {
			$this->add_error( 'min_discount', __( 'Min discount cannot be negative.', 'discount-tools' ) );
		}

		// Check min/max relationship
		if ( $min_discount !== null && $max_discount !== null && $min_discount > $max_discount ) {
			$this->add_error( 'min_discount', __( 'Min discount cannot be greater than max discount.', 'discount-tools' ) );
		}
	}

	/**
	 * Validate tier configuration.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $tiers Tier configuration.
	 * @return void
	 */
	private function validate_tiers( $tiers ) {
		if ( empty( $tiers ) ) {
			$this->add_error( 'discount_value', __( 'At least one tier is required.', 'discount-tools' ) );
			return;
		}

		$previous_min = -1;

		foreach ( $tiers as $index => $tier ) {
			// Check required fields
			if ( ! isset( $tier['min'] ) ) {
				$this->add_error( "tier_{$index}", __( 'Tier min quantity is required.', 'discount-tools' ) );
				continue;
			}

			if ( ! isset( $tier['discount'] ) ) {
				$this->add_error( "tier_{$index}", __( 'Tier discount value is required.', 'discount-tools' ) );
				continue;
			}

			$min = intval( $tier['min'] );
			$max = isset( $tier['max'] ) ? intval( $tier['max'] ) : null;
			$discount = floatval( $tier['discount'] );

			// Validate min
			if ( $min < 0 ) {
				$this->add_error( "tier_{$index}", __( 'Tier min quantity cannot be negative.', 'discount-tools' ) );
			}

			// Check for gaps
			if ( $min !== $previous_min + 1 && $previous_min !== -1 ) {
				$this->add_warning( "tier_{$index}", __( 'Gap detected between tiers.', 'discount-tools' ) );
			}

			// Validate max
			if ( $max !== null ) {
				if ( $max < $min ) {
					$this->add_error( "tier_{$index}", __( 'Tier max must be greater than or equal to min.', 'discount-tools' ) );
				}
				$previous_min = $max;
			} else {
				$previous_min = $min;
			}

			// Validate discount
			if ( $discount < 0 ) {
				$this->add_error( "tier_{$index}", __( 'Tier discount cannot be negative.', 'discount-tools' ) );
			}
		}
	}

	/**
	 * Validate date range.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return void
	 */
	private function validate_date_range( $rule ) {
		$start_date = $rule->get_start_date();
		$end_date = $rule->get_end_date();

		// If both dates are set, end must be after start
		if ( $start_date && $end_date ) {
			$start_timestamp = strtotime( $start_date );
			$end_timestamp = strtotime( $end_date );

			if ( $end_timestamp < $start_timestamp ) {
				$this->add_error( 'end_date', __( 'End date must be after start date.', 'discount-tools' ) );
			}

			// Warn if rule has already expired
			if ( $end_timestamp < time() ) {
				$this->add_warning( 'end_date', __( 'This rule has already expired.', 'discount-tools' ) );
			}
		}

		// Warn if start date is in the past
		if ( $start_date && strtotime( $start_date ) < time() ) {
			$this->add_warning( 'start_date', __( 'Start date is in the past.', 'discount-tools' ) );
		}
	}

	/**
	 * Validate usage limits.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return void
	 */
	private function validate_usage_limits( $rule ) {
		$usage_limit = $rule->get_usage_limit();
		$usage_limit_per_user = $rule->get_usage_limit_per_user();

		// Validate usage limit
		if ( $usage_limit !== null && $usage_limit < 0 ) {
			$this->add_error( 'usage_limit', __( 'Usage limit cannot be negative.', 'discount-tools' ) );
		}

		// Validate per-user limit
		if ( $usage_limit_per_user !== null && $usage_limit_per_user < 0 ) {
			$this->add_error( 'usage_limit_per_user', __( 'Per-user usage limit cannot be negative.', 'discount-tools' ) );
		}

		// Check relationship
		if ( $usage_limit !== null && $usage_limit_per_user !== null ) {
			if ( $usage_limit_per_user > $usage_limit ) {
				$this->add_warning( 'usage_limit_per_user', __( 'Per-user limit exceeds total limit.', 'discount-tools' ) );
			}
		}

		// Warn if usage limit is already reached
		$usage_count = $rule->get_usage_count();
		if ( $usage_limit !== null && $usage_count >= $usage_limit ) {
			$this->add_warning( 'usage_limit', __( 'Usage limit has been reached.', 'discount-tools' ) );
		}
	}

	/**
	 * Validate conditions.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return void
	 */
	private function validate_conditions( $rule ) {
		$conditions = $rule->get_conditions();

		if ( empty( $conditions ) ) {
			$this->add_warning( 'conditions', __( 'Rule has no conditions. It will apply to all orders.', 'discount-tools' ) );
			return;
		}

		// Validate each condition
		foreach ( $conditions as $index => $condition ) {
			if ( ! is_a( $condition, 'Discount_Tools_Condition' ) ) {
				$this->add_error( "condition_{$index}", __( 'Invalid condition object.', 'discount-tools' ) );
				continue;
			}

			// Use condition's built-in validation
			if ( ! $condition->validate() ) {
				$this->add_error( "condition_{$index}", __( 'Invalid condition configuration.', 'discount-tools' ) );
			}
		}
	}

	/**
	 * Run custom validators.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule Rule to validate.
	 * @return void
	 */
	private function run_custom_validators( $rule ) {
		foreach ( $this->custom_validators as $name => $callback ) {
			try {
				$result = call_user_func( $callback, $rule, $this );
				if ( $result === false ) {
					/* translators: %s: validator name */
					$this->add_error( $name, sprintf( __( 'Custom validator "%s" failed.', 'discount-tools' ), $name ) );
				}
			} catch ( Exception $e ) {
					/* translators: %1$s: validator name, %2$s: error message */
					$this->add_error( $name, sprintf( __( 'Custom validator "%1$s" error: %2$s', 'discount-tools' ), $name, $e->getMessage() ) );
			}
		}
	}

	/**
	 * Check for overlapping rules.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $rules Array of rules.
	 * @return void
	 */
	private function check_overlapping_rules( $rules ) {
		$active_rules = array_filter(
			$rules,
			function ( $rule ) {
				return $rule->get_status() === 'active';
			}
		);

		$count = count( $active_rules );

		for ( $i = 0; $i < $count; $i++ ) {
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$rule1 = $active_rules[ $i ];
				$rule2 = $active_rules[ $j ];

				if ( $this->rules_overlap( $rule1, $rule2 ) ) {
					$message = sprintf(
						/* translators: %1$s: first rule name, %2$s: second rule name */
						__( 'Rules "%1$s" and "%2$s" may overlap.', 'discount-tools' ),
						$rule1->get_name(),
						$rule2->get_name()
					);

					// Only warning if both are stackable
					if ( $rule1->is_stackable() && $rule2->is_stackable() ) {
						$this->add_warning( 'overlap', $message );
					} else {
						// Error if not stackable - potential conflict
						$this->add_error( 'overlap', $message . ' ' . __( 'Non-stackable rules conflict.', 'discount-tools' ) );
					}
				}
			}
		}
	}

	/**
	 * Check if two rules overlap.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule1 First rule.
	 * @param  Discount_Tools_Rule $rule2 Second rule.
	 * @return bool                       True if rules overlap.
	 */
	private function rules_overlap( $rule1, $rule2 ) {
		// Same rule type
		if ( $rule1->get_rule_type() !== $rule2->get_rule_type() ) {
			return false;
		}

		// Check date overlap
		if ( ! $this->dates_overlap( $rule1, $rule2 ) ) {
			return false;
		}

		// If both have no conditions, they overlap
		if ( empty( $rule1->get_conditions() ) && empty( $rule2->get_conditions() ) ) {
			return true;
		}

		// Complex condition comparison would require deeper analysis
		// For now, we assume potential overlap if they have conditions
		return true;
	}

	/**
	 * Check if date ranges overlap.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule1 First rule.
	 * @param  Discount_Tools_Rule $rule2 Second rule.
	 * @return bool                       True if dates overlap.
	 */
	private function dates_overlap( $rule1, $rule2 ) {
		$start1 = $rule1->get_start_date() ? strtotime( $rule1->get_start_date() ) : 0;
		$end1 = $rule1->get_end_date() ? strtotime( $rule1->get_end_date() ) : PHP_INT_MAX;
		$start2 = $rule2->get_start_date() ? strtotime( $rule2->get_start_date() ) : 0;
		$end2 = $rule2->get_end_date() ? strtotime( $rule2->get_end_date() ) : PHP_INT_MAX;

		// Check if ranges overlap
		return $start1 <= $end2 && $start2 <= $end1;
	}

	/**
	 * Check for priority conflicts.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $rules Array of rules.
	 * @return void
	 */
	private function check_priority_conflicts( $rules ) {
		$priorities = array();

		foreach ( $rules as $rule ) {
			if ( $rule->get_status() !== 'active' ) {
				continue;
			}

			$priority = $rule->get_priority();
			$rule_type = $rule->get_rule_type();

			$key = $rule_type . '_' . $priority;

			if ( ! isset( $priorities[ $key ] ) ) {
				$priorities[ $key ] = array();
			}

			$priorities[ $key ][] = $rule->get_name();
		}

		// Report rules with same priority
		foreach ( $priorities as $key => $rule_names ) {
			if ( count( $rule_names ) > 1 ) {
				$this->add_warning(
					'priority',
					sprintf(
						/* translators: %s: list of rule names */
						__( 'Multiple rules have the same priority: %s', 'discount-tools' ),
						implode( ', ', $rule_names )
					)
				);
			}
		}
	}

	/**
	 * Check for stackability issues.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $rules Array of rules.
	 * @return void
	 */
	private function check_stackability_issues( $rules ) {
		$stackable_rules = array();
		$non_stackable_rules = array();

		foreach ( $rules as $rule ) {
			if ( $rule->get_status() !== 'active' ) {
				continue;
			}

			if ( $rule->is_stackable() ) {
				$stackable_rules[] = $rule;
			} else {
				$non_stackable_rules[] = $rule;
			}
		}

		// Warn if many stackable rules exist
		if ( count( $stackable_rules ) > 5 ) {
			$this->add_warning(
				'stackable',
				sprintf(
					/* translators: %d: number of stackable rules */
					__( 'You have %d stackable rules. This may result in very large discounts.', 'discount-tools' ),
					count( $stackable_rules )
				)
			);
		}

		// Warn if mixing stackable and non-stackable
		if ( ! empty( $stackable_rules ) && ! empty( $non_stackable_rules ) ) {
			$this->add_warning(
				'stackable',
				__( 'Mixing stackable and non-stackable rules may cause unexpected behavior.', 'discount-tools' )
			);
		}
	}

	/**
	 * Add an error.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $field   Field name.
	 * @param  string $message Error message.
	 * @return void
	 */
	private function add_error( $field, $message ) {
		$this->errors[] = array(
			'field'   => $field,
			'message' => $message,
			'type'    => 'error',
		);
	}

	/**
	 * Add a warning.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $field   Field name.
	 * @param  string $message Warning message.
	 * @return void
	 */
	private function add_warning( $field, $message ) {
		$this->warnings[] = array(
			'field'   => $field,
			'message' => $message,
			'type'    => 'warning',
		);
	}
}
