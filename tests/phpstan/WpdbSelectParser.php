<?php
/**
 * Parser for the select list of a SQL query, for `WpdbQueryDynamicMethodReturnTypeExtension`.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

/**
 * Determines the columns a `SELECT` query returns, and the type of each.
 *
 * This is not a SQL parser. It reads only as much of the query as it needs:
 * the select list, and the tables named in the `FROM` clause with their
 * aliases. The query text is as reconstructed by `WpdbQueryVisitor`, where a
 * core table is `{wpdb:<table>}`, an unknown part is `{?}`, and an unknown
 * single value, from a placeholder, is `{value}`.
 *
 * Each column is typed as follows:
 *
 * - A column of a core table, including each one a `*` or `alias.*` expands
 *   to, has the type `WpdbSchema` gives it, or null as well for a table on the
 *   outer side of a `LEFT JOIN` or `RIGHT JOIN`, which has no row to match.
 * - `COUNT()` is a `decimal-int-string`, `SUM()` and `AVG()` are a
 *   `numeric-string` or null, and `MIN()` and `MAX()` of a core column are the
 *   column's type or null.
 * - A number or string literal is that constant string.
 * - Anything else is `string|null`, which is every value `wpdb` can return.
 *
 * A column's name is its alias, or else its name for a column reference, or
 * else the text of its expression, as MySQL names them.
 *
 * The columns are incomplete from the first one whose count is not known: a
 * `*` over a table that is not a core table, or a select expression with an
 * unknown part outside any parentheses, since that part could hold several
 * columns.
 *
 * @phpstan-type Column array{ name: string|null, type: non-empty-string, optional: bool }
 * @phpstan-type Columns array{ columns: list<Column>, complete: bool }
 * @phpstan-type Token array{ text: string, offset: int }
 * @phpstan-type Table array{ name: string|null, nullable: bool }
 * @phpstan-import-type Column from WpdbSchema as SchemaColumn
 */
final class WpdbSelectParser {

	/**
	 * Keywords which end the select list or the `FROM` clause.
	 */
	private const CLAUSE_KEYWORDS = array( 'FROM', 'WHERE', 'GROUP', 'HAVING', 'WINDOW', 'ORDER', 'LIMIT', 'FOR', 'INTO', 'LOCK', 'UNION', 'EXCEPT', 'INTERSECT', ';' );

	/**
	 * Keywords which start a join in the `FROM` clause.
	 */
	private const JOIN_KEYWORDS = array( 'JOIN', 'INNER', 'CROSS', 'LEFT', 'RIGHT', 'OUTER', 'NATURAL', 'STRAIGHT_JOIN' );

	/**
	 * Modifiers which may follow `SELECT`.
	 */
	private const SELECT_MODIFIERS = array( 'ALL', 'DISTINCT', 'DISTINCTROW', 'HIGH_PRIORITY', 'STRAIGHT_JOIN', 'SQL_SMALL_RESULT', 'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT', 'SQL_NO_CACHE', 'SQL_CACHE', 'SQL_CALC_FOUND_ROWS' );

	/**
	 * Keywords which can end an expression, and so are not an alias following one.
	 */
	private const NON_ALIAS_KEYWORDS = array( 'END', 'NULL', 'TRUE', 'FALSE', 'ASC', 'DESC', 'DISTINCT' );

	/**
	 * Schema of the core tables.
	 *
	 * @var WpdbSchema
	 */
	private WpdbSchema $schema;

	/**
	 * Parsed queries, keyed by query.
	 *
	 * @var array<string, array{ columns: list<array{ name: string|null, type: non-empty-string, optional: bool }>, complete: bool }|null>
	 * @phpstan-var array<string, Columns|null>
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @param WpdbSchema $schema Schema of the core tables.
	 */
	public function __construct( WpdbSchema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Gets the columns a query returns.
	 *
	 * @param string $query Query, as reconstructed by `WpdbQueryVisitor`.
	 * @return array{ columns: list<array{ name: string|null, type: non-empty-string, optional: bool }>, complete: bool }|null {
	 *     Columns, or null if the query is not a `SELECT` query this can read.
	 *
	 *     @type list<array> $columns  {
	 *         Columns, in order.
	 *
	 *         @type string|null      $name     Column name, or null if unknown.
	 *         @type non-empty-string $type     PHPDoc type of the column's values.
	 *         @type bool             $optional Whether the column is only present on some sites.
	 *     }
	 *     @type bool        $complete Whether the columns are all known. If not, more follow.
	 * }
	 * @phpstan-return Columns|null
	 */
	public function parse( string $query ): ?array {
		if ( ! array_key_exists( $query, $this->cache ) ) {
			$this->cache[ $query ] = $this->parse_uncached( $query );
		}
		return $this->cache[ $query ];
	}

	/**
	 * Gets the columns a query returns, without the cache.
	 *
	 * @param string $query Query.
	 * @return array|null Columns, or null if the query is not a `SELECT` query this can read.
	 * @phpstan-return Columns|null
	 */
	private function parse_uncached( string $query ): ?array {
		$tokens = self::tokenize( $query );
		$count  = count( $tokens );

		if ( 0 === $count || 'SELECT' !== strtoupper( $tokens[0]['text'] ) ) {
			return null;
		}

		$i = 1;
		while ( $i < $count && in_array( strtoupper( $tokens[ $i ]['text'] ), self::SELECT_MODIFIERS, true ) ) {
			++$i;
		}

		// Split the select list into its expressions.
		$items = array();
		$item  = array();
		$depth = 0;
		for ( ; $i < $count; $i++ ) {
			$text = $tokens[ $i ]['text'];
			if ( 0 === $depth && ( ',' === $text || in_array( strtoupper( $text ), self::CLAUSE_KEYWORDS, true ) ) ) {
				if ( ',' !== $text ) {
					break;
				}
				$items[] = $item;
				$item    = array();
				continue;
			}
			$depth  += self::get_depth_change( $text );
			$item[]  = $tokens[ $i ];
		}
		$items[] = $item;

		$tables = $this->parse_from( $tokens, $i );
		if ( null === $tables ) {
			return null;
		}

		$columns = array();
		foreach ( $items as $item ) {
			$item_columns = $this->get_item_columns( $item, $tables, $query );
			if ( null === $item_columns ) {
				return array(
					'columns'  => $columns,
					'complete' => false,
				);
			}
			foreach ( $item_columns as $column ) {
				$columns[] = $column;
			}
		}

		return array(
			'columns'  => $columns,
			'complete' => true,
		);
	}

	/**
	 * Reads the tables in the `FROM` clause.
	 *
	 * @param list<array{ text: string, offset: int }> $tokens Tokens of the query.
	 * @param int                                      $i      Index of the token ending the select list.
	 * @return array<string, array{ name: string|null, nullable: bool }>|null Tables, keyed by the lowercase alias or
	 *                                                                    name they are referred to by. Each has its
	 *                                                                    `wpdb` property name, or null if not a core
	 *                                                                    table, and whether its columns may be null
	 *                                                                    from an outer join. Null if the query is a
	 *                                                                    union, whose column types this does not read.
	 * @phpstan-param list<Token> $tokens
	 * @phpstan-return array<string, Table>|null
	 */
	private function parse_from( array $tokens, int $i ): ?array {
		$count = count( $tokens );

		// A union's columns may take their values from any of its queries.
		$depth = 0;
		for ( $j = $i; $j < $count; $j++ ) {
			$depth += self::get_depth_change( $tokens[ $j ]['text'] );
			if ( 0 === $depth && in_array( strtoupper( $tokens[ $j ]['text'] ), array( 'UNION', 'EXCEPT', 'INTERSECT' ), true ) ) {
				return null;
			}
		}

		$tables = array();
		if ( $i >= $count || 'FROM' !== strtoupper( $tokens[ $i ]['text'] ) ) {
			return $tables;
		}

		$expect_table = true;
		$outer_join   = null;
		$depth        = 0;
		for ( ++$i; $i < $count; $i++ ) {
			$text  = $tokens[ $i ]['text'];
			$upper = strtoupper( $text );

			if ( 0 === $depth && in_array( $upper, self::CLAUSE_KEYWORDS, true ) ) {
				break;
			}

			// `LEFT` and `RIGHT` are also functions, as in an `ON` condition.
			if ( ( 'LEFT' === $upper || 'RIGHT' === $upper ) && ! in_array( strtoupper( $tokens[ $i + 1 ]['text'] ?? '' ), array( 'JOIN', 'OUTER' ), true ) ) {
				$depth += self::get_depth_change( $text );
				continue;
			}

			if ( 0 === $depth && ( ',' === $text || in_array( $upper, self::JOIN_KEYWORDS, true ) ) ) {
				$expect_table = true;
				if ( 'LEFT' === $upper || 'RIGHT' === $upper ) {
					$outer_join = $upper;
				} elseif ( ',' === $text ) {
					$outer_join = null;
				}
				continue;
			}

			if ( 0 === $depth && $expect_table ) {
				$expect_table = false;

				if ( '(' === $text ) {
					// A derived table, or a parenthesized join. Its columns are unknown, unless they are qualified.
					$name   = null;
					$nested = 0;
					for ( ; $i < $count; $i++ ) {
						$nested += self::get_depth_change( $tokens[ $i ]['text'] );
						if ( 0 === $nested ) {
							break;
						}
					}
					$key = '';
				} else {
					$name = null;
					if ( preg_match( '/^\{wpdb:(\w+)\}$/', $text, $match ) && $this->schema->has_table( $match[1] ) ) {
						$name = $match[1];
					}
					$key = strtolower( self::unquote( $text ) );
				}

				// The alias, if any.
				$next = $tokens[ $i + 1 ]['text'] ?? '';
				if ( 'AS' === strtoupper( $next ) ) {
					$i   += 1;
					$next = $tokens[ $i + 1 ]['text'] ?? '';
				}
				if ( self::is_identifier( $next ) && ! in_array( strtoupper( $next ), array_merge( self::CLAUSE_KEYWORDS, self::JOIN_KEYWORDS, array( 'ON', 'USING', 'USE', 'IGNORE', 'FORCE', 'PARTITION' ) ), true ) ) {
					$key = strtolower( self::unquote( $next ) );
					$i  += 1;
				}

				// The table on the outer side of a join may have no row to match, which makes its columns null.
				if ( 'RIGHT' === $outer_join ) {
					foreach ( array_keys( $tables ) as $table_key ) {
						$tables[ $table_key ]['nullable'] = true;
					}
				}
				$tables[ $key ] = array(
					'name'     => $name,
					'nullable' => 'LEFT' === $outer_join,
				);
				$outer_join     = null;
				continue;
			}

			$depth += self::get_depth_change( $text );
		}

		return $tables;
	}

	/**
	 * Gets the columns of one expression in the select list.
	 *
	 * @param list<array{ text: string, offset: int }>                  $item   Tokens of the expression, with its alias.
	 * @param array<string, array{ name: string|null, nullable: bool }> $tables Tables in the `FROM` clause.
	 * @param string                                                    $query  Query.
	 * @return list<array{ name: string|null, type: non-empty-string, optional: bool }>|null Columns, or null if unknown.
	 * @phpstan-param list<Token> $item
	 * @phpstan-param array<string, Table> $tables
	 * @phpstan-return list<Column>|null
	 */
	private function get_item_columns( array $item, array $tables, string $query ): ?array {
		$count = count( $item );
		if ( 0 === $count ) {
			return null;
		}

		// An unknown part outside any parentheses could be any number of columns.
		$depth = 0;
		foreach ( $item as $token ) {
			if ( 0 === $depth && WpdbQueryVisitor::UNKNOWN === $token['text'] ) {
				return null;
			}
			$depth += self::get_depth_change( $token['text'] );
		}

		// The alias, if any, which may be a name or a quoted string.
		$alias     = null;
		$last_text = $item[ $count - 1 ]['text'];
		$previous  = $count >= 2 ? $item[ $count - 2 ]['text'] : '';
		if ( $count >= 3 && 'AS' === strtoupper( $previous ) && ( self::is_identifier( $last_text ) || self::is_quoted_string( $last_text ) ) ) {
			$alias = self::unquote( $last_text );
			$item  = array_slice( $item, 0, $count - 2 );
		} elseif (
			$count >= 2
			&& (
				// A string following a string literal is not an alias, but is joined to it.
				( self::is_quoted_string( $last_text ) && ( ')' === $previous || self::is_identifier( $previous ) ) )
				|| (
					self::is_identifier( $last_text )
					&& ! in_array( strtoupper( $last_text ), self::NON_ALIAS_KEYWORDS, true )
					&& ( ')' === $previous || self::is_identifier( $previous ) || self::is_literal( $previous ) )
				)
			)
		) {
			$alias = self::unquote( $last_text );
			$item  = array_slice( $item, 0, $count - 1 );
		}

		$texts = array_column( $item, 'text' );
		$count = count( $texts );

		// A `*` or `alias.*`.
		if ( array( '*' ) === $texts || ( 3 === $count && '.' === $texts[1] && '*' === $texts[2] ) ) {
			$star_tables = 1 === $count ? array_values( $tables ) : array( $tables[ strtolower( self::unquote( $texts[0] ) ) ] ?? null );
			if ( array() === $star_tables ) {
				return null;
			}
			$columns = array();
			foreach ( $star_tables as $table ) {
				if ( null === $table || null === $table['name'] ) {
					return null;
				}
				foreach ( $this->schema->get_columns( $table['name'] ) as $column ) {
					$columns[] = self::apply_nullable( $column, $table );
				}
			}
			return $columns;
		}

		// A column reference.
		$column_ref = self::get_column_reference( $texts );
		if ( null !== $column_ref ) {
			list( $qualifier, $name ) = $column_ref;
			$column                   = $this->find_column( $tables, $qualifier, $name );
			return array(
				array(
					'name'     => $alias ?? $name,
					'type'     => null !== $column ? $column['type'] : 'string|null',
					'optional' => null !== $column && $column['optional'],
				),
			);
		}

		// Otherwise MySQL names the column by the text of its expression.
		if ( null === $alias ) {
			$last  = end( $item );
			$text  = (string) substr( $query, $item[0]['offset'], $last['offset'] + strlen( $last['text'] ) - $item[0]['offset'] );
			$alias = false === strpos( $text, '{' ) ? $text : null;
		}

		return array(
			array(
				'name'     => $alias,
				'type'     => $this->get_expression_type( $texts, $tables ),
				'optional' => false,
			),
		);
	}

	/**
	 * Gets the type of the values of an expression which is not a column reference.
	 *
	 * @param list<string>                                              $texts  Tokens of the expression.
	 * @param array<string, array{ name: string|null, nullable: bool }> $tables Tables in the `FROM` clause.
	 * @return non-empty-string PHPDoc type.
	 * @phpstan-param array<string, Table> $tables
	 */
	private function get_expression_type( array $texts, array $tables ): string {
		$count = count( $texts );

		if ( 1 === $count && preg_match( '/^-?\d+$/', $texts[0] ) ) {
			return "'" . $texts[0] . "'";
		}
		if ( 1 === $count && preg_match( "/^'([\\w .:-]*)'$/", $texts[0], $match ) ) {
			return "'" . $match[1] . "'";
		}

		// A function call, like `COUNT( DISTINCT ID )`.
		if ( $count >= 3 && self::is_identifier( $texts[0] ) && '(' === $texts[1] && ')' === $texts[ $count - 1 ] ) {
			// The parentheses must be those of the one call, not `COUNT(a) + (b)`.
			$depth = 0;
			for ( $i = 1; $i < $count - 1; $i++ ) {
				$depth += self::get_depth_change( $texts[ $i ] );
				if ( 0 === $depth ) {
					return 'string|null';
				}
			}

			$arguments = array_slice( $texts, 2, $count - 3 );
			switch ( strtoupper( $texts[0] ) ) {
				case 'COUNT':
					return 'decimal-int-string';
				case 'SUM':
				case 'AVG':
					return 'numeric-string|null';
				case 'MIN':
				case 'MAX':
					if ( isset( $arguments[0] ) && 'DISTINCT' === strtoupper( $arguments[0] ) ) {
						$arguments = array_slice( $arguments, 1 );
					}
					$column_ref = self::get_column_reference( $arguments );
					$column     = null !== $column_ref ? $this->find_column( $tables, $column_ref[0], $column_ref[1] ) : null;
					if ( null !== $column ) {
						return self::add_null( $column['type'] );
					}
					return 'string|null';
			}
		}

		return 'string|null';
	}

	/**
	 * Finds a column of a core table.
	 *
	 * @param array<string, array{ name: string|null, nullable: bool }> $tables    Tables in the `FROM` clause.
	 * @param string|null                                               $qualifier Lowercase alias or name of the table,
	 *                                                                             or null if not qualified.
	 * @param string                                                    $name      Column name.
	 * @return array{ name: non-empty-string, type: non-empty-string, optional: bool }|null Column, or null if unknown.
	 * @phpstan-param array<string, Table> $tables
	 * @phpstan-return SchemaColumn|null
	 */
	private function find_column( array $tables, ?string $qualifier, string $name ): ?array {
		if ( null !== $qualifier ) {
			$table = $tables[ $qualifier ] ?? null;
			if ( null === $table || null === $table['name'] ) {
				return null;
			}
			$column = $this->schema->get_column( $table['name'], $name );
			return null !== $column ? self::apply_nullable( $column, $table ) : null;
		}

		foreach ( $tables as $table ) {
			$column = null !== $table['name'] ? $this->schema->get_column( $table['name'], $name ) : null;
			if ( null !== $column ) {
				return self::apply_nullable( $column, $table );
			}
		}
		return null;
	}

	/**
	 * Makes a column's type nullable if its table is on the outer side of a join.
	 *
	 * @param array{ name: non-empty-string, type: non-empty-string, optional: bool } $column Column of a core table.
	 * @param array{ name: string|null, nullable: bool }                             $table  Table the column is in.
	 * @return array{ name: non-empty-string, type: non-empty-string, optional: bool } Column.
	 * @phpstan-param SchemaColumn $column
	 * @phpstan-param Table $table
	 * @phpstan-return SchemaColumn
	 */
	private static function apply_nullable( array $column, array $table ): array {
		if ( $table['nullable'] ) {
			$column['type'] = self::add_null( $column['type'] );
		}
		return $column;
	}

	/**
	 * Adds null to a PHPDoc type.
	 *
	 * @param non-empty-string $type PHPDoc type.
	 * @return non-empty-string
	 */
	private static function add_null( string $type ): string {
		return '|null' === substr( $type, -5 ) ? $type : $type . '|null';
	}

	/**
	 * Gets the qualifier and name of a column reference, like `p.ID` or `ID`.
	 *
	 * @param list<string> $texts Tokens of an expression.
	 * @return array{ 0: string|null, 1: string }|null Lowercase qualifier, or null if none, and column name. Null if
	 *                                                 the expression is not a column reference.
	 */
	private static function get_column_reference( array $texts ): ?array {
		$count = count( $texts );
		if ( 1 === $count && self::is_identifier( $texts[0] ) && ! self::is_keyword_value( $texts[0] ) ) {
			return array( null, self::unquote( $texts[0] ) );
		}
		if ( 3 === $count && self::is_identifier( $texts[0] ) && '.' === $texts[1] && self::is_identifier( $texts[2] ) ) {
			return array( strtolower( self::unquote( $texts[0] ) ), self::unquote( $texts[2] ) );
		}
		return null;
	}

	/**
	 * Splits a query into tokens, leaving out whitespace and comments.
	 *
	 * @param string $query Query.
	 * @return list<array{ text: string, offset: int }>
	 * @phpstan-return list<Token>
	 */
	private static function tokenize( string $query ): array {
		preg_match_all(
			'/\s+|--[^\n]*|\/\*.*?\*\/|\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"|`[^`]*`|\{[^{}]*\}|\d+(?:\.\d+)?|[\w$]+|<=>|[<>!]=|<>|\|\||&&|./s',
			$query,
			$matches,
			PREG_OFFSET_CAPTURE
		);

		$tokens = array();
		foreach ( $matches[0] as $match ) {
			list( $text, $offset ) = $match;
			if ( '' === trim( $text ) || 0 === strpos( $text, '--' ) || 0 === strpos( $text, '/*' ) ) {
				continue;
			}
			$tokens[] = array(
				'text'   => $text,
				'offset' => $offset,
			);
		}
		return $tokens;
	}

	/**
	 * Gets how much a token changes the depth of parentheses.
	 *
	 * @param string $text Token.
	 * @return int
	 */
	private static function get_depth_change( string $text ): int {
		if ( '(' === $text ) {
			return 1;
		}
		if ( ')' === $text ) {
			return -1;
		}
		return 0;
	}

	/**
	 * Determines whether a token is an identifier: a name, a backtick-quoted name, or a core table.
	 *
	 * The unknown placeholder `{?}` is not one.
	 *
	 * @param string $text Token.
	 * @return bool
	 */
	private static function is_identifier( string $text ): bool {
		return (bool) preg_match( '/^(?:[A-Za-z_$][\w$]*|`[^`]+`|\{wpdb:\w+\})$/', $text );
	}

	/**
	 * Determines whether a token is a literal: a number, a quoted string, or a value from a placeholder.
	 *
	 * @param string $text Token.
	 * @return bool
	 */
	private static function is_literal( string $text ): bool {
		return WpdbQueryVisitor::VALUE === $text || (bool) preg_match( '/^(?:\d|\'|")/', $text );
	}

	/**
	 * Determines whether a token is a keyword which is a value, rather than a column name.
	 *
	 * @param string $text Token.
	 * @return bool
	 */
	private static function is_keyword_value( string $text ): bool {
		return in_array( strtoupper( $text ), array( 'NULL', 'TRUE', 'FALSE', 'CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_USER' ), true );
	}

	/**
	 * Determines whether a token is a quoted string, with single or double quotes.
	 *
	 * @param string $text Token.
	 * @return bool
	 */
	private static function is_quoted_string( string $text ): bool {
		return strlen( $text ) >= 2 && ( "'" === $text[0] || '"' === $text[0] ) && $text[0] === substr( $text, -1 );
	}

	/**
	 * Removes the backticks around an identifier, or the quotes around a string used as an alias.
	 *
	 * @param string $text Identifier or quoted string.
	 * @return string
	 */
	private static function unquote( string $text ): string {
		if ( strlen( $text ) >= 2 && '`' === $text[0] && '`' === substr( $text, -1 ) ) {
			return substr( $text, 1, -1 );
		}
		if ( self::is_quoted_string( $text ) ) {
			$quote = $text[0];
			return str_replace( array( $quote . $quote, '\\' . $quote, '\\\\' ), array( $quote, $quote, '\\' ), substr( $text, 1, -1 ) );
		}
		return $text;
	}
}
