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
	public function test_default_types_are_registered() {
		$types = wp_knowledge_types();

		$this->assertArrayHasKey( 'guideline', $types );
		$this->assertArrayHasKey( 'memory', $types );
		$this->assertArrayHasKey( 'note', $types );

		$this->assertSame( 'Guideline', $types['guideline']['title'] );
		$this->assertSame( 'Memory', $types['memory']['title'] );
		$this->assertSame( 'Note', $types['note']['title'] );
	}

	/**
	 * @ticket 65476
	 * @covers ::wp_knowledge_types
	 */
	public function test_types_are_filterable() {
		$callback = static function ( $types ) {
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
	 * @covers ::_wp_knowledge_ensure_default_type_term
	 */
	public function test_default_type_term_is_assigned_on_save() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
			)
		);

		$terms = wp_get_object_terms( $post_id, 'wp_knowledge_type', array( 'fields' => 'slugs' ) );

		$this->assertSame( array( 'note' ), $terms );
	}

	/**
	 * A row that already carries a type term should keep it, not gain `note`.
	 *
	 * @ticket 65476
	 * @covers ::_wp_knowledge_ensure_default_type_term
	 */
	public function test_existing_type_term_is_preserved_on_save() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
			)
		);

		// Assign a non-default term, replacing the `note` fallback from creation.
		$term = wp_insert_term( 'memory', 'wp_knowledge_type' );
		wp_set_object_terms( $post_id, (int) $term['term_id'], 'wp_knowledge_type' );

		// A subsequent save must not re-add the `note` fallback.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated knowledge',
			)
		);

		$terms = wp_get_object_terms( $post_id, 'wp_knowledge_type', array( 'fields' => 'slugs' ) );

		$this->assertSame( array( 'memory' ), $terms );
	}

	/**
	 * A term lazily created from a registered slug gets the registered label.
	 *
	 * @ticket 65476
	 * @covers ::_wp_knowledge_maybe_map_term_label
	 */
	public function test_registered_slug_term_gets_mapped_label() {
		$term = wp_insert_term( 'guideline', 'wp_knowledge_type' );
		$this->assertNotWPError( $term );

		$created = get_term( $term['term_id'], 'wp_knowledge_type' );

		$this->assertSame( 'guideline', $created->slug );
		$this->assertSame( 'Guideline', $created->name );
	}

	/**
	 * A user-provided label (where name differs from the slug) is left intact.
	 *
	 * @ticket 65476
	 * @covers ::_wp_knowledge_maybe_map_term_label
	 */
	public function test_custom_label_is_not_overwritten() {
		$term = wp_insert_term( 'My Custom Type', 'wp_knowledge_type', array( 'slug' => 'guideline' ) );
		$this->assertNotWPError( $term );

		$created = get_term( $term['term_id'], 'wp_knowledge_type' );

		$this->assertSame( 'guideline', $created->slug );
		$this->assertSame( 'My Custom Type', $created->name );
	}

	/**
	 * The label mapping must not touch terms in other taxonomies.
	 *
	 * @ticket 65476
	 * @covers ::_wp_knowledge_maybe_map_term_label
	 */
	public function test_label_mapping_is_scoped_to_knowledge_taxonomy() {
		$term = wp_insert_term( 'guideline', 'category' );
		$this->assertNotWPError( $term );

		$created = get_term( $term['term_id'], 'category' );

		$this->assertSame( 'guideline', $created->name );
	}
}
