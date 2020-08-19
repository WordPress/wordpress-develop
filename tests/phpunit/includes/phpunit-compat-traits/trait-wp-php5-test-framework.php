<?php

trait WPPHP5TestFramework {

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass() {
		if ( is_callable( 'static::_setUpBeforeClass' ) ) {
			static::_setUpBeforeClass();
		}
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass() {
		if ( is_callable( 'static::_tearDownAfterClass' ) ) {
			static::_tearDownAfterClass();
		}
    }

    /**
     * This method is called before each test.
     */
    protected function setUp() {
		if ( is_callable( 'static::_setUp' ) ) {
			static::_setUp();
		}
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown() {
		if ( is_callable( 'static::_tearDown' ) ) {
			static::_tearDown();
		}
	}

	/**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between setUp() and test.
     */
    protected function assertPreConditions() {
		if ( is_callable( 'static::_assertPreConditions' ) ) {
			static::_assertPreConditions();
		}
    }

    /**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between test and tearDown().
     */
    protected function assertPostConditions() {
		if ( is_callable( 'static::_assertPostConditions' ) ) {
			static::_assertPostConditions();
		}
    }

}