<?php

/**
 * Test the `get_default_link_to_edit()` function.
 *
 * @group bookmark
 * @covers ::get_default_link_to_edit
 */
class Tests_Admin_Includes_Bookmark_getDefaultLinkToEdit extends WP_UnitTestCase {

	/**
	 * Tests that get_default_link_to_edit() builds the expected default object.
	 *
	 * @ticket 66019
	 * @dataProvider data_default_link_values
	 *
	 * @param array  $get          Query-string values.
	 * @param string $expected_url  Expected link URL.
	 * @param string $expected_name Expected link name.
	 */
	public function test_get_default_link_to_edit( $get, $expected_url, $expected_name ) {
		$_GET = $get;

		$link = get_default_link_to_edit();

		$this->assertSame( $expected_url, $link->link_url );
		$this->assertSame( $expected_name, $link->link_name );
		$this->assertSame( 'Y', $link->link_visible );
	}

	/**
	 * Data provider for test_get_default_link_to_edit().
	 *
	 * @return array<string, array{
	 *     get: array<string, string>,
	 *     expected_url: string,
	 *     expected_name: string,
	 * }>
	 */
	public function data_default_link_values(): array {
		return array(
			'empty values'    => array(
				'get'           => array(),
				'expected_url'  => '',
				'expected_name' => '',
			),
			'provided values' => array(
				'get'           => array(
					'linkurl' => 'https://example.com/?a=1&amp;b=2',
					'name'    => 'Example &amp; name',
				),
				'expected_url'  => 'https://example.com/?a=1&#038;b=2',
				'expected_name' => 'Example &amp; name',
			),
		);
	}

	public function tear_down() {
		$_GET = array();
		parent::tear_down();
	}
}
