<?php
/**
 * AJAX Handler for Admin Operations
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin
 */

/**
 * AJAX Handler class.
 *
 * Handles AJAX requests for admin operations.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Ajax_Handler {
	/**
	 * Get string length in a multibyte-safe way.
	 *
	 * @since 1.0.0
	 * @param string $value Input string.
	 * @return int
	 */
	private function dt_strlen( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}
		return (int) strlen( $value );
	}


	/**
	 * Register AJAX actions.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_actions() {
		// Product search
		add_action( 'wp_ajax_dt_search_products', array( $this, 'search_products' ) );
		
		// Category search
		add_action( 'wp_ajax_dt_search_categories', array( $this, 'search_categories' ) );
		
		// Brand search
		add_action( 'wp_ajax_dt_search_brands', array( $this, 'search_brands' ) );
		
		// Customer email search
		add_action( 'wp_ajax_dt_search_customer_emails', array( $this, 'search_customer_emails' ) );
		
		// Toggle rule status
		add_action( 'wp_ajax_dt_toggle_rule_status', array( $this, 'toggle_rule_status' ) );
		
		// Reset usage count
		add_action( 'wp_ajax_dt_reset_usage_count', array( $this, 'reset_usage_count' ) );
	}

	/**
	 * Search products via AJAX.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function search_products() {
		// Check nonce - support multiple nonce names for different callers
		$nonce_verified = false;
		
		// Support for BXGY product selector (_wpnonce with dt_search_products action)
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'dt_search_products' ) ) {
			$nonce_verified = true;
		}
		// Support for condition builder (nonce with dt-search-nonce action)
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		elseif ( isset( $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'dt-search-nonce' ) ) {
			$nonce_verified = true;
		}
		// Support for older condition builder (security with dt-condition-builder action)
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		elseif ( isset( $_GET['security'] ) && wp_verify_nonce( wp_unslash( $_GET['security'] ), 'dt-condition-builder' ) ) {
			$nonce_verified = true;
		}
		
		if ( ! $nonce_verified ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'discount-tools' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		// Get search term - support both 'term' and 'search' parameters
		$search_term   = '';
		if ( isset( $_GET['search'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_GET['search'] ) );
		} elseif ( isset( $_GET['term'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_GET['term'] ) );
		}
		
		$selected_raw  = isset( $_GET['selected'] ) ? sanitize_text_field( wp_unslash( $_GET['selected'] ) ) : '';
		$selected_ids  = array_filter( array_map( 'absint', preg_split( '/[,\s]+/', $selected_raw ) ) );

		global $wpdb;

		if ( empty( $selected_ids ) && $this->dt_strlen( $search_term ) < 2 ) {
			wp_send_json_success( array( 'data' => array() ) );
		}

		if ( ! empty( $selected_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $selected_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Core table names and %d placeholders.
			$sql          = "SELECT DISTINCT p.ID, p.post_title, pm.meta_value as sku
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_sku')
				WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND p.ID IN ( {$placeholders} )";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table query required.
			$products = $wpdb->get_results( $wpdb->prepare( $sql, $selected_ids ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query required.
			$products = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT p.ID, p.post_title, pm.meta_value as sku
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_sku')
					WHERE p.post_type = 'product'
					AND p.post_status = 'publish'
					AND (
						p.post_title LIKE %s
						OR pm.meta_value LIKE %s
					)
					ORDER BY p.post_title ASC
					LIMIT 20",
					'%' . $wpdb->esc_like( $search_term ) . '%',
					'%' . $wpdb->esc_like( $search_term ) . '%'
				)
			);
		}

		$results = array();
		foreach ( $products as $product ) {
			$text = $product->post_title;
			if ( ! empty( $product->sku ) ) {
				$text .= ' (SKU: ' . $product->sku . ')';
			}
			$text .= ' [ID: ' . $product->ID . ']';

			$results[] = array(
				'id'   => $product->ID,
				'text' => $text,
			);
		}

		wp_send_json_success( array( 'data' => $results ) );
	}

	/**
	 * Search categories via AJAX.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function search_categories() {
		// Check nonce - support both old and new nonce names
		$nonce_verified = false;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( isset( $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'dt-search-nonce' ) ) {
			$nonce_verified = true;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		} elseif ( isset( $_GET['security'] ) && wp_verify_nonce( wp_unslash( $_GET['security'] ), 'dt-condition-builder' ) ) {
			$nonce_verified = true;
		}
		
		if ( ! $nonce_verified ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'discount-tools' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		$search_term  = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$selected_raw = isset( $_GET['selected'] ) ? sanitize_text_field( wp_unslash( $_GET['selected'] ) ) : '';
		$selected_ids = array_filter( array_map( 'absint', preg_split( '/[,\s]+/', $selected_raw ) ) );

		if ( empty( $selected_ids ) && $this->dt_strlen( $search_term ) < 2 ) {
			wp_send_json_success( array( 'data' => array() ) );
		}

		$taxonomy = 'product_cat';
		if ( isset( $_GET['taxonomy'] ) ) {
			$requested_taxonomy = sanitize_key( wp_unslash( $_GET['taxonomy'] ) );
			if ( in_array( $requested_taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
				$taxonomy = $requested_taxonomy;
			}
		}

		// Search categories or tags based on taxonomy
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 20,
		);

		if ( ! empty( $selected_ids ) ) {
			$args['include'] = $selected_ids;
			$args['orderby'] = 'include';
		} else {
			$args['name__like'] = $search_term;
			$args['orderby']    = 'name';
			$args['order']      = 'ASC';
		}

		$categories = get_terms( $args );

		if ( is_wp_error( $categories ) ) {
			wp_send_json_error( array( 'message' => $categories->get_error_message() ) );
		}

		$results = array();
		foreach ( $categories as $category ) {
			$text = $category->name;

			// Add parent hierarchy
			if ( 'product_cat' === $taxonomy && $category->parent ) {
				$parent = get_term( $category->parent, $taxonomy );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$text = $parent->name . ' > ' . $text;
				}
			}

			$text .= ' [ID: ' . $category->term_id . ']';

			$results[] = array(
				'id'   => $category->term_id,
				'text' => $text,
			);
		}

		wp_send_json_success( array( 'data' => $results ) );
	}

	/**
	 * Search brands via AJAX.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function search_brands() {
		// Check nonce - support both old and new nonce names
		$nonce_verified = false;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( isset( $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'dt-search-nonce' ) ) {
			$nonce_verified = true;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		} elseif ( isset( $_GET['security'] ) && wp_verify_nonce( wp_unslash( $_GET['security'] ), 'dt-condition-builder' ) ) {
			$nonce_verified = true;
		}
		
		if ( ! $nonce_verified ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'discount-tools' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		// Ensure Brand Detector is loaded
		if ( ! class_exists( 'Discount_Tools_Brand_Detector' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-brand-detector.php';
		}

		// Get brand taxonomy
		$brand_taxonomy = Discount_Tools_Brand_Detector::get_brand_taxonomy();
		if ( ! $brand_taxonomy ) {
			wp_send_json_error( array( 'message' => __( 'Brand taxonomy not found', 'discount-tools' ) ) );
		}

		$search_term  = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$selected_raw = isset( $_GET['selected'] ) ? sanitize_text_field( wp_unslash( $_GET['selected'] ) ) : '';
		$selected_ids = array_filter( array_map( 'absint', preg_split( '/[,\s]+/', $selected_raw ) ) );

		if ( empty( $selected_ids ) && $this->dt_strlen( $search_term ) < 2 ) {
			wp_send_json_success( array( 'data' => array() ) );
		}

		// Search brands
		$args = array(
			'taxonomy'   => $brand_taxonomy,
			'hide_empty' => false,
			'number'     => 20,
		);

		if ( ! empty( $selected_ids ) ) {
			$args['include'] = $selected_ids;
			$args['orderby'] = 'include';
		} else {
			$args['name__like'] = $search_term;
			$args['orderby']    = 'name';
			$args['order']      = 'ASC';
		}

		$brands = get_terms( $args );

		if ( is_wp_error( $brands ) ) {
			wp_send_json_error( array( 'message' => $brands->get_error_message() ) );
		}

		$results = array();
		foreach ( $brands as $brand ) {
			$results[] = array(
				'id'   => $brand->term_id,
				'text' => $brand->name . ' [ID: ' . $brand->term_id . ']',
			);
		}

		wp_send_json_success( array( 'data' => $results ) );
	}

	/**
	 * Search customer emails via AJAX.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function search_customer_emails() {
		// Check nonce
		$nonce_verified = false;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( isset( $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'dt-search-nonce' ) ) {
			$nonce_verified = true;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		} elseif ( isset( $_GET['security'] ) && wp_verify_nonce( wp_unslash( $_GET['security'] ), 'dt-condition-builder' ) ) {
			$nonce_verified = true;
		}
		
		if ( ! $nonce_verified ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'discount-tools' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

		if ( strlen( $query ) < 1 ) {
			wp_send_json_success( array( 'data' => array() ) );
		}

		global $wpdb;

		// Search in WordPress users and WooCommerce customers
		$emails = array();
		
		// Search WordPress users
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query required.
		$users = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_email 
				FROM {$wpdb->users} 
				WHERE user_email LIKE %s 
				ORDER BY user_email ASC 
				LIMIT 20",
				'%' . $wpdb->esc_like( $query ) . '%'
			)
		);
		
		if ( ! empty( $users ) ) {
			$emails = array_merge( $emails, $users );
		}
		
		// Search WooCommerce billing emails (for guest customers)
		if ( class_exists( 'WooCommerce' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query required.
			$billing_emails = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT meta_value as email 
					FROM {$wpdb->postmeta} 
					WHERE meta_key = '_billing_email' 
					AND meta_value LIKE %s 
					ORDER BY meta_value ASC 
					LIMIT 20",
					'%' . $wpdb->esc_like( $query ) . '%'
				)
			);
			
			if ( ! empty( $billing_emails ) ) {
				$emails = array_merge( $emails, $billing_emails );
			}
		}
		
		// Remove duplicates and limit to 20
		$emails = array_unique( $emails );
		$emails = array_slice( $emails, 0, 20 );
		sort( $emails );

		wp_send_json_success( $emails );
	}

	/**
	 * Toggle rule status via AJAX.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function toggle_rule_status() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! isset( $_POST['rule_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'discount-tools' ) ) );
		}

		$rule_id = absint( $_POST['rule_id'] );
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to wp_verify_nonce for verification.
		if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'toggle_rule_' . $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'discount-tools' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		$action = isset( $_POST['toggle_action'] ) ? sanitize_text_field( wp_unslash( $_POST['toggle_action'] ) ) : '';

		if ( ! in_array( $action, array( 'activate', 'deactivate' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'discount-tools' ) ) );
		}

		// Load repository
		require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-rule-repository.php';
		$repository = new Discount_Tools_Rule_Repository();
		$rule = $repository->find( $rule_id );

		if ( ! $rule ) {
			wp_send_json_error( array( 'message' => __( 'Rule not found.', 'discount-tools' ) ) );
		}

		// Update status
		$new_status = $action === 'activate' ? 'active' : 'inactive';
		
		// Update directly in database
		global $wpdb;
		$table = $wpdb->prefix . 'dt_rules';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query required.
		$result = $wpdb->update(
			$table,
			array( 'status' => $new_status ),
			array( 'id' => $rule_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Clear cache for this rule
			require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/repository/class-cache-manager.php';
			$cache_manager = Discount_Tools_Cache_Manager::get_instance();
			$cache_manager->delete_rule( $rule_id );
			$cache_manager->flush_all(); // Also flush all caches to ensure consistency

			$message = $action === 'activate' ?
				__( 'Rule activated successfully.', 'discount-tools' ) :
				__( 'Rule deactivated successfully.', 'discount-tools' );

			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update rule status.', 'discount-tools' ) ) );
		}
	}

	/**
	 * Reset usage count via AJAX.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function reset_usage_count() {
		// Check nonce
		check_ajax_referer( 'discount_tools_admin_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'discount-tools' ) ) );
		}

		// Get rule ID
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		if ( ! $rule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rule ID.', 'discount-tools' ) ) );
		}

		// Reset usage count in database
		global $wpdb;
		$table = $wpdb->prefix . 'dt_rules';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query required.
		$result = $wpdb->update(
			$table,
			array( 'usage_count' => 0 ),
			array( 'id' => $rule_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			wp_send_json_success( array( 
				'message' => __( 'Usage count has been reset to zero.', 'discount-tools' ),
				'usage_count' => 0
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reset usage count.', 'discount-tools' ) ) );
		}
	}
}
