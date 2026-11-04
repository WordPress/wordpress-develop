<?php
/**
 * Self-tests of the SQL lexer against the real wpdb::prepare().
 *
 * The invariant wpdb/prepared-structure is only as good as the lexer's reading
 * of wpdb::prepare() templates. These tests run the real method, from src/, so
 * that a change to it which the lexer does not follow fails here, and not as
 * false violations in the suite.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Tests_Invariants_Prepare extends TestCase {

	/**
	 * A wpdb which is not connected to a database: it escapes with addslashes().
	 *
	 * @var wpdb
	 */
	private static $wpdb;

	public static function setUpBeforeClass(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit method.
		require_once dirname( __DIR__ ) . '/fixtures/wpdb-functions.php';
		require_once dirname( __DIR__, 4 ) . '/src/wp-includes/class-wpdb.php';
		self::$wpdb = ( new ReflectionClass( 'wpdb' ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @dataProvider data_templates
	 *
	 * @param string $template A template for wpdb::prepare().
	 * @param array  $args     Arguments for it.
	 * @param bool   $same     Whether the prepared query has the template's structure.
	 */
	public function test_prepared_query_has_the_structure_of_its_template( $template, array $args, $same ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The template is what is being tested.
		$query = self::$wpdb->remove_placeholder_escape( self::$wpdb->prepare( $template, ...$args ) );

		$this->assertSame(
			$same,
			WP_Invariant_SQL_Lexer::same_structure(
				WP_Invariant_SQL_Lexer::structure( WP_Invariant_SQL_Lexer::tokens( $template, true ) ),
				WP_Invariant_SQL_Lexer::structure( WP_Invariant_SQL_Lexer::tokens( $query ) )
			),
			"wpdb::prepare() returned: {$query}"
		);
	}

	public function data_templates() {
		$tricky = "it's 50% \\ done\" -- /*";

		return array(
			'strings and numbers'           => array( 'SELECT ID FROM wp_posts WHERE post_name = %s AND post_parent = %d LIMIT %d', array( $tricky, 5, -1 ), true ),
			'already quoted %s'             => array( "SELECT * FROM wp_options WHERE option_name = '%s' OR option_name = \"%s\"", array( $tricky, $tricky ), true ),
			'identifier'                    => array( 'SELECT %i FROM %i WHERE %i = %s', array( 'ID', 'wp_posts', 'post_status', 'publish' ), true ),
			'like with literal percents'    => array( "SELECT * FROM wp_posts WHERE post_title LIKE '%%%s%%'", array( '50%' ), true ),
			'float'                         => array( 'SELECT * FROM t WHERE a > %f AND b < %F', array( 1.5, -2.25 ), true ),
			'numbered placeholders, quoted' => array( "SELECT * FROM t WHERE a = '%1\$s' AND b = '%2\$s' AND c = '%1\$s'", array( $tricky, 'x' ), true ),
			'numbered, one word, unquoted'  => array( 'SELECT * FROM %1$s WHERE ID = %2$d', array( 'wp_posts', 3 ), true ),
			'IN list'                       => array( 'SELECT * FROM t WHERE ID IN (%d, %d, %d)', array( 1, 2, 3 ), true ),
			'unrecognized percent'          => array( 'SELECT * FROM t WHERE a = %s AND b = 100% AND c = %d', array( 'x', 4 ), true ),
			'numbered, injected, unquoted'  => array( 'SELECT * FROM t WHERE ID = %1$s', array( '1 OR 1=1' ), false ),
			'formatted, injected, unquoted' => array( 'SELECT * FROM t WHERE ID = %5s', array( '1 OR 1=1' ), false ),
		);
	}
}
