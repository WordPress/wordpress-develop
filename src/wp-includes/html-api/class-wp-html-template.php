<?php

class WP_HTML_Template {
	/**
	 * Wraps HTML in a class indicating that it was generated from within
	 * the HTML API. Used for safely merging HTML of varying provenance,
	 * where miscoordination could otherwise result in double-encoding.
	 *
	 * @since 7.0
	 * @access private
	 */
	private static $sentinel_class = null;

	/**
	 * @param string     $template
	 * @param array|null $args
	 * @return object Rendered and wrapped HTML.
	 */
	public static function compile( string $template, ?array $args = null ) {
		self::ensure_sentinel();

		$builder = new class ( '', WP_HTML_Processor::CONSTRUCTOR_UNLOCK_CODE ) extends WP_HTML_Processor {
			public $deferred_updates = array();

			public function get_raw_attribute( $name ) {
				$lexical_updates = $this->lexical_updates;
				$this->remove_attribute( $name );
				$span = $this->lexical_updates[ strtolower( $name ) ];
				$this->lexical_updates = $lexical_updates;
				$span->text = substr( $this->html, $span->start, $span->length );
				return $span;
			}

			public function raw_replace_token( $content ) {
				$this->set_bookmark( 'here' );
				$here = $this->bookmarks['_here'];
				$this->deferred_updates[] = new WP_HTML_Text_Replacement(
					$here->start,
					$here->length,
					$content
				);
			}

			public function set_attribute( $name, $value ): bool {
				if ( ! parent::set_attribute( $name, $value ) ) {
					return false;
				}
				$lower_name = strtolower( $name );
				$this->deferred_updates[ $lower_name ] = $this->lexical_updates[ $lower_name ];
				return true;
			}

			public function remove_attribute( $name ): bool {
				if ( ! parent::remove_attribute( $name ) ) {
					return false;
				}

				$lower_name = strtolower( $name );
				$this->deferred_updates[ $lower_name ] = $this->lexical_updates[ $lower_name ];
				return true;
			}
		};

		$processor   = $builder::create_fragment( $template );
		$bit_pattern = '%(?P<VAR>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)';

		$refers_to = function ( $input ) use ( $args, $bit_pattern ){
			if ( 1 === preg_match( "~^</{$bit_pattern}>$~", $input, $matches ) ) {
				return isset( $args[ $matches['VAR'] ] ) ? $matches['VAR'] : null;
			}

			return false;
		};

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();

			// Skip over entire elements when instructed.
			if ( '#tag' === $token_type ) {
				if ( is_string( $processor->get_attribute( 'data-wp-if' ) ) ) {
					$ignorable = $processor->get_raw_attribute( 'data-wp-if' )->text;
					$quote     = $ignorable[ strlen( $ignorable ) - 1 ];
					$ignorable = substr(
						$ignorable,
						strcspn( $ignorable, $quote ) + 1,
						-1
					);
					$processor->remove_attribute( 'data-wp-if' );
					$condition = $refers_to( $ignorable );

					if ( isset( $condition, $args[ $condition ] ) && in_array( $args[ $condition ], array( false, null, '' ), true ) ) {
						$depth = $processor->get_current_depth();
						while ( $processor->next_token() && $processor->get_current_depth() > $depth ) {
							continue;
						}

						continue;
					}
				}

				// Replace Bits in attributes and spread attributes.
				foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
					// Spread attributes which are boolean; don’t replace those with values.
					if ( str_starts_with( $name, '...' ) && true === $processor->get_attribute( $name ) ) {
						$processor->remove_attribute( $name );
						$spread_name = substr( $name, 3 );
						if ( isset( $spread_name, $args[ $spread_name ] ) && is_array( $args[ $spread_name ] ) ) {
							$spread_args = $args[ $spread_name ];
							foreach ( $spread_args as $arg_name => $arg_value ) {
								if ( is_string( $arg_name ) && ( true === $arg_value || is_string( $arg_value ) ) ) {
									$processor->set_attribute( $arg_name, $arg_value );
								} else if ( is_string( $arg_name ) && in_array( $arg_value, array( false, null ), true ) ) {
									$processor->remove_attribute( $arg_name );
								}
							}
						}

						continue;
					}

					$raw_attr = $processor->get_raw_attribute( $name )->text;
					$last_c   = $raw_attr[ strlen( $raw_attr ) - 1 ];

					// Bit syntax cannot appear in unquoted attributes.
					if ( '"' !== $last_c && "'" !== $last_c ) {
						continue;
					}

					$value   = substr( $raw_attr, strcspn( $raw_attr, $last_c ) + 1, -1 );
					$matches = null;
					$bits    = preg_match_all( "~</{$bit_pattern}>~", $value, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
					if ( 0 === $bits || false === $bits ) {
						continue;
					}

					$updated = array();
					$was_at  = 0;
					foreach ( $matches as $match ) {
						$updated[] = substr( $value, $was_at, $match[0][1] - $was_at );
						$was_at    = $match[0][1] + strlen( $match[0][0] );
						$arg_name  = $match['VAR'][0];

						if ( isset( $arg_name, $args[ $arg_name ] ) && is_string( $args[ $arg_name ] ) ) {
							$updated[] = self::escape( $name, $args[ $arg_name ] );
						}
					}

					$updated[] = substr( $value, $was_at );
					$decoded   = WP_HTML_Decoder::decode_attribute( implode( '', $updated ) );
					if ( ! $processor->set_attribute( $name, $decoded ) ) {
						$processor->remove_attribute( $name );
					}
				}
			}

			if ( '#funky-comment' === $token_type ) {
				$text = $processor->get_modifiable_text();
				if ( 1 !== preg_match( "~^{$bit_pattern}$~", $text, $match ) ) {
					continue;
				}

				if ( isset( $match['VAR'], $args[ $match['VAR'] ] ) && is_string( $args[ $match['VAR'] ] ) ) {
					$processor->raw_replace_token( self::escape( null, $args[ $match['VAR'] ] ) );
				}
			}
		}

		return self::$sentinel_class::wrap( $template, $processor->deferred_updates );
	}

	public static function render( $compiled ): string {
		return $compiled->unwrap();
	}

	private static function escape( $attr_name, string $plaintext ): string {
		if ( isset( $attr_name ) && in_array( strtolower( $attr_name ), wp_kses_uri_attributes(), true ) ) {
			return esc_url( $plaintext );
		}

		return strtr(
			$plaintext,
			array(
				'<' => '&lt;',
				'>' => '&gt;',
				'&' => '&amp;',
				'"' => '&quot;',
				"'" => '&apos;',
			)
		);
	}

	/**
	 * Ensures that the sentinel class is dynamically generated at boot.
	 * This class is to never be serialized or instantiated outside of
	 * this parent class.
	 *
	 * @since 7.0
	 */
	private static function ensure_sentinel(): void {
		if ( isset( self::$sentinel_class ) ) {
			return;
		}

		self::$sentinel_class = new class () {
			private $html = '';

			private $updates = array();

			public static function wrap( string $html, array $updates ): self {
				$wrapper = new self();
				$wrapper->html = $html;
				$wrapper->updates = $updates;
				return $wrapper;
			}

			public function unwrap(): string {
				$processor = new class( $this->html ) extends WP_HTML_Tag_Processor {
					public function flood( $updates ) {
						$this->lexical_updates = $updates;
					}
				};

				$processor->flood( $this->updates );
				return $processor->get_updated_html();
			}
		};
	}
}
