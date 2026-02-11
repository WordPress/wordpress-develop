<?php
/**
 * Tests for wp_ai_client_prompt().
 *
 * @group ai-client
 * @covers ::wp_ai_client_prompt
 */

use WordPress\AiClient\Builders\PromptBuilder;

class Tests_AI_Client_Prompt extends WP_UnitTestCase {

	/**
	 * Test that wp_ai_client_prompt() returns a WP_AI_Client_Prompt_Builder instance.
	 *
	 * @ticket TBD
	 */
	public function test_returns_prompt_builder_instance() {
		$builder = wp_ai_client_prompt();

		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $builder );
	}

	/**
	 * Test that wp_ai_client_prompt() wraps a PromptBuilder internally.
	 *
	 * @ticket TBD
	 */
	public function test_wraps_sdk_prompt_builder() {
		$builder = wp_ai_client_prompt();

		$reflection = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$property   = $reflection->getProperty( 'builder' );
	

		$this->assertInstanceOf( PromptBuilder::class, $property->getValue( $builder ) );
	}

	/**
	 * Test that wp_ai_client_prompt() passes prompt content to the builder.
	 *
	 * @ticket TBD
	 */
	public function test_passes_prompt_content() {
		$builder = wp_ai_client_prompt( 'Hello, AI!' );

		$reflection       = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$builder_property = $reflection->getProperty( 'builder' );

		$wrapped = $builder_property->getValue( $builder );

		$wrapped_reflection = new ReflectionClass( get_class( $wrapped ) );
		$messages_property  = $wrapped_reflection->getProperty( 'messages' );

		$messages = $messages_property->getValue( $wrapped );

		$this->assertNotEmpty( $messages, 'Prompt content should produce at least one message.' );
	}

	/**
	 * Test that wp_ai_client_prompt() without arguments creates builder with no messages.
	 *
	 * @ticket TBD
	 */
	public function test_no_prompt_creates_empty_builder() {
		$builder = wp_ai_client_prompt();

		$reflection       = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$builder_property = $reflection->getProperty( 'builder' );

		$wrapped = $builder_property->getValue( $builder );

		$wrapped_reflection = new ReflectionClass( get_class( $wrapped ) );
		$messages_property  = $wrapped_reflection->getProperty( 'messages' );

		$messages = $messages_property->getValue( $wrapped );

		$this->assertEmpty( $messages, 'No prompt content should produce no messages.' );
	}

	/**
	 * Test that successive calls return independent builder instances.
	 *
	 * @ticket TBD
	 */
	public function test_returns_independent_instances() {
		$builder1 = wp_ai_client_prompt( 'First' );
		$builder2 = wp_ai_client_prompt( 'Second' );

		$this->assertNotSame( $builder1, $builder2 );
	}
}
