<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Condition Row Template
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin/partials
 *
 * @var array $dt_condition Condition data
 * @var int   $dt_index     Condition index
 * @var int   $dt_group_id  Group ID (optional, defaults to {{GROUP_ID}} for template)
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure Brand Detector is loaded
if ( ! class_exists( 'Discount_Tools_Brand_Detector' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '../class-brand-detector.php';
}

$dt_group_id = isset( $dt_group_id ) ? $dt_group_id : '{{GROUP_ID}}';
$dt_condition_type = isset( $dt_condition['condition_type'] ) ? $dt_condition['condition_type'] : '';
$dt_operator = isset( $dt_condition['operator'] ) ? $dt_condition['operator'] : 'equals';
$dt_value = isset( $dt_condition['value'] ) ? $dt_condition['value'] : '';

// Condition types configuration.
$dt_condition_types = array(
	'product'           => __( 'Product', 'discount-tools' ),
	'product_category'  => __( 'Product Category', 'discount-tools' ),
	'product_tag'       => __( 'Product Tag', 'discount-tools' ),
	'cart_total'        => __( 'Cart Total', 'discount-tools' ),
	'cart_quantity'     => __( 'Cart Quantity', 'discount-tools' ),
	'payment_method'    => __( 'Payment Method', 'discount-tools' ),
	'coupon_activation' => __( 'Coupon Activation Code', 'discount-tools' ),
);

// Add brand conditionally if detected
if ( Discount_Tools_Brand_Detector::has_brand_taxonomy() ) {
	$dt_condition_types['brand'] = __( 'Brand', 'discount-tools' );
}

// Operators configuration.
$dt_operators = array(
	'equals'            => __( 'equals', 'discount-tools' ),
	'not_equals'        => __( 'not equals', 'discount-tools' ),
	'in'                => __( 'in list', 'discount-tools' ),
	'not_in'            => __( 'not in list', 'discount-tools' ),
	'contains'          => __( 'contains', 'discount-tools' ),
	'greater_than'      => __( 'greater than', 'discount-tools' ),
	'greater_or_equal'  => __( 'greater or equal', 'discount-tools' ),
	'less_than'         => __( 'less than', 'discount-tools' ),
	'less_or_equal'     => __( 'less or equal', 'discount-tools' ),
);

// Operators by condition type.
$dt_type_operators = array(
	'product'           => array( 'in', 'not_in' ),
	'product_category'  => array( 'in', 'not_in' ),
	'product_tag'       => array( 'in', 'not_in' ),
	'brand'             => array( 'in', 'not_in' ),
	'cart_total'        => array( 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'equals' ),
	'cart_quantity'     => array( 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'equals' ),
	'payment_method'    => array( 'in', 'not_in' ),
	'coupon_activation' => array( 'in' ),
);

?>

<div class="dt-condition-row" data-index="<?php echo esc_attr( $dt_index ); ?>">
	<div class="dt-condition-fields">
		
		<!-- Condition Type -->
		<div class="dt-condition-field dt-condition-type-field">
			<select name="conditions[<?php echo esc_attr( $dt_group_id ); ?>][<?php echo esc_attr( $dt_index ); ?>][condition_type]" 
					class="dt-condition-type" 
					data-group="<?php echo esc_attr( $dt_group_id ); ?>" 
					data-index="<?php echo esc_attr( $dt_index ); ?>">
				<option value=""><?php echo esc_html__( '-- Select Condition --', 'discount-tools' ); ?></option>
				<?php foreach ( $dt_condition_types as $dt_type_key => $dt_type_label ) : ?>
					<option value="<?php echo esc_attr( $dt_type_key ); ?>" 
							<?php selected( $dt_condition_type, $dt_type_key ); ?>>
						<?php echo esc_html( $dt_type_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<!-- Operator -->
		<div class="dt-condition-field dt-condition-operator-field">
			<select name="conditions[<?php echo esc_attr( $dt_group_id ); ?>][<?php echo esc_attr( $dt_index ); ?>][operator]" 
					class="dt-condition-operator"
					data-type-operators='<?php echo esc_attr( wp_json_encode( $dt_type_operators ) ); ?>'>
				<?php foreach ( $dt_operators as $dt_op_key => $dt_op_label ) : ?>
					<option value="<?php echo esc_attr( $dt_op_key ); ?>" 
							<?php selected( $dt_operator, $dt_op_key ); ?>>
						<?php echo esc_html( $dt_op_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

	<!-- Value Input -->
	<?php 
	// Prepare display values for coupon_activation
	$dt_display_value = $dt_value;
	$dt_coupon_mappings = array(); // Array of {coupon: "", email: ""}
	
	if ( 'coupon_activation' === $dt_condition_type ) {
		// Parse the value - new structure: {"mappings": [{"coupon":"CODE1","email":"user@example.com"}, ...]}
		$dt_parsed_value = null;
		if ( is_string( $dt_value ) && ! empty( $dt_value ) ) {
			$dt_parsed_value = json_decode( $dt_value, true );
		} elseif ( is_array( $dt_value ) ) {
			$dt_parsed_value = $dt_value;
		}
		
		if ( is_array( $dt_parsed_value ) ) {
			// New format: mappings array
			if ( isset( $dt_parsed_value['mappings'] ) && is_array( $dt_parsed_value['mappings'] ) ) {
				$dt_coupon_mappings = $dt_parsed_value['mappings'];
			}
			// Old format: separate coupon_codes and email_list arrays - convert to new format
			elseif ( isset( $dt_parsed_value['coupon_codes'] ) && is_array( $dt_parsed_value['coupon_codes'] ) ) {
				$dt_coupons = $dt_parsed_value['coupon_codes'];
				$dt_emails = isset( $dt_parsed_value['email_list'] ) && is_array( $dt_parsed_value['email_list'] ) ? $dt_parsed_value['email_list'] : array();
				foreach ( $dt_coupons as $dt_i => $dt_coupon ) {
					$dt_coupon_mappings[] = array(
						'coupon' => $dt_coupon,
						'email' => isset( $dt_emails[$dt_i] ) ? $dt_emails[$dt_i] : ''
					);
				}
			}
			
			$dt_display_value = array( 'mappings' => $dt_coupon_mappings );
		}
	}
	?>
	
	<div class="dt-condition-field dt-condition-value-field">
		<?php if ( 'payment_method' === $dt_condition_type ) :
			$dt_all_gateways = array();
			if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
				$dt_all_gateways = WC()->payment_gateways()->payment_gateways();
			}
			$dt_selected_gw = array();
			if ( ! empty( $dt_value ) ) {
				if ( is_array( $dt_value ) ) {
					$dt_selected_gw = $dt_value;
				} else {
					$dt_decoded_gw = json_decode( $dt_value, true );
					$dt_selected_gw = is_array( $dt_decoded_gw ) ? $dt_decoded_gw : array( $dt_value );
				}
			}
		?>
			<div class="dt-payment-method-container" style="width:100%;">
				<select class="dt-payment-method-select" multiple style="width:100%;">
					<?php foreach ( $dt_all_gateways as $dt_gw_id => $dt_gateway ) : ?>
						<?php if ( 'yes' !== $dt_gateway->enabled ) : continue; endif; ?>
						<option value="<?php echo esc_attr( $dt_gw_id ); ?>"
							<?php selected( in_array( $dt_gw_id, $dt_selected_gw, true ), true ); ?>>
							<?php echo esc_html( $dt_gateway->get_title() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="hidden"
					name="conditions[<?php echo esc_attr( $dt_group_id ); ?>][<?php echo esc_attr( $dt_index ); ?>][value]"
					class="dt-condition-value"
					value="<?php echo esc_attr( wp_json_encode( $dt_selected_gw ) ); ?>">
			</div>
		<?php elseif ( 'coupon_activation' === $dt_condition_type ) : ?>
			<!-- For Coupon Activation: Show list of coupon-email mappings -->
			<div class="dt-coupon-mappings-container" style="width: 100%;">
				<div class="dt-coupon-mappings-list">
					<?php if ( ! empty( $dt_coupon_mappings ) ) : ?>
						<?php foreach ( $dt_coupon_mappings as $dt_map_index => $dt_mapping ) : ?>
							<div class="dt-coupon-mapping-row" data-map-index="<?php echo esc_attr( $dt_map_index ); ?>">
								<input type="text" 
									   class="dt-mapping-coupon" 
									   value="<?php echo esc_attr( $dt_mapping['coupon'] ); ?>" 
									   placeholder="<?php echo esc_attr__( 'Coupon Code', 'discount-tools' ); ?>"
									   style="width: 45%;">
								<input type="text" 
									   class="dt-mapping-email" 
									   value="<?php echo esc_attr( $dt_mapping['email'] ); ?>" 
									   placeholder="<?php echo esc_attr__( 'Email (optional)', 'discount-tools' ); ?>"
									   list="dt-email-suggestions-<?php echo esc_attr( $dt_group_id . '-' . $dt_index . '-' . $dt_map_index ); ?>"
									   style="width: 45%;">
								<button type="button" class="button dt-remove-mapping" style="width: 8%;" title="<?php echo esc_attr__( 'Remove', 'discount-tools' ); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
								<datalist id="dt-email-suggestions-<?php echo esc_attr( $dt_group_id . '-' . $dt_index . '-' . $dt_map_index ); ?>">
									<!-- Suggestions populated via JS -->
								</datalist>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<button type="button" class="button dt-add-mapping">
					<span class="dashicons dashicons-plus-alt"></span> <?php echo esc_html__( 'Add Coupon', 'discount-tools' ); ?>
				</button>
			</div>
			<!-- Hidden field stores the actual structured value -->
			<input type="hidden" 
				   name="conditions[<?php echo esc_attr( $dt_group_id ); ?>][<?php echo esc_attr( $dt_index ); ?>][value]" 
				   class="dt-condition-value" 
				   value="<?php echo esc_attr( is_array( $dt_display_value ) ? wp_json_encode( $dt_display_value ) : $dt_display_value ); ?>">
		<?php else : ?>
			<!-- For other condition types: Normal input -->
			<input type="text" 
				   name="conditions[<?php echo esc_attr( $dt_group_id ); ?>][<?php echo esc_attr( $dt_index ); ?>][value]" 
				   class="dt-condition-value" 
				   value="<?php echo esc_attr( is_array( $dt_value ) ? wp_json_encode( $dt_value ) : $dt_value ); ?>" 
				   placeholder="<?php echo esc_attr__( 'Value', 'discount-tools' ); ?>">
		<?php endif; ?>
		<input type="hidden" 
			   name="conditions[<?php echo esc_attr( $dt_group_id ); ?>][<?php echo esc_attr( $dt_index ); ?>][group_id]" 
			   value="<?php echo esc_attr( $dt_group_id ); ?>">
	</div>		<!-- Remove Button -->
		<div class="dt-condition-field dt-condition-actions-field">
			<button type="button" class="button dt-remove-condition" title="<?php echo esc_attr__( 'Remove Condition', 'discount-tools' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

	</div>

	<!-- Condition Helper Text -->
	<div class="dt-condition-help" style="display: none;">
		<span class="dt-help-text"></span>
	</div>
</div>
