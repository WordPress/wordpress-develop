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
	 * @ticket 30631
	 */
	public function test_nopaging_with_paged_returns_no_posts() {
		$cat_id = self::factory()->category->create();
		self::factory()->post->create_many( 5, array( 'category' => $cat_id ) );

		$q = new WP_Query(
			array(
				'cat'            => $cat_id,
				'posts_per_page' => -1,
				'paged'          => 2,
			)
		);

		$this->assertSame( 0, $q->post_count );
		$this->assertSame( 5, $q->found_posts );
		$this->assertSame( 1, $q->max_num_pages );
	}

	/**
	 * @ticket 30631
	 */
	public function test_nopaging_without_paged_returns_all_posts() {
		$cat_id = self::factory()->category->create();
		self::factory()->post->create_many( 5, array( 'category' => $cat_id ) );

		$q = new WP_Query(
			array(
				'cat'            => $cat_id,
				'posts_per_page' => -1,
				'paged'          => 1,
			)
		);

		$this->assertSame( 5, $q->post_count );
		$this->assertSame( 5, $q->found_posts );
		$this->assertSame( 1, $q->max_num_pages );
	}

	/**
	 * @ticket 30631
	 */
	public function test_out_of_bounds_page_returns_no_posts() {
		$cat_id = self::factory()->category->create();
		self::factory()->post->create_many( 5, array( 'category' => $cat_id ) );

		$q = new WP_Query(
			array(
				'cat'            => $cat_id,
				'posts_per_page' => 2,
				'paged'          => 3,
			)
		);

		$this->assertSame( 0, $q->post_count );
		$this->assertSame( 5, $q->found_posts );
		$this->assertSame( 3, $q->max_num_pages );
	}

	/**
	 * @ticket 30631
	 */
	public function test_nopaging_singular_unaffected() {
		$post_id = self::factory()->post->create();

		$q = new WP_Query(
			array(
				'p'              => $post_id,
				'posts_per_page' => -1,
				'paged'          => 2,
			)
		);

		$this->assertSame( 1, $q->post_count );
		$this->assertSame( $post_id, $q->posts[0]->ID );
	}
}
