<?php
/**
 * Unit tests covering WP_Block_Scanner functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.9.0
 *
 * @group block-scanner
 *
 * @coversDefaultClass WP_Block_Scanner
 */
class Tests_Blocks_BlockScanner_WP_Block_Scanner extends WP_UnitTestCase {
	/**
	 * Verifies that a block scanner will be created for an empty string.
	 *
	 * This test is foundational for many other to follow, so while it’s not
	 * interesting in and of itself, in passing it removes trivial assertions
	 * from later and more-interesting tests.
	 *
	 * @ticket 61401
	 */
	public function test_creates_with_empty_string() {
		$scanner = WP_Block_Scanner::create( '' );
		$this->assertInstanceOf(
			'WP_Block_Scanner',
			$scanner,
			'Failed to create the Block Scanner object for an empty string.'
		);
	}

	/**
	 * Verifies that no block delimiters are found in an empty string.
	 *
	 * @ticket 61401
	 */
	public function test_finds_no_block_delimiters_for_empty_string() {
		$scanner = WP_Block_Scanner::create( '' );

		$this->assertFalse(
			$scanner->next_delimiter(),
			'Should not have found any delimiters.'
		);
	}

	/**
	 * Verifies that freeform delimiters are found when requested for
	 * posts with no block content.
	 *
	 * @ticket 61401
	 */
	public function test_finds_freeform_delimiters_in_post_without_blocks() {
		$scanner = WP_Block_Scanner::create( 'This is <em>non-block</em> content.' );

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found the start of a freeform block but found nothing.'
		);

		$this->assertSame(
			WP_Block_Scanner::OPENER,
			$scanner->get_delimiter_type(),
			'Should have found an opening block delimiter.'
		);

		$this->assertSame(
			'core/freeform',
			$scanner->get_block_type(),
			'Should have found the start of a freeform block.'
		);

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found the end of a freeform block but found nothing.'
		);

		$this->assertSame(
			WP_Block_Scanner::CLOSER,
			$scanner->get_delimiter_type(),
			'Should have found a block closer.'
		);

		$this->assertSame(
			'core/freeform',
			$scanner->get_block_type(),
			'Should have found a freeform block closer.'
		);
	}

	/**
	 * Verifies that a post containing a single void block finds the block and nothing else.
	 *
	 * @ticket 61401
	 */
	public function test_finds_post_of_void_block() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:recent-posts /-->' );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found a block delimiter but found nothing.'
		);

		$this->assertSame(
			WP_Block_Scanner::VOID,
			$scanner->get_delimiter_type(),
			'Should have found a void block delimiter.'
		);

		$this->assertSame(
			'core/recent-posts',
			$scanner->get_block_type(),
			'Should have found a core/recent-posts void block.'
		);
	}

	/**
	 * Verifies that a post containing a single basic block finds the block opener and closer.
	 *
	 * @ticket 61401
	 */
	public function test_finds_open_and_close_of_post_with_basic_block() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->' );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found an opening block delimiter but found nothing.'
		);

		$this->assertSame(
			WP_Block_Scanner::OPENER,
			$scanner->get_delimiter_type(),
			'Should have found an opening block delimiter.'
		);

		$this->assertTrue(
			$scanner->opens_block( 'core/paragraph' ),
			'Should have found an opening core/paragraph delimiter.'
		);

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found a closing block delimiter but found nothing.'
		);

		$this->assertSame(
			WP_Block_Scanner::CLOSER,
			$scanner->get_delimiter_type(),
			'Should have found a closing block delimiter.'
		);

		$this->assertSame(
			'core/paragraph',
			$scanner->get_block_type(),
			'Should have found a closing paragraph delimiter.'
		);
	}

	/**
	 * Verifies that the parser refuses to parse the end of a document
	 * which partially contains what could be a block delimiter.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_partial_delimiter_endings
	 *
	 * @param string $html Input ending in a partial block delimiter.
	 */
	public function test_rejects_on_incomplete_inputs( $html ) {
		$scanner = WP_Block_Scanner::create( "<!-- wp:test/canary /-->{$html}" );

		$scanner->next_delimiter();
		$this->assertTrue(
			$scanner->opens_block( 'test/canary' ),
			'Should have found the test/canary block: check test code setup.'
		);

		$this->assertFalse(
			$scanner->next_delimiter(),
			'Should have failed to find any blocks after the test canary.'
		);

		$this->assertSame(
			WP_Block_Scanner::INCOMPLETE_INPUT,
			$scanner->get_last_error(),
			'Should have bailed because the input was incomplete.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_partial_delimiter_endings() {
		$tests = array();

		$delimiters = array(
			'opener' => '<!-- wp:core/paragraph {} -->',
			'void'   => '<!-- wp:my-plugin/mark /-->',
			'closer' => '<!-- /wp:group -->',
		);

		foreach ( $delimiters as $kind => $delimiter ) {
			for ( $i = strlen( $delimiter ) - 1; $i > 0; $i-- ) {
				$partial                        = substr( $delimiter, 0, $i );
				$tests[ "{$kind}: {$partial}" ] = array( $partial );
			}
		}

		return $tests;
	}

	/**
	 * Verifies that it’s not possible to proceed after reaching an error.
	 *
	 * @ticket 61401
	 */
	public function test_rejects_once_errored_out() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:incomplete' );

		$this->assertFalse(
			$scanner->next_delimiter(),
			"Shoudn’t have found any delimiters but found a '{$scanner->get_block_type()}'."
		);

		$this->assertSame(
			WP_Block_Scanner::INCOMPLETE_INPUT,
			$scanner->get_last_error(),
			'Should have reported incomplete input.'
		);

		$this->assertFalse(
			$scanner->next_delimiter(),
			'Should have failed to proceed after encountering an error.'
		);
	}

	/**
	 * Verifies that corrupted block delimiters are not matched as delimiters.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_invalid_block_delimiters_as_html_comments
	 *
	 * @param string $html Input containing an invalid block delimiter.
	 */
	public function test_rejects_invalid_block_comment_delimiters_as_html_comments( $html ) {
		$scanner = WP_Block_Scanner::create( "<!-- wp:tests/before /-->{$html}<!-- wp:tests/after /-->" );

		$scanner->next_delimiter();
		$this->assertTrue(
			$scanner->opens_block( 'tests/before' ),
			"Should have found the 'tests/before' block before the invalid block delimiter but found a '{$scanner->get_block_type()}' instead."
		);

		$scanner->next_token( 'visit-html' );
		$this->assertTrue(
			$scanner->opens_block( 'freeform' ),
			"Should have found the malform block delimiter as an HTML comment, but found a '{$scanner->get_block_type()}' instead."
		);

		$scanner->next_delimiter();
		$this->assertTrue(
			$scanner->opens_block( 'tests/after' ),
			"Should have found the 'tests/after' block after the invalid block delimiter but found a '{$scanner->get_block_type()}' instead."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_block_delimiters_as_html_comments() {
		return array(
			'Shortest HTML comment'         => array( '<!-->' ),
			'Span-of-dashes'                => array( '<!------>' ),
			'Empty HTML comment'            => array( '<!-- -->' ),
			'HTML comment with exclamation' => array( '<!-- --! is not the end -->' ),
			'No spaces, minimal info'       => array( '<!--wp:block-->' ),
			'No spaces, minimal info, void' => array( '<!--wp:block/-->' ),
			'No spaces, empty JSON'         => array( '<!--wp:block{}-->' ),
			'No spaces, empty JSON, void'   => array( '<!--wp:block{}/-->' ),
			'No space before wp:'           => array( '<!--wp:block -->' ),
			'No space after name'           => array( '<!-- wp:block-->' ),
			'No space before JSON'          => array( '<!-- wp:block{} -->' ),
			'No space after JSON'           => array( '<!-- wp:block {}-->' ),
			'Missing wp:'                   => array( '<!-- core/paragraph -->' ),
			'Malformed wp:'                 => array( '<!-- wordpress:core/paragraph -->' ),
			'Malformed block namespace'     => array( '<!-- wp:3more/block -->' ),
			'Malformed block name'          => array( '<!-- wp:core/paragraph/variation -->' ),
			'Invalid block name characters' => array( '<!-- wp:core/32-block -->' ),
		);
	}

	/**
	 * Verifies that incomplete HTML comments which could not produce delimiters
	 * are not considered incomplete input by the scanner.
	 *
	 * Note that the block parsing operates first on block comment delimiters and
	 * then on HTML semantics. It’s technically possible for blocks to delimit
	 * invalid or non-well-formed HTML, so there’s no need to try and preserve
	 * other HTML boundaries in the parser the way the HTML API does.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_incomplete_html_comments_that_are_not_delimiters
	 *
	 * @param string $html Input containing an HTML comment that is both incomplete and
	 *                     cannot represent an incomplete block comment delimiter.
	 */
	public function test_unclosed_html_comment_non_delimiter_is_not_incomplete_input( $html ) {
		$scanner = WP_Block_Scanner::create( "<!-- wp:group -->{$html}" );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found setup group block but found nothing: check test setup.'
		);

		$this->assertSame(
			'core/group',
			$scanner->get_block_type(),
			"Should have found setup 'group' block: check test setup."
		);

		$this->assertFalse(
			$scanner->next_delimiter(),
			"Should have found no other delimiters given the incomplete HTML comment, but found a '{$scanner->get_block_type()}' instead."
		);

		$this->assertNull(
			$scanner->get_last_error(),
			'Should have completed without reporting an error.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_html_comments_that_are_not_delimiters() {
		return array(
			'Opening and non-whitespace' => array( '<!--[' ),
			'Non wp: token'              => array( '<!-- this is not a block' ),
			'Non-: after wp'             => array( '<!-- wpm' ),
			'Invalid namespace'          => array( '<!-- wp:)' ),
			'Invalid name'               => array( '<!-- wp:core//test' ),
			'Invalid full name'          => array( '<!-- wp:core/test/variation' ),
			'Missing final ->'           => array( '<!-- -' ),
			'Missing final > (has !)'    => array( '<!-- --!' ),
			'Missing final >'            => array( '<!-- --' ),
		);
	}

	/**
	 * Verifies that block delimiters are matched even when the JSON attributes
	 * are malformed and cannot be parsed.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_invalid_block_json
	 *
	 * @param string $invalid_block_json improperly-encoded JSON document, or JSON not valid for a block’s attributes.
	 */
	public function test_matches_block_with_invalid_json( $invalid_block_json ) {
		$scanner = WP_Block_Scanner::create( "<!-- wp:block {$invalid_block_json} -->" );

		$scanner->next_delimiter();
		$this->assertTrue(
			$scanner->opens_block( 'core/block' ),
			'Should have found the test block but found nothing instead.'
		);

		$parsed_data          = $scanner->allocate_and_return_parsed_attributes();
		$exported_parsed_data = var_export( $parsed_data, true );
		$exported_parsed_data = self::unhide_whitespace( $exported_parsed_data );
		$this->assertNull(
			$parsed_data,
			"Should have failed to parse JSON attributes, but found '{$exported_parsed_data}' instead."
		);

		$this->assertNotNull(
			$scanner->get_last_json_error(),
			'Should have reported an error when attempting to parse JSON attributes.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_block_json() {
		return array(
			'Extra closing }'  => array( '{}}' ),
			'Empty list'       => array( '[]' ),
			'Non-empty list'   => array( '[1, 2, 3]' ),
			'Nested list'      => array( '[{"type": "broken"}]' ),
			'True'             => array( 'true' ),
			'False'            => array( 'false' ),
			'null'             => array( 'null' ),
			'Number (36)'      => array( '36' ),
			'Number (3.141e0)' => array( '3.141e0' ),
			'Unquoted string'  => array( '{"name": block}' ),
			'Letters'          => array( 'not_even_json' ),
		);
	}

	/**
	 * Verifies that a delimiter with unterminated JSON is not treated as a delimiter.
	 *
	 * @ticket 61401
	 */
	public function test_does_not_match_block_with_unterminated_json() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:block {"has_stuff": true -->' );

		$this->assertFalse(
			$scanner->next_delimiter(),
			"Should have failed to find block delimiter but found '{$scanner->get_block_type()}' instead."
		);
	}

	/**
	 * Verifies that a delimiter with content after the JSON attributes is not treated as a delimiter.
	 *
	 * @ticket 61401
	 */
	public function test_does_not_match_block_with_content_after_json() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:block {"has_stuff": true} "not allowed" -->' );

		$this->assertFalse(
			$scanner->next_delimiter(),
			"Should have failed to find block delimiter but found '{$scanner->get_block_type()}' instead."
		);
	}

	/**
	 * Verifies that the appropriate block delimiter type is reported for a matched delimiter.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_delimiters_and_their_types
	 *
	 * @param string $html           Contains a single block delimiter.
	 * @param string $delimiter_type Expected type of delimiter.
	 */
	public function test_reports_proper_delimiter_type( $html, $delimiter_type ) {
		$scanner = WP_Block_Scanner::create( $html );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found test block delimiter but found nothing instead.'
		);

		$this->assertSame(
			$delimiter_type,
			$scanner->get_delimiter_type(),
			'Failed to match the expected delimiter type (opener/closer/void)'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_delimiters_and_their_types() {
		return array(
			'Void'              => array( '<!-- wp:void /-->', WP_Block_Scanner::VOID ),
			'Void, full name'   => array( '<!-- wp:core/void /-->', WP_Block_Scanner::VOID ),
			'Opener'            => array( '<!-- wp:paragraph -->', WP_Block_Scanner::OPENER ),
			'Opener, full name' => array( '<!-- wp:core/list -->', WP_Block_Scanner::OPENER ),
			'Closer'            => array( '<!-- /wp:paragraph -->', WP_Block_Scanner::CLOSER ),
			'Closer, full name' => array( '<!-- /wp:core/list -->', WP_Block_Scanner::CLOSER ),
		);
	}

	/**
	 * Verifies that `get_delimiter_type()` returns `null` before finding any delimiters.
	 *
	 * @ticket 61401
	 */
	public function test_reports_no_delimiter_type_before_scanning() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:any/content -->' );

		$this->assertNull(
			$scanner->get_delimiter_type(),
			'Should not have returned a delimiter type before matching any delimiters.'
		);
	}

	/**
	 * Verifies that `get_delimiter_type()` returns `null` after scanning the last delimiter.
	 *
	 * @ticket 61401
	 */
	public function test_reports_no_delimiter_type_after_scanning() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:any/content -->' );

		while ( $scanner->next_delimiter( null, 'visit-html' ) ) {
			continue;
		}

		$this->assertNull(
			$scanner->get_delimiter_type(),
			'Should not have returned a delimiter type before matching any delimiters.'
		);
	}

	/**
	 * Verifies that `get_delimiter_type()` returns `null` after encountering an error.
	 *
	 * @ticket 61401
	 */
	public function test_reports_no_delimiter_type_after_an_error() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:incomplete/blo' );

		while ( $scanner->next_token( 'visit-html' ) ) {
			continue;
		}

		$this->assertNotNull(
			$scanner->get_last_error(),
			'Should have found an error state but found none: check test setup.'
		);

		$this->assertNull(
			$scanner->get_delimiter_type(),
			'Should not have returned a delimiter type before matching any delimiters.'
		);
	}

	/**
	 * Verifies that the appropriate block type is reported for a matched delimiter.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_delimiters_and_their_block_types
	 */
	public function test_reports_proper_block_type( $html, $block_type ) {
		$scanner = WP_Block_Scanner::create( $html );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found test block delimiter but found nothing instead.'
		);

		$this->assertSame(
			$block_type,
			$scanner->get_block_type(),
			'Should have found the expected block type.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_delimiters_and_their_block_types() {
		return array(
			'Opener, core/group' => array( '<!-- wp:core/group -->', 'core/group' ),
			'Void, core/group'   => array( '<!-- wp:core/group /-->', 'core/group' ),
			'Closer, core/group' => array( '<!-- /wp:core/group -->', 'core/group' ),
			'Opener, group'      => array( '<!-- wp:group -->', 'core/group' ),
			'Void, group'        => array( '<!-- wp:group /-->', 'core/group' ),
			'Closer, group'      => array( '<!-- /wp:group -->', 'core/group' ),
			'Opener, my/group'   => array( '<!-- wp:my/group -->', 'my/group' ),
			'Void, thy/group'    => array( '<!-- wp:thy/group /-->', 'thy/group' ),
			'Closer, the5/group' => array( '<!-- /wp:the-5/group -->', 'the-5/group' ),
		);
	}

	/**
	 * Verifies that the presence of the void flag is properly reported
	 * regardless of a conflict with a closing black.
	 *
	 * When block delimiters contain both the void flag and the closing flag,
	 * it shall be interpreted as a void block to match the behavior of
	 * the spec parser, but the block scanner exposes the closing flag to
	 * allow for user-space code to make its own determination.
	 *
	 * @ticket 61401
	 */
	public function test_reports_presence_of_void_flag() {
		$html    = '<!-- wp:void-and-closed --><!-- wp:void /--><!-- /wp:void-and-closed /-->';
		$scanner = WP_Block_Scanner::create( $html );

		// Test the opening delimiter.

		$this->assertTrue(
			$scanner->next_delimiter(),
			"Should have found opening 'void-and-closed' block delimiter but found nothing: check test setup."
		);

		$this->assertSame(
			'core/void-and-closed',
			$scanner->get_block_type(),
			"Should have found opening 'void-and-closed' block delimiter: check test setup."
		);

		$this->assertSame(
			WP_Block_Scanner::OPENER,
			$scanner->get_delimiter_type(),
			'Should have found an opening delimiter: check test setup.'
		);

		$this->assertFalse(
			$scanner->has_closing_flag(),
			'Should not have indicated the presence of the closing flag on an opening block.'
		);

		// Test the void delimiter.

		$this->assertTrue(
			$scanner->next_delimiter(),
			"Should have found the void 'void' block delimiter but found nothing: check test setup."
		);

		$this->assertSame(
			'core/void',
			$scanner->get_block_type(),
			"Should have found opening 'void-and-closed' block delimiter: check test setup."
		);

		$this->assertSame(
			WP_Block_Scanner::VOID,
			$scanner->get_delimiter_type(),
			'Should have found a void delimiter: check test setup.'
		);

		$this->assertFalse(
			$scanner->has_closing_flag(),
			'Should not have indicated the presence of the closing flag on the pure void block.'
		);

		// Test the void/closing delimiter.

		$this->assertTrue(
			$scanner->next_delimiter(),
			"Should have found closing 'void-and-closed' block delimiter but found nothing: check test setup."
		);

		$this->assertSame(
			'core/void-and-closed',
			$scanner->get_block_type(),
			"Should have found closing 'void-and-closed' block delimiter: check test setup."
		);

		$this->assertSame(
			WP_Block_Scanner::VOID,
			$scanner->get_delimiter_type(),
			'Should have found a closing delimiter: check test setup.'
		);

		$this->assertTrue(
			$scanner->has_closing_flag(),
			'Should have indicated the presence of the closing flag on a block with both the closing and void flags.'
		);
	}

	/**
	 * Verifies that the scanner indicates if the currently-matched delimiter
	 * is of a given block type.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_delimiters_and_their_block_types
	 *
	 * @param string $html       Contains a single delimiter.
	 * @param string $block_type Fully-qualified block type.
	 */
	public function test_reports_if_block_is_of_type( $html, $block_type ) {
		$scanner = WP_Block_Scanner::create( $html );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found test block delimiter but found nothing instead: check test setup.'
		);

		$this->assertTrue(
			$scanner->is_block_type( $block_type ),
			"Should have found the block to be of type '{$block_type}', detected type is '{$scanner->get_block_type()}'."
		);

		if ( str_starts_with( $block_type, 'core/' ) ) {
			// Prune off core namespace and detect implicit namespace.
			$block_type = substr( $block_type, strlen( 'core/' ) );

			$this->assertTrue(
				$scanner->is_block_type( $block_type ),
				"Should have found the block to be of core type '{$block_type}', detected type is '{$scanner->get_block_type()}'."
			);
		}
	}

	/**
	 * Verifies that the scanner indicates if the currently-matched delimiter
	 * opens a block of a given block type. This is true for openers and void delimiters.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_delimiters_and_their_block_types
	 *
	 * @param string $html       Contains a single delimiter.
	 * @param string $block_type Fully-qualified block type.
	 */
	public function test_reports_if_block_opens_type( $html, $block_type ) {
		$scanner = WP_Block_Scanner::create( $html );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found test block delimiter but found nothing instead: check test setup.'
		);

		if ( WP_Block_Scanner::CLOSER === $scanner->get_delimiter_type() ) {
			$this->assertFalse(
				$scanner->opens_block( $block_type ),
				'Should not have indicated that a closing delimiter opens a block.'
			);
			return;
		}

		$this->assertTrue(
			$scanner->opens_block( $block_type ),
			"Should have indicating opening of type '{$block_type}', detected type is '{$scanner->get_block_type()}'."
		);

		if ( str_starts_with( $block_type, 'core/' ) ) {
			// Prune off core namespace and detect implicit namespace.
			$block_type = substr( $block_type, strlen( 'core/' ) );

			$this->assertTrue(
				$scanner->opens_block( $block_type ),
				"Should have indicated opening of core type '{$block_type}', detected type is '{$scanner->get_block_type()}'."
			);
		}
	}

	/**
	 * Verifies that asking if a delimiter opens a block ignores the block type
	 * if none are provided in the explicit limiting list.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_delimiters_and_their_block_types
	 *
	 * @param string $html       Contains a single delimiter.
	 * @param string $block_type Fully-qualified block type (ignored but present due to shared data provider).
	 */
	public function test_opens_block_with_no_explicit_types_ignores_block_type( $html, $block_type ) {
		$scanner = WP_Block_Scanner::create( $html );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found test block delimiter but found nothing instead: check test setup.'
		);

		if ( WP_Block_Scanner::CLOSER === $scanner->get_delimiter_type() ) {
			$this->assertFalse(
				$scanner->opens_block(),
				'Should not have indicated that a closing delimiter opens a block.'
			);
		} else {
			$this->assertTrue(
				$scanner->opens_block(),
				"Should have indicated that a '{$scanner->get_delimiter_type()}' delimiter opens a block."
			);
		}
	}

	/**
	 * Verifies that when given multiple potential block types, that `opens_block()` properly
	 * indicates if the currently-matched block is an opening for at least one of them.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_delimiters_and_sets_of_block_types
	 *
	 * @param string   $html        Contains a single block delimiter.
	 * @param string[] $block_types Contains one or more block types, fully qualified or not.
	 * @param bool     $is_a_match  Indicates if the provided HTML contains a block of the type in the given set.
	 */
	public function test_opens_block_checks_against_multiple_provided_block_types( $html, $block_types, $is_a_match ) {
		$scanner = WP_Block_Scanner::create( $html );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found test setup block but found none: check test setup.'
		);

		$joined_types = implode( ', ', $block_types );

		if ( $is_a_match ) {
			$this->assertTrue(
				$scanner->opens_block( ...$block_types ),
				"Should have found that delimiter (type {$scanner->get_block_type()}) opens one of the following block types: {$joined_types}."
			);
		} else {
			$this->assertFalse(
				$scanner->opens_block( ...$block_types ),
				"Should not have found that delimiter (type {$scanner->get_block_type()}) opens one of the following block types: {$joined_types}."
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_delimiters_and_sets_of_block_types() {
		return array(
			// Positive matches.
			'type "test", first in set'                    => array( '<!-- wp:test -->', array( 'test', 'tossed', 'tess' ), true ),
			'type "core/test", first in set'               => array( '<!-- wp:core/test -->', array( 'test', 'tossed', 'tess' ), true ),
			'type "test", middle of set'                   => array( '<!-- wp:test -->', array( 'tust', 'test', 'tossed', 'tess' ), true ),
			'type "core/test", middle of set'              => array( '<!-- wp:core/test -->', array( 'tust', 'test', 'tossed', 'tess' ), true ),
			'type "test", last in set'                     => array( '<!-- wp:test -->', array( 'tust', 'tossed', 'tess', 'test' ), true ),
			'type "core/test", last in set'                => array( '<!-- wp:core/test -->', array( 'tust', 'tossed', 'tess', 'core/test' ), true ),
			'type "test", core/test first in set'          => array( '<!-- wp:test -->', array( 'core/test', 'tossed', 'tess' ), true ),
			'type "core/test", core/test first in set'     => array( '<!-- wp:core/test -->', array( 'core/test', 'tossed', 'tess' ), true ),
			'type "test", core/test in middle of set'      => array( '<!-- wp:test -->', array( 'tust', 'core/test', 'tossed', 'tess' ), true ),
			'type "core/test", core/test in middle of set' => array( '<!-- wp:core/test -->', array( 'tust', 'core/test', 'tossed', 'tess' ), true ),
			'type "test", core/test last in set'           => array( '<!-- wp:test -->', array( 'tust', 'tossed', 'tess', 'core/test' ), true ),
			'type "core/test", core/test last in set'      => array( '<!-- wp:core/test -->', array( 'tust', 'tossed', 'tess', 'core/test' ), true ),
			'non-core, fully-qualified'                    => array( '<!-- wp:test/block -->', array( 'test/ship', 'test/block', 'test/wheel' ), true ),

			// Negative matches.
			'type "test", not in set'                      => array( '<!-- wp:test -->', array( 'text', 'core/text', 'my/test' ), false ),
			'type "core/test", not in set'                 => array( '<!-- wp:core/test -->', array( 'text', 'core/text', 'my/test' ), false ),
			'type "next-dev/code", not in set'             => array( '<!-- wp:next-dev/code -->', array( 'code', 'new/code', 'dev/code' ), false ),
		);
	}

	/**
	 * Verifies that when scanning and visiting freeform blocks, that they
	 * return the appropriate information, an opening, and a closing.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_freeform_blocks_and_delimiter_indices
	 *
	 * @param string $html        Contains a freeform block after zero or more delimiters.
	 * @param int    $freeform_at Freeform is located after this many other delimiters.
	 */
	public function test_indicates_if_matched_delimiter_is_freeform( $html, $freeform_at ) {
		$scanner = WP_Block_Scanner::create( $html );

		for ( $i = 0; $i < $freeform_at; $i++ ) {
			$scanner->next_token( 'visit-html' );
		}

		// Opening delimiter.

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found the freeform content but didn’t: check test setup.'
		);

		$this->assertSame(
			'core/freeform',
			$scanner->get_block_type(),
			'Should have found a freeform block.'
		);

		$this->assertTrue(
			$scanner->is_html(),
			'Should have identified the delimiter as freeform.'
		);

		$this->assertSame(
			WP_Block_Scanner::OPENER,
			$scanner->get_delimiter_type(),
			'Should have stopped first on a freeform block opener.'
		);

		$this->assertTrue(
			$scanner->opens_block( 'freeform' ),
			'Should indicate that this (implicit) delimiter opens a freeform block (without the Core namespace).'
		);

		$this->assertTrue(
			$scanner->is_block_type( 'freeform' ),
			'Should indicate that this (implicit) delimiter is freeform (without the Core namespace).'
		);

		$this->assertTrue(
			$scanner->opens_block( 'core/freeform' ),
			'Should indicate that this (implicit) delimiter opens a freeform block (fully-qualified).'
		);

		$this->assertTrue(
			$scanner->is_block_type( 'core/freeform' ),
			'Should indicate that this (implicit) delimiter is freeform (fully-qualified).'
		);

		$this->assertNull(
			$scanner->allocate_and_return_parsed_attributes(),
			'Should not find any attributes on any freeform content.'
		);

		// Closing delimiter.

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found the closing (implicit) freeform delimiter but found nothing instead.'
		);

		$this->assertTrue(
			$scanner->is_html(),
			'Should have identified the delimiter as freeform.'
		);

		$this->assertSame(
			WP_Block_Scanner::CLOSER,
			$scanner->get_delimiter_type(),
			'Should have found the closing (implicit) freeform delimiter.'
		);

		$this->assertSame(
			'core/freeform',
			$scanner->get_block_type(),
			'Should have found the freeform block type.'
		);

		$this->assertFalse(
			$scanner->opens_block( 'core/freeform' ),
			'Should not indicate that the (implicit) freeform closing delimiter opens a block.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_freeform_blocks_and_delimiter_indices() {
		return array(
			'Only non-block content (one freeform)' => array( 'this is not inside a block', 0 ),
			'Freeform before a block'               => array( 'before the block<!-- wp:suffix /-->', 0 ),
			'Freeform after a block'                => array( '<!-- wp:prefix /-->after the block', 1 ),
			'Freeform between blocks'               => array( '<!-- wp:prefix /-->after the block<!-- wp:suffix /-->', 1 ),
			'Visits HTML content inside blocks'     => array( '<!-- wp:block -->this is innerHTML<!-- /wp:block -->this is freeform', 1 ),
		);
	}

	/**
	 * Verifies that the freeform functions do not report freeform content
	 * when explicit delimiters are matched.
	 *
	 * @ticket 61401
	 */
	public function test_actual_delimiters_are_not_freeform() {
		$scanner = WP_Block_Scanner::create( "<!-- wp:group --> \f\t\r\n <!-- /wp:group -->" );

		// Opening block.

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			"Should have found opening 'group' test block: check test setup."
		);

		$this->assertFalse(
			$scanner->is_html(),
			"Should not have reported the opening 'group' block as freeform."
		);

		$this->assertFalse(
			$scanner->is_non_whitespace_html(),
			"Should not have reported the opening 'group' block as non-whitespace freeform."
		);

		// Freeform block (implicit) opener.

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found (implicit) freeform test block: check test setup.'
		);

		$this->assertTrue(
			$scanner->is_html(),
			'Should have reported the (implicit) opening freeform delimiter.'
		);

		$this->assertFalse(
			$scanner->is_non_whitespace_html(),
			'Should have reported the (implicit) opening freeform delimiter as whitespace-only.'
		);

		// Freeform block (implicit) closer.

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found (implicit) freeform test block closer: check test setup.'
		);

		$this->assertTrue(
			$scanner->is_html(),
			'Should have reported the (implicit) closing freeform delimiter.'
		);

		$this->assertFalse(
			$scanner->is_non_whitespace_html(),
			'Should have reported the (implicit) closing freeform delimiter as whitespace-only.'
		);

		// Closing block.

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			"Should have found closing 'group' test block: check test setup."
		);

		$this->assertFalse(
			$scanner->is_html(),
			"Should not have reported the closing 'group' block as freeform."
		);

		$this->assertFalse(
			$scanner->is_non_whitespace_html(),
			"Should not have reported the closing 'group' block as non-whitespace freeform."
		);
	}

	/**
	 * Verifies that whitespace-only freeform content is properly indicated.
	 *
	 * This is used to skip over whitespace-only freeform content which is
	 * usually produced by {@see \serialize_blocks()} for clearer formatting.
	 *
	 * @ticket 61401
	 */
	public function test_indicates_if_freeform_content_is_only_whitespace() {
		$scanner = WP_Block_Scanner::create(
			<<<HTML
this is freeform but between the next two blocks is
another freeform block whose content is a newline
<!-- wp:separator /-->
<!-- wp:ladder /-->
HTML
		);

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found the first freeform block: check test setup.'
		);

		$this->assertSame(
			'core/freeform',
			$scanner->get_block_type(),
			'Should have identified the first (implicit) delimiter as freeform.'
		);

		$this->assertTrue(
			$scanner->is_html(),
			'Should have identified the first delimiter as (implicit) freeform.'
		);

		$this->assertTrue(
			$scanner->is_non_whitespace_html(),
			'Should have identified that the freeform block contains non-whitespace content.'
		);

		$this->assertTrue(
			$scanner->next_token( 'skip-html' ),
			"Should have found the first explicit 'separator' delimiter: check test setup."
		);

		$this->assertSame(
			'core/separator',
			$scanner->get_block_type(),
			"Should have found the 'separator' delimiter: check test setup."
		);

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found the second implicit freeform delimiter.'
		);

		$this->assertTrue(
			$scanner->is_html(),
			'Should have identified the second (implicit) freeform opening delimiter.'
		);

		$this->assertFalse(
			$scanner->is_non_whitespace_html(),
			'Should have identified that the second freeform block contains only whitespace content.'
		);

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			'Should have found the second implicit freeform closing delimiter'
		);

		$this->assertTrue(
			$scanner->next_token( 'visit-html' ),
			"Should have found the final 'ladder' delimiter."
		);

		$this->assertSame(
			'core/ladder',
			$scanner->get_block_type(),
			"Should have identified the final delimiter as a 'core/ladder' type: check test setup."
		);
	}

	/**
	 * Verifies that `get_attributes()` throws because it’s unsupported at the moment.
	 *
	 * This test should be changed if and when lazy attribute parsing is added.
	 *
	 * @ticket 61401
	 */
	public function test_get_attributes_currently_throws() {
		$scanner = WP_Block_Scanner::create( '<!-- wp:test {"not": "yet supported"} -->' );

		$this->assertTrue(
			$scanner->next_delimiter(),
			'Should have found the "test" setup delimiter but found nothing: check test setup.'
		);

		$this->assertSame(
			array( 'not' => 'yet supported' ),
			$scanner->allocate_and_return_parsed_attributes(),
			'Should have read eagerly-parsed block attributes: check test setup.'
		);

		$this->expectExceptionMessage( 'Lazy attribute parsing not yet supported' );
		$scanner->get_attributes();
	}

	/**
	 * Verifies that the scanner reports the appropriate string indices for each delimiter.
	 *
	 * @ticket 61401
	 *
	 * @dataProvider data_content_and_delimiter_spans
	 *
	 * @param string $html Contains one or more block delimiters,
	 *                     including implicit freeform delimiters.
	 * @param int[] $spans For each delimiter in `$html`, a [ start, length ]
	 *                     pair representing the textual span of the delimiter.
	 */
	public function test_returns_appropriate_span_for_delimiters( $html, ...$spans ) {
		$scanner = WP_Block_Scanner::create( $html );

		if ( 0 === count( $spans ) ) {
			$this->assertNull(
				$scanner->get_span(),
				'Should not have returned any span when not matched on a delimiter.'
			);
			return;
		}

		foreach ( $spans as $i => $span ) {
			$this->assertTrue(
				$scanner->next_token( 'visit-html' ),
				"Should have found delimiter in position {$i} but found nothing: check test setup."
			);

			$reported = $scanner->get_span();
			$this->assertSame(
				$span,
				array( $reported->start, $reported->length ),
				'Should have reported the proper span of text covered by the delimiter.'
			);
		}

		$this->assertFalse(
			$scanner->next_token( 'visit-html' ),
			'Should not have found any additional delimiters: check test setup.'
		);

		$this->assertNull(
			$scanner->get_span(),
			'Should not have returned any span after finishing the scan of a document.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_and_delimiter_spans() {
		return array(
			'Before matching' => array( 'Blocks <!-- wp:will/not --> advance yet' ),
			'Only freeform'   => array( 'Have a lovely day.', array( 0, 0 ), array( 18, 0 ) ),
			'Only void'       => array( '<!-- wp:into/abyss /-->', array( 0, 23 ) ),
			'Mixed'           => array( '<!-- wp:pw --><><!-- /wp:pw -->', array( 0, 14 ), array( 14, 0 ), array( 16, 0 ), array( 16, 15 ) ),
		);
	}

	//
	// Test helpers.
	//

	/**
	 * Replaces whitespace in a string with visual indicators for easier debugging.
	 *
	 * The definition of “whitespace” here is loose and intended for debugging tests.
	 * It’s okay to expand for more complete replacement, for example to replace all
	 * graphemes considered whitespace by Unicode, but not required unless it’s
	 * essential for tests.
	 *
	 * Concerning HTML and the block parser only the HTML whitespace is relevant.
	 *
	 * @param string $text Any input, potentially containing whitespace characters.
	 * @return string The input with whitespace replaced by visual placeholders.
	 */
	private static function unhide_whitespace( $text ) {
		return str_replace(
			array( ' ', "\t", "\r", "\f", "\n" ),
			array( '␠', '␉', '␍', '␌', '␤' ),
			$text
		);
	}
}
