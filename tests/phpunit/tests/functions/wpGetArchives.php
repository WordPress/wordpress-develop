<?php

/**
 * @group functions
 *
 * @covers ::wp_get_archives
 */
class Tests_Functions_wpGetArchives extends WP_UnitTestCase {
	protected static $post_ids;
	protected $month_url;
	protected $year_url;

	public function set_up() {
		parent::set_up();

		$this->month_url = get_month_link( gmdate( 'Y' ), gmdate( 'm' ) );
		$this->year_url  = get_year_link( gmdate( 'Y' ) );
	}

	public function tear_down() {
		unregister_post_type( 'taco' );

		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many(
			8,
			array(
				'post_type'   => 'post',
				'post_author' => '1',
			)
		);
	}

	public function test_wp_get_archives_default() {
		$expected['default'] = "<li><a href='" . $this->month_url . "'>" . gmdate( 'F Y' ) . '</a></li>';
		$this->assertSame( $expected['default'], trim( wp_get_archives( array( 'echo' => false ) ) ) );
	}

	public function test_wp_get_archives_type() {
		$expected['type'] = "<li><a href='" . $this->year_url . "'>" . gmdate( 'Y' ) . '</a></li>';
		$this->assertSame(
			$expected['type'],
			trim(
				wp_get_archives(
					array(
						'echo' => false,
						'type' => 'yearly',
					)
				)
			)
		);
	}

	public function test_wp_get_archives_limit() {
		$ids = array_slice( array_reverse( self::$post_ids ), 0, 5 );

		$link1 = get_permalink( $ids[0] );
		$link2 = get_permalink( $ids[1] );
		$link3 = get_permalink( $ids[2] );
		$link4 = get_permalink( $ids[3] );
		$link5 = get_permalink( $ids[4] );

		$title1 = get_post( $ids[0] )->post_title;
		$title2 = get_post( $ids[1] )->post_title;
		$title3 = get_post( $ids[2] )->post_title;
		$title4 = get_post( $ids[3] )->post_title;
		$title5 = get_post( $ids[4] )->post_title;

		$expected['limit'] = <<<EOF
<li><a href='$link1'>$title1</a></li>
	<li><a href='$link2'>$title2</a></li>
	<li><a href='$link3'>$title3</a></li>
	<li><a href='$link4'>$title4</a></li>
	<li><a href='$link5'>$title5</a></li>
EOF;
		$this->assertSameIgnoreEOL(
			$expected['limit'],
			trim(
				wp_get_archives(
					array(
						'echo'  => false,
						'type'  => 'postbypost',
						'limit' => 5,
					)
				)
			)
		);
	}

	public function test_wp_get_archives_format() {
		$expected['format'] = "<option value='" . $this->month_url . "'> " . gmdate( 'F Y' ) . ' </option>';
		$this->assertSame(
			$expected['format'],
			trim(
				wp_get_archives(
					array(
						'echo'   => false,
						'format' => 'option',
					)
				)
			)
		);
	}

	public function test_wp_get_archives_before_and_after() {
		$expected['before_and_after'] = "<div><a href='" . $this->month_url . "'>" . gmdate( 'F Y' ) . '</a></div>';
		$this->assertSame(
			$expected['before_and_after'],
			trim(
				wp_get_archives(
					array(
						'echo'   => false,
						'format' => 'custom',
						'before' => '<div>',
						'after'  => '</div>',
					)
				)
			)
		);
	}

	public function test_wp_get_archives_show_post_count() {
		$expected['show_post_count'] = "<li><a href='" . $this->month_url . "'>" . gmdate( 'F Y' ) . '</a>&nbsp;(8)</li>';
		$this->assertSame(
			$expected['show_post_count'],
			trim(
				wp_get_archives(
					array(
						'echo'            => false,
						'show_post_count' => 1,
					)
				)
			)
		);
	}

	public function test_wp_get_archives_echo() {
		$expected['echo'] = "\t<li><a href='" . $this->month_url . "'>" . gmdate( 'F Y' ) . '</a></li>' . "\n";
		$this->expectOutputString( $expected['echo'] );
		wp_get_archives( array( 'echo' => true ) );
	}

	public function test_wp_get_archives_order() {
		self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => '1',
				'post_date'   => '2012-10-23 19:34:42',
			)
		);

		$date_full             = gmdate( 'F Y' );
		$oct_url               = get_month_link( 2012, 10 );
		$expected['order_asc'] = <<<EOF
<li><a href='{$oct_url}'>October 2012</a></li>
	<li><a href='{$this->month_url}'>$date_full</a></li>
EOF;
		$this->assertSameIgnoreEOL(
			$expected['order_asc'],
			trim(
				wp_get_archives(
					array(
						'echo'  => false,
						'order' => 'ASC',
					)
				)
			)
		);

		$expected['order_desc'] = <<<EOF
<li><a href='{$this->month_url}'>$date_full</a></li>
	<li><a href='{$oct_url}'>October 2012</a></li>
EOF;
		$this->assertSameIgnoreEOL(
			$expected['order_desc'],
			trim(
				wp_get_archives(
					array(
						'echo'  => false,
						'order' => 'DESC',
					)
				)
			)
		);
	}

	/**
	 * @ticket 21596
	 */
	public function test_wp_get_archives_post_type() {
		register_post_type( 'taco', array( 'public' => true ) );

		self::factory()->post->create(
			array(
				'post_type'   => 'taco',
				'post_author' => '1',
				'post_date'   => '2014-10-23 19:34:42',
			)
		);

		$oct_url  = esc_url( add_query_arg( 'post_type', 'taco', get_month_link( 2014, 10 ) ) );
		$expected = "<li><a href='{$oct_url}'>October 2014</a></li>";
		$archives = wp_get_archives(
			array(
				'echo'      => false,
				'post_type' => 'taco',
			)
		);
		$this->assertSame( $expected, trim( $archives ) );
	}

	/**
	 * @ticket 64304
	 */
	public function test_wp_get_archives_args_filter() {
		// Test that the filter can modify the limit argument.
		add_filter(
			'wp_get_archives_args',
			static function ( $args ) {
				$args['limit'] = 3;
				return $args;
			}
		);

		$ids = array_slice( array_reverse( self::$post_ids ), 0, 3 );

		$expected = implode(
			'',
			array_map(
				static function ( $id ) {
					return sprintf( "\t<li><a href='%s'>%s</a></li>\n", get_permalink( $id ), get_the_title( $id ) );
				},
				$ids
			)
		);
		$archives = wp_get_archives(
			array(
				'echo'  => false,
				'type'  => 'postbypost',
				'limit' => 5, // This should be overridden by the filter to 3.
			)
		);

		$this->assertEqualHTML( $expected, $archives );
	}

	/**
	 * Tests that every archive link generated by wp_get_archives() leads to
	 * an archive containing exactly the posts from that period.
	 *
	 * Two of the posts share a week number (week 1) in different years,
	 * so a weekly link that does not limit the year would match both.
	 *
	 * @ticket 59027
	 *
	 * @dataProvider data_wp_get_archives_links_resolve_to_period
	 *
	 * @param string  $type     The archive type.
	 * @param int[][] $expected Expected post indexes for each archive link, in ascending order.
	 */
	public function test_wp_get_archives_links_resolve_to_period( $type, $expected ) {
		$post_ids = $this->create_dated_posts();

		$links = $this->get_archive_links(
			array(
				'type'  => $type,
				'order' => 'ASC',
				'limit' => count( $expected ),
			)
		);

		$this->assertCount( count( $expected ), $links, "Unexpected number of $type archive links." );

		foreach ( $links as $i => $url ) {
			$this->go_to( $url );

			$this->assertTrue( is_date(), "$url should be a date archive." );

			$expected_ids = array_map(
				static function ( $index ) use ( $post_ids ) {
					return $post_ids[ $index ];
				},
				$expected[ $i ]
			);
			$actual_ids   = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );

			sort( $expected_ids );
			sort( $actual_ids );

			$this->assertSame( $expected_ids, $actual_ids, "$url should list only the posts from that $type period." );
		}
	}

	/**
	 * Data provider.
	 *
	 * Post indexes refer to the posts created by create_dated_posts().
	 *
	 * @return array[]
	 */
	public function data_wp_get_archives_links_resolve_to_period() {
		return array(
			'yearly'  => array(
				'type'     => 'yearly',
				'expected' => array( array( 0 ), array( 1, 2 ) ),
			),
			'monthly' => array(
				'type'     => 'monthly',
				'expected' => array( array( 0 ), array( 1, 2 ) ),
			),
			'weekly'  => array(
				'type'     => 'weekly',
				'expected' => array( array( 0 ), array( 1, 2 ) ),
			),
			'daily'   => array(
				'type'     => 'daily',
				'expected' => array( array( 0 ), array( 1 ), array( 2 ) ),
			),
		);
	}

	/**
	 * Tests the markup of a weekly archive link.
	 *
	 * @ticket 59027
	 */
	public function test_wp_get_archives_weekly_markup() {
		$this->create_dated_posts();

		$url      = add_query_arg(
			array(
				'year' => 2022,
				'w'    => 1,
			),
			home_url( '/' )
		);
		$expected = "<li><a href='" . esc_url( $url ) . "'>January 3, 2022&#8211;January 9, 2022</a>&nbsp;(1)</li>";

		$archives = wp_get_archives(
			array(
				'echo'            => false,
				'type'            => 'weekly',
				'order'           => 'ASC',
				'limit'           => 1,
				'show_post_count' => true,
			)
		);

		$this->assertSame( $expected, trim( $archives ) );
	}

	/**
	 * Tests that the current week is marked as selected when viewing a weekly archive.
	 *
	 * @ticket 59027
	 */
	public function test_wp_get_archives_weekly_marks_current_week() {
		$this->create_dated_posts();

		$args = array(
			'type'  => 'weekly',
			'order' => 'ASC',
			'limit' => 2,
		);

		$links = $this->get_archive_links( $args );
		$this->go_to( $links[1] );

		$processor = new WP_HTML_Tag_Processor( wp_get_archives( array_merge( $args, array( 'echo' => false ) ) ) );
		$current   = array();
		while ( $processor->next_tag( 'a' ) ) {
			$current[] = $processor->get_attribute( 'aria-current' );
		}

		$this->assertSame( array( null, 'page' ), $current, 'Only the week being viewed should have aria-current="page".' );
	}

	/**
	 * Creates posts on fixed dates.
	 *
	 * 0: Wednesday 2022-01-05, week 1 of 2022.
	 * 1: Wednesday 2023-01-04, week 1 of 2023.
	 * 2: Thursday  2023-01-05, week 1 of 2023, a different day than post 1.
	 *
	 * @return int[] Post IDs.
	 */
	private function create_dated_posts() {
		$post_ids = array();
		foreach ( array( '2022-01-05 10:00:00', '2023-01-04 10:00:00', '2023-01-05 10:00:00' ) as $date ) {
			$post_ids[] = self::factory()->post->create( array( 'post_date' => $date ) );
		}

		return $post_ids;
	}

	/**
	 * Returns the URLs of the links in wp_get_archives() output, in output order.
	 *
	 * @param array $args Arguments for wp_get_archives().
	 * @return string[] Archive URLs.
	 */
	private function get_archive_links( $args ) {
		$processor = new WP_HTML_Tag_Processor( wp_get_archives( array_merge( $args, array( 'echo' => false ) ) ) );
		$links     = array();
		while ( $processor->next_tag( 'a' ) ) {
			$links[] = $processor->get_attribute( 'href' );
		}

		return $links;
	}
}
