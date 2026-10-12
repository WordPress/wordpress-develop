<?php

if ( class_exists( 'PHPUnit\Runner\Version' ) && version_compare( PHPUnit\Runner\Version::id(), '6.0', '>=' ) ) {

	$aliases = array(
		'PHPUnit\Framework\TestCase'                   => 'PHPUnit_Framework_TestCase',
		'PHPUnit\Framework\Exception'                  => 'PHPUnit_Framework_Exception',
		'PHPUnit\Framework\ExpectationFailedException' => 'PHPUnit_Framework_ExpectationFailedException',
		'PHPUnit\Framework\Error\Deprecated'           => 'PHPUnit_Framework_Error_Deprecated',
		'PHPUnit\Framework\Error\Notice'               => 'PHPUnit_Framework_Error_Notice',
		'PHPUnit\Framework\Error\Warning'              => 'PHPUnit_Framework_Error_Warning',
		'PHPUnit\Framework\Test'                       => 'PHPUnit_Framework_Test',
		'PHPUnit\Framework\Warning'                    => 'PHPUnit_Framework_Warning',
		'PHPUnit\Framework\AssertionFailedError'       => 'PHPUnit_Framework_AssertionFailedError',
		'PHPUnit\Framework\TestSuite'                  => 'PHPUnit_Framework_TestSuite',
		'PHPUnit\Framework\TestListener'               => 'PHPUnit_Framework_TestListener',
		'PHPUnit\Util\GlobalState'                     => 'PHPUnit_Util_GlobalState',
		'PHPUnit\Util\Getopt'                          => 'PHPUnit_Util_Getopt',
	);

	foreach ( $aliases as $original => $alias ) {
		if ( class_exists( $original ) ) {
			class_alias( $original, $alias );
		}
	}

	class PHPUnit_Util_Test {

		// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		public static function getTickets( $class_name, $method_name ) {
			$annotations = PHPUnit\Util\Test::parseTestMethodAnnotations( $class_name, $method_name );

			$tickets = array();

			if ( isset( $annotations['class']['ticket'] ) ) {
				$tickets = $annotations['class']['ticket'];
			}

			if ( isset( $annotations['method']['ticket'] ) ) {
				$tickets = array_merge( $tickets, $annotations['method']['ticket'] );
			}

			return array_unique( $tickets );
		}
	}

}
