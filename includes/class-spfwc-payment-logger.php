<?php
/**
 * SPFWC_Payment_Logger class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Payment_Logger class.
 *
 * @since 1.0.0
 */
class SPFWC_Payment_Logger {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_delete_order_item', array( $this, 'clear_log' ) );
		add_action( 'woocommerce_deleted_order_items', array( $this, 'clear_log' ) );
	}

	/**
	 * Add log data.
	 *
	 * @param array  $response Log message.
	 * @param int    $order_id Order ID.
	 * @param string $trans_code Transaction code.
	 * @param string $timestamp Timestamp.
	 */
	public static function add_log( $response, $order_id, $trans_code, $timestamp = '' ) {
		global $wpdb;

		if ( ! isset( $response['OperateId'] ) ) {
			return;
		}

		if ( empty( $timestamp ) ) {
			$timestamp = current_time( 'mysql' );
		}

		$payment_type = '';
		if ( in_array( $response['OperateId'], array( '1Check', '1Auth', '1Capture', '1Gathering', '1Change', '1Delete', '1Search', '1ReAuth' ) ) ) {
			$payment_type = 'card';
		} elseif ( in_array( $response['OperateId'], array( '2Add', '2Chg', '2Del', 'paid', 'expired' ) ) ) {
			$payment_type = 'cvs';
		}

		$query = $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}spfwc_log ( `timestamp`, `trans_code`, `operate_id`, `response`, `order_id`, `payment_type` ) VALUES ( %s, %s, %s, %s, %d, %s )",
			$timestamp,
			$trans_code,
			$response['OperateId'],
			json_encode( $response ),
			$order_id,
			$payment_type
		);
		$res   = $wpdb->query( $query );
	}

	/**
	 * Get log data.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @return array
	 */
	public static function get_log( $order_id, $trans_code ) {
		global $wpdb;

		$query    = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}spfwc_log WHERE `order_id` = %d AND `trans_code` = %s ORDER BY `timestamp` DESC",
			$order_id,
			$trans_code
		);
		$log_data = $wpdb->get_results( $query, ARRAY_A );
		return $log_data;
	}

	/**
	 * Get the latest log data.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @param  bool   $all All|Normal data only.
	 * @return array
	 */
	public static function get_latest_log( $order_id, $trans_code, $all = false ) {
		global $wpdb;

		$latest_log = array();
		$log_data   = self::get_log( $order_id, $trans_code );
		if ( $log_data ) {
			if ( $all ) {
				$latest_log = $log_data[0];
			} else {
				$latest_status    = array( '1Auth', '1Capture', '1Gathering', '1Delete', '2Add', '2Chg', '2Del', '5Auth', '5Gathering', '5Capture', '5Delete', 'paid' );
				$primarily_status = array( '1Auth', '1Gathering', '2Add', '5Auth', '5Gathering', '5Capture', 'paid' );
				$reauth_status    = array( '1ReAuth' );
				$reauth           = false;
				foreach ( (array) $log_data as $data ) {
					$response = json_decode( $data['response'], true );
					if ( isset( $response['ResponseCd'] ) ) {
						if ( 'OK' === $response['ResponseCd'] && in_array( $response['OperateId'], $reauth_status ) ) {
							$reauth = true;
						} else {
							if ( $reauth ) {
								if ( 'OK' === $response['ResponseCd'] && in_array( $response['OperateId'], $primarily_status ) ) {
									$latest_log = $data;
									break;
								}
							} else {
								if ( 'OK' === $response['ResponseCd'] && in_array( $response['OperateId'], $latest_status ) ) {
									$latest_log = $data;
									break;
								}
							}
						}
					}
				}
			}
		}
		return $latest_log;
	}

	/**
	 * The first response data.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @return array
	 */
	public function get_first_log( $order_id, $trans_code ) {
		global $wpdb;

		$query     = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}spfwc_log WHERE `order_id` = %d AND `trans_code` = %s ORDER BY `timestamp` ASC LIMIT 1",
			$order_id,
			$trans_code
		);
		$first_log = $wpdb->get_row( $query, ARRAY_A );
		return $first_log;
	}

	/**
	 * The first operation.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $trans_code Transaction code.
	 * @return string
	 */
	public static function get_first_operation( $order_id, $trans_code ) {
		global $wpdb;

		$query    = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}spfwc_log WHERE `order_id` = %d AND `trans_code` = %s ORDER BY `timestamp` ASC LIMIT 1",
			$order_id,
			$trans_code
		);
		$log_data = $wpdb->get_row( $query, ARRAY_A );
		if ( $log_data ) {
			$response  = json_decode( $log_data['response'], true );
			$operateid = ( isset( $response['OperateId'] ) ) ? $response['OperateId'] : '';
		} else {
			$operateid = '';
		}
		return $operateid;
	}

	/**
	 * Clear log data.
	 *
	 * @param int $order_id Order ID.
	 */
	public function clear_log( $order_id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}spfwc_log WHERE `order_id` = %d", $order_id ) );
	}

	/**
	 * Add post log data.
	 *
	 * @param array  $log Log data.
	 * @param string $trans_code Transaction code.
	 * @since 1.1.0
	 */
	public static function add_post_log( $log, $trans_code ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}spfwc_log ( `timestamp`, `trans_code`, `operate_id`, `response`, `log_type` ) VALUES ( %s, %s, %s, %s, %s )",
			current_time( 'mysql' ),
			$trans_code,
			'post',
			json_encode( $log ),
			'post'
		);
		$res   = $wpdb->query( $query );
	}

	/**
	 * Get post log data.
	 *
	 * @param  string $trans_code Transaction code.
	 * @return array
	 * @since 1.1.0
	 */
	public static function get_post_log( $trans_code ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT `response` FROM {$wpdb->prefix}spfwc_log WHERE `trans_code` = %s AND `log_type` = %s",
			$trans_code,
			'post'
		);
		$log   = $wpdb->get_var( $query );
		return json_decode( $log, true );
	}

	/**
	 * Clear post log data.
	 *
	 * @param string $trans_code Transaction code.
	 * @since 1.1.0
	 */
	public static function clear_post_log( $trans_code ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}spfwc_log WHERE `trans_code` = %s AND `log_type` = %s", $trans_code, 'post' ) );
	}

	/**
	 * Add notice log data.
	 *
	 * @param array  $log Log data.
	 * @param string $trans_code Transaction code.
	 * @since 1.2.0
	 */
	public static function add_notice_log( $log, $trans_code ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}spfwc_log ( `timestamp`, `trans_code`, `operate_id`, `response`, `log_type` ) VALUES ( %s, %s, %s, %s, %s )",
			current_time( 'mysql' ),
			$trans_code,
			'notice',
			json_encode( $log ),
			'notice'
		);
		$res   = $wpdb->query( $query );
	}

	/**
	 * Get notice log data.
	 *
	 * @param  string $trans_code Transaction code.
	 * @return array
	 * @since 1.2.0
	 */
	public static function get_notice_log( $trans_code ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT `response` FROM {$wpdb->prefix}spfwc_log WHERE `trans_code` = %s AND `log_type` = %s",
			$trans_code,
			'notice'
		);
		$log   = $wpdb->get_var( $query );
		return json_decode( $log, true );
	}

	/**
	 * Clear notice log data.
	 *
	 * @param string $trans_code Transaction code.
	 * @since 1.2.0
	 */
	public static function clear_notice_log( $trans_code ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}spfwc_log WHERE `trans_code` = %s AND `log_type` = %s", $trans_code, 'notice' ) );
	}
}

new SPFWC_Payment_Logger();
