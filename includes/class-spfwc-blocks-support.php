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
final class SPFWC_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_SonyPayment
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'sonypayment';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_sonypayment_settings', [] );
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
			'wc-sonypayment-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
//			wp_set_script_translations( 'wc-sonypayment-payments-blocks', 'woo-sonypayment', SPFWC_PLUGIN_DIR . '/languages');
			wp_set_script_translations( 'wc-sonypayment-payments-blocks', 'woo-sonypayment');
		}

		return [ 'wc-sonypayment-payments-blocks' ];
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

		$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
		if ( $description ) {
			if ( $this->gateway->testmode ) {
				$description .= ' ' . __( 'TESTMODE RUNNING.', 'woo-sonypayment' );
				$description  = trim( $description );
			}
			$description = apply_filters( 'spfwc_payment_description', wpautop( wp_kses_post( $description ) ), $this->name );
		}
		$form_html           = '';
		$three_d_secure_html = '';
		$response_data_3d    = array();
		if ( is_page( $checkout_page_id ) ) {
			ob_start();
			if ( ! $this->gateway->linktype ) {
				$this->gateway->elements_form();
			}
			$display_save_payment_method = is_checkout() && $this->gateway->cardmember;

			if ( apply_filters( 'spfwc_display_save_payment_method_checkbox',
					$display_save_payment_method ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
				if ( $this->gateway->cardmember ) {
					$this->gateway->save_payment_method_checkbox();
				}
			}
			$form_html = ob_get_contents();
			ob_end_clean();

			ob_start();
			$this->gateway->obtain_consent();
			$three_d_secure_html = ob_get_contents();

			ob_end_clean();
			if ( isset( $_GET['order_id'] ) && isset( $_GET['key'] ) ) {
				$key              = ( isset( $_GET['key'] ) ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
				$order_id         = ( isset( $_GET['order_id'] ) ) ? (int) wc_clean( wp_unslash( $_GET['order_id'] ) ) : 0;
				$order            = wc_get_order( $order_id );
				$response_data_3d = $order->get_meta( 'wc_sonypayment_block_process_payment_retransfer_' . $key, true );
				if ( $response_data_3d ) {
					$order->delete_meta_data( 'wc_sonypayment_block_process_payment_retransfer_' . $key );
					$order->save();
				} else {
					wp_safe_redirect( wc_get_checkout_url() );
				}
			}
		}

		$data = array(
			'title'               => $this->get_setting( 'title' ),
			'description'         => $description,
			'supports'            => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
			'cardlast4'           => '',
			'seccd'               => 'yes' === $this->get_setting( 'seccd', 'yes' ),
			'cardmember'          => 'yes' === $this->get_setting( 'cardmember', 'yes' ),
			'always_save'         => 'yes' === $this->get_setting( 'always_save', 'yes' ),
			'howtopay'            => $this->get_setting( 'howtopay', '1' ),
			'is_card_member'      => false,
			'linktype'            => 'yes' === $this->get_setting( 'linktype' ),
			'is_user_logged_in'   => is_user_logged_in(),
			'form_html'           => $form_html,
			'three_d_secure'      => 'yes' === $this->get_setting( 'three_d_secure', 'yes' ),
			'three_d_secure_html' => $three_d_secure_html,
			'response_data_3d'    => $response_data_3d,
		);

		return $data;
	}
}
