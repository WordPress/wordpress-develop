<?php

trait WP_PHP5_Test_Framework {

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
		if ( method_exists( $this, '_setUp' ) ) {
			$this->_setUp();
		}
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown() {
		if ( method_exists( $this, '_tearDown' ) ) {
			$this->_tearDown();
		}
	}

	/**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between setUp() and test.
     */
    protected function assertPreConditions() {
		if ( method_exists( $this, '_assertPreConditions' ) ) {
			$this->_assertPreConditions();
		}
    }

    /**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between test and tearDown().
     */
    protected function assertPostConditions() {
		if ( method_exists( $this, '_assertPostConditions' ) ) {
			$this->_assertPostConditions();
		}
    }

}