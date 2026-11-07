<?php

/**
 * Tests for the `wp_rel_callback()` function.
 *
 * @group formatting
 *
 * @covers ::wp_rel_callback
 */
class Tests_Formatting_WpRelCallback extends WP_UnitTestCase {

	/**
	 * Tests adding a rel attribute to an anchor that does not have one.
	 *
	 * @ticket 65819
	 */
	public function test_wp_rel_callback_adds_rel_to_link() {
		$matches = array( '<a href="https://example.com">', 'href="https://example.com"' );
		$actual  = wp_rel_callback( $matches, 'nofollow' );

		$this->assertSame( '<a href="https://example.com" rel="nofollow">', $actual );
	}

	/**
	 * Tests merging a new rel value with an existing rel attribute.
	 *
	 * @ticket 65819
	 */
	public function test_wp_rel_callback_merges_with_existing_rel() {
		$matches = array( '<a href="https://example.com" rel="external">', 'href="https://example.com" rel="external"' );
		$actual  = wp_rel_callback( $matches, 'nofollow' );

		$this->assertSame( '<a href="https://example.com" rel="external nofollow">', $actual );
	}

	/**
	 * Tests that existing rel values are not duplicated.
	 *
	 * @ticket 65819
	 */
	public function test_wp_rel_callback_does_not_duplicate_existing_rel() {
		$matches = array( '<a href="https://example.com" rel="nofollow ugc">', 'href="https://example.com" rel="nofollow ugc"' );
		$actual  = wp_rel_callback( $matches, 'nofollow' );

		$this->assertSame( '<a href="https://example.com" rel="nofollow ugc">', $actual );
	}

	/**
	 * Tests that nofollow is stripped when the link is internal.
	 *
	 * @ticket 65819
	 */
	public function test_wp_rel_callback_removes_nofollow_for_internal_links() {
		$home_url = home_url( '/test-page/' );
		$matches  = array( '<a href="' . $home_url . '">', 'href="' . $home_url . '"' );
		$actual   = wp_rel_callback( $matches, 'nofollow' );

		$this->assertSame( '<a href="' . $home_url . '">', $actual );
	}

	/**
	 * Tests that internal links keep existing rel and can add other rel values like ugc.
	 *
	 * @ticket 65819
	 */
	public function test_wp_rel_callback_preserves_other_rel_for_internal_links() {
		$home_url = home_url( '/internal/' );
		$matches  = array( '<a href="' . $home_url . '" rel="author">', 'href="' . $home_url . '" rel="author"' );
		$actual   = wp_rel_callback( $matches, 'ugc' );

		$this->assertSame( '<a href="' . $home_url . '" rel="author ugc">', $actual );
	}

	/**
	 * Tests handling of valueless attributes on the anchor.
	 *
	 * @ticket 65819
	 */
	public function test_wp_rel_callback_handles_valueless_attributes() {
		$matches = array( '<a href="https://example.com" download rel="opener">', 'href="https://example.com" download rel="opener"' );
		$actual  = wp_rel_callback( $matches, 'noreferrer' );

		$this->assertStringContainsString( 'download', $actual );
		$this->assertStringContainsString( 'rel="opener noreferrer"', $actual );
	}
}
