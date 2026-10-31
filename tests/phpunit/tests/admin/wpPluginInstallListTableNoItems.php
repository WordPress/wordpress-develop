<?php

/**
 * Tests for the empty results and table navigation of WP_Plugin_Install_List_Table.
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_wpPluginInstallListTableNoItems extends WP_UnitTestCase {

	/**
	 * The original tab global.
	 *
	 * @var mixed
	 */
	private $original_tab;

	/**
	 * The original request URI, or null when it was not set.
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	/**
	 * Saves the tab global and the request URI.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_tab         = $GLOBALS['tab'] ?? null;
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;

		$_SERVER['REQUEST_URI'] = '/wp-admin/plugin-install.php?tab=search&s=forms';
	}

	/**
	 * Restores the tab global and the request URI.
	 */
	public function tear_down() {
		$GLOBALS['tab'] = $this->original_tab;

		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		parent::tear_down();
	}

	/**
	 * Tests the message when a search has no results.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugin_Install_List_Table::no_items
	 */
	public function test_no_items_should_suggest_a_different_search() {
		$this->assertSame(
			'<div class="no-plugin-results">No plugins found. Try a different search.</div>',
			trim( get_echo( array( $this->get_table(), 'no_items' ) ) )
		);
	}

	/**
	 * Tests that no table navigation is shown on the Featured tab.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugin_Install_List_Table::display_tablenav
	 *
	 * @dataProvider data_positions
	 *
	 * @param string $which The navigation position.
	 */
	public function test_display_tablenav_should_output_nothing_on_featured_tab( $which ) {
		$GLOBALS['tab'] = 'featured';

		$this->assertSame( '', $this->get_tablenav( $which ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_positions() {
		return array(
			'top'    => array( 'top' ),
			'bottom' => array( 'bottom' ),
		);
	}

	/**
	 * Tests the top navigation, with the referer field and the table header action.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugin_Install_List_Table::display_tablenav
	 */
	public function test_display_tablenav_should_output_top_navigation() {
		$GLOBALS['tab'] = 'search';

		$action = new MockAction();
		add_action( 'install_plugins_table_header', array( $action, 'action' ) );
		add_action(
			'install_plugins_table_header',
			static function () {
				echo '<span>Header</span>';
			}
		);

		$this->assertSame(
			'<input type="hidden" name="_wp_http_referer" value="/wp-admin/plugin-install.php?tab=search&#038;s=forms" />'
			. '<div class="tablenav top"><div class="alignleft actions"><span>Header</span></div>'
			. '<div class="tablenav-pages no-pages"><span class="displaying-num">0 items</span></div><br class="clear" /></div>',
			$this->get_tablenav( 'top' ),
			'The top navigation should include the referer field and the header.'
		);
		$this->assertSame( 1, $action->get_call_count(), 'The table header action should fire once.' );
	}

	/**
	 * Tests the bottom navigation, without the table header action.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugin_Install_List_Table::display_tablenav
	 */
	public function test_display_tablenav_should_output_bottom_navigation() {
		$GLOBALS['tab'] = 'search';

		$action = new MockAction();
		add_action( 'install_plugins_table_header', array( $action, 'action' ) );

		$this->assertSame( '<div class="tablenav bottom"><div class="tablenav-pages no-pages"><span class="displaying-num">0 items</span></div><br class="clear" /></div>', $this->get_tablenav( 'bottom' ), 'The bottom navigation should be output.' );
		$this->assertSame( 0, $action->get_call_count(), 'The table header action should not fire.' );
	}

	/**
	 * Returns an Add Plugins list table.
	 *
	 * @return WP_Plugin_Install_List_Table The list table.
	 */
	private function get_table() {
		return _get_list_table( 'WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install' ) );
	}

	/**
	 * Returns the table navigation markup, without the whitespace between tags.
	 *
	 * @param string $which The navigation position.
	 * @return string The markup.
	 */
	private function get_tablenav( $which ) {
		$table  = $this->get_table();
		$method = new ReflectionMethod( $table, 'display_tablenav' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$output = get_echo( array( $method, 'invoke' ), array( $table, $which ) );

		return preg_replace( '/>\s+</', '><', trim( $output ) );
	}
}
