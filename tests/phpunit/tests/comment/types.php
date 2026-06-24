<?php

/**
 * Tests for the comment type registration API.
 *
 * @group comment
 *
 * @covers ::register_comment_type
 */
class Tests_Comment_Types extends WP_UnitTestCase {

	/**
	 * Comment type slug used across tests.
	 *
	 * @var string
	 */
	public $comment_type = 'foo';

	/**
	 * Ensures any comment type registered during a test is cleaned up.
	 */
	public function tear_down() {
		global $wp_comment_types;

		foreach ( array_keys( $wp_comment_types ) as $comment_type ) {
			if ( ! $wp_comment_types[ $comment_type ]->_builtin ) {
				unset( $wp_comment_types[ $comment_type ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * @ticket 35214
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
	 */
	public function test_register_comment_type_return_value() {
		$this->assertInstanceOf( 'WP_Comment_Type', register_comment_type( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_register_comment_type_with_too_long_name() {
		$this->assertInstanceOf( 'WP_Error', register_comment_type( 'comment_type_with_a_too_long_name' ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_register_comment_type_with_empty_name() {
		$this->assertInstanceOf( 'WP_Error', register_comment_type( '' ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_register_comment_type_show_ui_should_default_to_value_of_public() {
		register_comment_type( 'public_type', array( 'public' => true ) );
		$this->assertTrue( get_comment_type_object( 'public_type' )->show_ui );

		register_comment_type( 'private_type', array( 'public' => false ) );
		$this->assertFalse( get_comment_type_object( 'private_type' )->show_ui );
	}

	/**
	 * @ticket 35214
	 */
	public function test_built_in_comment_types_are_registered() {
		$this->assertTrue( comment_type_exists( 'comment' ) );
		$this->assertTrue( comment_type_exists( 'pingback' ) );
		$this->assertTrue( comment_type_exists( 'trackback' ) );
		$this->assertTrue( comment_type_exists( 'note' ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_built_in_note_type_is_internal_and_non_public() {
		$note = get_comment_type_object( 'note' );

		$this->assertTrue( $note->internal );
		$this->assertFalse( $note->public );
	}

	/**
	 * @ticket 35214
	 */
	public function test_comment_type_exists() {
		$this->assertFalse( comment_type_exists( 'foo' ) );

		register_comment_type( 'foo' );

		$this->assertTrue( comment_type_exists( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_comment_types_names() {
		register_comment_type( 'foo' );

		$types = get_comment_types();

		$this->assertContains( 'comment', $types );
		$this->assertContains( 'foo', $types );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_comment_types_objects() {
		register_comment_type( 'foo' );

		$types = get_comment_types( array(), 'objects' );

		$this->assertInstanceOf( 'WP_Comment_Type', $types['foo'] );
	}

	/**
	 * @ticket 35214
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
}
