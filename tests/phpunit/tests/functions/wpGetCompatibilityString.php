<?php

/**
 * Test wp_get_compatibility_string() function.
 *
 * @since 5.6.0
 *
 * @group functions.php
 * @covers ::wp_get_compatibility_string
 */
class Tests_Functions_WPGetCompatibilityString extends WP_UnitTestCase {

	/**
	 * @ticket       50787
	 * @dataProvider data_wp_get_compatibility_string
	 *
	 * @param string $key      The key for the particular string.
	 * @param string $name     Plugin or theme name.
	 * @param string $expected Expected string.
	 */
	public function test_wp_get_compatibility_string( $key = '', $name = '', $expected ) {
		$this->assertSame( $expected, wp_get_compatibility_string( $key, $name ) );
	}

	public function data_wp_get_compatibility_string() {
		return array(
			array( false, '', '' ),
			array( '', 'Lorem Ipsum', '' ),
			array( 'key_does_not_exist', '', '' ),
			array( 'key_does_not_exist', 'Lorem Ipsum', '' ),
			array( 'theme_incompatible_wp_php', '', 'This theme doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'theme_incompatible_wp_php', 'Lorem Ipsum', 'This theme doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'plugin_incompatible_wp_php', '', 'This plugin doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'plugin_incompatible_wp_php', 'Lorem Ipsum', 'This plugin doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'core_update_incompatible_wp_php', '', 'This update doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'core_update_incompatible_wp_php', 'Lorem Ipsum', 'This update doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'theme_incompatible_wp', '', 'This theme doesn&#8217;t work with your version of WordPress.' ),
			array( 'theme_incompatible_wp', 'Lorem Ipsum', 'This theme doesn&#8217;t work with your version of WordPress.' ),
			array( 'plugin_incompatible_wp', '', 'This plugin doesn&#8217;t work with your version of WordPress.' ),
			array( 'plugin_incompatible_wp', 'Lorem Ipsum', 'This plugin doesn&#8217;t work with your version of WordPress.' ),
			array( 'core_update_incompatible_wp', '', 'This update doesn&#8217;t work with your version of WordPress.' ),
			array( 'core_update_incompatible_wp', 'Lorem Ipsum', 'This update doesn&#8217;t work with your version of WordPress.' ),
			array( 'theme_incompatible_php', '', 'This theme doesn&#8217;t work with your version of PHP.' ),
			array( 'theme_incompatible_php', 'Lorem Ipsum', 'This theme doesn&#8217;t work with your version of PHP.' ),
			array( 'plugin_incompatible_php', '', 'This plugin doesn&#8217;t work with your version of PHP.' ),
			array( 'plugin_incompatible_php', 'Lorem Ipsum', 'This plugin doesn&#8217;t work with your version of PHP.' ),
			array( 'core_update_incompatible_php', '', 'This update doesn&#8217;t work with your version of PHP.' ),
			array( 'core_update_incompatible_php', 'Lorem Ipsum', 'This update doesn&#8217;t work with your version of PHP.' ),
			array( 'update_incompatible_wp_php', '', '' ),
			array( 'update_incompatible_wp_php', 'theme1', 'There is a new version of theme1 available, but it doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'update_incompatible_wp_php', 'plugin1', 'There is a new version of plugin1 available, but it doesn&#8217;t work with your versions of WordPress and PHP.' ),
			array( 'update_incompatible_wp', '', '' ),
			array( 'update_incompatible_wp', 'theme1', 'There is a new version of theme1 available, but it doesn&#8217;t work with your version of WordPress.' ),
			array( 'update_incompatible_wp', 'plugin1', 'There is a new version of plugin1 available, but it doesn&#8217;t work with your version of WordPress.' ),
			array( 'update_incompatible_php', '', '' ),
			array( 'update_incompatible_php', 'theme1', 'There is a new version of theme1 available, but it doesn&#8217;t work with your version of PHP.' ),
			array( 'update_incompatible_php', 'plugin1', 'There is a new version of plugin1 available, but it doesn&#8217;t work with your version of PHP.' ),
		);
	}
}
