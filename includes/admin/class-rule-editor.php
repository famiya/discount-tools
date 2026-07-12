<?php
/**
 * Rule Editor Class
 *
 * Handles the rule editing interface with tabbed navigation.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discount Tools Rule Editor Class
 *
 * Manages the rule editing interface with tabbed navigation.
 *
 * @since 1.0.0
 */
class Discount_Tools_Rule_Editor {

	/**
	 * Rule object being edited.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Rule|null
	 */
	private $rule;

	/**
	 * Rule repository instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Rule_Repository
	 */
	private $rule_repository;

	/**
	 * Condition repository instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Condition_Repository
	 */
	private $condition_repository;

	/**
	 * Current active tab.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $active_tab;

	/**
	 * Available tabs.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $tabs;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param int|null $rule_id Optional rule ID to edit.
	 */
	public function __construct( $rule_id = null ) {
		$this->rule_repository      = new Discount_Tools_Rule_Repository();
		$this->condition_repository = new Discount_Tools_Condition_Repository();

		// Load rule or create new.
		if ( $rule_id ) {
			$this->rule = $this->rule_repository->find( $rule_id );
			if ( ! $this->rule ) {
				$this->rule = $this->create_default_rule();
			}
		} else {
			$this->rule = $this->create_default_rule();
		}

		// Define available tabs.
		$this->tabs = array(
			'general'    => __( 'General', 'discount-tools' ),
			'conditions' => __( 'Conditions', 'discount-tools' ),
			'discounts'  => __( 'Discounts', 'discount-tools' ),
			'usage'      => __( 'Usage Limits', 'discount-tools' ),
			'display'    => __( 'Display', 'discount-tools' ),
		);

		// Get active tab.
		// Use 'editor_tab' parameter to avoid conflict with main menu 'tab' parameter
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab display parameter, not form processing.
		$this->active_tab = isset( $_GET['editor_tab'] ) ? sanitize_key( wp_unslash( $_GET['editor_tab'] ) ) : 'general';
		if ( ! array_key_exists( $this->active_tab, $this->tabs ) ) {
			$this->active_tab = 'general';
		}
	}

	/**
	 * Create default rule with defaults.
	 *
	 * @since  1.0.0
	 * @return Discount_Tools_Rule
	 */
	private function create_default_rule() {
		$rule = new Discount_Tools_Rule();
		$rule->set_priority( 10 );
		$rule->set_status( 'inactive' );
		$rule->set_rule_type( 'product' );
		$rule->set_discount_type( 'percentage' );
		$rule->set_meta_value( 'stackable', false );
		return $rule;
	}

	/**
	 * Get the rule object.
	 *
	 * @since  1.0.0
	 * @return Discount_Tools_Rule
	 */
	public function get_rule() {
		return $this->rule;
	}

	/**
	 * Check if this is a new rule.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_new() {
		return $this->rule->get_id() === 0;
	}

	/**
	 * Render the editor interface.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		?>
		<div class="wrap discount-tools-admin">
			<?php $this->render_header(); ?>
			<?php $this->render_tabs(); ?>
			
		<form method="post" action="" id="discount-tools-rule-form" class="dt-rule-editor-form">
			<?php 
			wp_nonce_field( 'discount_tools_save_rule', 'discount_tools_rule_nonce' );
			
			if ( ! $this->is_new() ) {
				echo '<input type="hidden" name="rule_id" value="' . esc_attr( $this->rule->get_id() ) . '">';
			}
			
			// Add hidden fields for required fields from other tabs
			// This ensures these values are included even when submitting from different tabs
			if ( $this->active_tab !== 'general' ) {
				// For new rules, get values from POST data (previous tab submission)
				// For existing rules, get from rule object
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on form submission.
				$name = $this->is_new() && isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : $this->rule->get_name();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on form submission.
				$rule_type = $this->is_new() && isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : $this->rule->get_rule_type();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on form submission.
				$status = $this->is_new() && isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : $this->rule->get_status();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on form submission.
				$priority = $this->is_new() && isset( $_POST['priority'] ) ? intval( $_POST['priority'] ) : $this->rule->get_priority();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on form submission.
				$discount_type = $this->is_new() && isset( $_POST['discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) ) : $this->rule->get_discount_type();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on form submission.
				$discount_value = $this->is_new() && isset( $_POST['discount_value'] ) ? floatval( $_POST['discount_value'] ) : $this->rule->get_discount_value();
				
				echo '<input type="hidden" name="name" value="' . esc_attr( $name ) . '">';
				echo '<input type="hidden" name="rule_type" value="' . esc_attr( $rule_type ) . '">';
				echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">';
				echo '<input type="hidden" name="priority" value="' . esc_attr( $priority ) . '">';
				echo '<input type="hidden" name="discount_type" value="' . esc_attr( $discount_type ) . '">';
				echo '<input type="hidden" name="discount_value" value="' . esc_attr( $discount_value ) . '">';
			}
			?>

			<div class="dt-rule-editor">
				<div class="dt-tab-content">
					<?php $this->render_tab_content(); ?>
				</div>					<div class="dt-editor-actions">
						<?php $this->render_actions(); ?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render editor header.
	 *
	 * @since 1.0.0
	 */
	private function render_header() {
		?>
		<h1 class="wp-heading-inline">
			<?php echo $this->is_new() ? esc_html__( 'Add New Discount Rule', 'discount-tools' ) : esc_html__( 'Edit Discount Rule', 'discount-tools' ); ?>
		</h1>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=discount-tools' ) ); ?>" class="page-title-action">
			<?php echo esc_html__( 'Back to Rules', 'discount-tools' ); ?>
		</a>

		<hr class="wp-header-end">

		<?php settings_errors( 'discount_tools' ); ?>
		<?php
	}

	/**
	 * Render navigation tabs.
	 *
	 * @since 1.0.0
	 */
	private function render_tabs() {
		?>
		<nav class="nav-tab-wrapper wp-clearfix">
			<?php foreach ( $this->tabs as $tab_key => $tab_label ) : ?>
				<?php
				$url = add_query_arg(
					array(
						'page' => 'discount-tools',
						'tab' => 'rules',
						'action' => $this->is_new() ? 'add' : 'edit',
						'editor_tab' => $tab_key,
					),
					admin_url( 'admin.php' )
				);

				if ( ! $this->is_new() ) {
					$url = add_query_arg( 'rule_id', $this->rule->get_id(), $url );
				}

				$active_class = $this->active_tab === $tab_key ? 'nav-tab-active' : '';
				?>
				<a href="<?php echo esc_url( $url ); ?>" 
				   class="nav-tab <?php echo esc_attr( $active_class ); ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render current tab content.
	 *
	 * @since 1.0.0
	 */
	private function render_tab_content() {
		$tab_file = DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/admin/partials/rule-editor-' . $this->active_tab . '.php';

		if ( file_exists( $tab_file ) ) {
			$rule = $this->rule;
			$editor = $this;
			include $tab_file;
		} else {
			?>
		<div class="notice notice-warning inline">
			<p><?php
			/* translators: %s: tab name */
			printf( esc_html__( 'Tab content for "%s" is not yet implemented.', 'discount-tools' ), esc_html( $this->tabs[ $this->active_tab ] ) ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Render action buttons.
	 *
	 * @since 1.0.0
	 */
	private function render_actions() {
		?>
		<div class="dt-actions-bar">
			<div class="dt-actions-left">
				<input type="submit" 
					   name="discount_tools_save_rule" 
					   class="button button-primary button-large" 
					   value="<?php echo $this->is_new() ? esc_attr__( 'Create Rule', 'discount-tools' ) : esc_attr__( 'Save Changes', 'discount-tools' ); ?>">
				
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=discount-tools' ) ); ?>" 
				   class="button button-large">
					<?php echo esc_html__( 'Cancel', 'discount-tools' ); ?>
				</a>

				<?php if ( ! $this->is_new() ) : ?>
					<button type="button" 
							class="button button-large dt-preview-rule" 
							data-rule-id="<?php echo esc_attr( $this->rule->get_id() ); ?>">
						<?php echo esc_html__( 'Preview Changes', 'discount-tools' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="dt-actions-right">
				<?php if ( ! $this->is_new() ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=discount-tools&action=duplicate&rule_id=' . $this->rule->get_id() ), 'duplicate_rule_' . $this->rule->get_id() ) ); ?>" 
					   class="button button-large">
						<?php echo esc_html__( 'Duplicate', 'discount-tools' ); ?>
					</a>

					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=discount-tools&action=delete&rule_id=' . $this->rule->get_id() ), 'delete_rule_' . $this->rule->get_id() ) ); ?>" 
					   class="button button-large button-link-delete" 
					   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this rule?', 'discount-tools' ) ); ?>');">
						<?php echo esc_html__( 'Delete', 'discount-tools' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Validate form data.
	 *
	 * @since  1.0.0
	 * @param  array $data Form data.
	 * @return array|WP_Error Sanitized data or error.
	 */
	public function validate( $data ) {
		$errors = new WP_Error();
		$existing_rule = $this->get_rule();
		$rule_value = function( $method, $default = '' ) use ( $existing_rule ) {
			return ( $existing_rule && method_exists( $existing_rule, $method ) ) ? $existing_rule->{$method}() : $default;
		};

		// Normalize legacy keys for compatibility with older templates.
		if ( isset( $data['type'] ) && ! isset( $data['rule_type'] ) ) {
			$data['rule_type'] = $data['type'];
		}
		if ( isset( $data['rule_name'] ) && ! isset( $data['name'] ) ) {
			$data['name'] = $data['rule_name'];
		}

		// When saving from other tabs, fall back to current rule values.
		if ( ! isset( $data['name'] ) || '' === $data['name'] ) {
			$data['name'] = $rule_value( 'get_name', '' );
		}
		if ( ! isset( $data['rule_type'] ) || '' === $data['rule_type'] ) {
			$data['rule_type'] = $rule_value( 'get_rule_type', '' );
		}
		if ( ! isset( $data['status'] ) || '' === $data['status'] ) {
			$data['status'] = $rule_value( 'get_status', '' );
		}
		if ( ! isset( $data['priority'] ) || '' === $data['priority'] ) {
			$data['priority'] = $rule_value( 'get_priority', '' );
		}
		if ( ! isset( $data['discount_type'] ) || '' === $data['discount_type'] ) {
			$data['discount_type'] = $rule_value( 'get_discount_type', '' );
		}
		if ( ! isset( $data['discount_value'] ) ) {
			$data['discount_value'] = $rule_value( 'get_discount_value', 0 );
		}
		if ( ! isset( $data['usage_limit'] ) ) {
			$data['usage_limit'] = $rule_value( 'get_usage_limit', 0 );
		}
		if ( ! isset( $data['usage_limit_per_user'] ) ) {
			$data['usage_limit_per_user'] = $rule_value( 'get_usage_limit_per_user', 0 );
		}
		// Preserve conditions when saving from other tabs
		if ( ! isset( $data['conditions'] ) || ! is_array( $data['conditions'] ) ) {
			$data['conditions'] = $rule_value( 'get_conditions', array() );
		}
		if ( ! isset( $data['description'] ) ) {
			$data['description'] = $rule_value( 'get_description', '' );
		}
		if ( ! isset( $data['start_date'] ) ) {
			$data['start_date'] = $rule_value( 'get_start_date', '' );
		}
		if ( ! isset( $data['end_date'] ) ) {
			$data['end_date'] = $rule_value( 'get_end_date', '' );
		}

		// Preserve BXGY meta fields when saving from other tabs
		if ( $existing_rule && in_array( $existing_rule->get_discount_type(), array( 'bxgy_same', 'bxgy_any' ), true ) ) {
			$rule_id = $existing_rule->get_id();
			if ( $rule_id > 0 ) {
				$meta = $this->rule_repository->get_meta( $rule_id );
				if ( ! isset( $data['bxgy_buy_quantity'] ) && isset( $meta['bxgy_buy_quantity'] ) ) {
					$data['bxgy_buy_quantity'] = $meta['bxgy_buy_quantity'];
				}
				if ( ! isset( $data['bxgy_get_quantity'] ) && isset( $meta['bxgy_get_quantity'] ) ) {
					$data['bxgy_get_quantity'] = $meta['bxgy_get_quantity'];
				}
				if ( ! isset( $data['bxgy_gift_products'] ) && isset( $meta['bxgy_gift_products'] ) ) {
					$data['bxgy_gift_products'] = $meta['bxgy_gift_products'];
				}
				if ( ! isset( $data['bxgy_repeating'] ) && isset( $meta['bxgy_repeating'] ) ) {
					$data['bxgy_repeating'] = $meta['bxgy_repeating'];
				}
				if ( ! isset( $data['bxgy_exchange_price'] ) && isset( $meta['bxgy_exchange_price'] ) ) {
					$data['bxgy_exchange_price'] = $meta['bxgy_exchange_price'];
				}
			}
		}

		// Preserve Bundle meta fields when saving from other tabs
		if ( $existing_rule && 'bundle' === $existing_rule->get_discount_type() ) {
			$rule_id = $existing_rule->get_id();
			if ( $rule_id > 0 ) {
				$meta = $this->rule_repository->get_meta( $rule_id );
				if ( ! isset( $data['bundle_quantity'] ) && isset( $meta['bundle_quantity'] ) ) {
					$data['bundle_quantity'] = $meta['bundle_quantity'];
				}
				if ( ! isset( $data['bundle_price'] ) && isset( $meta['bundle_price'] ) ) {
					$data['bundle_price'] = $meta['bundle_price'];
				}
				if ( ! isset( $data['bundle_repeating'] ) && isset( $meta['bundle_repeating'] ) ) {
					$data['bundle_repeating'] = $meta['bundle_repeating'];
				}
				if ( ! isset( $data['bundle_free_shipping'] ) && isset( $meta['bundle_free_shipping'] ) ) {
					$data['bundle_free_shipping'] = $meta['bundle_free_shipping'];
				}
				if ( ! isset( $data['bundle_free_shipping_countries'] ) && isset( $meta['bundle_free_shipping_countries'] ) ) {
					$data['bundle_free_shipping_countries'] = $meta['bundle_free_shipping_countries'];
				}
			}
		}

		// Validate name.
		if ( empty( $data['name'] ) ) {
			$errors->add( 'name_required', __( 'Rule name is required.', 'discount-tools' ) );
		}

		// Validate type - check if exists first.
		if ( ! isset( $data['rule_type'] ) || ! in_array( $data['rule_type'], array( 'product', 'cart' ) ) ) {
			$errors->add( 'invalid_type', __( 'Invalid rule type.', 'discount-tools' ) );
		}

		// Validate status - check if exists first.
		if ( ! isset( $data['status'] ) || ! in_array( $data['status'], array( 'active', 'inactive' ) ) ) {
			$errors->add( 'invalid_status', __( 'Invalid rule status.', 'discount-tools' ) );
		}

		// Validate priority - check if exists first.
		if ( ! isset( $data['priority'] ) || ! is_numeric( $data['priority'] ) || $data['priority'] < 1 || $data['priority'] > 100 ) {
			$errors->add( 'invalid_priority', __( 'Priority must be between 1 and 100.', 'discount-tools' ) );
		}

		// Validate discount type - set default if not provided.
		if ( ! isset( $data['discount_type'] ) || empty( $data['discount_type'] ) ) {
			$data['discount_type'] = 'percentage'; // Default discount type.
		}
		
		$valid_discount_types = array( 'percentage', 'fixed_amount', 'price_override', 'bxgy_same', 'bxgy_any', 'bundle', 'tiered', 'bulk' );
		if ( ! in_array( $data['discount_type'], $valid_discount_types ) ) {
			$errors->add( 'invalid_discount_type', __( 'Invalid discount type.', 'discount-tools' ) );
		}

		// Validate discount value.
		// Skip validation for types that don't use discount_value field
		$types_without_value = array( 'bxgy_same', 'bxgy_any', 'bundle' );
		if ( ! in_array( $data['discount_type'], $types_without_value, true ) ) {
			if ( ! isset( $data['discount_value'] ) || ! is_numeric( $data['discount_value'] ) || $data['discount_value'] < 0 ) {
				$errors->add( 'invalid_discount_value', __( 'Discount value must be a positive number.', 'discount-tools' ) );
			}

			// Additional validation for percentage.
			if ( isset( $data['discount_type'] ) && $data['discount_type'] === 'percentage' && $data['discount_value'] > 100 ) {
				$errors->add( 'invalid_percentage', __( 'Percentage discount cannot exceed 100%.', 'discount-tools' ) );
			}
		} else {
			// For types without discount_value, set to 0
			$data['discount_value'] = 0;
		}

		// Validate dates.
		if ( ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) ) {
			$start = strtotime( $data['start_date'] );
			$end = strtotime( $data['end_date'] );

			if ( $start >= $end ) {
				$errors->add( 'invalid_date_range', __( 'End date must be after start date.', 'discount-tools' ) );
			}
		}

		// Validate usage limit.
		if ( isset( $data['usage_limit'] ) && ! empty( $data['usage_limit'] ) ) {
			if ( ! is_numeric( $data['usage_limit'] ) || $data['usage_limit'] < 0 ) {
				$errors->add( 'invalid_usage_limit', __( 'Usage limit must be a positive number or zero for unlimited.', 'discount-tools' ) );
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return $this->sanitize( $data );
	}

	/**
	 * Sanitize form data.
	 *
	 * @since  1.0.0
	 * @param  array $data Raw form data.
	 * @return array Sanitized data.
	 */
	public function sanitize( $data ) {
		$sanitized = array();
		$rule = $this->get_rule();
		$rule_value = function( $method, $default = '' ) use ( $rule ) {
			return ( $rule && method_exists( $rule, $method ) ) ? $rule->{$method}() : $default;
		};

		// Derive defaults from the existing rule when available.
		$name          = isset( $data['name'] ) ? $data['name'] : $rule_value( 'get_name', '' );
		$description   = isset( $data['description'] ) ? $data['description'] : $rule_value( 'get_description', '' );
		$rule_type     = isset( $data['rule_type'] ) ? $data['rule_type'] : $rule_value( 'get_rule_type', 'product' );
		$status        = isset( $data['status'] ) ? $data['status'] : $rule_value( 'get_status', 'inactive' );
		$discount_type = isset( $data['discount_type'] ) ? $data['discount_type'] : $rule_value( 'get_discount_type', 'percentage' );
		$priority      = isset( $data['priority'] ) ? $data['priority'] : $rule_value( 'get_priority', 10 );
		$discount_value = isset( $data['discount_value'] ) ? $data['discount_value'] : $rule_value( 'get_discount_value', 0 );
		$usage_limit   = isset( $data['usage_limit'] ) ? $data['usage_limit'] : $rule_value( 'get_usage_limit', 0 );
		$start_date    = isset( $data['start_date'] ) ? $data['start_date'] : $rule_value( 'get_start_date', '' );
		$end_date      = isset( $data['end_date'] ) ? $data['end_date'] : $rule_value( 'get_end_date', '' );

		// Sanitize text fields.
		$sanitized['name'] = sanitize_text_field( $name );
		$sanitized['description'] = ( '' !== $description && null !== $description ) ? sanitize_textarea_field( $description ) : '';

		// Sanitize select fields.
		$sanitized['rule_type'] = sanitize_key( $rule_type );
		$sanitized['status'] = sanitize_key( $status );
		$sanitized['discount_type'] = ! empty( $discount_type ) ? sanitize_key( $discount_type ) : 'percentage';

		// Sanitize numeric fields.
		$sanitized['priority'] = absint( $priority );
		$sanitized['discount_value'] = floatval( $discount_value );
		$sanitized['usage_limit'] = absint( $usage_limit );
		$sanitized['usage_limit_per_user'] = isset( $data['usage_limit_per_user'] ) ? absint( $data['usage_limit_per_user'] ) : 0;
		
		// Add fields required by repository but not in form.
		$sanitized['minimum_amount'] = 0;
		$sanitized['maximum_amount'] = 0;

		// Sanitize boolean.
		// Checkboxes: if present in POST data, it's checked ('1'). If absent from the active tab, it's unchecked.
		// We use the 'active_tab' field to determine which tab is being saved.
		$active_tab = isset( $data['active_tab'] ) ? $data['active_tab'] : '';
		
		if ( $active_tab === 'general' ) {
			// Saving from General tab - evaluate stackable checkbox
			// If checkbox is in POST data and equals '1', it's checked; otherwise it's unchecked
			$sanitized['stackable'] = isset( $data['stackable'] ) && '1' === $data['stackable'];
		} else {
			// Saving from another tab - preserve existing value
			$sanitized['stackable'] = $rule_value( 'is_stackable', false );
		}

		// Sanitize dates.
		$sanitized['start_date'] = ! empty( $start_date ) ? sanitize_text_field( $start_date ) : null;
		$sanitized['end_date'] = ! empty( $end_date ) ? sanitize_text_field( $end_date ) : null;

		// Sanitize BXGY fields
		$sanitized['discount_subtype'] = isset( $data['discount_subtype'] ) ? sanitize_text_field( $data['discount_subtype'] ) : 'simple';
		$sanitized['bxgy_buy_quantity'] = isset( $data['bxgy_buy_quantity'] ) ? absint( $data['bxgy_buy_quantity'] ) : null;
		$sanitized['bxgy_get_quantity'] = isset( $data['bxgy_get_quantity'] ) ? absint( $data['bxgy_get_quantity'] ) : null;
		$sanitized['bxgy_get_discount'] = isset( $data['bxgy_get_discount'] ) ? floatval( $data['bxgy_get_discount'] ) : null;
		$sanitized['bxgy_get_type'] = isset( $data['bxgy_get_type'] ) ? sanitize_text_field( $data['bxgy_get_type'] ) : null;
		$sanitized['bxgy_exchange_price'] = isset( $data['bxgy_exchange_price'] ) ? max( 0, floatval( $data['bxgy_exchange_price'] ) ) : 0;

		return $sanitized;
	}

	/**
	 * Save rule from form data.
	 *
	 * @since  1.0.0
	 * @param  array $data Form data.
	 * @return int|WP_Error Rule ID on success, WP_Error on failure.
	 */
	public function save( $data ) {
		// Get the active tab for checkbox handling
		$active_tab = isset( $data['active_tab'] ) ? $data['active_tab'] : '';
		
		// Backwards compatibility: map legacy field names to current keys.
		if ( isset( $data['type'] ) && ! isset( $data['rule_type'] ) ) {
			$data['rule_type'] = $data['type'];
		}
		if ( isset( $data['rule_name'] ) && ! isset( $data['name'] ) ) {
			$data['name'] = $data['rule_name'];
		}

		// Validate data.
		$validated = $this->validate( $data );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		// Build data array for repository (matching actual table structure).
		$save_data = array(
			'name'                  => $validated['name'],
			'description'           => $validated['description'],
			'rule_type'             => $validated['rule_type'],
			'status'                => $validated['status'],
			'priority'              => $validated['priority'],
			'discount_type'         => $validated['discount_type'],
			'discount_value'        => $validated['discount_value'],
			'discount_subtype'      => isset( $validated['discount_subtype'] ) ? $validated['discount_subtype'] : 'simple',
			'start_date'            => $validated['start_date'],
			'end_date'              => $validated['end_date'],
			'usage_limit'           => $validated['usage_limit'],
			'usage_limit_per_user'  => $validated['usage_limit_per_user'],
			'apply_mode'            => $validated['stackable'] ? 'all' : 'first',
			'bxgy_buy_quantity'     => isset( $validated['bxgy_buy_quantity'] ) ? absint( $validated['bxgy_buy_quantity'] ) : null,
			'bxgy_get_quantity'     => isset( $validated['bxgy_get_quantity'] ) ? absint( $validated['bxgy_get_quantity'] ) : null,
			'bxgy_get_discount'     => isset( $validated['bxgy_get_discount'] ) ? floatval( $validated['bxgy_get_discount'] ) : null,
			'bxgy_get_type'         => isset( $validated['bxgy_get_type'] ) ? sanitize_text_field( $validated['bxgy_get_type'] ) : null,
		);

		// Save to database.
		global $wpdb;
		$table_name = $wpdb->prefix . 'dt_rules';
		
		if ( $this->is_new() ) {
			// Add timestamps for new record.
			$save_data['date_created'] = current_time( 'mysql' );
			$save_data['date_modified'] = current_time( 'mysql' );
			$save_data['usage_count'] = 0;
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using WP helper method insert.
			$result = $wpdb->insert( $table_name, $save_data );
			
			if ( $result ) {
				$rule_id = $wpdb->insert_id;
				$this->rule->set_id( $rule_id );
			} else {
				$rule_id = false;
			}
		} else {
			// Update timestamp.
			$save_data['date_modified'] = current_time( 'mysql' );
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using WP helper method update.
			$result = $wpdb->update( 
				$table_name, 
				$save_data, 
				array( 'id' => $this->rule->get_id() )
			);
			
			$rule_id = $result !== false ? $this->rule->get_id() : false;
		}
		
		// Save conditions.
		if ( isset( $data['conditions'] ) && is_array( $data['conditions'] ) ) {
			$this->save_conditions( $rule_id, $data['conditions'] );
			// Refresh rule conditions in memory so subsequent render uses latest data.
			if ( $rule_id ) {
				$updated_conditions = $this->condition_repository->find_by_rule_id( $rule_id );
				$this->rule->set_conditions( $updated_conditions );
			}
		}
		
	// Save BXGY meta data
	if ( $rule_id && ( $validated['discount_type'] === 'bxgy_same' || $validated['discount_type'] === 'bxgy_any' ) ) {
		// Save BXGY configuration
		$bxgy_fields = array( 'bxgy_buy_quantity', 'bxgy_get_quantity' );
		foreach ( $bxgy_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$value = absint( $data[ $field ] );
				$this->rule_repository->update_meta( $rule_id, $field, $value );
			}
		}
		
		// Handle repeating checkbox
		// Only update if saving from Discounts tab, otherwise preserve existing value
		if ( $active_tab === 'discounts' ) {
			// Saving from Discounts tab - evaluate bxgy_repeating checkbox
			// If checkbox is in POST data and equals '1', it's checked; otherwise it's unchecked
			$repeating = isset( $data['bxgy_repeating'] ) && $data['bxgy_repeating'] === '1' ? 1 : 0;
			$this->rule_repository->update_meta( $rule_id, 'bxgy_repeating', $repeating );
		}
		// If saving from another tab, don't update - preserve existing value
		
		// Save gift products (only for bxgy_any type and when saving from discounts tab)
		if ( $validated['discount_type'] === 'bxgy_any' ) {
			if ( $active_tab === 'discounts' ) {
				$exchange_price = isset( $data['bxgy_exchange_price'] ) ? max( 0, floatval( $data['bxgy_exchange_price'] ) ) : 0;
				$this->rule_repository->update_meta( $rule_id, 'bxgy_exchange_price', $exchange_price );

				// Only update gift products when saving from discounts tab
				if ( isset( $data['bxgy_gift_products'] ) && ! empty( $data['bxgy_gift_products'] ) ) {
					$gift_products = array_map( 'absint', (array) $data['bxgy_gift_products'] );
					$this->rule_repository->update_meta( $rule_id, 'bxgy_gift_products', $gift_products );
				}
			}
			// If saving from other tabs, preserve existing gift products (do nothing)
		} elseif ( $active_tab === 'discounts' ) {
			// Only clear gift products when saving from discounts tab AND type is not bxgy_any
			$this->rule_repository->update_meta( $rule_id, 'bxgy_gift_products', array() );
			$this->rule_repository->update_meta( $rule_id, 'bxgy_exchange_price', 0 );
		}
		
		// Always set discount to 100% (completely free) and type to percentage
		$this->rule_repository->update_meta( $rule_id, 'bxgy_get_discount', 100.0 );
		$this->rule_repository->update_meta( $rule_id, 'bxgy_get_type', 'percentage' );
	}
	
	// Save Bundle meta data
	if ( $rule_id && $validated['discount_type'] === 'bundle' ) {
		// Save bundle configuration
		$bundle_fields = array( 'bundle_quantity', 'bundle_price' );
		foreach ( $bundle_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$value = $field === 'bundle_quantity' ? absint( $data[ $field ] ) : floatval( $data[ $field ] );
				$this->rule_repository->update_meta( $rule_id, $field, $value );
			}
		}
		
		// Handle repeating checkbox
		// Only update if saving from Discounts tab, otherwise preserve existing value
		if ( $active_tab === 'discounts' ) {
			// Saving from Discounts tab - evaluate bundle_repeating checkbox
			// If checkbox is in POST data and equals '1', it's checked; otherwise it's unchecked
			$repeating = isset( $data['bundle_repeating'] ) && $data['bundle_repeating'] === '1' ? 1 : 0;
			$this->rule_repository->update_meta( $rule_id, 'bundle_repeating', $repeating );

			$bundle_free_shipping = isset( $data['bundle_free_shipping'] ) && $data['bundle_free_shipping'] === '1' ? 1 : 0;
			$this->rule_repository->update_meta( $rule_id, 'bundle_free_shipping', $bundle_free_shipping );

			$countries = isset( $data['bundle_free_shipping_countries'] ) && is_array( $data['bundle_free_shipping_countries'] )
				? array_values( array_filter( array_map( 'sanitize_text_field', $data['bundle_free_shipping_countries'] ) ) )
				: array();
			$this->rule_repository->update_meta( $rule_id, 'bundle_free_shipping_countries', $countries );
		}
		// If saving from another tab, don't update - preserve existing value
	}
	
		// Save display settings meta data
		if ( $rule_id ) {
			// Define field types for proper sanitization
			$checkbox_fields = array( 'show_on_product_page', 'show_in_cart', 'show_savings_message' );
			$all_fields = array( 'show_on_product_page', 'product_display_position', 'product_display_style', 'show_in_cart', 'show_savings_message', 'badge_text', 'savings_message' );
			
			// Only update display settings if display data is provided
			if ( isset( $data['display'] ) ) {
				// Initialize display data
				$display_data = $data['display'];
				
				// Set unchecked checkboxes to '0'
				foreach ( $checkbox_fields as $checkbox_field ) {
					if ( ! isset( $display_data[ $checkbox_field ] ) ) {
						$display_data[ $checkbox_field ] = '0';
					}
				}
				
				// Save all display fields
				foreach ( $all_fields as $field ) {
					if ( isset( $display_data[ $field ] ) ) {
						$value = $display_data[ $field ];
						
						// Sanitize based on field type
						if ( in_array( $field, $checkbox_fields ) ) {
							$sanitized_value = ! empty( $value ) ? '1' : '0';
						} else {
							$sanitized_value = sanitize_text_field( $value );
						}
						
						// Save to meta table with display_ prefix
						$this->rule_repository->update_meta( $rule_id, 'display_' . $field, $sanitized_value );
					}
				}
			}
			// If display data is not provided (e.g., saving from Conditions tab),
			// don't modify existing display settings - keep them as they are
		}

		if ( ! $rule_id ) {
			return new WP_Error( 'save_failed', __( 'Failed to save rule. Please try again.', 'discount-tools' ) );
		}

		// Clear rule cache to ensure changes are reflected immediately
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-cache-manager.php';
		$cache_manager = Discount_Tools_Cache_Manager::get_instance();
		$cache_manager->delete_rule( $rule_id );
		$cache_manager->flush_all();

		return $rule_id;
	}

	/**
	 * Save rule conditions.
	 *
	 * @since  1.0.0
	 * @param  int   $rule_id    The rule ID.
	 * @param  array $conditions The conditions array grouped by group_id.
	 * @return bool              True on success, false on failure.
	 */
	private function save_conditions( $rule_id, $conditions ) {
		// Delete all existing conditions for this rule.
		$this->condition_repository->delete_by_rule_id( $rule_id );

		// Track if any conditions were actually saved.
		$conditions_saved = false;

		// Process each condition group.
		foreach ( $conditions as $group_id => $group_conditions ) {
			if ( ! is_array( $group_conditions ) ) {
				continue;
			}

			// Process each condition in the group.
			foreach ( $group_conditions as $index => $condition_data ) {
				// Skip empty conditions.
				if ( empty( $condition_data['condition_type'] ) || empty( $condition_data['operator'] ) ) {
					continue;
				}

				// Validate condition value.
			$value = isset( $condition_data['value'] ) ? $condition_data['value'] : '';

			// For coupon_activation, handle both old and new formats
			if ( 'coupon_activation' === $condition_data['condition_type'] ) {
				
				// CRITICAL FIX: WordPress adds slashes to $_POST data, must remove them
				$value = wp_unslash( $value );
				
				// Try to decode the value as JSON first (new format)
				$decoded_value = null;
				if ( is_string( $value ) && ! empty( $value ) ) {
					$decoded_value = json_decode( $value, true);
				}					// Check if it's the new format with "mappings" key
					if ( is_array( $decoded_value ) && isset( $decoded_value['mappings'] ) && is_array( $decoded_value['mappings'] ) ) {
						// New format: {"mappings":[{"coupon":"CODE","email":"user@example.com"}]}
						// Convert to standardized format for storage
						$coupon_codes = array();
						$emails = array();
						
						foreach ( $decoded_value['mappings'] as $mapping ) {
							if ( ! empty( $mapping['coupon'] ) ) {
								$coupon_code = strtoupper( sanitize_text_field( $mapping['coupon'] ) );
								$coupon_codes[] = $coupon_code;
								
								// Email is optional in new format
								if ( ! empty( $mapping['email'] ) ) {
									$email = sanitize_email( $mapping['email'] );
									if ( is_email( $email ) ) {
										$emails[] = $email;
									}
								} else {
									$emails[] = '';  // Empty email for this coupon
								}
							}
						}
						
						// Pass as array - the repository will json_encode it
				$value = array(
					'coupon_codes' => $coupon_codes,
					'email_list' => $emails,
				);
			}
			} else {
					// Normal value processing
					if ( is_array( $value ) ) {
						// Keep as array - repository will handle encoding
						$value = array_map( 'sanitize_text_field', $value );
					} else {
						$raw_value = is_string( $value ) ? wp_unslash( $value ) : $value;
						$decoded   = null;
						if ( is_string( $raw_value ) ) {
							$decoded = json_decode( $raw_value, true );
						}

						if ( is_array( $decoded ) && json_last_error() === JSON_ERROR_NONE ) {
							// Keep as array - repository will handle encoding
							$value = array_map( 'sanitize_text_field', $decoded );
						} else {
							$value = sanitize_text_field( $raw_value );
						}
					}
				}

				// Skip if value is required but empty.
				if ( '' === $value || null === $value ) {
					continue;
				}

				// Prepare condition data for repository.
				$insert_data = array(
					'rule_id'  => $rule_id,
					'type'     => sanitize_text_field( $condition_data['condition_type'] ),
					'operator' => sanitize_text_field( $condition_data['operator'] ),
					'value'    => $value,
					'group_id' => absint( $group_id ),
				);

				// Create the condition.
				$result = $this->condition_repository->create( $insert_data );
				if ( $result ) {
					$conditions_saved = true;
				}
			}
		}

		return $conditions_saved;
	}
}
