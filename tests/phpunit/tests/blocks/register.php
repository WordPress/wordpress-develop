<?php
/**
 * Block registry tests.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Tests for register_block_type(), unregister_block_type(), get_dynamic_block_names()
 *
 * @since 5.0.0
 *
 * @group blocks
 */
class WP_Test_Block_Register extends WP_UnitTestCase {

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
	public static function wpSetUpBeforeClass( $factory ) {
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
	function render_stub() {}

	/**
	 * Tear down after each test.
	 *
	 * @since 5.0.0
	 */
	function tearDown() {
		parent::tearDown();

		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array( 'test-static', 'test-dynamic' ) as $block_name ) {
			$block_name = 'core/' . $block_name;

			if ( $registry->is_registered( $block_name ) ) {
				$registry->unregister( $block_name );
			}
		}
	}

	/**
	 * @ticket 45109
	 */
	function test_register_affects_main_registry() {
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
	function test_unregister_affects_main_registry() {
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
	function test_does_not_remove_block_asset_path_prefix() {
		$result = remove_block_asset_path_prefix( 'script-handle' );

		$this->assertSame( 'script-handle', $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_removes_block_asset_path_prefix() {
		$result = remove_block_asset_path_prefix( 'file:./block.js' );

		$this->assertSame( './block.js', $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_generate_block_asset_handle() {
		$block_name = 'unit-tests/my-block';

		$this->assertSame(
			'unit-tests-my-block-editor-script',
			generate_block_asset_handle( $block_name, 'editorScript' )
		);
		$this->assertSame(
			'unit-tests-my-block-script',
			generate_block_asset_handle( $block_name, 'script' )
		);
		$this->assertSame(
			'unit-tests-my-block-editor-style',
			generate_block_asset_handle( $block_name, 'editorStyle' )
		);
		$this->assertSame(
			'unit-tests-my-block-style',
			generate_block_asset_handle( $block_name, 'style' )
		);
	}

	/**
	 * @ticket 50263
	 */
	function test_field_not_found_register_block_script_handle() {
		$result = register_block_script_handle( array(), 'script' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_empty_value_register_block_script_handle() {
		$metadata = array( 'script' => '' );
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertFalse( $result );
	}

	/**
	 * @expectedIncorrectUsage register_block_script_handle
	 * @ticket 50263
	 */
	function test_missing_asset_file_register_block_script_handle() {
		$metadata = array(
			'file'   => __FILE__,
			'name'   => 'unit-tests/test-block',
			'script' => 'file:./fixtures/missing-asset.js',
		);
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_handle_passed_register_block_script_handle() {
		$metadata = array(
			'editorScript' => 'test-script-handle',
		);
		$result   = register_block_script_handle( $metadata, 'editorScript' );

		$this->assertSame( 'test-script-handle', $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_success_register_block_script_handle() {
		$metadata = array(
			'file'   => __FILE__,
			'name'   => 'unit-tests/test-block',
			'script' => 'file:./fixtures/block.js',
		);
		$result   = register_block_script_handle( $metadata, 'script' );

		$this->assertSame( 'unit-tests-test-block-script', $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_field_not_found_register_block_style_handle() {
		$result = register_block_style_handle( array(), 'style' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_empty_value_found_register_block_style_handle() {
		$metadata = array( 'style' => '' );
		$result   = register_block_style_handle( $metadata, 'style' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_handle_passed_register_block_style_handle() {
		$metadata = array(
			'style' => 'test-style-handle',
		);
		$result   = register_block_style_handle( $metadata, 'style' );

		$this->assertSame( 'test-style-handle', $result );
	}

	/**
	 * @ticket 50263
	 */
	function test_success_register_block_style_handle() {
		$metadata = array(
			'file'  => __FILE__,
			'name'  => 'unit-tests/test-block',
			'style' => 'file:./fixtures/block.css',
		);
		$result   = register_block_style_handle( $metadata, 'style' );

		$this->assertSame( 'unit-tests-test-block-style', $result );
	}

	/**
	 * Tests that the function returns false when the `block.json` is not found
	 * in the WordPress core.
	 *
	 * @ticket 50263
	 */
	function test_metadata_not_found_in_wordpress_core() {
		$result = register_block_type_from_metadata( 'unknown' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that the function returns false when the `block.json` is not found
	 * in the current directory.
	 *
	 * @ticket 50263
	 */
	function test_metadata_not_found_in_the_current_directory() {
		$result = register_block_type_from_metadata( __DIR__ );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that the function returns the registered block when the `block.json`
	 * is found in the fixtures directory.
	 *
	 * @ticket 50263
	 */
	function test_block_registers_with_metadata_fixture() {
		$result = register_block_type_from_metadata(
			__DIR__ . '/fixtures'
		);

		$this->assertInstanceOf( 'WP_Block_Type', $result );
		$this->assertSame( 'my-plugin/notice', $result->name );
		$this->assertSame( 'Notice', $result->title );
		$this->assertSame( 'common', $result->category );
		$this->assertSameSets( array( 'core/group' ), $result->parent );
		$this->assertSame( 'star', $result->icon );
		$this->assertSame( 'Shows warning, error or success notices…', $result->description );
		$this->assertSameSets( array( 'alert', 'message' ), $result->keywords );
		$this->assertSame(
			array(
				'message' => array(
					'type'     => 'string',
					'source'   => 'html',
					'selector' => '.message',
				),
			),
			$result->attributes
		);
		$this->assertSame(
			array(
				'my-plugin/message' => 'message',
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
				'attributes' => array(
					'message' => 'This is a notice!',
				),
			),
			$result->example
		);
		$this->assertSame( 'my-plugin-notice-editor-script', $result->editor_script );
		$this->assertSame( 'my-plugin-notice-script', $result->script );
		$this->assertSame( 'my-plugin-notice-editor-style', $result->editor_style );
		$this->assertSame( 'my-plugin-notice-style', $result->style );
	}

	/**
	 * @ticket 45109
	 */
	function test_get_dynamic_block_names() {
		register_block_type( 'core/test-static', array() );
		register_block_type( 'core/test-dynamic', array( 'render_callback' => array( $this, 'render_stub' ) ) );

		$dynamic_block_names = get_dynamic_block_names();

		$this->assertContains( 'core/test-dynamic', $dynamic_block_names );
		$this->assertNotContains( 'core/test-static', $dynamic_block_names );
	}

	/**
	 * @ticket 45109
	 */
	function test_has_blocks() {
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
	 * @ticket 49615
	 */
	public function test_filter_block_registration() {
		$filter_registration = function( $args, $name ) {
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
}
