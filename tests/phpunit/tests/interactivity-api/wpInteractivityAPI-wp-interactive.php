<?php
/**
 * Unit tests covering the data_wp_interactive_processor functionality of the
 * WP_Interactivity_API class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @since 6.5.0
 *
 * @coversDefaultClass WP_Interactivity_API
 *
 * @group interactivity-api
 */
class Tests_WP_Interactivity_API_WP_Interactive extends WP_UnitTestCase {
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
		$this->interactivity->state( 'myPlugin', array( 'id' => 'some-id' ) );
		$this->interactivity->state( 'otherPlugin', array( 'id' => 'other-id' ) );
	}

	/**
	 * Invokes the `process_directives` method of WP_Interactivity_API class.
	 *
	 * @param string $html The HTML that needs to be processed.
	 * @return array An array containing an instance of the WP_HTML_Tag_Processor and the processed HTML.
	 */
	private function process_directives( $html ) {
		$new_html = $this->interactivity->process_directives( $html );
		$p        = new WP_HTML_Tag_Processor( $new_html );
		$p->next_tag( array( 'class_name' => 'test' ) );
		return array( $p, $new_html );
	}

	/**
	 * Tests that a default namespace is applied when using the
	 * `data-wp-interactive` directive with a json object.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_sets_a_default_namespace_with_object() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that a default namespace is applied when using the
	 * `data-wp-interactive` directive with a string.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_sets_a_default_namespace_with_string() {
		$html    = '
					<div data-wp-interactive="myPlugin">
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that the most recent `data-wp-interactive` directive replaces the
	 * previous default namespace.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_replaces_the_previous_default_namespace() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "otherPlugin" }\'>
							<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
									<div class="test" data-wp-bind--id="state.id">Text</div>
							</div>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$p->next_tag( array( 'class_name' => 'test' ) );
		$this->assertSame( 'other-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that a `data-wp-interactive` directive with a json object that
	 * doesn't have a namespace property does not replace the previously
	 * established default namespace.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_json_without_namespace_doesnt_replace_the_previous_default_namespace() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div data-wp-interactive=\'{}\'>
									<div class="test" data-wp-bind--id="state.id">Text</div>
							</div>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$p->next_tag( array( 'class_name' => 'test' ) );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that an empty value for `data-wp-interactive` does not replace the
	 * previously established default namespace.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_with_empty_value_doesnt_replace_the_previous_default_namespace() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div data-wp-interactive="">
									<div class="test" data-wp-bind--id="state.id">Text</div>
							</div>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$p->next_tag( array( 'class_name' => 'test' ) );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that an invalid value for `data-wp-interactive` does not replace the
	 * previously established default namespace.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_with_invalid_value_doesnt_replace_the_previous_default_namespace() {
		$html    = '
				<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
						<div data-wp-interactive="$myPlugin">
								<div class="test" data-wp-bind--id="state.id">Text</div>
						</div>
						<div class="test" data-wp-bind--id="state.id">Text</div>
				</div>
		';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$p->next_tag( array( 'class_name' => 'test' ) );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that a `data-wp-interactive` directive with no assigned value does
	 * not replace the previously established default namespace.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_without_value_doesnt_replace_the_previous_default_namespace() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div data-wp-interactive>
									<div class="test" data-wp-bind--id="state.id">Text</div>
							</div>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$p->next_tag( array( 'class_name' => 'test' ) );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that multiple `data-wp-interactive` directives work correctly.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_works_with_multiple_directives() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\' data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that a custom namespace can override the default one provided by a
	 * `data-wp-interactive` directive.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_namespace_can_be_override_by_custom_one() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div class="test" data-wp-bind--id="otherPlugin::state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'other-id', $p->get_attribute( 'id' ) );
	}

	/**
	 * Tests that the `data-wp-interactive` setting is reset appropriately after a
	 * closing HTML tag.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_interactive_set_is_unset_on_closing_tag() {
		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
					<div data-wp-interactive=\'{ "namespace": "otherPlugin" }\'>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
		$p->next_tag( array( 'class_name' => 'test' ) );
		$this->assertSame( 'other-id', $p->get_attribute( 'id' ) );

		$html    = '
					<div data-wp-interactive=\'{ "namespace": "myPlugin" }\'>
							<div data-wp-interactive=\'{ "namespace": "otherPlugin" }\'>
									<div class="test" data-wp-bind--id="state.id">Text</div>
							</div>
							<div class="test" data-wp-bind--id="state.id">Text</div>
					</div>
			';
		list($p) = $this->process_directives( $html );
		$this->assertSame( 'other-id', $p->get_attribute( 'id' ) );
		$p->next_tag( array( 'class_name' => 'test' ) );
		$this->assertSame( 'some-id', $p->get_attribute( 'id' ) );
	}
}
