/**
 * Phenix Payments – front-end checkout.
 *
 * Uses the official PayPal JavaScript SDK. All amounts are set and verified
 * on the server; this script only collects non-sensitive details, shows a
 * summary, and relays PayPal callbacks to the WordPress REST API. Card data is
 * entered inside PayPal-hosted fields/iframes and never touches this site.
 */
( function () {
	'use strict';

	var cfg = window.PhenixPayConfig;
	if ( ! cfg ) {
		return;
	}

	var sdkPromise = null;

	function loadSdk() {
		if ( sdkPromise ) {
			return sdkPromise;
		}
		sdkPromise = new Promise( function ( resolve, reject ) {
			if ( window.paypal && window.paypal.Buttons ) {
				resolve( window.paypal );
				return;
			}
			var s = document.createElement( 'script' );
			s.src = cfg.sdkUrl;
			s.async = true;
			s.setAttribute( 'data-namespace', 'paypal' );
			s.onload = function () {
				if ( window.paypal && window.paypal.Buttons ) {
					resolve( window.paypal );
				} else {
					reject( new Error( 'sdk' ) );
				}
			};
			s.onerror = function () {
				sdkPromise = null;
				reject( new Error( 'sdk' ) );
			};
			document.head.appendChild( s );
		} );
		return sdkPromise;
	}

	function uuid() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return window.crypto.randomUUID();
		}
		var b = new Uint8Array( 16 );
		window.crypto.getRandomValues( b );
		b[ 6 ] = ( b[ 6 ] & 0x0f ) | 0x40;
		b[ 8 ] = ( b[ 8 ] & 0x3f ) | 0x80;
		var h = Array.prototype.map.call( b, function ( x ) {
			return ( x + 0x100 ).toString( 16 ).slice( 1 );
		} ).join( '' );
		return h.slice( 0, 8 ) + '-' + h.slice( 8, 12 ) + '-' + h.slice( 12, 16 ) + '-' + h.slice( 16, 20 ) + '-' + h.slice( 20 );
	}

	function api( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( body || {} ),
		} ).then(
			function ( res ) {
				return res.json().catch( function () {
					return {};
				} ).then( function ( json ) {
					if ( ! res.ok ) {
						var err = new Error( json.message || cfg.i18n.generic );
						err.code = json.code || 'http_' + res.status;
						err.fields = json.fields || null;
						err.retry = !! json.retry;
						err.status = res.status;
						throw err;
					}
					return json;
				} );
			},
			function () {
				var err = new Error( cfg.i18n.network );
				err.code = 'network';
				throw err;
			}
		);
	}

	function parseAmount( value ) {
		var v = String( value || '' ).replace( /[\s,]/g, '' );
		if ( ! /^\d{1,9}(\.\d{1,2})?$/.test( v ) ) {
			return null;
		}
		var parts = v.split( '.' );
		return parseInt( parts[ 0 ], 10 ) * 100 + parseInt( ( ( parts[ 1 ] || '' ) + '00' ).slice( 0, 2 ), 10 );
	}

	function formatMoney( minor, currency, symbol ) {
		var n = minor / 100;
		try {
			return new Intl.NumberFormat( cfg.locale, { style: 'currency', currency: currency } ).format( n ) + ( currency === 'USD' || currency === 'CAD' || currency === 'AUD' ? ' ' + currency : '' );
		} catch ( e ) {
			return symbol + n.toFixed( 2 ) + ' ' + currency;
		}
	}

	function sprintf( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return str.replace( /%(\d)\$s/g, function ( m, i ) {
			return args[ i - 1 ];
		} );
	}

	/* ------------------------------------------------------------------ */

	function PaymentForm( root ) {
		this.root = root;
		this.form = root.querySelector( '[data-step="details"]' );
		this.alertEl = root.querySelector( '.phx-pay__alert' );
		this.currency = root.getAttribute( 'data-currency' );
		this.symbol = root.getAttribute( 'data-symbol' );
		this.min = parseAmount( root.getAttribute( 'data-min' ) );
		this.max = parseAmount( root.getAttribute( 'data-max' ) );
		this.useCardFields = root.getAttribute( 'data-card-fields' ) === '1';
		this.data = null;
		this.rendered = false;
		this.busy = false;
		this.done = false;
		this.cardField = null;

		this.form.addEventListener( 'submit', this.onReview.bind( this ) );
		root.querySelector( '[data-action="edit"]' ).addEventListener( 'click', this.showStep.bind( this, 'details' ) );
		root.querySelector( '[data-action="restart"]' ).addEventListener( 'click', this.showStep.bind( this, 'review' ) );

		// Warm up the SDK as soon as the visitor starts filling the form.
		var warm = function () {
			loadSdk().catch( function () {} );
		};
		this.form.addEventListener( 'focusin', warm, { once: true } );
	}

	PaymentForm.prototype.showStep = function ( step ) {
		var self = this;
		this.clearAlert();
		[ 'details', 'review', 'done' ].forEach( function ( s ) {
			var el = self.root.querySelector( '[data-step="' + s + '"]' );
			el.hidden = s !== step;
			var ind = self.root.querySelector( '[data-step-indicator="' + s + '"]' );
			ind.classList.toggle( 'is-active', s === step );
		} );
		var focusTarget = step === 'details' ? this.form.querySelector( 'input:not([type=hidden]):not([readonly])' ) : this.root.querySelector( '[data-step="' + step + '"] [tabindex="-1"]' );
		if ( focusTarget ) {
			focusTarget.focus( { preventScroll: false } );
		}
	};

	PaymentForm.prototype.showAlert = function ( message, type ) {
		this.alertEl.textContent = message;
		this.alertEl.className = 'phx-pay__alert phx-pay__alert--' + ( type || 'error' );
		this.alertEl.hidden = false;
	};

	PaymentForm.prototype.clearAlert = function () {
		this.alertEl.hidden = true;
		this.alertEl.textContent = '';
	};

	PaymentForm.prototype.setFieldError = function ( name, message ) {
		var input = this.form.querySelector( '[name="' + name + '"]:not([type=hidden])' );
		var err = this.form.querySelector( '[data-error-for="' + name + '"]' );
		if ( err ) {
			err.textContent = message || '';
		}
		if ( input ) {
			if ( message ) {
				input.setAttribute( 'aria-invalid', 'true' );
			} else {
				input.removeAttribute( 'aria-invalid' );
			}
		}
	};

	PaymentForm.prototype.collect = function () {
		var f = this.form;
		var get = function ( n ) {
			var el = f.querySelector( '[name="' + n + '"]' );
			return el ? el.value.trim() : '';
		};
		return {
			name: get( 'name' ),
			email: get( 'email' ),
			reference: get( 'reference' ),
			description: get( 'description' ),
			amount: get( 'amount' ),
			amount_token: get( 'amount_token' ),
		};
	};

	PaymentForm.prototype.validate = function ( d ) {
		var errors = {};
		var i18n = cfg.i18n;
		if ( ! d.name ) {
			errors.name = i18n.required;
		}
		if ( ! d.email ) {
			errors.email = i18n.required;
		} else if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( d.email ) ) {
			errors.email = i18n.email;
		}
		if ( ! d.amount_token ) {
			var minor = parseAmount( d.amount );
			if ( minor === null || minor <= 0 ) {
				errors.amount = d.amount ? i18n.amount : i18n.required;
			} else if ( minor < this.min || minor > this.max ) {
				errors.amount = sprintf( i18n.amountRange, formatMoney( this.min, this.currency, this.symbol ), formatMoney( this.max, this.currency, this.symbol ) );
			}
		}
		return errors;
	};

	PaymentForm.prototype.applyErrors = function ( errors ) {
		var self = this;
		[ 'name', 'email', 'reference', 'description', 'amount' ].forEach( function ( n ) {
			self.setFieldError( n, errors[ n ] );
		} );
		var first = Object.keys( errors )[ 0 ];
		if ( first ) {
			var el = this.form.querySelector( '[name="' + first + '"]:not([type=hidden])' );
			if ( el ) {
				el.focus();
			}
		}
	};

	PaymentForm.prototype.onReview = function ( e ) {
		e.preventDefault();
		if ( this.done ) {
			return;
		}
		var d = this.collect();
		var errors = this.validate( d );
		this.applyErrors( errors );
		if ( Object.keys( errors ).length ) {
			this.showAlert( cfg.i18n.fixErrors );
			return;
		}
		this.data = d;
		this.data.amount_minor = parseAmount( d.amount );
		this.fillSummary();
		this.showStep( 'review' );
		this.renderPaymentMethods();
	};

	PaymentForm.prototype.fillSummary = function () {
		var d = this.data;
		var amountText = formatMoney( d.amount_minor, this.currency, this.symbol );
		var map = {
			name: d.name,
			email: d.email,
			reference: d.reference || '—',
			description: d.description || '—',
			amount: amountText,
		};
		this.root.querySelectorAll( '[data-summary]' ).forEach( function ( el ) {
			el.textContent = map[ el.getAttribute( 'data-summary' ) ];
		} );
	};

	PaymentForm.prototype.setProcessing = function ( on, text ) {
		var el = this.root.querySelector( '[data-role="processing"]' );
		el.hidden = ! on;
		this.root.querySelector( '[data-role="processing-text"]' ).textContent = on ? text || cfg.i18n.processing : '';
		this.root.classList.toggle( 'is-busy', !! on );
	};

	/* ---- Server calls ------------------------------------------------ */

	PaymentForm.prototype.createOrder = function ( funding ) {
		var self = this;
		if ( this.busy || this.done ) {
			return Promise.reject( new Error( 'busy' ) );
		}
		this.busy = true;
		this.clearAlert();
		var d = this.data;
		var payload = {
			attempt_id: uuid(),
			funding: funding,
			name: d.name,
			email: d.email,
			reference: d.reference,
			description: d.description,
			amount: d.amount,
			amount_token: d.amount_token,
		};
		var attempt = function ( retried ) {
			return api( 'orders', payload ).catch( function ( err ) {
				// One automatic retry on network failure; the server
				// de-duplicates by attempt_id so no second order is created.
				if ( err.code === 'network' && ! retried ) {
					return attempt( true );
				}
				throw err;
			} );
		};
		return attempt( false ).then(
			function ( res ) {
				self.busy = false;
				return res.order_id;
			},
			function ( err ) {
				self.busy = false;
				self.handleError( err );
				throw err;
			}
		);
	};

	PaymentForm.prototype.capture = function ( orderId ) {
		var self = this;
		this.setProcessing( true );
		return api( 'orders/' + encodeURIComponent( orderId ) + '/capture' ).then(
			function ( res ) {
				self.setProcessing( false );
				self.showResult( res );
				return res;
			},
			function ( err ) {
				self.setProcessing( false );
				throw err;
			}
		);
	};

	PaymentForm.prototype.handleError = function ( err ) {
		if ( err && err.message === 'busy' ) {
			return;
		}
		if ( err && err.fields ) {
			this.showStep( 'details' );
			this.applyErrors( err.fields );
		}
		this.showAlert( ( err && err.message ) || cfg.i18n.generic );
	};

	/* ---- Result screens --------------------------------------------- */

	PaymentForm.prototype.showResult = function ( res ) {
		var ok = res.status === 'completed';
		var pending = res.status === 'pending' || res.status === 'processing';
		var icon = this.root.querySelector( '[data-role="result-icon"]' );
		var details = this.root.querySelector( '[data-role="result-details"]' );
		var title = this.root.querySelector( '[data-role="result-title"]' );

		if ( ok || pending ) {
			this.done = true;
			this.root.classList.add( 'is-done' );
		}

		icon.className = 'phx-pay__result-icon phx-pay__result-icon--' + ( ok ? 'success' : pending ? 'pending' : 'error' );
		title.textContent = ok ? cfg.i18n.successTitle : pending ? cfg.i18n.pendingTitle : cfg.i18n.failedTitle;
		this.root.querySelector( '[data-role="result-message"]' ).textContent = res.message || '';

		details.innerHTML = '';
		var rows = [
			[ cfg.i18n.lblAmount, res.amount ],
			[ cfg.i18n.lblReference, res.reference ],
			[ cfg.i18n.lblTransaction, res.transaction_id ],
			[ cfg.i18n.lblPaymentId, res.payment_id ],
		];
		rows.forEach( function ( r ) {
			if ( ! r[ 1 ] ) {
				return;
			}
			var wrap = document.createElement( 'div' );
			var dt = document.createElement( 'dt' );
			var dd = document.createElement( 'dd' );
			dt.textContent = r[ 0 ];
			dd.textContent = r[ 1 ];
			wrap.appendChild( dt );
			wrap.appendChild( dd );
			details.appendChild( wrap );
		} );

		this.root.querySelector( '[data-action="restart"]' ).hidden = ok || pending;
		this.showStep( 'done' );
	};

	/* ---- PayPal rendering ------------------------------------------- */

	PaymentForm.prototype.renderPaymentMethods = function () {
		var self = this;
		if ( this.rendered ) {
			return;
		}
		var loading = this.root.querySelector( '[data-role="sdk-loading"]' );
		loadSdk().then(
			function ( paypal ) {
				loading.hidden = true;
				self.rendered = true;
				self.renderButtons( paypal );
				if ( self.useCardFields ) {
					self.renderCardFields( paypal );
				}
			},
			function () {
				loading.hidden = true;
				self.showAlert( cfg.i18n.sdkError );
			}
		);
	};

	PaymentForm.prototype.renderButtons = function ( paypal ) {
		var self = this;
		var container = this.root.querySelector( '[data-role="paypal-buttons"]' );

		paypal.Buttons( {
			style: { layout: 'vertical', shape: 'rect', color: 'gold', label: 'pay', height: 48 },

			createOrder: function ( data ) {
				var source = ( data && data.paymentSource ) || 'paypal';
				return self.createOrder( source );
			},

			onApprove: function ( data, actions ) {
				return self.capture( data.orderID ).catch( function ( err ) {
					// Card/bank declined inside the PayPal flow: let the buyer
					// pick another funding source without starting over.
					if ( err.retry && actions && typeof actions.restart === 'function' ) {
						self.showAlert( err.message );
						return actions.restart();
					}
					self.showResult( { status: 'error', message: err.message } );
				} );
			},

			onCancel: function ( data ) {
				if ( data && data.orderID ) {
					api( 'orders/' + encodeURIComponent( data.orderID ) + '/cancel' ).catch( function () {} );
				}
				self.showAlert( cfg.i18n.cancelled, 'info' );
			},

			onError: function () {
				if ( ! self.alertEl.hidden ) {
					return; // A specific message is already shown.
				}
				self.showAlert( cfg.i18n.generic );
			},
		} ).render( container ).catch( function () {
			self.showAlert( cfg.i18n.sdkError );
		} );
	};

	PaymentForm.prototype.renderCardFields = function ( paypal ) {
		var self = this;
		if ( typeof paypal.CardFields !== 'function' ) {
			return;
		}
		var wrap = this.root.querySelector( '[data-role="card-fields"]' );
		var cardField = paypal.CardFields( {
			createOrder: function () {
				return self.createOrder( 'card_fields' );
			},
			onApprove: function ( data ) {
				return self.capture( data.orderID ).catch( function ( err ) {
					if ( err.retry ) {
						self.showAlert( err.message );
						return;
					}
					self.showResult( { status: 'error', message: err.message } );
				} );
			},
			onError: function () {
				self.setProcessing( false );
				if ( self.alertEl.hidden ) {
					self.showAlert( cfg.i18n.generic );
				}
			},
			style: {
				input: { 'font-size': '16px', 'font-family': 'inherit', padding: '12px' },
				'.invalid': { color: '#b42318' },
			},
		} );

		if ( ! cardField.isEligible() ) {
			return; // Advanced card payments not enabled for this account.
		}
		this.cardField = cardField;
		wrap.hidden = false;
		cardField.NameField( { placeholder: cfg.i18n.cardholder } ).render( wrap.querySelector( '[data-cf="name"]' ) );
		cardField.NumberField().render( wrap.querySelector( '[data-cf="number"]' ) );
		cardField.ExpiryField().render( wrap.querySelector( '[data-cf="expiry"]' ) );
		cardField.CVVField().render( wrap.querySelector( '[data-cf="cvv"]' ) );

		var btn = wrap.querySelector( '[data-action="card-submit"]' );
		btn.addEventListener( 'click', function () {
			if ( self.busy || self.done ) {
				return;
			}
			btn.disabled = true;
			self.clearAlert();
			cardField.getState().then( function ( state ) {
				if ( ! state.isFormValid ) {
					self.showAlert( cfg.i18n.cardIncomplete );
					btn.disabled = false;
					return;
				}
				self.setProcessing( true );
				return cardField.submit().catch( function ( err ) {
					self.setProcessing( false );
					if ( self.alertEl.hidden ) {
						self.showAlert( ( err && err.message && err.code ) ? err.message : cfg.i18n.cardIncomplete );
					}
				} );
			} ).finally( function () {
				btn.disabled = false;
			} );
		} );
	};

	function init() {
		document.querySelectorAll( '[data-phx-pay]' ).forEach( function ( root ) {
			if ( ! root.__phxPay ) {
				root.__phxPay = new PaymentForm( root );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	// Elementor popups / dynamic content.
	window.addEventListener( 'elementor/frontend/init', init );
	document.addEventListener( 'phenix-pay:init', init );
} )();
