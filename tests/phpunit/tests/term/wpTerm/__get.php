<?php

require_once __DIR__ . '/base.php';

/**
 * @group term
 * @group taxonomy
 *
 * @covers WP_Term::__get
 */
class Tests_Term_WpTerm__Get extends WP_Term_UnitTestCase {

	/**
	 * @dataProvider data_should_get_dynamic_property
	 * @ticket 61890
	 *
	 * @param string $name     Dynamic property name to get.
	 * @param array  $args     Arguments to set up the test.
	 * @param mixed  $expected Expected value.
	 */
	public function test_should_get_dynamic_property( $name, $args, $expected ) {
		$term = self::factory()->term->create_and_get( $args['term_args'] );

		if ( array_key_exists( 'value', $args ) ) {
			$this->set_actual_dynamic_property_value( $term, $name, $args['value'] );
		}

		$actual = $term->$name;
		if ( 'data' === $name ) {
			$expected['term_id']          = $term->term_id;
			$expected['term_taxonomy_id'] = $term->term_taxonomy_id;
			$this->assertInstanceOf( 'stdClass', $actual, "'data' dynamic property should return a stdClass" );
			$actual = (array) $actual;
		}

		$this->assertSame( $expected, $actual, "$name dynamic property's value should match expected" );
		$this->assertArrayHasKey( $name, $this->get_actual_dynamic_property_value( $term ), "$name should be added into WP_Term::\$dynamic_properties array" );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_get_dynamic_property() {
		$term_args = self::get_term_args();

		return array(
			'unknown'                => array(
				'name'     => 'unknown',
				'args'     => array(
					'term_args' => $term_args,
				),
				'expected' => null,
			),
			'unknown previously set' => array(
				'name'     => 'unknown_previously_set',
				'args'     => array(
					'term_args' => $term_args,
					'value'     => 'unknown dynamic property',
				),
				'expected' => 'unknown dynamic property',
			),

			// Used by wp_update_term().
			'data'                   => array(
				'name'     => 'data',
				'args'     => array(
					'term_args' => $term_args,
				),
				'expected' => array(
					'term_id'          => 0, // Auto-populated in the test.
					'name'             => $term_args['name'],
					'slug'             => $term_args['slug'],
					'term_group'       => 0,
					'term_taxonomy_id' => 0, // Auto-populated in the test.
					'taxonomy'         => self::$taxonomy,
					'description'      => $term_args['description'],
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
			),

			// Added by WP_REST_Menus_Controller::get_term().
			'auto_add'               => array(
				'name'     => 'auto_add',
				'args'     => array(
					'term_args' => $term_args,
					'value'     => true,
				),
				'expected' => true,
			),
		);
	}

	/**
	 * @ticket 61890
	 */
	public function test_should_get_dynamic_property_added_by_get_terms() {
		// Set up the term such that get_terms() will add the 'object_id' dynamic property.
		$term    = self::factory()->term->create_and_get( self::get_term_args() );
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array( $term->term_id ), self::$taxonomy );
		$args   = array(
			'taxonomy'   => self::$taxonomy,
			'object_ids' => array( $post_id ),
			'fields'     => 'all_with_object_id',
		);
		$actual = get_terms( $args )[0];

		$this->assertSame( $post_id, $actual->object_id, "'object_id' dynamic property's value should match expected" );
		$this->assertArrayHasKey( 'object_id', $this->get_actual_dynamic_property_value( $actual ), "'object_id' should be added into WP_Term::\$dynamic_properties array" );
	}

	/**
	 * @ticket 61890
	 */
	public function test_should_get_dynamic_properties_added_by_wp_tag_cloud_dynamic() {
		$tag_id  = self::factory()->tag->create();
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array( $tag_id ), 'post_tag' );

		$actual_tag = null;
		$callback   = static function ( $tags ) use ( &$actual_tag ) {
			$actual_tag = $tags[0];
			return $tags;
		};

		add_filter( 'tag_cloud_sort', $callback );

		wp_tag_cloud( array( 'echo' => false ) );

		$this->assertInstanceOf( WP_Term::class, $actual_tag );

		foreach ( array( 'id', 'link' ) as $name ) {
			$expected = 'id' === $name
				? $tag_id
				: get_term_link( $tag_id, 'post_tag' );
			$this->assertSame( $expected, $actual_tag->$name, "$name dynamic property's value should match expected" );
			$this->assertArrayHasKey( $name, $this->get_actual_dynamic_property_value( $actual_tag ), "$name should be added into WP_Term::\$dynamic_properties array" );
		}
	}

	/**
	 * @ticket 61890
	 */
	public function test_should_get_dynamic_property_added_by__make_cat_compat() {
		$category = self::factory()->category->create_and_get( $this->get_term_args() );
		$expected = $category;
		_make_cat_compat( $category );

		$dynamic_properties = array(
			'cat_ID',
			'cat_name',
			'category_nicename',
			'category_description',
			'category_parent',
			'category_count',
		);
		foreach ( $dynamic_properties as $name ) {
			$this->assertSame( $expected->$name, $category->$name, "'$name' dynamic property's value should match expected" );
			$this->assertArrayHasKey( $name, $this->get_actual_dynamic_property_value( $category ), "'$name' should be added into WP_Term::\$dynamic_properties array" );
		}
	}
}
