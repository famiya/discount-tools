<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Rule Editor - Conditions Tab
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

// Get existing conditions.
$dt_conditions = $rule->get_conditions();
$dt_condition_groups = array();

// Group conditions by group_id.
foreach ( $dt_conditions as $dt_condition ) {
	if ( is_object( $dt_condition ) && method_exists( $dt_condition, 'get_data' ) ) {
		$dt_condition = $dt_condition->get_data();
	}

	$dt_group_id = isset( $dt_condition['group_id'] ) ? $dt_condition['group_id'] : 0;
	if ( ! isset( $dt_condition_groups[ $dt_group_id ] ) ) {
		$dt_condition_groups[ $dt_group_id ] = array();
	}
	$dt_condition_groups[ $dt_group_id ][] = $dt_condition;
}

// Ensure at least one empty group exists.
if ( empty( $dt_condition_groups ) ) {
	$dt_condition_groups[0] = array();
}

?>

<div class="dt-tab-panel dt-tab-conditions">
	
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Conditions', 'discount-tools' ); ?></h2>
			<p class="description">
				<?php echo esc_html__( 'Define when this discount rule should be applied. Conditions within a group use AND logic, while groups are combined with OR logic.', 'discount-tools' ); ?>
			</p>
		</div>
		<div class="dt-card-body">

			<div class="dt-condition-groups" id="dt-condition-groups">
				<?php foreach ( $dt_condition_groups as $dt_group_id => $dt_group_conditions ) : ?>
					<div class="dt-condition-group" data-group-id="<?php echo esc_attr( $dt_group_id ); ?>">
						<div class="dt-condition-group-header">
							<span class="dt-group-label">
								<?php
								/* translators: %d: group number */
								echo esc_html( sprintf( __( 'Condition Group %d', 'discount-tools' ), $dt_group_id + 1 ) );
								?>
							</span>
							<button type="button" class="button dt-remove-group">
								<?php echo esc_html__( 'Remove Group', 'discount-tools' ); ?>
							</button>
						</div>

						<div class="dt-conditions-list">
							<?php
							if ( empty( $dt_group_conditions ) ) {
								// Add one empty condition.
								$dt_condition = array(
									'condition_type' => '',
									'operator'       => 'equals',
									'value'          => '',
								);
								$dt_index = 0;
								include __DIR__ . '/condition-row.php';
							} else {
								foreach ( $dt_group_conditions as $dt_index => $dt_condition ) {
									include __DIR__ . '/condition-row.php';
								}
							}
							?>
						</div>

						<div class="dt-condition-group-actions">
							<button type="button" class="button dt-add-condition">
								<span class="dashicons dashicons-plus-alt"></span>
								<?php echo esc_html__( 'Add Condition (AND)', 'discount-tools' ); ?>
							</button>
						</div>

						<?php if ( count( $dt_condition_groups ) > 1 || $dt_group_id > 0 ) : ?>
							<div class="dt-or-separator">
								<span><?php echo esc_html__( 'OR', 'discount-tools' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="dt-condition-builder-actions" style="margin-top: 20px;">
				<button type="button" class="button button-secondary dt-add-group">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php echo esc_html__( 'Add Condition Group (OR)', 'discount-tools' ); ?>
				</button>
			</div>

			<div class="dt-card" style="margin-top: 30px; background: #f9f9f9;">
				<div class="dt-card-header">
					<h3><?php echo esc_html__( 'Logic Guide', 'discount-tools' ); ?></h3>
				</div>
				<div class="dt-card-body">
					<ul style="list-style: disc; margin-left: 20px; line-height: 1.8;">
						<li><strong><?php echo esc_html__( 'AND Logic:', 'discount-tools' ); ?></strong> <?php echo esc_html__( 'All conditions within a group must be true.', 'discount-tools' ); ?></li>
						<li><strong><?php echo esc_html__( 'OR Logic:', 'discount-tools' ); ?></strong> <?php echo esc_html__( 'At least one group must have all conditions true.', 'discount-tools' ); ?></li>
						<li><strong><?php echo esc_html__( 'Example:', 'discount-tools' ); ?></strong> <?php echo esc_html__( '(Product is A AND Cart total > $100) OR (Product is B AND User is VIP)', 'discount-tools' ); ?></li>
					</ul>
				</div>
			</div>

		</div>
	</div>

</div>

<!-- Condition Row Template (Hidden) -->
<script type="text/html" id="dt-condition-row-template">
	<?php
	$dt_condition = array(
		'condition_type' => '',
		'operator'       => 'equals',
		'value'          => '',
	);
	$dt_index = '{{INDEX}}';
	$dt_group_id = '{{GROUP_ID}}';
	include __DIR__ . '/condition-row.php';
	?>
</script>
