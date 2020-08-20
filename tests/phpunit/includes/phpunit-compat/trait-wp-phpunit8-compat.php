<?php
/**
 * Trait that contains any new/needed functionality from PHPUnit 8
 */
trait WP_PHPUnit8_Compat {

	// New
	function _assertIsArray( $actual, $message = '' ) {
		$this->assertInternalType( 'array', $actual, $message );
	}

	// New
	function _assertIsBool( $actual, $message = '' ) {
		$this->assertInternalType( 'boolean', $actual, $message );
	}

	// New
	function _assertIsFloat( $actual, $message = '' ) {
		$this->assertInternalType( 'float', $actual, $message );
	}

	// New
	function _assertIsInt( $actual, $message = '' ) {
		$this->assertInternalType( 'integer', $actual, $message );
	}

	// New
	function _assertIsNumeric( $actual, $message = '' ) {
		$this->assertInternalType( 'numeric', $actual, $message );
	}

	// New
	function _assertIsObject( $actual, $message = '' ) {
		$this->assertInternalType( 'object', $actual, $message );
	}

	// New
	function _assertIsResource( $actual, $message = '' ) {
		$this->assertInternalType( 'resource', $actual, $message );
	}

	// New
	function _assertIsString( $actual, $message = '' ) {
		$this->assertInternalType( 'string', $actual, $message );
	}

	// New
	function _assertIsScalar( $actual, $message = '' ) {
		$this->assertInternalType( 'scalar', $actual, $message );
	}

	// New
	function _assertIsCallable( $actual, $message = '' ) {
		$this->assertInternalType( 'callable', $actual, $message );
	}

	// New
	function _assertIsIterable( $actual, $message = '' ) {
		$this->assertInternalType( 'iterable', $actual, $message );
	}

	// New
	function _assertIsNotArray( $actual, $message = '' ) {
		$this->assertNotInternalType( 'array', $actual, $message );
	}

	// New
	function _assertIsNotBool( $actual, $message = '' ) {
		$this->assertNotInternalType( 'boolean', $actual, $message );
	}

	// New
	function _assertIsNotFloat( $actual, $message = '' ) {
		$this->assertNotInternalType( 'float', $actual, $message );
	}

	// New
	function _assertIsNotInt( $actual, $message = '' ) {
		$this->assertNotInternalType( 'integer', $actual, $message );
	}

	// New
	function _assertIsNotNumeric( $actual, $message = '' ) {
		$this->assertNotInternalType( 'numeric', $actual, $message );
	}

	// New
	function _assertIsNotObject( $actual, $message = '' ) {
		$this->assertNotInternalType( 'object', $actual, $message );
	}

	// New
	function _assertIsNotResource( $actual, $message = '' ) {
		$this->assertNotInternalType( 'resource', $actual, $message );
	}

	// New
	function _assertIsNotString( $actual, $message = '' ) {
		$this->assertNotInternalType( 'string', $actual, $message );
	}

	// New
	function _assertIsNotScalar( $actual, $message = '' ) {
		$this->assertNotInternalType( 'scalar', $actual, $message );
	}

	// New
	function _assertIsNotCallable( $actual, $message = '' ) {
		$this->assertNotInternalType( 'callable', $actual, $message );
	}

	// New
	function _assertIsNotIterable( $actual, $message = '' ) {
		$this->assertNotInternalType( 'iterable', $actual, $message );
	}
}
