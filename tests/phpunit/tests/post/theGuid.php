<?php

/**
 * Tests for the_guid().
 *
 * @group post
 *
 * @covers ::the_guid
 */
class Tests_Post_TheGuid extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test post guid.
	 *
	 * @var string
	 */
	protected static $guid = 'https://example.org/?p=42';

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'guid' => self::$guid,
			)
		);
	}

	/**
	 * Tears down test state after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Tests that the_guid() displays the guid when given a post ID.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_with_post_id() {
		$this->expectOutputString( self::$guid );
		the_guid( self::$post_id );
	}

	/**
	 * Tests that the_guid() displays the guid when given a WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_with_post_object() {
		$post = get_post( self::$post_id );

		$this->expectOutputString( self::$guid );
		the_guid( $post );
	}

	/**
	 * Tests that the_guid() displays the guid from global $post when no argument is passed.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_fallback_to_global_post() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->expectOutputString( self::$guid );
		the_guid();
	}

	/**
	 * Tests that the_guid() outputs an empty string when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_with_non_existent_post() {
		$this->expectOutputString( '' );
		the_guid( 999999 );
	}

	/**
	 * Tests that the_guid() outputs an empty string when called with 0 and no global post exists.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_with_zero_and_no_global_post() {
		unset( $GLOBALS['post'] );

		$this->expectOutputString( '' );
		the_guid( 0 );
	}

	/**
	 * Tests that the_guid() outputs an empty string when a post has an empty guid.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_with_empty_guid() {
		global $wpdb;

		$post_id = self::factory()->post->create();
		$wpdb->update(
			$wpdb->posts,
			array( 'guid' => '' ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$this->expectOutputString( '' );
		the_guid( $post_id );
	}

	/**
	 * Tests that the 'the_guid' filter hook modifies output and receives expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_filter_receives_arguments() {
		$filter = new MockAction();
		add_filter( 'the_guid', array( $filter, 'filter' ), 10, 2 );

		$this->expectOutputString( self::$guid );
		the_guid( self::$post_id );

		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( self::$guid, $filter_args[0][0] );
		$this->assertSame( self::$post_id, $filter_args[0][1] );
	}

	/**
	 * Tests that the 'the_guid' filter modifies the displayed output.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_filter_modifies_output() {
		$custom_callback = static function ( $guid, $post_id ) {
			return 'urn:uuid:12345-' . $post_id;
		};

		add_filter( 'the_guid', $custom_callback, 10, 2 );

		$this->expectOutputString( 'urn:uuid:12345-' . self::$post_id );
		the_guid( self::$post_id );

		remove_filter( 'the_guid', $custom_callback, 10 );
	}

	/**
	 * Tests that the_guid() respects the 'get_the_guid' filter.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_respects_get_the_guid_filter() {
		$custom_callback = static function ( $guid, $post_id ) {
			return 'https://custom.guid.org/?id=' . $post_id;
		};

		add_filter( 'get_the_guid', $custom_callback, 10, 2 );

		$this->expectOutputString( 'https://custom.guid.org/?id=' . self::$post_id );
		the_guid( self::$post_id );

		remove_filter( 'get_the_guid', $custom_callback, 10 );
	}

	/**
	 * Tests that the_guid() displays the guid for an attachment.
	 *
	 * @ticket 65819
	 */
	public function test_the_guid_with_attachment() {
		$attachment_guid = 'https://example.org/wp-content/uploads/2026/10/test.jpg';
		$attachment_id   = self::factory()->attachment->create_object(
			'test.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'guid'           => $attachment_guid,
			)
		);

		$this->expectOutputString( $attachment_guid );
		the_guid( $attachment_id );
	}
}
