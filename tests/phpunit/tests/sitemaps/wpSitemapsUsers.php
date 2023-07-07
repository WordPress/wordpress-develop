<?php

/**
 * @group sitemaps
 */
class Tests_Sitemaps_wpSitemapsUsers extends WP_UnitTestCase {

	/**
	 * List of user IDs.
	 *
	 * @var array
	 */
	public static $users;

	/**
	 * Editor ID for use in some tests.
	 *
	 * @var int
	 */
	public static $editor_id;

	/**
	 * Set up fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory A WP_UnitTest_Factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$users     = $factory->user->create_many( 10, array( 'role' => 'editor' ) );
		self::$editor_id = self::$users[0];
	}

	/**
	 * Test getting a URL list for a users sitemap page via
	 * WP_Sitemaps_Users::get_url_list().
	 */
	public function test_get_url_list_users() {
		// Set up the user to an editor to assign posts to other users.
		wp_set_current_user( self::$editor_id );

		// Create a set of posts for each user and generate the expected URL list data.
		$expected = array_map(
			static function ( $user_id ) {
				$post = self::factory()->post->create_and_get( array( 'post_author' => $user_id ) );

				return array(
					'loc' => get_author_posts_url( $user_id ),
				);
			},
			self::$users
		);

		$user_provider = new WP_Sitemaps_Users();

		$url_list = $user_provider->get_url_list( 1 );

		$this->assertSameSets( $expected, $url_list );
	}
}
