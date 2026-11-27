<?php
/**
 * A small MySQL lexer for comparing the structure of queries.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Splits MySQL into tokens, and compares the structure of a query template with a query.
 *
 * Structure is the query with its values left out: keywords, names, operators and
 * punctuation stay as they are, while every string or number literal becomes a
 * VALUE and every quoted identifier an IDENT. A value which changes the structure
 * of a query, such as a string which closes its quotes, is an injection.
 *
 * This is not a full MySQL parser. It knows enough to tell where literals,
 * identifiers and comments start and end, in MySQL's default SQL mode.
 */
final class WP_Invariant_SQL_Lexer {

	/**
	 * A placeholder of wpdb::prepare(), as wpdb::prepare() recognizes it.
	 */
	const PLACEHOLDER = '/\G%((?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?)([sdfFi])/';

	/**
	 * Splits a query into tokens.
	 *
	 * Each token is an array with `type`, `text`, `start` and `end` (byte offsets).
	 * Types are `string`, `number`, `ident` (in backticks), `word`, `punct` and
	 * `comment`. Whitespace is left out.
	 *
	 * With `$template`, the query is a template for wpdb::prepare(), and its
	 * placeholders become tokens of type `value` (quoted `%s`, `%d`, `%f` and
	 * `%F`), `ident-placeholder` (`%i`) or `raw` (numbered or formatted `%s`, which
	 * wpdb::prepare() leaves unquoted), and `%%` is a `%`.
	 *
	 * @param string $sql      Query.
	 * @param bool   $template Whether it is a template for wpdb::prepare().
	 * @return array[]
	 */
	public static function tokens( $sql, $template = false ) {
		if ( $template ) {
			// As wpdb::prepare() does, before it quotes %s again.
			$sql = str_replace( array( "'%s'", '"%s"' ), '%s', $sql );
		}

		$tokens = array();
		$length = strlen( $sql );
		$at     = 0;
		while ( $at < $length ) {
			$char  = $sql[ $at ];
			$start = $at;

			if ( false !== strpos( " \t\r\n\f\v", $char ) ) {
				++$at;
				continue;
			}

			if ( '#' === $char || ( '-' === $char && '--' === substr( $sql, $at, 2 ) && ( $at + 2 >= $length || false !== strpos( " \t\r\n\f\v", $sql[ $at + 2 ] ) ) ) ) {
				$end      = strpos( $sql, "\n", $at );
				$at       = false === $end ? $length : $end;
				$tokens[] = self::token( 'comment', $sql, $start, $at );
				continue;
			}

			if ( '/' === $char && '/*' === substr( $sql, $at, 2 ) ) {
				$end      = strpos( $sql, '*/', $at + 2 );
				$at       = false === $end ? $length : $end + 2;
				$tokens[] = self::token( 'comment', $sql, $start, $at );
				continue;
			}

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$at       = self::quoted_end( $sql, $at, $char );
				$tokens[] = self::token( '`' === $char ? 'ident' : 'string', $sql, $start, $at );
				continue;
			}

			if ( $template && '%' === $char ) {
				if ( '%%' === substr( $sql, $at, 2 ) ) {
					$at      += 2;
					$tokens[] = array(
						'type'  => 'punct',
						'text'  => '%',
						'start' => $start,
						'end'   => $at,
					);
					continue;
				}

				if ( preg_match( self::PLACEHOLDER, $sql, $match, 0, $at ) ) {
					$at += strlen( $match[0] );
					if ( 'i' === $match[2] ) {
						$type = 'ident-placeholder';
					} elseif ( 's' === $match[2] && ( '' !== $match[1] || ( $start > 0 && '%' === $sql[ $start - 1 ] ) ) ) {
						$type = 'raw';
					} else {
						$type = 'value';
					}
					$tokens[] = self::token( $type, $sql, $start, $at );
					continue;
				}
			}

			if ( preg_match( '/\G(?:0x[0-9a-fA-F]+|[0-9]+(?:\.[0-9]*)?(?:[eE][-+]?[0-9]+)?|\.[0-9]+(?:[eE][-+]?[0-9]+)?)(?![A-Za-z0-9_$\x80-\xff])/', $sql, $match, 0, $at ) ) {
				$at      += strlen( $match[0] );
				$tokens[] = self::token( 'number', $sql, $start, $at );
				continue;
			}

			if ( preg_match( '/\G[A-Za-z0-9_$\x80-\xff]+/', $sql, $match, 0, $at ) ) {
				$at      += strlen( $match[0] );
				$tokens[] = self::token( 'word', $sql, $start, $at );
				continue;
			}

			++$at;
			$tokens[] = self::token( 'punct', $sql, $start, $at );
		}

		return $tokens;
	}

	/**
	 * Fills a wpdb::prepare() template with a harmless value of each placeholder's kind.
	 *
	 * Quoted string placeholders become `'x'`, numbers `0`, identifiers `` `i` ``,
	 * and the unquoted placeholders wpdb::prepare() leaves raw become `x`. A `%%`
	 * becomes `%`. What wpdb::prepare() returns for the template should parse the
	 * same way as this.
	 *
	 * @param string $template Template for wpdb::prepare().
	 * @return string
	 */
	public static function fill_template( $template ) {
		$template = str_replace( array( "'%s'", '"%s"' ), '%s', $template );
		$filled   = '';
		$at       = 0;
		foreach ( self::tokens( $template, true ) as $token ) {
			switch ( $token['type'] ) {
				case 'value':
					$fill = 's' === substr( $token['text'], -1 ) ? "'x'" : '0';
					break;

				case 'ident-placeholder':
					$fill = '`i`';
					break;

				case 'raw':
					$fill = 'x';
					break;

				case 'punct':
					$fill = '%' === $token['text'] ? '%' : null;
					break;

				default:
					$fill = null;
			}

			if ( null !== $fill ) {
				$filled .= substr( $template, $at, $token['start'] - $at ) . $fill;
				$at      = $token['end'];
			}
		}

		return $filled . substr( $template, $at );
	}

	/**
	 * Returns the structure of tokens, as a list of strings to compare.
	 *
	 * Literals become `VALUE`, quoted identifiers `IDENT` and unquoted placeholders
	 * `RAW`; anything else keeps its text. A minus sign directly before a number
	 * belongs to the number, unless it follows something it could subtract from.
	 *
	 * @param array[] $tokens Tokens from tokens().
	 * @return string[]
	 */
	public static function structure( array $tokens ) {
		$structure = array();
		$count     = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			switch ( $token['type'] ) {
				case 'string':
				case 'number':
				case 'value':
					$structure[] = 'VALUE';
					break;

				case 'ident':
				case 'ident-placeholder':
					$structure[] = 'IDENT';
					break;

				case 'raw':
					$structure[] = 'RAW';
					break;

				case 'punct':
					$previous = end( $structure );
					if (
						'-' === $token['text'] &&
						isset( $tokens[ $i + 1 ] ) && 'number' === $tokens[ $i + 1 ]['type'] && $tokens[ $i + 1 ]['start'] === $token['end'] &&
						! in_array( $previous, array( 'VALUE', 'IDENT', 'RAW', ')' ), true )
					) {
						$structure[] = 'VALUE';
						++$i;
						break;
					}
					$structure[] = $token['text'];
					break;

				default:
					$structure[] = $token['text'];
			}
		}

		return $structure;
	}

	/**
	 * Whether a query has the structure of a template.
	 *
	 * A `RAW` placeholder in the template matches any single value, identifier or word.
	 *
	 * @param string[] $template Structure of the template.
	 * @param string[] $query    Structure of the query.
	 * @return bool
	 */
	public static function same_structure( array $template, array $query ) {
		if ( count( $template ) !== count( $query ) ) {
			return false;
		}

		foreach ( $template as $i => $part ) {
			if ( $part === $query[ $i ] ) {
				continue;
			}
			if ( 'RAW' === $part && ( 'VALUE' === $query[ $i ] || 'IDENT' === $query[ $i ] || preg_match( '/^[A-Za-z0-9_$\x80-\xff]+$/', $query[ $i ] ) ) ) {
				continue;
			}
			return false;
		}

		return true;
	}

	/**
	 * Finds where one query appears in another, and whether it lexes the same there.
	 *
	 * Text which matches is only the query when it starts and ends where tokens of
	 * the other query do, or when it starts or ends inside a quoted string, quoted
	 * identifier or comment, which the query would break into or out of. Text which
	 * matches only part of a number or a name, such as `= 1` in `= 11`, or which
	 * lies inside one token, such as a string holding the query's text, is another
	 * query with the same text, and is left out.
	 *
	 * @param string  $sql          Query to look in.
	 * @param array[] $sql_tokens   Its tokens, from tokens().
	 * @param string  $query        Query to look for.
	 * @param array[] $query_tokens Its tokens, from tokens().
	 * @return bool[] For each place the query appears, whether it lexes the same there.
	 */
	public static function appearances( $sql, array $sql_tokens, $query, array $query_tokens ) {
		$appearances = array();
		if ( array() === $query_tokens ) {
			return $appearances;
		}

		$structure = null;
		$offset    = 0;
		while ( false !== ( $at = strpos( $sql, $query, $offset ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$offset = $at + 1;

			// The tokens of $sql which hold the first and last characters of the query's tokens.
			$start = $at + $query_tokens[0]['start'];
			$end   = $at + $query_tokens[ count( $query_tokens ) - 1 ]['end'];
			$first = null;
			$last  = null;
			foreach ( $sql_tokens as $index => $token ) {
				if ( null === $first && $token['end'] > $start ) {
					$first = $index;
				}
				if ( $token['end'] >= $end ) {
					$last = $index;
					break;
				}
			}
			if ( null === $first || null === $last ) {
				continue;
			}

			$starts_inside = $sql_tokens[ $first ]['start'] !== $start;
			$ends_inside   = $sql_tokens[ $last ]['end'] !== $end;
			if ( $first === $last && ( $starts_inside || $ends_inside ) ) {
				continue;
			}
			if (
				( $starts_inside && in_array( $sql_tokens[ $first ]['type'], array( 'word', 'number' ), true ) ) ||
				( $ends_inside && in_array( $sql_tokens[ $last ]['type'], array( 'word', 'number' ), true ) )
			) {
				continue;
			}
			if ( $starts_inside || $ends_inside ) {
				$appearances[] = false;
				continue;
			}

			if ( null === $structure ) {
				$structure = self::structure( $query_tokens );
			}
			$appearances[] = self::structure( array_slice( $sql_tokens, $first, $last - $first + 1 ) ) === $structure;
		}

		return $appearances;
	}

	/**
	 * Returns where a quoted string or identifier ends.
	 *
	 * @param string $sql   Query.
	 * @param int    $at    Offset of the opening quote.
	 * @param string $quote The quote character.
	 * @return int Offset just after the closing quote, or the end of the query when it is not closed.
	 */
	private static function quoted_end( $sql, $at, $quote ) {
		$length = strlen( $sql );
		++$at;
		while ( $at < $length ) {
			$char = $sql[ $at ];
			if ( '\\' === $char && '`' !== $quote ) {
				$at += 2;
				continue;
			}
			if ( $char === $quote ) {
				if ( $at + 1 < $length && $sql[ $at + 1 ] === $quote ) {
					$at += 2;
					continue;
				}
				return $at + 1;
			}
			++$at;
		}

		return $length;
	}

	/**
	 * Makes a token.
	 *
	 * @param string $type  Type.
	 * @param string $sql   Query.
	 * @param int    $start Offset of the token.
	 * @param int    $end   Offset just after the token.
	 * @return array
	 */
	private static function token( $type, $sql, $start, $end ) {
		return array(
			'type'  => $type,
			'text'  => substr( $sql, $start, $end - $start ),
			'start' => $start,
			'end'   => $end,
		);
	}
}
