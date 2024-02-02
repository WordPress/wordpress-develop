<?php

/**
 * Unit tests covering HTML API functionality.
 *
 * This test suite runs a set of tests on the HTML API using a third-party suite of test fixtures.
 * A third-party test suite allows the HTML API's behavior to be compared against an external
 * standard. Without a third-party, there is risk of oversight or misinterpretation of the standard
 * being implemented in application code and in tests. html5lib-tests is used by other projects like
 * browsers or other HTML parsers for the same purpose of validating behavior against an
 * external reference.
 *
 * See the README file at DIR_TESTDATA / html5lib-tests for details on the third-party suite.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.5.0
 *
 * @group html-api
 */
class Tests_HtmlApi_Html5lib extends WP_UnitTestCase {
	/**
	 * The HTML Processor only accepts HTML in document <body>.
	 * Do not run tests that look for anything in document <head>.
	 */
	const SKIP_HEAD_TESTS = true;

	/**
	 * Skip specific tests that may not be supported.
	 */
	const SKIP_TESTS = array(
		'adoption01/line0046'        => 'Unimplemented: Reconstruction of active formatting elements.',
		'adoption01/line0159'        => 'Unimplemented: Reconstruction of active formatting elements.',
		'adoption01/line0318'        => 'Unimplemented: Reconstruction of active formatting elements.',
		'entities02/line0100'        => 'Encoded characters without semicolon termination in attribute values are not handled properly',
		'entities02/line0114'        => 'Encoded characters without semicolon termination in attribute values are not handled properly',
		'entities02/line0128'        => 'Encoded characters without semicolon termination in attribute values are not handled properly',
		'entities02/line0142'        => 'Encoded characters without semicolon termination in attribute values are not handled properly',
		'entities02/line0156'        => 'Encoded characters without semicolon termination in attribute values are not handled properly',
		'plain-text-unsafe/line0001' => 'HTML entities may be mishandled.',
		'plain-text-unsafe/line0105' => 'Binary.',
		'tests1/line0342'            => "Closing P tag implicitly creates opener, which we don't visit.",
		'tests1/line0720'            => 'Unimplemented: Reconstruction of active formatting elements.',
		'tests15/line0001'           => 'Unimplemented: Reconstruction of active formatting elements.',
		'tests15/line0022'           => 'Unimplemented: Reconstruction of active formatting elements.',
		'tests2/line0317'            => 'HTML entities may be mishandled.',
		'tests2/line0408'            => 'HTML entities may be mishandled.',
		'tests2/line0650'            => 'Whitespace only test never enters "in body" parsing mode.',
		'tests20/line0497'           => "Closing P tag implicitly creates opener, which we don't visit.",
		'tests23/line0001'           => 'Unimplemented: Reconstruction of active formatting elements.',
		'tests23/line0041'           => 'Unimplemented: Reconstruction of active formatting elements.',
		'tests23/line0069'           => 'Unimplemented: Reconstruction of active formatting elements.',
		'tests23/line0101'           => 'Unimplemented: Reconstruction of active formatting elements.',
		'tests26/line0263'           => 'BUG: An active formatting element should be created for a trailing text node.',
	);

	/**
	 * Verify the parsing results of the HTML Processor against the
	 * test cases in the Html5lib tests project.
	 *
	 * @dataProvider data_external_html5lib_tests
	 *
	 * @param string $fragment_context Context element in which to parse HTML, such as BODY or SVG.
	 * @param string $html             Given test HTML.
	 * @param string $result           Tree structure of parsed HTML.
	 */
	public function test_parse( $fragment_context, $html, $result ) {
		if ( self::SKIP_HEAD_TESTS ) {
			$html_start = "<html>\n  <head>\n  <body>\n";
			if (
				strlen( $result ) < strlen( $html_start ) ||
				substr( $result, 0, strlen( $html_start ) ) !== $html_start
			) {
				$this->markTestSkipped( 'Skip test with expected content in <head> (unsupported).' );
			}
		}

		if ( array_key_exists( $this->dataName(), self::SKIP_TESTS ) ) {
			$this->markTestSkipped( self::SKIP_TESTS[ $this->dataName() ] );
		}

		$processed_tree = self::build_tree_representation( $fragment_context, $html );

		if ( null === $processed_tree ) {
			$this->markTestIncomplete( 'Test includes unsupported markup.' );
		}

		$this->assertEquals( $result, $processed_tree, "HTML was not processed correctly:\n{$html}" );
	}

	/**
	 * Data provider.
	 *
	 * Tests from https://github.com/html5lib/html5lib-tests
	 *
	 * @return array[]
	 */
	public function data_external_html5lib_tests() {
		$test_dir = DIR_TESTDATA . '/html5lib-tests/tree-construction/';

		$handle = opendir( $test_dir );
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( ! stripos( $entry, '.dat' ) ) {
				continue;
			}

			if ( 'entities01.dat' === $entry || 'entities02.dat' === $entry ) {
				continue;
			}

			foreach ( self::parse_html5_dat_testfile( $test_dir . $entry ) as $k => $test ) {
				// strip .dat extension from filename
				$test_suite = substr( $entry, 0, -4 );
				$line       = str_pad( strval( $test[0] ), 4, '0', STR_PAD_LEFT );

				yield "{$test_suite}/line{$line}" => array_slice( $test, 1 );
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
	private static function build_tree_representation( $fragment_context, $html ) {
		$processor = WP_HTML_Processor::create_fragment( $html, "<{$fragment_context}>" );
		if ( null === $processor ) {
			return null;
		}

		$output = "<html>\n  <head>\n  <body>\n";

		// Initially, assume we're 2 levels deep at: html > body > [position]
		$indent_level = 2;
		$indent       = '  ';

		while ( $processor->next_token() ) {
			if ( ! is_null( $processor->get_last_error() ) ) {
				return null;
			}

			switch ( $processor->get_token_type() ) {
				case '#tag':
					$tag_name = strtolower( $processor->get_tag() );

					if ( $processor->is_tag_closer() ) {
						--$indent_level;
						break;
					}

					$tag_indent = count( $processor->get_breadcrumbs() ) - 1;

					if ( ! WP_HTML_Processor::is_void( $tag_name ) ) {
						$indent_level = $tag_indent + 1;
					}

					$output .= str_repeat( $indent, $tag_indent ) . "<{$tag_name}>\n";

					$attribute_names = $processor->get_attribute_names_with_prefix( '' );
					if ( $attribute_names ) {
						sort( $attribute_names, SORT_STRING );

						foreach ( $attribute_names as $attribute_name ) {
							$val = $processor->get_attribute( $attribute_name );
							/*
							 * Attributes with no value are `true` with the HTML API,
							 * We map use the empty string value in the tree structure.
							 */
							if ( true === $val ) {
								$val = '';
							}
							$output .= str_repeat( $indent, $tag_indent + 1 ) . "{$attribute_name}=\"{$val}\"\n";
						}
					}

					break;

				case '#text':
					$output .= str_repeat( $indent, $indent_level ) . "\"{$processor->get_modifiable_text()}\"\n";
					break;

				case '#comment':
					switch ( $processor->get_comment_type() ) {
						case WP_HTML_Processor::COMMENT_AS_ABRUPTLY_CLOSED_COMMENT:
						case WP_HTML_Processor::COMMENT_AS_HTML_COMMENT:
							$comment_text_content = $processor->get_modifiable_text();
							break;

						case WP_HTML_Processor::COMMENT_AS_CDATA_LOOKALIKE:
							$comment_text_content = "[CDATA[{$processor->get_modifiable_text()}]]";
							break;

						default:
							throw new Error( "Unhandled comment type for tree construction: {$processor->get_comment_type()}" );
					}
					// Comments must be "<" then "!-- " then the data then " -->".
					$output .= str_repeat( $indent, $indent_level ) . "<!-- {$comment_text_content} -->\n";
					break;

				default:
					$serialized_token_type = var_export( $processor->get_token_type(), true );
					throw new Error( "Unhandled token type for tree construction: {$serialized_token_type}" );
			}
		}

		if ( $processor->paused_at_incomplete_token() ) {
			return null;
		}

		// Tests always end with a trailing newline.
		return $output . "\n";
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
						yield array(
							$test_line_number,
							$test_context_element,
							// Remove the trailing newline
							substr( $test_html, 0, -1 ),
							$test_dom,
						);
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
				 *
				 * - Element nodes must be represented by a "<" then the tag name string ">", and all the attributes must be given, sorted lexicographically by UTF-16 code unit according to their attribute name string, on subsequent lines, as if they were children of the element node.
				 * - Attribute nodes must have the attribute name string, then an "=" sign, then the attribute value in double quotes (").
				 * - Text nodes must be the string, in double quotes. Newlines aren't escaped.
				 * - Comments must be "<" then "!-- " then the data then " -->".
				 * - DOCTYPEs must be "<!DOCTYPE " then the name then if either of the system id or public id is non-empty a space, public id in double-quotes, another space an the system id in double-quotes, and then in any case ">".
				 * - Processing instructions must be "<?", then the target, then a space, then the data and then ">". (The HTML parser cannot emit processing instructions, but scripts can, and the WebVTT to DOM rules can emit them.)
				 * - Template contents are represented by the string "content" with the children below it.
				 */
				case 'document':
					if ( '|' === $line[0] ) {
						$test_dom .= substr( $line, 2 );
					} else {
						// This is a text node that includes unescaped newlines.
						// Everything else should be singles lines starting with "| ".
						$test_dom .= $line;
					}
					break;
			}
		}

		fclose( $handle );

		// Return the last result when reaching the end of the file.
		return array(
			$test_line_number,
			$test_context_element,
			// Remove the trailing newline
			substr( $test_html, 0, -1 ),
			$test_dom,
		);
	}
}
