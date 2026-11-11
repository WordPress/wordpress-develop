<?php

/**
 * Tests for the functions that output the screen to install plugins.
 *
 * @group plugins
 * @group admin
 */
class Admin_Includes_Plugin_Install_InstallScreenFunctions_Test extends WP_UnitTestCase {

	/**
	 * The original values of the globals and the request data that the tests change.
	 *
	 * @var array
	 */
	private $original = array();

	/**
	 * The arguments of the requests to the Plugin Installation API.
	 *
	 * @var array[]
	 */
	private $api_requests = array();

	/**
	 * The response of the Plugin Installation API.
	 *
	 * @var array|WP_Error
	 */
	private $api_response;

	/**
	 * Loads the tested file.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	/**
	 * Saves the globals and the request data, and answers the API.
	 */
	public function set_up() {
		parent::set_up();

		$this->original['request']       = $_REQUEST;
		$this->original['get']           = $_GET;
		$this->original['wp_list_table'] = $GLOBALS['wp_list_table'] ?? null;

		$_REQUEST = array();
		$_GET     = array();

		$this->api_requests = array();
		$this->api_response = array(
			'seo'   => array(
				'name'  => 'SEO',
				'slug'  => 'seo',
				'count' => 1,
			),
			'forms' => array(
				'name'  => 'Contact Forms',
				'slug'  => 'contact-forms',
				'count' => 25,
			),
		);

		add_filter( 'pre_http_request', array( $this, 'answer_api' ), 10, 3 );
		add_filter( 'wp_trigger_error_trigger_error', '__return_false' );
	}

	/**
	 * Restores the globals and the request data.
	 */
	public function tear_down() {
		$_REQUEST = $this->original['request'];
		$_GET     = $this->original['get'];

		$GLOBALS['wp_list_table'] = $this->original['wp_list_table'];

		parent::tear_down();
	}

	/**
	 * Answers the requests to WordPress.org, and records them.
	 *
	 * @param false|array|WP_Error $preempt The response.
	 * @param array                $args    The arguments of the request.
	 * @param string               $url     The URL of the request.
	 * @return array|WP_Error The response.
	 */
	public function answer_api( $preempt, $args, $url ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->api_requests[] = array( $query['action'] ?? '', $query['request'] ?? array() );

		if ( is_wp_error( $this->api_response ) ) {
			return $this->api_response;
		}

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $this->api_response ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Tests that the popular tags come from the API.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_popular_tags
	 */
	public function test_install_popular_tags_should_ask_the_api_for_the_hot_tags() {
		$tags = install_popular_tags( array( 'number' => 5 ) );

		$this->assertEquals( (object) $this->api_response, $tags );
		$this->assertCount( 1, $this->api_requests );
		$this->assertSame( 'hot_tags', $this->api_requests[0][0] );
		$this->assertSame( '5', $this->api_requests[0][1]['number'] );
	}

	/**
	 * Tests that the popular tags are cached for three hours.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_popular_tags
	 */
	public function test_install_popular_tags_should_cache_the_tags_by_arguments() {
		$args = array( 'number' => 5 );

		install_popular_tags( $args );

		$this->assertEquals( (object) $this->api_response, get_site_transient( 'poptags_' . md5( serialize( $args ) ) ), 'The tags should be cached.' );

		// With an external object cache, the lifetime is kept by the cache and not in the options.
		if ( ! wp_using_ext_object_cache() ) {
			$expires = (int) get_site_option( '_site_transient_timeout_poptags_' . md5( serialize( $args ) ) );

			$this->assertEqualsWithDelta( time() + ( 3 * HOUR_IN_SECONDS ), $expires, 10, 'The tags should be cached for three hours.' );
		}

		$this->api_response = array();

		$this->assertCount( 2, (array) install_popular_tags( $args ), 'The cached tags should be returned.' );
		$this->assertCount( 1, $this->api_requests, 'The API should be asked once.' );

		install_popular_tags( array( 'number' => 10 ) );

		$this->assertCount( 2, $this->api_requests, 'Other arguments should not use the cache.' );
	}

	/**
	 * Tests that an error is returned and not cached.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_popular_tags
	 */
	public function test_install_popular_tags_should_return_the_error_of_the_api_without_caching_it() {
		$this->api_response = new WP_Error( 'plugins_api_failed', 'The API failed.' );

		$tags = install_popular_tags();

		$this->assertWPError( $tags );
		$this->assertFalse( get_site_transient( 'poptags_' . md5( serialize( array() ) ) ), 'The error should not be cached.' );
	}

	/**
	 * Tests the dashboard of the plugin installer.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_dashboard
	 */
	public function test_install_dashboard_should_output_the_popular_tags() {
		$GLOBALS['wp_list_table'] = new class() {
			/**
			 * Outputs the table.
			 */
			public function display() {
				echo '<div id="table"></div>';
			}
		};

		$output = get_echo( 'install_dashboard' );

		$this->assertStringContainsString( '<div id="table"></div>', $output, 'The table should be output.' );
		$this->assertStringContainsString( '<h2>Popular tags</h2>', $output );
		$this->assertStringContainsString( 'You may also browse based on the most popular tags in the Plugin Directory:', $output );
		$this->assertStringContainsString( '<p class="popular-tags">', $output );
		$this->assertStringContainsString( 'plugin-install.php?tab=search&#038;type=tag&#038;s=SEO', $output, 'The link should search the tag.' );
		$this->assertStringContainsString( 'plugin-install.php?tab=search&#038;type=tag&#038;s=Contact+Forms', $output, 'The name of the tag should be encoded.' );
		$this->assertStringContainsString( 'aria-label="SEO (1 plugin)"', $output, 'One plugin should be counted in the singular.' );
		$this->assertStringContainsString( 'aria-label="Contact Forms (25 plugins)"', $output, 'Several plugins should be counted in the plural.' );
		$this->assertStringEndsWith( '</p><br class="clear" /></div>', $output );
	}

	/**
	 * Tests that the error of the API is output instead of the tags.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_dashboard
	 */
	public function test_install_dashboard_should_output_the_error_of_the_api() {
		$GLOBALS['wp_list_table'] = new class() {
			/**
			 * Outputs the table.
			 */
			public function display() {}
		};

		$this->api_response = new WP_Error( 'plugins_api_failed', 'The API failed.' );

		$output = get_echo( 'install_dashboard' );

		$this->assertStringContainsString( '<p class="popular-tags">An unexpected error occurred.', $output, 'The message of the error should be output.' );
		$this->assertStringEndsWith( 'support forums</a>.</p><br class="clear" /></div>', $output );
		$this->assertStringNotContainsString( 'tag-cloud-link', $output );
	}

	/**
	 * Tests the form to search plugins, with its defaults.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_search_form
	 */
	public function test_install_search_form_should_search_a_keyword_by_default() {
		$output = get_echo( 'install_search_form' );

		$this->assertStringContainsString( '<form class="search-form search-plugins" method="get">', $output );
		$this->assertStringContainsString( '<input type="hidden" name="tab" value="search" />', $output );
		$this->assertStringContainsString( '<input type="search" name="s" id="search-plugins" value="" class="wp-filter-search" />', $output );
		$this->assertStringContainsString( '<option value="term" selected=\'selected\'>Keyword</option>', $output, 'The keyword should be selected.' );
		$this->assertStringContainsString( '<option value="author">Author</option>', $output );
		$this->assertStringContainsString( '<option value="tag">Tag</option>', $output );
		$this->assertStringContainsString( 'id="search-submit"', $output, 'The button should be output.' );
	}

	/**
	 * Tests the form to search plugins, with a search.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_search_form
	 */
	public function test_install_search_form_should_use_the_search_of_the_request() {
		$_REQUEST['s']    = 'contact+\\"forms\\"';
		$_REQUEST['type'] = 'author';

		$output = get_echo( 'install_search_form' );

		$this->assertStringContainsString( 'value="contact &quot;forms&quot;"', $output, 'The term should be decoded, unslashed and escaped.' );
		$this->assertStringContainsString( '<option value="author" selected=\'selected\'>Author</option>', $output, 'The type should be selected.' );
		$this->assertStringNotContainsString( 'value="term" selected', $output );
	}

	/**
	 * Tests the form to upload a plugin.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_plugins_upload
	 */
	public function test_install_plugins_upload_should_output_the_upload_form() {
		$output = get_echo( 'install_plugins_upload' );

		$this->assertStringContainsString( 'If you have a plugin in a .zip format, you may install or update it by uploading it here.', $output );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $output );
		$this->assertStringContainsString( 'action="' . esc_url( self_admin_url( 'update.php?action=upload-plugin' ) ) . '"', $output );
		$this->assertStringContainsString( 'name="_wpnonce" value="' . wp_create_nonce( 'plugin-upload' ) . '"', $output, 'The nonce for the action of the upload should be output.' );
		$this->assertStringContainsString( '<input type="file" id="pluginzip" name="pluginzip" accept=".zip" />', $output );
		$this->assertStringContainsString( 'name="install-plugin-submit"', $output );
		$this->assertStringContainsString( 'value="Install Now"', $output );
	}

	/**
	 * Tests the form of the favorites, without a user.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_plugins_favorites_form
	 */
	public function test_install_plugins_favorites_form_should_output_the_form() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$output = get_echo( 'install_plugins_favorites_form' );

		$this->assertStringContainsString( 'If you have marked plugins as favorites on WordPress.org, you can browse them here.', $output );
		$this->assertStringContainsString( '<input type="hidden" name="tab" value="favorites" />', $output );
		$this->assertStringContainsString( '<input type="search" id="user" name="user" value="" />', $output, 'The username should be empty.' );
		$this->assertStringContainsString( 'value="Get Favorites"', $output );
		$this->assertStringContainsString( '<input type="hidden" id="wporg-username-nonce" name="_wpnonce" value="' . wp_create_nonce( 'save_wporg_username_' . $admin_id ) . '" />', $output, 'The nonce of the user should be output.' );
	}

	/**
	 * Tests that the username of the user is filled in.
	 *
	 * @ticket 66558
	 *
	 * @covers ::install_plugins_favorites_form
	 */
	public function test_install_plugins_favorites_form_should_fill_in_the_username() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		update_user_option( $admin_id, 'wporg_favorites', 'jane"doe' );

		$output = get_echo( 'install_plugins_favorites_form' );

		$this->assertStringContainsString( 'value="jane&quot;doe"', $output );
	}

	/**
	 * Tests the introduction of each tab above the table.
	 *
	 * @ticket 66558
	 *
	 * @covers ::display_plugins_table
	 *
	 * @dataProvider data_tab_introductions
	 *
	 * @param string $filter   The name of the filter of the tab.
	 * @param string $expected The expected text above the table.
	 */
	public function test_display_plugins_table_should_introduce_the_tab( $filter, $expected ) {
		$GLOBALS['wp_list_table'] = new class() {
			/**
			 * Outputs the table.
			 */
			public function display() {
				echo '<div id="table"></div>';
			}
		};

		$output = $this->display_in_filter( $filter );

		$this->assertStringStartsWith( $expected, $output );
		$this->assertStringContainsString( '<form id="plugin-filter" method="post">', $output, 'The form should be output.' );
		$this->assertStringContainsString( '<div id="table"></div>', $output, 'The table should be output.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tab_introductions() {
		return array(
			'beta'        => array( 'install_plugins_beta', '<p>You are using a development version of WordPress. These feature plugins are also under development. <a href="https://make.wordpress.org/core/handbook/about/release-cycle/features-as-plugins/">Learn more</a>.</p>' ),
			'featured'    => array( 'install_plugins_featured', '<br>' ),
			'recommended' => array( 'install_plugins_recommended', '<p>These suggestions are based on the plugins you and other users have installed.</p>' ),
			'popular'     => array( 'install_plugins_popular', "\t<form id=\"plugin-filter\"" ),
		);
	}

	/**
	 * Tests that the table of the favorites is not output without a user.
	 *
	 * @ticket 66558
	 *
	 * @covers ::display_plugins_table
	 */
	public function test_display_plugins_table_should_not_output_the_favorites_without_a_user() {
		$GLOBALS['wp_list_table'] = new class() {
			/**
			 * Outputs the table.
			 */
			public function display() {
				echo '<div id="table"></div>';
			}
		};

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( '', $this->display_in_filter( 'install_plugins_favorites' ) );
	}

	/**
	 * Tests that the table of the favorites is output for a user of the request or of the option.
	 *
	 * @ticket 66558
	 *
	 * @covers ::display_plugins_table
	 */
	public function test_display_plugins_table_should_output_the_favorites_with_a_user() {
		$GLOBALS['wp_list_table'] = new class() {
			/**
			 * Outputs the table.
			 */
			public function display() {
				echo '<div id="table"></div>';
			}
		};

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_GET['user'] = 'jane';

		$this->assertStringContainsString( '<div id="table"></div>', $this->display_in_filter( 'install_plugins_favorites' ), 'The user of the request should be enough.' );

		unset( $_GET['user'] );
		update_user_option( $admin_id, 'wporg_favorites', 'jane' );

		$this->assertStringContainsString( '<div id="table"></div>', $this->display_in_filter( 'install_plugins_favorites' ), 'The user of the option should be enough.' );
	}

	/**
	 * Returns the output of display_plugins_table() as it runs for a filter.
	 *
	 * @param string $filter The name of the filter.
	 * @return string The output.
	 */
	private function display_in_filter( $filter ) {
		global $wp_current_filter;

		$wp_current_filter[] = $filter;

		try {
			return get_echo( 'display_plugins_table' );
		} finally {
			array_pop( $wp_current_filter );
		}
	}
}
