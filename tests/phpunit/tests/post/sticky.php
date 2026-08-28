<?php
/**
 * Unit tests for stickiness and Post Type Support API integration.
 *
 * @package WordPress
 * @group sticky
 * @ticket 48954
 */

class Tests_Post_Stickiness extends WP_UnitTestCase {

	/**
	 * Set up test environment before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		update_option( 'sticky_posts', array() );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$custom_types = array( 'custom_type', 'my_custom_type', 'another_type' );
		foreach ( $custom_types as $type ) {
			if ( post_type_exists( $type ) ) {
				_unregister_post_type( $type );
			}
		}
		update_option( 'sticky_posts', array() );
		parent::tearDown();
	}

	/**
	 * Test that stickiness works for post types that support it.
	 */
	public function test_stickiness_for_supported_post_type() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );

		stick_post( $post_id );
		$this->assertTrue( is_sticky( $post_id ) );

		unstick_post( $post_id );
		$this->assertFalse( is_sticky( $post_id ) );
	}

	/**
	 * Test that stickiness does not work for post types that do not support it.
	 */
	public function test_stickiness_for_unsupported_post_type() {
		register_post_type(
			'custom_type',
			array(
				'label'    => 'Custom Type',
				'public'   => true,
				'supports' => array( 'title', 'editor' ),
			)
		);

		$post_id = self::factory()->post->create( array( 'post_type' => 'custom_type' ) );
		stick_post( $post_id );
		$this->assertFalse( is_sticky( $post_id ) );
	}

	/**
	 * Test that stickiness is removed when switching to a post type that does not support it.
	 */
	public function test_stickiness_removed_on_post_type_switch() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		stick_post( $post_id );
		$this->assertTrue( is_sticky( $post_id ) );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => 'page',
			)
		);
		$this->assertFalse( is_sticky( $post_id ) );
	}

	/**
	 * Test that UI functions handle post type switches correctly.
	 */
	public function test_ui_functions_after_post_type_switch() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => $admin_id,
			)
		);
		stick_post( $post_id );

		$states = get_post_states( get_post( $post_id ) );
		$this->assertArrayHasKey( 'sticky', $states );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => 'page',
			)
		);

		$states = get_post_states( get_post( $post_id ) );
		$this->assertArrayNotHasKey( 'sticky', $states );
	}

	/**
	 * Test backward compatibility - existing sticky posts behavior.
	 */
	public function test_backward_compatibility() {
		$this->assertTrue( post_type_supports( 'post', 'sticky' ) );
		$this->assertFalse( post_type_supports( 'page', 'sticky' ) );

		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		stick_post( $post_id );
		$this->assertTrue( is_sticky( $post_id ) );
	}

	/**
	 * Test that developers can add sticky support to custom post types.
	 */
	public function test_custom_post_type_sticky_support() {
		register_post_type(
			'my_custom_type',
			array(
				'label'    => 'My Custom Type',
				'public'   => true,
				'supports' => array( 'title', 'editor', 'sticky' ),
			)
		);

		$this->assertTrue( post_type_supports( 'my_custom_type', 'sticky' ) );

		$custom_post_id = self::factory()->post->create( array( 'post_type' => 'my_custom_type' ) );
		stick_post( $custom_post_id );
		$this->assertTrue( is_sticky( $custom_post_id ) );

		register_post_type(
			'another_type',
			array(
				'label'    => 'Another Type',
				'public'   => true,
				'supports' => array( 'title' ),
			)
		);

		$this->assertFalse( post_type_supports( 'another_type', 'sticky' ) );

		add_post_type_support( 'another_type', 'sticky' );
		$this->assertTrue( post_type_supports( 'another_type', 'sticky' ) );
	}

	/**
	 * Test that post_type_supports() checks work correctly for stickiness.
	 */
	public function test_post_type_supports_sticky() {
		$this->assertTrue( post_type_supports( 'post', 'sticky' ) );
		$this->assertFalse( post_type_supports( 'page', 'sticky' ) );
		$this->assertFalse( post_type_supports( 'attachment', 'sticky' ) );
	}
}
