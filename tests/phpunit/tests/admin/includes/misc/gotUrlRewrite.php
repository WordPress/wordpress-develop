<?php

/**
 * Tests for the got_url_rewrite() function.
 *
 * @group admin
 * @group rewrite
 *
 * @covers ::got_url_rewrite
 */
class Tests_got_url_rewrite extends WP_UnitTestCase {

	/**
	 * Tests that got_url_rewrite() correctly detects URL rewrite support based on server and filters.
	 *
	 * @ticket 65135
	 *
	 * @dataProvider data_got_url_rewrite
	 *
	 * @param bool $expected     The expected result from got_url_rewrite().
	 * @param bool $mod_rewrite  Whether mod_rewrite is reported as supported.
	 * @param bool $is_nginx     Value for the $is_nginx global.
	 * @param bool $is_caddy     Value for the $is_caddy global.
	 * @param bool $iis7_perm    Whether IIS7 supports permalinks (simulated).
	 * @param bool|null $filter_val Optional value for the 'got_url_rewrite' filter.
	 */
	public function test_got_url_rewrite( $expected, $mod_rewrite, $is_nginx, $is_caddy, $iis7_perm, $filter_val = null ) {
		global $is_nginx, $is_caddy;

		// Backup globals.
		$prev_nginx = $is_nginx;
		$prev_caddy = $is_caddy;

		$is_nginx = $is_nginx;
		$is_caddy = $is_caddy;

		// Mock got_mod_rewrite and iis7_supports_permalinks via filters if possible.
		// However, got_url_rewrite calls got_mod_rewrite() which calls apache_mod_loaded.
		// We can use the 'got_rewrite' filter to control got_mod_rewrite()'s output.
		add_filter( 'got_rewrite', $mod_rewrite ? '__return_true' : '__return_false' );

		// iis7_supports_permalinks() uses iis7_rewrite_rule_exists() which checks files.
		// For simplicity, if we are on a non-IIS environment, it returns false.
		// If we need to test IIS path, we might need more complex mocking or just rely on the final filter.

		if ( null !== $filter_val ) {
			add_filter( 'got_url_rewrite', $filter_val ? '__return_true' : '__return_false' );
		}

		$this->assertSame( $expected, got_url_rewrite() );

		// Cleanup.
		remove_filter( 'got_rewrite', $mod_rewrite ? '__return_true' : '__return_false' );
		if ( null !== $filter_val ) {
			remove_filter( 'got_url_rewrite', $filter_val ? '__return_true' : '__return_false' );
		}
		$is_nginx = $prev_nginx;
		$is_caddy = $prev_caddy;
	}

	/**
	 * Data provider for test_got_url_rewrite.
	 *
	 * @return array[] {
	 *     @type bool      $expected    The expected result.
	 *     @type bool      $mod_rewrite Whether mod_rewrite is supported.
	 *     @type bool      $is_nginx    Whether server is nginx.
	 *     @type bool      $is_caddy    Whether server is Caddy.
	 *     @type bool      $iis7_perm   Whether IIS7 supports permalinks.
	 *     @type bool|null $filter_val  Optional filter value.
	 * }
	 */
	public function data_got_url_rewrite() {
		return array(
			'All false' => array(
				'expected'    => false,
				'mod_rewrite' => false,
				'is_nginx'    => false,
				'is_caddy'    => false,
				'iis7_perm'   => false,
			),
			'Apache mod_rewrite supported' => array(
				'expected'    => true,
				'mod_rewrite' => true,
				'is_nginx'    => false,
				'is_caddy'    => false,
				'iis7_perm'   => false,
			),
			'Nginx supported' => array(
				'expected'    => true,
				'mod_rewrite' => false,
				'is_nginx'    => true,
				'is_caddy'    => false,
				'iis7_perm'   => false,
			),
			'Caddy supported' => array(
				'expected'    => true,
				'mod_rewrite' => false,
				'is_nginx'    => false,
				'is_caddy'    => true,
				'iis7_perm'   => false,
			),
			'Filter overrides to true' => array(
				'expected'    => true,
				'mod_rewrite' => false,
				'is_nginx'    => false,
				'is_caddy'    => false,
				'iis7_perm'   => false,
				'filter_val'  => true,
			),
			'Filter overrides to false' => array(
				'expected'    => false,
				'mod_rewrite' => true,
				'is_nginx'    => true,
				'is_caddy'    => true,
				'iis7_perm'   => true,
				'filter_val'  => false,
			),
		);
	}
}
