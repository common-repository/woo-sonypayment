<?php
/**
 * SPFWC_MyAccount class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_MyAccount class.
 *
 * @since 1.0.0
 */
class SPFWC_MyAccount {

	/**
	 * Constructor.
	 */
	public function __construct() {

		$settings     = get_option( 'woocommerce_sonypayment_settings', array() );
		$is_3d_secure = ( isset( $settings['three_d_secure'] ) && 'yes' === $settings['three_d_secure'] ) ? true : false;
		$linktype     = ( isset( $settings['linktype'] ) && 'yes' === $settings['linktype'] ) ? true : false;

		add_filter( 'woocommerce_get_query_vars', array( $this, 'get_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
		add_action( 'woocommerce_account_edit-cardmember_endpoint', array( $this, 'account_edit_endpoint' ) );
		add_filter( 'woocommerce_endpoint_edit-cardmember_title', array( $this, 'get_endpoint_title' ) );
		if ( $is_3d_secure || $linktype ) {
			add_action( 'template_redirect', array( $this, 'save_cardmember_3d' ) );
		} else {
			add_action( 'template_redirect', array( $this, 'save_cardmember' ) );
		}
		add_action( 'template_redirect', array( $this, 'delete_cardmember' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'account_scripts' ) );
	}

	/**
	 * Add "Edit card member page" to My Account.
	 *
	 * @param  array $query_vars Query vars.
	 * @return array
	 */
	public function get_query_vars( $query_vars ) {
		$query_vars['edit-cardmember'] = 'edit-cardmember';
		return $query_vars;
	}

	/**
	 * Menu name of "Edit card member page".
	 *
	 * @param  array $items Navigation items.
	 * @return array
	 */
	public function account_menu_items( $items ) {
		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		if ( isset( $settings['cardmember'] ) && 'yes' === $settings['cardmember'] ) {
			$customer_id = get_current_user_id();
			$member      = new SPFWC_Card_Member( $customer_id );
			if ( $member->is_card_member() ) {
				$items['edit-cardmember'] = __( 'Update credit card', 'woo-sonypayment' );
			} else {
				$items['edit-cardmember'] = __( 'Save credit card', 'woo-sonypayment' );
			}
		}
		return $items;
	}

	/**
	 * Display "Edit card member page".
	 */
	public function account_edit_endpoint() {
		$deletable   = false;
		$settings    = get_option( 'woocommerce_sonypayment_settings', array() );
		$customer_id = get_current_user_id();
		$member      = new SPFWC_Card_Member( $customer_id );
		$cardlast4   = '';
		if ( $member->is_card_member() ) {
			$response_member = $member->search_card_member();
			if ( 'OK' === $response_member['ResponseCd'] ) {
				$cardlast4 = substr( $response_member['CardNo'], -4 );
				if ( ! spfwc_get_customer_active_card_orders( $customer_id ) ) {
					$deletable = true;
				}
			}
		}
		$deletable    = apply_filters( 'spfwc_deletable_cardmember', $deletable, $customer_id );
		$is_3d_secure = ( isset( $settings['three_d_secure'] ) && 'yes' === $settings['three_d_secure'] ) ? true : false;
		$linktype     = ( isset( $settings['linktype'] ) && 'yes' === $settings['linktype'] ) ? true : false;

		if ( isset( $_GET['transaction_code'] ) ) {
			$transaction_code = wp_unslash( $_GET['transaction_code'] );
			$notice           = SPFWC_Payment_Logger::get_notice_log( $transaction_code );
			if ( $notice ) {
				wc_add_notice( $notice, 'error' );
				wc_print_notices();
				SPFWC_Payment_Logger::clear_notice_log( $transaction_code );
			}
		}

		include SPFWC_PLUGIN_DIR . '/includes/spfwc-form-cardmember.php';
	}

	/**
	 * Title of "Edit card member page".
	 *
	 * @param  string $title The page title.
	 * @return string
	 */
	public function get_endpoint_title( $title ) {
		$customer_id = get_current_user_id();
		$member      = new SPFWC_Card_Member( $customer_id );
		if ( $member->is_card_member() ) {
			$title = __( 'Update credit card', 'woo-sonypayment' );
		} else {
			$title = __( 'Save credit card', 'woo-sonypayment' );
		}
		return $title;
	}

	/**
	 * Update card information.
	 *
	 * @throws SPFWC_Exception On error.
	 */
	public function save_cardmember_3d() {
		global $wp;

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$customer_id = get_current_user_id();

		if ( $customer_id <= 0 ) {
			return;
		}

		$settings = get_option( 'woocommerce_sonypayment_settings', array() );

		if ( is_user_logged_in() && 'yes' === $settings['cardmember'] ) {

			if ( isset( $_POST['done3d_myaccount'] ) ) {
				if ( isset( $_POST['SecureResultCode'] ) && isset( $_POST['ResponseCd'] ) && 'OK' === $_POST['ResponseCd'] ) {
					$member = new SPFWC_Card_Member( $customer_id );
					// Search of card member.
					$response_member = $member->search_card_member( $param_list );
					if ( 'OK' !== $response_member['ResponseCd'] ) {
						delete_user_meta( $customer_id, '_spfwc_member_id' );
						delete_user_meta( $customer_id, '_spfwc_member_pass' );
					}
				} else {
					delete_user_meta( $customer_id, '_spfwc_member_id' );
					delete_user_meta( $customer_id, '_spfwc_member_pass' );
				}
				wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
				exit;

			} else {

				if ( empty( $_POST['action'] ) || 'save_cardmember' !== wp_unslash( $_POST['action'] ) || empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'spfwc_edit_cardmember' ) ) {
					return;
				}

				wc_nocache_headers();

				if ( empty( $_POST['sonypayment_card_number'] ) ) {
					wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Card number', 'woo-sonypayment' ) ), 'error' );
				}

				if ( empty( $_POST['sonypayment_card_expmm'] ) || empty( $_POST['sonypayment_card_expyy'] ) ) {
					wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Expiry (MM/YY)', 'woo-sonypayment' ) ), 'error' );
				}

				if ( isset( $settings['seccd'] ) && 'yes' === $settings['seccd'] ) {
					if ( empty( $_POST['sonypayment_card_seccd'] ) ) {
						wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Card code', 'woo-sonypayment' ) ), 'error' );
					}
				}

				if ( empty( $_POST['sonypayment_card_name'] ) ) {
					wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Card name', 'woo-sonypayment' ) ), 'error' );
				}

				if ( 0 === wc_notice_count( 'error' ) ) {
					$param_list                    = array();
					$param_list['MerchantId']      = $settings['merchant_id'];
					$param_list['MerchantPass']    = $settings['merchant_pass'];
					$param_list['TenantId']        = $settings['tenant_id'];
					$param_list['TransactionDate'] = spfwc_get_transaction_date();
					$param_list['MerchantFree3']   = $customer_id;
					if ( isset( $_POST['transaction_code'] ) ) {
						$param_list['MerchantFree1'] = wp_unslash( $_POST['transaction_code'] );
					}
					try {
						$token = ( isset( $_POST['sonypayment_token_code'] ) ) ? trim( wp_unslash( $_POST['sonypayment_token_code'] ) ) : '';
						if ( ! empty( $token ) ) {
							// Refer to token status.
							$sln                     = new SPFWC_SLN_Connection();
							$params                  = array();
							$param_list['Token']     = $token;
							$param_list['OperateId'] = '1TokenSearch';
							$params['param_list']    = $param_list;
							$params['send_url']      = $sln->send_url_token();
							$response_token          = $sln->connection( $params );
							if ( 'OK' !== $response_token['ResponseCd'] || 'OK' !== $response_token['TokenResponseCd'] ) {
								$responsecd = explode( '|', $response_token['ResponseCd'] . '|' . $response_token['TokenResponseCd'] );
								foreach ( (array) $responsecd as $cd ) {
									if ( 'OK' !== $cd ) {
										$response_token[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
									}
								}
								$localized_message = __( 'Update processing failed. Please retry.', 'woo-sonypayment' );
								throw new SPFWC_Exception( print_r( $response_token, true ), $localized_message );
							}
						}

						$post_data = wp_unslash( $_POST );
						SPFWC_Payment_Logger::add_post_log( $post_data, $param_list['MerchantFree1'] );
						$customer = get_user_by( 'id', absint( $customer_id ) );

						$member = new SPFWC_Card_Member( $customer_id );
						if ( $member->is_card_member() ) {
							// Update card member.
							$param_list['OperateId'] = '4MemChg';
							$param_list['KaiinId']   = $member->get_member_id();
							$param_list['KaiinPass'] = $member->get_member_pass();
						} else {
							// Register card member.
							$member_id               = $member->make_member_id( $customer_id );
							$member_pass             = $member->make_member_pass();
							$param_list['OperateId'] = '4MemAdd';
							$param_list['KaiinId']   = $member_id;
							$param_list['KaiinPass'] = $member_pass;
							$member->set_member_id( $member_id );
							$member->set_member_pass( $member_pass );
						}

						// Redirect to 3D Secure Authentication page.
						$param_list['ProcNo']          = spfwc_get_transaction_code( 7 );
						$redirect_url                  = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_sonypayment' ) );
						$param_list['RedirectUrl']     = esc_url( $redirect_url );
						$param_list['BillingFullName'] = ( isset( $post_data['sonypayment_billing_name'] ) ) ? trim( $post_data['sonypayment_billing_name'] ) : '';
						$param_list['BillingEmail']    = $customer->user_email;

						$encrypt_value = SPFWC_SLN_Connection::get_encrypt_value_3dsecure( $param_list );
						$send_url      = SPFWC_SLN_Connection::send_url_3dsecure();
						ob_start();
						?>
						<!DOCTYPE html>
						<html lang="ja">
						<head>
						<title></title>
						</head>
						<body onload="javascript:document.forms['redirectForm'].submit();">
						<form action="<?php echo esc_url( $send_url ); ?>" method="post" id="redirectForm">
						<input type="hidden" name="MerchantId" value="<?php echo esc_attr( $settings['merchant_id'] ); ?>" />
						<input type="hidden" name="EncryptValue" value="<?php echo esc_attr( $encrypt_value ); ?>" />
						</form>
						</body>
						</html>
						<?php
						$redirect_form = ob_get_contents();
						ob_end_clean();
						echo $redirect_form;
						exit;

					} catch ( SPFWC_Exception $e ) {
						SPFWC_Logger::add_log( 'Cardmember Error: ' . $e->getMessage() );
						wc_add_notice( $e->getLocalizedMessage(), 'error' );
						wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
						exit;
					}
				} else {
					wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
					exit;
				}
			}
		}
	}

	/**
	 * Update card information.
	 *
	 * @throws SPFWC_Exception On error.
	 */
	public function save_cardmember() {
		global $wp;

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		if ( empty( $_POST['action'] ) || 'save_cardmember' !== wp_unslash( $_POST['action'] ) || empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'spfwc_edit_cardmember' ) ) {
			return;
		}

		wc_nocache_headers();

		$customer_id = get_current_user_id();

		if ( $customer_id <= 0 ) {
			return;
		}

		$settings = get_option( 'woocommerce_sonypayment_settings', array() );

		if ( empty( $_POST['sonypayment_card_number'] ) ) {
			wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Card number', 'woo-sonypayment' ) ), 'error' );
		}

		if ( empty( $_POST['sonypayment_card_expmm'] ) || empty( $_POST['sonypayment_card_expyy'] ) ) {
			wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Expiry (MM/YY)', 'woo-sonypayment' ) ), 'error' );
		}

		if ( isset( $settings['seccd'] ) && 'yes' === $settings['seccd'] ) {
			if ( empty( $_POST['sonypayment_card_seccd'] ) ) {
				wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Card code', 'woo-sonypayment' ) ), 'error' );
			}
		}

		if ( empty( $_POST['sonypayment_card_name'] ) || empty( $_POST['sonypayment_card_name'] ) ) {
			wc_add_notice( sprintf( __( '%s is a required field.', 'woo-sonypayment' ), __( 'Card name', 'woo-sonypayment' ) ), 'error' );
		}

		if ( 0 === wc_notice_count( 'error' ) ) {
			try {
				if ( is_user_logged_in() && 'yes' === $settings['cardmember'] ) {
					$param_list                    = array();
					$param_list['MerchantId']      = $settings['merchant_id'];
					$param_list['MerchantPass']    = $settings['merchant_pass'];
					$param_list['TenantId']        = $settings['tenant_id'];
					$param_list['TransactionDate'] = spfwc_get_transaction_date();
					$param_list['MerchantFree1']   = spfwc_get_transaction_code();
					$param_list['MerchantFree3']   = $customer_id;

					$token = ( isset( $_POST['sonypayment_token_code'] ) ) ? trim( wp_unslash( $_POST['sonypayment_token_code'] ) ) : '';
					if ( ! empty( $token ) ) {
						// Refer to token status.
						$sln                     = new SPFWC_SLN_Connection();
						$param_list['Token']     = $token;
						$param_list['OperateId'] = '1TokenSearch';
						$params['param_list']    = $param_list;
						$params['send_url']      = $sln->send_url_token();
						$response_token          = $sln->connection( $params );
						if ( 'OK' !== $response_token['ResponseCd'] || 'OK' !== $response_token['TokenResponseCd'] ) {
							$responsecd = explode( '|', $response_token['ResponseCd'] . '|' . $response_token['TokenResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								if ( 'OK' !== $cd ) {
									$response_token[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
								}
							}
							$localized_message = __( 'Update processing failed. Please retry.', 'woo-sonypayment' );
							throw new SPFWC_Exception( print_r( $response_token, true ), $localized_message );
						}
					}

					$member = new SPFWC_Card_Member( $customer_id );
					if ( $member->is_card_member() ) {
						// Search of card member.
						$response_member = $member->search_card_member( $param_list );
						if ( 'OK' === $response_member['ResponseCd'] ) {
							// Update card member.
							$response_member = $member->update_card_member( $param_list );
							if ( 'OK' === $response_member['ResponseCd'] ) {
								wc_add_notice( __( 'Card member updated successfully.', 'woo-sonypayment' ) );
							} else {
								$responsecd = explode( '|', $response_member['ResponseCd'] );
								foreach ( (array) $responsecd as $cd ) {
									$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
								}
								$localized_message = __( 'Failed updating card number.', 'woo-sonypayment' );
								throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
							}
						} else {
							$responsecd = explode( '|', $response_member['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
							}
							$localized_message = __( 'Card member does not found.', 'woo-sonypayment' );
							throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
						}
					} else {
						// Register card member.
						$response_member = $member->create_card_member( $param_list );
						if ( 'OK' === $response_member['ResponseCd'] ) {
							wc_add_notice( __( 'Card member saved successfully.', 'woo-sonypayment' ) );
						} else {
							$responsecd = explode( '|', $response_member['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
							}
							$localized_message = __( 'Failed saving card member.', 'woo-sonypayment' );
							throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
						}
					}
				}

				wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
				exit;

			} catch ( SPFWC_Exception $e ) {
				SPFWC_Logger::add_log( 'Cardmember Error: ' . $e->getMessage() );
				wc_add_notice( $e->getLocalizedMessage(), 'error' );
				wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
				exit;
			}
		} else {
			wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}
	}

	/**
	 * Update card member.
	 *
	 * @throws SPFWC_Exception On error.
	 */
	public function delete_cardmember() {
		global $wp;

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		if ( empty( $_POST['action'] ) || 'delete_cardmember' !== wp_unslash( $_POST['action'] ) || empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'spfwc_edit_cardmember' ) ) {
			return;
		}

		wc_nocache_headers();

		$customer_id = get_current_user_id();

		if ( $customer_id <= 0 ) {
			return;
		}

		$settings = get_option( 'woocommerce_sonypayment_settings', array() );

		try {
			if ( is_user_logged_in() && 'yes' === $settings['cardmember'] ) {
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TenantId']        = $settings['tenant_id'];
				$param_list['TransactionDate'] = spfwc_get_transaction_date();
				$param_list['MerchantFree1']   = spfwc_get_transaction_code();
				$param_list['MerchantFree3']   = $customer_id;

				$member = new SPFWC_Card_Member( $customer_id );
				if ( $member->is_card_member() ) {
					// Search of card member.
					$response_member = $member->search_card_member( $param_list );
					if ( 'OK' === $response_member['ResponseCd'] ) {
						// Delete of card member.
						$response_member = $member->delete_card_member( $param_list );
						if ( 'OK' !== $response_member['ResponseCd'] ) {
							$responsecd = explode( '|', $response_member['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
							}
							$localized_message = __( 'Failed deleting card member.', 'woo-sonypayment' );
							throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
						}
					} else {
						$responsecd = explode( '|', $response_member['ResponseCd'] );
						foreach ( (array) $responsecd as $cd ) {
							$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
						}
						$localized_message = __( 'Card member does not found.', 'woo-sonypayment' );
						throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
					}
				} else {
					$localized_message = __( 'Card member does not found.', 'woo-sonypayment' );
					throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
				}
			}

			wc_add_notice( __( 'Card member deleted successfully.', 'woo-sonypayment' ) );
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;

		} catch ( SPFWC_Exception $e ) {
			SPFWC_Logger::add_log( 'Cardmember Error: ' . $e->getMessage() );
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			wp_safe_redirect( wc_get_endpoint_url( 'edit-cardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}
	}

	/**
	 * Outputs scripts.
	 */
	public function account_scripts() {
		global $wp;

		if ( ! is_page( wc_get_page_id( 'myaccount' ) ) && ! isset( $wp->query_vars['edit-cardmember'] ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		if ( ! isset( $settings['cardmember'] ) || 'yes' !== $settings['cardmember'] ) {
			return;
		}

		wp_register_script( 'sonypayment_script', plugins_url( 'assets/js/spfwc-myaccount.js', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION, true );
		$customer_id                             = get_current_user_id();
		$member                                  = new SPFWC_Card_Member( $customer_id );
		$sonypayment_params                      = array();
		$sonypayment_params['is_user_logged_in'] = is_user_logged_in();
		$sonypayment_params['is_card_member']    = $member->is_card_member();
		$sonypayment_params['seccd']             = ( isset( $settings['seccd'] ) && 'yes' === $settings['seccd'] ) ? true : false;
		$sonypayment_params['cardmember']        = ( isset( $settings['cardmember'] ) && 'yes' === $settings['cardmember'] ) ? true : false;
		$sonypayment_params['is_3d_secure']      = ( isset( $settings['three_d_secure'] ) && 'yes' === $settings['three_d_secure'] ) ? true : false;
		$sonypayment_params['linktype']          = ( isset( $settings['linktype'] ) && 'yes' === $settings['linktype'] ) ? true : false;
		$sonypayment_params['return_url']        = wc_get_page_permalink( 'myaccount' );
		$sonypayment_params['message']           = array(
			'error_card_number' => __( 'The card number is not a valid credit card number.', 'woo-sonypayment' ),
			'error_card_expmm'  => __( 'The card\'s expiration month is invalid.', 'woo-sonypayment' ),
			'error_card_expyy'  => __( 'The card\'s expiration year is invalid.', 'woo-sonypayment' ),
			'error_card_seccd'  => __( 'The card\'s security code is invalid.', 'woo-sonypayment' ),
			'error_card'        => __( 'Your credit card information is incorrect.', 'woo-sonypayment' ),
			'confirm_delete'    => __( 'Are you sure you want to delete card member?', 'woo-sonypayment' ),
			'error_agree'       => __( 'Please check the "I agree to the handling of personal information" checkbox.', 'woo-sonypayment' ),
		);
		wp_localize_script( 'sonypayment_script', 'sonypayment_params', apply_filters( 'sonypayment_params', $sonypayment_params ) );
		wp_enqueue_script( 'sonypayment_script' );

		if ( ! empty( $settings['token_code'] ) ) {
			$api_token = SPFWC_SLN_Connection::api_token_url();
			?>
		<script type="text/javascript"
		src="<?php echo esc_attr( $api_token ); ?>?k_TokenNinsyoCode=<?php echo esc_attr( $settings['token_code'] ); ?>" callBackFunc="setToken" class="spsvToken">
		</script>
			<?php
		}
	}
}

new SPFWC_MyAccount();
