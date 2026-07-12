<?php
/**
 * Discount Result Model Class
 *
 * Represents the result of applying a discount rule.
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/models
 */

/**
 * Discount result model class.
 *
 * Encapsulates the calculated discount information.
 *
 * @since      1.0.0
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/models
 * @author     Hugo Shih <hugo@hugoshih.eu.org>
 */
class Discount_Tools_Discount {

	/**
	 * Rule ID that generated this discount.
	 *
	 * @var int
	 */
	private $rule_id = 0;

	/**
	 * Rule name.
	 *
	 * @var string
	 */
	private $rule_name = '';

	/**
	 * Product ID (null for cart-level discounts).
	 *
	 * @var int|null
	 */
	private $product_id = null;

	/**
	 * Original price.
	 *
	 * @var float
	 */
	private $original_price = 0.0;

	/**
	 * Discount amount.
	 *
	 * @var float
	 */
	private $discount_amount = 0.0;

	/**
	 * Final price after discount.
	 *
	 * @var float
	 */
	private $final_price = 0.0;

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
	 * Quantity.
	 *
	 * @var int
	 */
	private $quantity = 1;

	/**
	 * Additional metadata.
	 *
	 * @var array
	 */
	private $meta = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array $data Discount data.
	 */
	public function __construct( $data = array() ) {
		if ( ! empty( $data ) ) {
			$this->set_data( $data );
		}
	}

	/**
	 * Set discount data from array.
	 *
	 * @since 1.0.0
	 * @param array $data Discount data.
	 */
	public function set_data( $data ) {
		$properties = array(
			'rule_id',
			'rule_name',
			'product_id',
			'original_price',
			'discount_amount',
			'final_price',
			'discount_type',
			'discount_value',
			'quantity',
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
	 * Get discount data as array.
	 *
	 * @since  1.0.0
	 * @return array Discount data.
	 */
	public function get_data() {
		return array(
			'rule_id'         => $this->rule_id,
			'rule_name'       => $this->rule_name,
			'product_id'      => $this->product_id,
			'original_price'  => $this->original_price,
			'discount_amount' => $this->discount_amount,
			'final_price'     => $this->final_price,
			'discount_type'   => $this->discount_type,
			'discount_value'  => $this->discount_value,
			'quantity'        => $this->quantity,
			'meta'            => $this->meta,
		);
	}

	// Getters and Setters.

	/**
	 * Get rule ID.
	 *
	 * @since  1.0.0
	 * @return int Rule ID.
	 */
	public function get_rule_id() {
		return $this->rule_id;
	}

	/**
	 * Set rule ID.
	 *
	 * @since 1.0.0
	 * @param int $id Rule ID.
	 */
	public function set_rule_id( $id ) {
		$this->rule_id = absint( $id );
	}

	/**
	 * Get rule name.
	 *
	 * @since  1.0.0
	 * @return string Rule name.
	 */
	public function get_rule_name() {
		return $this->rule_name;
	}

	/**
	 * Set rule name.
	 *
	 * @since 1.0.0
	 * @param string $name Rule name.
	 */
	public function set_rule_name( $name ) {
		$this->rule_name = sanitize_text_field( $name );
	}

	/**
	 * Get product ID.
	 *
	 * @since  1.0.0
	 * @return int|null Product ID.
	 */
	public function get_product_id() {
		return $this->product_id;
	}

	/**
	 * Set product ID.
	 *
	 * @since 1.0.0
	 * @param int|null $id Product ID.
	 */
	public function set_product_id( $id ) {
		$this->product_id = $id ? absint( $id ) : null;
	}

	/**
	 * Get original price.
	 *
	 * @since  1.0.0
	 * @return float Original price.
	 */
	public function get_original_price() {
		return $this->original_price;
	}

	/**
	 * Set original price.
	 *
	 * @since 1.0.0
	 * @param float $price Original price.
	 */
	public function set_original_price( $price ) {
		$this->original_price = floatval( $price );
	}

	/**
	 * Get discount amount.
	 *
	 * @since  1.0.0
	 * @return float Discount amount.
	 */
	public function get_discount_amount() {
		return $this->discount_amount;
	}

	/**
	 * Set discount amount.
	 *
	 * @since 1.0.0
	 * @param float $amount Discount amount.
	 */
	public function set_discount_amount( $amount ) {
		$this->discount_amount = floatval( $amount );
	}

	/**
	 * Get final price.
	 *
	 * @since  1.0.0
	 * @return float Final price.
	 */
	public function get_final_price() {
		return $this->final_price;
	}

	/**
	 * Set final price.
	 *
	 * @since 1.0.0
	 * @param float $price Final price.
	 */
	public function set_final_price( $price ) {
		$this->final_price = floatval( $price );
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
		$this->discount_type = sanitize_text_field( $type );
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
	 * Get quantity.
	 *
	 * @since  1.0.0
	 * @return int Quantity.
	 */
	public function get_quantity() {
		return $this->quantity;
	}

	/**
	 * Set quantity.
	 *
	 * @since 1.0.0
	 * @param int $quantity Quantity.
	 */
	public function set_quantity( $quantity ) {
		$this->quantity = absint( $quantity );
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
	 * Calculate discount amount based on original price and discount parameters.
	 *
	 * @since 1.0.0
	 */
	public function calculate() {
		switch ( $this->discount_type ) {
			case 'percentage':
				$this->discount_amount = ( $this->original_price * $this->discount_value ) / 100;
				$this->final_price     = $this->original_price - $this->discount_amount;
				break;

			case 'fixed':
				$this->discount_amount = min( $this->discount_value, $this->original_price );
				$this->final_price     = $this->original_price - $this->discount_amount;
				break;

			case 'price_override':
				$this->discount_amount = $this->original_price - $this->discount_value;
				$this->final_price     = $this->discount_value;
				break;

			default:
				$this->discount_amount = 0;
				$this->final_price     = $this->original_price;
				break;
		}

		// Ensure final price is never negative.
		if ( $this->final_price < 0 ) {
			$this->discount_amount = $this->original_price;
			$this->final_price     = 0;
		}
	}

	/**
	 * Get total discount amount (discount_amount * quantity).
	 *
	 * @since  1.0.0
	 * @return float Total discount.
	 */
	public function get_total_discount() {
		return $this->discount_amount * $this->quantity;
	}

	/**
	 * Get total final price (final_price * quantity).
	 *
	 * @since  1.0.0
	 * @return float Total final price.
	 */
	public function get_total_final_price() {
		return $this->final_price * $this->quantity;
	}

	/**
	 * Get discount percentage (0-100).
	 *
	 * @since  1.0.0
	 * @return float Discount percentage.
	 */
	public function get_discount_percentage() {
		if ( $this->original_price == 0 ) {
			return 0;
		}

		return ( $this->discount_amount / $this->original_price ) * 100;
	}

	/**
	 * Get formatted discount display.
	 *
	 * @since  1.0.0
	 * @param  bool $with_currency Whether to include currency symbol.
	 * @return string Formatted discount.
	 */
	public function get_formatted_discount( $with_currency = true ) {
		if ( 'percentage' === $this->discount_type ) {
			return number_format( $this->discount_value, 2 ) . '%';
		}

		if ( $with_currency ) {
			return wc_price( $this->discount_amount );
		}

		return number_format( $this->discount_amount, 2 );
	}

	/**
	 * Check if discount is valid.
	 *
	 * @since  1.0.0
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid() {
		return $this->discount_amount > 0 && $this->final_price >= 0;
	}
}
