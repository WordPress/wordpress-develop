<?php
/**
 * Invariants of the database layer.
 *
 * prepared-structure and prepared-reaches-mysql share the queries which
 * wpdb::prepare() returned in the running test, through WP_Invariant::shared().
 * prepared-parse-tree and queries-are-valid-mysql parse queries with the MySQL
 * parser from WordPress/sqlite-database-integration, as an independent oracle.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

$prepared = WP_Invariant::shared( 'wpdb/prepared' );

return array(
	WP_Invariant::define( 'prepared-structure' )
		->describe( 'What wpdb::prepare() returns has the structure of its query template: the values it fills in add no SQL of their own.' )
		->after(
			'wpdb::prepare',
			static function ( WP_Invariant_Call $call ) use ( $prepared ) {
				$result = $call->return_value();
				if ( ! is_string( $result ) || '' === $result ) {
					return;
				}

				// The query as MySQL will receive it: the query filter removes the escapes of literal percent signs.
				$query    = $call->self()->remove_placeholder_escape( $result );
				$template = $call->arg( 'query' );
				$tokens   = WP_Invariant_SQL_Lexer::tokens( $query );
				$call->expect(
					WP_Invariant_SQL_Lexer::same_structure(
						WP_Invariant_SQL_Lexer::structure( WP_Invariant_SQL_Lexer::tokens( $template, true ) ),
						WP_Invariant_SQL_Lexer::structure( $tokens )
					),
					'wpdb::prepare( %s ) returned %s.',
					$template,
					$query
				);

				// Keep the latest 256 for prepared-reaches-mysql, oldest first.
				if ( ! isset( $prepared->queries ) ) {
					$prepared->queries = array();
				}
				unset( $prepared->queries[ $query ] );
				$prepared->queries[ $query ] = $tokens;
				if ( count( $prepared->queries ) > 256 ) {
					unset( $prepared->queries[ array_key_first( $prepared->queries ) ] );
				}
			}
		),

	WP_Invariant::define( 'prepared-reaches-mysql' )
		->describe( 'A query returned by wpdb::prepare() reaches MySQL intact: where it appears in a query sent to MySQL, it lexes the same there, and does not break into or out of a string, quoted identifier or comment. Text which only matches part of a number or a name, such as `= 1` in `= 11`, is another query.' )
		->before(
			'wpdb::_do_query',
			static function ( WP_Invariant_Call $call ) use ( $prepared ) {
				if ( empty( $prepared->queries ) ) {
					return;
				}

				$sql    = $call->arg( 'query' );
				$tokens = null;
				foreach ( $prepared->queries as $query => $query_tokens ) {
					if ( array() === $query_tokens || false === strpos( $sql, $query ) ) {
						continue;
					}

					if ( null === $tokens ) {
						$tokens = WP_Invariant_SQL_Lexer::tokens( $sql );
					}

					$appearances = WP_Invariant_SQL_Lexer::appearances( $sql, $tokens, $query, $query_tokens );
					if ( array() !== $appearances ) {
						$call->expect( ! in_array( false, $appearances, true ), 'The prepared query %s does not lex the same inside %s.', $query, $sql );
					}
				}
			}
		),

	WP_Invariant::define( 'prepared-parse-tree' )
		->describe( 'Parsed with MySQL\'s grammar, what wpdb::prepare() returns has the same parse tree as its template filled with harmless values: the values it fills in change no SQL. Templates which are not whole statements are left to prepared-structure.' )
		->state(
			static function () {
				return array( 'templates' => array() );
			},
			'run'
		)
		->after(
			'wpdb::prepare',
			static function ( WP_Invariant_Call $call, array &$state ) {
				$result   = $call->return_value();
				$template = $call->arg( 'query' );
				if ( ! is_string( $result ) || '' === $result || ! is_string( $template ) ) {
					return;
				}

				// The same templates are prepared over and over: keep the parse trees of the latest 2,000.
				if ( ! array_key_exists( $template, $state['templates'] ) ) {
					$state['templates'][ $template ] = WP_Invariant_MySQL::parse_tree( WP_Invariant_SQL_Lexer::fill_template( $template ) );
					if ( count( $state['templates'] ) > 2000 ) {
						unset( $state['templates'][ array_key_first( $state['templates'] ) ] );
					}
				}
				$expected = $state['templates'][ $template ];
				if ( null === $expected ) {
					return;
				}

				$query = $call->self()->remove_placeholder_escape( $result );
				$call->expect( WP_Invariant_MySQL::parse_tree( $query ) === $expected, 'wpdb::prepare( %s ) returned %s, which MySQL parses differently.', $template, $query );
			}
		),

	WP_Invariant::define( 'queries-are-valid-mysql' )
		->describe( 'Every query sent to MySQL is valid MySQL 8.4, as MySQL\'s grammar parses it.' )
		->include_test_callers()
		->before(
			'wpdb::_do_query',
			static function ( WP_Invariant_Call $call ) {
				$query = $call->arg( 'query' );
				$call->expect( ! is_string( $query ) || null !== WP_Invariant_MySQL::parse_tree( $query ), 'MySQL would reject this query as invalid: %s', $query );
			}
		),

	WP_Invariant::define( 'query-is-counted' )
		->describe( 'A successful wpdb::query() adds at least 1 to $wpdb->num_queries. It can add more when it runs queries of its own.' )
		->before(
			'wpdb::query',
			static function ( WP_Invariant_Call $call ) {
				return $call->self()->num_queries;
			}
		)
		->after(
			'wpdb::query',
			static function ( WP_Invariant_Call $call ) {
				if ( false === $call->return_value() ) {
					return;
				}

				$call->expect(
					$call->self()->num_queries > $call->before(),
					'wpdb::query() succeeded, but num_queries went from %d to %d.',
					$call->before(),
					$call->self()->num_queries
				);
			}
		),

	WP_Invariant::define( 'tables-are-prefixed' )
		->describe( 'Every table a query sent to MySQL names, as MySQL\'s grammar parses it, starts with $wpdb->prefix or $wpdb->base_prefix, or is in information_schema, or is CUSTOM_USER_TABLE or CUSTOM_USER_META_TABLE.' )
		->before(
			'wpdb::_do_query',
			static function ( WP_Invariant_Call $call ) {
				$query = $call->arg( 'query' );
				if ( ! is_string( $query ) ) {
					return;
				}

				// Parsed once for this and queries-are-valid-mysql; a query MySQL would reject is left to that.
				$tables = WP_Invariant_MySQL::table_names( $query );
				if ( empty( $tables ) ) {
					return;
				}

				$wpdb     = $call->self();
				// An empty prefix, which WordPress allows, is the start of every name.
				$prefixes = array_unique( array( strtolower( (string) $wpdb->prefix ), strtolower( (string) $wpdb->base_prefix ) ) );
				$custom   = array();
				foreach ( array( 'CUSTOM_USER_TABLE', 'CUSTOM_USER_META_TABLE' ) as $constant ) {
					if ( defined( $constant ) ) {
						$custom[] = strtolower( constant( $constant ) );
					}
				}

				foreach ( $tables as list( $database, $table ) ) {
					$table = strtolower( $table );
					if ( 'information_schema' === strtolower( (string) $database ) || in_array( $table, $custom, true ) ) {
						continue;
					}

					$prefixed = false;
					foreach ( $prefixes as $prefix ) {
						$prefixed = $prefixed || 0 === strpos( $table, $prefix );
					}
					if ( ! $call->expect( $prefixed, 'The query names the table %s, without the prefix %s: %s', null === $database ? $table : "{$database}.{$table}", implode( ' or ', $prefixes ), $query ) ) {
						return;
					}
				}
			}
		),

	WP_Invariant::define( 'request-values-stay-literals' )
		->describe( 'A value the request sent, where it appears in a query sent to MySQL, is inside one string literal or quoted identifier there: it adds no SQL of its own, whether or not the query went through wpdb::prepare(). Values shorter than 4 bytes, or without a quote, backslash or backtick, are not followed. Each value is reported once.' )
		->include_test_callers()
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->before(
			'wpdb::_do_query',
			static function ( WP_Invariant_Call $call, array &$reported ) {
				$sql = $call->arg( 'query' );
				if ( ! is_string( $sql ) ) {
					return;
				}

				$tokens = null;
				foreach ( WP_Invariant_Request::values( "'\"\\`" ) as $value ) {
					if ( isset( $reported[ $value ] ) ) {
						continue;
					}

					$length = strlen( $value );
					foreach ( WP_Invariant_Request::occurrences( $sql, $value ) as $at ) {
						if ( null === $tokens ) {
							$tokens = WP_Invariant_SQL_Lexer::tokens( $sql );
						}

						$inside = false;
						foreach ( $tokens as $token ) {
							if ( $token['start'] <= $at && $at + $length <= $token['end'] ) {
								// Strictly inside the quotes, not including them.
								$inside = ( 'string' === $token['type'] || 'ident' === $token['type'] ) && $token['start'] < $at && $at + $length < $token['end'];
								break;
							}
						}

						if ( ! $inside ) {
							$reported[ $value ] = true;
							$call->expect( false, 'The request value %s appears in a query outside a string literal: %s', $value, $sql );
							continue 2;
						}
					}
				}
			}
		),
);
