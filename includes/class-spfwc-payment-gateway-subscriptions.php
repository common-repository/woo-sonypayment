<?php
/**
 * WC_Gateway_SonyPayment class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_SonyPayment class.
 *
 * @extends WC_Payment_Gateway
 * @since 1.0.0
 */
class WC_Gateway_SonyPayment_Subscriptions extends WC_Gateway_SonyPayment {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			$this->supports = array_merge(
				$this->supports,
				array(
					'subscriptions',
					'subscription_suspension',
					'subscription_cancellation',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
				)
			);

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			// add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'my_subscriptions_payment_method' ), 10, 2 );
			add_filter( 'wcs_view_subscription_actions', array( $this, 'view_subscription_actions' ), 10, 2 );
			add_filter( 'spfwc_display_save_payment_method_checkbox', array( $this, 'hide_save_payment_method_checkbox' ) );
			add_filter( 'spfwc_display_howtopay_select', array( $this, 'hide_howtopay_select' ) );
			add_filter( 'spfwc_deletable_cardmember', array( $this, 'deletable_cardmember' ), 10, 2 );
			add_filter( 'spfwc_save_cardmember', array( $this, 'save_cardmember' ), 10, 3 );
		}
	}

	/**
	 * Check if order contains subscriptions.
	 *
	 * @param  int $order_id Order ID.
	 * @return bool Returns true of order contains subscription.
	 */
	protected function order_contains_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param  float    $amount_to_charge The amount to charge.
	 * @param  WC_Order $renewal_order A WC_Order object created to record the renewal payment.
	 * @throws SPFWC_Exception If payment is invalid.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		try {
			$order_id    = $renewal_order->get_id();
			$customer_id = $renewal_order->get_customer_id();

			$settings         = get_option( 'woocommerce_sonypayment_settings', array() );
			$transaction_date = spfwc_get_transaction_date();
			$trans_code       = spfwc_get_transaction_code();
			$operate_id       = apply_filters( 'spfwc_scheduled_subscription_card_operate_id', $settings['subscription_operate_id'], $renewal_order );

			$sln                           = new SPFWC_SLN_Connection();
			$params                        = array();
			$param_list                    = array();
			$param_list['MerchantId']      = $settings['merchant_id'];
			$param_list['MerchantPass']    = $settings['merchant_pass'];
			$param_list['TenantId']        = $settings['tenant_id'];
			$param_list['TransactionDate'] = $transaction_date;
			$param_list['MerchantFree1']   = $trans_code;
			$param_list['MerchantFree2']   = $order_id;
			$member                        = new SPFWC_Card_Member( $customer_id );
			if ( 0 < $customer_id && $member->is_card_member() ) {
				$response_member = $member->search_card_member( $param_list );
				if ( 'OK' === $response_member['ResponseCd'] ) {
					$params['send_url']   = $sln->send_url();
					$params['param_list'] = array_merge(
						$param_list,
						array(
							'MerchantFree3' => $customer_id,
							'KaiinId'       => $member->get_member_id(),
							'KaiinPass'     => $member->get_member_pass(),
							'OperateId'     => $operate_id,
							'PayType'       => '01',
							'Amount'        => $amount_to_charge,
						)
					);
					$response_data        = $sln->connection( $params );
					if ( 'OK' !== $response_data['ResponseCd'] ) {
						$localized_message = __( 'Subscription payment processing failed.', 'woo-sonypayment' );
						$responsecd        = explode( '|', $response_data['ResponseCd'] );
						foreach ( (array) $responsecd as $cd ) {
							$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
						}
						$localized_message = __( 'Subscription payment processing failed.', 'woo-sonypayment' );
						throw new SPFWC_Exception( print_r( $response_data, true ), $localized_message );
					}
					do_action( 'spfwc_scheduled_subscription_payment', $response_data, $renewal_order );
					parent::process_response( $response_data, $renewal_order );

				} else {
					$responsecd = explode( '|', $response_member['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
					}
					$localized_message  = __( 'Subscription payment processing failed.', 'woo-sonypayment' );
					$localized_message .= __( 'Card member does not found.', 'woo-sonypayment' );
					throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
				}
			} else {
				$localized_message  = __( 'Subscription payment processing failed.', 'woo-sonypayment' );
				$localized_message .= __( 'Card member does not found.', 'woo-sonypayment' );
				$renewal_order->update_status( 'failed' );
			}
		} catch ( SPFWC_Exception $e ) {
			SPFWC_Logger::add_log( 'Payment Subscription Error: ' . $e->getMessage() );
			wc_add_notice( $e->getLocalizedMessage(), 'error' );

			do_action( 'spfwc_scheduled_subscription_payment_error', $e, $renewal_order );

			$renewal_order->update_status( 'failed' );
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param  string          $payment_method_to_display the default payment method text to display.
	 * @param  WC_Subscription $subscription the subscription details.
	 * @return string the subscription payment method
	 */
	public function my_subscriptions_payment_method( $payment_method_to_display, $subscription ) {

		return $payment_method_to_display;
	}

	/**
	 * Initialise gateway settings form fields for sunscriptions.
	 */
	public function init_form_fields() {

		parent::init_form_fields();

		$form_fields = array();
		foreach ( $this->form_fields as $key => $field ) {
			if ( 'cardmember' === $key ) {
				$field['description'] .= '<br />' . __( 'Please be sure to enable when using the subscription.', 'woo-sonypayment' );
			}
			$form_fields[ $key ] = $field;
			if ( 'operate_id' === $key ) {
				$form_fields['subscription_operate_id'] = array(
					'title'       => __( 'Subscriptions operation mode', 'woo-sonypayment' ),
					'type'        => 'select',
					'options'     => array(
						'1Auth'      => __( 'Credit', 'woo-sonypayment' ),
						'1Gathering' => __( 'Credit sales recorded', 'woo-sonypayment' ),
					),
					'default'     => '1Gathering',
					'description' => __( 'Setting up the operation mode of the subscription.', 'woo-sonypayment' ) . '<br />' . __( 'In case of \'Credit\' setting, it need to change to \'Sales recorded\' manually in later. In case of \'Credit sales recorded\' setting, sales will be recorded at the time of subscription purchase.', 'woo-sonypayment' ),
				);
			}
		}
		$this->form_fields = $form_fields;
	}

	/**
	 * Retrieve available actions that a user can perform on the subscription.
	 *
	 * @param  array           $actions Actions.
	 * @param  WC_Subscription $subscription the subscription details.
	 * @return array
	 */
	public function view_subscription_actions( $actions, $subscription ) {

		if ( array_key_exists( 'resubscribe', $actions ) ) {
			unset( $actions['resubscribe'] );
		}
		return $actions;
	}

	/**
	 * Can not choose the option which won't register the credit card.
	 *
	 * @param  bool $display_save_payment_method Save the card.
	 * @return bool
	 */
	public function hide_save_payment_method_checkbox( $display_save_payment_method ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}
		return $display_save_payment_method;
	}

	/**
	 * Set only for lump-sum payment.
	 *
	 * @param  string $display_howtopay Number of payments.
	 * @return string
	 */
	public function hide_howtopay_select( $display_howtopay ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$display_howtopay = '1';
		}
		return $display_howtopay;
	}

	/**
	 * Members with subscription contracts can not deletable.
	 *
	 * @param  bool $deletable Is deletable.
	 * @param  int  $customer_id The WP user ID.
	 * @return bool
	 */
	public function deletable_cardmember( $deletable, $customer_id ) {

		$member = new SPFWC_Card_Member( $customer_id );
		if ( 0 < $customer_id && $member->is_card_member() ) {
			if ( wcs_user_has_subscription( $customer_id, '', 'active' ) ) {
				$deletable = false;
			}
		}
		return $deletable;
	}

	/**
	 * Always registering as a card member at the time of subscription purchase.
	 *
	 * @param  string   $card_member change|add.
	 * @param  WC_Order $order Order object.
	 * @param  int      $customer_id The WP user ID.
	 * @return string
	 */
	public function save_cardmember( $card_member, $order, $customer_id ) {

		if ( empty( $card_member ) && 0 < $customer_id ) {
			$order_id = $order->get_id();
			if ( $this->order_contains_subscription( $order_id ) ) {
				$member      = new SPFWC_Card_Member( $customer_id );
				$card_member = ( $member->is_card_member() ) ? 'change' : 'add';
			}
		}
		return $card_member;
	}
}
