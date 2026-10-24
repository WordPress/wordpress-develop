<?php

/**
 * Tests for wp_kses_version().
 *
 * @group kses
 *
 * @covers ::wp_kses_version
 */
class Tests_Kses_wpKsesVersion extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_kses_version_string() {
		$this->assertSame( '0.2.2', wp_kses_version() );
	}
}
