<?php
/**
 * Registers core post abilities.
 * This is a utility class to encapsulate the registration of post-related abilities.
 * And reuse shared logic and schemas between the abilities.
 * It is not intended to be instantiated or consumed directly by any other code or plugin.
 *
 * @internal This class is not part of the public API.
 * @access private
 */
class WP_Posts_Abilities {

	/**
	 * Available public post types.
	 *
	 * @var array
	 */
	private static $available_post_types;

	/**
	 * Description string of available post types.
	 *
	 * @var string
	 */
	private static $available_post_types_desc;

	/**
	 * Shared output schema for basic post response.
	 *
	 * @var array
	 */
	private static $post_output_schema;

	/**
	 * Shared input schema properties for post fields.
	 *
	 * @var array
	 */
	private static $post_fields_schema;

	/**
	 * Initializes shared data.
	 *
	 * @return void
	 */
	private static function init(): void {
		self::$available_post_types      = array_values( (array) get_post_types( array( 'public' => true ), 'names' ) );
		self::$available_post_types_desc = empty( self::$available_post_types )
			? __( 'none' )
			: implode( ', ', self::$available_post_types );

		self::$post_output_schema = array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'post_type'      => array( 'type' => 'string' ),
				'status'         => array( 'type' => 'string' ),
				'link'           => array( 'type' => 'string' ),
				'title'          => array( 'type' => 'string' ),
				'content'        => array( 'type' => 'string' ),
				'excerpt'        => array( 'type' => 'string' ),
				'date'           => array( 'type' => 'string' ),
				'date_gmt'       => array( 'type' => 'string' ),
				'modified'       => array( 'type' => 'string' ),
				'modified_gmt'   => array( 'type' => 'string' ),
				'slug'           => array( 'type' => 'string' ),
				'author'         => array( 'type' => 'integer' ),
				'comment_status' => array( 'type' => 'string' ),
				'ping_status'    => array( 'type' => 'string' ),
				'sticky'         => array( 'type' => 'boolean' ),
				'template'       => array( 'type' => 'string' ),
			),
		);

		self::$post_fields_schema = array(
			'title'          => array(
				'type'        => 'string',
				'description' => __( 'Post title.' ),
			),
			'content'        => array(
				'type'        => 'string',
				'description' => __( 'Post content as HTML. Include WordPress block comments (<!-- wp:blockname {"attr":"value"} -->) for full block editor compatibility. Use wpmcp/list-block-types to get valid block names and attributes.' ),
			),
			'excerpt'        => array(
				'type'        => 'string',
				'description' => __( 'Post excerpt.' ),
			),
			'status'         => array(
				'type'        => 'string',
				'description' => __( 'Post status (draft, publish, etc).' ),
				'default'     => 'draft',
			),
			'author'         => array(
				'type'        => 'integer',
				'description' => __( 'Author user ID.' ),
			),
			'meta'           => array(
				'type'                 => 'object',
				'description'          => __( 'Meta fields to set on the post.' ),
				'additionalProperties' => true,
			),
			'tax_input'      => array(
				'type'                 => 'object',
				'description'          => __( 'Taxonomy terms mapping (taxonomy => term IDs or slugs).' ),
				'additionalProperties' => true,
			),
			'date'           => array(
				'type'        => 'string',
				'description' => __( 'Post date in YYYY-MM-DD HH:MM:SS format (site timezone).' ),
			),
			'date_gmt'       => array(
				'type'        => 'string',
				'description' => __( 'Post date in YYYY-MM-DD HH:MM:SS format (GMT).' ),
			),
			'comment_status' => array(
				'type'        => 'string',
				'description' => __( 'Whether comments are allowed.' ),
				'enum'        => array( 'open', 'closed' ),
			),
			'ping_status'    => array(
				'type'        => 'string',
				'description' => __( 'Whether pingbacks/trackbacks are allowed.' ),
				'enum'        => array( 'open', 'closed' ),
			),
			'password'       => array(
				'type'        => 'string',
				'description' => __( 'Password to protect the post.' ),
			),
			'parent'         => array(
				'type'        => 'integer',
				'description' => __( 'Parent post ID for hierarchical post types.' ),
			),
			'menu_order'     => array(
				'type'        => 'integer',
				'description' => __( 'Order value for sorting.' ),
			),
			'categories'     => array(
				'type'        => 'array',
				'description' => __( 'Category IDs or slugs to assign.' ),
				'items'       => array( 'type' => array( 'integer', 'string' ) ),
			),
			'tags'           => array(
				'type'        => 'array',
				'description' => __( 'Tag IDs or slugs to assign.' ),
				'items'       => array( 'type' => array( 'integer', 'string' ) ),
			),
			'template'       => array(
				'type'        => 'string',
				'description' => __( 'Page template file to use (e.g., "templates/full-width.php").' ),
			),
			'slug'           => array(
				'type'        => 'string',
				'description' => __( 'Alphanumeric identifier for the post.' ),
			),
		);
	}

	/**
	 * Registers all post abilities.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::init();
		self::register_create_post();
		self::register_get_post();
		self::register_find_posts();
		self::register_update_post();
	}

	/**
	 * Formats a post for output.
	 *
	 * @param WP_Post $post               The post object.
	 * @param bool    $include_taxonomies Whether to include taxonomy terms.
	 * @param bool    $include_meta       Whether to include meta fields.
	 * @return array Formatted post data.
	 */
	private static function format_post_output( WP_Post $post, bool $include_taxonomies = false, bool $include_meta = false ): array {
		$result = array(
			'id'             => $post->ID,
			'post_type'      => $post->post_type,
			'status'         => $post->post_status,
			'link'           => (string) get_permalink( $post->ID ),
			'title'          => (string) $post->post_title,
			'content'        => (string) $post->post_content,
			'excerpt'        => (string) $post->post_excerpt,
			'date'           => (string) $post->post_date,
			'date_gmt'       => (string) $post->post_date_gmt,
			'modified'       => (string) $post->post_modified,
			'modified_gmt'   => (string) $post->post_modified_gmt,
			'slug'           => (string) $post->post_name,
			'author'         => (int) $post->post_author,
			'comment_status' => (string) $post->comment_status,
			'ping_status'    => (string) $post->ping_status,
			'sticky'         => is_sticky( $post->ID ),
			'template'       => (string) get_page_template_slug( $post ),
		);

		if ( $include_taxonomies ) {
			$result['taxonomies'] = self::build_taxonomy_output( $post->ID, $post->post_type );
		}

		if ( $include_meta ) {
			$result['meta'] = get_post_meta( $post->ID );
		}

		return $result;
	}

	/**
	 * Sanitizes meta input values recursively.
	 *
	 * Ensures all meta values are scalar types (string, int, float, bool)
	 * or arrays of scalar types. Prevents object injection and ensures
	 * string values are sanitized.
	 *
	 * @param array $meta The meta input array.
	 * @return array Sanitized meta array.
	 */
	private static function sanitize_meta_input( array $meta ): array {
		$sanitized = array();

		foreach ( $meta as $key => $value ) {
			// Sanitize meta key.
			$sanitized_key = sanitize_key( (string) $key );
			if ( empty( $sanitized_key ) ) {
				continue;
			}

			// Sanitize meta value.
			$sanitized[ $sanitized_key ] = self::sanitize_meta_value( $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitizes a single meta value.
	 *
	 * @param mixed $value The meta value to sanitize.
	 * @return mixed Sanitized value (scalar or array of scalars).
	 */
	private static function sanitize_meta_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_meta_value' ), $value );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
			return $value;
		}

		// Reject non-scalar values (objects, resources, etc.).
		return '';
	}

	/**
	 * Resolves taxonomy terms from IDs or slugs.
	 *
	 * @param mixed  $terms_in Terms as IDs, slugs, or names.
	 * @param string $taxonomy The taxonomy name.
	 * @return array Array of resolved term IDs.
	 */
	private static function resolve_taxonomy_terms( $terms_in, string $taxonomy ): array {
		$term_ids = array();
		$terms_in = is_array( $terms_in ) ? $terms_in : array( $terms_in );

		foreach ( $terms_in as $t ) {
			if ( is_numeric( $t ) ) {
				$term_ids[] = (int) $t;
				continue;
			}
			if ( ! is_string( $t ) ) {
				continue;
			}
			$term = get_term_by( 'slug', $t, $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'name', $t, $taxonomy );
			}
			if ( $term instanceof WP_Term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		return $term_ids;
	}

	/**
	 * Builds taxonomy output for a post.
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $post_type The post type.
	 * @return array Taxonomy terms organized by taxonomy.
	 */
	private static function build_taxonomy_output( int $post_id, string $post_type ): array {
		$taxonomies      = get_object_taxonomies( $post_type, 'names' );
		$taxonomy_output = array();

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$taxonomy_output[ $taxonomy ] = array_map(
				static function ( $term ) {
					return array(
						'id'     => $term->term_id,
						'name'   => $term->name,
						'slug'   => $term->slug,
						'parent' => $term->parent,
					);
				},
				$terms
			);
		}

		return $taxonomy_output;
	}

	/**
	 * Generic recursive processor for nested query structures.
	 *
	 * @param array         $input             The query input (with queries/relation structure).
	 * @param callable      $process_leaf      Callback to process leaf clauses. Returns array or null.
	 * @param callable|null $process_top_level Optional callback for top-level fields (e.g., column).
	 * @return array WordPress-compatible query array.
	 */
	private static function process_query_recursive( array $input, callable $process_leaf, ?callable $process_top_level = null ): array {
		$result = array();

		if ( ! empty( $input['relation'] ) && in_array( $input['relation'], array( 'AND', 'OR' ), true ) ) {
			$result['relation'] = $input['relation'];
		}

		if ( $process_top_level ) {
			$process_top_level( $input, $result );
		}

		if ( ! empty( $input['queries'] ) && is_array( $input['queries'] ) ) {
			foreach ( $input['queries'] as $query ) {
				if ( ! is_array( $query ) ) {
					continue;
				}
				if ( isset( $query['queries'] ) ) {
					$nested = self::process_query_recursive( $query, $process_leaf, $process_top_level );
					if ( ! empty( $nested ) ) {
						$result[] = $nested;
					}
				} else {
					$clause = $process_leaf( $query );
					if ( null !== $clause ) {
						$result[] = $clause;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Process a meta_query leaf clause.
	 *
	 * @param array $query The clause data.
	 * @return array|null The processed clause or null if invalid.
	 */
	private static function process_meta_clause( array $query ): ?array {
		if ( empty( $query['key'] ) ) {
			return null;
		}
		$clause = array(
			'key'     => sanitize_key( (string) $query['key'] ),
			'compare' => isset( $query['compare'] ) ? sanitize_text_field( (string) $query['compare'] ) : '=',
		);
		if ( array_key_exists( 'value', $query ) ) {
			$clause['value'] = $query['value'];
		}
		if ( ! empty( $query['type'] ) ) {
			$clause['type'] = sanitize_text_field( (string) $query['type'] );
		}
		return $clause;
	}

	/**
	 * Process a tax_query leaf clause.
	 *
	 * @param array $query The clause data.
	 * @return array|null The processed clause or null if invalid.
	 */
	private static function process_tax_clause( array $query ): ?array {
		if ( empty( $query['taxonomy'] ) || ! isset( $query['terms'] ) ) {
			return null;
		}
		$taxonomy = sanitize_key( (string) $query['taxonomy'] );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$clause = array(
			'taxonomy' => $taxonomy,
			'terms'    => is_array( $query['terms'] ) ? $query['terms'] : array( $query['terms'] ),
			'field'    => isset( $query['field'] ) && in_array( $query['field'], array( 'term_id', 'slug', 'name', 'term_taxonomy_id' ), true )
				? $query['field'] : 'term_id',
			'operator' => isset( $query['operator'] ) && in_array( $query['operator'], array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' ), true )
				? $query['operator'] : 'IN',
		);
		if ( isset( $query['include_children'] ) ) {
			$clause['include_children'] = (bool) $query['include_children'];
		}
		return $clause;
	}

	/**
	 * Process a date_query leaf clause.
	 *
	 * @param array $query The clause data.
	 * @return array|null The processed clause or null if empty.
	 */
	private static function process_date_clause( array $query ): ?array {
		$clause     = array();
		$int_fields = array( 'year', 'month', 'week', 'day', 'hour', 'minute', 'second', 'dayofweek', 'dayofweek_iso', 'dayofyear' );
		foreach ( $int_fields as $field ) {
			if ( isset( $query[ $field ] ) ) {
				$clause[ $field ] = (int) $query[ $field ];
			}
		}
		if ( isset( $query['after'] ) ) {
			$clause['after'] = is_array( $query['after'] ) ? array_map( 'intval', $query['after'] ) : sanitize_text_field( (string) $query['after'] );
		}
		if ( isset( $query['before'] ) ) {
			$clause['before'] = is_array( $query['before'] ) ? array_map( 'intval', $query['before'] ) : sanitize_text_field( (string) $query['before'] );
		}
		if ( isset( $query['inclusive'] ) ) {
			$clause['inclusive'] = (bool) $query['inclusive'];
		}
		if ( ! empty( $query['compare'] ) && in_array( $query['compare'], array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
			$clause['compare'] = $query['compare'];
		}
		if ( ! empty( $query['column'] ) && in_array( $query['column'], array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ), true ) ) {
			$clause['column'] = $query['column'];
		}
		return ! empty( $clause ) ? $clause : null;
	}

	/**
	 * Process date_query top-level fields.
	 *
	 * @param array $input  The input data.
	 * @param array $result The result array (passed by reference).
	 */
	private static function process_date_top_level( array $input, array &$result ): void {
		if ( ! empty( $input['column'] ) && in_array( $input['column'], array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ), true ) ) {
			$result['column'] = $input['column'];
		}
	}

	/**
	 * Checks taxonomy assignment permissions.
	 *
	 * @param array  $input     The input data.
	 * @param string $post_type The post type.
	 * @return bool True if user has permission, false otherwise.
	 */
	private static function check_taxonomy_permissions( array $input, string $post_type ): bool {
		// Merge categories and tags into tax_input for unified processing.
		$tax_input = isset( $input['tax_input'] ) && is_array( $input['tax_input'] ) ? $input['tax_input'] : array();
		if ( ! empty( $input['categories'] ) && is_array( $input['categories'] ) ) {
			$tax_input['category'] = $input['categories'];
		}
		if ( ! empty( $input['tags'] ) && is_array( $input['tags'] ) ) {
			$tax_input['post_tag'] = $input['tags'];
		}

		if ( empty( $tax_input ) ) {
			return true;
		}

		$supported_taxonomies = get_object_taxonomies( $post_type, 'names' );
		foreach ( $tax_input as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			if ( ! in_array( $taxonomy, $supported_taxonomies, true ) ) {
				continue;
			}
			$taxonomy_obj = get_taxonomy( $taxonomy );
			if ( $taxonomy_obj && ! current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks status change permissions.
	 *
	 * @param array  $input The input data.
	 * @param object $pto   The post type object.
	 * @return bool True if user has permission, false otherwise.
	 */
	private static function check_status_permissions( array $input, object $pto ): bool {
		if ( ! isset( $input['status'] ) ) {
			return true;
		}
		$status = sanitize_key( (string) $input['status'] );
		if ( in_array( $status, array( 'publish', 'private', 'future' ), true ) ) {
			if ( ! current_user_can( $pto->cap->publish_posts ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks author change permissions.
	 *
	 * @param array    $input          The input data.
	 * @param object   $pto            The post type object.
	 * @param int|null $current_author The current author ID (for updates), or null (for creates).
	 * @return bool True if user has permission, false otherwise.
	 */
	private static function check_author_permissions( array $input, object $pto, ?int $current_author = null ): bool {
		if ( empty( $input['author'] ) ) {
			return true;
		}
		$new_author = (int) $input['author'];
		$compare_to = $current_author ?? get_current_user_id();
		if ( $new_author !== $compare_to ) {
			if ( ! current_user_can( $pto->cap->edit_others_posts ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Processes and resolves tax_input.
	 *
	 * @param array  $input     The input data.
	 * @param string $post_type The post type.
	 * @return array Resolved taxonomy input.
	 */
	private static function process_tax_input( array $input, string $post_type ): array {
		// Merge categories and tags into tax_input for unified processing.
		if ( ! isset( $input['tax_input'] ) ) {
			$input['tax_input'] = array();
		}
		if ( ! empty( $input['categories'] ) && is_array( $input['categories'] ) ) {
			$input['tax_input']['category'] = $input['categories'];
		}
		if ( ! empty( $input['tags'] ) && is_array( $input['tags'] ) ) {
			$input['tax_input']['post_tag'] = $input['tags'];
		}

		if ( empty( $input['tax_input'] ) || ! is_array( $input['tax_input'] ) ) {
			return array();
		}

		$supported_taxonomies = get_object_taxonomies( $post_type, 'names' );
		$resolved_tax_input   = array();

		foreach ( $input['tax_input'] as $taxonomy => $terms_in ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			if ( ! in_array( $taxonomy, $supported_taxonomies, true ) ) {
				continue;
			}

			$term_ids = self::resolve_taxonomy_terms( $terms_in, $taxonomy );

			if ( ! empty( $term_ids ) ) {
				$resolved_tax_input[ $taxonomy ] = $term_ids;
			}
		}

		return $resolved_tax_input;
	}

	/**
	 * Builds post array from input with sanitization.
	 *
	 * @param array  $input     The input data.
	 * @param string $post_type The post type.
	 * @param bool   $is_update Whether this is an update (uses array_key_exists) or create (uses ! empty).
	 * @return array|WP_Error The sanitized post array, or WP_Error on validation failure.
	 */
	private static function build_postarr( array $input, string $post_type, bool $is_update = false ): array|WP_Error {
		$postarr = array();

		// Helper to check if field should be processed.
		$has_field = $is_update
			? fn( $key ) => array_key_exists( $key, $input )
			: fn( $key ) => ! empty( $input[ $key ] );

		if ( $has_field( 'title' ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( $has_field( 'content' ) ) {
			$postarr['post_content'] = wp_kses_post( (string) $input['content'] );
		}
		if ( $has_field( 'excerpt' ) ) {
			$postarr['post_excerpt'] = wp_kses_post( (string) $input['excerpt'] );
		}
		if ( $has_field( 'status' ) ) {
			$status = sanitize_key( (string) $input['status'] );
			if ( get_post_status_object( $status ) ) {
				$postarr['post_status'] = $status;
			} elseif ( ! $is_update ) {
				$postarr['post_status'] = 'draft';
			}
		} elseif ( ! $is_update ) {
			$postarr['post_status'] = 'draft';
		}
		if ( $has_field( 'author' ) ) {
			$author_id = (int) $input['author'];
			if ( $author_id && ! get_userdata( $author_id ) ) {
				return new WP_Error(
					'ability_invalid_author',
					__( 'Invalid author ID.' )
				);
			}
			if ( $author_id ) {
				$postarr['post_author'] = $author_id;
			}
		}
		if ( $has_field( 'meta' ) && is_array( $input['meta'] ) ) {
			$postarr['meta_input'] = self::sanitize_meta_input( $input['meta'] );
		}
		if ( $has_field( 'date' ) ) {
			$postarr['post_date'] = sanitize_text_field( (string) $input['date'] );
		}
		if ( $has_field( 'date_gmt' ) ) {
			$postarr['post_date_gmt'] = sanitize_text_field( (string) $input['date_gmt'] );
		}
		if ( $has_field( 'comment_status' ) ) {
			$postarr['comment_status'] = in_array( $input['comment_status'], array( 'open', 'closed' ), true )
				? $input['comment_status']
				: 'closed';
		}
		if ( $has_field( 'ping_status' ) ) {
			$postarr['ping_status'] = in_array( $input['ping_status'], array( 'open', 'closed' ), true )
				? $input['ping_status']
				: 'closed';
		}
		if ( $has_field( 'password' ) ) {
			$postarr['post_password'] = sanitize_text_field( (string) $input['password'] );
		}
		if ( $has_field( 'parent' ) ) {
			$parent_id = (int) $input['parent'];
			if ( $parent_id && ! get_post( $parent_id ) ) {
				return new WP_Error(
					'ability_invalid_parent',
					__( 'Invalid parent post ID.' )
				);
			}
			$postarr['post_parent'] = $parent_id;
		}
		if ( $has_field( 'menu_order' ) ) {
			$postarr['menu_order'] = (int) $input['menu_order'];
		}
		if ( $has_field( 'template' ) ) {
			$template          = sanitize_text_field( (string) $input['template'] );
			$valid_templates   = array_keys( wp_get_theme()->get_page_templates( null, $post_type ) );
			$valid_templates[] = ''; // Allow empty template.
			if ( ! in_array( $template, $valid_templates, true ) ) {
				return new WP_Error(
					'ability_invalid_template',
					__( 'Invalid template.' )
				);
			}
			$postarr['page_template'] = $template;
		}
		if ( $has_field( 'slug' ) ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}

		// Process taxonomy terms.
		$has_taxonomy_input = array_key_exists( 'tax_input', $input )
			|| array_key_exists( 'categories', $input )
			|| array_key_exists( 'tags', $input );

		if ( $has_taxonomy_input || ! $is_update ) {
			$resolved_tax_input = self::process_tax_input( $input, $post_type );
			if ( ! empty( $resolved_tax_input ) ) {
				$postarr['tax_input'] = $resolved_tax_input;
			}
		}

		return $postarr;
	}

	/**
	 * Registers the core/create-post ability.
	 *
	 * @return void
	 */
	private static function register_create_post(): void {
		wp_register_ability(
			'core/create-post',
			array(
				'label'               => __( 'Create Post' ),
				'description'         => sprintf(
					/* translators: %s: comma-separated list of available post types */
					__( 'Create a WordPress post for any post type using HTML content. Supports WordPress block comments for full editor compatibility. Use list-block-types first to get available blocks and their attributes. Available post types: %s.' ),
					self::$available_post_types_desc
				),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'post_type' ),
					'properties' => array_merge(
						array(
							'post_type' => array(
								'type'        => 'string',
								'description' => __( 'The post type to create.' ),
								'enum'        => self::$available_post_types,
							),
						),
						self::$post_fields_schema
					),
				),
				'output_schema'       => self::$post_output_schema,
				'permission_callback' => function ( $input = array() ): bool {
					$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : '';
					if ( ! $post_type || ! post_type_exists( $post_type ) ) {
						return false;
					}
					$pto = get_post_type_object( $post_type );
					if ( ! $pto ) {
						return false;
					}
					$cap = $pto->cap->create_posts ?? $pto->cap->edit_posts;
					if ( ! current_user_can( $cap ) ) {
						return false;
					}
					if ( ! self::check_status_permissions( $input, $pto ) ) {
						return false;
					}
					if ( ! self::check_author_permissions( $input, $pto ) ) {
						return false;
					}
					if ( ! self::check_taxonomy_permissions( $input, $post_type ) ) {
						return false;
					}
					return true;
				},
				'execute_callback'    => function ( $input = array() ) {
					$post_type = sanitize_key( (string) $input['post_type'] );
					if ( ! post_type_exists( $post_type ) ) {
						return new WP_Error(
							'ability_core-create-post_invalid_post_type',
							/* translators: %s: post type name. */
							sprintf( __( 'Post type "%s" does not exist.' ), esc_html( $post_type ) )
						);
					}

					$postarr = self::build_postarr( $input, $post_type, false );
					if ( is_wp_error( $postarr ) ) {
						return $postarr;
					}
					$postarr['post_type'] = $post_type;

					$post_id = wp_insert_post( $postarr, true );
					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					$post = get_post( $post_id );
					if ( ! $post ) {
						return new WP_Error(
							'ability_core-create-post_creation_failed',
							__( 'Post created but could not be loaded.' )
						);
					}

					return self::format_post_output( $post );
				},
				'category'            => 'post',
				'meta'                => array(
					'annotations'  => array(
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Registers the core/get-post ability.
	 *
	 * @return void
	 */
	private static function register_get_post(): void {
		wp_register_ability(
			'core/get-post',
			array(
				'label'               => __( 'Get Post' ),
				'description'         => __( 'Retrieve a single WordPress post by ID. Returns post data including title, content, excerpt, status, and optionally taxonomies and meta fields.' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'                 => array(
							'type'        => 'integer',
							'description' => __( 'The post ID to retrieve.' ),
						),
						'include_taxonomies' => array(
							'type'        => 'boolean',
							'description' => __( 'Include taxonomy terms attached to the post.' ),
							'default'     => false,
						),
						'include_meta'       => array(
							'type'        => 'boolean',
							'description' => __( 'Include post meta fields.' ),
							'default'     => false,
						),
					),
				),
				'output_schema'       => array_merge(
					self::$post_output_schema,
					array(
						'properties' => array_merge(
							self::$post_output_schema['properties'],
							array(
								'taxonomies' => array(
									'type'                 => 'object',
									'additionalProperties' => array(
										'type'  => 'array',
										'items' => array(
											'type'       => 'object',
											'properties' => array(
												'id'     => array( 'type' => 'integer' ),
												'name'   => array( 'type' => 'string' ),
												'slug'   => array( 'type' => 'string' ),
												'parent' => array( 'type' => 'integer' ),
											),
										),
									),
								),
								'meta'       => array(
									'type'                 => 'object',
									'additionalProperties' => true,
								),
							)
						),
					)
				),
				'permission_callback' => function ( $input = array() ): bool {
					$post_id = isset( $input['id'] ) ? (int) $input['id'] : 0;
					if ( ! $post_id ) {
						return false;
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return false;
					}
					return current_user_can( 'read_post', $post_id );
				},
				'execute_callback'    => function ( $input = array() ) {
					$post_id = (int) $input['id'];
					$post    = get_post( $post_id );
					if ( ! $post ) {
						return new WP_Error(
							'ability_core-get-post_not_found',
							/* translators: %d: post ID. */
							sprintf( __( 'Post with ID %d not found.' ), $post_id )
						);
					}

					$include_taxonomies = ! empty( $input['include_taxonomies'] );
					$include_meta       = ! empty( $input['include_meta'] );

					return self::format_post_output( $post, $include_taxonomies, $include_meta );
				},
				'category'            => 'post',
				'meta'                => array(
					'annotations'  => array(
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Registers the core/find-posts ability.
	 *
	 * @return void
	 */
	private static function register_find_posts(): void {
		$meta_query_schema = array(
			'type'        => 'object',
			'description' => __( 'Meta query with nested AND/OR support. Use relation at any level.' ),
			'properties'  => array(
				'relation' => array(
					'type'        => 'string',
					'enum'        => array( 'AND', 'OR' ),
					'description' => __( 'Logical relationship between queries at this level.' ),
				),
				'queries'  => array(
					'type'        => 'array',
					'description' => __( 'Array of meta clauses or nested query groups.' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'key'      => array(
								'type'        => 'string',
								'description' => __( 'Meta key to query.' ),
							),
							'value'    => array(
								'description' => __( 'Meta value to compare.' ),
							),
							'compare'  => array(
								'type'        => 'string',
								'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ),
								'description' => __( 'Comparison operator.' ),
							),
							'type'     => array(
								'type'        => 'string',
								'enum'        => array( 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED' ),
								'description' => __( 'Value type for comparison.' ),
							),
							'relation' => array(
								'type'        => 'string',
								'enum'        => array( 'AND', 'OR' ),
								'description' => __( 'Relation for nested group.' ),
							),
							'queries'  => array(
								'type'        => 'array',
								'description' => __( 'Nested meta queries.' ),
							),
						),
					),
				),
			),
		);

		$tax_query_schema = array(
			'type'        => 'object',
			'description' => __( 'Taxonomy query with nested AND/OR support.' ),
			'properties'  => array(
				'relation' => array(
					'type'        => 'string',
					'enum'        => array( 'AND', 'OR' ),
					'description' => __( 'Logical relationship between queries at this level.' ),
				),
				'queries'  => array(
					'type'        => 'array',
					'description' => __( 'Array of taxonomy clauses or nested query groups.' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'taxonomy'         => array(
								'type'        => 'string',
								'description' => __( 'Taxonomy slug.' ),
							),
							'field'            => array(
								'type'        => 'string',
								'enum'        => array( 'term_id', 'slug', 'name', 'term_taxonomy_id' ),
								'description' => __( 'Field to match terms by.' ),
							),
							'terms'            => array(
								'description' => __( 'Term(s) to match.' ),
							),
							'operator'         => array(
								'type'        => 'string',
								'enum'        => array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' ),
								'description' => __( 'Comparison operator.' ),
							),
							'include_children' => array(
								'type'        => 'boolean',
								'description' => __( 'Include child terms.' ),
							),
							'relation'         => array(
								'type'        => 'string',
								'enum'        => array( 'AND', 'OR' ),
								'description' => __( 'Relation for nested group.' ),
							),
							'queries'          => array(
								'type'        => 'array',
								'description' => __( 'Nested taxonomy queries.' ),
							),
						),
					),
				),
			),
		);

		$date_query_schema = array(
			'type'        => 'object',
			'description' => __( 'Date query with nested AND/OR support.' ),
			'properties'  => array(
				'relation' => array(
					'type'        => 'string',
					'enum'        => array( 'AND', 'OR' ),
					'description' => __( 'Logical relationship between queries at this level.' ),
				),
				'column'   => array(
					'type'        => 'string',
					'enum'        => array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ),
					'description' => __( 'Date column to query against.' ),
				),
				'queries'  => array(
					'type'        => 'array',
					'description' => __( 'Array of date clauses or nested query groups.' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'year'           => array(
								'type'        => 'integer',
								'description' => __( 'Year (4 digits).' ),
							),
							'month'          => array(
								'type'        => 'integer',
								'description' => __( 'Month (1-12).' ),
							),
							'week'           => array(
								'type'        => 'integer',
								'description' => __( 'Week of year (1-53).' ),
							),
							'day'            => array(
								'type'        => 'integer',
								'description' => __( 'Day of month (1-31).' ),
							),
							'hour'           => array(
								'type'        => 'integer',
								'description' => __( 'Hour (0-23).' ),
							),
							'minute'         => array(
								'type'        => 'integer',
								'description' => __( 'Minute (0-59).' ),
							),
							'second'         => array(
								'type'        => 'integer',
								'description' => __( 'Second (0-59).' ),
							),
							'dayofweek'      => array(
								'type'        => 'integer',
								'description' => __( 'Day of week (1=Sunday, 7=Saturday).' ),
							),
							'dayofweek_iso'  => array(
								'type'        => 'integer',
								'description' => __( 'ISO day of week (1=Monday, 7=Sunday).' ),
							),
							'dayofyear'      => array(
								'type'        => 'integer',
								'description' => __( 'Day of year (1-366).' ),
							),
							'after'          => array(
								'description' => __( 'Date to retrieve posts after. String or array {year, month, day}.' ),
							),
							'before'         => array(
								'description' => __( 'Date to retrieve posts before. String or array {year, month, day}.' ),
							),
							'inclusive'      => array(
								'type'        => 'boolean',
								'description' => __( 'Include boundary dates in results.' ),
							),
							'compare'        => array(
								'type'        => 'string',
								'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ),
								'description' => __( 'Comparison operator.' ),
							),
							'column'         => array(
								'type'        => 'string',
								'enum'        => array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ),
								'description' => __( 'Date column for this clause.' ),
							),
							'relation'       => array(
								'type'        => 'string',
								'enum'        => array( 'AND', 'OR' ),
								'description' => __( 'Relation for nested group.' ),
							),
							'queries'        => array(
								'type'        => 'array',
								'description' => __( 'Nested date queries.' ),
							),
						),
					),
				),
			),
		);

		wp_register_ability(
			'core/find-posts',
			array(
				'label'               => __( 'Find Posts' ),
				'description'         => sprintf(
					/* translators: %s: comma-separated list of available post types */
					__( 'Search WordPress posts with full WP_Query support including nested meta_query, tax_query, and date_query with AND/OR relations. Available post types: %s.' ),
					self::$available_post_types_desc
				),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'          => array(
							'type'        => 'string',
							'description' => __( 'Post type to search.' ),
							'enum'        => self::$available_post_types,
						),
						'post_status'        => array(
							'type'        => 'string',
							'description' => __( 'Post status filter.' ),
						),
						'search'             => array(
							'type'        => 'string',
							'description' => __( 'Search term for title/content.' ),
						),
						'author'             => array(
							'type'        => 'integer',
							'description' => __( 'Filter by author ID.' ),
						),
						'posts_per_page'     => array(
							'type'        => 'integer',
							'description' => __( 'Number of posts to return (max 100).' ),
							'default'     => 10,
						),
						'orderby'            => array(
							'type'        => 'string',
							'description' => __( 'Sort field.' ),
							'enum'        => array( 'date', 'modified', 'title', 'name', 'ID', 'author', 'menu_order', 'rand' ),
						),
						'order'              => array(
							'type'        => 'string',
							'description' => __( 'Sort direction.' ),
							'enum'        => array( 'ASC', 'DESC' ),
						),
						'meta_query'         => $meta_query_schema,
						'tax_query'          => $tax_query_schema,
						'date_query'         => $date_query_schema,
						'include_taxonomies' => array(
							'type'        => 'boolean',
							'description' => __( 'Include taxonomy terms in results.' ),
							'default'     => false,
						),
						'include_meta'       => array(
							'type'        => 'boolean',
							'description' => __( 'Include meta fields in results.' ),
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'posts'       => array(
							'type'  => 'array',
							'items' => self::$post_output_schema,
						),
						'total_posts' => array(
							'type'        => 'integer',
							'description' => __( 'Total posts matching query.' ),
						),
					),
				),
				'permission_callback' => function ( $input = array() ): bool {
					$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'post';
					if ( ! post_type_exists( $post_type ) ) {
						return false;
					}
					$pto = get_post_type_object( $post_type );
					if ( ! $pto ) {
						return false;
					}
					return current_user_can( $pto->cap->edit_posts );
				},
				'execute_callback'    => function ( $input = array() ) {
					$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'post';
					if ( ! post_type_exists( $post_type ) ) {
						return new WP_Error(
							'ability_core-find-posts_invalid_post_type',
							/* translators: %s: post type name. */
							sprintf( __( 'Post type "%s" does not exist.' ), esc_html( $post_type ) )
						);
					}

					$query_args = array(
						'post_type'      => $post_type,
						'posts_per_page' => min( 100, max( 1, isset( $input['posts_per_page'] ) ? (int) $input['posts_per_page'] : 10 ) ),
					);

					if ( ! empty( $input['post_status'] ) ) {
						$query_args['post_status'] = sanitize_key( (string) $input['post_status'] );
					}
					if ( ! empty( $input['search'] ) ) {
						$query_args['s'] = sanitize_text_field( (string) $input['search'] );
					}
					if ( ! empty( $input['author'] ) ) {
						$query_args['author'] = (int) $input['author'];
					}
					if ( ! empty( $input['orderby'] ) ) {
						$query_args['orderby'] = sanitize_key( (string) $input['orderby'] );
					}
					if ( ! empty( $input['order'] ) ) {
						$query_args['order'] = in_array( $input['order'], array( 'ASC', 'DESC' ), true ) ? $input['order'] : 'DESC';
					}

					// Process meta_query.
					if ( ! empty( $input['meta_query'] ) && is_array( $input['meta_query'] ) ) {
						$meta_query = self::process_query_recursive(
							$input['meta_query'],
							array( __CLASS__, 'process_meta_clause' )
						);
						if ( ! empty( $meta_query ) ) {
							$query_args['meta_query'] = $meta_query;
						}
					}

					// Process tax_query.
					if ( ! empty( $input['tax_query'] ) && is_array( $input['tax_query'] ) ) {
						$tax_query = self::process_query_recursive(
							$input['tax_query'],
							array( __CLASS__, 'process_tax_clause' )
						);
						if ( ! empty( $tax_query ) ) {
							$query_args['tax_query'] = $tax_query;
						}
					}

					// Process date_query.
					if ( ! empty( $input['date_query'] ) && is_array( $input['date_query'] ) ) {
						$date_query = self::process_query_recursive(
							$input['date_query'],
							array( __CLASS__, 'process_date_clause' ),
							array( __CLASS__, 'process_date_top_level' )
						);
						if ( ! empty( $date_query ) ) {
							$query_args['date_query'] = $date_query;
						}
					}

					$query = new WP_Query( $query_args );

					$include_taxonomies = ! empty( $input['include_taxonomies'] );
					$include_meta       = ! empty( $input['include_meta'] );

					$posts = array();
					foreach ( $query->posts as $post ) {
						$posts[] = self::format_post_output( $post, $include_taxonomies, $include_meta );
					}

					return array(
						'posts'       => $posts,
						'total_posts' => (int) $query->found_posts,
					);
				},
				'category'            => 'post',
				'meta'                => array(
					'annotations'  => array(
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Registers the core/update-post ability.
	 *
	 * @return void
	 */
	private static function register_update_post(): void {
		wp_register_ability(
			'core/update-post',
			array(
				'label'               => __( 'Update Post' ),
				'description'         => __( 'Update an existing WordPress post. Only provided fields will be modified.' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array_merge(
						array(
							'id' => array(
								'type'        => 'integer',
								'description' => __( 'The post ID to update.' ),
							),
						),
						self::$post_fields_schema
					),
				),
				'output_schema'       => self::$post_output_schema,
				'permission_callback' => function ( $input = array() ): bool {
					$post_id = isset( $input['id'] ) ? (int) $input['id'] : 0;
					if ( ! $post_id ) {
						return false;
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return false;
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return false;
					}
					$pto = get_post_type_object( $post->post_type );
					if ( ! $pto ) {
						return false;
					}
					if ( ! self::check_status_permissions( $input, $pto ) ) {
						return false;
					}
					if ( ! self::check_author_permissions( $input, $pto, (int) $post->post_author ) ) {
						return false;
					}
					if ( ! self::check_taxonomy_permissions( $input, $post->post_type ) ) {
						return false;
					}
					return true;
				},
				'execute_callback'    => function ( $input = array() ) {
					$post_id = (int) $input['id'];
					$post    = get_post( $post_id );
					if ( ! $post ) {
						return new WP_Error(
							'ability_core-update-post_not_found',
							/* translators: %d: post ID. */
							sprintf( __( 'Post with ID %d not found.' ), $post_id )
						);
					}

					$postarr = self::build_postarr( $input, $post->post_type, true );
					if ( is_wp_error( $postarr ) ) {
						return $postarr;
					}

					if ( empty( $postarr ) ) {
						return self::format_post_output( $post );
					}

					$postarr['ID'] = $post_id;

					$result = wp_update_post( $postarr, true );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$updated_post = get_post( $post_id );
					if ( ! $updated_post ) {
						return new WP_Error(
							'ability_core-update-post_update_failed',
							__( 'Post updated but could not be loaded.' )
						);
					}

					return self::format_post_output( $updated_post );
				},
				'category'            => 'post',
				'meta'                => array(
					'annotations'  => array(
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
