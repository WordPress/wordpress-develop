<?php
/**
 * Unit tests covering the data_wp_bind_processor functionality of the
 * WP_Interactivity_API class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @coversDefaultClass WP_Interactivity_API
 *
 * @since 6.5.0
 *
 * @group interactivity-api
 */
class Tests_WP_Interactivity_API_WP_Bind extends WP_UnitTestCase {
	/**
	 * Instance of WP_Interactivity_API.
	 *
	 * @var WP_Interactivity_API
	 */
	protected $interactivity;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactivity = new WP_Interactivity_API();
		$this->interactivity->state(
			'myPlugin',
			array(
				'id'          => 'some-id',
				'width'       => 100,
				'isOpen'      => false,
				'null'        => null,
				'trueString'  => 'true',
				'falseString' => 'false',
				'trueValue'   => true,
				'falseValue'  => false,
			)
		);
	}

	/**
	 * Invokes the `process_directives` method of WP_Interactivity_API class.
	 *
	 * @param string $html The HTML that needs to be processed.
	 * @return array{ 0: WP_HTML_Tag_Processor, 1: string } An array containing an instance of the WP_HTML_Tag_Processor and the processed HTML.
	 */
	private function process_directives( string $html ): array {
		$new_html = $this->interactivity->process_directives( $html );
		$p        = new WP_HTML_Tag_Processor( $new_html );
		$p->next_tag();
		return array( $p, $new_html );
	}

	/**
	 * Tests setting an attribute via `data-wp-bind`.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_sets_attribute() {
		$html    = '<div data-wp-bind--id="myPlugin::state.id">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests replacing an existing attribute via `data-wp-bind`.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_replaces_attribute() {
		$html    = '<div id="other-id" data-wp-bind--id="myPlugin::state.id">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests setting a numerical value as an attribute via `data-wp-bind`.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_sets_number_value() {
		$html    = '<img data-wp-bind--width="myPlugin::state.width">';
		list($p) = $this->process_directives( $html );
		$this->assertSame( '100', $p->get_attribute( 'width' ) );
	}

	/**
	 * Tests that a float value is formatted as a string when set as an attribute
	 * via `data-wp-bind`.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_sets_float_value() {
		$this->interactivity->state( 'myPlugin', array( 'ratio' => 1.5 ) );

		$html               = '<div data-wp-bind--data-ratio="myPlugin::state.ratio">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( '1.5', $p->get_attribute( 'data-ratio' ) );
		$this->assertSame( '<div data-ratio="1.5" data-wp-bind--data-ratio="myPlugin::state.ratio">Text</div>', $new_html );
	}

	/**
	 * Tests that a float value is not formatted with the locale's decimal separator.
	 *
	 * Casting a float to string is locale-dependent before PHP 8.0, whereas the
	 * client receives the number from the JSON-encoded store, which never is.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_sets_float_value_independently_of_the_locale() {
		$previous_locale = setlocale( LC_NUMERIC, '0' ); // Passing "0" queries the current setting without changing it.
		if ( false === setlocale( LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'de_DE@euro', 'German' ) ) {
			$this->markTestSkipped( 'No locale with a comma decimal separator is available.' );
		}

		try {
			$this->interactivity->state( 'myPlugin', array( 'ratio' => 1.5 ) );

			$html    = '<div data-wp-bind--data-ratio="myPlugin::state.ratio">Text</div>';
			list($p) = $this->process_directives( $html );
			$this->assertSame( '1.5', $p->get_attribute( 'data-ratio' ) );
		} finally {
			setlocale( LC_NUMERIC, false === $previous_locale ? 'C' : $previous_locale );
		}
	}

	/**
	 * Tests that true strings are set properly as attribute values.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_sets_true_string() {
		$html               = '<div data-wp-bind--id="myPlugin::state.trueString">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'true', $p->get_attribute( 'id' ) );
		$this->assertSame( '<div id="true" data-wp-bind--id="myPlugin::state.trueString">Text</div>', $new_html );
	}

	/**
	 * Tests that false strings are set properly as attribute values.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_sets_false_string() {
		$html               = '<div data-wp-bind--id="myPlugin::state.falseString">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'false', $p->get_attribute( 'id' ) );
		$this->assertSame( '<div id="false" data-wp-bind--id="myPlugin::state.falseString">Text</div>', $new_html );
	}

	/**
	 * Tests that `data-wp-bind` ignores directives with no suffix.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_ignores_empty_bound_attribute() {
		$html     = '<div data-wp-bind="myPlugin::state.id">Text</div>';
		$new_html = $this->interactivity->process_directives( $html );
		$this->assertSame( $html, $new_html );
	}

	/**
	 * Tests that `data-wp-bind` ignores directives with no suffix but still
	 * processes valid bind directives on the same element.
	 *
	 * @ticket 64518
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_ignores_empty_suffix_but_processes_valid_binds() {
		$html    = '<div data-wp-bind="myPlugin::state.id" data-wp-bind--id="myPlugin::state.id">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that `data-wp-bind` does nothing when referencing non-existent
	 * references.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_doesnt_do_anything_on_non_existent_references() {
		$html     = '<div data-wp-bind--id="myPlugin::state.nonExistengKey">Text</div>';
		$new_html = $this->interactivity->process_directives( $html );
		$this->assertSame( $html, $new_html );
	}

	/**
	 * Tests that `data-wp-bind` ignores directives with empty values.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 * @expectedIncorrectUsage WP_Interactivity_API::evaluate
	 */
	public function test_wp_bind_ignores_empty_value() {
		$html     = '<div data-wp-bind--id="">Text</div>';
		$new_html = $this->interactivity->process_directives( $html );
		$this->assertSame( $html, $new_html );
	}

	/**
	 * Tests that `data-wp-bind` ignores directives without values.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 * @expectedIncorrectUsage WP_Interactivity_API::evaluate
	 */
	public function test_wp_bind_ignores_without_value() {
		$html     = '<div data-wp-bind--id>Text</div>';
		$new_html = $this->interactivity->process_directives( $html );
		$this->assertSame( $html, $new_html );
	}

	/**
	 * Tests that `data-wp-bind` works with multiple instances of the same
	 * directive on a tag.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_works_with_multiple_same_directives() {
		$html    = '<div data-wp-bind--id="myPlugin::state.id" data-wp-bind--id="myPlugin::state.id">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that `data-wp-bind` works with multiple instances of different
	 * directives on a tag.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_works_with_multiple_different_directives() {
		$html    = '<img data-wp-bind--id="myPlugin::state.id" data-wp-bind--width="myPlugin::state.width">';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$this->assertSame( '100', $p->get_attribute( 'width' ) );
	}

	/**
	 * Tests adding boolean attributes to a tag using `data-wp-bind`.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_adds_boolean_attribute_if_true() {
		$html               = '<div data-wp-bind--hidden="myPlugin::!state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertTrue( $p->get_attribute( 'hidden' ) );
		$this->assertSame( '<div hidden data-wp-bind--hidden="myPlugin::!state.isOpen">Text</div>', $new_html );
	}

	/**
	 * Tests replacing a pre-existing boolean attribute on a tag using
	 * `data-wp-bind`.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_replaces_existing_attribute_if_true() {
		$html               = '<div hidden="true" data-wp-bind--hidden="myPlugin::!state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertTrue( $p->get_attribute( 'hidden' ) );
		$this->assertSame( '<div hidden data-wp-bind--hidden="myPlugin::!state.isOpen">Text</div>', $new_html );
	}

	/**
	 * Tests that boolean attributes are not added when bound to false or null
	 * values.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_doesnt_add_boolean_attribute_if_false_or_null() {
		$html               = '<div data-wp-bind--hidden="myPlugin::state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'hidden' ) );
		$this->assertSame( $html, $new_html );

		$html               = '<div data-wp-bind--hidden="myPlugin::state.null">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'hidden' ) );
		$this->assertSame( $html, $new_html );
	}

	/**
	 * Tests removing boolean attributes from a tag using `data-wp-bind` and a
	 * false or null value.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_removes_boolean_attribute_if_false_or_null() {
		$html    = '<div hidden data-wp-bind--hidden="myPlugin::state.isOpen">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'hidden' ) );

		$html    = '<div hidden data-wp-bind--hidden="myPlugin::state.null">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'hidden' ) );
	}

	/**
	 * Tests adding values to aria or data attributes when the condition evaluates
	 * to true.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_adds_value_if_true_in_aria_or_data_attributes() {
		$html               = '<div data-wp-bind--aria-hidden="myPlugin::!state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'true', $p->get_attribute( 'aria-hidden' ) );
		$this->assertSame( '<div aria-hidden="true" data-wp-bind--aria-hidden="myPlugin::!state.isOpen">Text</div>', $new_html );

		$html               = '<div data-wp-bind--data-is-closed="myPlugin::!state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'true', $p->get_attribute( 'data-is-closed' ) );
		$this->assertSame( '<div data-is-closed="true" data-wp-bind--data-is-closed="myPlugin::!state.isOpen">Text</div>', $new_html );
	}

	/**
	 * Tests replacing values in aria or data attributes when the condition
	 * evaluates to true.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_replaces_value_if_true_in_aria_or_data_attributes() {
		$html               = '<div aria-hidden="false" data-wp-bind--aria-hidden="myPlugin::!state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'true', $p->get_attribute( 'aria-hidden' ) );
		$this->assertSame( '<div aria-hidden="true" data-wp-bind--aria-hidden="myPlugin::!state.isOpen">Text</div>', $new_html );

		$html     = '<div data-is-closed="false" data-wp-bind--data-is-closed="myPlugin::!state.isOpen">Text</div>';
		$new_html = $this->interactivity->process_directives( $html );
		$p        = new WP_HTML_Tag_Processor( $new_html );
		$p->next_tag();
		$this->assertSame( 'true', $p->get_attribute( 'data-is-closed' ) );
		$this->assertSame( '<div data-is-closed="true" data-wp-bind--data-is-closed="myPlugin::!state.isOpen">Text</div>', $new_html );
	}

	/**
	 * Tests adding the value 'false' to aria or data attributes when the
	 * condition evaluates to false.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_adds_value_if_false_in_aria_or_data_attributes() {
		$html               = '<div data-wp-bind--aria-hidden="myPlugin::state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'false', $p->get_attribute( 'aria-hidden' ) );
		$this->assertSame( '<div aria-hidden="false" data-wp-bind--aria-hidden="myPlugin::state.isOpen">Text</div>', $new_html );

		$html               = '<div data-wp-bind--data-is-closed="myPlugin::state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'false', $p->get_attribute( 'data-is-closed' ) );
		$this->assertSame( '<div data-is-closed="false" data-wp-bind--data-is-closed="myPlugin::state.isOpen">Text</div>', $new_html );
	}

	/**
	 * Tests replacing values in aria or data attributes when the condition
	 * evaluates to false.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_replaces_value_if_false_in_aria_or_data_attributes() {
		$html               = '<div aria-hidden="true" data-wp-bind--aria-hidden="myPlugin::state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'false', $p->get_attribute( 'aria-hidden' ) );
		$this->assertSame( '<div aria-hidden="false" data-wp-bind--aria-hidden="myPlugin::state.isOpen">Text</div>', $new_html );

		$html               = '<div data-is-closed="true" data-wp-bind--data-is-closed="myPlugin::state.isOpen">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertSame( 'false', $p->get_attribute( 'data-is-closed' ) );
		$this->assertSame( '<div data-is-closed="false" data-wp-bind--data-is-closed="myPlugin::state.isOpen">Text</div>', $new_html );
	}

	/**
	 * Tests removing values from aria or data attributes when the value is null.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_removes_value_if_null_in_aria_or_data_attributes() {
		$html    = '<div aria-hidden="true" data-wp-bind--aria-hidden="myPlugin::state.null">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'aria-hidden' ) );

		$html    = '<div data-is-closed="true" data-wp-bind--data-is-closed="myPlugin::state.null">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'data-is-closed' ) );
	}

	/**
	 * Tests handling of bindings within nested tags.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_handles_nested_bindings() {
		$html    = '<div data-wp-bind--id="myPlugin::state.id"><img data-wp-bind--width="myPlugin::state.width"></div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$p->next_tag();
		$this->assertSame( '100', $p->get_attribute( 'width' ) );
	}

	/**
	 * Tests handling bindings to boolean values.
	 *
	 * @ticket 60758
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_handles_true_value() {
		$html    = '<div data-wp-bind--id="myPlugin::state.trueValue"></div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( true, $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests ignores unique IDs in bind directive.
	 *
	 * @ticket 64106
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_ignores_unique_ids() {
		$html    = '<div data-wp-bind--id="myPlugin::state.trueValue"></div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( true, $p->get_attribute( 'id' ) );

		$html    = '<div data-wp-bind--id---unique-id="myPlugin::state.trueValue"></div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'id' ) );
		$this->assertNull( $p->get_attribute( 'id---unique-id' ) );
	}

	/**
	 * Tests that `data-wp-bind` ignores directives with unique IDs but still
	 * processes valid bind directives on the same element.
	 *
	 * @ticket 64518
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_ignores_unique_id_but_processes_valid_binds() {
		$html    = '<div data-wp-bind--id---unique-id="myPlugin::state.id" data-wp-bind--id="myPlugin::state.id">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Data provider for float values which JSON cannot represent.
	 *
	 * @return array<non-empty-string, array{ value: float }> Data provider.
	 */
	public function data_non_finite_values(): array {
		return array(
			'INF'  => array( 'value' => INF ),
			'-INF' => array( 'value' => -INF ),
			'NAN'  => array( 'value' => NAN ),
		);
	}

	/**
	 * Tests that `data-wp-bind` rejects INF and NAN.
	 *
	 * These are scalars, but a store holding one fails to encode in its
	 * entirety, so the client is sent no state at all rather than a value which
	 * merely disagrees with the server.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_non_finite_values
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_bind_processor
	 *
	 * @param float $value Non-finite value to bind.
	 */
	public function test_wp_bind_rejects_non_finite_value( $value ) {
		$this->interactivity->state( 'myPlugin', array( 'nonFinite' => $value ) );

		$html    = '<div data-wp-bind--data-ratio="myPlugin::state.nonFinite">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'data-ratio' ), 'Expected no attribute to have been set for a value JSON cannot represent.' );
		$this->assertSame(
			array(
				'WP_Interactivity_API::data_wp_bind_processor' => 'Attempted to bind a non-finite number to the "data-ratio" attribute. Ensure the state/context property or the derived state closure resolves to a finite number or a string. (This message was added in version 7.1.0.)',
			),
			$this->caught_doing_it_wrong,
			'Expected _doing_it_wrong() to have been called once with the non-finite value message.'
		);
	}

	/**
	 * Data provider for values a bound object may serialize to.
	 *
	 * @return array<non-empty-string, array{ value: mixed, expected: string }> Data provider.
	 */
	public function data_json_serializable_values(): array {
		return array(
			'string'  => array(
				'value'    => 'serialized-form',
				'expected' => 'serialized-form',
			),
			'integer' => array(
				'value'    => 42,
				'expected' => '42',
			),
			'float'   => array(
				'value'    => 1.5,
				'expected' => '1.5',
			),
			/*
			 * The JSON encoder keeps resolving a serializable object which serializes to another one, so the
			 * value bound on the server has to follow it all the way down to match what the client receives.
			 */
			'nested'  => array(
				'value'    => $this->get_json_serializable( 'serialized-form' ),
				'expected' => 'serialized-form',
			),
		);
	}

	/**
	 * Tests that an object is bound as whatever it serializes to for the client.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_json_serializable_values
	 *
	 * @param mixed  $value    Value the object serializes to.
	 * @param string $expected Expected attribute value.
	 */
	public function test_wp_bind_sets_json_serializable_value( $value, string $expected ) {
		$this->interactivity->state( 'myPlugin', array( 'serializable' => $this->get_json_serializable( $value ) ) );

		$html    = '<div data-wp-bind--id="myPlugin::state.serializable">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( $expected, $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that the bound attribute value matches what the client is sent.
	 *
	 * This is what makes serializable objects safe to bind: the value rendered
	 * into the attribute is the same one the client store is hydrated with, so
	 * evaluating the directive again in the browser is a no-op.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_json_serializable_value_matches_the_client_store() {
		$this->interactivity->state( 'myPlugin', array( 'serializable' => $this->get_json_serializable( 'serialized-form' ) ) );

		$html    = '<div data-wp-bind--id="myPlugin::state.serializable">Text</div>';
		list($p) = $this->process_directives( $html );

		$data    = $this->interactivity->filter_script_module_interactivity_data( array() );
		$encoded = wp_json_encode( $data['state'] );
		$this->assertIsString( $encoded, 'Expected the client state to be encodable as JSON.' );

		$this->assertSame( 'serialized-form', $p->get_attribute( 'id' ) );
		$this->assertStringContainsString(
			'"serializable":"serialized-form"',
			$encoded,
			'Expected the rendered attribute value to match the value sent to the client.'
		);
	}

	/**
	 * Tests that a bound object which cannot be serialized does not abort the render.
	 *
	 * `JsonSerializable::jsonSerialize()` is arbitrary code, so resolving an object
	 * through the JSON encoder can throw. A binding must not be able to take down the
	 * page, which is the whole point of checking the value at all. An exception
	 * escaping the directive processor would also leave the context and namespace
	 * stacks unrestored, breaking every later `process_directives()` call on the same
	 * instance.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_bind_processor
	 */
	public function test_wp_bind_rejects_object_which_fails_to_serialize() {
		$unserializable = new class() implements JsonSerializable {
			/**
			 * Fails to produce a value for the client.
			 *
			 * @return mixed Never returns.
			 * @throws RuntimeException Always.
			 */
			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				throw new RuntimeException( 'This object cannot be serialized.' );
			}
		};

		$this->interactivity->state(
			'myPlugin',
			array(
				'unserializable' => $unserializable,
				'id'             => 'some-id',
			)
		);

		$html    = '<div data-wp-bind--id="myPlugin::state.unserializable">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'id' ), 'Expected no attribute to have been set for an object which cannot be serialized.' );
		$this->assertSame(
			array(
				'WP_Interactivity_API::data_wp_bind_processor' => 'Attempted to bind a non-scalar value to the "id" attribute. Ensure the state/context property or the derived state closure resolves to a string, number, or boolean. (This message was added in version 7.1.0.)',
			),
			$this->caught_doing_it_wrong,
			'Expected _doing_it_wrong() to have been called once with the non-scalar value message.'
		);

		// The stacks are restored for the next render only if no exception escaped.
		$html    = '<div data-wp-bind--id="myPlugin::state.id">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ), 'Expected a later render on the same instance to be unaffected.' );
	}

	/**
	 * Tests that an object serializing to a value JSON cannot represent is rejected.
	 *
	 * The encoding does not fail here the way it does for a bare INF. When
	 * `json_encode()` rejects the value, `wp_json_encode()` retries with a plain
	 * object rebuilt from the public properties, which discards `jsonSerialize()`
	 * entirely and encodes to `{}`. The object therefore resolves to something
	 * non-scalar and is reported as such, rather than with the non-finite message.
	 *
	 * The store is rebuilt the same way, so the client is sent `{}` for this
	 * reference. The two still agree that there is no usable value here.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_bind_processor
	 */
	public function test_wp_bind_rejects_object_serializing_to_a_non_finite_value() {
		$this->interactivity->state( 'myPlugin', array( 'nonFinite' => $this->get_json_serializable( INF ) ) );

		$html    = '<div data-wp-bind--id="myPlugin::state.nonFinite">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'id' ), 'Expected no attribute to have been set.' );
		$this->assertSame(
			array(
				'WP_Interactivity_API::data_wp_bind_processor' => 'Attempted to bind a non-scalar value to the "id" attribute. Ensure the state/context property or the derived state closure resolves to a string, number, or boolean. (This message was added in version 7.1.0.)',
			),
			$this->caught_doing_it_wrong,
			'Expected the non-scalar message, since the object resolves to an empty object rather than failing to encode.'
		);

		$data    = $this->interactivity->filter_script_module_interactivity_data( array() );
		$encoded = wp_json_encode( $data['state'] );
		$this->assertIsString( $encoded, 'Expected the client state to still be encodable as JSON.' );
		$this->assertStringContainsString(
			'"nonFinite":{}',
			$encoded,
			'Expected the client to be sent the same empty object the server resolved.'
		);
	}

	/**
	 * Tests that an object serializing to null removes the attribute quietly.
	 *
	 * The object is resolved before the null check, so it reaches it as the null
	 * the client will receive, and is treated the same as a null value would be.
	 * There is nothing to report: null is a value the client can be sent.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_removes_attribute_for_object_serializing_to_null() {
		$this->interactivity->state( 'myPlugin', array( 'nothing' => $this->get_json_serializable( null ) ) );

		$html               = '<div id="other-id" data-wp-bind--id="myPlugin::state.nothing">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'id' ), 'Expected the pre-existing attribute to have been removed.' );
		$this->assertEqualHTML( '<div data-wp-bind--id="myPlugin::state.nothing">Text</div>', $new_html );
		$this->assertSame(
			array(),
			$this->caught_doing_it_wrong,
			'Expected an object serializing to null to be treated as a null value, without reporting a usage error.'
		);
	}

	/**
	 * Tests that an object serializing to a boolean keeps the boolean attribute
	 * semantics of the value it serializes to.
	 *
	 * Resolving the object first means the checks below it do not have to know an
	 * object was ever involved, so the existing handling composes. This asserts
	 * that it does.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_applies_boolean_semantics_to_object_serializing_to_a_boolean() {
		$this->interactivity->state(
			'myPlugin',
			array(
				'yes' => $this->get_json_serializable( true ),
				'no'  => $this->get_json_serializable( false ),
			)
		);

		// True sets a bare boolean attribute.
		$html               = '<div data-wp-bind--hidden="myPlugin::state.yes">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertTrue( $p->get_attribute( 'hidden' ) );
		$this->assertSame( '<div hidden data-wp-bind--hidden="myPlugin::state.yes">Text</div>', $new_html );

		// False removes it.
		$html               = '<div hidden data-wp-bind--hidden="myPlugin::state.no">Text</div>';
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( 'hidden' ) );
		$this->assertEqualHTML( '<div data-wp-bind--hidden="myPlugin::state.no">Text</div>', $new_html );

		// On a `data-` or `aria-` attribute it becomes the string Preact would write.
		$html    = '<div data-wp-bind--data-open="myPlugin::state.yes">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'true', $p->get_attribute( 'data-open' ) );

		$html    = '<div data-wp-bind--aria-hidden="myPlugin::state.no">Text</div>';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'false', $p->get_attribute( 'aria-hidden' ) );
	}

	/**
	 * Tests that a bound number is written the same way the client store writes it.
	 *
	 * This is the invariant the number formatting exists for. A cast would round to
	 * `precision` where the store uses `serialize_precision`, so both are rendered
	 * by the same encoder instead of being compared after the fact.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_bind_number_value_matches_the_client_store() {
		$this->interactivity->state( 'myPlugin', array( 'ratio' => 1 / 3 ) );

		$html    = '<div data-wp-bind--data-ratio="myPlugin::state.ratio">Text</div>';
		list($p) = $this->process_directives( $html );

		$data    = $this->interactivity->filter_script_module_interactivity_data( array() );
		$encoded = wp_json_encode( $data['state'] );
		$this->assertIsString( $encoded, 'Expected the client state to be encodable as JSON.' );

		$expected = wp_json_encode( 1 / 3 );
		$this->assertSame( $expected, $p->get_attribute( 'data-ratio' ) );
		$this->assertStringContainsString(
			'"ratio":' . $expected,
			$encoded,
			'Expected the rendered attribute value to match the number sent to the client.'
		);
	}

	/**
	 * Creates an object which serializes to the given value for the client.
	 *
	 * @param mixed $value Value the object serializes to.
	 * @return JsonSerializable Object serializing to `$value`.
	 */
	private function get_json_serializable( $value ): JsonSerializable {
		return new class( $value ) implements JsonSerializable {
			/**
			 * Value the object serializes to.
			 *
			 * @var mixed
			 */
			private $value;

			/**
			 * Constructor.
			 *
			 * @param mixed $value Value the object serializes to.
			 */
			public function __construct( $value ) {
				$this->value = $value;
			}

			/**
			 * Returns the value for JSON serialization.
			 *
			 * @return mixed Value the client receives.
			 */
			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return $this->value;
			}
		};
	}

	/**
	 * Data provider for values which cannot be stored in an attribute value.
	 *
	 * WP_HTML_Tag_Processor::set_attribute() escapes an ordinary attribute with
	 * strtr() and one of the URI attributes listed by wp_kses_uri_attributes()
	 * with esc_url(). Neither should be reached with a non-scalar value, so each
	 * value is paired with one attribute at a time: a regression in one of those
	 * paths then cannot be masked by the other failing first.
	 *
	 * @return array<non-empty-string, array{ value: mixed, tag_name: non-empty-string, attribute: non-empty-string, existing_value: non-empty-string }> Data provider.
	 */
	public function data_non_scalar_values(): array {
		$values = array(
			'list'                                      => array( 'a', 'b' ),
			'associative array'                         => array( 'a' => 'b' ),
			'empty array'                               => array(),
			'object'                                    => new stdClass(),
			'stringable object'                         => new class() {
				/**
				 * Returns the string representation.
				 *
				 * @return string String representation.
				 */
				public function __toString() {
					return 'stringified';
				}
			},
			'stringable object serializing to an array' => new class() implements JsonSerializable {
				/**
				 * Returns the string representation.
				 *
				 * @return string String representation.
				 */
				public function __toString() {
					return 'stringified';
				}

				/**
				 * Returns the value for JSON serialization.
				 *
				 * @return array<string, string> Value the client receives.
				 */
				#[\ReturnTypeWillChange]
				public function jsonSerialize() {
					return array( 'not' => 'the string representation' );
				}
			},
			'object serializing to an array'            => $this->get_json_serializable( array( 'a', 'b' ) ),
		);

		$attributes = array(
			'ordinary attribute' => array(
				'tag_name'       => 'div',
				'attribute'      => 'id',
				'existing_value' => 'other-id',
			),
			'URI attribute'      => array(
				'tag_name'       => 'a',
				'attribute'      => 'href',
				'existing_value' => 'https://example.com/',
			),
		);

		$data = array();
		foreach ( $values as $value_label => $value ) {
			foreach ( $attributes as $attribute_label => $attribute ) {
				$data[ "$value_label in $attribute_label" ] = array( 'value' => $value ) + $attribute;
			}
		}
		return $data;
	}

	/**
	 * Tests that `data-wp-bind` rejects non-scalar values instead of passing
	 * them along to WP_HTML_Tag_Processor::set_attribute().
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_non_scalar_values
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_bind_processor
	 *
	 * @param mixed  $value          Non-scalar value to bind.
	 * @param string $tag_name       Tag name to bind the value on.
	 * @param string $attribute      Attribute name to bind the value to.
	 * @param string $existing_value Pre-existing value for the bound attribute. Unused, as the attribute is absent here.
	 */
	public function test_wp_bind_rejects_non_scalar_value( $value, string $tag_name, string $attribute, string $existing_value ) {
		unset( $existing_value ); // The bound attribute is absent here, so there is no pre-existing value to remove.

		$this->interactivity->state( 'myPlugin', array( 'nonScalar' => $value ) );

		$html               = sprintf( '<%1$s data-wp-bind--%2$s="myPlugin::state.nonScalar">Text</%1$s>', $tag_name, $attribute );
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( $attribute ), "Expected no $attribute attribute to have been set for a non-scalar value." );
		$this->assertSame( $html, $new_html, 'Expected the markup to be left unchanged.' );
		$this->assertSame(
			array(
				'WP_Interactivity_API::data_wp_bind_processor' => sprintf(
					'Attempted to bind a non-scalar value to the "%s" attribute. Ensure the state/context property or the derived state closure resolves to a string, number, or boolean. (This message was added in version 7.1.0.)',
					$attribute
				),
			),
			$this->caught_doing_it_wrong,
			'Expected _doing_it_wrong() to have been called once with the non-scalar value message.'
		);
	}

	/**
	 * Tests that `data-wp-bind` removes a pre-existing attribute when the
	 * evaluated value is non-scalar.
	 *
	 * @ticket 65740
	 *
	 * @covers ::process_directives
	 *
	 * @dataProvider data_non_scalar_values
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_bind_processor
	 *
	 * @param mixed  $value          Non-scalar value to bind.
	 * @param string $tag_name       Tag name to bind the value on.
	 * @param string $attribute      Attribute name to bind the value to.
	 * @param string $existing_value Pre-existing value for the bound attribute.
	 */
	public function test_wp_bind_removes_existing_attribute_for_non_scalar_value( $value, string $tag_name, string $attribute, string $existing_value ) {
		$this->interactivity->state( 'myPlugin', array( 'nonScalar' => $value ) );

		$html               = sprintf( '<%1$s %2$s="%3$s" data-wp-bind--%2$s="myPlugin::state.nonScalar">Text</%1$s>', $tag_name, $attribute, $existing_value );
		list($p, $new_html) = $this->process_directives( $html );
		$this->assertNull( $p->get_attribute( $attribute ), "Expected the pre-existing $attribute attribute to have been removed." );
		$this->assertEqualHTML( sprintf( '<%1$s data-wp-bind--%2$s="myPlugin::state.nonScalar">Text</%1$s>', $tag_name, $attribute ), $new_html );
		$this->assertSame(
			array(
				'WP_Interactivity_API::data_wp_bind_processor' => sprintf(
					'Attempted to bind a non-scalar value to the "%s" attribute. Ensure the state/context property or the derived state closure resolves to a string, number, or boolean. (This message was added in version 7.1.0.)',
					$attribute
				),
			),
			$this->caught_doing_it_wrong,
			'Expected _doing_it_wrong() to have been called once with the non-scalar value message.'
		);
	}
}
