<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Settings Page Template
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin/partials
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings
$dt_settings = get_option( 'discount_tools_settings', array() );
$dt_defaults = array(
	'enabled' => true,
	'default_priority' => 10,
	'cache_duration' => 3600,
	'show_discount_table' => true,
	'show_cart_discount' => true,
	'show_checkout_savings' => true,
	'enable_logging' => false,
	'debug_mode' => false,
);
$dt_settings = wp_parse_args( $dt_settings, $dt_defaults );

// Handle messages
settings_errors( 'discount_tools' );

?>

<div class="wrap discount-tools-admin">
	<h1><?php echo esc_html__( 'Discount Tools Settings', 'discount-tools' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'discount_tools_save_settings', 'discount_tools_settings_nonce' ); ?>

		<!-- General Settings -->
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php echo esc_html__( 'General Settings', 'discount-tools' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php echo esc_html__( 'Enable Plugin', 'discount-tools' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="discount_tools_settings[enabled]" 
									   value="1" 
									   <?php checked( $dt_settings['enabled'], true ); ?>>
								<?php echo esc_html__( 'Enable discount rules functionality', 'discount-tools' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="default_priority"><?php echo esc_html__( 'Default Priority', 'discount-tools' ); ?></label>
						</th>
						<td>
							<input type="number" 
								   id="default_priority" 
								   name="discount_tools_settings[default_priority]" 
								   value="<?php echo esc_attr( $dt_settings['default_priority'] ); ?>" 
								   min="1" 
								   max="100">
							<p class="description"><?php echo esc_html__( 'Default priority for new rules.', 'discount-tools' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cache_duration"><?php echo esc_html__( 'Cache Duration', 'discount-tools' ); ?></label>
						</th>
						<td>
							<input type="number" 
								   id="cache_duration" 
								   name="discount_tools_settings[cache_duration]" 
								   value="<?php echo esc_attr( $dt_settings['cache_duration'] ); ?>" 
								   min="0">
							<p class="description"><?php echo esc_html__( 'Cache duration in seconds. Set to 0 to disable caching.', 'discount-tools' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- Display Settings -->
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php echo esc_html__( 'Display Settings', 'discount-tools' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php echo esc_html__( 'Product Page', 'discount-tools' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="discount_tools_settings[show_discount_table]" 
									   value="1" 
									   <?php checked( $dt_settings['show_discount_table'], true ); ?>>
								<?php echo esc_html__( 'Show discount table on product pages', 'discount-tools' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php echo esc_html__( 'Cart Page', 'discount-tools' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="discount_tools_settings[show_cart_discount]" 
									   value="1" 
									   <?php checked( $dt_settings['show_cart_discount'], true ); ?>>
								<?php echo esc_html__( 'Show discount details in cart', 'discount-tools' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php echo esc_html__( 'Checkout Page', 'discount-tools' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="discount_tools_settings[show_checkout_savings]" 
									   value="1" 
									   <?php checked( $dt_settings['show_checkout_savings'], true ); ?>>
								<?php echo esc_html__( 'Show total savings on checkout', 'discount-tools' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- Advanced Settings -->
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php echo esc_html__( 'Advanced Settings', 'discount-tools' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php echo esc_html__( 'Logging', 'discount-tools' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="discount_tools_settings[enable_logging]" 
									   value="1" 
									   <?php checked( $dt_settings['enable_logging'], true ); ?>>
								<?php echo esc_html__( 'Enable activity logging', 'discount-tools' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Log discount rule applications for debugging.', 'discount-tools' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php echo esc_html__( 'Debug Mode', 'discount-tools' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="discount_tools_settings[debug_mode]" 
									   value="1" 
									   <?php checked( $dt_settings['debug_mode'], true ); ?>>
								<?php echo esc_html__( 'Enable debug mode', 'discount-tools' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Show detailed debug information. Only for development.', 'discount-tools' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<p class="submit">
			<input type="submit" 
				   name="discount_tools_save_settings" 
				   class="button button-primary" 
				   value="<?php echo esc_attr__( 'Save Settings', 'discount-tools' ); ?>">
		</p>
	</form>
</div>

<style>
.discount-tools-admin .postbox {
	margin-top: 20px;
}
</style>
