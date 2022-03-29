<?php

/**
 * @group functions.php
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
}
