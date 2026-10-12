<?php
/**
 * Payment form template.
 *
 * @package PhenixPayments
 * @var array $view Prepared by Phenix_Pay_Shortcode::render().
 */

defined( 'ABSPATH' ) || exit;

$uid   = $view['uid'];
$fixed = null !== $view['fixed_minor'];
?>
<div class="phx-pay" id="<?php echo esc_attr( $uid ); ?>" data-phx-pay
	data-currency="<?php echo esc_attr( $view['currency'] ); ?>"
	data-symbol="<?php echo esc_attr( $view['symbol'] ); ?>"
	data-min="<?php echo esc_attr( $view['min'] ); ?>"
	data-max="<?php echo esc_attr( $view['max'] ); ?>"
	data-card-fields="<?php echo $view['card_fields'] ? '1' : '0'; ?>">

	<div class="phx-pay__card">
		<header class="phx-pay__header">
			<h2 class="phx-pay__title"><?php echo esc_html( $view['title'] ); ?></h2>
			<p class="phx-pay__subtitle">
				<?php
				/* translators: %s: business name */
				echo esc_html( sprintf( __( 'Secure payment to %s', 'phenix-payments' ), $view['brand'] ) );
				?>
			</p>
			<?php if ( $view['is_sandbox'] ) : ?>
				<p class="phx-pay__test-badge" role="note"><?php esc_html_e( 'TEST MODE — PayPal Sandbox. No real money will be charged.', 'phenix-payments' ); ?></p>
			<?php endif; ?>
		</header>

		<ol class="phx-pay__steps" aria-label="<?php esc_attr_e( 'Payment progress', 'phenix-payments' ); ?>">
			<li class="is-active" data-step-indicator="details"><span>1</span> <?php esc_html_e( 'Details', 'phenix-payments' ); ?></li>
			<li data-step-indicator="review"><span>2</span> <?php esc_html_e( 'Review &amp; pay', 'phenix-payments' ); ?></li>
			<li data-step-indicator="done"><span>3</span> <?php esc_html_e( 'Confirmation', 'phenix-payments' ); ?></li>
		</ol>

		<div class="phx-pay__alert" role="alert" aria-live="assertive" hidden></div>

		<!-- Step 1: details -->
		<form class="phx-pay__step" data-step="details" novalidate>
			<?php if ( $fixed ) : ?>
				<input type="hidden" name="amount_token" value="<?php echo esc_attr( $view['amount_token'] ); ?>">
				<input type="hidden" name="amount" value="<?php echo esc_attr( Phenix_Pay_Money::to_decimal( $view['fixed_minor'] ) ); ?>">
				<div class="phx-pay__fixed-amount">
					<span class="phx-pay__label-text"><?php esc_html_e( 'Amount due', 'phenix-payments' ); ?></span>
					<strong><?php echo esc_html( Phenix_Pay_Money::format( $view['fixed_minor'], $view['currency'] ) ); ?></strong>
				</div>
			<?php endif; ?>

			<div class="phx-pay__grid">
				<div class="phx-pay__field">
					<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Full name', 'phenix-payments' ); ?> <span class="phx-pay__req" aria-hidden="true">*</span></label>
					<input id="<?php echo esc_attr( $uid ); ?>-name" name="name" type="text" autocomplete="name" maxlength="100" required aria-describedby="<?php echo esc_attr( $uid ); ?>-name-err">
					<p class="phx-pay__error" id="<?php echo esc_attr( $uid ); ?>-name-err" data-error-for="name"></p>
				</div>

				<div class="phx-pay__field">
					<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email address', 'phenix-payments' ); ?> <span class="phx-pay__req" aria-hidden="true">*</span></label>
					<input id="<?php echo esc_attr( $uid ); ?>-email" name="email" type="email" autocomplete="email" inputmode="email" maxlength="191" required aria-describedby="<?php echo esc_attr( $uid ); ?>-email-err">
					<p class="phx-pay__error" id="<?php echo esc_attr( $uid ); ?>-email-err" data-error-for="email"></p>
				</div>

				<div class="phx-pay__field">
					<label for="<?php echo esc_attr( $uid ); ?>-reference"><?php esc_html_e( 'Invoice or project reference', 'phenix-payments' ); ?></label>
					<input id="<?php echo esc_attr( $uid ); ?>-reference" name="reference" type="text" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. INV-1024', 'phenix-payments' ); ?>"
						value="<?php echo esc_attr( $view['reference'] ); ?>" <?php echo $view['reference_lock'] ? 'readonly' : ''; ?>>
					<p class="phx-pay__error" data-error-for="reference"></p>
				</div>

				<?php if ( ! $fixed ) : ?>
					<div class="phx-pay__field">
						<label for="<?php echo esc_attr( $uid ); ?>-amount">
							<?php
							/* translators: %s: currency code */
							echo esc_html( sprintf( __( 'Amount (%s)', 'phenix-payments' ), $view['currency'] ) );
							?>
							<span class="phx-pay__req" aria-hidden="true">*</span>
						</label>
						<div class="phx-pay__money">
							<span class="phx-pay__money-symbol" aria-hidden="true"><?php echo esc_html( $view['symbol'] ); ?></span>
							<input id="<?php echo esc_attr( $uid ); ?>-amount" name="amount" type="text" inputmode="decimal" autocomplete="off" required
								placeholder="0.00" value="<?php echo esc_attr( $view['prefill_amount'] ); ?>" aria-describedby="<?php echo esc_attr( $uid ); ?>-amount-err">
						</div>
						<p class="phx-pay__error" id="<?php echo esc_attr( $uid ); ?>-amount-err" data-error-for="amount"></p>
					</div>
				<?php endif; ?>

				<div class="phx-pay__field phx-pay__field--full">
					<label for="<?php echo esc_attr( $uid ); ?>-description"><?php esc_html_e( 'Payment description', 'phenix-payments' ); ?></label>
					<?php if ( $fixed && '' !== $view['description'] ) : ?>
						<input id="<?php echo esc_attr( $uid ); ?>-description" name="description" type="text" readonly value="<?php echo esc_attr( $view['description'] ); ?>">
					<?php else : ?>
						<textarea id="<?php echo esc_attr( $uid ); ?>-description" name="description" rows="2" maxlength="255" placeholder="<?php esc_attr_e( 'What is this payment for?', 'phenix-payments' ); ?>"><?php echo esc_textarea( $view['desc_default'] ); ?></textarea>
					<?php endif; ?>
					<p class="phx-pay__error" data-error-for="description"></p>
				</div>
			</div>

			<button type="submit" class="phx-pay__btn phx-pay__btn--primary"><?php echo esc_html( $view['button_text'] ); ?></button>
			<p class="phx-pay__secure-note">
				<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24"><path fill="currentColor" d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5Zm-3 8V6a3 3 0 1 1 6 0v3H9Z"/></svg>
				<?php esc_html_e( 'Payments are processed securely by PayPal. Your card details are never stored on our website.', 'phenix-payments' ); ?>
			</p>
		</form>

		<!-- Step 2: review & pay -->
		<div class="phx-pay__step" data-step="review" hidden>
			<h3 class="phx-pay__section-title" tabindex="-1"><?php esc_html_e( 'Payment summary', 'phenix-payments' ); ?></h3>
			<dl class="phx-pay__summary">
				<div><dt><?php esc_html_e( 'Name', 'phenix-payments' ); ?></dt><dd data-summary="name"></dd></div>
				<div><dt><?php esc_html_e( 'Email', 'phenix-payments' ); ?></dt><dd data-summary="email"></dd></div>
				<div><dt><?php esc_html_e( 'Reference', 'phenix-payments' ); ?></dt><dd data-summary="reference"></dd></div>
				<div><dt><?php esc_html_e( 'Description', 'phenix-payments' ); ?></dt><dd data-summary="description"></dd></div>
				<div class="phx-pay__summary-total"><dt><?php esc_html_e( 'Total', 'phenix-payments' ); ?></dt><dd data-summary="amount"></dd></div>
			</dl>
			<button type="button" class="phx-pay__link" data-action="edit"><?php esc_html_e( '← Edit details', 'phenix-payments' ); ?></button>

			<div class="phx-pay__loading" data-role="sdk-loading">
				<span class="phx-pay__spinner" aria-hidden="true"></span>
				<?php esc_html_e( 'Loading secure checkout…', 'phenix-payments' ); ?>
			</div>

			<div class="phx-pay__methods">
				<div class="phx-pay__buttons" data-role="paypal-buttons"></div>

				<?php if ( $view['card_fields'] ) : ?>
					<div class="phx-pay__cardfields" data-role="card-fields" hidden>
						<div class="phx-pay__divider"><span><?php esc_html_e( 'or pay with card', 'phenix-payments' ); ?></span></div>
						<div class="phx-pay__cf" data-cf="name"></div>
						<div class="phx-pay__cf" data-cf="number"></div>
						<div class="phx-pay__cf-row">
							<div class="phx-pay__cf" data-cf="expiry"></div>
							<div class="phx-pay__cf" data-cf="cvv"></div>
						</div>
						<button type="button" class="phx-pay__btn phx-pay__btn--primary" data-action="card-submit">
							<?php esc_html_e( 'Pay now', 'phenix-payments' ); ?> <span data-summary="amount"></span>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Processing overlay -->
		<div class="phx-pay__processing" data-role="processing" hidden>
			<span class="phx-pay__spinner phx-pay__spinner--lg" aria-hidden="true"></span>
			<p role="status" aria-live="polite" data-role="processing-text"></p>
		</div>

		<!-- Step 3: result -->
		<div class="phx-pay__step phx-pay__result" data-step="done" hidden>
			<div class="phx-pay__result-icon" data-role="result-icon" aria-hidden="true"></div>
			<h3 class="phx-pay__result-title" tabindex="-1" data-role="result-title"></h3>
			<p class="phx-pay__result-message" data-role="result-message"></p>
			<dl class="phx-pay__summary phx-pay__summary--compact" data-role="result-details"></dl>
			<button type="button" class="phx-pay__btn phx-pay__btn--secondary" data-action="restart" hidden><?php esc_html_e( 'Try again', 'phenix-payments' ); ?></button>
		</div>
	</div>
</div>
