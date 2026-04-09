<?php
/**
 * WP_Mailer class.
 *
 * A standard way to manage emails sent by WordPress core.
 *
 * @package WordPress
 * @subpackage Mail
 * @since 7.1.0
 */

/**
 * Core class used to send standardized emails.
 *
 * @since 7.1.0
 */
class WP_Mailer {

	/**
	 * Registered emails.
	 *
	 * @since 7.1.0
	 * @var array
	 */
	private static $emails = array();

	/**
	 * Sends a standardized email.
	 *
	 * @since 7.1.0
	 *
	 * @param string $email_id Email identifier.
	 * @param array  $args {
	 *     Optional. Email arguments.
	 *
	 *     @type string|string[] $to          Array or comma-separated list of email addresses.
	 *     @type string|string[] $headers     Optional. Additional headers.
	 *     @type string|string[] $attachments Optional. Paths to files to attach.
	 * }
	 * @param array  $data     Optional. Data to be used in the template placeholders.
	 * @return bool Whether the email was sent successfully.
	 */
	public static function send( $email_id, $args = array(), $data = array() ) {
		$email = self::get_email( $email_id );

		if ( ! $email ) {
			return false;
		}

		$group = $email[ 'group' ];

		/**
		 * Filters the data used for email template rendering.
		 *
		 * @since 7.1.0
		 *
		 * @param array  $data     The template data.
		 * @param string $email_id The email identifier.
		 * @param string $group    The email group.
		 */
		$data = apply_filters( "wp_mailer_{$group}_data", $data, $email_id, $group );
		$data = apply_filters( "wp_mailer_{$email_id}_data", $data, $email_id, $group );

		$subject = self::render( $email[ 'subject' ], $data );
		$message = self::render( $email[ 'body' ], $data );

		/**
		 * Filters the email subject before sending.
		 *
		 * @since 7.1.0
		 *
		 * @param string $subject  The rendered subject.
		 * @param string $email_id The email identifier.
		 * @param array  $data     The template data.
		 */
		$subject = apply_filters( "wp_mailer_{$group}_subject", $subject, $email_id, $data );
		$subject = apply_filters( "wp_mailer_{$email_id}_subject", $subject, $email_id, $data );

		/**
		 * Filters the email message before sending.
		 *
		 * @since 7.1.0
		 *
		 * @param string $message  The rendered message.
		 * @param string $email_id The email identifier.
		 * @param array  $data     The template data.
		 */
		$message = apply_filters( "wp_mailer_{$group}_message", $message, $email_id, $data );
		$message = apply_filters( "wp_mailer_{$email_id}_message", $message, $email_id, $data );

		$to          = isset( $args[ 'to' ] ) ? $args[ 'to' ] : '';
		$headers     = isset( $args[ 'headers' ] ) ? $args[ 'headers' ] : '';
		$attachments = isset( $args[ 'attachments' ] ) ? $args[ 'attachments' ] : array();

		/**
		 * Filters the email headers before sending.
		 *
		 * @since 7.1.0
		 *
		 * @param string|array $headers  The email headers.
		 * @param string       $email_id The email identifier.
		 * @param array        $data     The template data.
		 */
		$headers = apply_filters( "wp_mailer_{$group}_headers", $headers, $email_id, $data );
		$headers = apply_filters( "wp_mailer_{$email_id}_headers", $headers, $email_id, $data );

		return wp_mail( $to, $subject, $message, $headers, $attachments );
	}

	/**
	 * Registers an email template.
	 *
	 * @since 7.1.0
	 *
	 * @param string $email_id Email identifier.
	 * @param string $group    Email group (e.g., 'privacy', 'admin').
	 * @param array  $args {
	 *     Email template arguments.
	 *
	 *     @type string $subject Email subject template.
	 *     @type string $body    Email body template.
	 * }
	 */
	public static function register_email( $email_id, $group, $args ) {
		self::$emails[ $email_id ] = array(
			'group'   => $group,
			'subject' => $args[ 'subject' ],
			'body'    => $args[ 'body' ],
		);
	}

	/**
	 * Retrieves an email template.
	 *
	 * @since 7.1.0
	 *
	 * @param string $email_id Email identifier.
	 * @return array|false Email template data on success, false on failure.
	 */
	public static function get_email( $email_id ) {
		if ( isset( self::$emails[ $email_id ] ) ) {
			return self::$emails[ $email_id ];
		}

		/**
		 * Filters the email template before it is retrieved.
		 *
		 * This allows for lazy registration of emails.
		 *
		 * @since 7.1.0
		 *
		 * @param array|null $email    The email template data.
		 * @param string     $email_id The email identifier.
		 */
		return apply_filters( "wp_mailer_get_email_{$email_id}", null, $email_id );
	}

	/**
	 * Renders a template with data.
	 *
	 * Supports {{mustache}} style placeholders.
	 *
	 * @since 7.1.0
	 *
	 * @param string $template The template string.
	 * @param array  $data     The data for replacement.
	 * @return string The rendered string.
	 */
	public static function render( $template, $data ) {
		return preg_replace_callback(
			'/{{(.*?)}}/',
			function( $matches ) use ( $data ) {
				$key = trim( $matches[ 1 ] );
				return isset( $data[ $key ] ) ? $data[ $key ] : $matches[ 0 ];
			},
			$template
		);
	}
}
