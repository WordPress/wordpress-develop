<?php

/**
 * Tests for the `wp_force_plain_post_permalink()` function.
 *
 * @group link
 *
 * @covers ::wp_force_plain_post_permalink
 */
class Tests_Link_WpForcePlainPostPermalink extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tear_down() {
		_unregister_post_status( 'wptests_public' );
		parent::tear_down();
	}

	/**
	 * Tests that a missing post forces a plain permalink.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_missing_post() {
		$this->assertTrue( wp_force_plain_post_permalink( PHP_INT_MAX ), 'A missing post ID should force a plain permalink.' );
		$this->assertTrue( wp_force_plain_post_permalink(), 'No global post should force a plain permalink.' );
	}

	/**
	 * Tests the result for each built-in post status.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_post_statuses
	 *
	 * @param string $status   Post status.
	 * @param bool   $expected Expected result.
	 */
	public function test_post_statuses( $status, $expected ) {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => $status,
				'post_date'   => 'future' === $status ? '2099-01-01 00:00:00' : '2024-01-01 00:00:00',
			)
		);

		$this->assertSame( $expected, wp_force_plain_post_permalink( $post_id ) );
	}

	/**
	 * Data provider for test_post_statuses().
	 *
	 * @return array[]
	 */
	public static function data_post_statuses() {
		return array(
			'publish' => array( 'publish', false ),
			'draft'   => array( 'draft', true ),
			'pending' => array( 'pending', true ),
			'future'  => array( 'future', true ),
		);
	}

	/**
	 * Tests that the global post is used by default and an explicit post takes precedence.
	 *
	 * @ticket 65819
	 */
	public function test_uses_global_post_by_default() {
		$draft_id     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$published_id = self::factory()->post->create();

		$GLOBALS['post'] = get_post( $published_id );

		$this->assertFalse( wp_force_plain_post_permalink(), 'The global published post should not force a plain permalink.' );
		$this->assertTrue( wp_force_plain_post_permalink( $draft_id ), 'An explicit draft post should force a plain permalink.' );
	}

	/**
	 * Tests that a private post only gets a pretty permalink for users who can read it.
	 *
	 * @ticket 65819
	 */
	public function test_private_post_depends_on_read_capability() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'private',
				'post_author' => self::$admin_id,
			)
		);

		$this->assertTrue( wp_force_plain_post_permalink( $post_id ), 'A logged-out user should get a plain permalink.' );

		wp_set_current_user( self::$subscriber_id );
		$this->assertTrue( wp_force_plain_post_permalink( $post_id ), 'A subscriber should get a plain permalink.' );

		wp_set_current_user( self::$admin_id );
		$this->assertFalse( wp_force_plain_post_permalink( $post_id ), 'An administrator should not get a plain permalink.' );
	}

	/**
	 * Tests that protected statuses only avoid a plain permalink for sample permalinks.
	 *
	 * @ticket 65819
	 */
	public function test_protected_status_depends_on_sample() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertTrue( wp_force_plain_post_permalink( $post_id, false ), 'A draft should force a plain permalink.' );
		$this->assertFalse( wp_force_plain_post_permalink( $post_id, true ), 'A draft sample permalink should not be plain.' );

		wp_set_current_user( self::$admin_id );

		$this->assertTrue( wp_force_plain_post_permalink( $post_id, false ), 'A draft should force a plain permalink even for users who can read it.' );
	}

	/**
	 * Tests that a private post is not treated as a sample, even for sample permalinks.
	 *
	 * @ticket 65819
	 */
	public function test_private_status_ignores_sample() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'private' ) );

		$this->assertTrue( wp_force_plain_post_permalink( $post_id, true ) );
	}

	/**
	 * Tests that a post object with the 'sample' filter is treated as a sample permalink
	 * unless $sample is passed.
	 *
	 * @ticket 65819
	 */
	public function test_sample_filter_on_post_object() {
		$post         = get_post( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );
		$post->filter = 'sample';

		$this->assertFalse( wp_force_plain_post_permalink( $post ), 'A sample post object should not force a plain permalink.' );
		$this->assertTrue( wp_force_plain_post_permalink( $post, false ), 'An explicit $sample value should take precedence.' );
	}

	/**
	 * Tests that an unregistered post status forces a plain permalink.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_unregistered_post_status() {
		$post_id = self::factory()->post->create();

		$post              = get_post( $post_id );
		$post->post_status = 'wptests_unregistered';

		$this->assertTrue( wp_force_plain_post_permalink( $post, false ) );
	}

	/**
	 * Tests that an unregistered post type forces a plain permalink.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_unregistered_post_type() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'wptests_unregistered' ) );

		$this->assertTrue( wp_force_plain_post_permalink( $post_id ) );
	}

	/**
	 * Tests that a custom public post status does not force a plain permalink.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_custom_public_status() {
		register_post_status( 'wptests_public', array( 'public' => true ) );

		$post_id = self::factory()->post->create( array( 'post_status' => 'wptests_public' ) );

		$this->assertFalse( wp_force_plain_post_permalink( $post_id ) );
	}
}
