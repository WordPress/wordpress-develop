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
	 * @ticket 66368
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

			'Void block with a numeric attribute name'    =>
				array( '<!-- wp:void {"0":"a"} /-->' ),

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
	 * @link https://github.com/WordPress/wordpress-develop/pull/9558
	 * @link https://github.com/WordPress/gutenberg/pull/71291
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

	/**
	 * Tests that block attributes are always serialized as a JSON object.
	 *
	 * @dataProvider data_serialize_block_attributes
	 *
	 * @ticket 66368
	 *
	 * @covers ::serialize_block_attributes
	 *
	 * @param array<array-key, mixed> $block_attributes Block attributes.
	 * @param non-empty-string        $expected         Expected serialization.
	 */
	public function test_serialize_block_attributes( array $block_attributes, string $expected ) {
		$this->assertSame( $expected, serialize_block_attributes( $block_attributes ) );
	}

	/**
	 * Data provider for {@see self::test_serialize_block_attributes()}.
	 *
	 * @return array<non-falsy-string, array{ 0: array<mixed>, 1: non-empty-string }>
	 */
	public static function data_serialize_block_attributes(): array {
		return array(
			'No attributes'                        => array( array(), '{}' ),
			'String attribute'                     => array( array( 'align' => 'wide' ), '{"align":"wide"}' ),
			'Numeric attribute name'               => array( array( 0 => 'a' ), '{"0":"a"}' ),
			'Sequential numeric names'             => array(
				array(
					0 => 'a',
					1 => 'b',
				),
				'{"0":"a","1":"b"}',
			),
			'Numeric name not starting from zero'  => array( array( 1 => 'a' ), '{"1":"a"}' ),
			'Name starting with a null byte'       => array( array( "\0" . 'a' => 1 ), '{"\\u0000a":1}' ),
			'List value'                           => array( array( 'className' => array( 'a', 'b' ) ), '{"className":["a","b"]}' ),
			'Empty array value'                    => array( array( 'style' => array() ), '{"style":[]}' ),
			'Characters unsafe in an HTML comment' => array( array( 'text' => '--> <!-- & "' ), '{"text":"\\u002d\\u002d\\u003e \\u003c!\\u002d\\u002d \\u0026 \\u0022"}' ),
		);
	}

	/**
	 * Tests that INF and NAN are serialized as null, as JSON.stringify() does, rather than losing all attributes.
	 *
	 * @dataProvider data_serialize_block_attributes_non_finite_numbers
	 *
	 * @ticket 66368
	 *
	 * @covers ::serialize_block_attributes
	 *
	 * @param array<array-key, mixed> $block_attributes Block attributes.
	 * @param non-empty-string        $expected         Expected serialization.
	 */
	public function test_serialize_block_attributes_non_finite_numbers( array $block_attributes, string $expected ) {
		$this->assertSame( $expected, serialize_block_attributes( $block_attributes ) );
	}

	/**
	 * Data provider for {@see self::test_serialize_block_attributes_non_finite_numbers()}.
	 *
	 * @return array<non-falsy-string, array{ 0: array<mixed>, 1: string }>
	 */
	public static function data_serialize_block_attributes_non_finite_numbers(): array {
		return array(
			'INF'                      => array(
				array(
					'align' => 'wide',
					'ratio' => INF,
				),
				'{"align":"wide","ratio":null}',
			),
			'Negative INF'             => array( array( 'ratio' => -INF ), '{"ratio":null}' ),
			'NAN'                      => array( array( 'ratio' => NAN ), '{"ratio":null}' ),
			'Nested in an array'       => array( array( 'style' => array( 'ratios' => array( 1.5, INF ) ) ), '{"style":{"ratios":[1.5,null]}}' ),
			'Numeric attribute name'   => array( array( 0 => INF ), '{"0":null}' ),
			'Finite floats left alone' => array( array( 'ratio' => 1.5 ), '{"ratio":1.5}' ),
		);
	}

	/**
	 * Tests that replacing INF with null does not change a variable that an attribute refers to.
	 *
	 * @ticket 66368
	 *
	 * @covers ::serialize_block_attributes
	 */
	public function test_serialize_block_attributes_non_finite_number_reference() {
		$ratio            = INF;
		$block_attributes = array( 'ratio' => &$ratio );

		$this->assertSame( '{"ratio":null}', serialize_block_attributes( $block_attributes ) );
		$this->assertInfinite( $ratio, 'The variable the attribute refers to should not be changed.' );
	}

	/**
	 * Tests that attributes which still cannot be encoded are serialized as an empty object.
	 *
	 * @ticket 66368
	 *
	 * @covers ::serialize_block_attributes
	 */
	public function test_serialize_block_attributes_encoding_failure() {
		$block_attributes = array();
		$nested           = &$block_attributes;
		for ( $i = 0; $i < 600; $i++ ) {
			$nested['a'] = array();
			$nested      = &$nested['a'];
		}
		unset( $nested );

		$this->assertSame( '{}', serialize_block_attributes( $block_attributes ) );
	}

	/**
	 * Tests that an INF value does not hide attributes nested too deeply, since json_encode() reports the later error.
	 *
	 * @ticket 66368
	 *
	 * @covers ::serialize_block_attributes
	 */
	public function test_serialize_block_attributes_non_finite_number_with_encoding_failure() {
		$block_attributes = array( 'ratio' => INF );
		$nested           = &$block_attributes;
		for ( $i = 0; $i < 600; $i++ ) {
			$nested['a'] = array();
			$nested      = &$nested['a'];
		}
		unset( $nested );

		$this->assertSame( '{}', serialize_block_attributes( $block_attributes ) );
	}

	/**
	 * Tests that attributes which refer to themselves are serialized as an empty object, rather than causing a fatal error.
	 *
	 * @ticket 66368
	 *
	 * @covers ::serialize_block_attributes
	 */
	public function test_serialize_block_attributes_recursion() {
		$block_attributes         = array( 'align' => 'wide' );
		$block_attributes['self'] = &$block_attributes;

		$this->assertSame( '{}', serialize_block_attributes( $block_attributes ) );
	}

	/**
	 * Tests that attributes passed as an object are still serialized, rather than causing a fatal error.
	 *
	 * @ticket 66368
	 *
	 * @covers ::serialize_block_attributes
	 */
	public function test_serialize_block_attributes_object() {
		$block_attributes = (object) array( 'align' => 'wide' );

		// @phpstan-ignore argument.type (An object is passed on purpose, since the function encoded one before its attributes were checked for a list.)
		$this->assertSame( '{"align":"wide"}', serialize_block_attributes( $block_attributes ) );
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
	 * @ticket 66368
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
