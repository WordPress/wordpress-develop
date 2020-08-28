<?php
/**
 * Trait that contains any new/deprecated/removed functionality in PHPUnit 7
 */
trait WP_PHPUnit7_Compat {

	// New
	public function _assertEqualsWithDelta( $expected, $actual, $delta, $message = '' ) {
		$this->assertEquals( $expected, $actual, $message, $delta );
	}

	// New
	public function _assertNotEqualsWithDelta( $expected, $actual, $delta, $message = '' ) {
		$this->assertNotEquals( $expected, $actual, $message, $delta );
	}

	// New
	public function _assertIsArray( $actual, $message = '' ) {
		$this->assertInternalType( 'array', $actual, $message );
	}

	// New
	public function _assertIsBool( $actual, $message = '' ) {
		$this->assertInternalType( 'boolean', $actual, $message );
	}

	// New
	public function _assertIsFloat( $actual, $message = '' ) {
		$this->assertInternalType( 'float', $actual, $message );
	}

	// New
	public function _assertIsInt( $actual, $message = '' ) {
		$this->assertInternalType( 'integer', $actual, $message );
	}

	// New
	public function _assertIsNumeric( $actual, $message = '' ) {
		$this->assertInternalType( 'numeric', $actual, $message );
	}

	// New
	public function _assertIsObject( $actual, $message = '' ) {
		$this->assertInternalType( 'object', $actual, $message );
	}

	// New
	public function _assertIsResource( $actual, $message = '' ) {
		$this->assertInternalType( 'resource', $actual, $message );
	}

	// New
	public function _assertIsString( $actual, $message = '' ) {
		$this->assertInternalType( 'string', $actual, $message );
	}

	// New
	public function _assertIsScalar( $actual, $message = '' ) {
		$this->assertInternalType( 'scalar', $actual, $message );
	}

	// New
	public function _assertIsCallable( $actual, $message = '' ) {
		$this->assertInternalType( 'callable', $actual, $message );
	}

	// New
	public function _assertIsIterable( $actual, $message = '' ) {
		$this->assertInternalType( 'iterable', $actual, $message );
	}

	// New
	public function _assertIsNotArray( $actual, $message = '' ) {
		$this->assertNotInternalType( 'array', $actual, $message );
	}

	// New
	public function _assertIsNotBool( $actual, $message = '' ) {
		$this->assertNotInternalType( 'boolean', $actual, $message );
	}

	// New
	public function _assertIsNotFloat( $actual, $message = '' ) {
		$this->assertNotInternalType( 'float', $actual, $message );
	}

	// New
	public function _assertIsNotInt( $actual, $message = '' ) {
		$this->assertNotInternalType( 'integer', $actual, $message );
	}

	// New
	public function _assertIsNotNumeric( $actual, $message = '' ) {
		$this->assertNotInternalType( 'numeric', $actual, $message );
	}

	// New
	public function _assertIsNotObject( $actual, $message = '' ) {
		$this->assertNotInternalType( 'object', $actual, $message );
	}

	// New
	public function _assertIsNotResource( $actual, $message = '' ) {
		$this->assertNotInternalType( 'resource', $actual, $message );
	}

	// New
	public function _assertIsNotString( $actual, $message = '' ) {
		$this->assertNotInternalType( 'string', $actual, $message );
	}

	// New
	public function _assertIsNotScalar( $actual, $message = '' ) {
		$this->assertNotInternalType( 'scalar', $actual, $message );
	}

	// New
	public function _assertIsNotCallable( $actual, $message = '' ) {
		$this->assertNotInternalType( 'callable', $actual, $message );
	}

	// New
	public function _assertIsNotIterable( $actual, $message = '' ) {
		$this->assertNotInternalType( 'iterable', $actual, $message );
	}
}
