<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Rule Editor - General Tab
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

?>

<div class="dt-tab-panel dt-tab-general">
	<!-- Hidden field to identify which tab is being saved -->
	<input type="hidden" name="active_tab" value="general">
	
	<!-- Basic Information Section -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Basic Information', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Configure the basic settings for this discount rule.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<div class="dt-form-grid">
				<!-- Rule Name -->
				<div class="dt-form-field dt-field-full">
					<label for="rule_name" class="dt-form-label">
						<?php echo esc_html__( 'Rule Name', 'discount-tools' ); ?>
						<span class="required">*</span>
					</label>
					<input type="text" 
						   id="rule_name" 
						   name="name" 
						   value="<?php echo esc_attr( $rule->get_name() ); ?>" 
						   class="dt-form-input" 
						   placeholder="<?php echo esc_attr__( 'e.g., Summer Sale 20% Off', 'discount-tools' ); ?>"
						   required>
					<span class="dt-form-description">
						<?php echo esc_html__( 'Enter a descriptive name for this discount rule (for internal use only).', 'discount-tools' ); ?>
					</span>
				</div>

				<!-- Rule Description -->
				<div class="dt-form-field dt-field-full">
					<label for="rule_description" class="dt-form-label">
						<?php echo esc_html__( 'Description', 'discount-tools' ); ?>
					</label>
					<textarea id="rule_description" 
							  name="description" 
							  class="dt-form-input" 
							  rows="3"
							  placeholder="<?php echo esc_attr__( 'Optional description for reference', 'discount-tools' ); ?>"><?php echo esc_textarea( $rule->get_description() ); ?></textarea>
					<span class="dt-form-description">
						<?php echo esc_html__( 'Optional description to help you remember what this rule is for.', 'discount-tools' ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Rule Configuration Section -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Rule Configuration', 'discount-tools' ); ?></h2>
		</div>
		<div class="dt-card-body">
			<div class="dt-form-grid">
			<!-- Rule Type -->
			<div class="dt-form-field">
				<label for="rule_type" class="dt-form-label">
					<?php echo esc_html__( 'Rule Type', 'discount-tools' ); ?>
					<span class="required">*</span>
				</label>
				<select id="rule_type" name="rule_type" class="dt-form-input" required>
					<option value="product" <?php selected( $rule->get_rule_type(), 'product' ); ?>>
						<?php echo esc_html__( 'Product Discount', 'discount-tools' ); ?>
					</option>
					<option value="cart" <?php selected( $rule->get_rule_type(), 'cart' ); ?>>
						<?php echo esc_html__( 'Cart Discount', 'discount-tools' ); ?>
					</option>
				</select>
				<span class="dt-form-description">
					<?php echo esc_html__( 'Product: Applies to individual products. Cart: Applies to entire cart.', 'discount-tools' ); ?>
				</span>
			</div>				<!-- Status -->
				<div class="dt-form-field">
					<label for="rule_status" class="dt-form-label">
						<?php echo esc_html__( 'Status', 'discount-tools' ); ?>
						<span class="required">*</span>
					</label>
					<select id="rule_status" name="status" class="dt-form-input" required>
						<option value="active" <?php selected( $rule->get_status(), 'active' ); ?>>
							<?php echo esc_html__( 'Active', 'discount-tools' ); ?>
						</option>
						<option value="inactive" <?php selected( $rule->get_status(), 'inactive' ); ?>>
							<?php echo esc_html__( 'Inactive', 'discount-tools' ); ?>
						</option>
					</select>
					<span class="dt-form-description">
						<?php echo esc_html__( 'Only active rules will be applied to orders.', 'discount-tools' ); ?>
					</span>
				</div>

				<!-- Priority -->
				<div class="dt-form-field">
					<label for="rule_priority" class="dt-form-label">
						<?php echo esc_html__( 'Priority', 'discount-tools' ); ?>
						<span class="required">*</span>
					</label>
					<input type="number" 
						   id="rule_priority" 
						   name="priority" 
						   value="<?php echo esc_attr( $rule->get_priority() ); ?>" 
						   class="dt-form-input" 
						   min="1" 
						   max="100"
						   required>
					<span class="dt-form-description">
						<?php echo esc_html__( 'Higher numbers = higher priority (1-100). Default: 10', 'discount-tools' ); ?>
					</span>
				</div>

				<!-- Stackable -->
				<div class="dt-form-field">
					<label class="dt-form-label">
						<?php echo esc_html__( 'Stacking', 'discount-tools' ); ?>
					</label>
				<div class="dt-checkbox-wrapper">
					<label class="dt-checkbox-label">
						<input type="checkbox" 
							   id="rule_stackable" 
							   name="stackable" 
							   value="1" 
							   <?php checked( $rule->is_stackable(), true ); ?>>
						<span><?php echo esc_html__( 'Allow this rule to stack with other rules', 'discount-tools' ); ?></span>
					</label>
				</div>
				<span class="dt-form-description">
					<?php echo esc_html__( 'If enabled, this discount can be combined with other stackable discounts.', 'discount-tools' ); ?>
				</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Date Range Section -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Date Range', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Optional: Limit this rule to a specific time period.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<div class="dt-form-grid">
				<!-- Start Date -->
				<div class="dt-form-field">
					<label for="start_date" class="dt-form-label">
						<?php echo esc_html__( 'Start Date & Time', 'discount-tools' ); ?>
					</label>
					<input type="datetime-local" 
						   id="start_date" 
						   name="start_date" 
						   value="<?php echo $rule->get_start_date() ? esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( $rule->get_start_date() ) ) ) : ''; ?>"
						   class="dt-form-input dt-datepicker">
					<span class="dt-form-description">
						<?php echo esc_html__( 'Leave empty for no start date restriction.', 'discount-tools' ); ?>
					</span>
				</div>

				<!-- End Date -->
				<div class="dt-form-field">
					<label for="end_date" class="dt-form-label">
						<?php echo esc_html__( 'End Date & Time', 'discount-tools' ); ?>
					</label>
					<input type="datetime-local" 
						   id="end_date" 
						   name="end_date" 
						   value="<?php echo $rule->get_end_date() ? esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( $rule->get_end_date() ) ) ) : ''; ?>"
						   class="dt-form-input dt-datepicker">
					<span class="dt-form-description">
						<?php echo esc_html__( 'Leave empty for no end date restriction.', 'discount-tools' ); ?>
					</span>
				</div>
			</div>

			<?php if ( ! $editor->is_new() && ( $rule->get_start_date() || $rule->get_end_date() ) ) : ?>
				<div class="dt-date-info">
					<?php
					$dt_now = current_time( 'timestamp' );
					$dt_start = $rule->get_start_date() ? strtotime( $rule->get_start_date() ) : null;
					$dt_end = $rule->get_end_date() ? strtotime( $rule->get_end_date() ) : null;

					if ( $dt_start && $dt_now < $dt_start ) {
						echo '<div class="notice notice-info inline"><p>';
						echo '<span class="dashicons dashicons-clock"></span> ';
						echo wp_kses_post( sprintf(
							/* translators: %s: human-readable time difference */
							esc_html__( 'This rule is scheduled to start in %s.', 'discount-tools' ),
							esc_html( human_time_diff( $dt_now, $dt_start ) )
						) );
						echo '</p></div>';
					} elseif ( $dt_end && $dt_now > $dt_end ) {
						echo '<div class="notice notice-warning inline"><p>';
						echo '<span class="dashicons dashicons-warning"></span> ';
						echo wp_kses_post( sprintf(
							/* translators: %s: human-readable time difference */
							esc_html__( 'This rule expired %s ago.', 'discount-tools' ),
							esc_html( human_time_diff( $dt_end, $dt_now ) )
						) );
						echo '</p></div>';
					} elseif ( $dt_start && $dt_end && $dt_now >= $dt_start && $dt_now <= $dt_end ) {
						echo '<div class="notice notice-success inline"><p>';
						echo '<span class="dashicons dashicons-yes"></span> ';
						echo wp_kses_post( sprintf(
							/* translators: %s: human-readable time difference */
							esc_html__( 'This rule is currently active and will end in %s.', 'discount-tools' ),
							esc_html( human_time_diff( $dt_now, $dt_end ) )
						) );
						echo '</p></div>';
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Quick Tips -->
	<div class="dt-card dt-card-info">
		<div class="dt-card-header">
			<h2>
				<span class="dashicons dashicons-info"></span>
				<?php echo esc_html__( 'Quick Tips', 'discount-tools' ); ?>
			</h2>
		</div>
		<div class="dt-card-body">
			<ul class="dt-tips-list">
				<li>
					<strong><?php echo esc_html__( 'Rule Name:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Choose a clear name to easily identify this rule in the list.', 'discount-tools' ); ?>
				</li>
				<li>
					<strong><?php echo esc_html__( 'Priority:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Higher priority rules are evaluated first. Use priority to control which discount applies when multiple rules match.', 'discount-tools' ); ?>
				</li>
				<li>
					<strong><?php echo esc_html__( 'Stacking:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Enable stacking to allow multiple discounts to apply together. Disable to make this rule exclusive.', 'discount-tools' ); ?>
				</li>
				<li>
					<strong><?php echo esc_html__( 'Date Range:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Perfect for seasonal sales or limited-time promotions. Rules automatically activate and deactivate based on dates.', 'discount-tools' ); ?>
				</li>
			</ul>
		</div>
	</div>

</div>
