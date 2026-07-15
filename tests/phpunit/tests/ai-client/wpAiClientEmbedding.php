<?php
/**
 * Tests for wp_ai_client_embedding().
 *
 * @group ai-client
 * @covers ::wp_ai_client_embedding
 */

use WordPress\AiClient\Messages\DTO\MessagePart;

class Tests_AI_Client_Embedding extends WP_UnitTestCase {

	/**
	 * Gets the inputs stored on the wrapped SDK embedding builder.
	 *
	 * @param WP_AI_Client_Embedding_Builder $builder The WordPress embedding builder instance.
	 * @return array The wrapped builder's inputs.
	 */
	private function get_wrapped_builder_inputs( WP_AI_Client_Embedding_Builder $builder ): array {
		$reflection_class = new ReflectionClass( WP_AI_Client_Embedding_Builder::class );
		$builder_property = $reflection_class->getProperty( 'builder' );
		if ( PHP_VERSION_ID < 80100 ) {
			$builder_property->setAccessible( true );
		}
		$wrapped_builder = $builder_property->getValue( $builder );

		$reflection_class2 = new ReflectionClass( get_class( $wrapped_builder ) );
		$inputs_property   = $reflection_class2->getProperty( 'inputs' );
		if ( PHP_VERSION_ID < 80100 ) {
			$inputs_property->setAccessible( true );
		}

		return $inputs_property->getValue( $wrapped_builder );
	}

	/**
	 * Test that wp_ai_client_embedding() returns a WP_AI_Client_Embedding_Builder instance.
	 *
	 * @ticket 64591
	 */
	public function test_returns_embedding_builder_instance() {
		$builder = wp_ai_client_embedding();

		$this->assertInstanceOf( WP_AI_Client_Embedding_Builder::class, $builder );
	}

	/**
	 * Test that successive calls return independent builder instances.
	 *
	 * @ticket 64591
	 */
	public function test_returns_independent_instances() {
		$builder1 = wp_ai_client_embedding( 'First' );
		$builder2 = wp_ai_client_embedding( 'Second' );

		$this->assertNotSame( $builder1, $builder2 );
	}

	/**
	 * Test that calling with no arguments creates a builder without inputs or an error state.
	 *
	 * @ticket 64591
	 */
	public function test_no_arguments_creates_builder_without_inputs() {
		$builder = wp_ai_client_embedding();

		$this->assertSame( array(), $this->get_wrapped_builder_inputs( $builder ) );
	}

	/**
	 * Test that a single argument becomes a single input.
	 *
	 * @ticket 64591
	 */
	public function test_single_argument_becomes_single_input() {
		$builder = wp_ai_client_embedding( 'Test input' );

		$inputs = $this->get_wrapped_builder_inputs( $builder );

		$this->assertCount( 1, $inputs );
		$this->assertInstanceOf( MessagePart::class, $inputs[0] );
	}

	/**
	 * Test that multiple variadic arguments each become an independent input.
	 *
	 * @ticket 64591
	 */
	public function test_variadic_arguments_become_independent_inputs() {
		$builder = wp_ai_client_embedding( 'First', 'Second', 'Third' );

		$inputs = $this->get_wrapped_builder_inputs( $builder );

		$this->assertCount( 3, $inputs );
		$this->assertContainsOnlyInstancesOf( MessagePart::class, $inputs );
	}
}
