<?php

/**
 * @group post
 * @group query
 *
 * @covers ::get_posts
 */
class Tests_Post_GetPosts extends WP_UnitTestCase {


	private $split_the_query_result = null;

	public function tesTests_Post_GetPostst_offset_should_be_null_by_default() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		$found = get_posts(
			array(
				'numberposts' => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
			)
		);

		$this->assertSame( array( $p1 ), $found );
	}

	public function test_offset_0_should_be_respected() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		$found = get_posts(
			array(
				'numberposts' => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
				'offset'      => 0,
			)
		);

		$this->assertSame( array( $p1 ), $found );
	}

	public function test_offset_non_0_should_be_respected() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		$found = get_posts(
			array(
				'numberposts' => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
				'offset'      => 1,
			)
		);

		$this->assertSame( array( $p2 ), $found );
	}

	/**
	 * @ticket 34060
	 */
	public function test_paged_should_not_be_overridden_by_default_offset() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		$found = get_posts(
			array(
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'paged'          => 2,
				'posts_per_page' => 1,
			)
		);

		$this->assertSame( array( $p2 ), $found );
	}

	public function test_explicit_offset_0_should_override_paged() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		$found = get_posts(
			array(
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'paged'          => 2,
				'posts_per_page' => 1,
				'offset'         => 0,
			)
		);

		$this->assertSame( array( $p1 ), $found );
	}

	public function test_explicit_offset_non_0_should_override_paged() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);
		$p3 = self::factory()->post->create(
			array(
				'post_date' => '2013-04-04 04:04:04',
			)
		);

		$found = get_posts(
			array(
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'paged'          => 2,
				'posts_per_page' => 1,
				'offset'         => 2,
			)
		);

		$this->assertSame( array( $p3 ), $found );
	}

	/**
	 * Test case to ensure that the split the query is false when posts_per_page is = 1
	 *
	 * @ticket 57416
	 */
	public function test_one_post_per_page_no_split_query() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		add_filter( 'split_the_query', array( $this, 'filter_split_the_query' ) );

		$found = get_posts(
			array(
				'posts_per_page' => 1,
			)
		);

		remove_filter( 'split_the_query', array( $this, 'filter_split_the_query' ) );

		$this->assertEquals( false, $this->split_the_query_result );
	}

	/**
	 * Test case to ensure that the split the query is true when posts_per_page is > 1
	 *
	 * @ticket 57416
	 */
	public function test_two_posts_per_page_split_query() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		add_filter( 'split_the_query', array( $this, 'filter_split_the_query' ) );

		$found = get_posts(
			array(
				'posts_per_page' => 2,
			)
		);

		remove_filter( 'split_the_query', array( $this, 'filter_split_the_query' ) );

		$this->assertEquals( true, $this->split_the_query_result );
	}

	/**
	 * @ticket 57416
	 */
	public function filter_split_the_query( $split_the_query_default ) {
		$this->split_the_query_result = $split_the_query_default;  // Store the value into a private var so that it can be asserted.
		return $split_the_query_default;
	}

	/**
	 * Test that the split_query filter is called.
	 *
	 * @ticket 57416
	 */
	public function test_filter_split_query_is_called() {
		$p1 = self::factory()->post->create(
			array(
				'post_date' => '2015-04-04 04:04:04',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_date' => '2014-04-04 04:04:04',
			)
		);

		$action = new MockAction();

		add_filter( 'split_the_query', array( $action, 'filter' ) );

		$found = get_posts(
			array(
				'posts_per_page' => 2,
			)
		);

		$this->assertSame( 1, $action->get_call_count() );

		remove_filter( 'split_the_query', array( $action, 'filter' ) );
	}
}
