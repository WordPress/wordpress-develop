<?php

/**
 * @group functions
 *
 * @covers ::wp_get_ext_types
 */
class Tests_Functions_WpGetExtTypes extends WP_UnitTestCase {
	/**
	 * @ticket 35987
	 */
	public function test_wp_get_ext_types() {
		$extensions = wp_get_ext_types();

		$this->assertIsArray( $extensions );
		$this->assertNotEmpty( $extensions );

		add_filter( 'ext2type', '__return_empty_array' );
		$extensions = wp_get_ext_types();
		$this->assertSame( array(), $extensions );

		remove_filter( 'ext2type', '__return_empty_array' );
		$extensions = wp_get_ext_types();
		$this->assertIsArray( $extensions );
		$this->assertNotEmpty( $extensions );
	}
}
