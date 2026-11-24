<?php
/**
 * An isolated child of a file rules.php skips. The child loads this file after the bootstrap,
 * where WP_PHPUnit_Leak_Listener plans its snapshots. Runs before Tests_Leak_Probe_Class_Fixture,
 * whose chdir() would leave the child unable to read a relative -c configuration.
 */
class Tests_Leak_Probe_Skipped_Child extends WP_UnitTestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_skipped_in_child() {
		$this->assertTrue( true );
	}
}
