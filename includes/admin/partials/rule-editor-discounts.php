<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Rule Editor - Discounts Tab
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

<div class="dt-tab-panel dt-tab-discounts">
	<!-- Hidden field to identify which tab is being saved -->
	<input type="hidden" name="active_tab" value="discounts">
	
	<!-- Discount Configuration Section -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Discount Configuration', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Configure how the discount should be calculated and applied.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<div class="dt-form-grid">
				<!-- Discount Type -->
				<div class="dt-form-field">
					<label for="discount_type" class="dt-form-label">
						<?php echo esc_html__( 'Discount Type', 'discount-tools' ); ?>
						<span class="required">*</span>
					</label>
					<select id="discount_type" name="discount_type" class="dt-form-input" required>
						<option value="percentage" <?php selected( $rule->get_discount_type(), 'percentage' ); ?>>
							<?php echo esc_html__( 'Percentage Discount (%)', 'discount-tools' ); ?>
						</option>
						<option value="fixed_amount" <?php selected( $rule->get_discount_type(), 'fixed_amount' ); ?>>
							<?php echo esc_html__( 'Fixed Amount Discount', 'discount-tools' ); ?>
						</option>
						<option value="price_override" <?php selected( $rule->get_discount_type(), 'price_override' ); ?>>
							<?php echo esc_html__( 'Override Price', 'discount-tools' ); ?>
						</option>
					<option value="bxgy_same" <?php selected( $rule->get_discount_type(), 'bxgy_same' ); ?>>
						<?php echo esc_html__( 'Buy X Get X (Same Product)', 'discount-tools' ); ?>
					</option>
					<option value="bxgy_any" <?php selected( $rule->get_discount_type(), 'bxgy_any' ); ?>>
						<?php echo esc_html__( 'Buy X Get Y (Any Qualifying Product)', 'discount-tools' ); ?>
					</option>
					<option value="bundle" <?php selected( $rule->get_discount_type(), 'bundle' ); ?>>
						<?php echo esc_html__( 'Bundle (Set) Discount', 'discount-tools' ); ?>
					</option>
					</select>
					<span class="dt-form-description">
						<?php echo esc_html__( 'Select the type of discount to apply.', 'discount-tools' ); ?>
					</span>
				</div>

				<!-- Discount Value -->
				<div class="dt-form-field">
					<label for="discount_value" class="dt-form-label">
						<?php echo esc_html__( 'Discount Value', 'discount-tools' ); ?>
						<span class="required">*</span>
					</label>
					<input type="number" 
						   id="discount_value" 
						   name="discount_value" 
						   value="<?php echo esc_attr( $rule->get_discount_value() ); ?>" 
						   class="dt-form-input" 
						   step="0.01" 
						   min="0"
						   required>
					<span class="dt-form-description dt-discount-hint">
						<?php echo esc_html__( 'Enter the discount value (e.g., 20 for 20% or $20)', 'discount-tools' ); ?>
					</span>
				</div>
			</div>

			<!-- Discount Type Help -->
			<div class="dt-discount-type-help">
				<div class="dt-help-item" data-type="percentage">
					<span class="dashicons dashicons-info"></span>
					<?php echo esc_html__( 'Percentage: Enter a value between 0 and 100. Example: 20 = 20% discount', 'discount-tools' ); ?>
				</div>
				<div class="dt-help-item" data-type="fixed_amount">
					<span class="dashicons dashicons-info"></span>
					<?php
				/* translators: %1$s: currency symbol, %2$s: formatted price */
				printf( esc_html__( 'Fixed Amount: Enter the discount amount in %1$s. Example: 10 = %2$s discount', 'discount-tools' ), esc_html( get_woocommerce_currency_symbol() ), wp_kses_post( wc_price( 10 ) ) );
				?>
				</div>
				<div class="dt-help-item" data-type="price_override">
					<span class="dashicons dashicons-info"></span>
					<?php echo esc_html__( 'Override Price: Set a new fixed price for the product. Original price will be replaced.', 'discount-tools' ); ?>
				</div>
			<div class="dt-help-item" data-type="bxgy_same">
				<span class="dashicons dashicons-info"></span>
				<?php echo esc_html__( 'Buy X Get X (Same Product): Each product qualifies independently. Example: Buy 2 Get 1 Free, buy 6 units = 2 units free.', 'discount-tools' ); ?>
			</div>
			<div class="dt-help-item" data-type="bxgy_any">
				<span class="dashicons dashicons-info"></span>
				<?php echo esc_html__( 'Buy X Get Y (Any Product): Combine all qualifying items, discount cheapest items. Example: Buy 2 Get 1 Free with 6 total items = 2 cheapest items free.', 'discount-tools' ); ?>
			</div>
			</div>
		</div>
	</div>

	<!-- Usage Limits Section -->
	<div class="dt-card">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Usage Limits', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Restrict how many times this discount can be used.', 'discount-tools' ); ?></p>
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
						   placeholder="<?php echo esc_attr__( 'Unlimited', 'discount-tools' ); ?>">
					<span class="dt-form-description">
						<?php echo esc_html__( 'Maximum number of times this discount can be used in total. Leave empty for unlimited.', 'discount-tools' ); ?>
					</span>
				</div>

				<!-- Current Usage Count (Read-only) -->
				<div class="dt-form-field">
					<label class="dt-form-label">
						<?php echo esc_html__( 'Times Used', 'discount-tools' ); ?>
					</label>
					<input type="text" 
						   value="<?php echo esc_attr( $rule->get_usage_count() ); ?>" 
						   class="dt-form-input" 
						   readonly
						   disabled>
					<span class="dt-form-description">
						<?php echo esc_html__( 'Number of times this discount has been used so far.', 'discount-tools' ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Advanced Discount Options -->
	<div class="dt-card dt-tiered-discounts" style="display: none;">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Tiered Discounts', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Create quantity-based discount tiers (e.g., buy 3 get 10%, buy 5 get 20%).', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<div class="notice notice-info inline">
				<p>
					<span class="dashicons dashicons-info"></span>
					<?php echo esc_html__( 'Tiered discount features will be available in a future update. This will allow you to create multiple discount levels based on quantity purchased.', 'discount-tools' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Buy X Get Y Configuration -->
	<div class="dt-card dt-bxgy-config" style="display: none;">
		<div class="dt-card-header">
			<h2><?php echo esc_html__( 'Buy X Get Y Configuration', 'discount-tools' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Configure the Buy X Get Y promotion details.', 'discount-tools' ); ?></p>
		</div>
		<div class="dt-card-body">
			<div class="dt-form-grid">
			<!-- Buy Quantity (X) -->
			<div class="dt-form-field">
				<label for="bxgy_buy_quantity" class="dt-form-label">
					<?php echo esc_html__( 'Buy Quantity (X)', 'discount-tools' ); ?>
					<span class="required">*</span>
				</label>
					<input type="number" 
						   id="bxgy_buy_quantity" 
						   name="bxgy_buy_quantity" 
					   value="<?php echo esc_attr( $rule->get_bxgy_buy_quantity() ?? 1 ); ?>" 
					   class="dt-form-input" 
					   min="1"
					   step="1"
					   required>
				<span class="dt-form-description">
					<?php echo esc_html__( '客戶必須購買的商品數量。例如:買 2 送 1,這裡輸入 2', 'discount-tools' ); ?>
					</span>
				</div>

			<!-- Get Quantity (Y) -->
			<div class="dt-form-field">
				<label for="bxgy_get_quantity" class="dt-form-label">
					<?php echo esc_html__( 'Get Quantity (Y)', 'discount-tools' ); ?>
					<span class="required">*</span>
				</label>
					<input type="number" 
						   id="bxgy_get_quantity" 
						   name="bxgy_get_quantity" 
						   value="<?php echo esc_attr( $rule->get_bxgy_get_quantity() ?? 1 ); ?>" 
						   class="dt-form-input" 
						   min="1"
						   step="1">
					<span class="dt-form-description">
						<?php echo esc_html__( 'Number of items customer gets discounted. Example: 1 for "Get 1"', 'discount-tools' ); ?>
					</span>
				</div>

		<!-- Repeating Promotion -->
		<div class="dt-form-field">
			<label for="bxgy_repeating" class="dt-form-label">
				<?php echo esc_html__( 'Repeating Promotion', 'discount-tools' ); ?>
			</label>
			<label style="display: flex; align-items: center; gap: 8px;">
				<input type="checkbox" 
				   id="bxgy_repeating" 
				   name="bxgy_repeating" 
				   value="1"
				   <?php checked( $rule->get_meta_value( 'bxgy_repeating', 1 ), 1 ); ?>>
				<span><?php echo esc_html__( 'Apply promotion repeatedly', 'discount-tools' ); ?></span>
			</label>
			<span class="dt-form-description">
				<?php echo esc_html__( 'When checked: Buy 2 Get 1 Free, purchasing 6 items = 2 free items (repeats). When unchecked: only applies once (6 items = 1 free item).', 'discount-tools' ); ?>
			</span>
		</div>

		<!-- Exchange price and exchange products (for bxgy_any only) -->
		<div class="dt-form-field dt-bxgy-gift-products" style="display:none;">
			<label for="bxgy_exchange_price" class="dt-form-label">
				<?php echo esc_html__( 'Exchange Price (Y Item)', 'discount-tools' ); ?>
			</label>
			<input type="number"
			       id="bxgy_exchange_price"
			       name="bxgy_exchange_price"
			       value="<?php echo esc_attr( $rule->get_meta_value( 'bxgy_exchange_price', '0' ) ); ?>"
			       class="dt-form-input"
			       min="0"
			       step="0.01">
			<span class="dt-form-description">
				<?php echo esc_html__( 'Set extra amount customer pays for each Y item. Enter 0 for free.', 'discount-tools' ); ?>
			</span>
		</div>

		<div class="dt-form-field dt-bxgy-gift-products" style="display:none;">
			<label for="bxgy_gift_products_search" class="dt-form-label">
				<?php echo esc_html__( 'Exchange Product', 'discount-tools' ); ?>
				<span class="required">*</span>
			</label>
			
			<!-- Selected Gift Products (Tag Style Display) -->
			<div class="dt-gift-products-selected" style="margin-bottom: 10px; min-height: 40px;">
				<?php
				$dt_gift_products = $rule->get_meta_value( 'bxgy_gift_products', array() );
				if ( ! empty( $dt_gift_products ) && is_array( $dt_gift_products ) ) {
					foreach ( $dt_gift_products as $dt_product_id ) {
						$dt_product = wc_get_product( $dt_product_id );
						if ( $dt_product ) {
							$dt_sku = $dt_product->get_sku();
							$dt_display_sku = ! empty( $dt_sku ) ? $dt_sku : 'ID: ' . $dt_product_id;
							echo '<span class="dt-gift-tag" data-product-id="' . esc_attr( $dt_product_id ) . '">' . 
							     esc_html( $dt_product->get_name() ) . ' <span class="dt-gift-sku">(#' . esc_html( $dt_display_sku ) . ')</span>' .
							     '<button type="button" class="dt-gift-remove" title="' . esc_attr__( 'Remove', 'discount-tools' ) . '">×</button>' .
							     '<input type="hidden" name="bxgy_gift_products[]" value="' . esc_attr( $dt_product_id ) . '">' .
							     '</span>';
						}
					}
				}
				?>
			</div>
			
			<!-- Search Input with Custom Trigger -->
			<div class="dt-gift-search-wrapper">
				<input type="text" 
				       id="bxgy_gift_products_search_input"
				       class="dt-form-input" 
				       placeholder="<?php echo esc_attr__( 'Search and select exchange products...', 'discount-tools' ); ?>"
				       style="width: 100%;">
				<div class="dt-gift-search-results" style="display: none;"></div>
			</div>
			
			<!-- Hidden select for compatibility -->
			<select id="bxgy_gift_products_search" 
			        class="dt-gift-product-search" 
			        multiple="multiple"
			        style="display: none;">
			</select>
			
			<span class="dt-form-description">
				<?php echo esc_html__( 'Select exchange products. When conditions are met, these items are added to cart automatically at the configured exchange price.', 'discount-tools' ); ?>
			</span>
		</div>
		</div>			<!-- Example Display -->
			<div class="notice notice-success inline" style="margin-top: 20px;">
				<p>
					<strong><?php echo esc_html__( 'Examples:', 'discount-tools' ); ?></strong><br>
					<span class="dt-bxgy-example">
						<?php echo esc_html__( 'Buy 2 Get 1 Free (Repeating) = Buy Quantity: 2, Get Quantity: 1, Repeating: Checked', 'discount-tools' ); ?><br>
						<?php echo esc_html__( 'Buy 3 Get 2 Free (Repeating) = Buy Quantity: 3, Get Quantity: 2, Repeating: Checked', 'discount-tools' ); ?><br>
						<?php echo esc_html__( 'Buy 5 Get 1 Free (One-time) = Buy Quantity: 5, Get Quantity: 1, Repeating: Unchecked', 'discount-tools' ); ?>
					</span>
				</p>
				<p style="margin-top: 10px;">
					<strong><?php echo esc_html__( 'Note:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Y items are free when exchange price is 0. Use Conditions tab to specify which products qualify.', 'discount-tools' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Bundle (Set) Discount Configuration -->
	<div class="dt-section-card dt-bundle-config" style="display:none;">
		<div class="dt-section-card-header">
			<h3><?php echo esc_html__( 'Bundle Configuration', 'discount-tools' ); ?></h3>
			<span class="dt-section-description">
				<?php echo esc_html__( 'Set up bundle pricing - buy multiple items at a fixed bundle price', 'discount-tools' ); ?>
			</span>
		</div>
		<div class="dt-section-card-body">
			<?php
			$dt_bundle_free_shipping_enabled = $rule->get_meta_value( 'bundle_free_shipping', 0 );
			$dt_bundle_free_shipping_enabled = ( $dt_bundle_free_shipping_enabled === '1' || $dt_bundle_free_shipping_enabled === 1 || $dt_bundle_free_shipping_enabled === true );
			$dt_bundle_free_shipping_countries = $rule->get_meta_value( 'bundle_free_shipping_countries', array() );
			if ( ! is_array( $dt_bundle_free_shipping_countries ) ) {
				$dt_bundle_free_shipping_countries = array_filter( array_map( 'trim', explode( ',', strval( $dt_bundle_free_shipping_countries ) ) ) );
			}
			$dt_wc_countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_shipping_countries() : array();
			?>

			<!-- Bundle Quantity -->
			<div class="dt-form-field">
				<label for="bundle_quantity" class="dt-form-label">
					<?php echo esc_html__( 'Bundle Quantity', 'discount-tools' ); ?>
					<span class="required">*</span>
				</label>
				<input type="number" 
					   id="bundle_quantity" 
					   name="bundle_quantity" 
					   value="<?php echo esc_attr( $rule->get_meta_value( 'bundle_quantity', 2 ) ); ?>" 
					   class="dt-form-input" 
					   min="2"
					   step="1">
				<span class="dt-form-description">
					<?php echo esc_html__( 'Number of items in the bundle. Example: 2 for "2 items for $149"', 'discount-tools' ); ?>
				</span>
			</div>

			<!-- Bundle Price -->
			<div class="dt-form-field">
				<label for="bundle_price" class="dt-form-label">
					<?php echo esc_html__( 'Bundle Price', 'discount-tools' ); ?>
					<span class="required">*</span>
				</label>
				<input type="number" 
					   id="bundle_price" 
					   name="bundle_price" 
					   value="<?php echo esc_attr( $rule->get_meta_value( 'bundle_price', '' ) ); ?>" 
					   class="dt-form-input" 
					   min="0"
					   step="0.01">
				<span class="dt-form-description">
					<?php echo esc_html__( 'Fixed price for the bundle. Example: 149 for "$149 for 2 items"', 'discount-tools' ); ?>
				</span>
			</div>

			<!-- Repeating Bundle -->
			<div class="dt-form-field">
				<label for="bundle_repeating" class="dt-form-label">
					<?php echo esc_html__( 'Repeating Bundle', 'discount-tools' ); ?>
				</label>
				<label style="display: flex; align-items: center; gap: 8px;">
					<input type="checkbox" 
					   id="bundle_repeating" 
					   name="bundle_repeating" 
					   value="1"
					   <?php checked( $rule->get_meta_value( 'bundle_repeating', 1 ), 1 ); ?>>
					<span><?php echo esc_html__( 'Apply bundle pricing repeatedly', 'discount-tools' ); ?></span>
				</label>
				<span class="dt-form-description">
					<?php echo esc_html__( 'When checked: 4 items = 2 bundles. When unchecked: only first 2 items get bundle price, 3rd and 4th at regular price.', 'discount-tools' ); ?>
				</span>
			</div>

			<!-- Free Shipping for Bundle -->
			<div class="dt-form-field">
				<label for="bundle_free_shipping" class="dt-form-label">
					<?php echo esc_html__( 'Free Shipping for Bundle', 'discount-tools' ); ?>
				</label>
				<label style="display: flex; align-items: center; gap: 8px;">
					<input type="checkbox"
					   id="bundle_free_shipping"
					   name="bundle_free_shipping"
					   value="1"
					   <?php checked( $dt_bundle_free_shipping_enabled, true ); ?>>
					<span><?php echo esc_html__( 'Enable free shipping when bundle conditions are met', 'discount-tools' ); ?></span>
				</label>
				<span class="dt-form-description">
					<?php echo esc_html__( 'Optionally limit free shipping to selected countries/regions.', 'discount-tools' ); ?>
				</span>
			</div>

			<div class="dt-form-field dt-bundle-free-shipping-countries" style="display: <?php echo $dt_bundle_free_shipping_enabled ? 'block' : 'none'; ?>;">
				<label for="bundle_free_shipping_countries" class="dt-form-label">
					<?php echo esc_html__( 'Free Shipping Countries/Regions', 'discount-tools' ); ?>
				</label>
				<select id="bundle_free_shipping_countries"
				        name="bundle_free_shipping_countries[]"
				        class="dt-form-input"
				        multiple="multiple"
				        style="width: 100%; min-height: 140px;">
					<?php foreach ( $dt_wc_countries as $dt_country_code => $dt_country_name ) : ?>
						<option value="<?php echo esc_attr( $dt_country_code ); ?>" <?php selected( in_array( $dt_country_code, $dt_bundle_free_shipping_countries, true ), true ); ?>>
							<?php echo esc_html( $dt_country_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="dt-form-description">
					<?php echo esc_html__( 'Leave empty to apply free shipping to all countries/regions.', 'discount-tools' ); ?>
				</span>
			</div>

			<!-- Example Display -->
			<div class="notice notice-success inline" style="margin-top: 20px;">
				<p>
					<strong><?php echo esc_html__( 'Example:', 'discount-tools' ); ?></strong><br>
					<?php echo esc_html__( 'Regular Price: $115 per item', 'discount-tools' ); ?><br>
					<?php echo esc_html__( 'Bundle: 2 items for $149 (Repeating)', 'discount-tools' ); ?><br><br>
					<?php echo esc_html__( 'Result: 2 items = $149 (save $81), 3 items = $149 + $115 = $264, 4 items = $149 + $149 = $298', 'discount-tools' ); ?>
				</p>
				<p style="margin-top: 10px;">
					<strong><?php echo esc_html__( 'Note:', 'discount-tools' ); ?></strong>
					<?php echo esc_html__( 'Use Conditions tab to specify which products qualify for bundle pricing. Frontend will show strikethrough original price and highlighted bundle price.', 'discount-tools' ); ?>
				</p>
			</div>
		</div>
	</div>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Show/hide discount value field and advanced options based on type
	function updateDiscountFields() {
		var type = $('#discount_type').val();
		var $valueField = $('#discount_value').closest('.dt-form-field');
		var $helpItems = $('.dt-help-item');
		var $tieredCard = $('.dt-tiered-discounts');
		var $bxgyCard = $('.dt-bxgy-config');
		
		// Show/hide value field
		if (type === 'bxgy_same' || type === 'bxgy_any' || type === 'bundle') {
			$valueField.hide();
		} else {
			$valueField.show();
		}
		
		// Show relevant help text
		$helpItems.hide();
		$('.dt-help-item[data-type="' + type + '"]').show();
		
		// Update placeholder and min/max
		var $input = $('#discount_value');
		if (type === 'percentage') {
			$input.attr('max', '100');
			$input.attr('placeholder', '20');
		} else {
			$input.removeAttr('max');
			$input.attr('placeholder', '10.00');
		}
		
		// Show/hide advanced option cards
		$tieredCard.hide();
		$bxgyCard.hide();
		$('.dt-bundle-config').hide();
		$('.dt-free-shipping-config').hide();
		
		// Show BXGY configuration for BXGY types
		if (type === 'bxgy_same' || type === 'bxgy_any') {
			$bxgyCard.show();
			
			// Show gift products field only for bxgy_any
			if (type === 'bxgy_any') {
				$('.dt-bxgy-gift-products').show();
			} else {
				$('.dt-bxgy-gift-products').hide();
			}
		}
		
		// Show Bundle configuration for bundle type
		if (type === 'bundle') {
			$('.dt-bundle-config').show();
		}

		$('.dt-bundle-free-shipping-countries').toggle(
			$('#bundle_free_shipping').is(':checked')
		);
		
		// Tiered discounts could be available for percentage/fixed_amount
		// (Currently disabled - will be implemented in future update)
		/*
		if (type === 'percentage' || type === 'fixed_amount') {
			$tieredCard.show();
		}
		*/
	}
	
	// Initialize on page load
	$('#discount_type').on('change', updateDiscountFields);
	updateDiscountFields();
	
	// Initialize Select2 for product search
	if (typeof $.fn.select2 !== 'undefined') {
		$('.dt-product-search').select2({
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				delay: 250,
				type: 'GET',
				data: function(params) {
					return {
						action: 'dt_search_products',
						search: params.term,
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'dt_search_products' ) ); ?>'
					};
				},
				processResults: function(data) {
					// Handle nested data structure: {success: true, data: {data: [...]}}
					if (data.success && data.data && data.data.data) {
						return {
							results: data.data.data
						};
					}
					// Fallback for simpler structure: {success: true, data: [...]}
					if (data.success && data.data && Array.isArray(data.data)) {
						return {
							results: data.data
						};
					}
					return {
						results: []
					};
				},
				cache: true
			},
			minimumInputLength: 2,
			placeholder: '<?php echo esc_js( __( 'Type to search products...', 'discount-tools' ) ); ?>',
			allowClear: true,
			width: '100%'
		});
	}
	
	// Add conflict validation for Stacking + Repeating on BXGY discounts
	$('#enable_stacking, #bxgy_repeating').on('change', function() {
		var discountType = $('#discount_type').val();
		var stackingEnabled = $('#enable_stacking').is(':checked');
		var repeatingEnabled = $('#bxgy_repeating').is(':checked');
		
		// Check if both are enabled for BXGY discounts
		if ((discountType === 'bxgy_same' || discountType === 'bxgy_any') && 
			stackingEnabled && repeatingEnabled) {
			alert('<?php echo esc_js( __( 'Warning: Stacking and Repeating cannot be used together for BXGY discounts. Repeating will be disabled.', 'discount-tools' ) ); ?>');
			$('#bxgy_repeating').prop('checked', false);
		}
	});
	
	// Add validation
	$(document).on('change', '#bundle_free_shipping', function() {
		$('.dt-bundle-free-shipping-countries').toggle($(this).is(':checked'));
	});

	$('form.dt-rule-editor-form').on('submit', function(e) {
		var type = $('#discount_type').val();
		var value = parseFloat($('#discount_value').val());
		
		// Validate percentage is between 0 and 100
		if (type === 'percentage' && (value < 0 || value > 100)) {
			e.preventDefault();
			alert('<?php echo esc_js( __( 'Percentage discount must be between 0 and 100.', 'discount-tools' ) ); ?>');
			$('#discount_value').focus();
			return false;
		}
		
		// Validate non-negative values
		if (value < 0) {
			e.preventDefault();
			alert('<?php echo esc_js( __( 'Discount value must be non-negative.', 'discount-tools' ) ); ?>');
			$('#discount_value').focus();
			return false;
		}
		
		// Validate BXGY Stacking + Repeating conflict on form submit
		if ((type === 'bxgy_same' || type === 'bxgy_any') && 
			$('#enable_stacking').is(':checked') && 
			$('#bxgy_repeating').is(':checked')) {
			e.preventDefault();
			alert('<?php echo esc_js( __( 'Error: Stacking and Repeating cannot be used together for BXGY discounts.', 'discount-tools' ) ); ?>');
			return false;
		}
		
		return true;
	});
});
</script>
