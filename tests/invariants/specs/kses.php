<?php
/**
 * Invariants of wp_kses(), checked against how a browser reads its output.
 *
 * These hook htmlspecialchars() and htmlentities(), built-in PHP functions,
 * which slows down every call to a built-in function. Leave them out with
 * WP_INVARIANTS_SKIP=kses.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// What wp_kses_allowed_html() and wp_kses_uri_attributes() last returned, as kses read them.
$kses = WP_Invariant::shared( 'kses/settings' );

// Elements a browser adds by itself, such as the TBODY around table rows.
$implied = array(
	'html'     => true,
	'head'     => true,
	'body'     => true,
	'tbody'    => true,
	'colgroup' => true,
);

/*
 * Where HTML next has an escaped tag or comment: a character reference for `<`, such as
 * `&lt;`, `&#60;` or `&#x3C;`, before a letter, `/` and a letter, or `!--`. It is
 * found without PCRE, which would reset preg_last_error() for the code under test.
 */
$escaped_tag = static function ( $html, $from ) {
	for ( $at = strpos( $html, '&', $from ); false !== $at; $at = strpos( $html, '&', $at + 1 ) ) {
		$end = null;
		if ( '&lt;' === substr( $html, $at, 4 ) || '&LT;' === substr( $html, $at, 4 ) ) {
			$end = $at + 4;
		} elseif ( '&#' === substr( $html, $at, 2 ) ) {
			$hex    = isset( $html[ $at + 2 ] ) && ( 'x' === $html[ $at + 2 ] || 'X' === $html[ $at + 2 ] );
			$digits = $at + ( $hex ? 3 : 2 );
			$digits = $digits + strspn( $html, '0', $digits );
			if ( strtolower( substr( $html, $digits, 2 ) ) !== ( $hex ? '3c' : '60' ) ) {
				continue;
			}

			// Without a semicolon, the reference takes every digit which follows, as in `&#x3cb`.
			$end = $digits + 2;
			if ( isset( $html[ $end ] ) && ';' === $html[ $end ] ) {
				++$end;
			} elseif ( strspn( $html, $hex ? '0123456789abcdefABCDEF' : '0123456789', $end, 1 ) ) {
				continue;
			}
		}

		$next = null === $end ? '' : substr( $html, $end, 3 );
		if ( '' !== $next && ( ctype_alpha( $next[0] ) || ( '/' === $next[0] && isset( $next[1] ) && ctype_alpha( $next[1] ) ) || '!--' === $next ) ) {
			return array( $at, substr( $html, $at, $end - $at ) . $next );
		}
	}

	return null;
};

/*
 * Returns what makes HTML unsafe which kses would filter out of it: an element
 * or comment it removes, an event handler attribute, or a URL with a scheme it
 * does not allow. Returns null when kses would only change what is harmless.
 */
$unsafe_html = static function ( $html, $filtered ) {
	$before = WP_Invariant_HTML_API::markup( $html );
	$after  = WP_Invariant_HTML_API::markup( $filtered );
	if ( null === $before || null === $after ) {
		return null;
	}
	foreach ( $before as $kind => $count ) {
		if ( $count > ( isset( $after[ $kind ] ) ? $after[ $kind ] : 0 ) ) {
			return "it has {$kind} markup, which kses removes";
		}
	}

	$uri_attributes = wp_kses_uri_attributes();
	$protocols      = wp_allowed_protocols();
	$processor      = new WP_HTML_Tag_Processor( $html );
	while ( $processor->next_tag() ) {
		$handlers = $processor->get_attribute_names_with_prefix( 'on' );
		if ( ! empty( $handlers ) ) {
			return sprintf( 'its %s tag has the event handler %s', $processor->get_tag(), $handlers[0] );
		}
		foreach ( $uri_attributes as $attribute ) {
			$url = $processor->get_attribute( $attribute );
			if ( is_string( $url ) && wp_kses_bad_protocol( $url, $protocols ) !== $url ) {
				return sprintf( 'its %s tag has the URL %s, with a scheme kses does not allow', $processor->get_tag(), $url );
			}
		}
	}

	return null;
};

/*
 * Whether test code called an API which stores what it is given without
 * filtering it, by design, such as wp_insert_comment(), which wp_new_comment()
 * calls once it has filtered the comment.
 */
$raw_from_tests = static function () {
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
		if ( 'wp_insert_comment' === $frame['function'] && ! isset( $frame['class'] ) ) {
			return isset( $frame['file'] ) && WP_Invariant::is_test_file( $frame['file'] );
		}
	}

	return false;
};


// What HTML escaping functions returned in the running test with escaped markup in it: output => function.
$escaped = WP_Invariant::shared( 'kses/escaped' );

// Most outputs kept; past that, the oldest are forgotten.
$most_escaped = 200;

// PHP's and WordPress's functions which escape HTML.
$escapers = array(
	'htmlspecialchars' => true,
	'htmlentities'     => true,
	'esc_html'         => true,
	'esc_attr'         => true,
	'esc_textarea'     => true,
	'esc_xml'          => true,
	'_wp_specialchars' => true,
);

return array(
	WP_Invariant::define( 'input-not-escaped' )
		->describe( 'wp_kses() is given HTML, not HTML which WordPress escaped first: the text of its content has no escaped tag or comment, such as &lt;p or &#60;/p, which an HTML escaping function returned, whether PHP\'s htmlspecialchars() or htmlentities(), or WordPress\'s esc_html(), esc_attr(), esc_textarea(), esc_xml() or _wp_specialchars(). Markup escaped before it is filtered shows up as text. Escaped markup which content arrived with, as a user may type it, is left out, as are attribute values and the contents of elements such as TEXTAREA, which are escaped as they should be.' )
		->after_each(
			array_keys( $escapers ),
			static function ( WP_Invariant_Call $call ) use ( $escaped, $escaped_tag, $most_escaped ) {
				$output = $call->return_value();
				if ( ! is_string( $output ) || false === strpos( $output, '&' ) || null === $escaped_tag( $output, 0 ) ) {
					return;
				}

				if ( ! isset( $escaped->outputs ) ) {
					$escaped->outputs = array();
				}
				unset( $escaped->outputs[ $output ] );
				$escaped->outputs[ $output ] = $call->target();
				if ( count( $escaped->outputs ) > $most_escaped ) {
					unset( $escaped->outputs[ array_key_first( $escaped->outputs ) ] );
				}
			}
		)
		->before(
			'wp_kses',
			static function ( WP_Invariant_Call $call ) use ( $escaped, $escaped_tag ) {
				$content = $call->arg( 'content' );
				if ( empty( $escaped->outputs ) || ! is_string( $content ) || false === strpos( $content, '&' ) ) {
					return;
				}

				$texts = null;
				for ( $found = $escaped_tag( $content, 0 ); null !== $found; $found = $escaped_tag( $content, $found[0] + 1 ) ) {
					// Only escaped markup an escaping function returned counts, wherever it appears in the content.
					$escaper = null;
					foreach ( $escaped->outputs as $output => $function ) {
						$output = (string) $output;
						foreach ( WP_Invariant_Request::occurrences( $content, $output ) as $at ) {
							if ( $found[0] >= $at && $found[0] < $at + strlen( $output ) ) {
								$escaper = $function;
								break 2;
							}
						}
					}
					if ( null === $escaper ) {
						continue;
					}

					$texts = null === $texts ? WP_Invariant_HTML_API::texts( $content ) : $texts;
					foreach ( (array) $texts as $text ) {
						if ( $found[0] >= $text[0] && $found[0] < $text[1] ) {
							$call->expect( false, 'wp_kses() was given the escaped markup %s, which %s() escaped, at byte %d of %s', $found[1], $escaper, $found[0], substr( $content, max( 0, $found[0] - 40 ), 120 ) );
							return;
						}
					}
				}
			}
		),

	WP_Invariant::define( 'allowed-markup-only' )
		->describe( 'Read as a browser reads it, what wp_kses() returns has only elements its allowed HTML allows, no event handler attributes unless allowed, and no URL with a scheme outside its allowed protocols.' )
		->include_test_callers()
		->after(
			'wp_kses_allowed_html',
			static function ( WP_Invariant_Call $call ) use ( $kses ) {
				$context = $call->arg( 'context' );
				if ( is_string( $context ) ) {
					$kses->allowed[ $context ] = $call->return_value();
				}
			}
		)
		->after(
			'wp_kses_uri_attributes',
			static function ( WP_Invariant_Call $call ) use ( $kses ) {
				$kses->uri_attributes = array_fill_keys( array_map( 'strtolower', (array) $call->return_value() ), true );
			}
		)
		->after(
			'wp_kses',
			static function ( WP_Invariant_Call $call ) use ( $kses, $implied ) {
				$output = $call->return_value();
				if ( ! is_string( $output ) || false === strpos( $output, '<' ) ) {
					return;
				}

				// A context name is looked up as kses looked it up, without running its filter again.
				$allowed = $call->arg( 'allowed_html' );
				if ( is_string( $allowed ) ) {
					$allowed = isset( $kses->allowed[ $allowed ] ) ? $kses->allowed[ $allowed ] : null;
				}
				if ( is_array( $allowed ) ) {
					$allowed = array_change_key_case( $allowed, CASE_LOWER );
				}

				$protocols = $call->arg( 'allowed_protocols' );
				if ( empty( $protocols ) ) {
					$protocols = wp_allowed_protocols();
				}
				$protocols = array_map( 'strtolower', $protocols );

				foreach ( WP_Invariant_Browser::elements( $output ) as $element ) {
					$tag = WP_Invariant_Browser::tag_name( $element );
					if ( isset( $implied[ $tag ] ) ) {
						continue;
					}

					if ( is_array( $allowed ) && ! $call->expect( isset( $allowed[ $tag ] ), 'wp_kses() let through a %s element: %s', $tag, $output ) ) {
						return;
					}

					$allowed_attributes = is_array( $allowed ) && is_array( $allowed[ $tag ] ) ? array_change_key_case( $allowed[ $tag ], CASE_LOWER ) : array();
					foreach ( $element->attributes as $attribute ) {
						$name = strtolower( $attribute->name );
						if ( 0 === strpos( $name, 'on' ) && empty( $allowed_attributes[ $name ] ) ) {
							$call->expect( false, 'wp_kses() let through the event handler %s on a %s element: %s', $name, $tag, $output );
							return;
						}

						if ( isset( $kses->uri_attributes[ $name ] ) ) {
							$scheme = WP_Invariant_Browser::url_scheme( $attribute->value );
							if ( null !== $scheme && ! $call->expect( in_array( $scheme, $protocols, true ), 'wp_kses() let through a %s URL in %s: %s', $scheme, $name, $output ) ) {
								return;
							}
						}
					}
				}
			}
		),

	WP_Invariant::define( 'idempotent-when-reapplied' )
		->describe( 'Given something it returned earlier in the test, wp_kses() returns it unchanged, when its allowed HTML and protocols are the same.' )
		->include_test_callers()
		->state(
			static function () {
				return array();
			},
			'test'
		)
		->after(
			'wp_kses',
			static function ( WP_Invariant_Call $call, array &$outputs ) {
				$input  = $call->arg( 'content' );
				$output = $call->return_value();
				if ( ! is_string( $input ) || ! is_string( $output ) ) {
					return;
				}

				$allowed = $call->arg( 'allowed_html' );
				$key     = md5( serialize( array( is_string( $allowed ) ? $allowed : (array) $allowed, $call->arg( 'allowed_protocols' ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Only to compare arguments.
				if ( isset( $outputs[ $key ][ $input ] ) ) {
					$call->expect( $output === $input, 'wp_kses() changed what it had returned before, %s, to %s.', $input, $output );
				}

				unset( $outputs[ $key ][ $output ] );
				$outputs[ $key ][ $output ] = true;
				if ( count( $outputs[ $key ] ) > 256 ) {
					unset( $outputs[ $key ][ array_key_first( $outputs[ $key ] ) ] );
				}
			}
		),

	WP_Invariant::define( 'stored-html-is-filtered' )
		->describe( 'While WordPress filters a field with kses, which it does for users without unfiltered_html, what it stores in that field is already safe by kses: wp_kses() would remove no element or comment from it, and it has no event handler attribute and no URL with a scheme kses does not allow. Attributes WordPress adds after kses, such as rel on links in comments, do not count, and neither do comments tests store with wp_insert_comment(), which stores what it is given. That holds for the content, excerpt and title of posts, the content of comments, the descriptions of terms and the description of users, however they reach the database. Each path through WordPress is reported once for each field.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->before_each(
			array( 'wpdb::insert', 'wpdb::update' ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $unsafe_html, $raw_from_tests ) {
				$wpdb  = $call->self();
				$table = $call->arg( 'table' );
				$data  = $call->arg( 'data' );
				if ( ! is_array( $data ) || ! is_string( $table ) ) {
					return;
				}

				// Fields kses filters as they are saved: field => the filter which saves it.
				$fields = array();
				if ( $table === $wpdb->posts ) {
					$fields = array(
						'post_content' => 'content_save_pre',
						'post_excerpt' => 'excerpt_save_pre',
						'post_title'   => 'title_save_pre',
					);
				} elseif ( $table === $wpdb->comments ) {
					$fields = array( 'comment_content' => 'pre_comment_content' );
				} elseif ( $table === $wpdb->term_taxonomy ) {
					$fields = array( 'description' => 'pre_term_description' );
				} elseif ( $table === $wpdb->usermeta ) {
					$where = 'wpdb::update' === $call->target() ? $call->arg( 'where' ) : $data;
					if ( is_array( $where ) && isset( $where['meta_key'] ) && 'description' === $where['meta_key'] ) {
						$fields = array( 'meta_value' => 'pre_user_description' );
					}
				}

				foreach ( $fields as $field => $filter ) {
					if ( ! isset( $data[ $field ] ) || ! is_string( $data[ $field ] ) || false === strpbrk( $data[ $field ], '<>&' ) ) {
						continue;
					}

					// wp_filter_kses() filters for the filter it runs in, and wp_filter_post_kses() as post content.
					if ( false !== has_filter( $filter, 'wp_filter_post_kses' ) ) {
						$context = 'post';
					} elseif ( false !== has_filter( $filter, 'wp_filter_kses' ) ) {
						$context = $filter;
					} else {
						continue;
					}

					$value    = $data[ $field ];
					$filtered = wp_kses( $value, $context );
					$unsafe   = $filtered === $value ? null : $unsafe_html( $value, $filtered );
					if ( null === $unsafe || $raw_from_tests() ) {
						continue;
					}

					$path = implode( ' < ', $call->stack( 4 ) );
					if ( isset( $reported[ "{$table}.{$field} {$path}" ] ) ) {
						continue;
					}
					$reported[ "{$table}.{$field} {$path}" ] = true;

					$call->expect( false, '%s stored HTML in %s.%s which kses would filter, while the %s filter applies kses: %s. It stored %s', $call->target(), $table, $field, $filter, $unsafe, $value );
				}
			}
		),
);
