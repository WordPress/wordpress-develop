<?php

/**
 * Tests for the comment type registration API.
 *
 * @group comment
 */
class Tests_Comment_Types extends WP_UnitTestCase {

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 * @covers ::get_comment_type_object
	 */
	public function test_register_comment_type() {
		$this->assertNull( get_comment_type_object( 'foo' ) );

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
	public function test_register_comment_type_without_labels_uses_default_labels() {
		register_comment_type( 'foo' );

		$cobj = get_comment_type_object( 'foo' );

		$this->assertSame( 'Comments', $cobj->label );
		$this->assertSame( 'Comments', $cobj->labels->name );
		$this->assertSame( 'Comment', $cobj->labels->singular_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_return_value() {
		$this->assertInstanceOf( 'WP_Comment_Type', register_comment_type( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_register_comment_type_with_too_long_name() {
		$this->assertInstanceOf( 'WP_Error', register_comment_type( 'comment_type_with_a_too_long_name' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_register_comment_type_with_empty_name() {
		$this->assertInstanceOf( 'WP_Error', register_comment_type( '' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_built_in_comment_types_are_registered() {
		$this->assertTrue( comment_type_exists( 'comment' ) );
		$this->assertTrue( comment_type_exists( 'pingback' ) );
		$this->assertTrue( comment_type_exists( 'trackback' ) );
		$this->assertTrue( comment_type_exists( 'note' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_built_in_note_type_is_internal_and_non_public() {
		$note = get_comment_type_object( 'note' );

		$this->assertTrue( $note->internal );
		$this->assertFalse( $note->public );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::comment_type_exists
	 */
	public function test_comment_type_exists() {
		$this->assertFalse( comment_type_exists( 'foo' ) );

		register_comment_type( 'foo' );

		$this->assertTrue( comment_type_exists( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_names() {
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
	public function test_get_comment_types_objects() {
		register_comment_type( 'foo' );

		$types = get_comment_types( array(), 'objects' );

		$this->assertInstanceOf( 'WP_Comment_Type', $types['foo'] );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_filtered_by_property() {
		register_comment_type( 'foo', array( 'public' => false ) );

		$public = get_comment_types( array( 'public' => true ) );

		$this->assertContains( 'comment', $public );
		$this->assertNotContains( 'foo', $public );
		$this->assertNotContains( 'note', $public );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 */
	public function test_unregister_comment_type() {
		register_comment_type( 'foo' );

		$this->assertTrue( unregister_comment_type( 'foo' ) );
		$this->assertNull( get_comment_type_object( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 */
	public function test_unregister_comment_type_unknown_returns_error() {
		$this->assertWPError( unregister_comment_type( 'does_not_exist' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::unregister_comment_type
	 */
	public function test_unregister_comment_type_twice_returns_error() {
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
	public function test_unregister_built_in_comment_type_is_not_allowed( $comment_type ) {
		$this->assertWPError( unregister_comment_type( $comment_type ) );
		$this->assertTrue( comment_type_exists( $comment_type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_built_in_comment_types() {
		return array(
			array( 'comment' ),
			array( 'pingback' ),
			array( 'trackback' ),
			array( 'note' ),
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
	public function test_register_built_in_comment_type_is_rejected( $comment_type ) {
		$original_label = get_comment_type_object( $comment_type )->label;

		$result = register_comment_type( $comment_type, array( 'label' => 'Hijacked' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'comment_type_builtin', $result->get_error_code() );
		$this->assertSame( $original_label, get_comment_type_object( $comment_type )->label );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_twice_overwrites_previous_registration() {
		register_comment_type( 'foo', array( 'label' => 'First' ) );
		register_comment_type( 'foo', array( 'label' => 'Second' ) );

		$this->assertSame( 'Second', get_comment_type_object( 'foo' )->label );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 * @covers ::unregister_comment_type
	 */
	public function test_register_after_unregister_succeeds() {
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
	public function test_registered_comment_type_actions_fire() {
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
	public function test_unregistered_comment_type_action_fires() {
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
	public function test_register_comment_type_with_20_character_name_succeeds() {
		$comment_type = str_repeat( 'a', 20 );

		$this->assertInstanceOf( 'WP_Comment_Type', register_comment_type( $comment_type ) );
		$this->assertTrue( comment_type_exists( $comment_type ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_register_comment_type_name_is_sanitized() {
		$comment_type_object = register_comment_type( 'Foo Bar!' );

		$this->assertSame( 'foobar', $comment_type_object->name );
		$this->assertFalse( comment_type_exists( 'Foo Bar!' ) );
		$this->assertTrue( comment_type_exists( 'foobar' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_types
	 */
	public function test_get_comment_types_with_or_operator() {
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
	public function test_get_comment_types_with_not_operator() {
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
	public function test_get_comment_types_names_output_is_keyed_by_type_name() {
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
	public function test_get_comment_type_object_with_non_scalar_returns_null() {
		$this->assertNull( get_comment_type_object( array() ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::create_initial_comment_types
	 */
	public function test_create_initial_comment_types_is_idempotent() {
		create_initial_comment_types();
		create_initial_comment_types();

		$this->assertCount( 4, get_comment_types( array( '_builtin' => true ) ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_labels_are_built_from_args() {
		register_comment_type(
			'foo',
			array(
				'label'  => 'Foos',
				'labels' => array(
					'singular_name' => 'Foo',
				),
			)
		);

		$cobj = get_comment_type_object( 'foo' );

		$this->assertSame( 'Foos', $cobj->label );
		$this->assertSame( 'Foos', $cobj->labels->name );
		$this->assertSame( 'Foo', $cobj->labels->singular_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_comment_type_labels_filter() {
		add_filter(
			'comment_type_labels_foo',
			static function ( $labels ) {
				$labels->singular_name = 'Filtered Foo';
				return $labels;
			}
		);

		register_comment_type( 'foo' );

		$this->assertSame( 'Filtered Foo', get_comment_type_object( 'foo' )->labels->singular_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_label_only_registration_populates_label_fallback_chain() {
		register_comment_type( 'foo', array( 'label' => 'Foos' ) );

		$labels = get_comment_type_object( 'foo' )->labels;

		$this->assertSame( 'Foos', $labels->name );
		$this->assertSame( 'Foos', $labels->singular_name );
		$this->assertSame( 'Foos', $labels->menu_name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_labels_do_not_include_post_type_only_labels() {
		register_comment_type( 'foo', array( 'label' => 'Foos' ) );

		$labels = get_comment_type_object( 'foo' )->labels;

		$this->assertObjectNotHasProperty( 'name_admin_bar', $labels );
		$this->assertObjectNotHasProperty( 'all_items', $labels );
		$this->assertObjectNotHasProperty( 'archives', $labels );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_labels_do_not_spawn_post_type_only_labels_from_menu_name() {
		register_comment_type(
			'foo',
			array(
				'label'  => 'Foos',
				'labels' => array(
					'menu_name' => 'Foo Menu',
				),
			)
		);

		$labels = get_comment_type_object( 'foo' )->labels;

		$this->assertSame( 'Foo Menu', $labels->menu_name );
		$this->assertObjectNotHasProperty( 'all_items', $labels );
		$this->assertObjectNotHasProperty( 'archives', $labels );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_comment_type_labels
	 */
	public function test_comment_type_labels_filter_missing_name_is_backfilled() {
		add_filter(
			'comment_type_labels_foo',
			static function ( $labels ) {
				unset( $labels->name );
				return $labels;
			}
		);

		register_comment_type( 'foo', array( 'label' => 'Foos' ) );

		$this->assertSame( 'Foos', get_comment_type_object( 'foo' )->labels->name );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::register_comment_type
	 */
	public function test_registered_comment_type_action_receives_type_and_object() {
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
	public function test_register_comment_type_ignores_hierarchical_argument() {
		register_comment_type( 'foo', array( 'hierarchical' => true ) );

		$cobj = get_comment_type_object( 'foo' );

		$this->assertFalse( $cobj->hierarchical, 'A comment type should never be hierarchical.' );
		$this->assertSame( 'Comments', $cobj->label, 'The default label should survive the argument.' );
		$this->assertSame( 'Comments', $cobj->labels->name );
		$this->assertSame( 'Comment', $cobj->labels->singular_name );
	}

}
