<?php

/**
 * @group canonical
 */
class Tests_Canonical_Feeds extends WP_Canonical_UnitTestCase {
	public $structure = '/%postname%/';

	public static $posts = array();

	public static function wpSetUpBeforeClass( $factory ) {

		self::$posts[0] = $factory->post->create( array( 'post_name' => 'post0' ) );

		$c1 = self::factory()->tag->create_and_get(
			array(
				'name' => 'Comments',
				'slug' => 'comments',
			)
		);

		$c2 = self::factory()->tag->create_and_get(
			array(
				'name' => 'Test Tag',
				'slug' => 'test',
			)
		);

		wp_set_object_terms( self::$posts[0], array( $c1->slug, $c2->slug ), 'post_tag' );
	}

	/**
	 * @ticket 39535
	 *
	 * @dataProvider data_canonical_feed
	 */
	public function test_canonical_feed( $test_url, $expected, $ticket = 0, $expected_doing_it_wrong = array() ) {
		global $wp;
		if ( $ticket ) {
			$this->knownWPBug( $ticket );
		}

		$this->go_to( $test_url );

		$query_vars = array_diff( $wp->query_vars, $wp->extra_query_vars );

		$this->assertEquals( $expected, $query_vars );
	}

	public function data_canonical_feed() {
		/* Data format:
		 * [0]: Test URL.
		 * [1]: array( expected query vars to be set )
		 * [2]: (optional) The ticket the test refers to, Can be skipped if unknown.
		 */

		return array(
			// posts feed for tag 'comments'
			array(
				'/tag/comments/feed/',
				array(
					'tag'  => 'comments',
					'feed' => 'feed',
				),
				39535,
			),

			// posts feed for tag 'test'
			array(
				'/tag/test/feed/',
				array(
					'tag'  => 'test',
					'feed' => 'feed',
				),
			),

			// comments feed entire site
			array(
				'/comments/feed/',
				array(
					'withcomments' => '1',
					'feed'         => 'feed',
				),
			),

		);
	}
}
