<?php
/**
 * SPFWC_Payment_Request class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Payment_Request class.
 *
 * @since 1.0.0
 */
class SPFWC_Payment_Request {

	/**
	 * Initialize class actions.
	 */
	public function __construct() {

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		$this->init();
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'wc_ajax_spfwc_get_card_member', array( $this, 'ajax_get_card_member' ) );
	}

	/**
	 * Load public scripts and styles.
	 */
	public function payment_scripts() {

		if ( ! is_ssl() ) {
			SPFWC_Logger::add_log( 'SonyPayment requires SSL.' );
		}

		if ( ! is_product() && ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		wp_register_script( 'sonypayment_request_script', plugins_url( 'assets/js/spfwc-request.js', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION, true );
		$sonypayment_request_params                = array();
		$sonypayment_request_params['ajax_url']    = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$sonypayment_request_params['customer_id'] = get_current_user_id();
		$sonypayment_request_params['nonce']       = array(
			'payment'     => wp_create_nonce( 'spfwc-payment_request' ),
			'card_member' => wp_create_nonce( 'spfwc-get_card_member' ),
			'checkout'    => wp_create_nonce( 'woocommerce-process_checkout' ),
		);
		wp_localize_script( 'sonypayment_request_script', 'sonypayment_request_params', apply_filters( 'sonypayment_request_params', $sonypayment_request_params ) );
		wp_enqueue_script( 'sonypayment_request_script' );
	}

	/**
	 * Get card member info.
	 */
	public function ajax_get_card_member() {
		check_ajax_referer( 'spfwc-get_card_member', 'security' );

		$data        = array();
		$customer_id = absint( wp_unslash( $_POST['customer_id'] ) );
		$member      = new SPFWC_card_member( $customer_id );
		if ( $member->is_card_member() ) {
			$response_member = $member->search_card_member();
			if ( 'OK' === $response_member['ResponseCd'] ) {
				$data['cardlast4']  = substr( $response_member['CardNo'], -4 );
				$data['cardfirst4'] = substr( $response_member['CardNo'], 0, 4 );
				$data['status']     = 'success';
			} else {
				$data['status'] = 'fail';
			}
		}
		wp_send_json( $data );
	}
}

new SPFWC_Payment_Request();
