<?php
/**
 * SPFWC_Card_Member class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Card_Member class.
 *
 * @since 1.0.0
 */
class SPFWC_Card_Member {

	/**
	 * SonyPayment Member ID
	 *
	 * @var string
	 */
	private $member_id = '';

	/**
	 * SonyPayment Member password
	 *
	 * @var string
	 */
	private $member_pass = '';

	/**
	 * WP User ID
	 *
	 * @var integer
	 */
	private $customer_id = 0;

	/**
	 * Constructor.
	 *
	 * @param int $customer_id The WP user ID.
	 */
	public function __construct( $customer_id = 0 ) {
		if ( 0 < $customer_id ) {
			self::set_customer_id( $customer_id );
		}
	}

	/**
	 * Member ID in SonyPayment.
	 *
	 * @return string
	 */
	public function get_member_id() {
		$this->member_id = get_user_meta( $this->get_customer_id(), '_spfwc_member_id', true );
		return $this->member_id;
	}

	/**
	 * Member password in SonyPayment.
	 *
	 * @return string
	 */
	public function get_member_pass() {
		$this->member_pass = get_user_meta( $this->get_customer_id(), '_spfwc_member_pass', true );
		return $this->member_pass;
	}

	/**
	 * User ID in WordPress.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return absint( $this->customer_id );
	}

	/**
	 * Set Member ID used by SonyPayment.
	 *
	 * @param string $member_id Member ID.
	 */
	public function set_member_id( $member_id ) {
		$this->member_id = $member_id;
		update_user_meta( $this->get_customer_id(), '_spfwc_member_id', $this->member_id );
	}

	/**
	 * Set Member password used by SonyPayment.
	 *
	 * @param string $member_pass Member password.
	 */
	public function set_member_pass( $member_pass ) {
		$this->member_pass = $member_pass;
		update_user_meta( $this->get_customer_id(), '_spfwc_member_pass', $this->member_pass );
	}

	/**
	 * Set User ID used by WordPress.
	 *
	 * @param int $customer_id The WP user ID.
	 */
	public function set_customer_id( $customer_id ) {
		$this->customer_id = absint( $customer_id );
	}

	/**
	 * Is member of SonyPayment?
	 *
	 * @return bool
	 */
	public function is_card_member() {
		$member_id   = $this->get_member_id();
		$member_pass = $this->get_member_pass();
		return ( ! empty( $member_id ) && ! empty( $member_pass ) ) ? true : false;
	}

	/**
	 * Setting required parameters.
	 *
	 * @return array
	 */
	protected function set_palam_list() {
		$settings                      = get_option( 'woocommerce_sonypayment_settings' );
		$param_list                    = array();
		$param_list['MerchantId']      = $settings['merchant_id'];
		$param_list['MerchantPass']    = $settings['merchant_pass'];
		$param_list['TenantId']        = $settings['tenant_id'];
		$param_list['TransactionDate'] = spfwc_get_transaction_date();
		$param_list['MerchantFree1']   = spfwc_get_transaction_code();
		$param_list['MerchantFree3']   = $this->get_customer_id();
		return $param_list;
	}

	/**
	 * Search of members for SonyPayment.
	 *
	 * @param  array $param_list Parameters.
	 * @return array
	 */
	public function search_card_member( $param_list = array() ) {
		if ( empty( $param_list ) ) {
			$param_list = $this->set_palam_list();
		}
		$sln                  = new SPFWC_SLN_Connection();
		$params               = array();
		$params['send_url']   = $sln->send_url_member();
		$params['param_list'] = array_merge(
			$param_list,
			array(
				'OperateId' => '4MemRefM',
				'KaiinId'   => $this->get_member_id(),
				'KaiinPass' => $this->get_member_pass(),
			)
		);
		$response             = $sln->connection( $params );
		if ( 'OK' === $response['ResponseCd'] ) {
			$response['KaiinId']   = $params['param_list']['KaiinId'];
			$response['KaiinPass'] = $params['param_list']['KaiinPass'];
		}
		do_action( 'spfwc_search_card_member', $param_list, $response );
		return $response;
	}

	/**
	 * Creation of members for SonyPayment.
	 *
	 * @param  array $param_list Parameters.
	 * @return array
	 */
	public function create_card_member( $param_list = array() ) {
		if ( empty( $param_list ) ) {
			$param_list = $this->set_palam_list();
		}
		$member_id            = $this->make_member_id( $this->get_customer_id() );
		$member_pass          = $this->make_member_pass();
		$sln                  = new SPFWC_SLN_Connection();
		$params               = array();
		$params['send_url']   = $sln->send_url_member();
		$params['param_list'] = array_merge(
			$param_list,
			array(
				'OperateId' => '4MemAdd',
				'KaiinId'   => $member_id,
				'KaiinPass' => $member_pass,
			)
		);
		$response             = $sln->connection( $params );
		if ( 'OK' === $response['ResponseCd'] ) {
			$this->set_member_id( $member_id );
			$this->set_member_pass( $member_pass );
			$response['KaiinId']   = $member_id;
			$response['KaiinPass'] = $member_pass;
			$response['use_token'] = true;
		}
		do_action( 'spfwc_create_card_member', $param_list, $response );
		return $response;
	}

	/**
	 * Update of members for SonyPayment.
	 *
	 * @param  array $param_list Parameters.
	 * @return array
	 */
	public function update_card_member( $param_list = array() ) {
		if ( empty( $param_list ) ) {
			$param_list = $this->set_palam_list();
		}
		$sln                  = new SPFWC_SLN_Connection();
		$params               = array();
		$params['send_url']   = $sln->send_url_member();
		$params['param_list'] = array_merge(
			$param_list,
			array(
				'OperateId' => '4MemChg',
				'KaiinId'   => $this->get_member_id(),
				'KaiinPass' => $this->get_member_pass(),
			)
		);
		$response             = $sln->connection( $params );
		if ( 'OK' === $response['ResponseCd'] ) {
			$response['use_token'] = true;
		}
		do_action( 'spfwc_update_card_member', $param_list, $response );
		return $response;
	}

	/**
	 * Delete of members for SonyPayment.
	 *
	 * @param  array $param_list Parameters.
	 * @return array
	 */
	public function delete_card_member( $param_list = array() ) {
		if ( empty( $param_list ) ) {
			$param_list = $this->set_palam_list();
		}
		$sln                               = new SPFWC_SLN_Connection();
		$params                            = array();
		$params['param_list']              = $param_list;
		$params['param_list']['OperateId'] = '4MemInval';
		$params['param_list']['KaiinId']   = $this->get_member_id();
		$params['param_list']['KaiinPass'] = $this->get_member_pass();
		$params['send_url']                = $sln->send_url_member();
		// Member invalidation.
		$response = $sln->connection( $params );
		if ( 'OK' === $response['ResponseCd'] ) {
			$params['param_list']['OperateId'] = '4MemDel';
			// Member delete.
			$response = $sln->connection( $params );
			if ( 'OK' === $response['ResponseCd'] ) {
				delete_user_meta( $this->get_customer_id(), '_spfwc_member_id' );
				delete_user_meta( $this->get_customer_id(), '_spfwc_member_pass' );
			}
		}
		do_action( 'spfwc_delete_card_member', $param_list, $response );
		return $response;
	}

	/**
	 * Make Member ID.
	 *
	 * @param  int $customer_id The WP user ID.
	 * @param  int $digits Generated digits.
	 * @return string
	 */
	public function make_member_id( $customer_id, $digits = 7 ) {
		$num = str_repeat( '9', $digits );
		$id  = sprintf( '%0' . $digits . 'd', wp_rand( 1, (int) $num ) );
		return 'wc' . $customer_id . 'i' . $id;
	}

	/**
	 * Make Member password.
	 *
	 * @return string
	 */
	public function make_member_pass() {
		$pass = sprintf( '%012d', wp_rand() );
		return $pass;
	}
}
