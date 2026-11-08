<?php

/**
 * Tests for the comment type registration API.
 *
 * @group comment
 */
class Tests_Comment_Types extends WP_UnitTestCase {

	/**
	 * Gets a registered comment type object, failing the test if it is missing.
	 *
	 * @param string $comment_type Comment type key.
	 * @return WP_Comment_Type The registered comment type object.
	 */
	private function get_registered_comment_type( string $comment_type ): WP_Comment_Type {
		$comment_type_object = get_comment_type_object( $comment_type );
		$this->assertInstanceOf( WP_Comment_Type::class, $comment_type_object, "The {$comment_type} comment type should be registered." );
		return $comment_type_object;
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 * @covers ::get_comment_type_object
	 */
	public function test_register_comment_type(): void {
		$before = get_comment_type_object( 'foo' );
		$this->assertNull( $before );

		register_comment_type( 'foo' );

		$cobj = get_comment_type_object( 'foo' );
		$this->assertInstanceOf( 'WP_Comment_Type', $cobj );
		$this->assertSame( 'foo', $cobj->name );

		// Test some defaults.
		$this->assertTrue( $cobj->public );
		$this->assertFalse( $cobj->internal );
		$this->assertFalse( $cobj->_builtin );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 * @covers ::get_comment_type_labels
	 */
	public function test_register_comment_type_without_labels_uses_default_labels(): void {
		register_comment_type( 'foo' );

		$cobj = $this->get_registered_comment_type( 'foo' );

		$this->assertSame( 'Comments', $cobj->label );
		$this->assertSame( 'Comments', $cobj->labels->name );
		$this->assertSame( 'Comment', $cobj->labels->singular_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_return_value(): void {
		$this->assertInstanceOf( 'WP_Comment_Type', register_comment_type( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_register_comment_type_with_too_long_name(): void {
		$this->assertInstanceOf( 'WP_Error', register_comment_type( 'comment_type_with_a_too_long_name' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_register_comment_type_with_empty_name(): void {
		$this->assertInstanceOf( 'WP_Error', register_comment_type( '' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_built_in_comment_types_are_registered(): void {
		$this->assertTrue( comment_type_exists( 'comment' ) );
		$this->assertTrue( comment_type_exists( 'pingback' ) );
		$this->assertTrue( comment_type_exists( 'trackback' ) );
		$this->assertTrue( comment_type_exists( 'note' ) );
		$this->assertTrue( comment_type_exists( 'reaction' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_built_in_note_type_is_internal_and_non_public(): void {
		$note = $this->get_registered_comment_type( 'note' );

		$this->assertTrue( $note->internal );
		$this->assertFalse( $note->public );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_built_in_reaction_type_is_internal_and_non_public(): void {
		$reaction = $this->get_registered_comment_type( 'reaction' );

		$this->assertTrue( $reaction->internal );
		$this->assertFalse( $reaction->public );
		$this->assertTrue( $reaction->_builtin );
		$this->assertSame( 'Reactions', $reaction->label );
		$this->assertSame( 'Reaction', $reaction->labels->singular_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::comment_type_exists
	 */
	public function test_comment_type_exists(): void {
		$this->assertFalse( comment_type_exists( 'foo' ) );

		register_comment_type( 'foo' );

		$this->assertTrue( comment_type_exists( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_names(): void {
		register_comment_type( 'foo' );

		$types = get_comment_types();

		$this->assertContains( 'comment', $types );
		$this->assertContains( 'foo', $types );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_objects(): void {
		register_comment_type( 'foo' );

		$types = get_comment_types( array(), 'objects' );

		$this->assertInstanceOf( 'WP_Comment_Type', $types['foo'] );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_filtered_by_property(): void {
		register_comment_type( 'foo', array( 'public' => false ) );

		$public = get_comment_types( array( 'public' => true ) );

		$this->assertContains( 'comment', $public );
		$this->assertNotContains( 'foo', $public );
		$this->assertNotContains( 'note', $public );
		$this->assertNotContains( 'reaction', $public );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 */
	public function test_unregister_comment_type(): void {
		register_comment_type( 'foo' );

		$this->assertTrue( unregister_comment_type( 'foo' ) );
		$this->assertNull( get_comment_type_object( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 */
	public function test_unregister_comment_type_unknown_returns_error(): void {
		$this->assertWPError( unregister_comment_type( 'does_not_exist' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 */
	public function test_unregister_comment_type_twice_returns_error(): void {
		register_comment_type( 'foo' );

		$this->assertTrue( unregister_comment_type( 'foo' ) );
		$this->assertWPError( unregister_comment_type( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 *
	 * @dataProvider data_built_in_comment_types
	 */
	public function test_unregister_built_in_comment_type_is_not_allowed( string $comment_type ): void {
		$this->assertWPError( unregister_comment_type( $comment_type ) );
		$this->assertTrue( comment_type_exists( $comment_type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<int, array{0: string}>
	 */
	public function data_built_in_comment_types(): array {
		return array(
			array( 'comment' ),
			array( 'pingback' ),
			array( 'trackback' ),
			array( 'note' ),
			array( 'reaction' ),
		);
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 *
	 * @expectedIncorrectUsage register_comment_type
	 *
	 * @dataProvider data_built_in_comment_types
	 */
	public function test_register_built_in_comment_type_is_rejected( string $comment_type ): void {
		$original_label = $this->get_registered_comment_type( $comment_type )->label;

		$result = register_comment_type( $comment_type, array( 'label' => 'Hijacked' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_type_builtin', $result->get_error_code() );
		$this->assertSame( $original_label, $this->get_registered_comment_type( $comment_type )->label );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_twice_overwrites_previous_registration(): void {
		register_comment_type( 'foo', array( 'label' => 'First' ) );
		register_comment_type( 'foo', array( 'label' => 'Second' ) );

		$this->assertSame( 'Second', $this->get_registered_comment_type( 'foo' )->label );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 * @covers ::unregister_comment_type
	 */
	public function test_register_after_unregister_succeeds(): void {
		register_comment_type( 'foo' );
		unregister_comment_type( 'foo' );

		$this->assertInstanceOf( 'WP_Comment_Type', register_comment_type( 'foo' ) );
		$this->assertTrue( comment_type_exists( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_registered_comment_type_actions_fire(): void {
		$action         = new MockAction();
		$action_for_foo = new MockAction();

		add_action( 'registered_comment_type', array( $action, 'action' ) );
		add_action( 'registered_comment_type_foo', array( $action_for_foo, 'action' ) );

		register_comment_type( 'foo' );

		$this->assertSame( 1, $action->get_call_count() );
		$this->assertSame( 1, $action_for_foo->get_call_count() );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 */
	public function test_unregistered_comment_type_action_fires(): void {
		register_comment_type( 'foo' );

		$action = new MockAction();
		add_action( 'unregistered_comment_type', array( $action, 'action' ) );

		unregister_comment_type( 'foo' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_with_20_character_name_succeeds(): void {
		$comment_type = str_repeat( 'a', 20 );

		$this->assertInstanceOf( 'WP_Comment_Type', register_comment_type( $comment_type ) );
		$this->assertTrue( comment_type_exists( $comment_type ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_name_is_sanitized(): void {
		$comment_type_object = register_comment_type( 'Foo Bar!' );

		$this->assertInstanceOf( WP_Comment_Type::class, $comment_type_object );
		$this->assertSame( 'foobar', $comment_type_object->name );
		$this->assertFalse( comment_type_exists( 'Foo Bar!' ) );
		$this->assertTrue( comment_type_exists( 'foobar' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_with_or_operator(): void {
		register_comment_type( 'foo', array( 'public' => false ) );

		$types = get_comment_types(
			array(
				'public'   => true,
				'internal' => true,
			),
			'names',
			'or'
		);

		// 'comment' matches on public, 'note' matches on internal.
		$this->assertContains( 'comment', $types );
		$this->assertContains( 'note', $types );
		$this->assertNotContains( 'foo', $types );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_with_not_operator(): void {
		register_comment_type( 'foo', array( 'internal' => true ) );

		$types = get_comment_types( array( 'internal' => true ), 'names', 'not' );

		$this->assertContains( 'comment', $types );
		$this->assertNotContains( 'note', $types );
		$this->assertNotContains( 'foo', $types );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_names_output_is_keyed_by_type_name(): void {
		register_comment_type( 'foo' );

		$types = get_comment_types();

		$this->assertSame( 'foo', $types['foo'] );
		$this->assertSame( 'comment', $types['comment'] );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_object
	 */
	public function test_get_comment_type_object_with_non_scalar_returns_null(): void {
		$this->assertNull( get_comment_type_object( array() ) ); // @phpstan-ignore argument.type (Intentionally passing a non-scalar value.)
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_create_initial_comment_types_is_idempotent(): void {
		create_initial_comment_types();
		create_initial_comment_types();

		$this->assertCount( 5, get_comment_types( array( '_builtin' => true ) ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_labels_are_built_from_args(): void {
		register_comment_type(
			'foo',
			array(
				'label'  => 'Foos',
				'labels' => array(
					'singular_name' => 'Foo',
				),
			)
		);

		$cobj = $this->get_registered_comment_type( 'foo' );

		$this->assertSame( 'Foos', $cobj->label );
		$this->assertSame( 'Foos', $cobj->labels->name );
		$this->assertSame( 'Foo', $cobj->labels->singular_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_comment_type_labels_filter(): void {
		add_filter(
			'comment_type_labels_foo',
			static function ( stdClass $labels ): stdClass {
				$labels->singular_name = 'Filtered Foo';
				return $labels;
			}
		);

		register_comment_type( 'foo' );

		$this->assertSame( 'Filtered Foo', $this->get_registered_comment_type( 'foo' )->labels->singular_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_label_only_registration_populates_label_fallback_chain(): void {
		register_comment_type( 'foo', array( 'label' => 'Foos' ) );

		$labels = $this->get_registered_comment_type( 'foo' )->labels;

		$this->assertSame( 'Foos', $labels->name );
		$this->assertSame( 'Foos', $labels->singular_name );
		$this->assertSame( 'Foos', $labels->menu_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_labels_do_not_include_post_type_only_labels(): void {
		register_comment_type( 'foo', array( 'label' => 'Foos' ) );

		$labels = $this->get_registered_comment_type( 'foo' )->labels;

		$this->assertObjectNotHasProperty( 'name_admin_bar', $labels );
		$this->assertObjectNotHasProperty( 'all_items', $labels );
		$this->assertObjectNotHasProperty( 'archives', $labels );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_get_comment_type_labels_does_not_modify_a_registered_type(): void {
		register_comment_type( 'foo', array( 'label' => 'Foos' ) );

		$comment_type_object = $this->get_registered_comment_type( 'foo' );
		$registered_labels   = get_object_vars( $comment_type_object->labels );

		$first  = get_comment_type_labels( $comment_type_object );
		$second = get_comment_type_labels( $comment_type_object );

		$this->assertSame( $registered_labels, get_object_vars( $comment_type_object->labels ), 'The registered labels should not change.' );
		$this->assertEquals( $first, $second, 'Repeated calls should return the same labels.' );
		$this->assertObjectNotHasProperty( 'archives', $second, 'Post-type-only labels should not appear on repeated calls.' );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_labels_do_not_spawn_post_type_only_labels_from_menu_name(): void {
		register_comment_type(
			'foo',
			array(
				'label'  => 'Foos',
				'labels' => array(
					'menu_name' => 'Foo Menu',
				),
			)
		);

		$labels = $this->get_registered_comment_type( 'foo' )->labels;

		$this->assertSame( 'Foo Menu', $labels->menu_name );
		$this->assertObjectNotHasProperty( 'all_items', $labels );
		$this->assertObjectNotHasProperty( 'archives', $labels );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_comment_type_labels_filter_missing_name_is_backfilled(): void {
		add_filter(
			'comment_type_labels_foo',
			static function ( stdClass $labels ): stdClass {
				unset( $labels->name );
				return $labels;
			}
		);

		register_comment_type( 'foo', array( 'label' => 'Foos' ) );

		$this->assertSame( 'Foos', $this->get_registered_comment_type( 'foo' )->labels->name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_registered_comment_type_action_receives_type_and_object(): void {
		$action = new MockAction();

		add_action( 'registered_comment_type', array( $action, 'action' ), 10, 2 );

		register_comment_type( 'foo' );

		$args = $action->get_args();

		$this->assertSame( 'foo', $args[0][0] );
		$this->assertInstanceOf( 'WP_Comment_Type', $args[0][1] );
		$this->assertSame( 'foo', $args[0][1]->name );
	}

	/**
	 * Comment types are never hierarchical. The default labels reserve the hierarchical
	 * slot as null, so honoring a provided value would resolve every label to null.
	 *
	 * @ticket 35214
	 *
	 * @covers WP_Comment_Type::set_props
	 */
	public function test_register_comment_type_ignores_hierarchical_argument(): void {
		register_comment_type( 'foo', array( 'hierarchical' => true ) );

		$cobj = $this->get_registered_comment_type( 'foo' );

		$this->assertFalse( $cobj->hierarchical, 'A comment type should never be hierarchical.' );
		$this->assertSame( 'Comments', $cobj->label, 'The default label should survive the argument.' );
		$this->assertSame( 'Comments', $cobj->labels->name );
		$this->assertSame( 'Comment', $cobj->labels->singular_name );
	}

	/**
	 * The names WP_Comment_Query reads as query tokens cannot be registered, since a type
	 * stored under one of them could never be queried for on its own.
	 *
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 *
	 * @dataProvider data_reserved_comment_type_names
	 *
	 * @expectedIncorrectUsage register_comment_type
	 *
	 * @param string $comment_type Reserved comment type name.
	 */
	public function test_register_reserved_comment_type_is_rejected( string $comment_type ): void {
		$result = register_comment_type( $comment_type );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'comment_type_reserved', $result->get_error_code() );
		$this->assertNull( get_comment_type_object( $comment_type ) );
	}

	/**
	 * Data provider for test_register_reserved_comment_type_is_rejected().
	 *
	 * @return array<string, string[]>
	 */
	public function data_reserved_comment_type_names(): array {
		return array(
			'all type token'   => array( 'all' ),
			'comments alias'   => array( 'comments' ),
			'pings bucket key' => array( 'pings' ),
		);
	}

	/**
	 * The built-in guard reads the raw arguments, so passing '_builtin' bypasses it. This
	 * matches register_post_type(), where '_builtin' is an accepted internal-use argument
	 * with no guard at all. Pinned so the follow-ups that give '_builtin' more meaning
	 * cannot change it by accident.
	 *
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_builtin_argument_bypasses_the_built_in_guard(): void {
		$result = register_comment_type(
			'pingback',
			array(
				'_builtin' => true,
				'label'    => 'Hijacked',
			)
		);

		$this->assertInstanceOf( 'WP_Comment_Type', $result, 'Passing _builtin bypasses the guard.' );
		$this->assertSame( 'Hijacked', $this->get_registered_comment_type( 'pingback' )->label );
	}

	/**
	 * Passing '_builtin' on a custom type makes it behave like a built-in for everyone
	 * else: it can no longer be re-registered or unregistered.
	 *
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 * @covers ::unregister_comment_type
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_register_comment_type_builtin_argument_locks_a_custom_type(): void {
		register_comment_type( 'foo', array( '_builtin' => true ) );

		$this->assertInstanceOf( 'WP_Error', register_comment_type( 'foo' ) );
		$this->assertInstanceOf( 'WP_Error', unregister_comment_type( 'foo' ) );
	}

	/**
	 * Built-in labels are rebuilt on a locale change rather than served from the static
	 * default-labels cache.
	 *
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_built_in_labels_are_rebuilt_on_locale_change(): void {
		$original = $this->get_registered_comment_type( 'comment' )->label;

		add_filter(
			'gettext',
			static function ( $translation, $text ) {
				return 'Comments' === $text ? 'Kommentare' : $translation;
			},
			10,
			2
		);

		/** This action is documented in wp-includes/class-wp-locale-switcher.php */
		do_action( 'change_locale', 'de_DE' );

		$this->assertSame(
			'Kommentare',
			$this->get_registered_comment_type( 'comment' )->label,
			'A locale change should rebuild the built-in labels.'
		);
		$this->assertNotSame( $original, $this->get_registered_comment_type( 'comment' )->label );
	}

	/**
	 * Before wp-settings.php registers the built-ins, the registry global does not exist.
	 * Both accessors have to cope with that rather than warn.
	 *
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 * @covers ::get_comment_type_object
	 */
	public function test_accessors_handle_an_unset_registry(): void {
		$registry = $GLOBALS['wp_comment_types'];
		unset( $GLOBALS['wp_comment_types'] );

		try {
			$this->assertSame( array(), get_comment_types() );
			$this->assertNull( get_comment_type_object( 'comment' ) );
		} finally {
			$GLOBALS['wp_comment_types'] = $registry;
		}
	}

	/**
	 * The registry is a per-process global, so it is not scoped to a site. This matches
	 * post types, and is worth pinning because comment counts and query exclusions are
	 * per-site data.
	 *
	 * @ticket 35214
	 *
	 * @group ms-required
	 *
	 * @covers ::register_comment_type
	 */
	public function test_registry_is_not_scoped_to_a_site(): void {
		register_comment_type( 'foo' );

		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );

		$registered_after_switch = comment_type_exists( 'foo' );

		restore_current_blog();

		$this->assertTrue(
			$registered_after_switch,
			'A registered comment type should still be registered after switch_to_blog().'
		);
	}
}
