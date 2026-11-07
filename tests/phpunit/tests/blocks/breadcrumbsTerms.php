<?php
/**
 * Tests for the breadcrumbs of the terms of a post of the Breadcrumbs block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::block_core_breadcrumbs_get_terms_breadcrumbs
 */
class Tests_Blocks_BreadcrumbsTerms extends WP_UnitTestCase {

	/**
	 * Creates a post without terms.
	 *
	 * @param array $args Optional. The arguments of the post. Default none.
	 * @return int The ID of the post.
	 */
	private function create_post( $args = array() ) {
		$post_id = self::factory()->post->create( $args );

		// Posts get the default category, unless they have categories.
		if ( empty( $args['post_category'] ) ) {
			wp_set_object_terms( $post_id, array(), 'category' );
		}

		return $post_id;
	}

	/**
	 * Returns the labels of the items.
	 *
	 * @param array $items The items.
	 * @return string[] The labels.
	 */
	private function get_labels( $items ) {
		return array_column( $items, 'label' );
	}

	/**
	 * Tests that a post type without public taxonomies has no breadcrumbs.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_no_breadcrumbs_for_a_post_type_without_public_taxonomies() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( array(), block_core_breadcrumbs_get_terms_breadcrumbs( $page_id, 'page' ) );
	}

	/**
	 * Tests that a post without terms has no breadcrumbs.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_no_breadcrumbs_for_a_post_without_terms() {
		$post_id = $this->create_post();

		$this->assertSame( array(), block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) );
	}

	/**
	 * Tests the breadcrumbs of the category of a post.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_the_category_of_a_post() {
		$category_id = self::factory()->category->create( array( 'name' => 'News' ) );
		$post_id     = $this->create_post( array( 'post_category' => array( $category_id ) ) );

		$this->assertSame(
			array(
				array(
					'label' => 'News',
					'url'   => get_term_link( $category_id, 'category' ),
				),
			),
			block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' )
		);
	}

	/**
	 * Tests that the ancestors of the category come first.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_ancestors_of_the_category_first() {
		$grandparent_id = self::factory()->category->create( array( 'name' => 'Grandparent' ) );
		$parent_id      = self::factory()->category->create(
			array(
				'name'   => 'Parent',
				'parent' => $grandparent_id,
			)
		);
		$child_id       = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'parent' => $parent_id,
			)
		);
		$post_id        = $this->create_post( array( 'post_category' => array( $child_id ) ) );

		$items = block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' );

		$this->assertSame( array( 'Grandparent', 'Parent', 'Child' ), $this->get_labels( $items ) );
		$this->assertSame( get_term_link( $grandparent_id, 'category' ), $items[0]['url'] );
		$this->assertSame( get_term_link( $child_id, 'category' ), $items[2]['url'] );
	}

	/**
	 * Tests the first taxonomy that has terms, when the category has none.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_first_taxonomy_with_terms() {
		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array( 'a-tag' ), 'post_tag' );

		$items = block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' );

		$this->assertSame( array( 'a-tag' ), $this->get_labels( $items ) );
	}

	/**
	 * Tests that the first taxonomy wins when the post has terms in several.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_first_taxonomy_when_there_are_terms_in_several() {
		$category_id = self::factory()->category->create( array( 'name' => 'News' ) );
		$post_id     = $this->create_post( array( 'post_category' => array( $category_id ) ) );
		wp_set_object_terms( $post_id, array( 'a-tag' ), 'post_tag' );

		$this->assertSame( array( 'News' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Tests the taxonomy that the filter asks for.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_taxonomy_of_the_filter() {
		$category_id = self::factory()->category->create( array( 'name' => 'News' ) );
		$post_id     = $this->create_post( array( 'post_category' => array( $category_id ) ) );
		wp_set_object_terms( $post_id, array( 'a-tag' ), 'post_tag' );

		$this->set_settings( array( 'taxonomy' => 'post_tag' ) );

		$this->assertSame( array( 'a-tag' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Tests the fallback when the taxonomy that the filter asks for has no terms or does not exist.
	 *
	 * @ticket 66590
	 *
	 * @dataProvider data_unusable_taxonomies
	 *
	 * @param string $taxonomy The taxonomy of the settings.
	 */
	public function test_should_fall_back_to_the_first_taxonomy_with_terms_when_the_taxonomy_of_the_filter_is_not_usable( $taxonomy ) {
		$category_id = self::factory()->category->create( array( 'name' => 'News' ) );
		$post_id     = $this->create_post( array( 'post_category' => array( $category_id ) ) );

		$this->set_settings( array( 'taxonomy' => $taxonomy ) );

		$this->assertSame( array( 'News' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unusable_taxonomies() {
		return array(
			'taxonomy without terms for the post' => array( 'post_tag' ),
			'taxonomy that does not exist'        => array( 'missing-taxonomy' ),
			'taxonomy that is not listed'         => array( 'post_format' ),
		);
	}

	/**
	 * Tests the first term of a post with several terms.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_first_term_when_there_are_several() {
		$first_id  = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$second_id = self::factory()->category->create( array( 'name' => 'Beta' ) );
		$post_id   = $this->create_post( array( 'post_category' => array( $second_id, $first_id ) ) );

		$this->assertSame( array( 'Alpha' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Tests the term that the filter asks for.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_term_of_the_filter() {
		$first_id  = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$second_id = self::factory()->category->create(
			array(
				'name' => 'Beta',
				'slug' => 'the-beta',
			)
		);
		$post_id   = $this->create_post( array( 'post_category' => array( $first_id, $second_id ) ) );

		$this->set_settings( array( 'term' => 'the-beta' ) );

		$this->assertSame( array( 'Beta' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Tests that the term of the filter is skipped when the post does not have it.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_first_term_when_the_term_of_the_filter_is_not_assigned() {
		$first_id  = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$second_id = self::factory()->category->create( array( 'name' => 'Beta' ) );
		self::factory()->category->create(
			array(
				'name' => 'Gamma',
				'slug' => 'the-gamma',
			)
		);
		$post_id = $this->create_post( array( 'post_category' => array( $first_id, $second_id ) ) );

		$this->set_settings( array( 'term' => 'the-gamma' ) );

		$this->assertSame( array( 'Alpha' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Tests that the term of the filter does not matter for a post with a single term.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_only_term_whatever_the_term_of_the_filter() {
		$only_id = self::factory()->category->create( array( 'name' => 'Only' ) );
		self::factory()->category->create(
			array(
				'name' => 'Other',
				'slug' => 'other-term',
			)
		);
		$post_id = $this->create_post( array( 'post_category' => array( $only_id ) ) );

		$this->set_settings( array( 'term' => 'other-term' ) );

		$this->assertSame( array( 'Only' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Tests the ancestors of the term that the filter asks for.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_ancestors_of_the_term_of_the_filter() {
		$first_id  = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$parent_id = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$child_id  = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'slug'   => 'the-child',
				'parent' => $parent_id,
			)
		);
		$post_id   = $this->create_post( array( 'post_category' => array( $first_id, $child_id ) ) );

		$this->set_settings( array( 'term' => 'the-child' ) );

		$this->assertSame( array( 'Parent', 'Child' ), $this->get_labels( block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' ) ) );
	}

	/**
	 * Tests the arguments of the filter.
	 *
	 * @ticket 66590
	 */
	public function test_should_pass_the_post_type_and_the_post_to_the_filter() {
		$post_id = $this->create_post();
		$args    = null;

		add_filter(
			'block_core_breadcrumbs_post_type_settings',
			static function ( $settings, $post_type, $filtered_post_id ) use ( &$args ) {
				$args = array( $settings, $post_type, $filtered_post_id );

				return $settings;
			},
			10,
			3
		);

		block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' );

		$this->assertSame( array( array(), 'post', $post_id ), $args );
	}

	/**
	 * Tests that the filter does not run for a post type without public taxonomies.
	 *
	 * @ticket 66590
	 */
	public function test_should_not_run_the_filter_for_a_post_type_without_public_taxonomies() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$ran     = false;

		add_filter(
			'block_core_breadcrumbs_post_type_settings',
			static function ( $settings ) use ( &$ran ) {
				$ran = true;

				return $settings;
			}
		);

		block_core_breadcrumbs_get_terms_breadcrumbs( $page_id, 'page' );

		$this->assertFalse( $ran );
	}

	/**
	 * Tests that a taxonomy that is not shown in REST, or not publicly queryable, is skipped.
	 *
	 * @ticket 66590
	 *
	 * @dataProvider data_taxonomies_that_are_skipped
	 *
	 * @param array $args The arguments of the taxonomy.
	 */
	public function test_should_skip_taxonomies_that_are_not_public( $args ) {
		register_taxonomy( 'genre', 'post', $args );

		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array( 'jazz' ), 'genre' );

		$result = block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' );

		unregister_taxonomy( 'genre' );

		$this->assertSame( array(), $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_taxonomies_that_are_skipped() {
		return array(
			'not shown in REST'      => array(
				array(
					'public'       => true,
					'show_in_rest' => false,
				),
			),
			'not publicly queryable' => array(
				array(
					'public'             => true,
					'publicly_queryable' => false,
					'show_in_rest'       => true,
				),
			),
		);
	}

	/**
	 * Tests a custom taxonomy that is public and shown in REST.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_a_custom_public_taxonomy() {
		register_taxonomy(
			'genre',
			'post',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);

		$post_id = $this->create_post();
		wp_set_object_terms( $post_id, array( 'jazz' ), 'genre' );

		$items = block_core_breadcrumbs_get_terms_breadcrumbs( $post_id, 'post' );

		unregister_taxonomy( 'genre' );

		$this->assertSame( array( 'jazz' ), $this->get_labels( $items ) );
	}

	/**
	 * Sets the settings that the filter gives.
	 *
	 * @param array $settings The settings.
	 */
	private function set_settings( $settings ) {
		add_filter(
			'block_core_breadcrumbs_post_type_settings',
			static function () use ( $settings ) {
				return $settings;
			}
		);
	}
}
