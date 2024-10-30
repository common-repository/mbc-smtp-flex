=== MBC SMTP Flex ===
Contributors: yogaboy
Donate link: http://mbc-smtp-flex.bistromatics.com/
Tags: wp_mail, authenticated mail, smtp, amazon, ses, simple email service, phpmailer, force sender, force sender name
Requires at least: 3.5.1
Tested up to: 4.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extends wp_mail function to allow you to define the server, port, connection security and credentials.

== Description ==

Use SMTP with authentication to deliver messages from WordPress. Intercepts wp_mail function to allow you to define the server, port, connection security and credentials.

Designed initially as a replacement for the wp_mail function, this plugin uses the core WordPress mail functions and extends only the settings needed to allow connection to third-party mailing systems that require authentication and have sender and recipient restrictions.

Particularly good with Amazon SES where there are tight restrictions for sender lists and in sandbox mode only validated addresses may receive messages.

You can also simply use this to force sender and receivers for any messages from WordPress as well as set the default name in messages originating from your site.

Features:

*   Flexible configuration works with basically any mail service and protocol
*   Test function will allow you to diagnose specific errors in the email transmission
*   Able to handle Amazon Sandbox restrictions by overriding the recipient and sending only to an authorized address
*   Debug mode to capture transmission logs from phpmailer

== Installation ==

1. Download and unzip this plugin
2. Upload the \"mbs-smtp-flex\" folder to your site\'s /wp-content/plugins/ directory
3. Activate the plugin through the \'Plugins\' menu in WordPress
4. Under WP Admin Settings menu, select MBC SMTP Settings and enter server details
5. Send a test message, confirm delivery and Activate to allow the plugin to extend wp_mail

== Frequently Asked Questions ==

= How to I fix a connection failure problem? =

If the plugin is unable to connect to your server, ensure the following:

*   Ensure your server's outbound ports allow traffic over your SMTP port
*   Try to ping the SMTP host directly from your server if you have SSH available
*   Consider a CNAME wrapper to the host if your hosting company is blocking that domain
*   Check whitelist/blacklist options with SMTP server host and whitelist your server IP if possible
*   Ensure OpenSSL is available on the server (OPENSSL_VERSION_TEXT) if you are using ssl or tls for connection security

= How to I configure this for Amazon SES? =

To configure the plugin for Amazon Simple Email Service:

*   Setup your domain with Amazon SES and ensure the DNS settings are entered to validate the domain (we recommend using Route 53 to simplify the process)
*   If you are new to Amazon SES, you need to force both the sender and recipient while in sandbox mode to a verified address
*   In order to get out of sandbox mode, you need to request a quota limit change, with the request to allow to valid recipients. Please read this article for more information: http://docs.aws.amazon.com/ses/latest/DeveloperGuide/request-production-access.html
*   For TLS connections, use port 587 and for SSL use port 465
*   When you are out of the sandbox mode, you can deselect the force recipient option and test to an arbitrary email address

== Changelog ==

= 0.5 =
* Initial release with manipulation of headers and phpmailer settings.
