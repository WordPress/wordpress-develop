<?php
/**
 * Test cases for wp_list_bookmarks().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 2.1.0
 *
 * @group bookmark
 * @group template
 *
 * @covers ::wp_list_bookmarks
 */
class Tests_wp_list_bookmarks extends WP_UnitTestCase {

	/**
	 * Categorized bookmarks listing test.
	 *
	 * @ticket 65957
	 *
	 * @dataProvider data_wp_list_bookmarks_categorized
	 *
	 * @param array $args     Arguments for wp_list_bookmarks.
	 * @param array $expected Substrings expected in the output.
	 */
	public function test_wp_list_bookmarks_categorized( array $args, array $expected ): void {
		$cat_id = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Alpha Category',
			)
		);

		self::factory()->bookmark->create(
			array(
				'link_name'     => 'Alpha Site',
				'link_url'      => 'https://alpha.example.org',
				'link_category' => array( $cat_id ),
			)
		);

		$args['echo'] = 0;
		$output       = wp_list_bookmarks( $args );

		foreach ( $expected as $needle ) {
			$needle = str_replace( '%cat_id%', (string) $cat_id, $needle );
			$this->assertStringContainsString( $needle, $output );
		}
	}

	/**
	 * Data provider for test_wp_list_bookmarks_categorized.
	 *
	 * @return array<string, array{
	 *     args: array<string, mixed>,
	 *     expected: string[],
	 * }>
	 */
	public function data_wp_list_bookmarks_categorized(): array {
		return array(
			'default categorized output'         => array(
				'args'     => array(
					'categorize' => 1,
				),
				'expected' => array(
					'<li id="linkcat-%cat_id%" class="linkcat">',
					'<h2>Alpha Category</h2>',
					"<ul class='xoxo blogroll'>",
					'<li><a href="https://alpha.example.org"',
					'Alpha Site</a></li>',
					'</ul>',
					'</li>',
				),
			),
			'custom category class and wrappers' => array(
				'args'     => array(
					'categorize'      => 1,
					'class'           => 'custom-bookmarks-class',
					'title_before'    => '<h3>',
					'title_after'     => '</h3>',
					'category_before' => '<div id="%id" class="%class">',
					'category_after'  => '</div>',
				),
				'expected' => array(
					'<div id="linkcat-%cat_id%" class="custom-bookmarks-class">',
					'<h3>Alpha Category</h3>',
					"<ul class='xoxo blogroll'>",
					'</div>',
				),
			),
			'array of classes'                   => array(
				'args'     => array(
					'categorize' => 1,
					'class'      => array( 'class-one', 'class-two' ),
				),
				'expected' => array(
					'class="class-one class-two"',
				),
			),
		);
	}

	/**
	 * Tests uncategorized bookmarks listing.
	 *
	 * @ticket 65957
	 *
	 * @dataProvider data_wp_list_bookmarks_uncategorized
	 *
	 * @param array $args         Arguments for wp_list_bookmarks.
	 * @param array $expected     Substrings expected in the output.
	 * @param array $not_expected Substrings not expected in the output.
	 */
	public function test_wp_list_bookmarks_uncategorized( array $args, array $expected, array $not_expected ): void {
		$cat_id = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Uncategorized Group',
			)
		);

		self::factory()->bookmark->create(
			array(
				'link_name'     => 'Single Site',
				'link_url'      => 'https://single.example.org',
				'link_category' => array( $cat_id ),
			)
		);

		$args['echo'] = 0;
		$output       = wp_list_bookmarks( $args );

		foreach ( $expected as $needle ) {
			$this->assertStringContainsString( $needle, $output );
		}

		foreach ( $not_expected as $needle ) {
			$this->assertStringNotContainsString( $needle, $output );
		}
	}

	/**
	 * Data provider for test_wp_list_bookmarks_uncategorized.
	 *
	 * @return array<string, array{
	 *     args: array<string, mixed>,
	 *     expected: string[],
	 *     not_expected: string[],
	 * }>
	 */
	public function data_wp_list_bookmarks_uncategorized(): array {
		return array(
			'uncategorized with default title_li' => array(
				'args'         => array(
					'categorize' => 0,
					'title_li'   => 'Custom Bookmarks Title',
				),
				'expected'     => array(
					'<h2>Custom Bookmarks Title</h2>',
					"<ul class='xoxo blogroll'>",
					'<li><a href="https://single.example.org"',
					'Single Site</a></li>',
				),
				'not_expected' => array(
					'Uncategorized Group',
				),
			),
			'uncategorized with empty title_li'   => array(
				'args'         => array(
					'categorize' => 0,
					'title_li'   => '',
				),
				'expected'     => array(
					'<li><a href="https://single.example.org"',
					'Single Site</a></li>',
				),
				'not_expected' => array(
					'<h2>',
					'</h2>',
					"<ul class='xoxo blogroll'>",
				),
			),
		);
	}

	/**
	 * Tests echo parameter behavior.
	 *
	 * @ticket 65957
	 */
	public function test_wp_list_bookmarks_echo(): void {
		$cat_id = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Echo Category',
			)
		);

		self::factory()->bookmark->create(
			array(
				'link_name'     => 'Echo Site',
				'link_url'      => 'https://echo.example.org',
				'link_category' => array( $cat_id ),
			)
		);

		ob_start();
		$echo_result = wp_list_bookmarks(
			array(
				'echo'     => 1,
				'category' => $cat_id,
			)
		);
		$echoed      = ob_get_clean();

		$return_result = wp_list_bookmarks(
			array(
				'echo'     => 0,
				'category' => $cat_id,
			)
		);

		$this->assertNull( $echo_result );
		$this->assertSame( $echoed, $return_result );
		$this->assertStringContainsString( 'Echo Site', $echoed );
	}

	/**
	 * Tests filtering bookmarks by category include, exclude, and category_name.
	 *
	 * @ticket 65957
	 */
	public function test_wp_list_bookmarks_category_filtering(): void {
		$cat_a = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Alpha Group',
			)
		);
		$cat_b = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Beta Group',
			)
		);

		self::factory()->bookmark->create(
			array(
				'link_name'     => 'Alpha Site',
				'link_url'      => 'https://alpha.example.org',
				'link_category' => array( $cat_a ),
			)
		);

		self::factory()->bookmark->create(
			array(
				'link_name'     => 'Beta Site',
				'link_url'      => 'https://beta.example.org',
				'link_category' => array( $cat_b ),
			)
		);

		// Test category inclusion.
		$included_output = wp_list_bookmarks(
			array(
				'echo'     => 0,
				'category' => $cat_a,
			)
		);
		$this->assertStringContainsString( 'Alpha Site', $included_output );
		$this->assertStringNotContainsString( 'Beta Site', $included_output );

		// Test category exclusion.
		$excluded_output = wp_list_bookmarks(
			array(
				'echo'             => 0,
				'exclude_category' => $cat_a,
			)
		);
		$this->assertStringNotContainsString( 'Alpha Site', $excluded_output );
		$this->assertStringContainsString( 'Beta Site', $excluded_output );

		// Test category_name filter.
		$name_filter_output = wp_list_bookmarks(
			array(
				'echo'          => 0,
				'category_name' => 'Beta Group',
			)
		);
		$this->assertStringNotContainsString( 'Alpha Site', $name_filter_output );
		$this->assertStringContainsString( 'Beta Site', $name_filter_output );
	}

	/**
	 * Tests link_category and wp_list_bookmarks filter hooks.
	 *
	 * @ticket 65957
	 */
	public function test_wp_list_bookmarks_filters(): void {
		$cat_id = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Original Category Name',
			)
		);

		self::factory()->bookmark->create(
			array(
				'link_name'     => 'Filtered Site',
				'link_url'      => 'https://filtered.example.org',
				'link_category' => array( $cat_id ),
			)
		);

		$filter_cat_name = static function ( $name ) {
			return 'Modified Category: ' . $name;
		};

		$filter_html = static function ( $html ) {
			return '<!-- Start -->' . $html . '<!-- End -->';
		};

		add_filter( 'link_category', $filter_cat_name );
		add_filter( 'wp_list_bookmarks', $filter_html );

		$output = wp_list_bookmarks(
			array(
				'echo'     => 0,
				'category' => $cat_id,
			)
		);

		remove_filter( 'link_category', $filter_cat_name );
		remove_filter( 'wp_list_bookmarks', $filter_html );

		$this->assertStringContainsString( 'Modified Category: Original Category Name', $output );
		$this->assertStringStartsWith( '<!-- Start -->', $output );
		$this->assertStringEndsWith( '<!-- End -->', $output );
	}

	/**
	 * Tests hide_invisible argument.
	 *
	 * @ticket 65957
	 */
	public function test_wp_list_bookmarks_hide_invisible(): void {
		$cat_id = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Visibility Category',
			)
		);

		self::factory()->bookmark->create(
			array(
				'link_name'     => 'Invisible Link',
				'link_url'      => 'https://invisible.example.org',
				'link_visible'  => 'N',
				'link_category' => array( $cat_id ),
			)
		);

		$hidden_output = wp_list_bookmarks(
			array(
				'echo'           => 0,
				'hide_invisible' => 1,
				'category'       => $cat_id,
			)
		);
		$this->assertStringNotContainsString( 'Invisible Link', $hidden_output );

		$visible_output = wp_list_bookmarks(
			array(
				'echo'           => 0,
				'hide_invisible' => 0,
				'category'       => $cat_id,
			)
		);
		$this->assertStringContainsString( 'Invisible Link', $visible_output );
	}

	/**
	 * Tests when no bookmarks are found.
	 *
	 * @ticket 65957
	 */
	public function test_wp_list_bookmarks_empty(): void {
		$output = wp_list_bookmarks(
			array(
				'echo'     => 0,
				'category' => 999999,
			)
		);

		$this->assertSame( '', $output );
	}
}
