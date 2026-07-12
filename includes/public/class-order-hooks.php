<?php
/**
 * Order Hooks
 *
 * Integrates with WooCommerce order hooks.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 */

/**
 * Order hooks class.
 *
 * Saves discount information to order metadata.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/public
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Order_Hooks {

	/**
	 * Meta key prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $meta_prefix = '_dt_';

	/**
	 * Register hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_hooks() {
		// Save discount data when order is created
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_discount_data' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_discount_data_from_store_api' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_line_item_discount_data' ), 10, 4 );

		// Display discount data in admin order details
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_discount_in_admin' ), 10, 1 );
		
		// Display discount data in customer order details
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_discount_in_customer_order' ), 10, 1 );

		// Add discount column to orders list
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_discount_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_discount_column' ), 10, 2 );

		// HPOS compatibility
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_discount_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_discount_column_hpos' ), 10, 2 );
	}

	/**
	 * Save discount data to order metadata.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order Order object.
	 * @param  array    $data  Posted data.
	 * @return void
	 */
	public function save_discount_data( $order, $data ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Get discount data from session
		$cart_discounts = WC()->session->get( 'discount_tools_cart_discounts' );

		// Save cart-level discounts
		if ( ! empty( $cart_discounts ) ) {
			$this->save_cart_discounts( $order, $cart_discounts );
		}

		// Persist rule IDs early so usage tracking does not depend on checkout session timing.
		$rule_ids = $this->collect_applied_rule_ids();
		if ( ! empty( $rule_ids ) ) {
			$order->update_meta_data( $this->meta_prefix . 'rule_ids', $rule_ids );
		}

		// Save product-level discounts
		$this->save_product_discounts( $order );

		// Calculate and save total savings
		$total_savings = $this->calculate_total_savings( $order );
		$order->update_meta_data( $this->meta_prefix . 'total_savings', $total_savings );
	}

	/**
	 * Collect applied rule IDs from session/cart sources.
	 *
	 * @since  1.1.3
	 * @access private
	 * @return array
	 */
	private function collect_applied_rule_ids() {
		$rule_ids = array();

		if ( WC()->session ) {
			$cart_discounts = WC()->session->get( 'discount_tools_cart_discounts', array() );
			if ( ! empty( $cart_discounts['rules_applied'] ) && is_array( $cart_discounts['rules_applied'] ) ) {
				foreach ( $cart_discounts['rules_applied'] as $rule ) {
					if ( isset( $rule['rule_id'] ) ) {
						$rule_ids[] = absint( $rule['rule_id'] );
					}
				}
			}

			$shipping_rule_ids = WC()->session->get( 'dt_shipping_rule_ids', array() );
			if ( is_array( $shipping_rule_ids ) ) {
				foreach ( $shipping_rule_ids as $shipping_rule_id ) {
					$rule_ids[] = absint( $shipping_rule_id );
				}
			}
		}

		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( isset( $cart_item['discount_tools_rule_id'] ) ) {
					$rule_ids[] = absint( $cart_item['discount_tools_rule_id'] );
				}

				if ( isset( $cart_item['discount_tools_bundle']['rule_id'] ) ) {
					$rule_ids[] = absint( $cart_item['discount_tools_bundle']['rule_id'] );
				}

				if ( isset( $cart_item['discount_tools_bxgy']['rule_id'] ) ) {
					$rule_ids[] = absint( $cart_item['discount_tools_bxgy']['rule_id'] );
				}

				if ( isset( $cart_item['discount_tools_rules'] ) && is_array( $cart_item['discount_tools_rules'] ) ) {
					foreach ( $cart_item['discount_tools_rules'] as $item_rule ) {
						if ( isset( $item_rule['rule_id'] ) ) {
							$rule_ids[] = absint( $item_rule['rule_id'] );
						}
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $rule_ids ) ) );
	}

	/**
	 * Save discount data from Store API (Block Checkout).
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order   Order object.
	 * @param  object   $request Request object.
	 * @return void
	 */
	public function save_discount_data_from_store_api( $order, $request ) {
		$this->save_discount_data( $order, array() );
	}

	/**
	 * Save cart-level discounts to order.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  WC_Order $order          Order object.
	 * @param  array    $cart_discounts Cart discount data.
	 * @return void
	 */
	private function save_cart_discounts( $order, $cart_discounts ) {
		// Save total cart discount
		if ( isset( $cart_discounts['total_discount'] ) ) {
			$order->update_meta_data( $this->meta_prefix . 'cart_discount', $cart_discounts['total_discount'] );
		}

		// Save applied rules
		if ( ! empty( $cart_discounts['rules_applied'] ) ) {
			$rules_data = array();
			
			foreach ( $cart_discounts['rules_applied'] as $rule ) {
				$rules_data[] = array(
					'rule_id' => $rule['rule_id'],
					'rule_name' => $rule['rule_name'],
					'discount_type' => $rule['discount_type'],
					'discount_amount' => $rule['discount_amount'],
					'applied_at' => current_time( 'mysql' ),
				);
			}

			$order->update_meta_data( $this->meta_prefix . 'cart_rules', $rules_data );

			// Save rule IDs for easy querying
			$rule_ids = wp_list_pluck( $rules_data, 'rule_id' );
			$order->update_meta_data( $this->meta_prefix . 'rule_ids', $rule_ids );
		}
	}

	/**
	 * Save product-level discounts to order.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  WC_Order $order Order object.
	 * @return void
	 */
	private function save_product_discounts( $order ) {
		$product_discounts = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			// Check if item has discount data
			$discount_data = $item->get_meta( 'discount_tools_discount' );
			$original_price = $item->get_meta( 'discount_tools_original_price' );
			$rules = $item->get_meta( 'discount_tools_rules' );

			if ( ! empty( $discount_data ) || ! empty( $original_price ) ) {
				$product_discounts[ $item_id ] = array(
					'product_id' => $item->get_product_id(),
					'product_name' => $item->get_name(),
					'quantity' => $item->get_quantity(),
					'original_price' => $original_price,
					'discount_amount' => $discount_data,
					'final_price' => $item->get_total() / $item->get_quantity(),
					'rules' => $rules,
				);
			}

			// Check for free products
			$is_free = $item->get_meta( 'discount_tools_free_product' );
			if ( $is_free ) {
				$rule_id = $item->get_meta( 'discount_tools_rule_id' );
				$rule_name = $item->get_meta( 'discount_tools_rule_name' );
				$exchange_price = max( 0, floatval( $item->get_meta( 'discount_tools_exchange_price' ) ) );
				$is_exchange = $exchange_price > 0;

				$product_discounts[ $item_id ] = array(
					'product_id' => $item->get_product_id(),
					'product_name' => $item->get_name(),
					'quantity' => $item->get_quantity(),
					'is_free_product' => ! $is_exchange,
					'is_exchange_product' => $is_exchange,
					'exchange_price' => $exchange_price,
					'rule_id' => $rule_id,
					'rule_name' => $rule_name,
				);
			}
		}

		if ( ! empty( $product_discounts ) ) {
			$order->update_meta_data( $this->meta_prefix . 'product_discounts', $product_discounts );
		}
	}

	/**
	 * Save discount metadata to order line items.
	 *
	 * Keeps bundle, BXGY and product-level discount rule IDs attached to the
	 * created order item so usage tracking can read them later.
	 *
	 * @since  1.1.3
	 * @param  WC_Order_Item_Product $item       Order item.
	 * @param  string                $cart_item_key Cart item key.
	 * @param  array                 $values     Cart item values.
	 * @param  WC_Order              $order      Order object.
	 * @return void
	 */
	public function save_order_line_item_discount_data( $item, $cart_item_key, $values, $order ) {
		if ( ! $item instanceof WC_Order_Item_Product || ! is_array( $values ) ) {
			return;
		}

		if ( isset( $values['discount_tools_applied'] ) ) {
			$item->add_meta_data( 'discount_tools_applied', (bool) $values['discount_tools_applied'], true );
		}

		if ( isset( $values['discount_tools_original_price'] ) ) {
			$item->add_meta_data( 'discount_tools_original_price', $values['discount_tools_original_price'], true );
		}

		if ( isset( $values['discount_tools_discount'] ) ) {
			$item->add_meta_data( 'discount_tools_discount', $values['discount_tools_discount'], true );
		}

		if ( isset( $values['discount_tools_rules'] ) && is_array( $values['discount_tools_rules'] ) ) {
			$item->add_meta_data( 'discount_tools_rules', $values['discount_tools_rules'], true );
		}

		if ( isset( $values['discount_tools_bundle'] ) && is_array( $values['discount_tools_bundle'] ) ) {
			$item->add_meta_data( 'discount_tools_bundle', $values['discount_tools_bundle'], true );
		}

		if ( isset( $values['discount_tools_bxgy'] ) && is_array( $values['discount_tools_bxgy'] ) ) {
			$item->add_meta_data( 'discount_tools_bxgy', $values['discount_tools_bxgy'], true );
		}

		if ( isset( $values['discount_tools_free_product'] ) ) {
			$item->add_meta_data( 'discount_tools_free_product', (bool) $values['discount_tools_free_product'], true );
		}

		if ( isset( $values['discount_tools_rule_id'] ) ) {
			$item->add_meta_data( 'discount_tools_rule_id', absint( $values['discount_tools_rule_id'] ), true );
		}

		if ( isset( $values['discount_tools_rule_name'] ) ) {
			$item->add_meta_data( 'discount_tools_rule_name', sanitize_text_field( $values['discount_tools_rule_name'] ), true );
		}

		if ( isset( $values['discount_tools_exchange_price'] ) ) {
			$item->add_meta_data( 'discount_tools_exchange_price', $values['discount_tools_exchange_price'], true );
		}
	}

	/**
	 * Calculate total savings on order.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  WC_Order $order Order object.
	 * @return float           Total savings amount.
	 */
	private function calculate_total_savings( $order ) {
		$total_savings = 0;

		// Cart discount
		$cart_discount = $order->get_meta( $this->meta_prefix . 'cart_discount' );
		if ( $cart_discount ) {
			$total_savings += floatval( $cart_discount );
		}

		// Product discounts
		$product_discounts = $order->get_meta( $this->meta_prefix . 'product_discounts' );
		if ( ! empty( $product_discounts ) ) {
			foreach ( $product_discounts as $discount ) {
				if ( isset( $discount['discount_amount'] ) ) {
					$total_savings += floatval( $discount['discount_amount'] ) * intval( $discount['quantity'] );
				}
				// Free products
				if ( ! empty( $discount['is_free_product'] ) && isset( $discount['original_price'] ) ) {
					$total_savings += floatval( $discount['original_price'] ) * intval( $discount['quantity'] );
				}
			}
		}

		return $total_savings;
	}

	/**
	 * Display discount information in admin order details.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order Order object.
	 * @return void
	 */
	public function display_discount_in_admin( $order ) {
		$total_savings = $order->get_meta( $this->meta_prefix . 'total_savings' );
		$cart_rules = $order->get_meta( $this->meta_prefix . 'cart_rules' );
		$product_discounts = $order->get_meta( $this->meta_prefix . 'product_discounts' );
		// No discounts applied
		if ( empty( $total_savings ) && empty( $cart_rules ) && empty( $product_discounts ) ) {
			return;
		}

		?>
		<div class="discount-tools-order-discounts">
			<h3><?php esc_html_e( 'Applied Discounts', 'discount-tools' ); ?></h3>
			
			<?php if ( $total_savings > 0 ) : ?>
				<p class="total-savings">
					<strong><?php esc_html_e( 'Total Savings:', 'discount-tools' ); ?></strong>
					<span class="amount"><?php echo wp_kses_post( wc_price( $total_savings ) ); ?></span>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $cart_rules ) ) : ?>
				<div class="cart-discounts">
					<h4><?php esc_html_e( 'Cart-Level Discounts:', 'discount-tools' ); ?></h4>
					<ul>
						<?php foreach ( $cart_rules as $rule ) : ?>
							<li>
								<strong><?php echo esc_html( $rule['rule_name'] ); ?></strong>
								(ID: <?php echo intval( $rule['rule_id'] ); ?>)
								- <?php echo wp_kses_post( wc_price( $rule['discount_amount'] ) ); ?>
								<br>
								<small>
									<?php esc_html_e( 'Type:', 'discount-tools' ); ?>
									<?php echo esc_html( $rule['discount_type'] ); ?>
									|
									<?php esc_html_e( 'Applied:', 'discount-tools' ); ?>
									<?php echo esc_html( $rule['applied_at'] ); ?>
								</small>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $product_discounts ) ) : ?>
				<div class="product-discounts">
					<h4><?php esc_html_e( 'Product-Level Discounts:', 'discount-tools' ); ?></h4>
					<ul>
						<?php foreach ( $product_discounts as $discount ) : ?>
							<li>
								<strong><?php echo esc_html( $discount['product_name'] ); ?></strong>
								(<?php echo intval( $discount['quantity'] ); ?>x)
								<?php if ( ! empty( $discount['is_exchange_product'] ) ) : ?>
									- <span class="free-product"><?php esc_html_e( 'EXCHANGE PRODUCT', 'discount-tools' ); ?></span>
									<br>
									<small>
										<?php esc_html_e( 'Exchange Price:', 'discount-tools' ); ?>
										<?php echo wp_kses_post( wc_price( $discount['exchange_price'] ) ); ?>
										|
										<?php esc_html_e( 'Rule:', 'discount-tools' ); ?>
										<?php echo esc_html( $discount['rule_name'] ); ?>
									</small>
								<?php elseif ( ! empty( $discount['is_free_product'] ) ) : ?>
									- <span class="free-product"><?php esc_html_e( 'FREE PRODUCT', 'discount-tools' ); ?></span>
									<br>
									<small><?php esc_html_e( 'Rule:', 'discount-tools' ); ?> <?php echo esc_html( $discount['rule_name'] ); ?></small>
								<?php else : ?>
									- <?php esc_html_e( 'Discount:', 'discount-tools' ); ?>
									<?php echo wp_kses_post( wc_price( $discount['discount_amount'] ) ); ?>
									<?php if ( isset( $discount['original_price'] ) ) : ?>
										<br>
										<small>
											<?php esc_html_e( 'Original:', 'discount-tools' ); ?>
											<?php echo wp_kses_post( wc_price( $discount['original_price'] ) ); ?>
											→
											<?php esc_html_e( 'Final:', 'discount-tools' ); ?>
											<?php echo wp_kses_post( wc_price( $discount['final_price'] ) ); ?>
										</small>
									<?php endif; ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<style>
				.discount-tools-order-discounts {
					margin-top: 20px;
					padding: 15px;
					background: #f8f9fa;
					border: 1px solid #ddd;
					border-radius: 4px;
				}
				.discount-tools-order-discounts h3 {
					margin-top: 0;
					color: #2271b1;
				}
				.discount-tools-order-discounts .total-savings {
					font-size: 16px;
					padding: 10px;
					background: #d4edda;
					border: 1px solid #c3e6cb;
					border-radius: 4px;
				}
				.discount-tools-order-discounts .total-savings .amount {
					color: #155724;
					font-weight: bold;
					float: right;
				}
				.discount-tools-order-discounts ul {
					list-style: none;
					padding-left: 0;
				}
				.discount-tools-order-discounts li {
					padding: 8px;
					border-bottom: 1px solid #ddd;
				}
				.discount-tools-order-discounts li:last-child {
					border-bottom: none;
				}
				.discount-tools-order-discounts .free-product {
					color: #28a745;
					font-weight: bold;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Display discount information in customer order details.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order Order object.
	 * @return void
	 */
	public function display_discount_in_customer_order( $order ) {
		$total_savings = $order->get_meta( $this->meta_prefix . 'total_savings' );

		if ( empty( $total_savings ) ) {
			return;
		}

		?>
		<section class="woocommerce-discount-tools-savings">
			<h2><?php esc_html_e( 'You Saved', 'discount-tools' ); ?></h2>
			<table class="woocommerce-table">
				<?php if ( $total_savings > 0 ) : ?>
					<tr>
						<th><?php esc_html_e( 'Total Savings:', 'discount-tools' ); ?></th>
						<td><strong class="savings-amount"><?php echo wp_kses_post( wc_price( $total_savings ) ); ?></strong></td>
					</tr>
				<?php endif; ?>
			</table>
		</section>
		<style>
			.woocommerce-discount-tools-savings {
				margin-top: 20px;
			}
			.woocommerce-discount-tools-savings .savings-amount {
				color: #28a745;
				font-size: 1.2em;
			}
		</style>
		<?php
	}

	/**
	 * Add discount column to orders list.
	 *
	 * @since  1.0.0
	 * @param  array $columns Existing columns.
	 * @return array          Modified columns.
	 */
	public function add_order_discount_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;

			// Add after order total column
			if ( 'order_total' === $key ) {
				$new_columns['discount_tools_savings'] = __( 'Savings', 'discount-tools' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render discount column content in orders list.
	 *
	 * @since  1.0.0
	 * @param  string $column  Column name.
	 * @param  int    $post_id Post ID (Order ID).
	 * @return void
	 */
	public function render_order_discount_column( $column, $post_id ) {
		if ( 'discount_tools_savings' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		$total_savings = $order->get_meta( $this->meta_prefix . 'total_savings' );

		if ( $total_savings > 0 ) {
			echo '<span style="color: #28a745; font-weight: bold;">' . wp_kses_post( wc_price( $total_savings ) ) . '</span>';
		} else {
			echo '—';
		}
	}

	/**
	 * Render discount column content in orders list (HPOS).
	 *
	 * @since  1.0.0
	 * @param  string   $column Column name.
	 * @param  WC_Order $order  Order object.
	 * @return void
	 */
	public function render_order_discount_column_hpos( $column, $order ) {
		if ( 'discount_tools_savings' !== $column ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$total_savings = $order->get_meta( $this->meta_prefix . 'total_savings' );

		if ( $total_savings > 0 ) {
			echo '<span style="color: #28a745; font-weight: bold;">' . wp_kses_post( wc_price( $total_savings ) ) . '</span>';
		} else {
			echo '—';
		}
	}

	/**
	 * Get order discount data.
	 *
	 * Public method to retrieve discount data from order.
	 *
	 * @since  1.0.0
	 * @param  int $order_id Order ID.
	 * @return array         Discount data.
	 */
	public function get_order_discount_data( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array();
		}

		return array(
			'total_savings' => $order->get_meta( $this->meta_prefix . 'total_savings' ),
			'cart_discount' => $order->get_meta( $this->meta_prefix . 'cart_discount' ),
			'cart_rules' => $order->get_meta( $this->meta_prefix . 'cart_rules' ),
			'product_discounts' => $order->get_meta( $this->meta_prefix . 'product_discounts' ),
			'rule_ids' => $order->get_meta( $this->meta_prefix . 'rule_ids' ),
		);
	}

	/**
	 * Get orders by rule ID.
	 *
	 * Find all orders that used a specific discount rule.
	 *
	 * @since  1.0.0
	 * @param  int $rule_id Rule ID.
	 * @return array        Array of order IDs.
	 */
	public function get_orders_by_rule( $rule_id ) {
		$args = array(
			'limit' => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for product lookup.
			'meta_query' => array(
				array(
					'key' => $this->meta_prefix . 'rule_ids',
					'value' => sprintf( ':"%d";', $rule_id ),
					'compare' => 'LIKE',
				),
			),
			'return' => 'ids',
		);

		return wc_get_orders( $args );
	}
}
