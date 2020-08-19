<?php

trait WPPHPUnit9Compat {

	// Removed
	function _assertInternalType( $type, $var, $message = "" ) {
		if ( 'integer' === $type ) {
			$type = 'int';
		} elseif ( 'boolean' === $type ) {
			$type = 'bool';
		}

		$method = "assertIs{$type}";

		$this->$method( $var, $message );
	}

	// Removed
	function _assertNotInternalType( $type, $var, $message = "" ) {
		if ( 'integer' === $type ) {
			$type = 'int';
		} elseif ( 'boolean' === $type ) {
			$type = 'bool';
		}

		$method = "assertIsNot{$type}";

		$this->$method( $var, $message );
	}

	// Deprecated
	function _assertFileDoesNotExist( $file, $message = "" ) {
		$this->assertFileNotExists( $file, $message );
	}
	
}