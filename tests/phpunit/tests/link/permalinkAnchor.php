<?php
/**
 * Tests for the `permalink_anchor()` function.
 *
 * @group link
 * @group post
 *
 * @covers ::permalink_anchor
 */
class Tests_Link_PermalinkAnchor extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Sets up shared fixtures for the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Test Post Title',
			)
		);
	}

	/**
	 * Sets up the global $post object before each test method.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['post'] = get_post( self::$post_id );
	}

	/**
	 * Tears down the global $post object after each test method.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Tests that permalink_anchor() outputs the post ID anchor by default when no argument is passed.
	 *
	 * @ticket 65819
	 */
	public function test_permalink_anchor_default_mode_id() {
		$expected = '<a id="post-' . self::$post_id . '"></a>';

		$this->assertSame( $expected, get_echo( 'permalink_anchor' ) );
	}

	/**
	 * Tests that permalink_anchor() outputs the post ID anchor when explicitly passing 'id'.
	 *
	 * @ticket 65819
	 */
	public function test_permalink_anchor_explicit_mode_id() {
		$expected = '<a id="post-' . self::$post_id . '"></a>';

		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( 'id' ) ) );
	}

	/**
	 * Tests that permalink_anchor() handles mode case-insensitively for 'id'.
	 *
	 * @ticket 65819
	 */
	public function test_permalink_anchor_mode_id_case_insensitive() {
		$expected = '<a id="post-' . self::$post_id . '"></a>';

		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( 'ID' ) ) );
		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( 'Id' ) ) );
	}

	/**
	 * Tests that permalink_anchor() outputs the sanitized title anchor when passing 'title'.
	 *
	 * @ticket 65819
	 */
	public function test_permalink_anchor_mode_title() {
		$expected = '<a id="test-post-title-' . self::$post_id . '"></a>';

		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( 'title' ) ) );
	}

	/**
	 * Tests that permalink_anchor() handles mode case-insensitively for 'title'.
	 *
	 * @ticket 65819
	 */
	public function test_permalink_anchor_mode_title_case_insensitive() {
		$expected = '<a id="test-post-title-' . self::$post_id . '"></a>';

		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( 'TITLE' ) ) );
		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( 'Title' ) ) );
	}

	/**
	 * Tests that permalink_anchor() properly sanitizes titles containing special characters.
	 *
	 * @ticket 65819
	 */
	public function test_permalink_anchor_mode_title_with_special_characters() {
		$special_post_id = self::factory()->post->create(
			array(
				'post_title' => 'Special Characters & Symbols: 100% Valid!',
			)
		);

		$GLOBALS['post'] = get_post( $special_post_id );

		$expected = '<a id="special-characters-symbols-100-valid-' . $special_post_id . '"></a>';

		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( 'title' ) ) );
	}

	/**
	 * Tests that permalink_anchor() falls back to id mode for unrecognized mode arguments.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unrecognized_modes
	 *
	 * @param string $mode Mode to pass.
	 */
	public function test_permalink_anchor_unrecognized_mode_falls_back_to_id( $mode ) {
		$expected = '<a id="post-' . self::$post_id . '"></a>';

		$this->assertSame( $expected, get_echo( 'permalink_anchor', array( $mode ) ) );
	}

	/**
	 * Data provider for test_permalink_anchor_unrecognized_mode_falls_back_to_id().
	 *
	 * @return array[]
	 */
	public static function data_unrecognized_modes() {
		return array(
			'empty string'        => array( '' ),
			'arbitrary string'    => array( 'custom' ),
			'invalid mode slug'   => array( 'slug' ),
			'numeric string mode' => array( '123' ),
		);
	}
}
