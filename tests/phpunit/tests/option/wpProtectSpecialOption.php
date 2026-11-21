<?php

/**
 * Tests for wp_protect_special_option().
 *
 * @group option
 *
 * @covers ::wp_protect_special_option
 */
class Tests_Option_WpProtectSpecialOption extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_allows_regular_option_name() {
		$this->assertNull( wp_protect_special_option( 'blogname' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_dies_for_alloptions() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'alloptions is a protected WP option and may not be modified' );

		wp_protect_special_option( 'alloptions' );
	}

	/**
	 * @ticket 65819
	 */
	public function test_dies_for_notoptions() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'notoptions is a protected WP option and may not be modified' );

		wp_protect_special_option( 'notoptions' );
	}
}
