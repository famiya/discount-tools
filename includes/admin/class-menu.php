<?php
/**
 * Admin Menu Manager
 *
 * Manages WordPress admin menu structure.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin
 */

/**
 * Admin menu class.
 *
 * Handles admin menu registration and page routing.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Menu {

	/**
	 * The capability required to access menu pages.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $capability = 'manage_woocommerce';

	/**
	 * Main menu slug.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $menu_slug = 'discount-tools';

	/**
	 * Register admin menu.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_menu() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Add single submenu under WooCommerce with tabs
		add_submenu_page(
			'woocommerce',
			__( 'Discount Rules', 'discount-tools' ),
			__( 'Discount Rules', 'discount-tools' ),
			$this->capability,
			$this->menu_slug,
			array( $this, 'render_main_page' ),
			10
		);
	}

	/**
	 * Render main page with tabs.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_main_page() {
		// Check permissions
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'discount-tools' ) );
		}

		// Get current tab
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'rules';
		
		// Get action
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$rule_id = isset( $_GET['rule_id'] ) ? absint( wp_unslash( $_GET['rule_id'] ) ) : 0;

		// Handle bulk actions (POST)
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_bulk_action(); checked for bulk action selection only.
		if ( $current_tab === 'rules' && isset( $_POST['action'] ) && sanitize_text_field( wp_unslash( $_POST['action'] ) ) !== '-1' ) {
			$this->handle_bulk_action();
		}

		// Handle edit/add actions for rules tab
		if ( $current_tab === 'rules' && in_array( $action, array( 'edit', 'add' ) ) ) {
			if ( $action === 'edit' ) {
				$this->render_edit_rule_page( $rule_id );
			} else {
				$this->render_add_rule_page();
			}
			return;
		}

		// Handle other actions
		if ( $current_tab === 'rules' && ! empty( $action ) ) {
			switch ( $action ) {
				case 'delete':
					$this->handle_delete_rule( $rule_id );
					break;
				case 'duplicate':
					$this->handle_duplicate_rule( $rule_id );
					break;
				case 'toggle':
					$this->handle_toggle_rule( $rule_id );
					break;
			}
		}

		// Render tabs navigation
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<nav class="nav-tab-wrapper wp-clearfix">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '&tab=rules' ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'rules' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'All Rules', 'discount-tools' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '&tab=rules&action=add' ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'rules' && $action === 'add' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Add New Rule', 'discount-tools' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '&tab=settings' ) ); ?>" 
				   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'discount-tools' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				// Render tab content
				switch ( $current_tab ) {
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'rules':
					default:
						$this->render_rules_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render rules tab.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function render_rules_tab() {
		$this->render_rules_list_page();
	}

	/**
	 * Render settings tab.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function render_settings_tab() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Use the new Settings API template
		include_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/partials/settings-page.php';
	}

	/**
	 * Render rules list page.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function render_rules_list_page() {
		// Load the Rules List Table class if not already loaded.
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-rules-list-table.php';
		
		include_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/partials/rules-list.php';
	}

	/**
	 * Render add rule page.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_add_rule_page() {
		// Check permissions
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'discount-tools' ) );
		}

		// Load Rule Editor class if not already loaded.
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-rule-editor.php';

		// Create editor instance for new rule
		$editor = new Discount_Tools_Rule_Editor();

		// Handle form submission
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_save_rule_with_editor().
		if ( isset( $_POST['discount_tools_save_rule'] ) ) {
			$this->handle_save_rule_with_editor( $editor );
		}

		// Render editor
		$editor->render();
	}

	/**
	 * Render edit rule page.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Rule ID.
	 * @return void
	 */
	private function render_edit_rule_page( $rule_id ) {
		if ( ! $rule_id ) {
			wp_die( esc_html__( 'Invalid rule ID.', 'discount-tools' ) );
		}

		// Load Rule Editor class if not already loaded.
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/class-rule-editor.php';

		// Create editor instance
		$editor = new Discount_Tools_Rule_Editor( $rule_id );

		// Handle form submission
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_save_rule_with_editor().
		if ( isset( $_POST['discount_tools_save_rule'] ) ) {
			$this->handle_save_rule_with_editor( $editor );
		}

		// Render editor
		$editor->render();
		return;

		// Load rule data
		$repository = new Discount_Tools_Rule_Repository();
		$rule = $repository->find( $rule_id );

		if ( ! $rule ) {
			wp_die( esc_html__( 'Rule not found.', 'discount-tools' ) );
		}

		include_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/partials/rule-edit.php';
	}



	/**
	 * Handle save rule.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Rule ID (0 for new rule).
	 * @return void
	 */
	private function handle_save_rule( $rule_id = 0 ) {
		// Verify nonce
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! isset( $_POST['discount_tools_rule_nonce'] ) || 
			 ! wp_verify_nonce( wp_unslash( $_POST['discount_tools_rule_nonce'] ), 'discount_tools_save_rule' ) ) {
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Security check failed.', 'discount-tools' ) );
		}

		// Sanitize and validate input
		$rule_data = $this->sanitize_rule_data( wp_unslash( $_POST ) );

		// Validate
		$rule = new Discount_Tools_Rule();
		foreach ( $rule_data as $key => $value ) {
			$method = 'set_' . $key;
			if ( method_exists( $rule, $method ) ) {
				$rule->$method( $value );
			}
		}

		$validator = new Discount_Tools_Validator();
		$validation = $validator->validate( $rule );

		if ( ! $validation['valid'] ) {
			$error_messages = implode( '<br>', $validation['errors'] );
			add_settings_error(
				'discount_tools',
				'validation_error',
				$error_messages,
				'error'
			);
			return;
		}

		// Save rule
		$repository = new Discount_Tools_Rule_Repository();

		try {
			if ( $rule_id ) {
				$rule->set_id( $rule_id );
				$repository->update( $rule );
				$message = __( 'Rule updated successfully.', 'discount-tools' );
			} else {
				$rule_id = $repository->create( $rule );
				$message = __( 'Rule created successfully.', 'discount-tools' );
			}

			// Save conditions if provided
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Conditions array passed to save_rule_conditions for sanitization.
			if ( isset( $_POST['conditions'] ) && is_array( $_POST['conditions'] ) ) {
				$this->save_rule_conditions( $rule_id, wp_unslash( $_POST['conditions'] ) );
			}
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			add_settings_error(
				'discount_tools',
				'rule_saved',
				$message,
				'success'
			);

			// Redirect to rules list
			wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '&message=saved' ) );
			exit;

		} catch ( Exception $e ) {
			add_settings_error(
				'discount_tools',
				'save_error',
				/* translators: %s: error message */
				sprintf( __( 'Error saving rule: %s', 'discount-tools' ), $e->getMessage() ),
				'error'
			);
		}
	}

	/**
	 * Handle save rule with editor.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Discount_Tools_Rule_Editor $editor Editor instance.
	 * @return void
	 */
	private function handle_save_rule_with_editor( $editor ) {
		// Verify nonce
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! isset( $_POST['discount_tools_rule_nonce'] ) || 
			 ! wp_verify_nonce( wp_unslash( $_POST['discount_tools_rule_nonce'] ), 'discount_tools_save_rule' ) ) {
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Security check failed.', 'discount-tools' ) );
		}

		// Save rule using editor
		$result = $editor->save( wp_unslash( $_POST ) );

		if ( is_wp_error( $result ) ) {
			// Display errors
			foreach ( $result->get_error_messages() as $message ) {
				add_settings_error(
					'discount_tools',
					'validation_error',
					$message,
					'error'
				);
			}
			return;
		}

		// Success
		$rule_id = $result;
		$message = $editor->is_new() ? 
			__( 'Rule created successfully.', 'discount-tools' ) : 
			__( 'Rule updated successfully.', 'discount-tools' );

		add_settings_error(
			'discount_tools',
			'rule_saved',
			$message,
			'success'
		);

		// Get current tab to redirect back to it
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect parameter.
		$current_tab = isset( $_GET['editor_tab'] ) ? sanitize_key( wp_unslash( $_GET['editor_tab'] ) ) : 'general';
		
		// Redirect back to the same tab
		$redirect_url = add_query_arg(
			array(
				'page'       => $this->menu_slug,
				'tab'        => 'rules',
				'action'     => 'edit',
				'rule_id'    => $rule_id,
				'editor_tab' => $current_tab,
				'message'    => 'saved'
			),
			admin_url( 'admin.php' )
		);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle delete rule.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Rule ID.
	 * @return void
	 */
	private function handle_delete_rule( $rule_id ) {
		// Verify nonce
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! isset( $_GET['_wpnonce'] ) || 
			 ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'delete_rule_' . $rule_id ) ) {
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Security check failed.', 'discount-tools' ) );
		}

		$repository = new Discount_Tools_Rule_Repository();
		
		if ( $repository->delete( $rule_id ) ) {
			add_settings_error(
				'discount_tools',
				'rule_deleted',
				__( 'Rule deleted successfully.', 'discount-tools' ),
				'success'
			);
		} else {
			add_settings_error(
				'discount_tools',
				'delete_error',
				__( 'Error deleting rule.', 'discount-tools' ),
				'error'
			);
		}
	}

	/**
	 * Handle bulk action.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function handle_bulk_action() {
		// Verify nonce
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! isset( $_POST['_wpnonce'] ) || 
			 ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'bulk-rules' ) ) {
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Security check failed.', 'discount-tools' ) );
		}

		// Get action
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( $action === '-1' ) {
			$action = isset( $_POST['action2'] ) ? sanitize_text_field( wp_unslash( $_POST['action2'] ) ) : '';
		}

		// Get selected rules
		$rule_ids = isset( $_POST['rule'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['rule'] ) ) : array();

		if ( empty( $rule_ids ) ) {
			add_settings_error(
				'discount_tools',
				'no_rules_selected',
				__( 'No rules selected.', 'discount-tools' ),
				'error'
			);
			return;
		}

		$repository = new Discount_Tools_Rule_Repository();
		$success_count = 0;

		switch ( $action ) {
			case 'activate':
				foreach ( $rule_ids as $rule_id ) {
					if ( $repository->update( $rule_id, array( 'status' => 'active' ) ) ) {
						$success_count++;
					}
				}
				$message = sprintf(
					/* translators: %d: number of rules activated */
					_n( '%d rule activated.', '%d rules activated.', $success_count, 'discount-tools' ),
					$success_count
				);
				break;

			case 'deactivate':
				foreach ( $rule_ids as $rule_id ) {
					if ( $repository->update( $rule_id, array( 'status' => 'inactive' ) ) ) {
						$success_count++;
					}
				}
				$message = sprintf(
					/* translators: %d: number of rules deactivated */
					_n( '%d rule deactivated.', '%d rules deactivated.', $success_count, 'discount-tools' ),
					$success_count
				);
				break;

			case 'delete':
				foreach ( $rule_ids as $rule_id ) {
					if ( $repository->delete( $rule_id ) ) {
						$success_count++;
					}
				}
				$message = sprintf(
					/* translators: %d: number of rules deleted */
					_n( '%d rule deleted.', '%d rules deleted.', $success_count, 'discount-tools' ),
					$success_count
				);
				break;

			default:
				$message = __( 'Invalid bulk action.', 'discount-tools' );
				$success_count = 0;
				break;
		}

		if ( $success_count > 0 ) {
			add_settings_error(
				'discount_tools',
				'bulk_action_success',
				$message,
				'success'
			);
		} else {
			add_settings_error(
				'discount_tools',
				'bulk_action_error',
				__( 'Bulk action failed.', 'discount-tools' ),
				'error'
			);
		}
	}

	/**
	 * Handle duplicate rule.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Rule ID.
	 * @return void
	 */
	private function handle_duplicate_rule( $rule_id ) {
		// Verify nonce
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! isset( $_GET['_wpnonce'] ) || 
			 ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'duplicate_rule_' . $rule_id ) ) {
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Security check failed.', 'discount-tools' ) );
		}

		$repository = new Discount_Tools_Rule_Repository();
		$rule = $repository->find( $rule_id );

		if ( ! $rule ) {
			add_settings_error(
				'discount_tools',
				'duplicate_error',
				__( 'Rule not found.', 'discount-tools' ),
				'error'
			);
			return;
		}

		// Store original data before modifying rule
		$original_conditions = $rule->get_conditions();
		$original_meta = $rule->get_meta();

		// Create duplicate
		$rule->set_id( 0 );
		$rule->set_name( $rule->get_name() . ' (Copy)' );
		$rule->set_status( 'inactive' );

		try {
			// Convert Rule object to array for create() method
			$rule_data = $rule->get_data();
			$new_rule_id = $repository->create( $rule_data );
			
			if ( $new_rule_id ) {
				// Copy conditions
				if ( ! empty( $original_conditions ) ) {
					foreach ( $original_conditions as $condition ) {
						$condition_data = $condition->get_data();
						$condition_data['rule_id'] = $new_rule_id;
						$condition_data['id'] = 0; // Reset ID for new condition
						
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using WP helper method insert.
					$wpdb->insert(
							$wpdb->prefix . 'dt_conditions',
							array(
								'rule_id'        => $condition_data['rule_id'],
								'condition_type' => $condition_data['condition_type'],
								'operator'       => $condition_data['operator'],
								'value'          => is_array( $condition_data['value'] ) ? wp_json_encode( $condition_data['value'] ) : $condition_data['value'],
								'group_id'       => $condition_data['group_id'],
							),
							array( '%d', '%s', '%s', '%s', '%d' )
						);
					}
				}
				
				// Copy meta data
				if ( ! empty( $original_meta ) ) {
					foreach ( $original_meta as $meta_key => $meta_value ) {
						$repository->update_meta( $new_rule_id, $meta_key, $meta_value );
					}
				}
			}
			
			add_settings_error(
				'discount_tools',
				'rule_duplicated',
				__( 'Rule duplicated successfully.', 'discount-tools' ),
				'success'
			);
		} catch ( Exception $e ) {
			add_settings_error(
				'discount_tools',
				'duplicate_error',
				/* translators: %s: error message */
				sprintf( __( 'Error duplicating rule: %s', 'discount-tools' ), $e->getMessage() ),
				'error'
			);
		}
	}

	/**
	 * Handle toggle rule status.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $rule_id Rule ID.
	 * @return void
	 */
	private function handle_toggle_rule( $rule_id ) {
		// Verify nonce
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! isset( $_GET['_wpnonce'] ) || 
			 ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'toggle_rule_' . $rule_id ) ) {
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Security check failed.', 'discount-tools' ) );
		}

		$repository = new Discount_Tools_Rule_Repository();
		$rule = $repository->find( $rule_id );

		if ( ! $rule ) {
			add_settings_error(
				'discount_tools',
				'toggle_error',
				__( 'Rule not found.', 'discount-tools' ),
				'error'
			);
			return;
		}

		// Toggle status
		$new_status = ( $rule->get_status() === 'active' ) ? 'inactive' : 'active';
		$rule->set_status( $new_status );

		try {
			$repository->update( $rule );
			
			$message = ( $new_status === 'active' ) 
				? __( 'Rule activated successfully.', 'discount-tools' )
				: __( 'Rule deactivated successfully.', 'discount-tools' );

			add_settings_error(
				'discount_tools',
				'rule_toggled',
				$message,
				'success'
			);
		} catch ( Exception $e ) {
			add_settings_error(
				'discount_tools',
				'toggle_error',
				/* translators: %s: error message */
				sprintf( __( 'Error toggling rule: %s', 'discount-tools' ), $e->getMessage() ),
				'error'
			);
		}
	}

	/**
	 * Handle save settings.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function handle_save_settings() {
		// Verify nonce
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! isset( $_POST['discount_tools_settings_nonce'] ) || 
			 ! wp_verify_nonce( wp_unslash( $_POST['discount_tools_settings_nonce'] ), 'discount_tools_save_settings' ) ) {
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Security check failed.', 'discount-tools' ) );
		}

		// Get settings
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to sanitize_settings for sanitization.
		$settings = isset( $_POST['discount_tools_settings'] ) ? wp_unslash( $_POST['discount_tools_settings'] ) : array();

		// Sanitize
		$sanitized_settings = $this->sanitize_settings( $settings );

		// Save
		update_option( 'discount_tools_settings', $sanitized_settings );

		add_settings_error(
			'discount_tools',
			'settings_saved',
			__( 'Settings saved successfully.', 'discount-tools' ),
			'success'
		);
	}

	/**
	 * Sanitize rule data.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $data Raw POST data.
	 * @return array       Sanitized data.
	 */
	private function sanitize_rule_data( $data ) {
		$sanitized = array();

		// Basic fields
		$sanitized['name'] = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$sanitized['type'] = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : 'product';
		$sanitized['status'] = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active';
		$sanitized['priority'] = isset( $data['priority'] ) ? intval( $data['priority'] ) : 10;
		$sanitized['stackable'] = isset( $data['stackable'] ) && $data['stackable'] === '1';

		// Discount fields
		$sanitized['discount_type'] = isset( $data['discount_type'] ) ? sanitize_text_field( $data['discount_type'] ) : 'percentage';
		$sanitized['discount_value'] = isset( $data['discount_value'] ) ? floatval( $data['discount_value'] ) : 0;

		// BXGY fields (Buy X Get Y / Buy One Get One)
		if ( isset( $data['bxgy_buy_quantity'] ) ) {
			$sanitized['bxgy_buy_quantity'] = max( 1, intval( $data['bxgy_buy_quantity'] ) );
		}
		if ( isset( $data['bxgy_get_quantity'] ) ) {
			$sanitized['bxgy_get_quantity'] = max( 1, intval( $data['bxgy_get_quantity'] ) );
		}
		if ( isset( $data['bxgy_repeating'] ) ) {
			$sanitized['bxgy_repeating'] = $data['bxgy_repeating'] === '1' ? 1 : 0;
		}
		if ( isset( $data['bxgy_exchange_price'] ) ) {
			$sanitized['bxgy_exchange_price'] = max( 0, floatval( $data['bxgy_exchange_price'] ) );
		}
		// Gift products for bxgy_any type
		if ( isset( $data['bxgy_gift_products'] ) && is_array( $data['bxgy_gift_products'] ) ) {
			$sanitized['bxgy_gift_products'] = array_map( 'absint', $data['bxgy_gift_products'] );
		}
		
		// For BXGY types, set get_discount to 100% and get_type to percentage
		if ( in_array( $sanitized['discount_type'], array( 'bogo', 'bxgy_same', 'bxgy_any' ), true ) ) {
			$sanitized['bxgy_get_discount'] = 100.00;
			$sanitized['bxgy_get_type'] = 'percentage';
		}

		// Bundle fields (Bundle Set Discount)
		if ( isset( $data['bundle_quantity'] ) ) {
			$sanitized['bundle_quantity'] = max( 2, intval( $data['bundle_quantity'] ) );
		}
		if ( isset( $data['bundle_price'] ) ) {
			$sanitized['bundle_price'] = max( 0, floatval( $data['bundle_price'] ) );
		}
		if ( isset( $data['bundle_repeating'] ) ) {
			$sanitized['bundle_repeating'] = $data['bundle_repeating'] === '1' ? 1 : 0;
		}
		if ( isset( $data['bundle_free_shipping'] ) ) {
			$sanitized['bundle_free_shipping'] = $data['bundle_free_shipping'] === '1' ? 1 : 0;
		}
		if ( isset( $data['bundle_free_shipping_countries'] ) && is_array( $data['bundle_free_shipping_countries'] ) ) {
			$sanitized['bundle_free_shipping_countries'] = array_values( array_filter( array_map( 'sanitize_text_field', $data['bundle_free_shipping_countries'] ) ) );
		}

		// Optional fields
		if ( isset( $data['start_date'] ) && ! empty( $data['start_date'] ) ) {
			$sanitized['start_date'] = sanitize_text_field( $data['start_date'] );
		}

		if ( isset( $data['end_date'] ) && ! empty( $data['end_date'] ) ) {
			$sanitized['end_date'] = sanitize_text_field( $data['end_date'] );
		}

		if ( isset( $data['usage_limit'] ) && $data['usage_limit'] > 0 ) {
			$sanitized['usage_limit'] = intval( $data['usage_limit'] );
		}

		// Discount config
		if ( isset( $data['discount_config'] ) && is_array( $data['discount_config'] ) ) {
			$sanitized['discount_config'] = $data['discount_config'];
		}

		return $sanitized;
	}

	/**
	 * Save rule conditions.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int   $rule_id    Rule ID.
	 * @param  array $conditions Conditions data.
	 * @return void
	 */
	private function save_rule_conditions( $rule_id, $conditions ) {
		$repository = new Discount_Tools_Condition_Repository();

		// Delete existing conditions
		$repository->delete_by_rule_id( $rule_id );

		// Add new conditions
		foreach ( $conditions as $condition_data ) {
			$condition = new Discount_Tools_Condition();
			$condition->set_rule_id( $rule_id );
			$condition->set_type( sanitize_text_field( $condition_data['type'] ) );
			$condition->set_operator( sanitize_text_field( $condition_data['operator'] ) );
			$condition->set_value( $condition_data['value'] );
			
			if ( isset( $condition_data['group'] ) ) {
				$condition->set_group( intval( $condition_data['group'] ) );
			}

			$repository->create( $condition );
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $settings Raw settings.
	 * @return array           Sanitized settings.
	 */
	private function sanitize_settings( $settings ) {
		$sanitized = array();

		$sanitized['enabled'] = isset( $settings['enabled'] ) && $settings['enabled'] === '1';
		$sanitized['default_priority'] = isset( $settings['default_priority'] ) ? intval( $settings['default_priority'] ) : 10;
		$sanitized['cache_duration'] = isset( $settings['cache_duration'] ) ? intval( $settings['cache_duration'] ) : 3600;
		$sanitized['show_discount_table'] = isset( $settings['show_discount_table'] ) && $settings['show_discount_table'] === '1';
		$sanitized['show_cart_discount'] = isset( $settings['show_cart_discount'] ) && $settings['show_cart_discount'] === '1';
		$sanitized['show_checkout_savings'] = isset( $settings['show_checkout_savings'] ) && $settings['show_checkout_savings'] === '1';
		$sanitized['enable_logging'] = isset( $settings['enable_logging'] ) && $settings['enable_logging'] === '1';
		$sanitized['debug_mode'] = isset( $settings['debug_mode'] ) && $settings['debug_mode'] === '1';

		return $sanitized;
	}

	/**
	 * Get menu capability.
	 *
	 * @since  1.0.0
	 * @return string Capability required.
	 */
	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Get menu slug.
	 *
	 * @since  1.0.0
	 * @return string Menu slug.
	 */
	public function get_menu_slug() {
		return $this->menu_slug;
	}
}
