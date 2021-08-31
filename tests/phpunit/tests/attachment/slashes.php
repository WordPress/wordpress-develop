<?php

/**
 * @group attachment
 * @group slashes
 * @ticket 21767
 */
class Tests_Attachment_Slashes extends WP_UnitTestCase {
	protected static $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	function set_up() {
		parent::set_up();

		wp_set_current_user( self::$author_id );

		// It is important to test with both even and odd numbered slashes,
		// as KSES does a strip-then-add slashes in some of its function calls.
		$this->slash_1 = 'String with 1 slash \\';
		$this->slash_2 = 'String with 2 slashes \\\\';
		$this->slash_3 = 'String with 3 slashes \\\\\\';
		$this->slash_4 = 'String with 4 slashes \\\\\\\\';
		$this->slash_5 = 'String with 5 slashes \\\\\\\\\\';
		$this->slash_6 = 'String with 6 slashes \\\\\\\\\\\\';
		$this->slash_7 = 'String with 7 slashes \\\\\\\\\\\\\\';
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	function test_wp_insert_attachment() {
		$post_id = wp_insert_attachment(
			array(
				'post_status'           => 'publish',
				'post_title'            => $this->slash_1,
				'post_content_filtered' => $this->slash_3,
				'post_excerpt'          => $this->slash_5,
				'post_type'             => 'post',
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( wp_unslash( $this->slash_1 ), $post->post_title );
		$this->assertSame( wp_unslash( $this->slash_3 ), $post->post_content_filtered );
		$this->assertSame( wp_unslash( $this->slash_5 ), $post->post_excerpt );

		$post_id = wp_insert_attachment(
			array(
				'post_status'           => 'publish',
				'post_title'            => $this->slash_2,
				'post_content_filtered' => $this->slash_4,
				'post_excerpt'          => $this->slash_6,
				'post_type'             => 'post',
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( wp_unslash( $this->slash_2 ), $post->post_title );
		$this->assertSame( wp_unslash( $this->slash_4 ), $post->post_content_filtered );
		$this->assertSame( wp_unslash( $this->slash_6 ), $post->post_excerpt );
	}

}
