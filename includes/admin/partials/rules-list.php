<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Rules List Page Template
 *
 * @link       https://hugoshih.eu.org
 * @since      1.0.0
 *
 * @package    Discount_Tools
 * @subpackage Discount_Tools/includes/admin/partials
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Create list table instance.
$dt_list_table = new Discount_Tools_Rules_List_Table();
$dt_list_table->prepare_items();

// Handle messages.
settings_errors( 'discount_tools' );

?>

<div class="discount-tools-admin">
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=discount-tools&tab=rules&action=add' ) ); ?>" class="button button-primary">
		<?php echo esc_html__( 'Add New', 'discount-tools' ); ?>
	</a>

	<hr class="wp-header-end">

	<?php if ( $dt_list_table->has_items() ) : ?>
		
		<form method="post">
			<input type="hidden" name="page" value="discount-tools" />
			<input type="hidden" name="tab" value="rules" />
			<?php wp_nonce_field( 'bulk-rules', '_wpnonce' ); ?>
			<?php 
			$dt_list_table->views();
			$dt_list_table->search_box( __( 'Search Rules', 'discount-tools' ), 'rule' );
			$dt_list_table->display(); 
			?>
		</form>

	<?php else : ?>
		
		<div class="notice notice-info inline">
			<p><?php echo esc_html__( 'No discount rules found. Create your first rule to get started!', 'discount-tools' ); ?></p>
		</div>

		<div class="discount-tools-empty-state">
			<span class="dashicons dashicons-tag dt-empty-icon"></span>
			<h2><?php echo esc_html__( 'Get Started with Discount Tools', 'discount-tools' ); ?></h2>
			<p><?php echo esc_html__( 'Create powerful discount rules for your WooCommerce store.', 'discount-tools' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=discount-tools&tab=rules&action=add' ) ); ?>" class="button button-primary button-hero">
				<?php echo esc_html__( 'Create Your First Rule', 'discount-tools' ); ?>
			</a>
		</div>

	<?php endif; ?>
</div>
