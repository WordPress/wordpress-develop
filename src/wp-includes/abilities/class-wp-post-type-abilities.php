<?php
/**
 * Registers core post type abilities.
 *
 * This is a utility class to encapsulate the registration of post-type-related abilities.
 * It is not intended to be instantiated or consumed directly by any other code or plugin.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 7.0.0
 *
 * @internal This class is not part of the public API.
 * @access private
 */

declare( strict_types=1 );

/**
 * Registers core post type abilities.
 *
 * @since 7.0.0
 * @access private
 */
class WP_Post_Type_Abilities {

	/*
	 * -------------------------------------------------------------------------
	 * Ability Registration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Registers all post type abilities based on registered post types.
	 *
	 * Iterates over all registered post types and registers abilities
	 * for those that have `show_in_abilities` enabled.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		$post_types = get_post_types( array(), 'objects' );

		foreach ( $post_types as $post_type_object ) {
			$show = $post_type_object->show_in_abilities ?? false;

			if ( false === $show ) {
				continue;
			}

			$register_get = true === $show || ( is_array( $show ) && ! empty( $show['get'] ) );

			if ( $register_get ) {
				self::register_get_ability( $post_type_object );
			}
		}
	}

	/**
	 * Registers the get ability for a specific post type.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return void
	 */
	private static function register_get_ability( WP_Post_Type $post_type_object ): void {
		$slug  = $post_type_object->name;
		$label = $post_type_object->labels->singular_name ?? $post_type_object->label;
		$name  = "core/post-type/{$slug}/get";

		wp_register_ability(
			$name,
			array(
				'label'               => sprintf(
					/* translators: %s: Post type singular name. */
					__( 'Get %s' ),
					$label
				),
				'description'         => sprintf(
					/* translators: %1$s: Post type singular name (lowercase), %2$s: Post type plural name (lowercase). */
					__( 'Retrieves a single %1$s by ID or queries multiple %2$s with optional filters.' ),
					strtolower( $label ),
					strtolower( $post_type_object->labels->name ?? $post_type_object->label )
				),
				'category'            => 'post',
				'input_schema'        => self::build_get_input_schema( $post_type_object ),
				'output_schema'       => self::build_get_output_schema( $post_type_object ),
				'execute_callback'    => self::make_execute_get_callback( $post_type_object ),
				'permission_callback' => self::make_permission_get_callback( $post_type_object ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Schema Building
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Builds the input schema for the get ability.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return array The JSON schema for input.
	 */
	private static function build_get_input_schema( WP_Post_Type $post_type_object ): array {
		$slug     = $post_type_object->name;
		$statuses = array_values( get_post_stati( array( 'internal' => false ) ) );

		$include_properties = array(
			'taxonomies' => array(
				'type'        => 'boolean',
				'description' => __( 'Whether to include taxonomy terms in the response.' ),
			),
		);

		if ( post_type_supports( $slug, 'custom-fields' ) ) {
			$include_properties['meta'] = array(
				'type'        => 'boolean',
				'description' => __( 'Whether to include post meta in the response.' ),
			);
		}

		$include_schema = array(
			'type'                 => 'object',
			'description'          => __( 'Additional data to include in the response.' ),
			'properties'           => $include_properties,
			'additionalProperties' => false,
		);

		$query_properties = array(
			'tax'  => self::build_query_group_schema(
				__( 'Taxonomy query to filter posts by taxonomy terms.' ),
				self::build_tax_clause_schema()
			),
			'meta' => self::build_query_group_schema(
				__( 'Meta query to filter posts by post meta values.' ),
				self::build_meta_clause_schema()
			),
			'date' => self::build_date_query_schema(),
		);

		// Build orderby enum dynamically based on post type supports.
		$orderby_values = array( 'date', 'title', 'modified', 'id', 'author', 'relevance' );
		if ( post_type_supports( $slug, 'page-attributes' ) ) {
			$orderby_values[] = 'menu_order';
		}
		if ( post_type_supports( $slug, 'comments' ) ) {
			$orderby_values[] = 'comment_count';
		}

		// All properties are optional. When `id` is present, single-post mode.
		// When absent, query mode. Empty input returns latest published posts.
		$properties = array(
			'id'       => array(
				'type'        => 'integer',
				'description' => __( 'Unique identifier for the post. When provided, retrieves a single post by ID.' ),
				'minimum'     => 1,
			),
			'status'   => array(
				'type'        => 'string',
				'description' => __( 'Filter by post status.' ),
				'enum'        => $statuses,
			),
			'search'   => array(
				'type'        => 'string',
				'description' => __( 'Search term to filter posts by.' ),
			),
			'author'   => array(
				'type'        => 'integer',
				'description' => __( 'Filter posts by author user ID.' ),
				'minimum'     => 1,
			),
			'per_page' => array(
				'type'        => 'integer',
				'description' => __( 'Maximum number of posts to return. Defaults to 10.' ),
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'page'     => array(
				'type'        => 'integer',
				'description' => __( 'Page number for paginated results. Defaults to 1.' ),
				'minimum'     => 1,
			),
			'order'    => array(
				'type'                 => 'object',
				'description'          => __( 'Ordering parameters.' ),
				'properties'           => array(
					'orderby'   => array(
						'type'        => 'string',
						'description' => __( 'Field to order results by. Defaults to date.' ),
						'enum'        => $orderby_values,
					),
					'direction' => array(
						'type'        => 'string',
						'description' => __( 'Order direction. Defaults to desc.' ),
						'enum'        => array( 'asc', 'desc' ),
					),
				),
				'additionalProperties' => false,
			),
			'query'    => array(
				'type'                 => 'object',
				'description'          => __( 'Advanced query filters for taxonomy terms, meta values, and dates.' ),
				'properties'           => $query_properties,
				'additionalProperties' => false,
			),
			'include'  => $include_schema,
		);

		// Supports-dependent filter properties.
		if ( post_type_supports( $slug, 'comments' ) ) {
			$properties['comment_status'] = array(
				'type'        => 'string',
				'description' => __( 'Filter by comment status.' ),
				'enum'        => array( 'open', 'closed' ),
			);
		}

		if ( post_type_supports( $slug, 'trackbacks' ) ) {
			$properties['ping_status'] = array(
				'type'        => 'string',
				'description' => __( 'Filter by ping status.' ),
				'enum'        => array( 'open', 'closed' ),
			);
		}

		if ( $post_type_object->hierarchical ) {
			$properties['parent'] = array(
				'type'        => 'integer',
				'description' => __( 'Filter by parent post ID. Use 0 for top-level posts.' ),
				'minimum'     => 0,
			);
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * Builds a query group schema with the recursive { relation, queries[] } structure.
	 *
	 * @since 7.0.0
	 *
	 * @param string $description  Description for the query group.
	 * @param array  $leaf_schema  JSON Schema for a leaf clause.
	 * @return array The JSON schema for the query group.
	 */
	private static function build_query_group_schema( string $description, array $leaf_schema ): array {
		$nested_group_schema = array(
			'type'                 => 'object',
			'description'          => __( 'Nested query group with its own relation.' ),
			'required'             => array( 'queries' ),
			'properties'           => array(
				'relation' => array(
					'type'        => 'string',
					'description' => __( 'Logical relation between nested clauses.' ),
					'enum'        => array( 'AND', 'OR' ),
				),
				'queries'  => array(
					'type'        => 'array',
					'description' => __( 'Nested query clauses.' ),
				),
			),
			'additionalProperties' => false,
		);

		return array(
			'type'                 => 'object',
			'description'          => $description,
			'properties'           => array(
				'relation' => array(
					'type'        => 'string',
					'description' => __( 'Logical relation between query clauses.' ),
					'enum'        => array( 'AND', 'OR' ),
				),
				'queries'  => array(
					'type'        => 'array',
					'description' => __( 'List of query clauses or nested groups.' ),
					'items'       => array(
						'oneOf' => array(
							$leaf_schema,
							$nested_group_schema,
						),
					),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Builds the taxonomy clause leaf schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array The JSON schema for a taxonomy query clause.
	 */
	private static function build_tax_clause_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'taxonomy', 'terms' ),
			'properties'           => array(
				'taxonomy'         => array(
					'type'        => 'string',
					'description' => __( 'Taxonomy slug to query.' ),
				),
				'terms'            => array(
					'type'        => 'array',
					'description' => __( 'Taxonomy terms to match.' ),
					'items'       => array(
						'type' => array( 'integer', 'string' ),
					),
				),
				'field'            => array(
					'type'        => 'string',
					'description' => __( 'Term field to match against.' ),
					'enum'        => array( 'term_id', 'slug', 'name', 'term_taxonomy_id' ),
				),
				'operator'         => array(
					'type'        => 'string',
					'description' => __( 'SQL operator to use for the query.' ),
					'enum'        => array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' ),
				),
				'include_children' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether to include child terms. Only applicable for hierarchical taxonomies.' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Builds the meta clause leaf schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array The JSON schema for a meta query clause.
	 */
	private static function build_meta_clause_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'key' ),
			'properties'           => array(
				'key'     => array(
					'type'        => 'string',
					'description' => __( 'Meta key to query.' ),
				),
				'value'   => array(
					'type'        => array( 'string', 'integer', 'array' ),
					'description' => __( 'Meta value to match. Use an array for BETWEEN, NOT BETWEEN, IN, and NOT IN comparisons.' ),
				),
				'compare' => array(
					'type'        => 'string',
					'description' => __( 'Comparison operator.' ),
					'enum'        => array(
						'=',
						'!=',
						'>',
						'>=',
						'<',
						'<=',
						'LIKE',
						'NOT LIKE',
						'IN',
						'NOT IN',
						'BETWEEN',
						'NOT BETWEEN',
						'EXISTS',
						'NOT EXISTS',
						'REGEXP',
						'NOT REGEXP',
						'RLIKE',
					),
				),
				'type'    => array(
					'type'        => 'string',
					'description' => __( 'Cast the meta value to this type for comparison.' ),
					'enum'        => array(
						'NUMERIC',
						'CHAR',
						'DATE',
						'DATETIME',
						'TIME',
						'BINARY',
						'SIGNED',
						'UNSIGNED',
						'DECIMAL',
					),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Builds the date query schema with the top-level column field.
	 *
	 * @since 7.0.0
	 *
	 * @return array The JSON schema for a date query.
	 */
	private static function build_date_query_schema(): array {
		$date_columns = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' );

		$date_object_schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'year'  => array(
					'type'        => 'integer',
					'description' => __( 'Year.' ),
				),
				'month' => array(
					'type'        => 'integer',
					'description' => __( 'Month.' ),
				),
				'day'   => array(
					'type'        => 'integer',
					'description' => __( 'Day.' ),
				),
			),
			'additionalProperties' => false,
		);

		$date_clause_schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'year'          => array(
					'type'        => 'integer',
					'description' => __( 'Four-digit year.' ),
				),
				'month'         => array(
					'type'        => 'integer',
					'description' => __( 'Month number (1-12).' ),
				),
				'week'          => array(
					'type'        => 'integer',
					'description' => __( 'Week of the year (0-53).' ),
				),
				'day'           => array(
					'type'        => 'integer',
					'description' => __( 'Day of the month (1-31).' ),
				),
				'hour'          => array(
					'type'        => 'integer',
					'description' => __( 'Hour (0-23).' ),
				),
				'minute'        => array(
					'type'        => 'integer',
					'description' => __( 'Minute (0-59).' ),
				),
				'second'        => array(
					'type'        => 'integer',
					'description' => __( 'Second (0-59).' ),
				),
				'dayofweek'     => array(
					'type'        => 'integer',
					'description' => __( 'Day of the week (1-7, Sunday is 1).' ),
				),
				'dayofweek_iso' => array(
					'type'        => 'integer',
					'description' => __( 'ISO day of the week (1-7, Monday is 1).' ),
				),
				'dayofyear'     => array(
					'type'        => 'integer',
					'description' => __( 'Day of the year (1-366).' ),
				),
				'after'         => array(
					'oneOf'       => array(
						array(
							'type'        => 'string',
							'description' => __( 'Date string parseable by strtotime().' ),
						),
						$date_object_schema,
					),
					'description' => __( 'Retrieve posts after this date.' ),
				),
				'before'        => array(
					'oneOf'       => array(
						array(
							'type'        => 'string',
							'description' => __( 'Date string parseable by strtotime().' ),
						),
						$date_object_schema,
					),
					'description' => __( 'Retrieve posts before this date.' ),
				),
				'inclusive'     => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the after/before dates are inclusive.' ),
				),
				'compare'       => array(
					'type'        => 'string',
					'description' => __( 'Comparison operator.' ),
					'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ),
				),
				'column'        => array(
					'type'        => 'string',
					'description' => __( 'Database column to query against for this clause.' ),
					'enum'        => $date_columns,
				),
			),
			'additionalProperties' => false,
		);

		$nested_group_schema = array(
			'type'                 => 'object',
			'description'          => __( 'Nested date query group with its own relation.' ),
			'required'             => array( 'queries' ),
			'properties'           => array(
				'relation' => array(
					'type'        => 'string',
					'description' => __( 'Logical relation between nested clauses.' ),
					'enum'        => array( 'AND', 'OR' ),
				),
				'queries'  => array(
					'type'        => 'array',
					'description' => __( 'Nested date query clauses.' ),
				),
			),
			'additionalProperties' => false,
		);

		return array(
			'type'                 => 'object',
			'description'          => __( 'Date query to filter posts by date fields.' ),
			'properties'           => array(
				'relation' => array(
					'type'        => 'string',
					'description' => __( 'Logical relation between date query clauses.' ),
					'enum'        => array( 'AND', 'OR' ),
				),
				'column'   => array(
					'type'        => 'string',
					'description' => __( 'Default database column to query against.' ),
					'enum'        => $date_columns,
				),
				'queries'  => array(
					'type'        => 'array',
					'description' => __( 'List of date query clauses or nested groups.' ),
					'items'       => array(
						'oneOf' => array(
							$date_clause_schema,
							$nested_group_schema,
						),
					),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Builds the output schema for the get ability, based on post type supports.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return array The JSON schema for output.
	 */
	private static function build_get_output_schema( WP_Post_Type $post_type_object ): array {
		$post_schema = self::build_post_schema( $post_type_object );

		return array(
			'oneOf' => array(
				$post_schema,
				array(
					'type'                 => 'object',
					'properties'           => array(
						'posts'       => array(
							'type'        => 'array',
							'description' => __( 'List of posts matching the query.' ),
							'items'       => $post_schema,
						),
						'total'       => array(
							'type'        => 'integer',
							'description' => __( 'Total number of posts matching the query.' ),
						),
						'total_pages' => array(
							'type'        => 'integer',
							'description' => __( 'Total number of pages.' ),
						),
					),
					'required'             => array( 'posts', 'total', 'total_pages' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	/**
	 * Builds a single post object schema based on what the post type supports.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return array The JSON schema for a single post object.
	 */
	private static function build_post_schema( WP_Post_Type $post_type_object ): array {
		$slug = $post_type_object->name;

		// Base fields that are always present regardless of supports.
		$properties = array(
			'id'       => array(
				'type'        => 'integer',
				'description' => __( 'The post ID.' ),
			),
			'type'     => array(
				'type'        => 'string',
				'description' => __( 'The post type.' ),
			),
			'status'   => array(
				'type'        => 'string',
				'description' => __( 'The post status.' ),
			),
			'date'     => array(
				'type'        => 'string',
				'description' => __( 'The post publication date in ISO 8601 format.' ),
			),
			'modified' => array(
				'type'        => 'string',
				'description' => __( 'The post last modified date in ISO 8601 format.' ),
			),
			'slug'     => array(
				'type'        => 'string',
				'description' => __( 'The post slug.' ),
			),
			'link'     => array(
				'type'        => 'string',
				'description' => __( 'The permalink URL.' ),
			),
		);

		$required = array( 'id', 'type', 'status', 'date', 'modified', 'slug', 'link' );

		// Conditional fields based on post type supports.
		if ( post_type_supports( $slug, 'title' ) ) {
			$properties['title'] = array(
				'type'        => 'string',
				'description' => __( 'The post title.' ),
			);
			$required[]          = 'title';
		}

		if ( post_type_supports( $slug, 'editor' ) ) {
			$properties['content'] = array(
				'type'        => 'string',
				'description' => __( 'The post content.' ),
			);
			$required[]            = 'content';
		}

		if ( post_type_supports( $slug, 'excerpt' ) ) {
			$properties['excerpt'] = array(
				'type'        => 'string',
				'description' => __( 'The post excerpt.' ),
			);
			$required[]            = 'excerpt';
		}

		if ( post_type_supports( $slug, 'author' ) ) {
			$properties['author'] = array(
				'type'                 => 'object',
				'description'          => __( 'The post author.' ),
				'properties'           => array(
					'id'           => array(
						'type'        => 'integer',
						'description' => __( 'The author user ID.' ),
					),
					'display_name' => array(
						'type'        => 'string',
						'description' => __( 'The author display name.' ),
					),
				),
				'required'             => array( 'id', 'display_name' ),
				'additionalProperties' => false,
			);
			$required[]           = 'author';
		}

		if ( post_type_supports( $slug, 'thumbnail' ) ) {
			$properties['featured_media'] = array(
				'type'        => 'integer',
				'description' => __( 'The featured image attachment ID. 0 if no featured image is set.' ),
			);
			$required[]                   = 'featured_media';
		}

		if ( post_type_supports( $slug, 'page-attributes' ) ) {
			$properties['parent']     = array(
				'type'        => 'integer',
				'description' => __( 'The parent post ID. 0 if no parent.' ),
			);
			$properties['menu_order'] = array(
				'type'        => 'integer',
				'description' => __( 'The order value for the post, used for sorting.' ),
			);
			$required[]               = 'parent';
			$required[]               = 'menu_order';
		}

		if ( post_type_supports( $slug, 'post-formats' ) ) {
			$properties['format'] = array(
				'type'        => 'string',
				'description' => __( 'The post format.' ),
			);
			$required[]           = 'format';
		}

		if ( post_type_supports( $slug, 'comments' ) ) {
			$properties['comment_status'] = array(
				'type'        => 'string',
				'description' => __( 'Whether comments are allowed.' ),
				'enum'        => array( 'open', 'closed' ),
			);
			$required[]                   = 'comment_status';
		}

		if ( post_type_supports( $slug, 'trackbacks' ) ) {
			$properties['ping_status'] = array(
				'type'        => 'string',
				'description' => __( 'Whether trackbacks and pingbacks are allowed.' ),
				'enum'        => array( 'open', 'closed' ),
			);
			$required[]                = 'ping_status';
		}

		// Optional fields included when requested via `include` input flags.
		$term_schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'id'   => array(
					'type'        => 'integer',
					'description' => __( 'The term ID.' ),
				),
				'name' => array(
					'type'        => 'string',
					'description' => __( 'The term name.' ),
				),
				'slug' => array(
					'type'        => 'string',
					'description' => __( 'The term slug.' ),
				),
			),
			'required'             => array( 'id', 'name', 'slug' ),
			'additionalProperties' => false,
		);

		$properties['taxonomies'] = array(
			'type'                 => 'object',
			'description'          => __( 'Taxonomy terms grouped by taxonomy name. Only present when include.taxonomies is true.' ),
			'additionalProperties' => array(
				'type'  => 'array',
				'items' => $term_schema,
			),
		);

		if ( post_type_supports( $slug, 'custom-fields' ) ) {
			$properties['meta'] = array(
				'type'                 => 'object',
				'description'          => __( 'Public post meta key-value pairs. Only present when include.meta is true.' ),
				'additionalProperties' => array(
					'type' => array( 'string', 'array' ),
				),
			);
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $required,
			'additionalProperties' => false,
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Execution
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Creates the execute callback for a post type's get ability.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return Closure The execute callback.
	 */
	private static function make_execute_get_callback( WP_Post_Type $post_type_object ): Closure {
		return static function ( $input = array() ) use ( $post_type_object ) {
			$input = is_array( $input ) ? $input : array();

			// Single post retrieval by ID.
			if ( ! empty( $input['id'] ) ) {
				return self::execute_get_single( (int) $input['id'], $post_type_object, $input );
			}

			// Multi-post query.
			return self::execute_get_query( $post_type_object, $input );
		};
	}

	/**
	 * Creates the permission callback for a post type's get ability.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return Closure The permission callback.
	 */
	private static function make_permission_get_callback( WP_Post_Type $post_type_object ): Closure {
		return static function ( $input = array() ) use ( $post_type_object ): bool {
			$input = is_array( $input ) ? $input : array();

			// For single post retrieval, check specific post permission.
			// If the post doesn't exist, verify the user has general read
			// capability before letting the execute callback return a 404.
			if ( ! empty( $input['id'] ) ) {
				$post = get_post( (int) $input['id'] );

				if ( ! $post || $post->post_type !== $post_type_object->name ) {
					return current_user_can( $post_type_object->cap->read_others_posts ?? 'read' );
				}

				return current_user_can( 'read_post', $post->ID );
			}

			// For queries, check general read capability.
			return current_user_can( $post_type_object->cap->read ?? 'read' );
		};
	}

	/**
	 * Retrieves a single post by ID.
	 *
	 * @since 7.0.0
	 *
	 * @param int          $post_id          The post ID.
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @param array        $input            The input parameters.
	 * @return array|WP_Error Post data or error.
	 */
	private static function execute_get_single( int $post_id, WP_Post_Type $post_type_object, array $input ) {
		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== $post_type_object->name ) {
			return new WP_Error(
				'post_not_found',
				__( 'Post not found.' ),
				array( 'status' => 404 )
			);
		}

		return self::format_post( $post, $post_type_object, $input );
	}

	/**
	 * Queries multiple posts.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @param array        $input            The input parameters.
	 * @return array|WP_Error Query results with posts, total, and total_pages, or error.
	 */
	private static function execute_get_query( WP_Post_Type $post_type_object, array $input ) {
		$per_page = $input['per_page'] ?? 10;
		$page     = $input['page'] ?? 1;

		$query_args = array(
			'post_type'      => $post_type_object->name,
			'post_status'    => $input['status'] ?? 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'perm'           => 'readable',
		);

		if ( ! empty( $input['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['author'] ) ) {
			$query_args['author'] = (int) $input['author'];
		}

		if ( ! empty( $input['comment_status'] ) ) {
			$query_args['comment_status'] = sanitize_key( $input['comment_status'] );
		}

		if ( ! empty( $input['ping_status'] ) ) {
			$query_args['ping_status'] = sanitize_key( $input['ping_status'] );
		}

		if ( isset( $input['parent'] ) ) {
			$query_args['post_parent'] = (int) $input['parent'];
		}

		if ( ! empty( $input['order'] ) ) {
			$order_input = $input['order'];
			$orderby_map = array(
				'date'          => 'date',
				'title'         => 'title',
				'modified'      => 'modified',
				'id'            => 'ID',
				'author'        => 'author',
				'relevance'     => 'relevance',
				'menu_order'    => 'menu_order',
				'comment_count' => 'comment_count',
			);

			if ( ! empty( $order_input['orderby'] ) && isset( $orderby_map[ $order_input['orderby'] ] ) ) {
				$query_args['orderby'] = $orderby_map[ $order_input['orderby'] ];
			}
			if ( ! empty( $order_input['direction'] ) ) {
				$query_args['order'] = strtoupper( $order_input['direction'] );
			}
		}

		// Process advanced query clauses.
		if ( ! empty( $input['query'] ) ) {
			$query_input = $input['query'];

			if ( ! empty( $query_input['tax'] ) ) {
				// Validate that all taxonomies in the query are public.
				$taxonomies_in_query = self::extract_taxonomies_from_query( $query_input['tax'] );
				$allowed_taxonomies  = self::get_allowed_taxonomies( $post_type_object->name );
				$invalid_taxonomies  = array_diff( $taxonomies_in_query, $allowed_taxonomies );

				if ( ! empty( $invalid_taxonomies ) ) {
					return new WP_Error(
						'invalid_taxonomy',
						sprintf(
							/* translators: %s: Comma-separated list of invalid taxonomy slugs. */
							__( 'The following taxonomies are not allowed: %s' ),
							implode( ', ', $invalid_taxonomies )
						),
						array( 'status' => 400 )
					);
				}

				$tax_query = self::process_query_recursive(
					$query_input['tax'],
					array( __CLASS__, 'process_tax_clause' )
				);
				if ( ! empty( $tax_query ) ) {
					$query_args['tax_query'] = $tax_query;
				}
			}

			if ( ! empty( $query_input['meta'] ) ) {
				// Validate that all meta keys in the query have show_in_abilities enabled.
				$meta_keys_in_query = self::extract_meta_keys_from_query( $query_input['meta'] );
				$allowed_meta_keys  = self::get_allowed_meta_keys( $post_type_object->name );
				$invalid_keys       = array_diff( $meta_keys_in_query, $allowed_meta_keys );

				if ( ! empty( $invalid_keys ) ) {
					return new WP_Error(
						'invalid_meta_key',
						sprintf(
							/* translators: %s: Comma-separated list of invalid meta keys. */
							__( 'The following meta keys are not allowed: %s' ),
							implode( ', ', $invalid_keys )
						),
						array( 'status' => 400 )
					);
				}

				$meta_query = self::process_query_recursive(
					$query_input['meta'],
					array( __CLASS__, 'process_meta_clause' )
				);
				if ( ! empty( $meta_query ) ) {
					$query_args['meta_query'] = $meta_query;
				}
			}

			if ( ! empty( $query_input['date'] ) ) {
				$date_query = self::process_query_recursive(
					$query_input['date'],
					array( __CLASS__, 'process_date_clause' ),
					array( __CLASS__, 'process_date_top_level' )
				);
				if ( ! empty( $date_query ) ) {
					$query_args['date_query'] = $date_query;
				}
			}
		}

		$query = new WP_Query( $query_args );
		$posts = array();

		foreach ( $query->posts as $post ) {
			$posts[] = self::format_post( $post, $post_type_object, $input );
		}

		return array(
			'posts'       => $posts,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Formats a post object into the ability output format.
	 *
	 * Fields included depend on what the post type supports.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Post      $post             The post object.
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @param array        $input            The input parameters (for include flags).
	 * @return array Formatted post data.
	 */
	private static function format_post( WP_Post $post, WP_Post_Type $post_type_object, array $input ): array {
		$slug = $post_type_object->name;

		// Base fields always present.
		$data = array(
			'id'       => $post->ID,
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'date'     => mysql2date( 'c', $post->post_date_gmt ),
			'modified' => mysql2date( 'c', $post->post_modified_gmt ),
			'slug'     => $post->post_name,
			'link'     => get_permalink( $post ),
		);

		// Conditional fields based on post type supports.
		if ( post_type_supports( $slug, 'title' ) ) {
			$data['title'] = get_the_title( $post );
		}

		if ( post_type_supports( $slug, 'editor' ) ) {
			/** This filter is documented in wp-includes/post-template.php */
			$data['content'] = apply_filters( 'the_content', $post->post_content );
		}

		if ( post_type_supports( $slug, 'excerpt' ) ) {
			$data['excerpt'] = get_the_excerpt( $post );
		}

		if ( post_type_supports( $slug, 'author' ) ) {
			$author         = get_userdata( (int) $post->post_author );
			$data['author'] = array(
				'id'           => (int) $post->post_author,
				'display_name' => $author ? $author->display_name : '',
			);
		}

		if ( post_type_supports( $slug, 'thumbnail' ) ) {
			$data['featured_media'] = (int) get_post_thumbnail_id( $post );
		}

		if ( post_type_supports( $slug, 'page-attributes' ) ) {
			$data['parent']     = (int) $post->post_parent;
			$data['menu_order'] = (int) $post->menu_order;
		}

		if ( post_type_supports( $slug, 'post-formats' ) ) {
			$format         = get_post_format( $post );
			$data['format'] = $format ? $format : 'standard';
		}

		if ( post_type_supports( $slug, 'comments' ) ) {
			$data['comment_status'] = $post->comment_status;
		}

		if ( post_type_supports( $slug, 'trackbacks' ) ) {
			$data['ping_status'] = $post->ping_status;
		}

		// Include optional data based on include flags.
		$include = $input['include'] ?? array();

		if ( ! empty( $include['taxonomies'] ) ) {
			$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
			$terms_data = array();

			foreach ( $taxonomies as $taxonomy ) {
				if ( ! $taxonomy->public ) {
					continue;
				}
				$terms = get_the_terms( $post, $taxonomy->name );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$terms_data[ $taxonomy->name ] = array_map(
						static function ( $term ): array {
							return array(
								'id'   => $term->term_id,
								'name' => $term->name,
								'slug' => $term->slug,
							);
						},
						$terms
					);
				}
			}

			$data['taxonomies'] = ! empty( $terms_data ) ? $terms_data : new stdClass();
		}

		if ( ! empty( $include['meta'] ) && post_type_supports( $slug, 'custom-fields' ) ) {
			$meta              = get_post_meta( $post->ID );
			$public_meta       = array();
			$allowed_meta_keys = self::get_allowed_meta_keys( $slug );

			foreach ( $meta as $key => $values ) {
				// Skip protected meta keys.
				if ( is_protected_meta( $key, 'post' ) ) {
					continue;
				}
				// Only include meta keys that are registered with show_in_abilities enabled.
				if ( ! in_array( $key, $allowed_meta_keys, true ) ) {
					continue;
				}
				$public_meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
			}

			$data['meta'] = ! empty( $public_meta ) ? $public_meta : new stdClass();
		}

		return $data;
	}

	/*
	 * -------------------------------------------------------------------------
	 * Query Processing
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Recursively converts a semantic { relation, queries[] } structure to a native WP query array.
	 *
	 * The semantic JSON format uses explicit `relation` and `queries` properties,
	 * while WP uses numeric-keyed arrays with a `relation` string key.
	 *
	 * @since 7.0.0
	 *
	 * @param array         $input             The semantic query input.
	 * @param callable      $process_leaf      Callback to process a leaf clause. Receives an array, returns an array or null.
	 * @param callable|null $process_top_level Optional. Callback to handle top-level fields (e.g., date_query 'column').
	 *                                         Receives ($input, &$result).
	 * @return array The native WP query array.
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
					// Nested group: recurse.
					$nested = self::process_query_recursive( $query, $process_leaf, $process_top_level );
					if ( ! empty( $nested ) ) {
						$result[] = $nested;
					}
				} else {
					// Leaf clause: process with type-specific callback.
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
	 * Processes a taxonomy query leaf clause.
	 *
	 * @since 7.0.0
	 *
	 * @param array $clause The raw clause data.
	 * @return array|null The processed clause or null if invalid.
	 */
	private static function process_tax_clause( array $clause ): ?array {
		if ( empty( $clause['taxonomy'] ) || empty( $clause['terms'] ) ) {
			return null;
		}

		$taxonomy = sanitize_key( $clause['taxonomy'] );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$result = array(
			'taxonomy' => $taxonomy,
			'terms'    => (array) $clause['terms'],
		);

		$allowed_fields = array( 'term_id', 'slug', 'name', 'term_taxonomy_id' );
		if ( ! empty( $clause['field'] ) && in_array( $clause['field'], $allowed_fields, true ) ) {
			$result['field'] = $clause['field'];
		}

		$allowed_operators = array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' );
		if ( ! empty( $clause['operator'] ) && in_array( $clause['operator'], $allowed_operators, true ) ) {
			$result['operator'] = $clause['operator'];
		}

		if ( isset( $clause['include_children'] ) ) {
			$result['include_children'] = (bool) $clause['include_children'];
		}

		return $result;
	}

	/**
	 * Processes a meta query leaf clause.
	 *
	 * @since 7.0.0
	 *
	 * @param array $clause The raw clause data.
	 * @return array|null The processed clause or null if invalid.
	 */
	private static function process_meta_clause( array $clause ): ?array {
		if ( empty( $clause['key'] ) ) {
			return null;
		}

		$result = array(
			'key' => sanitize_key( $clause['key'] ),
		);

		if ( isset( $clause['value'] ) ) {
			$result['value'] = $clause['value'];
		}

		$allowed_compare = array(
			'=',
			'!=',
			'>',
			'>=',
			'<',
			'<=',
			'LIKE',
			'NOT LIKE',
			'IN',
			'NOT IN',
			'BETWEEN',
			'NOT BETWEEN',
			'EXISTS',
			'NOT EXISTS',
			'REGEXP',
			'NOT REGEXP',
			'RLIKE',
		);
		if ( ! empty( $clause['compare'] ) && in_array( $clause['compare'], $allowed_compare, true ) ) {
			$result['compare'] = $clause['compare'];
		}

		$allowed_types = array(
			'NUMERIC',
			'CHAR',
			'DATE',
			'DATETIME',
			'TIME',
			'BINARY',
			'SIGNED',
			'UNSIGNED',
			'DECIMAL',
		);
		if ( ! empty( $clause['type'] ) && in_array( $clause['type'], $allowed_types, true ) ) {
			$result['type'] = $clause['type'];
		}

		return $result;
	}

	/**
	 * Processes a date query leaf clause.
	 *
	 * @since 7.0.0
	 *
	 * @param array $clause The raw clause data.
	 * @return array|null The processed clause or null if invalid.
	 */
	private static function process_date_clause( array $clause ): ?array {
		$result = array();

		$int_fields = array(
			'year',
			'month',
			'week',
			'day',
			'hour',
			'minute',
			'second',
			'dayofweek',
			'dayofweek_iso',
			'dayofyear',
		);

		foreach ( $int_fields as $field ) {
			if ( isset( $clause[ $field ] ) ) {
				$result[ $field ] = (int) $clause[ $field ];
			}
		}

		// Handle after/before as string or { year, month, day } object.
		foreach ( array( 'after', 'before' ) as $boundary ) {
			if ( isset( $clause[ $boundary ] ) ) {
				if ( is_string( $clause[ $boundary ] ) ) {
					$result[ $boundary ] = sanitize_text_field( $clause[ $boundary ] );
				} elseif ( is_array( $clause[ $boundary ] ) ) {
					$date_parts = array();
					foreach ( array( 'year', 'month', 'day' ) as $part ) {
						if ( isset( $clause[ $boundary ][ $part ] ) ) {
							$date_parts[ $part ] = (int) $clause[ $boundary ][ $part ];
						}
					}
					if ( ! empty( $date_parts ) ) {
						$result[ $boundary ] = $date_parts;
					}
				}
			}
		}

		if ( isset( $clause['inclusive'] ) ) {
			$result['inclusive'] = (bool) $clause['inclusive'];
		}

		$allowed_compare = array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' );
		if ( ! empty( $clause['compare'] ) && in_array( $clause['compare'], $allowed_compare, true ) ) {
			$result['compare'] = $clause['compare'];
		}

		$allowed_columns = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' );
		if ( ! empty( $clause['column'] ) && in_array( $clause['column'], $allowed_columns, true ) ) {
			$result['column'] = $clause['column'];
		}

		return ! empty( $result ) ? $result : null;
	}

	/**
	 * Processes top-level date query fields.
	 *
	 * Handles the `column` field that applies as the default for all date clauses.
	 *
	 * @since 7.0.0
	 *
	 * @param array $input  The date query input.
	 * @param array $result The result array (passed by reference).
	 * @return void
	 */
	private static function process_date_top_level( array $input, array &$result ): void {
		$allowed_columns = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' );
		if ( ! empty( $input['column'] ) && in_array( $input['column'], $allowed_columns, true ) ) {
			$result['column'] = $input['column'];
		}
	}

	/**
	 * Returns all meta keys that are registered with show_in_abilities enabled for a post type.
	 *
	 * @since 7.0.0
	 *
	 * @param string $post_type_slug The post type slug.
	 * @return string[] List of allowed meta keys.
	 */
	private static function get_allowed_meta_keys( string $post_type_slug ): array {
		$registered_meta = array_merge(
			get_registered_meta_keys( 'post', $post_type_slug ),
			get_registered_meta_keys( 'post' )
		);

		$allowed = array();
		foreach ( $registered_meta as $key => $args ) {
			if ( ! empty( $args['show_in_abilities'] ) ) {
				$allowed[] = $key;
			}
		}

		return $allowed;
	}

	/**
	 * Extracts all meta keys from a meta query structure recursively.
	 *
	 * @since 7.0.0
	 *
	 * @param array $query The meta query input.
	 * @return string[] List of meta keys found in the query.
	 */
	private static function extract_meta_keys_from_query( array $query ): array {
		$keys = array();

		if ( ! empty( $query['queries'] ) && is_array( $query['queries'] ) ) {
			foreach ( $query['queries'] as $sub_query ) {
				if ( ! is_array( $sub_query ) ) {
					continue;
				}

				if ( isset( $sub_query['queries'] ) ) {
					// Nested group: recurse.
					$keys = array_merge( $keys, self::extract_meta_keys_from_query( $sub_query ) );
				} elseif ( ! empty( $sub_query['key'] ) ) {
					// Leaf clause with a key.
					$keys[] = sanitize_key( $sub_query['key'] );
				}
			}
		}

		return array_unique( $keys );
	}

	/**
	 * Returns all public taxonomies associated with a post type.
	 *
	 * @since 7.0.0
	 *
	 * @param string $post_type_slug The post type slug.
	 * @return string[] List of allowed taxonomy slugs.
	 */
	private static function get_allowed_taxonomies( string $post_type_slug ): array {
		$taxonomies = get_object_taxonomies( $post_type_slug, 'objects' );
		$allowed    = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$allowed[] = $taxonomy->name;
			}
		}

		return $allowed;
	}

	/**
	 * Extracts all taxonomy slugs from a taxonomy query structure recursively.
	 *
	 * @since 7.0.0
	 *
	 * @param array $query The taxonomy query input.
	 * @return string[] List of taxonomy slugs found in the query.
	 */
	private static function extract_taxonomies_from_query( array $query ): array {
		$taxonomies = array();

		if ( ! empty( $query['queries'] ) && is_array( $query['queries'] ) ) {
			foreach ( $query['queries'] as $sub_query ) {
				if ( ! is_array( $sub_query ) ) {
					continue;
				}

				if ( isset( $sub_query['queries'] ) ) {
					// Nested group: recurse.
					$taxonomies = array_merge( $taxonomies, self::extract_taxonomies_from_query( $sub_query ) );
				} elseif ( ! empty( $sub_query['taxonomy'] ) ) {
					// Leaf clause with a taxonomy.
					$taxonomies[] = sanitize_key( $sub_query['taxonomy'] );
				}
			}
		}

		return array_unique( $taxonomies );
	}
}
