<?php

/**
 * Tests for the search box and sorting of WP_Plugins_List_Table.
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_wpPluginsListTableSearchBox extends WP_UnitTestCase {

	/**
	 * The request keys the tests set.
	 *
	 * @var string[]
	 */
	private static $request_keys = array( 's', 'orderby', 'order' );

	/**
	 * The original request URI, or null when it was not set.
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	/**
	 * The original globals changed by the list-table fixture.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * Saves the request URI and the list-table globals.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
		$this->original_globals     = array(
			'orderby' => $GLOBALS['orderby'] ?? null,
			'order'   => $GLOBALS['order'] ?? null,
		);

		if ( array_key_exists( 'status', $GLOBALS ) ) {
			$this->original_globals['status'] = $GLOBALS['status'];
		}
	}

	/**
	 * Restores the request, the request URI and the list-table globals.
	 */
	public function tear_down() {
		foreach ( self::$request_keys as $key ) {
			unset( $_REQUEST[ $key ], $_GET[ $key ] );
		}

		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		unset( $GLOBALS['status'] );

		foreach ( $this->original_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		parent::tear_down();
	}

	/**
	 * Tests that the search box is hidden without a search and without plugins.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugins_List_Table::search_box
	 *
	 * @dataProvider data_empty_search_terms
	 *
	 * @param string|null $search The search term, or null for none.
	 */
	public function test_search_box_should_be_hidden_without_search_or_plugins( $search ) {
		if ( null !== $search ) {
			$_REQUEST['s'] = $search;
		}

		$this->assertSame( '', get_echo( array( $this->get_table( array() ), 'search_box' ), array( 'Search Plugins', 'plugin' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_search_terms() {
		return array(
			'no search'    => array( null ),
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
		);
	}

	/**
	 * Tests the search box when plugins are listed.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugins_List_Table::search_box
	 */
	public function test_search_box_should_be_shown_with_plugins() {
		$this->assertEqualHTML(
			'<p class="search-box">'
			. '<label for="plugin-search-input">Search Plugins</label>'
			. '<input type="search" id="plugin-search-input" class="wp-filter-search" name="s" value="" />'
			. '<input type="submit" id="search-submit" class="button hide-if-js" value="Search Plugins" />'
			. '</p>',
			$this->get_search_box( array( 'hello.php' => array( 'Name' => 'Hello Dolly' ) ) )
		);
	}

	/**
	 * Tests the search box for a search without results, with the sort order kept and escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugins_List_Table::search_box
	 */
	public function test_search_box_should_keep_search_and_sort_order() {
		$_REQUEST['s']       = 'a "b" <c>';
		$_GET['s']           = $_REQUEST['s'];
		$_REQUEST['orderby'] = 'name"><x';
		$_REQUEST['order']   = 'DESC"><x';

		$this->assertEqualHTML(
			'<input type="hidden" name="orderby" value="name&quot;&gt;&lt;x" />'
			. '<input type="hidden" name="order" value="DESC&quot;&gt;&lt;x" />'
			. '<p class="search-box">'
			. '<label for="plugin-search-input">Search Plugins</label>'
			. '<input type="search" id="plugin-search-input" class="wp-filter-search" name="s" value="a &quot;b&quot; &lt;c&gt;" />'
			. '<input type="submit" id="search-submit" class="button hide-if-js" value="Search Plugins" />'
			. '</p>',
			$this->get_search_box( array() )
		);
	}

	/**
	 * Tests that the plugins list cannot be sorted by a column.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugins_List_Table::get_sortable_columns
	 */
	public function test_get_sortable_columns_should_return_no_columns() {
		$table  = $this->get_table( array() );
		$method = new ReflectionMethod( $table, 'get_sortable_columns' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->assertSame( array(), $method->invoke( $table ) );
	}

	/**
	 * Tests that plugins are compared case-insensitively by the requested field and order.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Plugins_List_Table::_order_callback
	 *
	 * @dataProvider data_orders
	 *
	 * @param string $order    The sort order.
	 * @param string $a        The first value.
	 * @param string $b        The second value.
	 * @param int    $expected The expected sign of the comparison.
	 */
	public function test_order_callback_should_compare_by_field( $order, $a, $b, $expected ) {
		$GLOBALS['orderby'] = 'Author';
		$GLOBALS['order']   = $order;

		$result = $this->get_table( array() )->_order_callback(
			array(
				'Name'   => 'Zulu Plugin',
				'Author' => $a,
			),
			array(
				'Name'   => 'Alpha Plugin',
				'Author' => $b,
			)
		);

		$this->assertSame( $expected, $result <=> 0 );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_orders() {
		return array(
			'equal values'                 => array( 'ASC', 'Alpha', 'Alpha', 0 ),
			'ascending'                    => array( 'ASC', 'Alpha', 'beta', -1 ),
			'ascending, reversed'          => array( 'ASC', 'beta', 'Alpha', 1 ),
			'descending'                   => array( 'DESC', 'Alpha', 'beta', 1 ),
			'case-insensitive'             => array( 'ASC', 'alpha', 'ALPHA', 0 ),
			'any other order is ascending' => array( 'desc', 'Alpha', 'beta', -1 ),
		);
	}

	/**
	 * Returns the search box markup, without the whitespace between tags.
	 *
	 * @param array $items The listed plugins.
	 * @return string The markup.
	 */
	private function get_search_box( $items ) {
		$output = get_echo( array( $this->get_table( $items ), 'search_box' ), array( 'Search Plugins', 'plugin' ) );

		return preg_replace( '/>\s+</', '><', trim( $output ) );
	}

	/**
	 * Returns a Plugins list table with the given items.
	 *
	 * @param array $items The listed plugins.
	 * @return WP_Plugins_List_Table The list table.
	 */
	private function get_table( $items ) {
		$table        = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
		$table->items = $items;

		return $table;
	}
}
