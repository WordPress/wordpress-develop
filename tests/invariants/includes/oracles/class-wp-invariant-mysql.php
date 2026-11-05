<?php
/**
 * Parses MySQL with the parser from WordPress/sqlite-database-integration, as an oracle for invariants.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Asks a MySQL parser generated from MySQL's own grammar how a query would be read.
 *
 * The parser is the `wordpress/mysql-parser` package by Jan Jakeš, in
 * packages/mysql-parser of https://github.com/WordPress/sqlite-database-integration,
 * at the commit in tests/invariants/DEPENDENCIES. It is loaded the first time a
 * check needs it, from .invariants/sqlite-database-integration, or from the
 * directory in the environment variable WP_INVARIANTS_MYSQL_PARSER.
 */
final class WP_Invariant_MySQL {

	/**
	 * Shape of a number literal, after folding a minus sign into it.
	 */
	const NUMBER = 'simple_expr(literal_or_null(literal(NUM_literal)))';

	/**
	 * The parser, once loaded.
	 *
	 * @var WP_Parser|null
	 */
	private static $parser = null;

	/**
	 * Returns the shape of a query's parse tree, or null when it is not valid MySQL.
	 *
	 * The shape names each grammar rule and token, but leaves out the values of
	 * literals: two queries which differ only in their values have the same shape.
	 * Numbers all have one shape, whatever their size or sign.
	 *
	 * @param string $sql Query.
	 * @return string|null
	 */
	public static function parse_tree( $sql ) {
		$tree = self::parser()->parse( ( new WP_MySQL_Lexer( $sql ) )->remaining_tokens() );
		return null === $tree ? null : self::shape( $tree );
	}

	/**
	 * Returns the shape of a node or token.
	 *
	 * @param WP_Parser_Node|WP_MySQL_Token $node Node or token.
	 * @return string
	 */
	private static function shape( $node ) {
		if ( ! $node instanceof WP_Parser_Node ) {
			return $node->get_name();
		}

		if ( 'NUM_literal' === $node->rule_name ) {
			return 'NUM_literal';
		}

		$children = $node->get_children();
		$parts    = array();
		foreach ( $children as $child ) {
			$parts[] = self::shape( $child );
		}

		// A minus sign before a number literal is part of the value.
		if ( 'simple_expr' === $node->rule_name && 2 === count( $parts ) && 'MINUS' === $parts[0] && self::NUMBER === $parts[1] ) {
			return self::NUMBER;
		}

		return $node->rule_name . '(' . implode( ',', $parts ) . ')';
	}

	/**
	 * Loads the parser.
	 *
	 * @return WP_Parser
	 */
	private static function parser() {
		if ( null !== self::$parser ) {
			return self::$parser;
		}

		$directory = getenv( 'WP_INVARIANTS_MYSQL_PARSER' );
		if ( ! $directory ) {
			$directory = dirname( __DIR__, 4 ) . '/.invariants/sqlite-database-integration/packages/mysql-parser/src';
		}
		if ( ! is_file( "{$directory}/mysql-parse-table.php" ) ) {
			throw new RuntimeException( sprintf( 'The MySQL parser is not in %s. See tests/invariants/README.md.', $directory ) );
		}

		foreach ( array( 'parser/class-wp-parser-token.php', 'class-wp-mysql-token.php', 'class-wp-mysql-lexer.php', 'parser/class-wp-parser-grammar.php', 'parser/class-wp-parser-node.php', 'parser/class-wp-parser.php' ) as $file ) {
			require_once "{$directory}/{$file}";
		}

		self::$parser = new WP_Parser( new WP_Parser_Grammar( require "{$directory}/mysql-parse-table.php" ) );
		return self::$parser;
	}
}
