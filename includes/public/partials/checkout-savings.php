<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Checkout Savings Display Template
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get cart and calculate savings
$dt_cart = WC()->cart;
if ( ! $dt_cart || $dt_cart->is_empty() ) {
	return;
}

// Calculate total savings
$dt_total_savings = 0;
$dt_discount_count = 0;

foreach ( $dt_cart->get_fees() as $dt_fee ) {
	if ( $dt_fee->amount < 0 ) {
		$dt_total_savings += abs( $dt_fee->amount );
		$dt_discount_count++;
	}
}

// Don't display if no savings
if ( $dt_total_savings <= 0 ) {
	return;
}

// Calculate savings percentage
$dt_cart_subtotal = $dt_cart->get_subtotal();
$dt_savings_percent = $dt_cart_subtotal > 0 ? ( $dt_total_savings / $dt_cart_subtotal ) * 100 : 0;
?>

<div class="dt-checkout-savings">
	<div class="dt-savings-icon">
		<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<circle cx="12" cy="12" r="10" stroke="#10b981" stroke-width="2"/>
			<path d="M8 12L11 15L16 9" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
	</div>

	<div class="dt-savings-content">
		<h3 class="dt-savings-title">
			<?php esc_html_e( 'Congratulations! You\'re Saving Money!', 'discount-tools' ); ?>
		</h3>

		<div class="dt-savings-details">
			<div class="dt-savings-amount">
				<span class="dt-savings-label"><?php esc_html_e( 'Total Savings:', 'discount-tools' ); ?></span>
				<span class="dt-savings-value"><?php echo wp_kses_post( wc_price( $dt_total_savings ) ); ?></span>
			</div>

			<div class="dt-savings-percentage">
				<span class="dt-percentage-badge">
					<?php echo esc_html( round( $dt_savings_percent, 1 ) ); ?>% <?php esc_html_e( 'OFF', 'discount-tools' ); ?>
				</span>
			</div>
		</div>

		<?php if ( $dt_discount_count > 0 ) : ?>
			<p class="dt-savings-note">
				<?php
				printf(
					/* translators: %d: Number of discounts applied */
					esc_html( _n(
						'%d discount has been applied to your order.',
						'%d discounts have been applied to your order.',
						$dt_discount_count,
						'discount-tools'
					) ),
					esc_html( $dt_discount_count )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
</div>

<style>
.dt-checkout-savings {
	background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%);
	border: 2px solid #10b981;
	border-radius: 12px;
	padding: 25px;
	margin: 20px 0;
	display: flex;
	align-items: center;
	gap: 20px;
	box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);
	animation: dt-checkout-slide-in 0.5s ease-out;
}

@keyframes dt-checkout-slide-in {
	from {
		opacity: 0;
		transform: translateY(-20px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

.dt-savings-icon {
	flex-shrink: 0;
}

.dt-savings-icon svg {
	display: block;
	filter: drop-shadow(0 2px 4px rgba(16, 185, 129, 0.3));
}

.dt-savings-content {
	flex-grow: 1;
}

.dt-savings-title {
	margin: 0 0 15px 0;
	font-size: 20px;
	font-weight: 700;
	color: #065f46;
}

.dt-savings-details {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 15px;
	margin-bottom: 10px;
}

.dt-savings-amount {
	display: flex;
	flex-direction: column;
	gap: 5px;
}

.dt-savings-label {
	font-size: 14px;
	color: #047857;
	font-weight: 500;
}

.dt-savings-value {
	font-size: 28px;
	font-weight: 800;
	color: #065f46;
	line-height: 1;
}

.dt-savings-percentage {
	display: flex;
	align-items: center;
}

.dt-percentage-badge {
	background: #10b981;
	color: #fff;
	padding: 8px 16px;
	border-radius: 20px;
	font-size: 16px;
	font-weight: 700;
	white-space: nowrap;
	box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
}

.dt-savings-note {
	margin: 0;
	font-size: 14px;
	color: #047857;
	font-style: italic;
}

/* Mobile responsive */
@media (max-width: 768px) {
	.dt-checkout-savings {
		flex-direction: column;
		text-align: center;
		padding: 20px;
	}

	.dt-savings-title {
		font-size: 18px;
	}

	.dt-savings-details {
		flex-direction: column;
		gap: 10px;
	}

	.dt-savings-amount {
		align-items: center;
	}

	.dt-savings-value {
		font-size: 24px;
	}

	.dt-percentage-badge {
		font-size: 14px;
		padding: 6px 12px;
	}
}

@media (max-width: 480px) {
	.dt-savings-icon svg {
		width: 36px;
		height: 36px;
	}

	.dt-savings-title {
		font-size: 16px;
	}

	.dt-savings-value {
		font-size: 20px;
	}

	.dt-savings-note {
		font-size: 13px;
	}
}

/* Print styles */
@media print {
	.dt-checkout-savings {
		border: 2px solid #000;
		box-shadow: none;
		animation: none;
	}

	.dt-savings-icon svg {
		filter: none;
	}

	.dt-percentage-badge {
		box-shadow: none;
	}
}
</style>
