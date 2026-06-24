<?php

/**
 * @group functions
 *
 * @covers ::wp_ext2type
 */
class Tests_Functions_WpExt2Type extends WP_UnitTestCase {
	/**
	 * @ticket 35987
	 */
	public function test_wp_ext2type() {
		$extensions = wp_get_ext_types();

		$this->assertNotEmpty( $extensions );

		foreach ( $extensions as $type => $extension_list ) {
			foreach ( $extension_list as $extension ) {
				$this->assertSame( $type, wp_ext2type( $extension ) );
				$this->assertSame( $type, wp_ext2type( strtoupper( $extension ) ) );
			}
		}

		$this->assertNull( wp_ext2type( 'unknown_format' ) );
	}
}
