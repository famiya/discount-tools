<?php
/**
 * Calculator
 *
 * Handles all discount calculations.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 */

/**
 * Calculator class.
 *
 * Performs discount calculations for all discount types with precision handling.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/engine
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Calculator {

	/**
	 * Calculate discount for a product.
	 *
	 * @since  1.0.0
	 * @param  float  $original_price Original price.
	 * @param  string $discount_type  Discount type (percentage, fixed, price_override).
	 * @param  float  $discount_value Discount value.
	 * @param  int    $quantity       Quantity. Default 1.
	 * @param  array  $options        Additional options.
	 * @return array {
	 *     Calculation result.
	 *
	 *     @type float  $original_price  Original price.
	 *     @type float  $discount_amount Total discount amount.
	 *     @type float  $final_price     Final price after discount.
	 *     @type float  $unit_price      Price per unit after discount.
	 *     @type float  $discount_percent Discount percentage.
	 *     @type string $discount_type   Discount type applied.
	 * }
	 */
	public function calculate( $original_price, $discount_type, $discount_value, $quantity = 1, $options = array() ) {
		// Ensure proper types and precision
		$original_price = $this->format_price( $original_price );
		$discount_value = $this->format_price( $discount_value );
		$quantity = max( 1, intval( $quantity ) );

		// Calculate based on discount type
		switch ( $discount_type ) {
			case 'percentage':
				$result = $this->calculate_percentage( $original_price, $discount_value, $quantity, $options );
				break;

			case 'fixed':
			case 'fixed_amount':
				$result = $this->calculate_fixed_amount( $original_price, $discount_value, $quantity, $options );
				break;

			case 'price_override':
				$result = $this->calculate_price_override( $original_price, $discount_value, $quantity, $options );
				break;

			default:
				$result = $this->get_zero_discount( $original_price, $quantity );
				break;
		}

		$result['discount_type'] = $discount_type;

		return $result;
	}

	/**
	 * Calculate percentage discount.
	 *
	 * @since  1.0.0
	 * @param  float $original_price Original price.
	 * @param  float $percentage     Discount percentage (0-100).
	 * @param  int   $quantity       Quantity.
	 * @param  array $options        Additional options.
	 * @return array Calculation result.
	 */
	private function calculate_percentage( $original_price, $percentage, $quantity, $options = array() ) {
		// Validate percentage range
		$percentage = max( 0, min( 100, $percentage ) );

		// Calculate discount amount per unit
		if ( $this->use_bcmath() ) {
			$discount_per_unit = bcmul( $original_price, bcdiv( $percentage, '100', 4 ), 2 );
		} else {
			$discount_per_unit = round( $original_price * ( $percentage / 100 ), 2 );
		}

		// Calculate total discount
		$discount_amount = $this->multiply( $discount_per_unit, $quantity );

		// Calculate final prices
		$unit_price = $this->subtract( $original_price, $discount_per_unit );
		$final_price = $this->multiply( $unit_price, $quantity );

		// Apply minimum/maximum constraints if set
		if ( isset( $options['min_discount'] ) ) {
			$min_discount = $this->format_price( $options['min_discount'] );
			if ( $this->compare( $discount_amount, $min_discount ) < 0 ) {
				$discount_amount = $min_discount;
				$final_price = $this->subtract( $this->multiply( $original_price, $quantity ), $discount_amount );
				$unit_price = $this->divide( $final_price, $quantity );
			}
		}

		if ( isset( $options['max_discount'] ) ) {
			$max_discount = $this->format_price( $options['max_discount'] );
			if ( $this->compare( $discount_amount, $max_discount ) > 0 ) {
				$discount_amount = $max_discount;
				$final_price = $this->subtract( $this->multiply( $original_price, $quantity ), $discount_amount );
				$unit_price = $this->divide( $final_price, $quantity );
			}
		}

		return array(
			'original_price'   => $original_price,
			'discount_amount'  => $discount_amount,
			'final_price'      => max( 0, $final_price ),
			'unit_price'       => max( 0, $unit_price ),
			'discount_percent' => $percentage,
			'quantity'         => $quantity,
		);
	}

	/**
	 * Calculate fixed amount discount.
	 *
	 * @since  1.0.0
	 * @param  float $original_price Original price.
	 * @param  float $fixed_amount   Fixed discount amount.
	 * @param  int   $quantity       Quantity.
	 * @param  array $options        Additional options.
	 * @return array Calculation result.
	 */
	private function calculate_fixed_amount( $original_price, $fixed_amount, $quantity, $options = array() ) {
		$fixed_amount = $this->format_price( $fixed_amount );

		// Determine if discount is per item or per order
		$per_item = isset( $options['per_item'] ) ? (bool) $options['per_item'] : true;

		if ( $per_item ) {
			// Discount applies per item
			$discount_per_unit = min( $fixed_amount, $original_price );
			$discount_amount = $this->multiply( $discount_per_unit, $quantity );
			$unit_price = $this->subtract( $original_price, $discount_per_unit );
		} else {
			// Discount applies to entire order
			$total_price = $this->multiply( $original_price, $quantity );
			$discount_amount = min( $fixed_amount, $total_price );
			$unit_price = $this->divide( $this->subtract( $total_price, $discount_amount ), $quantity );
		}

		$final_price = $this->multiply( $unit_price, $quantity );

		// Calculate discount percentage
		$total_original = $this->multiply( $original_price, $quantity );
		$discount_percent = $total_original > 0 
			? $this->multiply( $this->divide( $discount_amount, $total_original ), 100 )
			: 0;

		return array(
			'original_price'   => $original_price,
			'discount_amount'  => $discount_amount,
			'final_price'      => max( 0, $final_price ),
			'unit_price'       => max( 0, $unit_price ),
			'discount_percent' => $this->format_price( $discount_percent ),
			'quantity'         => $quantity,
		);
	}

	/**
	 * Calculate price override discount.
	 *
	 * @since  1.0.0
	 * @param  float $original_price Original price.
	 * @param  float $override_price New price.
	 * @param  int   $quantity       Quantity.
	 * @param  array $options        Additional options.
	 * @return array Calculation result.
	 */
	private function calculate_price_override( $original_price, $override_price, $quantity, $options = array() ) {
		$override_price = max( 0, $this->format_price( $override_price ) );

		// Calculate discount amount
		$discount_per_unit = max( 0, $this->subtract( $original_price, $override_price ) );
		$discount_amount = $this->multiply( $discount_per_unit, $quantity );

		// Final prices
		$unit_price = $override_price;
		$final_price = $this->multiply( $override_price, $quantity );

		// Calculate discount percentage
		$discount_percent = $original_price > 0 
			? $this->multiply( $this->divide( $discount_per_unit, $original_price ), 100 )
			: 0;

		return array(
			'original_price'   => $original_price,
			'discount_amount'  => $discount_amount,
			'final_price'      => $final_price,
			'unit_price'       => $unit_price,
			'discount_percent' => $this->format_price( $discount_percent ),
			'quantity'         => $quantity,
		);
	}

	/**
	 * Calculate tiered/quantity-based discount.
	 *
	 * @since  1.0.0
	 * @param  float $original_price Original price.
	 * @param  int   $quantity       Quantity.
	 * @param  array $tiers          Tier configuration.
	 * @return array Calculation result.
	 */
	public function calculate_tiered( $original_price, $quantity, $tiers ) {
		$original_price = $this->format_price( $original_price );
		$quantity = max( 1, intval( $quantity ) );

		// Find applicable tier
		$applicable_tier = null;
		foreach ( $tiers as $tier ) {
			$min_qty = isset( $tier['min_quantity'] ) ? intval( $tier['min_quantity'] ) : 0;
			$max_qty = isset( $tier['max_quantity'] ) ? intval( $tier['max_quantity'] ) : PHP_INT_MAX;

			if ( $quantity >= $min_qty && $quantity <= $max_qty ) {
				$applicable_tier = $tier;
				break;
			}
		}

		// If no tier applies, return zero discount
		if ( ! $applicable_tier ) {
			return $this->get_zero_discount( $original_price, $quantity );
		}

		// Calculate using the tier's discount type and value
		$discount_type = $applicable_tier['discount_type'];
		$discount_value = $applicable_tier['discount_value'];

		$result = $this->calculate( $original_price, $discount_type, $discount_value, $quantity );
		$result['tier_applied'] = $applicable_tier;

		return $result;
	}

	/**
	 * Calculate bulk discount (volume-based).
	 *
	 * Similar to tiered but with progressive calculation.
	 *
	 * @since  1.0.0
	 * @param  float $original_price Original price.
	 * @param  int   $quantity       Quantity.
	 * @param  array $tiers          Tier configuration.
	 * @return array Calculation result.
	 */
	public function calculate_bulk( $original_price, $quantity, $tiers ) {
		$original_price = $this->format_price( $original_price );
		$quantity = max( 1, intval( $quantity ) );

		// Sort tiers by min_quantity
		usort( $tiers, function( $a, $b ) {
			$min_a = isset( $a['min_quantity'] ) ? intval( $a['min_quantity'] ) : 0;
			$min_b = isset( $b['min_quantity'] ) ? intval( $b['min_quantity'] ) : 0;
			return $min_a - $min_b;
		} );

		$total_discount = 0;
		$remaining_qty = $quantity;

		foreach ( $tiers as $tier ) {
			if ( $remaining_qty <= 0 ) {
				break;
			}

			$min_qty = isset( $tier['min_quantity'] ) ? intval( $tier['min_quantity'] ) : 0;
			$max_qty = isset( $tier['max_quantity'] ) ? intval( $tier['max_quantity'] ) : PHP_INT_MAX;

			if ( $quantity >= $min_qty ) {
				$tier_qty = min( $remaining_qty, $max_qty - $min_qty + 1 );
				
				// Calculate discount for this tier
				$tier_result = $this->calculate(
					$original_price,
					$tier['discount_type'],
					$tier['discount_value'],
					$tier_qty
				);

				$total_discount = $this->add( $total_discount, $tier_result['discount_amount'] );
				$remaining_qty -= $tier_qty;
			}
		}

		$total_original = $this->multiply( $original_price, $quantity );
		$final_price = $this->subtract( $total_original, $total_discount );
		$unit_price = $this->divide( $final_price, $quantity );

		$discount_percent = $total_original > 0 
			? $this->multiply( $this->divide( $total_discount, $total_original ), 100 )
			: 0;

		return array(
			'original_price'   => $original_price,
			'discount_amount'  => $total_discount,
			'final_price'      => max( 0, $final_price ),
			'unit_price'       => max( 0, $unit_price ),
			'discount_percent' => $this->format_price( $discount_percent ),
			'quantity'         => $quantity,
		);
	}

	/**
	 * Calculate cart-level discount.
	 *
	 * @since  1.0.0
	 * @param  array  $cart_items     Array of cart items with prices.
	 * @param  string $discount_type  Discount type.
	 * @param  float  $discount_value Discount value.
	 * @param  array  $options        Additional options.
	 * @return array Calculation result.
	 */
	public function calculate_cart_discount( $cart_items, $discount_type, $discount_value, $options = array() ) {
		// Calculate cart total
		$cart_total = 0;
		foreach ( $cart_items as $item ) {
			// Skip free items from BXGY to avoid calculating discount on free products
			if ( isset( $item['discount_tools_free_product'] ) && $item['discount_tools_free_product'] === true ) {
				continue;
			}
			
			// Prefer line_total (actual paid amount) over price*qty (handles free items)
			if ( isset( $item['line_total'] ) ) {
				$item_total = $this->format_price( $item['line_total'] );
			} else {
				$price = isset( $item['price'] ) ? $this->format_price( $item['price'] ) : 0;
				$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
				$item_total = $this->multiply( $price, $qty );
			}
			$cart_total = $this->add( $cart_total, $item_total );
		}

		// Calculate discount
		$result = $this->calculate( $cart_total, $discount_type, $discount_value, 1, $options );

		// Distribute discount across items proportionally
		$items_with_discount = array();
		foreach ( $cart_items as $item ) {
			// Prefer line_total (actual paid amount) over price*qty
			if ( isset( $item['line_total'] ) ) {
				$item_total = $this->format_price( $item['line_total'] );
			} else {
				$price = isset( $item['price'] ) ? $this->format_price( $item['price'] ) : 0;
				$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
				$item_total = $this->multiply( $price, $qty );
			}

			// Calculate proportional discount
			if ( $cart_total > 0 ) {
				$proportion = $this->divide( $item_total, $cart_total );
				$item_discount = $this->multiply( $result['discount_amount'], $proportion );
			} else {
				$item_discount = 0;
			}

			$items_with_discount[] = array_merge( $item, array(
				'discount_amount' => $item_discount,
				'final_price'     => $this->subtract( $item_total, $item_discount ),
			) );
		}

		$result['items'] = $items_with_discount;

		return $result;
	}

/**
 * Calculate Buy X Get Y discount (100% free).
 *
 * @since  1.0.5
 * @param  array  $cart_items   Cart items array with 'price' and 'quantity' keys.
 * @param  int    $buy_qty      Buy X quantity.
 * @param  int    $get_qty      Get Y quantity (always 100% free).
 * @param  bool   $repeating    Whether to apply promotion repeatedly.
 * @param  string $mode         bxgy_same or bxgy_any.
 * @return array {
 *     Calculation result.
 *
 *     @type float  $discount_amount Total discount amount.
 *     @type string $discount_type   Always 'bxgy'.
 *     @type array  $items           Items with individual discount amounts.
 * }
 */
public function calculate_bxgy_discount( $cart_items, $buy_qty, $get_qty, $repeating, $mode ) {
	// Validate inputs
	$buy_qty = max( 1, intval( $buy_qty ) );
	$get_qty = max( 1, intval( $get_qty ) );
	$repeating = ( $repeating === true || $repeating === 1 || $repeating === '1' );
	$set_size = $buy_qty + $get_qty;		$total_discount = 0;
		$items_with_discount = array();
		
		if ( empty( $cart_items ) || ! is_array( $cart_items ) ) {
			return array(
				'discount_amount' => 0,
				'discount_type'   => 'bxgy',
				'items'           => array(),
			);
		}
		
		if ( $mode === 'bxgy_same' ) {
			// Each product qualifies independently
			foreach ( $cart_items as $item ) {
				$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
				$price = isset( $item['price'] ) ? $this->format_price( $item['price'] ) : 0;
				$item_discount = 0;
				
				if ( $repeating ) {
					// Repeating: Calculate complete sets and apply to each
					$complete_sets = floor( $qty / $set_size );
					if ( $complete_sets > 0 ) {
						$discounted_qty = $complete_sets * $get_qty;
						// 100% discount (free)
						$item_discount = $this->multiply( $price, $discounted_qty );
						$total_discount = $this->add( $total_discount, $item_discount );
					}
				} else {
					// One-time: Apply only once if qualified
					if ( $qty >= $set_size ) {
						// Give Y items free (100% off)
						$item_discount = $this->multiply( $price, $get_qty );
						$total_discount = $this->add( $total_discount, $item_discount );
					}
				}
				
				$items_with_discount[] = array_merge( $item, array(
					'discount_amount' => $item_discount,
				) );
			}
		} else {
			// bxgy_any: Combine all qualifying items
			$total_qty = 0;
			foreach ( $cart_items as $item ) {
				$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
				$total_qty += $qty;
			}
			
			if ( $repeating ) {
				// Repeating: Calculate complete sets
				$complete_sets = floor( $total_qty / $set_size );
				$discounted_qty = $complete_sets * $get_qty;
			} else {
				// One-time: Apply only once if qualified
				$discounted_qty = ( $total_qty >= $set_size ) ? $get_qty : 0;
			}
			
			if ( $discounted_qty > 0 ) {
				// Apply 100% discount to cheapest items
				$sorted_items = $this->sort_items_by_price( $cart_items );
				$remaining_discount_qty = $discounted_qty;
				
				foreach ( $sorted_items as $item ) {
					if ( $remaining_discount_qty <= 0 ) {
						$items_with_discount[] = array_merge( $item, array(
							'discount_amount' => 0,
						) );
						continue;
					}
					
					$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
					$price = isset( $item['price'] ) ? $this->format_price( $item['price'] ) : 0;
					$apply_to_qty = min( $remaining_discount_qty, $qty );
					
					// 100% discount (free)
					$item_discount = $this->multiply( $price, $apply_to_qty );
					$total_discount = $this->add( $total_discount, $item_discount );
					$remaining_discount_qty -= $apply_to_qty;
					
					$items_with_discount[] = array_merge( $item, array(
						'discount_amount' => $item_discount,
					) );
				}
			} else {
				// No complete sets, no discounts
				foreach ( $cart_items as $item ) {
					$items_with_discount[] = array_merge( $item, array(
						'discount_amount' => 0,
					) );
				}
			}
		}
		
		return array(
			'discount_amount' => $total_discount,
			'discount_type'   => 'bxgy',
			'items'           => $items_with_discount,
		);
	}

	/**
	 * Calculate bundle (set) discount.
	 * 
	 * Example: Regular $115 per item, Bundle: 2 for $149
	 * - 2 items = $149 (save $81)
	 * - 3 items = $149 + $115 = $264
	 * - 4 items = $149 + $149 = $298 (if repeating)
	 *
	 * @since  1.0.6
	 * @param  array $cart_items    Cart items array.
	 * @param  int   $bundle_qty    Items per bundle.
	 * @param  float $bundle_price  Price per bundle.
	 * @param  bool  $repeating     Apply to multiple sets.
	 * @return array Discount result.
	 */
	public function calculate_bundle_discount( $cart_items, $bundle_qty, $bundle_price, $repeating ) {
		$total_discount = 0;
		$items_with_discount = array();
		
		// Calculate total quantity across all items
		$total_qty = 0;
		foreach ( $cart_items as $item ) {
			$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
			$total_qty += $qty;
		}
		
		// Calculate how many complete bundles
		$complete_bundles = floor( $total_qty / $bundle_qty );
		$remaining_items = $total_qty % $bundle_qty;
		
		if ( ! $repeating && $complete_bundles > 0 ) {
			// Non-repeating: only first bundle gets discount
			$complete_bundles = 1;
			$remaining_items = $total_qty - $bundle_qty;
		}
		
		// Calculate total regular price
		$total_regular_price = 0;
		foreach ( $cart_items as $item ) {
			$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
			$price = isset( $item['price'] ) ? $this->format_price( $item['price'] ) : 0;
			$total_regular_price = $this->add( $total_regular_price, $this->multiply( $price, $qty ) );
		}
		
		// Calculate bundle savings
		// Example: 2 items at $115 = $230, bundle price $149 -> discount = $81
		if ( $complete_bundles > 0 ) {
			$bundled_items = $complete_bundles * $bundle_qty;
			
			// Calculate what customer would pay at regular price for bundled items
			$bundled_regular_price = 0;
			$items_counted = 0;
			foreach ( $cart_items as $item ) {
				$qty = isset( $item['quantity'] ) ? max( 1, intval( $item['quantity'] ) ) : 1;
				$price = isset( $item['price'] ) ? $this->format_price( $item['price'] ) : 0;
				
				if ( $items_counted + $qty <= $bundled_items ) {
					// All items from this line are in bundles
					$bundled_regular_price = $this->add( $bundled_regular_price, $this->multiply( $price, $qty ) );
					$items_counted += $qty;
				} elseif ( $items_counted < $bundled_items ) {
					// Partial items from this line are in bundles
					$qty_in_bundle = $bundled_items - $items_counted;
					$bundled_regular_price = $this->add( $bundled_regular_price, $this->multiply( $price, $qty_in_bundle ) );
					$items_counted = $bundled_items;
					break;
				} else {
					break;
				}
			}
			
			// Bundle discount = regular price - bundle price
			$bundle_price_formatted = $this->format_price( $bundle_price );
			$total_bundle_price = $this->multiply( $bundle_price_formatted, $complete_bundles );
			$total_discount = $this->subtract( $bundled_regular_price, $total_bundle_price );
		}
		
		// Add discount info to items
		foreach ( $cart_items as $item ) {
			$items_with_discount[] = array_merge( $item, array(
				'discount_amount' => 0, // Discount is applied as cart fee, not per-item
				'bundle_info' => array(
					'complete_bundles' => $complete_bundles,
					'bundle_qty' => $bundle_qty,
					'bundle_price' => $bundle_price,
				),
			) );
		}
		
		return array(
			'discount_amount' => $total_discount,
			'discount_type'   => 'bundle',
			'items'           => $items_with_discount,
			'bundle_info' => array(
				'complete_bundles' => $complete_bundles,
				'remaining_items' => $remaining_items,
				'bundle_qty' => $bundle_qty,
				'bundle_price' => $bundle_price,
			),
		);
	}

	/**
	 * Sort cart items by price (ascending).
	 *
	 * @since  1.0.5
	 * @param  array $items Cart items array.
	 * @return array Sorted items.
	 */
	private function sort_items_by_price( $items ) {
		usort( $items, function( $a, $b ) {
			$price_a = isset( $a['price'] ) ? $this->format_price( $a['price'] ) : 0;
			$price_b = isset( $b['price'] ) ? $this->format_price( $b['price'] ) : 0;
			return $this->compare( $price_a, $price_b );
		} );
		
		return $items;
	}

	/**
	 * Apply maximum discount constraint.
	 *
	 * @since  1.0.0
	 * @param  array $result      Calculation result.
	 * @param  float $max_discount Maximum allowed discount.
	 * @return array Modified result.
	 */
	public function apply_max_discount( $result, $max_discount ) {
		$max_discount = $this->format_price( $max_discount );

		if ( $this->compare( $result['discount_amount'], $max_discount ) > 0 ) {
			$result['discount_amount'] = $max_discount;
			$result['final_price'] = $this->subtract(
				$this->multiply( $result['original_price'], $result['quantity'] ),
				$max_discount
			);
			$result['unit_price'] = $this->divide( $result['final_price'], $result['quantity'] );

			// Recalculate percentage
			$total_original = $this->multiply( $result['original_price'], $result['quantity'] );
			$result['discount_percent'] = $total_original > 0 
				? $this->format_price( $this->multiply( $this->divide( $max_discount, $total_original ), 100 ) )
				: 0;
		}

		return $result;
	}

	/**
	 * Apply minimum discount constraint.
	 *
	 * @since  1.0.0
	 * @param  array $result       Calculation result.
	 * @param  float $min_discount Minimum required discount.
	 * @return array Modified result.
	 */
	public function apply_min_discount( $result, $min_discount ) {
		$min_discount = $this->format_price( $min_discount );

		if ( $this->compare( $result['discount_amount'], $min_discount ) < 0 ) {
			$result['discount_amount'] = $min_discount;
			$result['final_price'] = $this->subtract(
				$this->multiply( $result['original_price'], $result['quantity'] ),
				$min_discount
			);
			$result['unit_price'] = $this->divide( $result['final_price'], $result['quantity'] );

			// Recalculate percentage
			$total_original = $this->multiply( $result['original_price'], $result['quantity'] );
			$result['discount_percent'] = $total_original > 0 
				? $this->format_price( $this->multiply( $this->divide( $min_discount, $total_original ), 100 ) )
				: 0;
		}

		return $result;
	}

	/**
	 * Get zero discount result.
	 *
	 * @since  1.0.0
	 * @param  float $original_price Original price.
	 * @param  int   $quantity       Quantity.
	 * @return array Zero discount result.
	 */
	private function get_zero_discount( $original_price, $quantity ) {
		$final_price = $this->multiply( $original_price, $quantity );

		return array(
			'original_price'   => $original_price,
			'discount_amount'  => 0,
			'final_price'      => $final_price,
			'unit_price'       => $original_price,
			'discount_percent' => 0,
			'quantity'         => $quantity,
		);
	}

	/**
	 * Format price with proper precision.
	 *
	 * @since  1.0.0
	 * @param  mixed $price Price value.
	 * @return float Formatted price.
	 */
	private function format_price( $price ) {
		// Use WooCommerce function if available
		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $price, 2 );
		}

		// Fallback to round
		return round( floatval( $price ), 2 );
	}

	/**
	 * Add two numbers with precision.
	 *
	 * @since  1.0.0
	 * @param  float $a First number.
	 * @param  float $b Second number.
	 * @return float Result.
	 */
	private function add( $a, $b ) {
		if ( $this->use_bcmath() ) {
			return bcadd( (string) $a, (string) $b, 2 );
		}
		return round( $a + $b, 2 );
	}

	/**
	 * Subtract two numbers with precision.
	 *
	 * @since  1.0.0
	 * @param  float $a First number.
	 * @param  float $b Second number.
	 * @return float Result.
	 */
	private function subtract( $a, $b ) {
		if ( $this->use_bcmath() ) {
			return bcsub( (string) $a, (string) $b, 2 );
		}
		return round( $a - $b, 2 );
	}

	/**
	 * Multiply two numbers with precision.
	 *
	 * @since  1.0.0
	 * @param  float $a First number.
	 * @param  float $b Second number.
	 * @return float Result.
	 */
	private function multiply( $a, $b ) {
		if ( $this->use_bcmath() ) {
			return bcmul( (string) $a, (string) $b, 2 );
		}
		return round( $a * $b, 2 );
	}

	/**
	 * Divide two numbers with precision.
	 *
	 * @since  1.0.0
	 * @param  float $a First number.
	 * @param  float $b Second number.
	 * @return float Result.
	 */
	private function divide( $a, $b ) {
		if ( $b == 0 ) {
			return 0;
		}

		if ( $this->use_bcmath() ) {
			return bcdiv( (string) $a, (string) $b, 2 );
		}
		return round( $a / $b, 2 );
	}

	/**
	 * Compare two numbers.
	 *
	 * @since  1.0.0
	 * @param  float $a First number.
	 * @param  float $b Second number.
	 * @return int   -1 if a < b, 0 if a == b, 1 if a > b.
	 */
	private function compare( $a, $b ) {
		if ( $this->use_bcmath() ) {
			return bccomp( (string) $a, (string) $b, 2 );
		}

		$diff = round( $a - $b, 2 );
		if ( $diff < 0 ) {
			return -1;
		} elseif ( $diff > 0 ) {
			return 1;
		}
		return 0;
	}

	/**
	 * Check if bcmath extension is available and should be used.
	 *
	 * @since  1.0.0
	 * @return bool True if bcmath should be used.
	 */
	private function use_bcmath() {
		return function_exists( 'bcadd' ) && function_exists( 'bcmul' );
	}
}
