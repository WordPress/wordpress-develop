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
	 * @dataProvider data_serialize_identity_from_parsed
	 *
	 * @param string $original Original block markup.
	 */
	public function test_serialize_identity_from_parsed( $original ) {
		$blocks = parse_blocks( $original );

		$actual = serialize_blocks( $blocks );

		$this->assertSame( $original, $actual );
	}

	public function data_serialize_identity_from_parsed() {
		return array(
			// Void block.
			array( '<!-- wp:void /-->' ),

			// Freeform content ($block_name = null).
			array( 'Example.' ),

			// Block with content.
			array( '<!-- wp:content -->Example.<!-- /wp:content -->' ),

			// Block with attributes.
			array( '<!-- wp:attributes {"key":"value"} /-->' ),

			// Block with inner blocks.
			array( "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->" ),

			// Block with attribute values that may conflict with HTML comment.
			array( '<!-- wp:attributes {"key":"\\u002d\\u002d\\u003c\\u003e\\u0026\\u0022"} /-->' ),

			// Block with attribute values that should not be escaped.
			array( '<!-- wp:attributes {"key":"€1.00 / 3 for €2.00"} /-->' ),
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
	 *
	 * @covers ::traverse_and_serialize_blocks
	 */
	public function test_traverse_and_serialize_blocks() {
		$markup = "<!-- wp:outer --><!-- wp:inner {\"key\":\"value\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->";
		$blocks = parse_blocks( $markup );

		$actual = traverse_and_serialize_blocks( $blocks, array( __CLASS__, 'add_attribute_to_inner_block' ) );

		$this->assertSame(
			"<!-- wp:outer --><!-- wp:inner {\"key\":\"value\",\"myattr\":\"myvalue\"} -->Example.<!-- /wp:inner -->\n\nExample.\n\n<!-- wp:void /--><!-- /wp:outer -->",
			$actual
		);
	}

	public static function add_attribute_to_inner_block( $block ) {
		if ( 'core/inner' === $block['blockName'] ) {
			$block['attrs']['myattr'] = 'myvalue';
		}
		return $block;
	}

	/**
	 * @ticket 59327
	 *
	 * @covers ::traverse_and_serialize_blocks
	 *
	 * @dataProvider data_serialize_identity_from_parsed
	 *
	 * @param string $original Original block markup.
	 */
	public function test_traverse_and_serialize_identity_from_parsed( $original ) {
		$blocks = parse_blocks( $original );

		$actual = traverse_and_serialize_blocks(
			$blocks,
			function ( $block ) {
				return $block;
			}
		);

		$this->assertSame( $original, $actual );
	}
}
