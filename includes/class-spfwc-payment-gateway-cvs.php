<?php
/**
 * WC_Gateway_SonyPayment_CVS class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_SonyPayment_CVS class.
 *
 * @extends WC_Payment_Gateway
 * @since 1.0.0
 */
class WC_Gateway_SonyPayment_CVS extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'sonypayment_cvs';
		$this->has_fields         = true;
		$this->method_title       = __( 'SonyPayment Online Payment Collection Agency Service', 'woo-sonypayment' );
		$this->method_description = __( 'When you contract with Sony Payment Services, the payment of convenience store, bank ATM, online banking will be available. Note that sole proprietorship can not be accepted.', 'woo-sonypayment' );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->enabled        = $this->get_option( 'enabled' );
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->testmode       = 'yes' === $this->get_option( 'testmode' );
		$this->merchant_id    = $this->get_option( 'merchant_id' );
		$this->merchant_pass  = $this->get_option( 'merchant_pass' );
		$this->paylimit       = $this->get_option( 'paylimit' );
		$this->settlement_fee = 'yes' === $this->get_option( 'settlement_fee' );
		$this->order_status   = $this->get_option( 'order_status', 'processing' );
		$this->logging        = 'yes' === $this->get_option( 'logging', 'yes' );

		$this->order_button_text = __( 'Continue to payment', 'woo-sonypayment' );

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_payment_detail' ), 10, 4 );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {

		return in_array( get_woocommerce_currency(), apply_filters( 'spfwc_supported_currencies', array( 'JPY' ) ) );
	}

	/**
	 * Admin save options.
	 */
	public function admin_options() {

		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error"><p><strong><?php esc_html_e( 'Gateway disabled', 'woo-sonypayment' ); ?></strong>: <?php esc_html_e( 'SonyPayment does not support your store currency.', 'woo-sonypayment' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Save settings.
	 */
	public function process_admin_options() {
		global $current_section;

		if ( $this->id == $current_section ) {
			parent::process_admin_options();

			$settings = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
			if ( ( isset( $settings['settlement_fee'] ) && 'yes' === $settings['settlement_fee'] ) &&
				isset( $_POST['cvs_amount_from'] ) && isset( $_POST['cvs_amount_to'] ) && isset( $_POST['cvs_fee'] ) ) {

				$cvs_amount_from = wc_clean( wp_unslash( $_POST['cvs_amount_from'] ) );
				$cvs_amount_to   = wc_clean( wp_unslash( $_POST['cvs_amount_to'] ) );
				$cvs_fee         = wc_clean( wp_unslash( $_POST['cvs_fee'] ) );

				$settlement_fees = array();
				foreach ( (array) $cvs_fee as $i => $value ) {
					$settlement_fees[ $i ] = array(
						'amount_from' => $cvs_amount_from[ $i ],
						'amount_to'   => $cvs_amount_to[ $i ],
						'fee'         => $cvs_fee[ $i ],
					);
				}
				update_option( 'spfwc_cvs_settlement_fees', $settlement_fees );
			}
		}
	}

	/**
	 * Checks if required items are set.
	 *
	 * @return bool
	 */
	public function is_valid_setting() {

		if ( empty( $this->merchant_id ) || empty( $this->merchant_pass ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( ! $this->is_valid_setting() ) {
			return false;
		}
		return parent::is_available();
	}

	/**
	 * Initialise gateway settings form fields.
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters(
			'spfwc_gateway_settings_cvs',
			array(
				'enabled'              => array(
					'title'   => __( 'Enable/Disable', 'woo-sonypayment' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable SonyPayment Online Payment Collection Agency Service', 'woo-sonypayment' ),
					'default' => 'no',
				),
				'title'                => array(
					'title'       => __( 'Title', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woo-sonypayment' ),
					'default'     => __( 'Convenience store / Electronic money / Pay-easy', 'woo-sonypayment' ),
					'desc_tip'    => true,
				),
				'description'          => array(
					'title'       => __( 'Description', 'woo-sonypayment' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'woo-sonypayment' ),
					'default'     => __( 'Pay by convenience store / Electronic money / Pay-easy', 'woo-sonypayment' ),
				),
				'testmode'             => array(
					'title'       => __( 'Test mode', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Test mode', 'woo-sonypayment' ),
					'default'     => 'yes',
					'description' => __( 'Connect to test environment and run in test mode.', 'woo-sonypayment' ),
				),
				'merchant_id'          => array(
					'title'       => __( 'Merchant ID', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Merchant ID\' (single-byte numbers only) issued from Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'merchant_pass'        => array(
					'title'       => __( 'Merchant Password', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Merchant Password\' (single-byte alphanumeric characters only) issued from Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'paylimit'             => array(
					'title'       => __( 'Due date for payment', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Due date for payment\'. The date on which the number of days specified from the purchase has passed is the payment deadline.', 'woo-sonypayment' ),
					'default'     => '7',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'settlement_fee'       => array(
					'title'       => __( 'Settlement fee', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'label'       => __( 'Set the settlement fee', 'woo-sonypayment' ),
					'desc_tip'    => true,
					'description' => __( 'It will be added to the total amount as a settlement fee.', 'woo-sonypayment' ),
					'default'     => 'no',
				),
				'settlement_fee_table' => array(
					'type'        => 'hidden',
					'default'     => '',
					'description' => __( 'Please set the settlement fee by the amount including tax. In case of the the online payment collection agency, the settlement fee is added to the total amount which is included shipping and consumption tax.', 'woo-sonypayment' ),
				),
				'order_status'         => array(
					'title'       => __( 'Order Status', 'woo-sonypayment' ),
					'type'        => 'select',
					'options'     => array(
						'processing' => __( 'Set to \'Processing\' after payment is received', 'woo-sonypayment' ),
						'completed'  => __( 'Set to \'Completed\' after payment is received', 'woo-sonypayment' ),
					),
					'default'     => 'processing',
					'description' => __( 'If a non-virtual item is purchased, it will be \'Processing\' even if \'Completed\' is selected.', 'woo-sonypayment' ),
				),
				'logging'              => array(
					'title'       => __( 'Save the log', 'woo-sonypayment' ),
					'label'       => __( 'Save the log of payment results', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'description' => __( 'Save the log of payment results to WooCommerce System Status log.', 'woo-sonypayment' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
			)
		);
	}

	/**
	 * Payment form on checkout page.
	 */
	public function payment_fields() {

		$description = $this->get_description() ? $this->get_description() : '';

		ob_start();
		echo '<div id="sonypayment-cvs-payment-data">';
		if ( $description ) {
			if ( $this->testmode ) {
				$description .= ' ' . __( 'TESTMODE RUNNING.', 'woo-sonypayment' );
				$description  = trim( $description );
			}
			echo apply_filters( 'spfwc_payment_description_cvs', wpautop( wp_kses_post( $description ) ), $this->id );
		}
		if ( $this->settlement_fee ) {
			$cart_totals = WC()->session->get( 'cart_totals' );
			if ( isset( $cart_totals['total'] ) ) {
				$fee = $this->get_settlement_fee( $cart_totals['total'] );
				if ( 0 < $fee ) {
					echo '<div class="sonypayment-cvs-settlement-fee">' . sprintf( __( '* A settlement fee of <strong>%s</strong> will be added at the time of settlement.', 'woo-sonypayment' ), wc_price( $fee ) ) . '</div>';
				}
			}
		}
		echo '</div>';
		ob_end_flush();
	}

	/**
	 * Outputs scripts.
	 */
	public function payment_scripts() {

		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
			return;
		}
		if ( 'no' === $this->enabled ) {
			return;
		}

		wp_register_script( 'sonypayment_cvs_script', plugins_url( 'assets/js/spfwc-payment-cvs.js', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION, true );
		$sonypayment_cvs_params            = array();
		$sonypayment_cvs_params['message'] = array(
			'error_billing_last_name_kana'  => __( 'Please enter billing last name (kana).', 'woo-sonypayment' ),
			'error_billing_first_name_kana' => __( 'Please enter billing first name (kana).', 'woo-sonypayment' ),
		);
		wp_localize_script( 'sonypayment_cvs_script', 'sonypayment_cvs_params', apply_filters( 'sonypayment_cvs_params', $sonypayment_cvs_params ) );
		wp_enqueue_script( 'sonypayment_cvs_script' );
	}

	/**
	 * Process the payment.
	 *
	 * @param  int  $order_id Order ID.
	 * @param  bool $retry Retry.
	 * @throws SPFWC_Exception If payment is invalid.
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true ) {

		$order = wc_get_order( $order_id );

		try {
			if ( $order->get_total() > 0 ) {
				$billing_name = $order->get_billing_last_name() . $order->get_billing_first_name();
				if ( get_option( 'wc4jp-yomigana' ) ) {
					$last_name_kana  = $order->get_meta( '_billing_yomigana_last_name', true );
					$first_name_kana = $order->get_meta( '_billing_yomigana_first_name', true );
				} else {
					$last_name_kana  = $order->get_meta( '_billing_last_name_kana', true );
					$first_name_kana = $order->get_meta( '_billing_first_name_kana', true );
				}
				$billing_name_kana = mb_convert_kana( $last_name_kana, 'KVC' ) . mb_convert_kana( $first_name_kana, 'KVC' );
				if ( empty( $billing_name_kana ) ) {
					$billing_name_kana = $billing_name;
				}
				$item_name   = '';
				$order_items = $order->get_items();
				foreach ( $order->get_items() as $item_id => $item ) {
					$item_name = mb_convert_kana( $item->get_name(), 'ASKV' );
					break;
				}
				if ( 1 < count( $order_items ) ) {
					if ( 16 < mb_strlen( $item_name . __( ' etc.', 'woo-sonypayment' ), 'UTF-8' ) ) {
						$item_name = mb_substr( $item_name, 0, 12, 'UTF-8' ) . __( ' etc.', 'woo-sonypayment' );
					}
				} else {
					if ( 16 < mb_strlen( $item_name, 'UTF-8' ) ) {
						$item_name = mb_substr( $item_name, 0, 13, 'UTF-8' ) . __( '...', 'woo-sonypayment' );
					}
				}
				$paylimit = date_i18n( 'Ymd', current_time( 'timestamp' ) + ( 86400 * $this->paylimit ) ) . '2359';
				$home_url = str_replace( 'http://', 'https://', home_url( '/' ) );

				if ( $this->settlement_fee ) {
					// Add settlement fee.
					$fee = $this->get_settlement_fee( $order->get_total() );
					if ( $fee ) {
						$fee           = floatval( $fee );
						$formatted_fee = wc_price( $fee, array( 'currency' => $order->get_currency() ) );
						$item_fee      = new WC_Order_Item_Fee();
						$item_fee->set_amount( $fee );
						$item_fee->set_total( $fee );
						$item_fee->set_name( __( 'Settlement fee', 'woo-sonypayment' ) );
						$order->add_item( $item_fee );
						$order->calculate_totals( false );
						$order->save();
					}
				}

				// Online storage agency payment connection.
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $this->merchant_id;
				$param_list['MerchantPass']    = $this->merchant_pass;
				$param_list['TransactionDate'] = spfwc_get_transaction_date();
				$param_list['MerchantFree1']   = spfwc_get_transaction_code();
				$param_list['MerchantFree2']   = $order_id;
				$param_list['Amount']          = $order->get_total();
				$param_list['OperateId']       = '2Add';
				$param_list['PayLimit']        = $paylimit;
				$param_list['NameKanji']       = $billing_name;
				$param_list['NameKana']        = $billing_name_kana;
				$param_list['TelNo']           = $order->get_billing_phone();
				$param_list['ShouhinName']     = $item_name;
				$param_list['ReturnURL']       = $home_url;
				$params['param_list']          = $param_list;
				$params['send_url']            = $sln->send_url_cvs();
				$response_data                 = $sln->connection( $params );
				if ( 'OK' === $response_data['ResponseCd'] ) {
					do_action( 'spfwc_process_payment_cvs', $response_data, $order, $params );
					$this->process_response( $response_data, $order, $params );

					$freearea = trim( $response_data['FreeArea'] );
					$url      = add_query_arg(
						array(
							'code' => $freearea,
							'rkbn' => 1,
						),
						SPFWC_SLN_Connection::redirect_url_cvs()
					);

					// Remove cart.
					WC()->cart->empty_cart();

					// Redirect to online storage agency page.
					return array(
						'result'   => 'success',
						'redirect' => $url,
					);

				} else {
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
					}
					$localized_message = __( 'Payment processing failed. Please retry.', 'woo-sonypayment' );
					throw new SPFWC_Exception( print_r( $response_data, true ), $localized_message );
				}
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} catch ( SPFWC_Exception $e ) {
			SPFWC_Logger::add_log( 'Payment CVS Error: ' . $e->getMessage() );
			wc_add_notice( $e->getLocalizedMessage(), 'error' );

			do_action( 'spfwc_process_payment_cvs_error', $e, $order );

			$order->update_status( 'failed' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Store extra meta data for an order.
	 *
	 * @param array    $response_data SLN response data.
	 * @param WC_Order $order Order object.
	 * @param array    $params Connection parameters.
	 */
	public function process_response( $response_data, $order, $params ) {

		if ( 'OK' === $response_data['ResponseCd'] && ! empty( $response_data['MerchantFree1'] ) ) {
			$order_id   = $order->get_id();
			$trans_code = $response_data['MerchantFree1'];
			$order->update_meta_data( '_spfwc_trans_code', $trans_code );

			$response_data['PayLimit'] = $params['param_list']['PayLimit'];
			$response_data['Amount']   = $params['param_list']['Amount'];
			$url                       = add_query_arg(
				array(
					'code' => trim( $response_data['FreeArea'] ),
					'rkbn' => 2,
				),
				SPFWC_SLN_Connection::redirect_url_cvs()
			);
			$order->update_meta_data( '_spfwc_cvs_url', $url );
			$order->update_meta_data( '_spfwc_cvs_paylimit', $params['param_list']['PayLimit'] );
			$order->save();
			SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );

			// $order->payment_complete( $trans_code );
			$order->set_transaction_id( $trans_code );
			$order->update_status( 'on-hold', __( 'Awaiting payment of online', 'woo-sonypayment' ) );

			// $message = __( 'Payment procedure is completed.', 'woo-sonypayment' );
			// $order->add_order_note( $message );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		do_action( 'spfwc_process_response_cvs', $response_data, $order );
	}

	/**
	 * Payment detail in emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 * @param string   $email Email address.
	 */
	public function email_payment_detail( $order, $sent_to_admin, $plain_text, $email ) {

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}
		if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ) ) ) {
			return;
		}

		$url                = $order->get_meta( '_spfwc_cvs_url', true );
		$paylimit           = $order->get_meta( '_spfwc_cvs_paylimit', true );
		$paylimit_formatted = ( $paylimit ) ? spfwc_get_formatted_date( $paylimit ) : $paylimit;

		if ( $plain_text ) {
			$payment_detail  = "\n" . esc_html__( 'Payment method', 'woo-sonypayment' ) . "\n\n";
			$payment_detail .= esc_html__( 'If the payment has not been completed yet, please proceed the payment process from the following URL.', 'woo-sonypayment' ) . "\n";
			$payment_detail .= esc_html__( 'Payment URL', 'woo-sonypayment' ) . ':' . esc_url( $url ) . "\n";
			$payment_detail .= esc_html__( 'The due date of the payment', 'woo-sonypayment' ) . ':' . esc_html( $paylimit_formatted ) . "\n";
		} else {
			$text_align     = ( is_rtl() ) ? 'right' : 'left';
			$payment_detail = '<table id="spfwc-email-payment-detail" cellspacing="0" cellpadding="0" style="width: 100%; vertical-align: top; margin-bottom: 40px; padding:0;" border="0">
				<tr>
				<td style="text-align:' . $text_align . '; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; border:0; padding:0;" valign="top" width="50%">
					<h2>' . esc_html__( 'Payment method', 'woo-sonypayment' ) . '</h2>
					<div style="padding:12px;color:#636363;border:1px solid #e5e5e5">
						<div>
							<span>' . __( 'If the payment has not been completed yet, please proceed the payment process from the following URL.', 'woo-sonypayment' ) . '</span>
						</div>
						<div>
							<strong>' . esc_html__( 'Payment URL', 'woo-sonypayment' ) . ':</strong> <span>' . esc_html( $url ) . '</span>
						</div>
						<div>
							<strong>' . esc_html__( 'The due date of the payment', 'woo-sonypayment' ) . ':</strong> <span>' . esc_html( $paylimit_formatted ) . '</span>
						</div>
					</div>
				</td></tr>
			</table>';
		}
		echo apply_filters( 'spfwc_email_payment_detail_cvs', $payment_detail, $order, $plain_text );
	}

	/**
	 * Get settlement fee.
	 *
	 * @param  string $amount Amount.
	 * @return string $amount Fee amount.
	 */
	public function get_settlement_fee( $amount ) {
		$fee                 = 0;
		$cvs_settlement_fees = get_option( 'spfwc_cvs_settlement_fees', array() );
		foreach ( $cvs_settlement_fees as $fees ) {
			if ( (float) $fees['amount_from'] <= (float) $amount ) {
				$fee = (int) $fees['fee'];
				if ( empty( $fees['amount_to'] ) || (float) $amount <= (float) $fees['amount_to'] ) {
					break;
				} else {
					$fee = 0;
				}
			}
		}
		return $fee;
	}
}
