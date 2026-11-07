<?php

/**
 * @group query
 */
class Tests_Query_NoFoundRows extends WP_UnitTestCase {
	public function test_no_found_rows_default() {
		$q = new WP_Query(
			array(
				'post_type' => 'post',
			)
		);

		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
	}

	public function test_no_found_rows_false() {
		$q = new WP_Query(
			array(
				'post_type'     => 'post',
				'no_found_rows' => false,
			)
		);

		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
	}

	public function test_no_found_rows_0() {
		$q = new WP_Query(
			array(
				'post_type'     => 'post',
				'no_found_rows' => 0,
			)
		);

		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
	}

	public function test_no_found_rows_empty_string() {
		$q = new WP_Query(
			array(
				'post_type'     => 'post',
				'no_found_rows' => '',
			)
		);

		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
	}

	public function test_no_found_rows_true() {
		$q = new WP_Query(
			array(
				'post_type'     => 'post',
				'no_found_rows' => true,
			)
		);

		$this->assertStringNotContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
	}

	public function test_no_found_rows_non_bool_cast_to_true() {
		$q = new WP_Query(
			array(
				'post_type'     => 'post',
				'no_found_rows' => 'foo',
			)
		);

		$this->assertStringNotContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
	}

	/**
	 * @ticket 29552
	 */
	public function test_no_found_rows_default_with_nopaging_true() {
		$p = self::factory()->post->create();

		$q = new WP_Query(
			array(
				'post_type' => 'post',
				'nopaging'  => true,
			)
		);

		$this->assertStringNotContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
		$this->assertSame( 1, $q->found_posts );
	}

	/**
	 * @ticket 29552
	 */
	public function test_no_found_rows_default_with_postsperpage_minus1() {
		$p = self::factory()->post->create();

		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
			)
		);

		$this->assertStringNotContainsString( 'SQL_CALC_FOUND_ROWS', $q->request );
		$this->assertSame( 1, $q->found_posts );
	}

	/**
	 * Ensures {@see WP_Query::$found_posts} and {@see WP_Query::$max_num_pages}
	 * are not negative when the {@see 'found_posts'} filter returns a negative count.
	 *
	 * @ticket 65817
	 */
	public function test_found_posts_and_max_num_pages_are_not_negative_when_found_posts_is_filtered_negative() {
		self::factory()->post->create_many( 3 );

		add_filter(
			'found_posts',
			static function (): int {
				return -5;
			}
		);

		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 2,
			)
		);

		$this->assertSame( 0, $q->found_posts, 'Expected found_posts to be clamped to zero.' );
		$this->assertSame( 0, $q->max_num_pages, 'Expected max_num_pages to be clamped to zero.' );
	}

	/**
	 * Ensures the count passed to the {@see 'found_posts'} filter is not negative
	 * when the {@see 'found_posts_query'} filter makes the query return a negative number.
	 *
	 * @ticket 65817
	 */
	public function test_found_posts_is_not_negative_when_found_posts_query_is_filtered_negative() {
		self::factory()->post->create_many( 3 );

		add_filter(
			'found_posts_query',
			static function (): string {
				return 'SELECT -5';
			}
		);

		$filtered_found_posts = null;
		add_filter(
			'found_posts',
			static function ( int $found_posts ) use ( &$filtered_found_posts ): int {
				$filtered_found_posts = $found_posts;
				return $found_posts;
			}
		);

		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 2,
			)
		);

		$this->assertSame( 0, $filtered_found_posts, 'Expected the count passed to the found_posts filter to be clamped to zero.' );
		$this->assertSame( 0, $q->found_posts, 'Expected found_posts to be clamped to zero.' );
		$this->assertSame( 0, $q->max_num_pages, 'Expected max_num_pages to be zero.' );
	}

	/**
	 * Ensures {@see WP_Query::$max_num_pages} is not negative when a
	 * {@see 'post_limits'} filter injects a non-empty `LIMIT` clause on a
	 * `posts_per_page = -1` query, which otherwise never runs the found-rows
	 * calculation and skips the `max_num_pages` division entirely.
	 *
	 * @ticket 65817
	 */
	public function test_max_num_pages_is_not_negative_when_limits_are_filtered_with_postsperpage_minus1() {
		self::factory()->post->create_many( 3 );

		add_filter(
			'post_limits',
			static function (): string {
				return 'LIMIT 0, 2';
			}
		);

		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
			)
		);

		$this->assertIsString( $q->request );
		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $q->request, 'Expected the filtered LIMIT to trigger the found-rows query.' );
		$this->assertSame( 3, $q->found_posts, 'Expected found_posts to reflect all matching rows.' );
		$this->assertSame( 0, $q->max_num_pages, 'Expected max_num_pages to be clamped to zero.' );
	}
}
