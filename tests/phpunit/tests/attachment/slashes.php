<?php

/**
 * @group attachment
 * @group slashes
 * @ticket 21767
 *
 * @covers ::wp_insert_attachment
 */
class Tests_Attachment_Slashes extends WP_UnitTestCase {

	/*
	 * It is important to test with both even and odd numbered slashes,
	 * as KSES does a strip-then-add slashes in some of its function calls.
	 */

	const SLASH_1 = 'String with 1 slash \\';
	const SLASH_2 = 'String with 2 slashes \\\\';
	const SLASH_3 = 'String with 3 slashes \\\\\\';
	const SLASH_4 = 'String with 4 slashes \\\\\\\\';
	const SLASH_5 = 'String with 5 slashes \\\\\\\\\\';
	const SLASH_6 = 'String with 6 slashes \\\\\\\\\\\\';
	const SLASH_7 = 'String with 7 slashes \\\\\\\\\\\\\\';

	protected static $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$author_id );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_wp_insert_attachment() {
		$post_id = wp_insert_attachment(
			array(
				'post_status'           => 'publish',
				'post_title'            => self::SLASH_1,
				'post_content_filtered' => self::SLASH_3,
				'post_excerpt'          => self::SLASH_5,
				'post_type'             => 'post',
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( wp_unslash( self::SLASH_1 ), $post->post_title );
		$this->assertSame( wp_unslash( self::SLASH_3 ), $post->post_content_filtered );
		$this->assertSame( wp_unslash( self::SLASH_5 ), $post->post_excerpt );

		$post_id = wp_insert_attachment(
			array(
				'post_status'           => 'publish',
				'post_title'            => self::SLASH_2,
				'post_content_filtered' => self::SLASH_4,
				'post_excerpt'          => self::SLASH_6,
				'post_type'             => 'post',
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( wp_unslash( self::SLASH_2 ), $post->post_title );
		$this->assertSame( wp_unslash( self::SLASH_4 ), $post->post_content_filtered );
		$this->assertSame( wp_unslash( self::SLASH_6 ), $post->post_excerpt );
	}

}
