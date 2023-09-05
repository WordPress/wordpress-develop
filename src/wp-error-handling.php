<?php
/**
 * Set up basic error handling for WordPress.
 *
 * Initially we simply want to avoid error messages accidentally containing
 * HTML in certain configurations. In the future we may improve the display
 * beyond what PHP does by default.
 *
 * @package WordPress
 */

namespace WpOrg\Error_Handling;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/*
 * The error_reporting() function can be disabled in php.ini. On systems where that is the case,
 * it's best to add a dummy function to the wp-config.php file, but as this call to the function
 * is run prior to wp-config.php loading, it is wrapped in a function_exists() check.
 */
if ( function_exists( 'error_reporting' ) ) {
	/*
	 * Initialize error reporting to a known set of levels.
	 *
	 * This will be adapted in wp_debug_mode() located in wp-includes/load.php based on WP_DEBUG.
	 * @see https://www.php.net/manual/en/errorfunc.constants.php List of known error levels.
	 */
	error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
}

/**
 * Previous error handler for chaining. Private.
 *
 * @since 6.4.0
 * @private
 * @var callable|null
 */
$_wp_error_handling_previous_error_handler = set_error_handler( __NAMESPACE__ . '\\wp_error_handler' );

/**
 * Previous exception handler for chaining. Private.
 *
 * @since 6.4.0
 * @private
 * @var callable|null
 */
$_wp_error_handling_previous_exception_handler = set_exception_handler( __NAMESPACE__ . '\\wp_exception_handler' );

/**
 * Display errors like PHP's `display_errors` would.
 *
 * We have two conflicting goals here:
 *  1. We want to not print error text containing arbitrary HTML to the browser.
 *  2. We want all PHP's built-in stuff like `error_get_last()` to work despite
 *     bugs like https://bugs.php.net/bug.php?id=60575.
 *
 * Fortunately PHP doesn't normalize values set to `display_errors` via `ini_set()`, and
 * it treats any unrecognized value as "off". So what we do here is save whatever is in
 * `display_errors`, set it to "wp", print the error if appropriate, and then claims the
 * error was unhandled so PHP does everything else it would normally do.
 *
 * @since 6.4.0
 * @private
 *
 * @param int         $errno   Level of the error raised.
 * @param string      $errstr  Error message.
 * @param string|null $errfile Filename the error was raised in.
 * @param int         $errline Line number where the error was raised.
 */
function _wp_error_handler_printer( $errno, $errstr, $errfile, $errline ) {
	static $display_errors_ini_value = false;

	$v = ini_get( 'display_errors' );
	if ( 'wp' !== $v ) {
		// Adapted from https://github.com/php/php-src/blob/2d3bff38bbe607067de96f86f79dfebf3fb395cf/main/main.c#L429-L460
		$v = is_string( $v ) ? strtolower( $v ) : $v;
		if ( 'stderr' === $v || 2 === (int) $v ) {
			$display_errors_ini_value = 'stderr';
			ini_set( 'display_errors', 'wp' );
		} elseif ( 'on' === $v || 'yes' === $v || 'true' === $v || 'stdout' === $v || 0 !== (int) $v ) {
			$display_errors_ini_value = true;
			ini_set( 'display_errors', 'wp' );
		} elseif ( $display_errors_ini_value ) {
			$display_errors_ini_value = false;
			// Don't replace other falsey values with "wp", in case someone else is doing like we are.
		}
	}

	if ( ! $display_errors_ini_value || ( error_reporting() & $errno ) === 0 ) {
		return;
	}

	$ini_get_bool = function ( $setting ) {
		$v = ini_get( $setting );
		if ( is_string( $v ) ) {
			$v = strtolower( $v );
			if ( 'on' === $v || 'yes' === $v || 'true' === $v ) {
				return true;
			}
		}
		return 0 !== (int) $v;
	};

	// From https://github.com/php/php-src/blob/3f38105740d160e448881b268ebc44bfed20c7db/main/main.c#L1236-L1250
	if ( $ini_get_bool( 'ignore_repeated_errors' ) ) {
		$last = error_get_last();
		if ( $last && $last['message'] === $errstr && (
			$ini_get_bool( 'ignore_repeated_source' ) ||
			(int) $last['line'] === (int) $errline && $last['file'] === $errfile
		) ) {
			return;
		}
	}

	switch ( $errno ) {
		case E_ERROR:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_USER_ERROR:
			$type_str = 'Fatal error';
			break;
		case E_RECOVERABLE_ERROR:
			$type_str = 'Recoverable fatal error';
			break;
		case E_WARNING:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_USER_WARNING:
			$type_str = 'Warning';
			break;
		case E_PARSE:
			$type_str = 'Parse error';
			break;
		case E_NOTICE:
		case E_USER_NOTICE:
			$type_str = 'Notice';
			break;
		case E_STRICT:
			$type_str = 'Strict Standards';
			break;
		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			$type_str = 'Deprecated';
			break;
		default:
			$type_str = 'Unknown error';
			break;
	}

	$errfile = (string) $errfile;
	if ( '' === $errfile ) {
		$errfile = 'Unknown';
	}

	// Adapted from https://github.com/php/php-src/blob/3f38105740d160e448881b268ebc44bfed20c7db/main/main.c#L1352-L1379
	// with the addition of some `htmlspecialchars()` calls.
	if ( $ini_get_bool( 'xmlrpc_errors' ) ) {
		printf(
			'<?xml version="1.0"?><methodResponse><fault><value><struct><member><name>faultCode</name><value><int>%d</int></value></member><member><name>faultString</name><value><string>%s:%s in %s on line %u</string></value></member></struct></value></fault></methodResponse>',
			ini_get( 'xmlrpc_error_number' ),
			$type_str,
			htmlspecialchars( $errstr, ENT_QUOTES | ENT_XML1 ),
			htmlspecialchars( $errfile, ENT_QUOTES | ENT_XML1 ),
			$errline
		);
	} else {
		$error_prepend_string = (string) ini_get( 'error_prepend_string' );
		$error_append_string  = (string) ini_get( 'error_append_string' );

		$sapi = php_sapi_name();
		if ( 'cli' !== $sapi && 'phpdbg' !== $sapi ) {
			$errstr  = htmlspecialchars( $errstr, ENT_QUOTES | ENT_HTML401 );
			$errfile = htmlspecialchars( $errfile, ENT_QUOTES | ENT_HTML401 );
		}

		if ( $ini_get_bool( 'html_errors' ) ) {
			printf( "%s<br />\n<b>%s</b>:  %s in <b>%s</b> on line <b>%u</b><br />\n%s", $error_prepend_string, $type_str, $errstr, $errfile, $errline, $error_append_string );
		} elseif ( 'stderr' === $display_errors_ini_value && ( 'cli' === $sapi || 'cgi' === $sapi || 'phpdbg' === $sapi ) ) {
			fprintf( STDERR, "%s: %s in %s on line %u\n", $type_str, $errstr, $errfile, $errline );
			fflush( STDERR );
		} else {
			printf( "%s\n%s: %s in %s on line %u\n%s", $error_prepend_string, $type_str, $errstr, $errfile, $errline, $error_append_string );
		}
	}
}

/**
 * Handler for PHP's `set_error_handler`.
 *
 * Calls `_wp_error_handler_printer()` to handle printing and resetting of `display_errors`, then
 * chains to the next handler or returns false to let the default handler handle the rest of it.
 *
 * @since 6.4.0
 *
 * @param int         $errno   Level of the error raised.
 * @param string      $errstr  Error message.
 * @param string|null $errfile Filename the error was raised in.
 * @param int         $errline Line number where the error was raised.
 *
 * @return bool True to indicate the error was handled, false if not.
 */
function wp_error_handler( $errno, $errstr, $errfile, $errline ) {
	global $_wp_error_handling_previous_error_handler;

	_wp_error_handler_printer( $errno, $errstr, $errfile, $errline );

	if ( $_wp_error_handling_previous_error_handler ) {
		return $_wp_error_handling_previous_error_handler( $errno, $errstr, $errfile, $errline );
	} else {
		return false;
	}
}

/**
 * Handler for PHP's `set_exception_handler`.
 *
 * Calls `_wp_error_handler_printer()` to handle printing and resetting of `display_errors`, then
 * chains to the next handler or rethrows the exception to let the default handler handle
 * the rest of it.
 *
 * @since 6.4.0
 *
 * @param Throwable $throwable Object that was thrown.
 */
function wp_exception_handler( Throwable $throwable ) {
	global $_wp_error_handling_previous_exception_handler;

	try {
		// Adapted from https://github.com/php/php-src/blob/3f38105740d160e448881b268ebc44bfed20c7db/Zend/zend_exceptions.c#L954-L956
		_wp_error_handler_printer( E_ERROR, "Uncaught {$throwable->__toString()}\n  thrown", $throwable->getFile(), $throwable->getLine() );
	} catch ( Throwable $t ) {
		// Probably $throwable->__toString() is broken. Let the default handler handle that.
	}
	if ( $_wp_error_handling_previous_exception_handler ) {
		$_wp_error_handling_previous_exception_handler( $throwable );
	} else {
		throw $throwable;
	}
}
