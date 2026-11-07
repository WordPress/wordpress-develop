<?php
/**
 * Tests for the server-side rendering of the Calendar block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderCalendar extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/calendar',
			'attrs'     => array(),
		);

		self::factory()->post->create( array( 'post_date' => '2020-03-15 10:00:00' ) );

		$this->set_published_posts( true );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Sets whether the site has published posts, for the render function.
	 *
	 * @param bool $has_posts Whether the site has published posts.
	 */
	private function set_published_posts( $has_posts ) {
		if ( is_multisite() ) {
			update_option( 'post_count', $has_posts ? 1 : 0 );
		} else {
			update_option( 'wp_calendar_block_has_published_posts', $has_posts );
		}
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $query ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests that nothing is rendered without published posts for a visitor.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_render_nothing_without_published_posts_for_a_visitor() {
		$this->set_published_posts( false );

		wp_set_current_user( 0 );

		$this->assertSame( '', render_block_core_calendar( array() ) );
	}

	/**
	 * Tests the notice without published posts for a user who is logged in.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_render_a_notice_without_published_posts_for_a_logged_in_user() {
		$this->set_published_posts( false );

		wp_set_current_user( self::factory()->user->create() );

		$this->assertSame( '<div>The calendar block is hidden because there are no published posts.</div>', render_block_core_calendar( array() ) );
	}

	/**
	 * Tests the calendar.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_render_the_calendar_in_the_wrapper() {
		$markup = render_block_core_calendar( array() );

		$this->assertStringStartsWith( '<div class="wp-block-calendar"><table id="wp-calendar" class="wp-calendar-table">', $markup );
		$this->assertStringEndsWith( '</div>', $markup );
		$this->assertSame( 1, substr_count( $markup, '<table' ) );
		$this->assertStringContainsString( '<caption>' . date_i18n( 'F Y' ) . '</caption>', $markup );
	}

	/**
	 * Tests the preset colors.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_apply_the_preset_colors() {
		$table = $this->get_element(
			render_block_core_calendar(
				array(
					'textColor'       => 'vivid-red',
					'backgroundColor' => 'pale-pink',
				)
			),
			'table'
		);

		$classes = explode( ' ', $table['class'] );

		$this->assertContains( 'wp-calendar-table', $classes );
		$this->assertContains( 'has-text-color', $classes );
		$this->assertContains( 'has-vivid-red-color', $classes );
		$this->assertContains( 'has-background', $classes );
		$this->assertContains( 'has-pale-pink-background-color', $classes );
		$this->assertArrayNotHasKey( 'style', $table );
	}

	/**
	 * Tests the custom colors.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_apply_the_custom_colors() {
		$table = $this->get_element(
			render_block_core_calendar(
				array(
					'style' => array(
						'color' => array(
							'text'       => '#112233',
							'background' => '#445566',
						),
					),
				)
			),
			'table'
		);

		$this->assertStringContainsString( 'color:#112233', $table['style'] );
		$this->assertStringContainsString( 'background-color:#445566', $table['style'] );
		$this->assertContains( 'has-text-color', explode( ' ', $table['class'] ) );
		$this->assertContains( 'has-background', explode( ' ', $table['class'] ) );
	}

	/**
	 * Tests that a preset color wins over a custom color.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_prefer_the_preset_colors_to_the_custom_colors() {
		$table = $this->get_element(
			render_block_core_calendar(
				array(
					'textColor'       => 'vivid-red',
					'backgroundColor' => 'pale-pink',
					'style'           => array(
						'color' => array(
							'text'       => '#112233',
							'background' => '#445566',
						),
					),
				)
			),
			'table'
		);

		$this->assertContains( 'has-vivid-red-color', explode( ' ', $table['class'] ) );
		$this->assertContains( 'has-pale-pink-background-color', explode( ' ', $table['class'] ) );
		$this->assertArrayNotHasKey( 'style', $table );
	}

	/**
	 * Tests that the table has no style without colors.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_not_add_a_style_or_a_class_without_colors() {
		$table = $this->get_element( render_block_core_calendar( array() ), 'table' );

		$this->assertSame( 'wp-calendar-table', $table['class'] );
		$this->assertArrayNotHasKey( 'style', $table );
	}

	/**
	 * Tests the class of the link color.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_add_the_class_of_the_link_color() {
		$attributes = array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#778899' ) ) ) ) );

		$this->assertContains( 'has-link-color', explode( ' ', $this->get_element( render_block_core_calendar( $attributes ), 'table' )['class'] ) );
		$this->assertNotContains( 'has-link-color', explode( ' ', $this->get_element( render_block_core_calendar( array() ), 'table' )['class'] ) );
	}

	/**
	 * Tests the month and the year of the attributes, with a permalink structure that has them.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_show_the_month_and_the_year_of_the_attributes() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$markup = render_block_core_calendar(
			array(
				'month' => 3,
				'year'  => 2020,
			)
		);

		$this->assertStringContainsString( '<caption>March 2020</caption>', $markup );
	}

	/**
	 * Tests that the month and the year of the attributes are not used without them in the permalink structure.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 *
	 * @dataProvider data_permalink_structures
	 *
	 * @param string $structure The permalink structure.
	 */
	public function test_should_ignore_the_month_and_the_year_of_the_attributes_without_them_in_the_permalinks( $structure ) {
		$this->set_permalink_structure( $structure );

		$markup = render_block_core_calendar(
			array(
				'month' => 3,
				'year'  => 2020,
			)
		);

		$this->assertStringNotContainsString( 'March 2020', $markup );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_permalink_structures() {
		return array(
			'plain'      => array( '' ),
			'post name'  => array( '/%postname%/' ),
			'year only'  => array( '/%year%/%postname%/' ),
			'month only' => array( '/%monthnum%/%postname%/' ),
		);
	}

	/**
	 * Tests that the attributes need both the month and the year.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 *
	 * @dataProvider data_incomplete_dates
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_need_both_the_month_and_the_year( $attributes ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$this->assertStringContainsString( '<caption>' . date_i18n( 'F Y' ) . '</caption>', render_block_core_calendar( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_dates() {
		return array(
			'month only' => array( array( 'month' => 3 ) ),
			'year only'  => array( array( 'year' => 2020 ) ),
			'none'       => array( array() ),
		);
	}

	/**
	 * Tests that the month and the year of the globals are restored.
	 *
	 * @ticket 66598
	 *
	 * @covers ::render_block_core_calendar
	 */
	public function test_should_restore_the_month_and_the_year() {
		global $monthnum, $year;

		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$monthnum = '11';
		$year     = '2021';

		render_block_core_calendar(
			array(
				'month' => 3,
				'year'  => 2020,
			)
		);

		$this->assertSame( '11', $monthnum );
		$this->assertSame( '2021', $year );
	}

	/**
	 * Tests the flag of the published posts on a multisite.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_has_published_posts
	 *
	 * @group ms-required
	 */
	public function test_has_published_posts_uses_the_post_count_on_multisite() {
		update_option( 'post_count', 0 );

		$this->assertFalse( block_core_calendar_has_published_posts() );

		update_option( 'post_count', '3' );

		$this->assertTrue( block_core_calendar_has_published_posts() );
	}

	/**
	 * Tests the flag of the published posts from the option on a single site.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_has_published_posts
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_cached_flags
	 *
	 * @param mixed $value    The value of the option.
	 * @param bool  $expected The expected result.
	 */
	public function test_has_published_posts_uses_the_option_on_a_single_site( $value, $expected ) {
		update_option( 'wp_calendar_block_has_published_posts', $value );

		$this->assertSame( $expected, block_core_calendar_has_published_posts() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_cached_flags() {
		return array(
			'true'         => array( true, true ),
			'false'        => array( false, false ),
			'one'          => array( '1', true ),
			'empty string' => array( '', false ),
		);
	}

	/**
	 * Tests that the flag is queried and saved when it is missing on a single site.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_has_published_posts
	 * @covers ::block_core_calendar_update_has_published_posts
	 *
	 * @group ms-excluded
	 */
	public function test_has_published_posts_queries_and_saves_the_flag_when_it_is_missing() {
		self::factory()->post->create();
		delete_option( 'wp_calendar_block_has_published_posts' );

		$this->assertTrue( block_core_calendar_has_published_posts() );
		$this->assertTrue( (bool) get_option( 'wp_calendar_block_has_published_posts' ) );
	}

	/**
	 * Tests the update of the flag.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_update_has_published_posts
	 *
	 * @group ms-excluded
	 */
	public function test_update_has_published_posts() {
		foreach ( get_posts(
			array(
				'numberposts' => -1,
				'post_status' => 'any',
			)
		) as $post ) {
			wp_delete_post( $post->ID, true );
		}

		$this->assertFalse( block_core_calendar_update_has_published_posts(), 'The flag should be false without posts.' );
		$this->assertFalse( (bool) get_option( 'wp_calendar_block_has_published_posts' ) );

		self::factory()->post->create( array( 'post_status' => 'draft' ) );
		self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertFalse( block_core_calendar_update_has_published_posts(), 'Drafts and pages should not count.' );

		self::factory()->post->create();

		$this->assertTrue( block_core_calendar_update_has_published_posts(), 'A published post should count.' );
		$this->assertTrue( (bool) get_option( 'wp_calendar_block_has_published_posts' ) );
	}
}
