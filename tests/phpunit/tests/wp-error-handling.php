<?php

namespace WpOrg\Error_Handling;

// Dummy functions for testing.

$mock_function_data = null;

function ini_get( $setting ) {
	global $mock_function_data;
	if ( null === $mock_function_data ) {
		return \ini_get( $setting );
	}

	if ( array_key_exists( $setting, $mock_function_data['ini'] ) ) {
		return $mock_function_data['ini'][ $setting ];
	}
	throw new \RuntimeException( "Ini setting `$setting` was not mocked" );
}

function ini_set( $setting, $value ) {
	global $mock_function_data;
	if ( null === $mock_function_data ) {
		return \ini_set( $setting, $value );
	}

	if ( array_key_exists( $setting, $mock_function_data['ini'] ) ) {
		$ret                                   = $mock_function_data['ini'][ $setting ];
		$mock_function_data['ini'][ $setting ] = $value;
		return $ret;
	}
	throw new \RuntimeException( "Ini setting `$setting` was not mocked" );
}

function php_sapi_name() {
	global $mock_function_data;
	if ( null === $mock_function_data ) {
		return \php_sapi_name();
	}

	return $mock_function_data['sapi'];
}

function error_reporting( $level = null ) {
	global $mock_function_data;
	if ( null === $mock_function_data ) {
		return null === $level ? \error_reporting() : \error_reporting( $level );
	}

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$ret = \error_reporting() === @\error_reporting() ? @\error_reporting() : $mock_function_data['ini']['error_reporting'];
	if ( null !== $level ) {
		$mock_function_data['ini']['error_reporting'] = $level;
	}
	return $ret;
}

function error_get_last() {
	global $mock_function_data;
	if ( null === $mock_function_data ) {
		return \error_get_last();
	}

	return isset( $mock_function_data['last'] ) ? $mock_function_data['last'] : null;
}

/**
 * @group wp-error-handling.php
 */
class Tests_WP_Error_Handling extends \WP_UnitTestCase {

	/**
	 * Globals to restore.
	 *
	 * @var array
	 */
	private $restore_globals = array();

	public static function set_up_before_class() {
		global $_wp_error_handling_previous_error_handler, $_wp_error_handling_previous_exception_handler;

		parent::set_up_before_class();

		if ( ! function_exists( 'wp_error_handler' ) ) {
			// We're just wanting to test the functions, not the other stuff.
			// So undo the other stuff after the require.
			$old_error_reporting = error_reporting();
			require_once ABSPATH . 'wp-error-handling.php';
			error_reporting( $old_error_reporting );
			restore_error_handler();
			restore_exception_handler();
			$_wp_error_handling_previous_error_handler     = null;
			$_wp_error_handling_previous_exception_handler = null;
		}
	}

	public function set_up() {
		global $mock_function_data, $_wp_error_handling_previous_error_handler, $_wp_error_handling_previous_exception_handler;

		parent::set_up();

		$mock_function_data = array(
			'ini'  => array(
				'display_errors'         => 'on',
				'xmlrpc_errors'          => 'off',
				'xmlrpc_error_number'    => 8675309,
				'ignore_repeated_errors' => 'off',
				'ignore_repeated_source' => 'off',
				'error_prepend_string'   => '',
				'error_append_string'    => '',
				'html_errors'            => 'on',
				'error_reporting'        => E_ALL,
			),
			'sapi' => 'apache',
		);

		$this->restore_globals = array(
			'mock_function_data'                        => null,
			'_wp_error_handling_previous_error_handler' => $_wp_error_handling_previous_error_handler,
			'_wp_error_handling_previous_exception_handler' => $_wp_error_handling_previous_exception_handler,
		);
	}

	public function tear_down() {
		parent::tear_down();

		foreach ( $this->restore_globals as $k => $v ) {
			$GLOBALS[ $k ] = $v;
		}
	}

	/**
	 * @dataProvider data_wp_error_handler_printer
	 */
	public function test_wp_error_handler_printer( $mock_data, $calls, $expect ) {
		global $mock_function_data;

		$mock_function_data = array_replace_recursive( $mock_function_data, $mock_data );

		$this->expectOutputString( $expect );
		foreach ( $calls as list( $errno, $errstr, $errfile, $errline, $new_display_errors_value ) ) {
			_wp_error_handler_printer( $errno, $errstr, $errfile, $errline );
			$this->assertSame( $new_display_errors_value, ini_get( 'display_errors' ) );
			$mock_function_data['last'] = array(
				'type'    => $errno,
				'message' => $errstr,
				'file'    => $errfile,
				'line'    => $errline,
			);
		}
	}

	public function data_wp_error_handler_printer() {
		return array(
			'display_errors off'                        => array(
				array( 'ini' => array( 'display_errors' => 'off' ) ),
				array(
					array( E_ERROR, 'Some error', 'some-file.php', 42, 'off' ),
				),
				'',
			),
			'error_reporting off'                       => array(
				array( 'ini' => array( 'error_reporting' => E_ALL & ~E_ERROR ) ),
				array(
					array( E_ERROR, 'Some error', 'some-file.php', 42, 'wp' ),
				),
				'',
			),
			'some errors'                               => array(
				array(),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_WARNING, '<i>Some warning</i>', 'some-file-&-what.php', 41, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				"<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>40</b><br />\n"
				. "<br />\n<b>Warning</b>:  &lt;i&gt;Some warning&lt;/i&gt; in <b>some-file-&amp;-what.php</b> on line <b>41</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  &lt;i&gt;Some error&lt;/i&gt; in <b>some-file.php</b> on line <b>42</b><br />\n",
			),
			'some errors, cli'                          => array(
				array( 'sapi' => 'cli' ),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_WARNING, '<i>Some warning</i>', 'some-file-&-what.php', 41, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				"<br />\n<b>Notice</b>:  <i>Some notice</i> in <b>some-file.php</b> on line <b>40</b><br />\n"
				. "<br />\n<b>Warning</b>:  <i>Some warning</i> in <b>some-file-&-what.php</b> on line <b>41</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  <i>Some error</i> in <b>some-file.php</b> on line <b>42</b><br />\n",
			),
			'without html_errors'                       => array(
				array( 'ini' => array( 'html_errors' => 'off' ) ),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_WARNING, '<i>Some warning</i>', 'some-file-&-what.php', 41, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				"\nNotice: &lt;i&gt;Some notice&lt;/i&gt; in some-file.php on line 40\n"
				. "\nWarning: &lt;i&gt;Some warning&lt;/i&gt; in some-file-&amp;-what.php on line 41\n"
				. "\nFatal error: &lt;i&gt;Some error&lt;/i&gt; in some-file.php on line 42\n",
			),
			'without html_errors, cli'                  => array(
				array(
					'ini'  => array( 'html_errors' => 'off' ),
					'sapi' => 'cli',
				),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_WARNING, '<i>Some warning</i>', 'some-file-&-what.php', 41, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				"\nNotice: <i>Some notice</i> in some-file.php on line 40\n"
				. "\nWarning: <i>Some warning</i> in some-file-&-what.php on line 41\n"
				. "\nFatal error: <i>Some error</i> in some-file.php on line 42\n",
			),
			'error_prepend_string'                      => array(
				array(
					'ini' => array(
						'error_prepend_string' => '<div>',
						'error_append_string'  => '</div>',
					),
				),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				"<div><br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>40</b><br />\n</div>"
				. "<div><br />\n<b>Fatal error</b>:  &lt;i&gt;Some error&lt;/i&gt; in <b>some-file.php</b> on line <b>42</b><br />\n</div>",
			),
			'error_prepend_string without html_errors'  => array(
				array(
					'ini' => array(
						'error_prepend_string' => '<div>',
						'error_append_string'  => '</div>',
						'html_errors'          => 'off',
					),
				),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				"<div>\nNotice: &lt;i&gt;Some notice&lt;/i&gt; in some-file.php on line 40\n</div>"
				. "<div>\nFatal error: &lt;i&gt;Some error&lt;/i&gt; in some-file.php on line 42\n</div>",
			),
			'error_prepend_string, cli, no html_errors' => array(
				array(
					'ini'  => array(
						'error_prepend_string' => '<div>',
						'error_append_string'  => '</div>',
						'html_errors'          => 'off',
					),
					'sapi' => 'cli',
				),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				"<div>\nNotice: <i>Some notice</i> in some-file.php on line 40\n</div>"
				. "<div>\nFatal error: <i>Some error</i> in some-file.php on line 42\n</div>",
			),
			'error_prepend_string, cli, no html_errors, display to stderr' => array(
				array(
					'ini'  => array(
						'display_errors'       => 'stderr',
						'error_prepend_string' => '<div>',
						'error_append_string'  => '</div>',
						'html_errors'          => 'off',
						'error_reporting'      => 0,
					),
					'sapi' => 'cli',
				),
				array(
					array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 42, 'wp' ),
				),
				'',
			),
			'null filename'                             => array(
				array(),
				array(
					array( E_NOTICE, '<i>Some notice</i>', '', 40, 'wp' ),
					array( E_WARNING, '<i>Some warning</i>', null, 41, 'wp' ),
				),
				"<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>Unknown</b> on line <b>40</b><br />\n"
				. "<br />\n<b>Warning</b>:  &lt;i&gt;Some warning&lt;/i&gt; in <b>Unknown</b> on line <b>41</b><br />\n",
			),
			'all error codes'                           => array(
				array(),
				array(
					array( E_ERROR, 'E_ERROR', 'file.php', 1, 'wp' ),
					array( E_CORE_ERROR, 'E_CORE_ERROR', 'file.php', 1, 'wp' ),
					array( E_COMPILE_ERROR, 'E_COMPILE_ERROR', 'file.php', 1, 'wp' ),
					array( E_USER_ERROR, 'E_USER_ERROR', 'file.php', 1, 'wp' ),
					array( E_RECOVERABLE_ERROR, 'E_RECOVERABLE_ERROR', 'file.php', 1, 'wp' ),
					array( E_WARNING, 'E_WARNING', 'file.php', 1, 'wp' ),
					array( E_CORE_WARNING, 'E_CORE_WARNING', 'file.php', 1, 'wp' ),
					array( E_COMPILE_WARNING, 'E_COMPILE_WARNING', 'file.php', 1, 'wp' ),
					array( E_USER_WARNING, 'E_USER_WARNING', 'file.php', 1, 'wp' ),
					array( E_PARSE, 'E_PARSE', 'file.php', 1, 'wp' ),
					array( E_NOTICE, 'E_NOTICE', 'file.php', 1, 'wp' ),
					array( E_USER_NOTICE, 'E_USER_NOTICE', 'file.php', 1, 'wp' ),
					array( E_STRICT, 'E_STRICT', 'file.php', 1, 'wp' ),
					array( E_DEPRECATED, 'E_DEPRECATED', 'file.php', 1, 'wp' ),
					array( E_USER_DEPRECATED, 'E_USER_DEPRECATED', 'file.php', 1, 'wp' ),
					array( -1, 'neg-one', 'file.php', 1, 'wp' ),
				),
				"<br />\n<b>Fatal error</b>:  E_ERROR in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  E_CORE_ERROR in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  E_COMPILE_ERROR in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  E_USER_ERROR in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Recoverable fatal error</b>:  E_RECOVERABLE_ERROR in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Warning</b>:  E_WARNING in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Warning</b>:  E_CORE_WARNING in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Warning</b>:  E_COMPILE_WARNING in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Warning</b>:  E_USER_WARNING in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Parse error</b>:  E_PARSE in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Notice</b>:  E_NOTICE in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Notice</b>:  E_USER_NOTICE in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Strict Standards</b>:  E_STRICT in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Deprecated</b>:  E_DEPRECATED in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Deprecated</b>:  E_USER_DEPRECATED in <b>file.php</b> on line <b>1</b><br />\n"
				. "<br />\n<b>Unknown error</b>:  neg-one in <b>file.php</b> on line <b>1</b><br />\n",
			),
			'xmlrpc_errors'                             => array(
				array( 'ini' => array( 'xmlrpc_errors' => 'on' ) ),
				array(
					array( E_ERROR, '<i>Some error</i>', 'some-&-file.php', 42, 'wp' ),
				),
				'<?xml version="1.0"?><methodResponse><fault><value><struct><member><name>faultCode</name><value><int>8675309</int></value></member><member><name>faultString</name><value><string>Fatal error:&lt;i&gt;Some error&lt;/i&gt; in some-&amp;-file.php on line 42</string></value></member></struct></value></fault></methodResponse>',
			),
			'ignore_repeated_errors'                    => array(
				array( 'ini' => array( 'ignore_repeated_errors' => 'on' ) ),
				array(
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 41, 'wp' ),
					array( E_ERROR, '<i>Some other error</i>', 'some-file.php', 42, 'wp' ),
				),
				"<br />\n<b>Fatal error</b>:  &lt;i&gt;Some error&lt;/i&gt; in <b>some-file.php</b> on line <b>40</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  &lt;i&gt;Some error&lt;/i&gt; in <b>some-file.php</b> on line <b>41</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  &lt;i&gt;Some other error&lt;/i&gt; in <b>some-file.php</b> on line <b>42</b><br />\n",
			),
			'ignore_repeated_source'                    => array(
				array(
					'ini' => array(
						'ignore_repeated_errors' => 'on',
						'ignore_repeated_source' => 'on',
					),
				),
				array(
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 40, 'wp' ),
					array( E_ERROR, '<i>Some error</i>', 'some-file.php', 41, 'wp' ),
					array( E_ERROR, '<i>Some other error</i>', 'some-file.php', 42, 'wp' ),
				),
				"<br />\n<b>Fatal error</b>:  &lt;i&gt;Some error&lt;/i&gt; in <b>some-file.php</b> on line <b>40</b><br />\n"
				. "<br />\n<b>Fatal error</b>:  &lt;i&gt;Some other error&lt;/i&gt; in <b>some-file.php</b> on line <b>42</b><br />\n",
			),
		);
	}

	public function test_wp_error_handler_printer_turn_off() {
		$this->expectOutputString(
			"<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>40</b><br />\n"
			. "<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>42</b><br />\n"
			. "<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>60</b><br />\n"
			. "<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>62</b><br />\n"
		);
		_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40 );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 41 );
		_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 42 );
		ini_set( 'display_errors', 'off' );
		_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 50 );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 51 );
		_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 52 );
		ini_set( 'display_errors', 'on' );
		_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 60 );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 61 );
		_wp_error_handler_printer( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 62 );
	}

	public function test_wp_error_handler_no_chaining() {
		global $_wp_error_handling_previous_error_handler;

		$this->expectOutputString( "<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>40</b><br />\n" );

		$_wp_error_handling_previous_error_handler = null;
		$this->assertFalse( wp_error_handler( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40 ) );
	}

	public function test_wp_error_handler_chaining() {
		global $_wp_error_handling_previous_error_handler;

		$this->expectOutputString( "<br />\n<b>Notice</b>:  &lt;i&gt;Some notice&lt;/i&gt; in <b>some-file.php</b> on line <b>40</b><br />\n" );

		$was_called                                = array();
		$_wp_error_handling_previous_error_handler = function () use ( &$was_called ) {
			$was_called[] = func_get_args();
			return 'ok';
		};
		$this->assertSame( 'ok', wp_error_handler( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40 ) );
		$this->assertSame( array( array( E_NOTICE, '<i>Some notice</i>', 'some-file.php', 40 ) ), $was_called );
	}

	public function test_wp_exception_handler_no_chaining() {
		global $_wp_error_handling_previous_error_handler, $_wp_error_handling_previous_exception_handler;

		$ex   = new \Exception( '<i>Some exception</i>' );
		$str  = htmlspecialchars( $ex->__toString(), ENT_QUOTES | ENT_HTML401 );
		$file = htmlspecialchars( $ex->getFile(), ENT_QUOTES | ENT_HTML401 );
		$this->expectOutputString( "<br />\n<b>Fatal error</b>:  Uncaught $str\n  thrown in <b>$file</b> on line <b>{$ex->getLine()}</b><br />\n" );

		$_wp_error_handling_previous_error_handler = function () {
			$this->fail( '_wp_error_handling_previous_error_handler should not have been called' );
		};

		$_wp_error_handling_previous_exception_handler = null;

		try {
			wp_exception_handler( $ex );
			$this->fail( 'Expected exception to be thrown' );
		} catch ( \Throwable $t ) {
			$this->assertSame( $ex, $t );
		}
	}

	public function test_wp_exception_handler_chaining() {
		global $_wp_error_handling_previous_error_handler, $_wp_error_handling_previous_exception_handler;

		$ex   = new \Exception( '<i>Some exception</i>' );
		$str  = htmlspecialchars( $ex->__toString(), ENT_QUOTES | ENT_HTML401 );
		$file = htmlspecialchars( $ex->getFile(), ENT_QUOTES | ENT_HTML401 );
		$this->expectOutputString( "<br />\n<b>Fatal error</b>:  Uncaught $str\n  thrown in <b>$file</b> on line <b>{$ex->getLine()}</b><br />\n" );

		$_wp_error_handling_previous_error_handler = function () use ( &$error_handler_was_called ) {
			$this->fail( '_wp_error_handling_previous_error_handler should not have been called' );
		};

		$was_called                                    = array();
		$_wp_error_handling_previous_exception_handler = function () use ( &$was_called ) {
			$was_called[] = func_get_args();
		};

		wp_exception_handler( $ex );

		$this->assertSame( array( array( $ex ) ), $was_called );
	}

	public function test_wp_exception_handler_bad_tostring() {
		global $_wp_error_handling_previous_exception_handler;

		$ex = new class( '<i>Some exception</i>' ) extends \Exception {
			public function __toString() {
				throw new \Exception( 'Error in __toString()' );
			}
		};
		$this->expectOutputString( '' );

		$was_called                                    = array();
		$_wp_error_handling_previous_exception_handler = function () use ( &$was_called ) {
			$was_called[] = func_get_args();
		};

		wp_exception_handler( $ex );

		$this->assertSame( array( array( $ex ) ), $was_called );
	}
}
