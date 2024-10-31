<?php
/**
 * Plugin Name: Order Sync With Google Sheets For Woocommerce
 * Plugin URI: https://wcordersync.com/
 * Description: Sync WooCommerce orders with Google Sheets. Perform WooCommerce order sync, e-commerce order management and sales order management from Google Sheets.
 * Version: 1.10.2
 * Author: WC Order Sync
 * Author URI: https://wcordersync.com/
 * Text Domain: order-sync-with-google-sheets-for-woocommerce
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package OrderSyncWithGoogleSheetForWooCommerce
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Base File for the plugin
 */
define( 'OSGSW_FILE', __FILE__ );
define( 'OSGSW_VERSION', '1.10.2' );
/**
 * Loading base file
 * Load plugin
 * If you are a developer, please don't change this file location
 */
if ( file_exists( __DIR__ . '/includes/boot.php' ) ) {
	require_once __DIR__ . '/includes/boot.php';
}
/**
 * Manipulating the plugin code WILL NOT ALLOW you to use the premium features.
 * Please download the free version of the plugin from https://wordpress.org/plugins/order-sync-with-google-sheets-for-woocommerce/
 */
