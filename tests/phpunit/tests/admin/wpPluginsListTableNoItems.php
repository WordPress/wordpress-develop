<?php

/**
 * Tests for WP_Plugins_List_Table::no_items().
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::no_items
 */
class Tests_Admin_wpPluginsListTableNoItems extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * The original plugins global.
	 *
	 * @var mixed
	 */
	private $original_plugins;

	/**
	 * The original request URI, which the list table changes for a search,
	 * or null when it was not set.
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	/**
	 * Sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Saves the plugins global.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_plugins     = $GLOBALS['plugins'] ?? null;
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
		$GLOBALS['plugins']         = array( 'all' => array() );
	}

	/**
	 * Restores the plugins global and the search request.
	 */
	public function tear_down() {
		$GLOBALS['plugins'] = $this->original_plugins;

		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		unset( $_REQUEST['s'] );

		parent::tear_down();
	}

	/**
	 * Tests the message when no plugins are installed.
	 *
	 * @ticket 65819
	 */
	public function test_should_report_no_plugins_available() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame( 'No plugins are currently available.', $this->get_no_items() );
	}

	/**
	 * Tests the message when plugins are installed but none match the view.
	 *
	 * @ticket 65819
	 */
	public function test_should_report_no_plugins_found() {
		wp_set_current_user( self::$admin_id );

		$GLOBALS['plugins']['all'] = array( 'hello.php' => array( 'Name' => 'Hello Dolly' ) );

		$this->assertSame( 'No plugins found.', $this->get_no_items() );
	}

	/**
	 * Tests that an empty search term is not treated as a search.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_search_terms
	 *
	 * @param string $search The search term.
	 */
	public function test_should_ignore_empty_search_term( $search ) {
		wp_set_current_user( self::$admin_id );

		$_REQUEST['s'] = $search;

		$this->assertSame( 'No plugins are currently available.', $this->get_no_items() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_search_terms() {
		return array(
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
		);
	}

	/**
	 * Tests the message and directory link for a search without results.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_report_search_without_results_with_directory_link() {
		wp_set_current_user( self::$admin_id );

		$_REQUEST['s'] = 'contact form';

		$this->assertSame(
			'No plugins found for: <strong>contact form</strong>.'
			. ' <a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugin-install.php?tab=search&#038;s=contact+form">Search for plugins in the WordPress Plugin Directory.</a>',
			$this->get_no_items()
		);
	}

	/**
	 * Tests that the search term is unslashed, decoded and escaped.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_escape_search_term() {
		wp_set_current_user( self::$admin_id );

		$_REQUEST['s'] = 'it\\\'s %3Cb%3E & "x"';

		$this->assertSame(
			'No plugins found for: <strong>it&#039;s &lt;b&gt; &amp; &quot;x&quot;</strong>.'
			. ' <a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugin-install.php?tab=search&#038;s=it%26%23039%3Bs+%26lt%3Bb%26gt%3B+%26amp%3B+%26quot%3Bx%26quot%3B">Search for plugins in the WordPress Plugin Directory.</a>',
			$this->get_no_items()
		);
	}

	/**
	 * Tests that no directory link is shown to a user who cannot install plugins.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_link_directory_for_user_without_capability() {
		wp_set_current_user( self::$subscriber_id );

		$_REQUEST['s'] = 'contact form';

		$this->assertSame( 'No plugins found for: <strong>contact form</strong>.', $this->get_no_items() );
	}

	/**
	 * Tests that no directory link is shown on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_not_link_directory_on_multisite() {
		grant_super_admin( self::$admin_id );
		wp_set_current_user( self::$admin_id );

		$_REQUEST['s'] = 'contact form';

		try {
			$output = $this->get_no_items();
		} finally {
			revoke_super_admin( self::$admin_id );
		}

		$this->assertSame( 'No plugins found for: <strong>contact form</strong>.', $output );
	}

	/**
	 * Returns the output of no_items().
	 *
	 * @return string The output.
	 */
	private function get_no_items() {
		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );

		return get_echo( array( $table, 'no_items' ) );
	}
}
