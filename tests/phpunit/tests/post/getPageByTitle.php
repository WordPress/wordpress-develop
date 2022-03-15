<?php


/**
 * @group post
 *
 * @covers ::get_page_by_title
 */
class Tests_Post_GetPageByTitle extends WP_UnitTestCase {
	/**
	 * @ticket 36905
	 */
	public function test_get_page_by_title() {
		$page_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);
		$page    = get_page_by_title( 'some Page' );
		$this->assertEquals( $page_id, $page->ID );
	}

	/**
	 * @ticket 36905
	 */
	public function test_get_page_by_title_second_call_uses_cache() {

		$page_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);

		$page = get_page_by_title( 'some Page' );
		$this->assertEquals( $page_id, $page->ID );

		$num_queries = get_num_queries();

		$page = get_page_by_title( 'some Page' );
		$this->assertEquals( $page_id, $page->ID );

		$this->assertSame( $num_queries, get_num_queries() );
	}
	/**
	 * @ticket 36905
	 */
	public function test_get_page_by_title_miss() {
		$page_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);
		$page    = get_page_by_title( 'xxx' );
		$this->assertNull( $page );
	}

	/**
	 * @ticket 36905
	 */
	public function test_get_page_by_title_miss_second_call_uses_cache() {

		$page_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);
		$bad_title = 'xxx';
		$page = get_page_by_title( $bad_title );
		$this->assertNull( $page );

		$num_queries = get_num_queries();

		$page = get_page_by_title( $bad_title );
		$this->assertNull( $page );

		$this->assertSame( $num_queries, get_num_queries() );
	}
	/**
	 * @ticket 36905
	 */
	public function test_should_be_case_insensitive() {
		$page_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);
		$page    = get_page_by_title( 'soMe paGE' );
		$this->assertEquals( $page_id, $page->ID );
	}

	/**
	 * @ticket 36905
	 */
	public function test_should_match_exact_title() {
		$page_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);

		$page = get_page_by_title( 'Some Page Yeah' );
		$this->assertNull( $page );

		$page = get_page_by_title( 'Yeah Some Page' );
		$this->assertNull( $page );
	}

	/**
	 * @ticket 36905
	 */
	public function test_should_return_one_result_if_some_posts_has_the_same_title() {
		$post_id_1 = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Title',
				'post_type'  => 'page',
			)
		);
		$post_id_2 = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Title',
				'post_type'  => 'page',
			)
		);
		$post      = get_page_by_title( 'Some Title' );
		$this->assertEquals( $post_id_1, $post->ID );
	}

	/**
	 * @ticket 36905
	 */
	public function test_should_obey_post_type() {
		register_post_type( 'my-cpt' );
		$post_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some CPT',
				'post_type'  => 'my-cpt',
			)
		);

		$post = get_page_by_title( 'Some CPT', OBJECT, 'my-cpt' );
		$this->assertEquals( $post_id, $post->ID );

		$post = get_page_by_title( 'Some CPT', OBJECT, 'page' );
		$this->assertNull( $post );
	}

	/**
	 * @ticket 36905
	 */
	public function test_should_get_different_post_types() {
		register_post_type( 'my-cpt' );
		$post_id_1 = self::factory()->post->create_object(
			array(
				'post_title' => 'Some CPT',
				'post_type'  => 'my-cpt',
			)
		);
		$post_id_2 = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);
		$post      = get_page_by_title( 'Some Page', OBJECT, array( 'my-cpt', 'page' ) );
		$this->assertEquals( $post_id_2, $post->ID );

		$post      = get_page_by_title( 'Some CPT', OBJECT, array( 'my-cpt', 'page' ) );
		$this->assertEquals( $post_id_1, $post->ID );
	}

	/**
	 * @ticket 36905
	 */
	public function test_should_get_different_post_statuses() {
		register_post_type( 'my-cpt' );

		$post_statuses = get_post_stati();
		foreach ( $post_statuses as $post_status ) {
			$title   = sprintf( 'Some %s post', $post_status );
			$post_id = self::factory()->post->create_object(
				array(
					'post_title'  => $title,
					'post_type'   => 'page',
					'post_status' => $post_status,
				)
			);
			$found   = get_page_by_title( $title );
			$this->assertEquals( $post_id, $found->ID );
		}
	}

	/**
	 * @ticket 36905
	 */
	public function test_output_param_should_be_obeyed() {
		$post_id = self::factory()->post->create_object(
			array(
				'post_title' => 'Some Page',
				'post_type'  => 'page',
			)
		);

		$found = get_page_by_title( 'Some Page' );
		self::assertIsObject( $found );
		$this->assertSame( $post_id, $found->ID );

		$found = get_page_by_title( 'Some Page', OBJECT );
		self::assertIsObject( $found );
		$this->assertSame( $post_id, $found->ID );

		$found = get_page_by_title( 'Some Page', ARRAY_N );
		self::assertIsArray( $found );
		$this->assertSame( $post_id, $found[0] );

		$found = get_page_by_title( 'Some Page', ARRAY_A );
		self::assertIsArray( $found );
		$this->assertSame( $post_id, $found['ID'] );
	}
}
