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
	 * @dataProvider data_should_not_remove_safe_global_style_rules
	 * @ticket       56266
	 *
	 * @param string $rule A rule to test.
	 */
	public function test_should_not_remove_safe_global_style_rules( $rule ) {
		$theme_data               = wp_parse_args( $this->user_theme_data, array( $rule => 'someValue' ) );
		$filtered_user_theme_json = $this->filter_global_styles( $theme_data );
		$safe_rules               = array_keys( $theme_data );
		foreach ( $safe_rules as $safe_rule ) {
			$this->assertArrayHasKey( $safe_rule, $filtered_user_theme_json, sprintf( 'wp_filter_global_styles_post() must not remove the "%s" rule as it\'s considered safe.', $safe_rule ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_not_remove_safe_global_style_rules() {
		$result = array();
		foreach ( WP_Theme_JSON::VALID_TOP_LEVEL_KEYS as $safe_rule ) {
			$result[ $safe_rule ] = array( $safe_rule );
		}

		// Settings always get removed.
		unset( $result['settings'] );

		return $result;
	}

	/**
	 * @ticket 56266
	 */
	public function test_should_remove_unsafe_global_style_rules() {
		$filtered_user_theme_json = $this->filter_global_styles( $this->user_theme_data );
		$this->assertArrayNotHasKey( 'nonSchemaRule', $filtered_user_theme_json, 'Filtered json data must not contain unsafe global style rules.' );
	}

	/**
	 * A valid font family style survives the global styles post filter.
	 *
	 * @ticket 63568
	 *
	 * @dataProvider data_valid_font_family_styles
	 *
	 * @param string $font_family A valid CSS font-family value.
	 */
	public function test_should_keep_a_valid_font_family_style( $font_family ) {
		$theme_data           = $this->user_theme_data;
		$theme_data['styles'] = array(
			'typography' => array(
				'fontFamily' => $font_family,
			),
		);

		$filtered = $this->filter_global_styles( $theme_data );

		$this->assertSame(
			$font_family,
			$filtered['styles']['typography']['fontFamily'],
			'The font family style should not change.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_valid_font_family_styles() {
		return array(
			'an apostrophe'        => array( '"O\'Reilly Sans", sans-serif' ),
			'a comma in a name'    => array( '"ACME, Sans", sans-serif' ),
			'an ampersand'         => array( '"Tom & Jerry"' ),
			'a hexadecimal escape' => array( '"O\\22 Reilly Sans"' ),
			'a numeric name'       => array( '"12345", monospace' ),
			'a percent sequence'   => array( '"Font 50%AB"' ),
			'a semicolon'          => array( '"A;B"' ),
			'braces'               => array( '"A{B}"' ),
		);
	}

	/**
	 * An unsafe font family style is removed.
	 *
	 * A value that the font family grammar rejects still goes through the
	 * existing KSES checks. Those checks keep their policy, so that a value
	 * such as `var(--wp--preset--font-family--x)` still works.
	 *
	 * @ticket 63568
	 *
	 * @dataProvider data_unsafe_font_family_styles
	 *
	 * @param string $font_family An unsafe font-family value.
	 */
	public function test_should_remove_an_unsafe_font_family_style( $font_family ) {
		$theme_data           = $this->user_theme_data;
		$theme_data['styles'] = array(
			'typography' => array(
				'fontFamily' => $font_family,
			),
		);

		$filtered = $this->filter_global_styles( $theme_data );

		$this->assertArrayNotHasKey(
			'typography',
			isset( $filtered['styles'] ) ? $filtered['styles'] : array(),
			'The unsafe font family style should be removed.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_unsafe_font_family_styles() {
		return array(
			'a javascript url'       => array( 'url(javascript:alert(1))' ),
			'an expression function' => array( 'expression(alert(1))' ),
			'a rule injection'       => array( 'Inter}body{color:red}' ),
		);
	}

	/**
	 * This is a helper method.
	 * It filters JSON theme data and returns it as an array.
	 *
	 * @param array $theme_data Theme data to filter.
	 * @return array Filtered theme data.
	 */
	private function filter_global_styles( $theme_data ) {
		$user_theme_json          = wp_slash( wp_json_encode( $theme_data ) );
		$filtered_user_theme_json = wp_filter_global_styles_post( $user_theme_json );

		return json_decode( wp_unslash( $filtered_user_theme_json ), true );
	}
}
