<?php

/**
 * @group comment
 * @covers ::pings_open
 */
class Tests_Comment_PingsOpen extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Remove the environment-based filter to test core pings_open() behavior in isolation.
		remove_filter( 'pings_open', 'wp_disable_pings_open_for_environment' );
	}

	/**
	 * @ticket 54159
	 */
	public function test_post_does_not_exist() {
		$this->assertFalse( pings_open( 99999 ) );
	}

	/**
	 * @ticket 54159
	 */
	public function test_post_exist_status_open() {
		$post = self::factory()->post->create_and_get();
		$this->assertTrue( pings_open( $post ) );
	}

	/**
	 * @ticket 54159
	 */
	public function test_post_exist_status_closed() {
		$post              = self::factory()->post->create_and_get();
		$post->ping_status = 'closed';

		$this->assertFalse( pings_open( $post ) );
	}
}
