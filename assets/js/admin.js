/**
 * Admin JavaScript for Discount Tools
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/assets/js
 */

(function($) {
	'use strict';

	/**
	 * Discount Tools Admin Class
	 */
	window.DiscountToolsAdmin = {

		/**
		 * Initialize
		 */
		init: function() {
			console.log('DiscountToolsAdmin initializing...');
			this.bindEvents();
			this.initGiftProductSelector();
			this.handleResultClick();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Quick toggle rule status (AJAX)
			$(document).on('click', '.dt-toggle-status', this.toggleRuleStatus);

			// Confirm bulk delete actions
			$('#doaction, #doaction2').on('click', this.confirmBulkDelete);

			// Reset usage count
			$(document).on('click', '#dt-reset-usage-count', this.resetUsageCount);
			console.log('Event handlers bound successfully');
		},

		/**
		 * Toggle rule status via AJAX
		 */
		toggleRuleStatus: function(e) {
			e.preventDefault();

			var $link = $(this);
			var ruleId = $link.data('rule-id');
			var action = $link.data('action');
			var nonce = $link.data('nonce');

			// Show loading state
			var originalText = $link.text();
			$link.html('<span class="dt-ajax-loading"></span>');
			$link.css('pointer-events', 'none');

			// AJAX request
			$.ajax({
				url: discountToolsAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dt_toggle_rule_status',
					rule_id: ruleId,
					toggle_action: action,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						// Update link
						var newAction = action === 'activate' ? 'deactivate' : 'activate';
						var newText = action === 'activate' ? 
							discountToolsAdmin.i18n.deactivate : 
							discountToolsAdmin.i18n.activate;

						$link.data('action', newAction);
						$link.text(newText);

						// Update status badge
						var $row = $link.closest('tr');
						var $statusBadge = $row.find('.dt-status-badge');
						
						if (action === 'activate') {
							$statusBadge.removeClass('dt-status-inactive')
								.addClass('dt-status-active')
								.html('<span class="dashicons dashicons-yes-alt"></span> ' + discountToolsAdmin.i18n.active);
						} else {
							$statusBadge.removeClass('dt-status-active')
								.addClass('dt-status-inactive')
								.html('<span class="dashicons dashicons-dismiss"></span> ' + discountToolsAdmin.i18n.inactive);
						}

						// Show success message
						DiscountToolsAdmin.showNotice(response.data.message, 'success');
					} else {
						$link.text(originalText);
						DiscountToolsAdmin.showNotice(response.data.message || discountToolsAdmin.i18n.error, 'error');
					}
				},
				error: function() {
					$link.text(originalText);
					DiscountToolsAdmin.showNotice(discountToolsAdmin.i18n.error, 'error');
				},
				complete: function() {
					$link.css('pointer-events', '');
				}
			});
		},

		/**
		 * Reset usage count via AJAX
		 */
		resetUsageCount: function(e) {
			e.preventDefault();

			var $button = $(this);
			var ruleId = $button.data('rule-id');
			var $spinner = $button.next('.spinner');

			// Confirm action
			if (!confirm('確定要將使用次數歸零嗎？此操作無法恢復。')) {
				return;
			}

			// Show loading state
			$button.prop('disabled', true);
			$spinner.addClass('is-active');

			// AJAX request
			$.ajax({
				url: discountToolsAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dt_reset_usage_count',
					rule_id: ruleId,
					nonce: discountToolsAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						// Update usage count display
						var $usageCount = $('.dt-usage-count');
						if ($usageCount.length) {
							$usageCount.text('0');
						}

						// Update progress bar if exists
						var $barFill = $('.dt-usage-bar-fill');
						if ($barFill.length) {
							$barFill.css('width', '0%');
						}

						// Update percentage if exists
						var $percentage = $('.dt-usage-percentage');
						if ($percentage.length) {
							$percentage.text('0.0%');
						}

						// Show success message
						DiscountToolsAdmin.showNotice(response.data.message, 'success');
					} else {
						DiscountToolsAdmin.showNotice(response.data.message || discountToolsAdmin.i18n.error, 'error');
					}
				},
				error: function() {
					DiscountToolsAdmin.showNotice(discountToolsAdmin.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		},

		/**
		 * Confirm bulk delete action
		 */
		confirmBulkDelete: function(e) {
			var $form = $(this).closest('form');
			var action = $form.find('select[name="action"]').val();
			
			if (action === '-1') {
				action = $form.find('select[name="action2"]').val();
			}

			if (action === 'delete') {
				var $checked = $form.find('input[name="rule_ids[]"]:checked');
				
				if ($checked.length === 0) {
					return false;
				}

				var count = $checked.length;
				var message = count === 1 ? 
					discountToolsAdmin.i18n.confirmDeleteSingle : 
					discountToolsAdmin.i18n.confirmDeleteMultiple.replace('%d', count);

				if (!confirm(message)) {
					e.preventDefault();
					return false;
				}
			}
		},

		/**
		 * Show admin notice
		 */
		showNotice: function(message, type) {
			type = type || 'info';

			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			
			$('.wrap h1').after($notice);

			// Auto dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeTo(500, 0, function() {
					$notice.slideUp(500, function() {
						$notice.remove();
					});
				});
			}, 5000);

			// Manual dismiss
			$notice.on('click', '.notice-dismiss', function() {
				$notice.fadeTo(200, 0, function() {
					$notice.slideUp(200, function() {
						$notice.remove();
					});
				});
			});
		},

		/**
		 * Initialize Gift Product Selector
		 */
		initGiftProductSelector: function() {
			var $input = $('#bxgy_gift_products_search_input');
			var $results = $('.dt-gift-search-results');
			var searchTimeout = null;
			
			if ($input.length === 0) {
				return;
			}

			// Handle input search
			$input.on('input', function() {
				clearTimeout(searchTimeout);
				var term = $(this).val().trim();
				
				if (term.length < 2) {
					$results.hide().empty();
					return;
				}
				
				searchTimeout = setTimeout(function() {
					DiscountToolsAdmin.searchProducts(term, $results);
				}, 250);
			});
			
			// Handle click outside to close
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.dt-gift-search-wrapper').length) {
					$results.hide();
				}
			});

			// Handle tag removal
			$(document).on('click', '.dt-gift-remove', function(e) {
				e.preventDefault();
				$(this).closest('.dt-gift-tag').fadeOut(200, function() {
					$(this).remove();
				});
			});
		},
		
		/**
		 * Search Products via AJAX
		 */
		searchProducts: function(term, $results) {
			var searchNonce = window.dtConditionBuilder && dtConditionBuilder.nonce ? dtConditionBuilder.nonce : discountToolsAdmin.nonce;
			
			$.ajax({
				url: ajaxurl,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'dt_search_products',
					term: term,
					security: searchNonce
				},
				success: function(response) {
					if (!response.success || !response.data || !response.data.data || response.data.data.length === 0) {
						$results.html('<div class="dt-search-no-results">No results found</div>').show();
						return;
					}
					
					var html = '';
					response.data.data.forEach(function(product) {
						// Extract SKU from text like "Product Name (SKU: ABC123) [ID: 123]"
						var sku = '';
						var name = product.text;
						var skuMatch = product.text.match(/\(SKU:\s*([^\)]+)\)/);
						if (skuMatch) {
							sku = skuMatch[1];
							name = product.text.split(' (SKU:')[0];
						} else {
							sku = 'ID: ' + product.id;
							name = product.text.replace(/\s*\[ID:\s*\d+\]/, '');
						}
						
						html += '<div class="dt-search-result-item" data-id="' + product.id + '" data-name="' + name + '" data-sku="' + sku + '">' +
								name + ' <span style="opacity:0.7;">(#' + sku + ')</span>' +
								'</div>';
					});
					
					$results.html(html).show();
				},
				error: function() {
					$results.html('<div class="dt-search-no-results">Search failed</div>').show();
				}
			});
		},
		
		/**
		 * Handle Result Click
		 */
		handleResultClick: function() {
			$(document).on('click', '.dt-search-result-item', function() {
				var productId = $(this).data('id');
				var productName = $(this).data('name');
				var productSku = $(this).data('sku');
				
				DiscountToolsAdmin.addGiftTag(productId, productName, productSku);
				
				// Clear search
				$('#bxgy_gift_products_search_input').val('');
				$('.dt-gift-search-results').hide().empty();
			});
		},

		/**
		 * Add Gift Tag
		 */
		addGiftTag: function(productId, productName, sku) {
			var $container = $('.dt-gift-products-selected');
			
			// Check if already exists
			if ($container.find('[data-product-id="' + productId + '"]').length > 0) {
				return;
			}

			var displaySku = sku || 'ID: ' + productId;
			var $tag = $('<span class="dt-gift-tag" data-product-id="' + productId + '">' +
				productName + ' <span class="dt-gift-sku">(#' + displaySku + ')</span>' +
				'<button type="button" class="dt-gift-remove" title="Remove">×</button>' +
				'<input type="hidden" name="bxgy_gift_products[]" value="' + productId + '">' +
				'</span>');
			
			$container.append($tag);
			$tag.hide().fadeIn(200);
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		console.log('Document ready, initializing DiscountToolsAdmin...');
		window.DiscountToolsAdmin.init();
	});

})(jQuery);
