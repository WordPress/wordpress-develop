<?php

/**
 * Tests to make sure querying posts results in the correct query_vars.
 *
 * @group query
 * @group pwcc
 */
class Tests_Query_QueryVars extends WP_UnitTestCase {

	/**
	 * Ensure the data provider includes a test for each query var.
	 */
	public function test_data_provider() {
		// An none-empty argument is required to get WP_Query to populate query_vars.
		$query = new WP_Query( array( 'p' => 1 ) );

		$wp_query_vars = array_keys( $query->query_vars );

		// `error` is a special case.
		$wp_query_vars = array_diff( $wp_query_vars, array( 'error' ) );

		// Generate a list of query vars being tested.
		$data_provider_tests = array_map(
			function ( $args ) {
				return $args[0];
			},
			$this->data_query_vars()
		);

		// Remove duplicates from the data provider.
		$data_provider_tests = array_unique( $data_provider_tests );

		$this->assertSameSets( $wp_query_vars, $data_provider_tests );
	}

	/**
	 * Test that query vars are set correctly when passed using array format arguments.
	 *
	 * @dataProvider data_query_vars
	 *
	 * @param string $query_var Query var to test.
	 * @param mixed  $value     Value to test.
	 * @param string $qv_tested Query var to test. Optional, defaults to $query_var.
	 * @param mixed  $expected_value Expected value. Optional, defaults to $value.
	 */
	public function test_query_vars_array_format( $query_var, $value, $qv_tested = null, $expected_value = null ) {
		if ( null === $expected_value ) {
			$expected_value = $value;
		}
		if ( null === $qv_tested ) {
			$qv_tested = $query_var;
		}
		$query = new WP_Query( array( $query_var => $value ) );

		$this->assertEquals( $expected_value, $query->get( $qv_tested ), 'Set query var should match passed value.' );
	}

	/**
	 * Test that query vars are set correctly when passed using string format arguments.
	 *
	 * @dataProvider data_query_vars
	 *
	 * @param string $query_var Query var to test.
	 * @param mixed  $value     Value to test.
	 * @param string $qv_tested Query var to test. Optional, defaults to $query_var.
	 * @param mixed  $expected_value Expected value. Optional, defaults to $value.
	 */
	public function test_query_vars_string_format( $query_var, $value, $qv_tested = null, $expected_value = null ) {
		if ( null === $expected_value ) {
			$expected_value = $value;
		}
		if ( null === $qv_tested ) {
			$qv_tested = $query_var;
		}

		if ( is_array( $value ) ) {
			$query_args = '';
			foreach ( $value as $v ) {
				$query_args .= "{$query_var}[]={$v}&";
			}
		} else {
			$query_args = "{$query_var}={$value}";
		}

		$query = new WP_Query( $query_args );

		$this->assertEquals( $expected_value, $query->get( $qv_tested ), 'Set query var should match passed value.' );
	}


	/**
	 * Data provider for all query vars.
	 *
	 * @return array[]
	 */
	public function data_query_vars() {
		return array(
			'm'                               =>
			array(
				'm',
				3,
			),
			'p'                               =>
			array(
				'p',
				3,
			),
			'post_parent'                     =>
			array(
				'post_parent',
				3,
			),
			'subpost'                         =>
			array(
				'subpost',
				'subpost-name',
			),
			'subpost (test attachment)'       =>
			array(
				'subpost',
				'subpost-name',
				'attachment',
			),

			'subpost_id'                      =>
			array(
				'subpost_id',
				3,
			),
			'subpost_id (test attachment_id)' =>
			array(
				'subpost_id',
				3,
				'attachment_id',
			),
			'attachment'                      =>
			array(
				'attachment',
				'attachment-name',
			),
			'attachment_id'                   =>
			array(
				'attachment_id',
				3,
			),
			'name'                            =>
			array(
				'name',
				'post-name',
			),
			'pagename'                        =>
			array(
				'pagename',
				'page-name',
			),
			'page_id'                         =>
			array(
				'page_id',
				3,
			),
			'second'                          =>
			array(
				'second',
				3,
			),
			'minute'                          =>
			array(
				'minute',
				3,
			),
			'hour'                            =>
			array(
				'hour',
				3,
			),
			'day'                             =>
			array(
				'day',
				3,
			),
			'monthnum'                        =>
			array(
				'monthnum',
				3,
			),
			'year'                            =>
			array(
				'year',
				2023,
			),
			'w'                               =>
			array(
				'w',
				3,
			),
			'category_name'                   =>
			array(
				'category_name',
				'category-name',
			),
			'tag'                             =>
			array(
				'tag',
				'tag-slug',
			),
			'cat'                             =>
			array(
				'cat',
				3,
			),
			'tag_id'                          =>
			array(
				'tag_id',
				3,
			),
			'author'                          =>
			array(
				'author',
				3,
			),
			'author_name'                     =>
			array(
				'author_name',
				'author-name',
			),
			'feed'                            =>
			array(
				'feed',
				true,
			),
			'tb'                              =>
			array(
				'tb',
				true,
			),
			'paged'                           =>
			array(
				'paged',
				3,
			),
			'meta_key'                        =>
			array(
				'meta_key',
				'meta-key',
			),
			'meta_value'                      =>
			array(
				'meta_value',
				'meta-value',
			),
			'preview'                         =>
			array(
				'preview',
				true,
			),
			's'                               =>
			array(
				's',
				'search term',
			),
			'sentence'                        =>
			array(
				'sentence',
				true,
			),
			'title'                           =>
			array(
				'title',
				'Post Title',
			),
			'fields'                          =>
			array(
				'fields',
				'all',
			),
			'menu_order'                      =>
			array(
				'menu_order',
				3,
			),
			'embed'                           =>
			array(
				'embed',
				true,
			),
			'category__in'                    =>
			array(
				'category__in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'category__not_in'                =>
			array(
				'category__not_in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'category__and'                   =>
			array(
				'category__and',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'post__in'                        =>
			array(
				'post__in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'post__not_in'                    =>
			array(
				'post__not_in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'post_name__in'                   =>
			array(
				'post_name__in',
				array(
					'post-name-1',
					'post-name-2',
					2 => 'post-name-3',
				),
			),
			'tag__in'                         =>
			array(
				'tag__in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'tag__not_in'                     =>
			array(
				'tag__not_in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'tag__and'                        =>
			array(
				'tag__and',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'tag_slug__in'                    =>
			array(
				'tag_slug__in',
				array(
					'tag-slug-1',
					'tag-slug-2',
					2 => 'tag-slug-3',
				),
			),
			'tag_slug__and'                   =>
			array(
				'tag_slug__and',
				array(
					'tag-slug-1',
					'tag-slug-2',
					2 => 'tag-slug-3',
				),
			),
			'post_parent__in'                 =>
			array(
				'post_parent__in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'post_parent__not_in'             =>
			array(
				'post_parent__not_in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'author__in'                      =>
			array(
				'author__in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'author__not_in'                  =>
			array(
				'author__not_in',
				array(
					1,
					2,
					2 => 3,
				),
			),
			'search_columns'                  =>
			array(
				'search_columns',
				array(
					'post_title',
					'post_content',
				),
			),
			'ignore_sticky_posts'             =>
			array(
				'ignore_sticky_posts',
				true,
			),
			'suppress_filters'                =>
			array(
				'suppress_filters',
				true,
			),
			'cache_results'                   =>
			array(
				'cache_results',
				false,
			),
			'update_post_term_cache'          =>
			array(
				'update_post_term_cache',
				false,
			),
			'update_menu_item_cache'          =>
			array(
				'update_menu_item_cache',
				false,
			),
			'lazy_load_term_meta'             =>
			array(
				'lazy_load_term_meta',
				true,
			),
			'update_post_meta_cache'          =>
			array(
				'update_post_meta_cache',
				false,
			),
			'post_type'                       =>
			array(
				'post_type',
				'page',
			),
			'posts_per_page (5)'              =>
			array(
				'posts_per_page',
				5,
			),
			'posts_per_page (-1)'             =>
			array(
				'posts_per_page',
				-1,
			),
			'posts_per_page (0)'              =>
			array(
				'posts_per_page',
				0,
				null,
				10, // since r27456
			),
			'posts_per_page ("")'             =>
			array(
				'posts_per_page',
				'',
				null,
				10,
			),
			'nopaging'                        =>
			array(
				'nopaging',
				true,
			),
			'comments_per_page'               =>
			array(
				'comments_per_page',
				5,
			),
			'no_found_rows'                   =>
			array(
				'no_found_rows',
				true,
			),
			'order'                           =>
			array(
				'order',
				'ASC',
			),
		);
	}
}
