jQuery( function($) {

	var spfwc_admin = {

		init: function() {

			if ( $( '#woocommerce_sonypayment_cardmember' ).prop( 'checked' ) ) {
				$( '#woocommerce_sonypayment_always_save' ).closest( 'tr' ).show();
			} else {
				$( '#woocommerce_sonypayment_always_save' ).closest( 'tr' ).hide();
			}

			if ( $( '#woocommerce_sonypayment_linktype' ).prop( 'checked' ) ) {
				$( '#woocommerce_sonypayment_token_code' ).closest( 'tr' ).hide();
				$( '#woocommerce_sonypayment_three_d_secure' ).closest( 'tr' ).hide();
				$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).show();
				$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).show();
				$( '#woocommerce_sonypayment_attention_3ds' ).show();
			} else {
				$( '#woocommerce_sonypayment_token_code' ).closest( 'tr' ).show();
				$( '#woocommerce_sonypayment_three_d_secure' ).closest( 'tr' ).show();
				$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).hide();
				$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).hide();
				$( '#woocommerce_sonypayment_attention_3ds' ).hide();
			}

			if ( $( '#woocommerce_sonypayment_three_d_secure' ).prop( 'checked' ) ) {
				$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).show();
				$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).show();
			} else {
				$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).hide();
				$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).hide();
			}

			$( document ).on( 'change', '#woocommerce_sonypayment_cardmember', function() {
				if ( $( this ).prop( 'checked' ) ) {
					$( '#woocommerce_sonypayment_always_save' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_sonypayment_always_save' ).closest( 'tr' ).hide();
				}
			});

			$( document ).on( 'change', '#woocommerce_sonypayment_linktype', function() {
				if ( $( this ).prop( 'checked' ) ) {
					$( '#woocommerce_sonypayment_token_code' ).closest( 'tr' ).hide();
					$( '#woocommerce_sonypayment_three_d_secure' ).closest( 'tr' ).hide();
					$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).show();
					$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).show();
					$( '#woocommerce_sonypayment_attention_3ds' ).show();
				} else {
					$( '#woocommerce_sonypayment_token_code' ).closest( 'tr' ).show();
					$( '#woocommerce_sonypayment_three_d_secure' ).closest( 'tr' ).show();
					if ( $( '#woocommerce_sonypayment_three_d_secure' ).prop( 'checked' ) ) {
						$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).show();
						$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).show();
					} else {
						$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).hide();
						$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).hide();
					}
					$( '#woocommerce_sonypayment_attention_3ds' ).hide();
				}
			});

			$( document ).on( 'change', '#woocommerce_sonypayment_three_d_secure', function() {
				if ( $( this ).prop( 'checked' ) ) {
					$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).show();
					$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_sonypayment_key_aes' ).closest( 'tr' ).hide();
					$( '#woocommerce_sonypayment_key_iv' ).closest( 'tr' ).hide();
				}
			});
		}
	};

	spfwc_admin.init();
});
