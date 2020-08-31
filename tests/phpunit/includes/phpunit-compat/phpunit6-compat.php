<?php

if ( class_exists( 'PHPUnit\Runner\Version' ) && version_compare( PHPUnit\Runner\Version::id(), '6.0', '>=' ) ) {

	class_alias( 'PHPUnit\Framework\TestCase', 'PHPUnit_Framework_TestCase' );
	class_alias( 'PHPUnit\Framework\Exception', 'PHPUnit_Framework_Exception' );
	class_alias( 'PHPUnit\Framework\ExpectationFailedException', 'PHPUnit_Framework_ExpectationFailedException' );
	class_alias( 'PHPUnit\Framework\Error\Deprecated', 'PHPUnit_Framework_Error_Deprecated' );
	class_alias( 'PHPUnit\Framework\Error\Notice', 'PHPUnit_Framework_Error_Notice' );
	class_alias( 'PHPUnit\Framework\Error\Warning', 'PHPUnit_Framework_Error_Warning' );
	class_alias( 'PHPUnit\Framework\Test', 'PHPUnit_Framework_Test' );
	class_alias( 'PHPUnit\Framework\Warning', 'PHPUnit_Framework_Warning' );
	class_alias( 'PHPUnit\Framework\AssertionFailedError', 'PHPUnit_Framework_AssertionFailedError' );
	class_alias( 'PHPUnit\Framework\TestSuite', 'PHPUnit_Framework_TestSuite' );
	class_alias( 'PHPUnit\Framework\TestListener', 'PHPUnit_Framework_TestListener' );
	class_alias( 'PHPUnit\Util\GlobalState', 'PHPUnit_Util_GlobalState' );

	// No longer available in PHPUnit 9
	if ( class_exists( 'PHPUnit\Util\Getopt' ) ) {
		class_alias( 'PHPUnit\Util\Getopt', 'PHPUnit_Util_Getopt' );
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

} else {
	// Alias the newer namespaced classes to their pre-namespace locations for PHPUnit 5.
	class_alias( 'PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase' );
	class_alias( 'PHPUnit_Framework_Exception', 'PHPUnit\Framework\Exception' );
	class_alias( 'PHPUnit_Framework_ExpectationFailedException', 'PHPUnit\Framework\ExpectationFailedException' );
	class_alias( 'PHPUnit_Framework_Error_Deprecated', 'PHPUnit\Framework\Error\Deprecated' );
	class_alias( 'PHPUnit_Framework_Error_Notice', 'PHPUnit\Framework\Error\Notice' );
	class_alias( 'PHPUnit_Framework_Error_Warning', 'PHPUnit\Framework\Error\Warning' );
	class_alias( 'PHPUnit_Framework_Error_Error', 'PHPUnit\Framework\Error\Error' );
	class_alias( 'PHPUnit_Framework_Test', 'PHPUnit\Framework\Test' );
	class_alias( 'PHPUnit_Framework_Warning', 'PHPUnit\Framework\Warning' );
	class_alias( 'PHPUnit_Framework_AssertionFailedError', 'PHPUnit\Framework\AssertionFailedError' );
	class_alias( 'PHPUnit_Framework_TestSuite', 'PHPUnit\Framework\TestSuite' );
	class_alias( 'PHPUnit_Framework_TestListener', 'PHPUnit\Framework\TestListener' );
	class_alias( 'PHPUnit_Util_GlobalState', 'PHPUnit\Util\GlobalState' );
}