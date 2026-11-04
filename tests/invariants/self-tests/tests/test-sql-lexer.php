<?php
/**
 * Self-tests of the SQL lexer used by the wpdb invariants.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Tests_Invariants_SQL_Lexer extends TestCase {

	public function test_splits_a_query_into_typed_tokens() {
		$tokens = WP_Invariant_SQL_Lexer::tokens( "SELECT * FROM `wp_posts` WHERE ID = 5 AND post_title = 'it''s' -- note\nLIMIT 1.5e3" );

		$this->assertSame(
			array(
				array( 'word', 'SELECT' ),
				array( 'punct', '*' ),
				array( 'word', 'FROM' ),
				array( 'ident', '`wp_posts`' ),
				array( 'word', 'WHERE' ),
				array( 'word', 'ID' ),
				array( 'punct', '=' ),
				array( 'number', '5' ),
				array( 'word', 'AND' ),
				array( 'word', 'post_title' ),
				array( 'punct', '=' ),
				array( 'string', "'it''s'" ),
				array( 'comment', '-- note' ),
				array( 'word', 'LIMIT' ),
				array( 'number', '1.5e3' ),
			),
			$this->types_and_texts( $tokens )
		);
	}

	public function test_backslash_escapes_stay_inside_strings() {
		$tokens = WP_Invariant_SQL_Lexer::tokens( "a = 'O\\'Reilly' OR b = \"x\\\"y\"" );

		$this->assertSame( array( 'a', '=', 'VALUE', 'OR', 'b', '=', 'VALUE' ), WP_Invariant_SQL_Lexer::structure( $tokens ) );
	}

	public function test_comments_are_tokens() {
		$tokens = WP_Invariant_SQL_Lexer::tokens( "SELECT 1 # one\n/* two */ --three" );

		$this->assertSame(
			array(
				array( 'word', 'SELECT' ),
				array( 'number', '1' ),
				array( 'comment', '# one' ),
				array( 'comment', '/* two */' ),
				array( 'punct', '-' ),
				array( 'punct', '-' ),
				array( 'word', 'three' ),
			),
			$this->types_and_texts( $tokens ),
			'"--" only starts a comment when whitespace follows it.'
		);
	}

	public function test_template_placeholders_become_values_identifiers_or_raw_text() {
		$template = "SELECT * FROM %i WHERE a = %s AND b = '%s' AND c = %d AND d = %f AND e LIKE '%%%s%%' AND f = %1\$s AND g LIKE %%%s AND h = 100%%";

		$this->assertSame(
			array( 'SELECT', '*', 'FROM', 'IDENT', 'WHERE', 'a', '=', 'VALUE', 'AND', 'b', '=', 'VALUE', 'AND', 'c', '=', 'VALUE', 'AND', 'd', '=', 'VALUE', 'AND', 'e', 'LIKE', 'VALUE', 'AND', 'f', '=', 'RAW', 'AND', 'g', 'LIKE', '%', 'RAW', 'AND', 'h', '=', 'VALUE', '%' ),
			WP_Invariant_SQL_Lexer::structure( WP_Invariant_SQL_Lexer::tokens( $template, true ) )
		);
	}

	/**
	 * @dataProvider data_prepared_queries
	 *
	 * @param string $template What was given to wpdb::prepare().
	 * @param string $query    What it returned.
	 * @param bool   $same     Whether they have the same structure.
	 */
	public function test_compares_the_structure_of_a_template_and_a_query( $template, $query, $same ) {
		$this->assertSame(
			$same,
			WP_Invariant_SQL_Lexer::same_structure(
				WP_Invariant_SQL_Lexer::structure( WP_Invariant_SQL_Lexer::tokens( $template, true ) ),
				WP_Invariant_SQL_Lexer::structure( WP_Invariant_SQL_Lexer::tokens( $query ) )
			)
		);
	}

	public function data_prepared_queries() {
		return array(
			'string value'                   => array( 'SELECT * FROM t WHERE a = %s', "SELECT * FROM t WHERE a = 'x'", true ),
			'escaped quote in value'         => array( 'SELECT * FROM t WHERE a = %s', "SELECT * FROM t WHERE a = 'x\\' OR 1=1 -- '", true ),
			'negative number'                => array( 'SELECT * FROM t LIMIT %d', 'SELECT * FROM t LIMIT -5', true ),
			'subtraction stays an operator'  => array( 'SELECT a - %d FROM t', 'SELECT a - 5 FROM t', true ),
			'identifier'                     => array( 'SELECT * FROM %i', 'SELECT * FROM `wp_posts`', true ),
			'raw placeholder, one word'      => array( 'SELECT * FROM %1$s', 'SELECT * FROM wp_posts', true ),
			'raw placeholder, injected SQL'  => array( 'SELECT * FROM t WHERE a = %1$s', 'SELECT * FROM t WHERE a = 1 OR 1=1', false ),
			'unescaped quote breaks out'     => array( 'SELECT * FROM t WHERE a = %s', "SELECT * FROM t WHERE a = 'x' OR '1'='1'", false ),
			'comment added'                  => array( 'SELECT * FROM t WHERE a = %d', 'SELECT * FROM t WHERE a = 1 -- ', false ),
			'percent in a LIKE value'        => array( "SELECT * FROM t WHERE a LIKE '%%%s%%'", "SELECT * FROM t WHERE a LIKE '%50\\%%'", true ),
			'literal percent outside quotes' => array( 'SELECT a % %d FROM t', 'SELECT a % 3 FROM t', true ),
		);
	}

	/**
	 * Returns the type and text of each token.
	 *
	 * @param array[] $tokens Tokens.
	 * @return array[]
	 */
	private function types_and_texts( array $tokens ) {
		return array_map(
			static function ( $token ) {
				return array( $token['type'], $token['text'] );
			},
			$tokens
		);
	}
}
