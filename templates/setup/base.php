<?php
/**
 * Base template for setup wizard.
 *
 * @package OrderSyncWithGoogleSheetForWooCommerce
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit(); ?>
<section class="osgsw-wrapper" x-data="setup">
	<?php osgsw()->load_template( 'setup/woocommerce' ); ?>
	<?php
	$active_new_user  = get_option('osgsw_new_user_activate_trigger1', '0' );
	$already_update   = get_option('osgsw_already_update_trigger1', '0' );
	$new_trigger_notice = false;
	if ( '1' != $active_new_user && '1' != $already_update ) { //phpcs:ignore
		?>
	<style>
		.osgsw-wrapper .ssgs-dashboard__header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			line-height: 40px;
			padding-top: 20px;
		}
		.ssgsw_notice_container2 {
			display: inline-block;
			position: relative;
			top: -23px;
			right: -46px;
		}
		p.ossgw_setup_now_button {
			position: absolute;
			top: -56px;
			right: -61px;
		}
	</style>
	<div class="ssgsw_appscript_trigger ssgsw_appscript_notice233">
		<div class="ssgsw_notice_container">
		<p> <?php esc_html_e('Hey! 👋 We’ve updated our Apps Script Please','order-sync-with-google-sheets-for-woocommerce');?> <strong class="ssgsw_extra_strong"> <?php esc_html_e('update your Apps Script','order-sync-with-google-sheets-for-woocommerce')?> </strong> <?php esc_html_e('on Google sheets ','order-sync-with-google-sheets-for-woocommerce')?> <?php esc_html_e('to enjoy all the new changes.','order-sync-with-google-sheets-for-woocommerce');?></p>
			<div class="ossgsw_inner_class">
				
				<p class="ossgw_setup_now_button"><span class="osgsw_remove_text_dec osgsw_remove_text_dec23"><?php esc_html_e('Setup Now →','order-sync-with-google-sheets-for-woocommerce');?></span></p>
			</div>	
		</div>
		<div class="ssgsw_notice_container2">
			<span class="dashicons dashicons-dismiss osgsw_notice_off" x-on:click="show_notice_popup_setup = true"></span>
		</div>	
	</div>
		<?php
	}
	?>
	<div id="popup1" class="ssgs_popup-container" x-show="show_notice_popup_setup" style="display: none">
	<div class="ssgs_popup-content" @click.away="show_notice_popup_setup = false">
		<div class="profile-section">
			<div class="profile-details">
				<h3 class="ossgw_profile-title2"><?php esc_html_e('Are you sure to close?','order-sync-with-google-sheets-for-woocommerce'); ?></h3>
				<p class="ossgw_extra_class2"><?php esc_html_e('Please make sure that you have updated both the Apps Script and the Trigger. Otherwise the plugin functionality may not work properly.'); ?></p>
			</div>
		</div>
		<div class="ssgs_first_section">
			<div class="osgs_button_section">
				
				<button type="button" class="osgsw_save_changes231 button" x-on:click="show_notice_popup_setup = false"><?php esc_html_e(' Later','order-sync-with-google-sheets-for-woocommerce'); ?></button>
				<button type="button" class="osgsw_save_close1 button" style="background-color:#005ae0; color:#fff"><?php esc_html_e('Confirm & close','order-sync-with-google-sheets-for-woocommerce'); ?></button>
			</div>
		</div>
	</div>
</div>
	<!-- setup wizard  -->
	<div class="ssgs-admin setup">
	<?php osgsw()->load_template( 'setup/welcome' ); ?>
		<!-- setup tabs  -->
		<div class="ssgs-tab">
			<?php osgsw()->load_template( 'setup/header' ); ?>
			<div class="ssgs-tab__content">
				<?php
					osgsw()->load_template( 'setup/step-1' );
					osgsw()->load_template( 'setup/step-2' );
					osgsw()->load_template( 'setup/step-3' );
					osgsw()->load_template( 'setup/step-4' );
					osgsw()->load_template( 'setup/step-5' );
				?>
			</div><!-- /Tab Content Wrapper -->
			<?php osgsw()->load_template( 'setup/footer' ); ?>
		</div>
	</div>
</section>

