<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Stripe_Blocks_Support class.
 *
 * @extends AbstractPaymentMethodType
 */
final class SPFWC_Blocks_Support_Cvs extends AbstractPaymentMethodType {
	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_SonyPayment_CVS
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'sonypayment_cvs';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_sonypayment_cvs_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$script_path       = '/build/index.js';
		$script_asset_path = SPFWC_PLUGIN_DIR . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url        = SPFWC_PLUGIN_URL . $script_path;

		wp_register_script(
			'wc-sonypayment-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
//			wp_set_script_translations( 'wc-sonypayment-payments-blocks', 'woo-sonypayment', SPFWC_PLUGIN_DIR . '/languages');
			wp_set_script_translations( 'wc-sonypayment-payments-blocks', 'woo-sonypayment');
		}

		return [ 'wc-sonypayment-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {


		$customer_id = get_current_user_id();


		$description     = $this->get_setting( 'description' );
		$test_mode       = 'yes' === $this->gateway->get_option( 'testmode' );
		$fee_text = '';

		$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
		if ( $description ) {
			if ( $this->gateway->testmode ) {
				$description .= ' ' . __( 'TESTMODE RUNNING.', 'woo-sonypayment' );
				$description  = trim( $description );
			}
			$description = apply_filters( 'spfwc_payment_description_cvs', wpautop( wp_kses_post( $description ) ), $this->name );
		}
		if ( is_page( $checkout_page_id ) ) {
			if ( $this->gateway->settlement_fee ) {
				$cart_totals = WC()->session->get( 'cart_totals' );
				if ( isset( $cart_totals['total'] ) ) {
					$fee = $this->gateway->get_settlement_fee( $cart_totals['total'] );
					if ( 0 < $fee ) {
						$fee_text = '<div class="sonypayment-cvs-settlement-fee">' . sprintf( __( '* A settlement fee of <strong>%s</strong> will be added at the time of settlement.', 'woo-sonypayment' ), wc_price( $fee ) ) . '</div>';
					}
				}
			}

		}

		$data = array(
			'title'             => $this->get_setting( 'title' ),
			'description'       => $description,
			'supports'          => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
			'is_user_logged_in' => is_user_logged_in(),
			'fee_text'          => $fee_text,
		);

		return $data;
	}
}
