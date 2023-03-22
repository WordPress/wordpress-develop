<?php

/**
 * @group export
 * @group attachments
 * @ticket 17379
 */
class Test_Export_Includes_Attachments extends WP_UnitTestCase {
	protected static $post_1;
	protected static $attachment_1;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_1 = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Test Post 1',
				'post_author' => $factory->user->create( array( 'role' => 'editor' ) ),
			)
		);

		$post_2 = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Test Post 2',
				'post_author' => $factory->user->create( array( 'role' => 'editor' ) ),
			)
		);

		$file = DIR_TESTDATA . '/images/test-image.jpg';

		self::$attachment_1 = $factory->attachment->create_upload_object( $file );

		set_post_thumbnail( self::$post_1->ID, self::$attachment_1 );
		set_post_thumbnail( $post_2, $factory->attachment->create_upload_object( $file ) );
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
				'author'  => self::$post_1->post_author,
			)
		);
		$xml = simplexml_load_string( ob_get_clean() );

		$this->assertNotEmpty( $xml->channel->item->title );
		$this->assertEquals( self::$post_1->ID, (int) $xml->channel->item[0]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_1, (int) $xml->channel->item[1]->children( 'wp', true )->post_id );

		// Test that the post and attachment by the other author are not included by asserting that there are only two items.
		$this->assertCount( 2, $xml->channel->item );
	}
}
