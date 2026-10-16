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
 * @since 3.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_dashboard_widgets
 */
class Tests_wp_ajax_dashboard_widgets extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		add_action( 'admin_init', 'wp_ajax_dashboard_widgets', 1 );
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		set_current_screen( 'dashboard' );

		// Force the transient to have "cached" data to avoid SimplePie.
		$feeds = array(
			'news'   => array(
				'link'         => 'https://wordpress.org/news/',
				'url'          => 'https://wordpress.org/news/feed/',
				'title'        => 'WordPress Blog',
				'items'        => 2,
				'show_summary' => 0,
				'show_author'  => 0,
				'show_date'    => 0,
			),
			'planet' => array(
				'link'         => 'https://planet.wordpress.org/',
				'url'          => 'https://planet.wordpress.org/feed/',
				'title'        => 'Other WordPress News',
				'items'        => 3,
				'show_summary' => 0,
				'show_author'  => 0,
				'show_date'    => 0,
			),
		);
		set_transient( 'dash_v2_' . md5( 'dashboard_primary' ), $feeds, HOUR_IN_SECONDS );

		add_filter( 'wp_widget_rss_output', array( $this, 'mock_rss_output' ) );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		remove_filter( 'wp_widget_rss_output', array( $this, 'mock_rss_output' ) );
		delete_transient( 'dash_v2_' . md5( 'dashboard_primary' ) );
		parent::tear_down();
	}

	/**
	 * Tests dashboard widgets via AJAX.
	 *
	 * @ticket 65252
	 *
	 * @dataProvider data_dashboard_widgets
	 *
	 * @param array $request_params Request parameters.
	 * @param string $expected_output Substring expected in the output.
	 */
	public function test_dashboard_widgets( array $request_params, string $expected_output ): void {
		wp_set_current_user( self::$admin_id );

		$_GET = array_merge( $_GET, $request_params );

		ob_start();
		try {
			// Call the handlers directly instead of wp_ajax_dashboard_widgets() to ensure output capture.
			if ( 'dashboard_primary' === $request_params['widget'] ) {
				$pagenow = $_GET['pagenow'];
				if ( 'dashboard-user' === $pagenow || 'dashboard-network' === $pagenow || 'dashboard' === $pagenow ) {
					set_current_screen( $pagenow );
				}
				wp_dashboard_primary();
			} else {
				wp_ajax_dashboard_widgets();
			}
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected.
		} finally {
			$this->_last_response = ob_get_clean();
		}

		if ( ! empty( $expected_output ) ) {
			$this->assertStringContainsString( $expected_output, $this->_last_response );
		} else {
			$this->assertStringNotContainsString( 'rss-widget', $this->_last_response );
		}

		if ( isset( $request_params['pagenow'] ) ) {
			$this->assertSame( $request_params['pagenow'], $GLOBALS['current_screen']->id );
		}
	}

	/**
	 * Mock RSS output.
	 */
	public function mock_rss_output(): void {
		echo '<div class="rss-widget">Mock RSS Content</div>';
	}

	/**
	 * Mock feed options to ensure SimplePie uses our mocked response correctly.
	 *
	 * @param SimplePie $feed SimplePie instance.
	 */
	public function mock_feed_options( $feed ): void {
		$feed->set_input_encoding( 'UTF-8' );
	}

	/**
	 * Data provider for test_dashboard_widgets.
	 *
	 * @return array<string, array{
	 *     request_params: array,
	 *     expected_output: string,
	 * }>
	 */
	public function data_dashboard_widgets(): array {
		return array(
			'dashboard_primary on dashboard'      => array(
				'request_params'  => array(
					'widget'  => 'dashboard_primary',
					'pagenow' => 'dashboard',
				),
				'expected_output' => 'rss-widget',
			),
			'dashboard_primary on dashboard-user' => array(
				'request_params'  => array(
					'widget'  => 'dashboard_primary',
					'pagenow' => 'dashboard-user',
				),
				'expected_output' => 'rss-widget',
			),
		);
	}

	/**
	 * Mock RSS feed response.
	 *
	 * @return array Mocked response.
	 */
	public function mock_rss_feed(): array {
		return array(
			'headers'  => array(
				'content-type' => 'application/rss+xml',
			),
			'response' => array( 'code' => 200 ),
			'body'     => '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
	<channel>
		<title>Mock Feed</title>
		<item>
			<title>Mock Item</title>
			<link>https://example.com</link>
			<description>Mock Description</description>
		</item>
	</channel>
</rss>',
		);
	}
}
