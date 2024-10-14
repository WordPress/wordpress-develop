<?php

/**
 * Tests to make sure querying posts based on various date parameters using "date_query" works as expected.
 *
 * @ticket 18694
 *
 * @group query
 * @group date
 * @group datequery
 *
 * @covers WP_Query
 */
class Tests_Query_DateQuery extends WP_UnitTestCase {

	public $q;

	public function set_up() {
		parent::set_up();
		unset( $this->q );
		$this->q = new WP_Query();
	}

	public function _get_query_result( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_status'            => 'any', // For the future post.
				'posts_per_page'         => '-1',  // To make sure results are accurate.
				'orderby'                => 'ID',  // Same order they were created.
				'order'                  => 'ASC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $this->q->query( $args );
	}

	public function test_date_query_before_array() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2007-09-24 07:17:23' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2008-03-29 07:17:23' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2008-07-15 07:17:23' ) );
		$p4 = self::factory()->post->create( array( 'post_date' => '2009-06-11 07:17:23' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'before' => array(
							'year'  => 2008,
							'month' => 6,
						),
					),
				),
			)
		);

		$this->assertSameSets( array( $p1, $p2 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Specifically tests to make sure values are defaulting to
	 * their minimum values when being used with "before".
	 */
	public function test_date_query_before_array_test_defaulting() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2007-09-24 07:17:23' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2008-03-29 07:17:23' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'before' => array(
							'year' => 2008,
						),
					),
				),
			)
		);

		$this->assertSameSets( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_before_string() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2007-09-24 07:17:23' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2008-03-29 07:17:23' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2008-07-15 07:17:23' ) );
		$p4 = self::factory()->post->create( array( 'post_date' => '2009-06-11 07:17:23' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'before' => 'May 4th, 2008',
					),
				),
			)
		);

		$this->assertSame( array( $p1, $p2 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_after_array() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2009-10-18 10:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2009-12-18 10:42:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2010-06-11 07:17:23' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'after' => array(
							'year'  => 2009,
							'month' => 12,
							'day'   => 31,
						),
					),
				),
			)
		);

		$this->assertSameSets( array( $p3 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Specifically tests to make sure values are defaulting to
	 * their maximum values when being used with "after".
	 */
	public function test_date_query_after_array_test_defaulting() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2008-12-18 10:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2009-01-18 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'after' => array(
							'year' => 2008,
						),
					),
				),
			)
		);

		$this->assertSame( array( $p2 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_after_string() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2009-12-18 09:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2009-12-18 10:42:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2009-12-19 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'after' => '2009-12-18 10:42:29',
					),
				),
			)
		);

		$this->assertSame( array( $p3 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_after_string_inclusive() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2009-12-18 09:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2009-12-18 10:42:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2009-12-19 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'after'     => '2009-12-18 10:42:29',
						'inclusive' => true,
					),
				),
			)
		);

		$this->assertSame( array( $p2, $p3 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * @ticket 26653
	 */
	public function test_date_query_inclusive_between_dates() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2006-12-18 09:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2007-01-18 10:42:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2007-12-19 10:42:29' ) );
		$p4 = self::factory()->post->create( array( 'post_date' => '2008-12-19 10:42:29' ) );
		$p5 = self::factory()->post->create( array( 'post_date' => '2009-12-19 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					'after'     => array(
						'year'  => 2007,
						'month' => 1,
					),
					'before'    => array(
						'year'  => 2008,
						'month' => 12,
					),
					'inclusive' => true,
				),
			)
		);

		$this->assertSame( array( $p2, $p3, $p4 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_Y() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 13:00:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2007-05-07 13:00:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before' => '2008',
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after' => '2007',
				),
			)
		);

		$this->assertSame( array( $p2 ), $before_posts );
		$this->assertSame( array( $p1 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_Y_inclusive() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 13:00:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2007-05-07 13:00:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before'    => '2008',
					'inclusive' => true,
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after'     => '2007',
					'inclusive' => true,
				),
			)
		);

		$this->assertSameSets( array( $p1, $p2 ), $before_posts );
		$this->assertSameSets( array( $p1, $p2 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_Ym() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 13:00:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-04-07 13:00:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before' => '2008-05',
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after' => '2008-04',
				),
			)
		);

		$this->assertSame( array( $p2 ), $before_posts );
		$this->assertSame( array( $p1 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_Ym_inclusive() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 13:00:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-04-07 13:00:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before'    => '2008-05',
					'inclusive' => true,
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after'     => '2008-04',
					'inclusive' => true,
				),
			)
		);

		$this->assertSameSets( array( $p1, $p2 ), $before_posts );
		$this->assertSameSets( array( $p1, $p2 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_Ymd() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 13:00:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-05 13:00:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before' => '2008-05-06',
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after' => '2008-05-05',
				),
			)
		);

		$this->assertSame( array( $p2 ), $before_posts );
		$this->assertSame( array( $p1 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_Ymd_inclusive() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 13:00:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-05 13:00:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before'    => '2008-05-06',
					'inclusive' => true,
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after'     => '2008-05-05',
					'inclusive' => true,
				),
			)
		);

		$this->assertSameSets( array( $p1, $p2 ), $before_posts );
		$this->assertSameSets( array( $p1, $p2 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_YmdHi() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:05:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:04:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before' => '2008-05-06 14:05',
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after' => '2008-05-06 14:04',
				),
			)
		);

		$this->assertSame( array( $p2 ), $before_posts );
		$this->assertSame( array( $p1 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_YmdHi_inclusive() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:05:00',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:04:00',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before'    => '2008-05-06 14:05',
					'inclusive' => true,
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after'     => '2008-05-06 14:04',
					'inclusive' => true,
				),
			)
		);

		$this->assertSameSets( array( $p1, $p2 ), $before_posts );
		$this->assertSameSets( array( $p1, $p2 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_YmdHis() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:05:15',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:05:14',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before' => '2008-05-06 14:05:15',
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after' => '2008-05-06 14:05:14',
				),
			)
		);

		$this->assertSame( array( $p2 ), $before_posts );
		$this->assertSame( array( $p1 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_YmdHis_inclusive() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:04:15',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:04:14',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before'    => '2008-05-06 14:04:15',
					'inclusive' => true,
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after'     => '2008-05-06 14:04:14',
					'inclusive' => true,
				),
			)
		);

		$this->assertSameSets( array( $p1, $p2 ), $before_posts );
		$this->assertSameSets( array( $p1, $p2 ), $after_posts );
	}

	/**
	 * @ticket 29908
	 */
	public function test_beforeafter_with_date_string_non_parseable() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:05:15',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2008-05-06 14:05:14',
			)
		);

		$before_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'before' => 'June 12, 2008',
				),
			)
		);

		$after_posts = $this->_get_query_result(
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					'after' => 'June 12, 2007',
				),
			)
		);

		$this->assertSame( array( $p1, $p2 ), $before_posts );
	}

	public function test_date_query_year() {
		$p1    = self::factory()->post->create( array( 'post_date' => '2009-12-19 10:42:29' ) );
		$p2    = self::factory()->post->create( array( 'post_date' => '2010-12-19 10:42:29' ) );
		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'year' => 2009,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_month() {
		$p1    = self::factory()->post->create( array( 'post_date' => '2009-12-19 10:42:29' ) );
		$p2    = self::factory()->post->create( array( 'post_date' => '2010-11-19 10:42:29' ) );
		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'month' => 12,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_week() {
		$p1    = self::factory()->post->create( array( 'post_date' => '2009-01-02 10:42:29' ) );
		$p2    = self::factory()->post->create( array( 'post_date' => '2010-03-19 10:42:29' ) );
		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'week' => 1,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_day() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2009-01-17 10:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2009-01-18 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'day' => 17,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_dayofweek() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2014-10-21 10:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2014-10-20 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'dayofweek' => 3,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * @ticket 28063
	 */
	public function test_date_query_dayofweek_iso() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2014-10-31 10:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2014-10-30 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'dayofweek_iso' => 5,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_hour() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2014-10-21 13:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2014-10-21 12:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'hour' => 13,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * @ticket 34228
	 */
	public function test_date_query_hour_should_not_ignore_0() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2014-10-21 00:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2014-10-21 01:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'year'     => 2014,
				'monthnum' => 10,
				'day'      => 21,
				'hour'     => 0,
				'minute'   => 42,
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_minute() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2014-10-21 10:56:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2014-10-21 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'minute' => 56,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_second() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2014-10-21 10:42:21' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2014-10-21 10:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'second' => 21,
					),
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_between_two_times() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2005-12-18 08:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2006-12-18 09:00:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2007-12-18 10:42:29' ) );
		$p4 = self::factory()->post->create( array( 'post_date' => '2008-12-18 17:00:29' ) );
		$p5 = self::factory()->post->create( array( 'post_date' => '2009-12-18 18:42:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'hour'    => 9,
						'minute'  => 0,
						'compare' => '>=',
					),
					array(
						'hour'    => '17',
						'minute'  => '0',
						'compare' => '<=',
					),
				),
			)
		);

		$this->assertSameSets( array( $p2, $p3, $p4 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_relation_or() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2006-12-18 14:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2007-01-18 10:42:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2007-12-19 10:34:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'hour' => 14,
					),
					array(
						'minute' => 34,
					),
					'relation' => 'OR',
				),
			)
		);

		$this->assertSame( array( $p1, $p3 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_query_compare_greater_than_or_equal_to() {
		$p1 = self::factory()->post->create( array( 'post_date' => '2006-12-18 13:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2007-01-18 14:34:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2007-12-19 14:37:29' ) );
		$p4 = self::factory()->post->create( array( 'post_date' => '2007-12-19 15:34:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					array(
						'hour'   => 14,
						'minute' => 34,
					),
					'compare' => '>=',
				),
			)
		);

		$this->assertSame( array( $p2, $p3, $p4 ), wp_list_pluck( $posts, 'ID' ) );
	}

	public function test_date_params_monthnum_m_duplicate() {
		global $wpdb;

		$p1 = self::factory()->post->create( array( 'post_date' => '2006-05-18 13:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2007-09-18 14:34:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2007-01-18 14:34:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					'month'    => 5,
					'monthnum' => 9,
				),
			)
		);

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );

		$this->assertStringContainsString( "MONTH( $wpdb->posts.post_date ) = 5", $this->q->request );
		$this->assertStringNotContainsString( "MONTH( $wpdb->posts.post_date ) = 9", $this->q->request );
	}

	public function test_date_params_week_w_duplicate() {
		global $wpdb;

		$p1 = self::factory()->post->create( array( 'post_date' => '2014-10-01 13:42:29' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2014-10-22 14:34:29' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2014-10-15 14:34:29' ) );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					'week' => 43,
					'w'    => 42,
				),
			)
		);

		$this->assertSame( array( $p2 ), wp_list_pluck( $posts, 'ID' ) );

		$this->assertStringContainsString( "WEEK( $wpdb->posts.post_date, 1 ) = 43", $this->q->request );
		$this->assertStringNotContainsString( "WEEK( $wpdb->posts.post_date, 1 ) = 42", $this->q->request );
	}

	/**
	 * @ticket 25775
	 */
	public function test_date_query_with_taxonomy_join() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2013-04-27 01:01:01',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2013-03-21 01:01:01',
			)
		);

		register_taxonomy( 'foo', 'post' );
		wp_set_object_terms( $p1, 'bar', 'foo' );

		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					'year' => 2013,
				),
				'tax_query'  => array(
					array(
						'taxonomy' => 'foo',
						'terms'    => array( 'bar' ),
						'field'    => 'name',
					),
				),
			)
		);

		_unregister_taxonomy( 'foo' );

		$this->assertSame( array( $p1 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * @ticket 29822
	 */
	public function test_date_query_one_nested_query() {
		$p1    = self::factory()->post->create( array( 'post_date' => '2004-10-01 13:42:29' ) );
		$p2    = self::factory()->post->create( array( 'post_date' => '2004-01-22 14:34:29' ) );
		$p3    = self::factory()->post->create( array( 'post_date' => '1984-10-15 14:34:29' ) );
		$p4    = self::factory()->post->create( array( 'post_date' => '1985-10-15 14:34:29' ) );
		$posts = $this->_get_query_result(
			array(
				'date_query' => array(
					'relation' => 'OR',
					array(
						'relation' => 'AND',
						array(
							'year' => 2004,
						),
						array(
							'month' => 1,
						),
					),
					array(
						'year' => 1984,
					),
				),
			)
		);

		$this->assertSame( array( $p2, $p3 ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * @ticket 29822
	 */
	public function test_date_query_one_nested_query_multiple_columns_relation_and() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2012-03-05 15:30:55',
			)
		);
		$this->update_post_modified( $p1, '2014-11-03 14:43:00' );

		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2012-05-05 15:30:55',
			)
		);
		$this->update_post_modified( $p2, '2014-10-03 14:43:00' );

		$p3 = self::factory()->post->create(
			array(
				'post_date' => '2013-05-05 15:30:55',
			)
		);
		$this->update_post_modified( $p3, '2014-10-03 14:43:00' );

		$p4 = self::factory()->post->create(
			array(
				'post_date' => '2012-02-05 15:30:55',
			)
		);
		$this->update_post_modified( $p4, '2012-12-03 14:43:00' );

		$q = new WP_Query(
			array(
				'date_query'             => array(
					'relation' => 'AND',
					array(
						'column' => 'post_date',
						array(
							'year' => 2012,
						),
					),
					array(
						'column' => 'post_modified',
						array(
							'year' => 2014,
						),
					),
				),
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'post_status'            => 'publish',
			)
		);

		$expected = array( $p1, $p2 );

		$this->assertSameSets( $expected, $q->posts );
	}

	/**
	 * @ticket 29822
	 */
	public function test_date_query_nested_query_multiple_columns_mixed_relations() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2012-03-05 15:30:55',
			)
		);
		$this->update_post_modified( $p1, '2014-11-03 14:43:00' );

		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2012-05-05 15:30:55',
			)
		);
		$this->update_post_modified( $p2, '2014-10-03 14:43:00' );

		$p3 = self::factory()->post->create(
			array(
				'post_date' => '2013-05-05 15:30:55',
			)
		);
		$this->update_post_modified( $p3, '2014-10-03 14:43:00' );

		$p4 = self::factory()->post->create(
			array(
				'post_date' => '2012-02-05 15:30:55',
			)
		);
		$this->update_post_modified( $p4, '2012-12-03 14:43:00' );

		$p5 = self::factory()->post->create(
			array(
				'post_date' => '2014-02-05 15:30:55',
			)
		);
		$this->update_post_modified( $p5, '2013-12-03 14:43:00' );

		$q = new WP_Query(
			array(
				'date_query'             => array(
					'relation' => 'OR',
					array(
						'relation' => 'AND',
						array(
							'column' => 'post_date',
							array(
								'day' => 05,
							),
						),
						array(
							'column' => 'post_date',
							array(
								'before' => array(
									'year'  => 2012,
									'month' => 4,
								),
							),
						),
					),
					array(
						'column' => 'post_modified',
						array(
							'month' => 12,
						),
					),
				),
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'post_status'            => 'publish',
			)
		);

		$expected = array( $p1, $p4, $p5 );
		$this->assertSameSets( $expected, $q->posts );
	}
}
