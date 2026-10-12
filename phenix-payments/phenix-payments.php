<?php
/**
 * Plugin Name:       Phenix Payments for PayPal
 * Plugin URI:        https://thephenixllc.com/
 * Description:       Secure invoice and project payments for The Phenix LLC using PayPal Checkout (PayPal, Pay Later, debit and credit cards). Card data is handled entirely by PayPal; every payment is verified server-side.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            The Phenix LLC
 * Author URI:        https://thephenixllc.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       phenix-payments
 * Domain Path:       /languages
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

define( 'PHENIX_PAY_VERSION', '1.0.1' );
define( 'PHENIX_PAY_DB_VERSION', '1' );
define( 'PHENIX_PAY_FILE', __FILE__ );
define( 'PHENIX_PAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'PHENIX_PAY_URL', plugin_dir_url( __FILE__ ) );

require_once PHENIX_PAY_DIR . 'includes/class-logger.php';
require_once PHENIX_PAY_DIR . 'includes/class-money.php';
require_once PHENIX_PAY_DIR . 'includes/class-settings.php';
require_once PHENIX_PAY_DIR . 'includes/class-installer.php';
require_once PHENIX_PAY_DIR . 'includes/class-payments-repository.php';
require_once PHENIX_PAY_DIR . 'includes/class-paypal-client.php';
require_once PHENIX_PAY_DIR . 'includes/class-payment-service.php';
require_once PHENIX_PAY_DIR . 'includes/class-notifications.php';
require_once PHENIX_PAY_DIR . 'includes/class-rest-controller.php';
require_once PHENIX_PAY_DIR . 'includes/class-shortcode.php';
require_once PHENIX_PAY_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once PHENIX_PAY_DIR . 'includes/admin/class-admin-settings-page.php';
	require_once PHENIX_PAY_DIR . 'includes/admin/class-admin-payments-page.php';
}

register_activation_hook( __FILE__, array( 'Phenix_Pay_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Phenix_Pay_Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Phenix_Pay_Plugin', 'instance' ) );
