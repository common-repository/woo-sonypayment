jQuery( function($) {

	const isNumberString = n => typeof n === 'string' && n !== '' && ! isNaN( n );

	var spfwc_myaccount = {

		init: function() {

			if ( $( 'form.spfwc-edit-cardmember-form' ).length ) {
				this.form = $( 'form.spfwc-edit-cardmember-form' );
			}

			$( document ).on( 'click', '#save-cardmember', function() {
				if ( ( sonypayment_params.is_3d_secure || sonypayment_params.linktype ) && ! $( '#sonypayment_agree' ).prop( 'checked' ) ) {
					spfwc_myaccount.submit_error( '<div class="woocommerce-error">' + sonypayment_params.message.error_agree + '</div>' );
					return false;
				}
				$( '#edit-cardmember-action' ).val( 'save_cardmember' );
				spfwc_myaccount.getToken();
				return false;
			});

			$( document ).on( 'click', '#delete-cardmember', function() {
				if ( ! window.confirm( sonypayment_params.message.confirm_delete ) ) {
					return false;
				}
				$( '#edit-cardmember-action' ).val( 'delete_cardmember' );
			});
		},

		block: function() {
			spfwc_myaccount.form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			spfwc_myaccount.form.unblock();
		},

		reset: function() {
			$( '.sonypayment-error' ).remove();
		},

		getToken: function() {
			spfwc_myaccount.block();

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
				spfwc_myaccount.submit_error( '<div class="woocommerce-error"><li>' + sonypayment_params.message.error_card + '</li></div>' );
				return false;
			}

			spfwc_myaccount.reset();

			var cardno = $( '#sonypayment-card-number' ).val();
			var expmm = $( '#sonypayment-card-expmm' ).val();
			var expyy = $( '#sonypayment-card-expyy' ).val();
			var seccd = ( $( '#sonypayment-card-seccd' ).val() != undefined ) ? $( '#sonypayment-card-seccd' ).val() : '';
			SpsvApi.spsvCreateToken( cardno, expyy, expmm, seccd, '', '', '', '', '' );
		},

		submit_error: function( error_message ) {
			$( '.woocommerce-NoticeGroup-myaccount, .woocommerce-error, .woocommerce-message' ).remove();
			spfwc_myaccount.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-myaccount">' + error_message + '</div>' );
			spfwc_myaccount.form.removeClass( 'processing' ).unblock();
			spfwc_myaccount.form.find( '.input-text, select' ).trigger( 'validate' ).blur();
			spfwc_myaccount.scroll_to_notices();
			$( document.body ).trigger( 'myaccount_error' );
		},

		scroll_to_notices: function() {
			var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-myaccount' ),
				isSmoothScrollSupported = 'scrollBehavior' in document.documentElement.style;

			if ( ! scrollElement.length ) {
				scrollElement = $( '.form.myaccount' );
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

	spfwc_myaccount.init();
});

function setToken( token, card ) {
	document.getElementById( "sonypayment-token-code" ).value = token;
	document.getElementById( "sonypayment-billing-name" ).value = document.getElementById( "sonypayment-card-name" ).value;
	document.sonypayment_cardmember.submit();
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
