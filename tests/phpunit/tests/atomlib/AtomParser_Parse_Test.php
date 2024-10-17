<?php
/**
 * Unit tests covering AtomParser functionality.
 *
 * @package    WordPress
 * @subpackage AtomLib
 */

/**
 * Test Atom Syndication Format.
 *
 * @requires extension xml
 *
 * @covers AtomParser::parse
 */
final class AtomParser_Parse_Test extends WP_UnitTestCase {

	/**
	 * Ensure the class being tested is loaded.
	 */
	public function set_up() {
		require_once dirname( __DIR__, 4 ) . '/src/wp-includes/atomlib.php';
	}

	/**
	 * Test that the `AtomParser::parse()` method correctly sets callback functions to handle certain parts of the XML.
	 *
	 * Safeguards handling of the PHP 8.4 deprecation of `xml_set_object()`.
	 */
	public function test_parse_sets_handlers() {
		$atom = new class() extends AtomParser {
			public $start_element_call_counter = 0;
			public $end_element_call_counter   = 0;
			public $start_ns_call_counter      = 0;
			public $end_ns_call_counter        = 0;
			public $cdata_call_counter         = 0;
			public $default_call_counter       = 0;

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Overloading property of upstream class.
			public $FILE = __DIR__ . '/../../data/feed/AtomParser_Parse_Test.xml';

			public function start_element( $parser, $name, $attrs ) {
				++$this->start_element_call_counter;
			}
			public function end_element( $parser, $name ) {
				++$this->end_element_call_counter;
			}
			public function start_ns( $parser, $prefix, $uri ) {
				++$this->start_ns_call_counter;
			}
			public function end_ns( $parser, $prefix ) {
				++$this->end_ns_call_counter;
			}
			public function cdata( $parser, $data ) {
				++$this->cdata_call_counter;
			}
			public function _default( $parser, $data ) {
				++$this->default_call_counter;
			}
		};

		$this->assertTrue( $atom->parse(), 'Parsing of XML file failed' );

		// Ensure no errors were logged.
		$this->assertNull( $atom->error, 'Unexpected errors encountered' );

		$msg = '%s() handler did not get called expected nr of times';
		$this->assertSame( 28, $atom->start_element_call_counter, sprintf( $msg, 'start_element' ) );
		$this->assertSame( 28, $atom->end_element_call_counter, sprintf( $msg, 'end_element' ) );
		$this->assertSame( 2, $atom->start_ns_call_counter, sprintf( $msg, 'start_ns' ) );
		$this->assertSame( 0, $atom->end_ns_call_counter, sprintf( $msg, 'end_ns' ) );
		$this->assertSame( 57, $atom->cdata_call_counter, sprintf( $msg, 'cdata' ) );
		$this->assertSame( 2, $atom->default_call_counter, sprintf( $msg, '_default' ) );
	}
}
