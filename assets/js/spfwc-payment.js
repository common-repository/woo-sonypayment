jQuery( function($) {

	const isNumberString = n => typeof n === 'string' && n !== '' && ! isNaN( n );

	var spfwc_payment = {

		init: function() {

			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( document.body ).on( 'click', '#place_order', function() {
				if ( spfwc_payment.isSonyPaymentChosen() && ! sonypayment_params.linktype ) {
					if ( sonypayment_params.is_3d_secure && ! $( '#sonypayment_agree' ).prop( 'checked' ) ) {
						spfwc_payment.submit_error( '<div class="woocommerce-error">' + sonypayment_params.message.error_agree + '</div>' );
						return false;
					}
					if ( sonypayment_params.is_user_logged_in && sonypayment_params.cardmember && sonypayment_params.is_card_member ) {
						var card_member = spfwc_payment.getCardMemberOption();
						if ( card_member == 'saved' ) {
							$( '<input>', {
								type: 'hidden',
								name: 'woocommerce_checkout_place_order',
								value: ''
							}).appendTo( this.form );
							this.form.submit();
							return false;
						} else if ( card_member == 'unsaved' || card_member == 'change' ) {
							spfwc_payment.getToken();
							return false;
						} else {
							spfwc_payment.submit_error( '<div class="woocommerce-error">' + sonypayment_params.message.error_card_member_option + '</div>' );
							return false;
						}
					} else {
						spfwc_payment.getToken();
						return false;
					}
				}
			});
		},

		isSonyPaymentChosen: function() {
			return $( '#payment_method_sonypayment' ).prop( 'checked' );
		},

		isSonyPaymentCvsChosen: function() {
			return $( '#payment_method_sonypayment_cvs' ).prop( 'checked' );
		},

		getCardMemberOption: function() {
			return $( 'input[name="sonypayment_card_member_option"]:checked' ).val();
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
			$.unblockUI(); // If arriving via Payment Request Button.
		},

		reset: function() {
			$( '.sonypayment-error' ).remove();
		},

		getPayType: function() {
			var paytype = '01';
			if ( ! $( '#sonypayment-card-paytype-default' ).prop( 'disabled' ) ) {
				paytype = $( '#sonypayment-card-paytype-default option:selected' ).val();
			} else if ( ! $( '#sonypayment-card-paytype-4535' ).prop( 'disabled' ) ) {
				paytype = $( '#sonypayment-card-paytype-4535 option:selected' ).val();
			} else if ( ! $( '#sonypayment-card-paytype-37' ).prop( 'disabled' ) ) {
				paytype = $( '#sonypayment-card-paytype-37 option:selected' ).val();
			} else if ( ! $( '#sonypayment-card-paytype-36' ).prop( 'disabled' ) ) {
				paytype = $( '#sonypayment-card-paytype-36 option:selected' ).val();
			}
			return paytype;
		},

		getToken: function() {
			spfwc_payment.block();

			var check = true;
			if ( ! isNumberString( $( '#sonypayment-card-number' ).val() ) ) {
				check = false;
			}
			if ( ! isNumberString( $( '#sonypayment-card-expmm' ).val() ) ) {
				check = false;
			}
			if ( ! isNumberString( $( '#sonypayment-card-expyy' ).val() ) ) {
				check = false;
			}
			if ( $( '#sonypayment-card-seccd' ).val() != undefined ) {
				if ( ! isNumberString( $( '#sonypayment-card-seccd' ).val() ) ) {
					check = false;
				}
			}
			if ( ! isValidInput( $( '#sonypayment-card-name' ).val() ) ) {
				check = false;
			}
			if ( ! check ) {
				spfwc_payment.submit_error( '<div class="woocommerce-error"><li>' + sonypayment_params.message.error_card + '</li></div>' );
				return false;
			}

			spfwc_payment.reset();

			var cardno = $( '#sonypayment-card-number' ).val();
			var expmm = $( '#sonypayment-card-expmm' ).val();
			var expyy = $( '#sonypayment-card-expyy' ).val();
			var seccd = ( $( '#sonypayment-card-seccd' ).val() != undefined ) ? $( '#sonypayment-card-seccd' ).val() : '';
			var paytype = ( '1' == spfwc_payment.howtopay ) ? '01' : spfwc_payment.getPayType();
			SpsvApi.spsvCreateToken( cardno, expyy, expmm, seccd, '', '', '', '', '' );
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

let spfwc_payment_block_page = false;
function setToken( token, card ) {
	document.getElementById( "sonypayment-token-code" ).value = token;
	document.getElementById( "sonypayment-billing-name" ).value = document.getElementById( "sonypayment-card-name" ).value;
	if( spfwc_payment_block_page ) {
		return;
	}
	var place_order = document.createElement( "input" );
	place_order.value = "";
	place_order.name = "woocommerce_checkout_place_order";
	document.checkout.appendChild( place_order );
	document.checkout.submit();
}

function isValidInput( input ) {
	for ( let i = 0; i < input.length; i++ ) {
		let char = input.charAt(i);
		if ( !( char >= 'A' && char <= 'Z' ) && !( char >= 'a' && char <= 'z' ) && char !== ' ' ) {
			return false;
		}
	}
	return true;
}
