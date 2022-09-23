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
			'blocks' => array(),
		),
	);

	/**
	 * @param $block_name
	 *
	 * @dataProvider data_should_not_remove_valid_blocks
	 */
	public function test_should_not_remove_valid_blocks( $block_name, $user_theme_data ) {
		$filtered_user_theme_json = $this->filter_global_styles( $user_theme_data );
		$this->assertFalse( empty( $filtered_user_theme_json['styles']['blocks'][ $block_name ] ), sprintf( 'wp_filter_global_styles_post() must not remove "%s" from the theme data as it\'s a valid block.', $block_name ) );
		$this->assertIsArray( $filtered_user_theme_json['styles']['blocks'][ $block_name ], sprintf( 'The "%s" block data must be of type array.', $block_name ) );
	}

	public function data_should_not_remove_valid_blocks() {
		$registry          = WP_Block_Type_Registry::get_instance();
		$registered_blocks = $registry->get_all_registered();
		$user_themes       = array();
		foreach ( $registered_blocks as $registered_block ) {
			$user_theme_data                                                = $this->user_theme_data;
			$user_theme_data['styles']['blocks'][ $registered_block->name ] = array(
				'border' => array(
					'radius' => '0',
				)
			);

			$user_themes[ 'block: ' . $registered_block->name ] = array(
				'block_name'      => $registered_block->name,
				'user_theme_data' => $user_theme_data,
			);
		}

		return $user_themes;
	}

	/**
	 * @param $block_name
	 *
	 * @dataProvider data_should_remove_invalid_blocks
	 */
	public function test_should_remove_invalid_blocks( $block_name, $user_theme_data ) {
		$filtered_user_theme_json = $this->filter_global_styles( $user_theme_data );
		$this->assertTrue( empty( $filtered_user_theme_json['styles']['blocks'][ $block_name ] ), sprintf( 'wp_filter_global_styles_post() must remove "%s" from the theme data as it\'s invalid.', $block_name ) );
	}

	public function data_should_remove_invalid_blocks() {
		$user_themes = array();
		for ( $i = 0; $i < 50; $i ++ ) {
			$user_theme_data                                    = $this->user_theme_data;
			$block_name                                         = uniqid( 'core/' );
			$user_theme_data['styles']['blocks'][ $block_name ] = array(
				'border' => array(
					'radius' => '0',
				)
			);

			$user_themes[ 'block: ' . $block_name ] = array(
				'block_name'      => $block_name,
				'user_theme_data' => $user_theme_data,
			);
		}

		return $user_themes;
	}

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
	 * @param array $theme_data Theme data to filter.
	 *
	 * @return array             Filtered theme data.
	 */
	private function filter_global_styles( $theme_data ) {
		$user_theme_json          = wp_slash( wp_json_encode( $theme_data ) );
		$filtered_user_theme_json = wp_filter_global_styles_post( $user_theme_json );

		return json_decode( wp_unslash( $filtered_user_theme_json ), true );
	}
}
