<?php

/**
 * Custom autoloader for the PHPUnit 9.x MockObject classes.
 *
 * Hack around PHPUnit < 9.3 mocking not being compatible with PHP 8.
 *
 * This is a temporary solution until the PHPUnit version constraints are widened.
 *
 * @since 5.9.0
 */
final class MockObject_Autoload {

	/**
	 * A list of the classes this autoloader handles.
	 *
	 * @var string[] => true
	 */
	private static $supported_classes = array(
		'PHPUnit\Framework\MockObject\Builder\NamespaceMatch' => '/phpunit7/MockObject/Builder/NamespaceMatch.php',
		'PHPUnit\Framework\MockObject\Builder\ParametersMatch' => '/phpunit7/MockObject/Builder/ParametersMatch.php',
		'PHPUnit\Framework\MockObject\InvocationMocker' => '/phpunit7/MockObject/InvocationMocker.php',
		'PHPUnit\Framework\MockObject\MockMethod'       => '/phpunit7/MockObject/MockMethod.php',
	);

	/**
	 * Loads a class.
	 *
	 * @param string $class_name The name of the class to load.
	 * @return bool
	 */
	public static function load( $class_name ) {

		if ( isset( self::$supported_classes[ $class_name ] ) === false ) {
			// Bow out, not a class this autoloader handles.
			return false;
		}

		if ( PHP_VERSION_ID < 80000 ) {
			// This autoloader is only needed when the tests are being run on PHP >= 8.0.
			// Let the standard Composer autoloader handle things otherwise.
			return false;
		}

		if ( class_exists( 'PHPUnit\Runner\Version' ) === false // PHPUnit < 6.0.
			|| ( version_compare( \PHPUnit\Runner\Version::id(), '7.0.0', '<' )
			&& version_compare( \PHPUnit\Runner\Version::id(), '8.0.0', '>=' ) )
		) {
			// This autoloader is only needed when the tests are being run on PHPUnit 7.
			return false;
		}

		$relative_path = self::$supported_classes[ $class_name ];
		$file          = \realpath( __DIR__ . $relative_path );

		if ( false === $file || @\file_exists( $file ) === false ) {
			return false;
		}

		require_once $file;
		return true;
	}
}
