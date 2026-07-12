<?php
/**
 * Rule Editor - Preview Tab
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

<div class="dt-tab-panel dt-tab-preview">
	
	<!-- Preview Settings -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Rule Preview', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Test how this discount rule will apply to different products and scenarios.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			
			<!-- Preview Controls -->
			<div class="dt-preview-controls">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="preview_product">
								<?php echo esc_html__( 'Test Product', 'discount-tools' ); ?>
							</label>
						</th>
						<td>
							<select id="preview_product" class="wc-product-search" style="width: 100%;" data-placeholder="<?php echo esc_attr__( 'Select a product...', 'discount-tools' ); ?>">
								<option value=""><?php echo esc_html__( 'Select a product...', 'discount-tools' ); ?></option>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Choose a product to test the discount calculation.', 'discount-tools' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="preview_quantity">
								<?php echo esc_html__( 'Quantity', 'discount-tools' ); ?>
							</label>
						</th>
						<td>
							<input type="number" 
								   id="preview_quantity" 
								   class="small-text"
								   value="1" 
								   min="1" 
								   max="9999">
							<p class="description">
								<?php echo esc_html__( 'Number of items to test.', 'discount-tools' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="preview_user_role">
								<?php echo esc_html__( 'User Role', 'discount-tools' ); ?>
							</label>
						</th>
						<td>
							<select id="preview_user_role" class="regular-text">
								<option value="guest"><?php echo esc_html__( 'Guest (Not logged in)', 'discount-tools' ); ?></option>
								<option value="customer" selected><?php echo esc_html__( 'Customer', 'discount-tools' ); ?></option>
								<option value="subscriber"><?php echo esc_html__( 'Subscriber', 'discount-tools' ); ?></option>
								<option value="administrator"><?php echo esc_html__( 'Administrator', 'discount-tools' ); ?></option>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Test as different user roles.', 'discount-tools' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" id="btn-run-preview" class="button button-primary">
						<span class="dashicons dashicons-controls-play"></span>
						<?php echo esc_html__( 'Run Preview', 'discount-tools' ); ?>
					</button>
					<span class="spinner" style="float: none; margin: 0 10px;"></span>
				</p>
			</div>

			<!-- Preview Results -->
			<div id="preview-results" class="dt-preview-results" style="display: none;">
				<h3><?php echo esc_html__( 'Preview Results', 'discount-tools' ); ?></h3>
				<div id="preview-content"></div>
			</div>

		</div>
	</div>

	<!-- Preview Information -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'How Preview Works', 'discount-tools' ); ?></h2>
		</div>
		<div class="dt-card-body">
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php echo esc_html__( 'Select a product and quantity to test.', 'discount-tools' ); ?></li>
				<li><?php echo esc_html__( 'The preview will show if the rule conditions are met.', 'discount-tools' ); ?></li>
				<li><?php echo esc_html__( 'You\'ll see the original price, discount amount, and final price.', 'discount-tools' ); ?></li>
				<li><?php echo esc_html__( 'Preview uses current rule settings (save changes first for accurate results).', 'discount-tools' ); ?></li>
			</ul>

			<div class="notice notice-warning inline" style="margin-top: 15px;">
				<p>
					<strong><?php echo esc_html__( 'Note:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Preview functionality requires the Rule Engine to be fully implemented. This feature will be completed in Phase 3 (Task 11).', 'discount-tools' ); ?>
				</p>
			</div>
		</div>
	</div>

</div>

<style>
.dt-preview-controls {
	background: #f9f9f9;
	padding: 20px;
	border-radius: 4px;
	margin-bottom: 20px;
}

.dt-preview-results {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 20px;
	margin-top: 20px;
}

.dt-preview-results h3 {
	margin-top: 0;
	color: #2271b1;
}

#preview-content {
	margin-top: 15px;
}

.preview-result-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 10px;
}

.preview-result-table th,
.preview-result-table td {
	padding: 10px;
	border: 1px solid #ddd;
	text-align: left;
}

.preview-result-table th {
	background: #f0f0f1;
	font-weight: 600;
}

.preview-result-success {
	color: #007017;
	font-weight: 600;
}

.preview-result-fail {
	color: #d63638;
}

.dt-tab-preview .dashicons {
	vertical-align: middle;
	margin-right: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Initialize product search (requires Select2 or WC product search)
	if (typeof $.fn.selectWoo !== 'undefined') {
		$('.wc-product-search').selectWoo({
			minimumInputLength: 2,
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						term: params.term,
						action: 'woocommerce_json_search_products',
						security: '<?php echo esc_js( wp_create_nonce( "search-products" ) ); ?>'
					};
				},
				processResults: function(data) {
					var results = [];
					$.each(data, function(id, text) {
						results.push({
							id: id,
							text: text
						});
					});
					return {
						results: results
					};
				}
			}
		});
	}

	// Run Preview
	$('#btn-run-preview').on('click', function() {
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $results = $('#preview-results');
		var $content = $('#preview-content');

		var productId = $('#preview_product').val();
		var quantity = $('#preview_quantity').val();
		var userRole = $('#preview_user_role').val();

		if (!productId) {
			alert('<?php echo esc_js( __( 'Please select a product first.', 'discount-tools' ) ); ?>');
			return;
		}

		// Show loading
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$results.hide();

		// AJAX request to preview
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'dt_preview_rule',
				nonce: '<?php echo esc_js( wp_create_nonce( "dt-preview-rule" ) ); ?>',
				product_id: productId,
				quantity: quantity,
				user_role: userRole,
				rule_id: <?php echo (int) $rule->get_id(); ?>
			},
			success: function(response) {
				if (response.success) {
					$content.html(buildPreviewResult(response.data));
					$results.fadeIn();
				} else {
					alert(response.data.message || '<?php echo esc_js( __( 'Preview failed. Please try again.', 'discount-tools' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'AJAX error. Please check your connection.', 'discount-tools' ) ); ?>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Build preview result HTML
	function buildPreviewResult(data) {
		var html = '<div class="preview-result">';
		
		html += '<h4>' + data.product_name + '</h4>';
		
		if (data.applicable) {
			html += '<p class="preview-result-success"><span class="dashicons dashicons-yes-alt"></span> ' + 
					'<?php echo esc_js( __( 'Rule conditions are met!', 'discount-tools' ) ); ?></p>';
			
			html += '<table class="preview-result-table">';
			html += '<tr><th><?php echo esc_js( __( 'Original Price', 'discount-tools' ) ); ?></th><td>' + data.original_price + '</td></tr>';
			html += '<tr><th><?php echo esc_js( __( 'Discount Amount', 'discount-tools' ) ); ?></th><td class="preview-result-success">' + data.discount_amount + '</td></tr>';
			html += '<tr><th><?php echo esc_js( __( 'Final Price', 'discount-tools' ) ); ?></th><td><strong>' + data.final_price + '</strong></td></tr>';
			html += '<tr><th><?php echo esc_js( __( 'Quantity', 'discount-tools' ) ); ?></th><td>' + data.quantity + '</td></tr>';
			html += '<tr><th><?php echo esc_js( __( 'Total Savings', 'discount-tools' ) ); ?></th><td class="preview-result-success"><strong>' + data.total_savings + '</strong></td></tr>';
			html += '</table>';
		} else {
			html += '<p class="preview-result-fail"><span class="dashicons dashicons-dismiss"></span> ' + 
					'<?php echo esc_js( __( 'Rule conditions are NOT met.', 'discount-tools' ) ); ?></p>';
			
			if (data.reason) {
				html += '<p><strong><?php echo esc_js( __( 'Reason:', 'discount-tools' ) ); ?></strong> ' + data.reason + '</p>';
			}
		}
		
		html += '</div>';
		
		return html;
	}

	// Placeholder function (will be implemented when backend is ready)
	console.log('Preview tab initialized');
});
</script>
