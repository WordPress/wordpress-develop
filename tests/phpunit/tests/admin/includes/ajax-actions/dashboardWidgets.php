<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_dashboard_widgets() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_dashboard_widgets
 */
class Tests_Admin_Includes_AjaxActions_dashboardWidgets extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Returns the transient key wp_dashboard_cached_rss_widget() uses for the primary widget.
	 *
	 * @return string
	 */
	private function get_primary_cache_key(): string {
		return 'dash_v2_' . md5( 'dashboard_primary_' . get_user_locale() );
	}

	/**
	 * Mocks the WordPress News and Planet feed requests.
	 *
	 * @param false|array|WP_Error $response Preemptive response.
	 * @param array                $args     Request arguments.
	 * @param string               $url      Request URL.
	 * @return array Mocked response.
	 */
	public function mock_feed_response( $response, $args, $url ): array {
		$title = str_contains( $url, 'planet.wordpress.org' ) ? 'Planet item' : 'News item';

		return array(
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(
				array( 'Content-Type' => 'application/rss+xml; charset=UTF-8' )
			),
			'body'     => '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Mock Feed</title><link>https://example.org/</link><description>Mock</description>'
				. "<item><title>{$title}</title><link>https://example.org/item</link><description>Mock description</description></item></channel></rss>",
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_dashboard_primary_renders_and_caches_feeds(): void {
		add_filter( 'pre_http_request', array( $this, 'mock_feed_response' ), 10, 3 );
		$_GET = array(
			'pagenow' => 'dashboard',
			'widget'  => 'dashboard_primary',
		);

		$response = $this->get_ajax_response( 'dashboard-widgets' );

		$this->assertSame( 2, substr_count( $response, '<div class="rss-widget">' ) );
		$this->assertStringContainsString( 'News item', $response );
		$this->assertStringContainsString( 'Planet item', $response );
		$this->assertSame( $response, get_transient( $this->get_primary_cache_key() ) );
	}

	/**
	 * A cached widget is output as-is without fetching the feeds, which would fail the test.
	 *
	 * @ticket 65252
	 */
	public function test_dashboard_primary_outputs_cached_widget(): void {
		set_transient( $this->get_primary_cache_key(), '<p>Cached widget</p>' );
		$_GET = array(
			'pagenow' => 'dashboard',
			'widget'  => 'dashboard_primary',
		);

		$this->assertSame( '<p>Cached widget</p>', $this->get_ajax_response( 'dashboard-widgets' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_dashboard_widgets_outputs_nothing_for_unknown_widget(): void {
		$_GET = array(
			'pagenow' => 'dashboard',
			'widget'  => 'unknown_widget',
		);

		$this->assertSame( '', $this->get_ajax_response( 'dashboard-widgets' ) );
	}

	/**
	 * @ticket 65252
	 *
	 * @dataProvider data_dashboard_screens
	 *
	 * @param string $pagenow Dashboard screen ID.
	 */
	public function test_dashboard_widgets_sets_dashboard_screen( string $pagenow ): void {
		$_GET = array(
			'pagenow' => $pagenow,
			'widget'  => 'unknown_widget',
		);

		$this->get_ajax_response( 'dashboard-widgets' );

		$this->assertSame( $pagenow, get_current_screen()->id );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public static function data_dashboard_screens(): array {
		return array(
			'dashboard'         => array( 'dashboard' ),
			'dashboard-user'    => array( 'dashboard-user' ),
			'dashboard-network' => array( 'dashboard-network' ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_dashboard_widgets_ignores_other_screens(): void {
		$_GET = array(
			'pagenow' => 'edit-post',
			'widget'  => 'unknown_widget',
		);

		$this->get_ajax_response( 'dashboard-widgets' );

		$this->assertSame( 'ajax', get_current_screen()->id );
	}
}
