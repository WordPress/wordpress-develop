<?php
/**
 * Tests trusted HTML isolation from printed client data.
 *
 * @group interactivity-api
 * @covers ::wp_interactivity_process_directives
 */
class Tests_WP_Interactivity_API_Client_Data_WP_HTML extends WP_UnitTestCase {
	/**
	 * Prints and decodes the enqueued Interactivity script module's data.
	 *
	 * @return array Decoded printed data.
	 */
	private function read_printed_data(): array {
		ob_start();
		wp_script_modules()->print_script_module_data();
		$printed = ob_get_clean();
		$this->assertSame( 1, preg_match( '/<script[^>]*id="wp-script-module-data-@wordpress\/interactivity"[^>]*>(.*?)<\/script>/s', $printed, $matches ) );
		return json_decode( $matches[1], true );
	}

	/**
	 * Processes rendering markup and reads the actual script module data JSON.
	 *
	 * @param string $directive Directive name.
	 * @param bool   $each Whether to render each items.
	 * @param bool   $direct Whether to put the token directly in state.
	 * @return array Decoded printed data.
	 */
	private function printed_data( string $directive, bool $each, bool $direct = false ): array {
		global $wp_interactivity;
		$previous         = $wp_interactivity;
		$wp_interactivity = new WP_Interactivity_API();
		$wp_interactivity->add_hooks();
		wp_enqueue_script_module( '@wordpress/interactivity' );
		wp_interactivity_state(
			'test',
			array(
				'text'         => 'processed',
				'items'        => array(
					array( 'id' => 'a' ),
					array( 'id' => 'b' ),
				),
				'descriptions' => array(
					'a' => '<b>A</b>',
					'b' => '<i>B</i>',
				),
				'description'  => static function () {
					return wp_interactivity_as_dangerous_html( wp_interactivity_state( 'test' )['descriptions'][ wp_interactivity_get_context()['item']['id'] ] );
				},
				'html'         => $direct ? wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' ) : static function () {
					return wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' );
				},
			)
		);
		$host   = $each ? '<ul><template data-wp-each="state.items"><li data-wp-' . $directive . '="state.description">…</li></template></ul>' : '<div data-wp-' . $directive . '="state.html">Fallback</div>';
		$output = wp_interactivity_process_directives( '<div data-wp-interactive="test">' . $host . '<span data-wp-text="state.text">x</span></div>' );
		$this->assertStringNotContainsString( 'data-wp-context', $output );
		$data = $this->read_printed_data();
		remove_filter( 'script_module_data_@wordpress/interactivity', array( $wp_interactivity, 'filter_script_module_interactivity_data' ) );
		remove_filter( 'script_module_data_@wordpress/interactivity-router', array( $wp_interactivity, 'filter_script_module_interactivity_router_data' ) );
		remove_filter( 'wp_script_attributes', array( $wp_interactivity, 'add_load_on_client_navigation_attribute_to_script_modules' ) );
		$wp_interactivity = $previous;
		$this->assertStringNotContainsString( 'marker-7f3a', wp_json_encode( $data ) );
		return $data;
	}

	/** Tests derived HTML and each rendering print exactly the same data as text. */
	public function test_rendering_data_equals_text_data() {
		foreach ( array( false, true ) as $each ) {
			$this->assertSame( $this->printed_data( 'text', $each ), $this->printed_data( 'html', $each ) );
		}
	}

	/** Tests tokens placed directly in state disclose no HTML. */
	public function test_direct_token_has_no_html_in_printed_data() {
		$data = $this->printed_data( 'html', false, true );
		$this->assertSame( array(), $data['state']['test']['html'] );
	}

	/**
	 * Processes a declining page and reads its printed script module data.
	 *
	 * @param bool $is_void   Whether the host is a void element.
	 * @param bool $with_html Whether the host carries the HTML directive.
	 * @return array Decoded printed data.
	 */
	private function declined_data( bool $is_void, bool $with_html ): array {
		global $wp_interactivity;
		$previous         = $wp_interactivity;
		$wp_interactivity = new WP_Interactivity_API();
		$wp_interactivity->add_hooks();
		wp_enqueue_script_module( '@wordpress/interactivity' );
		wp_interactivity_config( 'test', array( 'enabled' => true ) );
		wp_interactivity_state(
			'test',
			array(
				'text'    => 'processed',
				'derived' => static function () {
					return 'd';
				},
				'html'    => static function () use ( $is_void ) {
					return $is_void ? wp_interactivity_as_dangerous_html( '<p>marker-9c1e</p>' ) : '<p>Hi</p>';
				},
			)
		);
		$attribute = $with_html ? ' data-wp-html="state.html"' : '';
		$span      = '<span data-wp-text="state.derived">x</span>';
		$host      = $is_void ? '<br' . $attribute . '>' . $span : '<div' . $attribute . '>' . $span . '</div>';
		$output    = wp_interactivity_process_directives( '<div data-wp-interactive="test">' . $host . '<span data-wp-text="state.text">x</span></div>' );
		$this->assertStringContainsString( '<span data-wp-text="state.derived">d</span>', $output );
		$this->assertStringContainsString( '<span data-wp-text="state.text">processed</span>', $output );
		$this->assertStringNotContainsString( 'data-wp-context', $output );
		$data = $this->read_printed_data();
		remove_filter( 'script_module_data_@wordpress/interactivity', array( $wp_interactivity, 'filter_script_module_interactivity_data' ) );
		remove_filter( 'script_module_data_@wordpress/interactivity-router', array( $wp_interactivity, 'filter_script_module_interactivity_router_data' ) );
		remove_filter( 'wp_script_attributes', array( $wp_interactivity, 'add_load_on_client_navigation_attribute_to_script_modules' ) );
		$wp_interactivity = $previous;
		$this->assertStringNotContainsString( 'marker-9c1e', wp_json_encode( $data ) );
		return $data;
	}

	/**
	 * Tests declines only add the evaluated reference's derived path to data.
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 */
	public function test_declining_data_equals_baseline_except_reference_path() {
		foreach ( array( false, true ) as $void ) {
			$baseline = $this->declined_data( $void, false );
			$data     = $this->declined_data( $void, true );
			$this->assertSame( $baseline['state'], $data['state'] );
			$this->assertSame( $baseline['config'], $data['config'] );
			$this->assertSame( array( 'state.derived' ), $baseline['derivedStateClosures']['test'] );
			$this->assertSameSets( array( 'state.derived', 'state.html' ), $data['derivedStateClosures']['test'] );
			unset( $baseline['derivedStateClosures'], $data['derivedStateClosures'] );
			$this->assertSame( $baseline, $data );
		}
	}
}
