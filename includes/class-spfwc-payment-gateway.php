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
class WC_Gateway_SonyPayment extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = 'sonypayment';
		$this->has_fields         = true;
		$this->method_title       = __( 'SonyPayment', 'woo-sonypayment' );
		$this->method_description = __( 'Contract with Sony Payment Services, credit card payment will be available.', 'woo-sonypayment' );
		$this->supports           = array(
			'products',
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
		$this->linktype       = 'yes' === $this->get_option( 'linktype' );
		$this->merchant_id    = $this->get_option( 'merchant_id' );
		$this->merchant_pass  = $this->get_option( 'merchant_pass' );
		$this->tenant_id      = $this->get_option( 'tenant_id' );
		$this->auth_key       = $this->get_option( 'auth_key' );
		$this->token_code     = $this->get_option( 'token_code' );
		$this->three_d_secure = 'yes' === $this->get_option( 'three_d_secure', 'yes' );
		$this->seccd          = 'yes' === $this->get_option( 'seccd', 'yes' );
		$this->cardmember     = 'yes' === $this->get_option( 'cardmember', 'yes' );
		$this->always_save    = 'yes' === $this->get_option( 'always_save', 'yes' );
		$this->operate_id     = $this->get_option( 'operate_id', '1Gathering' );
		$this->howtopay       = $this->get_option( 'howtopay', '1' );
		$this->order_status   = $this->get_option( 'order_status', 'processing' );
		$this->logging        = 'yes' === $this->get_option( 'logging', 'yes' );

		if ( $this->linktype ) {
			$this->order_button_text = __( 'Continue to payment', 'woo-sonypayment' );
		}

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'payment_complete_order_status' ), 10, 3 );
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
	 * Checks if required items are set.
	 *
	 * @return bool
	 */
	public function is_valid_setting() {

		if ( empty( $this->merchant_id ) || empty( $this->merchant_pass ) || empty( $this->auth_key ) ) {
			return false;
		}
		if ( ! $this->linktype && empty( $this->token_code ) ) {
			return false;
		}
		if ( ! $this->check_auth_key( $this->auth_key ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check authentication key.
	 *
	 * @param  string $auth_key Pro version authentication key.
	 * @return bool
	 */
	private function check_auth_key( $auth_key ) {

		$auth_keys = array(
			'ace390940f0c8058349f504d9ac64b20',
			'53f11e441f3e6ad6922842fc313e3277',
			'985a5f14958a59153ada20efcdb4dcf9',
			'bfcca5663df9f3f0bc4b009ec965dfa9',
			'c5a8a75aaa52f6ee04f4d841dbde013a',
			'b38a8d85be78071c44815ddcf091deea',
			'b0e8b1227cfbf1697a081172826e5dcf',
			'1f492f83e8ccdd739a5deb328371a959',
			'30be449a0b51a4a4f14e1e52c0c096ab',
			'7b6c94fe08e55c3d953f79243f12bbf3',
		);
		return in_array( md5( $auth_key ), $auth_keys, true );
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
			'spfwc_gateway_settings',
			array(
				'enabled'        => array(
					'title'   => __( 'Enable/Disable', 'woo-sonypayment' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable SonyPayment', 'woo-sonypayment' ),
					'default' => 'no',
				),
				'title'          => array(
					'title'       => __( 'Title', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woo-sonypayment' ),
					'default'     => __( 'Credit Card', 'woo-sonypayment' ),
					'desc_tip'    => true,
				),
				'description'    => array(
					'title'       => __( 'Description', 'woo-sonypayment' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'woo-sonypayment' ),
					'default'     => __( 'Pay with your credit card.', 'woo-sonypayment' ),
				),
				'testmode'       => array(
					'title'       => __( 'Test mode', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Test mode', 'woo-sonypayment' ),
					'default'     => 'yes',
					'description' => __( 'Connect to test environment and run in test mode.', 'woo-sonypayment' ),
				),
				'linktype'       => array(
					'title'       => __( 'Connect type', 'woo-sonypayment' ),
					'label'       => __( 'Connect with External link type', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'description' => __( 'In case of external link type, a processing of payment runs after transitioning to the payment screen of SonyPayment. In case of usual, it completes in store\'s site only.', 'woo-sonypayment' ) .
						'<span id="woocommerce_sonypayment_attention_3ds"><br /><br />' .
						'<strong>' . __( '[Note on chargebacks]', 'woo-sonypayment' ) . '</strong><br />' .
						__( '* Even if sales approval has been obtained (when the authorization result is OK), chargebacks will still be incurred.', 'woo-sonypayment' ) . '<br />' .
						__( '* If chargebacks occur, there is no compensation or reimbursement by us or the credit card companies. The merchant is responsible for all charges.', 'woo-sonypayment' ) . '<br />' .
						__( "* Chargebacks will be incurred regardless of whether the merchant's intentional or negligent conduct is involved.", 'woo-sonypayment' ) . '<br />' .
						__( 'Please be sure to confirm the following before starting to use the service.', 'woo-sonypayment' ) . '<br />' .
						'<a href="https://www.sonypaymentservices.jp/consider/creditcard/chargeback.html" target="_blank">' . __( 'About chargebacks', 'woo-sonypayment' ) . '</a></span>',
					'default'     => 'no',
				),
				'merchant_id'    => array(
					'title'       => __( 'Merchant ID', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Merchant ID\' (single-byte numbers only) issued from Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'merchant_pass'  => array(
					'title'       => __( 'Merchant Password', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Merchant Password\' (single-byte alphanumeric characters only) issued from Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'tenant_id'      => array(
					'title'       => __( 'Tenant ID', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Tenant ID\' issued from Sony Payment Services. If the number of contracting shop is only one, enter \'0001\'.', 'woo-sonypayment' ),
					'default'     => '0001',
					'desc_tip'    => true,
					'placeholder' => '0001',
				),
				'auth_key'       => array(
					'title'       => __( 'Settlement auth key', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Settlement auth key\' (single-byte numbers only) issued from Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'three_d_secure' => array(
					'title'       => __( '3D Secure', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'label'       => __( 'Use 3D Secure authentication', 'woo-sonypayment' ),
					'default'     => '',
					'description' => __( '3D Secure Authentication for Settlement. To use it, you need to apply to Sony Payment Services.', 'woo-sonypayment' ) . '<br /><br />' .
						'<strong>' . __( '[About 3D Secure]', 'woo-sonypayment' ) . '</strong><br />' .
						__( 'If you do not use 3D Secure (do not check the "Use" box), the merchant is responsible for payment due to fraudulent use of the credit card.', 'woo-sonypayment' ) . '<br />' .
						__( 'Even if we have already paid the merchant an amount equivalent to the sales proceeds, the merchant must return the amount to us upon request for a chargeback (return of sales proceeds) from the credit card company.', 'woo-sonypayment' ) . '<br />' .
						__( 'Please note that chargebacks may occur even if you select "Use". Please understand this in advance.', 'woo-sonypayment' ) . '<br />' .
						__( 'If you have applied for the EMV 3D Secure service, please be sure to select "Use".', 'woo-sonypayment' ) . '<br /><br />' .
						'<strong>' . __( '[Note on chargebacks]', 'woo-sonypayment' ) . '</strong><br />' .
						__( '* Even if sales approval has been obtained (when the authorization result is OK), chargebacks will still be incurred.', 'woo-sonypayment' ) . '<br />' .
						__( '* If chargebacks occur, there is no compensation or reimbursement by us or the credit card companies. The merchant is responsible for all charges.', 'woo-sonypayment' ) . '<br />' .
						__( "* Chargebacks will be incurred regardless of whether the merchant's intentional or negligent conduct is involved.", 'woo-sonypayment' ) . '<br />' .
						__( 'Please be sure to confirm the following before starting to use the service.', 'woo-sonypayment' ) . '<br />' .
						'<a href="https://www.sonypaymentservices.jp/consider/creditcard/chargeback.html" target="_blank">' . __( 'About chargebacks', 'woo-sonypayment' ) . '</a>',
				),
				'key_aes'        => array(
					'title'       => __( 'Encryption Key', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Encryption Key\' (single-byte alphanumeric characters only) issued from Sony Payment Services.', 'woo-sonypayment' ) . __( 'If you want to use 3D Secure Authentication or External Link Type Payment, please apply to Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'key_iv'         => array(
					'title'       => __( 'Initialization Vector', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Initialization Vector\' (single-byte alphanumeric characters only) issued from Sony Payment Services.', 'woo-sonypayment' ) . __( 'If you want to use 3D Secure Authentication or External Link Type Payment, please apply to Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'token_code'     => array(
					'title'       => __( 'Token settlement auth code', 'woo-sonypayment' ),
					'type'        => 'text',
					'description' => __( 'Enter \'Token settlement auth code\' (single-byte alphanumeric characters only) issued from Sony Payment Services.', 'woo-sonypayment' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'seccd'          => array(
					'title'       => __( 'Security code (authentication assist)', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'label'       => __( 'Use authentication of Security code (authentication assist)', 'woo-sonypayment' ),
					'default'     => 'yes',
					'description' => __( 'Use \'Security code\' of authentication assist matching. If you don\'t use it, please set \'Do not use Matching Verify\' on the e-SCOTT admin page.', 'woo-sonypayment' ),
				),
				'cardmember'     => array(
					'title'       => __( 'Card Members', 'woo-sonypayment' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable payment via saved card', 'woo-sonypayment' ),
					'default'     => 'yes',
					'description' => __( 'When this is enabled, members can pay with saved card. Card number will be registered in SonyPayment, not in your store.', 'woo-sonypayment' ),
				),
				'always_save'    => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label'       => __( 'Always registering as a card member', 'woo-sonypayment' ),
					'default'     => 'yes',
					'description' => __( 'If this is enabled, the members can not choose the option which they won\'t register the credit card.', 'woo-sonypayment' ),
				),
				'operate_id'     => array(
					'title'       => __( 'Operation mode', 'woo-sonypayment' ),
					'type'        => 'select',
					'options'     => array(
						'1Auth'      => __( 'Credit', 'woo-sonypayment' ),
						'1Gathering' => __( 'Credit sales recorded', 'woo-sonypayment' ),
					),
					'default'     => '1Gathering',
					'description' => __( 'In case of \'Credit\' setting, it need to change to \'Sales recorded\' manually in later. In case of \'Credit sales recorded\' setting, sales will be recorded at the time of purchase.', 'woo-sonypayment' ),
				),
				'howtopay'       => array(
					'title'       => __( 'The number of installments', 'woo-sonypayment' ),
					'type'        => 'select',
					'options'     => array(
						'1' => __( 'Lump-sum payment only', 'woo-sonypayment' ),
						'2' => __( 'Enable installment payments', 'woo-sonypayment' ),
						'3' => __( 'Enable installment payments and bonus payments', 'woo-sonypayment' ),
					),
					'default'     => '1',
					'description' => __( 'Allow customer to choose the number of installment payments. In case of External link type,lump-sum payment only available.', 'woo-sonypayment' ),
				),
				'order_status'   => array(
					'title'       => __( 'Order Status', 'woo-sonypayment' ),
					'type'        => 'select',
					'options'     => array(
						'processing' => __( 'Set to \'Processing\'', 'woo-sonypayment' ),
						'completed'  => __( 'Set to \'Completed\'', 'woo-sonypayment' ),
					),
					'default'     => 'processing',
					'description' => __( 'If the \'Operation mode\' is set to \'Credit\' or if a non-virtual item is purchased, the transaction status will be \'Processing\' even if \'Completed\' is selected.', 'woo-sonypayment' ),
				),
				'logging'        => array(
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

		$display_save_payment_method = is_checkout() && $this->cardmember;
		$description                 = $this->get_description() ? $this->get_description() : '';

		ob_start();
		echo '<div id="sonypayment-payment-data">';
		if ( $description ) {
			if ( $this->testmode ) {
				$description .= ' ' . __( 'TESTMODE RUNNING.', 'woo-sonypayment' );
				$description  = trim( $description );
			}
			echo apply_filters( 'spfwc_payment_description', wpautop( wp_kses_post( $description ) ), $this->id );
		}
		if ( ! $this->linktype ) {
			$this->elements_form();
			if ( apply_filters( 'spfwc_display_save_payment_method_checkbox', $display_save_payment_method ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
				if ( $this->cardmember ) {
					$this->save_payment_method_checkbox();
				}
			}
			echo '<input type="hidden" name="sonypayment_token_code" id="sonypayment-token-code" value="" />';
			echo '<input type="hidden" name="sonypayment_billing_name" id="sonypayment-billing-name" value="" />';
		} else {
			if ( apply_filters( 'spfwc_display_save_payment_method_checkbox', $display_save_payment_method ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
				if ( $this->cardmember ) {
					$this->save_payment_method_checkbox();
				}
			}
		}
		$this->obtain_consent();
		echo '</div>';
		ob_end_flush();
	}

	/**
	 * Renders the SonyPayment elements form.
	 */
	public function elements_form() {

		$default_fields = array();
		$fields         = array();

		$default_fields['card-number-field'] = '<p class="form-row form-row-wide">
			<label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'woo-sonypayment' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-number" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" />
		</p>';
		$default_fields['card-expmm-field']  = '<p class="form-row form-row-wide">
			<label>' . esc_html__( 'Expiry (MM/YY)', 'woo-sonypayment' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-expmm" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="2" style="width:60px" placeholder="' . esc_attr__( 'MM', 'woo-sonypayment' ) . '" />&nbsp;/&nbsp;
			<input id="' . esc_attr( $this->id ) . '-card-expyy" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="2" style="width:60px" placeholder="' . esc_attr__( 'YY', 'woo-sonypayment' ) . '" />
		</p>';
		if ( $this->seccd ) {
			$default_fields['card-seccd-field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-card-seccd">' . esc_html__( 'Card code', 'woo-sonypayment' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-seccd" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" style="width:80px" />
			</p>';
		}
		$default_fields['card-name-field'] = '<p class="form-row form-row-wide">
			<label for="' . esc_attr( $this->id ) . '-card-name">' . esc_html__( 'Card name', 'woo-sonypayment' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-name" class="input-text" inputmode="text" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="TARO&nbsp;YAMADA" />
		</p>';
		$howtopay = apply_filters( 'spfwc_display_howtopay_select', $this->howtopay );
		if ( '1' !== $howtopay ) {
			$paytype_select_field                  = $this->paytype_select_field();
			$default_fields['card-howtopay-field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-card-howtopay">' . esc_html__( 'The number of installments', 'woo-sonypayment' ) . ' <span class="required">*</span></label>
				<span>' . $paytype_select_field . '</span>
			</p>';
		}
		$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>
		<fieldset id="<?php echo esc_attr( $this->id ); ?>-card-form" class="wc-payment-form" style="background:transparent;">
		<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
		<?php
		if ( is_user_logged_in() && $this->cardmember ) :
			$customer_id = get_current_user_id();
			$member      = new SPFWC_Card_Member( $customer_id );
			if ( $member->is_card_member() ) :
				$response_member = $member->search_card_member();
				$cardlast4       = ( 'OK' === $response_member['ResponseCd'] && isset( $response_member['CardNo'] ) ) ? substr( $response_member['CardNo'], -4 ) : '****';
				?>
			<p class="form-row form-row-wide">
				<input id="<?php echo esc_attr( $this->id ); ?>-card-member-option-saved" name="<?php echo esc_attr( $this->id ); ?>_card_member_option" type="radio" class="input-radio" value="saved" />
				<label for="<?php echo esc_attr( $this->id ); ?>-card-member-option-saved" style="display:inline;"><?php esc_html_e( 'Using the saved credit card.', 'woo-sonypayment' ); ?></label>
			</p>
			<p class="form-row form-row-wide">
				<label for="<?php echo esc_attr( $this->id ); ?>-card-member-cardlast4" style="display:inline;"><?php esc_html_e( 'Last 4 digits of the saved card number: ', 'woo-sonypayment' ); ?></label>
				<span id="<?php echo esc_attr( $this->id ); ?>-card-member-cardlast4"><?php echo esc_html( $cardlast4 ); ?></span>
			</p>
				<?php
				if ( $this->always_save ) :
					?>
			<p class="form-row form-row-wide">
				<input id="<?php echo esc_attr( $this->id ); ?>-card-member-option-change" name="<?php echo esc_attr( $this->id ); ?>_card_member_option" type="radio" class="input-radio" value="change" />
				<label for="<?php echo esc_attr( $this->id ); ?>-card-member-option-change" style="display:inline;"><?php esc_html_e( 'Change the saved credit card and pay.', 'woo-sonypayment' ); ?></label>
			</p>
					<?php
				else :
					?>
			<p class="form-row form-row-wide">
				<input id="<?php echo esc_attr( $this->id ); ?>-card-member-option-unsaved" name="<?php echo esc_attr( $this->id ); ?>_card_member_option" type="radio" class="input-radio" value="unsaved" />
				<label for="<?php echo esc_attr( $this->id ); ?>-card-member-option-unsaved" style="display:inline;"><?php esc_html_e( 'Using a new credit card.', 'woo-sonypayment' ); ?></label>
			</p>
					<?php
				endif;
			endif;
		endif;
		foreach ( $fields as $field ) {
			echo $field;
		}
		?>
			<div class="sonypayment-errors" role="alert"></div>
		<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Displays the save to account checkbox.
	 */
	public function save_payment_method_checkbox() {

		if ( is_user_logged_in() ) {
			$customer_id    = get_current_user_id();
			$member         = new SPFWC_Card_Member( $customer_id );
			$is_card_member = $member->is_card_member();
		} else {
			$is_card_member = false;
		}
		if ( $this->always_save ) {
			if ( $is_card_member ) {
				$save = '';
			} else {
				$save = 'add';
			}
			printf(
				'<input id="%1$s-save-payment-method" name="%1$s_save_payment_method" type="hidden" value="%2$s" />',
				esc_attr( $this->id ),
				esc_attr( $save )
			);
		} else {
			if ( $is_card_member ) {
				$save = 'change';
				$text = __( 'Change the saved card information and pay.', 'woo-sonypayment' );
			} else {
				$save = 'add';
				$text = __( 'Save the card information to your account data. You won\'t need to input the card number from next purchases.', 'woo-sonypayment' );
			}
			printf(
				'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
					<input id="%1$s-save-payment-method" name="%1$s_save_payment_method" type="checkbox" value="%2$s" style="width:auto;" />
					<label for="%1$s-save-payment-method" style="display:inline;">%3$s</label>
				</p>',
				esc_attr( $this->id ),
				esc_attr( $save ),
				esc_html( apply_filters( 'spfwc_save_to_account_text', $text, $save ) )
			);
		}
	}

	/**
	 * Displays the save to account checkbox.
	 */
	public function obtain_consent() {

		if ( $this->three_d_secure || $this->linktype ) {
			echo '<div id="sonypayment-consent-message">
				<textarea class="sonypayment_agreement_message" rows="5" readonly>' . wp_kses_post( spfwc_consent_message() ) . '</textarea>
				<p class="sonypayment-consent-area">
					<input type="checkbox" id="sonypayment_agree" value="agree" /><label for="sonypayment_agree">' . esc_html__( 'I agree to the handling of personal information', 'woo-sonypayment' ) . '</label>
				</p>
			</div>';
		}
	}

	/**
	 * Selection of installments field.
	 *
	 * @param string $paytype Number of payments.
	 */
	public function paytype_select_field( $paytype = '' ) {

		$field  = '<input type="hidden" name="' . esc_attr( $this->id ) . '_card_paytype" value="01" />';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-default" name="' . esc_attr( $this->id ) . '_card_paytype" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : ' ' ) . '>' . esc_html__( 'Lump-sum payment', 'woo-sonypayment' ) . '</option>
			</select>';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-4535" name="' . esc_attr( $this->id ) . '_card_paytype" style="display:none;" disabled="disabled" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Lump-sum payment', 'woo-sonypayment' ) . '</option>
				<option value="02"' . ( ( '02' == $paytype ) ? ' selected="selected"' : '' ) . '>2' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="03"' . ( ( '03' == $paytype ) ? ' selected="selected"' : '' ) . '>3' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="05"' . ( ( '05' == $paytype ) ? ' selected="selected"' : '' ) . '>5' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="06"' . ( ( '06' == $paytype ) ? ' selected="selected"' : '' ) . '>6' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="10"' . ( ( '10' == $paytype ) ? ' selected="selected"' : '' ) . '>10' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="12"' . ( ( '12' == $paytype ) ? ' selected="selected"' : '' ) . '>12' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="15"' . ( ( '15' == $paytype ) ? ' selected="selected"' : '' ) . '>15' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="18"' . ( ( '18' == $paytype ) ? ' selected="selected"' : '' ) . '>18' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="20"' . ( ( '20' == $paytype ) ? ' selected="selected"' : '' ) . '>20' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="24"' . ( ( '24' == $paytype ) ? ' selected="selected"' : '' ) . '>24' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="88"' . ( ( '88' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Revolving payment', 'woo-sonypayment' ) . '</option>';
		if ( 3 === (int) $this->howtopay ) {
			$field .= '
				<option value="80"' . ( ( '80' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Pay for it out of a bonus', 'woo-sonypayment' ) . '</option>';
		}
		$field .= '
			</select>';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-37" name="' . esc_attr( $this->id ) . '_card_paytype" style="display:none;" disabled="disabled" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Lump-sum payment', 'woo-sonypayment' ) . '</option>
				<option value="03"' . ( ( '03' == $paytype ) ? ' selected="selected"' : '' ) . '>3' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="05"' . ( ( '05' == $paytype ) ? ' selected="selected"' : '' ) . '>5' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="06"' . ( ( '06' == $paytype ) ? ' selected="selected"' : '' ) . '>6' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="10"' . ( ( '10' == $paytype ) ? ' selected="selected"' : '' ) . '>10' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="12"' . ( ( '12' == $paytype ) ? ' selected="selected"' : '' ) . '>12' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="15"' . ( ( '15' == $paytype ) ? ' selected="selected"' : '' ) . '>15' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="18"' . ( ( '18' == $paytype ) ? ' selected="selected"' : '' ) . '>18' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="20"' . ( ( '20' == $paytype ) ? ' selected="selected"' : '' ) . '>20' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>
				<option value="24"' . ( ( '24' == $paytype ) ? ' selected="selected"' : '' ) . '>24' . esc_html__( '-installments', 'woo-sonypayment' ) . '</option>';
		if ( 3 === (int) $this->howtopay ) {
			$field .= '
				<option value="80"' . ( ( '80' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Pay for it out of a bonus', 'woo-sonypayment' ) . '</option>';
		}
		$field .= '
			</select>';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-36" name="' . esc_attr( $this->id ) . '_card_paytype" style="display:none;" disabled="disabled" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Lump-sum payment', 'woo-sonypayment' ) . '</option>
				<option value="88"' . ( ( '88' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Revolving payment', 'woo-sonypayment' ) . '</option>';
		if ( 3 === (int) $this->howtopay ) {
			$field .= '
				<option value="80"' . ( ( '80' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Pay for it out of a bonus', 'woo-sonypayment' ) . '</option>';
		}
		$field .= '
			</select>';
		return $field;
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

		if ( $this->linktype ) {
			wp_register_style( 'sonypayment_styles', plugins_url( 'assets/css/spfwc.css', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION );
			wp_enqueue_style( 'sonypayment_styles' );

			wp_register_script( 'sonypayment_script', plugins_url( 'assets/js/spfwc-payment-link.js', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION, true );
			$sonypayment_params             = array();
			$sonypayment_params['linktype'] = $this->linktype;
			$sonypayment_params['message']  = array(
				'error_agree' => __( 'Please check the "I agree to the handling of personal information" checkbox.', 'woo-sonypayment' ),
			);
			wp_localize_script( 'sonypayment_script', 'sonypayment_params', apply_filters( 'sonypayment_params', $sonypayment_params ) );
			wp_enqueue_script( 'sonypayment_script' );
		} else {
			wp_register_style( 'sonypayment_styles', plugins_url( 'assets/css/spfwc.css', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION );
			wp_enqueue_style( 'sonypayment_styles' );

			wp_register_script( 'sonypayment_script', plugins_url( 'assets/js/spfwc-payment.js', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION, true );
			$is_card_member = false;
			if ( is_user_logged_in() && $this->cardmember ) {
				$customer_id    = get_current_user_id();
				$member         = new SPFWC_Card_Member( $customer_id );
				$is_card_member = $member->is_card_member();
			}
			$sonypayment_params                      = array();
			$sonypayment_params['is_user_logged_in'] = is_user_logged_in();
			$sonypayment_params['is_card_member']    = $is_card_member;
			$sonypayment_params['seccd']             = $this->seccd;
			$sonypayment_params['cardmember']        = $this->cardmember;
			$sonypayment_params['howtopay']          = $this->howtopay;
			$sonypayment_params['is_3d_secure']      = $this->three_d_secure;
			$sonypayment_params['linktype']          = $this->linktype;
			$sonypayment_params['return_url']        = $this->get_return_url();
			$sonypayment_params['message']           = array(
				'error_card_member_option' => __( 'Select the card to use for payment.', 'woo-sonypayment' ),
				'error_card_number'        => __( 'The card number is not a valid credit card number.', 'woo-sonypayment' ),
				'error_card_expmm'         => __( 'The card\'s expiration month is invalid.', 'woo-sonypayment' ),
				'error_card_expyy'         => __( 'The card\'s expiration year is invalid.', 'woo-sonypayment' ),
				'error_card_seccd'         => __( 'The card\'s security code is invalid.', 'woo-sonypayment' ),
				'error_card'               => __( 'Your credit card information is incorrect.', 'woo-sonypayment' ),
				'error_agree'              => __( 'Please check the "I agree to the handling of personal information" checkbox.', 'woo-sonypayment' ),
			);
			wp_localize_script( 'sonypayment_script', 'sonypayment_params', apply_filters( 'sonypayment_params', $sonypayment_params ) );
			wp_enqueue_script( 'sonypayment_script' );

			if ( ! empty( $this->token_code ) ) {
				$api_token = SPFWC_SLN_Connection::api_token_url();
				?>
				<script type="text/javascript"
				src="<?php echo esc_attr( $api_token ); ?>?k_TokenNinsyoCode=<?php echo esc_attr( $this->token_code ); ?>" callBackFunc="setToken" class="spsvToken">
				</script>
				<?php
			}
		}
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

		if ( $this->linktype ) {

			$order        = wc_get_order( $order_id );
			$redirect_url = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_sonypayment' ) );

			$param_list                    = array();
			$param_list['MerchantPass']    = $this->merchant_pass;
			$param_list['TransactionDate'] = spfwc_get_transaction_date();
			$param_list['MerchantFree1']   = spfwc_get_transaction_code();
			$param_list['MerchantFree2']   = $order_id;
			$param_list['TenantId']        = $this->tenant_id;

			$card_member = ( isset( $_POST['sonypayment_save_payment_method'] ) ) ? wp_unslash( $_POST['sonypayment_save_payment_method'] ) : '';
			if ( is_user_logged_in() && $this->cardmember && '' !== $card_member ) {
				$customer_id = get_current_user_id();
				$member      = new SPFWC_Card_Member( $customer_id );
				if ( 'add' === $card_member ) {
					$param_list['OperateId']     = '4MemAdd';
					$param_list['MerchantFree3'] = $customer_id;
					$param_list['KaiinId']       = $member->make_member_id( $customer_id );
					$param_list['KaiinPass']     = $member->make_member_pass();
				} elseif ( 'change' === $card_member ) {
					$param_list['OperateId']     = '4MemChg';
					$param_list['MerchantFree3'] = $customer_id;
					$param_list['KaiinId']       = $member->get_member_id();
					$param_list['KaiinPass']     = $member->get_member_pass();
				}
				$param_list['ProcNo']      = '0000000';
				$param_list['RedirectUrl'] = $redirect_url;
				$encrypt_value             = SPFWC_SLN_Connection::get_encrypt_value( $param_list );
				$redirect_url              = add_query_arg(
					array(
						'MerchantId'   => $this->merchant_id,
						'EncryptValue' => urlencode( $encrypt_value ),
					),
					SPFWC_SLN_Connection::send_url_link()
				);

				return array(
					'result'   => 'success',
					'redirect' => $redirect_url,
				);

			} else {
				if ( is_user_logged_in() && $this->cardmember ) {
					$customer_id                 = get_current_user_id();
					$member                      = new SPFWC_Card_Member( $customer_id );
					$param_list['MerchantFree3'] = $customer_id;
					$param_list['KaiinId']       = $member->get_member_id();
					$param_list['KaiinPass']     = $member->get_member_pass();
				}
				$param_list['OperateId']   = $this->operate_id;
				$param_list['PayType']     = '01';
				$param_list['Amount']      = $order->get_total();
				$param_list['ProcNo']      = '0000000';
				$param_list['RedirectUrl'] = $redirect_url;
				$encrypt_value             = SPFWC_SLN_Connection::get_encrypt_value( $param_list );
				$redirect_url              = add_query_arg(
					array(
						'MerchantId'   => $this->merchant_id,
						'EncryptValue' => urlencode( $encrypt_value ),
					),
					SPFWC_SLN_Connection::send_url_link()
				);

				return array(
					'result'   => 'success',
					'redirect' => $redirect_url,
				);
			}
		} else {

			$order = wc_get_order( $order_id );

			try {
				$sln                           = new SPFWC_SLN_Connection();
				$params                        = array();
				$param_list                    = array();
				$param_list['MerchantId']      = $this->merchant_id;
				$param_list['MerchantPass']    = $this->merchant_pass;
				$param_list['TransactionDate'] = spfwc_get_transaction_date();
				$param_list['MerchantFree1']   = spfwc_get_transaction_code();
				$param_list['MerchantFree2']   = $order_id;
				$param_list['TenantId']        = $this->tenant_id;
				$param_list['Amount']          = $order->get_total();

				$token = ( isset( $_POST['sonypayment_token_code'] ) ) ? trim( wp_unslash( $_POST['sonypayment_token_code'] ) ) : '';

				if ( $this->three_d_secure && isset( $_POST['done3d'] ) ) {
					$param_list['Token'] = $token;
					if ( isset( $_POST['EncodeXId3D'] ) ) {
						$param_list['EncodeXId3D'] = wp_unslash( $_POST['EncodeXId3D'] );
					}
					if ( isset( $_POST['MessageVersionNo3D'] ) ) {
						$param_list['MessageVersionNo3D'] = wp_unslash( $_POST['MessageVersionNo3D'] );
					}
					if ( isset( $_POST['TransactionStatus3D'] ) ) {
						$param_list['TransactionStatus3D'] = wp_unslash( $_POST['TransactionStatus3D'] );
					}
					if ( isset( $_POST['CAVVAlgorithm3D'] ) ) {
						$param_list['CAVVAlgorithm3D'] = wp_unslash( $_POST['CAVVAlgorithm3D'] );
					}
					if ( isset( $_POST['ECI3D'] ) ) {
						$param_list['ECI3D'] = wp_unslash( $_POST['ECI3D'] );
					}
					if ( isset( $_POST['CAVV3D'] ) ) {
						$param_list['CAVV3D'] = wp_unslash( $_POST['CAVV3D'] );
					}
					if ( isset( $_POST['SecureResultCode'] ) ) {
						$param_list['SecureResultCode'] = wp_unslash( $_POST['SecureResultCode'] );
					}
					if ( isset( $_POST['DSTransactionId'] ) ) {
						$param_list['DSTransactionId'] = wp_unslash( $_POST['DSTransactionId'] );
					}
					if ( isset( $_POST['ThreeDSServerTransactionId'] ) ) {
						$param_list['ThreeDSServerTransactionId'] = wp_unslash( $_POST['ThreeDSServerTransactionId'] );
					}
				} else {
					if ( ! empty( $token ) ) {
						// Refer to token status.
						$param_list['Token']     = $token;
						$param_list['OperateId'] = '1TokenSearch';
						$params['param_list']    = $param_list;
						$params['send_url']      = $sln->send_url_token();
						$response_token          = $sln->connection( $params );
						if ( 'OK' !== $response_token['ResponseCd'] || 'OK' !== $response_token['TokenResponseCd'] ) {
							if ( isset( $response_token['TokenResponseCd'] ) ) {
								$responsecd = explode( '|', $response_token['ResponseCd'] . '|' . $response_token['TokenResponseCd'] );
							} else {
								$responsecd = explode( '|', $response_token['ResponseCd'] );
							}
							foreach ( (array) $responsecd as $cd ) {
								if ( 'OK' !== $cd ) {
									$response_token[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
								}
							}
							$localized_message = __( 'Payment processing failed. Please retry.', 'woo-sonypayment' );
							throw new SPFWC_Exception( print_r( $response_token, true ), $localized_message );
						}
					}

					// Redirect to 3D Secure Authentication page.
					if ( $this->three_d_secure && $order->get_total() > 0 ) {
						$is_block  = ( isset( $_POST['is_block'] ) && (boolean) $_POST['is_block'] );
						$post_data = wp_unslash( $_POST );
						if ( $is_block ) {// ブロックの決済処理.
							$post_data['order_id']                         = $order_id;
							$post_data['is_block']                         = true;
							$post_data['woocommerce_checkout_place_order'] = '';
							$post_data['sonypayment_token_code']           = $token;
							$paytype                                       = ( isset( $_POST['sonypayment_card_paytype'] ) ) ? wp_unslash( $_POST['sonypayment_card_paytype'] ) : '01';
							$post_data['sonypayment_card_paytype']         = $paytype;
							if ( isset( $_POST['sonypayment_card_member_option'] ) ) {
								$post_data['sonypayment_card_member_option'] = wp_unslash( $_POST['sonypayment_card_member_option'] );
							}
							if ( isset( $_POST['sonypayment_save_payment_method'] ) ) {
								$post_data['sonypayment_save_payment_method'] = wp_unslash( $_POST['sonypayment_save_payment_method'] );
							}
							$post_data['_wpnonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
						}
						// if ( ! WC()->session->has_session() ) {
						// WC()->session->set_customer_session_cookie( true );
						// }
						// WC()->session->set( 'post3d', $post_data ); // Set the POST data in SESSION.
						SPFWC_Payment_Logger::add_post_log( $post_data, $param_list['MerchantFree1'] );
						if ( is_user_logged_in() && $this->cardmember ) {
							$customer_id = get_current_user_id();
							$member      = new SPFWC_Card_Member( $customer_id );
							// Card member pay with saved card.
							if ( isset( $_POST['sonypayment_card_member_option'] ) && 'saved' === wp_unslash( $_POST['sonypayment_card_member_option'] ) ) {
								// Search of card member.
								$response_member = $member->search_card_member( $param_list );
								if ( 'OK' !== $response_member['ResponseCd'] ) {
									$responsecd = explode( '|', $response_member['ResponseCd'] );
									foreach ( (array) $responsecd as $cd ) {
										$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
									}
									$localized_message = __( 'Card member does not found.', 'woo-sonypayment' );
									throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
								}
								$param_list['MerchantFree3'] = $customer_id;
								$param_list['KaiinId']       = $response_member['KaiinId'];
								$param_list['KaiinPass']     = $response_member['KaiinPass'];
								unset( $param_list['Token'] );
							}
						}
						$param_list['OperateId']       = '3Secure';
						$param_list['ProcNo']          = spfwc_get_transaction_code( 7 );
						$redirect_url                  = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_sonypayment' ) );
						$param_list['RedirectUrl']     = esc_url( $redirect_url );
						$param_list['BillingFullName'] = ( isset( $post_data['sonypayment_billing_name'] ) ) ? trim( $post_data['sonypayment_billing_name'] ) : '';
						$param_list['BillingEmail']    = ( isset( $post_data['billing_email'] ) ) ? trim( $post_data['billing_email'] ) : $order->get_billing_email();

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
						<input type="hidden" name="MerchantId" value="<?php echo esc_attr( $this->merchant_id ); ?>" />
						<input type="hidden" name="EncryptValue" value="<?php echo esc_attr( $encrypt_value ); ?>" />
						</form>
						</body>
						</html>
						<?php
						$redirect_form = ob_get_contents();
						ob_end_clean();

						if ( $is_block ) { // ブロックの決済処理.
							$key = uniqid( mt_rand() );
							$order->update_meta_data( 'wc_sonypayment_block_process_payment_transfer_' . $key, $redirect_form );
							$order->save();

							return array(
								'result'   => 'success',
								'redirect' => str_replace( 'http://', 'https://', add_query_arg( array(
									'wc-api'   => 'wc_sonypayment_transfer',
									'order_id' => $order_id,
									'key'      => $key,
								), home_url( '/' ) ) )
							,
							);
						}
						echo $redirect_form;
						exit;
					}
				}

				// Card member proccess.
				if ( is_user_logged_in() && $this->cardmember ) {
					$customer_id = get_current_user_id();
					$member      = new SPFWC_Card_Member( $customer_id );

					// Card member pay with saved card.
					if ( isset( $_POST['sonypayment_card_member_option'] ) && 'saved' === wp_unslash( $_POST['sonypayment_card_member_option'] ) ) {
						if ( ! isset( $param_list['KaiinId'] ) && ! isset( $param_list['KaiinPass'] ) ) {
							// Search of card member.
							$response_member = $member->search_card_member( $param_list );
							if ( 'OK' !== $response_member['ResponseCd'] ) {
								$responsecd = explode( '|', $response_member['ResponseCd'] );
								foreach ( (array) $responsecd as $cd ) {
									$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
								}
								$localized_message = __( 'Card member does not found.', 'woo-sonypayment' );
								throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
							}
							$param_list['MerchantFree3'] = $customer_id;
							$param_list['KaiinId']       = $response_member['KaiinId'];
							$param_list['KaiinPass']     = $response_member['KaiinPass'];
							unset( $param_list['Token'] );
						}
					} else {
						$card_member = ( isset( $_POST['sonypayment_save_payment_method'] ) ) ? wp_unslash( $_POST['sonypayment_save_payment_method'] ) : '';
						$card_member = apply_filters( 'spfwc_save_cardmember', $card_member, $order, $customer_id );
						if ( 'add' === $card_member ) {
							// Register of card member.
							$response_member = $member->create_card_member( $param_list );
							if ( 'OK' !== $response_member['ResponseCd'] ) {
								$responsecd = explode( '|', $response_member['ResponseCd'] );
								foreach ( (array) $responsecd as $cd ) {
									$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
								}
								$localized_message = __( 'Failed saving card member.', 'woo-sonypayment' );
								throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
							}
							$param_list['MerchantFree3'] = $customer_id;
							$param_list['KaiinId']       = $response_member['KaiinId'];
							$param_list['KaiinPass']     = $response_member['KaiinPass'];
							if ( true === (bool) $response_member['use_token'] ) {
								unset( $param_list['Token'] );
							}
						} elseif ( 'change' === $card_member ) {
							// Search of card member.
							$response_member = $member->search_card_member( $param_list );
							if ( 'OK' === $response_member['ResponseCd'] ) {
								// Update of card member.
								$response_member = $member->update_card_member( $param_list );
								if ( 'OK' !== $response_member['ResponseCd'] ) {
									$responsecd = explode( '|', $response_member['ResponseCd'] );
									foreach ( (array) $responsecd as $cd ) {
										$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
									}
									$localized_message = __( 'Failed updating card number.', 'woo-sonypayment' );
									throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
								}
								$param_list['MerchantFree3'] = $customer_id;
								$param_list['KaiinId']       = $member->get_member_id();
								$param_list['KaiinPass']     = $member->get_member_pass();
								if ( true === (bool) $response_member['use_token'] ) {
									unset( $param_list['Token'] );
								}
							} else {
								$responsecd = explode( '|', $response_member['ResponseCd'] );
								foreach ( (array) $responsecd as $cd ) {
									$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
								}
								$localized_message = __( 'Card member does not found.', 'woo-sonypayment' );
								throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
							}
						}
					}
				}

				if ( $order->get_total() > 0 ) {
					$paytype    = ( isset( $_POST['sonypayment_card_paytype'] ) ) ? wp_unslash( $_POST['sonypayment_card_paytype'] ) : '01';
					$operate_id = apply_filters( 'spfwc_card_operate_id', $this->operate_id, $order );
					// Settlement proccess.
					$param_list['PayType']   = $paytype;
					$param_list['OperateId'] = $operate_id;
					unset( $params['param_list'] );
					$params['param_list'] = $param_list;
					$params['send_url']   = $sln->send_url();
					$response_data        = $sln->connection( $params );
					if ( 'OK' !== $response_data['ResponseCd'] ) {
						$responsecd = explode( '|', $response_data['ResponseCd'] );
						foreach ( (array) $responsecd as $cd ) {
							$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
						}
						$localized_message = __( 'Payment processing failed. Please retry.', 'woo-sonypayment' );
						throw new SPFWC_Exception( print_r( $response_data, true ), $localized_message );
					}
					if ( isset( $_POST['SecureResultCode'] ) ) {
						$response_data['SecureResultCode'] = wp_unslash( $_POST['SecureResultCode'] );
						$response_data['Agreement']        = '1';
					}
					do_action( 'spfwc_process_payment', $response_data, $order );
					$this->process_response( $response_data, $order );

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
				SPFWC_Logger::add_log( 'Payment Error: ' . $e->getMessage() );
				wc_add_notice( $e->getLocalizedMessage(), 'error' );

				do_action( 'spfwc_process_payment_error', $e, $order );

				$order->update_status( 'failed' );

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		}
	}

	/**
	 * Store extra meta data for an order.
	 *
	 * @param array    $response_data SLN response data.
	 * @param WC_Order $order Order object.
	 */
	public function process_response( $response_data, $order ) {

		if ( 'OK' === $response_data['ResponseCd'] && ! empty( $response_data['MerchantFree1'] ) ) {
			$order_id   = $order->get_id();
			$trans_code = $response_data['MerchantFree1'];
			$order->update_meta_data( '_spfwc_trans_code', $trans_code );
			$order->save();

			SPFWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );

			$order->payment_complete( $trans_code );

			$message = __( 'Paymeint is completed.', 'woo-sonypayment' );
			$order->add_order_note( $message );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		do_action( 'spfwc_process_response', $response_data, $order );
	}

	/**
	 * Set order status.
	 *
	 * @param string   $order_status Order status.
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function payment_complete_order_status( $order_status, $order_id, $order ) {

		if ( 'completed' === $this->order_status ) {
			if ( 'subscription' === $order->get_created_via() ) {
				$settings = get_option( 'woocommerce_sonypayment_settings', array() );
				if ( ! empty( $settings['subscription_operate_id'] ) ) {
					$operate_id = apply_filters( 'spfwc_scheduled_subscription_card_operate_id', $settings['subscription_operate_id'], $order );
				} else {
					$operate_id = '1Gathering';
				}
			} else {
				$operate_id = apply_filters( 'spfwc_card_operate_id', $this->operate_id, $order );
			}
			if ( '1Gathering' === $operate_id ) {
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
				if ( $only_virtual ) {
					$order_status = 'completed';
				}
			}
		}
		return $order_status;
	}
}
