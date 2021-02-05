<?php

/**
 * @group bookmark
 * @covers ::wp_update_link
 */
class Tests_Bookmark_WpUpdateLink extends WP_UnitTestCase {

	public function test_should_update_existing_bookmark() {
		$bookmark_id = self::factory()->bookmark->create();
		$link_name   = 'foo';
		$result      = wp_update_link(
			array(
				'link_id'   => $bookmark_id,
				'link_name' => $link_name,
			)
		);
		$this->assertSame( $bookmark_id, $result );
		$this->assertSame( $link_name, get_bookmark( $bookmark_id )->link_name );
	}

	public function test_should_not_update_non_existing_bookmark() {
		$bookmark_id = -1;
		$link_name   = 'foo';
		$result      = wp_update_link(
			array(
				'link_id'   => $bookmark_id,
				'link_name' => $link_name,
			)
		);
		$this->assertNotSame( $bookmark_id, $result );
		$this->assertWPError( $result );
	}
}
