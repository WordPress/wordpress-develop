<?php

/**
 * Test the is_*() functions in query.php related to taxonomy terms across the URL structure.
 *
 * This exercises both query.php and rewrite.php: urls are fed through the rewrite code,
 * then we test the effects of each url on the wp_query object.
 *
 * @group query
 * @group rewrite
 * @group taxonomy
 *
 * @covers WP_Query
 */
class Tests_Query_IsTerm extends WP_UnitTestCase {
	protected $tag_id;
	protected $cat_id;
	protected $tax_id;
	protected $tax_id2;
	protected $post_id;

	protected $cat;
	protected $uncat;
	protected $tag;
	protected $tax;

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		create_initial_taxonomies();
		register_taxonomy( 'testtax', 'post', array( 'public' => true ) );

		flush_rewrite_rules();

		$this->tag_id  = self::factory()->tag->create( array( 'slug' => 'tag-slug' ) );
		$this->cat_id  = self::factory()->category->create( array( 'slug' => 'cat-slug' ) );
		$this->tax_id  = self::factory()->term->create(
			array(
				'taxonomy' => 'testtax',
				'slug'     => 'tax-slug',
			)
		);
		$this->tax_id2 = self::factory()->term->create(
			array(
				'taxonomy' => 'testtax',
				'slug'     => 'tax-slug2',
			)
		);
		$this->post_id = self::factory()->post->create();
		wp_set_object_terms( $this->post_id, $this->cat_id, 'category' );
		wp_set_object_terms( $this->post_id, array( $this->tax_id, $this->tax_id2 ), 'testtax' );

		$this->cat = get_term( $this->cat_id, 'category' );
		_make_cat_compat( $this->cat );
		$this->tag = get_term( $this->tag_id, 'post_tag' );

		$this->uncat = get_term_by( 'slug', 'uncategorized', 'category' );
		_make_cat_compat( $this->uncat );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts_tax_category_tax_query' ) );
	}

	public function test_tag_action_tax() {
		// Tag with taxonomy added.
		$this->go_to( home_url( '/tag/tag-slug/' ) );
		$this->assertQueryTrue( 'is_tag', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertNotEmpty( get_query_var( 'tag_id' ) );
		$this->assertEquals( get_queried_object(), $this->tag );
	}

	public function test_tag_query_cat_action_tax() {
		// Tag + category with taxonomy added.
		$this->go_to( home_url( "/tag/tag-slug/?cat=$this->cat_id" ) );
		$this->assertQueryTrue( 'is_category', 'is_tag', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertNotEmpty( get_query_var( 'cat' ) );
		$this->assertNotEmpty( get_query_var( 'tag_id' ) );
		$this->assertEquals( get_queried_object(), $this->cat );
	}

	public function test_tag_query_cat_query_tax_action_tax() {
		// Tag + category + tax with taxonomy added.
		$this->go_to( home_url( "/tag/tag-slug/?cat=$this->cat_id&testtax=tax-slug2" ) );
		$this->assertQueryTrue( 'is_category', 'is_tag', 'is_tax', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertNotEmpty( get_query_var( 'cat' ) );
		$this->assertNotEmpty( get_query_var( 'tag_id' ) );
		$this->assertNotEmpty( get_query_var( 'testtax' ) );
		$this->assertEquals( get_queried_object(), $this->cat );
	}

	public function test_cat_action_tax() {
		// Category with taxonomy added.
		$this->go_to( home_url( '/category/cat-slug/' ) );
		$this->assertQueryTrue( 'is_category', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'cat' ) );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertEquals( get_queried_object(), $this->cat );
	}

	/**
	 * @ticket 26627
	 */
	public function test_cat_uncat_action_tax() {
		// Category with taxonomy added.
		add_action( 'pre_get_posts', array( $this, 'cat_uncat_action_tax' ), 11 );

		$this->go_to( home_url( '/category/uncategorized/' ) );
		$this->assertQueryTrue( 'is_category', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'cat' ) );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertEquals( get_queried_object(), $this->uncat );

		remove_action( 'pre_get_posts', array( $this, 'cat_uncat_action_tax' ), 11 );
	}

	public function cat_uncat_action_tax( &$query ) {
		$this->assertTrue( $query->is_category() );
		$this->assertTrue( $query->is_archive() );
		$this->assertNotEmpty( $query->get( 'category_name' ) );
		$this->assertNotEmpty( $query->get( 'tax_query' ) );
		$this->assertEquals( $query->get_queried_object(), $this->uncat );
	}

	/**
	 * @ticket 26728
	 */
	public function test_tax_action_tax() {
		// Taxonomy with taxonomy added.
		$this->go_to( home_url( '/testtax/tax-slug2/' ) );
		$this->assertQueryTrue( 'is_tax', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertEquals( get_queried_object(), get_term( $this->tax_id, 'testtax' ) );
	}

	public function test_tax_query_tag_action_tax() {
		// Taxonomy + tag with taxonomy added.
		$this->go_to( home_url( "/testtax/tax-slug2/?tag_id=$this->tag_id" ) );
		$this->assertQueryTrue( 'is_tag', 'is_tax', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertNotEmpty( get_query_var( 'tag_id' ) );
		$this->assertEquals( get_queried_object(), $this->tag );
	}

	public function test_tax_query_cat_action_tax() {
		// Taxonomy + category with taxonomy added.
		$this->go_to( home_url( "/testtax/tax-slug2/?cat=$this->cat_id" ) );
		$this->assertQueryTrue( 'is_category', 'is_tax', 'is_archive' );
		$this->assertNotEmpty( get_query_var( 'tax_query' ) );
		$this->assertNotEmpty( get_query_var( 'taxonomy' ) );
		$this->assertNotEmpty( get_query_var( 'term_id' ) );
		$this->assertNotEmpty( get_query_var( 'cat' ) );
		$this->assertEquals( get_queried_object(), $this->cat );
	}

	public function pre_get_posts_tax_category_tax_query( &$query ) {
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'testtax',
					'field'    => 'term_id',
					'terms'    => $this->tax_id,
				),
			)
		);
	}

	/**
	 * @ticket 30623
	 */
	public function test_get_queried_object_with_custom_taxonomy_tax_query_and_field_term_id_should_return_term_object() {
		// Don't override the args provided below.
		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts_tax_category_tax_query' ) );

		$args = array(
			'tax_query' => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'testtax',
					'field'    => 'term_id',
					'terms'    => array(
						$this->tax_id,
					),
				),
			),
		);

		$q      = new WP_Query( $args );
		$object = $q->get_queried_object();

		$expected = get_term( $this->tax_id, 'testtax' );

		$this->assertEquals( $expected, $object );
	}

	/**
	 * @ticket 30623
	 */
	public function test_get_queried_object_with_custom_taxonomy_tax_query_and_field_slug_should_return_term_object() {
		// Don't override the args provided below.
		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts_tax_category_tax_query' ) );

		$args = array(
			'tax_query' => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'testtax',
					'field'    => 'slug',
					'terms'    => array(
						'tax-slug',
					),
				),
			),
		);

		$q      = new WP_Query( $args );
		$object = $q->get_queried_object();

		$expected = get_term( $this->tax_id, 'testtax' );

		// Only compare term_id because object_id may or may not be part of either value.
		$this->assertSame( $expected->term_id, $object->term_id );
	}

	/**
	 * @ticket 30623
	 */
	public function test_get_queried_object_with_custom_taxonomy_tax_query_with_multiple_clauses_should_return_term_object_corresponding_to_the_first_queried_tax() {
		// Don't override the args provided below.
		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts_tax_category_tax_query' ) );

		register_taxonomy( 'testtax2', 'post' );
		$testtax2_term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'testtax2',
				'slug'     => 'testtax2-slug',
			)
		);

		$args = array(
			'tax_query' => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'testtax',
					'field'    => 'slug',
					'terms'    => array(
						'tax-slug',
					),
				),
				array(
					'taxonomy' => 'testtax2',
					'field'    => 'slug',
					'terms'    => array(
						'testtax2-slug',
					),
				),
			),
		);

		$q      = new WP_Query( $args );
		$object = $q->get_queried_object();

		$expected = get_term( $this->tax_id, 'testtax' );

		// Only compare term_id because object_id may or may not be part of either value.
		$this->assertSame( $expected->term_id, $object->term_id );
	}
}
