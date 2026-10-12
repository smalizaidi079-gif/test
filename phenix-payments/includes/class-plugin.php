<?php
/**
 * Plugin bootstrap.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Plugin {

	/** @var Phenix_Pay_Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Capability required to view payments and change settings.
	 */
	public static function capability() {
		return (string) apply_filters( 'phenix_pay_capability', 'manage_options' );
	}

	private function __construct() {
		load_plugin_textdomain( 'phenix-payments', false, dirname( plugin_basename( PHENIX_PAY_FILE ) ) . '/languages' );

		Phenix_Pay_Installer::maybe_upgrade();

		( new Phenix_Pay_Shortcode() )->register();

		add_action(
			'rest_api_init',
			function () {
				( new Phenix_Pay_REST_Controller() )->register_routes();
			}
		);

		add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );

		if ( is_admin() ) {
			new Phenix_Pay_Admin_Settings_Page();
			new Phenix_Pay_Admin_Payments_Page();
			add_filter( 'plugin_action_links_' . plugin_basename( PHENIX_PAY_FILE ), array( $this, 'action_links' ) );
		}
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=phenix-payments-settings' ) ) . '">' . esc_html__( 'Settings', 'phenix-payments' ) . '</a>'
		);
		return $links;
	}

	public function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'When you make a payment, we collect your name, email address, the invoice/project reference and description you provide, and the payment amount. Payments are processed by PayPal; card details are entered directly with PayPal and are never received or stored by this website. We store the PayPal transaction ID and payment status to keep accurate financial records. PayPal\'s privacy statement applies to information you provide to PayPal.', 'phenix-payments' ) . '</p>';
		wp_add_privacy_policy_content( 'Phenix Payments for PayPal', wp_kses_post( $content ) );
	}
}
