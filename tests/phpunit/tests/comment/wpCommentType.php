<?php

/**
 * Tests for the WP_Comment_Type class.
 *
 * @group comment
 *
 * @coversDefaultClass WP_Comment_Type
 */
class Tests_Comment_WpCommentType extends WP_UnitTestCase {

	/**
	 * @ticket 35214
	 *
	 * @covers ::__construct
	 * @covers ::set_props
	 */
	public function test_instance_defaults() {
		$comment_type = new WP_Comment_Type( 'foo' );

		$this->assertSame( 'foo', $comment_type->name );
		$this->assertTrue( $comment_type->public );
		$this->assertFalse( $comment_type->internal );
		$this->assertFalse( $comment_type->_builtin );
		$this->assertTrue( $comment_type->show_ui );
		$this->assertFalse( $comment_type->hierarchical );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_set_props_overrides_defaults() {
		$comment_type = new WP_Comment_Type(
			'foo',
			array(
				'public'      => false,
				'internal'    => true,
				'description' => 'A test comment type.',
			)
		);

		$this->assertFalse( $comment_type->public );
		$this->assertTrue( $comment_type->internal );
		$this->assertSame( 'A test comment type.', $comment_type->description );
		// show_ui follows public when not explicitly set.
		$this->assertFalse( $comment_type->show_ui );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_register_comment_type_args_filter() {
		$filter = static function ( $args ) {
			$args['public'] = false;
			return $args;
		};

		add_filter( 'register_comment_type_args', $filter );
		$comment_type = new WP_Comment_Type( 'foo' );
		remove_filter( 'register_comment_type_args', $filter );

		$this->assertFalse( $comment_type->public );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_register_specific_comment_type_args_filter() {
		$filter = static function ( $args ) {
			$args['description'] = 'Filtered description.';
			return $args;
		};

		add_filter( 'register_foo_comment_type_args', $filter );
		$comment_type = new WP_Comment_Type( 'foo' );
		$other_type   = new WP_Comment_Type( 'bar' );
		remove_filter( 'register_foo_comment_type_args', $filter );

		$this->assertSame( 'Filtered description.', $comment_type->description );
		$this->assertSame( '', $other_type->description );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_default_labels
	 * @covers ::reset_default_labels
	 */
	public function test_get_default_labels_returns_expected_defaults() {
		WP_Comment_Type::reset_default_labels();

		$labels = WP_Comment_Type::get_default_labels();

		$this->assertSame( 'Comments', $labels['name'][0] );
		$this->assertSame( 'Comment', $labels['singular_name'][0] );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_default_labels
	 * @covers ::reset_default_labels
	 */
	public function test_reset_default_labels_clears_cache() {
		// Prime the cache, then mutate the returned (by-value) array.
		WP_Comment_Type::get_default_labels();

		WP_Comment_Type::reset_default_labels();

		// A fresh call rebuilds the defaults from translation functions.
		$labels = WP_Comment_Type::get_default_labels();
		$this->assertSame( 'Comments', $labels['name'][0] );
	}
}
