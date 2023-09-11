<?php

/**
 * @group cache
 */
class Test_Update_Post_Term_Caches extends WP_UnitTestCase {

	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	public static $posts;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$posts = $factory->post->create_many( 3 );
	}


	/**
	 * @covers ::update_post_term_caches
	 * @dataProvider data_post_term_caches
	 */
	public function test_post_term_caches( $post_type ) {
		$posts = array_map( 'get_post', self::$posts );

		$filter = new MockAction();
		add_filter( 'wp_get_object_terms_args', array( $filter, 'filter' ), 10, 3 );

		update_post_term_caches( $posts, $post_type );

		$args         = $filter->get_args();
		$first        = reset( $args );
		$taxes_values = array_pop( $first );
		$post_ids     = array_pop( $first );

		$taxes = get_object_taxonomies( 'post', 'names' );
		$this->assertSameSets( $taxes, $taxes_values, 'Check to see the taxonomies to be primed matched all the taxonomies register to post' );
		$this->assertSameSets( $post_ids, self::$posts, 'Check to see post ids match' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_post_term_caches() {
		return array(
			'any'                    => array(
				'post_type' => 'any',
			),
			'null'                   => array(
				'post_type' => null,
			),
			'false'                  => array(
				'post_type' => false,
			),
			'empty string'           => array(
				'post_type' => '',
			),
			'post'                   => array(
				'post_type' => 'post',
			),
			'post array'             => array(
				'post_type' => array( 'post' ),
			),
			'post and invalid array' => array(
				'post_type' => array( 'post', 'invalid' ),
			),
		);
	}
}
