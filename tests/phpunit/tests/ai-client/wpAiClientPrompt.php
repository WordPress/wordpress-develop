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
}
