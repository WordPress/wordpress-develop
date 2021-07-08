<?php
/**
 * Reusable block rendering tests.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Tests for reusable block rendering.
 *
 * @since 5.0.0
 *
 * @group blocks
 */
class WP_Test_Render_Reusable_Blocks extends WP_UnitTestCase {
	/**
	 * Fake user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Fake block ID.
	 *
	 * @var int
	 */
	protected static $block_id;

	/**
	 * Fake post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Test Post',
				'post_content' => '<p>Hello world!</p>',
			)
		);

		self::$block_id = $factory->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Test Block',
				'post_content' => '<!-- wp:core/paragraph --><p>Hello world!</p><!-- /wp:core/paragraph -->',
			)
		);
	}

	/**
	 * Delete fake data after tests run.
	 *
	 * @since 5.0.0
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$block_id, true );
		wp_delete_post( self::$post_id, true );
		self::delete_user( self::$user_id );
	}

	public function test_render() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/block' );
		$output     = $block_type->render( array( 'ref' => self::$block_id ) );
		$this->assertSame( '<p>Hello world!</p>', $output );
	}

	/**
	 * Make sure that a reusable block can be rendered twice in a row.
	 *
	 * @ticket 52364
	 */
	public function test_render_subsequent() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/block' );
		$output     = $block_type->render( array( 'ref' => self::$block_id ) );
		$output    .= $block_type->render( array( 'ref' => self::$block_id ) );
		$this->assertSame( '<p>Hello world!</p><p>Hello world!</p>', $output );
	}

	public function test_ref_empty() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/block' );
		$output     = $block_type->render( array() );
		$this->assertSame( '', $output );
	}

	public function test_ref_wrong_post_type() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/block' );
		$output     = $block_type->render( array( 'ref' => self::$post_id ) );
		$this->assertSame( '', $output );
	}
}
