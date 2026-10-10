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

	/**
	 * Gets the main query.
	 *
	 * The global is read when called as WP_UnitTestCase_Base::go_to() replaces it.
	 *
	 * @global WP_Query $wp_query WordPress Query object.
	 *
	 * @return WP_Query The main query.
	 */
	protected function get_main_query(): WP_Query {
		global $wp_query;

		return $wp_query;
	}

	/**
	 * @dataProvider data_built_in_post_types
	 *
	 * @param string $post_type Post type name.
	 * @param bool   $expected  Expected randomable value.
	 */
	public function test_built_in_post_types( $post_type, $expected ): void {
		$post_type_object = get_post_type_object( $post_type );

		$this->assertInstanceOf(
			WP_Post_Type::class,
			$post_type_object,
			sprintf( 'The built-in post type "%s" is not registered (expected randomable: %s).', $post_type, wp_json_encode( $expected ) )
		);
		$this->assertSame(
			$expected,
			$post_type_object->randomable,
			sprintf( 'The built-in post type "%s" has an unexpected randomable value (expected: %s).', $post_type, wp_json_encode( $expected ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function data_built_in_post_types(): array {
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
	 * @param array{public?: bool, publicly_queryable?: bool, randomable?: bool} $args     Post type registration arguments.
	 * @param bool                                                            $expected Expected randomable value.
	 */
	public function test_randomable_defaults( $args, $expected ): void {
		$post_type = register_post_type( 'wptests_pt', $args );

		$this->assertInstanceOf(
			WP_Post_Type::class,
			$post_type,
			sprintf( 'The post type could not be registered with arguments %s.', wp_json_encode( $args ) )
		);
		$this->assertSame(
			$expected,
			$post_type->randomable,
			sprintf( 'Expected randomable value %s for registration arguments %s.', wp_json_encode( $expected ), wp_json_encode( $args ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: array{public?: bool, publicly_queryable?: bool, randomable?: bool}, 1: bool}>
	 */
	public function data_randomable_defaults(): array {
		return array(
			'no arguments'                       => array( array(), false ),
			'public'                             => array( array( 'public' => true ), true ),
			'not public'                         => array( array( 'public' => false ), false ),
			'public, not publicly queryable'     => array(
				array(
					'public'             => true,
					'publicly_queryable' => false,
				),
				false,
			),
			'not public, publicly queryable'     => array(
				array(
					'public'             => false,
					'publicly_queryable' => true,
				),
				true,
			),
			'public, explicitly not randomable'  => array(
				array(
					'public'     => true,
					'randomable' => false,
				),
				false,
			),
			'not public, explicitly randomable'  => array(
				array(
					'public'     => false,
					'randomable' => true,
				),
				true,
			),
			'not publicly queryable, randomable' => array(
				array(
					'public'             => true,
					'publicly_queryable' => false,
					'randomable'         => true,
				),
				true,
			),
		);
	}

	public function test_randomable_can_be_changed_with_register_post_type_args(): void {
		$filter = static function ( array $args, string $post_type ): array {
			if ( 'page' === $post_type ) {
				$args['randomable'] = true;
			}
			return $args;
		};

		add_filter( 'register_post_type_args', $filter, 10, 2 );
		create_initial_post_types();
		$page = get_post_type_object( 'page' );

		$this->assertInstanceOf( WP_Post_Type::class, $page, 'The page post type is not registered.' );
		$this->assertTrue( $page->randomable, 'The register_post_type_args filter should be able to make pages randomable.' );
	}

	public function test_get_post_types_can_filter_by_randomable(): void {
		register_post_type( 'wptests_pt', array( 'public' => true ) );

		$post_types = get_post_types( array( 'randomable' => true ) );

		$this->assertContains( 'post', $post_types, 'Posts should be included in the randomable post types.' );
		$this->assertContains( 'wptests_pt', $post_types, 'A public custom post type should be included in the randomable post types.' );
		$this->assertNotContains( 'page', $post_types, 'Pages should not be included in the randomable post types.' );
		$this->assertNotContains( 'attachment', $post_types, 'Attachments should not be included in the randomable post types.' );
	}

	public function test_randomable_post_type_is_eligible_for_random_content(): void {
		register_post_type( 'wptests_pt', array( 'public' => true ) );

		$this->go_to( home_url( '/?post_type=wptests_pt' ) );

		$this->assertSame( array( 'wptests_pt' ), wp_get_random_content_post_types( $this->get_main_query() ), 'A randomable, viewable post type should be eligible for random content.' );
	}

	public function test_randomable_but_not_viewable_post_type_is_not_eligible_for_random_content(): void {
		register_post_type(
			'wptests_pt',
			array(
				'public'     => false,
				'randomable' => true,
			)
		);

		$this->go_to( home_url( '/?post_type=wptests_pt' ) );

		$this->assertSame( array(), wp_get_random_content_post_types( $this->get_main_query() ), 'A randomable post type that is not viewable should not be eligible for random content.' );
	}
}
