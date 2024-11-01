jQuery( function($) {

	var spfwc_payment = {

		init: function() {

			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( document.body ).on( 'click', '#place_order', function() {
				if ( spfwc_payment.isSonyPaymentChosen() && sonypayment_params.linktype ) {
					if ( $( '#sonypayment_agree' ).prop( 'checked' ) ) {
						return true;
					} else {
						spfwc_payment.submit_error( '<div class="woocommerce-error">' + sonypayment_params.message.error_agree + '</div>' );
						return false;
					}
				}
			});
		},

		isSonyPaymentChosen: function() {
			return $( '#payment_method_sonypayment' ).prop( 'checked' );
		},

		block: function() {
			spfwc_payment.form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			spfwc_payment.form.unblock();
		},

		reset: function() {
			$( '.sonypayment-error' ).remove();
		},

		submit_error: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			spfwc_payment.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			spfwc_payment.form.removeClass( 'processing' ).unblock();
			spfwc_payment.form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			spfwc_payment.scroll_to_notices();
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

	spfwc_payment.init();
});
