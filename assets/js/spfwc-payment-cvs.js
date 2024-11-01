jQuery( function($) {

	var spfwc_payment_cvs = {

		init: function() {

			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( document.body ).on( 'click', '#place_order', function() {
				if ( spfwc_payment_cvs.isSonyPaymentCvsChosen() ) {
					return spfwc_payment_cvs.checkKanaFields();
				}
			});
		},

		isSonyPaymentCvsChosen: function() {
			return $( '#payment_method_sonypayment_cvs' ).prop( 'checked' );
		},

		block: function() {
			spfwc_payment_cvs.form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			spfwc_payment_cvs.form.unblock();
		},

		reset: function() {

		},

		checkKanaFields: function() {
			var error_message = '';
			if ( $( '#billing_last_name_kana' ).val() != undefined ) {
				if ( '' == $( '#billing_last_name_kana' ).val() ) {
					error_message += '<li>' + sonypayment_cvs_params.message.error_billing_last_name_kana + '</li>';
				}
			}
			if ( $( '#billing_first_name_kana' ).val() != undefined ) {
				if ( '' == $( '#billing_first_name_kana' ).val() ) {
					error_message += '<li>' + sonypayment_cvs_params.message.error_billing_first_name_kana + '</li>';
				}
			}
			if ( error_message.length > 0 ) {
				spfwc_payment_cvs.submit_error( '<div class="woocommerce-error">' + error_message + '</div>' );
				return false;
			}

			//spfwc_payment_cvs.reset();
			return true;
		},

		submit_error: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			spfwc_payment_cvs.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			spfwc_payment_cvs.form.removeClass( 'processing' ).unblock();
			spfwc_payment_cvs.form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			spfwc_payment_cvs.scroll_to_notices();
			$( document.body ).trigger( 'checkout_error' );
		},

		scroll_to_notices: function() {
			var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' ),
				isSmoothScrollSupported = 'scrollBehavior' in document.documentElement.style;

			if ( ! scrollElement.length ) {
				scrollElement = $( '.form.checkout' );
			}

			if ( scrollElement.length ) {
				if ( isSmoothScrollSupported ) {
					scrollElement[0].scrollIntoView({
						behavior: 'smooth'
					});
				} else {
					$( 'html, body' ).animate( {
						scrollTop: ( scrollElement.offset().top - 100 )
					}, 1000 );
				}
			}
		}
	};

	spfwc_payment_cvs.init();
});
