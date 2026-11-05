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

	public function test_fill_template_puts_a_harmless_value_in_each_placeholder() {
		$this->assertSame(
			"SELECT * FROM `i` WHERE a = 'x' AND b = 'x' AND c = 0 AND d = 0 AND e LIKE '%%%s%%' AND f = x AND g = 100%",
			WP_Invariant_SQL_Lexer::fill_template( "SELECT * FROM %i WHERE a = %s AND b = '%s' AND c = %d AND d = %f AND e LIKE '%%%s%%' AND f = %1\$s AND g = 100%%" )
		);
	}
}
