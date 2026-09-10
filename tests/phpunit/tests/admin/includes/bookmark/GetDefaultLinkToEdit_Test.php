<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::get_default_link_to_edit
 */
class Tests_Admin_Includes_Bookmark_GetDefaultLinkToEdit_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_return_an_empty_visible_link() {
		$link = get_default_link_to_edit();

		$this->assertSame( '', $link->link_url, 'The link URL should be empty.' );
		$this->assertSame( '', $link->link_name, 'The link name should be empty.' );
		$this->assertSame( 'Y', $link->link_visible, 'The link should be visible.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_use_the_url_and_name_from_the_request() {
		$_GET['linkurl'] = 'https://example.com/?a=1&b=2';
		$_GET['name']    = 'O\\\'Brien & Sons';

		$link = get_default_link_to_edit();

		$this->assertSame( 'https://example.com/?a=1&#038;b=2', $link->link_url, 'The link URL was not escaped.' );
		$this->assertSame( 'O&#039;Brien &amp; Sons', $link->link_name, 'The link name was not unslashed and escaped.' );
	}
}
