<?php
/**
 * WordPress mailer factory.
 *
 * @package WordPress
 * @since 7.2.0
 */

/**
 * WordPress mailer factory class.
 *
 * Creates WPMailer instances with the same defaults that wp_mail() uses, so
 * that plugins can send mail directly while preserving WordPress behavior.
 *
 * @since 7.2.0
 */
class WPMailerFactory {

	/**
	 * Creates a new mailer instance.
	 *
	 * @since 7.2.0
	 *
	 * @return WPMailer A new mailer instance.
	 */
	public static function getMailer() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Uses PHPMailer naming conventions for API consistency.
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';
		require_once ABSPATH . WPINC . '/class-wpmailer.php';

		$mailer = new WPMailer( true );

		$mailer::$validator = static function ( $email ) {
			return (bool) is_email( $email );
		};

		return $mailer;
	}
}
