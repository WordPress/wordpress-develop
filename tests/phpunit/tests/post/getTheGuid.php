<?php

/**
 * Tests for get_the_guid().
 *
 * @group post
 *
 * @covers ::get_the_guid
 */
class Tests_Post_GetTheGuid extends WP_UnitTestCase {

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
	 * Tests that get_the_guid() returns the guid for a post.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_guid() {
		$this->assertSame( self::$guid, get_the_guid( self::$post_id ) );
	}

	/**
	 * Tests that get_the_guid() returns an empty string when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_guid_returns_empty_string_for_non_existent_post() {
		$this->assertSame( '', get_the_guid( 999999 ) );
	}

	/**
	 * Tests that the 'get_the_guid' filter receives expected arguments and modifies the return value.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_guid_filter() {
		add_filter(
			'get_the_guid',
			function ( $guid, $id ) {
				$this->assertSame( self::$guid, $guid );
				$this->assertSame( self::$post_id, $id );
				return 'urn:uuid:12345-' . $id;
			},
			10,
			2
		);

		$this->assertSame( 'urn:uuid:12345-' . self::$post_id, get_the_guid( self::$post_id ) );
	}
}
