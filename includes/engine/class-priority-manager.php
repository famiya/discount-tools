<?php
/**
 * Priority Manager
 *
 * Manages rule priority and stacking logic.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 */

/**
 * Priority manager class.
 *
 * Handles rule ordering and application logic.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Priority_Manager {

	/**
	 * Sort rules by priority.
	 *
	 * Higher priority (larger number) comes first.
	 * Same priority sorted by ID (ascending).
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of Discount_Tools_Rule objects.
	 * @return array        Sorted rules.
	 */
	public function sort_rules( $rules ) {
		if ( empty( $rules ) ) {
			return array();
		}

		// Create a copy to avoid modifying original array
		$sorted = array_values( $rules );

		// Sort using custom comparator
		usort( $sorted, array( $this, 'compare_rules' ) );

		return $sorted;
	}

	/**
	 * Filter rules that can be applied together.
	 *
	 * CORRECT STACKING LOGIC:
	 * - Stackable rules (apply_mode='all') can work together with ANY other rules
	 * - Non-stackable rules (apply_mode='first') are mutually exclusive with each other
	 * - When there are non-stackable rules, only the highest priority one is used
	 * - All stackable rules are ALWAYS included
	 *
	 * Example:
	 * - Rule #7 (Priority 2, non-stackable): 8折優惠
	 * - Rule #8 (Priority 11, stackable): 買WayFunky獲贈品
	 * Result: Both apply! (Rule #7 gives discount, Rule #8 gives gift)
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of sorted rules (MUST be sorted by priority).
	 * @return array        Filtered rules that can be applied.
	 */
	public function filter_applicable_rules( $rules ) {
		if ( empty( $rules ) ) {
			return array();
		}

		$applicable = array();
		$non_stackable_selected = null;

		// Separate rules into stackable and non-stackable
		foreach ( $rules as $rule ) {
			if ( $rule->is_stackable() ) {
				// Stackable rules are always included
				$applicable[] = $rule;
			} else {
				// For non-stackable rules, only select the first (highest priority) one
				if ( $non_stackable_selected === null ) {
					$non_stackable_selected = $rule;
				}
			}
		}

		// Add the selected non-stackable rule (if any)
		if ( $non_stackable_selected !== null ) {
			// Insert at the beginning to maintain priority order
			array_unshift( $applicable, $non_stackable_selected );
		}

		return $applicable;
	}

	/**
	 * Get rules by priority groups.
	 *
	 * Groups rules by their priority level.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Rules grouped by priority.
	 */
	public function group_by_priority( $rules ) {
		$groups = array();

		foreach ( $rules as $rule ) {
			$priority = $rule->get_priority();

			if ( ! isset( $groups[ $priority ] ) ) {
				$groups[ $priority ] = array();
			}

			$groups[ $priority ][] = $rule;
		}

		// Sort groups by priority (descending)
		krsort( $groups );

		return $groups;
	}

	/**
	 * Filter rules by type.
	 *
	 * @since  1.0.0
	 * @param  array  $rules Array of rules.
	 * @param  string $type  Rule type to filter.
	 * @return array         Filtered rules.
	 */
	public function filter_by_type( $rules, $type ) {
		return array_filter(
			$rules,
			function ( $rule ) use ( $type ) {
				return $rule->get_rule_type() === $type;
			}
		);
	}

	/**
	 * Filter stackable rules.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Stackable rules only.
	 */
	public function filter_stackable( $rules ) {
		return array_filter(
			$rules,
			function ( $rule ) {
				return $rule->is_stackable();
			}
		);
	}

	/**
	 * Filter non-stackable rules.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Non-stackable rules only.
	 */
	public function filter_non_stackable( $rules ) {
		return array_filter(
			$rules,
			function ( $rule ) {
				return ! $rule->is_stackable();
			}
		);
	}

	/**
	 * Get highest priority rule.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return Discount_Tools_Rule|null Highest priority rule or null.
	 */
	public function get_highest_priority( $rules ) {
		if ( empty( $rules ) ) {
			return null;
		}

		$sorted = $this->sort_rules( $rules );
		return reset( $sorted );
	}

	/**
	 * Get lowest priority rule.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return Discount_Tools_Rule|null Lowest priority rule or null.
	 */
	public function get_lowest_priority( $rules ) {
		if ( empty( $rules ) ) {
			return null;
		}

		$sorted = $this->sort_rules( $rules );
		return end( $sorted );
	}

	/**
	 * Adjust rule priority.
	 *
	 * Updates a rule's priority and re-sorts the collection.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $rule     Rule to adjust.
	 * @param  int                 $priority New priority.
	 * @return void
	 */
	public function adjust_priority( $rule, $priority ) {
		$rule->set_priority( $priority );
	}

	/**
	 * Resolve priority conflicts.
	 *
	 * Ensures each rule has a unique priority within its type.
	 * Rules with same priority will be adjusted.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Rules with resolved priorities.
	 */
	public function resolve_conflicts( $rules ) {
		if ( empty( $rules ) ) {
			return array();
		}

		// Group by type first
		$types = array();
		foreach ( $rules as $rule ) {
			$type = $rule->get_rule_type();
			if ( ! isset( $types[ $type ] ) ) {
				$types[ $type ] = array();
			}
			$types[ $type ][] = $rule;
		}

		$resolved = array();

		// Resolve conflicts within each type
		foreach ( $types as $type => $type_rules ) {
			$priority_map = array();

			foreach ( $type_rules as $rule ) {
				$priority = $rule->get_priority();

				// If priority is already used, increment until we find a free one
				while ( isset( $priority_map[ $priority ] ) ) {
					$priority++;
				}

				// Adjust if needed
				if ( $priority !== $rule->get_priority() ) {
					$this->adjust_priority( $rule, $priority );
				}

				$priority_map[ $priority ] = true;
				$resolved[] = $rule;
			}
		}

		return $resolved;
	}

	/**
	 * Calculate effective priority.
	 *
	 * Considers both base priority and dynamic factors.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $rule    Rule object.
	 * @param  array               $context Context for calculation.
	 * @return int                          Effective priority.
	 */
	public function calculate_effective_priority( $rule, $context = array() ) {
		$base_priority = $rule->get_priority();

		// Apply filters to allow dynamic priority adjustment
		$effective_priority = apply_filters(
			'discount_tools_effective_priority',
			$base_priority,
			$rule,
			$context
		);

		return intval( $effective_priority );
	}

	/**
	 * Check if rules can be stacked.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules to check.
	 * @return bool         True if all rules are stackable.
	 */
	public function can_stack( $rules ) {
		foreach ( $rules as $rule ) {
			if ( ! $rule->is_stackable() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Merge stackable rules.
	 *
	 * Returns rules that should be applied together.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Rules to apply.
	 */
	public function merge_stackable_rules( $rules ) {
		$sorted = $this->sort_rules( $rules );
		return $this->filter_applicable_rules( $sorted );
	}

	/**
	 * Get rule application order.
	 *
	 * Returns the order in which rules should be evaluated.
	 *
	 * @since  1.0.0
	 * @param  array $rules   Array of rules.
	 * @param  array $context Context for ordering.
	 * @return array          Ordered rules.
	 */
	public function get_application_order( $rules, $context = array() ) {
		// Calculate effective priorities
		$rules_with_effective = array();
		foreach ( $rules as $rule ) {
			$effective = $this->calculate_effective_priority( $rule, $context );
			$rules_with_effective[] = array(
				'rule'     => $rule,
				'priority' => $effective,
			);
		}

		// Sort by effective priority
		usort(
			$rules_with_effective,
			function ( $a, $b ) {
				if ( $a['priority'] === $b['priority'] ) {
					return $a['rule']->get_id() - $b['rule']->get_id();
				}
				return $b['priority'] - $a['priority'];
			}
		);

		// Extract rules
		return array_map(
			function ( $item ) {
				return $item['rule'];
			},
			$rules_with_effective
		);
	}

	/**
	 * Compare two rules for sorting.
	 *
	 * Used by usort() in sort_rules().
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule $a First rule.
	 * @param  Discount_Tools_Rule $b Second rule.
	 * @return int                    Comparison result.
	 */
	private function compare_rules( $a, $b ) {
		$priority_a = $a->get_priority();
		$priority_b = $b->get_priority();

		// Lower priority number = higher priority (ascending order)
		// Priority 1 should come before Priority 10
		if ( $priority_a !== $priority_b ) {
			return $priority_a - $priority_b;
		}

		// Same priority: sort by ID (ascending)
		return $a->get_id() - $b->get_id();
	}

	/**
	 * Get stacking summary.
	 *
	 * Returns information about how rules will be stacked.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Stacking summary.
	 */
	public function get_stacking_summary( $rules ) {
		$sorted = $this->sort_rules( $rules );
		$applicable = $this->filter_applicable_rules( $sorted );

		$stackable = $this->filter_stackable( $applicable );
		$non_stackable = $this->filter_non_stackable( $applicable );

		return array(
			'total_rules'       => count( $rules ),
			'sorted_rules'      => count( $sorted ),
			'applicable_rules'  => count( $applicable ),
			'stackable_rules'   => count( $stackable ),
			'non_stackable_rules' => count( $non_stackable ),
			'will_stack'        => count( $stackable ) > 1,
			'rules'             => array_map(
				function ( $rule ) {
					return array(
						'id'       => $rule->get_id(),
						'name'     => $rule->get_name(),
						'priority' => $rule->get_priority(),
						'stackable' => $rule->is_stackable(),
					);
				},
				$applicable
			),
		);
	}

	/**
	 * Rebalance priorities.
	 *
	 * Redistributes priorities to maintain even spacing.
	 *
	 * @since  1.0.0
	 * @param  array $rules   Array of rules.
	 * @param  int   $spacing Spacing between priorities.
	 * @return array          Rules with rebalanced priorities.
	 */
	public function rebalance_priorities( $rules, $spacing = 10 ) {
		if ( empty( $rules ) ) {
			return array();
		}

		$sorted = $this->sort_rules( $rules );
		$priority = 1000; // Start high

		foreach ( $sorted as $rule ) {
			$this->adjust_priority( $rule, $priority );
			$priority -= $spacing;
		}

		return $sorted;
	}

	/**
	 * Find priority gaps.
	 *
	 * Identifies gaps in priority numbering.
	 *
	 * @since  1.0.0
	 * @param  array $rules Array of rules.
	 * @return array        Priority gaps.
	 */
	public function find_priority_gaps( $rules ) {
		if ( empty( $rules ) ) {
			return array();
		}

		$sorted = $this->sort_rules( $rules );
		$gaps = array();
		$previous = null;

		foreach ( $sorted as $rule ) {
			$current = $rule->get_priority();

			if ( $previous !== null && ( $previous - $current ) > 1 ) {
				$gaps[] = array(
					'from' => $current + 1,
					'to'   => $previous - 1,
					'size' => ( $previous - $current ) - 1,
				);
			}

			$previous = $current;
		}

		return $gaps;
	}

	/**
	 * Suggest priority for new rule.
	 *
	 * @since  1.0.0
	 * @param  array  $existing_rules Existing rules.
	 * @param  string $position       Position: 'high', 'low', or 'middle'.
	 * @return int                    Suggested priority.
	 */
	public function suggest_priority( $existing_rules, $position = 'middle' ) {
		if ( empty( $existing_rules ) ) {
			return 100; // Default for first rule
		}

		$sorted = $this->sort_rules( $existing_rules );

		switch ( $position ) {
			case 'high':
				// Higher than highest
				$highest = reset( $sorted );
				return $highest->get_priority() + 10;

			case 'low':
				// Lower than lowest
				$lowest = end( $sorted );
				$priority = $lowest->get_priority() - 10;
				return max( 0, $priority ); // Never negative

			case 'middle':
			default:
				// Average priority
				$total = 0;
				foreach ( $sorted as $rule ) {
					$total += $rule->get_priority();
				}
				return intval( round( $total / count( $sorted ) ) );
		}
	}

	/**
	 * Apply rule selection strategy.
	 *
	 * @since  1.1.0
	 * @param  array  $rules    Array of Rule objects.
	 * @param  string $strategy Strategy: highest/lowest/priority/stack_all.
	 * @param  array  $context  Context for calculation (e.g., cart_total, product_id).
	 * @return array            Filtered rules according to strategy.
	 */
	public function apply_strategy( $rules, $strategy, $context = array() ) {
		if ( empty( $rules ) ) {
			return array();
		}

		switch ( $strategy ) {
			case 'highest':
				return $this->select_highest_discount( $rules, $context );

			case 'lowest':
				return $this->select_lowest_discount( $rules, $context );

			case 'priority':
				return $this->select_by_priority( $rules );

			case 'stack_all':
				return $rules; // Return all rules for stacking

			default:
				// Fallback to priority-based
				return $this->select_by_priority( $rules );
		}
	}

	/**
	 * Select rule with highest discount value.
	 *
	 * @since  1.1.0
	 * @access private
	 * @param  array $rules   Array of Rule objects.
	 * @param  array $context Context for calculation.
	 * @return array          Single rule with max discount.
	 */
	private function select_highest_discount( $rules, $context = array() ) {
		$applicable_rules = array();

		// Calculate discount for each rule and filter out rules with 0 discount
		foreach ( $rules as $rule ) {
			$discount = $this->calculate_potential_discount( $rule, $context );

			if ( $discount > 0 ) {
				$applicable_rules[] = array(
					'rule'     => $rule,
					'discount' => $discount,
				);
			}
		}

		// If no rules are applicable, return empty
		if ( empty( $applicable_rules ) ) {
			return array();
		}

		// Sort by discount amount (descending)
		usort( $applicable_rules, function( $a, $b ) {
			return $b['discount'] - $a['discount'];
		});

		// Return the rule with highest discount
		return array( $applicable_rules[0]['rule'] );
	}

	/**
	 * Select rule with lowest discount value.
	 *
	 * @since  1.1.0
	 * @access private
	 * @param  array $rules   Array of Rule objects.
	 * @param  array $context Context for calculation.
	 * @return array          Single rule with min discount.
	 */
	private function select_lowest_discount( $rules, $context = array() ) {
		$applicable_rules = array();

		// Calculate discount for each rule and filter out rules with 0 discount
		foreach ( $rules as $rule ) {
			$discount = $this->calculate_potential_discount( $rule, $context );

			if ( $discount > 0 ) {
				$applicable_rules[] = array(
					'rule'     => $rule,
					'discount' => $discount,
				);
			}
		}

		// If no rules are applicable, return empty
		if ( empty( $applicable_rules ) ) {
			return array();
		}

		// Sort by discount amount (ascending)
		usort( $applicable_rules, function( $a, $b ) {
			return $a['discount'] - $b['discount'];
		});

		// Return the rule with lowest discount
		return array( $applicable_rules[0]['rule'] );
	}

	/**
	 * Select rule by priority order.
	 * 
	 * Uses filter_applicable_rules to handle stacking logic:
	 * - Non-stackable rules are mutually exclusive
	 * - Only stackable rules can be combined
	 *
	 * @since  1.1.0
	 * @access private
	 * @param  array $rules Array of Rule objects.
	 * @return array        Array of rules to apply (single or multiple if all stackable).
	 */
	private function select_by_priority( $rules ) {
		// Sort rules by priority (descending)
		$sorted = $this->sort_rules( $rules );
		
		// Filter according to stacking rules
		// This handles all the logic about stackable vs non-stackable
		$applicable = $this->filter_applicable_rules( $sorted );
		
		return $applicable;
	}

	/**
	 * Calculate potential discount for a rule.
	 *
	 * @since  1.1.0
	 * @access private
	 * @param  Discount_Tools_Rule $rule    Rule object.
	 * @param  array               $context Context (e.g., price, cart_total).
	 * @return float                        Potential discount amount.
	 */
	private function calculate_potential_discount( $rule, $context = array() ) {
		$calculator = new Discount_Tools_Calculator();
		
		// Determine base amount for calculation
		$base_amount = 0;
		if ( isset( $context['price'] ) ) {
			$base_amount = $context['price'];
		} elseif ( isset( $context['cart_total'] ) ) {
			$base_amount = $context['cart_total'];
		} elseif ( function_exists( 'WC' ) && WC()->cart ) {
			$base_amount = WC()->cart->get_subtotal();
		}

		if ( $base_amount <= 0 ) {
			return 0;
		}

		$quantity = isset( $context['quantity'] ) ? $context['quantity'] : 1;

		// Calculate discount
		$result = $calculator->calculate(
			$base_amount,
			$rule->get_discount_type(),
			$rule->get_discount_value(),
			$quantity
		);

		return isset( $result['discount_amount'] ) ? $result['discount_amount'] : 0;
	}
}
