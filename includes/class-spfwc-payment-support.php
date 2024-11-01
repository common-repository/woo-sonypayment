<?php
/**
 * WC_Payment_Support class.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Payment_Support class.
 *
 * @since 1.0.0
 */
class WC_Payment_Support {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->add_user_profile();
		$this->add_kana_fields();

		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		if ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) {
			add_action( 'spfwc_delete_card_member', array( $this, 'delete_card_member' ), 10, 2 );
		}
	}

	/**
	 * Card member deletion processing in test mode.
	 *
	 * @param array $param_list Parameters.
	 * @param array $response Parameters.
	 */
	public function delete_card_member( $param_list, $response ) {
		if ( 'K71' === $response['ResponseCd'] && isset( $response['MerchantFree3'] ) ) {
			$customer_id = absint( $response['MerchantFree3'] );
			delete_user_meta( $customer_id, '_spfwc_member_id' );
			delete_user_meta( $customer_id, '_spfwc_member_pass' );
		}
	}

	/**
	 * Add card member information to edit user pages.
	 * The administrator can delete the card member information of the user.
	 */
	public function add_user_profile() {
		$settings = get_option( 'woocommerce_sonypayment_settings', array() );
		if ( ( isset( $settings['enabled'] ) && 'no' !== $settings['enabled'] ) &&
			( isset( $settings['cardmember'] ) && 'yes' === $settings['cardmember'] ) ) {
			add_action( 'show_user_profile', array( $this, 'add_card_member_fields' ), 11 );
			add_action( 'edit_user_profile', array( $this, 'add_card_member_fields' ), 11 );
			add_action( 'personal_options_update', array( $this, 'save_card_member_fields' ), 11 );
			add_action( 'edit_user_profile_update', array( $this, 'save_card_member_fields' ), 11 );
		}
	}

	/**
	 * Add kana fields.
	 * Only when "Woo Commerce For Japan" is not effective.
	 */
	public function add_kana_fields() {
		$settings = get_option( 'woocommerce_sonypayment_cvs_settings', array() );
		if ( isset( $settings['enabled'] ) && 'no' !== $settings['enabled'] ) {
			if ( ! get_option( 'wc4jp-yomigana' ) ) {
				add_filter( 'woocommerce_default_address_fields', array( $this, 'default_address_fields' ), 11 );
				add_action( 'woocommerce_formatted_address_replacements', array( $this, 'address_replacements' ), 21, 2 );
				add_filter( 'woocommerce_localisation_address_formats', array( $this, 'address_formats' ), 21 );
				add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'formatted_address' ), 11, 3 );
				add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'billing_address' ), 11, 2 );
				add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'shipping_address' ), 21, 2 );
				add_filter( 'woocommerce_get_order_address', array( $this, 'get_order_address' ), 21, 3 );
				add_filter( 'woocommerce_customer_meta_fields', array( $this, 'customer_meta_fields' ), 11 );
				add_filter( 'woocommerce_admin_billing_fields', array( $this, 'admin_billing_fields' ), 9 );
				add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'admin_shipping_fields' ), 9 );
			}
		}
	}

	/**
	 * Show card member on edit user pages.
	 *
	 * @param WP_User $user User object.
	 */
	public function add_card_member_fields( $user ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$customer_id = $user->ID;
		$member      = new SPFWC_Card_Member( $customer_id );
		$cardlast4   = '';
		if ( $member->is_card_member() ) {
			$response_member = $member->search_card_member();
			if ( 'OK' === $response_member['ResponseCd'] ) {
				$cardlast4 = substr( $response_member['CardNo'], -4 );
			}
			?>
		<h2><?php esc_html_e( 'SonyPayment Card Member', 'woo-sonypayment' ); ?></h2>
		<table class="form-table" id="fieldset-sonypayment-card-member'">
			<?php if ( ! empty( $cardlast4 ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last 4 digits of the saved card number', 'woo-sonypayment' ); ?></th>
				<td><span id="sonypayment-card-member-cardlast4"><?php echo esc_html( $cardlast4 ); ?></span></td>
			</tr>
		<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete the saved card member', 'woo-sonypayment' ); ?></th>
				<td>
					<label for="sonypayment-card-member-delete"><input name="sonypayment_card_member_delete" type="checkbox" id="sonypayment-card-member-delete" value="false"><?php esc_html_e( 'Delete a card member information registered in SonyPayment.', 'woo-sonypayment' ); ?></label>
					<br/>
					<span class="description"><?php esc_html_e( 'Don\'t delete in case of the sales isn\'t recorded or the subscription products is ongoing.', 'woo-sonypayment' ); ?></span>
				</td>
			</tr>
		</table>
			<?php
		}
	}

	/**
	 * Delete card member on edit user pages.
	 *
	 * @param int $user_id User ID of the user being saved.
	 */
	public function save_card_member_fields( $user_id ) {

		if ( isset( $_POST['sonypayment_card_member_delete'] ) ) {
			$member = new SPFWC_Card_Member( $user_id );
			if ( $member->is_card_member() ) {
				// Delete of card member.
				$response_member = $member->delete_card_member();
				if ( 'OK' !== $response_member['ResponseCd'] ) {
					$responsecd = explode( '|', $response_member['ResponseCd'] );
					foreach ( (array) $responsecd as $cd ) {
						$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
					}
					$localized_message = __( 'Failed deleting card member.', 'woo-sonypayment' );
					SPFWC_Logger::add_log( '[4MemDel] Error: ' . print_r( $response_member, true ) );
				}
			}
		}
	}

	/**
	 * Add kana fields.
	 *
	 * @param  array $fields Address fields.
	 * @return array
	 */
	public function default_address_fields( $fields ) {

		$address_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$address_fields['last_name_kana']  = array(
					'label'    => __( 'Last name (kana)', 'woo-sonypayment' ),
					'required' => false,
					'class'    => array( 'form-row-first' ),
				);
				$address_fields['first_name_kana'] = array(
					'label'    => __( 'First name (kana)', 'woo-sonypayment' ),
					'required' => false,
					'class'    => array( 'form-row-last' ),
					'clear'    => true,
				);
			}
			$address_fields[ $key ] = $field;
		}
		return apply_filters( 'spfwc_default_address_fields', $address_fields, $fields );
	}

	/**
	 * Add kana fields.
	 *
	 * @param  array $fields Address fields.
	 * @param  array $args Arguments.
	 * @return array
	 */
	public function address_replacements( $fields, $args ) {
		$fields['{first_name_kana}'] = ( isset( $args['first_name_kana'] ) ) ? $args['first_name_kana'] : '';
		$fields['{last_name_kana}']  = ( isset( $args['last_name_kana'] ) ) ? $args['last_name_kana'] : '';
		return apply_filters( 'spfwc_formatted_address_replacements', $fields );
	}

	/**
	 * Add kana fields.
	 *
	 * @param  array $fields Address fields.
	 * @return array
	 */
	public function address_formats( $fields ) {
		$fields['JP'] = "{postcode}\n{state} {city} {address_1}\n{address_2}\n{company}\n{last_name} {first_name}\n{last_name_kana} {first_name_kana}\n{country}";
		return apply_filters( 'spfwc_localisation_address_formats', $fields );
	}

	/**
	 * Add kana fields to myaccount address.
	 *
	 * @param  array  $address Address fields.
	 * @param  int    $customer_id The WP user ID.
	 * @param  string $address_type billing|shipping.
	 * @return array
	 */
	public function formatted_address( $address, $customer_id, $address_type ) {
		$address['first_name_kana'] = get_user_meta( $customer_id, $address_type . '_first_name_kana', true );
		$address['last_name_kana']  = get_user_meta( $customer_id, $address_type . '_last_name_kana', true );
		return apply_filters( 'spfwc_my_account_my_address_formatted_address', $address, $customer_id, $address_type );
	}

	/**
	 * Add kana fields to billing.
	 *
	 * @param  array    $address Address fields.
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	public function billing_address( $address, $order ) {
		$address['first_name_kana'] = $order->get_meta( '_billing_first_name_kana', true );
		$address['last_name_kana']  = $order->get_meta( '_billing_last_name_kana', true );
		return apply_filters( 'spfwc_order_formatted_billing_address', $address, $order );
	}

	/**
	 * Add kana fields to shipping.
	 *
	 * @param  array    $address Address fields.
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	public function shipping_address( $address, $order ) {
		$address['first_name_kana'] = $order->get_meta( '_shipping_first_name_kana', true );
		$address['last_name_kana']  = $order->get_meta( '_shipping_last_name_kana', true );
		return apply_filters( 'spfwc_order_formatted_shipping_address', $address, $order );
	}

	/**
	 * Add kana fields to order data.
	 *
	 * @param  array    $address Address fields.
	 * @param  string   $address_type billing|shipping.
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	public function get_order_address( $address, $address_type, $order ) {
		if ( 'billing' === $address_type ) {
			$address['first_name_kana'] = $order->get_meta( '_billing_first_name_kana', true );
			$address['last_name_kana']  = $order->get_meta( '_billing_last_name_kana', true );
		} else {
			$address['first_name_kana'] = $order->get_meta( '_shipping_first_name_kana', true );
			$address['last_name_kana']  = $order->get_meta( '_shipping_last_name_kana', true );
		}
		return apply_filters( 'spfwc_get_order_address', $address, $address_type, $order );
	}

	/**
	 * Add kana fields to customer data.
	 *
	 * @param  array $fields Address fields.
	 * @return array
	 */
	public function customer_meta_fields( $fields ) {
		$customer_meta_fields                     = array();
		$customer_meta_fields['billing']['title'] = $fields['billing']['title'];
		foreach ( $fields['billing']['fields'] as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$customer_meta_fields['billing']['fields']['billing_first_name_kana'] = array(
					'label'       => __( 'First name kana', 'woo-sonypayment' ),
					'description' => '',
				);
				$customer_meta_fields['billing']['fields']['billing_last_name_kana']  = array(
					'label'       => __( 'Last name kana', 'woo-sonypayment' ),
					'description' => '',
				);
			}
			$customer_meta_fields['billing']['fields'][ $key ] = $field;
		}
		$customer_meta_fields['shipping']['title'] = $fields['shipping']['title'];
		foreach ( $fields['shipping']['fields'] as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$customer_meta_fields['shipping']['fields']['shipping_first_name_kana'] = array(
					'label'       => __( 'First name kana', 'woo-sonypayment' ),
					'description' => '',
				);
				$customer_meta_fields['shipping']['fields']['shipping_last_name_kana']  = array(
					'label'       => __( 'Last name kana', 'woo-sonypayment' ),
					'description' => '',
				);
			}
			$customer_meta_fields['shipping']['fields'][ $key ] = $field;
		}
		return apply_filters( 'spfwc_customer_meta_fields', $customer_meta_fields, $fields );
	}

	/**
	 * Add billing kana fields.
	 *
	 * @param  array $fields Address fields.
	 * @return array
	 */
	public function admin_billing_fields( $fields ) {
		$billing_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$billing_fields['first_name_kana'] = array(
					'label' => __( 'First name kana', 'woo-sonypayment' ),
					'show'  => false,
				);
				$billing_fields['last_name_kana']  = array(
					'label' => __( 'Last name kana', 'woo-sonypayment' ),
					'show'  => false,
				);
			}
			$billing_fields[ $key ] = $field;
		}
		return apply_filters( 'spfwc_admin_billing_fields', $billing_fields, $fields );
	}

	/**
	 * Add shipping kana fields.
	 *
	 * @param  array $fields Address fields.
	 * @return array
	 */
	public function admin_shipping_fields( $fields ) {
		$shipping_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$shipping_fields['first_name_kana'] = array(
					'label' => __( 'First name kana', 'woo-sonypayment' ),
					'show'  => false,
				);
				$shipping_fields['last_name_kana']  = array(
					'label' => __( 'Last name kana', 'woo-sonypayment' ),
					'show'  => false,
				);
			}
			$shipping_fields[ $key ] = $field;
		}
		return apply_filters( 'spfwc_admin_shipping_fields', $shipping_fields, $fields );
	}
}

new WC_Payment_Support();
