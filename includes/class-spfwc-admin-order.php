<?php
/**
 * SPFWC_Admin_Order class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Admin_Order class.
 *
 * @since 1.0.0
 */
class SPFWC_Admin_Order {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'define_columns' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'define_columns' ), 20 ); // HPOS
		add_filter( 'manage_shop_order_posts_custom_column', array( $this, 'render_columns' ), 20, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_columns' ), 20, 2 ); // HPOS

		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'wp_ajax_spfwc_settlement_actions', array( $this, 'ajax_handler' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'settlement_scripts' ) );
	}

	/**
	 * Render columm: spfwc_status.
	 *
	 * @param  array $columns Existing columns.
	 * @return array
	 */
	public function define_columns( $columns ) {
		$columns['spfwc_status'] = __( 'Payment Status', 'woo-sonypayment' );
		return $columns;
	}

	/**
	 * Render columm: spfwc_status.
	 *
	 * @param string $column Column ID to render.
	 * @param  int|WC_Order  $post_or_order_object  Post ID or WC_Order being shown.
	 */
	public function render_columns( $column, $post_or_order_object ) {

		if ( 'spfwc_status' !== $column ) {
			return;
		}

		if ( $post_or_order_object instanceof WC_order ) {
			$order    = $post_or_order_object;
			$order_id = $order->get_id();
		} else {
			$order_id = absint( $post_or_order_object );
			$order    = wc_get_order( $order_id );
		}
		if ( ! is_object( $order ) ) {
			return;
		}

		$trans_code = $order->get_meta( '_spfwc_trans_code', true );
		if ( ! $trans_code ) {
			return;
		}
		$latest_log = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
		if ( ! $latest_log ) {
			return;
		}

		$response = json_decode( $latest_log['response'], true );
		if ( 'card' === $latest_log['payment_type'] ) {
			$operation_name = spfwc_get_operation_name( $latest_log['operate_id'] );
			$class          = ( ctype_digit( substr( $latest_log['operate_id'], 0, 1 ) ) ) ? 'card-' . mb_strtolower( substr( $latest_log['operate_id'], 1 ) ) : 'card-' . $latest_log['operate_id'];
		} elseif ( 'cvs' === $latest_log['payment_type'] ) {
			$expired = $this->check_paylimit( $order_id, $trans_code );
			if ( $expired ) {
				$operation_name = __( 'Expired', 'woo-sonypayment' );
				$class          = 'cvs-expired';
			} else {
				if ( isset( $latest_log['operate_id'] ) && '2Del' === $latest_log['operate_id'] ) {
					$operation_name = __( 'Canceled', 'woo-sonypayment' );
					$class          = 'cvs-del';
				} else {
					// if ( 'on-hold' === $order->get_status() ) {
					// $operation_name = __( 'Unpaid', 'woo-sonypayment' );
					// $class = 'cvs-unpaid';
					// } elseif( isset( $response['NyukinDate'] ) ) {
					// $operation_name = __( 'Paid', 'woo-sonypayment' );
					// $class = 'cvs-paid';
					// } else {
					// $operation_name = spfwc_get_operation_name( $latest_log['operate_id'] );
					// $class = ( ctype_digit( substr( $latest_log['operate_id'], 0, 1 ) ) ) ? 'cvs-'.mb_strtolower( substr( $latest_log['operate_id'], 1 ) ) : 'cvs-'.$latest_log['operate_id'];
					// }
					if ( isset( $response['NyukinDate'] ) ) {
						$operation_name = __( 'Paid', 'woo-sonypayment' );
						$class          = 'cvs-paid';
					} else {
						$operation_name = __( 'Unpaid', 'woo-sonypayment' );
						$class          = 'cvs-unpaid';
					}
				}
			}
		}
		printf( '<mark class="order-spfwc-status %s"><span>%s</span></mark>', esc_attr( sanitize_html_class( $class ) ), esc_html( $operation_name ) );
	}

	/**
	 * Settlement actions metabox.
	 */
	public function meta_box() {
		$order_id = wc_get_order( absint( isset( $_GET['id'] ) ? $_GET['id'] : 0 ) );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( 'sonypayment' === $payment_method || 'sonypayment_cvs' === $payment_method ) {
			$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
				? wc_get_page_screen_id( 'shop-order' )
				: 'shop_order';
			add_meta_box( 'spfwc-settlement-actions', __( 'SonyPayment', 'woo-sonypayment' ), array( $this, 'settlement_actions_box' ), $screen, 'side' );
		}
	}

	/**
	 * Settlement actions metabox content.
	 *
	 * @param  int|WC_Order  $post_or_order_object  Post ID or WC_Order being shown.
	 */
	public function settlement_actions_box( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( empty( $order ) ) {
			return;
		}
		$order_id = $order->get_id();
		$payment_method = $order->get_payment_method();
		$trans_code     = $order->get_meta( '_spfwc_trans_code', true );
		if ( empty( $trans_code ) ) {
			$trans_code = spfwc_init_transaction_code();
		}
		$latest_info = $this->settlement_latest_info( $order_id, $trans_code, $payment_method );
		?>
		<div id="spfwc-settlement-latest">
		<?php echo wp_kses_post( $latest_info ); ?>
		</div>
		<p id="spfwc-settlement-latest-button"><input type="button" class="button spfwc-settlement-info" id="spfwc-<?php echo esc_attr( $order_id ); ?>-<?php echo esc_attr( $trans_code ); ?>-1" value="<?php esc_attr_e( 'Info', 'woo-sonypayment' ); ?>" /></p>
		<?php
	}

	/**
	 * Settlement actions latest.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @param  string $payment_method Payment method.
	 * @return string
	 */
	private function settlement_latest_info( $order_id, $trans_code, $payment_method ) {

		$latest     = '';
		$latest_log = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
		if ( $latest_log ) {
			$response         = json_decode( $latest_log['response'], true );
			$latest_operation = '';
			if ( 'sonypayment' === $payment_method ) {
				$operation_name   = spfwc_get_operation_name( $latest_log['operate_id'] );
				$class            = ( ctype_digit( substr( $latest_log['operate_id'], 0, 1 ) ) ) ? ' card-' . mb_strtolower( substr( $latest_log['operate_id'], 1 ) ) : ' card-' . $latest_log['operate_id'];
				$latest_operation = '<span class="spfwc-settlement-admin' . esc_attr( $class ) . '">' . esc_html( $operation_name ) . '</span>';
			} elseif ( 'sonypayment_cvs' === $payment_method ) {
				$expired = $this->check_paylimit( $order_id, $trans_code );
				if ( $expired ) {
					$latest_operation = '<span class="spfwc-settlement-admin cvs-expired">' . esc_html__( 'Expired', 'woo-sonypayment' ) . '</span>';
				} else {
					if ( isset( $latest_log['operate_id'] ) && '2Del' === $latest_log['operate_id'] ) {
						$latest_operation = '<span class="spfwc-settlement-admin cvs-del">' . esc_html__( 'Canceled', 'woo-sonypayment' ) . '</span>';
					} else {
						// $order = wc_get_order( $order_id );
						// if ( 'on-hold' === $order->get_status() ) {
						// $latest_operation = '<span class="spfwc-settlement-admin cvs-unpaid">'.esc_html__( 'Unpaid', 'woo-sonypayment' ).'</span>';
						// } elseif( isset( $response['NyukinDate'] ) ) {
						// $latest_operation = '<span class="spfwc-settlement-admin cvs-paid">'.esc_html__( 'Paid', 'woo-sonypayment' ).'</span>';
						// } else {
						// $operation_name = spfwc_get_operation_name( $latest_log['operate_id'] );
						// $class = ( ctype_digit( substr( $latest_log['operate_id'], 0, 1 ) ) ) ? ' cvs-'.mb_strtolower( substr( $latest_log['operate_id'], 1 ) ) : ' cvs-'.$latest_log['operate_id'];
						// $latest_operation = '<span class="spfwc-settlement-admin'.esc_attr( $class ).'">'.esc_html( $operation_name ).'</span>';
						// }
						if ( isset( $response['NyukinDate'] ) ) {
							$latest_operation = '<span class="spfwc-settlement-admin cvs-paid">' . esc_html__( 'Paid', 'woo-sonypayment' ) . '</span>';
						} else {
							$latest_operation = '<span class="spfwc-settlement-admin cvs-unpaid">' . esc_html__( 'Unpaid', 'woo-sonypayment' ) . '</span>';
						}
					}
				}
			}
			$latest .= '<table>
				<tr><td colspan="2">' . $latest_operation . '</td></tr>
				<tr><th>' . esc_html__( 'Transaction date', 'woo-sonypayment' ) . ':</th><td>' . esc_html( $latest_log['timestamp'] ) . '</td></tr>
				<tr><th>' . esc_html__( 'Transaction code', 'woo-sonypayment' ) . ':</th><td>' . esc_html( $latest_log['trans_code'] ) . '</td></tr>';
			if ( 'sonypayment' === $payment_method && isset( $response['SecureResultCode'] ) ) {
				$latest .= '<tr><th>' . esc_html__( '3D Secure result code', 'woo-sonypayment' ) . ':</th><td>' . esc_html( $response['SecureResultCode'] ) . '</td></tr>';
			}
			if ( 'sonypayment' === $payment_method && isset( $response['Agreement'] ) ) {
				$latest .= '<tr><th>' . esc_html__( 'Handling of personal information', 'woo-sonypayment' ) . ':</th><td>' . esc_html__( 'Agreed', 'woo-sonypayment' ) . '</td></tr>';
			}
			if ( 'sonypayment' === $payment_method && isset( $response['PayType'] ) && '01' !== $response['PayType'] ) {
				$latest .= '<tr><th>' . esc_html__( 'The number of installments', 'woo-sonypayment' ) . ':</th><td>' . esc_html( spfwc_get_paytype( $response['PayType'] ) ) . '</td></tr>';
			} elseif ( 'sonypayment_cvs' === $payment_method && ! isset( $response['NyukinDate'] ) && isset( $response['PayLimit'] ) ) {
				$latest .= '<tr><th>' . esc_html__( 'Payment deadline', 'woo-sonypayment' ) . ':</th><td>' . esc_html( spfwc_get_formatted_date( substr( $response['PayLimit'], 0, 8 ), false ) ) . '</td></tr>';
			}
			// $latest .= '<tr><th>'.esc_html__( 'Status', 'woo-sonypayment' ).':</th><td>'.esc_html( $response['ResponseCd'] ).'</td></tr>
			// </table>';
			$latest .= '</table>';
		}
		return $latest;
	}

	/**
	 * Settlement actions history.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @return string
	 */
	private function settlement_history( $order_id, $trans_code ) {

		$history  = '';
		$log_data = SPFWC_Payment_Logger::get_log( $order_id, $trans_code );
		if ( $log_data ) {
			$num     = count( $log_data );
			$history = '<table class="spfwc-settlement-history">
				<thead class="spfwc-settlement-history-head">
					<tr><th></th><th>' . esc_html__( 'Processing date', 'woo-sonypayment' ) . '</th><th>' . esc_html__( 'Sequence number', 'woo-sonypayment' ) . '</th><th>' . esc_html__( 'Processing classification', 'woo-sonypayment' ) . '</th><th>' . esc_html__( 'Result', 'woo-sonypayment' ) . '</th></tr>
				</thead>
				<tbody class="spfwc-settlement-history-body">';
			foreach ( (array) $log_data as $data ) {
				$response       = json_decode( $data['response'], true );
				$class          = ( 'OK' !== $response['ResponseCd'] ) ? ' error' : '';
				$operation_name = ( isset( $response['OperateId'] ) ) ? spfwc_get_operation_name( $response['OperateId'] ) : '';
				$history       .= '<tr>
					<td class="num">' . esc_html( $num ) . '</td>
					<td class="datetime">' . esc_html( $data['timestamp'] ) . '</td>
					<td class="transactionid">' . esc_html( $response['TransactionId'] ) . '</td>
					<td class="operateid">' . esc_html( $operation_name ) . '</td>
					<td class="responsecd' . esc_attr( $class ) . '">' . esc_html( $response['ResponseCd'] ) . '</td>
				</tr>';
				$num--;
			}
			$history .= '</tbody>
				</table>';
		}
		return $history;
	}

	/**
	 * Check already deposited.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @return bool
	 */
	private function check_paid( $order_id, $trans_code ) {

		$paid     = false;
		$log_data = SPFWC_Payment_Logger::get_log( $order_id, $trans_code );
		if ( $log_data ) {
			foreach ( (array) $log_data as $data ) {
				$response = json_decode( $data['response'], true );
				if ( isset( $response['OperateId'] ) && 'paid' === $response['OperateId'] ) {
					$paid = true;
					break;
				}
			}
		}
		return $paid;
	}

	/**
	 * Validity check of payment deadline.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @return bool
	 */
	private function check_paylimit( $order_id, $trans_code ) {

		$paid = $this->check_paid( $order_id, $trans_code );
		if ( $paid ) {
			return false;
		}
		$expired  = false;
		$today    = date_i18n( 'YmdHi', current_time( 'timestamp' ) );
		$order    = wc_get_order( $order_id );
		$paylimit = $order->get_meta( '_spfwc_cvs_paylimit', true );
		if ( $today > $paylimit ) {
			$expired = true;
		}
		return $expired;
	}

	/**
	 * Ajax handler that performs settlement actions.
	 */
	public function ajax_handler() {
		check_ajax_referer( 'spfwc-settlement_actions', 'security' );

		if ( ! current_user_can( 'editor' ) && ! current_user_can( 'administrator' ) && ! current_user_can( 'shop_manager' ) && ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$mode = sanitize_title( $_POST['mode'] );
		$data = array();

		switch ( $mode ) {
			// Get latest information.
			case 'get_latest_info':
				$order_id       = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num      = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code     = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$payment_method = ( isset( $_POST['payment_method'] ) ) ? wp_unslash( $_POST['payment_method'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$init_code = str_repeat( '9', strlen( $trans_code ) );
				if ( $trans_code === $init_code ) {
					$order              = wc_get_order( $order_id );
					$trans_code         = $order->get_meta( $order_id, '_spfwc_trans_code', true );
					$data['trans_code'] = $trans_code;
				}
				$latest_info = $this->settlement_latest_info( $order_id, $trans_code, $payment_method );
				if ( $latest_info ) {
					$data['status'] = 'OK';
					$data['latest'] = $latest_info;
				}
				break;

			// Card - Transaction reference.
			case 'get_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res       = '';
				$init_code = str_repeat( '9', strlen( $trans_code ) );
				if ( $trans_code === $init_code ) {
					$customer_id = ( isset( $_POST['customer_id'] ) ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
					$member      = new SPFWC_Card_Member( $customer_id );
					if ( 0 < $customer_id && $member->is_card_member() ) {
						$response_member = $member->search_card_member();
						if ( 'OK' === $response_member['ResponseCd'] ) {
							$order  = wc_get_order( $order_id );
							$amount = $order->get_total();
							$res   .= '<span class="spfwc-settlement-admin spfwc-card-new">' . esc_html__( 'New', 'woo-sonypayment' ) . '</span>';
							$res   .= '<table class="spfwc-settlement-admin-table">';
							$res   .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
							<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
							</tr>';
							$res   .= '</table>';
							$res   .= '<div class="spfwc-settlement-admin-button">';
							$res   .= '<input type="button" id="spfwc-auth-button" class="button" value="' . esc_attr__( 'Credit', 'woo-sonypayment' ) . '" />';
							$res   .= '<input type="button" id="spfwc-gathering-button" class="button" value="' . esc_attr__( 'Credit sales recorded', 'woo-sonypayment' ) . '" />';
							$res   .= '</div>';
						} else {
							$res .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
							$res .= '<div class="spfwc-settlement-admin-error">';
							$res .= '<div><span class="message">' . esc_html__( 'Not registered of card member information', 'woo-sonypayment' ) . '</span></div>';
							$res .= '</div>';
						}
					} else {
						$res .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
						$res .= '<div class="spfwc-settlement-admin-error">';
						$res .= '<div><span class="message">' . esc_html__( 'Not registered of card member information', 'woo-sonypayment' ) . '</span></div>';
						$res .= '</div>';
					}
					$data['status'] = 'OK';
					$data['result'] = $res;
				} else {
					$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
					$latest_response               = json_decode( $latest_log['response'], true );
					$operateid                     = ( isset( $latest_response['OperateId'] ) ) ? $latest_response['OperateId'] : SPFWC_Payment_Logger::get_first_operation( $order_id, $trans_code );
					$settings                      = get_option( 'woocommerce_sonypayment_settings', array() );
					$transaction_date              = spfwc_get_transaction_date();
					$sln                           = new SPFWC_SLN_Connection();
					$params                        = array();
					$param_list                    = array();
					$param_list['MerchantId']      = $settings['merchant_id'];
					$param_list['MerchantPass']    = $settings['merchant_pass'];
					$param_list['TenantId']        = $settings['tenant_id'];
					$param_list['TransactionDate'] = $transaction_date;
					$param_list['MerchantFree1']   = $trans_code;
					$param_list['MerchantFree2']   = $order_id;
					$params['send_url']            = $sln->send_url();
					$params['param_list']          = array_merge(
						$param_list,
						array(
							'OperateId'   => '1Search',
							'ProcessId'   => $latest_response['ProcessId'],
							'ProcessPass' => $latest_response['ProcessPass'],
						)
					);
					$response_data                 = $sln->connection( $params );
					if ( 'OK' === $response_data['ResponseCd'] ) {
						$class          = ' card-' . mb_strtolower( substr( $latest_response['OperateId'], 1 ) );
						$operation_name = spfwc_get_operation_name( $latest_response['OperateId'] );
						$res           .= '<span class="spfwc-settlement-admin' . esc_attr( $class ) . '">' . esc_html( $operation_name ) . '</span>';
						$res           .= '<table class="spfwc-settlement-admin-table">';
						if ( isset( $response_data['Amount'] ) ) {
							$res .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
							<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $response_data['Amount'] ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $response_data['Amount'] ) . '" /></td>
							</tr>';
						}
						if ( isset( $response_data['SalesDate'] ) ) {
							$res .= '<tr><th>' . esc_html__( 'Recorded date of sales', 'woo-sonypayment' ) . '</th><td>' . esc_html( $response_data['SalesDate'] ) . '</td></tr>';
						}
						$res .= '</table>';
						$res .= '<div class="spfwc-settlement-admin-button">';
						if ( '1Delete' === $latest_response['OperateId'] ) {
							$res .= '<input type="button" id="spfwc-reauth-button" class="button" value="' . esc_attr__( 'Re-authorization', 'woo-sonypayment' ) . '" />';
						} else {
							if ( '1Auth' === $operateid && '1Capture' !== $latest_response['OperateId'] && '1Gathering' !== $latest_response['OperateId'] ) {
								$res .= '<input type="button" id="spfwc-reauth-button" class="button" value="' . esc_attr__( 'Re-authorization', 'woo-sonypayment' ) . '" />';
								$res .= '<input type="button" id="spfwc-capture-button" class="button" value="' . esc_attr__( 'Sales recorded', 'woo-sonypayment' ) . '" />';
							}
							if ( '1Delete' !== $latest_log['OperateId'] ) {
								$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
							}
							if ( '1Change' !== $latest_log['OperateId'] ) {
								$res .= '<input type="button" id="spfwc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'woo-sonypayment' ) . '" />';
							}
						}
						$res .= '</div>';
					} else {
						if ( 'K12' === $response_data['ResponseCd'] ) {
							$res .= '<span class="spfwc-settlement-admin card-delete">' . esc_html__( 'Expired', 'woo-sonypayment' ) . '</span>';
							$res .= '<div class="spfwc-settlement-admin-expired">';
							$res .= '<div><span class="code">K12</span> : <span class="message">' . esc_html__( 'Expired the due date of handling.', 'woo-sonypayment' ) . '</span></div>';
							$res .= '</div>';
						} else {
							$res       .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
							$res       .= '<div class="spfwc-settlement-admin-error">';
							$responsecd = explode( '|', $response_data['ResponseCd'] );
							foreach ( $responsecd as $cd ) {
								$message              = SPFWC_Payment_Message::response_message( $cd );
								$response_data[ $cd ] = $message;
								$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
							}
							$res .= '</div>';
							SPFWC_Logger::add_log( '[1Search] Error: ' . print_r( $response_data, true ) );
						}
					}
					$res           .= $this->settlement_history( $order_id, $trans_code );
					$data['status'] = $response_data['ResponseCd'];
					$data['result'] = $res;
				}
				break;

			// Card - Sales recorded.
			case 'capture_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res                           = '';
				$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response               = json_decode( $latest_log['response'], true );
				$settings                      = get_option( 'woocommerce_sonypayment_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TenantId']        = $settings['tenant_id'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				$customer_id                   = ( isset( $_POST['customer_id'] ) ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
				$member                        = new SPFWC_Card_Member( $customer_id );
				if ( 0 < $customer_id && $member->is_card_member() ) {
					$response_member = $member->search_card_member( $param_list );
					if ( 'OK' === $response_member['ResponseCd'] ) {
						$param_list['KaiinId']   = $member->get_member_id();
						$param_list['KaiinPass'] = $member->get_member_pass();
					}
				}
				$params['send_url']   = $sln->send_url();
				$params['param_list'] = array_merge(
					$param_list,
					array(
						'OperateId'   => '1Capture',
						'ProcessId'   => $latest_response['ProcessId'],
						'ProcessPass' => $latest_response['ProcessPass'],
						'SalesDate'   => $transaction_date,
					)
				);
				$response_data        = $sln->connection( $params );
				if ( 'K81' === $response_data['ResponseCd'] ) {
					$params['param_list']['KaiinId']   = '';
					$params['param_list']['KaiinPass'] = '';
					$response_data                     = $sln->connection( $params );
				}
				if ( 'OK' === $response_data['ResponseCd'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( 'Sales recorded', 'woo-sonypayment' ) );
						$order->add_order_note( $message );
					}

					$class          = ' card-' . mb_strtolower( substr( $response_data['OperateId'], 1 ) );
					$operation_name = spfwc_get_operation_name( $response_data['OperateId'] );
					$res           .= '<span class="spfwc-settlement-admin' . esc_attr( $class ) . '">' . esc_html( $operation_name ) . '</span>';
					$res           .= '<table class="spfwc-settlement-admin-table">';
					$res           .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					if ( isset( $response_data['SalesDate'] ) ) {
						$res .= '<tr><th>' . esc_html__( 'Recorded date of sales', 'woo-sonypayment' ) . '</th><td>' . esc_html( $response_data['SalesDate'] ) . '</td></tr>';
					}
					$res .= '</table>';
					$res .= '<div class="spfwc-settlement-admin-button">';
					$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
					$res .= '<input type="button" id="spfwc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'woo-sonypayment' ) . '" />';
					$res .= '</div>';
				} else {
					$res       .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res       .= '<div class="spfwc-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$message              = SPFWC_Payment_Message::response_message( $cd );
						$response_data[ $cd ] = $message;
						$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
					}
					$res .= '</div>';
					SPFWC_Logger::add_log( '[1Capture] Error: ' . print_r( $response_data, true ) );
				}
				do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				$res           .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['ResponseCd'];
				$data['result'] = $res;
				break;

			// Card - Cancel / Return.
			case 'delete_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res                           = '';
				$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response               = json_decode( $latest_log['response'], true );
				$settings                      = get_option( 'woocommerce_sonypayment_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TenantId']        = $settings['tenant_id'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				$customer_id                   = ( isset( $_POST['customer_id'] ) ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
				$member                        = new SPFWC_Card_Member( $customer_id );
				if ( 0 < $customer_id && $member->is_card_member() ) {
					$response_member = $member->search_card_member( $param_list );
					if ( 'OK' === $response_member['ResponseCd'] ) {
						$param_list['KaiinId']   = $member->get_member_id();
						$param_list['KaiinPass'] = $member->get_member_pass();
					}
				}
				$params['send_url']   = $sln->send_url();
				$params['param_list'] = array_merge(
					$param_list,
					array(
						'OperateId'   => '1Delete',
						'ProcessId'   => $latest_response['ProcessId'],
						'ProcessPass' => $latest_response['ProcessPass'],
					)
				);
				$response_data        = $sln->connection( $params );
				if ( 'K81' === $response_data['ResponseCd'] ) {
					$params['param_list']['KaiinId']   = '';
					$params['param_list']['KaiinPass'] = '';
					$response_data                     = $sln->connection( $params );
				}
				if ( 'OK' === $response_data['ResponseCd'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( 'Cancel', 'woo-sonypayment' ) );
						$order->add_order_note( $message );
					}

					$class          = ' card-' . mb_strtolower( substr( $response_data['OperateId'], 1 ) );
					$operation_name = spfwc_get_operation_name( $response_data['OperateId'] );
					$res           .= '<span class="spfwc-settlement-admin' . esc_attr( $class ) . '">' . esc_html( $operation_name ) . '</span>';
					$res           .= '<table class="spfwc-settlement-admin-table">';
					$res           .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					$res           .= '</table>';
					$res           .= '<div class="spfwc-settlement-admin-button">';
					$res           .= '<input type="button" id="spfwc-reauth-button" class="button" value="' . esc_attr__( 'Re-authorization', 'woo-sonypayment' ) . '" />';
					$res           .= '</div>';
				} else {
					$res       .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res       .= '<div class="spfwc-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$message              = SPFWC_Payment_Message::response_message( $cd );
						$response_data[ $cd ] = $message;
						$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
					}
					$res .= '</div>';
					SPFWC_Logger::add_log( '[1Delete] Error: ' . print_r( $response_data, true ) );
				}
				do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				$res           .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['ResponseCd'];
				$data['result'] = $res;
				break;

			// Card - Amount change.
			case 'change_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) || '' === $amount ) {
					$data['status'] = 'NG';
					break;
				}
				$res                           = '';
				$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response               = json_decode( $latest_log['response'], true );
				$operateid                     = ( isset( $latest_response['OperateId'] ) ) ? $latest_response['OperateId'] : SPFWC_Payment_Logger::get_first_operation( $order_id, $trans_code );
				$settings                      = get_option( 'woocommerce_sonypayment_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TenantId']        = $settings['tenant_id'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				$customer_id                   = ( isset( $_POST['customer_id'] ) ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
				$member                        = new SPFWC_Card_Member( $customer_id );
				if ( 0 < $customer_id && $member->is_card_member() ) {
					$response_member = $member->search_card_member( $param_list );
					if ( 'OK' === $response_member['ResponseCd'] ) {
						$param_list['KaiinId']   = $member->get_member_id();
						$param_list['KaiinPass'] = $member->get_member_pass();
					}
				}
				$params['send_url']   = $sln->send_url();
				$params['param_list'] = array_merge(
					$param_list,
					array(
						'OperateId'   => '1Change',
						'ProcessId'   => $latest_response['ProcessId'],
						'ProcessPass' => $latest_response['ProcessPass'],
						'Amount'      => $amount,
					)
				);
				$response_data        = $sln->connection( $params );
				if ( 'K81' === $response_data['ResponseCd'] ) {
					$params['param_list']['KaiinId']   = '';
					$params['param_list']['KaiinPass'] = '';
					$response_data                     = $sln->connection( $params );
				}
				if ( 'OK' === $response_data['ResponseCd'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( 'Amount change', 'woo-sonypayment' ) );
						$order->add_order_note( $message );
					}

					$class          = ' card-' . mb_strtolower( substr( $operateid, 1 ) );
					$operation_name = spfwc_get_operation_name( $response_data['OperateId'] );
					$res           .= '<span class="spfwc-settlement-admin' . esc_attr( $class ) . '">' . esc_html( $operation_name ) . '</span>';
					$res           .= '<table class="spfwc-settlement-admin-table">';
					$res           .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					if ( isset( $response_data['SalesDate'] ) ) {
						$res .= '<tr><th>' . esc_html__( 'Recorded date of sales', 'woo-sonypayment' ) . '</th><td>' . esc_html( $response_data['SalesDate'] ) . '</td></tr>';
					}
					$res .= '</table>';
					$res .= '<div class="spfwc-settlement-admin-button">';
					if ( '1Capture' !== $operateid && '1Gathering' !== $operateid ) {
						$res .= '<input type="button" id="spfwc-capture-button" class="button" value="' . esc_attr__( 'Sales recorded', 'woo-sonypayment' ) . '" />';
					}
					$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
					$res .= '<input type="button" id="spfwc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'woo-sonypayment' ) . '" />';
					$res .= '</div>';
				} else {
					$res       .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res       .= '<div class="spfwc-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$message              = SPFWC_Payment_Message::response_message( $cd );
						$response_data[ $cd ] = $message;
						$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
					}
					$res .= '</div>';
					SPFWC_Logger::add_log( '[1Change] Error: ' . print_r( $response_data, true ) );
				}
				do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				$res           .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['ResponseCd'];
				$data['result'] = $res;
				break;

			// Card - Credit.
			case 'auth_card':
			// Card - Credit sales recorded.
			case 'gathering_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res       = '';
				$init_code = str_repeat( '9', strlen( $trans_code ) );
				if ( $trans_code === $init_code ) {
					$trans_code = spfwc_get_transaction_code();
				} else {
					$latest_log      = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
					$latest_response = json_decode( $latest_log['response'], true );
				}
				$operateid                     = ( 'auth_card' === $mode ) ? '1Auth' : '1Gathering';
				$settings                      = get_option( 'woocommerce_sonypayment_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TenantId']        = $settings['tenant_id'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				// Search of card member.
				$customer_id = ( isset( $_POST['customer_id'] ) ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
				$member      = new SPFWC_Card_Member( $customer_id );
				if ( 0 < $customer_id && $member->is_card_member() ) {
					$response_member = $member->search_card_member( $param_list );
					if ( 'OK' === $response_member['ResponseCd'] ) {
						$params['send_url']   = $sln->send_url();
						$params['param_list'] = array_merge(
							$param_list,
							array(
								'KaiinId'   => $member->get_member_id(),
								'KaiinPass' => $member->get_member_pass(),
								'OperateId' => $operateid,
								'PayType'   => '01',
								'Amount'    => $amount,
							)
						);
						$response_data        = $sln->connection( $params );
						if ( 'OK' === $response_data['ResponseCd'] ) {
							$order = wc_get_order( $order_id );
							$order->update_meta_data( '_spfwc_trans_code', $trans_code );
							$order->save();
							if ( is_object( $order ) ) {
								$order->payment_complete( $trans_code );
								$operate = ( 'auth_card' === $mode ) ? 'Credit' : 'Credit sales recorded';
								$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( $operate, 'woo-sonypayment' ) );
								$order->add_order_note( $message );
							}

							$class          = ' card-' . mb_strtolower( substr( $operateid, 1 ) );
							$operation_name = spfwc_get_operation_name( $response_data['OperateId'] );
							$res           .= '<span class="spfwc-settlement-admin' . esc_attr( $class ) . '">' . esc_html( $operation_name ) . '</span>';
							$res           .= '<table class="spfwc-settlement-admin-table">';
							$res           .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
							<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
							</tr>';
							$res           .= '</table>';
							$res           .= '<div class="spfwc-settlement-admin-button">';
							if ( '1Capture' !== $operateid && '1Gathering' !== $operateid ) {
								$res .= '<input type="button" id="spfwc-capture-button" class="button" value="' . esc_attr__( 'Sales recorded', 'woo-sonypayment' ) . '" />';
							}
							$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
							$res .= '<input type="button" id="spfwc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'woo-sonypayment' ) . '" />';
							$res .= '</div>';
						} else {
							$res       .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
							$res       .= '<div class="spfwc-settlement-admin-error">';
							$responsecd = explode( '|', $response_data['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$message              = SPFWC_Payment_Message::response_message( $cd );
								$response_data[ $cd ] = $message;
								$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
							}
							$res .= '</div>';
							SPFWC_Logger::add_log( '[' . $operateid . '] Error: ' . print_r( $response_data, true ) );
						}
						do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
						SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
						$res               .= $this->settlement_history( $order_id, $trans_code );
						$data['status']     = $response_data['ResponseCd'];
						$data['trans_code'] = $trans_code;
						$data['result']     = $res;
					} else {
						$res       .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
						$res       .= '<div class="spfwc-settlement-admin-error">';
						$responsecd = explode( '|', $response_member['ResponseCd'] );
						foreach ( (array) $responsecd as $cd ) {
							$message              = SPFWC_Payment_Message::response_message( $cd );
							$response_data[ $cd ] = $message;
							$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
						}
						$res .= '</div>';
						SPFWC_Logger::add_log( '[4MemRefM] Error: ' . print_r( $response_member, true ) );
						$data['status'] = $response_member['ResponseCd'];
						$data['result'] = $res;
					}
				} else {
					$res           .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res           .= '<div class="spfwc-settlement-admin-error">';
					$res           .= '<div><span class="message">' . esc_html__( 'Not registered of card member information', 'woo-sonypayment' ) . '</span></div>';
					$res           .= '</div>';
					$data['status'] = 'NG';
					$data['result'] = $res;
				}
				break;

			// Card - Re-authorization.
			case 'reauth_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res                           = '';
				$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response               = json_decode( $latest_log['response'], true );
				$operateid                     = ( isset( $latest_response['OperateId'] ) ) ? $latest_response['OperateId'] : SPFWC_Payment_Logger::get_first_operation( $order_id, $trans_code );
				$settings                      = get_option( 'woocommerce_sonypayment_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TenantId']        = $settings['tenant_id'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				$customer_id                   = ( isset( $_POST['customer_id'] ) ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
				$member                        = new SPFWC_Card_Member( $customer_id );
				if ( 0 < $customer_id && $member->is_card_member() ) {
					$response_member = $member->search_card_member( $param_list );
					if ( 'OK' === $response_member['ResponseCd'] ) {
						$param_list['MerchantFree3'] = $customer_id;
						$param_list['KaiinId']       = $member->get_member_id();
						$param_list['KaiinPass']     = $member->get_member_pass();
					}
				}
				// if ( '1Gathering' === $operateid ) {
					$param_list['SalesDate'] = $transaction_date;
				// }
				$params['send_url']   = $sln->send_url();
				$params['param_list'] = array_merge(
					$param_list,
					array(
						'OperateId'   => '1ReAuth',
						'ProcessId'   => $latest_response['ProcessId'],
						'ProcessPass' => $latest_response['ProcessPass'],
						'Amount'      => $amount,
					)
				);
				$response_data        = $sln->connection( $params );
				if ( 'K81' === $response_data['ResponseCd'] ) {
					$params['param_list']['MerchantFree3'] = '';
					$params['param_list']['KaiinId']       = '';
					$params['param_list']['KaiinPass']     = '';
					$response_data                         = $sln->connection( $params );
				}
				if ( 'OK' === $response_data['ResponseCd'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( 'Re-authorization', 'woo-sonypayment' ) );
						$order->add_order_note( $message );
					}

					$class          = ' card-' . mb_strtolower( substr( $operateid, 1 ) );
					$operation_name = spfwc_get_operation_name( $response_data['OperateId'] );
					$res           .= '<span class="spfwc-settlement-admin' . esc_attr( $class ) . '">' . esc_html( $operation_name ) . '</span>';
					$res           .= '<table class="spfwc-settlement-admin-table">';
					$res           .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					if ( isset( $response_data['SalesDate'] ) ) {
						$res .= '<tr><th>' . esc_html__( 'Recorded date of sales', 'woo-sonypayment' ) . '</th><td>' . esc_html( $response_data['SalesDate'] ) . '</td></tr>';
					}
					$res .= '</table>';
					$res .= '<div class="spfwc-settlement-admin-button">';
					if ( '1Capture' !== $operateid && '1Gathering' !== $operateid ) {
						$res .= '<input type="button" id="spfwc-capture-button" class="button" value="' . esc_attr__( 'Sales recorded', 'woo-sonypayment' ) . '" />';
					}
					$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
					$res .= '<input type="button" id="spfwc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'woo-sonypayment' ) . '" />';
					$res .= '</div>';
				} else {
					$res       .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res       .= '<div class="spfwc-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$message              = SPFWC_Payment_Message::response_message( $cd );
						$response_data[ $cd ] = $message;
						$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
					}
					$res .= '</div>';
					SPFWC_Logger::add_log( '[1ReAuth] Error: ' . print_r( $response_data, true ) );
				}
				do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				$res           .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['ResponseCd'];
				$data['result'] = $res;
				break;

			// Card - Error.
			case 'error_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? absint( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$customer_id = ( isset( $_POST['customer_id'] ) ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
				$member      = new SPFWC_Card_Member( $customer_id );
				if ( 0 < $customer_id && $member->is_card_member() ) {
					$response_member = $member->search_card_member();
					if ( 'OK' === $response_member['ResponseCd'] ) {
						$order  = wc_get_order( $order_id );
						$amount = $order->get_total();
						$res   .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Repayment', 'woo-sonypayment' ) . '</span>';
						$res   .= '<table class="spfwc-settlement-admin-table">';
						$res   .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
						<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
						</tr>';
						$res   .= '</table>';
						$res   .= '<div class="spfwc-settlement-admin-button">';
						$res   .= '<input type="button" id="spfwc-auth-button" class="button" value="' . esc_attr__( 'Credit', 'woo-sonypayment' ) . '" />';
						$res   .= '<input type="button" id="spfwc-gathering-button" class="button" value="' . esc_attr__( 'Credit sales recorded', 'woo-sonypayment' ) . '" />';
						$res   .= '</div>';
						$res   .= $this->settlement_history( $order_id, $trans_code );
					} else {
						$res .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Payment error', 'woo-sonypayment' ) . '</span>';
						$res .= '<div class="spfwc-settlement-admin-error">';
						$res .= '<div><span class="message">' . esc_html__( 'Not registered of card member information', 'woo-sonypayment' ) . '</span></div>';
						$res .= '</div>';
					}
					$data['status'] = $response_member['ResponseCd'];
					$data['result'] = $res;
				} else {
					$res           .= '<span class="spfwc-settlement-admin card-error">' . esc_html__( 'Payment error', 'woo-sonypayment' ) . '</span>';
					$res           .= '<div class="spfwc-settlement-admin-error">';
					$res           .= '<div><span class="message">' . esc_html__( 'Not registered of card member information', 'woo-sonypayment' ) . '</span></div>';
					$res           .= '</div>';
					$data['status'] = 'NG';
					$data['result'] = $res;
				}
				break;

			// Card - Subscription update.
			case 'subscription_update':
				break;

			// CVS - Transaction reference.
			case 'get_cvs':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				if ( empty( $order_id ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res       = '';
				$init_code = str_repeat( '9', strlen( $trans_code ) );
				if ( $trans_code === $init_code ) {
					$order          = wc_get_order( $order_id );
					$amount         = $order->get_total();
					$settings       = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
					$paylimit       = date_i18n( 'Ymd', current_time( 'timestamp' ) + ( 86400 * $settings['paylimit'] ) );
					$res           .= '<span class="spfwc-settlement-admin cvs-add">' . esc_html__( 'New', 'woo-sonypayment' ) . '</span>';
					$res           .= '<table class="spfwc-settlement-admin-table">';
					$res           .= '<tr><th>' . esc_html__( 'Payment deadline', 'woo-sonypayment' ) . '</th>
					<td><input type="text" id="spfwc-paylimit_change" value="' . esc_attr( $paylimit ) . '" style="ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-paylimit" value="' . esc_attr( $paylimit ) . '" /></td>
					</tr>';
					$res           .= '<tr><th>' . sprintf( __( 'Payment amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					$res           .= '</table>';
					$res           .= '<div class="spfwc-settlement-admin-button">';
					$res           .= '<input type="button" id="spfwc-add-button" class="button" value="' . esc_attr__( 'Register', 'woo-sonypayment' ) . '" />';
					$res           .= '</div>';
					$data['status'] = 'OK';
					$data['result'] = $res;
				} else {
					$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
					$latest_response               = json_decode( $latest_log['response'], true );
					$settings                      = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
					$transaction_date              = spfwc_get_transaction_date();
					$sln                           = new SPFWC_SLN_Connection();
					$params                        = array();
					$param_list                    = array();
					$param_list['MerchantId']      = $settings['merchant_id'];
					$param_list['MerchantPass']    = $settings['merchant_pass'];
					$param_list['TransactionDate'] = $transaction_date;
					$param_list['MerchantFree1']   = $trans_code;
					$param_list['MerchantFree2']   = $order_id;
					$params['send_url']            = $sln->send_url_cvs();
					$params['param_list']          = array_merge(
						$param_list,
						array(
							'OperateId'   => '2Ref',
							'ProcessId'   => $latest_response['ProcessId'],
							'ProcessPass' => $latest_response['ProcessPass'],
						)
					);
					$response_data                 = $sln->connection( $params );
					if ( 'OK' === $response_data['ResponseCd'] ) {
						if ( isset( $response_data['NyukinDate'] ) ) {
							$order = wc_get_order( $order_id );
							if ( is_object( $order ) && ! $this->check_paid( $order_id, $trans_code ) ) {
								if ( isset( $response_data['CvsCd'] ) ) {
									$cvs_name = spfwc_get_cvs_name( $response_data['CvsCd'] );
									$message  = sprintf( __( 'Payment completed in %s.', 'woo-sonypayment' ), $cvs_name );
								} else {
									$message = __( 'Payment is completed.', 'woo-sonypayment' );
								}
								$order->update_status( 'processing', $message );
								$response_data['OperateId'] = 'paid';
								SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
							}

							$res .= '<span class="spfwc-settlement-admin cvs-paid">' . esc_html__( 'Paid', 'woo-sonypayment' ) . '</span>';
							$res .= '<table class="spfwc-settlement-admin-table">';
							if ( isset( $response_data['RecvNum'] ) ) {
								$res .= '<tr><th>' . esc_html__( 'Receipt number', 'woo-sonypayment' ) . '</th><td>' . esc_html( $response_data['RecvNum'] ) . '</td></tr>';
							}
							// if ( isset( $response_data['NyukinDate'] ) ) {
							$res .= '<tr><th>' . esc_html__( 'Deposit date and time', 'woo-sonypayment' ) . '</th><td>' . esc_html( spfwc_get_formatted_date( $response_data['NyukinDate'] ) ) . '</td></tr>';
							// }
							if ( isset( $response_data['CvsCd'] ) ) {
								$cvs_name = spfwc_get_cvs_name( $response_data['CvsCd'] );
								$res     .= '<tr><th>' . esc_html__( 'Convenience store name', 'woo-sonypayment' ) . '</th><td>' . esc_html( $cvs_name ) . '</td></tr>';
							}
							if ( isset( $response_data['Amount'] ) ) {
								$res .= '<tr><th>' . sprintf( __( 'Payment amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th><td>' . esc_html( $response_data['Amount'] ) . '</td></tr>';
							}
							$res .= '</table>';
							// $res .= '<div class="spfwc-settlement-admin-button">';
							// $res .= '<input type="button" id="spfwc-add-button" class="button" value="'.esc_attr__( 'Register', 'woo-sonypayment' ).'" />';
							// $res .= '</div>';
						} else {
							$paylimit = substr( $latest_response['PayLimit'], 0, 8 );
							$expired  = $this->check_paylimit( $order_id, $trans_code );
							$res     .= '<span class="spfwc-settlement-admin cvs-unpaid">' . esc_html__( 'Unpaid', 'woo-sonypayment' );
							if ( $expired ) {
								$res .= esc_html__( '(Expired)', 'woo-sonypayment' );
							}
							$res .= '</span>';
							$res .= '<table class="spfwc-settlement-admin-table">';
							$res .= '<tr><th>' . esc_html__( 'Payment deadline', 'woo-sonypayment' ) . '</th>
							<td><input type="text" id="spfwc-paylimit_change" value="' . esc_attr( $paylimit ) . '" style="ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-paylimit" value="' . esc_attr( $paylimit ) . '" /></td>
							</tr>';
							$res .= '<tr><th>' . sprintf( __( 'Payment amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
							<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $latest_response['Amount'] ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $latest_response['Amount'] ) . '" /></td>
							</tr>';
							$res .= '</table>';
							if ( isset( $latest_response['OperateId'] ) ) {
								$res .= '<div class="spfwc-settlement-admin-button">';
								if ( '2Del' !== $latest_response['OperateId'] ) {
									$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
								}
								if ( '2Chg' !== $latest_response['OperateId'] ) {
									$res .= '<input type="button" id="spfwc-change-button" class="button" value="' . esc_attr__( 'Change', 'woo-sonypayment' ) . '" />';
								}
								$res .= '</div>';
							}
						}
					} else {
						if ( isset( $latest_response['OperateId'] ) && '2Del' === $latest_response['OperateId'] && 'K12' === $response_data['ResponseCd'] ) {
							$res .= '<span class="spfwc-settlement-admin cvs-del">' . esc_html__( 'Canceled', 'woo-sonypayment' ) . '</span>';
							$res .= '<table class="spfwc-settlement-admin-table">';
							if ( isset( $latest_response['PayLimit'] ) ) {
								$res .= '<tr><th>' . esc_html__( 'Payment deadline', 'woo-sonypayment' ) . '</th><td>' . esc_html( spfwc_get_formatted_date( substr( $latest_response['PayLimit'], 0, 8 ), false ) ) . '</td></tr>';
							}
							if ( isset( $latest_response['Amount'] ) ) {
								$res .= '<tr><th>' . sprintf( __( 'Payment amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th><td>' . esc_html( $latest_response['Amount'] ) . '</td></tr>';
							}
							$res .= '</table>';
						} else {
							$res       .= '<span class="spfwc-settlement-admin cvs-error">' . __( 'Error', 'woo-sonypayment' ) . '</span>';
							$res       .= '<div class="spfwc-settlement-admin-error">';
							$responsecd = explode( '|', $response_data['ResponseCd'] );
							foreach ( (array) $responsecd as $cd ) {
								$message              = SPFWC_Payment_Message::response_message( $cd );
								$response_data[ $cd ] = $message;
								$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
							}
							$res .= '</div>';
							SPFWC_Logger::add_log( '[2Ref] Error: ' . print_r( $response_data, true ) );
						}
					}
					$res           .= $this->settlement_history( $order_id, $trans_code );
					$data['status'] = $response_data['ResponseCd'];
					$data['result'] = $res;
				}
				break;

			// CVS - Register.
			case 'add_cvs':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$paylimit   = ( isset( $_POST['paylimit'] ) ) ? wp_unslash( $_POST['paylimit'] ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : '';
				if ( empty( $order_id ) || empty( $trans_code ) || '' === $paylimit || '' === $amount ) {
					$data['status'] = 'NG';
					break;
				}
				$res       = '';
				$init_code = str_repeat( '9', strlen( $trans_code ) );
				if ( $trans_code === $init_code ) {
					$trans_code = spfwc_get_transaction_code();
				} else {
					$latest_log      = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
					$latest_response = json_decode( $latest_log['response'], true );
				}
				$order        = wc_get_order( $order_id );
				$billing_name = $order->get_billing_last_name() . $order->get_billing_first_name();
				if ( get_option( 'wc4jp-yomigana' ) ) {
					$last_name_kana  = $order->get_meta( '_billing_yomigana_last_name', true );
					$first_name_kana = $order->get_meta( '_billing_yomigana_first_name', true );
				} else {
					$last_name_kana  = $order->get_meta( '_billing_last_name_kana', true );
					$first_name_kana = $order->get_meta( '_billing_first_name_kana', true );
				}
				$billing_name_kana = mb_convert_kana( $last_name_kana, 'KVC' ) . mb_convert_kana( $first_name_kana, 'KVC' );
				$item_name         = '';
				$order_items       = $order->get_items();
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
				$settings                      = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				$params['send_url']            = $sln->send_url_cvs();
				$params['param_list']          = array_merge(
					$param_list,
					array(
						'OperateId'   => '2Add',
						'PayLimit'    => $paylimit . '2359',
						'Amount'      => $amount,
						'NameKanji'   => $billing_name,
						'NameKana'    => $billing_name_kana,
						'TelNo'       => $order->get_billing_phone(),
						'ShouhinName' => $item_name,
					)
				);
				$response_data                 = $sln->connection( $params );
				if ( 'OK' === $response_data['ResponseCd'] ) {
					$order->update_meta_data( '_spfwc_trans_code', $trans_code );
					$response_data['PayLimit'] = $params['param_list']['PayLimit'];
					$response_data['Amount']   = $params['param_list']['Amount'];
					$FreeArea                  = trim( $response_data['FreeArea'] );
					$url                       = add_query_arg(
						array(
							'code' => $FreeArea,
							'rkbn' => 2,
						),
						$sln->redirect_url_cvs()
					);
					$order->update_meta_data( '_spfwc_cvs_paylimit', $params['param_list']['PayLimit'] );
					$order->update_meta_data( '_spfwc_cvs_url', $url );
					$order->save();

					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( 'Register', 'woo-sonypayment' ) );
						$order->add_order_note( $message );
					}

					$res .= '<span class="spfwc-settlement-admin cvs-unpaid">' . esc_html__( 'Unpaid', 'woo-sonypayment' ) . '</span>';
					$res .= '<table class="spfwc-settlement-admin-table">';
					$res .= '<tr><th>' . esc_html__( 'Payment deadline', 'woo-sonypayment' ) . '</th>
					<td><input type="text" id="spfwc-paylimit_change" value="' . esc_attr( $paylimit ) . '" style="ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-paylimit" value="' . esc_attr( $paylimit ) . '" /></td>
					</tr>';
					$res .= '<tr><th>' . sprintf( __( 'Payment amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="spfwc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="spfwc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					$res .= '</table>';
					$res .= '<div class="spfwc-settlement-admin-button">';
					$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
					$res .= '<input type="button" id="spfwc-change-button" class="button" value="' . esc_attr__( 'Change', 'woo-sonypayment' ) . '" />';
					$res .= '</div>';
				} else {
					$res       .= '<span class="spfwc-settlement-admin cvs-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res       .= '<div class="spfwc-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$message              = SPFWC_Payment_Message::response_message( $cd );
						$response_data[ $cd ] = $message;
						$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
					}
					$res .= '</div>';
					SPFWC_Logger::add_log( '[2Add] Error: ' . print_r( $response_data, true ) );
				}
				do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				$res           .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['ResponseCd'];
				$data['result'] = $res;
				break;

			// CVS - Change.
			case 'change_cvs':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				$paylimit   = ( isset( $_POST['paylimit'] ) ) ? wp_unslash( $_POST['paylimit'] ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : '';
				if ( empty( $order_id ) || empty( $trans_code ) || '' === $paylimit || '' === $amount ) {
					$data['status'] = 'NG';
					break;
				}
				$res                           = '';
				$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response               = json_decode( $latest_log['response'], true );
				$settings                      = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				$params['send_url']            = $sln->send_url_cvs();
				$params['param_list']          = array_merge(
					$param_list,
					array(
						'OperateId'   => '2Chg',
						'ProcessId'   => $latest_response['ProcessId'],
						'ProcessPass' => $latest_response['ProcessPass'],
						'PayLimit'    => $paylimit . '2359',
						'Amount'      => $amount,
					)
				);
				$response_data                 = $sln->connection( $params );
				if ( 'OK' === $response_data['ResponseCd'] ) {
					$response_data['PayLimit'] = $params['param_list']['PayLimit'];
					$response_data['Amount']   = $params['param_list']['Amount'];

					$FreeArea = trim( $response_data['FreeArea'] );
					$url      = add_query_arg(
						array(
							'code' => $FreeArea,
							'rkbn' => 2,
						),
						SPFWC_SLN_Connection::redirect_url_cvs()
					);
					$order = wc_get_order( $order_id );
					$order->update_meta_data( '_spfwc_cvs_paylimit', $params['param_list']['PayLimit'] );
					$order->update_meta_data( '_spfwc_cvs_url', $url );
					$order->save();

					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( 'Change', 'woo-sonypayment' ) );
						$order->add_order_note( $message );
					}

					$res .= '<span class="spfwc-settlement-admin cvs-unpaid">' . esc_html__( 'Unpaid', 'woo-sonypayment' ) . '</span>';
					$res .= '<table class="spfwc-settlement-admin-table">';
					$res .= '<tr><th>' . esc_html__( 'Payment deadline', 'woo-sonypayment' ) . '</th><td>' . esc_html( $paylimit ) . '</td></tr>';
					$res .= '<tr><th>' . sprintf( __( 'Payment amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th><td>' . esc_html( $amount ) . '</td></tr>';
					$res .= '</table>';
					$res .= '<div class="spfwc-settlement-admin-button">';
					$res .= '<input type="button" id="spfwc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'woo-sonypayment' ) . '" />';
					$res .= '</div>';
				} else {
					$res       .= '<span class="spfwc-settlement-admin cvs-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res       .= '<div class="spfwc-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$message              = SPFWC_Payment_Message::response_message( $cd );
						$response_data[ $cd ] = $message;
						$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
					}
					$res .= '</div>';
					SPFWC_Logger::add_log( '[2Chg] Error: ' . print_r( $response_data, true ) );
				}
				do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				$res           .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['ResponseCd'];
				$data['result'] = $res;
				break;

			// CVS - Delete.
			case 'delete_cvs':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				if ( empty( $order_id ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res                           = '';
				$latest_log                    = SPFWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response               = json_decode( $latest_log['response'], true );
				$settings                      = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
				$transaction_date              = spfwc_get_transaction_date();
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $settings['merchant_id'];
				$param_list['MerchantPass']    = $settings['merchant_pass'];
				$param_list['TransactionDate'] = $transaction_date;
				$param_list['MerchantFree1']   = $trans_code;
				$param_list['MerchantFree2']   = $order_id;
				$params['send_url']            = $sln->send_url_cvs();
				$params['param_list']          = array_merge(
					$param_list,
					array(
						'OperateId'   => '2Del',
						'ProcessId'   => $latest_response['ProcessId'],
						'ProcessPass' => $latest_response['ProcessPass'],
					)
				);
				$response_data                 = $sln->connection( $params );
				if ( 'OK' === $response_data['ResponseCd'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'woo-sonypayment' ), __( 'Delete', 'woo-sonypayment' ) );
						$order->add_order_note( $message );
					}

					$res .= '<span class="spfwc-settlement-admin cvs-del">' . esc_html__( 'Canceled', 'woo-sonypayment' ) . '</span>';
					$res .= '<table class="spfwc-settlement-admin-table">';
					if ( isset( $latest_response['PayLimit'] ) ) {
						$res .= '<tr><th>' . esc_html__( 'Payment deadline', 'woo-sonypayment' ) . '</th><td>' . esc_html( spfwc_get_formatted_date( substr( $latest_response['PayLimit'], 0, 8 ), false ) ) . '</td></tr>';
					}
					if ( isset( $latest_response['Amount'] ) ) {
						$res .= '<tr><th>' . sprintf( __( 'Payment amount (%s)', 'woo-sonypayment' ), get_woocommerce_currency_symbol() ) . '</th><td>' . esc_html( $latest_response['Amount'] ) . '</td></tr>';
					}
					$res .= '</table>';
				} else {
					$res       .= '<span class="spfwc-settlement-admin cvs-error">' . esc_html__( 'Error', 'woo-sonypayment' ) . '</span>';
					$res       .= '<div class="spfwc-settlement-admin-error">';
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$message              = SPFWC_Payment_Message::response_message( $cd );
						$response_data[ $cd ] = $message;
						$res                 .= '<div><span class="code">' . esc_html( $cd ) . '</span> : <span class="message">' . esc_html( $message ) . '</span></div>';
					}
					$res .= '</div>';
					SPFWC_Logger::add_log( '[2Del] Error: ' . print_r( $response_data, true ) );
				}
				do_action( 'spfwc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
				$res           .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['ResponseCd'];
				$data['result'] = $res;
				break;
		}

		wp_send_json( $data );
	}

	/**
	 * Outputs scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	/**
	 * Outputs scripts.
	 */
	public function settlement_scripts() {
		$screen = get_current_screen();
		if ( $screen->id === 'woocommerce_page_wc-orders' ) {
			$order_id = wc_get_order( absint( isset( $_GET['id'] ) ? $_GET['id'] : 0 ) );
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			$order_id = $order->get_id();
		} else {
			global $post, $post_type, $pagenow;
			if ( ! is_object( $post ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}
			if ( 'post.php' !== $pagenow && 'shop_order' !== $post_type ) {
				return;
			}

			$order_id = absint( $post->ID );
			$order    = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$nonce                = wp_create_nonce( 'spfwc-settlement_actions' );
		$customer_id          = $order->get_customer_id();
		$payment_method       = $order->get_payment_method();
		$payment_method_title = $order->get_payment_method_title();
		?>
		<div id="spfwc-settlement-dialog" title="">
			<div id="spfwc-settlement-response-loading"></div>
			<fieldset>
			<div id="spfwc-settlement-response"></div>
			<input type="hidden" id="spfwc-order_id">
			<input type="hidden" id="spfwc-order_num">
			<input type="hidden" id="spfwc-trans_code">
			<input type="hidden" id="spfwc-error" />
			</fieldset>
		</div>
		<script type="text/javascript">
		jQuery( document ).ready( function($) {

			spfwc_admin_order = {

				loadingOn: function() {
					$( '#spfwc-settlement-response-loading' ).html( '<img src="<?php echo esc_url( admin_url() ); ?>images/loading.gif" />' );
				},

				loadingOff: function() {
					$( '#spfwc-settlement-response-loading' ).html( '' );
				},

				getSettlementLatestInfo: function( payment_method ) {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'get_latest_info',
							order_id: <?php echo esc_html( $order_id ); ?>,
							order_num: $( '#spfwc-order_num' ).val(),
							trans_code: $( '#spfwc-trans_code' ).val(),
							customer_id: <?php echo esc_html( $customer_id ); ?>,
							payment_method: payment_method,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-latest' ).html( retVal.latest );
						if ( retVal.trans_code != undefined ) {
							var init_id = '#spfwc-<?php echo esc_html( $order_id ); ?>-' + $( '#spfwc-trans_code' ).val() + '-1';
							var new_id = '#spfwc-<?php echo esc_html( $order_id ); ?>-' + retVal.trans_code + '-1';
							$( init_id ).attr( 'id', new_id );
							// $( '#spfwc-trans_code' ).val( retVal.trans_code );
						}
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					});
					return false;
				},

		<?php if ( 'sonypayment' === $payment_method ) : ?>
				getSettlementInfoCard: function() {
					spfwc_admin_order.loadingOn();

					var mode = ( '' != $( '#spfwc-error' ).val() ) ? 'error_card' : 'get_card';

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: mode,
							order_id: <?php echo esc_html( $order_id ); ?>,
							order_num: $( '#spfwc-order_num' ).val(),
							trans_code: $( '#spfwc-trans_code' ).val(),
							customer_id: <?php echo esc_html( $customer_id ); ?>,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				},

				captureSettlementCard: function( amount ) {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'capture_card',
							order_id: <?php echo esc_html( $order_id ); ?>,
							order_num: $( '#spfwc-order_num' ).val(),
							trans_code: $( '#spfwc-trans_code' ).val(),
							customer_id: <?php echo esc_html( $customer_id ); ?>,
							amount: amount,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						$( '#spfwc-settlement-response-loading' ).html( '' );
					});
					return false;
				},

				changeSettlementCard: function( amount ) {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'change_card',
							order_id: <?php echo esc_html( $order_id ); ?>,
							order_num: $( '#spfwc-order_num' ).val(),
							trans_code: $( '#spfwc-trans_code' ).val(),
							customer_id: <?php echo esc_html( $customer_id ); ?>,
							amount: amount,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				},

				deleteSettlementCard: function( amount ) {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'delete_card',
							order_id: <?php echo esc_html( $order_id ); ?>,
							order_num: $( '#spfwc-order_num' ).val(),
							trans_code: $( '#spfwc-trans_code' ).val(),
							customer_id: <?php echo esc_html( $customer_id ); ?>,
							amount: amount,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				},

				authSettlementCard: function( mode, amount ) {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: mode + '_card',
							order_id: <?php echo esc_html( $order_id ); ?>,
							order_num: $( '#spfwc-order_num' ).val(),
							trans_code: $( '#spfwc-trans_code' ).val(),
							customer_id: <?php echo esc_html( $customer_id ); ?>,
							amount: amount,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				}
		<?php elseif ( 'sonypayment_cvs' === $payment_method ) : ?>
				getSettlementInfoCvs: function() {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'get_cvs',
							order_id: <?php echo esc_html( $order_id ); ?>,
							trans_code: $( '#spfwc-trans_code' ).val(),
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				},

				changeSettlementCvs: function( paylimit, amount ) {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'change_cvs',
							order_id: <?php echo esc_html( $order_id ); ?>,
							trans_code: $( '#spfwc-trans_code' ).val(),
							paylimit: paylimit,
							amount: amount,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				},

				deleteSettlementCvs: function() {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'delete_cvs',
							order_id: <?php echo esc_html( $order_id ); ?>,
							trans_code: $( '#spfwc-trans_code' ).val(),
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				},

				addSettlementCvs: function( paylimit, amount ) {
					spfwc_admin_order.loadingOn();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						cache: false,
						dataType: 'json',
						data: {
							action: 'spfwc_settlement_actions',
							mode: 'add_cvs',
							order_id: <?php echo esc_html( $order_id ); ?>,
							trans_code: $( '#spfwc-trans_code' ).val(),
							paylimit: paylimit,
							amount: amount,
							security: '<?php echo esc_html( $nonce ); ?>'
						}
					}).done( function( retVal, dataType ) {
						$( '#spfwc-settlement-response' ).html( retVal.result );
					}).fail( function( jqXHR, textStatus, errorThrown ) {
						console.log( textStatus );
						console.log( jqXHR.status );
						console.log( errorThrown.message );
					}).always( function() {
						spfwc_admin_order.loadingOff();
					});
					return false;
				}
		<?php endif; ?>
			};

			$( '#spfwc-settlement-dialog' ).dialog({
				dialogClass: 'spfwc-dialog',
				bgiframe: true,
				autoOpen: false,
				height: 'auto',
				width: 'auto',
				modal: true,
				resizable: true,
				closeOnEscape: false,
				buttons: {
					"<?php esc_html_e( 'Close' ); ?>": function() {
						$( this ).dialog( 'close' );
					}
				},
				create: function() {
					$( this ).parent( '.ui-dialog' ).attr( 'id', 'spfwc-dialog' );
				},
				open: function() {
					<?php if ( 'sonypayment' === $payment_method ) : ?>
					spfwc_admin_order.getSettlementInfoCard();
					<?php elseif ( 'sonypayment_cvs' === $payment_method ) : ?>
					spfwc_admin_order.getSettlementInfoCvs();
					<?php endif; ?>
				},
				close: function() {
					spfwc_admin_order.getSettlementLatestInfo( '<?php echo esc_attr( $payment_method ); ?>' );
				}
			});

			$( document ).on( 'click', '.spfwc-settlement-info', function() {
				var idname = $( this ).attr( 'id' );
				var ids = idname.split( '-' );
				$( '#spfwc-trans_code' ).val( ids[2] );
				$( '#spfwc-order_num' ).val( ids[3] );
				$( '#spfwc-error' ).val( '' );
				$( '#spfwc-settlement-dialog' ).dialog( 'option', 'title', '<?php echo esc_attr( $payment_method_title ); ?>' );
				$( '#spfwc-settlement-dialog' ).dialog( 'open' );
			});

		<?php if ( 'sonypayment' === $payment_method ) : ?>
			$( document ).on( 'click', '#spfwc-capture-button', function() {
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to execute a processing sales recording?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.captureSettlementCard( $( '#spfwc-amount_change' ).val() );
			});

			$( document ).on( 'click', '#spfwc-delete-button', function() {
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to a processing of cancellation?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.deleteSettlementCard( $( '#spfwc-amount_change' ).val() );
			});

			$( document ).on( 'click', '#spfwc-change-button', function() {
				if ( $( '#spfwc-amount_change' ).val() == $( '#spfwc-amount' ).val() ) {
					return;
				}
				var amount = $( '#spfwc-amount_change' ).val();
				if ( amount == "" || parseInt( amount ) === 0 || ! $.isNumeric( amount ) ) {
					alert( "<?php esc_html_e( 'The spending amount format is incorrect. Please enter with numeric value.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to change the spending amount?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.changeSettlementCard( $( '#spfwc-amount_change' ).val() );
			});

			$( document ).on( 'click', '#spfwc-auth-button', function() {
				var amount = $( '#spfwc-amount_change' ).val();
				if ( amount == "" || parseInt( amount ) === 0 || ! $.isNumeric( amount ) ) {
					alert( "<?php esc_html_e( 'The spending amount format is incorrect. Please enter with numeric value.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to execute a processing of credit?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.authSettlementCard( 'auth', $( '#spfwc-amount_change' ).val() );
			});

			$( document ).on( 'click', '#spfwc-gathering-button', function() {
				var amount = $( '#spfwc-amount_change' ).val();
				if ( amount == "" || parseInt( amount ) === 0 || ! $.isNumeric( amount ) ) {
					alert( "<?php esc_html_e( 'The spending amount format is incorrect. Please enter with numeric value.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to execute a processing of credit sales recording?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.authSettlementCard( 'gathering', $( '#spfwc-amount_change' ).val() );
			});

			$( document ).on( 'click', '#spfwc-reauth-button', function() {
				var amount = $( '#spfwc-amount_change' ).val();
				if ( amount == "" || parseInt( amount ) === 0 || ! $.isNumeric( amount ) ) {
					alert( "<?php esc_html_e( 'The spending amount format is incorrect. Please enter with numeric value.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to a processing of re-authorization?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.authSettlementCard( 'reauth', $( '#spfwc-amount_change' ).val() );
			});
		<?php elseif ( 'sonypayment_cvs' === $payment_method ) : ?>
			$( document ).on( 'click', '#spfwc-delete-button', function() {
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to a processing of cancellation?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.deleteSettlementCvs();
			});

			$( document ).on( 'click', '#spfwc-change-button', function() {
				if ( ( $( '#spfwc-paylimit_change' ).val() == $( '#spfwc-paylimit' ).val() ) &&
					( $( '#spfwc-amount_change' ).val() == $( '#spfwc-amount' ).val() ) ) {
					return;
				}
				var paylimit = $( '#spfwc-paylimit_change' ).val();
				var amount = $( '#spfwc-amount_change' ).val();
				var today = '<?php echo spfwc_get_transaction_date(); ?>';
				if ( paylimit.length != 8 || ! $.isNumeric( paylimit ) ) {
					alert( "<?php esc_html_e( 'The format of payment due date is incorrect. Please enter with 8 digit number.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( today > paylimit ) {
					alert( "<?php esc_html_e( 'The payment due date is incorrect. Past day is not available.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( amount == "" || parseInt( amount ) === 0 || ! $.isNumeric( parseInt( amount ) ) ) {
					alert( "<?php esc_html_e( 'The format of payment amount is incorrect. Please enter with numeric value.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to execute a processing of changing payment due date and payment amount?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.changeSettlementCvs( paylimit, amount );
			});

			$( document ).on( 'click', '#spfwc-add-button', function() {
				var paylimit = $( '#spfwc-paylimit_change' ).val();
				var amount = $( '#spfwc-amount_change' ).val();
				var today = '<?php echo spfwc_get_transaction_date(); ?>';
				if ( paylimit.length != 8 || ! $.isNumeric( paylimit ) ) {
					alert( "<?php esc_html_e( 'The format of payment due date is incorrect. Please enter with 8 digit number.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( today > paylimit ) {
					alert( "<?php esc_html_e( 'The payment due date is incorrect. Past day is not available.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( amount == "" || parseInt( amount ) === 0 || ! $.isNumeric( parseInt( amount ) ) ) {
					alert( "<?php esc_html_e( 'The format of payment amount is incorrect. Please enter with numeric value.', 'woo-sonypayment' ); ?>" );
					return;
				}
				if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to execute a processing of registration?', 'woo-sonypayment' ); ?>" ) ) {
					return;
				}
				spfwc_admin_order.addSettlementCvs( paylimit, amount );
			});
		<?php endif; ?>
		});
		</script>
		<?php
	}
}

new SPFWC_Admin_Order();
