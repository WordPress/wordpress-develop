<?php

trait WPPHP71TestFramework {

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void {
		if ( is_callable( 'static::_setUpBeforeClass' ) ) {
			static::_setUpBeforeClass();
		}
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass(): void {
		if ( is_callable( 'static::_tearDownAfterClass' ) ) {
			static::_tearDownAfterClass();
		}
    }

    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
		if ( is_callable( 'static::_setUp' ) ) {
			static::_setUp();
		}
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown(): void {
		if ( is_callable( 'static::_tearDown' ) ) {
			static::_tearDown();
		}
	}

	/**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between setUp() and test.
     */
    protected function assertPreConditions(): void {
		if ( is_callable( 'static::_assertPreConditions' ) ) {
			static::_assertPreConditions();
		}
    }

    /**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between test and tearDown().
     */
    protected function assertPostConditions(): void {
		if ( is_callable( 'static::_assertPostConditions' ) ) {
			static::_assertPostConditions();
		}
    }

}