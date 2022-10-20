<?php
/**
 * Block registration tests
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Tests for register_block_type(), unregister_block_type(), get_dynamic_block_names(), and register_block_style().
 *
 * @since 5.0.0
 *
 * @group blocks
 */
class Tests_Blocks_Register extends WP_UnitTestCase {

	/**
	 * ID for a test post.
	 *
	 * @since 5.0.0
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up before class.
	 *
	 * @since 5.0.0
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_content' => file_get_contents( DIR_TESTDATA . '/blocks/do-blocks-original.html' ),
			)
		);
	}

	/**
	 * Tear down after class.
	 *
	 * @since 5.0.0
	 */
	public static function wpTearDownAfterClass() {
		// Also deletes revisions.
		wp_delete_post( self::$post_id, true );
	}

	/**
	 * Empty render function for tests to use.
	 */
	public function render_stub() {}

	/**
	 * Tear down after each test.
	 *
	 * @since 5.0.0
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array( 'core/test-static', 'core/test-dynamic', 'tests/notice' ) as $block_name ) {
			if ( $registry->is_registered( $block_name ) ) {
				$registry->unregister( $block_name );
			}
		}

		parent::tear_down();
	}

	/**
	 * Returns Polish locale string.
	 *
	 * @return string
	 */
	public function filter_set_locale_to_polish() {
		return 'pl_PL';
	}

	/**
	 * @ticket 45109
	 */
	public function test_register_affects_main_registry() {
		$name     = 'core/test-static';
		$settings = array(
			'icon' => 'text',
		);

		register_block_type( $name, $settings );

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( $name ) );
	}

	/**
	 * @ticket 45109
	 */
	public function test_unregister_affects_main_registry() {
		$name     = 'core/test-static';
		$settings = array(
			'icon' => 'text',
		);

		register_block_type( $name, $settings );
		unregister_block_type( $name );

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertFalse( $registry->is_registered( $name ) );
	}

	/**
	 * @ticket 50263
	 */
	public function test_does_not_remove_block_asset_path_prefix() {
		$result = remove_block_asset_path_prefix( 'script-handle' );

		$this->assertSame( 'script-handle', $result );
	}

	/**
	 * @ticket 50263
	 */
	public function test_removes_block_asset_path_prefix() {
		$result = remove_block_asset_path_prefix( 'file:block.js' );

		$this->assertSame( 'block.js', $result );
	}

	/**
	 * @ticket 54797
	 */
	public function test_removes_block_asset_path_prefix_and_current_directory() {
		$result = remove_block_asset_path_prefix( 'file:./block.js' );

		$this->assertSame( 'block.js', $result );
	}

	/**
	 * @ticket 50263
	 */
	public function test_generate_block_asset_handle() {
		$block_name = 'unit-tests/my-block';

		$this->assertSame(
			'unit-tests-my-block-editor-script',
			generate_block_asset_handle( $block_name, 'editorScript' )
		);
		$this->assertSame(
			'unit-tests-my-block-script',
			generate_block_asset_handle( $block_name, 'script', 0 )
		);
		$this->assertSame(
			'unit-tests-my-block-view-script-100',
			generate_block_asset_handle( $block_name, 'viewScript', 99 )
		);
		$this->assertSame(
			'unit-tests-my-block-editor-style-2',
			generate_block_asset_handle( $block_name, 'editorStyle', 1 )
		);
		$this->assertSame(
			'unit-tests-my-block-style',
			generate_block_asset_handle( $block_name, 'style' )
		);
	}

	/**
	 * @ticket 50328
	 */
	public function test_generate_block_asset_handle_core_block() {
		$block_name = 'core/paragraph';

		$this->assertSame(
			'wp-block-paragraph-editor',
			generate_block_asset_handle( $block_name, 'editorScript' )
		);
		$this->assertSame(
			'wp-block-paragraph',
			generate_block_asset_handle( $block_name, 'script', 0 )
		);
		$this->assertSame(
			'wp-block-paragraph-view-100',
			generate_block_asset_handle( $block_name, 'viewScript', 99 )
		);
		$this->assertSame(
			'wp-block-paragraph-editor-2',
			generate_block_asset_handle( $block_name, 'editorStyle', 1 )
		);
		$this->assertSame(
			'wp-block-paragraph',
			generate_block_asset_handle( $block_name, 'style' )
		);
	}

	/**
	 * @ticket 50263
	 */
	public function test_field_not_found_register_block_script_handle() {
		$result = register_block_script_handle( array(), 'script' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	public function test_empty_string_value_do_not_register_block_script_handle() {
		$metadata = array( 'script' => '' );
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertFalse( $result );
	}

	public function test_empty_array_value_do_not_register_block_script_handle() {
		$metadata = array( 'script' => array() );
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertFalse( $result );
	}

	public function test_wrong_array_index_do_not_register_block_script_handle() {
		$metadata = array( 'script' => array( 'test-script-handle' ) );
		$result   = register_block_script_handle( $metadata, 'script', 1 );

		$this->assertFalse( $result );
	}

	/**
	 * @expectedIncorrectUsage register_block_script_handle
	 * @ticket 50263
	 */
	public function test_missing_asset_file_register_block_script_handle() {
		$metadata = array(
			'file'   => __FILE__,
			'name'   => 'unit-tests/test-block',
			'script' => 'file:./blocks/notice/missing-asset.js',
		);
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	public function test_handle_passed_register_block_script_handle() {
		$metadata = array(
			'script' => 'test-script-handle',
		);
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertSame( 'test-script-handle', $result );
	}

	public function test_handles_passed_register_block_script_handles() {
		$metadata = array(
			'script' => array( 'test-script-handle', 'test-script-handle-2' ),
		);

		$result = register_block_script_handle( $metadata, 'script' );
		$this->assertSame( 'test-script-handle', $result );

		$result = register_block_script_handle( $metadata, 'script', 1 );
		$this->assertSame( 'test-script-handle-2', $result, 1 );
	}

	/**
	 * @ticket 50263
	 */
	public function test_success_register_block_script_handle() {
		$metadata = array(
			'file'   => DIR_TESTDATA . '/blocks/notice/block.json',
			'name'   => 'unit-tests/test-block',
			'script' => 'file:./block.js',
		);
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertSame( 'unit-tests-test-block-script', $result );
	}

	/**
	 * @ticket 55513
	 */
	public function test_success_register_block_script_handle_in_theme() {
		switch_theme( 'block-theme' );

		$metadata = array(
			'file'       => wp_normalize_path( get_theme_file_path( 'blocks/example-block/block.json' ) ),
			'name'       => 'block-theme/example-block',
			'viewScript' => 'file:./view.js',
		);
		$result   = register_block_script_handle( $metadata, 'viewScript' );

		$expected_script_handle = 'block-theme-example-block-view-script';
		$this->assertSame( $expected_script_handle, $result );
	}

	/**
	 * @ticket 50263
	 */
	public function test_field_not_found_register_block_style_handle() {
		$result = register_block_style_handle( array(), 'style' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	public function test_empty_string_value_do_not_register_block_style_handle() {
		$metadata = array( 'style' => '' );
		$result   = register_block_style_handle( $metadata, 'style' );

		$this->assertFalse( $result );
	}

	public function test_empty_array_value_do_not_register_block_style_handle() {
		$metadata = array( 'style' => array() );
		$result   = register_block_style_handle( $metadata, 'style' );

		$this->assertFalse( $result );
	}

	public function test_wrong_array_index_do_not_register_block_style_handle() {
		$metadata = array( 'style' => array( 'test-style-handle' ) );
		$result   = register_block_style_handle( $metadata, 'style', 1 );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	public function test_handle_passed_register_block_style_handle() {
		$metadata = array(
			'style' => 'test-style-handle',
		);
		$result   = register_block_style_handle( $metadata, 'style' );

		$this->assertSame( 'test-style-handle', $result );
	}

	public function test_handles_passed_register_block_style_handles() {
		$metadata = array(
			'style' => array( 'test-style-handle', 'test-style-handle-2' ),
		);

		$result = register_block_style_handle( $metadata, 'style' );
		$this->assertSame( 'test-style-handle', $result );

		$result = register_block_style_handle( $metadata, 'style', 1 );
		$this->assertSame( 'test-style-handle-2', $result, 1 );
	}

	/**
	 * @ticket 50263
	 * @ticket 50328
	 */
	public function test_success_register_block_style_handle() {
		$metadata = array(
			'file'  => DIR_TESTDATA . '/blocks/notice/block.json',
			'name'  => 'unit-tests/test-block',
			'style' => 'file:./block.css',
		);
		$result   = register_block_style_handle( $metadata, 'style' );

		$this->assertSame( 'unit-tests-test-block-style', $result );
		$this->assertFalse( wp_styles()->get_data( 'unit-tests-test-block-style', 'rtl' ) );

		// @ticket 50328
		$this->assertSame(
			wp_normalize_path( realpath( DIR_TESTDATA . '/blocks/notice/block.css' ) ),
			wp_normalize_path( wp_styles()->get_data( 'unit-tests-test-block-style', 'path' ) )
		);
	}

	/**
	 * @ticket 56664
	 */
	public function test_register_nonexistent_stylesheet() {
		$metadata = array(
			'file'  => DIR_TESTDATA . '/blocks/notice/block.json',
			'name'  => 'unit-tests/test-block-nonexistent-stylesheet',
			'style' => 'file:./nonexistent.css',
		);
		register_block_style_handle( $metadata, 'style' );

		global $wp_styles;
		$this->assertFalse( $wp_styles->registered['unit-tests-test-block-nonexistent-stylesheet-style']->src );
	}

	/**
	 * @ticket 55513
	 */
	public function test_success_register_block_style_handle_in_theme() {
		switch_theme( 'block-theme' );

		$metadata = array(
			'file'        => wp_normalize_path( get_theme_file_path( 'blocks/example-block/block.json' ) ),
			'name'        => 'block-theme/example-block',
			'editorStyle' => 'file:./editor-style.css',
		);
		$result   = register_block_style_handle( $metadata, 'editorStyle' );

		$expected_style_handle = 'block-theme-example-block-editor-style';
		$this->assertSame( $expected_style_handle, $result );
		$this->assertFalse( wp_styles()->get_data( $expected_style_handle, 'rtl' ) );
	}

	/**
	 * Tests that the function returns false when the `block.json` is not found
	 * in the WordPress core.
	 *
	 * @ticket 50263
	 */
	public function test_metadata_not_found_in_wordpress_core() {
		$result = register_block_type_from_metadata( 'unknown' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that the function returns false when the `block.json` is not found
	 * in the current directory.
	 *
	 * @ticket 50263
	 */
	public function test_metadata_not_found_in_the_current_directory() {
		$result = register_block_type_from_metadata( __DIR__ );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that the function returns the registered block when the `block.json`
	 * is found in the fixtures directory.
	 *
	 * @ticket 50263
	 * @ticket 50328
	 */
	public function test_block_registers_with_metadata_fixture() {
		$result = register_block_type_from_metadata(
			DIR_TESTDATA . '/blocks/notice'
		);

		$this->assertInstanceOf( 'WP_Block_Type', $result );
		$this->assertSame( 2, $result->api_version );
		$this->assertSame( 'tests/notice', $result->name );
		$this->assertSame( 'Notice', $result->title );
		$this->assertSame( 'common', $result->category );
		$this->assertSameSets( array( 'tests/group' ), $result->parent );
		$this->assertSameSets( array( 'tests/section' ), $result->ancestor );
		$this->assertSame( 'star', $result->icon );
		$this->assertSame( 'Shows warning, error or success notices…', $result->description );
		$this->assertSameSets( array( 'alert', 'message' ), $result->keywords );
		$this->assertSame(
			array(
				'message' => array(
					'type' => 'string',
				),
				'lock'    => array( 'type' => 'object' ),
			),
			$result->attributes
		);
		$this->assertSame(
			array(
				'tests/message' => 'message',
			),
			$result->provides_context
		);
		$this->assertSameSets( array( 'groupId' ), $result->uses_context );
		$this->assertSame(
			array(
				'align'             => true,
				'lightBlockWrapper' => true,
			),
			$result->supports
		);
		$this->assertSame(
			array(
				array(
					'name'      => 'default',
					'label'     => 'Default',
					'isDefault' => true,
				),
				array(
					'name'  => 'other',
					'label' => 'Other',
				),
			),
			$result->styles
		);
		$this->assertSame(
			array(
				array(
					'name'        => 'error',
					'title'       => 'Error',
					'description' => 'Shows error.',
					'keywords'    => array( 'failure' ),
				),
			),
			$result->variations
		);
		$this->assertSame(
			array(
				'attributes' => array(
					'message' => 'This is a notice!',
				),
			),
			$result->example
		);
		$this->assertSameSets(
			array( 'tests-notice-editor-script' ),
			$result->editor_script_handles
		);
		$this->assertSameSets(
			array( 'tests-notice-script' ),
			$result->script_handles
		);
		$this->assertSameSets(
			array( 'tests-notice-view-script', 'tests-notice-view-script-2' ),
			$result->view_script_handles
		);
		$this->assertSameSets(
			array( 'tests-notice-editor-style' ),
			$result->editor_style_handles
		);
		$this->assertSameSets(
			array( 'tests-notice-style', 'tests-notice-style-2' ),
			$result->style_handles
		);

		// @ticket 50328
		$this->assertSame(
			wp_normalize_path( realpath( DIR_TESTDATA . '/blocks/notice/block.css' ) ),
			wp_normalize_path( wp_styles()->get_data( 'unit-tests-test-block-style', 'path' ) )
		);

		// @ticket 53148
		$this->assertIsCallable( $result->render_callback );
	}

	/**
	 * @ticket 53233
	 */
	public function test_block_register_block_type_proxy_for_metadata() {
		$result = register_block_type(
			DIR_TESTDATA . '/blocks/notice'
		);

		$this->assertInstanceOf( 'WP_Block_Type', $result );
		$this->assertSame( 'tests/notice', $result->name );
	}

	/**
	 * Tests that an array value for 'editor_script' is correctly set and retrieved.
	 *
	 * As 'editor_script' is now a deprecated property, this should also set
	 * the value for the 'editor_script_handles' property.
	 *
	 * @ticket 56707
	 *
	 * @covers ::register_block_type
	 * @covers WP_Block_Type::__set
	 * @covers WP_Block_Type::__get
	 *
	 * @dataProvider data_register_block_type_accepts_editor_script_array
	 *
	 * @param array $editor_script The editor script array to register.
	 * @param array $expected      The expected registered editor script.
	 */
	public function test_register_block_type_accepts_editor_script_array( $editor_script, $expected ) {
		$settings = array( 'editor_script' => $editor_script );
		register_block_type( 'core/test-static', $settings );

		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( 'core/test-static' );
		$this->assertObjectHasAttribute( 'editor_script_handles', $block_type );
		$actual_script         = $block_type->editor_script;
		$actual_script_handles = $block_type->editor_script_handles;

		$this->assertSame(
			$expected,
			$actual_script,
			'editor_script was not set to the correct value.'
		);

		$this->assertSame(
			(array) $expected,
			$actual_script_handles,
			'editor_script_handles was not set to the correct value.'
		);
	}

	/**
	 * Data provider for test_register_block_type_accepts_editor_script_array().
	 *
	 * @return array
	 */
	public function data_register_block_type_accepts_editor_script_array() {
		return array(
			'an empty array'      => array(
				'editor_script' => array(),
				'expected'      => null,
			),
			'a single item array' => array(
				'editor_script' => array( 'hello' ),
				'expected'      => 'hello',
			),
			'a multi-item array'  => array(
				'editor_script' => array( 'hello', 'world' ),
				'expected'      => array( 'hello', 'world' ),
			),
		);
	}

	/**
	 * Tests that an array value for 'editor_script' containing invalid values
	 * correctly triggers _doing_it_wrong(), filters the value, and sets the
	 * property to the result.
	 *
	 * As 'editor_script' is now a deprecated property, this should also set
	 * the value for the 'editor_script_handles' property.
	 *
	 * @ticket 56707
	 *
	 * @covers ::register_block_type
	 * @covers WP_Block_Type::__set
	 * @covers WP_Block_Type::__get
	 *
	 * @dataProvider data_register_block_type_throws_doing_it_wrong
	 *
	 * @expectedIncorrectUsage WP_Block_Type::__set
	 *
	 * @param array $editor_script The editor script array to register.
	 * @param array $expected      The expected registered editor script.
	 */
	public function test_register_block_type_throws_doing_it_wrong( $editor_script, $expected ) {
		$settings = array( 'editor_script' => $editor_script );
		register_block_type( 'core/test-static', $settings );

		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( 'core/test-static' );
		$this->assertObjectHasAttribute( 'editor_script_handles', $block_type );
		$actual_script         = $block_type->editor_script;
		$actual_script_handles = $block_type->editor_script_handles;

		$this->assertSame(
			$expected,
			$actual_script,
			'editor_script was not set to the correct value.'
		);

		$this->assertSame(
			(array) $expected,
			$actual_script_handles,
			'editor_script_handles was not set to the correct value.'
		);
	}

	/**
	 * Data provider for test_register_block_type_throws_doing_it_wrong().
	 *
	 * @return array
	 */
	public function data_register_block_type_throws_doing_it_wrong() {
		return array(
			'a non-string array'     => array(
				'editor_script' => array( null, false, true, -1, 0, 1, -1.0, 0.0, 1.0, INF, NAN, new stdClass() ),
				'expected'      => null,
			),
			'a partial string array' => array(
				'editor_script' => array( null, false, 'script.js', true, 0, 'actions.js', 1, INF ),
				'expected'      => array( 'script.js', 'actions.js' ),
			),
			'a partial string array that results in one item with non-zero index' => array(
				'editor_script' => array( null, false, 'script.js' ),
				'expected'      => 'script.js',
			),
		);
	}

	/**
	 * @ticket 52301
	 */
	public function test_block_registers_with_metadata_i18n_support() {
		add_filter( 'locale', array( $this, 'filter_set_locale_to_polish' ) );
		load_textdomain( 'notice', WP_LANG_DIR . '/plugins/notice-pl_PL.mo' );

		$result = register_block_type_from_metadata(
			DIR_TESTDATA . '/blocks/notice'
		);

		unload_textdomain( 'notice' );
		remove_filter( 'locale', array( $this, 'filter_set_locale_to_polish' ) );

		$this->assertInstanceOf( 'WP_Block_Type', $result );
		$this->assertSame( 'tests/notice', $result->name );
		$this->assertSame( 'Powiadomienie', $result->title );
		$this->assertSame( 'Wyświetla ostrzeżenie, błąd lub powiadomienie o sukcesie…', $result->description );
		$this->assertSameSets( array( 'ostrzeżenie', 'wiadomość' ), $result->keywords );
		$this->assertSame(
			array(
				array(
					'name'      => 'default',
					'label'     => 'Domyślny',
					'isDefault' => true,
				),
				array(
					'name'  => 'other',
					'label' => 'Inny',
				),
			),
			$result->styles
		);
		$this->assertSame(
			array(
				array(
					'name'        => 'error',
					'title'       => 'Błąd',
					'description' => 'Wyświetla błąd.',
					'keywords'    => array( 'niepowodzenie' ),
				),
			),
			$result->variations
		);
	}

	/**
	 * @ticket 45109
	 */
	public function test_get_dynamic_block_names() {
		register_block_type( 'core/test-static', array() );
		register_block_type( 'core/test-dynamic', array( 'render_callback' => array( $this, 'render_stub' ) ) );

		$dynamic_block_names = get_dynamic_block_names();

		$this->assertContains( 'core/test-dynamic', $dynamic_block_names );
		$this->assertNotContains( 'core/test-static', $dynamic_block_names );
	}

	/**
	 * @ticket 45109
	 */
	public function test_has_blocks() {
		// Test with passing post ID.
		$this->assertTrue( has_blocks( self::$post_id ) );

		// Test with passing WP_Post object.
		$this->assertTrue( has_blocks( get_post( self::$post_id ) ) );

		// Test with passing content string.
		$this->assertTrue( has_blocks( get_post( self::$post_id ) ) );

		// Test default.
		$this->assertFalse( has_blocks() );
		$query = new WP_Query( array( 'post__in' => array( self::$post_id ) ) );
		$query->the_post();
		$this->assertTrue( has_blocks() );

		// Test string (without blocks).
		$content = file_get_contents( DIR_TESTDATA . '/blocks/do-blocks-expected.html' );
		$this->assertFalse( has_blocks( $content ) );
	}

	/**
	 * Tests that `has_blocks()` returns `false` with an invalid post.
	 *
	 * @ticket 55705
	 *
	 * @covers ::has_blocks
	 */
	public function test_has_blocks_with_invalid_post() {
		$a_post = (object) array(
			'ID'     => 55705,
			'filter' => 'display',
		);
		$this->assertFalse( has_blocks( $a_post ) );
	}

	/**
	 * @ticket 49615
	 */
	public function test_filter_block_registration() {
		$filter_registration = static function( $args, $name ) {
			$args['attributes'] = array( $name => array( 'type' => 'boolean' ) );
			return $args;
		};

		add_filter( 'register_block_type_args', $filter_registration, 10, 2 );
		register_block_type( 'core/test-filtered', array() );
		remove_filter( 'register_block_type_args', $filter_registration );

		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( 'core/test-filtered' );
		$this->assertSame( 'boolean', $block_type->attributes['core/test-filtered']['type'] );
	}

	/**
	 * @ticket 52138
	 */
	public function test_filter_block_registration_metadata() {
		$filter_metadata_registration = static function( $metadata ) {
			$metadata['apiVersion'] = 3;
			return $metadata;
		};

		add_filter( 'block_type_metadata', $filter_metadata_registration, 10, 2 );
		$result = register_block_type_from_metadata(
			DIR_TESTDATA . '/blocks/notice'
		);
		remove_filter( 'block_type_metadata', $filter_metadata_registration );

		$this->assertSame( 3, $result->api_version );
	}

	/**
	 * @ticket 52138
	 */
	public function test_filter_block_registration_metadata_settings() {
		$filter_metadata_registration = static function( $settings, $metadata ) {
			$settings['api_version'] = $metadata['apiVersion'] + 1;
			return $settings;
		};

		add_filter( 'block_type_metadata_settings', $filter_metadata_registration, 10, 2 );
		$result = register_block_type_from_metadata(
			DIR_TESTDATA . '/blocks/notice'
		);
		remove_filter( 'block_type_metadata_settings', $filter_metadata_registration );

		$this->assertSame( 3, $result->api_version );
	}

	/**
	 * Test case to validate `_doing_it_wrong()` when block style name attribute
	 * contains one or more spaces.
	 *
	 * @dataProvider data_register_block_style_name_contains_spaces
	 *
	 * @ticket 54296
	 *
	 * @covers ::register_block_style
	 *
	 * @expectedIncorrectUsage WP_Block_Styles_Registry::register
	 * @param array $block_styles Array of block styles to test.
	 */
	public function test_register_block_style_name_contains_spaces( array $block_styles ) {
		register_block_style( 'core/query', $block_styles );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_register_block_style_name_contains_spaces() {
		return array(
			'multiple spaces' => array(
				array(
					'name'  => 'style-class-1    style-class-2',
					'label' => 'Custom Style Label',
				),
			),
			'single space'    => array(
				array(
					'name'  => 'style-class-1 style-class-2',
					'label' => 'Custom Style Label',
				),
			),
		);
	}

	/**
	 * Test case to validate no `_doing_it_wrong()` happens when there is
	 * no empty space.
	 *
	 * @ticket 54296
	 *
	 * @covers ::register_block_style
	 */
	public function test_register_block_style_name_without_spaces() {
		$block_styles = array(
			'name'  => 'style-class-1',
			'label' => 'Custom Style Label',
		);

		$actual = register_block_style( 'core/query', $block_styles );
		$this->assertTrue( $actual );
	}
}
