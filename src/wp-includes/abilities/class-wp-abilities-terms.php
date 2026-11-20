<?php
/**
 * Abilities API: WP_Abilities_Terms class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register term-related abilities.
 *
 * Registers `core/terms-query`, which lists the terms of a taxonomy exposed to abilities via
 * `show_in_abilities`, and `core/term-create`, `core/term-update`, and `core/term-delete`,
 * which create, update, and delete one term of such a taxonomy. The write abilities return
 * the written term in the same shape as a `core/terms-query` item.
 *
 * Only register() is public. The ability callbacks are closures that call private
 * methods, so callers go through the Abilities API, such as
 * `wp_get_ability( 'core/terms-query' )->execute()`, which validates the input and
 * checks permissions before running them.
 *
 * @since 7.3.0
 *
 * @access private
 */
final class WP_Abilities_Terms {

	/**
	 * The ability category used for term abilities.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	private const CATEGORY = 'content';

	/**
	 * Default number of terms returned per page, unless `where` lists terms by ID or slug.
	 *
	 * @since 7.3.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of terms returned per page.
	 *
	 * The `ids` and `slugs` lists in `where` each hold at most this many values.
	 *
	 * @since 7.3.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Default fields returned when the caller does not request a field subset.
	 *
	 * @since 7.3.0
	 * @var list<string>
	 */
	private const DEFAULT_FIELDS = array( 'id', 'taxonomy', 'name', 'slug', 'link' );

	/**
	 * Registers all term abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.3.0
	 */
	public function register(): void {
		/*
		 * Taxonomies must be registered with `show_in_abilities` before the abilities are
		 * registered so they are included in the input schemas. Without one, no term
		 * ability is registered.
		 */
		$taxonomies = array_values( get_taxonomies( array( 'show_in_abilities' => true ) ) );
		if ( empty( $taxonomies ) ) {
			return;
		}

		$this->register_terms_query( $taxonomies );
		$this->register_term_create( $taxonomies );
		$this->register_term_update( $taxonomies );
		$this->register_term_delete( $taxonomies );
	}

	/**
	 * Registers the read-only `core/terms-query` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 */
	private function register_terms_query( array $taxonomies ): void {
		wp_register_ability(
			'core/terms-query',
			array(
				'label'               => __( 'Query Terms' ),
				'description'         => __( 'Retrieves a paginated list of the terms of a taxonomy exposed to abilities, filtered by any of term IDs, slugs, parent, post, and whether the term is assigned to any post. Requires an authenticated user. Filters are exact-match only; the ability does not perform full-text search.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_terms_query_input_schema( $taxonomies ),
				'output_schema'       => $this->get_terms_query_output_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_terms_query( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_query_permission( $input );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Registers the `core/term-create` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 */
	private function register_term_create( array $taxonomies ): void {
		wp_register_ability(
			'core/term-create',
			array(
				'label'               => __( 'Create Term' ),
				'description'         => __( 'Creates a term in a taxonomy exposed to abilities and returns it. Requires an authenticated user who can create terms in that taxonomy.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_term_create_input_schema( $taxonomies ),
				'output_schema'       => $this->get_term_output_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_term_create( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_create_permission( $input );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Registers the `core/term-update` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 */
	private function register_term_update( array $taxonomies ): void {
		wp_register_ability(
			'core/term-update',
			array(
				'label'               => __( 'Update Term' ),
				'description'         => __( 'Updates the given attributes of a term of a taxonomy exposed to abilities and returns it. Attributes that are left out keep their stored values. Requires an authenticated user who can edit the term.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_term_update_input_schema( $taxonomies ),
				'output_schema'       => $this->get_term_output_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_term_update( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_term_permission( $input, 'edit' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Registers the `core/term-delete` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 */
	private function register_term_delete( array $taxonomies ): void {
		wp_register_ability(
			'core/term-delete',
			array(
				'label'               => __( 'Delete Term' ),
				'description'         => __( 'Deletes a term of a taxonomy exposed to abilities permanently. Terms cannot be trashed. The default term of a taxonomy cannot be deleted. Requires an authenticated user who can delete the term.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_term_delete_input_schema( $taxonomies ),
				'output_schema'       => $this->get_term_delete_output_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_term_delete( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_term_permission( $input, 'delete' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Permission callback for the `core/terms-query` ability.
	 *
	 * Requires a logged-in user and a `where.taxonomy` that is exposed to abilities. No
	 * capability is needed to query terms.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_query_permission( $input = array() ): bool {
		// Intentionally strict: terms can only be queried by a logged-in user.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// The input is read defensively: WP_Ability::check_permissions() does not prepare it.
		$where = $this->get_raw_member( $input, 'where' );

		return null !== $where && null !== $this->get_exposed_taxonomy( $where['taxonomy'] ?? null );
	}

	/**
	 * Permission callback for the `core/term-create` ability.
	 *
	 * Requires a logged-in user, a `data.taxonomy` that is exposed to abilities, and the
	 * capability to create terms in it. {@see self::execute_term_create()} checks the
	 * capability again and makes every other decision.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_create_permission( $input = array() ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// The input is read defensively: WP_Ability::check_permissions() does not prepare it.
		$data = $this->get_raw_member( $input, 'data' );
		if ( null === $data ) {
			return false;
		}

		$taxonomy_object = $this->get_exposed_taxonomy( $data['taxonomy'] ?? null );

		return null !== $taxonomy_object && $this->can_create_terms( $taxonomy_object );
	}

	/**
	 * Permission callback for the `core/term-update` and `core/term-delete` abilities.
	 *
	 * Requires a logged-in user, a `where.taxonomy` that is exposed to abilities, a valid
	 * `where.id`, and the capability to edit or delete the term it names. A term that does
	 * not exist in the taxonomy is let through, so that the execute callback can report it
	 * as not found. The execute callbacks check the capability again and make every other
	 * decision.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $input  The ability input.
	 * @param string $action The operation: `edit` or `delete`.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_term_permission( $input, string $action ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// The input is read defensively: WP_Ability::check_permissions() does not prepare it.
		$where = $this->get_raw_member( $input, 'where' );
		if ( null === $where ) {
			return false;
		}

		$taxonomy_object = $this->get_exposed_taxonomy( $where['taxonomy'] ?? null );
		if ( ! $taxonomy_object ) {
			return false;
		}

		$term_id = $this->parse_filter_int( $where['id'] ?? null, 1 );
		if ( null === $term_id ) {
			return false;
		}

		$term = $this->get_term_in_taxonomy( $term_id, $taxonomy_object->name );
		if ( ! $term ) {
			return true;
		}

		return 'delete' === $action
			? $this->can_delete_term( $term_id, $taxonomy_object )
			: current_user_can( 'edit_term', $term_id );
	}

	/**
	 * Reads an object member of the raw input, such as `where` or `data`.
	 *
	 * Both the input and the member count as objects when they are an array or a `stdClass`.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $input The raw ability input.
	 * @param string $key   The member name.
	 * @return array<mixed>|null The member as an array, or null when the input or the member
	 *                           is missing or not an object.
	 */
	private function get_raw_member( $input, string $key ): ?array {
		$input = $this->get_raw_object( $input );
		if ( null === $input || ! array_key_exists( $key, $input ) ) {
			return null;
		}

		return $this->get_raw_object( $input[ $key ] );
	}

	/**
	 * Reads a raw value as an object.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $value The raw value.
	 * @return array<mixed>|null The value as an array, or null when it is not an array or a `stdClass`.
	 */
	private function get_raw_object( $value ): ?array {
		if ( is_array( $value ) ) {
			return $value;
		}

		return $value instanceof stdClass ? get_object_vars( $value ) : null;
	}

	/**
	 * Parses a raw input value into an integer of at least a minimum, or null when invalid.
	 *
	 * Accepts the whole numbers that the integer schema accepts: native integers, floats such
	 * as the 2.0 that JSON encoders produce, and integer strings such as "2", "2.0", or "+2".
	 * WP_Ability::execute() converts input to the schema types, but
	 * WP_Ability::check_permissions() does not, so a permission callback can receive any of them.
	 * Anything else, such as a fraction, is rejected rather than coerced: a malformed ID would
	 * otherwise resolve to another term.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $value The raw input value.
	 * @param int   $min   The smallest acceptable value.
	 * @return int|null The parsed integer, or null when the value is not an integer >= $min.
	 */
	private function parse_filter_int( $value, int $min ): ?int {
		if ( is_int( $value ) ) {
			return $value >= $min ? $value : null;
		}

		if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
			$int = (int) $value;

			return $int >= $min ? $int : null;
		}

		if ( ! rest_is_integer( $value ) ) {
			return null;
		}

		/*
		 * Other whole numbers are read as floats, which hold every integer only up to 2 ** 53.
		 * Past that, the value may not be the one that was sent, and casting a float beyond the
		 * integer range wraps it to an arbitrary integer, such as the ID of another term.
		 */
		$number = (float) $value;

		return $number >= $min && $number <= 2 ** 53 ? (int) $number : null;
	}

	/**
	 * Returns a taxonomy exposed through the Abilities API.
	 *
	 * Deliberately resolved on every call rather than cached: taxonomies can be
	 * unregistered or re-registered with different arguments between the ability
	 * being registered and the ability being used.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $taxonomy The taxonomy name.
	 * @return WP_Taxonomy|null The taxonomy object, or null when the taxonomy is not exposed.
	 */
	private function get_exposed_taxonomy( $taxonomy ): ?WP_Taxonomy {
		$taxonomy_object = is_string( $taxonomy ) ? get_taxonomy( $taxonomy ) : false;

		return $taxonomy_object instanceof WP_Taxonomy && ! empty( $taxonomy_object->show_in_abilities ) ? $taxonomy_object : null;
	}

	/**
	 * Returns the term of an ID, when it belongs to the given taxonomy.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $term_id  The term ID.
	 * @param string $taxonomy The taxonomy name.
	 * @return WP_Term|null The term, or null when the taxonomy holds no term of that ID.
	 */
	private function get_term_in_taxonomy( int $term_id, string $taxonomy ): ?WP_Term {
		$term = get_term( $term_id, $taxonomy );

		return $term instanceof WP_Term && $taxonomy === $term->taxonomy ? $term : null;
	}

	/**
	 * Checks whether the current user may create terms in a taxonomy.
	 *
	 * Terms of a hierarchical taxonomy need the capability to edit terms; terms of a flat
	 * taxonomy, such as tags, the capability to assign them.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Taxonomy $taxonomy_object The taxonomy object.
	 * @return bool Whether the current user may create terms in the taxonomy.
	 */
	private function can_create_terms( WP_Taxonomy $taxonomy_object ): bool {
		return current_user_can( $taxonomy_object->hierarchical ? $taxonomy_object->cap->edit_terms : $taxonomy_object->cap->assign_terms );
	}

	/**
	 * Checks whether the current user may delete a term.
	 *
	 * The default term of a taxonomy can never be deleted, and the `delete_term` capability is
	 * denied for it. For a default term, the capability to delete terms of the taxonomy is
	 * checked instead, so that a user who could delete other terms learns that the term is
	 * the default one. Any other term needs the `delete_term` capability on its ID.
	 *
	 * @since 7.3.0
	 *
	 * @param int         $term_id         The term ID.
	 * @param WP_Taxonomy $taxonomy_object The taxonomy of the term.
	 * @return bool Whether the current user may delete the term.
	 */
	private function can_delete_term( int $term_id, WP_Taxonomy $taxonomy_object ): bool {
		if ( $this->is_default_term( $term_id, $taxonomy_object ) ) {
			return current_user_can( $taxonomy_object->cap->delete_terms );
		}

		return current_user_can( 'delete_term', $term_id );
	}

	/**
	 * Checks whether a term is the default term of its taxonomy, which wp_delete_term() refuses to delete.
	 *
	 * The default term is the term of the `default_category` option for categories, and the term
	 * of the `default_term_{$taxonomy}` option for a taxonomy registered with a default term. No
	 * other term is a default term.
	 *
	 * @since 7.3.0
	 *
	 * @param int         $term_id         The term ID.
	 * @param WP_Taxonomy $taxonomy_object The taxonomy of the term.
	 * @return bool Whether the term is the default term.
	 */
	private function is_default_term( int $term_id, WP_Taxonomy $taxonomy_object ): bool {
		if ( 'category' === $taxonomy_object->name && (int) get_option( 'default_category' ) === $term_id ) {
			return true;
		}

		return ! empty( $taxonomy_object->default_term ) && (int) get_option( 'default_term_' . $taxonomy_object->name ) === $term_id;
	}

	/**
	 * Executes the `core/terms-query` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error A page of terms, or a WP_Error on failure.
	 */
	private function execute_terms_query( array $input ) {
		$where           = $input['where'] ?? array();
		$taxonomy_object = $this->get_exposed_taxonomy( $where['taxonomy'] ?? null );
		if ( ! $taxonomy_object ) {
			return $this->taxonomy_not_found_error();
		}

		$taxonomy = $taxonomy_object->name;

		// Intentionally strict: a parent filter on a flat taxonomy is an error, not ignored.
		if ( array_key_exists( 'parent', $where ) && ! $taxonomy_object->hierarchical ) {
			return $this->invalid_filter_error(
				'where[parent]',
				/* translators: %s: Taxonomy name. */
				sprintf( __( 'The "%s" taxonomy is not hierarchical.' ), $taxonomy )
			);
		}

		/*
		 * Intentionally strict: the terms of a post are listed whether or not they are used,
		 * so hiding empty terms cannot be combined with the post filter.
		 */
		$hide_empty = true === ( $where['hide_empty'] ?? false );
		if ( $hide_empty && isset( $where['post'] ) ) {
			return $this->invalid_filter_error(
				'where[hide_empty]',
				/* translators: 1: Parameter, 2: Parameter. */
				sprintf( __( 'The %1$s filter cannot be combined with the %2$s filter.' ), 'hide_empty', 'post' )
			);
		}

		/*
		 * A post that is missing, does not use the taxonomy, or cannot be read gives the same
		 * error, so the filter never tells whether a post ID exists.
		 */
		$post = null;
		if ( isset( $where['post'] ) ) {
			// An ID that is not valid is never handed to get_post(), which reads 0 as the global post.
			$post_id = $this->parse_filter_int( $where['post'], 1 );
			$post    = null !== $post_id ? get_post( $post_id ) : null;
			if (
				! $post instanceof WP_Post
				|| ! is_object_in_taxonomy( $post->post_type, $taxonomy )
				|| ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post->ID ) )
			) {
				return $this->invalid_filter_error( 'where[post]', __( 'Invalid post ID.' ) );
			}
		}

		/*
		 * The schema bounds the page and the page size. Both are bounded here as well, as a
		 * filter can let input past the schema.
		 */
		$pagination = $input['pagination'] ?? array();
		$page       = $this->parse_filter_int( $pagination['page'] ?? 1, 1 ) ?? 1;
		$lists      = isset( $where['ids'] ) || isset( $where['slugs'] );
		$per_page   = min( self::MAX_PER_PAGE, $this->parse_filter_int( $pagination['per_page'] ?? null, 1 ) ?? ( $lists ? self::MAX_PER_PAGE : self::DEFAULT_PER_PAGE ) );

		/*
		 * No order is set, so terms are ordered by name, ascending, unless the taxonomy is
		 * registered with another order. Terms with equal names have no defined order.
		 */
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => $hide_empty,
		);
		if ( isset( $where['ids'] ) ) {
			// Entries that are not valid term IDs are left out; the list must keep at least one.
			$ids = array_values( array_filter( array_map( fn( $id ) => $this->parse_filter_int( $id, 1 ), $where['ids'] ) ) );
			if ( empty( $ids ) ) {
				return $this->invalid_filter_error(
					'where[ids]',
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter must list one or more valid term IDs.' ), 'ids' )
				);
			}
			$args['include'] = $ids;
		}
		if ( isset( $where['slugs'] ) ) {
			if ( empty( $where['slugs'] ) ) {
				return $this->invalid_filter_error(
					'where[slugs]',
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter must list one or more valid term slugs.' ), 'slugs' )
				);
			}
			$args['slug'] = $where['slugs'];
		}
		if ( isset( $where['parent'] ) ) {
			$parent = $this->parse_filter_int( $where['parent'], 0 );
			if ( null === $parent ) {
				return $this->invalid_filter_error( 'where[parent]', __( 'The parent filter must be a non-negative integer.' ) );
			}
			$args['parent'] = $parent;
		}

		/*
		 * The query arguments a taxonomy is registered with apply to its terms, as they do
		 * when the terms of a post are listed, which applies them itself. Paging is set
		 * afterwards, so on this path it is always the one requested.
		 */
		if ( ! $post && is_array( $taxonomy_object->args ) ) {
			$args = array_merge( $args, $taxonomy_object->args );
		}

		// The total counts every matching term, so no paging reaches the count, even from the taxonomy.
		$count_args = $args;
		unset( $count_args['number'], $count_args['offset'] );
		if ( $post ) {
			$count_args['object_ids'] = $post->ID;
		}

		$args['number'] = $per_page;
		$args['offset'] = ( $page - 1 ) * $per_page;

		$terms = $post ? wp_get_object_terms( $post->ID, $taxonomy, $args ) : get_terms( $args );
		$total = wp_count_terms( $count_args );
		$total = is_numeric( $total ) ? (int) $total : 0;

		$fields = $this->normalize_fields( $input );
		$items  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			/*
			 * Intentionally strict: anything but a term of the taxonomy, such as a term of another
			 * taxonomy that a query filter adds, is left out of the items.
			 */
			if ( ! $term instanceof WP_Term || $taxonomy !== $term->taxonomy ) {
				continue;
			}
			$items[] = $this->format_term( $term, $fields );
		}

		$total_pages = (int) ceil( $total / $per_page );

		/*
		 * Intentionally strict: a page past the last one does not exist, so it is reported as
		 * not found instead of returning a bare empty list. A page that holds terms is always
		 * returned, and a request that matches no term returns zero totals and no error.
		 */
		if ( empty( $items ) && $total > 0 && $page > $total_pages ) {
			return new WP_Error(
				'terms_invalid_page_number',
				__( 'The page number requested is larger than the number of pages available.' ),
				array( 'status' => 404 )
			);
		}

		return array(
			'items'      => $items,
			'pagination' => array(
				'total'       => $total,
				'total_pages' => $total_pages,
			),
		);
	}

	/**
	 * Executes the `core/term-create` ability.
	 *
	 * The taxonomy and the capability to create terms in it are checked first, then every
	 * attribute rule, and only then is the term saved, so a rejected call writes nothing.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error The created term, or a WP_Error on failure.
	 */
	private function execute_term_create( array $input ) {
		$data            = $input['data'] ?? array();
		$taxonomy_object = $this->get_exposed_taxonomy( $data['taxonomy'] ?? null );
		if ( ! $taxonomy_object ) {
			return $this->taxonomy_not_found_error();
		}

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! $this->can_create_terms( $taxonomy_object ) ) {
			return new WP_Error(
				'term_cannot_create',
				__( 'Sorry, you are not allowed to create terms in this taxonomy.' ),
				array( 'status' => 403 )
			);
		}

		$error = $this->check_attributes( $data, $taxonomy_object, null );
		if ( $error ) {
			return $error;
		}

		$args = $this->get_save_args( $data, array( 'slug', 'description', 'parent' ) );
		$name = (string) ( $data['name'] ?? '' );

		$result = wp_insert_term( wp_slash( $name ), $taxonomy_object->name, wp_slash( $args ) );

		return $this->prepare_save_result( $result, $taxonomy_object->name, $this->normalize_fields( $input ) );
	}

	/**
	 * Executes the `core/term-update` ability.
	 *
	 * The term is looked up first, then the capability to edit it is checked, then every
	 * attribute rule, and only then is the term saved, so a rejected call writes nothing.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error The updated term, or a WP_Error on failure.
	 */
	private function execute_term_update( array $input ) {
		$where           = $input['where'] ?? array();
		$term_id         = (int) ( $where['id'] ?? 0 );
		$taxonomy_object = $this->get_exposed_taxonomy( $where['taxonomy'] ?? null );
		if ( ! $taxonomy_object || ! $this->get_term_in_taxonomy( $term_id, $taxonomy_object->name ) ) {
			return $this->term_not_found_error();
		}

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return new WP_Error(
				'term_cannot_edit',
				__( 'Sorry, you are not allowed to edit this term.' ),
				array( 'status' => 403 )
			);
		}

		$data  = $input['data'] ?? array();
		$error = $this->check_attributes( $data, $taxonomy_object, $term_id );
		if ( $error ) {
			return $error;
		}

		$args   = $this->get_save_args( $data, array( 'name', 'slug', 'description', 'parent' ) );
		$result = wp_update_term( $term_id, $taxonomy_object->name, wp_slash( $args ) );

		return $this->prepare_save_result( $result, $taxonomy_object->name, $this->normalize_fields( $input ) );
	}

	/**
	 * Executes the `core/term-delete` ability.
	 *
	 * The term is looked up first, then the capability to delete it is checked. The outcome
	 * is confirmed against the stored term afterwards.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, string>|WP_Error The outcome, or a WP_Error on failure.
	 */
	private function execute_term_delete( array $input ) {
		$where           = $input['where'] ?? array();
		$term_id         = (int) ( $where['id'] ?? 0 );
		$taxonomy_object = $this->get_exposed_taxonomy( $where['taxonomy'] ?? null );
		if ( ! $taxonomy_object || ! $this->get_term_in_taxonomy( $term_id, $taxonomy_object->name ) ) {
			return $this->term_not_found_error();
		}

		$taxonomy = $taxonomy_object->name;

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! $this->can_delete_term( $term_id, $taxonomy_object ) ) {
			return new WP_Error(
				'term_cannot_delete',
				__( 'Sorry, you are not allowed to delete this term.' ),
				array( 'status' => 403 )
			);
		}

		if ( $this->is_default_term( $term_id, $taxonomy_object ) ) {
			return new WP_Error(
				'term_is_default',
				'category' === $taxonomy
					? __( 'The default category cannot be deleted.' )
					: __( 'The default term cannot be deleted.' ),
				array( 'status' => 400 )
			);
		}

		wp_delete_term( $term_id, $taxonomy );

		// The return value of the delete is not trusted: the term is looked up again.
		if ( $this->get_term_in_taxonomy( $term_id, $taxonomy ) ) {
			return new WP_Error(
				'term_delete_failed',
				__( 'The term cannot be deleted.' ),
				array( 'status' => 500 )
			);
		}

		return array( 'outcome' => 'deleted' );
	}

	/**
	 * Checks the `data` attributes of a create or update call.
	 *
	 * The first rule that fails is returned and nothing is written.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data            The `data` input.
	 * @param WP_Taxonomy  $taxonomy_object The taxonomy of the term being written.
	 * @param int|null     $term_id         The ID of the term on update, or null on create.
	 * @return WP_Error|null The error for the first attribute that cannot be applied, or null.
	 */
	private function check_attributes( array $data, WP_Taxonomy $taxonomy_object, ?int $term_id ): ?WP_Error {
		if ( array_key_exists( 'parent', $data ) ) {
			if ( ! $taxonomy_object->hierarchical ) {
				return $this->invalid_attribute_error( 'parent', __( 'Cannot set parent term, taxonomy is not hierarchical.' ) );
			}

			// Every failure gives the same error, so it never tells whether a term ID exists.
			$parent_id = (int) $data['parent'];
			if ( 0 !== $parent_id && ! $this->get_term_in_taxonomy( $parent_id, $taxonomy_object->name ) ) {
				return $this->invalid_attribute_error( 'parent', __( 'Parent term does not exist.' ) );
			}

			/*
			 * Intentionally strict: a parent that would make a loop is an error. The save would
			 * store no parent instead, or change the parents of other terms to break the loop.
			 */
			if (
				null !== $term_id && $parent_id > 0
				&& ( $term_id === $parent_id || wp_find_hierarchy_loop( 'wp_get_term_taxonomy_parent_id', $term_id, $parent_id, array( $taxonomy_object->name ) ) )
			) {
				return $this->invalid_attribute_error( 'parent', __( 'The parent term cannot be the term itself or one of its descendants.' ) );
			}
		}

		/*
		 * Intentionally strict: a slug that sanitizes to nothing or to "0" is an error. The save
		 * would read it as no slug and generate one from the name instead.
		 */
		if ( array_key_exists( 'slug', $data ) && empty( sanitize_title( (string) $data['slug'] ) ) ) {
			return $this->invalid_attribute_error( 'slug', __( 'The slug must contain characters that are valid in a slug.' ) );
		}

		return null;
	}

	/**
	 * Builds the arguments passed to the save from the allowed `data` attributes.
	 *
	 * Only the given attributes that were sent reach the save, each copied by name.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data       The `data` input.
	 * @param list<string> $attributes The attributes the save accepts.
	 * @return array<string, mixed> The unslashed save arguments.
	 */
	private function get_save_args( array $data, array $attributes ): array {
		$args = array();
		foreach ( $attributes as $attribute ) {
			if ( array_key_exists( $attribute, $data ) ) {
				$args[ $attribute ] = $data[ $attribute ];
			}
		}

		return $args;
	}

	/**
	 * Turns the result of a term save into the ability output.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed>|WP_Error $result   The result of wp_insert_term() or wp_update_term().
	 * @param string                        $taxonomy The taxonomy name.
	 * @param list<string>                  $fields   The requested fields.
	 * @return array<string, mixed>|WP_Error The saved term, or a WP_Error on failure.
	 */
	private function prepare_save_result( $result, string $taxonomy, array $fields ) {
		if ( is_wp_error( $result ) ) {
			return $this->prepare_save_error( $result );
		}

		// The save may give the term a new ID, so the term is read back with the returned one.
		$term = $this->get_term_in_taxonomy( (int) ( $result['term_id'] ?? 0 ), $taxonomy );
		if ( ! $term ) {
			return $this->term_not_found_error();
		}

		return $this->format_term( $term, $fields );
	}

	/**
	 * Builds the ability error for an error returned by a term save.
	 *
	 * The error keeps the code and message of the save error, and its data only says what
	 * the caller needs: the status, the input path of the attribute at fault, and for an
	 * existing term its ID. Nothing else of the save error, such as a database error, is
	 * passed on.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Error $error The error returned by wp_insert_term() or wp_update_term().
	 * @return WP_Error The ability error.
	 */
	private function prepare_save_error( WP_Error $error ): WP_Error {
		$code    = $error->get_error_code();
		$message = $error->get_error_message( $code );

		switch ( $code ) {
			case 'term_exists':
				$data = array(
					'status'  => 400,
					'term_id' => (int) $error->get_error_data( $code ),
				);
				break;
			case 'empty_term_name':
			case 'invalid_term_name':
				$data = array(
					'status' => 400,
					'params' => array( 'data[name]' => $message ),
				);
				break;
			case 'duplicate_term_slug':
				$data = array(
					'status' => 400,
					'params' => array( 'data[slug]' => $message ),
				);
				break;
			case 'db_insert_error':
			case 'db_update_error':
				$data = array( 'status' => 500 );
				break;
			default:
				$data = array( 'status' => 400 );
		}

		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Returns the requested fields, or the default fields when none are given.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<string> The requested fields.
	 */
	private function normalize_fields( array $input ): array {
		$fields = $input['fields'] ?? array();

		return empty( $fields ) ? self::DEFAULT_FIELDS : $fields;
	}

	/**
	 * Formats a term into the ability output shape.
	 *
	 * Shared by all term abilities. The `id` is always included, whether or not it was
	 * requested, and the other fields follow in a fixed order. `parent` is only returned
	 * for hierarchical taxonomies.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Term      $term   The term.
	 * @param list<string> $fields The requested fields.
	 * @return array<string, mixed> The formatted term.
	 */
	private function format_term( WP_Term $term, array $fields ): array {
		$data = array( 'id' => (int) $term->term_id );

		if ( in_array( 'taxonomy', $fields, true ) ) {
			$data['taxonomy'] = $term->taxonomy;
		}
		if ( in_array( 'name', $fields, true ) ) {
			$data['name'] = $term->name;
		}
		if ( in_array( 'slug', $fields, true ) ) {
			$data['slug'] = $term->slug;
		}
		if ( in_array( 'description', $fields, true ) ) {
			$data['description'] = $term->description;
		}
		if ( in_array( 'parent', $fields, true ) && is_taxonomy_hierarchical( $term->taxonomy ) ) {
			$data['parent'] = (int) $term->parent;
		}
		if ( in_array( 'count', $fields, true ) ) {
			$data['count'] = (int) $term->count;
		}
		if ( in_array( 'link', $fields, true ) ) {
			$link         = get_term_link( $term );
			$data['link'] = is_string( $link ) ? $link : '';
		}

		return $data;
	}

	/**
	 * Returns the term field definitions, keyed by field name in output order.
	 *
	 * The output schema uses the definitions directly, and the `fields` input lists their names.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> Term field definitions.
	 */
	private function get_term_properties(): array {
		return array(
			'id'          => array(
				'type'        => 'integer',
				'description' => __( 'The term ID.' ),
			),
			'taxonomy'    => array(
				'type'        => 'string',
				'description' => __( 'The taxonomy of the term.' ),
			),
			'name'        => array(
				'type'        => 'string',
				'description' => __( 'The term name, as stored.' ),
			),
			'slug'        => array(
				'type'        => 'string',
				'description' => __( 'The term slug.' ),
			),
			'description' => array(
				'type'        => 'string',
				'description' => __( 'The term description, as stored.' ),
			),
			'parent'      => array(
				'type'        => 'integer',
				'description' => __( 'The parent term ID, or 0 for a top-level term. Present for hierarchical taxonomies.' ),
			),
			'count'       => array(
				'type'        => 'integer',
				'description' => __( 'The number of objects the term is assigned to, as Core counts them.' ),
			),
			'link'        => array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'The URL of the term archive. Empty when the term has none.' ),
			),
		);
	}

	/**
	 * Builds the schema of the `fields` input, shared by all term abilities that return terms.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The `fields` JSON Schema.
	 */
	private function get_fields_input_schema(): array {
		return array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => array_keys( $this->get_term_properties() ),
			),
			'description' => sprintf(
				/* translators: %s: Comma-separated list of field names. */
				__( 'Limit each returned term to these fields. The `id` is always included. If omitted or empty, the default fields are returned: %s. `parent` is left out for terms of a taxonomy that is not hierarchical.' ),
				'`' . implode( '`, `', self::DEFAULT_FIELDS ) . '`'
			),
		);
	}

	/**
	 * Builds the `taxonomy` schema, shared by all term abilities.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies  Exposed taxonomy names.
	 * @param string       $description The description of the property.
	 * @return array<string, mixed> The `taxonomy` JSON Schema.
	 */
	private function get_taxonomy_schema( array $taxonomies, string $description ): array {
		return array(
			'type'        => 'string',
			'enum'        => $taxonomies,
			'description' => $description,
		);
	}

	/**
	 * Builds the input schema for the `core/terms-query` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_terms_query_input_schema( array $taxonomies ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'taxonomy' ),
					'properties'           => array(
						'taxonomy'   => $this->get_taxonomy_schema( $taxonomies, __( 'Taxonomy to query for terms.' ) ),
						'ids'        => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'description' => __( 'Limit the query to these term IDs. The order of the IDs does not affect the order of the results. If `pagination.per_page` is omitted, every matching term is returned on the first page.' ),
						),
						'slugs'      => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type'      => 'string',
								'minLength' => 1,
							),
							'description' => __( 'Limit the query to terms with these slugs. The order of the slugs does not affect the order of the results. If `pagination.per_page` is omitted, every matching term is returned on the first page.' ),
						),
						'parent'     => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Filter by parent term ID. Only supported for hierarchical taxonomies. Use 0 for top-level terms.' ),
						),
						'post'       => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Limit the query to the terms assigned to this post. The post must be readable and of a post type that uses the taxonomy.' ),
						),
						'hide_empty' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether to leave out terms that are not assigned to any post. Defaults to false. Cannot be combined with `post`.' ),
						),
					),
					'description'          => __( 'Selects the terms to return: the terms of a `taxonomy`, filtered by any of `ids`, `slugs`, `parent`, `post`, and `hide_empty`. A term must match every given filter.' ),
				),
				'fields'     => $this->get_fields_input_schema(),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return. Defaults to 1. Requesting a page beyond the last one is an error, unless no term matches the query: then any page returns empty `items` and zero totals.' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_PER_PAGE,
							'description' => sprintf(
								/* translators: 1: Default number of terms per page, 2: Maximum number of terms per page. */
								__( 'Maximum number of terms to return per page. Defaults to %1$d, or to %2$d when `where.ids` or `where.slugs` is given.' ),
								self::DEFAULT_PER_PAGE,
								self::MAX_PER_PAGE
							),
						),
					),
					'description'          => __( 'Selects the page of results to return.' ),
				),
			),
		);
	}

	/**
	 * Returns the `data` attributes shared by the create and update input schemas.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The attribute schemas, keyed by attribute name.
	 */
	private function get_term_write_properties(): array {
		return array(
			'name'        => array(
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 200,
				'description' => __( 'The term name. It is sanitized as a term name is when it is saved.' ),
			),
			'slug'        => array(
				'type'        => 'string',
				'minLength'   => 1,
				'description' => __( 'The term slug. It is sanitized as a term slug is, and made unique in the taxonomy on create and when the parent changes.' ),
			),
			'description' => array(
				'type'        => 'string',
				'description' => __( 'The term description.' ),
			),
			'parent'      => array(
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'The parent term ID: a term of the same taxonomy, or 0 for a top-level term. Only supported for hierarchical taxonomies.' ),
			),
		);
	}

	/**
	 * Builds the `where` schema that names one term, shared by the update and delete abilities.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies  Exposed taxonomy names.
	 * @param string       $description The description of `where`.
	 * @return array<string, mixed> The `where` JSON Schema.
	 */
	private function get_where_term_schema( array $taxonomies, string $description ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'taxonomy', 'id' ),
			'properties'           => array(
				'taxonomy' => $this->get_taxonomy_schema( $taxonomies, __( 'The taxonomy of the term.' ) ),
				'id'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The term ID.' ),
				),
			),
			'description'          => $description,
		);
	}

	/**
	 * Builds the input schema for the `core/term-create` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_term_create_input_schema( array $taxonomies ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'taxonomy', 'name' ),
					'properties'           => array_merge(
						array(
							'taxonomy' => $this->get_taxonomy_schema( $taxonomies, __( 'The taxonomy of the term to create.' ) ),
						),
						$this->get_term_write_properties()
					),
					'description'          => __( 'The term to create.' ),
				),
				'fields' => $this->get_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/term-update` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_term_update_input_schema( array $taxonomies ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => $this->get_where_term_schema( $taxonomies, __( 'Selects the term to update, by its taxonomy and ID.' ) ),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => $this->get_term_write_properties(),
					'description'          => __( 'The attributes to change. Attributes that are left out keep their stored values.' ),
				),
				'fields' => $this->get_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/term-delete` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $taxonomies Exposed taxonomy names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_term_delete_input_schema( array $taxonomies ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where' => $this->get_where_term_schema( $taxonomies, __( 'Selects the term to delete, by its taxonomy and ID.' ) ),
			),
		);
	}

	/**
	 * Builds the output schema of a single term, shared by all term abilities.
	 *
	 * Only `id` is required, because it is always returned. The other fields are optional
	 * because the `fields` input lets the caller request any subset.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The term JSON Schema.
	 */
	private function get_term_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => $this->get_term_properties(),
		);
	}

	/**
	 * Builds the output schema for the `core/terms-query` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_terms_query_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items', 'pagination' ),
			'properties'           => array(
				'items'      => array(
					'type'        => 'array',
					'description' => __( 'The terms on the requested page, ordered by name unless the taxonomy is registered with another order.' ),
					'items'       => $this->get_term_output_schema(),
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'total', 'total_pages' ),
					'properties'           => array(
						'total'       => array(
							'type'        => 'integer',
							'description' => __( 'Total number of terms matching the query, across all pages.' ),
						),
						'total_pages' => array(
							'type'        => 'integer',
							'description' => __( 'Total number of result pages.' ),
						),
					),
					'description'          => __( 'The totals of the request, across all pages.' ),
				),
			),
		);
	}

	/**
	 * Builds the output schema for the `core/term-delete` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_term_delete_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'outcome' ),
			'properties'           => array(
				'outcome' => array(
					'type'        => 'string',
					'description' => __( 'What happened to the term: `deleted` when it was deleted permanently.' ),
				),
			),
		);
	}

	/**
	 * Builds the error for a taxonomy that is not exposed to abilities.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The taxonomy not found error.
	 */
	private function taxonomy_not_found_error(): WP_Error {
		return new WP_Error(
			'terms_taxonomy_not_found',
			__( 'The requested taxonomy was not found.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds the error for a term that cannot be found.
	 *
	 * Returned alike for a missing term, a term of another taxonomy, and a taxonomy that is
	 * not exposed to abilities, so it never tells which one applies.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The term not found error.
	 */
	private function term_not_found_error(): WP_Error {
		return new WP_Error(
			'term_not_found',
			__( 'The requested term was not found.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds the error for a filter that cannot be honored.
	 *
	 * The error data maps the filter's input path to the message under `params`, so callers
	 * can tell which filter failed without parsing the translated message.
	 *
	 * @since 7.3.0
	 *
	 * @param string $param   The filter's input path, such as `where[parent]`.
	 * @param string $message The error message.
	 * @return WP_Error The invalid filter error.
	 */
	private function invalid_filter_error( string $param, string $message ): WP_Error {
		return new WP_Error(
			'terms_invalid_filter',
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Builds the error for a `data` attribute that cannot be applied.
	 *
	 * The error data maps the attribute's input path, such as `data[parent]`, to the message
	 * under `params`.
	 *
	 * @since 7.3.0
	 *
	 * @param string $attribute The attribute name, such as `parent`.
	 * @param string $message   The error message.
	 * @return WP_Error The invalid attribute error.
	 */
	private function invalid_attribute_error( string $attribute, string $message ): WP_Error {
		return new WP_Error(
			'term_invalid_attribute',
			$message,
			array(
				'status' => 400,
				'params' => array( "data[{$attribute}]" => $message ),
			)
		);
	}
}
