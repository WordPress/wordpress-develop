<?php

/**
 * Tests for sanitize_post_field().
 *
 * @group post
 *
 * @covers ::sanitize_post_field
 */
class Tests_Post_SanitizePostField extends WP_UnitTestCase {

	/**
	 * Tests that the integer fields are cast to integers, in every context.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_integer_fields
	 *
	 * @param string $field   Name of the field.
	 * @param mixed  $value   Value of the field.
	 * @param int    $context Context.
	 */
	public function test_should_cast_integer_fields_to_integers( $field, $value, $context ) {
		$result = sanitize_post_field( $field, $value, 1, $context );

		$this->assertIsInt( $result );
		$this->assertSame( (int) $value, $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_integer_fields() {
		$data = array();

		foreach ( array( 'ID', 'post_parent', 'menu_order' ) as $field ) {
			foreach ( array( 'raw', 'edit', 'db', 'display', 'attribute', 'js' ) as $context ) {
				$data[ "$field in $context context" ] = array( $field, '12', $context );
			}
		}

		$data['numeric string with text'] = array( 'post_parent', '5 apples', 'display' );
		$data['empty string']             = array( 'menu_order', '', 'display' );
		$data['negative number']          = array( 'menu_order', '-3', 'edit' );

		return $data;
	}

	/**
	 * Tests that the ancestors are returned as a list of positive integers, in every context.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_contexts
	 *
	 * @param string $context Context.
	 */
	public function test_should_return_ancestors_as_positive_integers( $context ) {
		$this->assertSame( array( 3, 4, 0, 7 ), sanitize_post_field( 'ancestors', array( '3', -4, 'text', '7' ), 1, $context ) );
	}

	/**
	 * Tests that a value that is not an array is turned into an array of ancestors.
	 *
	 * @ticket 66495
	 */
	public function test_should_turn_a_single_ancestor_into_an_array() {
		$this->assertSame( array( 9 ), sanitize_post_field( 'ancestors', '9', 1, 'display' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contexts() {
		return array(
			'raw'       => array( 'raw' ),
			'edit'      => array( 'edit' ),
			'db'        => array( 'db' ),
			'display'   => array( 'display' ),
			'attribute' => array( 'attribute' ),
			'js'        => array( 'js' ),
		);
	}

	/**
	 * Tests that the value is returned as it is in the raw context, and the filters do not run.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_the_value_unchanged_in_the_raw_context() {
		$action = new MockAction();
		add_filter( 'post_foo', array( $action, 'filter' ) );
		add_filter( 'pre_post_foo', array( $action, 'filter' ) );
		add_filter( 'edit_post_foo', array( $action, 'filter' ) );

		$this->assertSame( 'A & <b>"quoted"</b>', sanitize_post_field( 'post_foo', 'A & <b>"quoted"</b>', 1, 'raw' ) );
		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that the filters of a field with the `post_` prefix run in the edit context, and in this order.
	 *
	 * @ticket 66495
	 */
	public function test_should_apply_the_edit_filters_of_a_prefixed_field() {
		$calls = array();
		foreach ( array( 'edit_post_foo', 'foo_edit_pre' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $value, $post_id ) use ( &$calls, $hook ) {
					$calls[] = array( $hook, $value, $post_id );

					return $value . '+' . $hook;
				},
				10,
				2
			);
		}

		$result = sanitize_post_field( 'post_foo', 'value', 42, 'edit' );

		$this->assertSame(
			array(
				array( 'edit_post_foo', 'value', 42 ),
				array( 'foo_edit_pre', 'value+edit_post_foo', 42 ),
			),
			$calls
		);
		$this->assertSame( 'value+edit_post_foo+foo_edit_pre', $result );
	}

	/**
	 * Tests that the filter of a field without the `post_` prefix runs in the edit context.
	 *
	 * @ticket 66495
	 */
	public function test_should_apply_the_edit_filter_of_a_field_without_prefix() {
		$received = null;
		add_filter(
			'edit_post_bar',
			static function ( $value, $post_id ) use ( &$received ) {
				$received = array( $value, $post_id );

				return 'filtered';
			},
			10,
			2
		);

		$this->assertSame( 'filtered', sanitize_post_field( 'bar', 'value', 42, 'edit' ) );
		$this->assertSame( array( 'value', 42 ), $received );
	}

	/**
	 * Tests that the text of the title, the excerpt and the password is formatted for a text area in the edit context.
	 *
	 * A text area needs the ampersand of an entity to be encoded again, so that the entity is shown as it was written.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_fields_formatted_to_edit
	 *
	 * @param string $field Name of the field.
	 */
	public function test_should_format_text_fields_for_editing( $field ) {
		$this->assertSame(
			'Fish &amp;amp; &lt;b&gt;Chips&lt;/b&gt; &quot;q&quot;',
			sanitize_post_field( $field, 'Fish &amp; <b>Chips</b> "q"', 1, 'edit' )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_fields_formatted_to_edit() {
		return array(
			'post_title'    => array( 'post_title' ),
			'post_excerpt'  => array( 'post_excerpt' ),
			'post_password' => array( 'post_password' ),
		);
	}

	/**
	 * Tests that other fields are escaped for use in an attribute in the edit context.
	 *
	 * An attribute keeps an entity that is already there, instead of encoding its ampersand again.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_fields_escaped_to_edit
	 *
	 * @param string $field Name of the field.
	 */
	public function test_should_escape_other_fields_as_attributes_for_editing( $field ) {
		$this->assertSame( 'Fish &amp; &lt;b&gt;Chips&lt;/b&gt; &quot;q&quot;', sanitize_post_field( $field, 'Fish &amp; <b>Chips</b> "q"', 1, 'edit' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_fields_escaped_to_edit() {
		return array(
			'post_name'      => array( 'post_name' ),
			'post_status'    => array( 'post_status' ),
			'comment_status' => array( 'comment_status' ),
		);
	}

	/**
	 * Tests that the filters of a field with the `post_` prefix run in the db context, and in this order.
	 *
	 * @ticket 66495
	 */
	public function test_should_apply_the_db_filters_of_a_prefixed_field() {
		$calls = array();
		foreach ( array( 'pre_post_foo', 'foo_save_pre' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $value ) use ( &$calls, $hook ) {
					$calls[] = $hook;

					return $value . '+' . $hook;
				}
			);
		}

		$this->assertSame( 'value+pre_post_foo+foo_save_pre', sanitize_post_field( 'post_foo', 'value', 42, 'db' ) );
		$this->assertSame( array( 'pre_post_foo', 'foo_save_pre' ), $calls );
	}

	/**
	 * Tests that the filters of a field without the `post_` prefix run in the db context, and in this order.
	 *
	 * @ticket 66495
	 */
	public function test_should_apply_the_db_filters_of_a_field_without_prefix() {
		$calls = array();
		foreach ( array( 'pre_post_bar', 'bar_pre' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $value ) use ( &$calls, $hook ) {
					$calls[] = $hook;

					return $value . '+' . $hook;
				}
			);
		}

		$this->assertSame( 'value+pre_post_bar+bar_pre', sanitize_post_field( 'bar', 'value', 42, 'db' ) );
		$this->assertSame( array( 'pre_post_bar', 'bar_pre' ), $calls );
	}

	/**
	 * Tests that the value is not escaped in the db context.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_escape_the_value_in_the_db_context() {
		$this->assertSame( 'A & <b>"q"</b>', sanitize_post_field( 'post_foo', 'A & <b>"q"</b>', 1, 'db' ) );
	}

	/**
	 * Tests that the display filter of a field with the `post_` prefix gets the value, the ID and the context.
	 *
	 * @ticket 66495
	 */
	public function test_should_apply_the_display_filter_of_a_prefixed_field() {
		$received = null;
		add_filter(
			'post_foo',
			static function ( $value, $post_id, $context ) use ( &$received ) {
				$received = array( $value, $post_id, $context );

				return 'filtered';
			},
			10,
			3
		);

		$this->assertSame( 'filtered', sanitize_post_field( 'post_foo', 'value', 42, 'display' ) );
		$this->assertSame( array( 'value', 42, 'display' ), $received );
	}

	/**
	 * Tests that the display filter of a field without the `post_` prefix gets the value, the ID and the context.
	 *
	 * @ticket 66495
	 */
	public function test_should_apply_the_display_filter_of_a_field_without_prefix() {
		$received = null;
		add_filter(
			'post_bar',
			static function ( $value, $post_id, $context ) use ( &$received ) {
				$received = array( $value, $post_id, $context );

				return 'filtered';
			},
			10,
			3
		);

		$this->assertSame( 'filtered', sanitize_post_field( 'bar', 'value', 42, 'display' ) );
		$this->assertSame( array( 'value', 42, 'display' ), $received );
	}

	/**
	 * Tests that a context that is not known uses the display filters, and does not escape the value.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_display_filters_for_other_contexts() {
		$contexts = array();
		add_filter(
			'post_foo',
			static function ( $value, $post_id, $context ) use ( &$contexts ) {
				$contexts[] = $context;

				return $value;
			},
			10,
			3
		);

		$this->assertSame( 'A & <b>"q"</b>', sanitize_post_field( 'post_foo', 'A & <b>"q"</b>', 1, 'rss' ) );
		$this->assertSame( array( 'rss' ), $contexts );
	}

	/**
	 * Tests that the value is escaped for an attribute in the attribute context, after the filter.
	 *
	 * @ticket 66495
	 */
	public function test_should_escape_the_value_in_the_attribute_context() {
		add_filter(
			'post_foo',
			static function ( $value ) {
				return $value . ' "added"';
			}
		);

		$this->assertSame( 'A &amp; &lt;b&gt; &quot;added&quot;', sanitize_post_field( 'post_foo', 'A & <b>', 1, 'attribute' ) );
	}

	/**
	 * Tests that the value is escaped for JavaScript in the js context, after the filter.
	 *
	 * @ticket 66495
	 */
	public function test_should_escape_the_value_in_the_js_context() {
		add_filter(
			'post_foo',
			static function ( $value ) {
				return $value . "'x'";
			}
		);

		$this->assertSame( 'Line\\\'s &lt;b&gt; \\\'x\\\'', str_replace( "\n", ' ', sanitize_post_field( 'post_foo', "Line's <b> ", 1, 'js' ) ) );
	}
}
