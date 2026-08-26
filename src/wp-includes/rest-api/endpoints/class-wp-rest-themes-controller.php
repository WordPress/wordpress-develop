<?php
/**
 * REST API: WP_REST_Themes_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.0.0
 */

/**
 * Core class used to manage themes via the REST API.
 *
 * @since 5.0.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Themes_Controller extends WP_REST_Controller {

	/**
	 * Matches theme's directory: `/themes/<subdirectory>/<theme>/` or `/themes/<theme>/`.
	 * Excludes invalid directory name characters: `/:<>*?"|`.
	 */
	const PATTERN = '[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?';

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'themes';
	}

	/**
	 * Registers the routes for themes.
	 *
	 * @since 5.0.0
	 *
	 * @see register_rest_route()
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
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'slug'   => array(
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'WordPress.org theme directory slug.' ),
							'pattern'     => '[\w\-]+',
						),
						'status' => array(
							'description' => __( 'The theme activation status.' ),
							'type'        => 'string',
							'enum'        => array( 'inactive', 'active' ),
							'default'     => 'inactive',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			sprintf( '/%s/(?P<stylesheet>%s)', $this->rest_base, self::PATTERN ),
			array(
				'args'   => array(
					'stylesheet' => array(
						'description'       => __( "The theme's stylesheet. This uniquely identifies the theme." ),
						'type'              => 'string',
						'sanitize_callback' => array( $this, '_sanitize_stylesheet_callback' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Sanitize the stylesheet to decode endpoint.
	 *
	 * @since 5.9.0
	 *
	 * @param string $stylesheet The stylesheet name.
	 * @return string Sanitized stylesheet.
	 */
	public function _sanitize_stylesheet_callback( $stylesheet ) {
		return urldecode( $stylesheet );
	}

	/**
	 * Checks if a given request has access to read the theme.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, otherwise WP_Error object.
	 */
	public function get_items_permissions_check( $request ) {
		if ( current_user_can( 'switch_themes' ) || current_user_can( 'manage_network_themes' ) ) {
			return true;
		}

		$registered = $this->get_collection_params();
		if ( isset( $registered['status'], $request['status'] ) && is_array( $request['status'] ) && array( 'active' ) === $request['status'] ) {
			return $this->check_read_active_theme_permission();
		}

		return new WP_Error(
			'rest_cannot_view_themes',
			__( 'Sorry, you are not allowed to view themes.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Checks if a given request has access to read the theme.
	 *
	 * @since 5.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, otherwise WP_Error object.
	 */
	public function get_item_permissions_check( $request ) {
		if ( current_user_can( 'switch_themes' ) || current_user_can( 'manage_network_themes' ) ) {
			return true;
		}

		$wp_theme      = wp_get_theme( $request['stylesheet'] );
		$current_theme = wp_get_theme();

		if ( $this->is_same_theme( $wp_theme, $current_theme ) ) {
			return $this->check_read_active_theme_permission();
		}

		return new WP_Error(
			'rest_cannot_view_themes',
			__( 'Sorry, you are not allowed to view themes.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Checks if a theme can be read.
	 *
	 * @since 5.7.0
	 *
	 * @return true|WP_Error True if the theme can be read, WP_Error object otherwise.
	 */
	protected function check_read_active_theme_permission() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( current_user_can( $post_type->cap->edit_posts ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_cannot_view_active_theme',
			__( 'Sorry, you are not allowed to view the active theme.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Retrieves a single theme.
	 *
	 * @since 5.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$wp_theme = wp_get_theme( $request['stylesheet'] );
		if ( ! $wp_theme->exists() ) {
			return new WP_Error(
				'rest_theme_not_found',
				__( 'Theme not found.' ),
				array( 'status' => 404 )
			);
		}
		$data = $this->prepare_item_for_response( $wp_theme, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * Retrieves a collection of themes.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$themes = array();

		$current_theme = wp_get_theme();
		$status        = $request['status'];

		if ( array( 'active' ) === $status ) {
			$prepared = $this->prepare_item_for_response( $current_theme, $request );
			$themes[] = $this->prepare_response_for_collection( $prepared );
		} else {
			foreach ( wp_get_themes() as $theme ) {
				$theme_status = ( $this->is_same_theme( $theme, $current_theme ) ) ? 'active' : 'inactive';
				if ( is_array( $status ) && ! in_array( $theme_status, $status, true ) ) {
					continue;
				}

				$prepared = $this->prepare_item_for_response( $theme, $request );
				$themes[] = $this->prepare_response_for_collection( $prepared );
			}
		}

		$response = rest_ensure_response( $themes );

		$response->header( 'X-WP-Total', count( $themes ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Prepares a single theme output for response.
	 *
	 * @since 5.0.0
	 * @since 5.9.0 Renamed `$theme` to `$item` to match parent class for PHP 8 named parameter support.
	 * @since 6.6.0 Added `stylesheet_uri` and `template_uri` fields.
	 *
	 * @param WP_Theme        $item    Theme object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$theme = $item;

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( rest_is_field_included( 'stylesheet', $fields ) ) {
			$data['stylesheet'] = $theme->get_stylesheet();
		}

		if ( rest_is_field_included( 'template', $fields ) ) {
			/**
			 * Use the get_template() method, not the 'Template' header, for finding the template.
			 * The 'Template' header is only good for what was written in the style.css, while
			 * get_template() takes into account where WordPress actually located the theme and
			 * whether it is actually valid.
			 */
			$data['template'] = $theme->get_template();
		}

		$plain_field_mappings = array(
			'requires_php' => 'RequiresPHP',
			'requires_wp'  => 'RequiresWP',
			'textdomain'   => 'TextDomain',
			'version'      => 'Version',
		);

		foreach ( $plain_field_mappings as $field => $header ) {
			if ( rest_is_field_included( $field, $fields ) ) {
				$data[ $field ] = $theme->get( $header );
			}
		}

		if ( rest_is_field_included( 'screenshot', $fields ) ) {
			// Using $theme->get_screenshot() with no args to get absolute URL.
			$data['screenshot'] = $theme->get_screenshot() ? $theme->get_screenshot() : '';
		}

		$rich_field_mappings = array(
			'author'      => 'Author',
			'author_uri'  => 'AuthorURI',
			'description' => 'Description',
			'name'        => 'Name',
			'tags'        => 'Tags',
			'theme_uri'   => 'ThemeURI',
		);

		foreach ( $rich_field_mappings as $field => $header ) {
			if ( rest_is_field_included( "{$field}.raw", $fields ) ) {
				$data[ $field ]['raw'] = $theme->display( $header, false, true );
			}

			if ( rest_is_field_included( "{$field}.rendered", $fields ) ) {
				$data[ $field ]['rendered'] = $theme->display( $header );
			}
		}

		$current_theme = wp_get_theme();
		if ( rest_is_field_included( 'status', $fields ) ) {
			$data['status'] = ( $this->is_same_theme( $theme, $current_theme ) ) ? 'active' : 'inactive';
		}

		if ( rest_is_field_included( 'theme_supports', $fields ) && $this->is_same_theme( $theme, $current_theme ) ) {
			foreach ( get_registered_theme_features() as $feature => $config ) {
				if ( ! is_array( $config['show_in_rest'] ) ) {
					continue;
				}

				$name = $config['show_in_rest']['name'];

				if ( ! rest_is_field_included( "theme_supports.{$name}", $fields ) ) {
					continue;
				}

				if ( ! current_theme_supports( $feature ) ) {
					$data['theme_supports'][ $name ] = $config['show_in_rest']['schema']['default'];
					continue;
				}

				$support = get_theme_support( $feature );

				if ( isset( $config['show_in_rest']['prepare_callback'] ) ) {
					$prepare = $config['show_in_rest']['prepare_callback'];
				} else {
					$prepare = array( $this, 'prepare_theme_support' );
				}

				$prepared = $prepare( $support, $config, $feature, $request );

				if ( is_wp_error( $prepared ) ) {
					continue;
				}

				$data['theme_supports'][ $name ] = $prepared;
			}
		}

		if ( rest_is_field_included( 'is_block_theme', $fields ) ) {
			$data['is_block_theme'] = $theme->is_block_theme();
		}

		if ( rest_is_field_included( 'stylesheet_uri', $fields ) ) {
			if ( $this->is_same_theme( $theme, $current_theme ) ) {
				$data['stylesheet_uri'] = get_stylesheet_directory_uri();
			} else {
				$data['stylesheet_uri'] = $theme->get_stylesheet_directory_uri();
			}
		}

		if ( rest_is_field_included( 'template_uri', $fields ) ) {
			if ( $this->is_same_theme( $theme, $current_theme ) ) {
				$data['template_uri'] = get_template_directory_uri();
			} else {
				$data['template_uri'] = $theme->get_template_directory_uri();
			}
		}

		if ( rest_is_field_included( 'default_template_types', $fields ) && $this->is_same_theme( $theme, $current_theme ) ) {
			$default_template_types = array();
			foreach ( get_default_block_template_types() as $slug => $template_type ) {
				$template_type['slug']    = (string) $slug;
				$default_template_types[] = $template_type;
			}
			$data['default_template_types'] = $default_template_types;
		}

		if ( rest_is_field_included( 'default_template_part_areas', $fields ) && $this->is_same_theme( $theme, $current_theme ) ) {
			$data['default_template_part_areas'] = get_allowed_block_template_part_areas();
		}

		$data = $this->add_additional_fields_to_object( $data, $request );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) {
			$response->add_links( $this->prepare_links( $theme ) );
		}

		/**
		 * Filters theme data returned from the REST API.
		 *
		 * @since 5.0.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Theme         $theme    Theme object used to create response.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( 'rest_prepare_theme', $response, $theme, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @since 5.7.0
	 *
	 * @param WP_Theme $theme Theme data.
	 * @return array Links for the given block type.
	 */
	protected function prepare_links( $theme ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%s', $this->namespace, $this->rest_base, $theme->get_stylesheet() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		if ( $this->is_same_theme( $theme, wp_get_theme() ) ) {
			// This creates a record for the active theme if not existent.
			$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		} else {
			$user_cpt = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( $theme );
			$id       = $user_cpt['ID'] ?? null;
		}

		if ( $id ) {
			$links['https://api.w.org/user-global-styles'] = array(
				'href' => rest_url( 'wp/v2/global-styles/' . $id ),
			);
		}

		if ( $theme->is_block_theme() && $this->is_same_theme( $theme, wp_get_theme() ) ) {
			$links['https://api.w.org/export-theme'] = array(
				'href'        => rest_url( 'wp-block-editor/v1/export' ),
				'targetHints' => array(
					'allow' => current_user_can( 'export' ) ? array( 'GET' ) : array(),
				),
			);
		}

		return $links;
	}

	/**
	 * Helper function to compare two themes.
	 *
	 * @since 5.7.0
	 *
	 * @param WP_Theme $theme_a First theme to compare.
	 * @param WP_Theme $theme_b Second theme to compare.
	 * @return bool
	 */
	protected function is_same_theme( $theme_a, $theme_b ) {
		return $theme_a->get_stylesheet() === $theme_b->get_stylesheet();
	}

	/**
	 * Checks read permission for a given theme.
	 *
	 * @since 6.9.0
	 *
	 * @param string $theme_slug Theme slug to check.
	 * @return true|WP_Error
	 */
	protected function check_read_permission( $theme_slug ) {
		$theme = wp_get_theme( $theme_slug );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'rest_theme_not_found', __( 'Theme not found.' ), array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * Matches request with the theme.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Request data.
	 * @param WP_Theme $theme Theme object.
	 * @return bool
	 */
	protected function does_theme_match_request( $request, $theme ) {
		$status       = $request['status'];
		$theme_status = ( $this->is_same_theme( $theme, wp_get_theme() ) ) ? 'active' : 'inactive';
		if ( ! is_array( $status ) || in_array( $theme_status, $status, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Retrieves theme data.
	 *
	 * @since 6.9.0
	 *
	 * @param string $theme_slug Theme slug to be retrieved.
	 * @return WP_Theme|WP_Error
	 */
	protected function get_theme_data( $theme_slug ) {
		$theme = wp_get_theme( $theme_slug );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'rest_theme_not_found', __( 'Theme not found.' ), array( 'status' => 404 ) );
		}
		return $theme;
	}

	/**
	 * Prepares the theme support value for inclusion in the REST API response.
	 *
	 * @since 5.5.0
	 *
	 * @param mixed           $support The raw value from get_theme_support().
	 * @param array           $args    The feature's registration args.
	 * @param string          $feature The feature name.
	 * @param WP_REST_Request $request The request object.
	 * @return mixed The prepared support value.
	 */
	protected function prepare_theme_support( $support, $args, $feature, $request ) {
		$schema = $args['show_in_rest']['schema'];

		if ( 'boolean' === $schema['type'] ) {
			return true;
		}

		if ( is_array( $support ) && ! $args['variadic'] ) {
			$support = $support[0];
		}

		return rest_sanitize_value_from_schema( $support, $schema );
	}

	/**
	 * Retrieves the theme's schema, conforming to JSON Schema.
	 *
	 * @since 5.0.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'theme',
			'type'       => 'object',
			'properties' => array(
				'stylesheet'                  => array(
					'description' => __( 'The theme\'s stylesheet. This uniquely identifies the theme.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'stylesheet_uri'              => array(
					'description' => __( 'The uri for the theme\'s stylesheet directory.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'template'                    => array(
					'description' => __( 'The theme\'s template. If this is a child theme, this refers to the parent theme, otherwise this is the same as the theme\'s stylesheet.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'template_uri'                => array(
					'description' => __( 'The uri for the theme\'s template directory. If this is a child theme, this refers to the parent theme, otherwise this is the same as the theme\'s stylesheet directory.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'author'                      => array(
					'description' => __( 'The theme author.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'The theme author\'s name, as found in the theme header.' ),
							'type'        => 'string',
						),
						'rendered' => array(
							'description' => __( 'HTML for the theme author, transformed for display.' ),
							'type'        => 'string',
						),
					),
				),
				'author_uri'                  => array(
					'description' => __( 'The website of the theme author.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'The website of the theme author, as found in the theme header.' ),
							'type'        => 'string',
							'format'      => 'uri',
						),
						'rendered' => array(
							'description' => __( 'The website of the theme author, transformed for display.' ),
							'type'        => 'string',
							'format'      => 'uri',
						),
					),
				),
				'description'                 => array(
					'description' => __( 'A description of the theme.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'The theme description, as found in the theme header.' ),
							'type'        => 'string',
						),
						'rendered' => array(
							'description' => __( 'The theme description, transformed for display.' ),
							'type'        => 'string',
						),
					),
				),
				'is_block_theme'              => array(
					'description' => __( 'Whether the theme is a block-based theme.' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'name'                        => array(
					'description' => __( 'The name of the theme.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'The theme name, as found in the theme header.' ),
							'type'        => 'string',
						),
						'rendered' => array(
							'description' => __( 'The theme name, transformed for display.' ),
							'type'        => 'string',
						),
					),
				),
				'requires_php'                => array(
					'description' => __( 'The minimum PHP version required for the theme to work.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'requires_wp'                 => array(
					'description' => __( 'The minimum WordPress version required for the theme to work.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'screenshot'                  => array(
					'description' => __( 'The theme\'s screenshot URL.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'tags'                        => array(
					'description' => __( 'Tags indicating styles and features of the theme.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'The theme tags, as found in the theme header.' ),
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
						),
						'rendered' => array(
							'description' => __( 'The theme tags, transformed for display.' ),
							'type'        => 'string',
						),
					),
				),
				'textdomain'                  => array(
					'description' => __( 'The theme\'s text domain.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'theme_supports'              => array(
					'description' => __( 'Features supported by this theme.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(),
				),
				'theme_uri'                   => array(
					'description' => __( 'The URI of the theme\'s webpage.' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'The URI of the theme\'s webpage, as found in the theme header.' ),
							'type'        => 'string',
							'format'      => 'uri',
						),
						'rendered' => array(
							'description' => __( 'The URI of the theme\'s webpage, transformed for display.' ),
							'type'        => 'string',
							'format'      => 'uri',
						),
					),
				),
				'version'                     => array(
					'description' => __( 'The theme\'s current version.' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'status'                      => array(
					'description' => __( 'A named status for the theme.' ),
					'type'        => 'string',
					'enum'        => array( 'inactive', 'active' ),
					'context'     => array( 'view', 'edit' ),
				),
				'default_template_types'      => array(
					'description' => __( 'A list of default template types.' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'slug'        => array(
								'type' => 'string',
							),
							'title'       => array(
								'type' => 'string',
							),
							'description' => array(
								'type' => 'string',
							),
						),
					),
				),
				'default_template_part_areas' => array(
					'description' => __( 'A list of allowed area values for template parts.' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'area'        => array(
								'type' => 'string',
							),
							'label'       => array(
								'type' => 'string',
							),
							'description' => array(
								'type' => 'string',
							),
							'icon'        => array(
								'type' => 'string',
							),
							'area_tag'    => array(
								'type' => 'string',
							),
						),
					),
				),
			),
		);

		foreach ( get_registered_theme_features() as $feature => $config ) {
			if ( ! is_array( $config['show_in_rest'] ) ) {
				continue;
			}

			$name = $config['show_in_rest']['name'];

			$schema['properties']['theme_supports']['properties'][ $name ] = $config['show_in_rest']['schema'];
		}

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the search params for the themes collection.
	 *
	 * @since 5.0.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = array(
			'status' => array(
				'description' => __( 'Limit result set to themes assigned one or more statuses.' ),
				'type'        => 'array',
				'items'       => array(
					'enum' => array( 'active', 'inactive' ),
					'type' => 'string',
				),
			),
		);

		/**
		 * Filters REST API collection parameters for the themes controller.
		 *
		 * @since 5.0.0
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_themes_collection_params', $query_params );
	}

	/**
	 * Sanitizes and validates the list of theme status.
	 *
	 * @since 5.0.0
	 * @deprecated 5.7.0
	 *
	 * @param string|array    $statuses  One or more theme statuses.
	 * @param WP_REST_Request $request   Full details about the request.
	 * @param string          $parameter Additional parameter to pass to validation.
	 * @return array|WP_Error A list of valid statuses, otherwise WP_Error object.
	 */
	public function sanitize_theme_status( $statuses, $request, $parameter ) {
		_deprecated_function( __METHOD__, '5.7.0' );

		$statuses = wp_parse_slug_list( $statuses );

		foreach ( $statuses as $status ) {
			$result = rest_validate_request_arg( $status, $request, $parameter );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $statuses;
	}
	/**
	 * Checks permissions to update a theme (switch themes).
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return true|WP_Error True if the request has access to edit items, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'switch_themes' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to switch themes for this site.' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Update a theme (switch themes).
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$theme_slug = $request['theme'];

		$theme = $this->get_theme_data( $theme_slug );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}

		// Check if we need to switch the theme
		if ( isset( $request['status'] ) && 'active' === $request['status'] ) {
			switch_theme( $theme_slug );
		}

		return $this->prepare_item_for_response( $theme, $request );
	}

	/**
	 * Checks permissions to install a theme.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'install_themes' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to install themes on this site.' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Install a theme.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		$slug = $request['slug'];
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Verify filesystem is accessible first.
		$filesystem_available = $this->is_filesystem_available();
		if ( is_wp_error( $filesystem_available ) ) {
			return $filesystem_available;
		}

		// Get theme information from WordPress.org API.
		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $api ) ) {
			if ( str_contains( $api->get_error_message(), 'Theme not found.' ) ) {
				$api->add_data( array( 'status' => 404 ) );
			} else {
				$api->add_data( array( 'status' => 500 ) );
			}
			return $api;
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );

		$result = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ) );
			return $result;
		}

		// Get the installed theme file
		$theme_file = $upgrader->theme_info();
		if ( ! $theme_file ) {
			return new WP_Error(
				'unable_to_determine_installed_theme',
				__( 'Unable to determine what theme was installed.' ),
				array( 'status' => 500 )
			);
		}

		$theme = wp_get_theme( $theme_file );

		// Check if we need to activate the theme
		if ( isset( $request['status'] ) && 'active' === $request['status'] ) {
			switch_theme( $theme_file );
		}

		$response = $this->prepare_item_for_response( $theme, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%s', $this->namespace, $this->rest_base, $theme_file ) ) );

		return $response;
	}


	/**
	 * Checks if the filesystem is available to perform theme installations.
	 *
	 * @since 6.9.0
	 *
	 * @return true|WP_Error True if the filesystem is available, or an error object if not.
	 */
	protected function is_filesystem_available() {
		$filesystem_method = get_filesystem_method();

		if ( 'direct' === $filesystem_method ) {
			return true;
		}

		ob_start();
		$credentials = request_filesystem_credentials( site_url() );
		ob_end_clean();

		if ( $credentials ) {
			return true;
		}

		return new WP_Error( 'fs_unavailable', __( 'Could not access filesystem.' ), array( 'status' => 500 ) );
	}

	/**
	 * Checks if a given request has access to delete a theme.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( 'delete_themes' ) ) {
			return new WP_Error(
				'rest_cannot_delete_theme',
				__( 'Sorry, you are not allowed to delete themes for this site.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$theme = $this->get_theme_data( $request['theme'] );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}

		// Cannot delete active theme.
		if ( $this->is_same_theme( $theme, wp_get_theme() ) ) {
			return new WP_Error(
				'rest_cannot_delete_active_theme',
				__( 'Cannot delete the active theme.' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Deletes a theme.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$theme = $this->get_theme_data( $request['theme'] );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}

		// Verify filesystem is accessible first.
		$filesystem_available = $this->is_filesystem_available();
		if ( is_wp_error( $filesystem_available ) ) {
			return $filesystem_available;
		}

		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$previous_theme_data = $this->prepare_item_for_response( $theme, $request );

		$result = delete_theme( $theme->get_stylesheet() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ) );
			return $result;
		}

		return new WP_REST_Response(
			array(
				'deleted'  => true,
				'previous' => $previous_theme_data->get_data(),
			),
			200
		);
	}

	/**
	 * Validates theme param.
	 *
	 * @since 6.9.0
	 *
	 * @param string $value Theme parameter value.
	 * @return true|WP_Error
	 */
	public function validate_theme_param( $value ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return new WP_Error( 'rest_invalid_theme', __( 'Invalid theme.' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Sanitizes theme param.
	 *
	 * @since 6.9.0
	 *
	 * @param string $value Theme parameter value.
	 * @return string
	 */
	public function sanitize_theme_param( $value ) {
		return urldecode( $value );
	}
}
