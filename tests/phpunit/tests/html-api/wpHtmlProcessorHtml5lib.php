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
	 * Verify the parsing results of the HTML Processor against the
	 * test cases in the Html5lib tests project.
	 *
	 * @ticket {TICKET_NUMBER}
	 *
	 * @dataProvider data_external_html5lib_tests
	 *
	 * @param string $fragment_context Context element in which to parse HTML, such as BODY or SVG.
	 * @param string $html             Given test HTML.
	 * @param string $result           Tree structure of parsed HTML.
	 */
	public function test_external_html5lib( $fragment_context, $html, $result ) {
		$processed_tree = self::build_html5_treelike_string( $fragment_context, $html );
		if ( null === $processed_tree ) {
			$this->markTestSkipped( 'Skipped test because it contains unsupported markup.' );
		} else {
			$this->assertEquals( $processed_tree, $result );
		}
	}

	/**
	 * Data provider.
	 *
	 * Tests from https://github.com/html5lib/html5lib-tests
	 *
	 * @return array[]
	 */
	public function data_external_html5lib_tests() {
		$test_dir = __DIR__ . '/html5lib-tests/tree-construction/';

		$handle = opendir( $test_dir );
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( ! stripos( $entry, '.dat' ) ) {
				continue;
			}

			// These tests contain no tags, which isn't yet
			// supported by the HTML API.
			if ( 'comments01.dat' === $entry ) {
				continue;
			}

			foreach ( self::parse_html5_dat_testfile( $test_dir . $entry ) as $k => $test ) {
				$case = $k + 1;
				yield "{$entry}/case {$case} - line {$test[0]}" => array_slice( $test, 1 );
			}
		}
		closedir( $handle );
	}


	/**
	 * Generates the tree-like structure represented in the Html5lib tests.
	 *
	 * @param string $fragment_context Context element in which to parse HTML, such as BODY or SVG.
	 * @param string $html             Given test HTML.
	 * @return string|null Tree structure of parsed HTML, if supported, else null.
	 */
	public static function build_html5_treelike_string( $fragment_context, $html ) {
		$p = WP_HTML_Processor::create_fragment( $html, "<{$fragment_context}>" );
		if ( null === $p ) {
			return null;
		}

		$output = "<html>\n  <head>\n  <body>\n";
		while ( $p->next_tag() ) {
			// Breadcrumbs include this tag, so skip 1 nesting level.
			foreach ( $p->get_breadcrumbs() as $index => $_ ) {
				if ( $index ) {
					$output .= '  ';
				}
			}
			$t       = strtolower( $p->get_tag() );
			$output .= "<{$t}>\n";
		}

		if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $p->get_last_error() ) {
			return null;
		}

		return $output;
	}

	/**
	 * Convert a given Html5lib test file into a test triplet.
	 *
	 * @param string $filename Path to `.dat` file with test cases.
	 *
	 * @return array|Generator Test triplets of HTML fragment context element,
	 *                         HTML, and the DOM structure it represents.
	 */
	public static function parse_html5_dat_testfile( $filename ) {
		$handle = fopen( $filename, 'r', false );

		/**
		 * Represents which section of the test case is being parsed.
		 *
		 * @var ?string
		 */
		$state = null;

		$line_number          = 0;
		$test_html            = '';
		$test_dom             = '';
		$test_context_element = 'body';
		$test_line_number     = 0;

		while ( false !== ( $line = fgets( $handle ) ) ) {
			++$line_number;

			if ( '#' === $line[0] ) {
				// Finish section.
				if ( "#data\n" === $line ) {
					// Yield when switching from a previous state.
					if ( $state ) {
						yield array( $test_line_number, $test_context_element, $test_html, $test_dom );
					}

					// Finish previous test.
					$test_line_number = $line_number;
					$test_html        = '';
					$test_dom         = '';
				}

				$state = trim( substr( $line, 1 ) );

				continue;
			}

			switch ( $state ) {
				/*
				 * Each test must begin with a string "#data" followed by a newline (LF). All
				 * subsequent lines until a line that says "#errors" are the test data and must be
				 * passed to the system being tested unchanged, except with the final newline (on the
				 * last line) removed.
				 */
				case 'data':
					$test_html .= $line;
					break;

				/*
				 * Then there *may* be a line that says "#document-fragment", which must
				 * be followed by a newline (LF), followed by a string of characters that
				 * indicates the context element, followed by a newline (LF). If the
				 * string of characters starts with "svg ", the context element is in
				 * the SVG namespace and the substring after "svg " is the local name.
				 * If the string of characters starts with "math ", the context element
				 * is in the MathML namespace and the substring after "math " is the
				 * local name. Otherwise, the context element is in the HTML namespace
				 * and the string is the local name. If this line is present the "#data"
				 * must be parsed using the HTML fragment parsing algorithm with the
				 * context element as context.
				 */
				case 'document-fragment':
					$test_context_element = explode( ' ', $line )[0];
					break;

				/*
				 * Then there must be a line that says "#document", which must be followed by a dump of
				 * the tree of the parsed DOM. Each node must be represented by a single line. Each line
				 * must start with "| ", followed by two spaces per parent node that the node has before
				 * the root document node.
				 */
				case 'document':
					// Ignore everything that doesn't look like an element.
					if ( '|' === $line[0] ) {
						$candidate = substr( $line, 2 );
						$trimmed   = trim( $candidate );
						// We ignore `<!` starters to skip `<!--` (comment opener) and `<!DOCTYPE`.
						if ( '<' === $trimmed[0] && '<!' !== substr( $trimmed, 0, 2 ) ) {
							$test_dom .= $candidate;
						}
					}
					break;
			}
		}

		fclose( $handle );

		// Return the last result when reaching the end of the file.
		return array( $line_number, $test_context_element, $test_html, $test_dom );
	}
}
