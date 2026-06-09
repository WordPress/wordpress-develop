<?php
/**
 * Tests for WP_AI_Client_Ability_Function_Resolver.
 *
 * @group ai-client
 * @covers WP_AI_Client_Ability_Function_Resolver
 */

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-test-abilities-trait.php';

class Tests_AI_Client_AbilityFunctionResolver extends WP_UnitTestCase {
	use WP_AI_Client_Test_Abilities_Trait;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::register_test_abilities();
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class() {
		self::unregister_test_abilities();

		parent::tear_down_after_class();
	}

	/**
	 * Test that is_ability_call returns true for a valid ability call.
	 *
	 * @ticket 64591
	 */
	public function test_is_ability_call_returns_true_for_valid_ability() {
		$call = new FunctionCall(
			'test-id',
			'wpab__tec__create_event',
			array()
		);

		$result = WP_AI_Client_Ability_Function_Resolver::is_ability_call( $call );

		$this->assertTrue( $result );
	}

	/**
	 * Test that is_ability_call returns true for a nested namespace.
	 *
	 * @ticket 64591
	 */
	public function test_is_ability_call_returns_true_for_nested_namespace() {
		$call = new FunctionCall(
			'test-id',
			'wpab__tec__v1__create_event',
			array()
		);

		$result = WP_AI_Client_Ability_Function_Resolver::is_ability_call( $call );

		$this->assertTrue( $result );
	}

	/**
	 * Test that is_ability_call returns false for a non-ability call.
	 *
	 * @ticket 64591
	 */
	public function test_is_ability_call_returns_false_for_non_ability() {
		$call = new FunctionCall(
			'test-id',
			'regular_function',
			array()
		);

		$result = WP_AI_Client_Ability_Function_Resolver::is_ability_call( $call );

		$this->assertFalse( $result );
	}

	/**
	 * Test that is_ability_call returns false when name is null.
	 *
	 * @ticket 64591
	 */
	public function test_is_ability_call_returns_false_when_name_is_null() {
		$call = new FunctionCall(
			'test-id',
			null,
			array()
		);

		$result = WP_AI_Client_Ability_Function_Resolver::is_ability_call( $call );

		$this->assertFalse( $result );
	}

	/**
	 * Test that is_ability_call returns false for partial prefix.
	 *
	 * @ticket 64591
	 */
	public function test_is_ability_call_returns_false_for_partial_prefix() {
		$call = new FunctionCall(
			'test-id',
			'wpab_single_underscore',
			array()
		);

		$result = WP_AI_Client_Ability_Function_Resolver::is_ability_call( $call );

		$this->assertFalse( $result );
	}

	/**
	 * Test that execute_ability returns error for non-ability call.
	 *
	 * @ticket 64591
	 */
	public function test_execute_ability_returns_error_for_non_ability_call() {
		$call = new FunctionCall(
			'test-id',
			'regular_function',
			array()
		);

		$response = WP_AI_Client_Ability_Function_Resolver::execute_ability( $call );

		$this->assertInstanceOf( FunctionResponse::class, $response );
		$this->assertSame( 'test-id', $response->getId() );
		$this->assertSame( 'regular_function', $response->getName() );
		$data = $response->getResponse();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertSame( 'Not an ability function call', $data['error'] );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'invalid_ability_call', $data['code'] );
	}

	/**
	 * Test that execute_ability returns error when ability not found.
	 *
	 * @ticket 64591
	 */
	public function test_execute_ability_returns_error_when_ability_not_found() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$call = new FunctionCall(
			'test-id',
			'wpab__nonexistent__ability',
			array()
		);

		$response = WP_AI_Client_Ability_Function_Resolver::execute_ability( $call );

		$this->assertInstanceOf( FunctionResponse::class, $response );
		$this->assertSame( 'test-id', $response->getId() );
		$this->assertSame( 'wpab__nonexistent__ability', $response->getName() );
		$data = $response->getResponse();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'not found', $data['error'] );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'ability_not_found', $data['code'] );
	}

	/**
	 * Test that execute_ability handles missing id.
	 *
	 * @ticket 64591
	 */
	public function test_execute_ability_handles_missing_id() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$call = new FunctionCall(
			null,
			'wpab__nonexistent__ability',
			array()
		);

		$response = WP_AI_Client_Ability_Function_Resolver::execute_ability( $call );

		$this->assertInstanceOf( FunctionResponse::class, $response );
		$this->assertSame( 'unknown', $response->getId() );
	}

	/**
	 * Test that has_ability_calls returns true when ability call is present.
	 *
	 * @ticket 64591
	 */
	public function test_has_ability_calls_returns_true_when_present() {
		$call = new FunctionCall(
			'test-id',
			'wpab__tec__create_event',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( 'Here is the result:' ),
				new MessagePart( $call ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::has_ability_calls( $message );

		$this->assertTrue( $result );
	}

	/**
	 * Test that has_ability_calls returns false when ability call is not present.
	 *
	 * @ticket 64591
	 */
	public function test_has_ability_calls_returns_false_when_not_present() {
		$call = new FunctionCall(
			'test-id',
			'regular_function',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( 'Here is the result:' ),
				new MessagePart( $call ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::has_ability_calls( $message );

		$this->assertFalse( $result );
	}

	/**
	 * Test that has_ability_calls returns false for text-only message.
	 *
	 * @ticket 64591
	 */
	public function test_has_ability_calls_returns_false_for_text_only() {
		$message = new UserMessage(
			array(
				new MessagePart( 'Just some text' ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::has_ability_calls( $message );

		$this->assertFalse( $result );
	}

	/**
	 * Test that has_ability_calls returns true with mixed content.
	 *
	 * @ticket 64591
	 */
	public function test_has_ability_calls_returns_true_with_mixed_content() {
		$regular_call = new FunctionCall(
			'regular-id',
			'regular_function',
			array()
		);

		$ability_call = new FunctionCall(
			'ability-id',
			'wpab__tec__create_event',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( 'Some text' ),
				new MessagePart( $regular_call ),
				new MessagePart( $ability_call ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::has_ability_calls( $message );

		$this->assertTrue( $result );
	}

	/**
	 * Test that has_ability_calls handles empty message.
	 *
	 * @ticket 64591
	 */
	public function test_has_ability_calls_with_empty_message() {
		$message = new ModelMessage( array() );

		$result = WP_AI_Client_Ability_Function_Resolver::has_ability_calls( $message );

		$this->assertFalse( $result );
	}

	/**
	 * Test that execute_abilities handles empty message.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_with_empty_message() {
		$message = new ModelMessage( array() );

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$this->assertCount( 0, $result->getParts() );
	}

	/**
	 * Test that execute_abilities handles errors gracefully.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_handles_errors_gracefully() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$call = new FunctionCall(
			'test-id',
			'wpab__nonexistent__ability',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( $call ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$parts = $result->getParts();
		$this->assertCount( 1, $parts );

		$response = $parts[0]->getFunctionResponse();
		$this->assertInstanceOf( FunctionResponse::class, $response );
		$data = $response->getResponse();
		$this->assertArrayHasKey( 'error', $data );
	}

	/**
	 * Test that execute_abilities returns a UserMessage.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_returns_user_message() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$call = new FunctionCall(
			'test-id',
			'wpab__nonexistent__ability',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( $call ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
	}

	/**
	 * Test that execute_abilities processes multiple calls.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_processes_multiple_calls() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$call1 = new FunctionCall(
			'call-1',
			'wpab__nonexistent__ability1',
			array()
		);

		$call2 = new FunctionCall(
			'call-2',
			'wpab__nonexistent__ability2',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( $call1 ),
				new MessagePart( $call2 ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$parts = $result->getParts();
		$this->assertCount( 2, $parts );
	}

	/**
	 * Test that execute_abilities only processes function calls.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_only_processes_function_calls() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$call = new FunctionCall(
			'test-id',
			'wpab__nonexistent__ability',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( 'Some text' ),
				new MessagePart( $call ),
				new MessagePart( 'More text' ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$parts = $result->getParts();
		// Only the function call should be processed.
		$this->assertCount( 1, $parts );
	}

	/**
	 * Test ability_name_to_function_name with simple name.
	 *
	 * @ticket 64591
	 */
	public function test_ability_name_to_function_name_simple() {
		$result = WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( 'tec/create_event' );

		$this->assertSame( 'wpab__tec__create_event', $result );
	}

	/**
	 * Test ability_name_to_function_name with nested namespace.
	 *
	 * @ticket 64591
	 */
	public function test_ability_name_to_function_name_nested() {
		$result = WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( 'tec/v1/create_event' );

		$this->assertSame( 'wpab__tec__v1__create_event', $result );
	}

	/**
	 * Test execute_ability with successful execution.
	 *
	 * @ticket 64591
	 */
	public function test_execute_ability_success() {
		$call = new FunctionCall(
			'test-id',
			'wpab__wpaiclienttests__simple',
			array()
		);

		$response = WP_AI_Client_Ability_Function_Resolver::execute_ability( $call );

		$this->assertInstanceOf( FunctionResponse::class, $response );
		$this->assertSame( 'test-id', $response->getId() );
		$this->assertSame( 'wpab__wpaiclienttests__simple', $response->getName() );
		$data = $response->getResponse();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test execute_ability with parameters.
	 *
	 * @ticket 64591
	 */
	public function test_execute_ability_with_parameters() {
		$call = new FunctionCall(
			'test-id',
			'wpab__wpaiclienttests__with-params',
			array( 'title' => 'Test Title' )
		);

		$response = WP_AI_Client_Ability_Function_Resolver::execute_ability( $call );

		$this->assertInstanceOf( FunctionResponse::class, $response );
		$this->assertSame( 'test-id', $response->getId() );
		$this->assertSame( 'wpab__wpaiclienttests__with-params', $response->getName() );
		$data = $response->getResponse();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertSame( 'Test Title', $data['title'] );
	}

	/**
	 * Test execute_ability handles WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_execute_ability_handles_wp_error() {
		$call = new FunctionCall(
			'test-id',
			'wpab__wpaiclienttests__returns-error',
			array()
		);

		$response = WP_AI_Client_Ability_Function_Resolver::execute_ability( $call );

		$this->assertInstanceOf( FunctionResponse::class, $response );
		$this->assertSame( 'test-id', $response->getId() );
		$this->assertSame( 'wpab__wpaiclienttests__returns-error', $response->getName() );
		$data = $response->getResponse();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertSame( 'This is a test error message.', $data['error'] );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'test_error', $data['code'] );
	}

	/**
	 * Test execute_abilities with successful execution.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_success() {
		$call = new FunctionCall(
			'test-id',
			'wpab__wpaiclienttests__simple',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( $call ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$parts = $result->getParts();
		$this->assertCount( 1, $parts );

		$response = $parts[0]->getFunctionResponse();
		$this->assertInstanceOf( FunctionResponse::class, $response );
		$data = $response->getResponse();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test execute_abilities with multiple successful executions.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_multiple_success() {
		$call1 = new FunctionCall(
			'call-1',
			'wpab__wpaiclienttests__simple',
			array()
		);

		$call2 = new FunctionCall(
			'call-2',
			'wpab__wpaiclienttests__hyphen-test',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( $call1 ),
				new MessagePart( $call2 ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$parts = $result->getParts();
		$this->assertCount( 2, $parts );

		// Check first response.
		$response1 = $parts[0]->getFunctionResponse();
		$this->assertInstanceOf( FunctionResponse::class, $response1 );
		$data1 = $response1->getResponse();
		$this->assertArrayHasKey( 'success', $data1 );
		$this->assertTrue( $data1['success'] );

		// Check second response.
		$response2 = $parts[1]->getFunctionResponse();
		$this->assertInstanceOf( FunctionResponse::class, $response2 );
		$data2 = $response2->getResponse();
		$this->assertArrayHasKey( 'hyphenated', $data2 );
		$this->assertTrue( $data2['hyphenated'] );
	}

	/**
	 * Test execute_abilities with mixed text and ability calls.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_with_mixed_content() {
		$call = new FunctionCall(
			'test-id',
			'wpab__wpaiclienttests__simple',
			array()
		);

		$message = new ModelMessage(
			array(
				new MessagePart( 'Starting execution' ),
				new MessagePart( $call ),
				new MessagePart( 'Execution complete' ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$parts = $result->getParts();
		// Only function calls should be processed.
		$this->assertCount( 1, $parts );

		$response = $parts[0]->getFunctionResponse();
		$this->assertInstanceOf( FunctionResponse::class, $response );
	}

	/**
	 * Test execute_abilities with ability that has parameters.
	 *
	 * @ticket 64591
	 */
	public function test_execute_abilities_with_parameters() {
		$call = new FunctionCall(
			'test-id',
			'wpab__wpaiclienttests__with-params',
			array( 'title' => 'Integration Test' )
		);

		$message = new ModelMessage(
			array(
				new MessagePart( $call ),
			)
		);

		$result = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $message );

		$this->assertInstanceOf( UserMessage::class, $result );
		$parts = $result->getParts();
		$this->assertCount( 1, $parts );

		$response = $parts[0]->getFunctionResponse();
		$this->assertInstanceOf( FunctionResponse::class, $response );
		$data = $response->getResponse();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'title', $data );
		$this->assertSame( 'Integration Test', $data['title'] );
	}
}
