<?php

/**
 * Unit test factory for terms.
 */
class WP_UnitTest_Factory_For_Term extends WP_UnitTest_Factory_For_Thing {

	/**
	 * The taxonomy this factory creates terms in, unless the args say otherwise.
	 *
	 * @var non-falsy-string
	 */
	private string $taxonomy;

	const DEFAULT_TAXONOMY = 'post_tag';

	/**
	 * @param object|null           $factory  Optional. Global factory that can be used to create other objects on the system. Default null.
	 * @param non-falsy-string|null $taxonomy Optional. The taxonomy to create terms in. Default self::DEFAULT_TAXONOMY.
	 */
	public function __construct( $factory = null, ?string $taxonomy = null ) {
		parent::__construct( $factory );
		$this->taxonomy                       = $taxonomy ? $taxonomy : self::DEFAULT_TAXONOMY;
		$this->default_generation_definitions = array(
			'name'        => new WP_UnitTest_Generator_Sequence( 'Term %s' ),
			'taxonomy'    => $this->taxonomy,
			'description' => new WP_UnitTest_Generator_Sequence( 'Term description %s' ),
		);
	}

	/**
	 * Creates a term object.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed> $args Array of arguments for inserting a term.
	 * @return positive-int The term ID.
	 * @throws WP_UnitTest_Factory_Exception When the term could not be created.
	 */
	public function create_object( $args ) {
		$args         = array_merge( array( 'taxonomy' => $this->taxonomy ), $args );
		$term_id_pair = wp_insert_term( $args['name'], $args['taxonomy'], $args );
		$term_id      = is_wp_error( $term_id_pair ) ? $term_id_pair : $term_id_pair['term_id'];

		$this->assert_valid_object_id( $term_id, 'Unable to create the term' );

		return $term_id;
	}

	/**
	 * Updates the term.
	 *
	 * @since UT (3.7.0)
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param int|WP_Term          $term   The term to update.
	 * @param array<string, mixed> $fields Array of arguments for updating a term.
	 * @return positive-int The term ID.
	 * @throws WP_UnitTest_Factory_Exception When the term could not be updated.
	 */
	public function update_object( $term, $fields ) {
		if ( is_object( $term ) ) {
			$taxonomy = $term->taxonomy;
		} else {
			/*
			 * create() passes an ID along with only the after-create callback results, so the
			 * fields say nothing about the taxonomy. The term may live in one other than this
			 * factory's default, having come from the args or the generation definitions, so
			 * read it from the term itself.
			 */
			$existing = get_term( $term );
			$taxonomy = $existing instanceof WP_Term ? $existing->taxonomy : $this->taxonomy;
		}

		$term_id_pair = wp_update_term( $term, $taxonomy, $fields );
		$term_id      = is_wp_error( $term_id_pair ) ? $term_id_pair : $term_id_pair['term_id'];

		$this->assert_valid_object_id( $term_id, 'Unable to update the term' );

		return $term_id;
	}

	/**
	 * Attach terms to the given post.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int          $post_id  The post ID.
	 * @param string|array $terms    An array of terms to set for the post, or a string of terms
	 *                               separated by commas. Hierarchical taxonomies must always pass IDs rather
	 *                               than names so that children with the same names but different parents
	 *                               aren't confused.
	 * @param string       $taxonomy Taxonomy name.
	 * @param bool         $append   Optional. If true, don't delete existing terms, just add on. If false,
	 *                               replace the terms with the new terms. Default true.
	 *
	 * @return array|false|WP_Error Array of term taxonomy IDs of affected terms. WP_Error or false on failure.
	 */
	public function add_post_terms( $post_id, $terms, $taxonomy, $append = true ) {
		return wp_set_post_terms( $post_id, $terms, $taxonomy, $append );
	}

	/**
	 * Create a term and returns it as an object.
	 *
	 * @since 4.3.0
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed>      $args                   Array of arguments for inserting a term.
	 * @param array<string, mixed>|null $generation_definitions The default values.
	 * @return WP_Term Term object.
	 * @throws WP_UnitTest_Factory_Exception When the term could not be created or retrieved.
	 */
	public function create_and_get( $args = array(), $generation_definitions = null ) {
		$term_id = $this->create( $args, $generation_definitions );

		// The term may have been created in a taxonomy that came from either the args or the
		// generation definitions, so look it up by ID alone rather than guessing which.
		$term = get_term( $term_id );

		$this->assert_valid_object( $term, $term_id, WP_Term::class, $args );

		return $term;
	}

	/**
	 * Retrieves the term by a given ID.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object or null when the term cannot be retrieved.
	 *
	 * @param int $term_id ID of the term to retrieve.
	 * @return WP_Term The term object.
	 * @throws WP_UnitTest_Factory_Exception When the term could not be retrieved.
	 */
	public function get_object_by_id( $term_id ) {
		$term = get_term( $term_id, $this->taxonomy );

		$this->assert_valid_object( $term, $term_id, WP_Term::class );

		return $term;
	}
}
