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
	 * Saved value of the $is_nginx global.
	 */
	private bool $prev_nginx;

	/**
	 * Saved value of the $is_caddy global.
	 */
	private bool $prev_caddy;

	public function set_up() {
		parent::set_up();
		global $is_nginx, $is_caddy;
		$this->prev_nginx = $is_nginx;
		$this->prev_caddy = $is_caddy;
	}

	public function tear_down() {
		global $is_nginx, $is_caddy;
		$is_nginx = $this->prev_nginx;
		$is_caddy = $this->prev_caddy;
		parent::tear_down();
	}

	/**
	 * Tests that got_url_rewrite() correctly detects URL rewrite support based on server and filters.
	 *
	 * @ticket 65135
	 *
	 * @dataProvider data_got_url_rewrite
	 *
	 * @param bool $expected     The expected result from got_url_rewrite().
	 * @param bool $mod_rewrite  Whether mod_rewrite is reported as supported.
	 * @param bool $is_nginx_val Value for the $is_nginx global.
	 * @param bool $is_caddy_val Value for the $is_caddy global.
	 * @param bool $iis7_perm    Whether IIS7 supports permalinks (simulated).
	 * @param bool|null $filter_val Optional value for the 'got_url_rewrite' filter.
	 */
	public function test_got_url_rewrite( $expected, $mod_rewrite, $is_nginx_val, $is_caddy_val, $iis7_perm, $filter_val = null ) {
		global $is_nginx, $is_caddy;

		$is_nginx = $is_nginx_val;
		$is_caddy = $is_caddy_val;

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

  /**
  * Data provider for test_got_url_rewrite.
  *
  * @return array<string, array{
  *     expected:    bool,
  *     mod_rewrite: bool,
  *     is_nginx:    bool,
  *     is_caddy:    bool,
  *     iis7_perm:   bool,
  *     filter_val?: bool|null,
  * }>
  */
	public function data_got_url_rewrite(): array {
		return array(
			'All false'                    => array(
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
			'Nginx supported'              => array(
				'expected'    => true,
				'mod_rewrite' => false,
				'is_nginx'    => true,
				'is_caddy'    => false,
				'iis7_perm'   => false,
			),
			'Caddy supported'              => array(
				'expected'    => true,
				'mod_rewrite' => false,
				'is_nginx'    => false,
				'is_caddy'    => true,
				'iis7_perm'   => false,
			),
			'Filter overrides to true'     => array(
				'expected'    => true,
				'mod_rewrite' => false,
				'is_nginx'    => false,
				'is_caddy'    => false,
				'iis7_perm'   => false,
				'filter_val'  => true,
			),
			'Filter overrides to false'    => array(
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
