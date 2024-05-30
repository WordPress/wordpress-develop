<?php

require_once __DIR__ . '/Admin_WpListTable_TestCase.php';

/**
 * @group admin
 *
 * @covers WP_List_Table::get_views_links
 */
class Admin_WpListTable_GetViewsLinks_Test extends Admin_WpListTable_TestCase {

	/**
	 * Tests the "get_views_links()" method.
	 *
	 * @ticket 42066
	 *
	 * @dataProvider data_get_views_links
	 *
	 * @param array $link_data {
	 *     An array of link data.
	 *
	 *     @type string $url     The link URL.
	 *     @type string $label   The link label.
	 *     @type bool   $current Optional. Whether this is the currently selected view.
	 * }
	 * @param array $expected
	 */
	public function test_get_views_links( $link_data, $expected ) {
		$get_views_links = new ReflectionMethod( $this->list_table, 'get_views_links' );
		$get_views_links->setAccessible( true );

		$actual = $get_views_links->invokeArgs( $this->list_table, array( $link_data ) );

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_views_links() {
		return array(
			'one "current" link'                           => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'     => add_query_arg( 'status', 'activated', 'https://example.org/' ),
						'label'   => 'Activated',
						'current' => false,
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated">Activated</a>',
				),
			),
			'two "current" links'                          => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'     => add_query_arg( 'status', 'activated', 'https://example.org/' ),
						'label'   => 'Activated',
						'current' => true,
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated" class="current" aria-current="page">Activated</a>',
				),
			),
			'one "current" link and one without "current" key' => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'   => add_query_arg( 'status', 'activated', 'https://example.org/' ),
						'label' => 'Activated',
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated">Activated</a>',
				),
			),
			'one "current" link with escapable characters' => array(
				'link_data' => array(
					'all'       => array(
						'url'     => 'https://example.org/',
						'label'   => 'All',
						'current' => true,
					),
					'activated' => array(
						'url'     => add_query_arg(
							array(
								'status' => 'activated',
								'sort'   => 'desc',
							),
							'https://example.org/'
						),
						'label'   => 'Activated',
						'current' => false,
					),
				),
				'expected'  => array(
					'all'       => '<a href="https://example.org/" class="current" aria-current="page">All</a>',
					'activated' => '<a href="https://example.org/?status=activated&#038;sort=desc">Activated</a>',
				),
			),
		);
	}

	/**
	 * Tests that "get_views_links()" throws a _doing_it_wrong().
	 *
	 * @ticket 42066
	 *
	 * @expectedIncorrectUsage WP_List_Table::get_views_links
	 *
	 * @dataProvider data_get_views_links_doing_it_wrong
	 *
	 * @param array $link_data {
	 *     An array of link data.
	 *
	 *     @type string $url     The link URL.
	 *     @type string $label   The link label.
	 *     @type bool   $current Optional. Whether this is the currently selected view.
	 * }
	 */
	public function test_get_views_links_doing_it_wrong( $link_data ) {
		$get_views_links = new ReflectionMethod( $this->list_table, 'get_views_links' );
		$get_views_links->setAccessible( true );
		$get_views_links->invokeArgs( $this->list_table, array( $link_data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_views_links_doing_it_wrong() {
		return array(
			'non-array $link_data'               => array(
				'link_data' => 'https://example.org, All, class="current" aria-current="page"',
			),
			'a link with no URL'                 => array(
				'link_data' => array(
					'all' => array(
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with an empty URL'           => array(
				'link_data' => array(
					'all' => array(
						'url'     => '',
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with a URL of only spaces'   => array(
				'link_data' => array(
					'all' => array(
						'url'     => '  ',
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with a non-string URL'       => array(
				'link_data' => array(
					'all' => array(
						'url'     => array(),
						'label'   => 'All',
						'current' => true,
					),
				),
			),
			'a link with no label'               => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'current' => true,
					),
				),
			),
			'a link with an empty label'         => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'label'   => '',
						'current' => true,
					),
				),
			),
			'a link with a label of only spaces' => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'label'   => '  ',
						'current' => true,
					),
				),
			),
			'a link with a non-string label'     => array(
				'link_data' => array(
					'all' => array(
						'url'     => 'https://example.org/',
						'label'   => array(),
						'current' => true,
					),
				),
			),
		);
	}
}
