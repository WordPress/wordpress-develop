<?php

/**
 * Test the `get_link_to_edit()` function.
 *
 * @group bookmark
 * @covers ::get_link_to_edit
 */
class Tests_Admin_Includes_Bookmark_getLinkToEdit extends WP_UnitTestCase {

	/**
	 * Tests that get_link_to_edit() returns the link in edit context.
	 *
	 * @ticket 66019
	 */
	public function test_get_link_to_edit_returns_link_for_editing() {
		$link_id = self::factory()->bookmark->create(
			array(
				'link_name' => 'A link',
				'link_url'  => 'https://example.com',
			)
		);

		$link = get_link_to_edit( $link_id );

		$this->assertInstanceOf( stdClass::class, $link );
		$this->assertSame( $link_id, $link->link_id );
		$this->assertSame( 'A link', $link->link_name );
	}
}
