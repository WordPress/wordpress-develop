<?php

/**
 * @group formatting
 * @ticket 43187
 */
class Tests_Targeted_Link_Rel extends WP_UnitTestCase {

	public function test_add_to_links_with_target_blank() {
		$content  = '<p>Links: <a href="/" target="_blank">No rel</a></p>';
		$expected = '<p>Links: <a href="/" target="_blank" rel="noopener noreferrer">No rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_add_to_links_with_target_foo() {
		$content  = '<p>Links: <a href="/" target="foo">No rel</a></p>';
		$expected = '<p>Links: <a href="/" target="foo" rel="noopener noreferrer">No rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_target_as_first_attribute() {
		$content  = '<p>Links: <a target="_blank" href="#">No rel</a></p>';
		$expected = '<p>Links: <a target="_blank" href="#" rel="noopener noreferrer">No rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_add_to_existing_rel() {
		$content  = '<p>Links: <a href="/" rel="existing values" target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing values noopener noreferrer" target="_blank">Existing rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_no_duplicate_values_added() {
		$content  = '<p>Links: <a href="/" rel="existing noopener values" target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing noopener values noreferrer" target="_blank">Existing rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_rel_with_single_quote_delimiter() {
		$content  = '<p>Links: <a href="/" rel=\'existing values\' target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel=\'existing values noopener noreferrer\' target="_blank">Existing rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_rel_with_no_delimiter() {
		$content  = '<p>Links: <a href="/" rel=existing target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing noopener noreferrer" target="_blank">Existing rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_rel_value_spaced_and_no_delimiter() {
		$content  = '<p>Links: <a href="/" rel = existing target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing noopener noreferrer" target="_blank">Existing rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_rel_value_spaced_and_no_delimiter_and_values_to_escape() {
		$content  = '<p>Links: <a href="/" rel = existing"value target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing&quot;value noopener noreferrer" target="_blank">Existing rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_escaped_quotes() {
		$content  = '<p>Links: <a href=\"/\" rel=\"existing values\" target=\"_blank\">Existing rel</a></p>';
		$expected = '<p>Links: <a href=\"/\" rel=\"existing values noopener noreferrer\" target=\"_blank\">Existing rel</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_ignore_links_with_no_target() {
		$content  = '<p>Links: <a href="/" target="_blank">Change me</a> <a href="/">Do not change me</a></p>';
		$expected = '<p>Links: <a href="/" target="_blank" rel="noopener noreferrer">Change me</a> <a href="/">Do not change me</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}

	/**
	 * Ensure empty rel attributes are not added.
	 *
	 * @ticket 45352.
	 */
	public function test_ignore_if_wp_targeted_link_rel_nulled() {
		add_filter( 'wp_targeted_link_rel', '__return_empty_string' );
		$content  = '<p>Links: <a href="/" target="_blank">Do not change me</a></p>';
		$expected = '<p>Links: <a href="/" target="_blank">Do not change me</a></p>';
		$this->assertEquals( $expected, wp_targeted_link_rel( $content ) );
	}
}
