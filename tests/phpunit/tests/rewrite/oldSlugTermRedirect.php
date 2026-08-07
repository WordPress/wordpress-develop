<?php

/**
 * @group rewrite
 * @ticket 15953
 * @covers wp_old_slug_term_redirect
 * @since x.x.x
 */
class Tests_Rewrite_OldSlugTermRedirect extends WP_UnitTestCase {
	protected $old_slug_redirect_url;

	public function set_up() {
		parent::set_up();

		add_filter( 'old_slug_redirect_url', array( $this, 'filter_old_slug_redirect_url' ) );

		$this->set_permalink_structure( '/%postname%/' );

		update_option( 'category_base', 'category' );
		update_option( 'tag_base', 'tag' );

		global $wp_rewrite;
		$category_base = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
		$tag_base      = get_option( 'tag_base' ) ? get_option( 'tag_base' ) : 'tag';
		$wp_rewrite->add_permastruct(
			'category',
			$wp_rewrite->front . $category_base . '/%' . 'category' . '%',
			array( 'ep_mask' => EP_CATEGORIES )
		);
		$wp_rewrite->add_permastruct(
			'post_tag',
			$wp_rewrite->front . $tag_base . '/%' . 'post_tag' . '%',
			array( 'ep_mask' => EP_TAGS )
		);

		flush_rewrite_rules( true );
	}

	public function tear_down() {
		$this->old_slug_redirect_url = null;

		parent::tear_down();
	}

	public function filter_old_slug_redirect_url( $url ) {
		$this->old_slug_redirect_url = $url;
		return false;
	}

	/**
	 * Tests that changing a category slug redirects the old URL to the new one.
	 */
	public function test_old_slug_term_redirect_category() {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Test Category',
				'slug'     => 'old-cat',
			)
		);

		$old_link = get_term_link( $term_id, 'category' );

		wp_update_term(
			$term_id,
			'category',
			array(
				'slug' => 'new-cat',
			)
		);

		$old_slugs = get_term_meta( $term_id, '_wp_old_slug', false );
		$this->assertContains( 'old-cat', $old_slugs );

		$new_link = get_term_link( $term_id, 'category' );

		$this->go_to( home_url( '/?category_name=old-cat' ) );

		$this->assertTrue( is_404(), 'Should be a 404' );
		$cat_name = get_query_var( 'category_name' );
		$this->assertSame( 'old-cat', $cat_name );

		$found = _find_term_by_old_slug( 'old-cat', 'category' );
		$this->assertSame( $term_id, $found, 'Should find term by old slug' );

		wp_old_slug_term_redirect();
		$this->assertSame( $new_link, $this->old_slug_redirect_url );
	}

	/**
	 * Tests that changing a tag slug redirects the old URL to the new one.
	 */
	public function test_old_slug_term_redirect_tag() {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'Test Tag',
				'slug'     => 'old-tag',
			)
		);

		$old_link = get_term_link( $term_id, 'post_tag' );

		wp_update_term(
			$term_id,
			'post_tag',
			array(
				'slug' => 'new-tag',
			)
		);

		$old_slugs = get_term_meta( $term_id, '_wp_old_slug', false );
		$this->assertContains( 'old-tag', $old_slugs );

		$new_link = get_term_link( $term_id, 'post_tag' );

		$this->go_to( home_url( '/?tag=old-tag' ) );

		$this->assertTrue( is_404(), 'Should be a 404' );
		$tag = get_query_var( 'tag' );
		$this->assertSame( 'old-tag', $tag );

		$found = _find_term_by_old_slug( 'old-tag', 'post_tag' );
		$this->assertSame( $term_id, $found, 'Should find term by old slug' );

		wp_old_slug_term_redirect();
		$this->assertSame( $new_link, $this->old_slug_redirect_url );
	}

	/**
	 * Tests that changing a custom taxonomy term slug redirects the old URL to the new one.
	 */
	public function test_old_slug_term_redirect_custom_taxonomy() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'public'       => true,
				'hierarchical' => false,
				'rewrite'      => array( 'slug' => 'wptests-tax' ),
			)
		);

		flush_rewrite_rules( true );

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'Old Term',
				'slug'     => 'old-term',
			)
		);

		$old_link = get_term_link( $term_id, 'wptests_tax' );

		wp_update_term(
			$term_id,
			'wptests_tax',
			array(
				'slug' => 'new-term',
			)
		);

		$new_link = get_term_link( $term_id, 'wptests_tax' );

		$this->go_to( $old_link );
		wp_old_slug_term_redirect();
		$this->assertSame( $new_link, $this->old_slug_redirect_url );

		_unregister_taxonomy( 'wptests_tax' );
	}

	/**
	 * Tests that changing a hierarchical taxonomy term slug redirects the old URL to the new one.
	 */
	public function test_old_slug_term_redirect_hierarchical_taxonomy() {
		register_taxonomy(
			'wptests_hier_tax',
			'post',
			array(
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'         => 'wptests-hier-tax',
					'hierarchical' => true,
				),
			)
		);

		flush_rewrite_rules( true );

		$parent_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_hier_tax',
				'name'     => 'Parent Term',
				'slug'     => 'parent',
			)
		);

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_hier_tax',
				'name'     => 'Child Term',
				'slug'     => 'child',
				'parent'   => $parent_id,
			)
		);

		$old_link = get_term_link( $term_id, 'wptests_hier_tax' );

		wp_update_term(
			$term_id,
			'wptests_hier_tax',
			array(
				'slug' => 'new-child',
			)
		);

		$new_link = get_term_link( $term_id, 'wptests_hier_tax' );

		$this->go_to( $old_link );
		wp_old_slug_term_redirect();
		$this->assertSame( $new_link, $this->old_slug_redirect_url );

		_unregister_taxonomy( 'wptests_hier_tax' );
	}

	/**
	 * Tests that no redirect occurs when the old slug is reused by another term.
	 */
	public function test_old_slug_doesnt_redirect_when_term_reused() {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'First Category',
				'slug'     => 'first-category',
			)
		);

		$old_link = get_term_link( $term_id, 'category' );

		wp_update_term(
			$term_id,
			'category',
			array(
				'slug' => 'renamed-category',
			)
		);

		$new_term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'First Category',
				'slug'     => 'first-category',
			)
		);

		$this->go_to( $old_link );
		wp_old_slug_term_redirect();
		$this->assertNull( $this->old_slug_redirect_url );
	}

	/**
	 * Tests that old slugs are stored in term meta and accumulate correctly.
	 */
	public function test_old_slug_stored_in_term_meta() {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Test Category',
				'slug'     => 'slug-1',
			)
		);

		// Change slug: slug-1 -> slug-2.
		wp_update_term(
			$term_id,
			'category',
			array(
				'slug' => 'slug-2',
			)
		);

		$old_slugs = get_term_meta( $term_id, '_wp_old_slug', false );
		$this->assertContains( 'slug-1', $old_slugs );

		// Change slug: slug-2 -> slug-3.
		wp_update_term(
			$term_id,
			'category',
			array(
				'slug' => 'slug-3',
			)
		);

		$old_slugs = get_term_meta( $term_id, '_wp_old_slug', false );
		$this->assertContains( 'slug-1', $old_slugs );
		$this->assertContains( 'slug-2', $old_slugs );

		// Change slug: slug-3 -> slug-1 (reusing slug-1).
		wp_update_term(
			$term_id,
			'category',
			array(
				'slug' => 'slug-1',
			)
		);

		// slug-1 is now current, so should be removed from old slugs.
		$old_slugs = get_term_meta( $term_id, '_wp_old_slug', false );
		$this->assertNotContains( 'slug-1', $old_slugs );
		$this->assertContains( 'slug-2', $old_slugs );
		$this->assertContains( 'slug-3', $old_slugs );
	}
}
