<?php

/**
 * @group export
 * @group attachments
 * @ticket 17379
 */
class Test_Export_Includes_Attachments extends WP_UnitTestCase {

	public static $post;

	public static $author;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author = $factory->user->create( array( 'role' => 'editor' ) );

		$args       = array(
			'post_title'   => 'Test Post',
			'post_content' => 'Test Content',
			'post_status'  => 'publish',
			'post_author'  => self::$author,
		);
		self::$post = self::factory()->post->create_and_get( $args );

		$file          = DIR_TESTDATA . '/images/test-image.jpg';
		$attachment_id = $factory->attachment->create_upload_object( $file );

		set_post_thumbnail( self::$post->ID, $attachment_id );
	}

	/**
	 * Tests the export function to ensure that attachments are included.
	 *
	 * Runs in a separate process to prevent "headers already sent" error.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed" when running in
	 * a separate process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_export_includes_attachments_for_specific_author() {
		require_once ABSPATH . 'wp-admin/includes/export.php';

		ob_start();
		export_wp(
			array(
				'content' => 'post',
				'author'  => self::$post->post_author,
			)
		);
		$xml = simplexml_load_string( ob_get_clean() );

		$this->assertNotEmpty( $xml->channel->item->title );
		$this->assertEquals( self::$post->post_title, (string) $xml->channel->item[0]->title );
		$this->assertEquals( basename( get_attached_file( get_post_thumbnail_id( self::$post->ID ) ) ), (string) $xml->channel->item[1]->title );
	}
}
