<?php

/**
 * Tests for wp_get_active_and_valid_themes().
 *
 * @group load.php
 * @covers ::wp_get_active_and_valid_themes
 */
class Tests_Load_WpGetActiveAndValidThemes extends WP_UnitTestCase {

	/**
	 * @ticket 57928
	 */
	public function test_wp_get_active_and_valid_themes() {
		// Defaults to TEMPLATEPATH (and potentially STYLESHEETPATH).
		$this->assertEquals(
			array(
				TEMPLATEPATH,
			),
			wp_get_active_and_valid_themes()
		);

		// Disabling 'wp_using_themes' should return an empty array.
		add_filter( 'wp_using_themes', '__return_false' );
		$this->assertEquals(
			array(),
			wp_get_active_and_valid_themes()
		);
	}
}
