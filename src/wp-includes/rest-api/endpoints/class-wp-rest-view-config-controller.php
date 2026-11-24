<?php
/**
 * REST API: WP_REST_View_Config_Controller class
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      7.1.0
 */

/**
 * Controller which provides a REST endpoint for retrieving the default
 * view configuration for a given entity type.
 *
 * @since 7.1.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_View_Config_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 7.1.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'view-config';
	}

	/**
	 * Registers the routes for the controller.
	 *
	 * @since 7.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'kind' => array(
							'description' => __( 'Entity kind.' ),
							'type'        => 'string',
							'required'    => true,
						),
						'name' => array(
							'description' => __( 'Entity name.' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks if a given request has access to read view config.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$kind = $request->get_param( 'kind' );
		$name = $request->get_param( 'name' );

		$capability = $this->get_required_capability( $kind, $name );

		if ( null === $capability ) {
			return new WP_Error(
				'rest_view_config_invalid_entity',
				__( 'Invalid entity kind or name.' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( $capability ) ) {
			return new WP_Error(
				'rest_cannot_read',
				__( 'Sorry, you are not allowed to read view config.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Resolves the capability required to read the view config for an entity.
	 *
	 * Known kinds map to the capability that gates managing that entity's list:
	 * post types use their own `edit_posts` capability (which honors custom
	 * `capability_type` registrations), taxonomies use `manage_terms`, and
	 * root-level entities use `manage_options`. A post type or taxonomy that is
	 * not registered, or not exposed to the REST API, resolves to `null` so the
	 * request is treated as referencing an unknown entity.
	 *
	 * Any other kind falls back to `edit_posts`. This keeps entities registered
	 * through the `get_entity_view_config_{$kind}_{$name}` filter readable behind
	 * a baseline capability.
	 *
	 * @since 7.1.0
	 *
	 * @param string $kind The entity kind (e.g. `postType`).
	 * @param string $name The entity name (e.g. `page`).
	 * @return string|null Capability required to read the config, or null if the
	 *                     entity is not registered.
	 */
	protected function get_required_capability( $kind, $name ) {
		switch ( $kind ) {
			case 'postType':
				$post_type = get_post_type_object( $name );
				if ( $post_type && $post_type->show_in_rest ) {
					return $post_type->cap->edit_posts;
				}
				return null;

			case 'taxonomy':
				$taxonomy = get_taxonomy( $name );
				if ( $taxonomy && $taxonomy->show_in_rest ) {
					return $taxonomy->cap->manage_terms;
				}
				return null;

			case 'root':
				return 'manage_options';
		}

		return 'edit_posts';
	}

	/**
	 * Returns the default view configuration for the given entity type.
	 *
	 * @since 7.1.0
	 * @since 7.3.0 Added the item count of each view in the `page` view list.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$kind = $request->get_param( 'kind' );
		$name = $request->get_param( 'name' );

		$config = wp_get_entity_view_config( $kind, $name );
		$schema = $this->get_item_schema();

		$response = array(
			'kind'            => $kind,
			'name'            => $name,
			'version'         => WP_View_Config_Data::LATEST_VERSION,
			'default_view'    => wp_prepare_json_value_for_client( $config['default_view'], $schema['properties']['default_view'] ),
			'default_layouts' => wp_prepare_json_value_for_client( $config['default_layouts'], $schema['properties']['default_layouts'] ),
			'view_list'       => wp_prepare_json_value_for_client( $config['view_list'], $schema['properties']['view_list'] ),
			'form'            => wp_prepare_json_value_for_client( $config['form'], $schema['properties']['form'] ),
		);

		/*
		 * The counts are added here rather than through the view config filter,
		 * so that they are computed from the view list after every filter has
		 * changed it. The counting is skipped when the request leaves out the
		 * view list, such as the editor's request for the form alone.
		 */
		if (
			'postType' === $kind &&
			'page' === $name &&
			rest_is_field_included( 'view_list', $this->get_fields_for_response( $request ) ) &&
			is_array( $response['view_list'] )
		) {
			$response['view_list'] = $this->add_counts_to_view_list( 'page', $response['view_list'], $response['default_view'], $response['default_layouts'] );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Recursively casts empty arrays to objects where the schema types them as
	 * objects.
	 *
	 * @since 7.1.0
	 * @since 7.2.0 Uses wp_prepare_json_value_for_client().
	 *
	 * @see wp_prepare_json_value_for_client()
	 *
	 * @param mixed $value  The value to normalize.
	 * @param array $schema The schema node describing the value.
	 * @return mixed The normalized value, with empty object-typed arrays cast to objects.
	 */
	protected function cast_empty_objects( $value, $schema ) {
		return is_array( $schema ) ? wp_prepare_json_value_for_client( $value, $schema ) : $value;
	}

	/**
	 * Retrieves the item's schema, conforming to JSON Schema.
	 *
	 * @since 7.1.0
	 * @since 7.3.0 Added the `count` property to the view list entries.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$view_base_properties = $this->get_view_base_schema();

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'view-config',
			'type'       => 'object',
			'properties' => array(
				'kind'            => array(
					'description' => __( 'Entity kind.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'name'            => array(
					'description' => __( 'Entity name.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'version'         => array(
					'description' => __( 'The schema version of the configuration.' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'default_view'    => array(
					'description' => __( 'Default view configuration.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array_merge(
						array(
							'type'   => array(
								'type' => 'string',
							),
							'layout' => $this->get_combined_layout_schema(),
						),
						$view_base_properties
					),
				),
				'default_layouts' => array(
					'description' => __( 'Default layout configurations.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'table'       => array(
							'type'       => 'object',
							'properties' => array_merge(
								$view_base_properties,
								array(
									'layout' => $this->get_table_layout_schema(),
								)
							),
						),
						'list'        => array(
							'type'       => 'object',
							'properties' => array_merge(
								$view_base_properties,
								array(
									'layout' => $this->get_list_layout_schema(),
								)
							),
						),
						'grid'        => array(
							'type'       => 'object',
							'properties' => array_merge(
								$view_base_properties,
								array(
									'layout' => $this->get_grid_layout_schema(),
								)
							),
						),
						'activity'    => array(
							'type'       => 'object',
							'properties' => array_merge(
								$view_base_properties,
								array(
									'layout' => $this->get_list_layout_schema(),
								)
							),
						),
						'pickerGrid'  => array(
							'type'       => 'object',
							'properties' => array_merge(
								$view_base_properties,
								array(
									'layout' => $this->get_grid_layout_schema(),
								)
							),
						),
						'pickerTable' => array(
							'type'       => 'object',
							'properties' => array_merge(
								$view_base_properties,
								array(
									'layout' => $this->get_table_layout_schema(),
								)
							),
						),
					),
				),
				'view_list'       => array(
					'description' => __( 'List of default views.' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'title' => array(
								'type' => 'string',
							),
							'slug'  => array(
								'type' => 'string',
							),
							'view'  => array(
								'type'       => 'object',
								'properties' => array_merge(
									array(
										'type'   => array(
											'type' => 'string',
										),
										'layout' => $this->get_combined_layout_schema(),
									),
									$view_base_properties
								),
							),
							'count' => array(
								'description' => __( 'Number of items the view holds.' ),
								'type'        => 'integer',
								'readonly'    => true,
							),
						),
					),
				),
				'form'            => array(
					'description' => __( 'Default form configuration.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => $this->get_form_schema(),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Returns the schema properties shared by all view types (ViewBase), excluding 'type'.
	 *
	 * Note that `search` and `page` are not part of the schema: they are managed
	 * via the URL, which is their only source of truth.
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema properties for the base view configuration.
	 */
	protected function get_view_base_schema() {
		return array(
			'filters'               => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'field'    => array(
							'type' => 'string',
						),
						'operator' => array(
							'type' => 'string',
							'enum' => array(
								'is',
								'isNot',
								'isAny',
								'isNone',
								'isAll',
								'isNotAll',
								'lessThan',
								'greaterThan',
								'lessThanOrEqual',
								'greaterThanOrEqual',
								'before',
								'after',
							),
						),
						'value'    => array(),
						'isLocked' => array(
							'type' => 'boolean',
						),
					),
				),
			),
			'sort'                  => array(
				'type'       => 'object',
				'properties' => array(
					'field'     => array(
						'type' => 'string',
					),
					'direction' => array(
						'type' => 'string',
						'enum' => array( 'asc', 'desc' ),
					),
				),
			),
			'perPage'               => array(
				'type' => 'integer',
			),
			'fields'                => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'string',
				),
			),
			'titleField'            => array(
				'type' => 'string',
			),
			'mediaField'            => array(
				'type' => 'string',
			),
			'descriptionField'      => array(
				'type' => 'string',
			),
			'showTitle'             => array(
				'type' => 'boolean',
			),
			'showMedia'             => array(
				'type' => 'boolean',
			),
			'showDescription'       => array(
				'type' => 'boolean',
			),
			'showLevels'            => array(
				'type' => 'boolean',
			),
			'groupBy'               => array(
				'type'       => 'object',
				'properties' => array(
					'field'     => array(
						'type' => 'string',
					),
					'direction' => array(
						'type' => 'string',
						'enum' => array( 'asc', 'desc' ),
					),
					'showLabel' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			),
			'infiniteScrollEnabled' => array(
				'type' => 'boolean',
			),
		);
	}

	/**
	 * Returns the schema for the ColumnStyle type.
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema for a column style object.
	 */
	protected function get_column_style_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'width'    => array(
					'description' => __( 'The width of the column.' ),
					'type'        => array( 'string', 'number' ),
				),
				'maxWidth' => array(
					'description' => __( 'The maximum width of the column.' ),
					'type'        => array( 'string', 'number' ),
				),
				'minWidth' => array(
					'description' => __( 'The minimum width of the column.' ),
					'type'        => array( 'string', 'number' ),
				),
				'align'    => array(
					'description' => __( 'The horizontal alignment of the column content.' ),
					'type'        => 'string',
					'enum'        => array( 'start', 'center', 'end' ),
				),
			),
		);
	}

	/**
	 * Returns the layout schema for table-type views (ViewTable, ViewPickerTable).
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema for a table layout object.
	 */
	protected function get_table_layout_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'styles'       => array(
					'description'          => __( 'Column styles keyed by field id, for the columns listed in the view fields. The primary column (title, media, and description fields) ignores these styles; in the table layout it takes the width left over by the other columns, or the last column does when there is no primary column.' ),
					'type'                 => 'object',
					'additionalProperties' => $this->get_column_style_schema(),
				),
				'density'      => array(
					'type' => 'string',
					'enum' => array( 'compact', 'balanced', 'comfortable' ),
				),
				'enableMoving' => array(
					'type' => 'boolean',
				),
			),
		);
	}

	/**
	 * Returns the layout schema for list-type views (ViewList, ViewActivity).
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema for a list layout object.
	 */
	protected function get_list_layout_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'density' => array(
					'type' => 'string',
					'enum' => array( 'compact', 'balanced', 'comfortable' ),
				),
			),
		);
	}

	/**
	 * Returns a combined layout schema that accepts properties from all view types.
	 *
	 * This is useful for contexts where the view type is not known ahead of time
	 * (e.g. the `view` override in a view list item), so all possible layout
	 * properties must be accepted.
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema for a combined layout object.
	 */
	protected function get_combined_layout_schema() {
		return array(
			'type'       => 'object',
			'properties' => array_merge(
				$this->get_table_layout_schema()['properties'],
				$this->get_grid_layout_schema()['properties'],
				$this->get_list_layout_schema()['properties']
			),
		);
	}

	/**
	 * Returns the layout schema for grid-type views (ViewGrid, ViewPickerGrid).
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema for a grid layout object.
	 */
	protected function get_grid_layout_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'badgeFields' => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
				'previewSize' => array(
					'type' => 'number',
				),
				'density'     => array(
					'type' => 'string',
					'enum' => array( 'compact', 'balanced', 'comfortable' ),
				),
			),
		);
	}

	/**
	 * Returns the schema for a form layout object as a discriminated union.
	 *
	 * Each variant is discriminated by a single-value enum on its `type` property,
	 * matching the TypeScript Layout union in dataviews/src/types/dataform.ts.
	 *
	 * @since 7.1.0
	 * @since 7.2.0 Added the `showPlaceholderIfEmpty` property to the panel layout.
	 *
	 * @return array Schema for a form layout object.
	 */
	protected function get_form_layout_schema() {
		return array(
			'oneOf' => array(
				// RegularLayout.
				array(
					'type'       => 'object',
					'properties' => array(
						'type'          => array(
							'type' => 'string',
							'enum' => array( 'regular' ),
						),
						'labelPosition' => array(
							'type' => 'string',
							'enum' => array( 'top', 'side', 'none' ),
						),
					),
				),
				// PanelLayout.
				array(
					'type'       => 'object',
					'properties' => array(
						'type'                   => array(
							'type' => 'string',
							'enum' => array( 'panel' ),
						),
						'labelPosition'          => array(
							'type' => 'string',
							'enum' => array( 'top', 'side', 'none' ),
						),
						'openAs'                 => array(
							'oneOf' => array(
								array(
									'type' => 'string',
									'enum' => array( 'dropdown', 'modal' ),
								),
								array(
									'type'       => 'object',
									'properties' => array(
										'type'        => array(
											'type' => 'string',
											'enum' => array( 'dropdown', 'modal' ),
										),
										'applyLabel'  => array(
											'type' => 'string',
										),
										'cancelLabel' => array(
											'type' => 'string',
										),
									),
								),
							),
						),
						'summary'                => array(
							'oneOf' => array(
								array( 'type' => 'string' ),
								array(
									'type'  => 'array',
									'items' => array(
										'type' => 'string',
									),
								),
							),
						),
						'editVisibility'         => array(
							'type' => 'string',
							'enum' => array( 'always', 'on-hover' ),
						),
						'showPlaceholderIfEmpty' => array(
							'type' => 'boolean',
						),
					),
				),
				// CardLayout.
				array(
					'type'       => 'object',
					'properties' => array(
						'type'          => array(
							'type' => 'string',
							'enum' => array( 'card' ),
						),
						'withHeader'    => array(
							'type' => 'boolean',
						),
						'isOpened'      => array(
							'type' => 'boolean',
						),
						'isCollapsible' => array(
							'type' => 'boolean',
						),
						'summary'       => array(
							'oneOf' => array(
								array( 'type' => 'string' ),
								array(
									'type'  => 'array',
									'items' => array(
										'oneOf' => array(
											array( 'type' => 'string' ),
											array(
												'type' => 'object',
												'properties' => array(
													'id' => array(
														'type' => 'string',
													),
													'visibility' => array(
														'type' => 'string',
														'enum' => array( 'always', 'when-collapsed' ),
													),
												),
											),
										),
									),
								),
							),
						),
					),
				),
				// RowLayout.
				array(
					'type'       => 'object',
					'properties' => array(
						'type'      => array(
							'type' => 'string',
							'enum' => array( 'row' ),
						),
						'alignment' => array(
							'type' => 'string',
							'enum' => array( 'start', 'center', 'end' ),
						),
						'styles'    => array(
							'type'                 => 'object',
							'additionalProperties' => array(
								'type'       => 'object',
								'properties' => array(
									'flex' => array(
										'type' => array( 'string', 'number' ),
									),
								),
							),
						),
					),
				),
				// DetailsLayout.
				array(
					'type'       => 'object',
					'properties' => array(
						'type'    => array(
							'type' => 'string',
							'enum' => array( 'details' ),
						),
						'summary' => array(
							'type' => 'string',
						),
					),
				),
			),
		);
	}

	/**
	 * Returns the schema for a form field item (string or object).
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema for a form field.
	 */
	protected function get_form_field_schema() {
		return array(
			'oneOf' => array(
				array( 'type' => 'string' ),
				array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type' => 'string',
						),
						'label'       => array(
							'type' => 'string',
						),
						'description' => array(
							'type' => 'string',
						),
						'layout'      => $this->get_form_layout_schema(),
						'children'    => array(
							'type'  => 'array',
							'items' => array(
								'oneOf' => array(
									array( 'type' => 'string' ),
									// This object can have the shape of a form field itself,
									// allowing for recursive nesting of form fields.
									// There's no easy way to codify this recursion via the JSON Schema draft-04
									// supported by the REST API.
									array( 'type' => 'object' ),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Returns the schema for the form configuration object.
	 *
	 * @since 7.1.0
	 *
	 * @return array Schema properties for the form configuration.
	 */
	protected function get_form_schema() {
		return array(
			'layout' => $this->get_form_layout_schema(),
			'fields' => array(
				'type'  => 'array',
				'items' => $this->get_form_field_schema(),
			),
		);
	}

	/**
	 * Adds the item count of each view to a post type's view list.
	 *
	 * Runs on the view list as the client receives it, after every view config
	 * filter, so a count always describes the view it sits on. Only a view whose
	 * total a status count answers gets one: a view without filters, which lists
	 * every status but trash, and a view whose only filter is on the status. A view
	 * narrowed by anything else, such as a date filter a plugin added, is left
	 * without a count rather than showing a total it does not hold. That includes
	 * filters a view inherits from the default view or from the defaults of any
	 * layout it can be shown in.
	 *
	 * `wp_count_posts()` answers every view from one cached query, and its
	 * `readable` permission argument keeps private posts the current user cannot
	 * read out of the totals.
	 *
	 * @since 7.3.0
	 *
	 * @param string       $post_type       The post type the view list belongs to.
	 * @param array        $view_list       The view list.
	 * @param array|object $default_view    The default view the views inherit from.
	 * @param array|object $default_layouts The default view properties of each layout.
	 * @return array The view list, with a count on each view a status total answers.
	 */
	protected function add_counts_to_view_list( $post_type, $view_list, $default_view = array(), $default_layouts = array() ) {
		$counts = (array) wp_count_posts( $post_type, 'readable' );

		foreach ( $view_list as $index => $entry ) {
			$statuses = $this->get_view_list_entry_statuses( $entry, $default_view, $default_layouts );
			if ( null === $statuses ) {
				continue;
			}

			$count = 0;
			foreach ( $statuses as $status ) {
				$count += isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
			}
			$view_list[ $index ]['count'] = $count;
		}

		return $view_list;
	}

	/**
	 * Returns the statuses a view list entry lists, when the status is all it
	 * filters by in every layout it can be shown in.
	 *
	 * The user can switch a view to any layout on offer, and each layout's defaults
	 * can bring filters of their own. The server cannot see which layout the user
	 * picked, so a count has to hold in all of them: when the layouts disagree, the
	 * entry gets none.
	 *
	 * The entry and defaults come from view config filters, so their shape is not
	 * trusted.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed        $entry           A view list entry.
	 * @param array|object $default_view    The default view the entry inherits from.
	 * @param array|object $default_layouts The default view properties of each layout.
	 * @return string[]|null The statuses, or null when the view filters by anything
	 *                       else in some layout or its shape is not recognized.
	 */
	protected function get_view_list_entry_statuses( $entry, $default_view = array(), $default_layouts = array() ) {
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$view            = isset( $entry['view'] ) ? (array) $entry['view'] : array();
		$default_view    = (array) $default_view;
		$default_layouts = (array) $default_layouts;

		// Without layouts on offer, no layout defaults apply.
		$layouts = empty( $default_layouts ) ? array( array() ) : $default_layouts;

		$statuses = null;
		foreach ( $layouts as $layout_defaults ) {
			$layout_statuses = $this->get_layered_view_statuses(
				array( $default_view, (array) $layout_defaults, $view )
			);
			if ( null === $layout_statuses || ( null !== $statuses && $layout_statuses !== $statuses ) ) {
				return null;
			}
			$statuses = $layout_statuses;
		}

		return $statuses;
	}

	/**
	 * Returns the statuses a view lists, when the status is all it filters by.
	 *
	 * Resolves the view's filters out of its layers as the client does: a later
	 * layer that sets them replaces them outright rather than adding to them. A
	 * `search` in a layer is ignored, as the client drops it from the config and
	 * takes the search from the URL instead.
	 *
	 * @since 7.3.0
	 *
	 * @param array[] $layers The view's layers, lowest first.
	 * @return string[]|null The statuses, or null when the view filters by anything
	 *                       else or its shape is not recognized.
	 */
	protected function get_layered_view_statuses( $layers ) {
		$filters = array();
		foreach ( $layers as $layer ) {
			if ( array_key_exists( 'filters', $layer ) ) {
				$filters = $layer['filters'];
			}
		}

		if ( ! is_array( $filters ) ) {
			return null;
		}
		if ( empty( $filters ) ) {
			// Every status but trash, matching what a view without filters queries.
			return array( 'publish', 'future', 'draft', 'pending', 'private' );
		}
		if ( 1 !== count( $filters ) ) {
			return null;
		}

		$filter = (array) reset( $filters );
		if (
			! isset( $filter['field'], $filter['operator'], $filter['value'] ) ||
			'status' !== $filter['field'] ||
			! in_array( $filter['operator'], array( 'is', 'isAny' ), true )
		) {
			return null;
		}

		$values   = is_array( $filter['value'] ) ? $filter['value'] : array( $filter['value'] );
		$statuses = array();
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				return null;
			}

			// The REST API also accepts several statuses in one value, separated by
			// commas or spaces, and parses them the same way.
			foreach ( wp_parse_slug_list( $value ) as $status ) {
				if ( 'any' === $status ) {
					// As in WP_Query, `any` is every status not excluded from search.
					$statuses = array_merge( $statuses, array_values( get_post_stati( array( 'exclude_from_search' => false ) ) ) );
				} else {
					$statuses[] = $status;
				}
			}
		}

		// A status listed twice is still queried once.
		return array_values( array_unique( $statuses ) );
	}
}
