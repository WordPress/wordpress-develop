<?php

/**
 * @group post
 */
class Tests_Post_wpUniquePostSlug extends WP_UnitTestCase {
	protected $post_ids = array();

	/**
	 * @ticket 21013
	 */
	public function test_non_latin_slugs() {
		$author_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$inputs = array(
			'Αρνάκι άσπρο και παχύ της μάνας του καμάρι, και άλλα τραγούδια',
			'Предлагаем супер металлообрабатывающее оборудование',
		);

		$outputs = array(
			'αρνάκι-άσπρο-και-παχύ-της-μάνας-του-κα-2',
			'предлагаем-супер-металлообрабатыва-2',
		);

		foreach ( $inputs as $k => $post_title ) {
			for ( $i = 0; $i < 2; $i++ ) {
				$post = array(
					'post_author'  => $author_id,
					'post_status'  => 'publish',
					'post_content' => 'Post content',
					'post_title'   => $post_title,
				);

				$id               = self::factory()->post->create( $post );
				$this->post_ids[] = $id;
			}

			$post = get_post( $id );
			$this->assertSame( $outputs[ $k ], urldecode( $post->post_name ) );
		}
	}

	/**
	 * @ticket 18962
	 */
	public function test_with_multiple_hierarchies() {
		register_post_type( 'post-type-1', array( 'hierarchical' => true ) );
		register_post_type( 'post-type-2', array( 'hierarchical' => true ) );

		$args              = array(
			'post_type'   => 'post-type-1',
			'post_name'   => 'some-slug',
			'post_status' => 'publish',
		);
		$one               = self::factory()->post->create( $args );
		$args['post_type'] = 'post-type-2';
		$two               = self::factory()->post->create( $args );

		$this->assertSame( 'some-slug', get_post( $one )->post_name );
		$this->assertSame( 'some-slug', get_post( $two )->post_name );

		$this->assertSame( 'some-other-slug', wp_unique_post_slug( 'some-other-slug', $one, 'publish', 'post-type-1', 0 ) );
		$this->assertSame( 'some-other-slug', wp_unique_post_slug( 'some-other-slug', $one, 'publish', 'post-type-2', 0 ) );

		_unregister_post_type( 'post-type-1' );
		_unregister_post_type( 'post-type-2' );
	}

	/**
	 * @ticket 30339
	 */
	public function test_with_hierarchy() {
		register_post_type( 'post-type-1', array( 'hierarchical' => true ) );

		$args              = array(
			'post_type'   => 'post-type-1',
			'post_name'   => 'some-slug',
			'post_status' => 'publish',
		);
		$one               = self::factory()->post->create( $args );
		$args['post_name'] = 'some-slug-2';
		$two               = self::factory()->post->create( $args );

		$this->assertSame( 'some-slug', get_post( $one )->post_name );
		$this->assertSame( 'some-slug-2', get_post( $two )->post_name );

		$this->assertSame( 'some-slug-3', wp_unique_post_slug( 'some-slug', 0, 'publish', 'post-type-1', 0 ) );

		_unregister_post_type( 'post-type-1' );
	}

	/**
	 * @ticket 18962
	 */
	public function test_wp_unique_post_slug_with_hierarchy_and_attachments() {
		register_post_type( 'post-type-1', array( 'hierarchical' => true ) );

		$args = array(
			'post_type'   => 'post-type-1',
			'post_name'   => 'some-slug',
			'post_status' => 'publish',
		);
		$one  = self::factory()->post->create( $args );

		$args       = array(
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_name'      => 'image',
		);
		$attachment = self::factory()->attachment->create_object( 'image.jpg', $one, $args );

		$args = array(
			'post_type'   => 'post-type-1',
			'post_name'   => 'image',
			'post_status' => 'publish',
			'post_parent' => $one,
		);
		$two  = self::factory()->post->create( $args );

		$this->assertSame( 'some-slug', get_post( $one )->post_name );
		$this->assertSame( 'image', get_post( $attachment )->post_name );
		$this->assertSame( 'image-2', get_post( $two )->post_name );

		// 'image' can be a child of image-2.
		$this->assertSame( 'image', wp_unique_post_slug( 'image', 0, 'publish', 'post-type-1', $two ) );

		_unregister_post_type( 'post-type-1' );
	}

	/**
	 * @dataProvider data_allowed_post_statuses_should_not_be_forced_to_be_unique
	 */
	public function test_allowed_post_statuses_should_not_be_forced_to_be_unique( $status ) {
		$p1 = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$p2 = self::factory()->post->create(
			array(
				'post_type' => 'post',
			)
		);

		$actual = wp_unique_post_slug( 'foo', $p2, $status, 'post', 0 );

		$this->assertSame( 'foo', $actual );
	}

	public function data_allowed_post_statuses_should_not_be_forced_to_be_unique() {
		return array(
			array( 'draft' ),
			array( 'pending' ),
			array( 'auto-draft' ),
		);
	}

	public function test_revisions_should_not_be_forced_to_be_unique() {
		$p1 = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$p2 = self::factory()->post->create(
			array(
				'post_type' => 'post',
			)
		);

		$actual = wp_unique_post_slug( 'foo', $p2, 'inherit', 'revision', 0 );

		$this->assertSame( 'foo', $actual );
	}

	/**
	 * @ticket 5305
	 */
	public function test_slugs_resulting_in_permalinks_that_resemble_year_archives_should_be_suffixed() {
		$this->set_permalink_structure( '/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '2015', $p, 'publish', 'post', 0 );
		$this->assertSame( '2015-2', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_slugs_resulting_in_permalinks_that_resemble_year_archives_should_not_be_suffixed_for_already_published_posts() {
		$this->set_permalink_structure( '/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_name'   => 'foo',
				'post_status' => 'publish',
			)
		);

		$found = wp_unique_post_slug( '2015', $p, 'publish', 'post', 0 );
		$this->assertSame( '2015-2', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_yearlike_slugs_should_not_be_suffixed_if_permalink_structure_does_not_result_in_a_clash_with_year_archives() {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '2015', $p, 'publish', 'post', 0 );
		$this->assertSame( '2015', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_slugs_resulting_in_permalinks_that_resemble_month_archives_should_be_suffixed() {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '11', $p, 'publish', 'post', 0 );
		$this->assertSame( '11-2', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_monthlike_slugs_should_not_be_suffixed_if_permalink_structure_does_not_result_in_a_clash_with_month_archives() {
		$this->set_permalink_structure( '/%year%/foo/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '11', $p, 'publish', 'post', 0 );
		$this->assertSame( '11', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_monthlike_slugs_should_not_be_suffixed_for_invalid_month_numbers() {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '13', $p, 'publish', 'post', 0 );
		$this->assertSame( '13', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_slugs_resulting_in_permalinks_that_resemble_day_archives_should_be_suffixed() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '30', $p, 'publish', 'post', 0 );
		$this->assertSame( '30-2', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_daylike_slugs_should_not_be_suffixed_if_permalink_structure_does_not_result_in_a_clash_with_day_archives() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '30', $p, 'publish', 'post', 0 );
		$this->assertSame( '30', $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_daylike_slugs_should_not_be_suffixed_for_invalid_day_numbers() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'foo',
			)
		);

		$found = wp_unique_post_slug( '32', $p, 'publish', 'post', 0 );
		$this->assertSame( '32', $found );
	}

	/**
	 * @ticket 34971
	 */
	public function test_embed_slug_should_be_suffixed_for_posts() {
		$this->set_permalink_structure( '/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'post',
				'post_name' => 'embed',
			)
		);

		$found = wp_unique_post_slug( 'embed', $p, 'publish', 'post', 0 );
		$this->assertSame( 'embed-2', $found );
	}

	/**
	 * @ticket 34971
	 */
	public function test_embed_slug_should_be_suffixed_for_pages() {
		$this->set_permalink_structure( '/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'embed',
			)
		);

		$found = wp_unique_post_slug( 'embed', $p, 'publish', 'paage', 0 );
		$this->assertSame( 'embed-2', $found );
	}

	/**
	 * @ticket 34971
	 */
	public function test_embed_slug_should_be_suffixed_for_attachments() {
		$this->set_permalink_structure( '/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_type' => 'attachment',
				'post_name' => 'embed',
			)
		);

		$found = wp_unique_post_slug( 'embed', $p, 'publish', 'attachment', 0 );
		$this->assertSame( 'embed-2', $found );
	}

	/**
	 * @ticket 39603
	 */
	public function test_suffix_search_uses_a_bounded_number_of_queries() {
		global $wpdb;

		$duplicates = 100;

		for ( $i = 0; $i < $duplicates; $i++ ) {
			self::factory()->post->create( array( 'post_name' => 'bulk' ) );
		}

		$queries = $wpdb->num_queries;
		$slug    = wp_unique_post_slug( 'bulk', 0, 'publish', 'post', 0 );

		$this->assertSame( 'bulk-' . ( $duplicates + 1 ), $slug );
		$this->assertLessThan( $duplicates, $wpdb->num_queries - $queries, 'The suffix search should not run one query per duplicate.' );
	}

	/**
	 * @ticket 39603
	 */
	public function test_suffix_fills_the_lowest_gap() {
		$ids = array();
		for ( $number = 1; $number <= 150; $number++ ) {
			$ids[ $number ] = self::factory()->post->create( array( 'post_name' => 'gap' ) );
		}

		wp_delete_post( $ids[128], true );
		$this->assertSame( 'gap-128', wp_unique_post_slug( 'gap', 0, 'publish', 'post', 0 ), 'A gap at the end of a batch should be filled.' );

		wp_delete_post( $ids[65], true );
		$this->assertSame( 'gap-65', wp_unique_post_slug( 'gap', 0, 'publish', 'post', 0 ), 'A gap at the start of a batch should be filled.' );

		wp_delete_post( $ids[9], true );
		$this->assertSame( 'gap-9', wp_unique_post_slug( 'gap', 0, 'publish', 'post', 0 ), 'The lowest of several gaps should be filled.' );
	}

	/**
	 * @ticket 39603
	 */
	public function test_suffix_ignores_slugs_that_only_look_like_duplicates() {
		foreach ( array( 'your-subject', 'your-subject-2', 'your-subject-3', 'your-subject-3-2', 'not-your-subject-7', 'your-subject-suffix-2', 'your-subject-04', 'your-subject-4a', 'your-subject-5' ) as $name ) {
			self::factory()->post->create( array( 'post_name' => $name ) );
		}

		$this->assertSame( 'your-subject-4', wp_unique_post_slug( 'your-subject', 0, 'publish', 'post', 0 ) );
	}

	/**
	 * @ticket 39603
	 */
	public function test_suffix_reuses_slug_freed_by_renaming_a_post() {
		$foo = self::factory()->post->create( array( 'post_name' => 'foo' ) );
		self::factory()->post->create( array( 'post_name' => 'bar' ) );

		wp_update_post(
			array(
				'ID'        => $foo,
				'post_name' => 'bar',
			)
		);

		$this->assertSame( 'bar-2', get_post( $foo )->post_name );

		$new = self::factory()->post->create( array( 'post_name' => 'bar' ) );

		$this->assertSame( 'bar-3', get_post( $new )->post_name );
	}

	/**
	 * @ticket 39603
	 */
	public function test_suffix_treats_slugs_the_database_considers_equal_as_taken() {
		global $wpdb;

		for ( $i = 0; $i < 20; $i++ ) {
			$id = self::factory()->post->create( array( 'post_name' => 'mixed' ) );
		}

		$wpdb->update( $wpdb->posts, array( 'post_name' => 'MIXED-20' ), array( 'ID' => $id ) );
		clean_post_cache( $id );

		$this->assertSame( 'mixed-21', wp_unique_post_slug( 'mixed', 0, 'publish', 'post', 0 ) );
	}

	/**
	 * @ticket 39603
	 */
	public function test_suffix_truncation_changes_when_suffix_gains_a_digit() {
		$slug = str_repeat( 'b', 200 );
		for ( $i = 0; $i < 12; $i++ ) {
			self::factory()->post->create( array( 'post_name' => $slug ) );
		}

		$this->assertSame( wp_truncate_slug( $slug, 197 ) . '-13', wp_unique_post_slug( $slug, 0, 'publish', 'post', 0 ) );
	}

	/**
	 * @ticket 39603
	 */
	public function test_suffix_respects_parent_for_hierarchical_post_types() {
		$parent_a = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$parent_b = self::factory()->post->create( array( 'post_type' => 'page' ) );

		for ( $i = 0; $i < 2; $i++ ) {
			self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_parent' => $parent_a,
					'post_name'   => 'child',
				)
			);
		}
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_b,
				'post_name'   => 'child',
			)
		);

		$this->assertSame( 'child-3', wp_unique_post_slug( 'child', 0, 'publish', 'page', $parent_a ) );
		$this->assertSame( 'child-2', wp_unique_post_slug( 'child', 0, 'publish', 'page', $parent_b ) );
	}
}
