<?php

/**
 * @group comment
 * @group privacy
 *
 * @covers ::wp_comments_personal_data_exporter
 */
class Tests_Comment_wpCommentsPersonalDataExporter extends WP_UnitTestCase {

	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Testing the `wp_comments_personal_data_exporter()` function.
	 *
	 * @ticket 43440
	 */
	public function test_wp_comments_personal_data_exporter() {
		$args = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_agent'        => 'SOME_AGENT',
			'comment_date'         => '2018-03-28 20:05:00',
			'comment_content'      => 'Comment',
		);

		$comment_id = self::factory()->comment->create( $args );

		$actual   = wp_comments_personal_data_exporter( $args['comment_author_email'] );
		$expected = $args;

		$this->assertTrue( $actual['done'] );

		// Number of exported comments.
		$this->assertCount( 1, $actual['data'] );

		// Number of exported comment properties.
		$this->assertCount( 8, $actual['data'][0]['data'] );

		// Exported group.
		$this->assertSame( 'comments', $actual['data'][0]['group_id'] );
		$this->assertSame( 'Comments', $actual['data'][0]['group_label'] );

		// Exported comment properties.
		$this->assertSame( $expected['comment_author'], $actual['data'][0]['data'][0]['value'] );
		$this->assertSame( $expected['comment_author_email'], $actual['data'][0]['data'][1]['value'] );
		$this->assertSame( $expected['comment_author_url'], $actual['data'][0]['data'][2]['value'] );
		$this->assertSame( $expected['comment_author_IP'], $actual['data'][0]['data'][3]['value'] );
		$this->assertSame( $expected['comment_agent'], $actual['data'][0]['data'][4]['value'] );
		$this->assertSame( $expected['comment_date'], $actual['data'][0]['data'][5]['value'] );
		$this->assertSame( $expected['comment_content'], $actual['data'][0]['data'][6]['value'] );
		$this->assertSame( esc_html( get_comment_link( $comment_id ) ), strip_tags( $actual['data'][0]['data'][7]['value'] ) );
	}

	/**
	 * Testing the `wp_comments_personal_data_exporter()` function for no comments found.
	 *
	 * @ticket 43440
	 */
	public function test_wp_comments_personal_data_exporter_no_comments_found() {

		$actual = wp_comments_personal_data_exporter( 'nocommentsfound@local.host' );

		$expected = array(
			'data' => array(),
			'done' => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_comments_personal_data_exporter()` function for an empty comment property.
	 *
	 * @ticket 43440
	 */
	public function test_wp_comments_personal_data_exporter_empty_comment_prop() {
		$args = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-03-28 20:05:00',
			'comment_agent'        => '',
			'comment_content'      => 'Comment',
		);

		$c = self::factory()->comment->create( $args );

		$actual = wp_comments_personal_data_exporter( $args['comment_author_email'] );

		$this->assertTrue( $actual['done'] );

		// Number of exported comments.
		$this->assertCount( 1, $actual['data'] );

		// Number of exported comment properties.
		$this->assertCount( 7, $actual['data'][0]['data'] );
	}

	/**
	 * Testing the `wp_comments_personal_data_exporter()` function with an empty second page.
	 *
	 * @ticket 43440
	 */
	public function test_wp_comments_personal_data_exporter_empty_second_page() {
		$args = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-03-28 20:05:00',
			'comment_agent'        => 'SOME_AGENT',
			'comment_content'      => 'Comment',
		);

		$c = self::factory()->comment->create( $args );

		$actual = wp_comments_personal_data_exporter( $args['comment_author_email'], 2 );

		$this->assertTrue( $actual['done'] );

		// Number of exported comments.
		$this->assertCount( 0, $actual['data'] );
	}

	/**
	 * Testing that `wp_comments_personal_data_exporter()` orders comments by ID.
	 *
	 * @ticket 57700
	 */
	public function test_wp_comments_personal_data_exporter_orders_comments_by_id() {

		$args = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-03-28 20:05:00',
			'comment_agent'        => 'SOME_AGENT',
			'comment_content'      => 'Comment',
		);
		self::factory()->comment->create( $args );

		$filter = new MockAction();
		add_filter( 'comments_clauses', array( &$filter, 'filter' ) );

		wp_comments_personal_data_exporter( $args['comment_author_email'] );

		$clauses = $filter->get_args()[0][0];

		$this->assertStringContainsString( 'comment_ID', $clauses['orderby'] );
	}
}
