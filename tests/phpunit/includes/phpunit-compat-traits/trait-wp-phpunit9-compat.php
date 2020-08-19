<?php

trait WPPHPUnit9Compat {

	// New
	function _assertStringContainsString( $needle, $haystack, $message = '' ) {
		// In older versions of PHPUnit, we can just pass through to assertContains.
		$this->assertContains( $needle, $haystack, $message );
	}

	// New
	function assertStringNotContainsString( $needle, $haystack, $message = '' ) {
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

	// Deprecated
	function _assertFileDoesNotExist( $file, $message = '' ) {
		$this->assertFileNotExists( $file, $message );
	}

	// Removed
	function _assertInternalType( $expected, $actual, $message = '' ) {
		if ( 'integer' === $expected ) {
			$type = 'int';
		} elseif ( 'boolean' === $expected ) {
			$type = 'bool';
		}

		$method = "assertIs{$expected}";

		$this->$method( $actual, $message );
	}

	// Removed
	function _assertNotInternalType( $expected, $actual, $message = '' ) {
		if ( 'integer' === $expected ) {
			$type = 'int';
		} elseif ( 'boolean' === $expected ) {
			$type = 'bool';
		}

		$method = "assertIsNot{$expected}";

		$this->$method( $actual, $message );
	}

}