<?php
/**
 * Edit cardmember form.
 *
 * @package Sony Payment Services pro for WooCommerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'spfwc_before_edit_cardmember_form' );
?>
<form name="sonypayment_cardmember" class="spfwc-edit-cardmember-form" action="" method="post">
	<?php do_action( 'spfwc_edit_cardmember_form_start' ); ?>
	<fieldset id="sonypayment-card-form" class="wc-payment-form" style="background:transparent;">
		<?php if ( ! empty( $cardlast4 ) ) : ?>
		<p class="form-row form-row-wide">
			<label for="sonypayment-card-member-cardlast4" style="display:inline;"><?php esc_html_e( 'Last 4 digits of the saved card number: ', 'woo-sonypayment' ); ?></label>
			<span id="sonypayment-card-member-cardlast4"><?php echo esc_html( $cardlast4 ); ?></span>
		</p>
		<?php endif; ?>
		<p class="form-row form-row-wide">
			<label for="sonypayment-card-number"><?php esc_html_e( 'Card number', 'woo-sonypayment' ); ?> <span class="required">*</span></label>
			<input id="sonypayment-card-number" name="sonypayment_card_number" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" />
		</p>
		<p class="form-row form-row-wide">
			<label><?php esc_html_e( 'Expiry (MM/YY)', 'woo-sonypayment' ); ?> <span class="required">*</span></label>
			<input id="sonypayment-card-expmm" name="sonypayment_card_expmm" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="2" style="width:60px" placeholder="<?php esc_attr_e( 'MM', 'woo-sonypayment' ); ?>" />&nbsp;/&nbsp;
			<input id="sonypayment-card-expyy" name="sonypayment_card_expyy" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="2" style="width:60px" placeholder="<?php esc_attr_e( 'YY', 'woo-sonypayment' ); ?>" />
		</p>
		<?php if ( ! empty( $settings['seccd'] ) && 'yes' === $settings['seccd'] ) : ?>
		<p class="form-row form-row-wide">
			<label for="sonypayment-card-seccd"><?php esc_html_e( 'Card code', 'woo-sonypayment' ); ?> <span class="required">*</span></label>
			<input id="sonypayment-card-seccd" name="sonypayment_card_seccd" class="input-text" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" style="width:80px" />
		</p>
		<?php endif; ?>
		<p class="form-row form-row-wide">
			<label for="sonypayment-card-name"><?php esc_html_e( 'Card name', 'woo-sonypayment' ); ?> <span class="required">*</span></label>
			<input id="sonypayment-card-name" name="sonypayment_card_name" class="input-text" inputmode="text" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="TARO&nbsp;YAMADA" />
		</p>
		<div class="clear"></div>
	</fieldset>
		<?php do_action( 'spfwc_edit_cardmember_form' ); ?>
		<?php if ( $is_3d_secure || $linktype ) : ?>
	<fieldset id="sonypayment-consent-message">
		<textarea class="sonypayment_agreement_message" rows="5" readonly><?php echo wp_kses_post( spfwc_consent_message() ); ?></textarea>
		<div class="sonypayment-consent-area">
			<label for="sonypayment_agree"><input type="checkbox" id="sonypayment_agree" value="agree" >&nbsp;&nbsp;<span><?php esc_html_e( 'I agree to the handling of personal information', 'woo-sonypayment' ); ?></span></label>
		</div>
		<input type="hidden" id="transaction_code" name="transaction_code" id="transaction_code" value="<?php echo esc_attr( spfwc_get_transaction_code() ); ?>" />
	</fieldset>
		<?php endif; ?>
	<p>
		<?php wp_nonce_field( 'spfwc_edit_cardmember' ); ?>
		<button type="submit" class="woocommerce-Button button" id="save-cardmember" value="save"><?php esc_html_e( 'Save changes', 'woo-sonypayment' ); ?></button>
		<?php if ( ! empty( $cardlast4 ) && $deletable ) : ?>
		<button type="submit" class="woocommerce-Button button" id="delete-cardmember" value="delete"><?php esc_html_e( 'Delete the saved card member', 'woo-sonypayment' ); ?></button>
		<?php endif; ?>
		<input type="hidden" id="edit-cardmember-action" name="action" value="" />
		<input type="hidden" id="sonypayment-token-code" name="sonypayment_token_code" value="" />
		<input type="hidden" id="sonypayment-billing-name" name="sonypayment_billing_name" value="" />
	</p>
	<?php do_action( 'spfwc_edit_cardmember_form_end' ); ?>
</form>
<?php do_action( 'spfwc_after_edit_cardmember_form' ); ?>
