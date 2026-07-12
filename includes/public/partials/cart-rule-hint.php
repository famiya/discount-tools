<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
/**
 * Cart Rule Hint Template
 * 
 * Displays promotional hints for cart-type discount rules on product pages.
 * Since cart rules depend on total cart value and other items, we can't calculate
 * exact discounts on individual product pages. Instead, we show qualification hints.
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $cart_rules is passed from display_discount_table()
if ( empty( $cart_rules ) ) {
	return;
}
?>

<div class="dt-cart-rule-hints">
	<?php foreach ( $cart_rules as $dt_rule ) : ?>
		<?php
		$dt_rule_name = $dt_rule->get_name();
		$dt_discount_type = $dt_rule->get_discount_type();
		$dt_discount_value = $dt_rule->get_discount_value();
		
		// Get conditions to build the hint message
		$dt_conditions = $dt_rule->get_conditions();
		$dt_min_cart_total = null;
		$dt_excluded_categories = array();
		$dt_excluded_brands = array();
		$dt_coupon_codes = array();
		
		foreach ( $dt_conditions as $dt_condition ) {
			$type = $dt_condition->get_type();
			$dt_operator = $dt_condition->get_operator();
			$dt_value = $dt_condition->get_value();
			
			if ( $type === 'cart_total' && $dt_operator === '>=' ) {
				$dt_min_cart_total = $dt_value;
			} elseif ( $type === 'product_category' && $dt_operator === 'not_in' ) {
				$dt_excluded_categories = $dt_value;
			} elseif ( $type === 'brand' && $dt_operator === 'not_in' ) {
				$dt_excluded_brands = $dt_value;
			} elseif ( $type === 'coupon_activation' ) {
			// Extract coupon codes for display
			// First check if value is a JSON string
			if ( is_string( $dt_value ) && ( strpos( $dt_value, '{' ) === 0 || strpos( $dt_value, '[' ) === 0 ) ) {
				$dt_decoded = json_decode( $dt_value, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$dt_value = $dt_decoded;
				}
			}
			
			if ( is_array( $dt_value ) && isset( $dt_value['coupon_codes'] ) ) {
				$dt_coupon_codes = $dt_value['coupon_codes'];
				
				// Handle nested JSON encoding (if coupon_codes array contains JSON strings)
				if ( ! empty( $dt_coupon_codes ) && is_array( $dt_coupon_codes ) ) {
					$dt_coupon_codes = array_map( function( $dt_code_item ) {
						if ( is_string( $dt_code_item ) && ( strpos( $dt_code_item, '{' ) === 0 || strpos( $dt_code_item, '[' ) === 0 ) ) {
							$dt_decoded_code = json_decode( $dt_code_item, true );
							if ( json_last_error() === JSON_ERROR_NONE && isset( $dt_decoded_code['coupon_codes'] ) ) {
								// Extract actual coupon codes from nested structure
								return $dt_decoded_code['coupon_codes'];
							}
						}
						return $dt_code_item;
					}, $dt_coupon_codes );
					
					// Flatten if we got nested arrays
					$dt_flattened = array();
					foreach ( $dt_coupon_codes as $dt_code ) {
						if ( is_array( $dt_code ) ) {
							$dt_flattened = array_merge( $dt_flattened, $dt_code );
						} else {
							$dt_flattened[] = $dt_code;
						}
					}
					$dt_coupon_codes = $dt_flattened;
				}
			} elseif ( is_array( $dt_value ) ) {
				$dt_coupon_codes = $dt_value;
			} else {
				$dt_coupon_codes = array( $dt_value );
			}
		}
		}
		
		// Format discount text
		$dt_discount_text = '';
		if ( $dt_discount_type === 'percentage' && $dt_discount_value > 0 ) {
			/* translators: %s: discount percentage value */
			$dt_discount_text = sprintf( __( '%s%% OFF', 'discount-tools' ), $dt_discount_value );
		} elseif ( $dt_discount_type === 'fixed_cart' && $dt_discount_value > 0 ) {
			/* translators: %s: formatted discount price */
			$dt_discount_text = sprintf( __( '%s OFF', 'discount-tools' ), wc_price( $dt_discount_value ) );
		} elseif ( $dt_discount_type === 'fixed_product' && $dt_discount_value > 0 ) {
			/* translators: %s: formatted discount price */
			$dt_discount_text = sprintf( __( '%s OFF per item', 'discount-tools' ), wc_price( $dt_discount_value ) );
		} elseif ( $dt_discount_type === 'bundle' ) {
			// For bundle rules, show bundle_quantity and bundle_price
			$dt_bundle_qty = $dt_rule->get_meta_value( 'bundle_quantity', 2 );
			$dt_bundle_price = $dt_rule->get_meta_value( 'bundle_price', 0 );
			if ( $dt_bundle_qty && $dt_bundle_price ) {
				/* translators: %1$d: bundle quantity, %2$s: formatted bundle price */
				$dt_discount_text = sprintf( __( '%1$d for %2$s', 'discount-tools' ), $dt_bundle_qty, wc_price( $dt_bundle_price ) );
			}
		} elseif ( in_array( $dt_discount_type, array( 'bxgy_same', 'bxgy_any' ) ) ) {
			// For BXGY rules, show buy/get quantities and discount type
			$dt_buy_qty = $dt_rule->get_meta_value( 'bxgy_buy_quantity', 1 );
			$dt_get_qty = $dt_rule->get_meta_value( 'bxgy_get_quantity', 1 );
			if ( $dt_buy_qty && $dt_get_qty ) {
				/* translators: %1$d: buy quantity, %2$d: get quantity */
				$dt_discount_text = sprintf( __( 'Buy %1$d Get %2$d Free', 'discount-tools' ), $dt_buy_qty, $dt_get_qty );
			}
		}
		
		// Get custom badge text if set
		$dt_badge_text = $dt_rule->get_meta_value( 'display_badge_text', '' );
		
		// Use discount_text as fallback only if badge_text is not set
		if ( empty( $dt_badge_text ) ) {
			$dt_badge_text = $dt_discount_text;
		}
		
		// Get custom message if set
		$dt_custom_message = $dt_rule->get_meta_value( 'display_savings_message', '' );
		
		// Always display applied rules (removed the skip logic)
		$dt_tag_icon_url = DISCOUNT_TOOLS_PLUGIN_URL . 'assets/images/discount-tag.svg';
		?>
		
		<div class="dt-cart-rule-hint">
			<div class="dt-hint-icon">
				<img src="<?php echo esc_url( $dt_tag_icon_url ); ?>" alt="<?php esc_attr_e( 'Discount', 'discount-tools' ); ?>" width="80" height="80">
			</div>
			<div class="dt-hint-content">
				<div class="dt-hint-badge">
					<span class="dt-badge-text">
						<?php 
						// Display rule name followed by "applied your shopping cart"
						echo esc_html( $dt_rule->get_name() ) . ' ' . esc_html__( 'applied your shopping cart', 'discount-tools' ); 
						?>
					</span>
					<?php
					// Show remove link when the coupon is currently applied to the cart
					if ( ! empty( $dt_coupon_codes ) ) :
						$dt_applied_code = '';
						$dt_applied_coupons = WC()->cart->get_applied_coupons();
						foreach ( $dt_coupon_codes as $dt_code_item ) {
							if ( in_array( strtolower( $dt_code_item ), $dt_applied_coupons, true ) ) {
								$dt_applied_code = $dt_code_item;
								break;
							}
						}
						if ( $dt_applied_code ) :
							$dt_remove_url = add_query_arg( 'remove_coupon', rawurlencode( $dt_applied_code ), wc_get_cart_url() );
							?>
							<a href="<?php echo esc_url( $dt_remove_url ); ?>"
							   class="dt-remove-applied-coupon"
							   style="font-size:0.9em;color:#dc3545;text-decoration:none;margin-left:10px;white-space:nowrap;">
								<?php esc_html_e( '（移除）', 'discount-tools' ); ?>
							</a>
							<?php
						endif;
					endif;
					?>
				</div>
				<div class="dt-hint-message">
					<?php if ( ! empty( $dt_coupon_codes ) ) : ?>
						<!-- Coupon code message removed as per user request -->
					<?php elseif ( $dt_min_cart_total ) : ?>
						<p class="dt-hint-condition">
							<?php
							printf(
								/* translators: 1: Discount text, 2: Minimum cart total */
								esc_html__( 'Get %1$s when your order reaches %2$s', 'discount-tools' ),
								'<strong>' . esc_html( $dt_discount_text ) . '</strong>',
								'<strong>' . wp_kses_post( wc_price( $dt_min_cart_total ) ) . '</strong>'
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		
	<?php endforeach; ?>
</div>

<style>
.dt-cart-rule-hints {
	margin: 20px 0;
}

.dt-cart-rule-hint {
	display: flex;
	align-items: flex-start;
	gap: 15px;
	padding: 8px;
	background: <?php echo esc_attr( \Discount_Tools\Admin\Settings::get( 'badge_background_color', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' ) ); ?>;
	border-radius: 8px;
	color: white;
	margin-bottom: 15px;
	box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dt-hint-icon {
	font-size: 80px;
	line-height: 1;
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
}

.dt-hint-icon img {
	width: 30px;
	height: 30px;
}

.dt-hint-content {
	flex: 1;
}

.dt-badge-text {
	display: inline-block;
	padding: 8px 8px;
	border-radius: 20px;
	font-weight: bold;
	font-size: 16px;
	letter-spacing: 0.5px;
}

.dt-hint-message {
	font-size: 14px;
	line-height: 1.6;
}

.dt-hint-condition {
	margin: 0 0 5px 0;
}

.dt-hint-condition strong {
	color: #ffd700;
}

.dt-hint-exclusions {
	margin: 5px 0 0 0;
	opacity: 0.8;
}

.dt-hint-exclusions small {
	font-size: 12px;
}

/* Responsive */
@media (max-width: 768px) {
	.dt-cart-rule-hint {
		text-align: left;
	}
    
	.dt-hint-icon {
		font-size: 64px;
	}

	.dt-hint-icon img {
		width: 64px;
		height: 64px;
	}
}
</style>

<?php
/**
 * Allow third-party customization
 *
 * @param array $cart_rules Cart-type rules
 */
do_action( 'dt_after_cart_rule_hints', $cart_rules );
?>
