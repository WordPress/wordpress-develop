<?php

/**
 * Tests for the_author_link().
 *
 * @group author
 * @group user
 *
 * @covers ::the_author_link
 */
class Tests_User_TheAuthorLink extends WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	private static $author_id = 0;

	/**
	 * Post ID authored by self::$author_id.
	 *
	 * @var int
	 */
	private static $post_id = 0;

	/**
	 * Creates shared author and post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'test_author',
				'display_name' => 'Test Author',
				'user_url'     => 'http://example.com',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Sets up the global post data before each test.
	 */
	public function set_up() {
		parent::set_up();

		setup_postdata( get_post( self::$post_id ) );
	}

	/**
	 * Tests that the_author_link() outputs the author website link with a title attribute by default.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_author_link_with_title_attribute_by_default() {
		$output = get_echo( 'the_author_link' );

		$this->assertSame( get_the_author_link(), $output );
		$this->assertStringContainsString( 'http://example.com', $output );
		$this->assertStringContainsString( 'Test Author', $output );
		$this->assertStringContainsString( 'title="', $output );
	}

	/**
	 * Tests that the_author_link() omits the title attribute when $use_title_attr is false.
	 *
	 * @ticket 65819
	 */
	public function test_omits_title_attribute_when_use_title_attr_is_false() {
		$output = get_echo( 'the_author_link', array( false ) );

		$this->assertSame( get_the_author_link( false ), $output );
		$this->assertStringContainsString( 'http://example.com', $output );
		$this->assertStringNotContainsString( 'title="', $output );
	}

	/**
	 * Tests that the_author_link() outputs the author display name when the author has no website URL.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_author_name_when_author_has_no_url() {
		$author_without_url = self::factory()->user->create(
			array(
				'display_name' => 'No URL Author',
				'user_url'     => '',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $author_without_url,
			)
		);

		setup_postdata( get_post( $post_id ) );

		$this->assertSame( 'No URL Author', get_echo( 'the_author_link' ) );
	}
}
