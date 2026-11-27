<?php
/**
 * WordPress mailer class.
 *
 * @package WordPress
 * @since 7.2.0
 */

/**
 * WordPress mailer class.
 *
 * Extends WP_PHPMailer to provide the default values and the pre-send
 * routines that wp_mail() relies on, so that plugins can obtain a fully
 * configured mailer and use the methods that wp_mail() does not expose.
 *
 * @since 7.2.0
 */
class WPMailer extends WP_PHPMailer {

	/**
	 * Sets up the mailer from the wp_mail() arguments.
	 *
	 * Resets any stale state, parses the headers, resolves the From address and
	 * name, and populates the mailer with the recipients, subject, body,
	 * content type, charset, custom headers, attachments, and embeds.
	 *
	 * @since 7.2.0
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $mailer The mailer instance to configure.
	 * @param array $atts {
	 *     Array of the `wp_mail()` arguments.
	 *
	 *     @type string|string[] $to          Array or comma-separated list of email addresses to send message.
	 *     @type string          $subject     Email subject.
	 *     @type string          $message     Message contents.
	 *     @type string|string[] $headers     Additional headers.
	 *     @type string|string[] $attachments Paths to files to attach.
	 *     @type string|string[] $embeds      Paths to files to embed.
	 * }
	 * @throws PHPMailer\PHPMailer\Exception If the From address is invalid.
	 *
	 * @return array The mail data, with the headers, attachments, and embeds
	 *               normalized to arrays, as used by the sending routines.
	 */
	public static function set_up( $mailer, $atts ) {
		$to          = $atts['to'];
		$subject     = $atts['subject'];
		$message     = $atts['message'];
		$headers     = $atts['headers'];
		$attachments = $atts['attachments'];
		$embeds      = $atts['embeds'];

		// Headers.
		$cc       = array();
		$bcc      = array();
		$reply_to = array();

		if ( empty( $headers ) ) {
			$headers = array();
		} else {
			if ( ! is_array( $headers ) ) {
				/*
				 * Explode the headers out, so this function can take
				 * both string headers and an array of headers.
				 */
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			} else {
				$tempheaders = $headers;
			}
			$headers = array();

			// If it's actually got contents.
			if ( ! empty( $tempheaders ) ) {
				// Iterate through the raw headers.
				foreach ( (array) $tempheaders as $header ) {
					if ( ! str_contains( $header, ':' ) ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts    = preg_split( '/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
						}
						continue;
					}
					// Explode them out.
					list( $name, $content ) = explode( ':', trim( $header ), 2 );

					// Cleanup crew.
					$name    = trim( $name );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						// Mainly for legacy -- process a "From:" header if it's there.
						case 'from':
							$bracket_pos = strpos( $content, '<' );
							if ( false !== $bracket_pos ) {
								// Text before the bracketed email is the "From" name.
								if ( $bracket_pos > 0 ) {
									$from_name = substr( $content, 0, $bracket_pos );
									$from_name = str_replace( '"', '', $from_name );
									$from_name = trim( $from_name );
								}

								$from_email = substr( $content, $bracket_pos + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );

								// Avoid setting an empty $from_email.
							} elseif ( '' !== trim( $content ) ) {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( str_contains( $content, ';' ) ) {
								list( $type, $charset_content ) = explode( ';', $content );
								$content_type                   = trim( $type );
								if ( false !== stripos( $charset_content, 'charset=' ) ) {
									$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
								} elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
									$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
									$charset  = '';
									if ( preg_match( '~^multipart/(\S+)~', $content_type, $matches ) ) {
										$content_type = 'multipart/' . strtolower( $matches[1] ) . '; boundary="' . $boundary . '"';
									}
								}

								// Avoid setting an empty $content_type.
							} elseif ( '' !== trim( $content ) ) {
								$content_type = trim( $content );
							}
							break;
						case 'cc':
							$cc = array_merge( (array) $cc, explode( ',', $content ) );
							break;
						case 'bcc':
							$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
							break;
						case 'reply-to':
							$reply_to = array_merge( (array) $reply_to, explode( ',', $content ) );
							break;
						default:
							// Add it to our grand headers array.
							$headers[ trim( $name ) ] = trim( $content );
							break;
					}
				}
			}
		}

		// Empty out the values that may be set.
		$mailer->clearAllRecipients();
		$mailer->clearAttachments();
		$mailer->clearCustomHeaders();
		$mailer->clearReplyTos();
		$mailer->Body    = '';
		$mailer->AltBody = '';

		/*
		 * Reset encoding to 8-bit, as it may have been automatically downgraded
		 * to 7-bit by PHPMailer (based on the body contents) in a previous call
		 * to wp_mail().
		 *
		 * See https://core.trac.wordpress.org/ticket/33972
		 */
		$mailer->Encoding = PHPMailer\PHPMailer\PHPMailer::ENCODING_8BIT;

		// Set "From" name and email.

		// If we don't have a name from the input headers.
		if ( ! isset( $from_name ) ) {
			$from_name = 'WordPress';
		}

		/*
		 * If we don't have an email from the input headers, default to wordpress@$sitename
		 * Some hosts will block outgoing mail from this address if it doesn't exist,
		 * but there's no easy alternative. Defaulting to admin_email might appear to be
		 * another option, but some hosts may refuse to relay mail from an unknown domain.
		 * See https://core.trac.wordpress.org/ticket/5007.
		 */
		if ( ! isset( $from_email ) ) {
			// Get the site domain and get rid of www.
			$sitename   = wp_parse_url( network_home_url(), PHP_URL_HOST );
			$from_email = 'wordpress@';

			if ( null !== $sitename ) {
				if ( str_starts_with( $sitename, 'www.' ) ) {
					$sitename = substr( $sitename, 4 );
				}

				$from_email .= $sitename;
			}
		}

		/**
		 * Filters the email address to send from.
		 *
		 * @since 2.2.0
		 *
		 * @param string $from_email Email address to send from.
		 */
		$from_email = apply_filters( 'wp_mail_from', $from_email );

		/**
		 * Filters the name to associate with the "from" email address.
		 *
		 * @since 2.3.0
		 *
		 * @param string $from_name Name associated with the "from" email address.
		 */
		$from_name = apply_filters( 'wp_mail_from_name', $from_name );

		$mailer->setFrom( $from_email, $from_name, false );

		// Set mail's subject and body.
		$mailer->Subject = $subject;
		$mailer->Body    = $message;

		// Set destination addresses, using appropriate methods for handling addresses.
		$address_headers = compact( 'to', 'cc', 'bcc', 'reply_to' );

		foreach ( $address_headers as $address_header => $addresses ) {
			if ( empty( $addresses ) ) {
				continue;
			}

			foreach ( (array) $addresses as $address ) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>".
					$recipient_name = '';

					if ( preg_match( '/(.*)<(.+)>/', $address, $matches ) ) {
						if ( count( $matches ) === 3 ) {
							$recipient_name = $matches[1];
							$address        = $matches[2];
						}
					}

					switch ( $address_header ) {
						case 'to':
							$mailer->addAddress( $address, $recipient_name );
							break;
						case 'cc':
							$mailer->addCC( $address, $recipient_name );
							break;
						case 'bcc':
							$mailer->addBCC( $address, $recipient_name );
							break;
						case 'reply_to':
							$mailer->addReplyTo( $address, $recipient_name );
							break;
					}
				} catch ( PHPMailer\PHPMailer\Exception $e ) {
					continue;
				}
			}
		}

		// Set to use PHP's mail().
		$mailer->isMail();

		// Set Content-Type and charset.

		// If we don't have a Content-Type from the input headers.
		if ( ! isset( $content_type ) ) {
			$content_type = 'text/plain';
		}

		/**
		 * Filters the wp_mail() content type.
		 *
		 * @since 2.3.0
		 *
		 * @param string $content_type Default wp_mail() content type.
		 */
		$content_type = apply_filters( 'wp_mail_content_type', $content_type );

		$mailer->ContentType = $content_type;

		// Set whether it's plaintext, depending on $content_type.
		if ( 'text/html' === $content_type ) {
			$mailer->isHTML( true );
		}

		// If we don't have a charset from the input headers.
		if ( ! isset( $charset ) ) {
			$charset = get_bloginfo( 'charset' );
		}

		/**
		 * Filters the default wp_mail() charset.
		 *
		 * @since 2.3.0
		 *
		 * @param string $charset Default email charset.
		 */
		$mailer->CharSet = apply_filters( 'wp_mail_charset', $charset );

		// Set custom headers.
		if ( ! empty( $headers ) ) {
			foreach ( (array) $headers as $name => $content ) {
				// Only add custom headers not added automatically by PHPMailer.
				if ( ! in_array( $name, array( 'MIME-Version', 'X-Mailer' ), true ) ) {
					try {
						$mailer->addCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
					} catch ( PHPMailer\PHPMailer\Exception $e ) {
						continue;
					}
				}
			}
		}

		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $filename => $attachment ) {
				$filename = is_string( $filename ) ? $filename : '';

				try {
					$mailer->addAttachment( $attachment, $filename );
				} catch ( PHPMailer\PHPMailer\Exception $e ) {
					continue;
				}
			}
		}

		if ( ! empty( $embeds ) ) {
			foreach ( $embeds as $key => $embed_path ) {
				/**
				 * Filters the arguments for PHPMailer's addEmbeddedImage() method.
				 *
				 * @since 6.9.0
				 *
				 * @param array $args {
				 *     An array of arguments for PHPMailer's addEmbeddedImage() method.
				 *
				 *     @type string $path        The path to the file.
				 *     @type string $cid         The Content-ID of the image. Default: The key in the embeds array.
				 *     @type string $name        The filename of the image.
				 *     @type string $encoding    The encoding of the image. Default: 'base64'.
				 *     @type string $type        The MIME type of the image. Default: empty string, which lets PHPMailer auto-detect.
				 *     @type string $disposition The disposition of the image. Default: 'inline'.
				 * }
				 */
				$embed_args = apply_filters(
					'wp_mail_embed_args',
					array(
						'path'        => $embed_path,
						'cid'         => (string) $key,
						'name'        => basename( $embed_path ),
						'encoding'    => 'base64',
						'type'        => '',
						'disposition' => 'inline',
					)
				);

				try {
					$mailer->addEmbeddedImage(
						$embed_args['path'],
						$embed_args['cid'],
						$embed_args['name'],
						$embed_args['encoding'],
						$embed_args['type'],
						$embed_args['disposition']
					);
				} catch ( PHPMailer\PHPMailer\Exception $e ) {
					continue;
				}
			}
		}

		return compact( 'to', 'subject', 'message', 'headers', 'attachments', 'embeds' );
	}

	/**
	 * Prepares the message for sending.
	 *
	 * Fires the {@see 'phpmailer_init'} action before the parent method runs.
	 *
	 * @since 7.2.0
	 *
	 * @return bool Whether the message was prepared successfully.
	 */
	public function preSend() {
		/**
		 * Fires after PHPMailer is initialized.
		 *
		 * @since 2.2.0
		 *
		 * @param PHPMailer $phpmailer The PHPMailer instance (passed by reference).
		 */
		do_action_ref_array( 'phpmailer_init', array( &$this ) );

		return parent::preSend();
	}
}
