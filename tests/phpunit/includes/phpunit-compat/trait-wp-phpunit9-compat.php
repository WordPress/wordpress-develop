<?php
/**
 * Trait that contains any new/needed functionality from PHPUnit 9
 */

use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\Error\Notice;
use PHPUnit\Framework\Error\Warning;

trait WP_PHPUnit9_Compat {

	// New
	function _assertStringContainsString( $needle, $haystack, $message = '' ) {
		// In older versions of PHPUnit, we can just pass through to assertContains.
		$this->assertContains( $needle, $haystack, $message );
	}

	// New
	function _assertStringNotContainsString( $needle, $haystack, $message = '' ) {
		// In older versions of PHPUnit, we can just pass through to assertContains.
		$this->assertNotContains( $needle, $haystack, $message );
	}

	// New
	function _assertMatchesRegularExpression( $pattern, $string, $message = '' ) {
		$this->assertRegExp( $pattern, $string, $message );
	}

	// New
	function _assertDoesNotMatchRegularExpression( $pattern, $string, $message = '' ) {
		$this->assertNotRegExp( $pattern, $string, $message );
	}

	// New
	function _expectDeprecation() {
		$this->expectException( Deprecated::class );
	}

	// New
	function _expectNotice() {
		$this->expectException( Notice::class );
	}

	// New
	function _expectWarning() {
		$this->expectException( Warning::class );
	}

	// Deprecated
	function _assertFileDoesNotExist( $file, $message = '' ) {
		$this->assertFileNotExists( $file, $message );
	}

	// Removed
	function _assertInternalType( $expected, $actual, $message = '' ) {
		if ( 'integer' === $expected ) {
			$expected = 'int';
		} elseif ( 'boolean' === $expected ) {
			$expected = 'bool';
		}

		$method = "assertIs{$expected}";

		$this->$method( $actual, $message );
	}

	// Removed
	function _assertNotInternalType( $expected, $actual, $message = '' ) {
		if ( 'integer' === $expected ) {
			$expected = 'int';
		} elseif ( 'boolean' === $expected ) {
			$expected = 'bool';
		}

		$method = "assertIsNot{$expected}";

		$this->$method( $actual, $message );
	}

}
