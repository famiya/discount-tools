<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Rule Edit Page Template
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

$dt_rule = isset( $rule ) ? $rule : new Discount_Tools_Rule();
$dt_rule_id = $dt_rule->get_id();
$dt_is_new = $dt_rule_id === 0;

// Set defaults for new rules
if ( $dt_is_new ) {
	$dt_rule->set_priority( 10 );
	$dt_rule->set_status( 'active' );
	$dt_rule->set_type( 'product' );
	$dt_rule->set_discount_type( 'percentage' );
	$dt_rule->set_stackable( true );
}

// Handle messages
settings_errors( 'discount_tools' );

?>

<div class="wrap discount-tools-admin">
	<h1 class="wp-heading-inline">
		<?php echo $dt_is_new ? esc_html__( 'Add New Discount Rule', 'discount-tools' ) : esc_html__( 'Edit Discount Rule', 'discount-tools' ); ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=discount-tools' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Back to Rules', 'discount-tools' ); ?>
	</a>

	<hr class="wp-header-end">

	<form method="post" action="">
		<?php wp_nonce_field( 'discount_tools_save_rule', 'discount_tools_rule_nonce' ); ?>

		<div class="discount-tools-rule-edit">
			
			<!-- Basic Information -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php echo esc_html__( 'Basic Information', 'discount-tools' ); ?></h2>
				</div>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="rule_name"><?php echo esc_html__( 'Rule Name', 'discount-tools' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" 
									   id="rule_name" 
									   name="name" 
									   value="<?php echo esc_attr( $dt_rule->get_name() ); ?>" 
									   class="regular-text" 
									   required>
								<p class="description"><?php echo esc_html__( 'Enter a descriptive name for this discount rule.', 'discount-tools' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="rule_type"><?php echo esc_html__( 'Rule Type', 'discount-tools' ); ?></label>
							</th>
							<td>
								<select id="rule_type" name="type">
									<option value="product" <?php selected( $dt_rule->get_type(), 'product' ); ?>><?php echo esc_html__( 'Product Discount', 'discount-tools' ); ?></option>
									<option value="cart" <?php selected( $dt_rule->get_type(), 'cart' ); ?>><?php echo esc_html__( 'Cart Discount', 'discount-tools' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'Select the type of discount rule.', 'discount-tools' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="rule_status"><?php echo esc_html__( 'Status', 'discount-tools' ); ?></label>
							</th>
							<td>
								<select id="rule_status" name="status">
									<option value="active" <?php selected( $dt_rule->get_status(), 'active' ); ?>><?php echo esc_html__( 'Active', 'discount-tools' ); ?></option>
									<option value="inactive" <?php selected( $dt_rule->get_status(), 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'discount-tools' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="rule_priority"><?php echo esc_html__( 'Priority', 'discount-tools' ); ?></label>
							</th>
							<td>
								<input type="number" 
									   id="rule_priority" 
									   name="priority" 
									   value="<?php echo esc_attr( $dt_rule->get_priority() ); ?>" 
									   min="1" 
									   max="100">
								<p class="description"><?php echo esc_html__( 'Higher numbers = higher priority. Range: 1-100.', 'discount-tools' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="rule_stackable"><?php echo esc_html__( 'Stackable', 'discount-tools' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" 
										   id="rule_stackable" 
										   name="stackable" 
										   value="1" 
										   <?php checked( $dt_rule->get_stackable(), true ); ?>>
									<?php echo esc_html__( 'Allow this rule to stack with other rules', 'discount-tools' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Discount Configuration -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php echo esc_html__( 'Discount Configuration', 'discount-tools' ); ?></h2>
				</div>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="discount_type"><?php echo esc_html__( 'Discount Type', 'discount-tools' ); ?></label>
							</th>
							<td>
								<select id="discount_type" name="discount_type">
									<option value="percentage" <?php selected( $dt_rule->get_discount_type(), 'percentage' ); ?>><?php echo esc_html__( 'Percentage', 'discount-tools' ); ?></option>
									<option value="fixed_amount" <?php selected( $dt_rule->get_discount_type(), 'fixed_amount' ); ?>><?php echo esc_html__( 'Fixed Amount', 'discount-tools' ); ?></option>
									<option value="price_override" <?php selected( $dt_rule->get_discount_type(), 'price_override' ); ?>><?php echo esc_html__( 'Price Override', 'discount-tools' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="discount_value"><?php echo esc_html__( 'Discount Value', 'discount-tools' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="number" 
									   id="discount_value" 
									   name="discount_value" 
									   value="<?php echo esc_attr( $dt_rule->get_discount_value() ); ?>" 
									   step="0.01" 
									   min="0" 
									   required>
								<p class="description"><?php echo esc_html__( 'Enter the discount value (percentage or amount).', 'discount-tools' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Date Range (Optional) -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php echo esc_html__( 'Date Range (Optional)', 'discount-tools' ); ?></h2>
				</div>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="start_date"><?php echo esc_html__( 'Start Date', 'discount-tools' ); ?></label>
							</th>
							<td>
								<input type="datetime-local" 
									   id="start_date" 
									   name="start_date" 
									   value="<?php echo $dt_rule->get_start_date() ? esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( $dt_rule->get_start_date() ) ) ) : ''; ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="end_date"><?php echo esc_html__( 'End Date', 'discount-tools' ); ?></label>
							</th>
							<td>
								<input type="datetime-local" 
									   id="end_date" 
									   name="end_date" 
									   value="<?php echo $dt_rule->get_end_date() ? esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( $dt_rule->get_end_date() ) ) ) : ''; ?>">
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Usage Limit (Optional) -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php echo esc_html__( 'Usage Limit (Optional)', 'discount-tools' ); ?></h2>
				</div>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="usage_limit"><?php echo esc_html__( 'Usage Limit', 'discount-tools' ); ?></label>
							</th>
							<td>
								<input type="number" 
									   id="usage_limit" 
									   name="usage_limit" 
									   value="<?php echo esc_attr( $dt_rule->get_usage_limit() ); ?>" 
									   min="0">
								<p class="description"><?php echo esc_html__( 'Maximum number of times this rule can be used. Leave 0 for unlimited.', 'discount-tools' ); ?></p>
							</td>
						</tr>
						<?php if ( ! $dt_is_new ) : ?>
						<tr>
							<th scope="row">
								<?php echo esc_html__( 'Current Usage', 'discount-tools' ); ?>
							</th>
							<td>
								<strong><?php echo esc_html( $dt_rule->get_usage_count() ); ?></strong> times
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
			</div>

			<!-- Conditions section will be added in next task -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php echo esc_html__( 'Conditions', 'discount-tools' ); ?></h2>
				</div>
				<div class="inside">
					<p class="description"><?php echo esc_html__( 'Conditions functionality will be available in the next update.', 'discount-tools' ); ?></p>
				</div>
			</div>

			<!-- Save Button -->
			<p class="submit">
				<input type="submit" 
					   name="discount_tools_save_rule" 
					   class="button button-primary" 
					   value="<?php echo $dt_is_new ? esc_attr__( 'Create Rule', 'discount-tools' ) : esc_attr__( 'Update Rule', 'discount-tools' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=discount-tools' ) ); ?>" class="button">
					<?php echo esc_html__( 'Cancel', 'discount-tools' ); ?>
				</a>
			</p>

		</div>
	</form>
</div>

<style>
.discount-tools-rule-edit .postbox {
	margin-bottom: 20px;
}

.discount-tools-rule-edit .required {
	color: #d63638;
}

.discount-tools-rule-edit .form-table th {
	width: 200px;
}
</style>
