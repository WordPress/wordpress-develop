<?php

trait WPPHPUnit8Compat {
	// New
	function _assertIsArray( $var, $message = '' ) {
		$this->assertInternalType( 'array', $var, $message );
	}

	// New
	function _assertIsBool( $var, $message = '' ) {
		$this->assertInternalType( 'boolean', $var, $message );
	}

	// New
	function _assertIsFloat( $var, $message = '' ) {
		$this->assertInternalType( 'float', $var, $message );
	}

	// New
	function _assertIsInt( $var, $message = '' ) {
		$this->assertInternalType( 'integer', $var, $message );
	}

	// New
	function _assertIsNumeric( $var, $message = '' ) {
		$this->assertInternalType( 'numeric', $var, $message );
	}

	// New
	function _assertIsObject( $var, $message = '' ) {
		$this->assertInternalType( 'object', $var, $message );
	}

	// New
	function _assertIsResource( $var, $message = '' ) {
		$this->assertInternalType( 'resource', $var, $message );
	}

	// New
	function _assertIsString( $var, $message = '' ) {
		$this->assertInternalType( 'string', $var, $message );
	}

	// New
	function _assertIsScalar( $var, $message = '' ) {
		$this->assertInternalType( 'scalar', $var, $message );
	}

	// New
	function _assertIsCallable( $var, $message = '' ) {
		$this->assertInternalType( 'callable', $var, $message );
	}

	// New
	function _assertIsIterable( $var, $message = '' ) {
		$this->assertInternalType( 'iterable', $var, $message );
	}

	// New
	function _assertIsNotArray( $var, $message = '' ) {
		$this->assertNotInternalType( 'array', $var, $message );
	}

	// New
	function _assertIsNotBool( $var, $message = '' ) {
		$this->assertNotInternalType( 'boolean', $var, $message );
	}

	// New
	function _assertIsNotFloat( $var, $message = '' ) {
		$this->assertNotInternalType( 'float', $var, $message );
	}

	// New
	function _assertIsNotInt( $var, $message = '' ) {
		$this->assertNotInternalType( 'integer', $var, $message );
	}

	// New
	function _assertIsNotNumeric( $var, $message = '' ) {
		$this->assertNotInternalType( 'numeric', $var, $message );
	}

	// New
	function _assertIsNotObject( $var, $message = '' ) {
		$this->assertNotInternalType( 'object', $var, $message );
	}

	// New
	function _assertIsNotResource( $var, $message = '' ) {
		$this->assertNotInternalType( 'resource', $var, $message );
	}

	// New
	function _assertIsNotString( $var, $message = '' ) {
		$this->assertNotInternalType( 'string', $var, $message );
	}

	// New
	function _assertIsNotScalar( $var, $message = '' ) {
		$this->assertNotInternalType( 'scalar', $var, $message );
	}

	// New
	function _assertIsNotCallable( $var, $message = '' ) {
		$this->assertNotInternalType( 'callable', $var, $message );
	}

	// New
	function _assertIsNotIterable( $var, $message = '' ) {
		$this->assertNotInternalType( 'iterable', $var, $message );
	}
}