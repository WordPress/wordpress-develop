<?php
/**
 * WP_Block_List tests.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.5.0
 */

/**
 * Tests for WP_Block_List
 *
 * @since 5.5.0
 *
 * @group blocks
 */
class WP_Block_List_Test extends WP_UnitTestCase {

	/**
	 * Fake block type registry.
	 *
	 * @var WP_Block_Type_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 */
	public function setUp() {
		parent::setUp();

		$this->registry = new WP_Block_Type_Registry();
		$this->registry->register( 'core/example', array() );
	}

	/**
	 * Tear down each test method.
	 */
	public function tearDown() {
		parent::tearDown();

		$this->registry = null;
	}

	/**
	 * @ticket 49927
	 */
	function test_array_access() {
		$parsed_blocks = parse_blocks( '<!-- wp:example /-->' );
		$context       = array();
		$blocks        = new WP_Block_List( $parsed_blocks, $context, $this->registry );

		// Test "offsetExists".
		$this->assertTrue( isset( $blocks[0] ) );

		// Test "offsetGet".
		$this->assertSame( 'core/example', $blocks[0]->name );

		// Test "offsetSet".
		$parsed_blocks[0]['blockName'] = 'core/updated';
		$blocks[0]                     = new WP_Block( $parsed_blocks[0], $context, $this->registry );
		$this->assertSame( 'core/updated', $blocks[0]->name );

		// Test "offsetUnset".
		unset( $blocks[0] );
		$this->assertFalse( isset( $blocks[0] ) );
	}

	/**
	 * @ticket 49927
	 */
	function test_iterable() {
		$parsed_blocks = parse_blocks( '<!-- wp:example --><!-- wp:example /--><!-- /wp:example -->' );
		$context       = array();
		$blocks        = new WP_Block_List( $parsed_blocks, $context, $this->registry );
		$assertions    = 0;

		foreach ( $blocks as $block ) {
			$this->assertSame( 'core/example', $block->name );
			$assertions++;
			foreach ( $block->inner_blocks as $inner_block ) {
				$this->assertSame( 'core/example', $inner_block->name );
				$assertions++;
			}
		}

		$blocks->rewind();
		while ( $blocks->valid() ) {
			$key   = $blocks->key();
			$block = $blocks->current();
			$this->assertSame( 0, $key );
			$assertions++;
			$this->assertSame( 'core/example', $block->name );
			$assertions++;
			$blocks->next();
		}

		$this->assertSame( 4, $assertions );
	}

	/**
	 * @ticket 49927
	 */
	function test_countable() {
		$parsed_blocks = parse_blocks( '<!-- wp:example /-->' );
		$context       = array();
		$blocks        = new WP_Block_List( $parsed_blocks, $context, $this->registry );

		$this->assertSame( 1, count( $blocks ) );
	}

}
