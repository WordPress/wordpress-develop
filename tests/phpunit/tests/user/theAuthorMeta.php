<?php

/**
 * Tests for the_author_meta().
 *
 * @group author
 * @group user
 *
 * @covers ::the_author_meta
 */
class Tests_User_TheAuthorMeta extends WP_UnitTestCase {

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
				'user_email'   => 'test_author@example.org',
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
	 * Tests that the_author_meta() outputs the requested field for the current post author
	 * or for an explicitly provided user ID.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_author_meta_for_current_author_and_explicit_user_id() {
		$this->assertSame(
			'Test Author',
			get_echo( 'the_author_meta', array( 'display_name' ) )
		);

		$other_user_id = self::factory()->user->create(
			array(
				'display_name' => 'Other User',
			)
		);

		$this->assertSame(
			'Other User',
			get_echo( 'the_author_meta', array( 'display_name', $other_user_id ) )
		);
	}

	/**
	 * Tests that the_author_meta() applies the dynamic 'the_author_{$field}' filter.
	 *
	 * @ticket 65819
	 */
	public function test_applies_the_author_field_filter() {
		$filter = new MockAction();
		add_filter( 'the_author_user_email', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'the_author_user_email',
			static function () {
				return 'filtered@example.org';
			},
			20
		);

		$output = get_echo( 'the_author_meta', array( 'user_email', self::$author_id ) );

		$this->assertSame( 'filtered@example.org', $output );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array( 'test_author@example.org', self::$author_id ),
			$filter->get_args()[0]
		);
	}
}
