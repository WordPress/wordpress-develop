<?php

/**
 * @group post
 */
class Tests_Post_GetLastPostDate extends WP_UnitTestCase {

	/**
	 * @ticket 47777
	 */
	public function test_get_lastpostdate() {
		$post_post_date_first = '2020-01-30 16:09:28';
		$post_post_date_last  = '2020-02-28 16:09:28';

		$book_post_date_first = '2019-03-30 18:11:30';
		$book_post_date_last  = '2019-04-30 18:11:30';

		// Register book post type.
		register_post_type( 'book', array( 'has_archive' => true ) );

		// Create a simple post.
		$simple_post_id_first = self::factory()->post->create(
			array(
				'post_title' => 'Simple Post First',
				'post_type'  => 'post',
				'post_date'  => $post_post_date_first,
			)
		);

		$simple_post_id_last = self::factory()->post->create(
			array(
				'post_title' => 'Simple Post Last',
				'post_type'  => 'post',
				'post_date'  => $post_post_date_last,
			)
		);

		// Create custom type post.
		$book_cpt_id_first = self::factory()->post->create(
			array(
				'post_title' => 'Book CPT First',
				'post_type'  => 'book',
				'post_date'  => $book_post_date_first,
			)
		);

		$book_cpt_id_last = self::factory()->post->create(
			array(
				'post_title' => 'Book CPT Last',
				'post_type'  => 'book',
				'post_date'  => $book_post_date_last,
			)
		);

		$this->assertSame( $post_post_date_last, get_lastpostdate( 'blog', 'post' ) );
		$this->assertSame( $book_post_date_last, get_lastpostdate( 'blog', 'book' ) );
	}

	/**
	 * @ticket 63614
	 */
	public function test_pre_get_lastpostdate_filter_short_circuits_query() {
		global $wpdb;

		self::factory()->post->create(
			array(
				'post_date' => '2020-02-28 16:09:28',
			)
		);

		$filter = new MockAction();
		add_filter( 'pre_get_lastpostdate', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'pre_get_lastpostdate',
			static function () {
				return '2001-01-01 00:00:00';
			}
		);

		$num_queries = $wpdb->num_queries;
		$result      = get_lastpostdate( 'blog', 'post' );

		$this->assertSame( '2001-01-01 00:00:00', $result, 'The filtered value should be returned.' );
		$this->assertSame( $num_queries, $wpdb->num_queries, 'No database queries should be run.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The filter should be called once.' );
		$this->assertSame( array( false, 'blog', 'post' ), $filter->get_args()[0], 'The filter should receive the default value, timezone, and post type.' );
	}

	/**
	 * @ticket 63614
	 */
	public function test_pre_get_lastpostdate_filter_returning_false_does_not_short_circuit() {
		$post_date = '2020-02-28 16:09:28';

		self::factory()->post->create(
			array(
				'post_date' => $post_date,
			)
		);

		add_filter( 'pre_get_lastpostdate', '__return_false' );

		$this->assertSame( $post_date, get_lastpostdate( 'blog', 'post' ) );
	}
}
