<?php
/**
 * Unit tests for WP_Block_Pattern_Categories_Registry::is_registered().
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.9.0
 *
 * @group block-patterns
 * @covers WP_Block_Pattern_Categories_Registry::is_registered
 */

class Tests_Block_Pattern_WPBlockPatternCategoriesRegistry extends WP_UnitTestCase {

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_with_empty_param() {
		$registry = WP_Block_Pattern_Categories_Registry::get_instance();

		// Should return false when called with empty string.
		$this->assertFalse( $registry->is_registered( '' ) );

		// Should return false when called with null.
		$this->assertFalse( $registry->is_registered( null ) );

		// Should return false when called with false.
		$this->assertFalse( $registry->is_registered( false ) );

		// Should return false when called with 0.
		$this->assertFalse( $registry->is_registered( 0 ) );
	}
}
