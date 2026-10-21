<?php
/**
 * Unit tests covering WP_REST_View_Config_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 * @group view-config
 *
 * @coversDefaultClass WP_REST_View_Config_Controller
 */
class WP_REST_View_Config_Controller_Test extends WP_Test_REST_TestCase {

	/**
	 * The REST route the controller registers.
	 */
	const ROUTE = '/wp/v2/view-config';

	/**
	 * Administrator user id (has `edit_theme_options` and `manage_options`).
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Editor user id (has `edit_posts` and `manage_categories`, lacks `manage_options`).
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Subscriber user id (lacks `edit_posts`).
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Creates shared users and the pages the view counts are made of.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id     = $factory->user->create( array( 'role' => 'editor' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		$factory->post->create_many(
			2,
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		$factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'pending',
			)
		);
		$factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'trash',
			)
		);
	}

	/**
	 * Deletes shared users.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$editor_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 * Dispatches a request to the view-config route.
	 *
	 * @param string $kind Entity kind.
	 * @param string $name Entity name.
	 * @return WP_REST_Response
	 */
	private function dispatch_request( $kind = 'postType', $name = 'page' ) {
		$request = new WP_REST_Request( 'GET', self::ROUTE );
		if ( null !== $kind ) {
			$request->set_param( 'kind', $kind );
		}
		if ( null !== $name ) {
			$request->set_param( 'name', $name );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Returns the `page` view list from the view-config route, keyed by slug.
	 *
	 * @return array The view list entries, keyed by slug.
	 */
	private function get_page_view_list() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_request( 'postType', 'page' );
		$this->assertSame( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );
		return array_column( $data['view_list'], null, 'slug' );
	}

	/**
	 * The route is registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( self::ROUTE, $routes );
	}

	/**
	 * Editors (with `edit_posts`) can read the view config.
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::get_items
	 */
	public function test_get_items_allows_users_with_edit_posts() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_request();

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Subscribers (without `edit_posts`) are forbidden.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_forbids_users_without_edit_posts() {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->dispatch_request();

		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	/**
	 * Logged-out users are unauthorized.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_get_items_requires_authentication() {
		wp_set_current_user( 0 );

		$response = $this->dispatch_request();

		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	/**
	 * Post type config is gated by that post type's own `edit_posts` capability,
	 * honoring custom capability registrations.
	 *
	 * `wp_template_part` maps `edit_posts` to `edit_theme_options`, which editors
	 * lack but administrators have.
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::get_required_capability
	 */
	public function test_get_items_uses_post_type_specific_capability() {
		wp_set_current_user( self::$editor_id );
		$this->assertErrorResponse(
			'rest_cannot_read',
			$this->dispatch_request( 'postType', 'wp_template_part' ),
			403
		);

		wp_set_current_user( self::$admin_id );
		$this->assertSame(
			200,
			$this->dispatch_request( 'postType', 'wp_template_part' )->get_status()
		);
	}

	/**
	 * An unregistered post type is treated as an unknown entity (404).
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::get_required_capability
	 */
	public function test_get_items_unknown_post_type_is_not_found() {
		wp_set_current_user( self::$admin_id );

		$response = $this->dispatch_request( 'postType', 'does_not_exist' );

		$this->assertErrorResponse( 'rest_view_config_invalid_entity', $response, 404 );
	}

	/**
	 * Taxonomy config is gated by the taxonomy's `manage_terms` capability.
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::get_required_capability
	 */
	public function test_get_items_uses_taxonomy_capability() {
		// Editors have `manage_categories` (the `manage_terms` cap for `category`).
		wp_set_current_user( self::$editor_id );
		$this->assertSame(
			200,
			$this->dispatch_request( 'taxonomy', 'category' )->get_status()
		);

		// Subscribers do not.
		wp_set_current_user( self::$subscriber_id );
		$this->assertErrorResponse(
			'rest_cannot_read',
			$this->dispatch_request( 'taxonomy', 'category' ),
			403
		);
	}

	/**
	 * An unregistered taxonomy is treated as an unknown entity (404).
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::get_required_capability
	 */
	public function test_get_items_unknown_taxonomy_is_not_found() {
		wp_set_current_user( self::$admin_id );

		$response = $this->dispatch_request( 'taxonomy', 'does_not_exist' );

		$this->assertErrorResponse( 'rest_view_config_invalid_entity', $response, 404 );
	}

	/**
	 * Root-level config requires `manage_options`.
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::get_required_capability
	 */
	public function test_get_items_root_requires_manage_options() {
		// Editors lack `manage_options`.
		wp_set_current_user( self::$editor_id );
		$this->assertErrorResponse(
			'rest_cannot_read',
			$this->dispatch_request( 'root', 'site' ),
			403
		);

		wp_set_current_user( self::$admin_id );
		$this->assertSame(
			200,
			$this->dispatch_request( 'root', 'site' )->get_status()
		);
	}

	/**
	 * Unknown kinds fall back to the baseline `edit_posts` capability so that
	 * entities registered through the view config filter remain readable.
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::get_required_capability
	 */
	public function test_get_items_unknown_kind_falls_back_to_edit_posts() {
		wp_set_current_user( self::$editor_id );
		$this->assertSame(
			200,
			$this->dispatch_request( 'custom_kind', 'custom_name' )->get_status()
		);

		wp_set_current_user( self::$subscriber_id );
		$this->assertErrorResponse(
			'rest_cannot_read',
			$this->dispatch_request( 'custom_kind', 'custom_name' ),
			403
		);
	}

	/**
	 * Both `kind` and `name` are required.
	 *
	 * @covers ::register_routes
	 */
	public function test_get_items_requires_kind_and_name() {
		wp_set_current_user( self::$editor_id );

		$missing_name = $this->dispatch_request( 'postType', null );
		$this->assertErrorResponse( 'rest_missing_callback_param', $missing_name, 400 );

		$missing_kind = $this->dispatch_request( null, 'page' );
		$this->assertErrorResponse( 'rest_missing_callback_param', $missing_kind, 400 );
	}

	/**
	 * The response echoes the requested entity and the documented config keys.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_returns_entity_and_config_shape() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_request( 'postType', 'page' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'postType', $data['kind'] );
		$this->assertSame( 'page', $data['name'] );
		$this->assertSame( WP_View_Config_Data::LATEST_VERSION, $data['version'] );
		$this->assertArrayHasKey( 'default_view', $data );
		$this->assertArrayHasKey( 'default_layouts', $data );
		$this->assertArrayHasKey( 'view_list', $data );
		$this->assertArrayHasKey( 'form', $data );
	}

	/**
	 * The response body matches wp_get_entity_view_config() for the entity.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_matches_underlying_config() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_request( 'postType', 'page' );
		// Normalize through JSON to compare what a client actually receives:
		// the response's object casts collapse back to the source arrays.
		$data   = json_decode( wp_json_encode( $response->get_data() ), true );
		$config = wp_get_entity_view_config( 'postType', 'page' );

		$this->assertSame( $config['default_view'], $data['default_view'] );
		$this->assertSame( $config['default_layouts'], $data['default_layouts'] );
		// The controller adds the item counts to the `page` view list.
		$this->assertSame( _wp_add_counts_to_view_list( 'page', $config['view_list'], $config['default_view'], $config['default_layouts'] ), $data['view_list'] );
		$this->assertSame( $config['form'], $data['form'] );
	}

	/**
	 * The `attachment` post type provides the form of the media editor.
	 *
	 * @ticket 66229
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_attachment_form() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_request( 'postType', 'attachment' );
		$this->assertSame( 200, $response->get_status(), 'The request succeeds.' );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );

		$this->assertSame( array( 'type' => 'panel' ), $data['form']['layout'], 'The form uses a panel layout.' );
		$this->assertSame(
			array( 'title', 'alt_text', 'caption', 'description', 'date', 'author', 'filename', 'mime_type', 'filesize', 'media_dimensions', 'attached_to' ),
			array_map(
				static function ( $field ) {
					return is_array( $field ) ? $field['id'] : $field;
				},
				$data['form']['fields']
			),
			'The form lists the media editor fields in order.'
		);
	}

	/**
	 * The `root`/`site` entity provides the form of the site identity screen.
	 *
	 * @ticket 65981
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_root_site_form() {
		// Admin: reading root config requires `manage_options`.
		wp_set_current_user( self::$admin_id );

		$response = $this->dispatch_request( 'root', 'site' );
		$this->assertSame( 200, $response->get_status() );

		$data = json_decode( wp_json_encode( $response->get_data() ), true );

		$this->assertSame(
			array(
				'type'          => 'regular',
				'labelPosition' => 'top',
			),
			$data['form']['layout']
		);
		$this->assertSame(
			array( 'title', 'description', 'site_logo', 'site_icon' ),
			$data['form']['fields']
		);
	}

	/**
	 * Empty object-typed config values serialize as JSON objects ({}), not arrays ([]).
	 *
	 * @covers ::get_items
	 */
	public function test_empty_object_fields_serialize_as_json_objects() {
		// Admin: reading wp_template_part config requires `edit_theme_options`.
		wp_set_current_user( self::$admin_id );

		// An entity with no provider yields empty default_layouts entries and form.
		$decoded = json_decode( wp_json_encode( $this->dispatch_request( 'custom_kind', 'custom_name' )->get_data() ) );

		$this->assertIsObject( $decoded->form );
		$this->assertIsObject( $decoded->default_layouts->table );
		$this->assertIsObject( $decoded->default_layouts->grid );
		$this->assertIsObject( $decoded->default_layouts->list );

		// wp_template_part yields an empty default_view.layout and grid layout.
		$decoded = json_decode( wp_json_encode( $this->dispatch_request( 'postType', 'wp_template_part' )->get_data() ) );

		$this->assertIsObject( $decoded->default_view->layout );
		$this->assertIsObject( $decoded->default_layouts->grid->layout );
	}

	/**
	 * Empty object-typed values nested inside a `view_list` item's `view`
	 * override serialize as JSON objects ({}), not arrays ([]).
	 *
	 * The `view.layout` (and its `styles` map) are typed as objects by the
	 * schema but are not produced by core data, so this exercises the
	 * schema-driven cast against a value supplied through the documented
	 * `get_entity_view_config_{$kind}_{$name}` filter.
	 *
	 * @covers ::get_items
	 */
	public function test_empty_objects_inside_view_list_view_serialize_as_json_objects() {
		wp_set_current_user( self::$editor_id );

		$filter = static function ( $data ) {
			return $data->merge(
				array(
					'view_list' => array(
						array(
							'slug'  => 'custom',
							'title' => 'Custom',
							'view'  => array(
								'type'   => 'table',
								'layout' => array(
									'styles' => array(),
								),
							),
						),
					),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_custom_kind_custom_name', $filter );

		$decoded = json_decode( wp_json_encode( $this->dispatch_request( 'custom_kind', 'custom_name' )->get_data() ) );

		remove_filter( 'get_entity_view_config_custom_kind_custom_name', $filter );

		$view = end( $decoded->view_list );
		$this->assertIsObject( $view->view->layout );
		$this->assertIsObject( $view->view->layout->styles );
	}

	/**
	 * The item schema exposes the documented top-level properties.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$controller = new WP_REST_View_Config_Controller();
		$schema     = $controller->get_item_schema();

		$this->assertSame( 'view-config', $schema['title'] );
		$this->assertSameSets(
			array( 'kind', 'name', 'version', 'default_view', 'default_layouts', 'view_list', 'form' ),
			array_keys( $schema['properties'] )
		);
	}

	/**
	 * `search` and `page` are not part of the view schema: they are managed via
	 * the URL, which is their only source of truth.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema_excludes_url_managed_view_properties() {
		$controller = new WP_REST_View_Config_Controller();
		$schema     = $controller->get_item_schema();

		$views = array(
			'default_view'             => $schema['properties']['default_view']['properties'],
			'view_list item view'      => $schema['properties']['view_list']['items']['properties']['view']['properties'],
			'default_layouts.table'    => $schema['properties']['default_layouts']['properties']['table']['properties'],
			'default_layouts.grid'     => $schema['properties']['default_layouts']['properties']['grid']['properties'],
			'default_layouts.list'     => $schema['properties']['default_layouts']['properties']['list']['properties'],
			'default_layouts.activity' => $schema['properties']['default_layouts']['properties']['activity']['properties'],
		);

		foreach ( $views as $label => $properties ) {
			$this->assertArrayNotHasKey( 'search', $properties, "$label should not declare a `search` property." );
			$this->assertArrayNotHasKey( 'page', $properties, "$label should not declare a `page` property." );
		}
	}

	/**
	 * Each status view of the `page` view list counts the pages in its status,
	 * and the "All" view counts every status but trash.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_counts_page_status_views() {
		$view_list = $this->get_page_view_list();

		$this->assertSame( 4, $view_list['all']['count'] );
		$this->assertSame( 1, $view_list['published']['count'] );
		$this->assertSame( 0, $view_list['future']['count'] );
		$this->assertSame( 2, $view_list['drafts']['count'] );
		$this->assertSame( 1, $view_list['pending']['count'] );
		$this->assertSame( 0, $view_list['private']['count'] );
		$this->assertSame( 1, $view_list['trash']['count'] );
	}

	/**
	 * A view narrowed by more than its status gets no count, since a status
	 * total would not describe it.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_omits_count_of_view_narrowed_by_other_filters() {
		$narrow_drafts = static function ( $data ) {
			return $data->merge(
				array(
					'view_list' => array(
						array(
							'slug' => 'drafts',
							'view' => array(
								'filters' => array(
									array(
										'field'    => 'date',
										'operator' => 'after',
										'value'    => '2018-01-01T00:00:00',
									),
								),
							),
						),
					),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_posttype_page', $narrow_drafts );
		$view_list = $this->get_page_view_list();
		remove_filter( 'get_entity_view_config_posttype_page', $narrow_drafts );

		$this->assertArrayNotHasKey( 'count', $view_list['drafts'] );
		$this->assertSame( 1, $view_list['published']['count'], 'Views the filter leaves alone keep their count.' );
	}

	/**
	 * A view a plugin adds gets a count when the status is all it filters by.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_counts_added_status_only_view() {
		$add_unpublished = static function ( $data ) {
			return $data->merge(
				array(
					'view_list' => array(
						array(
							'slug'  => 'unpublished',
							'title' => 'Unpublished',
							'view'  => array(
								'filters' => array(
									array(
										'field'    => 'status',
										'operator' => 'isAny',
										'value'    => array( 'draft', 'pending' ),
									),
								),
							),
						),
					),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_posttype_page', $add_unpublished );
		$view_list = $this->get_page_view_list();
		remove_filter( 'get_entity_view_config_posttype_page', $add_unpublished );

		$this->assertSame( 3, $view_list['unpublished']['count'] );
	}

	/**
	 * A view without filters of its own inherits those of the default view, so
	 * a filter there leaves the "All" view without a count. The status views
	 * set their own filters, which replace the inherited ones, so they keep
	 * theirs.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_omits_count_of_view_inheriting_default_view_filters() {
		$filter_default_view = static function ( $data ) {
			return $data->merge(
				array(
					'default_view' => array(
						'filters' => array(
							array(
								'field'    => 'author',
								'operator' => 'isAny',
								'value'    => array( 1 ),
							),
						),
					),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_posttype_page', $filter_default_view );
		$view_list = $this->get_page_view_list();
		remove_filter( 'get_entity_view_config_posttype_page', $filter_default_view );

		$this->assertArrayNotHasKey( 'count', $view_list['all'] );
		$this->assertSame( 2, $view_list['drafts']['count'] );
	}

	/**
	 * A view without filters of its own also inherits those of its layout's
	 * defaults, which take precedence over the default view's.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_omits_count_of_view_inheriting_layout_filters() {
		$filter_table_layout = static function ( $data ) {
			return $data->merge(
				array(
					'default_view'    => array( 'type' => 'table' ),
					'default_layouts' => array(
						'table' => array(
							'filters' => array(
								array(
									'field'    => 'author',
									'operator' => 'isAny',
									'value'    => array( 1 ),
								),
							),
						),
					),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_posttype_page', $filter_table_layout );
		$view_list = $this->get_page_view_list();
		remove_filter( 'get_entity_view_config_posttype_page', $filter_table_layout );

		$this->assertArrayNotHasKey( 'count', $view_list['all'] );
		$this->assertSame( 2, $view_list['drafts']['count'] );
	}

	/**
	 * The user can switch a view to any layout on offer, which the server cannot
	 * see, so a filter in the defaults of a layout other than the default one
	 * also leaves the "All" view without a count.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_omits_count_when_another_layout_has_filters() {
		$filter_table_layout = static function ( $data ) {
			return $data->merge(
				array(
					'default_layouts' => array(
						'table' => array(
							'filters' => array(
								array(
									'field'    => 'author',
									'operator' => 'isAny',
									'value'    => array( 1 ),
								),
							),
						),
					),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_posttype_page', $filter_table_layout );
		$view_list = $this->get_page_view_list();
		remove_filter( 'get_entity_view_config_posttype_page', $filter_table_layout );

		$this->assertArrayNotHasKey( 'count', $view_list['all'] );
		$this->assertSame( 2, $view_list['drafts']['count'], 'Views with filters of their own keep their count in every layout.' );
	}

	/**
	 * A search in the config does not narrow the views: the client drops it and
	 * takes the search from the URL instead.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_counts_views_despite_a_search_in_the_config() {
		$search_default_view = static function ( $data ) {
			return $data->merge(
				array(
					'default_view' => array( 'search' => 'hello' ),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_posttype_page', $search_default_view );
		$view_list = $this->get_page_view_list();
		remove_filter( 'get_entity_view_config_posttype_page', $search_default_view );

		$this->assertSame( 4, $view_list['all']['count'] );
		$this->assertSame( 2, $view_list['drafts']['count'] );
	}

	/**
	 * A status listed twice in a view's filter is counted once, as the query
	 * lists it once.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_counts_a_repeated_status_once() {
		$add_repeated = static function ( $data ) {
			return $data->merge(
				array(
					'view_list' => array(
						array(
							'slug'  => 'repeated',
							'title' => 'Repeated',
							'view'  => array(
								'filters' => array(
									array(
										'field'    => 'status',
										'operator' => 'isAny',
										'value'    => array( 'draft', 'draft', 'pending' ),
									),
								),
							),
						),
					),
				),
				1
			);
		};
		add_filter( 'get_entity_view_config_posttype_page', $add_repeated );
		$view_list = $this->get_page_view_list();
		remove_filter( 'get_entity_view_config_posttype_page', $add_repeated );

		$this->assertSame( 3, $view_list['repeated']['count'] );
	}

	/**
	 * The counts are not computed when the request leaves out the view list.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_skips_counts_when_view_list_is_not_requested() {
		wp_set_current_user( self::$editor_id );

		$count_calls = 0;
		$count_posts = static function ( $counts ) use ( &$count_calls ) {
			++$count_calls;
			return $counts;
		};
		add_filter( 'wp_count_posts', $count_posts );

		$request = new WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'kind', 'postType' );
		$request->set_param( 'name', 'page' );
		$request->set_param( '_fields', 'form' );
		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'wp_count_posts', $count_posts );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 0, $count_calls );
	}
}
