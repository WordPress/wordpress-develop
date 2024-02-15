<?php

/**
 * @group export
 * @group attachments
 * @ticket 17379
 */
class Test_Export_Includes_Attachments extends WP_UnitTestCase {
	protected static $post_1;
	protected static $post_2;
	protected static $page_1;
	protected static $page_2;
	protected static $attachment_1;
	protected static $attachment_2;
	protected static $attachment_3;
	protected static $attachment_4;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_1 = $factory->post->create_and_get(
			array(
				'post_title'  => 'Test Post 1',
				'post_type'   => 'post',
				'post_author' => $factory->user->create( array( 'role' => 'editor' ) ),
			)
		);

		self::$post_2 = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Test Post 2',
				'post_type'   => 'post',
				'post_author' => $factory->user->create( array( 'role' => 'editor' ) ),
			)
		);

		self::$page_1 = $factory->post->create_and_get(
			array(
				'post_title'  => 'Test Page 1',
				'post_type'   => 'page',
				'post_author' => $factory->user->create( array( 'role' => 'editor' ) ),
			)
		);

		self::$page_2 = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Test Page 2',
				'post_type'   => 'page',
				'post_author' => $factory->user->create( array( 'role' => 'editor' ) ),
			)
		);

		$file = DIR_TESTDATA . '/images/test-image.jpg';

		self::$attachment_1 = $factory->attachment->create_upload_object( $file, self::$post_1->ID );
		self::$attachment_2 = $factory->attachment->create_upload_object( $file, self::$post_2->ID );
		self::$attachment_3 = $factory->attachment->create_upload_object( $file, self::$page_1->ID );
		self::$attachment_4 = $factory->attachment->create_upload_object( $file, self::$page_2->ID );

		set_post_thumbnail( self::$post_1->ID, self::$attachment_1 );
		set_post_thumbnail( self::$post_2->ID, self::$attachment_2 );
		set_post_thumbnail( self::$page_1->ID, self::$attachment_3 );
		set_post_thumbnail( self::$page_2->ID, self::$attachment_4 );
	}

	/**
	 * Tests the export function to ensure that attachments are included when exporting all content.
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
	public function test_export_includes_attachments_all_content() {
		require_once ABSPATH . 'wp-admin/includes/export.php';

		ob_start();
		export_wp( array( 'content' => 'all' ) );
		$xml = simplexml_load_string( ob_get_clean() );

		$this->assertNotEmpty( $xml->channel->item->title );
		$this->assertEquals( self::$post_1->ID, (int) $xml->channel->item[0]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$post_2->ID, (int) $xml->channel->item[1]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$page_1->ID, (int) $xml->channel->item[2]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$page_2->ID, (int) $xml->channel->item[3]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_1, (int) $xml->channel->item[4]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_2, (int) $xml->channel->item[5]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_3, (int) $xml->channel->item[6]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_4, (int) $xml->channel->item[7]->children( 'wp', true )->post_id );
		$this->assertCount( 8, $xml->channel->item ); // Expect 4 items: 2 pages, 2 posts and 4 attachments
	}

	/**
	 * Tests the export function to ensure that attachments are included when exporting all posts.
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
	public function test_export_includes_attachments_for_all_posts() {
		require_once ABSPATH . 'wp-admin/includes/export.php';

		ob_start();
		export_wp( array( 'content' => 'post' ) );
		$xml = simplexml_load_string( ob_get_clean() );

		$this->assertNotEmpty( $xml->channel->item->title );
		$this->assertEquals( self::$post_1->ID, (int) $xml->channel->item[0]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$post_2->ID, (int) $xml->channel->item[1]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_1, (int) $xml->channel->item[2]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_2, (int) $xml->channel->item[3]->children( 'wp', true )->post_id );
		$this->assertCount( 4, $xml->channel->item ); // Expect 4 items: 2 posts and 2 attachments
	}

	/**
	 * Tests the export function to ensure that attachments are included when exporting all pages.
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
	public function test_export_includes_attachments_for_all_pages() {
		require_once ABSPATH . 'wp-admin/includes/export.php';

		ob_start();
		export_wp( array( 'content' => 'page' ) );
		$xml = simplexml_load_string( ob_get_clean() );

		$this->assertNotEmpty( $xml->channel->item->title );
		$this->assertEquals( self::$page_1->ID, (int) $xml->channel->item[0]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$page_2->ID, (int) $xml->channel->item[1]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_3, (int) $xml->channel->item[2]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_4, (int) $xml->channel->item[3]->children( 'wp', true )->post_id );
		$this->assertCount( 4, $xml->channel->item ); // Expect 4 items: 2 pages and 2 attachments
	}

	/**
	 * Tests the export function to ensure that attachments are included when exporting pages by a specific author.
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
	public function test_export_includes_attachments_for_specific_author_pages() {
		require_once ABSPATH . 'wp-admin/includes/export.php';

		ob_start();
		export_wp( array( 'content' => 'page', 'author' => self::$page_1->post_author ) );
		$xml = simplexml_load_string( ob_get_clean() );

		$this->assertNotEmpty( $xml->channel->item->title );
		$this->assertEquals( self::$page_1->ID, (int) $xml->channel->item[0]->children( 'wp', true )->post_id );
		$this->assertEquals( self::$attachment_3, (int) $xml->channel->item[1]->children( 'wp', true )->post_id );
		$this->assertCount( 2, $xml->channel->item ); // Expect 2 items: 1 page and 1 attachment
	}

	/**
	 * Tests the export function to ensure that attachments are included when exporting posts by a specific author.
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
	public function test_export_includes_attachments_for_specific_author_posts() {
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
		$this->assertCount( 2, $xml->channel->item );  // Expect 2 items: 1 post and 1 attachment
	}
}
