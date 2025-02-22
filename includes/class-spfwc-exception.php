<?php
/**
 * SPFWC_Exception class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Exception class.
 *
 * @extends Exception
 * @since 1.0.0
 */
class SPFWC_Exception extends Exception {

	/**
	 * Sanitized/localized error message.
	 *
	 * @var string
	 */
	protected $localized_message;

	/**
	 * Setup exception.
	 *
	 * @param string $error_message Full response.
	 * @param string $localized_message user-friendly translated error message.
	 */
	public function __construct( $error_message = '', $localized_message = '' ) {
		$this->localized_message = $localized_message;
		parent::__construct( $error_message );
	}

	/**
	 * Returns the localized message.
	 *
	 * @return string
	 */
	public function getLocalizedMessage() {
		return $this->localized_message;
	}
}
