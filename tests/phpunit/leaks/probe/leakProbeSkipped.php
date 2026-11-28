<?php
/**
 * Leaks a committed row, which rules.php hides from WP_PHPUnit_Leak_Listener by skipping this file.
 */
class Tests_Leak_Probe_Skipped extends WP_UnitTestCase {

	public function test_leaks_committed_option() {
		global $wpdb;

		update_option( 'leak_probe_skipped', 'leaked' );
		$wpdb->query( 'COMMIT' );
		$this->assertTrue( true );
	}
}
