<?php

/**
 * @ticket 49708
 *
 * @group post
 */
class Tests_Post_GetPostByGuid extends WP_UnitTestCase {

	/** @var \WP_Post */
	protected static $post;

	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ) {
		self::$post = $factory->post->create_and_get(
			[
				'post_title' => 'Test get page by path',
			]
		);
	}

	public function test_should_match_guid() {
		$post = get_post_by_guid( self::$post->guid );

		$this->assertEquals( self::$post->ID, $post->ID );
	}

}
