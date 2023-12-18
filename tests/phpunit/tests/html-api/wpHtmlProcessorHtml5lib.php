<?php

/**
 * Unit tests covering WP_HTML_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since TODO
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorHtml5lib extends WP_UnitTestCase {
	/**
	 * @dataProvider data_external_html5lib_tests
	 */
	public function test_external_html5lib( $html, $result ) {

		$processed_tree = self::build_html5_treelike_string( $html );
		$this->assertEquals( $processed_tree, $result );
	}


	/**
	 * Data provider.
	 *
	 * Tests from https://github.com/html5lib/html5lib-tests
	 */
	public function data_external_html5lib_tests() {
		$test_dir = __DIR__ . '/html5lib-tests/tree-construction/';

		$handle = opendir( $test_dir );
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( !stripos( $entry, '.dat' ) ) {
				continue;
			}

			foreach (self::parse_html5_dat_testfile($test_dir . $entry) as $k => $test) {
				yield "{$entry}/case {$k}" => $test;
			}
		}
		closedir( $handle );
	}


	static function build_html5_treelike_string( $html ) {
		$p = WP_HTML_Processor::create_fragment( $html );

		$output = "<html>\n  <head>\n  <body>\n";
		while ( $p->next_tag() ) {
			// breadcrumbs include our tag, so skip 1 nesting level
			foreach ( $p->get_breadcrumbs() as $index => $_) {
				if ( $index ) {
					$output .= '  ';
				}
			}
			$t = strtolower( $p->get_tag() );
			$output .= "<{$t}>\n";
		}

		return $output;
	}

	static function parse_html5_dat_testfile( $filename ) {
		$handle = fopen( $filename, 'r', false );

		/**
		 * @var ?string
		 */
		$state = null;

		$test_html = '';
		$test_dom = '';

		while ( false !== ( $line = fgets( $handle ) ) ) {

			if ( $line[0] === '#' ) {
				// finish section
				if ( $line == "#data\n" ) {
					// If we're switching from a previous state, yield
					if ( $state ) {
						yield [ $test_html, $test_dom ];
					}

					// finish previous test
					$test_html = "";
					$test_dom = "";
				}

				$state = trim( substr( $line, 1 ) );

				continue;
			}

			switch ( $state ) {
				// Each test must begin with a string "#data" followed by a newline (LF). All
				// subsequent lines until a line that says "#errors" are the test data and must be
				// passed to the system being tested unchanged, except with the final newline (on the
				// last line) removed.
				case 'data':
					$test_html .= $line;
					break;

				// Then there must be a line that says "#document", which must be followed by a dump of
				// the tree of the parsed DOM. Each node must be represented by a single line. Each line
				// must start with "| ", followed by two spaces per parent node that the node has before
				// the root document node.
				case 'document':
					// Ignore everything that doesn't look like an element
					if ( '|' === $line[0] ) {
						$candidate = substr( $line, 2 );
						$trimmed = trim( $candidate );
						if ( '<' === $trimmed[0] && '<!DOCTYPE' !== substr( $trimmed, 0, 9 ) ) {
							$test_dom .= $candidate;
						}
					}
					break;
			}
		}

		// EOF - return our last result
		return [ $test_html, $test_dom ];

		fclose( $handle );
	}
}

