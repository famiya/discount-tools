<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Rule Editor - Display Tab
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin/partials
 *
 * @var Discount_Tools_Rule         $rule   The rule object
 * @var Discount_Tools_Rule_Editor  $editor The editor instance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load existing display settings from meta
$dt_meta_defaults = array(
	'show_on_product_page'     => '1',
	'show_in_cart'             => '1',
	'show_savings_message'     => '1',
	'badge_text'               => '',
	'savings_message'          => '',
);

$dt_display_settings = array();
foreach ( $dt_meta_defaults as $dt_key => $dt_default ) {
	$dt_display_settings[ $dt_key ] = $rule->get_meta_value( 'display_' . $dt_key, $dt_default );
}

?>

<div class="dt-tab-panel dt-tab-display">
	
	<!-- Product Page Display -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Product Page Display', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Configure whether discount information appears on product pages. Display position and style are controlled globally in Settings.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="show_on_product_page">
							<?php echo esc_html__( 'Show on Product Page', 'discount-tools' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" 
								   id="show_on_product_page" 
								   name="display[show_on_product_page]" 
								   value="1" <?php checked( $dt_display_settings['show_on_product_page'], '1' ); ?>>
							<?php echo esc_html__( 'Display discount information on product pages', 'discount-tools' ); ?>
						</label>
						<p class="description">
							<?php 
							$dt_settings_url = admin_url( 'admin.php?page=discount-tools&tab=settings' );
							printf( 
								/* translators: %s: Settings page URL */
								esc_html__( 'Display position and style are managed in %s', 'discount-tools' ),
								'<a href="' . esc_url( $dt_settings_url ) . '">' . esc_html__( 'Settings', 'discount-tools' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Cart & Checkout Display -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Cart & Checkout Display', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Control discount visibility in cart and checkout.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="show_in_cart">
							<?php echo esc_html__( 'Show in Cart', 'discount-tools' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" 
								   id="show_in_cart" 
								   name="display[show_in_cart]" 
								   value="1" <?php checked( $dt_display_settings['show_in_cart'], '1' ); ?>>
							<?php echo esc_html__( 'Display discount details in shopping cart', 'discount-tools' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="show_savings_message">
							<?php echo esc_html__( 'Show Savings Message', 'discount-tools' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" 
								   id="show_savings_message" 
								   name="display[show_savings_message]" 
								   value="1" <?php checked( $dt_display_settings['show_savings_message'], '1' ); ?>>
							<?php echo esc_html__( 'Show total savings message in cart', 'discount-tools' ); ?>
						</label>
						<p class="description">
							<?php echo esc_html__( 'Example: "You saved $10.00 with this discount!"', 'discount-tools' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Custom Messages -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Custom Messages', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Customize discount messages shown to customers.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="badge_text">
							<?php echo esc_html__( 'Badge Text', 'discount-tools' ); ?>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="badge_text" 
							   name="display[badge_text]" 
							   class="regular-text"
							   placeholder="<?php echo esc_attr__( 'SALE!', 'discount-tools' ); ?>"
							   value="<?php echo esc_attr( $dt_display_settings['badge_text'] ); ?>">
						<p class="description">
							<?php echo esc_html__( 'Text to show on discount badge. Leave empty for default.', 'discount-tools' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="savings_message">
							<?php echo esc_html__( 'Savings Message Template', 'discount-tools' ); ?>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="savings_message" 
							   name="display[savings_message]" 
							   class="large-text"
							   placeholder="<?php echo esc_attr__( 'You saved {amount}!', 'discount-tools' ); ?>"
							   value="<?php echo esc_attr( $dt_display_settings['savings_message'] ); ?>">
						<p class="description">
							<?php echo esc_html__( 'Use {amount} as placeholder for savings amount. Leave empty for default.', 'discount-tools' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Implementation Notice -->
	<div class="dt-card">
		<div class="dt-card-body">
			<div class="notice notice-info inline" style="margin: 0;">
				<p>
					<strong><?php echo esc_html__( 'Note:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Display settings are saved but frontend implementation will be completed in Phase 6 (Tasks 22-24). This includes discount tables, cart messages, and styling options.', 'discount-tools' ); ?>
				</p>
			</div>
		</div>
	</div>

</div>


