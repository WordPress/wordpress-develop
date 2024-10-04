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
 * @since 6.6.0
 *
 * @group html-api
 * @group html-api-html5lib-tests
 */
class Tests_HtmlApi_Html5lib extends WP_UnitTestCase {
	const TREE_INDENT = '  ';

	/**
	 * Skip specific tests that may not be supported or have known issues.
	 */
	const SKIP_TESTS = array(
		'noscript01/line0014' => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests14/line0022'    => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests14/line0055'    => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests19/line0488'    => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests19/line0500'    => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests19/line1079'    => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests2/line0207'     => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests2/line0686'     => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests2/line0697'     => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'tests2/line0709'     => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
		'webkit01/line0231'   => 'Unimplemented: This parser does not add missing attributes to existing HTML or BODY tags.',
	);

	/**
	 * Verify the parsing results of the HTML Processor against the
	 * test cases in the Html5lib tests project.
	 *
	 * @ticket 60227
	 *
	 * @dataProvider data_external_html5lib_tests
	 *
	 * @param string|null $fragment_context Context element in which to parse HTML, such as BODY or SVG.
	 * @param string      $html             Given test HTML.
	 * @param string      $expected_tree    Tree structure of parsed HTML.
	 */
	public function test_parse( ?string $fragment_context, string $html, string $expected_tree ) {
		try {
			$processed_tree = self::build_tree_representation( $fragment_context, $html );
		} catch ( WP_HTML_Unsupported_Exception $e ) {
			$this->markTestSkipped( "Unsupported markup: {$e->getMessage()}" );
			return;
		}

		if ( null === $processed_tree ) {
			$this->markTestSkipped( 'Test includes unsupported markup.' );
			return;
		}

		$fragment_detail = $fragment_context ? " in context <{$fragment_context}>" : '';

		/*
		 * The HTML processor does not produce html, head, body tags if the processor does not reach them.
		 * HTML tree construction will always produce these tags, the HTML API does not at this time.
		 */
		$auto_generated_html_head_body = "<html>\n  <head>\n  <body>\n\n";
		$auto_generated_head_body      = "  <head>\n  <body>\n\n";
		$auto_generated_body           = "  <body>\n\n";
		if ( str_ends_with( $expected_tree, $auto_generated_html_head_body ) && ! str_ends_with( $processed_tree, $auto_generated_html_head_body ) ) {
			if ( str_ends_with( $processed_tree, "<html>\n  <head>\n\n" ) ) {
				$processed_tree = substr_replace( $processed_tree, "  <body>\n\n", -1 );
			} elseif ( str_ends_with( $processed_tree, "<html>\n\n" ) ) {
				$processed_tree = substr_replace( $processed_tree, "  <head>\n  <body>\n\n", -1 );
			} else {
				$processed_tree = substr_replace( $processed_tree, $auto_generated_html_head_body, -1 );
			}
		} elseif ( str_ends_with( $expected_tree, $auto_generated_head_body ) && ! str_ends_with( $processed_tree, $auto_generated_head_body ) ) {
			if ( str_ends_with( $processed_tree, "<head>\n\n" ) ) {
				$processed_tree = substr_replace( $processed_tree, "  <body>\n\n", -1 );
			} else {
				$processed_tree = substr_replace( $processed_tree, $auto_generated_head_body, -1 );
			}
		} elseif ( str_ends_with( $expected_tree, $auto_generated_body ) && ! str_ends_with( $processed_tree, $auto_generated_body ) ) {
			$processed_tree = substr_replace( $processed_tree, $auto_generated_body, -1 );
		}

		$this->assertSame( $expected_tree, $processed_tree, "HTML was not processed correctly{$fragment_detail}:\n{$html}" );
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

			foreach ( self::parse_html5_dat_testfile( $test_dir . $entry ) as $k => $test ) {
				// strip .dat extension from filename
				$test_suite = substr( $entry, 0, -4 );
				$line       = str_pad( strval( $test[0] ), 4, '0', STR_PAD_LEFT );
				$test_name  = "{$test_suite}/line{$line}";

				$test_context_element = $test[1];

				if ( self::should_skip_test( $test_context_element, $test_name ) ) {
					continue;
				}

				yield $test_name => array_slice( $test, 1 );
			}
		}
		closedir( $handle );
	}

	/**
	 * Determines whether a test case should be skipped.
	 *
	 * @param string $test_name     Test name.
	 * @param string $expected_tree Expected HTML tree structure.
	 *
	 * @return bool True if the test case should be skipped. False otherwise.
	 */
	private static function should_skip_test( ?string $test_context_element, string $test_name ): bool {
		if ( null !== $test_context_element && 'body' !== $test_context_element ) {
			return true;
		}

		if ( array_key_exists( $test_name, self::SKIP_TESTS ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Generates the tree-like structure represented in the Html5lib tests.
	 *
	 * @param string|null $fragment_context Context element in which to parse HTML, such as BODY or SVG.
	 * @param string      $html             Given test HTML.
	 * @return string|null Tree structure of parsed HTML, if supported, else null.
	 */
	private static function build_tree_representation( ?string $fragment_context, string $html ) {
		$processor = $fragment_context
			? WP_HTML_Processor::create_fragment( $html, "<{$fragment_context}>" )
			: WP_HTML_Processor::create_full_parser( $html );
		if ( null === $processor ) {
			throw new WP_HTML_Unsupported_Exception( "Could not create a parser with the given fragment context: {$fragment_context}.", '', 0, '', array(), array() );
		}

		/*
		 * The fragment parser will start in 2 levels deep at: html > body > [position]
		 * and requires adjustment to initial parameters.
		 * The full parser will not.
		 */
		$output       = '';
		$indent_level = 0;
		$was_text     = null;
		$text_node    = '';

		while ( $processor->next_token() ) {
			if ( null !== $processor->get_last_error() ) {
				break;
			}

			$token_name = $processor->get_token_name();
			$token_type = $processor->get_token_type();
			$is_closer  = $processor->is_tag_closer();

			if ( $was_text && '#text' !== $token_name ) {
				if ( '' !== $text_node ) {
					$output .= "{$text_node}\"\n";
				}
				$was_text  = false;
				$text_node = '';
			}

			switch ( $token_type ) {
				case '#doctype':
					$doctype = $processor->get_doctype_info();
					$output .= "<!DOCTYPE {$doctype->name}";
					if ( null !== $doctype->public_identifier || null !== $doctype->system_identifier ) {
						$output .= " \"{$doctype->public_identifier}\" \"{$doctype->system_identifier}\"";
					}
					$output .= ">\n";
					break;

				case '#tag':
					$namespace = $processor->get_namespace();
					$tag_name  = 'html' === $namespace
						? strtolower( $processor->get_tag() )
						: "{$namespace} {$processor->get_qualified_tag_name()}";

					if ( $is_closer ) {
						--$indent_level;

						if ( 'html' === $namespace && 'TEMPLATE' === $token_name ) {
							--$indent_level;
						}

						break;
					}

					$tag_indent = $indent_level;

					if ( $processor->expects_closer() ) {
						++$indent_level;
					}

					$output .= str_repeat( self::TREE_INDENT, $tag_indent ) . "<{$tag_name}>\n";

					$attribute_names = $processor->get_attribute_names_with_prefix( '' );
					if ( $attribute_names ) {
						$sorted_attributes = array();
						foreach ( $attribute_names as $attribute_name ) {
							$sorted_attributes[ $attribute_name ] = $processor->get_qualified_attribute_name( $attribute_name );
						}

						/*
						 * Sorts attributes to match html5lib sort order.
						 *
						 *  - First comes normal HTML attributes.
						 *  - Then come adjusted foreign attributes; these have spaces in their names.
						 *  - Finally come non-adjusted foreign attributes; these have a colon in their names.
						 *
						 * Example:
						 *
						 *       From: <math xlink:author definitionurl xlink:title xlink:show>
						 *     Sorted: 'definitionURL', 'xlink show', 'xlink title', 'xlink:author'
						 */
						uasort(
							$sorted_attributes,
							static function ( $a, $b ) {
								$a_has_ns = str_contains( $a, ':' );
								$b_has_ns = str_contains( $b, ':' );

								// Attributes with `:` should follow all other attributes.
								if ( $a_has_ns !== $b_has_ns ) {
									return $a_has_ns ? 1 : -1;
								}

								$a_has_sp = str_contains( $a, ' ' );
								$b_has_sp = str_contains( $b, ' ' );

								// Attributes with a namespace ' ' should come after those without.
								if ( $a_has_sp !== $b_has_sp ) {
									return $a_has_sp ? 1 : -1;
								}

								return $a <=> $b;
							}
						);

						foreach ( $sorted_attributes as $attribute_name => $display_name ) {
							$val = $processor->get_attribute( $attribute_name );
							/*
							 * Attributes with no value are `true` with the HTML API,
							 * We map use the empty string value in the tree structure.
							 */
							if ( true === $val ) {
								$val = '';
							}
							$output .= str_repeat( self::TREE_INDENT, $tag_indent + 1 ) . "{$display_name}=\"{$val}\"\n";
						}
					}

					// Self-contained tags contain their inner contents as modifiable text.
					$modifiable_text = $processor->get_modifiable_text();
					if ( '' !== $modifiable_text ) {
						$output .= str_repeat( self::TREE_INDENT, $tag_indent + 1 ) . "\"{$modifiable_text}\"\n";
					}

					if ( 'html' === $namespace && 'TEMPLATE' === $token_name ) {
						$output .= str_repeat( self::TREE_INDENT, $indent_level ) . "content\n";
						++$indent_level;
					}

					break;

				case '#cdata-section':
				case '#text':
					$text_content = $processor->get_modifiable_text();
					if ( '' === $text_content ) {
						break;
					}
					$was_text = true;
					if ( '' === $text_node ) {
						$text_node .= str_repeat( self::TREE_INDENT, $indent_level ) . '"';
					}
					$text_node .= $text_content;
					break;

				case '#funky-comment':
					// Comments must be "<" then "!-- " then the data then " -->".
					$output .= str_repeat( self::TREE_INDENT, $indent_level ) . "<!-- {$processor->get_modifiable_text()} -->\n";
					break;

				case '#comment':
					// Comments must be "<" then "!-- " then the data then " -->".
					$output .= str_repeat( self::TREE_INDENT, $indent_level ) . "<!-- {$processor->get_full_comment_text()} -->\n";
					break;

				default:
					$serialized_token_type = var_export( $processor->get_token_type(), true );
					throw new Error( "Unhandled token type for tree construction: {$serialized_token_type}" );
			}
		}

		if ( null !== $processor->get_unsupported_exception() ) {
			throw $processor->get_unsupported_exception();
		}

		if ( null !== $processor->get_last_error() ) {
			throw new WP_HTML_Unsupported_Exception( "Parser error: {$processor->get_last_error()}", '', 0, '', array(), array() );
		}

		if ( $processor->paused_at_incomplete_token() ) {
			throw new WP_HTML_Unsupported_Exception( 'Paused at incomplete token.', '', 0, '', array(), array() );
		}

		if ( '' !== $text_node ) {
			$output .= "{$text_node}\"\n";
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
		 * @var string|null
		 */
		$state = null;

		$line_number          = 0;
		$test_html            = '';
		$test_dom             = '';
		$test_context_element = null;
		$test_script_flag     = false;
		$test_line_number     = 0;

		while ( false !== ( $line = fgets( $handle ) ) ) {
			++$line_number;

			if ( '#' === $line[0] ) {
				// Finish section.
				if ( "#data\n" === $line ) {
					/*
					 * Yield when switching from a previous state.
					 * Do not yield tests with the scripting flag enabled. The scripting flag
					 * is always disabled in the HTML API.
					 */
					if ( $state && ! $test_script_flag ) {
						yield array(
							$test_line_number,
							$test_context_element,
							// Remove the trailing newline
							substr( $test_html, 0, -1 ),
							$test_dom,
						);
					}

					// Finish previous test.
					$test_line_number     = $line_number;
					$test_html            = '';
					$test_dom             = '';
					$test_context_element = null;
					$test_script_flag     = false;
				}
				if ( "#script-on\n" === $line ) {
					$test_script_flag = true;
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
					$test_context_element = trim( $line );
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
