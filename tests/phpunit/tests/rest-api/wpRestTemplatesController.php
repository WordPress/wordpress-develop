<?php
/**
 * Unit tests covering WP_REST_Templates_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @covers WP_REST_Templates_Controller
 *
 * @group restapi
 */
class Tests_REST_WpRestTemplatesController extends WP_Test_REST_Controller_Testcase {
	/**
	 * @var int
	 */
	protected static $admin_id;
	protected static $editor_id;
	protected static $subscriber_id;
	private static $template_post;
	private static $template_part_post;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetupBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor_id     = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		// Set up template post.
		$args                = array(
			'post_type'    => 'wp_template',
			'post_name'    => 'my_template',
			'post_title'   => 'My Template',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my template.',
			'tax_input'    => array(
				'wp_theme' => array(
					get_stylesheet(),
				),
			),
		);
		self::$template_post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( self::$template_post->ID, get_stylesheet(), 'wp_theme' );

		// Set up template part post.
		$args                     = array(
			'post_type'    => 'wp_template_part',
			'post_name'    => 'my_template_part',
			'post_title'   => 'My Template Part',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my template part.',
			'tax_input'    => array(
				'wp_theme'              => array(
					get_stylesheet(),
				),
				'wp_template_part_area' => array(
					WP_TEMPLATE_PART_AREA_HEADER,
				),
			),
		);
		self::$template_part_post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( self::$template_part_post->ID, get_stylesheet(), 'wp_theme' );
		wp_set_post_terms( self::$template_part_post->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$template_post->ID );
	}

	/**
	 * Tear down after each test.
	 *
	 * @since 6.5.0
	 */
	public function tear_down() {
		if ( has_filter( 'rest_pre_insert_wp_template_part', 'inject_ignored_hooked_blocks_metadata_attributes' ) ) {
			remove_filter( 'rest_pre_insert_wp_template_part', 'inject_ignored_hooked_blocks_metadata_attributes' );
		}
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'tests/block' ) ) {
			unregister_block_type( 'tests/hooked-block' );
		}

		parent::tear_down();
	}

	/**
	 * @covers WP_REST_Templates_Controller::register_routes
	 * @ticket 54596
	 * @ticket 56467
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			'/wp/v2/templates',
			$routes,
			'Templates route does not exist'
		);
		$this->assertArrayHasKey(
			'/wp/v2/templates/(?P<id>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)',
			$routes,
			'Single template based on the given ID route does not exist'
		);
		$this->assertArrayHasKey(
			'/wp/v2/templates/lookup',
			$routes,
			'Get template fallback content route does not exist'
		);
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_context_param
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates/default//my_template' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_items
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame(
			array(
				'id'              => 'default//my_template',
				'theme'           => 'default',
				'slug'            => 'my_template',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Description of my template.',
				'title'           => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'status'          => 'publish',
				'wp_id'           => self::$template_post->ID,
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => 0,
				'modified'        => mysql_to_rfc3339( self::$template_post->post_modified ),
				'author_text'     => 'Test Blog',
				'original_source' => 'site',
			),
			$this->find_and_normalize_template_by_id( $data, 'default//my_template' )
		);
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_items
	 */
	public function test_get_items_editor() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame(
			array(
				'id'              => 'default//my_template',
				'theme'           => 'default',
				'slug'            => 'my_template',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Description of my template.',
				'title'           => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'status'          => 'publish',
				'wp_id'           => self::$template_post->ID,
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => 0,
				'modified'        => mysql_to_rfc3339( self::$template_post->post_modified ),
				'author_text'     => 'Test Blog',
				'original_source' => 'site',
			),
			$this->find_and_normalize_template_by_id( $data, 'default//my_template' )
		);
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_items
	 */
	public function test_get_items_no_permission_subscriber() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_templates', $response, 403 );
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_items
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_templates', $response, 401 );
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_item
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/default//my_template' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		unset( $data['content'] );
		unset( $data['_links'] );

		$this->assertSame(
			array(
				'id'              => 'default//my_template',
				'theme'           => 'default',
				'slug'            => 'my_template',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Description of my template.',
				'title'           => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'status'          => 'publish',
				'wp_id'           => self::$template_post->ID,
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => 0,
				'modified'        => mysql_to_rfc3339( self::$template_post->post_modified ),
				'author_text'     => 'Test Blog',
				'original_source' => 'site',
			),
			$data
		);
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_item
	 */
	public function test_get_item_editor() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/default//my_template' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		unset( $data['content'] );
		unset( $data['_links'] );

		$this->assertSame(
			array(
				'id'              => 'default//my_template',
				'theme'           => 'default',
				'slug'            => 'my_template',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Description of my template.',
				'title'           => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'status'          => 'publish',
				'wp_id'           => self::$template_post->ID,
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => 0,
				'modified'        => mysql_to_rfc3339( self::$template_post->post_modified ),
				'author_text'     => 'Test Blog',
				'original_source' => 'site',
			),
			$data
		);
	}

	/**
	 * @covers WP_REST_Templates_Controller::get_item
	 */
	public function test_get_item_subscriber() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/default//my_template' );
		$response = rest_get_server()->dispatch( $request );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_templates', $response, 403 );
	}

	/**
	 * @ticket 54507
	 * @dataProvider data_get_item_works_with_a_single_slash
	 */
	public function test_get_item_works_with_a_single_slash( $endpoint_url ) {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', $endpoint_url );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		unset( $data['content'] );
		unset( $data['_links'] );

		$this->assertSame(
			array(
				'id'              => 'default//my_template',
				'theme'           => 'default',
				'slug'            => 'my_template',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Description of my template.',
				'title'           => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'status'          => 'publish',
				'wp_id'           => self::$template_post->ID,
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => 0,
				'modified'        => mysql_to_rfc3339( self::$template_post->post_modified ),
				'author_text'     => 'Test Blog',
				'original_source' => 'site',
			),
			$data
		);
	}

	public function data_get_item_works_with_a_single_slash() {
		return array(
			array( '/wp/v2/templates/default/my_template' ),
			array( '/wp/v2/templates/default//my_template' ),
		);
	}

	/**
	 * @dataProvider data_get_item_with_valid_theme_dirname
	 * @covers WP_REST_Templates_Controller::get_item
	 * @ticket 54596
	 *
	 * @param string $theme_dir Theme directory to test.
	 * @param string $template  Template to test.
	 * @param array  $args      Arguments to create the 'wp_template" post.
	 */
	public function test_get_item_with_valid_theme_dirname( $theme_dir, $template, array $args ) {
		wp_set_current_user( self::$admin_id );
		switch_theme( $theme_dir );

		// Set up template post.
		$args['post_type'] = 'wp_template';
		$args['tax_input'] = array(
			'wp_theme' => array(
				get_stylesheet(),
			),
		);
		$post              = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( $post->ID, get_stylesheet(), 'wp_theme' );

		$request  = new WP_REST_Request( 'GET', "/wp/v2/templates/{$theme_dir}//{$template}" );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		unset( $data['content'] );
		unset( $data['_links'] );
		$author_name = get_user_by( 'id', self::$admin_id )->get( 'display_name' );

		$this->assertSameSetsWithIndex(
			array(
				'id'              => "{$theme_dir}//{$template}",
				'theme'           => $theme_dir,
				'slug'            => $template,
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => $args['post_excerpt'],
				'title'           => array(
					'raw'      => $args['post_title'],
					'rendered' => $args['post_title'],
				),
				'status'          => 'publish',
				'wp_id'           => $post->ID,
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => self::$admin_id,
				'modified'        => mysql_to_rfc3339( $post->post_modified ),
				'author_text'     => $author_name,
				'original_source' => 'user',
			),
			$data
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_item_with_valid_theme_dirname() {
		$theme_root_dir = DIR_TESTDATA . '/themedir1/';
		return array(
			'template parts: parent theme'                => array(
				'theme_dir' => 'themedir1/block-theme',
				'template'  => 'small-header',
				'args'      => array(
					'post_name'    => 'small-header',
					'post_title'   => 'Small Header Template',
					'post_content' => file_get_contents( $theme_root_dir . '/block-theme/parts/small-header.html' ),
					'post_excerpt' => 'Description of small header template.',
				),
			),
			'template: parent theme'                      => array(
				'theme_dir' => 'themedir1/block-theme',
				'template'  => 'page-home',
				'args'      => array(
					'post_name'    => 'page-home',
					'post_title'   => 'Home Page Template',
					'post_content' => file_get_contents( $theme_root_dir . 'block-theme/templates/page-home.html' ),
					'post_excerpt' => 'Description of page home template.',
				),
			),
			'template parts: parent theme with non latin characters' => array(
				'theme_dir' => 'themedir1/block-theme-non-latin',
				'template'  => 'small-header-%cf%84%ce%b5%cf%83%cf%84',
				'args'      => array(
					'post_name'    => 'small-header-τεστ',
					'post_title'   => 'Small Header τεστ Template',
					'post_content' => file_get_contents( $theme_root_dir . '/block-theme-non-latin/parts/small-header-test.html' ),
					'post_excerpt' => 'Description of small header τεστ template.',
				),
			),
			'template: parent theme with non latin name'  => array(
				'theme_dir' => 'themedir1/block-theme-non-latin',
				'template'  => 'page-%cf%84%ce%b5%cf%83%cf%84',
				'args'      => array(
					'post_name'    => 'page-τεστ',
					'post_title'   => 'τεστ Page Template',
					'post_content' => file_get_contents( $theme_root_dir . 'block-theme-non-latin/templates/page-test.html' ),
					'post_excerpt' => 'Description of page τεστ template.',
				),
			),
			'template parts: parent theme with chinese characters' => array(
				'theme_dir' => 'themedir1/block-theme-non-latin',
				'template'  => 'small-header-%e6%b5%8b%e8%af%95',
				'args'      => array(
					'post_name'    => 'small-header-测试',
					'post_title'   => 'Small Header 测试 Template',
					'post_content' => file_get_contents( $theme_root_dir . '/block-theme-non-latin/parts/small-header-test.html' ),
					'post_excerpt' => 'Description of small header 测试 template.',
				),
			),
			'template: parent theme with non latin name using chinese characters' => array(
				'theme_dir' => 'themedir1/block-theme-non-latin',
				'template'  => 'page-%e6%b5%8b%e8%af%95',
				'args'      => array(
					'post_name'    => 'page-测试',
					'post_title'   => '测试 Page Template',
					'post_content' => file_get_contents( $theme_root_dir . 'block-theme-non-latin/templates/page-test.html' ),
					'post_excerpt' => 'Description of page 测试 template.',
				),
			),
			'template: parent theme deprecated path'      => array(
				'theme_dir' => 'themedir1/block-theme-deprecated-path',
				'template'  => 'page-home',
				'args'      => array(
					'post_name'    => 'page-home',
					'post_title'   => 'Home Page Template',
					'post_content' => file_get_contents( $theme_root_dir . 'block-theme-deprecated-path/block-templates/page-home.html' ),
					'post_excerpt' => 'Description of page home template.',
				),
			),
			'template: child theme'                       => array(
				'theme_dir' => 'themedir1/block-theme-child',
				'template'  => 'page-1',
				'args'      => array(
					'post_name'    => 'page-1',
					'post_title'   => 'Page 1 Template',
					'post_content' => file_get_contents( $theme_root_dir . 'block-theme-child/templates/page-1.html' ),
					'post_excerpt' => 'Description of page 1 template.',
				),
			),
			'template part: subdir with _-[]. characters' => array(
				'theme_dir' => 'themedir1/block_theme-[0.4.0]',
				'template'  => 'large-header',
				'args'      => array(
					'post_name'    => 'large-header',
					'post_title'   => 'Large Header Template Part',
					'post_content' => file_get_contents( $theme_root_dir . 'block_theme-[0.4.0]/parts/large-header.html' ),
					'post_excerpt' => 'Description of large header template.',
				),
			),
			'template: subdir with _-[]. characters'      => array(
				'theme_dir' => 'themedir1/block_theme-[0.4.0]',
				'template'  => 'page-large-header',
				'args'      => array(
					'post_name'    => 'page-large-header',
					'post_title'   => 'Page Large Template',
					'post_content' => file_get_contents( $theme_root_dir . 'block_theme-[0.4.0]/templates/page-large-header.html' ),
					'post_excerpt' => 'Description of page large template.',
				),
			),
		);
	}

	/**
	 * Tests that get_item() returns plugin-registered templates.
	 *
	 * @ticket 61804
	 *
	 * @covers WP_REST_Templates_Controller::get_item
	 */
	public function test_get_item_from_registry() {
		wp_set_current_user( self::$admin_id );

		$template_name = 'test-plugin//test-template';
		$args          = array(
			'content'     => 'Template content',
			'title'       => 'Test Template',
			'description' => 'Description of test template',
			'post_types'  => array( 'post', 'page' ),
		);

		wp_register_block_template( $template_name, $args );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/test-plugin//test-template' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertNotWPError( $response, "Fetching a registered template shouldn't cause an error." );

		$data = $response->get_data();

		$this->assertSame( 'default//test-template', $data['id'], 'Template ID mismatch.' );
		$this->assertSame( 'default', $data['theme'], 'Template theme mismatch.' );
		$this->assertSame( 'Template content', $data['content']['raw'], 'Template content mismatch.' );
		$this->assertSame( 'test-template', $data['slug'], 'Template slug mismatch.' );
		$this->assertSame( 'plugin', $data['source'], "Template source should be 'plugin'." );
		$this->assertSame( 'plugin', $data['origin'], "Template origin should be 'plugin'." );
		$this->assertSame( 'test-plugin', $data['author_text'], 'Template author text mismatch.' );
		$this->assertSame( 'Description of test template', $data['description'], 'Template description mismatch.' );
		$this->assertSame( 'Test Template', $data['title']['rendered'], 'Template title mismatch.' );
		$this->assertSame( 'test-plugin', $data['plugin'], 'Plugin name mismatch.' );

		wp_unregister_block_template( $template_name );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/test-plugin//test-template' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertNotWPError( $response, "Fetching an unregistered template shouldn't cause an error." );
		$this->assertSame( 404, $response->get_status(), 'Fetching an unregistered template should return 404.' );
	}

	/**
	 * @ticket 54507
	 * @dataProvider data_sanitize_template_id
	 */
	public function test_sanitize_template_id( $input_id, $sanitized_id ) {
		$endpoint = new WP_REST_Templates_Controller( 'wp_template' );
		$this->assertSame(
			$sanitized_id,
			$endpoint->_sanitize_template_id( $input_id )
		);
	}

	public function data_sanitize_template_id() {
		return array(
			array( 'tt1-blocks/index', 'tt1-blocks//index' ),
			array( 'tt1-blocks//index', 'tt1-blocks//index' ),

			array( 'theme-experiments/tt1-blocks/index', 'theme-experiments/tt1-blocks//index' ),
			array( 'theme-experiments/tt1-blocks//index', 'theme-experiments/tt1-blocks//index' ),
		);
	}

	/**
	 * @ticket 54422
	 * @covers WP_REST_Templates_Controller::create_item
	 */
	public function test_create_item() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/templates' );
		$request->set_body_params(
			array(
				'slug'        => 'my_custom_template',
				'description' => 'Just a description',
				'title'       => 'My Template',
				'content'     => 'Content',
				'author'      => self::$admin_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$modified = get_post( $data['wp_id'] )->post_modified;
		unset( $data['_links'] );
		unset( $data['wp_id'] );

		$author_name = get_user_by( 'id', self::$admin_id )->get( 'display_name' );

		$this->assertSame(
			array(
				'id'              => 'default//my_custom_template',
				'theme'           => 'default',
				'content'         => array(
					'raw' => 'Content',
				),
				'slug'            => 'my_custom_template',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Just a description',
				'title'           => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'status'          => 'publish',
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => self::$admin_id,
				'modified'        => mysql_to_rfc3339( $modified ),
				'author_text'     => $author_name,
				'original_source' => 'user',
			),
			$data
		);
	}

	/**
	 * @ticket 54680
	 * @covers WP_REST_Templates_Controller::create_item
	 * @covers WP_REST_Templates_Controller::get_item_schema
	 */
	public function test_create_item_with_numeric_slug() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/templates' );
		$request->set_body_params(
			array(
				'slug'        => '404',
				'description' => 'Template shown when no content is found.',
				'title'       => '404',
				'author'      => self::$admin_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$modified = get_post( $data['wp_id'] )->post_modified;
		unset( $data['_links'] );
		unset( $data['wp_id'] );

		$author_name = get_user_by( 'id', self::$admin_id )->get( 'display_name' );

		$this->assertSame(
			array(
				'id'              => 'default//404',
				'theme'           => 'default',
				'content'         => array(
					'raw' => '',
				),
				'slug'            => '404',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Template shown when no content is found.',
				'title'           => array(
					'raw'      => '404',
					'rendered' => '404',
				),
				'status'          => 'publish',
				'has_theme_file'  => false,
				'is_custom'       => false,
				'author'          => self::$admin_id,
				'modified'        => mysql_to_rfc3339( $modified ),
				'author_text'     => $author_name,
				'original_source' => 'user',
			),
			$data
		);
	}

	/**
	 * @ticket 54422
	 * @covers WP_REST_Templates_Controller::create_item
	 */
	public function test_create_item_raw() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/templates' );
		$request->set_body_params(
			array(
				'slug'        => 'my_custom_template_raw',
				'description' => 'Just a description',
				'title'       => array(
					'raw' => 'My Template',
				),
				'content'     => array(
					'raw' => 'Content',
				),
				'author'      => self::$admin_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$modified = get_post( $data['wp_id'] )->post_modified;
		unset( $data['_links'] );
		unset( $data['wp_id'] );

		$author_name = get_user_by( 'id', self::$admin_id )->get( 'display_name' );

		$this->assertSame(
			array(
				'id'              => 'default//my_custom_template_raw',
				'theme'           => 'default',
				'content'         => array(
					'raw' => 'Content',
				),
				'slug'            => 'my_custom_template_raw',
				'source'          => 'custom',
				'origin'          => null,
				'type'            => 'wp_template',
				'description'     => 'Just a description',
				'title'           => array(
					'raw'      => 'My Template',
					'rendered' => 'My Template',
				),
				'status'          => 'publish',
				'has_theme_file'  => false,
				'is_custom'       => true,
				'author'          => self::$admin_id,
				'modified'        => mysql_to_rfc3339( $modified ),
				'author_text'     => $author_name,
				'original_source' => 'user',
			),
			$data
		);
	}

	public function test_create_item_invalid_author() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/templates' );
		$request->set_body_params(
			array(
				'slug'        => 'my_custom_template_invalid_author',
				'description' => 'Just a description',
				'title'       => 'My Template',
				'content'     => 'Content',
				'author'      => -1,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_author', $response, 400 );
	}

	/**
	 * @covers WP_REST_Templates_Controller::update_item
	 */
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/templates/default//my_template' );
		$request->set_body_params(
			array(
				'title' => 'My new Index Title',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'My new Index Title', $data['title']['raw'] );
		$this->assertSame( 'custom', $data['source'] );
	}

	/**
	 * @covers WP_REST_Templates_Controller::update_item
	 */
	public function test_update_item_raw() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/templates/default//my_template' );
		$request->set_body_params(
			array(
				'title' => array( 'raw' => 'My new raw Index Title' ),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'My new raw Index Title', $data['title']['raw'] );
		$this->assertSame( 'custom', $data['source'] );
	}

	/**
	 * @covers WP_REST_Templates_Controller::delete_item
	 */
	public function test_delete_item() {
		// Set up template post.
		$args    = array(
			'post_type'    => 'wp_template',
			'post_name'    => 'my_test_template',
			'post_title'   => 'My Template',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my template.',
			'tax_input'    => array(
				'wp_theme' => array(
					get_stylesheet(),
				),
			),
		);
		$post_id = self::factory()->post->create( $args );
		wp_set_post_terms( $post_id, get_stylesheet(), 'wp_theme' );

		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/templates/default//my_test_template' );
		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'My Template', $data['title']['raw'] );
		$this->assertSame( 'trash', $data['status'] );
	}

	/**
	 * @covers WP_REST_Templates_Controller::delete_item
	 */
	public function test_delete_item_skip_trash() {
		// Set up template post.
		$args    = array(
			'post_type'    => 'wp_template',
			'post_name'    => 'my_test_template',
			'post_title'   => 'My Template',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my template.',
			'tax_input'    => array(
				'wp_theme' => array(
					get_stylesheet(),
				),
			),
		);
		$post_id = self::factory()->post->create( $args );
		wp_set_post_terms( $post_id, get_stylesheet(), 'wp_theme' );

		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/templates/default//my_test_template' );
		$request->set_param( 'force', 'true' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertNotEmpty( $data['previous'] );
	}

	/**
	 * @covers WP_REST_Templates_Controller::delete_item
	 */
	public function test_delete_item_fail() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/templates/justrandom//template' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_template_not_found', $response, 404 );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	public function test_prepare_item_limit_fields() {
		wp_set_current_user( self::$admin_id );

		$endpoint = new WP_REST_Templates_Controller( 'wp_template' );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/templates/default//my_template' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,slug' );
		$obj      = get_block_template( 'default//my_template', 'wp_template' );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->assertSame(
			array(
				'id',
				'slug',
			),
			array_keys( $response->get_data() )
		);
	}

	/**
	 * @ticket 54422
	 * @covers WP_REST_Templates_Controller::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/templates' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 18, $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'theme', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'source', $properties );
		$this->assertArrayHasKey( 'origin', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'wp_id', $properties );
		$this->assertArrayHasKey( 'has_theme_file', $properties );
		$this->assertArrayHasKey( 'is_custom', $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'author_text', $properties );
		$this->assertArrayHasKey( 'original_source', $properties );
		$this->assertArrayHasKey( 'plugin', $properties );
	}

	protected function find_and_normalize_template_by_id( $templates, $id ) {
		foreach ( $templates as $template ) {
			if ( $template['id'] === $id ) {
				unset( $template['content'] );
				unset( $template['_links'] );
				return $template;
			}
		}

		return null;
	}

	/**
	 * @dataProvider data_create_item_with_is_wp_suggestion
	 * @ticket 56467
	 * @covers WP_REST_Templates_Controller::create_item
	 *
	 * @param array $body_params Data set to test.
	 * @param array $expected    Expected results.
	 */
	public function test_create_item_with_is_wp_suggestion( array $body_params, array $expected ) {
		// Set up the user.
		$body_params['author'] = self::$admin_id;
		$expected['author']    = self::$admin_id;
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/templates' );
		$request->set_body_params( $body_params );
		$response                    = rest_get_server()->dispatch( $request );
		$data                        = $response->get_data();
		$modified                    = get_post( $data['wp_id'] )->post_modified;
		$expected['modified']        = mysql_to_rfc3339( $modified );
		$expected['author_text']     = get_user_by( 'id', self::$admin_id )->get( 'display_name' );
		$expected['original_source'] = 'user';

		unset( $data['_links'] );
		unset( $data['wp_id'] );

		$this->assertSame( $expected, $data );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_create_item_with_is_wp_suggestion() {
		$expected = array(
			'id'             => 'default//page-rigas',
			'theme'          => 'default',
			'content'        => array(
				'raw' => 'Content',
			),
			'slug'           => 'page-rigas',
			'source'         => 'custom',
			'origin'         => null,
			'type'           => 'wp_template',
			'description'    => 'Just a description',
			'title'          => array(
				'raw'      => 'My Template',
				'rendered' => 'My Template',
			),
			'status'         => 'publish',
			'has_theme_file' => false,
			'is_custom'      => false,
			'author'         => null,
		);

		return array(
			'is_wp_suggestion: true'  => array(
				'body_params' => array(
					'slug'             => 'page-rigas',
					'description'      => 'Just a description',
					'title'            => 'My Template',
					'content'          => 'Content',
					'is_wp_suggestion' => true,
					'author'           => null,
				),
				'expected'    => $expected,
			),
			'is_wp_suggestion: false' => array(
				'body_params' => array(
					'slug'             => 'page-hi',
					'description'      => 'Just a description',
					'title'            => 'My Template',
					'content'          => 'Content',
					'is_wp_suggestion' => false,
					'author'           => null,
				),
				'expected'    => array_merge(
					$expected,
					array(
						'id'        => 'default//page-hi',
						'slug'      => 'page-hi',
						'is_custom' => true,
					)
				),
			),
		);
	}

	/**
	 * @ticket 56467
	 * @covers WP_REST_Templates_Controller::get_template_fallback
	 */
	public function test_get_template_fallback() {
		wp_set_current_user( self::$admin_id );
		switch_theme( 'block-theme' );
		$request = new WP_REST_Request( 'GET', '/wp/v2/templates/lookup' );
		// Should fallback to `index.html`.
		$request->set_param( 'slug', 'tag-status' );
		$request->set_param( 'is_custom', false );
		$request->set_param( 'template_prefix', 'tag' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 'index', $response->get_data()['slug'], 'Should fallback to `index.html`.' );
		// Should fallback to `page.html`.
		$request->set_param( 'slug', 'page-hello' );
		$request->set_param( 'is_custom', false );
		$request->set_param( 'template_prefix', 'page' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 'page', $response->get_data()['slug'], 'Should fallback to `page.html`.' );
		// Should fallback to `index.html`.
		$request->set_param( 'slug', 'author' );
		$request->set_param( 'ignore_empty', true );
		$request->set_param( 'template_prefix', 'tag' );
		$request->set_param( 'is_custom', false );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 'index', $response->get_data()['slug'], 'Should fallback to `index.html` when  ignore_empty is `true`.' );
	}

	/**
	 * @ticket 60909
	 * @covers WP_REST_Templates_Controller::get_template_fallback
	 */
	public function test_get_template_fallback_not_found() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/templates/lookup' );
		$request->set_param( 'slug', 'not-found' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( new stdClass(), $data, 'Response should be an empty object when a fallback template is not found.' );
	}

	/**
	 * @ticket 57851
	 *
	 * @covers WP_REST_Templates_Controller::prepare_item_for_database
	 */
	public function test_prepare_item_for_database() {
		$endpoint = new WP_REST_Templates_Controller( 'wp_template_part' );

		$prepare_item_for_database = new ReflectionMethod( $endpoint, 'prepare_item_for_database' );
		$prepare_item_for_database->setAccessible( true );

		$body_params = array(
			'title'   => 'Untitled Template Part',
			'slug'    => 'untitled-template-part',
			'content' => '',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/template-parts' );
		$request->set_body_params( $body_params );

		$prepared = $prepare_item_for_database->invoke( $endpoint, $request );

		$this->assertInstanceOf( 'stdClass', $prepared, 'The item could not be prepared for the database.' );

		$this->assertObjectHasProperty( 'post_type', $prepared, 'The "post_type" was not included in the prepared template part.' );
		$this->assertObjectHasProperty( 'post_status', $prepared, 'The "post_status" was not included in the prepared template part.' );
		$this->assertObjectHasProperty( 'tax_input', $prepared, 'The "tax_input" was not included in the prepared template part.' );
		$this->assertArrayHasKey( 'wp_theme', $prepared->tax_input, 'The "wp_theme" tax was not included in the prepared template part.' );
		$this->assertArrayHasKey( 'wp_template_part_area', $prepared->tax_input, 'The "wp_template_part_area" tax was not included in the prepared template part.' );
		$this->assertObjectHasProperty( 'post_content', $prepared, 'The "post_content" was not included in the prepared template part.' );
		$this->assertObjectHasProperty( 'post_title', $prepared, 'The "post_title" was not included in the prepared template part.' );

		$this->assertSame( 'wp_template_part', $prepared->post_type, 'The "post_type" in the prepared template part should be "wp_template_part".' );
		$this->assertSame( 'publish', $prepared->post_status, 'The post status in the prepared template part should be "publish".' );
		$this->assertSame( WP_TEMPLATE_PART_AREA_UNCATEGORIZED, $prepared->tax_input['wp_template_part_area'], 'The area in the prepared template part should be uncategorized.' );
		$this->assertSame( 'Untitled Template Part', $prepared->post_title, 'The title was not correct in the prepared template part.' );

		$this->assertEmpty( $prepared->post_content, 'The content was not correct in the prepared template part.' );
	}

	/**
	 * @ticket 60671
	 *
	 * @covers WP_REST_Templates_Controller::prepare_item_for_database
	 * @covers inject_ignored_hooked_blocks_metadata_attributes
	 */
	public function test_prepare_item_for_database_injects_hooked_block() {
		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'tests/anchor-block' => 'after',
				),
			)
		);

		add_filter( 'rest_pre_insert_wp_template_part', 'inject_ignored_hooked_blocks_metadata_attributes' );

		$endpoint = new WP_REST_Templates_Controller( 'wp_template_part' );

		$prepare_item_for_database = new ReflectionMethod( $endpoint, 'prepare_item_for_database' );
		$prepare_item_for_database->setAccessible( true );

		$id          = get_stylesheet() . '//' . 'my_template_part';
		$body_params = array(
			'id'      => $id,
			'slug'    => 'my_template_part',
			'content' => '<!-- wp:tests/anchor-block -->Hello<!-- /wp:tests/anchor-block -->',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/template-parts' );
		$request->set_body_params( $body_params );

		$prepared = $prepare_item_for_database->invoke( $endpoint, $request );
		$this->assertSame(
			'<!-- wp:tests/anchor-block {"metadata":{"ignoredHookedBlocks":["tests/hooked-block"]}} -->Hello<!-- /wp:tests/anchor-block -->',
			$prepared->post_content,
			'The hooked block was not injected into the anchor block\'s ignoredHookedBlocks metadata.'
		);
	}
}
