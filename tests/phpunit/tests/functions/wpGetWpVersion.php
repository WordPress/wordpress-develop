<?php

/**
 * Tests for wp_get_wp_version().
 *
 * @group functions
 *
 * @covers ::wp_get_wp_version
 */
class Tests_Functions_WpGetWpVersion extends WP_UnitTestCase {

	/**
	 * Tests that the WordPress version is returned.
	 *
	 * @ticket 61627
	 */
	public function test_should_return_wp_version() {
		$this->assertSame( $GLOBALS['wp_version'], wp_get_wp_version() );
	}

	/**
	 * Tests that changes to the `$wp_version` global are ignored.
	 *
	 * @ticket 61627
	 */
	public function test_should_ignore_changes_to_wp_version_global() {
		$original_wp_version   = $GLOBALS['wp_version'];
		$GLOBALS['wp_version'] = 'modified_wp_version';
		$actual                = wp_get_wp_version();
		$GLOBALS['wp_version'] = $original_wp_version;

		$this->assertSame( $original_wp_version, $actual );
	}
}
