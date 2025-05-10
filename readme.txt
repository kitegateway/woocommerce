=== Kitegateway for WooCommerce ===
Contributors: Kitegateway Developers
Tags: payments, woocommerce, mobile-money, credit-card, kitegateway
Requires at least: 3.1
Tested up to: 6.8
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://github.com/kitegateway/woocommerce/blob/master/LICENSE

Accept credit cards, debit cards, and mobile money payments on your WooCommerce store with the official Kitegateway plugin.

== Description ==

Easily integrate Kitegateway to accept credit cards, debit cards, and mobile money payments on your WooCommerce store.

= Plugin Features =

* Collections: Card, Mobile money.

= Requirements =

1. Kitegateway [API](https://docs.kitegateway.com)
2. [WooCommerce](https://woocommerce.com/)
3. Supported PHP version: 7.4.0 - 8.1.0

== Installation ==

= Automatic Installation =
1. Log in to your WordPress Dashboard.
2. Navigate to **Plugins > Add New** from the left menu.
3. Search for **Kitegateway for WooCommerce**.
4. Click **Install Now** on **Kitegateway for WooCommerce**.
5. Activate the plugin.
6. Go to **WooCommerce > Settings**, click the **Payments** tab, and select **Kitegateway**.
7. Configure your **Kitegateway for WooCommerce** settings and save changes.

= Manual Installation =
1. Download the plugin zip file.
2. Log in to your WordPress Admin, go to **Plugins > Add New**.
3. Click **Upload Plugin**, select the zip file, and click **Install Now**.
4. Activate the plugin.
5. Go to **WooCommerce > Settings**, click the **Payments** tab, and select **Kitegateway**.
6. Configure your **Kitegateway for WooCommerce** settings and save changes.

For FTP manual installation, [see WordPress documentation](https://wordpress.org/documentation/article/manage-plugins/#manual-plugin-installation).

= Configure the Plugin =
1. Go to **WooCommerce > Settings**, click the **Payments** tab, and select **Kitegateway**.
2. Check **Enable/Disable** to enable the plugin.
3. Enter your **Pay Button Public Key** from the "Pay Buttons" page in your Kitegateway account dashboard.
4. Optionally, customize the **Modal Title** (default: Kitegateway).
5. Click **Save Changes**.

= Best Practices =
1. Verify transaction status on the Kitegateway Dashboard.
2. Keep API keys secure and private.
3. Use the latest version of the Kitegateway plugin.

= Debugging Errors =
Refer to our [error documentation](https://docs.kitegateway.com/getting-started/errors) for guidance. For `authorization` or `validation` errors, verify API keys. For `server` errors, contact our team.

= Support =
Contact our developer experience (DX) team via [email](mailto:tech@kitegateway.com). Follow us on [@Kitegateway](https://x.com/kitegateway).

= Contribution Guidelines =
Read our [community contribution guidelines](/CONTRIBUTING.md).

= License =
Contributions are licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

== Frequently Asked Questions ==

= What Do I Need To Use The Plugin? =
1. A [Kitegateway](https://kitegateway.com) account. Contact sales@kitegateway.com.

== Changelog ==
= 1.0.1 =
* Modified slug to match wordpress standard.

= 1.0.0 =
* Initial release with support for credit/debit cards and mobile money payments.

= 1.0.0 =
* First stable release.

== Screenshots ==
1. Plugin settings in WooCommerce.
2. Kitegateway configuration options.
3. Checkout page with Kitegateway payment option.
4. IPN messages in the dashboard.