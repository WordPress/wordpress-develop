<?php

/**
 * Tests for disabling pings in non-production environments.
 *
 * @group comment
 * @covers ::wp_should_disable_pings_for_environment
 * @covers ::wp_maybe_disable_outgoing_pings_for_environment
 * @covers ::wp_maybe_disable_trackback_for_environment
 * @covers ::wp_maybe_disable_xmlrpc_pingback_for_environment
 *
 * @ticket 64837
 */
class Tests_Comment_DisablePingsForEnvironment extends WP_UnitTestCase {

	/**
	 * Stores the original WP_ENVIRONMENT_TYPE env value for cleanup.
	 *
	 * @var string|false
	 */
	private $original_env;

	public function set_up() {
		parent::set_up();
		$this->original_env = getenv( 'WP_ENVIRONMENT_TYPE' );
	}

	public function tear_down() {
		if ( false === $this->original_env ) {
			putenv( 'WP_ENVIRONMENT_TYPE' );
		} else {
			putenv( 'WP_ENVIRONMENT_TYPE=' . $this->original_env );
		}
		parent::tear_down();
	}

	/**
	 * @ticket 64837
	 */
	public function test_should_disable_returns_true_for_local() {
		putenv( 'WP_ENVIRONMENT_TYPE=local' );
		$this->assertTrue( wp_should_disable_pings_for_environment() );
	}

	/**
	 * @ticket 64837
	 */
	public function test_should_disable_returns_true_for_development() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );
		$this->assertTrue( wp_should_disable_pings_for_environment() );
	}

	/**
	 * @ticket 64837
	 */
	public function test_should_disable_returns_true_for_staging() {
		putenv( 'WP_ENVIRONMENT_TYPE=staging' );
		$this->assertTrue( wp_should_disable_pings_for_environment() );
	}

	/**
	 * @ticket 64837
	 */
	public function test_should_disable_returns_false_for_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );
		$this->assertFalse( wp_should_disable_pings_for_environment() );
	}

	/**
	 * @ticket 64837
	 */
	public function test_filter_can_enable_pings_in_non_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=local' );
		add_filter( 'wp_should_disable_pings_for_environment', '__return_false' );

		$this->assertFalse( wp_should_disable_pings_for_environment() );
	}

	/**
	 * @ticket 64837
	 */
	public function test_filter_can_disable_pings_in_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );
		add_filter( 'wp_should_disable_pings_for_environment', '__return_true' );

		$this->assertTrue( wp_should_disable_pings_for_environment() );
	}

	/**
	 * @ticket 64837
	 */
	public function test_filter_receives_environment_type() {
		putenv( 'WP_ENVIRONMENT_TYPE=staging' );

		$received_type = null;
		add_filter(
			'wp_should_disable_pings_for_environment',
			function ( $should_disable, $environment_type ) use ( &$received_type ) {
				$received_type = $environment_type;
				return $should_disable;
			},
			10,
			2
		);

		wp_should_disable_pings_for_environment();

		$this->assertSame( 'staging', $received_type );
	}

	/**
	 * @ticket 64837
	 */
	public function test_outgoing_pingbacks_removed_in_non_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );

		// Re-register the defaults to ensure a clean state.
		add_action( 'do_all_pings', 'do_all_pingbacks', 10, 0 );

		// Fire the priority-1 callback.
		wp_maybe_disable_outgoing_pings_for_environment();

		$this->assertFalse( has_action( 'do_all_pings', 'do_all_pingbacks' ) );
	}

	/**
	 * @ticket 64837
	 */
	public function test_outgoing_trackbacks_removed_in_non_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );

		add_action( 'do_all_pings', 'do_all_trackbacks', 10, 0 );

		wp_maybe_disable_outgoing_pings_for_environment();

		$this->assertFalse( has_action( 'do_all_pings', 'do_all_trackbacks' ) );
	}

	/**
	 * @ticket 64837
	 */
	public function test_outgoing_generic_ping_removed_in_non_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );

		add_action( 'do_all_pings', 'generic_ping', 10, 0 );

		wp_maybe_disable_outgoing_pings_for_environment();

		$this->assertFalse( has_action( 'do_all_pings', 'generic_ping' ) );
	}

	/**
	 * @ticket 64837
	 */
	public function test_enclosures_not_removed_in_non_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );

		add_action( 'do_all_pings', 'do_all_enclosures', 10, 0 );

		wp_maybe_disable_outgoing_pings_for_environment();

		$this->assertTrue( has_action( 'do_all_pings', 'do_all_enclosures', 10 ) );
	}

	/**
	 * @ticket 64837
	 */
	public function test_outgoing_pings_preserved_in_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );

		add_action( 'do_all_pings', 'do_all_pingbacks', 10, 0 );
		add_action( 'do_all_pings', 'do_all_trackbacks', 10, 0 );
		add_action( 'do_all_pings', 'generic_ping', 10, 0 );

		wp_maybe_disable_outgoing_pings_for_environment();

		$this->assertTrue( has_action( 'do_all_pings', 'do_all_pingbacks', 10 ), 'do_all_pingbacks should still be hooked at priority 10.' );
		$this->assertTrue( has_action( 'do_all_pings', 'do_all_trackbacks', 10 ), 'do_all_trackbacks should still be hooked at priority 10.' );
		$this->assertTrue( has_action( 'do_all_pings', 'generic_ping', 10 ), 'generic_ping should still be hooked at priority 10.' );
	}

	/**
	 * @ticket 64837
	 */
	public function test_trackback_hook_is_registered() {
		$this->assertTrue( has_action( 'pre_trackback_post', 'wp_maybe_disable_trackback_for_environment', 10 ) );
	}

	/**
	 * @ticket 64837
	 */
	public function test_pings_open_unaffected_by_environment() {
		putenv( 'WP_ENVIRONMENT_TYPE=local' );

		$post = self::factory()->post->create_and_get(
			array( 'ping_status' => 'open' )
		);

		$this->assertTrue( pings_open( $post ) );
	}

	/**
	 * @ticket 64837
	 */
	public function test_xmlrpc_pingback_removed_in_non_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );

		$methods = array(
			'pingback.ping'                    => 'this:pingback_ping',
			'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks',
			'wp.getUsersBlogs'                 => 'this:wp_getUsersBlogs',
		);

		$filtered = wp_maybe_disable_xmlrpc_pingback_for_environment( $methods );

		$this->assertArrayNotHasKey( 'pingback.ping', $filtered );
	}

	/**
	 * @ticket 64837
	 */
	public function test_xmlrpc_pingback_preserved_in_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );

		$methods = array(
			'pingback.ping'    => 'this:pingback_ping',
			'wp.getUsersBlogs' => 'this:wp_getUsersBlogs',
		);

		$filtered = wp_maybe_disable_xmlrpc_pingback_for_environment( $methods );

		$this->assertArrayHasKey( 'pingback.ping', $filtered );
	}

	/**
	 * @ticket 64837
	 */
	public function test_xmlrpc_other_methods_preserved_in_non_production() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );

		$methods = array(
			'pingback.ping'                    => 'this:pingback_ping',
			'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks',
			'wp.getUsersBlogs'                 => 'this:wp_getUsersBlogs',
			'wp.getPost'                       => 'this:wp_getPost',
		);

		$filtered = wp_maybe_disable_xmlrpc_pingback_for_environment( $methods );

		$this->assertArrayHasKey( 'pingback.extensions.getPingbacks', $filtered );
		$this->assertArrayHasKey( 'wp.getUsersBlogs', $filtered );
		$this->assertArrayHasKey( 'wp.getPost', $filtered );
	}
}
