<?php
/**
 * Tests for filter_block_content().
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 7.2.0
 *
 * @group blocks
 * @group kses
 *
 * @covers ::filter_block_content
 */
class Tests_Blocks_FilterBlockContent extends WP_UnitTestCase {

	/**
	 * Block content filtering re-serializes what it parses, so an empty object
	 * attribute must survive it. This is the save-time path the bug was reported
	 * against, and it runs for every user without the `unfiltered_html` capability.
	 *
	 * @ticket 63325
	 *
	 * @dataProvider data_empty_object_markup
	 *
	 * @param string $markup Block markup that must survive unchanged.
	 */
	public function test_empty_objects_survive_kses_filtering( $markup ) {
		$this->assertSame( $markup, filter_block_content( $markup ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_empty_object_markup() {
		return array(
			'empty object and empty array' => array( '<!-- wp:test {"nested":{"object":{},"array":[]}} /-->' ),
			'inside an array'              => array( '<!-- wp:test {"items":[{},[]]} /-->' ),
			'inner blocks'                 => array( '<!-- wp:outer {"a":{}} --><!-- wp:inner {"b":{}} /--><!-- /wp:outer -->' ),
		);
	}

	/**
	 * Strings inside a populated object must still be sanitized.
	 *
	 * filter_block_kses_value() recurses through arrays only, so a populated object
	 * left as an object would carry its strings past wp_kses().
	 *
	 * @ticket 63325
	 */
	public function test_kses_still_sanitizes_strings_beside_a_preserved_empty_object() {
		$markup = '<!-- wp:test {"config":{"label":"<script>alert(1)</script><strong>Safe</strong>","options":{}}} /-->';

		$actual = filter_block_content( $markup );

		$this->assertStringNotContainsString( 'script', $actual, 'The disallowed tag should have been stripped.' );
		$this->assertStringContainsString( 'strong', $actual, 'The allowed tag should have survived.' );
		$this->assertStringContainsString( '"options":{}', $actual, 'The empty object should have survived.' );
	}

	/**
	 * The empty object must not reach saved content as anything but `{}`.
	 *
	 * Preservation uses no marker key or sentinel value. This guards against one
	 * being introduced later.
	 *
	 * @ticket 63325
	 */
	public function test_no_implementation_data_reaches_filtered_content() {
		$actual = filter_block_content( '<!-- wp:test {"nested":{"object":{}}} /-->' );

		$this->assertStringNotContainsString( '__wp', $actual );
		$this->assertStringNotContainsString( 'stdClass', $actual );
	}
}
