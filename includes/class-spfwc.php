<?php
/**
 * Woo_SonyPayment class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Woo_SonyPayment class.
 *
 * @since 1.0.0
 */
class Woo_SonyPayment {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * WP_Error object.
	 *
	 * @var WP_Error
	 */
	public $error;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->error = new WP_Error();

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		register_activation_hook( SPFWC_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( SPFWC_PLUGIN_FILE, array( $this, 'deactivate' ) );

		do_action( 'spfwc_loaded' );
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Run when plugin is activated.
	 */
	public function activate() {
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {
			deactivate_plugins( SPFWC_PLUGIN_BASENAME );
		} elseif ( in_array( 'sonypayment-light-for-woocommerce/woo-sonypayment-light.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			deactivate_plugins( SPFWC_PLUGIN_BASENAME );
			$notice = sprintf( esc_html__( 'The %1$s version could not be activated because the %2$s version has been activated. The light version and the pro version cannot be activated at the same time.', 'woo-sonypayment' ), '<strong>pro</strong>', '<strong>light</strong>' );
			wp_die(
				$notice,
				null,
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		} else {
			require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-install.php';
			SPFWC_Install::create_tables();
		}
	}

	/**
	 * Run when plugin is deactivated.
	 */
	public function deactivate() {

	}

	/**
	 * This function is hooked into admin_init to affect admin only.
	 */
	public function admin_init() {
		if ( ! is_plugin_active( SPFWC_PLUGIN_BASENAME ) ) {
			return;
		}
		if ( is_plugin_active( 'sonypayment-light-for-woocommerce/woo-sonypayment-light.php' ) ) {
			return;
		}

		include_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-admin-order.php';
	}

	/**
	 * Initial processing.
	 */
	public function init() {
		if ( ! defined( 'WC_VERSION' ) ) {
			return;
		}
		if ( defined( 'SPFWC_LIGHT_VERSION' ) ) {
			return;
		}

		load_plugin_textdomain( 'woo-sonypayment', false, plugin_basename( dirname( SPFWC_PLUGIN_FILE ) ) . '/languages' );

		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-logger.php';
		include_once SPFWC_PLUGIN_DIR . '/includes/spfwc-functions.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-sln-connection.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-card-member.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-logger.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-request.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-response-handler.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-gateway.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-gateway-cvs.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-gateway-subscriptions.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-support.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-payment-message.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-myaccount.php';
		require_once SPFWC_PLUGIN_DIR . '/includes/class-spfwc-exception.php';

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_filter( 'plugin_action_links_' . SPFWC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
			add_filter( 'woocommerce_get_sections_checkout', array( $this, 'get_sections_checkout' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_styles', array( $this, 'admin_styles' ) );
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_scripts() {
		global $current_section;
		$screen    = get_current_screen();
		$screen_id = ( $screen ) ? $screen->id : '';

		switch ( $screen_id ) {
			case 'woocommerce_page_wc-settings':
				if ( ! empty( $current_section ) && 'sonypayment' === $current_section ) {
					wp_enqueue_script( 'sonypayment_admin_scripts', plugins_url( 'assets/js/spfwc-admin.js', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION, true );
				} elseif ( ! empty( $current_section ) && 'sonypayment_cvs' === $current_section ) {
					wp_register_script( 'sonypayment_admin_cvs_scripts', plugins_url( 'assets/js/spfwc-admin-cvs.js', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION, true );
					$sonypayment_admin_cvs_params          = array();
					$sonypayment_admin_cvs_params['label'] = array(
						'amount_from' => __( 'Total Amount (From:)', 'woo-sonypayment' ),
						'amount_to'   => __( 'Total Amount (To:)', 'woo-sonypayment' ),
						'fee'         => __( 'Settlement fee (tax included)', 'woo-sonypayment' ),
						'insert_row'  => __( 'Insert row', 'woocommerce' ),
						'remove_row'  => __( 'Remove selected row(s)', 'woocommerce' ),
					);
					$settings                              = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
					if ( isset( $settings['settlement_fee'] ) && 'yes' === $settings['settlement_fee'] ) {
						$sonypayment_admin_cvs_params['fees'] = get_option( 'spfwc_cvs_settlement_fees', array() );
					}
					wp_localize_script( 'sonypayment_admin_cvs_scripts', 'sonypayment_admin_cvs_params', apply_filters( 'sonypayment_admin_cvs_params', $sonypayment_admin_cvs_params ) );
					wp_enqueue_script( 'sonypayment_admin_cvs_scripts' );
				}
				break;
			case 'shop_order':
				wp_enqueue_script( 'jquery-ui-dialog' );
				break;
		}
	}

	/**
	 * Enqueue admin styles.
	 */
	public function admin_styles() {

		wp_enqueue_style( 'sonypayment_admin_styles', plugins_url( 'assets/css/spfwc-admin.css', SPFWC_PLUGIN_FILE ), array(), SPFWC_VERSION );
	}

	/**
	 * Add the gateways to WooCommerce.
	 *
	 * @param  array $methods Gateway methods.
	 * @return array
	 */
	public function add_gateways( $methods ) {
		if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
			$methods[] = 'WC_Gateway_SonyPayment_Subscriptions';
		} else {
			$methods[] = 'WC_Gateway_SonyPayment';
		}
		$methods[] = 'WC_Gateway_SonyPayment_CVS';
		return $methods;
	}

	/**
	 * Adds plugin action links.
	 *
	 * @param  mixed $links Plugin action links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="admin.php?page=wc-settings&tab=checkout&section=sonypayment">' . esc_html__( 'Settings', 'woo-sonypayment' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Modifies the order of the gateways displayed in admin.
	 *
	 * @param  array $sections Sections.
	 * @return array
	 */
	public function get_sections_checkout( $sections ) {
		unset( $sections['sonypayment'] );
		unset( $sections['sonypayment_cvs'] );
		$sections['sonypayment']     = __( 'SonyPayment', 'woo-sonypayment' );
		$sections['sonypayment_cvs'] = __( 'SonyPayment Online Payment Collection Agency Service', 'woo-sonypayment' );
		return $sections;
	}
}
