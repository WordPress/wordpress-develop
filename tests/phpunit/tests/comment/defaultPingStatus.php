<?php

/**
 * Unit tests covering default ping status and pingback flag settings.
 *
 * @group comment
 * @group option
 *
 * @covers ::populate_options
 * @covers ::get_default_comment_status
 * @covers ::wp_insert_post
 * @covers ::_publish_post_hook
 */
class Tests_Comment_DefaultPingStatus extends WP_UnitTestCase {

	/**
	 * Original default_ping_status option value.
	 *
	 * @var string
	 */
	private $original_ping_status;

	/**
	 * Original default_pingback_flag option value.
	 *
	 * @var int|string
	 */
	private $original_pingback_flag;

	public function set_up() {
		parent::set_up();

		$this->original_ping_status   = get_option( 'default_ping_status' );
		$this->original_pingback_flag = get_option( 'default_pingback_flag' );
	}

	public function tear_down() {
		update_option( 'default_ping_status', $this->original_ping_status );
		update_option( 'default_pingback_flag', $this->original_pingback_flag );

		parent::tear_down();
	}

	/**
	 * Tests that default_ping_status defaults to 'closed' for fresh installations.
	 */
	public function test_default_ping_status_defaults_to_closed() {
		$this->assertSame( 'closed', get_option( 'default_ping_status' ) );
	}

	/**
	 * Tests that default_pingback_flag defaults to 0 for fresh installations.
	 */
	public function test_default_pingback_flag_defaults_to_zero() {
		$this->assertSame( 0, (int) get_option( 'default_pingback_flag' ) );
	}

	/**
	 * Tests that newly created posts default to ping_status 'closed'.
	 */
	public function test_new_post_defaults_to_closed_ping_status() {
		$post = self::factory()->post->create_and_get();

		$this->assertSame( 'closed', $post->ping_status );
		$this->assertFalse( pings_open( $post ) );
	}

	/**
	 * Tests that an explicit ping_status 'open' is respected on post creation.
	 */
	public function test_new_post_respects_explicit_open_ping_status() {
		$post = self::factory()->post->create_and_get(
			array( 'ping_status' => 'open' )
		);

		$this->assertSame( 'open', $post->ping_status );
		$this->assertTrue( pings_open( $post ) );
	}

	/**
	 * Tests that get_default_comment_status() returns 'closed' for pingback and trackback.
	 */
	public function test_get_default_comment_status_returns_closed_for_pingback_and_trackback() {
		$this->assertSame( 'closed', get_default_comment_status( 'post', 'pingback' ) );
		$this->assertSame( 'closed', get_default_comment_status( 'post', 'trackback' ) );
		$this->assertSame( 'open', get_default_comment_status( 'post', 'comment' ) );
	}

	/**
	 * Tests that modifying default_ping_status option updates new post creation defaults.
	 */
	public function test_updating_default_ping_status_option_updates_new_post_defaults() {
		update_option( 'default_ping_status', 'open' );

		$this->assertSame( 'open', get_default_comment_status( 'post', 'pingback' ) );

		$post = self::factory()->post->create_and_get();
		$this->assertSame( 'open', $post->ping_status );
		$this->assertTrue( pings_open( $post ) );
	}

	/**
	 * Tests that publishing a post does not queue _pingme postmeta when default_pingback_flag is 0.
	 */
	public function test_publish_post_does_not_queue_pingme_when_flag_is_zero() {
		update_option( 'default_pingback_flag', 0 );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Link: <a href="https://example.com/target">Target</a>',
			)
		);

		$this->assertSame( '', get_post_meta( $post_id, '_pingme', true ) );
	}

	/**
	 * Tests that publishing a post queues _pingme postmeta when default_pingback_flag is enabled.
	 */
	public function test_publish_post_queues_pingme_when_flag_is_enabled() {
		update_option( 'default_pingback_flag', 1 );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Link: <a href="https://example.com/target">Target</a>',
			)
		);

		$this->assertSame( '1', get_post_meta( $post_id, '_pingme', true ) );
	}

	/**
	 * Tests that populate_options() does not overwrite existing site settings during upgrades.
	 */
	public function test_populate_options_does_not_overwrite_existing_settings() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once ABSPATH . 'wp-admin/includes/schema.php';

		update_option( 'default_ping_status', 'open' );
		update_option( 'default_pingback_flag', 1 );

		populate_options();

		$this->assertSame( 'open', get_option( 'default_ping_status' ) );
		$this->assertSame( 1, (int) get_option( 'default_pingback_flag' ) );
	}
}
