<?php
/**
 * Rule Model Class
 *
 * Represents a discount rule with all properties and business logic.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/models
 */

/**
 * Rule model class.
 *
 * Encapsulates all rule data and business logic without database operations.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/models
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Rule {

	/**
	 * Rule ID.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Rule name.
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Rule description.
	 *
	 * @var string
	 */
	private $description = '';

	/**
	 * Discount type (percentage, fixed, price_override).
	 *
	 * @var string
	 */
	private $discount_type = 'percentage';

	/**
	 * Discount value.
	 *
	 * @var float
	 */
	private $discount_value = 0.0;

	/**
	 * Discount subtype (for bundle, bxgy, etc.).
	 *
	 * @var string
	 */
	private $discount_subtype = '';

	/**
	 * Rule type (product, cart, bulk, bogo, role_based).
	 *
	 * @var string
	 */
	private $rule_type = 'product';

	/**
	 * Rule status (active, inactive, scheduled).
	 *
	 * @var string
	 */
	private $status = 'active';

	/**
	 * Rule priority (lower number = higher priority).
	 *
	 * @var int
	 */
	private $priority = 10;

	/**
	 * Start date (null for no start date).
	 *
	 * @var string|null
	 */
	private $start_date = null;

	/**
	 * End date (null for no end date).
	 *
	 * @var string|null
	 */
	private $end_date = null;

	/**
	 * Usage limit (null for unlimited).
	 *
	 * @var int|null
	 */
	private $usage_limit = null;

	/**
	 * Usage count.
	 *
	 * @var int
	 */
	private $usage_count = 0;

	/**
	 * Per-user usage limit (null for unlimited).
	 *
	 * @var int|null
	 */
	private $usage_limit_per_user = null;

	/**
	 * Apply mode (first, all, best).
	 *
	 * @var string
	 */
	private $apply_mode = 'first';

	/**
	 * Date created.
	 *
	 * @var string
	 */
	private $date_created = '';

	/**
	 * Date modified.
	 *
	 * @var string
	 */
	private $date_modified = '';

	/**
	 * Rule conditions.
	 *
	 * @var array
	 */
	private $conditions = array();

	/**
	 * Rule metadata.
	 *
	 * @var array
	 */
	private $meta = array();

	/**
	 * BXGY: Buy quantity (X).
	 *
	 * @var int|null
	 */
	private $bxgy_buy_quantity = null;

	/**
	 * BXGY: Get quantity (Y).
	 *
	 * @var int|null
	 */
	private $bxgy_get_quantity = null;

	/**
	 * BXGY: Get discount amount.
	 *
	 * @var float|null
	 */
	private $bxgy_get_discount = null;

	/**
	 * BXGY: Get discount type.
	 *
	 * @var string|null
	 */
	private $bxgy_get_type = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array $data Rule data.
	 */
	public function __construct( $data = array() ) {
		if ( ! empty( $data ) ) {
			$this->set_data( $data );
		}
	}

	/**
	 * Set rule data from array.
	 *
	 * @since 1.0.0
	 * @param array $data Rule data.
	 */
	public function set_data( $data ) {
		$properties = array(
			'id',
			'name',
			'description',
			'discount_type',
			'discount_value',
			'rule_type',
			'status',
			'priority',
			'start_date',
			'end_date',
			'usage_limit',
			'usage_count',
			'usage_limit_per_user',
			'apply_mode',
			'date_created',
			'date_modified',
			'conditions',
			'meta',
		);

		foreach ( $properties as $property ) {
			if ( isset( $data[ $property ] ) ) {
				$method = 'set_' . $property;
				if ( method_exists( $this, $method ) ) {
					$this->$method( $data[ $property ] );
				}
			}
		}
	}

	/**
	 * Get rule data as array.
	 *
	 * @since  1.0.0
	 * @return array Rule data.
	 */
	public function get_data() {
		return array(
			'id'             => $this->id,
			'name'           => $this->name,
			'description'    => $this->description,
			'discount_type'  => $this->discount_type,
			'discount_value' => $this->discount_value,
			'rule_type'      => $this->rule_type,
			'status'         => $this->status,
			'priority'       => $this->priority,
			'start_date'     => $this->start_date,
			'end_date'       => $this->end_date,
			'usage_limit'    => $this->usage_limit,
			'usage_count'    => $this->usage_count,
			'usage_limit_per_user' => $this->usage_limit_per_user,
			'apply_mode'     => $this->apply_mode,
			'date_created'   => $this->date_created,
			'date_modified'  => $this->date_modified,
			'conditions'     => $this->conditions,
			'meta'           => $this->meta,
		);
	}

	// Getters and Setters.

	/**
	 * Get rule ID.
	 *
	 * @since  1.0.0
	 * @return int Rule ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set rule ID.
	 *
	 * @since 1.0.0
	 * @param int $id Rule ID.
	 */
	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	/**
	 * Get rule name.
	 *
	 * @since  1.0.0
	 * @return string Rule name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set rule name.
	 *
	 * @since 1.0.0
	 * @param string $name Rule name.
	 */
	public function set_name( $name ) {
		$this->name = sanitize_text_field( $name );
	}

	/**
	 * Get rule description.
	 *
	 * @since  1.0.0
	 * @return string Rule description.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Set rule description.
	 *
	 * @since 1.0.0
	 * @param string $description Rule description.
	 */
	public function set_description( $description ) {
		$this->description = sanitize_textarea_field( $description );
	}

	/**
	 * Get discount type.
	 *
	 * @since  1.0.0
	 * @return string Discount type.
	 */
	public function get_discount_type() {
		return $this->discount_type;
	}

	/**
	 * Set discount type.
	 *
	 * @since 1.0.0
	 * @param string $type Discount type.
	 */
	public function set_discount_type( $type ) {
		$allowed_types = array( 'percentage', 'fixed', 'price_override', 'fixed_amount', 'bxgy_same', 'bxgy_any', 'bundle', 'tiered', 'bulk' );
		if ( in_array( $type, $allowed_types, true ) ) {
			$this->discount_type = $type;
		}
	}

	/**
	 * Get discount value.
	 *
	 * @since  1.0.0
	 * @return float Discount value.
	 */
	public function get_discount_value() {
		return $this->discount_value;
	}

	/**
	 * Set discount value.
	 *
	 * @since 1.0.0
	 * @param float $value Discount value.
	 */
	public function set_discount_value( $value ) {
		$this->discount_value = floatval( $value );
	}

	/**
	 * Get rule type.
	 *
	 * @since  1.0.0
	 * @return string Rule type.
	 */
	public function get_rule_type() {
		return $this->rule_type;
	}

	/**
	 * Set rule type.
	 *
	 * @since 1.0.0
	 * @param string $type Rule type.
	 */
	public function set_rule_type( $type ) {
		$allowed_types = array( 'product', 'cart', 'bulk', 'bogo', 'role_based' );
		if ( in_array( $type, $allowed_types, true ) ) {
			$this->rule_type = $type;
		}
	}

	/**
	 * Get status.
	 *
	 * @since  1.0.0
	 * @return string Status.
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Set status.
	 *
	 * @since 1.0.0
	 * @param string $status Status.
	 */
	public function set_status( $status ) {
		$allowed_statuses = array( 'active', 'inactive', 'scheduled' );
		if ( in_array( $status, $allowed_statuses, true ) ) {
			$this->status = $status;
		}
	}

	/**
	 * Get priority.
	 *
	 * @since  1.0.0
	 * @return int Priority.
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Set priority.
	 *
	 * @since 1.0.0
	 * @param int $priority Priority.
	 */
	public function set_priority( $priority ) {
		$this->priority = absint( $priority );
	}

	/**
	 * Get start date.
	 *
	 * @since  1.0.0
	 * @return string|null Start date.
	 */
	public function get_start_date() {
		return $this->start_date;
	}

	/**
	 * Set start date.
	 *
	 * @since 1.0.0
	 * @param string|null $date Start date.
	 */
	public function set_start_date( $date ) {
		$this->start_date = $date ? sanitize_text_field( $date ) : null;
	}

	/**
	 * Get end date.
	 *
	 * @since  1.0.0
	 * @return string|null End date.
	 */
	public function get_end_date() {
		return $this->end_date;
	}

	/**
	 * Set end date.
	 *
	 * @since 1.0.0
	 * @param string|null $date End date.
	 */
	public function set_end_date( $date ) {
		$this->end_date = $date ? sanitize_text_field( $date ) : null;
	}

	/**
	 * Get usage limit.
	 *
	 * @since  1.0.0
	 * @return int|null Usage limit.
	 */
	public function get_usage_limit() {
		return $this->usage_limit;
	}

	/**
	 * Set usage limit.
	 *
	 * @since 1.0.0
	 * @param int|null $limit Usage limit.
	 */
	public function set_usage_limit( $limit ) {
		$this->usage_limit = $limit ? absint( $limit ) : null;
	}

	/**
	 * Get usage count.
	 *
	 * @since  1.0.0
	 * @return int Usage count.
	 */
	public function get_usage_count() {
		return $this->usage_count;
	}

	/**
	 * Set usage count.
	 *
	 * @since 1.0.0
	 * @param int $count Usage count.
	 */
	public function set_usage_count( $count ) {
		$this->usage_count = absint( $count );
	}

	/**
	 * Increment usage count.
	 *
	 * @since 1.0.0
	 */
	public function increment_usage_count() {
		$this->usage_count++;
	}

	/**
	 * Get per-user usage limit.
	 *
	 * @since  1.0.0
	 * @return int|null Per-user usage limit.
	 */
	public function get_usage_limit_per_user() {
		return $this->usage_limit_per_user;
	}

	/**
	 * Set per-user usage limit.
	 *
	 * @since 1.0.0
	 * @param int|null $limit Per-user usage limit.
	 */
	public function set_usage_limit_per_user( $limit ) {
		$this->usage_limit_per_user = $limit ? absint( $limit ) : null;
	}

	/**
	 * Get apply mode.
	 *
	 * @since  1.0.0
	 * @return string Apply mode.
	 */
	public function get_apply_mode() {
		return $this->apply_mode;
	}

	/**
	 * Set apply mode.
	 *
	 * @since 1.0.0
	 * @param string $mode Apply mode.
	 */
	public function set_apply_mode( $mode ) {
		$allowed_modes = array( 'first', 'all', 'best' );
		if ( in_array( $mode, $allowed_modes, true ) ) {
			$this->apply_mode = $mode;
		}
	}

	/**
	 * Get date created.
	 *
	 * @since  1.0.0
	 * @return string Date created.
	 */
	public function get_date_created() {
		return $this->date_created;
	}

	/**
	 * Set date created.
	 *
	 * @since 1.0.0
	 * @param string $date Date created.
	 */
	public function set_date_created( $date ) {
		$this->date_created = sanitize_text_field( $date );
	}

	/**
	 * Get date modified.
	 *
	 * @since  1.0.0
	 * @return string Date modified.
	 */
	public function get_date_modified() {
		return $this->date_modified;
	}

	/**
	 * Set date modified.
	 *
	 * @since 1.0.0
	 * @param string $date Date modified.
	 */
	public function set_date_modified( $date ) {
		$this->date_modified = sanitize_text_field( $date );
	}

	/**
	 * Get conditions.
	 *
	 * @since  1.0.0
	 * @return array Conditions.
	 */
	public function get_conditions() {
		return $this->conditions;
	}

	/**
	 * Set conditions.
	 *
	 * @since 1.0.0
	 * @param array $conditions Conditions.
	 */
	public function set_conditions( $conditions ) {
		$this->conditions = is_array( $conditions ) ? $conditions : array();
	}

	/**
	 * Add a condition.
	 *
	 * @since 1.0.0
	 * @param array $condition Condition data.
	 */
	public function add_condition( $condition ) {
		$this->conditions[] = $condition;
	}

	/**
	 * Get metadata.
	 *
	 * @since  1.0.0
	 * @return array Metadata.
	 */
	public function get_meta() {
		return $this->meta;
	}

	/**
	 * Set metadata.
	 *
	 * @since 1.0.0
	 * @param array $meta Metadata.
	 */
	public function set_meta( $meta ) {
		$this->meta = is_array( $meta ) ? $meta : array();
	}

	/**
	 * Get a single meta value.
	 *
	 * @since  1.0.0
	 * @param  string $key     Meta key.
	 * @param  mixed  $default Default value.
	 * @return mixed           Meta value.
	 */
	public function get_meta_value( $key, $default = null ) {
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : $default;
	}

	/**
	 * Set a single meta value.
	 *
	 * @since 1.0.0
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 */
	public function set_meta_value( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	// Business Logic Methods.

	/**
	 * Check if rule is active.
	 *
	 * @since  1.0.0
	 * @return bool True if active, false otherwise.
	 */
	public function is_active() {
		// Check status.
		if ( 'active' !== $this->status ) {
			return false;
		}

		// Check date range.
		if ( ! $this->is_within_date_range() ) {
			return false;
		}

		// Check usage limit.
		if ( ! $this->is_within_usage_limit() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if rule is within date range.
	 *
	 * @since  1.0.0
	 * @return bool True if within range, false otherwise.
	 */
	public function is_within_date_range() {
		$now = current_time( 'timestamp' );

		// Check start date.
		if ( $this->start_date ) {
			$start = strtotime( $this->start_date );
			if ( $now < $start ) {
				return false;
			}
		}

		// Check end date.
		if ( $this->end_date ) {
			$end = strtotime( $this->end_date );
			if ( $now > $end ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if rule is within usage limit.
	 *
	 * @since  1.0.0
	 * @return bool True if within limit, false otherwise.
	 */
	public function is_within_usage_limit() {
		if ( null === $this->usage_limit ) {
			return true; // No limit.
		}

		return $this->usage_count < $this->usage_limit;
	}

	/**
	 * Validate rule data.
	 *
	 * @since  1.0.0
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate() {
		// Check required fields.
		if ( empty( $this->name ) ) {
			return new WP_Error( 'empty_name', __( 'Rule name is required.', 'discount-tools' ) );
		}

		if ( empty( $this->discount_type ) ) {
			return new WP_Error( 'empty_discount_type', __( 'Discount type is required.', 'discount-tools' ) );
		}

		if ( $this->discount_value < 0 ) {
			return new WP_Error( 'invalid_discount_value', __( 'Discount value cannot be negative.', 'discount-tools' ) );
		}

		// Validate percentage discount.
		if ( 'percentage' === $this->discount_type && $this->discount_value > 100 ) {
			return new WP_Error( 'invalid_percentage', __( 'Percentage discount cannot exceed 100%.', 'discount-tools' ) );
		}

		// Validate date range.
		if ( $this->start_date && $this->end_date ) {
			if ( strtotime( $this->start_date ) > strtotime( $this->end_date ) ) {
				return new WP_Error( 'invalid_date_range', __( 'Start date must be before end date.', 'discount-tools' ) );
			}
		}

		// Validate usage limit.
		if ( null !== $this->usage_limit && $this->usage_limit < 1 ) {
			return new WP_Error( 'invalid_usage_limit', __( 'Usage limit must be at least 1.', 'discount-tools' ) );
		}

		return true;
	}

	/**
	 * Check if rule can be stacked with other rules.
	 *
	 * A rule is stackable if its apply_mode is 'all'.
	 *
	 * @since  1.0.0
	 * @return bool True if stackable, false otherwise.
	 */
	public function is_stackable() {
		return $this->apply_mode === 'all';
	}

	/**
	 * Get stackable status (alias for is_stackable).
	 *
	 * @since  1.0.0
	 * @return bool True if stackable, false otherwise.
	 */
	public function get_stackable() {
		return $this->is_stackable();
	}

	/**
	 * Get maximum discount amount constraint.
	 *
	 * @since  1.0.0
	 * @return float|null Maximum discount or null if no constraint.
	 */
	public function get_max_discount() {
		return $this->get_meta_value( 'max_discount', null );
	}

	/**
	 * Get minimum discount amount constraint.
	 *
	 * @since  1.0.0
	 * @return float|null Minimum discount or null if no constraint.
	 */
	public function get_min_discount() {
		return $this->get_meta_value( 'min_discount', null );
	}

	/**
	 * Get discount subtype.
	 *
	 * @since  1.0.0
	 * @return string Discount subtype.
	 */
	public function get_discount_subtype() {
		return $this->discount_subtype;
	}

	/**
	 * Set discount subtype.
	 *
	 * @since 1.0.0
	 * @param string $subtype Discount subtype.
	 */
	public function set_discount_subtype( $subtype ) {
		$this->discount_subtype = sanitize_text_field( $subtype );
	}

	/**
	 * Get BXGY buy quantity.
	 *
	 * @since  1.0.0
	 * @return int|null Buy quantity (X).
	 */
	public function get_bxgy_buy_quantity() {
		return $this->bxgy_buy_quantity;
	}

	/**
	 * Set BXGY buy quantity.
	 *
	 * @since 1.0.0
	 * @param int|null $quantity Buy quantity.
	 */
	public function set_bxgy_buy_quantity( $quantity ) {
		$this->bxgy_buy_quantity = $quantity !== null ? max( 1, intval( $quantity ) ) : null;
	}

	/**
	 * Get BXGY get quantity.
	 *
	 * @since  1.0.0
	 * @return int|null Get quantity (Y).
	 */
	public function get_bxgy_get_quantity() {
		return $this->bxgy_get_quantity;
	}

	/**
	 * Set BXGY get quantity.
	 *
	 * @since 1.0.0
	 * @param int|null $quantity Get quantity.
	 */
	public function set_bxgy_get_quantity( $quantity ) {
		$this->bxgy_get_quantity = $quantity !== null ? max( 1, intval( $quantity ) ) : null;
	}

	/**
	 * Get BXGY get discount.
	 *
	 * @since  1.0.0
	 * @return float|null Get discount amount.
	 */
	public function get_bxgy_get_discount() {
		return $this->bxgy_get_discount;
	}

	/**
	 * Set BXGY get discount.
	 *
	 * @since 1.0.0
	 * @param float|null $discount Get discount amount.
	 */
	public function set_bxgy_get_discount( $discount ) {
		$this->bxgy_get_discount = $discount !== null ? floatval( $discount ) : null;
	}

	/**
	 * Get BXGY get type.
	 *
	 * @since  1.0.0
	 * @return string|null Get discount type.
	 */
	public function get_bxgy_get_type() {
		return $this->bxgy_get_type;
	}

	/**
	 * Set BXGY get type.
	 *
	 * @since 1.0.0
	 * @param string|null $type Get discount type.
	 */
	public function set_bxgy_get_type( $type ) {
		$this->bxgy_get_type = $type !== null ? sanitize_text_field( $type ) : null;
	}

	/**
	 * Check if rule is applicable.
	 *
	 * This is a placeholder for more complex applicability logic.
	 * Full implementation will be in the Rule Engine (Task 11).
	 *
	 * @since  1.0.0
	 * @return bool True if applicable, false otherwise.
	 */
	public function is_applicable() {
		return $this->is_active();
	}
}
