<?php

/**
 * @group taxonomy
 *
 * @covers ::sanitize_term
 */
class Tests_Term_SanitizeTerm extends WP_UnitTestCase {

	/**
	 * Tests sanitize_term() inputs and outputs.
	 *
	 * @ticket 64238
	 * @dataProvider data_sanitize_term
	 *
	 * @param Closure(): (object|array<string, mixed>) $input_callback       Callback to get the term data.
	 * @param string                                   $context              Context in which to sanitize the term.
	 * @param string                                   $expected_description Expected sanitized description.
	 */
	public function test_sanitize_term( Closure $input_callback, string $context, string $expected_description ): void {
		$input    = $input_callback();
		$taxonomy = 'category';

		$sanitized = sanitize_term( $input, $taxonomy, $context );

		if ( is_object( $input ) ) {
			$this->assertInstanceOf( stdClass::class, $sanitized );
			$this->assertSame( $context, $sanitized->filter );
			if ( isset( $input->description ) ) {
				$this->assertSame( $expected_description, $sanitized->description );
			}
		} else {
			$this->assertIsArray( $sanitized );
			$this->assertSame( $context, $sanitized['filter'] );
			if ( isset( $input['description'] ) ) {
				$this->assertSame( $expected_description, $sanitized['description'] );
			}
		}
	}

	/**
	 * Data provider for test_sanitize_term.
	 *
	 * @return array<string, array{
	 *     input_callback: Closure(): (object|array<string, mixed>),
	 *     context: string,
	 *     expected_description: string,
	 * }>
	 */
	public function data_sanitize_term(): array {
		$description = 'Test <script>console.log("Hello")</script> Description';

		return array(
			'Object with term_id, edit context'    => array(
				'input_callback'       => fn() => (object) array(
					'term_id'     => 123,
					'name'        => 'Test Term',
					'description' => $description,
				),
				'context'              => 'edit',
				'expected_description' => esc_html( $description ),
			),
			'Object without term_id, edit context' => array(
				'input_callback'       => fn() => (object) array(
					'name'        => 'Test Term',
					'description' => $description,
				),
				'context'              => 'edit',
				'expected_description' => esc_html( $description ),
			),
			'Array with term_id, edit context'     => array(
				'input_callback'       => fn() => array(
					'term_id'     => 123,
					'name'        => 'Test Term',
					'description' => $description,
				),
				'context'              => 'edit',
				'expected_description' => esc_html( $description ),
			),
			'Array without term_id, edit context'  => array(
				'input_callback'       => fn() => array(
					'name'        => 'Test Term',
					'description' => $description,
				),
				'context'              => 'edit',
				'expected_description' => esc_html( $description ),
			),
			'Raw context'                          => array(
				'input_callback'       => fn() => (object) array(
					'term_id'     => 123,
					'description' => $description,
				),
				'context'              => 'raw',
				'expected_description' => $description,
			),
			'Display context'                      => array(
				'input_callback'       => fn() => (object) array(
					'term_id'     => 123,
					'description' => $description,
				),
				'context'              => 'display',
				'expected_description' => "<p>$description</p>\n",
			),
			'Attribute context'                    => array(
				'input_callback'       => fn() => (object) array(
					'term_id'     => 123,
					'description' => $description,
				),
				'context'              => 'attribute',
				'expected_description' => esc_attr( "<p>$description</p>\n" ),
			),
			'JS context'                           => array(
				'input_callback'       => fn() => (object) array(
					'term_id'     => 123,
					'description' => $description,
				),
				'context'              => 'js',
				'expected_description' => esc_js( "<p>$description</p>\n" ),
			),
		);
	}
}
