<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::edit_link
 */
class Tests_Admin_Includes_Bookmark_EditLink_Test extends WP_UnitTestCase {

	/**
	 * User ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * User ID of a subscriber.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function set_up() {
		parent::set_up();

		// The 'manage_links' capability is only granted while the Link Manager is enabled.
		add_filter( 'pre_option_link_manager_enabled', '__return_true' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_die_for_users_who_cannot_manage_links() {
		wp_set_current_user( self::$subscriber_id );
		$this->set_up_post_data();

		$this->expectException( 'WPDieException' );
		$this->expectExceptionCode( 403 );

		edit_link();
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_insert_a_link_from_the_post_data() {
		wp_set_current_user( self::$admin_id );
		$this->set_up_post_data();

		$link_id = edit_link();

		$this->assertIsInt( $link_id, 'The link ID should be an integer.' );
		$this->assertSame( 'Example', get_bookmark( $link_id )->link_name, 'The link name was not stored.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_update_the_link_when_a_link_id_is_passed() {
		wp_set_current_user( self::$admin_id );
		$link_id = self::factory()->bookmark->create( array( 'link_name' => 'Original' ) );
		$this->set_up_post_data( array( 'link_name' => 'Updated' ) );

		$result = edit_link( $link_id );

		$this->assertSame( $link_id, $result, 'The existing link ID should be returned.' );
		$this->assertSame( 'Updated', get_bookmark( $link_id )->link_name, 'The link was not updated.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_escape_the_posted_link_name() {
		wp_set_current_user( self::$admin_id );
		$this->set_up_post_data( array( 'link_name' => '<b>Bold</b>' ) );

		$link_id = edit_link();

		$this->assertSame( '&lt;b&gt;Bold&lt;/b&gt;', get_bookmark( $link_id )->link_name );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_escape_the_posted_link_url() {
		wp_set_current_user( self::$admin_id );
		$this->set_up_post_data( array( 'link_url' => 'https://example.com/?a=1&b=2' ) );

		$link_id = edit_link();

		$this->assertSame( 'https://example.com/?a=1&#038;b=2', get_bookmark( $link_id )->link_url );
	}

	/**
	 * @ticket 66019
	 *
	 * @dataProvider data_link_visible
	 *
	 * @param array  $post_data Values to add to the posted link data.
	 * @param string $expected  The expected stored visibility.
	 */
	public function test_should_normalize_the_posted_visibility( $post_data, $expected ) {
		wp_set_current_user( self::$admin_id );
		$this->set_up_post_data( $post_data );

		$link_id = edit_link();

		$this->assertSame( $expected, get_bookmark( $link_id )->link_visible );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{post_data: array<string, string>, expected: string}>
	 */
	public function data_link_visible() {
		return array(
			'no value'       => array(
				'post_data' => array(),
				'expected'  => 'Y',
			),
			'an uppercase N' => array(
				'post_data' => array( 'link_visible' => 'N' ),
				'expected'  => 'N',
			),
			'a lowercase n'  => array(
				'post_data' => array( 'link_visible' => 'n' ),
				'expected'  => 'Y',
			),
		);
	}

	/**
	 * Populates $_POST with the fields edit_link() reads unconditionally.
	 *
	 * @param array $post_data Optional. Values to add to the posted link data.
	 */
	private function set_up_post_data( $post_data = array() ) {
		$_POST = array_merge(
			array(
				'link_url'   => 'https://example.com/',
				'link_name'  => 'Example',
				'link_image' => '',
				'link_rss'   => '',
			),
			$post_data
		);
	}
}
