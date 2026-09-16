<?php
/**
 * Tests for the Content Type API.
 *
 * @package WordPress
 * @subpackage Post
 */

/**
 * Tests for the Content Type API.
 *
 * @group post
 * @group content-type
 */
class Tests_Post_WP_Content_Type extends WP_UnitTestCase {

	/**
	 * Clean up content types after each test.
	 */
	public function tear_down() {
		global $wp_content_types;

		// Unregister any test content types.
		if ( is_array( $wp_content_types ) ) {
			foreach ( array_keys( $wp_content_types ) as $content_type ) {
				// Skip cleanup if it starts with 'test_' or known test types.
				if ( strpos( $content_type, 'cpt' ) === 0 || strpos( $content_type, 'book' ) === 0 ) {
					unregister_content_type( $content_type );
				}
			}
		}

		parent::tear_down();
	}

	/**
	 * Test that register_content_type() returns a WP_Content_Type object on success.
	 */
	public function test_register_content_type_returns_object() {
		$result = register_content_type(
			'cpt_test',
			array(
				'labels' => array(
					'name' => 'Test CPT',
				),
			)
		);

		$this->assertInstanceOf( 'WP_Content_Type', $result );
		$this->assertSame( 'cpt_test', $result->name );
	}

	/**
	 * Test that register_content_type() also registers the post type.
	 */
	public function test_register_content_type_registers_post_type() {
		register_content_type(
			'cpt_test',
			array(
				'labels' => array(
					'name' => 'Test CPT',
				),
				'public' => true,
			)
		);

		$this->assertTrue( post_type_exists( 'cpt_test' ) );

		$post_type = get_post_type_object( 'cpt_test' );
		$this->assertTrue( $post_type->public );
	}

	/**
	 * Test that register_content_type() registers meta fields.
	 */
	public function test_register_content_type_registers_meta_fields() {
		register_content_type(
			'cpt_test',
			array(
				'fields' => array(
					'test_field' => array(
						'type'   => 'string',
						'single' => true,
					),
				),
			)
		);

		$registered_meta = get_registered_meta_keys( 'post', 'cpt_test' );

		$this->assertArrayHasKey( 'test_field', $registered_meta );
		$this->assertSame( 'string', $registered_meta['test_field']['type'] );
	}

	/**
	 * Test that register_content_type() fails for invalid content type names.
	 */
	public function test_register_content_type_invalid_name_too_long() {
		$this->setExpectedIncorrectUsage( 'register_content_type' );

		$result = register_content_type( 'this_name_is_way_too_long', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'content_type_length_invalid', $result->get_error_code() );
	}

	/**
	 * Test that register_content_type() fails for empty content type names.
	 */
	public function test_register_content_type_invalid_name_empty() {
		$this->setExpectedIncorrectUsage( 'register_content_type' );

		$result = register_content_type( '', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'content_type_length_invalid', $result->get_error_code() );
	}

	/**
	 * Test that register_content_type() fails for duplicate content types.
	 */
	public function test_register_content_type_duplicate() {
		$this->setExpectedIncorrectUsage( 'register_content_type' );

		register_content_type( 'cpt_test', array() );
		$result = register_content_type( 'cpt_test', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'content_type_exists', $result->get_error_code() );
	}

	/**
	 * Test content_type_exists() function.
	 */
	public function test_content_type_exists() {
		$this->assertFalse( content_type_exists( 'cpt_test' ) );

		register_content_type( 'cpt_test', array() );

		$this->assertTrue( content_type_exists( 'cpt_test' ) );
	}

	/**
	 * Test get_content_type_object() function.
	 */
	public function test_get_content_type_object() {
		$this->assertNull( get_content_type_object( 'cpt_test' ) );

		register_content_type( 'cpt_test', array() );

		$content_type = get_content_type_object( 'cpt_test' );
		$this->assertInstanceOf( 'WP_Content_Type', $content_type );
		$this->assertSame( 'cpt_test', $content_type->name );
	}

	/**
	 * Test get_content_types() function.
	 */
	public function test_get_content_types() {
		register_content_type( 'cpt_one', array() );
		register_content_type( 'cpt_two', array() );

		$types = get_content_types();
		$this->assertContains( 'cpt_one', $types );
		$this->assertContains( 'cpt_two', $types );

		$objects = get_content_types( array(), 'objects' );
		$this->assertArrayHasKey( 'cpt_one', $objects );
		$this->assertArrayHasKey( 'cpt_two', $objects );
		$this->assertInstanceOf( 'WP_Content_Type', $objects['cpt_one'] );
	}

	/**
	 * Test unregister_content_type() function.
	 */
	public function test_unregister_content_type() {
		register_content_type(
			'cpt_test',
			array(
				'fields' => array(
					'test_field' => array(
						'type' => 'string',
					),
				),
			)
		);

		$this->assertTrue( content_type_exists( 'cpt_test' ) );
		$this->assertTrue( post_type_exists( 'cpt_test' ) );

		$result = unregister_content_type( 'cpt_test' );

		$this->assertTrue( $result );
		$this->assertFalse( content_type_exists( 'cpt_test' ) );
		$this->assertFalse( post_type_exists( 'cpt_test' ) );
	}

	/**
	 * Test unregister_content_type() for non-existent type.
	 */
	public function test_unregister_content_type_not_exists() {
		$result = unregister_content_type( 'nonexistent' );

		$this->assertWPError( $result );
		$this->assertSame( 'content_type_not_exists', $result->get_error_code() );
	}

	/**
	 * Test get_content_type_fields() function.
	 */
	public function test_get_content_type_fields() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn' => array(
						'type'     => 'string',
						'required' => true,
					),
					'year' => array(
						'type' => 'integer',
					),
				),
			)
		);

		$fields = get_content_type_fields( 'book' );

		$this->assertArrayHasKey( 'isbn', $fields );
		$this->assertArrayHasKey( 'year', $fields );
		$this->assertSame( 'string', $fields['isbn']['type'] );
		$this->assertTrue( $fields['isbn']['required'] );
		$this->assertSame( 'integer', $fields['year']['type'] );
	}

	/**
	 * Test get_content_type_field() function.
	 */
	public function test_get_content_type_field() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn' => array(
						'type'        => 'string',
						'description' => 'International Standard Book Number',
					),
				),
			)
		);

		$field = get_content_type_field( 'book', 'isbn' );

		$this->assertIsArray( $field );
		$this->assertSame( 'string', $field['type'] );
		$this->assertSame( 'International Standard Book Number', $field['description'] );

		$nonexistent = get_content_type_field( 'book', 'nonexistent' );
		$this->assertNull( $nonexistent );
	}

	/**
	 * Test get_content_type_ui() function.
	 */
	public function test_get_content_type_ui() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn' => array( 'type' => 'string' ),
					'year' => array( 'type' => 'integer' ),
				),
				'ui'     => array(
					'editor_panel' => array(
						'title'  => 'Book Details',
						'fields' => array( 'isbn', 'year' ),
					),
				),
			)
		);

		$ui = get_content_type_ui( 'book' );

		$this->assertArrayHasKey( 'editor_panel', $ui );
		$this->assertSame( 'Book Details', $ui['editor_panel']['title'] );
		$this->assertSame( array( 'isbn', 'year' ), $ui['editor_panel']['fields'] );
	}

	/**
	 * Test field type validation.
	 */
	public function test_field_type_validation_invalid_type() {
		$result = register_content_type(
			'cpt_test',
			array(
				'fields' => array(
					'test_field' => array(
						'type' => 'invalid_type',
					),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_field_type', $result->get_error_code() );
	}

	/**
	 * Test that all valid field types are accepted.
	 *
	 * @dataProvider data_valid_field_types
	 *
	 * @param string $type Field type to test.
	 */
	public function test_valid_field_types( $type ) {
		$result = register_content_type(
			'cpt_' . $type,
			array(
				'fields' => array(
					'test_field' => array(
						'type' => $type,
					),
				),
			)
		);

		$this->assertInstanceOf( 'WP_Content_Type', $result );
	}

	/**
	 * Data provider for valid field types.
	 */
	public function data_valid_field_types() {
		return array(
			array( 'string' ),
			array( 'integer' ),
			array( 'number' ),
			array( 'boolean' ),
			array( 'array' ),
			array( 'object' ),
		);
	}

	/**
	 * Test field normalization with defaults.
	 */
	public function test_field_normalization() {
		register_content_type(
			'cpt_test',
			array(
				'fields' => array(
					'minimal_field' => array(),
				),
			)
		);

		$field = get_content_type_field( 'cpt_test', 'minimal_field' );

		$this->assertSame( 'string', $field['type'] );
		$this->assertTrue( $field['single'] );
		$this->assertTrue( $field['show_in_rest'] );
		$this->assertFalse( $field['required'] );
		$this->assertSame( 'Minimal Field', $field['label'] );
		$this->assertSame( 'text', $field['control'] );
	}

	/**
	 * Test show_in_rest defaults to true for content types.
	 */
	public function test_show_in_rest_default() {
		register_content_type( 'cpt_test', array() );

		$post_type = get_post_type_object( 'cpt_test' );
		$this->assertTrue( $post_type->show_in_rest );
	}

	/**
	 * Test enum field validation.
	 */
	public function test_enum_field_validation() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'genre' => array(
						'type' => 'string',
						'enum' => array( 'fiction', 'non-fiction', 'mystery' ),
					),
				),
			)
		);

		$content_type = get_content_type_object( 'book' );
		$field        = $content_type->get_field( 'genre' );

		$this->assertSame( array( 'fiction', 'non-fiction', 'mystery' ), $field['enum'] );
	}

	/**
	 * Test validate_content_type_values() function with valid values.
	 */
	public function test_validate_values_valid() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn' => array(
						'type'     => 'string',
						'required' => true,
					),
					'year' => array(
						'type' => 'integer',
					),
				),
			)
		);

		$result = validate_content_type_values(
			'book',
			array(
				'isbn' => '978-0-123456-78-9',
				'year' => 2024,
			)
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test validate_content_type_values() function with missing required field.
	 */
	public function test_validate_values_missing_required() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		$result = validate_content_type_values( 'book', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_required_field', $result->get_error_code() );
	}

	/**
	 * Test validate_content_type_values() function with invalid enum value.
	 */
	public function test_validate_values_invalid_enum() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'genre' => array(
						'type' => 'string',
						'enum' => array( 'fiction', 'non-fiction' ),
					),
				),
			)
		);

		$result = validate_content_type_values(
			'book',
			array(
				'genre' => 'invalid_genre',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_enum_value', $result->get_error_code() );
	}

	/**
	 * Test get_content_type_rest_schema() function.
	 */
	public function test_get_rest_schema() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn' => array(
						'type'        => 'string',
						'required'    => true,
						'description' => 'ISBN number',
					),
					'year' => array(
						'type' => 'integer',
					),
				),
			)
		);

		$schema = get_content_type_rest_schema( 'book' );

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'isbn', $schema['properties'] );
		$this->assertArrayHasKey( 'year', $schema['properties'] );
		$this->assertSame( 'string', $schema['properties']['isbn']['type'] );
		$this->assertSame( 'ISBN number', $schema['properties']['isbn']['description'] );
		$this->assertContains( 'isbn', $schema['required'] );
	}

	/**
	 * Test registered_content_type action hook.
	 */
	public function test_registered_content_type_action() {
		$action_called = false;
		$captured_args = array();

		add_action(
			'registered_content_type',
			function ( $content_type, $content_type_object, $args ) use ( &$action_called, &$captured_args ) {
				$action_called = true;
				$captured_args = compact( 'content_type', 'content_type_object', 'args' );
			},
			10,
			3
		);

		register_content_type(
			'cpt_test',
			array(
				'public' => true,
			)
		);

		$this->assertTrue( $action_called );
		$this->assertSame( 'cpt_test', $captured_args['content_type'] );
		$this->assertInstanceOf( 'WP_Content_Type', $captured_args['content_type_object'] );
		$this->assertTrue( $captured_args['args']['public'] );
	}

	/**
	 * Test register_content_type_args filter hook.
	 */
	public function test_register_content_type_args_filter() {
		add_filter(
			'register_content_type_args',
			function ( $args, $content_type ) {
				$args['description'] = 'Filtered description';
				return $args;
			},
			10,
			2
		);

		register_content_type( 'cpt_test', array() );

		$post_type = get_post_type_object( 'cpt_test' );
		$this->assertSame( 'Filtered description', $post_type->description );
	}

	/**
	 * Test WP_Content_Type::to_array() method.
	 */
	public function test_content_type_to_array() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn' => array( 'type' => 'string' ),
				),
				'ui'     => array(
					'editor_panel' => array( 'title' => 'Details' ),
				),
			)
		);

		$content_type = get_content_type_object( 'book' );
		$array        = $content_type->to_array();

		$this->assertSame( 'book', $array['name'] );
		$this->assertArrayHasKey( 'isbn', $array['fields'] );
		$this->assertArrayHasKey( 'editor_panel', $array['ui'] );
	}

	/**
	 * Test WP_Content_Type::get_required_fields() method.
	 */
	public function test_get_required_fields() {
		register_content_type(
			'book',
			array(
				'fields' => array(
					'isbn'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'year'   => array(
						'type'     => 'integer',
						'required' => false,
					),
					'author' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		$content_type = get_content_type_object( 'book' );
		$required     = $content_type->get_required_fields();

		$this->assertCount( 2, $required );
		$this->assertContains( 'isbn', $required );
		$this->assertContains( 'author', $required );
		$this->assertNotContains( 'year', $required );
	}

	/**
	 * Test sanitization callback for string field.
	 */
	public function test_sanitize_string_field() {
		$sanitized = WP_Content_Type::sanitize_by_type( '<script>alert("xss")</script>test', 'string' );
		$this->assertSame( 'test', $sanitized );
	}

	/**
	 * Test sanitization callback for integer field.
	 */
	public function test_sanitize_integer_field() {
		$sanitized = WP_Content_Type::sanitize_by_type( '42.9', 'integer' );
		$this->assertSame( 42, $sanitized );
	}

	/**
	 * Test sanitization callback for number field.
	 */
	public function test_sanitize_number_field() {
		$sanitized = WP_Content_Type::sanitize_by_type( '42.9', 'number' );
		$this->assertSame( 42.9, $sanitized );
	}

	/**
	 * Test sanitization callback for boolean field.
	 */
	public function test_sanitize_boolean_field() {
		$this->assertTrue( WP_Content_Type::sanitize_by_type( '1', 'boolean' ) );
		$this->assertTrue( WP_Content_Type::sanitize_by_type( 1, 'boolean' ) );
		$this->assertFalse( WP_Content_Type::sanitize_by_type( '0', 'boolean' ) );
		$this->assertFalse( WP_Content_Type::sanitize_by_type( 0, 'boolean' ) );
	}

	/**
	 * Test default control types for different field types.
	 */
	public function test_default_control_types() {
		register_content_type(
			'cpt_test',
			array(
				'fields' => array(
					'string_field'  => array( 'type' => 'string' ),
					'integer_field' => array( 'type' => 'integer' ),
					'number_field'  => array( 'type' => 'number' ),
					'boolean_field' => array( 'type' => 'boolean' ),
				),
			)
		);

		$fields = get_content_type_fields( 'cpt_test' );

		$this->assertSame( 'text', $fields['string_field']['control'] );
		$this->assertSame( 'number', $fields['integer_field']['control'] );
		$this->assertSame( 'number', $fields['number_field']['control'] );
		$this->assertSame( 'checkbox', $fields['boolean_field']['control'] );
	}

	/**
	 * Test complete book example from RFC.
	 */
	public function test_complete_book_example() {
		$result = register_content_type(
			'book',
			array(
				'labels'       => array(
					'name'          => 'Books',
					'singular_name' => 'Book',
				),
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'fields'       => array(
					'isbn'           => array(
						'type'         => 'string',
						'single'       => true,
						'required'     => true,
						'show_in_rest' => true,
						'label'        => 'ISBN',
						'control'      => 'text',
					),
					'published_year' => array(
						'type'         => 'integer',
						'single'       => true,
						'show_in_rest' => true,
						'label'        => 'Published Year',
						'control'      => 'number',
					),
				),
				'ui'           => array(
					'editor_panel' => array(
						'title'  => 'Book Details',
						'fields' => array( 'isbn', 'published_year' ),
					),
				),
			)
		);

		$this->assertInstanceOf( 'WP_Content_Type', $result );
		$this->assertTrue( post_type_exists( 'book' ) );

		$post_type = get_post_type_object( 'book' );
		$this->assertSame( 'Books', $post_type->labels->name );
		$this->assertTrue( $post_type->public );
		$this->assertTrue( $post_type->show_in_rest );

		$fields = get_content_type_fields( 'book' );
		$this->assertArrayHasKey( 'isbn', $fields );
		$this->assertArrayHasKey( 'published_year', $fields );
		$this->assertTrue( $fields['isbn']['required'] );
		$this->assertSame( 'integer', $fields['published_year']['type'] );

		$ui = get_content_type_ui( 'book' );
		$this->assertSame( 'Book Details', $ui['editor_panel']['title'] );
	}
}
