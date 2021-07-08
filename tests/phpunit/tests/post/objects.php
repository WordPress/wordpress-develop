<?php

/**
 * @group post
 */
class Tests_Post_Objects extends WP_UnitTestCase {

	function test_get_post() {
		$id = self::factory()->post->create();

		$post = get_post( $id );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertSame( $id, $post->ID );
		$this->assertTrue( isset( $post->ancestors ) );
		$this->assertSame( array(), $post->ancestors );

		// Unset and then verify that the magic method fills the property again.
		unset( $post->ancestors );
		$this->assertSame( array(), $post->ancestors );

		// Magic get should make meta accessible as properties.
		add_post_meta( $id, 'test', 'test' );
		$this->assertSame( 'test', get_post_meta( $id, 'test', true ) );
		$this->assertSame( 'test', $post->test );

		// Make sure meta does not eclipse true properties.
		add_post_meta( $id, 'post_type', 'dummy' );
		$this->assertSame( 'dummy', get_post_meta( $id, 'post_type', true ) );
		$this->assertSame( 'post', $post->post_type );

		// Excercise the output argument.
		$post = get_post( $id, ARRAY_A );
		$this->assertIsArray( $post );
		$this->assertSame( 'post', $post['post_type'] );

		$post = get_post( $id, ARRAY_N );
		$this->assertIsArray( $post );
		$this->assertFalse( isset( $post['post_type'] ) );
		$this->assertTrue( in_array( 'post', $post, true ) );

		$post = get_post( $id );
		$post = get_post( $post, ARRAY_A );
		$this->assertIsArray( $post );
		$this->assertSame( 'post', $post['post_type'] );
		$this->assertSame( $id, $post['ID'] );

		// Should default to OBJECT when given invalid output argument.
		$post = get_post( $id, 'invalid-output-value' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertSame( $id, $post->ID );

		// Make sure stdClass in $GLOBALS['post'] is handled.
		$post_std = $post->to_array();
		$this->assertIsArray( $post_std );
		$post_std        = (object) $post_std;
		$GLOBALS['post'] = $post_std;
		$post            = get_post( null );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertSame( $id, $post->ID );
		unset( $GLOBALS['post'] );

		// If no global post and passing empty value, expect null.
		$this->assertNull( get_post( null ) );
		$this->assertNull( get_post( 0 ) );
		$this->assertNull( get_post( '' ) );
		$this->assertNull( get_post( false ) );
	}

	function test_get_post_ancestors() {
		$parent_id     = self::factory()->post->create();
		$child_id      = self::factory()->post->create();
		$grandchild_id = self::factory()->post->create();
		$updated       = wp_update_post(
			array(
				'ID'          => $child_id,
				'post_parent' => $parent_id,
			)
		);
		$this->assertSame( $updated, $child_id );
		$updated = wp_update_post(
			array(
				'ID'          => $grandchild_id,
				'post_parent' => $child_id,
			)
		);
		$this->assertSame( $updated, $grandchild_id );

		$this->assertSame( array( $parent_id ), get_post( $child_id )->ancestors );
		$this->assertSame( array( $parent_id ), get_post_ancestors( $child_id ) );
		$this->assertSame( array( $parent_id ), get_post_ancestors( get_post( $child_id ) ) );

		$this->assertSame( array( $child_id, $parent_id ), get_post( $grandchild_id )->ancestors );
		$this->assertSame( array( $child_id, $parent_id ), get_post_ancestors( $grandchild_id ) );
		$this->assertSame( array( $child_id, $parent_id ), get_post_ancestors( get_post( $grandchild_id ) ) );

		$this->assertSame( array(), get_post( $parent_id )->ancestors );
		$this->assertSame( array(), get_post_ancestors( $parent_id ) );
		$this->assertSame( array(), get_post_ancestors( get_post( $parent_id ) ) );
	}

	/**
	 * @ticket 22882
	 */
	function test_get_post_ancestors_with_falsey_values() {
		foreach ( array( null, 0, false, '0', '' ) as $post_id ) {
			$this->assertIsArray( get_post_ancestors( $post_id ) );
			$this->assertSame( array(), get_post_ancestors( $post_id ) );
		}
	}

	function test_get_post_category_property() {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$this->assertIsArray( $post->post_category );
		$this->assertCount( 1, $post->post_category );
		$this->assertEquals( get_option( 'default_category' ), $post->post_category[0] );
		$term1 = wp_insert_term( 'Foo', 'category' );
		$term2 = wp_insert_term( 'Bar', 'category' );
		$term3 = wp_insert_term( 'Baz', 'category' );
		wp_set_post_categories( $post_id, array( $term1['term_id'], $term2['term_id'], $term3['term_id'] ) );
		$this->assertCount( 3, $post->post_category );
		$this->assertSame( array( $term2['term_id'], $term3['term_id'], $term1['term_id'] ), $post->post_category );

		$post = get_post( $post_id, ARRAY_A );
		$this->assertCount( 3, $post['post_category'] );
		$this->assertSame( array( $term2['term_id'], $term3['term_id'], $term1['term_id'] ), $post['post_category'] );
	}

	function test_get_tags_input_property() {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$this->assertIsArray( $post->tags_input );
		$this->assertEmpty( $post->tags_input );
		wp_set_post_tags( $post_id, 'Foo, Bar, Baz' );
		$this->assertIsArray( $post->tags_input );
		$this->assertCount( 3, $post->tags_input );
		$this->assertSame( array( 'Bar', 'Baz', 'Foo' ), $post->tags_input );

		$post = get_post( $post_id, ARRAY_A );
		$this->assertIsArray( $post['tags_input'] );
		$this->assertCount( 3, $post['tags_input'] );
		$this->assertSame( array( 'Bar', 'Baz', 'Foo' ), $post['tags_input'] );
	}

	function test_get_page_template_property() {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$this->assertIsString( $post->page_template );
		$template = get_post_meta( $post->ID, '_wp_page_template', true );
		$this->assertSame( $template, $post->page_template );
		update_post_meta( $post_id, '_wp_page_template', 'foo.php' );
		$template = get_post_meta( $post->ID, '_wp_page_template', true );
		$this->assertSame( 'foo.php', $template );
		$this->assertSame( $template, $post->page_template );
	}

	function test_get_post_filter() {
		$post = get_post(
			self::factory()->post->create(
				array(
					'post_title' => "Mary's home",
				)
			)
		);

		$this->assertSame( 'raw', $post->filter );
		$this->assertIsInt( $post->post_parent );

		$display_post = get_post( $post, OBJECT, 'js' );
		$this->assertSame( 'js', $display_post->filter );
		$this->assertSame( esc_js( "Mary's home" ), $display_post->post_title );

		// Pass a js filtered WP_Post to get_post() with the filter set to raw.
		// The post should be fetched from cache instead of using the passed object.
		$raw_post = get_post( $display_post, OBJECT, 'raw' );
		$this->assertSame( 'raw', $raw_post->filter );
		$this->assertNotEquals( esc_js( "Mary's home" ), $raw_post->post_title );

		$raw_post->filter( 'js' );
		$this->assertSame( 'js', $post->filter );
		$this->assertSame( esc_js( "Mary's home" ), $raw_post->post_title );
	}

	/**
	 * @ticket 53235
	 */
	public function test_numeric_properties_should_be_cast_to_ints() {
		$post_id  = self::factory()->post->create();
		$contexts = array( 'raw', 'edit', 'db', 'display', 'attribute', 'js' );

		foreach ( $contexts as $context ) {
			$post = get_post( $post_id, OBJECT, $context );

			$this->assertIsInt( $post->ID );
			$this->assertIsInt( $post->post_parent );
			$this->assertIsInt( $post->menu_order );
		}
	}

	function test_get_post_identity() {
		$post = get_post( self::factory()->post->create() );

		$post->foo = 'bar';

		$this->assertSame( 'bar', get_post( $post )->foo );
		$this->assertSame( 'bar', get_post( $post, OBJECT, 'display' )->foo );
	}

	function test_get_post_array() {
		$id = self::factory()->post->create();

		$post = get_post( $id, ARRAY_A );

		$this->assertSame( $id, $post['ID'] );
		$this->assertIsArray( $post['ancestors'] );
		$this->assertSame( 'raw', $post['filter'] );
	}

	/**
	 * @ticket 22223
	 */
	function test_get_post_cache() {
		global $wpdb;

		$id = self::factory()->post->create();
		wp_cache_delete( $id, 'posts' );

		// get_post( stdClass ) should not prime the cache.
		$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d LIMIT 1", $id ) );
		$post = get_post( $post );
		$this->assertEmpty( wp_cache_get( $id, 'posts' ) );

		// get_post( WP_Post ) should not prime the cache.
		get_post( $post );
		$this->assertEmpty( wp_cache_get( $id, 'posts' ) );

		// get_post( ID ) should prime the cache.
		get_post( $post->ID );
		$this->assertNotEmpty( wp_cache_get( $id, 'posts' ) );
	}
}
