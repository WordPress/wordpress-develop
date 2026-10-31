<?php

/**
 * @group formatting
 *
 * @covers ::recursively_sanitize
 */
class Tests_Formatting_RecursivelySanitize extends WP_UnitTestCase {

	/**
	 * @dataProvider data_recursively_sanitize
	 */
	public function test_recursively_sanitize( $input, $context, $expected ) {
		$this->assertEquals( $expected, recursively_sanitize( $input, $context ) );
	}

	public function data_recursively_sanitize() {
		return array(
			// Empty array.
			array( array(), 'auto', array() ),

			// Simple string.
			array( 'Text with <script>alert("xss")</script>', 'auto', 'Text with' ),

			// Array with sanitized keys and values.
			array(
				array(
					'text'            => 'Simple <b>text</b>',
					'key with spaces' => 'value',
				),
				'auto',
				array(
					'text'          => 'Simple text',
					'keywithspaces' => 'value',
				),
			),

			// Nested array.
			array(
				array(
					'level1' => array(
						'text' => 'Text with <script>alert("xss")</script>',
					),
				),
				'auto',
				array(
					'level1' => array(
						'text' => 'Text with',
					),
				),
			),

			// Object.
			array(
				(object) array(
					'text'            => 'Text with <script>alert("xss")</script>',
					'key with spaces' => 'value',
				),
				'auto',
				(object) array(
					'text'          => 'Text with',
					'keywithspaces' => 'value',
				),
			),

			// Mixed array and object.
			array(
				array(
					'user' => (object) array(
						'profile' => array(
							'bio' => 'Bio with <script>alert("xss")</script>',
						),
					),
				),
				'auto',
				array(
					'user' => (object) array(
						'profile' => array(
							'bio' => 'Bio with',
						),
					),
				),
			),

			// Primitive types.
			array( null, 'auto', null ),
			array( true, 'auto', true ),
			array( false, 'auto', false ),
			array( 42, 'auto', 42 ),

			// Context-specific sanitization.
			array(
				array( 'text' => "Multi-line\ntext" ),
				'text',
				array( 'text' => 'Multi-line text' ),
			),
			array(
				array( 'text' => "Multi-line\ntext" ),
				'textarea',
				array( 'text' => "Multi-line\ntext" ),
			),
		);
	}

	public function test_recursively_sanitize_should_prevent_infinite_recursion() {
		// Create a structure with many levels of nesting.
		$input = 'test';
		for ( $i = 0; $i < 60; $i++ ) {
			$input = array( 'level' => $input );
		}

		// This should not cause infinite recursion or fatal errors
		$result = recursively_sanitize( $input, 'auto' );

		// The function should handle deep nesting gracefully
		$this->assertTrue( is_array( $result ) || is_null( $result ) );
	}

	public function test_recursively_sanitize_filter() {
		$filter = new MockAction();
		add_filter( 'recursively_sanitize', array( $filter, 'filter' ) );

		recursively_sanitize( 'test string', 'auto' );

		$this->assertSame( 1, $filter->get_call_count() );

		remove_filter( 'recursively_sanitize', array( $filter, 'filter' ) );
	}
}
