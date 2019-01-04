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
}
