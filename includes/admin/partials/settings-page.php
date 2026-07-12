<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Settings Page Template
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings
$dt_settings = get_option( \Discount_Tools\Admin\Settings::OPTION_NAME, array() );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" id="dt-settings-form">
		<?php
		settings_fields( 'discount_tools_settings' );
		do_settings_sections( 'discount-tools-settings' );
		submit_button();
		?>
	</form>

	<div class="dt-settings-actions" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
		<h2><?php esc_html_e( 'Import / Export Settings', 'discount-tools' ); ?></h2>
		
		<div style="margin-bottom: 20px;">
			<h3><?php esc_html_e( 'Export Settings', 'discount-tools' ); ?></h3>
			<p><?php esc_html_e( 'Export your current settings as a JSON file for backup or migration.', 'discount-tools' ); ?></p>
			<button type="button" class="button button-secondary" id="dt-export-settings">
				<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
				<?php esc_html_e( 'Export Settings', 'discount-tools' ); ?>
			</button>
		</div>

		<div style="margin-bottom: 20px;">
			<h3><?php esc_html_e( 'Import Settings', 'discount-tools' ); ?></h3>
			<p><?php esc_html_e( 'Import settings from a previously exported JSON file.', 'discount-tools' ); ?></p>
			<input type="file" id="dt-import-file" accept=".json" style="display: none;">
			<button type="button" class="button button-secondary" id="dt-import-settings">
				<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
				<?php esc_html_e( 'Import Settings', 'discount-tools' ); ?>
			</button>
		</div>

		<div style="border-top: 1px solid #ddd; padding-top: 20px;">
			<h3><?php esc_html_e( 'Reset Settings', 'discount-tools' ); ?></h3>
			<p class="description" style="color: #d63638;">
				<?php esc_html_e( 'Warning: This will reset all settings to their default values. This action cannot be undone.', 'discount-tools' ); ?>
			</p>
			<button type="button" class="button button-secondary" id="dt-reset-settings" style="color: #d63638; border-color: #d63638;">
				<span class="dashicons dashicons-undo" style="margin-top: 3px;"></span>
				<?php esc_html_e( 'Reset to Defaults', 'discount-tools' ); ?>
			</button>
		</div>
	</div>
</div>

<style>
.dt-settings-actions h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 2px solid #2271b1;
}

.dt-settings-actions h3 {
	font-size: 14px;
	margin-bottom: 5px;
}

.dt-settings-actions .button .dashicons {
	float: left;
	margin-right: 5px;
}

#dt-import-settings.uploading,
#dt-export-settings.processing,
#dt-reset-settings.processing {
	opacity: 0.6;
	pointer-events: none;
}
</style>

<script>
jQuery(document).ready(function($) {
	const nonce = '<?php echo esc_attr( wp_create_nonce( 'dt-admin-nonce' ) ); ?>';

	// Export Settings
	$('#dt-export-settings').on('click', function() {
		const button = $(this);
		button.addClass('processing').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'dt_export_settings',
				nonce: nonce
			},
			success: function(response) {
				console.log('Export response:', response);
				
				if (response && response.success && response.data) {
					// Create download link
					const exportData = response.data.data || response.data;
					const filename = response.data.filename || 'discount-tools-export.json';
					
					try {
						const dataStr = JSON.stringify(exportData, null, 2);
						const dataBlob = new Blob([dataStr], {type: 'application/json'});
						const url = window.URL.createObjectURL(dataBlob);
						const link = document.createElement('a');
						link.href = url;
						link.download = filename;
						document.body.appendChild(link);
						link.click();
						document.body.removeChild(link);
						window.URL.revokeObjectURL(url);

						alert('<?php esc_html_e( 'Settings exported successfully!', 'discount-tools' ); ?>');
					} catch (e) {
						console.error('Export blob error:', e);
						alert('<?php esc_html_e( 'Export failed: ', 'discount-tools' ); ?>' + e.message);
					}
				} else {
					console.error('Export failed response:', response);
					const errorMsg = response && response.data && response.data.message 
						? response.data.message 
						: '<?php esc_html_e( 'Export failed.', 'discount-tools' ); ?>';
					alert(errorMsg);
				}
			},
			error: function(xhr, status, error) {
				console.error('Export AJAX error:', {xhr, status, error});
				console.error('Response text:', xhr.responseText);
				
				let errorMsg = '<?php esc_html_e( 'Export failed. Please try again.', 'discount-tools' ); ?>';
				
				try {
					const response = JSON.parse(xhr.responseText);
					if (response && response.data && response.data.message) {
						errorMsg = response.data.message;
					}
				} catch (e) {
					// Response is not JSON
					if (xhr.responseText) {
						errorMsg += '\n\n' + xhr.responseText.substring(0, 200);
					}
				}
				
				alert(errorMsg);
			},
			complete: function() {
				button.removeClass('processing').prop('disabled', false);
			}
		});
	});

	// Import Settings
	$('#dt-import-settings').on('click', function() {
		$('#dt-import-file').click();
	});

	$('#dt-import-file').on('change', function() {
		const file = this.files[0];
		if (!file) return;

		const button = $('#dt-import-settings');
		button.addClass('uploading');

		const reader = new FileReader();
		reader.onload = function(e) {
			try {
				const data = JSON.parse(e.target.result);
				
				if (!data.settings) {
					throw new Error('Invalid settings file');
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'dt_import_settings',
						nonce: nonce,
						settings_data: JSON.stringify(data)
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							location.reload();
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Import failed.', 'discount-tools' ); ?>');
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Import failed. Please try again.', 'discount-tools' ); ?>');
					},
					complete: function() {
						button.removeClass('uploading');
						$('#dt-import-file').val('');
					}
				});
			} catch (error) {
				alert('<?php esc_html_e( 'Invalid JSON file.', 'discount-tools' ); ?>');
				button.removeClass('uploading');
				$('#dt-import-file').val('');
			}
		};
		reader.readAsText(file);
	});

	// Reset Settings
	$('#dt-reset-settings').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to reset all settings to defaults? This cannot be undone.', 'discount-tools' ); ?>')) {
			return;
		}

		const button = $(this);
		button.addClass('processing');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'dt_reset_settings',
				nonce: nonce
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Reset failed.', 'discount-tools' ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'Reset failed. Please try again.', 'discount-tools' ); ?>');
			},
			complete: function() {
				button.removeClass('processing');
			}
		});
	});
});
</script>
