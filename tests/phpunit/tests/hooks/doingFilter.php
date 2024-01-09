<?php

/**
 * Test doing_filter().
 *
 * @group hooks
 * @covers ::doing_filter
 */
class Tests_Hooks_DoingFilter extends WP_UnitTestCase {

	/**
	 * Flag to keep track whether a certain filter has been applied.
	 *
	 * Used in the `test_doing_filter_real()` test method.
	 *
	 * @var bool
	 */
	protected $apply_testing_filter = false;

	/**
	 * Flag to keep track whether a certain filter has been applied.
	 *
	 * Used in the `test_doing_filter_real()` test method.
	 *
	 * @var bool
	 */
	protected $apply_testing_nested_filter = false;

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Make sure potentially changed properties are reverted to their default value.
		$this->apply_testing_filter        = false;
		$this->apply_testing_nested_filter = false;

		parent::tear_down();
	}

	/**
	 * @ticket 14994
	 */
	public function test_doing_filter() {
		global $wp_current_filter;

		$wp_current_filter = array(); // Set to an empty array first.

		$this->assertFalse( doing_filter() );            // No filter is passed in, and no filter is being processed.
		$this->assertFalse( doing_filter( 'testing' ) ); // Filter is passed in but not being processed.

		$wp_current_filter[] = 'testing';

		$this->assertTrue( doing_filter() );                    // No action is passed in, and a filter is being processed.
		$this->assertTrue( doing_filter( 'testing' ) );         // Filter is passed in and is being processed.
		$this->assertFalse( doing_filter( 'something_else' ) ); // Filter is passed in but not being processed.

		$wp_current_filter = array();
	}

	/**
	 * @ticket 14994
	 */
	public function test_doing_filter_real() {
		$this->assertFalse( doing_filter() );            // No filter is passed in, and no filter is being processed.
		$this->assertFalse( doing_filter( 'testing' ) ); // Filter is passed in but not being processed.

		add_filter( 'testing', array( $this, 'apply_testing_filter' ) );
		$this->assertTrue( has_action( 'testing' ) );
		$this->assertSame( 10, has_action( 'testing', array( $this, 'apply_testing_filter' ) ) );

		apply_filters( 'testing', '' );

		// Make sure it ran.
		$this->assertTrue( $this->apply_testing_filter );

		$this->assertFalse( doing_filter() );            // No longer doing any filters.
		$this->assertFalse( doing_filter( 'testing' ) ); // No longer doing this filter.
	}

	public function apply_testing_filter() {
		$this->apply_testing_filter = true;

		$this->assertTrue( doing_filter() );
		$this->assertTrue( doing_filter( 'testing' ) );
		$this->assertFalse( doing_filter( 'something_else' ) );
		$this->assertFalse( doing_filter( 'testing_nested' ) );

		add_filter( 'testing_nested', array( $this, 'apply_testing_nested_filter' ) );
		$this->assertTrue( has_action( 'testing_nested' ) );
		$this->assertSame( 10, has_action( 'testing_nested', array( $this, 'apply_testing_nested_filter' ) ) );

		apply_filters( 'testing_nested', '' );

		// Make sure it ran.
		$this->assertTrue( $this->apply_testing_nested_filter );

		$this->assertFalse( doing_filter( 'testing_nested' ) );
		$this->assertFalse( doing_filter( 'testing_nested' ) );
	}

	public function apply_testing_nested_filter() {
		$this->apply_testing_nested_filter = true;
		$this->assertTrue( doing_filter() );
		$this->assertTrue( doing_filter( 'testing' ) );
		$this->assertTrue( doing_filter( 'testing_nested' ) );
		$this->assertFalse( doing_filter( 'something_else' ) );
	}
}
