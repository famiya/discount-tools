<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Rule Editor - Usage Limits Tab
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

<div class="dt-tab-panel dt-tab-usage">
	
	<!-- Usage Limits Section -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Usage Limits', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Control how many times this discount can be used.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<div class="dt-form-grid">
				<!-- Total Usage Limit -->
				<div class="dt-form-field">
					<label for="usage_limit" class="dt-form-label">
						<?php echo esc_html__( 'Total Usage Limit', 'discount-tools' ); ?>
					</label>
					<input type="number" 
						   id="usage_limit" 
						   name="usage_limit" 
						   value="<?php echo esc_attr( $rule->get_usage_limit() ); ?>" 
						   class="dt-form-input" 
						   min="0"
						   placeholder="0">
					<span class="dt-form-description">
						<?php echo esc_html__( 'Maximum number of times this rule can be used across all customers. 0 = unlimited.', 'discount-tools' ); ?>
					</span>
				</div>

				<!-- Current Usage (for existing rules) -->
				<?php if ( ! $editor->is_new() ) : ?>
				<div class="dt-form-field">
					<label class="dt-form-label">
						<?php echo esc_html__( 'Current Usage', 'discount-tools' ); ?>
					</label>
					<div class="dt-usage-display">
						<div class="dt-usage-stats">
							<span class="dt-usage-count"><?php echo esc_html( $rule->get_usage_count() ); ?></span>
							<span class="dt-usage-label"><?php echo esc_html__( 'times used', 'discount-tools' ); ?></span>
						</div>
						<?php if ( $rule->get_usage_limit() > 0 ) : ?>
							<?php 
							$dt_percentage = ( $rule->get_usage_count() / $rule->get_usage_limit() ) * 100;
							$dt_bar_class = $dt_percentage >= 90 ? 'dt-usage-bar-danger' : ( $dt_percentage >= 75 ? 'dt-usage-bar-warning' : '' );
							?>
							<div class="dt-usage-bar-wrapper">
								<div class="dt-usage-bar <?php echo esc_attr( $dt_bar_class ); ?>">
									<div class="dt-usage-bar-fill" style="width: <?php echo esc_attr( min( 100, $dt_percentage ) ); ?>%;"></div>
								</div>
								<span class="dt-usage-percentage"><?php echo esc_html( number_format( $dt_percentage, 1 ) ); ?>%</span>
							</div>
						<?php endif; ?>
					</div>
					<span class="dt-form-description">
						<?php echo esc_html__( 'Number of orders where this discount has been applied.', 'discount-tools' ); ?>
					</span>
					<div style="margin-top: 10px;">
						<button type="button" id="dt-reset-usage-count" class="button" data-rule-id="<?php echo esc_attr( $rule->get_id() ); ?>">
							<?php echo esc_html__( 'Reset Usage Count', 'discount-tools' ); ?>
						</button>
						<span class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( ! $editor->is_new() && $rule->get_usage_limit() > 0 ) : ?>
				<?php
				$dt_remaining = max( 0, $rule->get_usage_limit() - $rule->get_usage_count() );
				if ( $dt_remaining === 0 ) {
					echo '<div class="notice notice-warning inline"><p>';
					echo '<span class="dashicons dashicons-warning"></span> ';
					echo esc_html__( 'This rule has reached its usage limit and will not be applied to new orders.', 'discount-tools' );
					echo '</p></div>';
				} elseif ( $dt_remaining <= 10 ) {
					echo '<div class="notice notice-info inline"><p>';
					echo '<span class="dashicons dashicons-info"></span> ';
					echo wp_kses_post( sprintf(
						/* translators: %d: number of uses remaining */
						esc_html__( 'Only %d uses remaining before this rule reaches its limit.', 'discount-tools' ),
						esc_html( $dt_remaining )
					) );
					echo '</p></div>';
				}
				?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Per User Limits -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Per-User Limits', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Limit how many times each customer can use this discount.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<div class="dt-form-grid">
				<!-- Per-User Usage Limit -->
				<div class="dt-form-field">
					<label for="usage_limit_per_user" class="dt-form-label">
						<?php echo esc_html__( 'Per-User Usage Limit', 'discount-tools' ); ?>
					</label>
					<input type="number" 
						   id="usage_limit_per_user" 
						   name="usage_limit_per_user" 
						   value="<?php echo esc_attr( $rule->get_usage_limit_per_user() ? $rule->get_usage_limit_per_user() : 0 ); ?>" 
						   class="dt-form-input" 
						   min="0"
						   placeholder="0">
					<span class="dt-form-description">
						<?php echo esc_html__( 'Maximum number of times each individual customer can use this discount. 0 = unlimited.', 'discount-tools' ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<?php if ( ! $editor->is_new() && $rule->get_usage_count() > 0 ) : ?>
	<!-- Usage History (Placeholder) -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Recent Usage', 'discount-tools' ); ?></h2>
		</div>
		<div class="dt-card-body">
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=discount-tools-reports&rule_id=' . $rule->get_id() ) ); ?>" class="button">
					<?php echo esc_html__( 'View Detailed Usage Report', 'discount-tools' ); ?>
				</a>
			</p>
			<span class="dt-form-description">
				<?php echo esc_html__( 'See which orders used this discount, total savings, and more.', 'discount-tools' ); ?>
			</span>
		</div>
	</div>
	<?php endif; ?>

</div>

<style>
.dt-usage-display {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.dt-usage-stats {
	display: flex;
	align-items: baseline;
	gap: 8px;
}

.dt-usage-count {
	font-size: 32px;
	font-weight: 700;
	color: #2271b1;
}

.dt-usage-label {
	font-size: 14px;
	color: #646970;
}

.dt-usage-bar-wrapper {
	display: flex;
	align-items: center;
	gap: 10px;
}

.dt-usage-bar {
	flex: 1;
	height: 12px;
	background: #e0e0e0;
	border-radius: 6px;
	overflow: hidden;
}

.dt-usage-bar-fill {
	height: 100%;
	background: #2271b1;
	transition: width 0.3s ease;
	border-radius: 6px;
}

.dt-usage-bar-warning .dt-usage-bar-fill {
	background: #dba617;
}

.dt-usage-bar-danger .dt-usage-bar-fill {
	background: #d63638;
}

.dt-usage-percentage {
	font-size: 13px;
	font-weight: 600;
	color: #646970;
	min-width: 45px;
	text-align: right;
}
</style>
