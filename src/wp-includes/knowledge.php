<?php
/**
 * Knowledge API: Public functions for the `wp_knowledge` post type.
 *
 * The Knowledge post type is a private-by-default storage primitive. Individual
 * rows are classified by one or more terms in the `wp_knowledge_type` taxonomy
 * (for example "guideline" or "note"). This file holds the type
 * registry, the default-term fallback applied on save, and the helper that
 * gives lazily created type terms a human-readable label.
 *
 * @package WordPress
 * @subpackage Knowledge
 * @since 7.2.0
 */

/**
 * Retrieves the registered knowledge types, keyed by slug.
 *
 * Plugins can register their own types via the {@see 'wp_knowledge_types'} filter.
 *
 * @since 7.2.0
 *
 * @return array {
 *     Slug-keyed map of knowledge types.
 *
 *     @type array ...$0 {
 *         Data for a single knowledge type.
 *
 *         @type string $title The human-readable label for the type.
 *     }
 * }
 * @phpstan-return array<non-empty-string, array{title: non-empty-string}>
 */
function wp_knowledge_types(): array {
	/**
	 * Filters the knowledge types available on this site.
	 *
	 * @since 7.2.0
	 *
	 * @param array $types {
	 *     Slug-keyed map of knowledge types.
	 *
	 *     @type array ...$0 {
	 *         Data for a single knowledge type.
	 *
	 *         @type string $title The human-readable label for the type.
	 *     }
	 * }
	 * @phpstan-param array<non-empty-string, array{title: non-empty-string}> $types
	 */
	return apply_filters(
		'wp_knowledge_types',
		array(
			'guideline' => array(
				'title' => _x( 'Guideline', 'knowledge type' ),
			),
			'note'      => array(
				'title' => _x( 'Note', 'knowledge type' ),
			),
		)
	);
}

/**
 * Ensures every knowledge row carries at least one type term.
 *
 * Knowledge rows are classified by their `wp_knowledge_type` terms, so a row
 * saved without one would belong to no type at all. This assigns the `note`
 * fallback in that case, keeping every row classified.
 *
 * Hooked to `wp_after_insert_post` so it runs after the row's terms are saved,
 * when the final set of terms is known.
 *
 * @since 7.2.0
 * @access private
 *
 * @param int     $post_id Saved post ID.
 * @param WP_Post $post    Saved post object.
 */
function wp_knowledge_ensure_default_type_term( int $post_id, WP_Post $post ): void {
	if ( 'wp_knowledge' !== $post->post_type ) {
		return;
	}

	$terms = get_the_terms( $post_id, 'wp_knowledge_type' );
	if ( is_wp_error( $terms ) || ! empty( $terms ) ) {
		return;
	}

	/*
	 * Resolve to a term ID up front, creating the term on first use:
	 * wp_set_object_terms() interprets strings as names for hierarchical
	 * taxonomies, not slugs.
	 */
	$term = term_exists( 'note', 'wp_knowledge_type' );
	if ( ! $term ) {
		$term = wp_insert_term( 'note', 'wp_knowledge_type' );
		if ( is_wp_error( $term ) ) {
			return;
		}
	}

	wp_set_object_terms( $post_id, (int) $term['term_id'], 'wp_knowledge_type' );
}

/**
 * Swaps a raw knowledge-type slug for its registered label on term creation.
 *
 * Hooked to the `wp_insert_term_data` filter. When `wp_set_object_terms()` is
 * called with a slug that does not yet exist, wp_insert_term() fires and this
 * filter runs after WordPress has computed both `name` and `slug`. A `name`
 * equal to `slug` indicates the term was created from a raw slug (for example by
 * `wp_set_object_terms()`) rather than from a user-provided label, so the label is
 * replaced with the title from {@see wp_knowledge_types()}.
 *
 * The name is written once and shared by every user, so it is resolved in the
 * site locale, not the locale of the request that happens to create the term.
 *
 * @since 7.2.0
 * @access private
 *
 * @param array  $data     Term data to be inserted (keyed by column name).
 * @param string $taxonomy Taxonomy slug.
 * @return array Possibly modified term data.
 *
 * @phpstan-param  array{ name: string, slug: string } $data
 * @phpstan-return array{ name: string, slug: string }
 */
function wp_knowledge_maybe_map_term_label( $data, string $taxonomy ): array {
	if ( ! is_array( $data ) ) {
		$data = array_fill_keys( array( 'name', 'slug' ), '' );
	}

	if ( 'wp_knowledge_type' !== $taxonomy ) {
		return $data;
	}

	if ( $data['name'] !== $data['slug'] ) {
		return $data;
	}

	// Type titles may be translatable, so resolve them under the site locale.
	$switched_locale = switch_to_locale( get_locale() );

	$types = wp_knowledge_types();
	if ( isset( $types[ $data['slug'] ] ) ) {
		$data['name'] = $types[ $data['slug'] ]['title'];
	}

	if ( $switched_locale ) {
		restore_previous_locale();
	}

	return $data;
}
