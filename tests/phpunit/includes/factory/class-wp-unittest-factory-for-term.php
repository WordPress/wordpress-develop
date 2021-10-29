<?php

/**
 * Unit test factory for terms.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int create( $args = array(), $generation_definitions = null )
 * @method WP_Term create_and_get( $args = array(), $generation_definitions = null )
 * @method int[] create_many( $count, $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Term extends WP_UnitTest_Factory_For_Thing {

	private $taxonomy;
	const DEFAULT_TAXONOMY = 'post_tag';

	public function __construct( $factory = null, $taxonomy = null ) {
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
	 * @param array $args Array or string of arguments for inserting a term.
	 *
	 * @return array|WP_Error
	 */
	public function create_object( $args ) {
		$args         = array_merge( array( 'taxonomy' => $this->taxonomy ), $args );
		$term_id_pair = wp_insert_term( $args['name'], $args['taxonomy'], $args );
		if ( is_wp_error( $term_id_pair ) ) {
			return $term_id_pair;
		}
		return $term_id_pair['term_id'];
	}

	/**
	 * Updates the term.
	 *
	 * @param int|object   $term   The term to update.
	 * @param array|string $fields The context in which to relate the term to the object.
	 *
	 * @return int The term ID.
	 */
	public function update_object( $term, $fields ) {
		$fields = array_merge( array( 'taxonomy' => $this->taxonomy ), $fields );
		if ( is_object( $term ) ) {
			$taxonomy = $term->taxonomy;
		}
		$term_id_pair = wp_update_term( $term, $taxonomy, $fields );
		return $term_id_pair['term_id'];
	}

	/**
	 * Attach terms to the given post.
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
	 * @param array $args                   Array or string of arguments for inserting a term.
	 * @param null  $generation_definitions The default values.
	 *
	 * @return WP_Term|WP_Error|null WP_Term on success. WP_Error if taxonomy does not exist. Null for miscellaneous failure.
	 */
	public function create_and_get( $args = array(), $generation_definitions = null ) {
		$term_id = $this->create( $args, $generation_definitions );

		if ( is_wp_error( $term_id ) ) {
			return $term_id;
		}

		$taxonomy = isset( $args['taxonomy'] ) ? $args['taxonomy'] : $this->taxonomy;
		return get_term( $term_id, $taxonomy );
	}

	/**
	 * Retrieves the term by a given ID.
	 *
	 * @param int $term_id ID of the term to retrieve.
	 *
	 * @return WP_Term|WP_Error|null WP_Term on success. WP_Error if taxonomy does not exist. Null for miscellaneous failure.
	 */
	public function get_object_by_id( $term_id ) {
		return get_term( $term_id, $this->taxonomy );
	}
}
