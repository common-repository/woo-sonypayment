<?php
/**
 * SPFWC_Install class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPFWC_Install class.
 *
 * @since 1.0.0
 */
class SPFWC_Install {

	/**
	 * DB updates and callbacks that need to be run per version.
	 *
	 * @var array
	 */
	private static $db_updates = array();

	/**
	 * Create tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$wpdb->hide_errors();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}spfwc_log';" ) != $wpdb->prefix . 'spfwc_log' ) {
			$query = "CREATE TABLE {$wpdb->prefix}spfwc_log (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` DATETIME NOT NULL,
  `trans_code` CHAR( 20 ) NOT NULL,
  `operate_id` CHAR( 20 ) NOT NULL,
  `response` LONGTEXT NOT NULL,
  `order_id` BIGINT UNSIGNED NULL,
  `payment_type` CHAR( 20 ) NULL,
  `log_type` CHAR( 20 ) NULL,
  PRIMARY KEY ( `log_id` ),
  KEY `trans_code` ( `trans_code` ),
  KEY `order_trans` ( `order_id`, `trans_code` ),
  KEY `order_id` ( `order_id` )
) $collate;";
			dbDelta( $query );
		}
	}
}
