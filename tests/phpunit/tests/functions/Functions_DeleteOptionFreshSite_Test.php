<?php

/**
 * Tests for _delete_option_fresh_site function.
 *
 * @group functions
 *
 * @covers ::_delete_option_fresh_site
 */
class Functions_DeleteOptionFreshSite_Test extends WP_UnitTestCase {

	/**
	 * @ticket 57191
	 */
	public function test_delete_option_fresh_site() {
		$current_option = get_option( 'fresh_site' );
		update_option( 'fresh_site', '1' );

		_delete_option_fresh_site();
		$actual = get_option( 'fresh_site' );
		update_option( 'fresh_site', $current_option );

		$this->assertSame( '0', $actual );
	}
}
