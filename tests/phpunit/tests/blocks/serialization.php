<?php
/**
 * Block serialization tests.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.3.3
 */

/**
 * Tests for block serialization functions
 *
 * @since 5.3.3
 *
 * @group blocks
 */
class WP_Test_Block_Serialization extends WP_UnitTestCase {

	/**
	 * @dataProvider data_serialize_identity_from_parsed
	 */
	function test_serialize_identity_from_parsed( $original ) {
		$blocks = parse_blocks( $original );

		$actual   = serialize_blocks( $blocks );
		$expected = $original;

		$this->assertEquals( $expected, $actual );
	}

	function data_serialize_identity_from_parsed() {
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
		);
	}

	function test_serialized_block_name() {
		$this->assertEquals( null, strip_core_block_namespace( null ) );
		$this->assertEquals( 'example', strip_core_block_namespace( 'example' ) );
		$this->assertEquals( 'example', strip_core_block_namespace( 'core/example' ) );
		$this->assertEquals( 'plugin/example', strip_core_block_namespace( 'plugin/example' ) );
	}

}
