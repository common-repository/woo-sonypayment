<?php
/**
 * Functions
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

/**
 * Transaction code.
 *
 * @param  int $digits Generated digits.
 * @return string
 */
function spfwc_get_transaction_code( $digits = 12 ) {
	$num              = str_repeat( '9', $digits );
	$transaction_code = apply_filters( 'spfwc_transaction_code', sprintf( '%0' . $digits . 'd', wp_rand( 1, (int) $num ) ), $num );
	return $transaction_code;
}

/**
 * Inittial transaction code.
 *
 * @param  int $digits Generated digits.
 * @return string
 */
function spfwc_init_transaction_code( $digits = 12 ) {
	$transaction_code = apply_filters( 'spfwc_init_transaction_code', str_repeat( '9', $digits ) );
	return $transaction_code;
}

/**
 * Transaction date.
 *
 * @return string 'yyyymmdd'
 */
function spfwc_get_transaction_date() {
	$transaction_date = date_i18n( 'Ymd', current_time( 'timestamp' ) );
	return $transaction_date;
}

/**
 * Date format.
 *
 * @param  string $date Date.
 * @param  bool   $localize Translate|Original.
 * @return string
 */
function spfwc_get_formatted_date( $date, $localize = true ) {
	if ( 14 === strlen( $date ) ) {
		$formatted_date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 ) . ' ' . substr( $date, 8, 2 ) . ':' . substr( $date, 10, 2 ) . ':' . substr( $date, 12, 2 );
		if ( $localize ) {
			$formatted_date = date_i18n( __( 'M j, Y @ G:i:s', 'woo-sonypayment' ), strtotime( $formatted_date ) );
		}
	} elseif ( 12 === strlen( $date ) ) {
		$formatted_date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 ) . ' ' . substr( $date, 8, 2 ) . ':' . substr( $date, 10, 2 );
		if ( $localize ) {
			$formatted_date = date_i18n( __( 'M j, Y @ G:i', 'woo-sonypayment' ), strtotime( $formatted_date ) );
		}
	} elseif ( 8 === strlen( $date ) ) {
		$formatted_date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		if ( $localize ) {
			$formatted_date = date_i18n( __( 'M j, Y', 'woo-sonypayment' ), strtotime( $formatted_date ) );
		}
	} else {
		$formatted_date = $date;
	}
	return $formatted_date;
}

/**
 * Get order property with compatibility for WC lt 3.0.
 *
 * @param  WC_Order $order Order object.
 * @param  string   $key   Order property.
 * @return mixed Value of order property.
 */
function spfwc_get_order_prop( $order, $key ) {
	$getter = array( $order, 'get_' . $key );
	return is_callable( $getter ) ? call_user_func( $getter ) : $order->{ $key };
}

if ( ! function_exists( 'is_edit_cardmember_page' ) ) {

	/**
	 * Checks if is an edit card member page.
	 *
	 * @return bool
	 */
	function is_edit_cardmember_page() {
		global $wp;

		return ( is_page( wc_get_page_id( 'myaccount' ) ) && isset( $wp->query_vars['edit-cardmember'] ) );
	}
}

/**
 * Operation name.
 *
 * @param  string $OperateId Operation ID.
 * @return string
 */
function spfwc_get_operation_name( $OperateId ) {
	$operation_name = '';
	switch ( $OperateId ) {
		case '1Check':
			$operation_name = __( 'Card check', 'woo-sonypayment' );
			break;
		case '1Auth':
			$operation_name = __( 'Credit', 'woo-sonypayment' );
			break;
		case '1Capture':
			$operation_name = __( 'Sales recorded', 'woo-sonypayment' );
			break;
		case '1Gathering':
			$operation_name = __( 'Credit sales recorded', 'woo-sonypayment' );
			break;
		case '1Change':
			$operation_name = __( 'Amount change', 'woo-sonypayment' );
			break;
		case '1Delete':
			$operation_name = __( 'Cancel', 'woo-sonypayment' );
			break;
		case '1Search':
			$operation_name = __( 'Transaction reference', 'woo-sonypayment' );
			break;
		case '1ReAuth':
			$operation_name = __( 'Re-authorization', 'woo-sonypayment' );
			break;
		case '2Add':
			$operation_name = __( 'Register', 'woo-sonypayment' );
			break;
		case '2Chg':
			$operation_name = __( 'Change', 'woo-sonypayment' );
			break;
		case '2Del':
			$operation_name = __( 'Delete', 'woo-sonypayment' );
			break;
		case '4MemRef':
		case '4MemRefM':
		case '4MemRefMulti':
		case '4MemRefToken':
			$operation_name = __( 'Member reference', 'woo-sonypayment' );
			break;
		case '4MemUnInval':
			$operation_name = __( 'Cancellation of member', 'woo-sonypayment' );
			break;
		case '4MemDel':
			$operation_name = __( 'Delete member', 'woo-sonypayment' );
			break;
		case '5Auth':
			$operation_name = __( 'Foreign currency credit', 'woo-sonypayment' );
			break;
		case '5Gathering':
			$operation_name = __( 'Foreign currency credit sales settled', 'woo-sonypayment' );
			break;
		case '5Capture':
			$operation_name = __( 'Foreign currency sales settled', 'woo-sonypayment' );
			break;
		case '5Delete':
			$operation_name = __( 'Foreign currency cancellation', 'woo-sonypayment' );
			break;
		case '5OpeUnInval':
			$operation_name = __( 'Resume of foreign currency transactions', 'woo-sonypayment' );
			break;
		case 'paid':
			$operation_name = __( 'Payment', 'woo-sonypayment' );
			break;
		case 'expired':
			$operation_name = __( 'Expired', 'woo-sonypayment' );
			break;
	}
	return $operation_name;
}

/**
 * Storage agency name.
 *
 * @param  string $CvsCd Convenience store code.
 * @return string
 */
function spfwc_get_cvs_name( $CvsCd ) {
	switch ( trim( $CvsCd ) ) {
		case 'LSN':
			$cvs_name = __( 'Lawson', 'woo-sonypayment' );
			break;
		case 'FAM':
			$cvs_name = __( 'Family Mart', 'woo-sonypayment' );
			break;
		case 'SAK':
			$cvs_name = __( 'Thanks', 'woo-sonypayment' );
			break;
		case 'CCK':
			$cvs_name = __( 'Circle K', 'woo-sonypayment' );
			break;
		case 'ATM':
			$cvs_name = __( 'Pay-easy (ATM)', 'woo-sonypayment' );
			break;
		case 'ONL':
			$cvs_name = __( 'Pay-easy (online)', 'woo-sonypayment' );
			break;
		case 'LNK':
			$cvs_name = __( 'Pay-easy (information link)', 'woo-sonypayment' );
			break;
		case 'SEV':
			$cvs_name = __( 'Seven-Eleven', 'woo-sonypayment' );
			break;
		case 'MNS':
			$cvs_name = __( 'Ministop', 'woo-sonypayment' );
			break;
		case 'DAY':
			$cvs_name = __( 'Daily Yamazaki', 'woo-sonypayment' );
			break;
		case 'EBK':
			$cvs_name = __( 'Rakuten Bank', 'woo-sonypayment' );
			break;
		case 'JNB':
			$cvs_name = __( 'Japan Net Bank', 'woo-sonypayment' );
			break;
		case 'EDY':
			$cvs_name = __( 'Edy', 'woo-sonypayment' );
			break;
		case 'SUI':
			$cvs_name = __( 'Suica', 'woo-sonypayment' );
			break;
		case 'FFF':
			$cvs_name = __( 'Three F', 'woo-sonypayment' );
			break;
		case 'JIB':
			$cvs_name = __( 'Jibun Bank', 'woo-sonypayment' );
			break;
		case 'SNB':
			$cvs_name = __( 'Shumishin SBI Net Bank', 'woo-sonypayment' );
			break;
		case 'SCM':
			$cvs_name = __( 'Seico Mart', 'woo-sonypayment' );
			break;
		case 'JPM':
			$cvs_name = __( 'JCB Premo', 'woo-sonypayment' );
			break;
		default:
			$cvs_name = $CvsCd;
	}
	return $cvs_name;
}

/**
 * The number of installments.
 *
 * @param  string $PayType Number of payments.
 * @return string
 */
function spfwc_get_paytype( $PayType ) {
	switch ( $PayType ) {
		case '01':
			$paytype_name = __( 'Lump-sum payment', 'woo-sonypayment' );
			break;
		case '02':
		case '03':
		case '05':
		case '06':
		case '10':
		case '12':
		case '15':
		case '18':
		case '20':
		case '24':
			$times        = (int) $PayType;
			$paytype_name = $times . __( '-installments', 'woo-sonypayment' );
			break;
		case '80':
			$paytype_name = __( 'Pay for it out of a bonus', 'woo-sonypayment' );
			break;
		case '88':
			$paytype_name = __( 'Revolving payment', 'woo-sonypayment' );
			break;
	}
	return $paytype_name;
}

/**
 * Card members have active orders.
 *
 * @param  int $customer_id The WP user ID.
 * @return bool
 */
function spfwc_get_customer_active_card_orders( $customer_id ) {

	$active                = false;
	$active_order_statuses = apply_filters(
		'spfwc_active_order_statuses',
		array(
			'wc-pending',
			'wc-processing',
			'wc-on-hold',
			'wc-refunded',
		)
	);

	$customer        = get_user_by( 'id', absint( $customer_id ) );
	$customer_orders = wc_get_orders(
		array(
			'limit'    => -1,
			'customer' => array( array( 0, $customer->user_email ) ),
			'return'   => 'ids',
		)
	);

	if ( ! empty( $customer_orders ) ) {
		foreach ( $customer_orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$payment_method = $order->get_payment_method();
			$order_status   = get_post_status( $order_id );
			if ( 'sonypayment' === $payment_method && in_array( $order_status, $active_order_statuses, true ) ) {
				$active = true;
				break;
			}
		}
	}

	return $active;
}

/**
 * Consent to Obtain Personal Information Messsage.
 *
 * @return string
 */
function spfwc_consent_message() {
	$message = __( '* Cautions on Use of Credit Cards', 'woo-sonypayment' ) . "\n" .
		__( 'In order to prevent unauthorized use of your credit card through theft of information such as your credit card number, we use "EMV 3D Secure," an identity authentication service recommended by international brands.', 'woo-sonypayment' ) . "\n" .
		__( 'In order to use EMV 3D Secure, it is necessary to send information about you to the card issuer.', 'woo-sonypayment' ) . "\n" .
		__( 'Please read "* Provision of Personal Information to Third Parties" below and enter your card information only if you agree to the terms of the agreement.', 'woo-sonypayment' ) . "\n" .
		__( '* Provision of Personal Information to Third Parties', 'woo-sonypayment' ) . "\n" .
		__( 'The following personal information, etc. collected from customers will be provided to the issuer of the card being used by the customer for the purpose of detecting and preventing fraudulent use by the card issuer.', 'woo-sonypayment' ) . "\n" .
		__( '"Full name", "e-mail address", "Membership information held by the business", "IP address", "device information", "Information on the Internet usage environment", and "Billing address".', 'woo-sonypayment' ) . "\n" .
		__( 'If the issuer of the card you are using is located in a foreign country, these information may be transferred to the country to which such issuer belongs.', 'woo-sonypayment' ) . "\n" .
		__( 'If you are a minor, you are required to obtain the consent of a person with parental authority or a guardian before using the Service.', 'woo-sonypayment' ) . "\n" .
		__( '* Agreement to provide personal information to a third party', 'woo-sonypayment' ) . "\n" .
		__( 'If you agree to the above "* Provision of Personal Information to Third Parties", please check the "I agree to the handling of personal information" checkbox and press "Place order".', 'woo-sonypayment' ) . "\n" .
		__( '* Safety Control Measures', 'woo-sonypayment' ) . "\n" .
		__( 'We may provide all or part of the information obtained from our customers to subcontractors in the United States.', 'woo-sonypayment' ) . "\n" .
		__( 'We will confirm that the subcontractor takes necessary and appropriate measures for the safe management of the information before storing it.', 'woo-sonypayment' ) . "\n" .
		__( 'For an overview of the legal system regarding the protection of personal information in the relevant country, please check here.', 'woo-sonypayment' ) . "\n" .
		'https://www.ppc.go.jp/personalinfo/legal/kaiseihogohou/#gaikoku';
	return $message;
}
