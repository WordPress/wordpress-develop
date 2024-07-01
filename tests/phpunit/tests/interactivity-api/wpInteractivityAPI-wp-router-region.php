<?php
/**
 * Unit tests covering the `data_wp_router_region` processor functionality of
 * the WP_Interactivity_API class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @coversDefaultClass WP_Interactivity_API
 *
 * @group interactivity-api
 */
class Tests_WP_Interactivity_API_WP_Router_Region extends WP_UnitTestCase {
	/**
	 * Instance of WP_Interactivity_API.
	 *
	 * @var WP_Interactivity_API
	 */
	protected $interactivity;

	/**
	 * Original WP_Hook instance associated to `wp_footer`.
	 *
	 * @var WP_Hook
	 */
	protected $original_wp_footer;

	/**
	 * Original instance associated to `wp_footer`.
	 *
	 * @var WP_Styles
	 */
	protected $original_wp_styles;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactivity = new WP_Interactivity_API();

		// Removes all hooks set for `wp_footer`.
		global $wp_filter;
		$this->original_wp_footer = $wp_filter['wp_footer'];
		$wp_filter['wp_footer']   = new WP_Hook();

		// Removes all registered styles.
		$this->original_wp_styles = isset( $GLOBALS['wp_styles'] ) ? $GLOBALS['wp_styles'] : null;
		$GLOBALS['wp_styles']     = new WP_Styles();
		remove_action( 'wp_default_styles', 'wp_default_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		// Restores all previous hooks set for `wp_footer`.
		global $wp_filter;
		$wp_filter['wp_footer'] = $this->original_wp_footer;

		// Restores all previous registered styles.
		$GLOBALS['wp_styles'] = $this->original_wp_styles;
		add_action( 'wp_default_styles', 'wp_default_styles' );
		add_action( 'wp_print_styles', 'print_emoji_styles' );

		parent::tear_down();
	}

	/**
	 * Executes the hooks associated to `wp_footer`.
	 */
	protected function render_wp_footer() {
		ob_start();
		do_action( 'wp_footer' );
		return ob_get_clean();
	}

	/**
	 * Tests that no elements are added if the `data-wp-router-region` is
	 * missing.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_router_region_missing() {
		$html     = '<div>Nothing here</div>';
		$new_html = $this->interactivity->process_directives( $html );
		$footer   = $this->render_wp_footer();
		$this->assertSame( $html, $new_html );
		$this->assertSame( '', $footer );
		$this->assertSame( '', get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Tests that the `data-wp-router-region` directive adds a loading bar and a
	 * region for screen reader announcements in the footer, and styles for the
	 * loading bar. Also checks that the markup and styles are only added once.
	 *
	 * @ticket 60356
	 *
	 * @covers ::process_directives
	 */
	public function test_wp_router_region_adds_loading_bar_aria_live_region_only_once() {
		$html     = '
			<div data-wp-router-region="region A">Interactive region</div>
			<div data-wp-router-region="region B">Another interactive region</div>
		';
		$new_html = $this->interactivity->process_directives( $html );
		$this->assertSame( $html, $new_html );

		// Check that the style is loaded, but only once.
		$styles = get_echo( 'wp_print_styles' );
		$query  = array( 'tag_name' => 'style' );
		$p      = new WP_HTML_Tag_Processor( $styles );
		$this->assertTrue( $p->next_tag( $query ) );
		$this->assertSame( 'wp-interactivity-router-animations-inline-css', $p->get_attribute( 'id' ) );
		$this->assertStringContainsString( '.wp-interactivity-router-loading-bar', $styles );
		$this->assertFalse( $p->next_tag( $query ) );

		// Check that the markup is loaded, but only once.
		$footer = $this->render_wp_footer();
		$query  = array( 'class_name' => 'wp-interactivity-router-loading-bar' );
		$p      = new WP_HTML_Tag_Processor( $footer );
		$this->assertTrue( $p->next_tag( $query ) );
		$this->assertFalse( $p->next_tag( $query ) );
		$query = array( 'class_name' => 'screen-reader-text' );
		$p     = new WP_HTML_Tag_Processor( $footer );
		$this->assertTrue( $p->next_tag( $query ) );
		$this->assertFalse( $p->next_tag( $query ) );
	}
}
