<?php
/**
 * Tests for wp_ai_client_prompt().
 *
 * @group ai-client
 * @covers ::wp_ai_client_prompt
 */

class Tests_AI_Client_Prompt extends WP_UnitTestCase {

	/**
	 * Test that wp_ai_client_prompt() returns a WP_AI_Client_Prompt_Builder instance.
	 *
	 * @ticket 64591
	 */
	public function test_returns_prompt_builder_instance() {
		$builder = wp_ai_client_prompt();

		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $builder );
	}

	/**
	 * Test that successive calls return independent builder instances.
	 *
	 * @ticket 64591
	 */
	public function test_returns_independent_instances() {
		$builder1 = wp_ai_client_prompt( 'First' );
		$builder2 = wp_ai_client_prompt( 'Second' );

		$this->assertNotSame( $builder1, $builder2 );
	}

	/**
	 * Tests that returns a WP_AI_Client_Prompt_Builder instance even when AI is not supported, but that the builder contains an error.
	 */
	public function test_returns_error_builder_when_ai_not_supported(): void {
		// Temporarily disable AI support for this test.
		add_filter( 'wp_supports_ai', '__return_false' );
		$builder = wp_ai_client_prompt();
		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $builder );

		// Check the $error prop is a real WP_Error with the expected message.
		$reflection = new ReflectionClass( $builder );
		$error_prop = $reflection->getProperty( 'error' );
		if ( PHP_VERSION_ID < 80100 ) {
			$error_prop->setAccessible( true );
		}
		$error = $error_prop->getValue( $builder );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'AI features are not supported in the current environment.', $error->get_error_message() );
	}
}
