<?php

/**
 * @group formatting
 * @ticket 43187
 */
class Tests_Formatting_wpTargetedLinkRel extends WP_UnitTestCase {

	public function test_add_to_links_with_target_blank() {
		$content  = '<p>Links: <a href="/" target="_blank">No rel</a></p>';
		$expected = '<p>Links: <a href="/" target="_blank" rel="noopener">No rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_add_to_links_with_target_foo() {
		$content  = '<p>Links: <a href="/" target="foo">No rel</a></p>';
		$expected = '<p>Links: <a href="/" target="foo" rel="noopener">No rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_target_as_first_attribute() {
		$content  = '<p>Links: <a target="_blank" href="#">No rel</a></p>';
		$expected = '<p>Links: <a target="_blank" href="#" rel="noopener">No rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_add_to_existing_rel() {
		$content  = '<p>Links: <a href="/" rel="existing values" target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing values noopener" target="_blank">Existing rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_no_duplicate_values_added() {
		$content  = '<p>Links: <a href="/" rel="existing noopener values" target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing noopener values" target="_blank">Existing rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_rel_with_single_quote_delimiter() {
		$content  = '<p>Links: <a href="/" rel=\'existing values\' target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing values noopener" target="_blank">Existing rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_rel_with_no_delimiter() {
		$content  = '<p>Links: <a href="/" rel=existing target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing noopener" target="_blank">Existing rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_rel_value_spaced_and_no_delimiter() {
		$content  = '<p>Links: <a href="/" rel = existing target="_blank">Existing rel</a></p>';
		$expected = '<p>Links: <a href="/" rel="existing noopener" target="_blank">Existing rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_escaped_quotes() {
		$content  = '<p>Links: <a href=\"/\" rel=\"existing values\" target=\"_blank\">Existing rel</a></p>';
		$expected = '<p>Links: <a href=\"/\" rel=\"existing values noopener\" target=\"_blank\">Existing rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_ignore_links_with_no_target() {
		$content  = '<p>Links: <a href="/" target="_blank">Change me</a> <a href="/">Do not change me</a></p>';
		$expected = '<p>Links: <a href="/" target="_blank" rel="noopener">Change me</a> <a href="/">Do not change me</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	/**
	 * Ensure empty rel attributes are not added.
	 *
	 * @ticket 45352
	 */
	public function test_ignore_if_wp_targeted_link_rel_nulled() {
		add_filter( 'wp_targeted_link_rel', '__return_empty_string' );
		$content  = '<p>Links: <a href="/" target="_blank">Do not change me</a></p>';
		$expected = '<p>Links: <a href="/" target="_blank">Do not change me</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	/**
	 * Ensure default content filters are added.
	 *
	 * @ticket 45292
	 */
	public function test_wp_targeted_link_rel_filters_run() {
		$content  = '<p>Links: <a href="/" target="_blank">No rel</a></p>';
		$expected = '<p>Links: <a href="/" target="_blank" rel="noopener">No rel</a></p>';

		$post = $this->factory()->post->create_and_get(
			array(
				'post_content' => $content,
			)
		);

		$this->assertSame( $expected, $post->post_content );
	}

	/**
	 * Ensure JSON format is preserved when relation attribute (rel) is missing.
	 *
	 * @ticket 46316
	 */
	public function test_wp_targeted_link_rel_should_preserve_json() {
		$content  = '<p>Links: <a href=\"\/\" target=\"_blank\">No rel<\/a><\/p>';
		$expected = '<p>Links: <a href=\"\/\" target=\"_blank\" rel=\"noopener\">No rel<\/a><\/p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	/**
	 * Ensure the content of style and script tags are not processed
	 *
	 * @ticket 47244
	 */
	public function test_wp_targeted_link_rel_skips_style_and_scripts() {
		$content  = '<style><a href="/" target=a></style><p>Links: <script>console.log("<a href=\'/\' target=a>hi</a>");</script><script>alert(1);</script>here <a href="/" target=_blank>aq</a></p><script>console.log("<a href=\'last\' target=\'_blank\'")</script>';
		$expected = '<style><a href="/" target=a></style><p>Links: <script>console.log("<a href=\'/\' target=a>hi</a>");</script><script>alert(1);</script>here <a href="/" target="_blank" rel="noopener">aq</a></p><script>console.log("<a href=\'last\' target=\'_blank\'")</script>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	/**
	 * Ensure entirely serialized content is ignored.
	 *
	 * @ticket 46402
	 */
	public function test_ignore_entirely_serialized_content() {
		$content  = 'a:1:{s:4:"html";s:52:"<p>Links: <a href="/" target="_blank">No Rel</a></p>";}';
		$expected = 'a:1:{s:4:"html";s:52:"<p>Links: <a href="/" target="_blank">No Rel</a></p>";}';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

	public function test_wp_targeted_link_rel_tab_separated_values_are_split() {
		$content  = "<p>Links: <a href=\"/\" target=\"_blank\" rel=\"ugc\t\tnoopener\t\">No rel</a></p>";
		$expected = '<p>Links: <a href="/" target="_blank" rel="ugc noopener">No rel</a></p>';
		$this->assertSame( $expected, wp_targeted_link_rel( $content ) );
	}

}
