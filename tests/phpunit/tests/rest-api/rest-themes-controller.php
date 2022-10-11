<?php
/**
 * Unit tests covering WP_REST_Themes_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi-themes
 * @group restapi
 */
class WP_Test_REST_Themes_Controller extends WP_Test_REST_Controller_Testcase {
	/**
	 * Subscriber user ID.
	 *
	 * @since 5.0.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $subscriber_id;

	/**
	 * Contributor user ID.
	 *
	 * @since 5.0.0
	 *
	 * @var int $contributor_id
	 */
	protected static $contributor_id;

	/**
	 * Admin user ID.
	 *
	 * @since 5.7.0
	 *
	 * @var int $admin_id
	 */
	protected static $admin_id;

	/**
	 * The current theme object.
	 *
	 * @since 5.0.0
	 *
	 * @var WP_Theme $current_theme
	 */
	protected static $current_theme;

	/**
	 * The REST API route for themes.
	 *
	 * @since 5.0.0
	 *
	 * @var string $themes_route
	 */
	protected static $themes_route = '/wp/v2/themes';

	/**
	 * Performs a REST API request for the active theme.
	 *
	 * @since 5.0.0
	 *
	 * @param string $method Optional. Request method. Default GET.
	 * @return WP_REST_Response The request's response.
	 */
	protected function perform_active_theme_request( $method = 'GET' ) {
		$request = new WP_REST_Request( $method, self::$themes_route );
		$request->set_param( 'status', 'active' );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Check that common properties are included in a response.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_REST_Response $response Current REST API response.
	 */
	protected function check_get_theme_response( $response ) {
		if ( $response instanceof WP_REST_Response ) {
			$headers  = $response->get_headers();
			$response = $response->get_data();
		} else {
			$headers = array();
		}

		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertSame( 1, $headers['X-WP-Total'] );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
		$this->assertSame( 1, $headers['X-WP-TotalPages'] );
	}

	/**
	 * Set up class test fixtures.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id       = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$subscriber_id  = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		self::$contributor_id = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);
		self::$current_theme  = wp_get_theme();

		wp_set_current_user( self::$contributor_id );
	}

	/**
	 * Clean up test fixtures.
	 *
	 * @since 5.0.0
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$subscriber_id );
		self::delete_user( self::$contributor_id );
		self::delete_user( self::$admin_id );
	}

	/**
	 * Set up each test method.
	 *
	 * @since 5.0.0
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$contributor_id );
		switch_theme( 'rest-api' );
	}

	/**
	 * Theme routes should be registered correctly.
	 *
	 * @ticket 45016
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( self::$themes_route, $routes );
		$this->assertArrayHasKey(
			sprintf( '%s/(?P<stylesheet>%s)', self::$themes_route, WP_REST_Themes_Controller::PATTERN ),
			$routes
		);
	}

	/**
	 * Test retrieving a collection of themes.
	 *
	 * @ticket 45016
	 */
	public function test_get_items() {
		$response = self::perform_active_theme_request();

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->check_get_theme_response( $response );
		$fields = array(
			'_links',
			'author',
			'author_uri',
			'description',
			'name',
			'requires_php',
			'requires_wp',
			'screenshot',
			'status',
			'stylesheet',
			'tags',
			'template',
			'textdomain',
			'theme_supports',
			'theme_uri',
			'version',
		);
		$this->assertSameSets( $fields, array_keys( $data[0] ) );
	}

	/**
	 * Test retrieving a collection of inactive themes.
	 *
	 * @ticket 50152
	 */
	public function test_get_items_inactive() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', self::$themes_route );
		$request->set_param( 'status', 'inactive' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$fields = array(
			'_links',
			'author',
			'author_uri',
			'description',
			'name',
			'requires_php',
			'requires_wp',
			'screenshot',
			'status',
			'stylesheet',
			'tags',
			'template',
			'textdomain',
			'theme_uri',
			'version',
		);
		$this->assertSameSets( $fields, array_keys( $data[0] ) );

		$this->assertContains( 'twentytwenty', wp_list_pluck( $data, 'stylesheet' ) );
		$this->assertNotContains( get_stylesheet(), wp_list_pluck( $data, 'stylesheet' ) );
	}

	/**
	 * Test retrieving a collection of inactive themes.
	 *
	 * @ticket 50152
	 */
	public function test_get_items_active_and_inactive() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', self::$themes_route );
		$request->set_param( 'status', array( 'active', 'inactive' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertContains( 'twentytwenty', wp_list_pluck( $data, 'stylesheet' ) );
		$this->assertContains( get_stylesheet(), wp_list_pluck( $data, 'stylesheet' ) );
	}

	/**
	 * @ticket 46723
	 * @ticket 50152
	 * @dataProvider data_get_items_by_status
	 */
	public function test_get_items_logged_out( $status, $error_code ) {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', self::$themes_route );
		$request->set_param( 'status', $status );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $error_code, $response, 401 );
	}

	/**
	 * An error should be returned when the user does not have the edit_posts capability.
	 *
	 * @ticket 45016
	 * @ticket 50152
	 * @dataProvider data_get_items_by_status
	 */
	public function test_get_items_no_permission( $status, $error_code ) {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', self::$themes_route );
		$request->set_param( 'status', $status );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $error_code, $response, 403 );
	}

	public function data_get_items_by_status() {
		return array(
			array( 'active', 'rest_cannot_view_active_theme' ),
			array( 'active, inactive', 'rest_cannot_view_themes' ),
			array( 'inactive', 'rest_cannot_view_themes' ),
			array( '', 'rest_cannot_view_themes' ),
		);
	}

	/**
	 * @ticket 50152
	 * @dataProvider data_get_items_by_status_for_contributor
	 */
	public function test_get_items_contributor( $status, $error_code ) {
		wp_set_current_user( self::$contributor_id );
		$request = new WP_REST_Request( 'GET', self::$themes_route );
		$request->set_param( 'status', $status );

		$response = rest_get_server()->dispatch( $request );

		if ( $error_code ) {
			$this->assertErrorResponse( $error_code, $response, 403 );
		} else {
			$this->assertSame( 200, $response->get_status() );
		}
	}

	public function data_get_items_by_status_for_contributor() {
		return array(
			array( 'active', '' ),
			array( 'active, inactive', 'rest_cannot_view_themes' ),
			array( 'inactive', 'rest_cannot_view_themes' ),
			array( '', 'rest_cannot_view_themes' ),
		);
	}

	/**
	 * @ticket 46723
	 */
	public function test_get_item_single_post_type_cap() {
		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'edit_pages' );
		wp_set_current_user( $user->ID );

		$response = self::perform_active_theme_request();
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test an item is prepared for the response.
	 *
	 * @ticket 45016
	 */
	public function test_prepare_item() {
		$response = self::perform_active_theme_request();
		$this->assertSame( 200, $response->get_status() );
		$this->check_get_theme_response( $response );
	}

	/**
	 * Verify the theme schema.
	 *
	 * @ticket 45016
	 */
	public function test_get_item_schema() {
		$response   = self::perform_active_theme_request( 'OPTIONS' );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 15, $properties );

		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'raw', $properties['author']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['author']['properties'] );

		$this->assertArrayHasKey( 'author_uri', $properties );
		$this->assertArrayHasKey( 'raw', $properties['author_uri']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['author_uri']['properties'] );

		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'raw', $properties['description']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['description']['properties'] );

		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'raw', $properties['name']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['name']['properties'] );

		$this->assertArrayHasKey( 'requires_php', $properties );
		$this->assertArrayHasKey( 'requires_wp', $properties );
		$this->assertArrayHasKey( 'screenshot', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'stylesheet', $properties );

		$this->assertArrayHasKey( 'tags', $properties );
		$this->assertArrayHasKey( 'raw', $properties['tags']['properties'] );
		$this->assertArrayHasKey( 'items', $properties['tags']['properties']['raw'] );
		$this->assertArrayHasKey( 'rendered', $properties['tags']['properties'] );

		$this->assertArrayHasKey( 'template', $properties );
		$this->assertArrayHasKey( 'textdomain', $properties );
		$this->assertArrayHasKey( 'theme_supports', $properties );

		$this->assertArrayHasKey( 'theme_uri', $properties );
		$this->assertArrayHasKey( 'raw', $properties['theme_uri']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['theme_uri']['properties'] );

		$this->assertArrayHasKey( 'version', $properties );

		$theme_supports = $properties['theme_supports']['properties'];
		$this->assertArrayHasKey( 'align-wide', $theme_supports );
		$this->assertArrayHasKey( 'automatic-feed-links', $theme_supports );
		$this->assertArrayHasKey( 'block-templates', $theme_supports );
		$this->assertArrayHasKey( 'block-template-parts', $theme_supports, "Theme supports should have 'block-template-parts' key" );
		$this->assertArrayHasKey( 'custom-header', $theme_supports );
		$this->assertArrayHasKey( 'custom-background', $theme_supports );
		$this->assertArrayHasKey( 'custom-logo', $theme_supports );
		$this->assertArrayHasKey( 'customize-selective-refresh-widgets', $theme_supports );
		$this->assertArrayHasKey( 'title-tag', $theme_supports );
		$this->assertArrayHasKey( 'dark-editor-style', $theme_supports );
		$this->assertArrayHasKey( 'disable-custom-font-sizes', $theme_supports );
		$this->assertArrayHasKey( 'disable-custom-gradients', $theme_supports );
		$this->assertArrayHasKey( 'disable-layout-styles', $theme_supports );
		$this->assertArrayHasKey( 'editor-color-palette', $theme_supports );
		$this->assertArrayHasKey( 'editor-font-sizes', $theme_supports );
		$this->assertArrayHasKey( 'editor-gradient-presets', $theme_supports );
		$this->assertArrayHasKey( 'editor-styles', $theme_supports );
		$this->assertArrayHasKey( 'formats', $theme_supports );
		$this->assertArrayHasKey( 'html5', $theme_supports );
		$this->assertArrayHasKey( 'post-thumbnails', $theme_supports );
		$this->assertArrayHasKey( 'responsive-embeds', $theme_supports );
		$this->assertArrayHasKey( 'title-tag', $theme_supports );
		$this->assertArrayHasKey( 'wp-block-styles', $theme_supports );
		$this->assertCount( 23, $theme_supports, 'There should be 23 theme supports' );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_author() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'author', $result[0] );
		$this->assertSame( 'Michael Heilemann', $result[0]['author']['raw'] );
		$this->assertSame(
			'<a href="http://binarybonsai.com/?search=1&#038;term=2">Michael Heilemann</a>',
			$result[0]['author']['rendered']
		);
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_author_uri() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'author_uri', $result[0] );
		$this->assertSame( 'http://binarybonsai.com/?search=1&term=2', $result[0]['author_uri']['raw'] );
		$this->assertSame( 'http://binarybonsai.com/?search=1&#038;term=2', $result[0]['author_uri']['rendered'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_description() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'description', $result[0] );
		$this->assertSame(
			'The 9\' foot tall theme.',
			$result[0]['description']['raw']
		);
		$this->assertSame(
			'The 9&#8242; foot tall theme.',
			$result[0]['description']['rendered']
		);
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_requires_php() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'requires_php', $result[0] );
		$this->assertSame( '5.6', $result[0]['requires_php'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_requires_wp() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'requires_wp', $result[0] );
		$this->assertSame( '5.3', $result[0]['requires_wp'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_name() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'name', $result[0] );
		$this->assertSame( 'REST Theme', $result[0]['name']['raw'] );
		$this->assertSame( 'REST Theme', $result[0]['name']['rendered'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_screenshot() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'screenshot', $result[0] );
		$this->assertSame( '', $result[0]['screenshot'] ); // No screenshot for default theme
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_stylesheet() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'stylesheet', $result[0] );
		$this->assertSame( 'rest-api', $result[0]['stylesheet'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_tags() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'tags', $result[0] );
		$this->assertSame( array( 'holiday', 'custom-menu' ), $result[0]['tags']['raw'] );
		$this->assertSame( 'holiday, custom-menu', $result[0]['tags']['rendered'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_template() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'template', $result[0] );
		$this->assertSame( 'default', $result[0]['template'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_textdomain() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'textdomain', $result[0] );
		$this->assertSame( 'rest-api', $result[0]['textdomain'] );
	}

	public function test_theme_theme_uri() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_uri', $result[0] );
		$this->assertSame( 'http://wordpress.org/?search=1&term=2', $result[0]['theme_uri']['raw'] );
		$this->assertSame( 'http://wordpress.org/?search=1&#038;term=2', $result[0]['theme_uri']['rendered'] );
	}

	/**
	 * @ticket 49906
	 */
	public function test_theme_version() {
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'version', $result[0] );
		$this->assertSame( '1.6', $result[0]['version'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_disable_custom_colors_false() {
		remove_theme_support( 'disable-custom-colors' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'disable-custom-colors', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['disable-custom-colors'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_disable_custom_colors_true() {
		remove_theme_support( 'disable-custom-colors' );
		add_theme_support( 'disable-custom-colors' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['disable-custom-colors'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_disable_custom_font_sizes_false() {
		remove_theme_support( 'disable-custom-font-sizes' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'disable-custom-font-sizes', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['disable-custom-font-sizes'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_disable_custom_font_sizes_true() {
		remove_theme_support( 'disable-custom-font-sizes' );
		add_theme_support( 'disable-custom-font-sizes' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['disable-custom-font-sizes'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_editor_font_sizes_false() {
		remove_theme_support( 'editor-font-sizes' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'editor-font-sizes', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['editor-font-sizes'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_editor_font_sizes_array() {
		remove_theme_support( 'editor-font-sizes' );
		$tiny = array(
			'name' => 'Tiny',
			'size' => 8,
			'slug' => 'tiny',
		);
		add_theme_support( 'editor-font-sizes', array( $tiny ) );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'editor-font-sizes', $result[0]['theme_supports'] );
		$this->assertEqualSetsWithIndex( array( $tiny ), $result[0]['theme_supports']['editor-font-sizes'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_editor_color_palette_false() {
		remove_theme_support( 'editor-color-palette' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'editor-color-palette', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['editor-color-palette'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_editor_color_palette_array() {
		remove_theme_support( 'editor-color-palette' );
		$wordpress_blue = array(
			'name'  => 'WordPress Blue',
			'slug'  => 'wordpress-blue',
			'color' => '#0073AA',
		);
		add_theme_support( 'editor-color-palette', array( $wordpress_blue ) );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertSame( array( $wordpress_blue ), $result[0]['theme_supports']['editor-color-palette'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_enable_automatic_feed_links() {
		remove_theme_support( 'automatic-feed-links' );
		add_theme_support( 'automatic-feed-links' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['automatic-feed-links'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_does_not_enable_automatic_feed_links() {
		remove_theme_support( 'automatic-feed-links' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'automatic-feed-links', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['automatic-feed-links'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_does_not_support_custom_logo() {
		remove_theme_support( 'custom-logo' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'custom-logo', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['custom-logo'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_custom_logo() {
		remove_theme_support( 'custom-logo' );
		$wordpress_logo = array(
			'width'                => 400,
			'height'               => 100,
			'flex-width'           => true,
			'flex-height'          => true,
			'header-text'          => array( 'site-title', 'site-description' ),
			'unlink-homepage-logo' => false,
		);
		add_theme_support( 'custom-logo', $wordpress_logo );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertSame( $wordpress_logo, $result[0]['theme_supports']['custom-logo'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_does_not_support_custom_header() {
		remove_theme_support( 'custom-header' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'custom-header', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['custom-header'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_custom_header() {
		remove_theme_support( 'custom-header' );
		$wordpress_header = array(
			'default-image'          => '',
			'random-default'         => false,
			'width'                  => 0,
			'height'                 => 0,
			'flex-height'            => false,
			'flex-width'             => false,
			'default-text-color'     => '',
			'header-text'            => true,
			'uploads'                => true,
			'wp-head-callback'       => '',
			'admin-head-callback'    => '',
			'admin-preview-callback' => '',
			'video'                  => false,
			'video-active-callback'  => 'is_front_page',
		);
		$excluded         = array(
			'wp-head-callback',
			'admin-head-callback',
			'admin-preview-callback',
			'video-active-callback',
		);
		add_theme_support( 'custom-header', $wordpress_header );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );

		$expected = array_diff_key( $wordpress_header, array_flip( $excluded ) );
		$this->assertSame( $expected, $result[0]['theme_supports']['custom-header'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_does_not_support_custom_background() {
		remove_theme_support( 'custom-background' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'custom-background', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['custom-background'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_custom_background() {
		remove_theme_support( 'custom-background' );
		$background = array(
			'default-image'          => '',
			'default-preset'         => 'default',
			'default-position-x'     => 'left',
			'default-position-y'     => 'top',
			'default-size'           => 'auto',
			'default-repeat'         => 'repeat',
			'default-attachment'     => 'scroll',
			'default-color'          => '',
			'wp-head-callback'       => '_custom_background_cb',
			'admin-head-callback'    => '',
			'admin-preview-callback' => '',
		);
		$excluded   = array(
			'wp-head-callback',
			'admin-head-callback',
			'admin-preview-callback',
		);
		add_theme_support( 'custom-background', $background );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );

		$expected = array_diff_key( $background, array_flip( $excluded ) );
		$this->assertSame( $expected, $result[0]['theme_supports']['custom-background'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_does_not_support_html5() {
		remove_theme_support( 'html5' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'html5', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['html5'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_html5() {
		remove_theme_support( 'html5' );
		$html5 = array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'script',
			'style',
		);
		add_theme_support( 'html5', $html5 );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertSame( $html5, $result[0]['theme_supports']['html5'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_cannot_manage_title_tag() {
		remove_theme_support( 'title-tag' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'title-tag', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['title-tag'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_can_manage_title_tag() {
		global $_wp_theme_features;
		$_wp_theme_features['title-tag'] = true;
		$response                        = self::perform_active_theme_request();
		$result                          = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['title-tag'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_cannot_manage_selective_refresh_for_widgets() {
		remove_theme_support( 'customize-selective-refresh-widgets' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'customize-selective-refresh-widgets', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['customize-selective-refresh-widgets'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_can_manage_selective_refresh_for_widgets() {
		remove_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'customize-selective-refresh-widgets' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['customize-selective-refresh-widgets'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_no_wp_block_styles() {
		remove_theme_support( 'wp-block-styles' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'wp-block-styles', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['wp-block-styles'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_wp_block_styles_optin() {
		remove_theme_support( 'wp-block-styles' );
		add_theme_support( 'wp-block-styles' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['wp-block-styles'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_no_align_wide() {
		remove_theme_support( 'align-wide' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'align-wide', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['align-wide'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_align_wide_optin() {
		remove_theme_support( 'align-wide' );
		add_theme_support( 'align-wide' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['align-wide'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_no_editor_styles() {
		remove_theme_support( 'editor-styles' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'editor-styles', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['editor-styles'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_editor_styles_optin() {
		remove_theme_support( 'editor-styles' );
		add_theme_support( 'editor-styles' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['editor-styles'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_no_dark_editor_style() {
		remove_theme_support( 'dark-editor-style' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'dark-editor-style', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['dark-editor-style'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_dark_editor_style_optin() {
		remove_theme_support( 'dark-editor-style' );
		add_theme_support( 'dark-editor-style' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['dark-editor-style'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_no_disable_custom_gradients() {
		remove_theme_support( 'disable-custom-gradients' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'disable-custom-gradients', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['disable-custom-gradients'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_disable_custom_gradients() {
		remove_theme_support( 'disable-custom-gradients' );
		add_theme_support( 'disable-custom-gradients' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['disable-custom-gradients'] );
	}

	/**
	 * @ticket 49037
	 */
	public function test_theme_supports_editor_gradient_presets_array() {
		remove_theme_support( 'editor-gradient-presets' );
		$gradient = array(
			'name'     => __( 'Vivid cyan blue to vivid purple', 'themeLangDomain' ),
			'gradient' => 'linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)',
			'slug'     => 'vivid-cyan-blue-to-vivid-purple',
		);
		add_theme_support( 'editor-gradient-presets', array( $gradient ) );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertSame( array( $gradient ), $result[0]['theme_supports']['editor-gradient-presets'] );
	}

	/**
	 * Should include relevant data in the 'theme_supports' key.
	 *
	 * @ticket 45016
	 */
	public function test_theme_supports_formats() {
		remove_theme_support( 'post-formats' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'formats', $result[0]['theme_supports'] );
		$this->assertSame( array( 'standard' ), $result[0]['theme_supports']['formats'] );
	}

	/**
	 * Test when a theme only supports some post formats.
	 *
	 * @ticket 45016
	 */
	public function test_theme_supports_formats_non_default() {
		add_theme_support( 'post-formats', array( 'aside', 'video' ) );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'formats', $result[0]['theme_supports'] );
		$this->assertSame( array( 'standard', 'aside', 'video' ), $result[0]['theme_supports']['formats'] );
	}

	/**
	 * Test when a theme does not support responsive embeds.
	 *
	 * @ticket 45016
	 */
	public function test_theme_supports_responsive_embeds_false() {
		remove_theme_support( 'responsive-embeds' );
		$response = self::perform_active_theme_request();

		$result = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'responsive-embeds', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['responsive-embeds'] );
	}

	/**
	 * Test when a theme supports responsive embeds.
	 *
	 * @ticket 45016
	 */
	public function test_theme_supports_responsive_embeds_true() {
		remove_theme_support( 'responsive-embeds' );
		add_theme_support( 'responsive-embeds' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['responsive-embeds'] );
	}

	/**
	 * Test when a theme does not support post thumbnails.
	 *
	 * @ticket 45016
	 */
	public function test_theme_supports_post_thumbnails_false() {
		remove_theme_support( 'post-thumbnails' );
		$response = self::perform_active_theme_request();

		$result = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertArrayHasKey( 'post-thumbnails', $result[0]['theme_supports'] );
		$this->assertFalse( $result[0]['theme_supports']['post-thumbnails'] );
	}

	/**
	 * Test when a theme supports all post thumbnails.
	 *
	 * @ticket 45016
	 */
	public function test_theme_supports_post_thumbnails_true() {
		remove_theme_support( 'post-thumbnails' );
		add_theme_support( 'post-thumbnails' );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertTrue( $result[0]['theme_supports']['post-thumbnails'] );
	}

	/**
	 * Test when a theme only supports post thumbnails for certain post types.
	 *
	 * @ticket 45016
	 */
	public function test_theme_supports_post_thumbnails_array() {
		remove_theme_support( 'post-thumbnails' );
		add_theme_support( 'post-thumbnails', array( 'post' ) );
		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertSame( array( 'post' ), $result[0]['theme_supports']['post-thumbnails'] );
	}

	/**
	 * @ticket 49406
	 */
	public function test_variadic_theme_support() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'array',
				'variadic'     => true,
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type' => 'string',
						),
					),
				),
			)
		);
		add_theme_support( 'test-feature', 'a', 'b', 'c' );

		$response = self::perform_active_theme_request();
		$result   = $response->get_data();
		$this->assertArrayHasKey( 'theme_supports', $result[0] );
		$this->assertSame( array( 'a', 'b', 'c' ), $result[0]['theme_supports']['test-feature'] );
	}

	/**
	 * It should be possible to register custom fields to the endpoint.
	 *
	 * @ticket 45016
	 */
	public function test_get_additional_field_registration() {
		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
		);

		register_rest_field(
			'theme',
			'my_custom_int',
			array(
				'schema'       => $schema,
				'get_callback' => array( $this, 'additional_field_get_callback' ),
			)
		);

		$response = self::perform_active_theme_request( 'OPTIONS' );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		$response = self::perform_active_theme_request( 'GET' );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'my_custom_int', $data[0] );
		$this->assertSame( 2, $data[0]['my_custom_int'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	/**
	 * Return a value for the custom field.
	 *
	 * @since 5.0.0
	 *
	 * @param array $theme Theme data array.
	 * @return int Additional field value.
	 */
	public function additional_field_get_callback( $theme ) {
		return 2;
	}

	/**
	 * The create_item() method does not exist for themes.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * The update_item() method does not exist for themes.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement update_item().
	}

	/**
	 * Test single theme.
	 *
	 * @ticket 50152
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );
		$route    = sprintf( '%s/%s', self::$themes_route, WP_DEFAULT_THEME );
		$request  = new WP_REST_Request( 'GET', $route );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data         = $response->get_data();
		$links        = $response->get_links();
		$fields       = array(
			'author',
			'author_uri',
			'description',
			'name',
			'requires_php',
			'requires_wp',
			'screenshot',
			'status',
			'stylesheet',
			'tags',
			'template',
			'textdomain',
			'theme_uri',
			'version',
		);
		$fields_links = array( 'collection', 'self' );

		$this->assertSameSets( $fields, array_keys( $data ) );
		$this->assertSameSets( $fields_links, array_keys( $links ) );
	}

	/**
	 * @ticket 50152
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', self::$themes_route . '/' . WP_DEFAULT_THEME );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view_themes', $response, 403 );
	}

	/**
	 * @ticket 50152
	 */
	public function test_get_active_item_no_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', self::$themes_route . '/' . get_stylesheet() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view_active_theme', $response, 403 );
	}

	/**
	 * @ticket 50152
	 */
	public function test_get_item_invalid() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', self::$themes_route . '/invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_theme_not_found', $response, 404 );
	}

	/**
	 * @ticket 50152
	 */
	public function test_get_active_item_as_contributor() {
		$route    = sprintf( '%s/%s', self::$themes_route, get_stylesheet() );
		$request  = new WP_REST_Request( 'GET', $route );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @dataProvider data_get_item_non_subdir_theme
	 * @ticket 54596
	 * @covers WP_REST_Themes_Controller::get_item
	 *
	 * @param string $theme_dir     Theme directory to test.
	 * @param string $expected_name Expected theme name.
	 */
	public function test_get_item_non_subdir_theme( $theme_dir, $expected_name ) {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', self::$themes_route . $theme_dir );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $expected_name, $response->get_data()['name']['raw'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_item_non_subdir_theme() {
		return array(
			'parent theme'                => array(
				'theme_dir'     => '/block-theme',
				'expected_name' => 'Block Theme',
			),
			'child theme'                 => array(
				'theme_dir'     => '/block-theme-child',
				'expected_name' => 'Block Theme Child Theme',
			),
			'theme with _-[]. characters' => array(
				'theme_dir'     => '/block_theme-[0.4.0]',
				'expected_name' => 'Block Theme [0.4.0]',
			),
		);
	}

	/**
	 * @dataProvider data_get_item_subdirectory_theme
	 * @ticket 54349
	 * @ticket 54596
	 * @covers WP_REST_Themes_Controller::get_item
	 *
	 * @param string $theme_dir     Theme directory to test.
	 * @param string $expected_name Expected theme name.
	 */
	public function test_get_item_subdirectory_theme( $theme_dir, $expected_name ) {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', self::$themes_route . $theme_dir );
		$response = rest_do_request( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			'A 200 OK status was not returned.'
		);
		$this->assertSame(
			$expected_name,
			$response->get_data()['name']['raw'],
			'The actual theme name was not the expected theme name.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_item_subdirectory_theme() {
		return array(
			'theme2'                      => array(
				'theme_dir'     => '/subdir/theme2',
				'expected_name' => 'My Subdir Theme',
			),
			'theme with _-[]. characters' => array(
				'theme_dir'     => '/subdir/block_theme-[1.0.0]',
				'expected_name' => 'Block Theme [1.0.0] in subdirectory',
			),
		);
	}

	/**
	 * @ticket 54349
	 */
	public function test_can_support_further_routes() {
		register_rest_route(
			'wp/v2',
			sprintf( '/themes/(?P<stylesheet>%s)//test', WP_REST_Themes_Controller::PATTERN ),
			array(
				'callback'            => function ( WP_REST_Request $request ) {
					return $request['stylesheet'];
				},
				'permission_callback' => '__return_true',
			)
		);

		wp_set_current_user( self::$admin_id );

		$response = rest_do_request( self::$themes_route . '/default//test' );
		$this->assertSame( 'default', $response->get_data() );

		$response = rest_do_request( self::$themes_route . '/subdir/theme2//test' );
		$this->assertSame( 'subdir/theme2', $response->get_data() );
	}

	/**
	 * The delete_item() method does not exist for themes.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	/**
	 * Context is not supported for themes.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
		// Controller does not use get_context_param().
	}
}
