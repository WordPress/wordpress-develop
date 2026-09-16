<?php

/**
 * Test cases for the `wp_trigger_error()` function.
 *
 * @since 6.4.0
 *
 * @group functions
 *
 * @covers ::wp_trigger_error
 */
class Tests_Functions_WpTriggerError extends WP_UnitTestCase {

	/**
	 * @ticket 57686
	 *
	 * @dataProvider data_should_trigger_error
	 *
	 * @param string $function_name    The function name to test.
	 * @param string $message          The message to test.
	 * @param string $expected_message The expected error message.
	 */
	public function test_should_throw_exception( $function_name, $message, $expected_message ) {
		$this->expectException( WP_Exception::class );
		$this->expectExceptionMessage( $expected_message );

		wp_trigger_error( $function_name, $message, E_USER_ERROR );
	}

	/**
	 * @ticket 57686
	 *
	 * @dataProvider data_should_trigger_error
	 *
	 * @param string $function_name    The function name to test.
	 * @param string $message          The message to test.
	 * @param string $expected_message The expected error message.
	 */
	public function test_should_trigger_warning( $function_name, $message, $expected_message ) {
		$this->expectWarning();
		$this->expectWarningMessage( $expected_message );

		wp_trigger_error( $function_name, $message, E_USER_WARNING );
	}

	/**
	 * @ticket 57686
	 *
	 * @dataProvider data_should_trigger_error
	 *
	 * @param string $function_name    The function name to test.
	 * @param string $message          The message to test.
	 * @param string $expected_message The expected error message.
	 */
	public function test_should_trigger_notice( $function_name, $message, $expected_message ) {
		$this->expectNotice();
		$this->expectNoticeMessage( $expected_message );

		wp_trigger_error( $function_name, $message );
	}

	/**
	 * @ticket 57686
	 *
	 * @dataProvider data_should_trigger_error
	 *
	 * @param string $function_name    The function name to test.
	 * @param string $message          The message to test.
	 * @param string $expected_message The expected error message.
	 */
	public function test_should_trigger_deprecation( $function_name, $message, $expected_message ) {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( $expected_message );

		wp_trigger_error( $function_name, $message, E_USER_DEPRECATED );
	}

	/**
	 * Tests that the caller's file and line number are appended to the message
	 * when called indirectly via _doing_it_wrong(), pointing to the caller of
	 * _doing_it_wrong() rather than to wp-includes/functions.php.
	 *
	 * @ticket 64561
	 */
	public function test_caller_info_is_appended_when_called_via_doing_it_wrong() {
		$triggered_message = null;

		/*
		 * The test framework adds __return_false to 'doing_it_wrong_trigger_error' so that
		 * _doing_it_wrong() calls are tracked without actually triggering errors.
		 * Remove it temporarily so wp_trigger_error() is actually called.
		 */
		remove_all_filters( 'doing_it_wrong_trigger_error' );

		// Declare the expected incorrect usage so tearDown() does not flag it as unexpected.
		$this->setExpectedIncorrectUsage( 'some_function' );

		set_error_handler(
			static function ( $errno, $errstr ) use ( &$triggered_message ) {
				$triggered_message = $errstr;
				return true;
			}
		);

		$caller_line = __LINE__ + 1;
		_doing_it_wrong( 'some_function', 'indirect call message', '1.0' );

		restore_error_handler();

		// Restore the suppression filter for any subsequent calls in this test.
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		$this->assertNotNull( $triggered_message, 'No error was triggered.' );
		$this->assertStringContainsString(
			sprintf( '(Called from %s on line %d.)', __FILE__, $caller_line ),
			$triggered_message,
			'The caller info must point to the caller of _doing_it_wrong(), not to wp-includes/functions.php.'
		);
		$this->assertStringNotContainsString(
			'wp-includes/functions.php',
			$triggered_message,
			'The caller info must not point to wp-includes/functions.php.'
		);
	}

	/**
	 * Tests that the caller's file and line number are appended to the message
	 * when wp_trigger_error() is called directly, and that the info points to
	 * the calling file rather than to wp-includes/functions.php.
	 *
	 * @ticket 64561
	 */
	public function test_caller_info_is_not_from_wp_includes_functions_php() {
		$triggered_message = null;

		set_error_handler(
			static function ( $errno, $errstr ) use ( &$triggered_message ) {
				$triggered_message = $errstr;
				return true;
			}
		);

		$caller_line = __LINE__ + 1;
		wp_trigger_error( 'test_fn', 'test message' );

		restore_error_handler();

		$this->assertNotNull( $triggered_message, 'No error was triggered.' );
		$this->assertStringNotContainsString(
			'wp-includes/functions.php',
			$triggered_message,
			'The caller info must not point to wp-includes/functions.php.'
		);
		$this->assertStringContainsString(
			sprintf( '(Called from %s on line %d.)', __FILE__, $caller_line ),
			$triggered_message,
			'The caller info must point to this test file and the correct line.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_trigger_error() {
		return array(
			'function name and message are given'          => array(
				'function_name'    => 'some_function',
				'message'          => 'expected the function name and message',
				'expected_message' => 'some_function(): expected the function name and message',
			),
			'message is given'                             => array(
				'function_name'    => '',
				'message'          => 'expect only the message',
				'expected_message' => 'expect only the message',
			),
			'function name is given'                       => array(
				'function_name'    => 'some_function',
				'message'          => '',
				'expected_message' => 'some_function(): ',
			),
			'allowed HTML elements are present in message' => array(
				'function_name'    => 'some_function',
				'message'          => '<strong>expected</strong> the function name and message',
				'expected_message' => 'some_function(): <strong>expected</strong> the function name and message',
			),
			'HTML links are present in message'            => array(
				'function_name'    => 'some_function',
				'message'          => '<a href="https://example.com">expected the function name and message</a>',
				'expected_message' => 'some_function(): <a href="https://example.com">expected the function name and message</a>',
			),
			'disallowed HTML elements are present in message' => array(
				'function_name'    => 'some_function',
				'message'          => '<script>alert("expected the function name and message")</script>',
				'expected_message' => 'some_function(): alert("expected the function name and message")',
			),
		);
	}
}
