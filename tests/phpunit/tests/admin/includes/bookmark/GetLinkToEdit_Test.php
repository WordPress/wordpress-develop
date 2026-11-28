<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::get_link_to_edit
 */
class Tests_Admin_Includes_Bookmark_GetLinkToEdit_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_return_the_link_prepared_for_the_edit_context() {
		$link_id = self::factory()->bookmark->create( array( 'link_name' => 'Tom & "Jerry"' ) );

		$this->assertSame( 'Tom &amp; &quot;Jerry&quot;', get_link_to_edit( $link_id )->link_name );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_accept_a_link_object() {
		$link_id = self::factory()->bookmark->create( array( 'link_name' => 'Tom & "Jerry"' ) );

		$this->assertSame( 'Tom &amp; &quot;Jerry&quot;', get_link_to_edit( get_bookmark( $link_id ) )->link_name );
	}
}
