<?php

/**
 * Tests that subclass overrides of the legacy deprecation expectation methods keep running.
 *
 * @group testsuite
 * @group phpunit
 *
 * @ticket 54029
 */
class Tests_Includes_LegacyDeprecationOverrides extends WP_UnitTestCase {
	/**
	 * Names of the overridden methods that ran, in order.
	 *
	 * @var string[]
	 */
	private $overrides_run = array();

	public function expectDeprecated() {
		$this->overrides_run[] = 'expectDeprecated';
		parent::expectDeprecated();
	}

	public function expectedDeprecated() {
		$this->overrides_run[] = 'expectedDeprecated';
		parent::expectedDeprecated();
	}

	protected function assert_post_conditions() {
		parent::assert_post_conditions();

		$this->assertSame( array( 'expectDeprecated', 'expectedDeprecated' ), $this->overrides_run );
	}

	/**
	 * @expectedWPDeprecated Tests_Includes_LegacyDeprecationOverrides::mock_deprecated
	 */
	public function test_overrides_run_and_still_handle_expectations() {
		$this->assertSame( array( 'expectDeprecated' ), $this->overrides_run );
		$this->assertTrue( $this->mock_deprecated() );
	}

	protected function mock_deprecated() {
		_deprecated_function( __METHOD__, '2.5' );
		return true;
	}
}
