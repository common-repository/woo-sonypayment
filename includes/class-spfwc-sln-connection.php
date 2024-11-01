<?php
/**
 * SPFWC_SLN_Connection class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_SLN_Connection class.
 * Connection with SLN.
 *
 * @since 1.0.0
 */
class SPFWC_SLN_Connection {

	const SEND_URL          = 'https://www.e-scott.jp/online/aut/OAUT002.do';
	const SEND_URL_MEMBER   = 'https://www.e-scott.jp/online/crp/OCRP005.do';
	const API_TOKEN_URL     = 'https://www.e-scott.jp/euser/stn/CdGetJavaScript.do';
	const SEND_URL_TOKEN    = 'https://www.e-scott.jp/online/atn/OATN005.do';
	const SEND_URL_3DSECURE = 'https://www.e-scott.jp/online/tds/OTDS010.do';
	const SEND_URL_LINK     = 'https://www.e-scott.jp/euser/snp/SSNP005ReferStart.do';
	const SEND_URL_CVS      = 'https://www.e-scott.jp/online/cnv/OCNV005.do';
	const REDIRECT_URL_CVS  = 'https://link.kessai.info/JLP/JLPcon';

	const TEST_SEND_URL          = 'https://www.test.e-scott.jp/online/aut/OAUT002.do';
	const TEST_SEND_URL_MEMBER   = 'https://www.test.e-scott.jp/online/crp/OCRP005.do';
	const TEST_API_TOKEN_URL     = 'https://www.test.e-scott.jp/euser/stn/CdGetJavaScript.do';
	const TEST_SEND_URL_TOKEN    = 'https://www.test.e-scott.jp/online/atn/OATN005.do';
	const TEST_SEND_URL_3DSECURE = 'https://www.test.e-scott.jp/online/tds/OTDS010.do';
	const TEST_SEND_URL_LINK     = 'https://www.test.e-scott.jp/euser/snp/SSNP005ReferStart.do';
	const TEST_SEND_URL_CVS      = 'https://www.test.e-scott.jp/online/cnv/OCNV005.do';
	const TEST_REDIRECT_URL_CVS  = 'https://link.kessai.info/JLPCT/JLPcon';

	const CIPHER_METHOD = 'aes-128-cbc';
	const KEY_AES       = '7Nvpiw5gdB5Z73Pe';
	const KEY_IV        = '4SMIwMoxm7VVhTEC';

	/**
	 * Test mode.
	 *
	 * @var boolean
	 */
	private $testmode;

	/**
	 * Connection url.
	 *
	 * @var string
	 */
	private $connection_url;

	/**
	 * Connection timeout value.
	 *
	 * @var int
	 */
	private $connection_timeout;

	/**
	 * Construct.
	 */
	public function __construct() {
		$settings                 = get_option( 'woocommerce_sonypayment_settings', array() );
		$this->testmode           = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? true : false;
		$this->connection_url     = '';
		$this->connection_timeout = 60;
	}

	/**
	 * Connection URL.
	 *
	 * @return string
	 */
	public function send_url() {
		$url = ( $this->testmode ) ? self::TEST_SEND_URL : self::SEND_URL;
		return $url;
	}

	/**
	 * Connection membership URL.
	 *
	 * @return string
	 */
	public function send_url_member() {
		$url = ( $this->testmode ) ? self::TEST_SEND_URL_MEMBER : self::SEND_URL_MEMBER;
		return $url;
	}

	/**
	 * Connection API URL.
	 *
	 * @return string
	 */
	public static function api_token_url() {
		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		$url      = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? self::TEST_API_TOKEN_URL : self::API_TOKEN_URL;
		return $url;
	}

	/**
	 * Connection Token URL.
	 *
	 * @return string
	 */
	public function send_url_token() {
		$url = ( $this->testmode ) ? self::TEST_SEND_URL_TOKEN : self::SEND_URL_TOKEN;
		return $url;
	}

	/**
	 * Connection 3D Secure URL.
	 *
	 * @return string
	 */
	public static function send_url_3dsecure() {
		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		$url      = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? self::TEST_SEND_URL_3DSECURE : self::SEND_URL_3DSECURE;
		return $url;
	}

	/**
	 * Connection Linktype URL.
	 *
	 * @return string
	 */
	public static function send_url_link() {
		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		$url      = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? self::TEST_SEND_URL_LINK : self::SEND_URL_LINK;
		return $url;
	}

	/**
	 * Connection CVS URL.
	 *
	 * @return string
	 */
	public function send_url_cvs() {
		$url = ( $this->testmode ) ? self::TEST_SEND_URL_CVS : self::SEND_URL_CVS;
		return $url;
	}

	/**
	 * CVS redirect URL.
	 *
	 * @return string
	 */
	public static function redirect_url_cvs() {
		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		$url      = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? self::TEST_REDIRECT_URL_CVS : self::REDIRECT_URL_CVS;
		return $url;
	}

	/**
	 * Set connection URL.
	 *
	 * @param string $connection_url URL.
	 */
	public function set_connection_url( $connection_url ) {
		$this->connection_url = $connection_url;
	}

	/**
	 * Get connection URL.
	 *
	 * @return string
	 */
	public function get_connection_url() {
		return $this->connection_url;
	}

	/**
	 * Set connection time limit.
	 *
	 * @param int $connection_timeout Time limit (sec).
	 */
	public function set_connection_timeout( $connection_timeout = 0 ) {
		$this->connection_timeout = $connection_timeout;
	}

	/**
	 * Get connection time limit.
	 *
	 * @return string
	 */
	public function get_connection_timeout() {
		return $this->connection_timeout;
	}

	/**
	 * Connection.
	 *
	 * @param  array $params Transmission parameters.
	 * @return array
	 */
	public function connection( $params ) {

		$this->set_connection_url( $params['send_url'] );
		// $this->set_connection_timeout( 60 );
		$return_value = $this->send_request( $params['param_list'] );
		if ( ! empty( $return_value ) ) {
			$response = explode( "\r\n\r\n", $return_value );
			parse_str( $response[1], $response_data );
			if ( ! array_key_exists( 'ResponseCd', $response_data ) ) {
				$response_data['ResponseCd'] = 'NG';
			}
		} else {
			$response_data['ResponseCd'] = 'NG';
		}
		return $response_data;
	}

	/**
	 * Request connection.
	 *
	 * @param  array $param_list Request parameters.
	 * @return array
	 */
	public function send_request( &$param_list = array() ) {

		$return_value = array();

		// Parameter check.
		if ( empty( $param_list ) === false ) {

			$url = parse_url( $this->connection_url );

			// Create HTTP data.
			$http_data = http_build_query( $param_list );

			// Create HTTP header.
			$http_header = 'POST ' . $url['path'] . ' HTTP/1.1' . "\r\n" .
			'Host: ' . $url['host'] . "\r\n" .
			'User-Agent: SLN_PAYMENT_CLIENT_PG_PHP_VERSION_1_0' . "\r\n" .
			'Content-Type: application/x-www-form-urlencoded' . "\r\n" .
			'Content-Length: ' . strlen( $http_data ) . "\r\n" .
			'Connection: close';

			// Create POST data.
			$http_post = $http_header . "\r\n\r\n" . $http_data;

			$errno   = 0;
			$errstr  = '';
			$hm      = array();
			$context = stream_context_create(
				array(
					'ssl' => array(
						'capture_peer_cert'       => true,
						'capture_peer_cert_chain' => true,
						'disable_compression'     => true,
					),
				)
			);

			// Socket connection.
			$fp = @stream_socket_client( 'tlsv1.2://' . $url['host'] . ':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context );
			if ( false === $fp ) {
				SPFWC_Logger::add_log( 'SonyPayment SLN Connection error: ' . __( 'TLS 1.2 connection failed.', 'woo-sonypayment' ) );
				// $fp = @stream_socket_client( 'ssl://'.$url['host'].':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context );
				// if( $fp === false ) {
				// SPFWC_Logger::add_log( 'SonyPayment SLN Connection error: '.__( 'SSL connection failed.', 'woo-sonypayment' ) );
					return $return_value;
				// }
			}

			if ( false !== $fp ) {

				// Timeout setting after connection.
				$result = socket_set_timeout( $fp, $this->connection_timeout );
				if ( true === $result ) {

					// Send request.
					fwrite( $fp, $http_post );

					// Get response.
					$response_data = '';
					while ( ! feof( $fp ) ) {
						$response_data .= @fgets( $fp, 4096 );
					}

					// Get socket communication response.
					$hm = stream_get_meta_data( $fp );

					// Disconnect socket communication.
					$result = fclose( $fp );
					if ( true === $result ) {
						if ( true !== $hm['timed_out'] ) {
							// Get response data.
							$return_value = $response_data;
						} else {
							SPFWC_Logger::add_log( 'SonyPayment SLN Connection error: ' . __( 'Timeout occurred during communication.', 'woo-sonypayment' ) );
						}
					} else {
						SPFWC_Logger::add_log( 'SonyPayment SLN Connection error: ' . __( 'Failed to disconnect from SLN.', 'woo-sonypayment' ) );
					}
				} else {
					SPFWC_Logger::add_log( 'SonyPayment SLN Connection error: ' . __( 'Timeout setting failed.', 'woo-sonypayment' ) );
				}
			}
		} else {
			SPFWC_Logger::add_log( 'SonyPayment SLN Connection error: ' . __( 'Invalid request parameter specification.', 'woo-sonypayment' ) );
		}

		return $return_value;
	}

	/**
	 * Encrypt value.
	 *
	 * @param  array $data Encrypt value.
	 * @return string
	 */
	public static function get_encrypt_value( $data ) {
		$settings      = get_option( 'woocommerce_sonypayment_settings', array() );
		$key_aes       = ( ! empty( $settings['key_aes'] ) ) ? $settings['key_aes'] : self::KEY_AES;
		$key_iv        = ( ! empty( $settings['key_iv'] ) ) ? $settings['key_iv'] : self::KEY_IV;
		$data_query    = http_build_query( $data );
		$encrypt_value = openssl_encrypt( $data_query, self::CIPHER_METHOD, $key_aes, false, $key_iv );
		return $encrypt_value;
	}

	/**
	 * Decrypt value.
	 *
	 * @param  string $data Encrypt value.
	 * @return array
	 */
	public static function get_decrypt_value( $data ) {
		$settings      = get_option( 'woocommerce_sonypayment_settings', array() );
		$key_aes       = ( ! empty( $settings['key_aes'] ) ) ? $settings['key_aes'] : self::KEY_AES;
		$key_iv        = ( ! empty( $settings['key_iv'] ) ) ? $settings['key_iv'] : self::KEY_IV;
		$decrypt_value = openssl_decrypt( $data, self::CIPHER_METHOD, $key_aes, false, $key_iv );
		return $decrypt_value;
	}

	/**
	 * Encrypt value. ( For 3D Secure )
	 *
	 * @param  array $data Encrypt value.
	 * @return string
	 */
	public static function get_encrypt_value_3dsecure( $data ) {
		$settings      = get_option( 'woocommerce_sonypayment_settings', array() );
		$key_aes       = ( ! empty( $settings['key_aes'] ) ) ? $settings['key_aes'] : self::KEY_AES;
		$key_iv        = ( ! empty( $settings['key_iv'] ) ) ? $settings['key_iv'] : self::KEY_IV;
		$data_query    = http_build_query( $data );
		$encrypt_value = openssl_encrypt( $data_query, self::CIPHER_METHOD, $key_aes, OPENSSL_RAW_DATA, $key_iv );
		$encrypt_value = base64_encode( $encrypt_value );
		return $encrypt_value;
	}

	/**
	 * Decrypt value. ( For 3D Secure )
	 *
	 * @param  string $data Encrypt value.
	 * @return array
	 */
	public static function get_decrypt_value_3dsecure( $data ) {
		$settings      = get_option( 'woocommerce_sonypayment_settings', array() );
		$key_aes       = ( ! empty( $settings['key_aes'] ) ) ? $settings['key_aes'] : self::KEY_AES;
		$key_iv        = ( ! empty( $settings['key_iv'] ) ) ? $settings['key_iv'] : self::KEY_IV;
		$data          = base64_decode( $data );
		$decrypt_value = openssl_decrypt( $data, self::CIPHER_METHOD, $key_aes, OPENSSL_RAW_DATA, $key_iv );
		return $decrypt_value;
	}
}
