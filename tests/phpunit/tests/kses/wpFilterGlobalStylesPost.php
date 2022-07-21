<?php
/**
 * @group kses
 *
 * @covers ::wp_filter_global_styles_post
 */
class Tests_Kses_WpFilterGlobalStylesPost extends WP_UnitTestCase {

	/**
	 * @ticket 56266
	 */
	public function test_wp_filter_global_styles_post_returns_correct_value() {
		$user_theme_json = '{
 			"isGlobalStylesUserThemeJSON": 1,
 			"version": 1,
 			"settings": {
 				"typography": {
 					"fontFamilies": {
 						"fontFamily": "\"DM Sans\", sans-serif",
 						"slug": "dm-sans",
 						"name": "DM Sans",
 					}
 				}
 			}
 		}';

		$filtered_user_theme_json = wp_filter_global_styles_post( $user_theme_json );
		$this->assertSame( $user_theme_json, $filtered_user_theme_json, 'Filtered and expected json data must match.' );
	}
}
