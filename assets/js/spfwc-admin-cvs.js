jQuery( function($) {

	var spfwc_admin_cvs = {

		init: function() {

			if ( $( '#woocommerce_sonypayment_cvs_settlement_fee' ).prop( 'checked' ) ) {
				$( '#woocommerce_sonypayment_cvs_settlement_fee_table' ).closest( 'tr' ).show();
			} else {
				$( '#woocommerce_sonypayment_cvs_settlement_fee_table' ).closest( 'tr' ).hide();
			}

			$( document ).on( 'change', '#woocommerce_sonypayment_cvs_settlement_fee', function() {
				if ( $( this ).prop( 'checked' ) ) {
					$( '#woocommerce_sonypayment_cvs_settlement_fee_table' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_sonypayment_cvs_settlement_fee_table' ).closest( 'tr' ).hide();
				}
			});
			spfwc_admin_cvs.createFeeTable();

			$( document ).on( 'click', '.insert_fee', function() {
				spfwc_admin_cvs.onAddNewRow();
			});

			$( document ).on( 'click', '.remove_fee', function() {
				spfwc_admin_cvs.onDeleteRow();
			});

			$( document ).on( 'click', '.cvs-fee-input', function() {
				spfwc_admin_cvs.onSelectedRow();
			});
		},

		createFeeTable: function() {
			var fee_table = '<table class="sonypayment_cvs_settlement_fee wc_input_table widefat" id="sonypayment-cvs-fee-table"></table>';
			var fee_table_thead = '<thead><tr><th width="8%">' + sonypayment_admin_cvs_params.label.amount_from + '</th><th width="8%">' + sonypayment_admin_cvs_params.label.amount_to + '</th><th width="8%">' + sonypayment_admin_cvs_params.label.fee + '</th></tr></thead>';
			var fee_table_tfoot = '<tfoot><tr><th colspan="3"><a href="javascript:void(0);" class="button insert_fee">' + sonypayment_admin_cvs_params.label.insert_row + '</a><a href="javascript:void(0);" class="button remove_fee">' + sonypayment_admin_cvs_params.label.remove_row + '</a></th></tr></tfoot>';
			var fee_table_tbody = '<tbody id="sonypayment-cvs-fee"></tbody>';
			$( '#woocommerce_sonypayment_cvs_settlement_fee_table' ).closest( 'td' ).append( fee_table );
			$( '#sonypayment-cvs-fee-table' ).append( fee_table_thead );
			$( '#sonypayment-cvs-fee-table' ).append( fee_table_tfoot );
			$( '#sonypayment-cvs-fee-table' ).append( fee_table_tbody );

			$.each( sonypayment_admin_cvs_params.fees, function( index, fees ) {
				$( '#sonypayment-cvs-fee' ).append( '<tr><td><input type="text" value="' + fees.amount_from + '" placeholder="*" name="cvs_amount_from[]" class="ui-autocomplete-input cvs-fee-input" style="text-transform:uppercase" autocomplete="off"></td>'
					+ '<td><input type="text" value="' + fees.amount_to + '" placeholder="*" name="cvs_amount_to[]" class="ui-autocomplete-input cvs-fee-input" style="text-transform:uppercase" autocomplete="off"></td>'
					+ '<td><input type="text" value="' + fees.fee + '" placeholder="*" name="cvs_fee[]" class="ui-autocomplete-input cvs-fee-input" style="text-transform:uppercase" autocomplete="off"></td></tr>' );
			});
			var rows = $( '#sonypayment-cvs-fee-table tbody' ).children().length;
			if ( rows < 1 ) {
				spfwc_admin_cvs.onAddNewRow();
			}
		},

		onAddNewRow: function( event ) {
			var amount_from = 0;
			var amount_to = 0;
			var rows = $( '#sonypayment-cvs-fee-table tbody' ).children().length;
			if ( 0 < rows ) {
				$( 'input[name^="cvs_amount_to"]' ).each( function( index, elem ) {
					amount_to = $( this ).val();
				});
				amount_from = ( amount_to ) ? parseInt( amount_to ) + 1 : '';
			}
			$( '#sonypayment-cvs-fee' ).append( '<tr><td><input type="text" value="' + amount_from + '" placeholder="*" name="cvs_amount_from[]" class="ui-autocomplete-input cvs-fee-input" style="text-transform:uppercase" autocomplete="off"></td>'
				+ '<td><input type="text" value="" placeholder="*" name="cvs_amount_to[]" class="ui-autocomplete-input cvs-fee-input" style="text-transform:uppercase" autocomplete="off"></td>'
				+ '<td><input type="text" value="" placeholder="*" name="cvs_fee[]" class="ui-autocomplete-input cvs-fee-input" style="text-transform:uppercase" autocomplete="off"></td></tr>' );
		},

		onDeleteRow: function() {
			$( '.current-row' ).remove();
		},

		onSelectedRow: function() {
			var focused = $( ':focus' );
			$( '#sonypayment-cvs-fee-table tr' ).removeClass( 'current-row' );
			$( focused ).closest( 'tr' ).addClass( 'current-row' );
		},

	};

	spfwc_admin_cvs.init();
});
