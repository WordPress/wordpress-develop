<?php

/**
 * Tests for is_multi_author().
 *
 * @group author
 * @group user
 *
 * @covers ::is_multi_author
 */
class Tests_User_IsMultiAuthor extends WP_UnitTestCase {

	/**
	 * First author user ID.
	 *
	 * @var int
	 */
	private static $author_1;

	/**
	 * Second author user ID.
	 *
	 * @var int
	 */
	private static $author_2;

	/**
	 * Creates shared author fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_1 = $factory->user->create( array( 'role' => 'author' ) );
		self::$author_2 = $factory->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Clears the multi-author transient before each test.
	 */
	public function set_up() {
		parent::set_up();
		delete_transient( 'is_multi_author' );
	}

	/**
	 * Tests that is_multi_author() returns false when zero or one author has published posts
	 * and caches 0 in the 'is_multi_author' transient.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_with_zero_or_single_author() {
		$this->assertFalse( is_multi_author() );
		$this->assertSame( 0, get_transient( 'is_multi_author' ) );

		self::factory()->post->create(
			array(
				'post_author' => self::$author_1,
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( is_multi_author() );
		$this->assertSame( 0, get_transient( 'is_multi_author' ) );
	}

	/**
	 * Tests that is_multi_author() returns true when two distinct authors have published posts
	 * and caches 1 in the 'is_multi_author' transient.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_when_multiple_authors_have_published_posts() {
		self::factory()->post->create(
			array(
				'post_author' => self::$author_1,
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_2,
				'post_status' => 'publish',
			)
		);

		$this->assertTrue( is_multi_author() );
		$this->assertSame( 1, get_transient( 'is_multi_author' ) );
	}

	/**
	 * Tests that is_multi_author() ignores draft posts and non-post post types from a second author.
	 *
	 * @ticket 65819
	 */
	public function test_ignores_drafts_and_other_post_types() {
		self::factory()->post->create(
			array(
				'post_author' => self::$author_1,
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_2,
				'post_status' => 'draft',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$author_2,
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);

		$this->assertFalse( is_multi_author() );
	}

	/**
	 * Tests that is_multi_author() uses the cached 'is_multi_author' transient when available.
	 *
	 * @ticket 65819
	 */
	public function test_uses_cached_transient_value() {
		set_transient( 'is_multi_author', 1 );
		$this->assertTrue( is_multi_author() );

		set_transient( 'is_multi_author', 0 );
		$this->assertFalse( is_multi_author() );
	}

	/**
	 * Tests that is_multi_author() applies the 'is_multi_author' filter.
	 *
	 * @ticket 65819
	 */
	public function test_applies_is_multi_author_filter() {
		$filter = new MockAction();
		add_filter( 'is_multi_author', array( $filter, 'filter' ) );
		add_filter( 'is_multi_author', '__return_true', 20 );

		$this->assertTrue( is_multi_author() );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( false ), $filter->get_args()[0] );
	}
}
