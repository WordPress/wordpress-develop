<?php

/**
 * @group kses
 *
 * @covers ::wp_filter_global_styles_post
 */
class Tests_Kses_WpFilterGlobalStylesPost extends WP_UnitTestCase {

	/**
	 * Theme data.
	 *
	 * @var array
	 */
	private $user_theme_data = array(
		'isGlobalStylesUserThemeJSON' => 1,
		'version'                     => 1,
		'nonSchemaRule'               => 'someValue',
		'styles'                      => array(
			'blocks' => array(
				'core/button' => array(
					'border' => array(
						'radius' => '0',
					),
				),
			),
		),
	);

	/**
	 * @ticket 56266
	 */
	public function test_should_not_remove_safe_global_style_rules() {
		$filtered_user_theme_json = $this->filter_global_styles( $this->user_theme_data );
		$this->assertArrayHasKey( 'styles', $filtered_user_theme_json, 'Filtered json data must contain safe global style rules.' );
	}

	/**
	 * @ticket 56266
	 */
	public function test_should_remove_unsafe_global_style_rules() {
		$filtered_user_theme_json = $this->filter_global_styles( $this->user_theme_data );
		$this->assertArrayNotHasKey( 'nonSchemaRule', $filtered_user_theme_json, 'Filtered json data must not contain unsafe global style rules.' );
	}

	/**
	 * This is a helper method.
	 * It filters JSON theme data and returns it as an array.
	 *
	 * @param  array $theme_data Theme data to filter.
	 *
	 * @return array             Filtered theme data.
	 */
	private function filter_global_styles( $theme_data ) {
		$user_theme_json          = wp_slash( wp_json_encode( $theme_data ) );
		$filtered_user_theme_json = wp_filter_global_styles_post( $user_theme_json );
		return json_decode( wp_unslash( $filtered_user_theme_json ), true );
	}
}
