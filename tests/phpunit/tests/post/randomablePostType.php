<?php

/**
 * Tests for the `randomable` post type argument.
 *
 * @group post
 * @group rewrite
 * @ticket 64498
 *
 * @covers WP_Post_Type::set_props
 */
class Tests_Post_RandomablePostType extends WP_UnitTestCase {

	public function tear_down() {
		_unregister_post_type( 'wptests_pt' );

		parent::tear_down();
	}

	/**
	 * @dataProvider data_built_in_post_types
	 *
	 * @param string $post_type Post type name.
	 * @param bool   $expected  Expected randomable value.
	 */
	public function test_built_in_post_types( $post_type, $expected ) {
		$this->assertSame( $expected, get_post_type_object( $post_type )->randomable );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array>
	 */
	public function data_built_in_post_types() {
		return array(
			'post'       => array( 'post', true ),
			'page'       => array( 'page', false ),
			'attachment' => array( 'attachment', false ),
			'revision'   => array( 'revision', false ),
			'nav_menu'   => array( 'nav_menu_item', false ),
		);
	}

	/**
	 * @dataProvider data_randomable_defaults
	 *
	 * @param array $args     Post type registration arguments.
	 * @param bool  $expected Expected randomable value.
	 */
	public function test_randomable_defaults( $args, $expected ) {
		$post_type = register_post_type( 'wptests_pt', $args );

		$this->assertSame( $expected, $post_type->randomable );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array>
	 */
	public function data_randomable_defaults() {
		return array(
			'no arguments'                          => array( array(), false ),
			'public'                                => array( array( 'public' => true ), true ),
			'not public'                            => array( array( 'public' => false ), false ),
			'public, not publicly queryable'        => array(
				array(
					'public'             => true,
					'publicly_queryable' => false,
				),
				false,
			),
			'not public, publicly queryable'        => array(
				array(
					'public'             => false,
					'publicly_queryable' => true,
				),
				true,
			),
			'public, explicitly not randomable'     => array(
				array(
					'public'     => true,
					'randomable' => false,
				),
				false,
			),
			'not public, explicitly randomable'     => array(
				array(
					'public'     => false,
					'randomable' => true,
				),
				true,
			),
			'not publicly queryable, randomable'    => array(
				array(
					'public'             => true,
					'publicly_queryable' => false,
					'randomable'         => true,
				),
				true,
			),
			'explicit null falls back to queryable' => array(
				array(
					'public'     => true,
					'randomable' => null,
				),
				true,
			),
		);
	}

	public function test_randomable_can_be_changed_with_register_post_type_args() {
		$filter = static function ( $args, $post_type ) {
			if ( 'page' === $post_type ) {
				$args['randomable'] = true;
			}
			return $args;
		};

		add_filter( 'register_post_type_args', $filter, 10, 2 );
		create_initial_post_types();
		$randomable = get_post_type_object( 'page' )->randomable;
		create_initial_post_types();

		$this->assertTrue( $randomable );
	}

	public function test_get_post_types_can_filter_by_randomable() {
		register_post_type( 'wptests_pt', array( 'public' => true ) );

		$post_types = get_post_types( array( 'randomable' => true ) );

		$this->assertContains( 'post', $post_types );
		$this->assertContains( 'wptests_pt', $post_types );
		$this->assertNotContains( 'page', $post_types );
		$this->assertNotContains( 'attachment', $post_types );
	}

	public function test_randomable_post_type_is_eligible_for_random_content() {
		register_post_type( 'wptests_pt', array( 'public' => true ) );

		$this->go_to( home_url( '/?post_type=wptests_pt' ) );

		$this->assertSame( array( 'wptests_pt' ), wp_get_random_content_post_types( $GLOBALS['wp_query'] ) );
	}

	public function test_randomable_but_not_viewable_post_type_is_not_eligible_for_random_content() {
		register_post_type(
			'wptests_pt',
			array(
				'public'     => false,
				'randomable' => true,
			)
		);

		$this->go_to( home_url( '/?post_type=wptests_pt' ) );

		$this->assertSame( array(), wp_get_random_content_post_types( $GLOBALS['wp_query'] ), 'Post types must also be viewable.' );
	}
}
