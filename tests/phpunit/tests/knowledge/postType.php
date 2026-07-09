<?php
/**
 * Tests for the wp_knowledge post type registration.
 *
 * @package WordPress
 * @subpackage Knowledge
 *
 * @group knowledge
 * @group post
 */
class Tests_Knowledge_PostType extends WP_UnitTestCase {

	/**
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'wp_knowledge' ) );
	}

	/**
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_post_type_is_builtin_and_private(): void {
		$post_type = get_post_type_object( 'wp_knowledge' );

		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$this->assertTrue( $post_type->_builtin, '_builtin should be true' );
		$this->assertFalse( $post_type->public, 'public should be false' );
		$this->assertFalse( $post_type->show_ui, 'show_ui should be false' );
		$this->assertFalse( $post_type->hierarchical, 'hierarchical should be false' );
	}

	/**
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_post_type_rest_configuration(): void {
		$post_type = get_post_type_object( 'wp_knowledge' );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type );

		$this->assertTrue( $post_type->show_in_rest, 'show_in_rest should be true' );
		$this->assertSame( 'knowledge', $post_type->rest_base );
		$this->assertSame( 'WP_REST_Knowledge_Controller', $post_type->rest_controller_class );
	}

	/**
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_post_type_supports(): void {
		$this->assertTrue( post_type_supports( 'wp_knowledge', 'title' ) );
		$this->assertTrue( post_type_supports( 'wp_knowledge', 'editor' ) );
		$this->assertTrue( post_type_supports( 'wp_knowledge', 'excerpt' ) );
		$this->assertTrue( post_type_supports( 'wp_knowledge', 'author' ) );
		$this->assertTrue( post_type_supports( 'wp_knowledge', 'revisions' ) );
	}

	/**
	 * Revisions are supported and served by the default revisions controller.
	 *
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_post_type_supports_revisions_with_default_controller(): void {
		$this->assertTrue( post_type_supports( 'wp_knowledge', 'revisions' ) );

		$post_type = get_post_type_object( 'wp_knowledge' );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$controller = $post_type->get_revisions_rest_controller();
		$this->assertInstanceOf( 'WP_REST_Revisions_Controller', $controller );
	}

	/**
	 * Autosave support is removed, so no autosave endpoints are registered.
	 *
	 * Knowledge is headless storage with no editor session; `editor` support
	 * implies `autosave`, which is explicitly removed at registration.
	 *
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_post_type_does_not_support_autosaves(): void {
		$this->assertFalse( post_type_supports( 'wp_knowledge', 'autosave' ) );
		$post_type = get_post_type_object( 'wp_knowledge' );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$this->assertNull( $post_type->get_autosave_rest_controller() );
	}

	/**
	 * The `read` capability is remapped so that the base `read` cap (held by
	 * subscribers) does not grant access to the post type.
	 *
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_read_capability_is_remapped(): void {
		$post_type = get_post_type_object( 'wp_knowledge' );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type );

		$this->assertSame( 'read_knowledge_items', $post_type->cap->read );
	}

	/**
	 * The per-post meta capabilities (derived from the singular `knowledge_item`
	 * base) must not collide with the primitive capabilities (derived from the
	 * plural `knowledge_items` base). A collision would make checks such as
	 * `current_user_can( 'edit_knowledge_items' )` ambiguous.
	 *
	 * @ticket 65476
	 * @covers ::create_initial_post_types
	 */
	public function test_post_type_meta_caps_do_not_collide_with_primitives(): void {
		$post_type = get_post_type_object( 'wp_knowledge' );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$cap = $post_type->cap;

		// Meta capabilities are derived from the singular `knowledge_item` base.
		$this->assertSame( 'edit_knowledge_item', $cap->edit_post );
		$this->assertSame( 'read_knowledge_item', $cap->read_post );
		$this->assertSame( 'delete_knowledge_item', $cap->delete_post );

		// Primitive capabilities are derived from the plural `knowledge_items` base.
		$this->assertSame( 'edit_knowledge_items', $cap->edit_posts );
		$this->assertSame( 'edit_others_knowledge_items', $cap->edit_others_posts );
		$this->assertSame( 'publish_knowledge_items', $cap->publish_posts );
		$this->assertSame( 'read_private_knowledge_items', $cap->read_private_posts );

		// The meta and primitive forms must be distinct.
		$this->assertNotSame( $cap->edit_post, $cap->edit_posts );
		$this->assertNotSame( $cap->read_post, $cap->read_private_posts );
		$this->assertNotSame( $cap->delete_post, $cap->delete_posts );
	}

	/**
	 * @ticket 65476
	 * @covers ::create_initial_taxonomies
	 */
	public function test_knowledge_type_taxonomy_is_attached(): void {
		$this->assertTrue( taxonomy_exists( 'wp_knowledge_type' ) );
		$this->assertContains( 'wp_knowledge_type', get_object_taxonomies( 'wp_knowledge' ) );

		$taxonomy = get_taxonomy( 'wp_knowledge_type' );
		$this->assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		$this->assertTrue( $taxonomy->hierarchical, 'taxonomy should be hierarchical' );
		$this->assertFalse( $taxonomy->public, 'taxonomy should not be public' );
		$this->assertTrue( $taxonomy->show_in_rest, 'taxonomy should be shown in REST' );
	}
}
