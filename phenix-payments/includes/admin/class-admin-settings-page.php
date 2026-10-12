<?php
/**
 * Settings screen: environment, credentials, payment options, status.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Admin_Settings_Page {

	const SLUG          = 'phenix-payments-settings';
	const STATUS_OPTION = 'phenix_pay_connection_status';

	public function __construct() {
		// After the parent "Phenix Payments" menu is registered.
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_phenix_pay_test_connection', array( $this, 'test_connection' ) );
		add_action( 'admin_post_phenix_pay_clear_log', array( $this, 'clear_log' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	public function menu() {
		add_submenu_page(
			Phenix_Pay_Admin_Payments_Page::SLUG,
			__( 'Phenix Payments Settings', 'phenix-payments' ),
			__( 'Settings', 'phenix-payments' ),
			Phenix_Pay_Plugin::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'phenix-payments' ) ) {
			return;
		}
		wp_enqueue_style( 'phenix-payments-admin', PHENIX_PAY_URL . 'assets/css/admin.css', array(), PHENIX_PAY_VERSION );
		wp_enqueue_script( 'phenix-payments-admin', PHENIX_PAY_URL . 'assets/js/admin.js', array(), PHENIX_PAY_VERSION, true );
		wp_localize_script(
			'phenix-payments-admin',
			'PhenixPayAdmin',
			array(
				'confirmLive' => __( "Switch to LIVE mode?\n\nReal payments will be charged to your clients and deposited in your PayPal account. Make sure you have:\n• a PayPal Business account\n• LIVE Client ID, Secret and Webhook ID saved\n• completed sandbox testing", 'phenix-payments' ),
				'copied'      => __( 'Copied', 'phenix-payments' ),
			)
		);
	}

	public function register() {
		register_setting(
			'phenix_pay_settings_group',
			Phenix_Pay_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Validate and sanitise every submitted setting.
	 */
	public function sanitize( $input ) {
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			return Phenix_Pay_Settings::all();
		}
		$input = is_array( $input ) ? $input : array();
		$old   = Phenix_Pay_Settings::all();
		$out   = $old;

		$out['environment'] = ( isset( $input['environment'] ) && 'live' === $input['environment'] ) ? 'live' : 'sandbox';

		foreach ( array( 'sandbox', 'live' ) as $env ) {
			$id_key = $env . '_client_id';
			if ( isset( $input[ $id_key ] ) ) {
				$out[ $id_key ] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input[ $id_key ] );
			}
			$wh_key = $env . '_webhook_id';
			if ( isset( $input[ $wh_key ] ) ) {
				$out[ $wh_key ] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input[ $wh_key ] );
			}
			$secret_key = $env . '_client_secret';
			if ( ! empty( $input[ $env . '_clear_secret' ] ) ) {
				$out[ $secret_key ] = '';
			} elseif ( isset( $input[ $secret_key ] ) && '' !== trim( (string) $input[ $secret_key ] ) ) {
				$secret             = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input[ $secret_key ] );
				$out[ $secret_key ] = Phenix_Pay_Settings::encrypt( $secret );
			}
			// Blank secret field = keep existing (it is never echoed back).
		}

		$currency        = isset( $input['currency'] ) ? strtoupper( (string) $input['currency'] ) : 'USD';
		$out['currency'] = Phenix_Pay_Money::is_supported_currency( $currency ) ? $currency : 'USD';

		foreach ( array( 'enable_card_button', 'enable_card_fields', 'enable_paylater', 'allow_custom_amount', 'notify_admin', 'send_receipt', 'debug_log', 'delete_on_uninstall' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$min = Phenix_Pay_Money::to_minor( isset( $input['min_amount'] ) ? (string) $input['min_amount'] : '' );
		$max = Phenix_Pay_Money::to_minor( isset( $input['max_amount'] ) ? (string) $input['max_amount'] : '' );
		if ( null !== $min && $min >= 1 ) {
			$out['min_amount'] = $min;
		}
		if ( null !== $max && $max >= $out['min_amount'] ) {
			$out['max_amount'] = $max;
		} else {
			add_settings_error( Phenix_Pay_Settings::OPTION, 'max', __( 'Maximum amount must be greater than the minimum amount.', 'phenix-payments' ) );
		}

		$out['brand_name']      = mb_substr( sanitize_text_field( isset( $input['brand_name'] ) ? $input['brand_name'] : '' ), 0, 127 );
		$color                  = sanitize_hex_color( isset( $input['accent_color'] ) ? $input['accent_color'] : '' );
		$out['accent_color']    = $color ? $color : '#d9480f';
		$out['success_message'] = mb_substr( sanitize_textarea_field( isset( $input['success_message'] ) ? $input['success_message'] : '' ), 0, 500 );
		$email                  = sanitize_email( isset( $input['notify_email'] ) ? $input['notify_email'] : '' );
		$out['notify_email']    = is_email( $email ) ? $email : '';

		if ( $out['environment'] !== $old['environment'] || $out['sandbox_client_id'] !== $old['sandbox_client_id'] || $out['live_client_id'] !== $old['live_client_id'] ) {
			delete_option( self::STATUS_OPTION );
		}

		Phenix_Pay_Logger::info( 'Settings updated', array( 'user' => get_current_user_id(), 'environment' => $out['environment'] ) );
		return $out;
	}

	/**
	 * Check credentials (OAuth) and the webhook ID for the active environment.
	 */
	public function test_connection() {
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'phenix-payments' ), 403 );
		}
		check_admin_referer( 'phenix_pay_test_connection' );

		$env    = Phenix_Pay_Settings::environment();
		$client = new Phenix_Pay_PayPal_Client( $env );
		$status = array(
			'env'     => $env,
			'time'    => time(),
			'auth'    => false,
			'webhook' => false,
			'message' => '',
		);

		$token = $client->get_access_token( true );
		if ( is_wp_error( $token ) ) {
			$status['message'] = $token->get_error_message();
		} else {
			$status['auth'] = true;
			$webhook_id     = Phenix_Pay_Settings::credential( 'webhook_id', $env );
			if ( '' === $webhook_id ) {
				$status['message'] = __( 'Credentials are valid, but no Webhook ID is configured.', 'phenix-payments' );
			} else {
				$res = $client->get_webhook( $webhook_id );
				if ( ! is_wp_error( $res ) && 200 === $res['code'] ) {
					$expected = rest_url( Phenix_Pay_REST_Controller::NS . '/webhook' );
					$url      = isset( $res['body']['url'] ) ? $res['body']['url'] : '';
					if ( untrailingslashit( $url ) !== untrailingslashit( $expected ) ) {
						$status['message'] = sprintf( /* translators: 1 configured url 2 expected url */ __( 'Webhook found, but its URL (%1$s) does not match this site (%2$s).', 'phenix-payments' ), $url, $expected );
					} else {
						$status['webhook'] = true;
						$status['message'] = __( 'Connected. Credentials and webhook are valid.', 'phenix-payments' );
					}
				} else {
					$status['message'] = __( 'Credentials are valid, but the Webhook ID was not found for this app/environment.', 'phenix-payments' );
				}
			}
		}

		update_option( self::STATUS_OPTION, $status, false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&phx_tested=1' ) );
		exit;
	}

	public function clear_log() {
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'phenix-payments' ), 403 );
		}
		check_admin_referer( 'phenix_pay_clear_log' );
		Phenix_Pay_Logger::clear();
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=log' ) );
		exit;
	}

	public function notices() {
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'phenix-payments' ) ) {
			return;
		}
		if ( Phenix_Pay_Settings::is_sandbox() ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Phenix Payments is in SANDBOX (test) mode.', 'phenix-payments' ) . '</strong> ' . esc_html__( 'No real payments will be taken.', 'phenix-payments' ) . '</p></div>';
		}
		if ( ! is_ssl() && ! Phenix_Pay_Settings::is_sandbox() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Your site is not using HTTPS. Live payments require HTTPS.', 'phenix-payments' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ */

	public function render() {
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'phenix-payments' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		?>
		<div class="wrap phx-admin">
			<h1><?php esc_html_e( 'Phenix Payments for PayPal', 'phenix-payments' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'settings' => __( 'Settings', 'phenix-payments' ),
					'guide'    => __( 'Setup guide', 'phenix-payments' ),
					'log'      => __( 'Log', 'phenix-payments' ),
				);
				foreach ( $tabs as $key => $label ) {
					printf(
						'<a href="%s" class="nav-tab %s">%s</a>',
						esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $key ) ),
						$tab === $key ? 'nav-tab-active' : '',
						esc_html( $label )
					);
				}
				?>
			</nav>
			<?php
			if ( 'guide' === $tab ) {
				$this->render_guide();
			} elseif ( 'log' === $tab ) {
				$this->render_log();
			} else {
				$this->render_status();
				$this->render_form();
			}
			?>
		</div>
		<?php
	}

	private function render_status() {
		$env        = Phenix_Pay_Settings::environment();
		$status     = get_option( self::STATUS_OPTION );
		$has_creds  = Phenix_Pay_Settings::has_credentials( $env );
		$has_hook   = '' !== Phenix_Pay_Settings::credential( 'webhook_id', $env );
		$webhook    = rest_url( Phenix_Pay_REST_Controller::NS . '/webhook' );
		$tested     = is_array( $status ) && $status['env'] === $env;

		if ( ! $has_creds ) {
			$state = array( 'bad', __( 'Not configured', 'phenix-payments' ) );
		} elseif ( $tested && $status['auth'] && $status['webhook'] ) {
			$state = array( 'good', __( 'Connected', 'phenix-payments' ) );
		} elseif ( $tested && ! $status['auth'] ) {
			$state = array( 'bad', __( 'Credentials rejected', 'phenix-payments' ) );
		} elseif ( $tested ) {
			$state = array( 'warn', __( 'Needs attention', 'phenix-payments' ) );
		} else {
			$state = array( 'warn', __( 'Not tested yet', 'phenix-payments' ) );
		}
		?>
		<div class="phx-status">
			<div class="phx-status__row">
				<span class="phx-pill phx-pill--<?php echo esc_attr( $state[0] ); ?>"><?php echo esc_html( $state[1] ); ?></span>
				<span class="phx-pill phx-pill--<?php echo 'live' === $env ? 'live' : 'sandbox'; ?>"><?php echo 'live' === $env ? esc_html__( 'LIVE', 'phenix-payments' ) : esc_html__( 'SANDBOX / TEST MODE', 'phenix-payments' ); ?></span>
				<span><?php echo $has_creds ? '✔ ' . esc_html__( 'Client ID & Secret saved', 'phenix-payments' ) : '✖ ' . esc_html__( 'Client ID & Secret missing', 'phenix-payments' ); ?></span>
				<span><?php echo $has_hook ? '✔ ' . esc_html__( 'Webhook ID saved', 'phenix-payments' ) : '✖ ' . esc_html__( 'Webhook ID missing', 'phenix-payments' ); ?></span>
			</div>
			<?php if ( $tested && $status['message'] ) : ?>
				<p class="phx-status__msg">
					<?php
					echo esc_html( $status['message'] );
					echo ' <em>(' . esc_html( sprintf( /* translators: %s: time ago */ __( 'checked %s ago', 'phenix-payments' ), human_time_diff( (int) $status['time'] ) ) ) . ')</em>';
					?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="phx-inline">
				<input type="hidden" name="action" value="phenix_pay_test_connection">
				<?php wp_nonce_field( 'phenix_pay_test_connection' ); ?>
				<?php submit_button( __( 'Test connection', 'phenix-payments' ), 'secondary', 'submit', false, $has_creds ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
			<p class="phx-status__webhook">
				<strong><?php esc_html_e( 'Webhook URL (add this in your PayPal app):', 'phenix-payments' ); ?></strong><br>
				<code id="phx-webhook-url"><?php echo esc_html( $webhook ); ?></code>
				<button type="button" class="button button-small" data-phx-copy="phx-webhook-url"><?php esc_html_e( 'Copy', 'phenix-payments' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Shortcode:', 'phenix-payments' ); ?> <code>[phenix_payments]</code> · <code>[phenix_payments amount="250" reference="INV-1024" description="Website design deposit"]</code></p>
		</div>
		<?php
	}

	private function credential_fields( $env ) {
		$opt   = Phenix_Pay_Settings::OPTION;
		$s     = Phenix_Pay_Settings::all();
		$label = 'live' === $env ? __( 'Live', 'phenix-payments' ) : __( 'Sandbox', 'phenix-payments' );
		$saved = '' !== (string) $s[ $env . '_client_secret' ];
		?>
		<tr>
			<th scope="row"><label for="phx-<?php echo esc_attr( $env ); ?>-cid"><?php echo esc_html( sprintf( /* translators: %s env */ __( '%s Client ID', 'phenix-payments' ), $label ) ); ?></label></th>
			<td>
				<?php if ( Phenix_Pay_Settings::is_constant_defined( $env, 'client_id' ) ) : ?>
					<code><?php echo esc_html( Phenix_Pay_Settings::constant_name( $env, 'client_id' ) ); ?></code> <?php esc_html_e( 'is defined in wp-config.php.', 'phenix-payments' ); ?>
				<?php else : ?>
					<input type="text" class="large-text code" id="phx-<?php echo esc_attr( $env ); ?>-cid" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $env ); ?>_client_id]" value="<?php echo esc_attr( $s[ $env . '_client_id' ] ); ?>" autocomplete="off" spellcheck="false">
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="phx-<?php echo esc_attr( $env ); ?>-sec"><?php echo esc_html( sprintf( /* translators: %s env */ __( '%s Secret', 'phenix-payments' ), $label ) ); ?></label></th>
			<td>
				<?php if ( Phenix_Pay_Settings::is_constant_defined( $env, 'client_secret' ) ) : ?>
					<code><?php echo esc_html( Phenix_Pay_Settings::constant_name( $env, 'client_secret' ) ); ?></code> <?php esc_html_e( 'is defined in wp-config.php.', 'phenix-payments' ); ?>
				<?php else : ?>
					<input type="password" class="large-text code" id="phx-<?php echo esc_attr( $env ); ?>-sec" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $env ); ?>_client_secret]" value="" autocomplete="new-password" spellcheck="false"
						placeholder="<?php echo $saved ? esc_attr__( '•••••••• saved — leave blank to keep', 'phenix-payments' ) : ''; ?>">
					<?php if ( $saved ) : ?>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $env ); ?>_clear_secret]" value="1"> <?php esc_html_e( 'Remove saved secret', 'phenix-payments' ); ?></label>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Stored encrypted. It is never displayed again after saving.', 'phenix-payments' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="phx-<?php echo esc_attr( $env ); ?>-wh"><?php echo esc_html( sprintf( /* translators: %s env */ __( '%s Webhook ID', 'phenix-payments' ), $label ) ); ?></label></th>
			<td>
				<?php if ( Phenix_Pay_Settings::is_constant_defined( $env, 'webhook_id' ) ) : ?>
					<code><?php echo esc_html( Phenix_Pay_Settings::constant_name( $env, 'webhook_id' ) ); ?></code> <?php esc_html_e( 'is defined in wp-config.php.', 'phenix-payments' ); ?>
				<?php else : ?>
					<input type="text" class="regular-text code" id="phx-<?php echo esc_attr( $env ); ?>-wh" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $env ); ?>_webhook_id]" value="<?php echo esc_attr( $s[ $env . '_webhook_id' ] ); ?>" autocomplete="off" spellcheck="false">
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function checkbox( $key, $label, $help = '' ) {
		$opt = Phenix_Pay_Settings::OPTION;
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( $opt . '[' . $key . ']' ); ?>" value="1" <?php checked( (int) Phenix_Pay_Settings::get( $key ), 1 ); ?>> <?php echo esc_html( $label ); ?></label>
		<?php if ( $help ) : ?>
			<p class="description"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
		<br>
		<?php
	}

	private function render_form() {
		$opt = Phenix_Pay_Settings::OPTION;
		$s   = Phenix_Pay_Settings::all();
		?>
		<form method="post" action="options.php" id="phx-settings-form">
			<?php settings_fields( 'phenix_pay_settings_group' ); ?>

			<h2><?php esc_html_e( 'Environment', 'phenix-payments' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="phx-env"><?php esc_html_e( 'Mode', 'phenix-payments' ); ?></label></th>
					<td>
						<select id="phx-env" name="<?php echo esc_attr( $opt ); ?>[environment]" data-current="<?php echo esc_attr( $s['environment'] ); ?>">
							<option value="sandbox" <?php selected( $s['environment'], 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (test mode – no real money)', 'phenix-payments' ); ?></option>
							<option value="live" <?php selected( $s['environment'], 'live' ); ?>><?php esc_html_e( 'Live (real payments)', 'phenix-payments' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Sandbox and Live use different credentials from the PayPal Developer Dashboard. Test thoroughly in Sandbox before switching to Live.', 'phenix-payments' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Sandbox credentials', 'phenix-payments' ); ?></h2>
			<table class="form-table" role="presentation"><?php $this->credential_fields( 'sandbox' ); ?></table>

			<h2><?php esc_html_e( 'Live credentials', 'phenix-payments' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Live REST API credentials require a PayPal Business account. A Personal account cannot be used to accept payments through this plugin.', 'phenix-payments' ); ?></p>
			<table class="form-table" role="presentation"><?php $this->credential_fields( 'live' ); ?></table>

			<h2><?php esc_html_e( 'Payment options', 'phenix-payments' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="phx-currency"><?php esc_html_e( 'Currency', 'phenix-payments' ); ?></label></th>
					<td>
						<select id="phx-currency" name="<?php echo esc_attr( $opt ); ?>[currency]">
							<?php foreach ( Phenix_Pay_Money::CURRENCIES as $code => $c ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['currency'], $code ); ?>><?php echo esc_html( $code . ' – ' . $c['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'For a Canadian PayPal account, USD and CAD are recommended. If you receive a currency you do not hold a balance in, PayPal may ask you to accept/convert it (Settings → Payments → "Block payments" / currency preferences in PayPal).', 'phenix-payments' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Payment methods', 'phenix-payments' ); ?></th>
					<td>
						<?php
						$this->checkbox( 'enable_card_button', __( 'Show the "Debit or Credit Card" button (guest checkout, no PayPal account needed)', 'phenix-payments' ), __( 'Shown by PayPal only when it is eligible for your account and the buyer\'s country.', 'phenix-payments' ) );
						$this->checkbox( 'enable_card_fields', __( 'Enable embedded card fields (Advanced Credit and Debit Card Payments)', 'phenix-payments' ), __( 'Requires PayPal approval for advanced card processing on your Business account. If not approved, the fields are hidden automatically and the card button above is used instead.', 'phenix-payments' ) );
						$this->checkbox( 'enable_paylater', __( 'Offer Pay Later where PayPal makes it available', 'phenix-payments' ) );
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Amounts', 'phenix-payments' ); ?></th>
					<td>
						<?php $this->checkbox( 'allow_custom_amount', __( 'Allow clients to enter the amount (forms without a fixed amount)', 'phenix-payments' ) ); ?>
						<label><?php esc_html_e( 'Minimum', 'phenix-payments' ); ?> <input type="text" inputmode="decimal" class="small-text" name="<?php echo esc_attr( $opt ); ?>[min_amount]" value="<?php echo esc_attr( Phenix_Pay_Money::to_decimal( $s['min_amount'] ) ); ?>"></label>
						&nbsp;
						<label><?php esc_html_e( 'Maximum', 'phenix-payments' ); ?> <input type="text" inputmode="decimal" class="regular-text" style="width:8em" name="<?php echo esc_attr( $opt ); ?>[max_amount]" value="<?php echo esc_attr( Phenix_Pay_Money::to_decimal( $s['max_amount'] ) ); ?>"></label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Branding & messages', 'phenix-payments' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="phx-brand"><?php esc_html_e( 'Business name', 'phenix-payments' ); ?></label></th>
					<td><input id="phx-brand" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[brand_name]" value="<?php echo esc_attr( $s['brand_name'] ); ?>"><p class="description"><?php esc_html_e( 'Shown on the form and on the PayPal checkout page.', 'phenix-payments' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="phx-accent"><?php esc_html_e( 'Accent colour', 'phenix-payments' ); ?></label></th>
					<td><input id="phx-accent" type="color" name="<?php echo esc_attr( $opt ); ?>[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>"> <span class="description"><?php esc_html_e( 'Match your site\'s primary brand colour.', 'phenix-payments' ); ?></span></td>
				</tr>
				<tr>
					<th scope="row"><label for="phx-success"><?php esc_html_e( 'Success message', 'phenix-payments' ); ?></label></th>
					<td><textarea id="phx-success" class="large-text" rows="2" name="<?php echo esc_attr( $opt ); ?>[success_message]"><?php echo esc_textarea( $s['success_message'] ); ?></textarea></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Notifications', 'phenix-payments' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Emails', 'phenix-payments' ); ?></th>
					<td>
						<?php
						$this->checkbox( 'notify_admin', __( 'Email me when a payment is completed', 'phenix-payments' ) );
						$this->checkbox( 'send_receipt', __( 'Email the client a receipt', 'phenix-payments' ) );
						?>
						<label><?php esc_html_e( 'Notification email', 'phenix-payments' ); ?> <input type="email" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Advanced', 'phenix-payments' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Maintenance', 'phenix-payments' ); ?></th>
					<td>
						<?php
						$this->checkbox( 'debug_log', __( 'Debug logging (records API call status codes; never secrets or card data)', 'phenix-payments' ) );
						$this->checkbox( 'delete_on_uninstall', __( 'Delete all settings and payment records when the plugin is deleted', 'phenix-payments' ), __( 'Leave unchecked to keep your financial records.', 'phenix-payments' ) );
						?>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_guide() {
		$webhook = rest_url( Phenix_Pay_REST_Controller::NS . '/webhook' );
		?>
		<div class="phx-guide">
			<h2><?php esc_html_e( '1. Use a PayPal Business account', 'phenix-payments' ); ?></h2>
			<p><?php esc_html_e( 'Accepting payments through the PayPal Checkout API requires a Business account. To convert your existing Canadian Personal account (keeping your email, balance and history): log in at paypal.com → Settings (gear icon) → Account options → "Upgrade to a Business account", then complete the business details. PayPal notes that business accounts generally cannot be downgraded again.', 'phenix-payments' ); ?></p>

			<h2><?php esc_html_e( '2. Create a REST API app', 'phenix-payments' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Go to developer.paypal.com and log in with your PayPal Business account (same login as paypal.com).', 'phenix-payments' ); ?></li>
				<li><?php esc_html_e( 'Open "Apps & Credentials". Use the Sandbox / Live toggle at the top.', 'phenix-payments' ); ?></li>
				<li><?php esc_html_e( 'Sandbox: open the Default Application (or "Create App", type Merchant). Copy the Client ID and Secret into the Sandbox fields here.', 'phenix-payments' ); ?></li>
				<li><?php esc_html_e( 'Live: switch the toggle to Live, "Create App", and copy the Live Client ID and Secret. (If Live is unavailable, your account is not yet a verified Business account.)', 'phenix-payments' ); ?></li>
			</ol>
			<p><strong><?php esc_html_e( 'Never share your PayPal password. This plugin only needs the app Client ID, Secret and Webhook ID.', 'phenix-payments' ); ?></strong></p>

			<h2><?php esc_html_e( '3. Add a webhook (each environment)', 'phenix-payments' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'In the app, scroll to "Webhooks" → "Add Webhook".', 'phenix-payments' ); ?></li>
				<li><?php esc_html_e( 'Webhook URL:', 'phenix-payments' ); ?> <code><?php echo esc_html( $webhook ); ?></code></li>
				<li><?php esc_html_e( 'Event types: CHECKOUT.ORDER.APPROVED, CHECKOUT.ORDER.COMPLETED, PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.PENDING, PAYMENT.CAPTURE.DENIED, PAYMENT.CAPTURE.DECLINED, PAYMENT.CAPTURE.REFUNDED, PAYMENT.CAPTURE.REVERSED.', 'phenix-payments' ); ?></li>
				<li><?php esc_html_e( 'Save, then copy the Webhook ID shown in the list into the matching Webhook ID field here.', 'phenix-payments' ); ?></li>
			</ol>

			<h2><?php esc_html_e( '4. Card payments', 'phenix-payments' ); ?></h2>
			<p><?php esc_html_e( 'The black "Debit or Credit Card" button lets clients pay by card without a PayPal account. PayPal decides when it is shown (it depends on your account and the buyer). For card fields embedded directly in the form, PayPal must approve your Business account for Advanced Credit and Debit Card Payments (Canada supports Visa, Mastercard, American Express and JCB). In Sandbox, enable "Advanced Credit and Debit Card Payments" under the app\'s features to test it.', 'phenix-payments' ); ?></p>

			<h2><?php esc_html_e( '5. Optional: keep credentials in wp-config.php', 'phenix-payments' ); ?></h2>
			<pre class="phx-code">define( 'PHENIX_PAY_LIVE_CLIENT_ID', 'your-live-client-id' );
define( 'PHENIX_PAY_LIVE_CLIENT_SECRET', 'your-live-secret' );
define( 'PHENIX_PAY_LIVE_WEBHOOK_ID', 'your-live-webhook-id' );
define( 'PHENIX_PAY_SANDBOX_CLIENT_ID', '...' );
define( 'PHENIX_PAY_SANDBOX_CLIENT_SECRET', '...' );
define( 'PHENIX_PAY_SANDBOX_WEBHOOK_ID', '...' );</pre>

			<h2><?php esc_html_e( '6. Add the form to a page', 'phenix-payments' ); ?></h2>
			<p><code>[phenix_payments]</code> — <?php esc_html_e( 'client enters the amount.', 'phenix-payments' ); ?></p>
			<p><code>[phenix_payments amount="500" reference="INV-1024" description="Brand strategy – milestone 1"]</code> — <?php esc_html_e( 'fixed amount the client cannot change.', 'phenix-payments' ); ?></p>
			<p><?php esc_html_e( 'In Elementor, use the "Shortcode" widget. Exclude the payment page from page caching (or caching plugins/CDN) so security tokens stay fresh.', 'phenix-payments' ); ?></p>
		</div>
		<?php
	}

	private function render_log() {
		$entries = Phenix_Pay_Logger::entries();
		?>
		<p><?php esc_html_e( 'Recent events (newest first). Secrets and card data are never logged.', 'phenix-payments' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="phenix_pay_clear_log">
			<?php wp_nonce_field( 'phenix_pay_clear_log' ); ?>
			<?php submit_button( __( 'Clear log', 'phenix-payments' ), 'delete small', 'submit', false ); ?>
		</form>
		<table class="widefat striped phx-log">
			<thead><tr><th><?php esc_html_e( 'Time (UTC)', 'phenix-payments' ); ?></th><th><?php esc_html_e( 'Level', 'phenix-payments' ); ?></th><th><?php esc_html_e( 'Message', 'phenix-payments' ); ?></th><th><?php esc_html_e( 'Context', 'phenix-payments' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $entries ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No log entries.', 'phenix-payments' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $entries as $e ) : ?>
				<tr>
					<td><?php echo esc_html( $e['time'] ); ?></td>
					<td><span class="phx-level phx-level--<?php echo esc_attr( $e['level'] ); ?>"><?php echo esc_html( $e['level'] ); ?></span></td>
					<td><?php echo esc_html( $e['message'] ); ?></td>
					<td><?php echo $e['context'] ? '<code>' . esc_html( wp_json_encode( $e['context'] ) ) . '</code>' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
