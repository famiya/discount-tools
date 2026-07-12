<?php
/**
 * Plugin Name:       Discount Tools
 * Plugin URI:        https://hugoshih.eu.org/discount-tools/
 * Description:       A powerful WooCommerce discount management plugin that helps you create dynamic pricing and discount rules.
 * Version:           1.2.0
 * Author:            Hugo Shih
 * Author URI:        https://hugoshih.eu.org
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       discount-tools
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.0
 *
 * @package Discount_Tools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 */
define( 'DISCOUNT_TOOLS_VERSION', '1.2.0' );
define( 'DISCOUNT_TOOLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DISCOUNT_TOOLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DISCOUNT_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-activator.php
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Activation/deactivation hooks require specific naming.
function activate_discount_tools() {
	require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/class-activator.php';
	Discount_Tools_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-deactivator.php
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Activation/deactivation hooks require specific naming.
function deactivate_discount_tools() {
	require_once DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/class-deactivator.php';
	Discount_Tools_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_discount_tools' );
register_deactivation_hook( __FILE__, 'deactivate_discount_tools' );

/**
 * Declare compatibility with WooCommerce features.
 *
 * @since 1.1.1
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require DISCOUNT_TOOLS_PLUGIN_DIR . 'includes/class-discount-tools.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 1.0.0
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Activation/deactivation hooks require specific naming.
function run_discount_tools() {
	global $discount_tools;
	$discount_tools = new Discount_Tools();
	$discount_tools->run();
}

// Check if WooCommerce is active before running the plugin
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Checking WP core hook.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	run_discount_tools();
} else {
	// Display admin notice if WooCommerce is not active
	add_action( 'admin_notices', 'discount_tools_woocommerce_missing_notice' );
}

/**
 * Display admin notice when WooCommerce is not active.
 *
 * @since 1.0.0
 */
function discount_tools_woocommerce_missing_notice() {
	?>
	<div class="error notice">
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce plugin name */
				esc_html__( 'Discount Tools requires %s to be installed and active.', 'discount-tools' ),
				'<strong>' . esc_html__( 'WooCommerce', 'discount-tools' ) . '</strong>'
			);
			?>
		</p>
	</div>
	<?php
}
