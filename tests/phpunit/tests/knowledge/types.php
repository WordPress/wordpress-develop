<?php
/**
 * Tests for the knowledge types registry and the wp_knowledge_type term helpers.
 *
 * @package WordPress
 * @subpackage Knowledge
 *
 * @group knowledge
 */
class Tests_Knowledge_Types extends WP_UnitTestCase {

	/**
	 * @ticket 65476
	 * @covers ::wp_knowledge_types
	 */
	public function test_default_types_are_registered(): void {
		$types = wp_knowledge_types();

		$this->assertArrayHasKey( 'guideline', $types );
		$this->assertArrayHasKey( 'note', $types );

		$this->assertSame( 'Guideline', $types['guideline']['title'] );
		$this->assertSame( 'Note', $types['note']['title'] );
	}

	/**
	 * @ticket 65476
	 * @covers ::wp_knowledge_types
	 */
	public function test_types_are_filterable(): void {
		$callback = static function ( array $types ): array {
			$types['skill'] = array( 'title' => 'Skill' );
			return $types;
		};

		add_filter( 'wp_knowledge_types', $callback );
		$types = wp_knowledge_types();
		remove_filter( 'wp_knowledge_types', $callback );

		$this->assertArrayHasKey( 'skill', $types );
		$this->assertSame( 'Skill', $types['skill']['title'] );
	}

	/**
	 * A knowledge row saved without a type term should fall back to `note`.
	 *
	 * @ticket 65476
	 * @covers ::wp_knowledge_ensure_default_type_term
	 */
	public function test_default_type_term_is_assigned_on_save(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
			)
		);
		$this->assertIsInt( $post_id );

		$terms = wp_get_object_terms( $post_id, 'wp_knowledge_type', array( 'fields' => 'slugs' ) );

		$this->assertSame( array( 'note' ), $terms );
	}

	/**
	 * A row that already carries a type term should keep it, not gain `note`.
	 *
	 * @ticket 65476
	 * @covers ::wp_knowledge_ensure_default_type_term
	 */
	public function test_existing_type_term_is_preserved_on_save(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
			)
		);
		$this->assertIsInt( $post_id );

		// Assign a non-default term, replacing the `note` fallback from creation.
		$term = wp_insert_term( 'guideline', 'wp_knowledge_type' );
		$this->assertIsArray( $term );
		wp_set_object_terms( $post_id, (int) $term['term_id'], 'wp_knowledge_type' );

		// A subsequent save must not re-add the `note` fallback.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated knowledge',
			)
		);

		$terms = wp_get_object_terms( $post_id, 'wp_knowledge_type', array( 'fields' => 'slugs' ) );

		$this->assertSame( array( 'guideline' ), $terms );
	}

	/**
	 * A term lazily created from a registered slug gets the registered label.
	 *
	 * @ticket 65476
	 * @covers ::wp_knowledge_maybe_map_term_label
	 */
	public function test_registered_slug_term_gets_mapped_label(): void {
		$term = wp_insert_term( 'guideline', 'wp_knowledge_type' );
		$this->assertIsArray( $term );

		$created = get_term( $term['term_id'], 'wp_knowledge_type' );
		$this->assertInstanceOf( WP_Term::class, $created );

		$this->assertSame( 'guideline', $created->slug );
		$this->assertSame( 'Guideline', $created->name );
	}

	/**
	 * A user-provided label (where name differs from the slug) is left intact.
	 *
	 * @ticket 65476
	 * @covers ::wp_knowledge_maybe_map_term_label
	 */
	public function test_custom_label_is_not_overwritten(): void {
		$term = wp_insert_term( 'My Custom Type', 'wp_knowledge_type', array( 'slug' => 'guideline' ) );
		$this->assertIsArray( $term );

		$created = get_term( $term['term_id'], 'wp_knowledge_type' );
		$this->assertInstanceOf( WP_Term::class, $created );

		$this->assertSame( 'guideline', $created->slug );
		$this->assertSame( 'My Custom Type', $created->name );
	}

	/**
	 * The label mapping must not touch terms in other taxonomies.
	 *
	 * @ticket 65476
	 * @covers ::wp_knowledge_maybe_map_term_label
	 */
	public function test_label_mapping_is_scoped_to_knowledge_taxonomy(): void {
		$term = wp_insert_term( 'guideline', 'category' );
		$this->assertIsArray( $term );

		$created = get_term( $term['term_id'], 'category' );
		$this->assertInstanceOf( WP_Term::class, $created );

		$this->assertSame( 'guideline', $created->name );
	}

	/**
	 * A term name is stored once and shared, so the label must be resolved in the
	 * site locale even when the request runs in a different one.
	 *
	 * @ticket 65476
	 * @covers ::wp_knowledge_maybe_map_term_label
	 */
	public function test_label_is_resolved_in_site_locale(): void {
		// Simulate a non-site request locale. Priority 1 runs before the locale
		// switcher (priority 10), so the mapping's switch still wins.
		$request_locale = 'de_DE';
		add_filter(
			'determine_locale',
			static function () use ( $request_locale ) {
				return $request_locale;
			},
			1
		);

		// The request runs in a non-site locale before the mapping switches.
		$this->assertSame( $request_locale, determine_locale() );
		$this->assertNotSame(
			$request_locale,
			get_locale(),
			'Test setup requires the site locale to differ from the request locale.'
		);

		$captured = null;
		add_filter(
			'wp_knowledge_types',
			static function ( $types ) use ( &$captured ) {
				$captured = determine_locale();
				return $types;
			}
		);

		$term = wp_insert_term( 'note', 'wp_knowledge_type' );

		$this->assertNotWPError( $term );
		$this->assertSame( get_locale(), $captured, 'Label should be resolved in the site locale.' );
		$this->assertNotSame( $request_locale, $captured, 'Label should not be resolved in the request locale.' );
	}
}
