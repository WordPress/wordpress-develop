<?php

trait WPPHPUnit9Compat {

	// New
	function _assertStringContainsString( $a, $b, $c = '' ) {
		// In older versions of PHPUnit, we can just pass through to assertContains.
		$this->assertContains( $a, $b, $c );
	}

	// Removed
	function _assertInternalType( $type, $var, $message = '' ) {
		if ( 'integer' === $type ) {
			$type = 'int';
		} elseif ( 'boolean' === $type ) {
			$type = 'bool';
		}

		$method = "assertIs{$type}";

		$this->$method( $var, $message );
	}

	// Removed
	function _assertNotInternalType( $type, $var, $message = '' ) {
		if ( 'integer' === $type ) {
			$type = 'int';
		} elseif ( 'boolean' === $type ) {
			$type = 'bool';
		}

		$method = "assertIsNot{$type}";

		$this->$method( $var, $message );
	}

	// Deprecated
	function _assertFileDoesNotExist( $file, $message = '' ) {
		$this->assertFileNotExists( $file, $message );
	}
	
}