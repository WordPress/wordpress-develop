<?php
/**
 * Unit tests covering WP_Interactivity_API extensibility.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @since 6.9.0
 *
 * @group interactivity-api
 *
 * @coversDefaultClass WP_Interactivity_API
 */
class Tests_Interactivity_API_WpInteractivityAPIExtensibility extends WP_UnitTestCase {

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
		$this->interactivity = new class() extends WP_Interactivity_API {
			/**
			 * Processes the `data-wp-show` directive.
			 *
			 * @param WP_Interactivity_API_Directives_Processor $p    The directives processor.
			 * @param string                                    $mode Processing mode ('enter'|'exit').
			 */
			public function show_processor( $p, $mode ) {
				if ( 'enter' !== $mode ) {
					return;
				}
				$entries = $this->get_directive_entries( $p, 'show' );
				if ( empty( $entries ) ) {
					return;
				}
				$resolved = $this->evaluate( $entries[0] );
				if ( $resolved ) {
					$p->remove_attribute( 'style' );
				} else {
					$p->set_attribute( 'style', 'display: none' );
				}
			}
		};
		wp_default_script_modules();
		$this->interactivity->add_hooks();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		global $wp_script_modules;
		parent::tear_down();
		$wp_script_modules = null;
	}

	/*
	 * ── Subclass: override evaluate() ─────────────────────────────────
	 *
	 * These tests verify that a subclass CAN override evaluate() and that
	 * the override is invoked during directive processing. No expression
	 * evaluation logic is implemented — just a proof of mechanism.
	 */

	/**
	 * Tests that a subclass can override evaluate() and also delegate to
	 * parent::evaluate() for paths it does not need to intercept.
	 *
	 * @ticket 65757
	 *
	 * @covers ::evaluate
	 */
	public function test_subclass_can_fall_back_to_parent() {
		$instance = new class() extends WP_Interactivity_API {
			protected function evaluate( $entry ) {
				// Override for paths containing 'message'; delegate everything else.
				if ( str_contains( $entry['value'], 'message' ) ) {
					return 'overridden';
				}
				return parent::evaluate( $entry );
			}
		};

		$instance->state(
			'test',
			array(
				'message' => 'original',
				'color'   => 'blue',
			)
		);

		$html_overridden = '<div data-wp-interactive="test" data-wp-text="state.message">fallback</div>';
		$html_normal     = '<div data-wp-interactive="test" data-wp-text="state.color">fallback</div>';

		$overridden_result = $instance->process_directives( $html_overridden );

		// The data-wp-text processor replaces the element content, so 'fallback' should be gone.
		$this->assertStringNotContainsString( 'fallback', $overridden_result );
		$this->assertStringContainsString( 'overridden', $overridden_result );
		$this->assertStringNotContainsString( 'original', $overridden_result );

		$normal_result     = $instance->process_directives( $html_normal );
		$this->assertStringNotContainsString( 'fallback', $normal_result );
		$this->assertStringContainsString( 'blue', $normal_result );
	}

	/**
	 * Tests that a subclass can override the static $directive_processors
	 * property to register a custom directive without using the filter.
	 *
	 * The custom processor calls $this->evaluate() to resolve the show
	 * value via the normal evaluate path.
	 *
	 * @ticket 65757
	 *
	 * @covers ::_process_directives
	 */
	public function test_subclass_can_override_directive_processors() {
		$instance = new class() extends WP_Interactivity_API {
			protected static $directive_processors = array(
				'data-wp-interactive' => 'data_wp_interactive_processor',
				'data-wp-show'        => 'subclass_show',
			);

			public function subclass_show( $p, $mode ) {
				if ( 'enter' !== $mode ) {
					return;
				}
				$entries = $this->get_directive_entries( $p, 'show' );
				if ( empty( $entries ) ) {
					return;
				}
				$resolved = $this->evaluate( $entries[0] );
				if ( $resolved ) {
					$p->remove_attribute( 'style' );
				} else {
					$p->set_attribute( 'style', 'display: none' );
				}
			}
		};

		$instance->state(
			'test',
			array( 'shouldShow' => false )
		);

		$html_false = '<div data-wp-interactive="test" data-wp-show="state.shouldShow">content</div>';
		$updated    = $instance->process_directives( $html_false );

		$this->assertStringContainsString( 'display: none', $updated );

		// Also verify the truthy path.
		$instance->state(
			'test',
			array( 'shouldShow' => true )
		);

		$html_true = '<div data-wp-interactive="test" data-wp-show="state.shouldShow">content</div>';
		$updated   = $instance->process_directives( $html_true );

		$this->assertStringNotContainsString( 'display: none', $updated );
	}

	/*
	 * ── Filter: register a data-wp-show directive ─────────────────────
	 *
	 * The same show/hide behaviour is registered via the filter using
	 * two different callable types.
	 */

	/**
	 * Helper: registers a data-wp-show processor via the filter and
	 * returns the processed HTML.
	 *
	 * @param callable $callback The processor callback.
	 * @param bool     $show     Whether the element should be visible.
	 * @return string Processed HTML.
	 */
	private function process_with_show( $callback, bool $show = true ): string {
		add_filter(
			'wp_interactivity_directive_processors',
			function ( $processors ) use ( $callback ) {
				$processors['data-wp-show'] = $callback;
				return $processors;
			}
		);

		$json = wp_json_encode( array( 'shouldShow' => $show ) );
		$html = '<div data-wp-interactive="test" data-wp-context="'
			. esc_attr( $json )
			. '" data-wp-show="context.shouldShow">content</div>';

		return $this->interactivity->process_directives( $html );
	}

	/**
	 * Tests the filter with a Closure as the processor callable —
	 * the element should be hidden (display: none) when the
	 * context value is false.
	 *
	 * @ticket 65757
	 *
	 * @covers ::_process_directives
	 */
	public function test_filter_with_closure_processor_hides() {
		$show_processor = \Closure::bind(
			function ( $p, $mode ) {
				if ( 'enter' !== $mode ) {
					return;
				}
				$entries = $this->get_directive_entries( $p, 'show' );
				if ( empty( $entries ) ) {
					return;
				}
				$resolved = $this->evaluate( $entries[0] );
				if ( $resolved ) {
					$p->remove_attribute( 'style' );
				} else {
					$p->set_attribute( 'style', 'display: none' );
				}
			},
			$this->interactivity,
			WP_Interactivity_API::class
		);

		$updated = $this->process_with_show( $show_processor, false );

		$this->assertStringContainsString( 'display: none', $updated );

		// Also verify the truthy path.
		$updated = $this->process_with_show( $show_processor, true );

		$this->assertStringNotContainsString( 'display: none', $updated );
	}

	/**
	 * Tests the filter with a named static method as the processor
	 * callback — the element should be visible (no style attribute)
	 * when the context value is true.
	 *
	 * @ticket 65757
	 *
	 * @covers ::_process_directives
	 */
	public function test_filter_with_named_function_processor_shows() {
		$updated = $this->process_with_show( 'show_processor', true );

		$this->assertStringNotContainsString( 'display: none', $updated );

		// Also verify the falsy path.
		$updated = $this->process_with_show( 'show_processor', false );

		$this->assertStringContainsString( 'display: none', $updated );
	}
}
