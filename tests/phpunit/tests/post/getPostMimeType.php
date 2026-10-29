<?php
/**
 * Tests for get_post_mime_type().
 *
 * @group post
 *
 * @covers ::get_post_mime_type
 */
class Tests_Post_GetPostMimeType extends WP_UnitTestCase {

	/**
	 * Attachment post ID.
	 *
	 * @var int
	 */
	private static $attachment_id;

	/**
	 * Regular post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Regular post',
			)
		);

		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => 'test-image.jpg',
				'post_parent'    => self::$post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'Test Image',
			)
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_invalid_post() {
		$this->assertFalse( get_post_mime_type( 0 ) );
		$this->assertFalse( get_post_mime_type( PHP_INT_MAX ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_mime_type_for_attachment_id() {
		$this->assertSame( 'image/jpeg', get_post_mime_type( self::$attachment_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_mime_type_for_post_object() {
		$attachment = get_post( self::$attachment_id );

		$this->assertSame( 'image/jpeg', get_post_mime_type( $attachment ) );
	}

	/**
	 * Regular posts store an empty mime type string.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_regular_post() {
		$this->assertSame( '', get_post_mime_type( self::$post_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_uses_global_post_when_no_argument() {
		$GLOBALS['post'] = get_post( self::$attachment_id );

		$this->assertSame( 'image/jpeg', get_post_mime_type() );

		$GLOBALS['post'] = null;
	}
}
