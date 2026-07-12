<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
/**
 * Discount Table Template
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get style preference
$dt_style = isset( $dt_style ) ? $dt_style : 'table';

// Check if we should use custom messages from rules instead
$dt_use_custom_messages = false;
$dt_custom_messages = array();

foreach ( $dt_rules as $dt_rule ) {
	$dt_badge_text = $dt_rule->get_meta_value( 'display_badge_text', '' );
	if ( ! empty( $dt_badge_text ) ) {
		$dt_use_custom_messages = true;
		$dt_custom_messages[] = array(
			'rule_id'   => $dt_rule->get_id(),
			'rule_name' => $dt_rule->get_name(),
			'badge_text' => $dt_badge_text,
			'priority'  => $dt_rule->get_priority(),
		);
	}
}

// Sort by priority (lower number = higher priority = displays first)
if ( ! empty( $dt_custom_messages ) ) {
	usort( $dt_custom_messages, function( $a, $b ) {
		return $a['priority'] - $b['priority'];
	});
}

// If rules have custom messages, display them based on style
if ( $dt_use_custom_messages && ! empty( $dt_custom_messages ) ) {
	// Get settings
	$dt_text_color = \Discount_Tools\Admin\Settings::get( 'message_text_color', '#333333' );
	$dt_font_size = \Discount_Tools\Admin\Settings::get( 'message_font_size', 16 );
	$dt_badge_bg_color = \Discount_Tools\Admin\Settings::get( 'badge_background_color', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' );
	
	if ( $dt_style === 'text' ) {
		// Text style: dashicons-tag icon before each message line
		?>
		<div class="dt-discount-display dt-custom-messages dt-style-text" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 8px;">
			<?php foreach ( $dt_custom_messages as $dt_message ) : ?>
				<div style="display: flex; align-items: center; gap: 8px; color: <?php echo esc_attr( $dt_text_color ); ?>; font-size: <?php echo esc_attr( $dt_font_size ); ?>px; line-height: 1.8; font-weight: 500;">
					<span class="dashicons dashicons-tag" style="color: #ff6600; font-size: <?php echo esc_attr( $dt_font_size + 2 ); ?>px; width: auto; height: auto; flex-shrink: 0;"></span>
					<?php echo wp_kses_post( $dt_message['badge_text'] ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	} else {
		// Badge style: Individual badges with background color
		?>
		<div class="dt-discount-display dt-custom-messages dt-style-badge" style="margin: 20px 0;">
			<?php foreach ( $dt_custom_messages as $dt_message ) : ?>
				<div class="dt-custom-message-badge" style="display: inline-block; padding: 10px 20px; margin: 5px; background: <?php echo esc_attr( $dt_badge_bg_color ); ?>; border-radius: 25px; color: white; font-size: <?php echo esc_attr( $dt_font_size ); ?>px; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
					<?php echo wp_kses_post( $dt_message['badge_text'] ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
	return;
}

// 如果沒有自訂訊息，顯示簡單的預設信息
if ( ! $dt_use_custom_messages ) {
	// Get settings for styling
	$dt_text_color = \Discount_Tools\Admin\Settings::get( 'message_text_color', '#333333' );
	$dt_font_size = \Discount_Tools\Admin\Settings::get( 'message_font_size', 16 );
	$dt_badge_bg_color = \Discount_Tools\Admin\Settings::get( 'badge_background_color', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' );
	
	// 簡單的預設折扣信息
	?>
	<div class="dt-discount-display dt-default-message" style="margin: 15px 0; padding: 12px 20px; background: <?php echo esc_attr( $dt_badge_bg_color ); ?>; border-radius: 25px; text-align: center;">
		<span style="color: white; font-size: <?php echo esc_attr( $dt_font_size ); ?>px; font-weight: 600;">
			🎉 <?php esc_html_e( '享有優惠折扣！數量越多折扣越大！', 'discount-tools' ); ?>
		</span>
	</div>
	<?php
}

/**
 * Allow third-party customization
 *
 * @param WC_Product $product Product object
 * @param array      $dt_rules   Applied rules
 * @param string     $dt_style   Display style
 */
do_action( 'dt_after_discount_display', $product, $dt_rules, array(), $dt_style );
?>
