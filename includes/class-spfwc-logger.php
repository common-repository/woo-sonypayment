<?php
/**
 * SPFWC_Logger class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Logger class.
 *
 * @since 1.0.0
 */
class SPFWC_Logger {

	/**
	 * Logger instance.
	 *
	 * @var array
	 */
	public static $logger;

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function add_log( $message, $level = 'info' ) {

		$settings = get_option( 'woocommerce_sonypayment_settings' );
		if ( empty( $settings ) || isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) {
			return;
		}

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			if ( empty( self::$logger ) ) {
				self::$logger = new WC_Logger();
			}
			self::$logger->add( 'sonypayment', $message );

		} else {
			if ( empty( self::$logger ) ) {
				self::$logger = wc_get_logger();
			}
			self::$logger->log( $level, $message, array( 'source' => 'sonypayment' ) );
		}
	}
}
