<?php

/**
 * Tests for get_the_posts_pagination() and the_posts_pagination().
 *
 * @group link
 * @group pagination
 * @covers ::get_the_posts_pagination
 * @covers ::the_posts_pagination
 */
class Tests_Link_GetThePostsPagination extends WP_UnitTestCase {

	/**
	 * Set up test fixtures before class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create_many( 3 );
	}

	/**
	 * Tests that get_the_posts_pagination() returns an empty string when total pages <= 1.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_posts_pagination_empty_when_single_page() {
		global $wp_query, $paged;

		$paged    = 1;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		$this->assertSame( '', get_the_posts_pagination() );
	}

	/**
	 * Tests that get_the_posts_pagination() returns pagination markup when multiple pages exist.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_posts_pagination_returns_markup() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$pagination = get_the_posts_pagination();

		$this->assertStringContainsString( 'class="navigation pagination"', $pagination );
		$this->assertStringContainsString( 'aria-label="Posts pagination"', $pagination );
		$this->assertStringContainsString( 'class="nav-links"', $pagination );
		$this->assertStringContainsString( 'aria-current="page"', $pagination );
		$this->assertStringContainsString( 'class="prev page-numbers"', $pagination );
		$this->assertStringContainsString( 'class="next page-numbers"', $pagination );
		$this->assertStringContainsString( 'paged=3', $pagination );
	}

	/**
	 * Tests get_the_posts_pagination() with custom arguments.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_posts_pagination_custom_args() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$pagination = get_the_posts_pagination(
			array(
				'screen_reader_text' => 'Custom Posts Navigation',
				'aria_label'         => 'Custom Posts ARIA Label',
				'class'              => 'custom-pagination-class',
				'prev_text'          => 'Go Back',
				'next_text'          => 'Go Forward',
			)
		);

		$this->assertStringContainsString( 'class="navigation custom-pagination-class"', $pagination );
		$this->assertStringContainsString( 'aria-label="Custom Posts ARIA Label"', $pagination );
		$this->assertStringContainsString( 'Custom Posts Navigation', $pagination );
		$this->assertStringContainsString( 'Go Back', $pagination );
		$this->assertStringContainsString( 'Go Forward', $pagination );
	}

	/**
	 * Tests that aria_label falls back to screen_reader_text when aria_label is omitted.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_posts_pagination_aria_label_fallback() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$pagination = get_the_posts_pagination(
			array(
				'screen_reader_text' => 'Fallback Screen Reader Text',
				'aria_label'         => '',
			)
		);

		$this->assertStringContainsString( 'aria-label="Fallback Screen Reader Text"', $pagination );
	}

	/**
	 * Tests the 'the_posts_pagination_args' filter.
	 *
	 * @ticket 65819
	 */
	public function test_the_posts_pagination_args_filter() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$filter = static function ( $args ) {
			$args['class'] = 'filtered-pagination-class';
			return $args;
		};

		add_filter( 'the_posts_pagination_args', $filter );
		$pagination = get_the_posts_pagination();
		remove_filter( 'the_posts_pagination_args', $filter );

		$this->assertStringContainsString( 'filtered-pagination-class', $pagination );
	}

	/**
	 * Tests that the_posts_pagination() echoes the pagination markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_posts_pagination_echoes() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$expected = get_the_posts_pagination();

		$this->expectOutputString( $expected );
		the_posts_pagination();
	}
}
