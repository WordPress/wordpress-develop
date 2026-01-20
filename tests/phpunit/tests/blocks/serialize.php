<?php
/**
 * Tests for block serialization functions.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 5.3.3
 *
 * @group blocks
 */
class Tests_Blocks_Serialize extends WP_UnitTestCase {
	/**
	 * Ensure there are no issues with special character encoding.
	 *
	 * @ticket 63917
	 */
	public function test_attribute_encoding() {
		$block = array(
			'blockName'    => 'test',
			'attrs'        => array(
				'lt'         => '<',
				'gt'         => '>',
				'amp'        => '&',
				'bs'         => '\\',
				'quot'       => '"',
				'bs-bs-quot' => '\\\\"',
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$expected = '<!-- wp:test {"lt":"\\u003c","gt":"\\u003e","amp":"\\u0026","bs":"\\u005c","quot":"\\u0022","bs-bs-quot":"\\u005c\\u005c\\u0022"} /-->';
		$this->assertSame( $expected, serialize_block( $block ) );
	}

	/**
	 * @dataProvider data_serialize_identity_from_parsed
	 *
	 * @param string $original Original block markup.
	 *
	 * @ticket 63917
	 */
	public function test_serialize_identity_from_parsed( $original ) {
		$blocks = parse_blocks( $original );

		$actual = serialize_blocks( $blocks );

		$this->assertSame( $original, $actual );
	}

	public static function data_serialize_identity_from_parsed(): array {
		return array(
			'Void block'                                  =>
				array( '<!-- wp:void /-->' ),

			'Freeform content ($block_name = null)'       =>
				array( 'Example.' ),

			'Block with content'                          =>
				array( '<!-- wp:content -->Example.<!-- /wp:content -->' ),

			'Block with attributes'                       =>
				array( '<!-- wp:attributes {"key":"value"} /-->' ),

			'Block with inner blocks'                     =>
				array( "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->" ),

			'Block with attribute values that may conflict with HTML comment' =>
				array( '<!-- wp:attributes {"key":"\\u002d\\u002d\\u003c\\u003e\\u0026\\u0022"} /-->' ),

			'Block with attribute values that should not be escaped' =>
				array( '<!-- wp:attributes {"key":"€1.00 / 3 for €2.00"} /-->' ),

			'Backslashes in attributes, Gutenberg #16508' =>
				array( '<!-- wp:attributes {"bs":"\\u005c","bsQuote":"\\u005c\\u0022","bsQuoteBs":"\\u005c\\u0022\\u005c"} /-->' ),

			'Tricky backslashes'                          =>
				array( '<!-- wp:attributes {"bsbsQbsbsbsQ":"\\u005c\\u005c\\u0022\\u005c\\u005c\\u005c\\u005c\\u0022"} /-->' ),
		);
	}

	/**
	 * The serialization was adjusted to use unicode escapes sequences for escaped `\` and `"`
	 * characters inside JSON strings.
	 *
	 * Ensure that the previous escape form can be parsed compatibly and serialized back to
	 * the new form.
	 *
	 * @see https://github.com/WordPress/wordpress-develop/pull/9558
	 * @see https://github.com/WordPress/gutenberg/pull/71291
	 *
	 * @ticket 63917
	 *
	 * @dataProvider data_serialize_compatible_forms
	 *
	 * @param string $before Previous serialization form.
	 * @param string $after  New serialization form.
	 */
	public function test_older_serialization_is_compatible( string $before, string $after ) {
		$this->assertNotSame( $before, $after, 'The same serialization should not be provided for before and after.' );
		$blocks = parse_blocks( $before );
		$actual = serialize_blocks( $blocks );
		$this->assertSame( $after, $actual );
	}

	public static function data_serialize_compatible_forms(): array {
		return array(
			'Special characters' => array(
				'<!-- wp:attributes {"lt":"\\u003c","gt":"\\u003e","amp":"\\u0026","bs":"\\\\","quot":"\\u0022"} /-->',
				'<!-- wp:attributes {"lt":"\\u003c","gt":"\\u003e","amp":"\\u0026","bs":"\\u005c","quot":"\\u0022"} /-->',
			),

			'Backslashes'        => array(
				'<!-- wp:attributes {"bs":"\\\\","bsQuote":"\\\\\\u0022","bsQuoteBs":"\\\\\\u0022\\\\"} /-->',
				'<!-- wp:attributes {"bs":"\\u005c","bsQuote":"\\u005c\\u0022","bsQuoteBs":"\\u005c\\u0022\\u005c"} /-->',
			),
		);
	}

	public function test_serialized_block_name() {
		$this->assertNull( strip_core_block_namespace( null ) );
		$this->assertSame( 'example', strip_core_block_namespace( 'example' ) );
		$this->assertSame( 'example', strip_core_block_namespace( 'core/example' ) );
		$this->assertSame( 'plugin/example', strip_core_block_namespace( 'plugin/example' ) );
	}

	/**
	 * @ticket 59327
	 * @ticket 59412
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_pre_callback_modifies_current_block() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, array( __CLASS__, 'add_attribute_to_inner_block' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:inner {\"key\":\"value\",\"myattr\":\"myvalue\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	/**
	 * @ticket 59669
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_post_callback_modifies_current_block() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, null, array( __CLASS__, 'add_attribute_to_inner_block' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:inner {\"key\":\"value\",\"myattr\":\"myvalue\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	public static function add_attribute_to_inner_block( &$block ) {
		if ( 'core/inner' === $block['blockName'] ) {
			$block['attrs']['myattr'] = 'myvalue';
		}
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_pre_callback_prepends_to_inner_block() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, array( __CLASS__, 'insert_next_to_inner_block_callback' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:tests/inserted-block /--><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_post_callback_appends_to_inner_block() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, null, array( __CLASS__, 'insert_next_to_inner_block_callback' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner --><!-- wp:tests/inserted-block /-->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	public static function insert_next_to_inner_block_callback( $block ) {
		if ( 'core/inner' !== $block['blockName'] ) {
			return '';
		}

		return get_comment_delimited_block_content( 'tests/inserted-block', array(), '' );
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_pre_callback_prepends_to_child_blocks() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, array( __CLASS__, 'insert_next_to_child_blocks_callback' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:tests/inserted-block {\"parent\":\"core/outer\"} /--><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:tests/inserted-block {\"parent\":\"core/outer\"} /--><!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_post_callback_appends_to_child_blocks() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, null, array( __CLASS__, 'insert_next_to_child_blocks_callback' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner --><!-- wp:tests/inserted-block {\"parent\":\"core/outer\"} /-->\n\nExample.\n\n<!-- wp:void /--><!-- wp:tests/inserted-block {\"parent\":\"core/outer\"} /--><!-- /wp:outer -->",
			$actual
		);
	}

	public static function insert_next_to_child_blocks_callback( $block, $parent_block ) {
		if ( ! isset( $parent_block ) ) {
			return '';
		}

		return get_comment_delimited_block_content(
			'tests/inserted-block',
			array(
				'parent' => $parent_block['blockName'],
			),
			''
		);
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_pre_callback_prepends_if_prev_block() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, array( __CLASS__, 'insert_next_to_if_prev_or_next_block_callback' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:tests/inserted-block {\"prev_or_next\":\"core/inner\"} /--><!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_post_callback_appends_if_prev_block() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, null, array( __CLASS__, 'insert_next_to_if_prev_or_next_block_callback' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner --><!-- wp:tests/inserted-block {\"prev_or_next\":\"core/void\"} /-->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	public static function insert_next_to_if_prev_or_next_block_callback( $block, $parent_block, $prev_or_next ) {
		if ( ! isset( $prev_or_next ) ) {
			return '';
		}

		return get_comment_delimited_block_content(
			'tests/inserted-block',
			array(
				'prev_or_next' => $prev_or_next['blockName'],
			),
			''
		);
	}

	/**
	 * @ticket 59327
	 * @ticket 59412
	 *
	 * @covers ::traverse_and_serialize_blocks
	 *
	 * @dataProvider data_serialize_identity_from_parsed
	 *
	 * @param string $original Original block markup.
	 */
	public function test_traverse_and_serialize_identity_from_parsed( $original ) {
		$blocks = parse_blocks( $original );

		$actual = traverse_and_serialize_blocks( $blocks );

		$this->assertSame( $original, $actual );
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_do_not_insert_in_void_block() {
		$markup = '<!-- wp:void /-->';
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks(
			$blocks,
			array( __CLASS__, 'insert_next_to_child_blocks_callback' ),
			array( __CLASS__, 'insert_next_to_child_blocks_callback' )
		);

		$this->assertSame( $markup, $actual );
	}

	/**
	 * @ticket 59313
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks_do_not_insert_in_empty_parent_block() {
		$markup = '<!-- wp:outer --><div class="wp-block-outer"></div><!-- /wp:outer -->';
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks(
			$blocks,
			array( __CLASS__, 'insert_next_to_child_blocks_callback' ),
			array( __CLASS__, 'insert_next_to_child_blocks_callback' )
		);

		$this->assertSame( $markup, $actual );
	}
}
