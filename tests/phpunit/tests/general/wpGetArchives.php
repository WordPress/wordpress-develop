<?php

/**
 * @group general
 * @group template
 * @covers ::wp_get_archives
 */
class Tests_General_wpGetArchives extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		wp_cache_delete( 'last_changed', 'posts' );
	}

	/**
	 * @ticket 23206
	 */
	public function test_get_archives_cache() {
		global $wpdb;

		self::factory()->post->create_many( 3, array( 'post_type' => 'post' ) );
		wp_cache_delete( 'last_changed', 'posts' );
		$this->assertFalse( wp_cache_get( 'last_changed', 'posts' ) );

		$num_queries = $wpdb->num_queries;

		// Cache is not primed, expect 1 query.
		$result = wp_get_archives(
			array(
				'type' => 'monthly',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$time1 = wp_cache_get( 'last_changed', 'posts' );
		$this->assertNotEmpty( $time1 );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Cache is primed, expect no queries.
		$result = wp_get_archives(
			array(
				'type' => 'monthly',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		// Change args, resulting in a different query string. Cache is not primed, expect 1 query.
		$result = wp_get_archives(
			array(
				'type'  => 'monthly',
				'echo'  => false,
				'order' => 'ASC',
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Cache is primed, expect no queries.
		$result = wp_get_archives(
			array(
				'type'  => 'monthly',
				'echo'  => false,
				'order' => 'ASC',
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Change type. Cache is not primed, expect 1 query.
		$result = wp_get_archives(
			array(
				'type' => 'yearly',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Cache is primed, expect no queries.
		$result = wp_get_archives(
			array(
				'type' => 'yearly',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		// Change type. Cache is not primed, expect 1 query.
		$result = wp_get_archives(
			array(
				'type' => 'daily',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Cache is primed, expect no queries.
		$result = wp_get_archives(
			array(
				'type' => 'daily',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		// Change type. Cache is not primed, expect 1 query.
		$result = wp_get_archives(
			array(
				'type' => 'weekly',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Cache is primed, expect no queries.
		$result = wp_get_archives(
			array(
				'type' => 'weekly',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		// Change type. Cache is not primed, expect 1 query.
		$result = wp_get_archives(
			array(
				'type' => 'postbypost',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Cache is primed, expect no queries.
		$result = wp_get_archives(
			array(
				'type' => 'postbypost',
				'echo' => false,
			)
		);
		$this->assertIsString( $result );
		$this->assertSame( $time1, wp_cache_get( 'last_changed', 'posts' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 34058
	 */
	function test_wp_get_archives_result_object() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		// Create multiple posts spread to different post data
		$p1 = wp_insert_post(
			array(
				'post_date'   => '2021-04-11 13:19:06',
				'post_title'  => 'Post sample 1',
				'post_status' => 'publish',
			)
		);

		$p2 = wp_insert_post(
			array(
				'post_date'   => '2021-04-11 19:44:42',
				'post_title'  => 'Post sample 2',
				'post_status' => 'publish',
			)
		);

		$p3 = wp_insert_post(
			array(
				'post_date'   => '2020-09-21 11:01:44',
				'post_title'  => 'Post sample 6',
				'post_status' => 'publish',
			)
		);

		// Default. 'type' = 'monthly', 'order' = 'DESC'
		$results1 = wp_get_archives_result_object();

		$exp1             = new stdClass;
		$exp1->url        = get_site_url() . '/2021/04/';
		$exp1->label      = 'April 2021';
		$exp1->post_count = '2';
		$exp1->year       = '2021';
		$exp1->month      = '4';

		$exp2             = new stdClass;
		$exp2->url        = get_site_url() . '/2020/09/';
		$exp2->label      = 'September 2020';
		$exp2->post_count = '1';
		$exp2->year       = '2020';
		$exp2->month      = '9';

		$this->assertEquals( array( $exp1, $exp2 ), $results1 );

		// 'type' = 'yearly', order = 'DESC'
		$results2 = wp_get_archives_result_object( array( 'type' => 'yearly' ) );

		$res2exp1             = new stdClass;
		$res2exp1->url        = get_site_url() . '/2021/';
		$res2exp1->label      = '2021';
		$res2exp1->post_count = '2';
		$res2exp1->year       = '2021';

		$res2exp2             = new stdClass;
		$res2exp2->url        = get_site_url() . '/2020/';
		$res2exp2->label      = '2020';
		$res2exp2->post_count = '1';
		$res2exp2->year       = '2020';

		$this->assertEquals( array( $res2exp1, $res2exp2 ), $results2 );

		// 'type' = 'daily', order = 'DESC'
		$results3 = wp_get_archives_result_object( array( 'type' => 'daily' ) );

		$res3exp1             = new stdClass;
		$res3exp1->url        = get_site_url() . '/2021/04/11/';
		$res3exp1->label      = 'April 11, 2021';
		$res3exp1->post_count = '2';
		$res3exp1->year       = '2021';
		$res3exp1->month      = '4';
		$res3exp1->day        = '11';

		$res3exp2             = new stdClass;
		$res3exp2->url        = get_site_url() . '/2020/09/21/';
		$res3exp2->label      = 'September 21, 2020';
		$res3exp2->post_count = '1';
		$res3exp2->year       = '2020';
		$res3exp2->month      = '9';
		$res3exp2->day        = '21';

		$this->assertEquals( array( $res3exp1, $res3exp2 ), $results3 );

		// 'type' = 'weekly', order = 'DESC'
		$results4 = wp_get_archives_result_object( array( 'type' => 'weekly' ) );

		$res4exp1             = new stdClass;
		$res4exp1->url        = add_query_arg(
			array(
				'm' => '2021',
				'w' => '14',
			),
			get_site_url() . '/'
		);
		$res4exp1->label      = 'April 5, 2021&#8211;April 11, 2021';
		$res4exp1->post_count = '2';
		$res4exp1->year       = '2021';
		$res4exp1->week       = '14';
		$res4exp1->week_start = 'April 5, 2021';
		$res4exp1->week_end   = 'April 11, 2021';

		$res4exp2             = new stdClass;
		$res4exp2->url        = add_query_arg(
			array(
				'm' => '2020',
				'w' => '39',
			),
			get_site_url() . '/'
		);
		$res4exp2->label      = 'September 21, 2020&#8211;September 27, 2020';
		$res4exp2->post_count = '1';
		$res4exp2->year       = '2020';
		$res4exp2->week       = '39';
		$res4exp2->week_start = 'September 21, 2020';
		$res4exp2->week_end   = 'September 27, 2020';

		$this->assertEquals( array( $res4exp1, $res4exp2 ), $results4 );

		// 'type' = 'postbypost', order = 'DESC'
		$results5 = wp_get_archives_result_object( array( 'type' => 'postbypost' ) );

		$res5exp1        = new stdClass;
		$res5exp1->url   = get_permalink( $p1 );
		$res5exp1->label = 'Post sample 1';

		$res5exp2        = new stdClass;
		$res5exp2->url   = get_permalink( $p2 );
		$res5exp2->label = 'Post sample 2';

		$res5exp3        = new stdClass;
		$res5exp3->url   = get_permalink( $p3 );
		$res5exp3->label = 'Post sample 6';

		$this->assertEquals( array( $res5exp2, $res5exp1, $res5exp3 ), $results5 );
	}

	/**
	 * @ticket 34058
	 */
	function test_wp_get_archives_result() {
		self::factory()->post->create_many( 3, array( 'post_type' => 'post' ) );
		$results1 = wp_get_archives_result();

		$this->assertEquals( 1, count( $results1['results'] ) );
		$this->assertEquals(
			array(
			'type'      => 'monthly',
			'limit'     => '',
			'order'     => 'DESC',
			'post_type' => 'post',
			),
			$results1['parsed_args']
		);

		$results2 = wp_get_archives_result( array( 'type' => 'postbypost' ) );
		$this->assertEquals( 3, count( $results2['results'] ) );
		$this->assertEquals( 'postbypost', $results2['parsed_args']['type'] );
	}

	/**
	 * @ticket 34058
	 */
	function test_wp_get_archives_result_incorrect_usage() {
		$this->setExpectedIncorrectUsage( 'wp_get_archives_result' );

		wp_get_archives_result( array( 'type' => 'anything' ) );
	}
}
