=== Sony Payment Services pro for WooCommerce ===
Contributors: Welcart Inc., sonypaymentservices
Tags: credit card, payment, woocommerce, subscriptions, sonypayment
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.0 - 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Sony Payment Services pro for WooCommerce plugin allows you to accept Credit Cards, Convenience Stores, Pay-easy, E-money Payments via e-SCOTT Smart system Powered by Sony Payment Services.
This plugin acts as an addon to add a payment method on WooCommerce checkout page.
On the checkout page, our plugin connects to e-SCOTT Smart system.

Sony Payment Services pro for WooCommerce is currently only available for customers having their registered office in Japan.
And available currency is only JPY.

= The diffrence between "Sony Payment Services light for WooCommerce" and "Sony Payment Services Pro for WooCommerce" =

1. Sony Payment Services Pro supports a recurring payment.
2. In Sony Payment Services Pro, you can update the status and the amounts in the WooCommerce admin page. 
Note:Even if you stop using Sony Payment Services Pro for WooCommerce and install and enable Sony Payment Services light for WooCommerce, you will not be able to use the features of the light version.
Please be sure to ask change your contract to Sony Payment Services.
And these versions can't be activated and used at the same time.

= About e-SCOTT Smart system =

e-SCOTT Smart system is an online payment gateway Powered by Sony Payment Services that allows both individuals and businesses to accept payments over the Internet.
The highest level of security of payments processed by e-SCOTT Smart system is verified by PCI DSS.
System guarantees convenience and instant order execution. 

Service: https://www.sonypaymentservices.jp/
Privacy Policy: https://www.sonypaymentservices.jp/policy/

In order to use this plugin you will need a merchant services account.
Sony Payment Services offers merchant accounts. 
Please go to the signup page by clicking the link below to create a new account.

Signup page: https://form.sonypaymentservices.jp/input_woocommerce_pro.html

= Credit Card Payment =

Either token payment(non-passage) type or external link type can be chosen for credit card payment.

= Online Payment Collection Agency Service(Convenience Stores, Pay-easy, E-money Payments) =

Online Payment Collection Agency Service is only available for legal entity.
Your customers will choose the settlement type on the checkout page provided from Sony Payment Services.(Extrnal link type.) 
Payment status automatically changed to "Paid" when the customers payments has been confirmed.

= User Manual =

https://www.collne.com/dl/woo/sony-payment-services-pro-for-woocommerce.pdf

== Installation ==

= Minimum Requirements =

* WooCommerce 3.0 or greater
* PHP 7.0 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Sony Payment Services pro for WooCommerce, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type Sony Payment Services pro for WooCommerce and click Search Plugins. Once you've found our plugin you can view details about it such as the the rating and description. Most importantly, of course, you can install it by simply clicking Install Now.

= Manual installation =

Download page
https://ja.wordpress.org/plugins/woo-sonypayment/

1.Go to WordPress Plugins > Add New
2.Click Upload Plugin Zip File
3.Upload the zipped Sony Payment Services pro for WooCommerce file and click "Upload Now"
4.Go to Installed Plugins
5.Activate the "Sony Payment Services pro for WooCommerce"

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Yes. Please upgrade to version 1.1.0.
To implement recurring billing, you must use WooCommerce official plugin named "WooCommerce Subscriptions"(Prospress Inc.) with this plugin.

= In case of convinience store payment, payment status doesn't change to "Paid" althogh the customers payments has been confirmed. =

Please apply for "Payment result notification URL" to Sony Payment Services.
If you already applied for it, please confirm that there are no mistakes.

== Changelog ==

= 2.0.0 - 2024-09-30 =
* Added support for blocking certain payment methods.
* Added support for high-performance order storage.

= 1.2.6 - 2024-06-25 =
* Fix - Fixed the bug where the form becomes unresponsive when a credit card payment fails.
* Update - Added support for EMV-3DS.
* Update - WordPress tested up to 6.5

= 1.2.5 - 2024-03-19 =
* Update - Updated readme.

= 1.2.4 - 2024-03-19 =
* Fix - Fixed error messages.
* Update - Updated readme.

= 1.2.3 - 2023-05-31 =
* Fix - Fixed the bug that the payment fee for Online Payment Collection Agency is not calculated correctly.

= 1.2.2 - 2023-05-29 =
* Update - WordPress tested up to 6.2
* Update - WC tested up to 7.7
* Update - Added switch to set order status to "Completed" when purchasing virtual items only.
* Update - Added input value check.
* Update - Added permission check to management screen form submission.

= 1.2.1 - 2023-01-05 =
* Update - WordPress tested up to 6.1
* Update - WC tested up to 7.2
* Fix - Fixed the bug that Kanji and Kana characters are not aligned in the first name and last name entry fields on the Payment page > Billing Information details.

= 1.2.0 - 2022-08-31 =
* Update - WC tested up to 6.8
* Update - 3D Secure 2.0 has been handled.
* Update - Changed the transition of the payment screen when purchasing by selecting "Purchase by changing the card" in the external link type.
* Fix - Fixed the bug that caused a transition to the BadRequest screen in case of a payment error.

= 1.1.15 - 2022-04-13 =
* Fix - Fixed the bug that "K39" error occurs when reauthorizing.

= 1.1.14 - 2022-04-11 =
* Update - Disabled the display of the deposit procedure in the processing and completion e-mails when depositing funds through the Online Payment Collection Agency.
* Fix - Deprecation warning for "capture_session_meta".

= 1.1.13 - 2022-04-04 =
* Update - WC tested up to 6.3
* Update - Changed status transition for Online Payment Collection Agency.
* Update - Disabled sending an email during processing when making a payment to Online Payment Collection Agency.
* Update - Changed the notation in the e-mail when there is a payment fee for Online Payment Collection Agency.

= 1.1.12 - 2022-01-31 =
* Update - WordPress tested up to 5.9
* Update - WC tested up to 6.1
* Fix - The layout of the payment information in Online Payment Collection Agency Service text message is broken.

= 1.1.11 - 2021-07-26 =
* Update - WordPress tested up to 5.8
* Update - WC tested up to 5.5
* Update - Set "(Kanji) Name" as the default setting when "Kana Name" is not set in Online Payment Collection Agency.

= 1.1.10 - 2021-06-17 =
* Update - WordPress tested up to 5.7
* Update - WC tested up to 5.4
* Fix - Fixed the bug that card information is not registered when "Always registering as a card member" is selected.

= 1.1.9 - 2020-11-24 =
* Update - WordPress tested up to 5.6
* Update - WC tested up to 4.7
* Fix - Added the AES key input field.

= 1.1.8 - 2020-09-23 =
* Update - WordPress tested up to 5.5
* Update - WC tested up to 4.5
* Fix - Fixed the bug that the input field does not appear when the "Set Payment Charges" is checked.
* Fix - Fixed the bug that a payment error is caused when failing to recover from the 3D secure authentication screen.
* Fix - Changed the text domain to "woo-sonypayment".

= 1.1.7 - 2020-06-01 =
* Feature - Added the function to disable simultaneous use with "Sony Payment Services light for WooCommerce".

= 1.1.6 - 2020-05-11 =
* Update - WordPress tested up to 5.4
* Update - WC tested up to 4.1
* Update - Changed the plugin name
* Fixed a bug that the last 4 digits of registered cards are not displayed.
* Feature - Added the function to add an online payment settlement fee.
* Fix - Fixed the bug that "EncryptValue is invalid." displays when the "3D Secure Authentication Result Code" is "3 (not supported by the card issuer)".
* Fix - Fixed the bug that the card information update screen appears when you use a quick payment by using the screen between three parties.

= 1.1.5 - 2019-12-05 =
* Feature - Added the function of recording "3D secure authentication result code" when using 3D secure.
* Fix - Fixed the bug that the return value of 3D secure authentication don't get normally.
* Feature - Added E-SCOTT error messages.

= 1.1.4 - 2019-11-15 =
* Feature - Supports 3D Secure authentication
* Fix - Fixed the bug that [Record sales] displays again even if it's already done [Record sales] when after [change amount].
* Update - WordPress tested up to 5.3
* Update - WC tested up to 3.8

= 1.1.3 - 2019-10-30 =
* Fix - Fixed the bug that prevented credit card registration and update on My page
* Update - WC tested up to 3.7

= 1.1.2 - 2019-05-15 =
* Update - WordPress tested up to 5.2
* Update - WC tested up to 3.6

= 1.1.1 - 2018-12-17 =
* Fix - Fixed the bug that the error of getting payment status after re-authorization
* Update - Changed the Plug-in name
* Update - WC tested up to 3.5

= 1.1.0 - 2018-09-01 =
* Feature - Supports "WooCommerce Subscriptions" plugin

= 1.0.0 - 2018-06-01 =
* Feature - SonyPayment Credit Card Payment
* Feature - SonyPayment Online Payment Collection Agency Service
