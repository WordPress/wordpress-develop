<?php

/**
 * @group query
 */
class Tests_Query_Args_Php_8x_Compat extends WP_UnitTestCase {
	/**
	 * @ticket 60745
	 * @dataProvider invalid_query_arg_data_provider
	 */
	public function test_feed_query_arg_with_array_value( $query_args ) {
		$post  = self::factory()->post->create_and_get();
		$query = new WP_Query( $query_args );
		$posts = $query->get_posts();
		$this->assertSame( 1, count( $posts ) );
	}

	public function invalid_query_arg_data_provider() {
		return array(
			'scalar_query_arg_is_array' => array(
				array(
					'attachment'      => array(),
					'attachment_id'   => array(),
					'author'          => array(),
					'author_name'     => array(),
					'cat'             => array(),
					'day'             => array(),
					'embed'           => array(),
					'error'           => array(),
					'favicon'         => array(),
					'feed'            => array(),
					'hour'            => array(),
					'm'               => array(),
					'menu_order'      => array(),
					'minute'          => array(),
					'monthnum'        => array(),
					'name'            => array(),
					'p'               => array(),
					'page'            => array(),
					'page_id'         => array(),
					'paged'           => array(),
					'pagename'        => array(),
					'post_status'     => array(),
					'post_type'       => array(),
					'preview'         => array(),
					'robots'          => array(),
					's'               => array(),
					'second'          => array(),
					'subpost'         => array(),
					'subpost_id'      => array(),
					'tb'              => array(),
					'title'           => array(),
					'w'               => array(),
					'withcomments'    => array(),
					'withoutcomments' => array(),
					'year'            => array(),
				),
			),
			'query_array_arg_is_string' => array(
				array(
					'author__in'          => 'string',
					'author__not_in'      => 'string',
					'category__and'       => 'string',
					'category__in'        => 'string',
					'category__not_in'    => 'string',
					'post__in'            => 'string',
					'post__not_in'        => 'string',
					'post_name__in'       => 'string',
					'post_parent__in'     => 'string',
					'post_parent__not_in' => 'string',
					'tag__and'            => 'string',
					'tag__in'             => 'string',
					'tag__not_in'         => 'string',
					'tag_slug__and'       => 'string',
					'tag_slug__in'        => 'string',
				),
			),
		);
	}
}
