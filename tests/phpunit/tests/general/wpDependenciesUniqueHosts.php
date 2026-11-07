<?php

/**
 * Tests for the `wp_dependencies_unique_hosts()` function.
 *
 * @group general
 * @group script
 *
 * @covers ::wp_dependencies_unique_hosts
 */
class Tests_General_WpDependenciesUniqueHosts extends WP_UnitTestCase {

	protected $old_wp_scripts;
	protected $old_wp_styles;
	protected $old_server_name;

	public function set_up() {
		parent::set_up();
		$this->old_wp_scripts  = $GLOBALS['wp_scripts'] ?? null;
		$this->old_wp_styles   = $GLOBALS['wp_styles'] ?? null;
		$this->old_server_name = $_SERVER['SERVER_NAME'] ?? '';

		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
	}

	public function tear_down() {
		$GLOBALS['wp_scripts']  = $this->old_wp_scripts;
		$GLOBALS['wp_styles']   = $this->old_wp_styles;
		$_SERVER['SERVER_NAME'] = $this->old_server_name;
		parent::tear_down();
	}

	/**
	 * Tests that an empty queue returns an empty array.
	 *
	 * @ticket 65819
	 */
	public function test_wp_dependencies_unique_hosts_empty() {
		$this->assertSame( array(), wp_dependencies_unique_hosts() );
	}

	/**
	 * Tests extracting unique hosts from queued scripts and styles.
	 *
	 * @ticket 65819
	 */
	public function test_wp_dependencies_unique_hosts_extracts_hosts() {
		$_SERVER['SERVER_NAME'] = 'example.org';

		wp_enqueue_script( 'external-script', 'https://cdn.example.com/script.js', array(), null );
		wp_enqueue_style( 'external-style', 'https://assets.example.net/style.css', array(), null );

		$hosts = wp_dependencies_unique_hosts();

		$this->assertSame( array( 'cdn.example.com', 'assets.example.net' ), $hosts );
	}

	/**
	 * Tests that duplicate hosts across scripts and styles are deduplicated.
	 *
	 * @ticket 65819
	 */
	public function test_wp_dependencies_unique_hosts_deduplicates_hosts() {
		$_SERVER['SERVER_NAME'] = 'example.org';

		wp_enqueue_script( 'cdn-script-1', 'https://cdn.example.com/script1.js', array(), null );
		wp_enqueue_script( 'cdn-script-2', 'https://cdn.example.com/script2.js', array(), null );
		wp_enqueue_style( 'cdn-style-1', 'https://cdn.example.com/style.css', array(), null );

		$hosts = wp_dependencies_unique_hosts();

		$this->assertSame( array( 'cdn.example.com' ), $hosts );
	}

	/**
	 * Tests that hosts matching $_SERVER['SERVER_NAME'] are excluded.
	 *
	 * @ticket 65819
	 */
	public function test_wp_dependencies_unique_hosts_excludes_local_server_name() {
		$_SERVER['SERVER_NAME'] = 'local.example.org';

		wp_enqueue_script( 'local-script', 'https://local.example.org/js/app.js', array(), null );
		wp_enqueue_script( 'external-script', 'https://cdn.example.com/lib.js', array(), null );

		$hosts = wp_dependencies_unique_hosts();

		$this->assertSame( array( 'cdn.example.com' ), $hosts );
	}

	/**
	 * Tests that relative URLs without a host are ignored.
	 *
	 * @ticket 65819
	 */
	public function test_wp_dependencies_unique_hosts_ignores_relative_urls() {
		wp_enqueue_script( 'relative-script', '/wp-includes/js/script.js', array(), null );

		$this->assertSame( array(), wp_dependencies_unique_hosts() );
	}
}
