<?php

require_once __DIR__ . '/base.php';

/**
 * Test functions in global-styles-and-settings.php.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @group themes
 *
 * @covers ::wp_get_viewport_media_queries
 */
class Tests_Theme_WpGetGlobalStylesAndSettings extends WP_Theme_UnitTestCase {

	/**
	 * @ticket 82082
	 */
	public function test_wp_get_viewport_media_queries_returns_media_queries() {
		$this->assertSame(
			array(
				'@mobile'  => '@media (width <= 640px)',
				'@tablet'  => '@media (640px < width <= 960px)',
				'@desktop' => '@media (width > 960px)',
			),
			wp_get_viewport_media_queries(
				array(
					'mobile' => '640px',
					'tablet' => '960px',
				),
				array(
					'include_desktop' => true,
				)
			)
		);
	}
}
