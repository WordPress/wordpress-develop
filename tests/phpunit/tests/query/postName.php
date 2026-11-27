<?php

/**
 * Tests for the post slug clauses of WP_Query.
 *
 * @group query
 *
 * @covers WP_Query::get_posts
 * @covers WP_Query::parse_orderby
 */
class Tests_Query_PostName extends WP_UnitTestCase {

	/**
	 * Published post, which an unescaped slug condition would also return.
	 *
	 * @var int
	 */
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_name'   => 'existing-post',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * A slug that a `sanitize_title` callback changes is still escaped in the SQL.
	 *
	 * @dataProvider data_slug_query_vars
	 *
	 * @param array $query_vars Query vars with a slug that the filter replaces.
	 */
	public function test_slug_is_escaped_after_sanitize_title_filter( array $query_vars ) {
		global $wpdb;

		add_filter(
			'sanitize_title',
			static function ( $title, $raw_title, $context ) {
				return 'query' === $context ? "nothing') OR ('1'='1" : $title;
			},
			10,
			3
		);

		$query = new WP_Query( $query_vars + array( 'fields' => 'ids' ) );

		$this->assertSame( '', $wpdb->last_error, 'The query should be valid SQL.' );
		$this->assertSame( array(), $query->posts, 'No post should match the filtered slug.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function data_slug_query_vars() {
		return array(
			'name'                         => array( array( 'name' => 'anything' ) ),
			'attachment'                   => array(
				array(
					'attachment' => 'anything',
					'post_type'  => 'any',
				),
			),
			'post_name__in'                => array( array( 'post_name__in' => array( 'anything' ) ) ),
			'post_name__in, ordered by it' => array(
				array(
					'post_name__in' => array( 'anything', 'existing-post' ),
					'orderby'       => 'post_name__in',
				),
			),
		);
	}

	/**
	 * A slug with percent-encoded characters reaches the `posts_where` filter
	 * unchanged, without placeholder escapes.
	 */
	public function test_percent_encoded_slug_is_unchanged_in_posts_where() {
		$slug    = sanitize_title( '中文测试' );
		$post_id = self::factory()->post->create(
			array(
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);

		$where = '';
		add_filter(
			'posts_where',
			static function ( $clause ) use ( &$where ) {
				$where = $clause;
				return $clause;
			}
		);

		$query = new WP_Query(
			array(
				'name'   => $slug,
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $post_id ), $query->posts, 'The post should be found by its slug.' );
		$this->assertStringContainsString( "post_name = '{$slug}'", $where, 'The slug should reach the filter unchanged.' );
	}
}
