<?php
/**
 * Invariants of the database layer.
 *
 * prepared-structure and prepared-reaches-mysql share the queries which
 * wpdb::prepare() returned in the running test, through WP_Invariant::shared().
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
		->describe( 'A query returned by wpdb::prepare() reaches MySQL intact: where it appears in a query sent to MySQL, it starts and ends on that query\'s tokens and lexes the same there.' )
		->before(
			'wpdb::_do_query',
			static function ( WP_Invariant_Call $call ) use ( $prepared ) {
				if ( empty( $prepared->queries ) ) {
					return;
				}

				$sql    = $call->arg( 'query' );
				$tokens = null;
				foreach ( $prepared->queries as $query => $query_tokens ) {
					$at = array() === $query_tokens ? false : strpos( $sql, $query );
					if ( false === $at ) {
						continue;
					}

					if ( null === $tokens ) {
						$tokens = WP_Invariant_SQL_Lexer::tokens( $sql );
					}

					// The span of the prepared query's tokens in the query sent to MySQL.
					$start = $at + $query_tokens[0]['start'];
					$end   = $at + $query_tokens[ count( $query_tokens ) - 1 ]['end'];
					$first = null;
					$last  = null;
					foreach ( $tokens as $index => $token ) {
						if ( $token['start'] === $start ) {
							$first = $index;
						}
						if ( $token['end'] === $end ) {
							$last = $index;
							break;
						}
					}

					$intact = null !== $first && null !== $last &&
						WP_Invariant_SQL_Lexer::structure( array_slice( $tokens, $first, $last - $first + 1 ) ) === WP_Invariant_SQL_Lexer::structure( $query_tokens );
					$call->expect( $intact, 'The prepared query %s reached MySQL inside %s.', $query, $sql );
				}
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
);
