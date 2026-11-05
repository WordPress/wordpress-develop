<?php
/**
 * Self-tests of the oracles: PHP's HTML, URL and XML parsers, and the MySQL parser.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Tests_Invariants_Oracles extends TestCase {

	public function test_browser_reads_a_url_scheme_as_browsers_do() {
		$this->assertSame( 'javascript', WP_Invariant_Browser::url_scheme( WP_Invariant_Browser::attribute_value( ' java&#x09;script:alert(1)' ) ) );
		$this->assertSame( 'javascript', WP_Invariant_Browser::url_scheme( 'JaVaScRiPt:alert(1)' ) );
		$this->assertSame( 'https', WP_Invariant_Browser::url_scheme( 'https://example.org/' ) );
		$this->assertNull( WP_Invariant_Browser::url_scheme( '/relative' ) );
		$this->assertNull( WP_Invariant_Browser::url_scheme( '//example.org/' ) );
	}

	public function test_browser_tells_whether_a_value_stays_in_its_attribute() {
		$this->assertSame( 'a" onclick="x', WP_Invariant_Browser::attribute_value( 'a&quot; onclick=&quot;x' ) );
		$this->assertNull( WP_Invariant_Browser::attribute_value( 'a" onclick="x' ) );
	}

	public function test_browser_finds_elements_in_templates_and_implied_elements() {
		$names = array_map( array( 'WP_Invariant_Browser', 'tag_name' ), WP_Invariant_Browser::elements( '<template><img src=x onerror=y></template><table><tr><td>a' ) );

		$this->assertSame( array( 'template', 'img', 'table', 'tbody', 'tr', 'td' ), $names );
	}

	public function test_browser_trees_are_equal_when_a_browser_builds_the_same_tree() {
		$this->assertSame( WP_Invariant_Browser::tree( '<p>a<p>b' ), WP_Invariant_Browser::tree( '<p>a</p><p>b</p>' ) );
		$this->assertNotSame( WP_Invariant_Browser::tree( '<p>a<p>b' ), WP_Invariant_Browser::tree( '<p>a<span>b</span></p>' ) );
	}

	public function test_xml_parser_counts_elements_and_rejects_malformed_xml() {
		$this->assertSame( 0, WP_Invariant_Browser::xml_child_elements( 'a &amp; b <![CDATA[<b>]]>' ) );
		$this->assertSame( 1, WP_Invariant_Browser::xml_child_elements( 'a <b/>' ) );
		$this->assertNull( WP_Invariant_Browser::xml_child_elements( 'a & b' ) );
		$this->assertNull( WP_Invariant_Browser::xml_child_elements( 'a &nbsp; b' ) );
	}

	public function test_mysql_parse_trees_leave_out_values_but_not_structure() {
		$tree = WP_Invariant_MySQL::parse_tree( "SELECT ID FROM wp_posts WHERE post_name = 'x' AND post_parent = 0" );

		$this->assertNotNull( $tree );
		$this->assertSame( $tree, WP_Invariant_MySQL::parse_tree( "SELECT ID FROM wp_posts WHERE post_name = 'it\\'s' AND post_parent = -5" ) );
		$this->assertSame( $tree, WP_Invariant_MySQL::parse_tree( "SELECT ID FROM wp_posts WHERE post_name = '' AND post_parent = 18446744073709551615" ) );
		$this->assertNotSame( $tree, WP_Invariant_MySQL::parse_tree( "SELECT ID FROM wp_posts WHERE post_name = 'x' OR 1=1 -- ' AND post_parent = 0" ) );
		$this->assertNull( WP_Invariant_MySQL::parse_tree( 'SELECT ID FROM wp_posts WHERE' ) );
	}

	public function test_browser_reads_a_start_tag_where_it_would_move_or_drop_it_elsewhere() {
		$this->assertSame(
			array(
				'name'       => 'td',
				'attributes' => array( 'title' => "a\u{2209}b&" ),
			),
			WP_Invariant_Browser::start_tag( '<td title="a&notin;b&amp" TITLE=y>' )
		);
		$this->assertSame(
			array(
				'name'       => 'svg',
				'attributes' => array(
					'viewbox'    => '0 0 1 1',
					'xlink:href' => 'x',
				),
			),
			WP_Invariant_Browser::start_tag( '<svg viewBox="0 0 1 1" xlink:href=x>' )
		);
		$this->assertSame( array( 'disabled' => '' ), WP_Invariant_Browser::start_tag( '<input disabled>' )['attributes'] );
		$this->assertSame( array( 'title' => '&notinx' ), WP_Invariant_Browser::start_tag( '<a title="&notinx">' )['attributes'], 'A reference without a semicolon, followed by a letter, is not decoded in an attribute.' );
		$this->assertNull( WP_Invariant_Browser::start_tag( '<body class=x>' ) );
	}

	public function test_browser_reads_a_textarea_value_without_its_leading_line_break() {
		$this->assertSame( 'a&b <i>', WP_Invariant_Browser::textarea_value( "\na&amp;b <i>" ) );
		$this->assertNull( WP_Invariant_Browser::textarea_value( 'a</textarea><b>' ) );
	}

	public function test_browser_lists_the_nodes_of_a_fragment() {
		$escaped = '{"a":"' . str_repeat( chr( 92 ) . 'u002d', 2 ) . chr( 92 ) . 'u003e"}';

		$this->assertSame( array( array( 'comment', " wp:x {$escaped} /" ) ), WP_Invariant_Browser::nodes( "<!-- wp:x {$escaped} /-->" ) );
		$this->assertSame( array( array( 'comment', ' wp:x {"a":"' ), array( 'text', '"} /-->' ) ), WP_Invariant_Browser::nodes( '<!-- wp:x {"a":"-->"} /-->' ) );
		$this->assertSame( array( array( 'text', 'a' ), array( 'element', 'b' ) ), WP_Invariant_Browser::nodes( 'a<b>' ) );
	}

	/**
	 * @dataProvider data_redirect_hosts
	 *
	 * @param string $location Location header.
	 * @param string $host     Host a browser goes to.
	 */
	public function test_browser_finds_the_host_a_location_goes_to( $location, $host ) {
		$this->assertSame( $host, WP_Invariant_Browser::url_host( $location, 'http://site.invalid/wp-admin/' ) );
	}

	public function data_redirect_hosts() {
		return array(
			'relative path'              => array( 'edit.php', 'site.invalid' ),
			'absolute path'              => array( '/wp-admin/', 'site.invalid' ),
			'protocol-relative'          => array( '//evil.example/', 'evil.example' ),
			'backslash after a slash'    => array( '/\\evil.example/', 'evil.example' ),
			'backslashes'                => array( '\\\\evil.example', 'evil.example' ),
			'backslash after the scheme' => array( 'https:/\\evil.example', 'evil.example' ),
			'leading space'              => array( ' //evil.example', 'evil.example' ),
			'user info'                  => array( 'http://site.invalid@evil.example/', 'evil.example' ),
			'upper case'                 => array( 'HTTPS://EVIL.Example/', 'evil.example' ),
			'no host'                    => array( 'mailto:a@example.org', '' ),
		);
	}

	public function test_xml_parser_tells_whether_a_document_is_well_formed() {
		$this->assertTrue( WP_Invariant_Browser::xml_well_formed( '<?xml version="1.0"?><a>&amp;</a>' ) );
		$this->assertFalse( WP_Invariant_Browser::xml_well_formed( '<?xml version="1.0"?><a>&</a>' ) );
		$this->assertFalse( WP_Invariant_Browser::xml_well_formed( '<a/><b/>' ) );
	}

	/**
	 * @dataProvider data_table_names
	 *
	 * @param string     $sql    Query.
	 * @param array|null $tables Tables it names.
	 */
	public function test_mysql_finds_the_tables_a_query_names( $sql, $tables ) {
		$this->assertSame( $tables, WP_Invariant_MySQL::table_names( $sql ) );
	}

	public function data_table_names() {
		return array(
			'join, alias and subquery' => array( 'SELECT p.* FROM `wp_posts` p JOIN wp_postmeta AS m ON m.post_id = p.ID WHERE p.ID IN (SELECT object_id FROM wp_term_relationships)', array( array( null, 'wp_posts' ), array( null, 'wp_postmeta' ), array( null, 'wp_term_relationships' ) ) ),
			'insert'                   => array( 'INSERT INTO wp_options (option_name) VALUES (1)', array( array( null, 'wp_options' ) ) ),
			'describe'                 => array( 'DESCRIBE wp_posts;', array( array( null, 'wp_posts' ) ) ),
			'database'                 => array( 'SELECT * FROM information_schema.tables', array( array( 'information_schema', 'tables' ) ) ),
			'aliases of a delete'      => array( 'DELETE a, b FROM wp_a a JOIN wp_b b', array( array( null, 'wp_a' ), array( null, 'wp_b' ) ) ),
			'common table expression'  => array( 'WITH x AS (SELECT 1) SELECT * FROM x JOIN wp_posts', array( array( null, 'wp_posts' ) ) ),
			'pattern of SHOW TABLES'   => array( "SHOW TABLES LIKE 'wp_x'", array() ),
			'no table'                 => array( 'SELECT FOUND_ROWS()', array() ),
			'not valid MySQL'          => array( 'SELECT * FROM', null ),
		);
	}

	public function test_fill_template_puts_a_harmless_value_in_each_placeholder() {
		$this->assertSame(
			"SELECT * FROM `i` WHERE a = 'x' AND b = 'x' AND c = 0 AND d = 0 AND e LIKE '%%%s%%' AND f = x AND g = 100%",
			WP_Invariant_SQL_Lexer::fill_template( "SELECT * FROM %i WHERE a = %s AND b = '%s' AND c = %d AND d = %f AND e LIKE '%%%s%%' AND f = %1\$s AND g = 100%%" )
		);
	}
}
