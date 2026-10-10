<?php
/**
 * Tests the opaque trusted HTML token.
 *
 * @group interactivity-api
 * @covers ::wp_interactivity_as_dangerous_html
 */
class Tests_Interactivity_API_WpInteractivityAsDangerousHtml extends WP_UnitTestCase {

	/** Tests that ordinary inspection never exposes the registered HTML. */
	public function test_token_is_opaque() {
		$token = wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' );
		ob_start();
		var_dump( $token );
		$dump            = ob_get_clean();
		$representations = array(
			$dump,
			print_r( $token, true ),
			var_export( $token, true ),
			print_r( (array) $token, true ),
			print_r( get_object_vars( $token ), true ),
			json_encode( $token ),
			wp_json_encode( $token ),
			serialize( $token ),
		);
		foreach ( $representations as $representation ) {
			$this->assertStringNotContainsString( 'marker-7f3a', $representation );
		}
		$this->assertSame( array(), get_class_methods( $token ) );
	}

	/** Tests that a property write cannot change the registered HTML. */
	public function test_property_write_does_not_change_rendering() {
		$token = wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' );
		try {
			$token->html = '<i>other</i>';
		} catch ( Throwable $error ) {
			// PHP 8.2 deprecates dynamic properties; either outcome is allowed.
		}
		$api = new WP_Interactivity_API();
		$api->state( 'test', array( 'html' => $token ) );
		$this->assertSame(
			'<div data-wp-html="test::state.html"><b>marker-7f3a</b></div>',
			$api->process_directives( '<div data-wp-html="test::state.html">Fallback</div>' )
		);
	}

	/** Tests Core delivery without active plugins through the public functions. */
	public function test_core_function_without_plugins() {
		global $wp_interactivity;
		$previous         = $wp_interactivity;
		$wp_interactivity = new WP_Interactivity_API();
		update_option( 'active_plugins', array() );
		$this->assertTrue( function_exists( 'wp_interactivity_as_dangerous_html' ) );
		wp_interactivity_state( 'test', array( 'html' => wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' ) ) );
		$this->assertSame(
			'<div data-wp-html="test::state.html"><b>marker-7f3a</b></div>',
			wp_interactivity_process_directives( '<div data-wp-html="test::state.html">Fallback</div>' )
		);
		$wp_interactivity = $previous;
	}
}
