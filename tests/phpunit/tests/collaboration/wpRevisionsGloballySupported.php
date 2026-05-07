<?php
/**
 * Tests for revision-based gating of real-time collaboration.
 *
 * @package WordPress
 * @subpackage Collaboration
 *
 * @group collaboration
 *
 * @covers ::wp_revisions_are_globally_supported
 * @covers ::wp_is_collaboration_enabled
 */
class Tests_Collaboration_WpRevisionsGloballySupported extends WP_UnitTestCase {

	/**
	 * When WP_POST_REVISIONS is unset, revisions behave as enabled site-wide.
	 *
	 * @ticket 77499
	 */
	public function test_wp_revisions_are_globally_supported_defaults_to_true() {
		$this->assertTrue( wp_revisions_are_globally_supported() );
	}

	/**
	 * Collaboration follows global revision availability when the option is on.
	 *
	 * @ticket 77499
	 */
	public function test_wp_is_collaboration_enabled_true_when_option_enabled_and_revisions_supported() {
		update_option( 'wp_collaboration_enabled', 1 );

		$this->assertTrue( wp_revisions_are_globally_supported() );
		$this->assertTrue( wp_is_collaboration_enabled() );
	}
}
