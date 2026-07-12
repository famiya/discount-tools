<?php
/**
 * Repository Interface
 *
 * Base interface for all repository classes.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 */

/**
 * Repository interface.
 *
 * Defines standard methods for data access layer.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/repository
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
interface Discount_Tools_Repository_Interface {

	/**
	 * Find by ID.
	 *
	 * @since  1.0.0
	 * @param  int $id Record ID.
	 * @return mixed   Record object or null.
	 */
	public function find( $id );

	/**
	 * Find all records.
	 *
	 * @since  1.0.0
	 * @param  array $args Query arguments.
	 * @return array       Array of record objects.
	 */
	public function find_all( $args = array() );

	/**
	 * Create a new record.
	 *
	 * @since  1.0.0
	 * @param  array $data Record data.
	 * @return int|false   Created record ID or false on failure.
	 */
	public function create( $data );

	/**
	 * Update an existing record.
	 *
	 * @since  1.0.0
	 * @param  int   $id   Record ID.
	 * @param  array $data Updated data.
	 * @return bool        True on success, false on failure.
	 */
	public function update( $id, $data );

	/**
	 * Delete a record.
	 *
	 * @since  1.0.0
	 * @param  int $id Record ID.
	 * @return bool    True on success, false on failure.
	 */
	public function delete( $id );

	/**
	 * Count records.
	 *
	 * @since  1.0.0
	 * @param  array $args Query arguments.
	 * @return int         Number of records.
	 */
	public function count( $args = array() );
}
