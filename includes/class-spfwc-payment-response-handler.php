<?php
/**
 * SPFWC_Payment_Response_Handler class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Payment_Response_Handler class.
 *
 * @since 1.0.0
 */
class SPFWC_Payment_Response_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_wc_sonypayment', array( $this, 'response_handler' ) );
		add_action( 'woocommerce_api_wc_sonypayment_transfer', array( $this, 'block_process_payment_transfer' ) );
	}

	/**
	 * Response handler.
	 *
	 * @throws SPFWC_Exception Failed to return from 3D Secure authentication..
	 */
	public function response_handler() {

		if ( ! isset( $_GET['wc-api'] ) || ( 'wc_sonypayment' !== wp_unslash( $_GET['wc-api'] ) ) ) {
			return;
		}

		try {
			// Payment response.
			if ( $this->is_valid_payment_notification() ) {
				$settings = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
				if ( $settings['merchant_id'] === wp_unslash( $_REQUEST['MerchantId'] ) ) {
					$response_data = wp_unslash( $_REQUEST );

					$order_id = absint( $response_data['MerchantFree2'] );
					$order    = wc_get_order( $order_id );

					if ( isset( $response_data['CvsCd'] ) ) {
						$cvs_name = spfwc_get_cvs_name( $response_data['CvsCd'] );
						$message  = sprintf( __( 'Payment completed in %s.', 'woo-sonypayment' ), $cvs_name );
					} else {
						$message = __( 'Payment is completed.', 'woo-sonypayment' );
					}

					if ( 'completed' === $settings['order_status'] ) {
						$only_virtual = false;
						$order_items  = $order->get_items();
						if ( 0 < count( $order_items ) ) {
							$only_virtual = true;
							foreach ( $order->get_items() as $item ) {
								$product = $item->get_product();
								if ( ! $product->is_virtual() ) {
									$only_virtual = false;
									break;
								}
							}
						}
						$order_status = ( $only_virtual ) ? 'completed' : 'processing';
						$order->update_status( $order_status, $message );
					} else {
						$order->update_status( 'processing', $message );
					}
					// wp_update_post(
					// 	array(
					// 		'ID'          => $order_id,
					// 		'post_status' => 'wc-processing',
					// 	)
					// );
					// $order->add_order_note( $message );

					$trans_code                 = $response_data['MerchantFree1'];
					$response_data['OperateId'] = 'paid';
					SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				}

				header( 'HTTP/1.1 200 OK' );
				exit;

			} elseif ( isset( $_REQUEST['EncryptValue'] ) ) {
				$settings = get_option( 'woocommerce_sonypayment_settings', array() );

				// 3D Secure Authentication response.
				if ( 'yes' !== $settings['linktype'] && 'yes' === $settings['three_d_secure'] ) {
					$encrypt_value = SPFWC_SLN_Connection::get_decrypt_value_3dsecure( wp_unslash( $_REQUEST['EncryptValue'] ) );
					if ( ! $encrypt_value ) {
						throw new SPFWC_Exception( 'EncryptValue is empty.' );
					}

					parse_str( $encrypt_value, $response_data );
					if ( ! $this->is_valid_3dsecure_response( $response_data ) ) {
						throw new SPFWC_Exception( 'EncryptValue is invalid.' );
					}

					// From checkout page.
					if ( '3Secure' === $response_data['OperateId'] ) {

						// Redirect to error page when authentication fails on (1) (8) (9).
						if ( 1 === (int) $response_data['SecureResultCode'] || 8 === (int) $response_data['SecureResultCode'] || 9 === (int) $response_data['SecureResultCode'] ) {
							$responsecd = explode( '|', $response_data['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
							}
							SPFWC_Logger::add_log( '[3Secure] Error: ' . print_r( $response_data, true ) );
							$localized_message = __( '3D Secure authentication failed.', 'woo-sonypayment' );
							wc_add_notice( $localized_message, 'error' );
							wp_safe_redirect( wc_get_checkout_url() );
							exit;
						}

						$post3d = SPFWC_Payment_Logger::get_post_log( $response_data['MerchantFree1'] );
						if ( $post3d ) :
							SPFWC_Payment_Logger::clear_post_log( $response_data['MerchantFree1'] );
							if ( isset( $post3d['is_block'] ) && $post3d['is_block'] ) {
								$meta_value                                     = array();
								$meta_value['woocommerce_checkout_place_order'] = $post3d['woocommerce_checkout_place_order'];
								if ( isset( $post3d['sonypayment_token_code'] ) ) {
									$meta_value['sonypayment_token_code'] = $post3d['sonypayment_token_code'];
								}
								if ( isset( $post3d['sonypayment_card_paytype'] ) ) {
									$meta_value['sonypayment_card_paytype'] = $post3d['sonypayment_card_paytype'];
								}
								if ( isset( $post3d['sonypayment_card_member_option'] ) ) {
									$meta_value['sonypayment_card_member_option'] = $post3d['sonypayment_card_member_option'];
								}
								if ( isset( $post3d['sonypayment_save_payment_method'] ) ) {
									$meta_value['sonypayment_save_payment_method'] = $post3d['sonypayment_save_payment_method'];
								}
								$meta_value = array_merge( $meta_value, $response_data );
								$key        = uniqid( mt_rand() );

								$order = wc_get_order( $post3d['order_id'] );
								$order->update_meta_data( 'wc_sonypayment_block_process_payment_retransfer_' . $key, $meta_value );
								$order->save();
								wp_safe_redirect( add_query_arg( array(
									'order_id' => $post3d['order_id'],
									'key'      => $key,
								), wc_get_checkout_url() ) );
								exit;
							}
							?>
							<!DOCTYPE html>
							<html lang="ja">
							<head>
							<title></title>
							</head>
							<body onload="javascript:document.forms['redirectForm'].submit();">
							<form action="<?php echo esc_url( wc_get_checkout_url() ); ?>" method="post" id="redirectForm">
							<?php
							// Revert POST values.
							foreach ( (array) $post3d as $key => $value ) {
								if ( is_array( $value ) ) {
									foreach ( (array) $value as $k => $v ) {
										echo '<input type="hidden" name="' . $key . '[' . $k . ']" value="' . $v . '" />' . "\n";
									}
								} else {
									echo '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
								}
							}

							// Send 3D Secure Authentication results.
							if ( isset( $response_data['EncodeXId3D'] ) ) {
								echo '<input type="hidden" name="EncodeXId3D" value="' . $response_data['EncodeXId3D'] . '" />' . "\n";
							}
							if ( isset( $response_data['MessageVersionNo3D'] ) ) {
								echo '<input type="hidden" name="MessageVersionNo3D" value="' . $response_data['MessageVersionNo3D'] . '" />' . "\n";
							}
							if ( isset( $response_data['TransactionStatus3D'] ) ) {
								echo '<input type="hidden" name="TransactionStatus3D" value="' . $response_data['TransactionStatus3D'] . '" />' . "\n";
							}
							if ( isset( $response_data['CAVVAlgorithm3D'] ) ) {
								echo '<input type="hidden" name="CAVVAlgorithm3D" value="' . $response_data['CAVVAlgorithm3D'] . '" />' . "\n";
							}
							if ( isset( $response_data['ECI3D'] ) ) {
								echo '<input type="hidden" name="ECI3D" value="' . $response_data['ECI3D'] . '" />' . "\n";
							}
							if ( isset( $response_data['CAVV3D'] ) ) {
								echo '<input type="hidden" name="CAVV3D" value="' . $response_data['CAVV3D'] . '" />' . "\n";
							}
							if ( isset( $response_data['SecureResultCode'] ) ) {
								echo '<input type="hidden" name="SecureResultCode" value="' . $response_data['SecureResultCode'] . '" />' . "\n";
							}
							if ( isset( $response_data['DSTransactionId'] ) ) {
								echo '<input type="hidden" name="DSTransactionId" value="' . $response_data['DSTransactionId'] . '" />' . "\n";
							}
							if ( isset( $response_data['ThreeDSServerTransactionId'] ) ) {
								echo '<input type="hidden" name="ThreeDSServerTransactionId" value="' . $response_data['ThreeDSServerTransactionId'] . '" />' . "\n";
							}
							// 3D Secure Authentication complete.
							?>
							<input type="hidden" name="done3d" value="1" />
							<div class="wait_message" style="text-align: center; margin-top: 100px;"><?php esc_html_e( 'Please wait a moment.', 'woo-sonypayment' ); ?></div>
							</form>
							</body>
							</html>
							<?php
						else :
							SPFWC_Logger::add_log( '[3Secure] Error: ' . print_r( $response_data, true ) );
							$localized_message = __( 'Failed to return from 3D Secure authentication.', 'woo-sonypayment' );
							wc_add_notice( $localized_message, 'error' );
							wp_safe_redirect( wc_get_checkout_url() );
						endif;
						exit;

					// From myaccount page.
					} elseif ( '4MemAdd' === $response_data['OperateId'] || '4MemChg' === $response_data['OperateId'] ) {

						$redirect_url = wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) );

						// Redirect to error page when authentication fails on (1) (8) (9).
						if ( 1 === (int) $response_data['SecureResultCode'] || 8 === (int) $response_data['SecureResultCode'] || 9 === (int) $response_data['SecureResultCode'] ) {
							if ( '4MemAdd' === $response_data['OperateId'] && isset( $response_data['MerchantFree3'] ) ) {
								delete_user_meta( $response_data['MerchantFree3'], '_spfwc_member_id' );
								delete_user_meta( $response_data['MerchantFree3'], '_spfwc_member_pass' );
							}
							$responsecd = explode( '|', $response_data['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
							}
							SPFWC_Logger::add_log( '[' . $response_data['OperateId'] . '] Error: ' . print_r( $response_data, true ) );
							$localized_message = __( '3D Secure authentication failed.', 'woo-sonypayment' );
							// wc_add_notice( $localized_message, 'error' );
							SPFWC_Payment_Logger::add_notice_log( $localized_message, $response_data['MerchantFree1'] );
							wp_safe_redirect( add_query_arg( 'transaction_code', $response_data['MerchantFree1'], $redirect_url ) );
							exit;
						}

						$post3d = SPFWC_Payment_Logger::get_post_log( $response_data['MerchantFree1'] );
						if ( $post3d ) :
							SPFWC_Payment_Logger::clear_post_log( $response_data['MerchantFree1'] );
							?>
							<!DOCTYPE html>
							<html lang="ja">
							<head>
							<title></title>
							</head>
							<body onload="javascript:document.forms['redirectForm'].submit();">
							<form action="<?php echo esc_url( $redirect_url ); ?>" method="post" id="redirectForm">
							<?php
							// Revert POST values.
							foreach ( (array) $post3d as $key => $value ) {
								if ( is_array( $value ) ) {
									foreach ( (array) $value as $k => $v ) {
										echo '<input type="hidden" name="' . $key . '[' . $k . ']" value="' . $v . '" />' . "\n";
									}
								} else {
									echo '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
								}
							}

							// Send 3D Secure Authentication results.
							if ( isset( $response_data['SecureResultCode'] ) ) {
								echo '<input type="hidden" name="SecureResultCode" value="' . esc_attr( $response_data['SecureResultCode'] ) . '" />' . "\n";
							}
							if ( isset( $response_data['ResponseCd'] ) ) {
								echo '<input type="hidden" name="ResponseCd" value="' . esc_attr( $response_data['ResponseCd'] ) . '" />' . "\n";
							}
							// 3D Secure Authentication complete.
							?>
							<input type="hidden" name="done3d_myaccount" value="1" />
							<div class="wait_message" style="text-align: center; margin-top: 100px;"><?php esc_html_e( 'Please wait a moment.', 'woo-sonypayment' ); ?></div>
							</form>
							</body>
							</html>
							<?php
						else :
							SPFWC_Logger::add_log( '[' . $response_data['OperateId'] . '] Error: ' . print_r( $response_data, true ) );
							$localized_message = __( 'Failed to return from 3D Secure authentication.', 'woo-sonypayment' );
							wc_add_notice( $localized_message, 'error' );
							wp_safe_redirect( $redirect_url );
						endif;
						exit;
					}

				// Link type response.
				} else {
					$encrypt_value = SPFWC_SLN_Connection::get_decrypt_value( wp_unslash( $_REQUEST['EncryptValue'] ) );
					if ( ! $encrypt_value ) {
						throw new SPFWC_Exception( 'EncryptValue is empty.' );
					}

					parse_str( $encrypt_value, $response_data );
					if ( ! $this->is_valid_payment_response( $response_data ) ) {
						throw new SPFWC_Exception( 'EncryptValue is invalid.' );
					}

					// From checkout page.
					if ( isset( $response_data['MerchantId'] ) && isset( $response_data['MerchantFree2'] ) ) {
						if ( $response_data['MerchantId'] !== $settings['merchant_id'] ) {
							throw new SPFWC_Exception( 'EncryptValue is invalid.' );
						}

						// Cancel.
						$cancel = array( 'P51', 'P52', 'P55', 'P56', 'P62', 'P63', 'P64', 'P65', 'P69', 'P70' );
						if ( in_array( $response_data['ResponseCd'], $cancel ) ) {

							// Return checkout page redirect.
							wp_safe_redirect( wc_get_checkout_url() );

						// Success.
						} elseif ( 'OK' === $response_data['ResponseCd'] ) {

							$order_id     = absint( $response_data['MerchantFree2'] );
							$order        = wc_get_order( $order_id );

							if ( '4MemAdd' === $response_data['OperateId'] || '4MemChg' === $response_data['OperateId'] ) {
								$customer_id = absint( $response_data['MerchantFree3'] );
								$member      = new SPFWC_Card_Member( $customer_id );
								$member->set_member_id( $response_data['KaiinId'] );
								$member->set_member_pass( $response_data['KaiinPass'] );

								$settings   = get_option( 'woocommerce_sonypayment_settings', array() );
								$operate_id = $settings['operate_id'];

								$redirect_url = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_sonypayment' ) );

								// Payment process.
								$sln                           = new SPFWC_SLN_Connection();
								$params                        = array();
								$param_list                    = array();
								$param_list['MerchantPass']    = $settings['merchant_pass'];
								$param_list['TenantId']        = $settings['tenant_id'];
								$param_list['TransactionDate'] = $response_data['TransactionDate'];
								$param_list['MerchantFree1']   = $response_data['MerchantFree1'];
								$param_list['MerchantFree2']   = $response_data['MerchantFree2'];
								$param_list['MerchantFree3']   = $response_data['MerchantFree3'];
								$param_list['KaiinId']         = $response_data['KaiinId'];
								$param_list['KaiinPass']       = $response_data['KaiinPass'];
								$param_list['OperateId']       = $operate_id;
								$param_list['PayType']         = '01';
								$param_list['Amount']          = $order->get_total();
								$param_list['ProcNo']          = '0000000';
								$param_list['RedirectUrl']     = $redirect_url;
								if ( isset( $response_data['EncodeXId3D'] ) ) {
									$param_list['EncodeXId3D'] = wp_unslash( $response_data['EncodeXId3D'] );
								}
								if ( isset( $response_data['MessageVersionNo3D'] ) ) {
									$param_list['MessageVersionNo3D'] = wp_unslash( $response_data['MessageVersionNo3D'] );
								}
								if ( isset( $response_data['TransactionStatus3D'] ) ) {
									$param_list['TransactionStatus3D'] = wp_unslash( $response_data['TransactionStatus3D'] );
								}
								if ( isset( $response_data['CAVVAlgorithm3D'] ) ) {
									$param_list['CAVVAlgorithm3D'] = wp_unslash( $response_data['CAVVAlgorithm3D'] );
								}
								if ( isset( $response_data['ECI3D'] ) ) {
									$param_list['ECI3D'] = wp_unslash( $response_data['ECI3D'] );
								}
								if ( isset( $response_data['CAVV3D'] ) ) {
									$param_list['CAVV3D'] = wp_unslash( $response_data['CAVV3D'] );
								}
								if ( isset( $response_data['SecureResultCode'] ) ) {
									$param_list['SecureResultCode'] = wp_unslash( $response_data['SecureResultCode'] );
								}
								if ( isset( $response_data['DSTransactionId'] ) ) {
									$param_list['DSTransactionId'] = wp_unslash( $response_data['DSTransactionId'] );
								}
								if ( isset( $response_data['ThreeDSServerTransactionId'] ) ) {
									$param_list['ThreeDSServerTransactionId'] = wp_unslash( $response_data['ThreeDSServerTransactionId'] );
								}

								$encrypt_value = SPFWC_SLN_Connection::get_encrypt_value( $param_list );
								wp_redirect(
									add_query_arg(
										array(
											'MerchantId'   => $settings['merchant_id'],
											'EncryptValue' => urlencode( $encrypt_value ),
										),
										SPFWC_SLN_Connection::send_url_link()
									)
								);
								exit();

							} else {
								$redirect_url = $order->get_checkout_order_received_url();

								$trans_code = $response_data['MerchantFree1'];
								$order->update_meta_data( '_spfwc_trans_code', $trans_code );
								$order->save();

								if ( isset( $response_data['SecureResultCode'] ) ) {
									$response_data['Agreement'] = '1';
								}

								SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );

								$order->payment_complete( $trans_code );

								$message = __( 'Payment is completed.', 'woo-sonypayment' );
								$order->add_order_note( $message );

								if ( is_callable( array( $order, 'save' ) ) ) {
									$order->save();
								}

								// Remove cart.
								WC()->cart->empty_cart();

								// Return thank you page redirect.
								wp_safe_redirect( $redirect_url );
							}

						// Error.
						} else {
							$responsecd = explode( '|', $response_data['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
							}
							SPFWC_Logger::add_log( 'Payment Error: ' . print_r( $response_data, true ) );
							$localized_message = __( 'Payment processing failed. Please retry.', 'woo-sonypayment' );
							wc_add_notice( $localized_message, 'error' );
							wp_safe_redirect( wc_get_checkout_url() );
						}
						exit;

					// From myaccount page.
					} else {
						if ( '4MemAdd' === $response_data['OperateId'] || '4MemChg' === $response_data['OperateId'] ) {
							if ( $this->is_valid_3dsecure_response( $response_data ) ) {

								$redirect_url = wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) );

								// Redirect to error page when authentication fails on (1) (8) (9).
								if ( 1 === (int) $response_data['SecureResultCode'] || 8 === (int) $response_data['SecureResultCode'] || 9 === (int) $response_data['SecureResultCode'] ) {
									if ( '4MemAdd' === $response_data['OperateId'] && isset( $response_data['MerchantFree3'] ) ) {
										delete_user_meta( $response_data['MerchantFree3'], '_spfwc_member_id' );
										delete_user_meta( $response_data['MerchantFree3'], '_spfwc_member_pass' );
									}
									$responsecd = explode( '|', $response_data['ResponseCd'] );
									foreach ( (array) $responsecd as $cd ) {
										$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
									}
									SPFWC_Logger::add_log( '[' . $response_data['OperateId'] . '] Error: ' . print_r( $response_data, true ) );
									$localized_message = __( '3D Secure authentication failed.', 'woo-sonypayment' );
									// wc_add_notice( $localized_message, 'error' );
									SPFWC_Payment_Logger::add_notice_log( $localized_message, $response_data['MerchantFree1'] );
									wp_safe_redirect( add_query_arg( 'transaction_code', $response_data['MerchantFree1'], $redirect_url ) );
									exit;
								}

								$post3d = SPFWC_Payment_Logger::get_post_log( $response_data['MerchantFree1'] );
								if ( $post3d ) :
									SPFWC_Payment_Logger::clear_post_log( $response_data['MerchantFree1'] );
									?>
									<!DOCTYPE html>
									<html lang="ja">
									<head>
									<title></title>
									</head>
									<body onload="javascript:document.forms['redirectForm'].submit();">
									<form action="<?php echo esc_url( $redirect_url ); ?>" method="post" id="redirectForm">
									<?php
									// Revert POST values.
									foreach ( (array) $post3d as $key => $value ) {
										if ( is_array( $value ) ) {
											foreach ( (array) $value as $k => $v ) {
												echo '<input type="hidden" name="' . $key . '[' . $k . ']" value="' . $v . '" />' . "\n";
											}
										} else {
											echo '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
										}
									}

									// Send 3D Secure Authentication results.
									if ( isset( $response_data['SecureResultCode'] ) ) {
										echo '<input type="hidden" name="SecureResultCode" value="' . esc_attr( $response_data['SecureResultCode'] ) . '" />' . "\n";
									}
									if ( isset( $response_data['ResponseCd'] ) ) {
										echo '<input type="hidden" name="ResponseCd" value="' . esc_attr( $response_data['ResponseCd'] ) . '" />' . "\n";
									}
									// 3D Secure Authentication complete.
									?>
									<input type="hidden" name="done3d_myaccount" value="1" />
									<div class="wait_message" style="text-align: center; margin-top: 100px;"><?php esc_html_e( 'Please wait a moment.', 'woo-sonypayment' ); ?></div>
									</form>
									</body>
									</html>
									<?php
								else :
									SPFWC_Logger::add_log( '[' . $response_data['OperateId'] . '] Error: ' . print_r( $response_data, true ) );
									$localized_message = __( 'Failed to return from 3D Secure authentication.', 'woo-sonypayment' );
									// wc_add_notice( $localized_message, 'error' );
									SPFWC_Payment_Logger::add_notice_log( $localized_message, $response_data['MerchantFree1'] );
									wp_safe_redirect( add_query_arg( 'transaction_code', $response_data['MerchantFree1'], $redirect_url ) );
								endif;
								exit;

							} else {
								if ( 'OK' !== $response_data['ResponseCd'] ) {
									$responsecd = explode( '|', $response_data['ResponseCd'] );
									foreach ( (array) $responsecd as $cd ) {
										$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
									}
									SPFWC_Logger::add_log( '[' . $response_data['OperateId'] . '] Error: ' . print_r( $response_data, true ) );
									$localized_message = ( '4MemAdd' === $response_data['OperateId'] ) ? __( 'Failed saving card member.', 'woo-sonypayment' ) : __( 'Card member update failed.', 'woo-sonypayment' );
									wc_add_notice( $localized_message, 'error' );
									wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
									exit;
								}

								$customer_id = absint( $response_data['MerchantFree3'] );
								$member      = new SPFWC_Card_Member( $customer_id );
								$member->set_member_id( $response_data['KaiinId'] );
								$member->set_member_pass( $response_data['KaiinPass'] );
								wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
							}
						}
					}
					exit;
				}
			}
		} catch ( Exception $e ) {
			SPFWC_Logger::add_log( 'Response Error: ' . $e->getMessage() );
			wc_add_notice( $e->getLocalizedMessage(), 'error' );

			wp_die(
				$e->getMessage(),
				'Bad request',
				array(
					'response' => 400,
				)
			);
		}
	}

	/**
	 * Checks if required items are set.
	 *
	 * @return bool
	 */
	public function is_valid_payment_notification() {

		if ( ! isset( $_REQUEST['MerchantId'] ) ||
			! isset( $_REQUEST['TransactionId'] ) ||
			! isset( $_REQUEST['RecvNum'] ) ||
			! isset( $_REQUEST['NyukinDate'] ) ||
			! isset( $_REQUEST['MerchantFree1'] ) ||
			! isset( $_REQUEST['MerchantFree2'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if required items are set.
	 *
	 * @param  array $response_data 3D Secure response.
	 * @return bool
	 */
	public function is_valid_3dsecure_response( $response_data = null ) {

		if ( null === $response_data ) {
			return false;
		}

		if ( ! isset( $response_data['SecureResultCode'] ) ||
			! isset( $response_data['ResponseCd'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if required items are set.
	 *
	 * @param  array $response_data 3D Secure response.
	 * @return bool
	 */
	public function is_valid_payment_response( $response_data = null ) {

		if ( null === $response_data ) {
			return false;
		}

		// if ( ! isset( $response_data['MerchantId'] ) ||
		// 	! isset( $response_data['OperateId'] ) ||
		// 	! isset( $response_data['ResponseCd'] ) ||
		// 	! isset( $response_data['MerchantFree1'] ) ) {
		// 	return false;
		// }
		if ( ! isset( $response_data['OperateId'] ) ||
			! isset( $response_data['ResponseCd'] ) ||
			! isset( $response_data['MerchantFree1'] ) ) {
			return false;
		}

		// $settings = get_option( 'woocommerce_sonypayment_settings', array() );
		// if ( $response_data['MerchantId'] !== $settings['merchant_id'] ) {
		// 	return false;
		// }

		return true;
	}

	/**
	 * ブロックでの支払い処理.画面表示用.
	 *
	 * @return void
	 */
	public function block_process_payment_transfer() {

		$key      = ( isset( $_GET['key'] ) ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		$order_id = ( isset( $_GET['order_id'] ) ) ? (int) wc_clean( wp_unslash( $_GET['order_id'] ) ) : 0;
		if ( empty( $key ) || empty( $order_id ) ) {
			wc_add_notice( 'パラメータが不正です。', 'error' ); // todo not working
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) );
			die();
		}

		$order         = wc_get_order( $order_id );
		$redirect_form = $order->get_meta( 'wc_sonypayment_block_process_payment_transfer_' . $key, true );
		if ( empty( $redirect_form ) ) {
			wc_add_notice( 'パラメータが不正です。', 'error' ); // todo not working
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) );
			die();
		}
		$order->delete_meta_data( 'wc_sonypayment_block_process_payment_transfer_' . $key );
		$order->save();
		echo $redirect_form;
		exit;
	}
}

new SPFWC_Payment_Response_Handler();
