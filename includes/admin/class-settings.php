<?php
/**
 * Settings Page Controller
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/admin
 */

namespace Discount_Tools\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class
 */
class Settings {

	/**
	 * Singleton instance.
	 *
	 * @var Settings
	 */
	private static $instance = null;

	/**
	 * Option name
	 *
	 * @var string
	 */
	const OPTION_NAME = 'discount_tools_settings';

	/**
	 * Get singleton instance.
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class (private to enforce singleton pattern)
	 */
	private function __construct() {
		// Don't register admin_menu hook - settings page is now a tab in the main menu
		// Only register settings and AJAX handlers
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_dt_export_settings', array( $this, 'export_settings' ) );
		add_action( 'wp_ajax_dt_import_settings', array( $this, 'import_settings' ) );
		add_action( 'wp_ajax_dt_reset_settings', array( $this, 'reset_settings' ) );
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page() {
		static $added = false;
		if ( $added ) {
			return;
		}
		
		add_submenu_page(
			'discount-tools',
			__( 'Settings', 'discount-tools' ),
			__( 'Settings', 'discount-tools' ),
			'manage_options',
			'discount-tools-settings',
			array( $this, 'render_settings_page' )
		);
		
		$added = true;
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		
		register_setting(
			'discount_tools_settings',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// General Settings Section
		add_settings_section(
			'dt_general_section',
			__( 'General Settings', 'discount-tools' ),
			array( $this, 'render_general_section' ),
			'discount-tools-settings'
		);

		add_settings_field(
			'enable_plugin',
			__( 'Enable Plugin', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_general_section',
			array(
				'label_for' => 'enable_plugin',
				'description' => __( 'Enable or disable the discount functionality globally.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'discount_priority',
			__( 'Discount Priority', 'discount-tools' ),
			array( $this, 'render_select_field' ),
			'discount-tools-settings',
			'dt_general_section',
			array(
				'label_for' => 'discount_priority',
				'options' => array(
					'first' => __( 'Apply First (Before Other Discounts)', 'discount-tools' ),
					'last'  => __( 'Apply Last (After Other Discounts)', 'discount-tools' ),
				),
				'description' => __( 'Control when this plugin applies discounts relative to other discount plugins.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'stack_discounts',
			__( 'Stack Multiple Discounts', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_general_section',
			array(
				'label_for' => 'stack_discounts',
				'description' => __( 'Allow multiple discount rules to stack on the same product.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'rule_application_strategy',
			__( 'Rule Application Strategy', 'discount-tools' ),
			array( $this, 'render_select_field' ),
			'discount-tools-settings',
			'dt_general_section',
			array(
				'label_for' => 'rule_application_strategy',
				'options' => array(
					'priority'  => __( 'Apply by Priority Order', 'discount-tools' ),
					'highest'   => __( 'Apply Highest Discount Only', 'discount-tools' ),
					'lowest'    => __( 'Apply Lowest Discount Only', 'discount-tools' ),
					'stack_all' => __( 'Stack All Applicable Discounts', 'discount-tools' ),
				),
				'description' => __( 'When multiple rules match, determine which rule(s) to apply. Priority: use rule priority field. Highest: calculate and apply the rule with maximum discount. Lowest: apply the rule with minimum discount. Stack All: apply all matching rules cumulatively.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'coupon_interaction_mode',
			__( 'Coupon & Discount Interaction', 'discount-tools' ),
			array( $this, 'render_select_field' ),
			'discount-tools-settings',
			'dt_general_section',
			array(
				'label_for' => 'coupon_interaction_mode',
				'options' => array(
					'both_active'          => __( 'Both Active', 'discount-tools' ),
					'coupons_only'         => __( 'Coupons Only, Disable Discount Tools', 'discount-tools' ),
					'discount_tools_only'  => __( 'Discount Tools Only, Disable Coupons', 'discount-tools' ),
				),
				'description' => __( 'Control how WooCommerce coupons and Discount Tools rules interact. Both Active: allow both to apply simultaneously. Coupons Only: disable plugin discounts when any coupon is used. Discount Tools Only: prevent customers from using coupons (plugin rules take priority).', 'discount-tools' ),
			)
		);

		// Display Settings Section
		add_settings_section(
			'dt_display_section',
			__( 'Display Settings', 'discount-tools' ),
			array( $this, 'render_display_section' ),
			'discount-tools-settings'
		);

		add_settings_field(
			'show_discount_table_on_product',
			__( 'Product Page', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'show_discount_table_on_product',
				'description' => __( 'Show discount table on product pages', 'discount-tools' ),
			)
		);

		add_settings_field(
			'show_discount_in_cart',
			__( 'Cart Page', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'show_discount_in_cart',
				'description' => __( 'Show discount details in cart', 'discount-tools' ),
			)
		);

		add_settings_field(
			'show_discount_on_checkout',
			__( 'Checkout Page', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'show_discount_on_checkout',
				'description' => __( 'Show total savings on checkout', 'discount-tools' ),
			)
		);

		add_settings_field(
			'product_display_position',
			__( 'Display Position', 'discount-tools' ),
			array( $this, 'render_select_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'product_display_position',
				'options' => array(
					'before_add_to_cart' => __( 'Before Add to Cart Button', 'discount-tools' ),
					'after_add_to_cart'  => __( 'After Add to Cart Button', 'discount-tools' ),
					'before_product_meta' => __( 'Before Product Meta', 'discount-tools' ),
					'after_product_meta' => __( 'After Product Meta', 'discount-tools' ),
				),
				'description' => __( 'Where to display discount information on product pages.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'product_display_style',
			__( 'Display Style', 'discount-tools' ),
			array( $this, 'render_select_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'product_display_style',
				'options' => array(
					'badge' => __( 'Badge', 'discount-tools' ),
					'text'  => __( 'Text', 'discount-tools' ),
				),
				'description' => __( 'How to display the discount information to customers.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'badge_background_color',
			__( 'Badge Background Color', 'discount-tools' ),
			array( $this, 'render_color_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'badge_background_color',
				'description' => __( 'Background color for discount badges. Use gradient format like: linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'discount-tools' ),
			)
		);

		add_settings_field(
			'message_text_color',
			__( 'Message Text Color', 'discount-tools' ),
			array( $this, 'render_color_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'message_text_color',
				'description' => __( 'Color for custom discount messages.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'message_font_size',
			__( 'Message Font Size', 'discount-tools' ),
			array( $this, 'render_number_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'message_font_size',
				'description' => __( 'Font size for custom messages (in pixels).', 'discount-tools' ),
				'min' => 10,
				'max' => 48,
				'default' => 16,
			)
		);

		add_settings_field(
			'show_original_price',
			__( 'Show Original Price', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_display_section',
			array(
				'label_for' => 'show_original_price',
				'description' => __( 'Show the original price with strikethrough when discount is applied.', 'discount-tools' ),
			)
		);

		// Advanced Settings Section
		add_settings_section(
			'dt_advanced_section',
			__( 'Advanced Settings', 'discount-tools' ),
			array( $this, 'render_advanced_section' ),
			'discount-tools-settings'
		);

		add_settings_field(
			'cache_duration',
			__( 'Cache Duration', 'discount-tools' ),
			array( $this, 'render_number_field' ),
			'discount-tools-settings',
			'dt_advanced_section',
			array(
				'label_for' => 'cache_duration',
				'min' => 0,
				'max' => 86400,
				'description' => __( 'Cache discount calculations for this many seconds (0 to disable caching).', 'discount-tools' ),
			)
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug Mode', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_advanced_section',
			array(
				'label_for' => 'debug_mode',
				'description' => __( 'Enable debug logging for troubleshooting. Logs are written to WP Debug Log.', 'discount-tools' ),
			)
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Delete Data on Uninstall', 'discount-tools' ),
			array( $this, 'render_checkbox_field' ),
			'discount-tools-settings',
			'dt_advanced_section',
			array(
				'label_for' => 'delete_data_on_uninstall',
				'description' => __( 'Remove all plugin data when the plugin is uninstalled.', 'discount-tools' ),
			)
		);
		
		$registered = true;
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include plugin_dir_path( __FILE__ ) . 'partials/settings-page.php';
	}

	/**
	 * Render general section description
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general plugin behavior and discount application rules.', 'discount-tools' ) . '</p>';
	}

	/**
	 * Render display section description
	 */
	public function render_display_section() {
		echo '<p>' . esc_html__( 'Configure how discounts are displayed to customers across your store.', 'discount-tools' ) . '</p>';
	}

	/**
	 * Render advanced section description
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced options for performance tuning and troubleshooting.', 'discount-tools' ) . '</p>';
	}

	/**
	 * Render checkbox field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : false;
		?>
		<label>
			<input type="checkbox" 
				   name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['label_for'] . ']' ); ?>" 
				   value="1" 
				   <?php checked( $value, true ); ?>>
			<?php echo esc_html( $args['description'] ); ?>
		</label>
		<?php
	}

	/**
	 * Render select field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_select_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['label_for'] . ']' ); ?>" 
				id="<?php echo esc_attr( $args['label_for'] ); ?>">
			<?php foreach ( $args['options'] as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render text field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		?>
		<input type="text" 
			   name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['label_for'] . ']' ); ?>" 
			   id="<?php echo esc_attr( $args['label_for'] ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text">
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render number field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		?>
		<input type="number" 
			   name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['label_for'] . ']' ); ?>" 
			   id="<?php echo esc_attr( $args['label_for'] ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   min="<?php echo esc_attr( $args['min'] ); ?>"
			   max="<?php echo esc_attr( $args['max'] ); ?>">
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render color field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_color_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		?>
		<input type="text" 
			   name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['label_for'] . ']' ); ?>" 
			   id="<?php echo esc_attr( $args['label_for'] ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text"
			   placeholder="linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
		<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render rule application strategy dropdown
	 *
	 * @since 1.1.0
	 */
	public function render_strategy_dropdown() {
		$options = get_option( self::OPTION_NAME, array() );
		$current = isset( $options['rule_application_strategy'] ) ? $options['rule_application_strategy'] : 'priority';
		
		$strategy_options = array(
			'highest'    => __( 'Apply Highest Discount Only', 'discount-tools' ),
			'lowest'     => __( 'Apply Lowest Discount Only', 'discount-tools' ),
			'priority'   => __( 'Apply by Priority Order', 'discount-tools' ),
			'stack_all'  => __( 'Stack All Applicable Discounts', 'discount-tools' ),
		);
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME . '[rule_application_strategy]' ); ?>" 
				id="rule_application_strategy">
			<?php foreach ( $strategy_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Controls how multiple applicable rules interact. "Priority" uses the priority number set in each rule. "Highest/Lowest" calculates and applies the best discount. "Stack All" combines all discounts.', 'discount-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render coupon interaction mode dropdown
	 *
	 * @since 1.1.0
	 */
	public function render_coupon_mode_dropdown() {
		$options = get_option( self::OPTION_NAME, array() );
		$current = isset( $options['coupon_interaction_mode'] ) ? $options['coupon_interaction_mode'] : 'both_active';
		
		$mode_options = array(
			'both_active'         => __( 'Both Active', 'discount-tools' ),
			'coupons_only'        => __( 'Coupons Only, Disable Discount Tools', 'discount-tools' ),
			'discount_tools_only' => __( 'Discount Tools Only, Disable Coupons', 'discount-tools' ),
		);
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME . '[coupon_interaction_mode]' ); ?>" 
				id="coupon_interaction_mode">
			<?php foreach ( $mode_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Determines how WooCommerce coupons interact with discount rules. "Both Active" allows both to work together. "Coupons Only" disables this plugin when a coupon is applied. "Discount Tools Only" prevents WooCommerce coupons from being used.', 'discount-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Checkboxes
		$checkboxes = array(
			'enable_plugin',
			'stack_discounts',
			'show_discount_table_on_product',
			'show_discount_in_cart',
			'show_discount_on_checkout',
			'show_original_price',
			'debug_mode',
			'delete_data_on_uninstall',
		);

		foreach ( $checkboxes as $checkbox ) {
			$sanitized[ $checkbox ] = isset( $input[ $checkbox ] ) && $input[ $checkbox ];
		}

		// Select fields
		if ( isset( $input['discount_priority'] ) && in_array( $input['discount_priority'], array( 'first', 'last' ), true ) ) {
			$sanitized['discount_priority'] = $input['discount_priority'];
		}

		if ( isset( $input['rule_application_strategy'] ) && in_array( $input['rule_application_strategy'], array( 'priority', 'highest', 'lowest', 'stack_all' ), true ) ) {
			$sanitized['rule_application_strategy'] = $input['rule_application_strategy'];
		}

		if ( isset( $input['coupon_interaction_mode'] ) && in_array( $input['coupon_interaction_mode'], array( 'both_active', 'coupons_only', 'discount_tools_only' ), true ) ) {
			$sanitized['coupon_interaction_mode'] = $input['coupon_interaction_mode'];
		}

		if ( isset( $input['product_display_position'] ) && in_array( $input['product_display_position'], array( 'before_add_to_cart', 'after_add_to_cart', 'before_product_meta', 'after_product_meta' ), true ) ) {
			$sanitized['product_display_position'] = $input['product_display_position'];
		}

		if ( isset( $input['product_display_style'] ) && in_array( $input['product_display_style'], array( 'badge', 'text' ), true ) ) {
			$sanitized['product_display_style'] = $input['product_display_style'];
		}

		// Text fields
		if ( isset( $input['badge_background_color'] ) ) {
			$sanitized['badge_background_color'] = sanitize_text_field( $input['badge_background_color'] );
		}

		if ( isset( $input['message_text_color'] ) ) {
			$sanitized['message_text_color'] = sanitize_hex_color( $input['message_text_color'] );
		}

		if ( isset( $input['message_font_size'] ) ) {
			$sanitized['message_font_size'] = absint( $input['message_font_size'] );
			if ( $sanitized['message_font_size'] < 10 || $sanitized['message_font_size'] > 48 ) {
				$sanitized['message_font_size'] = 16;
			}
		}

		// Number fields
		if ( isset( $input['cache_duration'] ) ) {
			$sanitized['cache_duration'] = absint( $input['cache_duration'] );
		}

		return $sanitized;
	}

	/**
	 * Export settings as JSON
	 */
	public function export_settings() {
		// Log for debugging
		
		try {
			// Verify nonce
			$nonce_check = check_ajax_referer( 'dt-admin-nonce', 'nonce', false );
			if ( ! $nonce_check ) {
					wp_send_json_error( array( 
					'message' => __( 'Security check failed.', 'discount-tools' ),
					'debug' => 'nonce_failed'
				) );
				return;
			}

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Permission denied.', 'discount-tools' ),
					'debug' => 'permission_denied'
				) );
				return;
			}


			// Get settings
			$settings = get_option( self::OPTION_NAME, array() );
			
			// Export rules
			$rules_data = array();
			if ( class_exists( 'Discount_Tools_Rule_Repository' ) ) {
				try {
					$repository = new \Discount_Tools_Rule_Repository();
					$all_rules = $repository->find_all();
					
					
					foreach ( $all_rules as $rule ) {
						$conditions_data = array();
						foreach ( $rule->get_conditions() as $condition ) {
							$conditions_data[] = array(
								'condition_type' => $condition->get_condition_type(),
								'operator' => $condition->get_operator(),
								'value' => $condition->get_value(),
							);
						}
						
						$rules_data[] = array(
							'name'                  => $rule->get_name(),
							'description'           => $rule->get_description(),
							'rule_type'             => $rule->get_rule_type(),
							'discount_type'         => $rule->get_discount_type(),
							'discount_subtype'      => $rule->get_discount_subtype(),
							'discount_value'        => $rule->get_discount_value(),
							'priority'              => $rule->get_priority(),
							'status'                => $rule->get_status(),
							'start_date'            => $rule->get_start_date(),
							'end_date'              => $rule->get_end_date(),
							'usage_limit'           => $rule->get_usage_limit(),
							'usage_limit_per_user'  => $rule->get_usage_limit_per_user(),
							'apply_mode'            => $rule->get_apply_mode(),
							'bxgy_buy_quantity'     => $rule->get_bxgy_buy_quantity(),
							'bxgy_get_quantity'     => $rule->get_bxgy_get_quantity(),
							'bxgy_get_discount'     => $rule->get_bxgy_get_discount(),
							'bxgy_get_type'         => $rule->get_bxgy_get_type(),
							'conditions'            => $conditions_data,
							'metadata'              => $rule->get_meta(),
						);
					}
				} catch ( Exception $e ) {
					// Continue with empty rules array
				}
			}
			
			$export_data = array(
				'version' => DISCOUNT_TOOLS_VERSION,
				'exported' => current_time( 'mysql' ),
				'settings' => $settings,
				'rules' => $rules_data,
			);


			wp_send_json_success( array(
				'data' => $export_data,
				'filename' => 'discount-tools-export-' . gmdate( 'Y-m-d' ) . '.json',
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 
				'message' => __( 'Export failed: ', 'discount-tools' ) . $e->getMessage(),
				'error' => $e->getMessage(),
				'debug' => 'exception'
			) );
		}
	}

	/**
	 * Import settings from JSON
	 */
	public function import_settings() {
		check_ajax_referer( 'dt-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		if ( empty( $_POST['settings_data'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No settings data provided.', 'discount-tools' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		$import_data_raw = isset( $_POST['settings_data'] ) ? wp_unslash( $_POST['settings_data'] ) : '';
		$import_data = json_decode( $import_data_raw, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON data.', 'discount-tools' ) ) );
		}

		if ( ! isset( $import_data['settings'] ) || ! is_array( $import_data['settings'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings format.', 'discount-tools' ) ) );
		}

		// Import settings
		$sanitized = $this->sanitize_settings( $import_data['settings'] );
		update_option( self::OPTION_NAME, $sanitized );
		
		$imported_count = 0;
		
		// Import rules if present
		if ( isset( $import_data['rules'] ) && is_array( $import_data['rules'] ) ) {
			$repository = new \Discount_Tools_Rule_Repository();
			
			foreach ( $import_data['rules'] as $rule_data ) {
				try {
					// Create rule
					$rule = new \Discount_Tools_Rule();
					$rule->set_name( $rule_data['name'] );
					$rule->set_description( $rule_data['description'] ?? '' );
					$rule->set_rule_type( $rule_data['rule_type'] );
					$rule->set_discount_type( $rule_data['discount_type'] );
					$rule->set_discount_subtype( $rule_data['discount_subtype'] ?? 'simple' );
					$rule->set_discount_value( $rule_data['discount_value'] );
					$rule->set_priority( $rule_data['priority'] ?? 10 );
					$rule->set_status( $rule_data['status'] ?? 'active' );
					$rule->set_start_date( $rule_data['start_date'] ?? null );
					$rule->set_end_date( $rule_data['end_date'] ?? null );
					$rule->set_usage_limit( $rule_data['usage_limit'] ?? null );
					$rule->set_usage_limit_per_user( $rule_data['usage_limit_per_user'] ?? null );
					$rule->set_apply_mode( $rule_data['apply_mode'] ?? 'first' );
					if ( isset( $rule_data['bxgy_buy_quantity'] ) ) {
						$rule->set_bxgy_buy_quantity( $rule_data['bxgy_buy_quantity'] );
					}
					if ( isset( $rule_data['bxgy_get_quantity'] ) ) {
						$rule->set_bxgy_get_quantity( $rule_data['bxgy_get_quantity'] );
					}
					if ( isset( $rule_data['bxgy_get_discount'] ) ) {
						$rule->set_bxgy_get_discount( $rule_data['bxgy_get_discount'] );
					}
					if ( isset( $rule_data['bxgy_get_type'] ) ) {
						$rule->set_bxgy_get_type( $rule_data['bxgy_get_type'] );
					}
					
					// Add conditions
					if ( isset( $rule_data['conditions'] ) && is_array( $rule_data['conditions'] ) ) {
						foreach ( $rule_data['conditions'] as $condition_data ) {
							$condition = new \Discount_Tools_Condition(
								$condition_data['condition_type'],
								$condition_data['operator'],
								$condition_data['value']
							);
							$rule->add_condition( $condition );
						}
					}
					
					// Save rule
					$rule_id = $repository->save( $rule );
					
					// Add metadata
					if ( $rule_id && isset( $rule_data['metadata'] ) && is_array( $rule_data['metadata'] ) ) {
						foreach ( $rule_data['metadata'] as $meta_key => $meta_value ) {
							$repository->update_meta( $rule_id, $meta_key, $meta_value );
						}
					}
					
					$imported_count++;
				} catch ( \Throwable $e ) {
					// Log error but continue
				}
			}
		}
		
		$message = __( 'Settings imported successfully.', 'discount-tools' );
		if ( $imported_count > 0 ) {
			/* translators: %d: number of rules imported */
			$message .= ' ' . sprintf( __( '%d rules imported.', 'discount-tools' ), $imported_count );
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Reset settings to defaults
	 */
	public function reset_settings() {
		check_ajax_referer( 'dt-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		$defaults = $this->get_default_settings();
		update_option( self::OPTION_NAME, $defaults );

		wp_send_json_success( array( 'message' => __( 'Settings reset to defaults.', 'discount-tools' ) ) );
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings.
	 */
	public function get_default_settings() {
		return array(
			'enable_plugin'              => true,
			'discount_priority'          => 'first',
			'stack_discounts'            => false,
			'rule_application_strategy'  => 'priority',
			'coupon_interaction_mode'    => 'both_active',
			'product_display_position'   => 'after_add_to_cart',
			'product_display_style'      => 'badge',
			'message_text_color'         => '#333333',
			'message_font_size'          => 16,
			'badge_background_color'     => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
			'show_original_price'        => true,
			'cache_duration'             => 3600,
			'debug_mode'                 => false,
			'delete_data_on_uninstall'   => false,
		);
	}

	/**
	 * Get a setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	public static function get( $key, $default = null ) {
		$options = get_option( self::OPTION_NAME, array() );
		
		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		// Return default from defaults array
		$instance = new self();
		$defaults = $instance->get_default_settings();
		
		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}
}
