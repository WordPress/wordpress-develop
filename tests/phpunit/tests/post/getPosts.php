<?php

/**
 * @group post
 * @group query
 */
class Tests_Post_GetPosts extends WP_UnitTestCase {
	public function test_offset_should_be_null_by_default() {
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
	 * Verifies that get_posts() accepts a query string for the `$args` parameter.
	 *
	 * @ticket 64813
	 */
	public function test_should_accept_query_string_args(): void {
		self::factory()->post->create();
		$second_post_id = self::factory()->post->create();
		$found_post_ids = get_posts( 'numberposts=1&fields=ids' );

		$this->assertSame( array( $second_post_id ), $found_post_ids );
	}
}
