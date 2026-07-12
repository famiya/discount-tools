<?php
/**
 * Rules List Table
 *
 * Extends WP_List_Table to display discount rules in a professional WordPress admin table.
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

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Discount Tools Rules List Table Class
 *
 * Professional WordPress admin table for displaying and managing discount rules.
 *
 * @since 1.0.0
 */
class Discount_Tools_Rules_List_Table extends WP_List_Table {

	/**
	 * Rule repository instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Discount_Tools_Rule_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'rule',
				'plural'   => 'rules',
				'ajax'     => true,
			)
		);

		$this->repository = new Discount_Tools_Rule_Repository();
	}

	/**
	 * Get a list of columns.
	 *
	 * @since  1.0.0
	 * @return array Column titles keyed by column ID.
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'name'     => __( 'Rule Name', 'discount-tools' ),
			'type'     => __( 'Type', 'discount-tools' ),
			'discount' => __( 'Discount', 'discount-tools' ),
			'priority' => __( 'Priority', 'discount-tools' ),
			'status'   => __( 'Status', 'discount-tools' ),
			'usage'    => __( 'Usage', 'discount-tools' ),
			'dates'    => __( 'Date Range', 'discount-tools' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @since  1.0.0
	 * @return array Sortable column IDs and default sort direction.
	 */
	public function get_sortable_columns() {
		return array(
			'name'     => array( 'name', false ),
			'type'     => array( 'type', false ),
			'priority' => array( 'priority', true ),
			'status'   => array( 'status', false ),
			'usage'    => array( 'usage_count', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @since  1.0.0
	 * @return array Bulk actions array.
	 */
	public function get_bulk_actions() {
		return array(
			'activate'   => __( 'Activate', 'discount-tools' ),
			'deactivate' => __( 'Deactivate', 'discount-tools' ),
			'delete'     => __( 'Delete', 'discount-tools' ),
		);
	}

	/**
	 * Get views (filter links).
	 *
	 * @since  1.0.0
	 * @return array Views array.
	 */
	protected function get_views() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';

		$all_count    = $this->repository->count();
		$active_count = $this->repository->count( array( 'status' => 'active' ) );
		$inactive_count = $this->repository->count( array( 'status' => 'inactive' ) );

		$views = array(
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( remove_query_arg( 'status' ) ),
				$current === 'all' ? 'current' : '',
				__( 'All', 'discount-tools' ),
				$all_count
			),
			'active' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', 'active' ) ),
				$current === 'active' ? 'current' : '',
				__( 'Active', 'discount-tools' ),
				$active_count
			),
			'inactive' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', 'inactive' ) ),
				$current === 'inactive' ? 'current' : '',
				__( 'Inactive', 'discount-tools' ),
				$inactive_count
			),
		);

		return $views;
	}

	/**
	 * Extra table navigation (filters).
	 *
	 * @since  1.0.0
	 * @param  string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
		?>
		<div class="alignleft actions">
			<select name="type" id="filter-by-type">
				<option value=""><?php esc_html_e( 'All Types', 'discount-tools' ); ?></option>
				<option value="product" <?php selected( $type, 'product' ); ?>><?php esc_html_e( 'Product', 'discount-tools' ); ?></option>
				<option value="cart" <?php selected( $type, 'cart' ); ?>><?php esc_html_e( 'Cart', 'discount-tools' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'discount-tools' ), 'button', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare items for display.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		// Handle bulk actions.
		$this->process_bulk_action();

		// Set up columns.
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination parameters.
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build query parameters.
		$args = array(
			'limit'  => $per_page,
			'offset' => $offset,
		);

		// Status filter.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		if ( isset( $_GET['status'] ) && in_array( wp_unslash( $_GET['status'] ), array( 'active', 'inactive' ), true ) ) {
			$args['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}

		// Type filter.
		if ( isset( $_GET['type'] ) && in_array( wp_unslash( $_GET['type'] ), array( 'product', 'cart' ), true ) ) {
			$args['type'] = sanitize_text_field( wp_unslash( $_GET['type'] ) );
		}

		// Search query.
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Sorting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'priority';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'ASC';

		$args['orderby'] = $orderby;
		$args['order']   = strtoupper( $order );

		// Get rules.
		$this->items = $this->repository->find_all( $args );

		// Set pagination.
		$total_items = $this->repository->count( $args );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Render checkbox column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Checkbox HTML.
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="rule_ids[]" value="%d" />',
			$item->get_id()
		);
	}

	/**
	 * Render name column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Name column HTML.
	 */
	public function column_name( $item ) {
		$rule_id = $item->get_id();
		$title   = '<strong>' . esc_html( $item->get_name() ) . '</strong>';

		// Edit link.
		$edit_url = add_query_arg(
			array(
				'page'    => 'discount-tools',
				'action'  => 'edit',
				'rule_id' => $rule_id,
			),
			admin_url( 'admin.php' )
		);

		$title = sprintf(
			'<a href="%s" class="row-title">%s</a>',
			esc_url( $edit_url ),
			$title
		);

		// Description.
		if ( $item->get_description() ) {
			$title .= '<p class="description">' . esc_html( wp_trim_words( $item->get_description(), 15 ) ) . '</p>';
		}

		// Row actions.
		$actions = array();

		$actions['edit'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $edit_url ),
			__( 'Edit', 'discount-tools' )
		);

		$actions['duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			wp_nonce_url(
				add_query_arg(
					array(
						'page'    => 'discount-tools',
						'action'  => 'duplicate',
						'rule_id' => $rule_id,
					),
					admin_url( 'admin.php' )
				),
				'duplicate_rule_' . $rule_id
			),
			__( 'Duplicate', 'discount-tools' )
		);

		// Toggle action (AJAX).
		$toggle_action = $item->get_status() === 'active' ? 'deactivate' : 'activate';
		$toggle_text   = $item->get_status() === 'active' ? __( 'Deactivate', 'discount-tools' ) : __( 'Activate', 'discount-tools' );

		$actions['toggle'] = sprintf(
			'<a href="#" class="dt-toggle-status" data-rule-id="%d" data-action="%s" data-nonce="%s">%s</a>',
			$rule_id,
			$toggle_action,
			wp_create_nonce( 'toggle_rule_' . $rule_id ),
			$toggle_text
		);

		$actions['delete'] = sprintf(
			'<a href="%s" class="delete-rule" onclick="return confirm(\'%s\');">%s</a>',
			wp_nonce_url(
				add_query_arg(
					array(
						'page'    => 'discount-tools',
						'action'  => 'delete',
						'rule_id' => $rule_id,
					),
					admin_url( 'admin.php' )
				),
				'delete_rule_' . $rule_id
			),
			esc_js( __( 'Are you sure you want to delete this rule?', 'discount-tools' ) ),
			__( 'Delete', 'discount-tools' )
		);

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Render type column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Type column HTML.
	 */
	public function column_type( $item ) {
		$type_labels = array(
			'product' => __( 'Product', 'discount-tools' ),
			'cart'    => __( 'Cart', 'discount-tools' ),
		);

		$type = $item->get_rule_type();
		$label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : ucfirst( $type );

		$icon = $type === 'product' ? 'dashicons-tag' : 'dashicons-cart';

		return sprintf(
			'<span class="dashicons %s"></span> %s',
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * Render discount column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Discount column HTML.
	 */
	public function column_discount( $item ) {
		$discount_type  = $item->get_discount_type();
		$discount_value = $item->get_discount_value();

		switch ( $discount_type ) {
			case 'percentage':
				return '<strong>' . esc_html( $discount_value . '%' ) . '</strong>';

			case 'fixed_amount':
				return '<strong>' . wp_kses_post( wc_price( $discount_value ) ) . '</strong>';

			case 'price_override':
				return wp_kses_post( wc_price( $discount_value ) ) . '<br><small>' . __( 'Override', 'discount-tools' ) . '</small>';

			case 'bogo':
				return '<strong>' . __( 'BOGO', 'discount-tools' ) . '</strong>';

			default:
				return esc_html( ucfirst( str_replace( '_', ' ', $discount_type ) ) );
		}
	}

	/**
	 * Render priority column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Priority column HTML.
	 */
	public function column_priority( $item ) {
		$priority = $item->get_priority();
		$html     = '<strong>' . esc_html( $priority ) . '</strong>';

		// Check if rule is stackable (apply_mode = 'all')
		if ( $item->get_apply_mode() === 'all' ) {
			$html .= ' <span class="dashicons dashicons-admin-links" title="' . esc_attr__( 'Stackable with other rules', 'discount-tools' ) . '"></span>';
		}

		return $html;
	}

	/**
	 * Render status column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Status column HTML.
	 */
	public function column_status( $item ) {
		$status = $item->get_status();

		if ( $status === 'active' ) {
			return '<span class="dt-status-badge dt-status-active"><span class="dashicons dashicons-yes-alt"></span> ' . __( 'Active', 'discount-tools' ) . '</span>';
		} else {
			return '<span class="dt-status-badge dt-status-inactive"><span class="dashicons dashicons-dismiss"></span> ' . __( 'Inactive', 'discount-tools' ) . '</span>';
		}
	}

	/**
	 * Render usage column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Usage column HTML.
	 */
	public function column_usage( $item ) {
		$usage_count = $item->get_usage_count();
		$usage_limit = $item->get_usage_limit();

		if ( $usage_limit ) {
			$percentage = ( $usage_limit > 0 ) ? ( $usage_count / $usage_limit ) * 100 : 0;
			$bar_color  = $percentage >= 90 ? '#d63638' : ( $percentage >= 75 ? '#dba617' : '#2271b1' );

			return sprintf(
				'<div class="dt-usage-info">
					<span class="dt-usage-text">%d / %d</span>
					<div class="dt-usage-bar">
						<div class="dt-usage-bar-fill" style="width: %d%%; background-color: %s;"></div>
					</div>
				</div>',
				$usage_count,
				$usage_limit,
				min( 100, $percentage ),
				$bar_color
			);
		} else {
			return sprintf(
				'<span class="dt-usage-unlimited">%d <small>%s</small></span>',
				$usage_count,
				__( 'times', 'discount-tools' )
			);
		}
	}

	/**
	 * Render dates column.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item Rule object.
	 * @return string Dates column HTML.
	 */
	public function column_dates( $item ) {
		$start_date = $item->get_start_date();
		$end_date   = $item->get_end_date();
		$now        = current_time( 'timestamp' );

		if ( ! $start_date && ! $end_date ) {
			return '<span class="dt-date-unlimited">' . __( 'No date restrictions', 'discount-tools' ) . '</span>';
		}

		$html = '<div class="dt-date-range">';

		if ( $start_date && $end_date ) {
			$start_time = strtotime( $start_date );
			$end_time   = strtotime( $end_date );

			$html .= '<strong>' . date_i18n( 'M j, Y', $start_time ) . '</strong><br>';
			$html .= '<small>' . __( 'to', 'discount-tools' ) . '</small><br>';
			$html .= '<strong>' . date_i18n( 'M j, Y', $end_time ) . '</strong>';

			// Status indicator.
			if ( $now < $start_time ) {
				$html .= '<br><span class="dt-date-status dt-date-scheduled">' . __( 'Scheduled', 'discount-tools' ) . '</span>';
			} elseif ( $now > $end_time ) {
				$html .= '<br><span class="dt-date-status dt-date-expired">' . __( 'Expired', 'discount-tools' ) . '</span>';
			}
		} elseif ( $start_date ) {
			$start_time = strtotime( $start_date );
			$html .= __( 'From:', 'discount-tools' ) . ' <strong>' . date_i18n( 'M j, Y', $start_time ) . '</strong>';

			if ( $now < $start_time ) {
				$html .= '<br><span class="dt-date-status dt-date-scheduled">' . __( 'Scheduled', 'discount-tools' ) . '</span>';
			}
		} elseif ( $end_date ) {
			$end_time = strtotime( $end_date );
			$html .= __( 'Until:', 'discount-tools' ) . ' <strong>' . date_i18n( 'M j, Y', $end_time ) . '</strong>';

			if ( $now > $end_time ) {
				$html .= '<br><span class="dt-date-status dt-date-expired">' . __( 'Expired', 'discount-tools' ) . '</span>';
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Default column renderer.
	 *
	 * @since  1.0.0
	 * @param  Discount_Tools_Rule $item        Rule object.
	 * @param  string              $column_name Column name.
	 * @return string Column HTML.
	 */
	public function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Process bulk actions.
	 *
	 * @since 1.0.0
	 */
	public function process_bulk_action() {
		// Security check.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Get selected rule IDs.
		$rule_ids = isset( $_GET['rule_ids'] ) ? array_map( 'absint', $_GET['rule_ids'] ) : array();

		if ( empty( $rule_ids ) ) {
			return;
		}

		// Verify nonce.
		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$processed = 0;

	foreach ( $rule_ids as $rule_id ) {
		switch ( $action ) {
			case 'activate':
				if ( $this->repository->update( $rule_id, array( 'status' => 'active' ) ) ) {
					$processed++;
				}
				break;

			case 'deactivate':
				if ( $this->repository->update( $rule_id, array( 'status' => 'inactive' ) ) ) {
					$processed++;
				}
				break;

			case 'delete':
				if ( $this->repository->delete( $rule_id ) ) {
					$processed++;
				}
				break;
		}
	}

		// Display success message.
		if ( $processed > 0 ) {
			$message = '';
			switch ( $action ) {
				case 'activate':
					/* translators: %d: number of rules activated */
					$message = sprintf( _n( '%d rule activated.', '%d rules activated.', $processed, 'discount-tools' ), $processed );
					break;
				case 'deactivate':
					/* translators: %d: number of rules deactivated */
					$message = sprintf( _n( '%d rule deactivated.', '%d rules deactivated.', $processed, 'discount-tools' ), $processed );
					break;
				case 'delete':
					/* translators: %d: number of rules deleted */
					$message = sprintf( _n( '%d rule deleted.', '%d rules deleted.', $processed, 'discount-tools' ), $processed );
					break;
			}

			add_settings_error(
				'discount_tools',
				'bulk_action_success',
				$message,
				'success'
			);
		}

		// Redirect to remove query args.
		wp_safe_redirect( remove_query_arg( array( 'action', 'rule_ids', '_wpnonce', '_wp_http_referer' ) ) );
		exit;
	}

	/**
	 * Display empty table message.
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No rules found.', 'discount-tools' );
	}
}
