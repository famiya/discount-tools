<?php
/**
 * Brand Taxonomy Detector
 *
 * Automatically detects which brand taxonomy is being used on the site.
 * Supports common brand plugins and custom taxonomies.
 *
 * @package Discount_Tools
 * @subpackage Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Brand Detector Class
 */
class Discount_Tools_Brand_Detector {

	/**
	 * Transient key for caching detected taxonomy
	 *
	 * @var string
	 */
	const CACHE_KEY = 'dt_brand_taxonomy';

	/**
	 * Cache expiration time (12 hours)
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 12 * HOUR_IN_SECONDS;

	/**
	 * Known brand taxonomy names (in order of preference)
	 *
	 * @var array
	 */
	private static $known_taxonomies = array(
		'product_brand',           // WooCommerce Brands, Perfect Brands
		'yith_product_brand',      // YITH WooCommerce Brands
		'pwb-brand',               // Perfect WooCommerce Brands
		'pa_brand',                // WooCommerce Product Attribute
		'brands',                  // Generic brand taxonomy
	);

	/**
	 * Get the detected brand taxonomy
	 *
	 * @return string|false Taxonomy name or false if not found
	 */
	public static function get_brand_taxonomy() {
		// Check cache first
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return '' === $cached ? false : $cached;
		}

		// Detect taxonomy
		$taxonomy = self::detect_taxonomy();

		// Cache the result (empty string for false to distinguish from no cache)
		set_transient( self::CACHE_KEY, $taxonomy ? $taxonomy : '', self::CACHE_EXPIRATION );

		return $taxonomy;
	}

	/**
	 * Detect which brand taxonomy is available
	 *
	 * @return string|false Taxonomy name or false if not found
	 */
	private static function detect_taxonomy() {
		// Check known taxonomies first
		foreach ( self::$known_taxonomies as $taxonomy ) {
			if ( self::taxonomy_exists_and_has_terms( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		// Search for custom taxonomies containing "brand" in the name
		$product_taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $product_taxonomies as $taxonomy ) {
			if ( false !== stripos( $taxonomy->name, 'brand' ) ) {
				if ( self::taxonomy_exists_and_has_terms( $taxonomy->name ) ) {
					return $taxonomy->name;
				}
			}
		}

		return false;
	}

	/**
	 * Check if taxonomy exists and has terms
	 *
	 * @param string $taxonomy Taxonomy name
	 * @return bool True if taxonomy exists and has at least one term
	 */
	private static function taxonomy_exists_and_has_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		// Check if taxonomy is registered for products
		$object_types = get_taxonomy( $taxonomy )->object_type;
		if ( ! in_array( 'product', $object_types, true ) ) {
			return false;
		}

		// Check if it has any terms
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);

		return ! empty( $terms ) && ! is_wp_error( $terms );
	}

	/**
	 * Check if a brand taxonomy is available
	 *
	 * @return bool True if brand taxonomy is available
	 */
	public static function has_brand_taxonomy() {
		return false !== self::get_brand_taxonomy();
	}

	/**
	 * Clear the cached brand taxonomy
	 * Useful after installing/uninstalling brand plugins
	 *
	 * @return void
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Get all available brand taxonomies (for debugging)
	 *
	 * @return array Array of taxonomy names
	 */
	public static function get_all_brand_taxonomies() {
		$found = array();

		// Check known taxonomies
		foreach ( self::$known_taxonomies as $taxonomy ) {
			if ( self::taxonomy_exists_and_has_terms( $taxonomy ) ) {
				$found[] = $taxonomy;
			}
		}

		// Search custom taxonomies
		$product_taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $product_taxonomies as $taxonomy ) {
			if ( false !== stripos( $taxonomy->name, 'brand' ) && ! in_array( $taxonomy->name, $found, true ) ) {
				if ( self::taxonomy_exists_and_has_terms( $taxonomy->name ) ) {
					$found[] = $taxonomy->name;
				}
			}
		}

		return $found;
	}
}
