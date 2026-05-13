<?php
/**
 * Distributed Editing reason-code helpers.
 *
 * @package WordPress
 */

/**
 * Returns the canonical Distributed Editing reason-code status map.
 *
 * This helper is intentionally inert. It only defines the authority vocabulary
 * for future DE-RTC server responses and does not register endpoints, settings,
 * filters, schema, save-path behavior, or post-lock behavior.
 *
 * @since 7.1.0
 *
 * @return int[] HTTP status codes keyed by canonical reason code.
 */
function wp_de_rtc_get_reason_codes() {
	return array(
		'de_rtc_missing_sync_meta'                     => 409,
		'de_rtc_sync_meta_restored_from_revision'      => 409,
		'de_rtc_sync_meta_unrecoverable'               => 409,
		'de_rtc_external_content_mismatch'             => 409,
		'de_rtc_base_version_stale'                    => 409,
		'stale_base_version_rejected'                  => 409,
		'de_rtc_live_session_newer_than_restored_meta' => 409,
		'de_rtc_rebase_failed'                         => 409,
		'de_rtc_sync_meta_tampered'                    => 403,
		'de_rtc_unfiltered_html_would_change_content'  => 403,
		'de_rtc_feature_disabled'                      => 403,
		'de_rtc_malformed_sync_payload'                => 400,
		'de_rtc_unknown_sync_meta_format'              => 400,
		'de_rtc_storage_failure'                       => 500,
	);
}

/**
 * Returns supported Distributed Editing sync-meta format labels.
 *
 * These labels identify the payload grammar only. This helper does not choose
 * or initialize any synchronization algorithm.
 *
 * @since 7.1.0
 *
 * @return string[] Supported sync-meta format labels.
 */
function wp_de_rtc_get_supported_sync_meta_formats() {
	return array(
		'diff-match-patch',
		'yjs',
		'automerge',
	);
}

/**
 * Registers Distributed Editing REST routes.
 *
 * The current route is an internal proof boundary for sync-meta recovery. It is
 * feature-gated and is not yet wired to Gutenberg save flows or post-lock
 * replacement.
 *
 * @since 7.1.0
 */
function wp_de_rtc_register_rest_routes() {
	foreach ( wp_de_rtc_get_rest_recovery_post_type_rest_bases() as $rest_base ) {
		register_rest_route(
			'wp/v2',
			'/' . $rest_base . '/(?P<id>[\d]+)/distributed-editing/recovery',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the post.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'wp_de_rtc_rest_recovery_endpoint',
					'permission_callback' => 'wp_de_rtc_rest_recovery_permissions_check',
					'args'                => array(
						'mode'                        => array(
							'description' => __( 'Recovery execution mode.' ),
							'type'        => 'string',
							'enum'        => array( 'dry_run', 'apply' ),
							'default'     => 'dry_run',
						),
						'candidate_post_content_hash' => array(
							'description' => __( 'Expected SHA-256 hash of the server-derived recovery candidate.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
					),
				),
			)
		);

		register_rest_route(
			'wp/v2',
			'/' . $rest_base . '/(?P<id>[\d]+)/distributed-editing/stale-base',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the post.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'wp_de_rtc_rest_stale_base_endpoint',
					'permission_callback' => 'wp_de_rtc_rest_stale_base_permissions_check',
					'args'                => array(
						'client_base_version'      => array(
							'description' => __( 'Distributed Editing sync version that the client edited from.' ),
							'type'        => 'string',
							'required'    => true,
						),
						'server_version'           => array(
							'description' => __( 'Current server-side Distributed Editing sync version, when already known to the caller.' ),
							'type'        => 'string',
						),
						'pending_change_count'     => array(
							'description' => __( 'Number of pending local change groups the client believes are unconfirmed.' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 1,
						),
						'remote_change_count'      => array(
							'description' => __( 'Number of remote change groups the server is reporting since the client base version.' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 1,
						),
						'can_attempt_local_rebase' => array(
							'description' => __( 'Whether the client may attempt a local rebase without first refetching server state.' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);

		register_rest_route(
			'wp/v2',
			'/' . $rest_base . '/(?P<id>[\d]+)/distributed-editing/retry-submit',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the post.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'wp_de_rtc_rest_retry_submit_endpoint',
					'permission_callback' => 'wp_de_rtc_rest_retry_submit_permissions_check',
					'args'                => array(
						'client_base_version'        => array(
							'description' => __( 'Distributed Editing sync version that the rebased client edits are based on.' ),
							'type'        => 'string',
							'required'    => true,
						),
						'rebased_from_version'       => array(
							'description' => __( 'Original stale Distributed Editing sync version that the client rebased from.' ),
							'type'        => 'string',
						),
						'pending_change_count'       => array(
							'description' => __( 'Number of pending local change groups the client is retrying.' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 1,
						),
						'proposed_post_content_hash' => array(
							'description' => __( 'SHA-256 hash of the client proposed post content.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
					),
				),
			)
		);

		register_rest_route(
			'wp/v2',
			'/' . $rest_base . '/(?P<id>[\d]+)/distributed-editing/retry-save',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the post.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'wp_de_rtc_rest_retry_save_endpoint',
					'permission_callback' => 'wp_de_rtc_rest_retry_save_permissions_check',
					'args'                => array(
						'client_base_version'          => array(
							'description' => __( 'Distributed Editing sync version that the rebased client edits are based on.' ),
							'type'        => 'string',
							'required'    => true,
						),
						'accepted_proof_server_version' => array(
							'description' => __( 'Server sync version from the accepted retry-submit proof.' ),
							'type'        => 'string',
							'required'    => true,
						),
						'rebased_from_version'         => array(
							'description' => __( 'Original stale Distributed Editing sync version that the client rebased from.' ),
							'type'        => 'string',
						),
						'pending_change_count'         => array(
							'description' => __( 'Number of pending local change groups the client is saving.' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 1,
						),
						'proposed_post_content'        => array(
							'description' => __( 'Client-proposed post content without Distributed Editing sync metadata.' ),
							'type'        => 'string',
						),
						'proposed_post_content_hash'   => array(
							'description' => __( 'SHA-256 hash of the proposed post content without sync metadata.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'accepted_proof_saves_post'    => array(
							'description' => __( 'Whether the accepted proof claimed to save the post.' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'accepted_proof_mutates_post_content' => array(
							'description' => __( 'Whether the accepted proof claimed to mutate post content.' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'accepted_proof_creates_revision' => array(
							'description' => __( 'Whether the accepted proof claimed to create a revision.' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'accepted_proof_claims_saved'  => array(
							'description' => __( 'Whether the accepted proof claimed saved state.' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}
}
add_action( 'rest_api_init', 'wp_de_rtc_register_rest_routes' );

add_filter( 'rest_pre_insert_post', 'wp_de_rtc_rest_pre_insert_stale_base_probe', 10, 2 );
add_filter( 'rest_pre_insert_page', 'wp_de_rtc_rest_pre_insert_stale_base_probe', 10, 2 );

/**
 * Returns REST bases that currently support Distributed Editing recovery.
 *
 * @since 7.1.0
 *
 * @return string[] REST bases keyed by post type.
 */
function wp_de_rtc_get_rest_recovery_post_type_rest_bases() {
	$post_types = array( 'post', 'page' );
	$rest_bases = array();

	foreach ( $post_types as $post_type ) {
		$rest_base = wp_de_rtc_get_post_type_rest_base( $post_type );

		if ( $rest_base ) {
			$rest_bases[ $post_type ] = $rest_base;
		}
	}

	/**
	 * Filters REST bases that support Distributed Editing recovery routes.
	 *
	 * Keys are post type names and values are REST bases.
	 *
	 * @since 7.1.0
	 *
	 * @param string[] $rest_bases REST bases keyed by post type.
	 */
	$rest_bases = apply_filters( 'wp_de_rtc_rest_recovery_post_type_rest_bases', $rest_bases );

	return array_filter( array_map( 'sanitize_key', (array) $rest_bases ) );
}

/**
 * Returns the REST base for a post type.
 *
 * @since 7.1.0
 *
 * @param string $post_type Post type name.
 * @return string REST base, or empty string when unsupported.
 */
function wp_de_rtc_get_post_type_rest_base( $post_type ) {
	$post_type_object = get_post_type_object( $post_type );

	if ( ! $post_type_object || ! $post_type_object->show_in_rest ) {
		return '';
	}

	if ( ! empty( $post_type_object->rest_base ) ) {
		return sanitize_key( $post_type_object->rest_base );
	}

	return sanitize_key( $post_type_object->name );
}

/**
 * Registers Distributed Editing settings.
 *
 * Distributed Editing remains disabled by default. The setting exists so the
 * REST proof endpoint has an explicit opt-in path before any editor save path
 * or post-lock replacement work is introduced.
 *
 * @since 7.1.0
 */
function wp_de_rtc_register_settings() {
	register_setting(
		'writing',
		'wp_de_rtc_enabled',
		array(
			'type'              => 'boolean',
			'label'             => __( 'Distributed Editing' ),
			'description'       => __( 'Enable Distributed Editing recovery and collaboration endpoints for editable posts on this site.' ),
			'sanitize_callback' => 'wp_de_rtc_sanitize_enabled_setting',
			'default'           => false,
			'show_in_rest'      => false,
		)
	);

	if ( function_exists( 'add_settings_field' ) ) {
		add_settings_field(
			'wp_de_rtc_enabled',
			__( 'Distributed Editing' ),
			'wp_de_rtc_render_enabled_setting',
			'writing',
			'default'
		);
	}
}
add_action( 'admin_init', 'wp_de_rtc_register_settings' );

/**
 * Adds Distributed Editing settings to the post block editor.
 *
 * The site option remains hidden from the REST settings controller. The editor
 * only receives the derived fact it needs to decide whether the guarded
 * Distributed Editing retry-save handoff should be active for the current post.
 *
 * @since 7.1.0
 *
 * @param array                   $editor_settings      Default editor settings.
 * @param WP_Block_Editor_Context $block_editor_context The current block editor context.
 * @return array Block editor settings with Distributed Editing facts.
 */
function wp_de_rtc_add_block_editor_settings( $editor_settings, $block_editor_context ) {
	$enabled = false;

	if ( ! empty( $block_editor_context->post ) ) {
		$enabled = wp_de_rtc_is_enabled_for_post( $block_editor_context->post );
	}

	$editor_settings['distributedEditing'] = array(
		'enabled'          => $enabled,
		'retrySaveHandoff' => $enabled,
	);

	return $editor_settings;
}
add_filter( 'block_editor_settings_all', 'wp_de_rtc_add_block_editor_settings', 10, 2 );

/**
 * Sanitizes the site-level Distributed Editing enablement setting.
 *
 * @since 7.1.0
 *
 * @param mixed $value Submitted setting value.
 * @return bool Sanitized setting value.
 */
function wp_de_rtc_sanitize_enabled_setting( $value ) {
	return wp_validate_boolean( $value );
}

/**
 * Renders the Distributed Editing Writing setting field.
 *
 * @since 7.1.0
 */
function wp_de_rtc_render_enabled_setting() {
	?>
	<label for="wp_de_rtc_enabled">
		<input name="wp_de_rtc_enabled" type="checkbox" id="wp_de_rtc_enabled" value="1" <?php checked( true, wp_de_rtc_is_enabled() ); ?> />
		<?php _e( 'Enable Distributed Editing for posts and pages.' ); ?>
	</label>
	<p class="description">
		<?php _e( 'Distributed Editing is experimental and can increase server activity. Keep it disabled on constrained hosting unless the site has been evaluated for collaborative editing traffic.' ); ?>
	</p>
	<?php
}

/**
 * Checks permissions for the sync-meta recovery REST endpoint.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return true|WP_Error True when the request may proceed, otherwise a WP_Error.
 */
function wp_de_rtc_rest_recovery_permissions_check( $request ) {
	$post = get_post( (int) $request['id'] );

	if ( ! $post || ! wp_de_rtc_rest_recovery_request_matches_post_type( $request, $post ) ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error(
			'rest_cannot_edit',
			__( 'Sorry, you are not allowed to edit this post.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	if ( ! wp_de_rtc_is_enabled_for_post( $post ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_feature_disabled',
			__( 'Distributed Editing is not enabled for this post.' ),
			array(
				'detail'  => 'feature_disabled_for_post',
				'post_id' => (int) $post->ID,
			)
		);
	}

	return true;
}

/**
 * Checks permissions for the stale-base rejection REST endpoint.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return true|WP_Error True when the request may proceed, otherwise a WP_Error.
 */
function wp_de_rtc_rest_stale_base_permissions_check( $request ) {
	$post = get_post( (int) $request['id'] );

	if ( ! $post || ! wp_de_rtc_rest_stale_base_request_matches_post_type( $request, $post ) ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error(
			'rest_cannot_edit',
			__( 'Sorry, you are not allowed to edit this post.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	if ( ! wp_de_rtc_is_enabled_for_post( $post ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_feature_disabled',
			__( 'Distributed Editing is not enabled for this post.' ),
			array(
				'detail'  => 'feature_disabled_for_post',
				'post_id' => (int) $post->ID,
			)
		);
	}

	return true;
}

/**
 * Checks permissions for the retry-submit proof REST endpoint.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return true|WP_Error True when the request may proceed, otherwise a WP_Error.
 */
function wp_de_rtc_rest_retry_submit_permissions_check( $request ) {
	$post = get_post( (int) $request['id'] );

	if ( ! $post || ! wp_de_rtc_rest_retry_submit_request_matches_post_type( $request, $post ) ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error(
			'rest_cannot_edit',
			__( 'Sorry, you are not allowed to edit this post.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	if ( ! wp_de_rtc_is_enabled_for_post( $post ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_feature_disabled',
			__( 'Distributed Editing is not enabled for this post.' ),
			array(
				'detail'  => 'feature_disabled_for_post',
				'post_id' => (int) $post->ID,
			)
		);
	}

	return true;
}

/**
 * Checks permissions for the retry-save REST endpoint.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return true|WP_Error True when the request may proceed, otherwise a WP_Error.
 */
function wp_de_rtc_rest_retry_save_permissions_check( $request ) {
	$post = get_post( (int) $request['id'] );

	if ( ! $post || ! wp_de_rtc_rest_retry_save_request_matches_post_type( $request, $post ) ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error(
			'rest_cannot_edit',
			__( 'Sorry, you are not allowed to edit this post.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	if ( ! wp_de_rtc_is_enabled_for_post( $post ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_feature_disabled',
			__( 'Distributed Editing is not enabled for this post.' ),
			array(
				'detail'  => 'feature_disabled_for_post',
				'post_id' => (int) $post->ID,
			)
		);
	}

	return true;
}

/**
 * Returns whether the REST recovery request matches the post type route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @param WP_Post         $post    Post object.
 * @return bool Whether the requested route matches the post type REST base.
 */
function wp_de_rtc_rest_recovery_request_matches_post_type( $request, $post ) {
	$requested_rest_base = wp_de_rtc_get_rest_recovery_request_rest_base( $request );
	$post_rest_base      = wp_de_rtc_get_post_type_rest_base( $post->post_type );
	$supported_bases     = wp_de_rtc_get_rest_recovery_post_type_rest_bases();

	return (
		'' !== $requested_rest_base &&
		'' !== $post_rest_base &&
		$requested_rest_base === $post_rest_base &&
		in_array( $post_rest_base, $supported_bases, true )
	);
}

/**
 * Returns the post type REST base from a recovery request route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return string Requested post type REST base, or empty string.
 */
function wp_de_rtc_get_rest_recovery_request_rest_base( $request ) {
	$route = $request->get_route();

	if ( ! is_string( $route ) ) {
		return '';
	}

	if ( ! preg_match( '#^/wp/v2/([^/]+)/\d+/distributed-editing/recovery$#', $route, $matches ) ) {
		return '';
	}

	return sanitize_key( $matches[1] );
}

/**
 * Returns whether the REST stale-base request matches the post type route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @param WP_Post         $post    Post object.
 * @return bool Whether the requested route matches the post type REST base.
 */
function wp_de_rtc_rest_stale_base_request_matches_post_type( $request, $post ) {
	$requested_rest_base = wp_de_rtc_get_rest_stale_base_request_rest_base( $request );
	$post_rest_base      = wp_de_rtc_get_post_type_rest_base( $post->post_type );
	$supported_bases     = wp_de_rtc_get_rest_recovery_post_type_rest_bases();

	return (
		'' !== $requested_rest_base &&
		'' !== $post_rest_base &&
		$requested_rest_base === $post_rest_base &&
		in_array( $post_rest_base, $supported_bases, true )
	);
}

/**
 * Returns the post type REST base from a stale-base request route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return string Requested post type REST base, or empty string.
 */
function wp_de_rtc_get_rest_stale_base_request_rest_base( $request ) {
	$route = $request->get_route();

	if ( ! is_string( $route ) ) {
		return '';
	}

	if ( ! preg_match( '#^/wp/v2/([^/]+)/\d+/distributed-editing/stale-base$#', $route, $matches ) ) {
		return '';
	}

	return sanitize_key( $matches[1] );
}

/**
 * Returns whether the REST retry-submit request matches the post type route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @param WP_Post         $post    Post object.
 * @return bool Whether the requested route matches the post type REST base.
 */
function wp_de_rtc_rest_retry_submit_request_matches_post_type( $request, $post ) {
	$requested_rest_base = wp_de_rtc_get_rest_retry_submit_request_rest_base( $request );
	$post_rest_base      = wp_de_rtc_get_post_type_rest_base( $post->post_type );
	$supported_bases     = wp_de_rtc_get_rest_recovery_post_type_rest_bases();

	return (
		'' !== $requested_rest_base &&
		'' !== $post_rest_base &&
		$requested_rest_base === $post_rest_base &&
		in_array( $post_rest_base, $supported_bases, true )
	);
}

/**
 * Returns the post type REST base from a retry-submit request route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return string Requested post type REST base, or empty string.
 */
function wp_de_rtc_get_rest_retry_submit_request_rest_base( $request ) {
	$route = $request->get_route();

	if ( ! is_string( $route ) ) {
		return '';
	}

	if ( ! preg_match( '#^/wp/v2/([^/]+)/\d+/distributed-editing/retry-submit$#', $route, $matches ) ) {
		return '';
	}

	return sanitize_key( $matches[1] );
}

/**
 * Returns whether the REST retry-save request matches the post type route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @param WP_Post         $post    Post object.
 * @return bool Whether the requested route matches the post type REST base.
 */
function wp_de_rtc_rest_retry_save_request_matches_post_type( $request, $post ) {
	$requested_rest_base = wp_de_rtc_get_rest_retry_save_request_rest_base( $request );
	$post_rest_base      = wp_de_rtc_get_post_type_rest_base( $post->post_type );
	$supported_bases     = wp_de_rtc_get_rest_recovery_post_type_rest_bases();

	return (
		'' !== $requested_rest_base &&
		'' !== $post_rest_base &&
		$requested_rest_base === $post_rest_base &&
		in_array( $post_rest_base, $supported_bases, true )
	);
}

/**
 * Returns the post type REST base from a retry-save request route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return string Requested post type REST base, or empty string.
 */
function wp_de_rtc_get_rest_retry_save_request_rest_base( $request ) {
	$route = $request->get_route();

	if ( ! is_string( $route ) ) {
		return '';
	}

	if ( ! preg_match( '#^/wp/v2/([^/]+)/\d+/distributed-editing/retry-save$#', $route, $matches ) ) {
		return '';
	}

	return sanitize_key( $matches[1] );
}

/**
 * Handles the sync-meta recovery REST endpoint.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response|WP_Error REST response on success, otherwise a WP_Error.
 */
function wp_de_rtc_rest_recovery_endpoint( $request ) {
	$post_id = (int) $request['id'];
	$mode    = $request->get_param( 'mode' );
	$mode    = is_string( $mode ) ? $mode : 'dry_run';
	$plan    = wp_de_rtc_plan_sync_meta_recovery_update( $post_id );

	if ( is_wp_error( $plan ) ) {
		return $plan;
	}

	if ( $request->has_param( 'candidate_post_content_hash' ) ) {
		$plan['candidate_post_content_hash'] = (string) $request->get_param( 'candidate_post_content_hash' );
	}

	if ( 'apply' === $mode ) {
		$result = wp_de_rtc_apply_sync_meta_recovery_update(
			$plan,
			array(
				'mode' => 'apply',
			)
		);
	} else {
		$result = wp_de_rtc_dry_run_sync_meta_recovery_update( $plan );
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$result['rest_route']          = 'post_sync_meta_recovery';
	$result['permission_contract'] = wp_de_rtc_get_rest_recovery_permission_contract( $post_id );

	return rest_ensure_response( $result );
}

/**
 * Handles the stale-base rejection REST endpoint.
 *
 * This proof endpoint models the future save-path response contract only. It
 * does not save, rebase, refetch, repair sync metadata, replace post locks, or
 * create revisions.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_Error Stale-base rejection error with normalized DE-RTC data.
 */
function wp_de_rtc_rest_stale_base_endpoint( $request ) {
	return wp_de_rtc_get_stale_base_rejection_error(
		(int) $request['id'],
		array(
			'client_base_version'      => $request->get_param( 'client_base_version' ),
			'server_version'           => $request->get_param( 'server_version' ),
			'pending_change_count'     => $request->get_param( 'pending_change_count' ),
			'remote_change_count'      => $request->get_param( 'remote_change_count' ),
			'can_attempt_local_rebase' => $request->get_param( 'can_attempt_local_rebase' ),
			'rest_route'               => 'post_stale_base_rejection',
		)
	);
}

/**
 * Handles the retry-submit proof REST endpoint.
 *
 * This endpoint proves the server-side acceptance gate for a rebased client
 * retry. It compares the client base version against the current sync metadata
 * version and returns either an accepted-for-future-save response or the
 * canonical stale-base rejection. It does not save, mutate post content, create
 * revisions, replace post locks, or claim the post is persisted.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response|WP_Error REST response on success, otherwise a stale-base error.
 */
function wp_de_rtc_rest_retry_submit_endpoint( $request ) {
	$result = wp_de_rtc_get_retry_submit_acceptance_result(
		(int) $request['id'],
		array(
			'client_base_version'        => $request->get_param( 'client_base_version' ),
			'rebased_from_version'       => $request->get_param( 'rebased_from_version' ),
			'pending_change_count'       => $request->get_param( 'pending_change_count' ),
			'proposed_post_content_hash' => $request->get_param( 'proposed_post_content_hash' ),
		)
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response( $result );
}

/**
 * Handles the retry-save REST endpoint.
 *
 * This endpoint is the first explicit write boundary for a retry after stale-
 * base local rebase. It requires evidence from an accepted retry-submit proof,
 * verifies the server version is still current, and lets the server own the
 * updated sync metadata embedded in post_content.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response|WP_Error REST response on success, otherwise an error.
 */
function wp_de_rtc_rest_retry_save_endpoint( $request ) {
	$result = wp_de_rtc_save_retry_submitted_post(
		(int) $request['id'],
		array(
			'client_base_version'                 => $request->get_param( 'client_base_version' ),
			'accepted_proof_server_version'      => $request->get_param( 'accepted_proof_server_version' ),
			'rebased_from_version'               => $request->get_param( 'rebased_from_version' ),
			'pending_change_count'               => $request->get_param( 'pending_change_count' ),
			'proposed_post_content'              => $request->get_param( 'proposed_post_content' ),
			'proposed_post_content_hash'         => $request->get_param( 'proposed_post_content_hash' ),
			'accepted_proof_saves_post'          => $request->get_param( 'accepted_proof_saves_post' ),
			'accepted_proof_mutates_post_content' => $request->get_param( 'accepted_proof_mutates_post_content' ),
			'accepted_proof_creates_revision'    => $request->get_param( 'accepted_proof_creates_revision' ),
			'accepted_proof_claims_saved'        => $request->get_param( 'accepted_proof_claims_saved' ),
		)
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response( $result );
}

/**
 * Returns a proof-only retry-submit acceptance result.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @param array       $args {
 *     Retry-submit proof arguments.
 *
 *     @type mixed $client_base_version        Client base version after local rebase.
 *     @type mixed $rebased_from_version       Original stale version that was rebased from.
 *     @type mixed $pending_change_count       Pending local change count. Default 1.
 *     @type mixed $proposed_post_content_hash Hash of the proposed post content.
 * }
 * @return array|WP_Error Retry-submit acceptance result, or stale-base rejection.
 */
function wp_de_rtc_get_retry_submit_acceptance_result( $post, $args = array() ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	$client_base_version        = isset( $args['client_base_version'] ) ? sanitize_text_field( (string) $args['client_base_version'] ) : '';
	$server_version             = wp_de_rtc_get_post_sync_meta_version( $post );
	$pending_change_count       = array_key_exists( 'pending_change_count', $args ) ? max( 0, (int) $args['pending_change_count'] ) : 1;
	$rebased_from_version       = isset( $args['rebased_from_version'] ) ? sanitize_text_field( (string) $args['rebased_from_version'] ) : null;
	$proposed_post_content_hash = isset( $args['proposed_post_content_hash'] ) ? sanitize_text_field( (string) $args['proposed_post_content_hash'] ) : null;

	if ( '' === $client_base_version || null === $server_version || $client_base_version !== $server_version ) {
		return wp_de_rtc_get_stale_base_rejection_error(
			$post,
			array(
				'client_base_version'      => $client_base_version,
				'server_version'           => $server_version,
				'pending_change_count'     => $pending_change_count,
				'remote_change_count'      => 1,
				'can_attempt_local_rebase' => false,
				'rest_route'               => 'post_retry_submit_stale_base',
			)
		);
	}

	return array(
		'result'                              => 'retry_submit_accepted_for_future_save',
		'retry_submit_accepted'               => true,
		'rest_route'                          => 'post_retry_submit',
		'post_id'                             => (int) $post->ID,
		'client_base_version'                 => $client_base_version,
		'server_version'                      => $server_version,
		'rebased_from_version'                => $rebased_from_version,
		'pending_change_count'                => $pending_change_count,
		'proposed_post_content_hash'          => $proposed_post_content_hash,
		'requires_server_state_refetch'       => false,
		'requires_manual_conflict_resolution' => false,
		'can_export_local_updates'            => $pending_change_count > 0,
		'save_path_required'                  => true,
		'saves_post'                          => false,
		'mutates_post_content'                => false,
		'creates_revision'                    => false,
		'claims_saved'                        => false,
		'permission_contract'                 => wp_de_rtc_get_rest_recovery_permission_contract( $post ),
	);
}

/**
 * Applies an accepted retry-save request.
 *
 * The client sends stripped proposed post content and proof metadata. The
 * server validates the proof against the current sync-meta version, rejects
 * contradictory proof flags, creates the next sync meta, and persists the
 * combined post_content with revision evidence.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @param array       $args {
 *     Retry-save request arguments.
 *
 *     @type mixed $client_base_version                 Client base version after local rebase.
 *     @type mixed $accepted_proof_server_version       Server version from accepted proof.
 *     @type mixed $rebased_from_version                Original stale version that was rebased from.
 *     @type mixed $pending_change_count                Pending local change count. Default 1.
 *     @type mixed $proposed_post_content               Proposed content without sync metadata.
 *     @type mixed $proposed_post_content_hash          Hash of proposed content without sync metadata.
 *     @type mixed $accepted_proof_saves_post           Whether proof claimed to save.
 *     @type mixed $accepted_proof_mutates_post_content Whether proof claimed to mutate content.
 *     @type mixed $accepted_proof_creates_revision     Whether proof claimed to create a revision.
 *     @type mixed $accepted_proof_claims_saved         Whether proof claimed saved state.
 * }
 * @return array|WP_Error Retry-save result, or rejection.
 */
function wp_de_rtc_save_retry_submitted_post( $post, $args = array() ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	$client_base_version            = isset( $args['client_base_version'] ) ? sanitize_text_field( (string) $args['client_base_version'] ) : '';
	$accepted_proof_server_version = isset( $args['accepted_proof_server_version'] ) ? sanitize_text_field( (string) $args['accepted_proof_server_version'] ) : '';
	$server_version                 = wp_de_rtc_get_post_sync_meta_version( $post );
	$pending_change_count           = array_key_exists( 'pending_change_count', $args ) ? max( 0, (int) $args['pending_change_count'] ) : 1;
	$rebased_from_version           = isset( $args['rebased_from_version'] ) ? sanitize_text_field( (string) $args['rebased_from_version'] ) : null;
	$accepted_proof_saves_post      = ! empty( $args['accepted_proof_saves_post'] ) && wp_validate_boolean( $args['accepted_proof_saves_post'] );
	$accepted_proof_mutates_content = ! empty( $args['accepted_proof_mutates_post_content'] ) && wp_validate_boolean( $args['accepted_proof_mutates_post_content'] );
	$accepted_proof_creates_revision = ! empty( $args['accepted_proof_creates_revision'] ) && wp_validate_boolean( $args['accepted_proof_creates_revision'] );
	$accepted_proof_claims_saved    = ! empty( $args['accepted_proof_claims_saved'] ) && wp_validate_boolean( $args['accepted_proof_claims_saved'] );

	if (
		$accepted_proof_saves_post ||
		$accepted_proof_mutates_content ||
		$accepted_proof_creates_revision ||
		$accepted_proof_claims_saved
	) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the retry save because the accepted proof claimed persistence.' ),
			array(
				'detail'      => 'retry_save_proof_claimed_persistence',
				'post_id'     => (int) $post->ID,
				'rest_route'  => 'post_retry_save',
				'saves_post'  => false,
				'claims_saved' => false,
			)
		);
	}

	if (
		'' === $client_base_version ||
		'' === $accepted_proof_server_version ||
		null === $server_version ||
		$client_base_version !== $server_version ||
		$accepted_proof_server_version !== $server_version
	) {
		return wp_de_rtc_get_stale_base_rejection_error(
			$post,
			array(
				'client_base_version'      => $client_base_version,
				'server_version'           => $server_version,
				'pending_change_count'     => $pending_change_count,
				'remote_change_count'      => 1,
				'can_attempt_local_rebase' => false,
				'rest_route'               => 'post_retry_save_stale_base',
			)
		);
	}

	if ( ! array_key_exists( 'proposed_post_content', $args ) || ! is_string( $args['proposed_post_content'] ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing rejected the retry save because proposed post content is missing.' ),
			array(
				'detail'     => 'missing_retry_save_proposed_content',
				'post_id'    => (int) $post->ID,
				'rest_route' => 'post_retry_save',
			)
		);
	}

	$proposed_post_content      = (string) $args['proposed_post_content'];
	$proposed_post_content_hash = wp_de_rtc_hash_content( $proposed_post_content );
	$expected_content_hash      = isset( $args['proposed_post_content_hash'] ) ? sanitize_text_field( (string) $args['proposed_post_content_hash'] ) : null;

	if ( null !== $expected_content_hash && ! hash_equals( $expected_content_hash, $proposed_post_content_hash ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the retry save because the proposed content hash does not match.' ),
			array(
				'detail'                       => 'retry_save_proposed_content_hash_mismatch',
				'post_id'                      => (int) $post->ID,
				'rest_route'                   => 'post_retry_save',
				'proposed_post_content_hash'   => $proposed_post_content_hash,
				'expected_post_content_hash'   => $expected_content_hash,
				'saves_post'                   => false,
				'mutates_post_content'         => false,
				'creates_revision'             => false,
				'claims_saved'                 => false,
			)
		);
	}

	$current = wp_de_rtc_parse_post_content_sync_meta( $post->post_content );

	if ( is_wp_error( $current ) ) {
		return $current;
	}

	$proposed = wp_de_rtc_parse_post_content_sync_meta( $proposed_post_content );

	if ( is_wp_error( $proposed ) ) {
		return $proposed;
	}

	if ( null !== $proposed['sync_meta'] ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the retry save because clients may not submit altered sync metadata.' ),
			array(
				'detail'               => 'retry_save_client_submitted_sync_meta',
				'post_id'              => (int) $post->ID,
				'rest_route'           => 'post_retry_save',
				'saves_post'           => false,
				'mutates_post_content' => false,
				'creates_revision'     => false,
				'claims_saved'         => false,
			)
		);
	}

	if (
		! is_array( $current['sync_meta'] ) ||
		! is_string( $current['sync_meta_format'] ) ||
		! is_string( $current['sync_meta_position'] )
	) {
		return wp_de_rtc_get_stale_base_rejection_error(
			$post,
			array(
				'client_base_version'      => $client_base_version,
				'server_version'           => null,
				'pending_change_count'     => $pending_change_count,
				'remote_change_count'      => 1,
				'can_attempt_local_rebase' => false,
				'rest_route'               => 'post_retry_save_stale_base',
			)
		);
	}

	$next_version   = wp_de_rtc_get_next_sync_meta_version( $server_version, $proposed_post_content_hash );
	$next_sync_meta = $current['sync_meta'];

	$next_sync_meta['version']           = $next_version;
	$next_sync_meta['previous_version']  = $server_version;
	$next_sync_meta['last_server_update'] = array(
		'type'                         => 'retry_save',
		'user_id'                      => get_current_user_id(),
		'client_base_version'          => $client_base_version,
		'accepted_proof_server_version' => $accepted_proof_server_version,
		'rebased_from_version'         => $rebased_from_version,
		'pending_change_count'         => $pending_change_count,
		'proposed_post_content_hash'   => $proposed_post_content_hash,
	);

	$candidate_post_content = wp_de_rtc_add_sync_meta_to_post_content(
		$proposed_post_content,
		$current['sync_meta_format'],
		$next_sync_meta,
		$current['sync_meta_position']
	);

	if ( is_wp_error( $candidate_post_content ) ) {
		return $candidate_post_content;
	}

	$revision_ids_before_save = wp_de_rtc_get_post_revision_ids( $post->ID );
	$candidate_hash           = wp_de_rtc_hash_content( $candidate_post_content );
	$updated_post_id          = wp_update_post(
		wp_slash(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $candidate_post_content,
			)
		),
		true
	);

	if ( is_wp_error( $updated_post_id ) ) {
		return $updated_post_id;
	}

	if ( ! $updated_post_id ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_storage_failure',
			__( 'Distributed Editing could not save the retry update because the post update failed.' ),
			array(
				'detail'  => 'retry_save_post_update_failed',
				'post_id' => (int) $post->ID,
			)
		);
	}

	$revision_ids_after_save = wp_de_rtc_get_post_revision_ids( $post->ID );
	$created_revision_ids    = array_values( array_diff( $revision_ids_after_save, $revision_ids_before_save ) );

	return array(
		'mode'                                => 'retry_save',
		'result'                              => 'retry_save_applied',
		'retry_save_accepted'                 => true,
		'rest_route'                          => 'post_retry_save',
		'post_id'                             => (int) $post->ID,
		'updated_post_id'                     => (int) $updated_post_id,
		'client_base_version'                 => $client_base_version,
		'accepted_proof_server_version'       => $accepted_proof_server_version,
		'previous_server_version'             => $server_version,
		'server_version'                      => $next_version,
		'rebased_from_version'                => $rebased_from_version,
		'pending_change_count'                => $pending_change_count,
		'proposed_post_content_hash'          => $proposed_post_content_hash,
		'saved_post_content_hash'             => $candidate_hash,
		'requires_server_state_refetch'       => false,
		'requires_manual_conflict_resolution' => false,
		'can_export_local_updates'            => false,
		'save_path_required'                  => false,
		'saves_post'                          => true,
		'mutates_post_content'                => true,
		'creates_revision'                    => ! empty( $created_revision_ids ),
		'claims_saved'                        => true,
		'revision_ids_before_save'            => $revision_ids_before_save,
		'revision_ids_after_save'             => $revision_ids_after_save,
		'created_revision_ids'                => $created_revision_ids,
		'revision_created'                    => ! empty( $created_revision_ids ),
		'permission_contract'                 => wp_de_rtc_get_rest_recovery_permission_contract( $post ),
	);
}

/**
 * Rejects explicit stale-base probes in the REST post update save path.
 *
 * This is a proof-only save-path boundary. It recognizes only explicit
 * `de_rtc_stale_base_probe` requests and otherwise returns the prepared post
 * unchanged so ordinary REST saves continue through the existing controller.
 *
 * @since 7.1.0
 *
 * @param stdClass|WP_Error $prepared_post Prepared post object or an earlier error.
 * @param WP_REST_Request   $request       Request object.
 * @return stdClass|WP_Error Prepared post when no probe is present, otherwise a stale-base error.
 */
function wp_de_rtc_rest_pre_insert_stale_base_probe( $prepared_post, $request ) {
	if ( ! wp_de_rtc_is_rest_save_stale_base_probe_request( $request ) || is_wp_error( $prepared_post ) ) {
		return $prepared_post;
	}

	$post_id = 0;

	if ( is_object( $prepared_post ) && isset( $prepared_post->ID ) ) {
		$post_id = (int) $prepared_post->ID;
	}

	if ( ! $post_id && isset( $request['id'] ) ) {
		$post_id = (int) $request['id'];
	}

	$post = get_post( $post_id );

	if ( ! $post || ! wp_de_rtc_rest_save_probe_request_matches_post_type( $request, $post ) ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error(
			'rest_cannot_edit',
			__( 'Sorry, you are not allowed to edit this post.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	if ( ! wp_de_rtc_is_enabled_for_post( $post ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_feature_disabled',
			__( 'Distributed Editing is not enabled for this post.' ),
			array(
				'detail'  => 'feature_disabled_for_post',
				'post_id' => (int) $post->ID,
			)
		);
	}

	return wp_de_rtc_get_stale_base_rejection_error(
		$post,
		array(
			'client_base_version'      => $request->get_param( 'client_base_version' ),
			'server_version'           => $request->get_param( 'server_version' ),
			'pending_change_count'     => $request->get_param( 'pending_change_count' ),
			'remote_change_count'      => $request->get_param( 'remote_change_count' ),
			'can_attempt_local_rebase' => $request->get_param( 'can_attempt_local_rebase' ),
			'rest_route'               => wp_de_rtc_get_rest_save_probe_response_route( $request ),
		)
	);
}

/**
 * Returns whether a REST post save request explicitly asks for stale-base proof.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Request object.
 * @return bool Whether the request is a stale-base proof probe.
 */
function wp_de_rtc_is_rest_save_stale_base_probe_request( $request ) {
	return wp_validate_boolean( $request->get_param( 'de_rtc_stale_base_probe' ) );
}

/**
 * Returns whether the REST save request route matches the post type REST base.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Request object.
 * @param WP_Post         $post    Post object.
 * @return bool Whether the requested route matches the post type REST base.
 */
function wp_de_rtc_rest_save_probe_request_matches_post_type( $request, $post ) {
	$requested_rest_base = wp_de_rtc_get_rest_save_probe_request_rest_base( $request );
	$post_rest_base      = wp_de_rtc_get_post_type_rest_base( $post->post_type );
	$supported_bases     = wp_de_rtc_get_rest_recovery_post_type_rest_bases();

	return (
		'' !== $requested_rest_base &&
		'' !== $post_rest_base &&
		$requested_rest_base === $post_rest_base &&
		in_array( $post_rest_base, $supported_bases, true )
	);
}

/**
 * Returns the post type REST base from a REST save probe request route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Request object.
 * @return string Requested post type REST base, or empty string.
 */
function wp_de_rtc_get_rest_save_probe_request_rest_base( $request ) {
	$route = $request->get_route();

	if ( ! is_string( $route ) ) {
		return '';
	}

	if ( ! preg_match( '#^/wp/v2/([^/]+)/\d+(?:/autosaves)?$#', $route, $matches ) ) {
		return '';
	}

	return sanitize_key( $matches[1] );
}

/**
 * Returns the response route label for a REST save probe request.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Request object.
 * @return string Response route label.
 */
function wp_de_rtc_get_rest_save_probe_response_route( $request ) {
	$route = $request->get_route();

	if ( is_string( $route ) && preg_match( '#/autosaves$#', $route ) ) {
		return 'post_autosave_stale_base_probe';
	}

	return 'post_save_stale_base_probe';
}

/**
 * Returns the canonical stale-base rejection error.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @param array       $args {
 *     Optional stale-base rejection data.
 *
 *     @type mixed  $client_base_version      Client base version.
 *     @type mixed  $server_version           Server version known to the caller.
 *     @type mixed  $pending_change_count     Pending local change count. Default 1.
 *     @type mixed  $remote_change_count      Remote change count. Default 1.
 *     @type mixed  $can_attempt_local_rebase Whether the client thinks it can rebase. Default false.
 *     @type string $rest_route               Response route label. Default 'post_stale_base_rejection'.
 * }
 * @return WP_Error Stale-base rejection error with normalized DE-RTC data.
 */
function wp_de_rtc_get_stale_base_rejection_error( $post, $args = array() ) {
	$post                     = get_post( $post );
	$post_id                  = $post ? (int) $post->ID : 0;
	$pending_change_count     = array_key_exists( 'pending_change_count', $args ) ? max( 0, (int) $args['pending_change_count'] ) : 1;
	$remote_change_count      = array_key_exists( 'remote_change_count', $args ) ? max( 0, (int) $args['remote_change_count'] ) : 1;
	$can_attempt_local_rebase = ! empty( $args['can_attempt_local_rebase'] ) && wp_validate_boolean( $args['can_attempt_local_rebase'] );
	$requires_server_refetch  = true;
	$client_base_version      = isset( $args['client_base_version'] ) ? sanitize_text_field( (string) $args['client_base_version'] ) : '';
	$server_version           = isset( $args['server_version'] ) ? $args['server_version'] : null;
	$server_version           = is_string( $server_version ) && '' !== $server_version
		? sanitize_text_field( $server_version )
		: ( $post ? wp_de_rtc_get_post_sync_meta_version( $post ) : null );
	$can_attempt_local_rebase = $can_attempt_local_rebase && ! $requires_server_refetch;
	$permission_contract      = wp_de_rtc_get_rest_recovery_permission_contract( $post );
	$rest_route               = isset( $args['rest_route'] ) ? sanitize_key( $args['rest_route'] ) : 'post_stale_base_rejection';

	return wp_de_rtc_get_reason_error(
		'stale_base_version_rejected',
		__( 'Distributed Editing rejected the update because the client base version is stale.' ),
		array(
			'detail'                              => 'stale_base_version_rejected',
			'post_id'                             => $post_id,
			'client_base_version'                 => $client_base_version,
			'server_version'                      => $server_version,
			'pending_change_count'                => $pending_change_count,
			'remote_change_count'                 => $remote_change_count,
			'requires_server_state_refetch'       => $requires_server_refetch,
			'can_attempt_local_rebase'            => $can_attempt_local_rebase,
			'requires_manual_conflict_resolution' => false,
			'can_export_local_updates'            => $pending_change_count > 0,
			'rest_route'                          => $rest_route,
			'permission_contract'                 => $permission_contract,
		)
	);
}

/**
 * Returns the current sync-meta version for a post.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @return string|null Sync-meta version, or null when no version is available.
 */
function wp_de_rtc_get_post_sync_meta_version( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return null;
	}

	$parsed = wp_de_rtc_parse_post_content_sync_meta( $post->post_content );

	if ( is_wp_error( $parsed ) || ! isset( $parsed['sync_meta'] ) || ! is_array( $parsed['sync_meta'] ) ) {
		return null;
	}

	if ( ! array_key_exists( 'version', $parsed['sync_meta'] ) ) {
		return null;
	}

	return (string) $parsed['sync_meta']['version'];
}

/**
 * Returns whether Distributed Editing is enabled for the site.
 *
 * @since 7.1.0
 *
 * @return bool Whether Distributed Editing is enabled.
 */
function wp_de_rtc_is_enabled() {
	return (bool) get_option( 'wp_de_rtc_enabled', false );
}

/**
 * Returns whether Distributed Editing is enabled for a post.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @return bool Whether Distributed Editing is enabled.
 */
function wp_de_rtc_is_enabled_for_post( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$enabled = wp_de_rtc_is_enabled();

	/**
	 * Filters whether Distributed Editing is enabled for a post.
	 *
	 * @since 7.1.0
	 *
	 * @param bool    $enabled Whether Distributed Editing is enabled.
	 * @param WP_Post $post    Post object.
	 */
	return (bool) apply_filters( 'wp_de_rtc_enabled_for_post', $enabled, $post );
}

/**
 * Returns the current recovery endpoint permission contract.
 *
 * @since 7.1.0
 * @access private
 *
 * @param int|WP_Post $post Post ID or object.
 * @return array Permission contract metadata.
 */
function wp_de_rtc_get_rest_recovery_permission_contract( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return array(
			'post_id'                         => 0,
			'requires_edit_post'              => true,
			'feature_enabled'                 => false,
			'unfiltered_html_review_required' => true,
			'unfiltered_html_allowed'         => current_user_can( 'unfiltered_html' ),
		);
	}

	return array(
		'post_id'                         => (int) $post->ID,
		'post_type'                       => $post->post_type,
		'post_type_rest_base'             => wp_de_rtc_get_post_type_rest_base( $post->post_type ),
		'requires_edit_post'              => true,
		'feature_enabled'                 => wp_de_rtc_is_enabled_for_post( $post ),
		'unfiltered_html_review_required' => true,
		'unfiltered_html_allowed'         => current_user_can( 'unfiltered_html' ),
	);
}

/**
 * Formats Distributed Editing sync metadata as a SCRIPT element.
 *
 * The JSON is encoded so that user-controlled values cannot produce a literal
 * `</script` sequence inside the script contents.
 *
 * @since 7.1.0
 *
 * @param string $format    Sync-meta format label.
 * @param mixed  $sync_meta Sync metadata to JSON-encode.
 * @return string|WP_Error SCRIPT element on success, otherwise a WP_Error.
 */
function wp_de_rtc_format_sync_meta( $format, $sync_meta ) {
	$format = wp_de_rtc_normalize_sync_meta_format( $format );

	if ( ! in_array( $format, wp_de_rtc_get_supported_sync_meta_formats(), true ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_unknown_sync_meta_format',
			__( 'The Distributed Editing sync metadata format is not supported.' ),
			array(
				'format' => $format,
			)
		);
	}

	$json = wp_json_encode(
		$sync_meta,
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
	);

	if ( false === $json || false !== stripos( $json, '</script' ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'The Distributed Editing sync metadata could not be encoded.' ),
			array(
				'detail' => 'json_encode_failed',
			)
		);
	}

	$script = wp_get_inline_script_tag(
		$json,
		array(
			'type'                  => 'wp/post-sync-meta',
			'data-sync-meta-format' => $format,
		)
	);

	if ( '' === $script ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'The Distributed Editing sync metadata could not be embedded.' ),
			array(
				'detail' => 'script_embedding_failed',
			)
		);
	}

	return $script;
}

/**
 * Adds Distributed Editing sync metadata to post content.
 *
 * @since 7.1.0
 *
 * @param string $content   Post content.
 * @param string $format    Sync-meta format label.
 * @param mixed  $sync_meta Sync metadata to JSON-encode.
 * @param string $position  Optional. Sync-meta position, either 'trailer' or 'prefix'. Default 'trailer'.
 * @return string|WP_Error Post content with sync metadata on success, otherwise a WP_Error.
 */
function wp_de_rtc_add_sync_meta_to_post_content( $content, $format, $sync_meta, $position = 'trailer' ) {
	$script = wp_de_rtc_format_sync_meta( $format, $sync_meta );

	if ( is_wp_error( $script ) ) {
		return $script;
	}

	if ( 'prefix' === $position ) {
		return $script . $content;
	}

	if ( 'trailer' === $position ) {
		return $content . $script;
	}

	return wp_de_rtc_get_reason_error(
		'de_rtc_malformed_sync_payload',
		__( 'The Distributed Editing sync metadata position is not supported.' ),
		array(
			'detail'   => 'unknown_sync_meta_position',
			'position' => $position,
		)
	);
}

/**
 * Parses Distributed Editing sync metadata from the edge of post content.
 *
 * This recognizes sync metadata only as a prefix or trailer SCRIPT element.
 * Content without sync metadata is returned unchanged with null metadata.
 *
 * @since 7.1.0
 *
 * @param string $content Post content.
 * @return array|WP_Error Parsed content data on success, otherwise a WP_Error.
 */
function wp_de_rtc_parse_post_content_sync_meta( $content ) {
	$prefix = wp_de_rtc_match_edge_sync_meta_script( $content, 'prefix' );

	if ( false !== $prefix ) {
		$parsed = wp_de_rtc_parse_sync_meta_script( $prefix['script'], $prefix['json'] );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		if ( false !== $parsed ) {
			return array(
				'content'            => substr( $content, strlen( $prefix['match'] ) ),
				'sync_meta'          => $parsed['sync_meta'],
				'sync_meta_format'   => $parsed['sync_meta_format'],
				'sync_meta_position' => 'prefix',
				'raw_sync_meta'      => $prefix['script'],
			);
		}
	}

	$trailer = wp_de_rtc_match_edge_sync_meta_script( $content, 'trailer' );

	if ( false !== $trailer ) {
		$parsed = wp_de_rtc_parse_sync_meta_script( $trailer['script'], $trailer['json'] );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		if ( false !== $parsed ) {
			return array(
				'content'            => substr( $content, 0, strlen( $content ) - strlen( $trailer['match'] ) ),
				'sync_meta'          => $parsed['sync_meta'],
				'sync_meta_format'   => $parsed['sync_meta_format'],
				'sync_meta_position' => 'trailer',
				'raw_sync_meta'      => $trailer['script'],
			);
		}
	}

	return array(
		'content'            => $content,
		'sync_meta'          => null,
		'sync_meta_format'   => null,
		'sync_meta_position' => null,
		'raw_sync_meta'      => null,
	);
}

/**
 * Finds the most recent revision containing parseable Distributed Editing sync metadata.
 *
 * This helper only scans confirmed revision content. It skips autosave
 * revisions and does not restore revisions, update the parent post, repair
 * missing metadata, or create new revisions.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @return array|WP_Error {
 *     Revision scan result on success, or a WP_Error when the post is invalid.
 *
 *     @type bool        $found                    Whether parseable sync metadata was found.
 *     @type int         $post_id                  Parent post ID.
 *     @type int         $revision_id              Revision ID containing sync metadata, or 0 when none was found.
 *     @type string|null $revision_date_gmt        Revision GMT date, or null when none was found.
 *     @type array|null  $sync_meta                Parsed sync metadata, or null when none was found.
 *     @type string|null $sync_meta_format         Sync metadata format, or null when none was found.
 *     @type string|null $sync_meta_position       Sync metadata position, or null when none was found.
 *     @type string|null $raw_sync_meta            Raw sync metadata SCRIPT element, or null when none was found.
 *     @type string|null $content                  Revision content without sync metadata, or null when none was found.
 *     @type int         $scanned_revisions        Number of revisions inspected.
 *     @type int[]       $malformed_revision_ids   Revision IDs skipped because sync metadata could not be parsed.
 * }
 */
function wp_de_rtc_find_latest_revision_with_sync_meta( $post ) {
	$post = get_post( $post );

	if ( ! $post || empty( $post->ID ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_unrecoverable',
			__( 'Distributed Editing could not scan revisions because the post does not exist.' ),
			array(
				'detail' => 'invalid_post',
			)
		);
	}

	$revisions = wp_get_post_revisions(
		$post,
		array(
			'check_enabled' => false,
			'numberposts'   => -1,
			'order'         => 'DESC',
			'orderby'       => 'date ID',
		)
	);

	$scanned_revisions      = 0;
	$malformed_revision_ids = array();

	foreach ( $revisions as $revision ) {
		++$scanned_revisions;

		if ( wp_is_post_autosave( $revision ) ) {
			continue;
		}

		$parsed = wp_de_rtc_parse_post_content_sync_meta( $revision->post_content );

		if ( is_wp_error( $parsed ) ) {
			$malformed_revision_ids[] = (int) $revision->ID;
			continue;
		}

		if ( null === $parsed['sync_meta'] ) {
			continue;
		}

		return array(
			'found'                  => true,
			'post_id'                => (int) $post->ID,
			'revision_id'            => (int) $revision->ID,
			'revision_date_gmt'      => $revision->post_date_gmt,
			'sync_meta'              => $parsed['sync_meta'],
			'sync_meta_format'       => $parsed['sync_meta_format'],
			'sync_meta_position'     => $parsed['sync_meta_position'],
			'raw_sync_meta'          => $parsed['raw_sync_meta'],
			'content'                => $parsed['content'],
			'scanned_revisions'      => $scanned_revisions,
			'malformed_revision_ids' => $malformed_revision_ids,
		);
	}

	return array(
		'found'                  => false,
		'post_id'                => (int) $post->ID,
		'revision_id'            => 0,
		'revision_date_gmt'      => null,
		'sync_meta'              => null,
		'sync_meta_format'       => null,
		'sync_meta_position'     => null,
		'raw_sync_meta'          => null,
		'content'                => null,
		'scanned_revisions'      => $scanned_revisions,
		'malformed_revision_ids' => $malformed_revision_ids,
	);
}

/**
 * Gets the Distributed Editing sync-meta recovery decision for a post.
 *
 * This helper is intentionally inert and read-only. It parses the current post
 * content and, only when sync metadata is missing, consumes the latest-revision
 * scan helper output. It does not restore revisions, update posts, repair
 * content, create revisions, change locks, register REST behavior, or save.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @return array|WP_Error {
 *     Recovery decision on success, or a WP_Error when current sync metadata is malformed.
 *
 *     @type string      $decision                   Decision label.
 *     @type int         $post_id                    Post ID.
 *     @type bool        $recovery_required          Whether recovery is required.
 *     @type bool        $restorable                 Whether automatic restoration data is available.
 *     @type bool        $manual_resolution_required Whether manual resolution is required.
 *     @type array|null  $reason                     Canonical reason data, or null when no recovery is required.
 *     @type string      $current_content            Current post content without sync metadata.
 *     @type string      $current_content_hash       SHA-256 hash of current content without sync metadata.
 *     @type string      $content_hash_algorithm     Content hash algorithm.
 *     @type array|null  $current_sync_meta          Parsed current sync metadata, or null when missing.
 *     @type string|null $current_sync_meta_format   Current sync metadata format, or null when missing.
 *     @type string|null $current_sync_meta_position Current sync metadata position, or null when missing.
 *     @type string|null $current_raw_sync_meta      Current raw sync metadata SCRIPT, or null when missing.
 *     @type array|null  $base_revision              Restorable revision data, or null when unavailable.
 *     @type string|null $base_revision_content_hash SHA-256 hash of base revision content, or null when unavailable.
 *     @type array|null  $revision_scan              Revision scan result, or null when no scan was needed.
 *     @type array|null  $external_change            Data for a later mutation helper to reconstruct the external change.
 * }
 */
function wp_de_rtc_get_post_sync_meta_recovery_decision( $post ) {
	$post = get_post( $post );

	if ( ! $post || empty( $post->ID ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_unrecoverable',
			__( 'Distributed Editing could not decide sync metadata recovery because the post does not exist.' ),
			array(
				'detail' => 'invalid_post',
			)
		);
	}

	$current = wp_de_rtc_parse_post_content_sync_meta( $post->post_content );

	if ( is_wp_error( $current ) ) {
		return $current;
	}

	$current_content_hash = wp_de_rtc_hash_content( $current['content'] );

	if ( null !== $current['sync_meta'] ) {
		return array(
			'decision'                   => 'no_recovery_required',
			'post_id'                    => (int) $post->ID,
			'recovery_required'          => false,
			'restorable'                 => false,
			'manual_resolution_required' => false,
			'reason'                     => null,
			'current_content'            => $current['content'],
			'current_content_hash'       => $current_content_hash,
			'content_hash_algorithm'     => 'sha256',
			'current_sync_meta'          => $current['sync_meta'],
			'current_sync_meta_format'   => $current['sync_meta_format'],
			'current_sync_meta_position' => $current['sync_meta_position'],
			'current_raw_sync_meta'      => $current['raw_sync_meta'],
			'base_revision'              => null,
			'base_revision_content_hash' => null,
			'revision_scan'              => null,
			'external_change'            => null,
		);
	}

	$revision_scan = wp_de_rtc_find_latest_revision_with_sync_meta( $post );

	if ( is_wp_error( $revision_scan ) ) {
		return $revision_scan;
	}

	if ( $revision_scan['found'] ) {
		$base_revision_content_hash = wp_de_rtc_hash_content( $revision_scan['content'] );

		return array(
			'decision'                   => 'recovery_required_restorable',
			'post_id'                    => (int) $post->ID,
			'recovery_required'          => true,
			'restorable'                 => true,
			'manual_resolution_required' => false,
			'reason'                     => wp_de_rtc_get_reason_data(
				'de_rtc_missing_sync_meta',
				array(
					'detail'               => 'restorable_revision_found',
					'recovery_reason_code' => 'de_rtc_sync_meta_restored_from_revision',
				)
			),
			'current_content'            => $current['content'],
			'current_content_hash'       => $current_content_hash,
			'content_hash_algorithm'     => 'sha256',
			'current_sync_meta'          => null,
			'current_sync_meta_format'   => null,
			'current_sync_meta_position' => null,
			'current_raw_sync_meta'      => null,
			'base_revision'              => array(
				'revision_id'            => $revision_scan['revision_id'],
				'revision_date_gmt'      => $revision_scan['revision_date_gmt'],
				'content'                => $revision_scan['content'],
				'content_hash'           => $base_revision_content_hash,
				'sync_meta'              => $revision_scan['sync_meta'],
				'sync_meta_format'       => $revision_scan['sync_meta_format'],
				'sync_meta_position'     => $revision_scan['sync_meta_position'],
				'raw_sync_meta'          => $revision_scan['raw_sync_meta'],
				'scanned_revisions'      => $revision_scan['scanned_revisions'],
				'malformed_revision_ids' => $revision_scan['malformed_revision_ids'],
			),
			'base_revision_content_hash' => $base_revision_content_hash,
			'revision_scan'              => $revision_scan,
			'external_change'            => array(
				'base_revision_id'              => $revision_scan['revision_id'],
				'base_revision_date_gmt'        => $revision_scan['revision_date_gmt'],
				'base_content'                  => $revision_scan['content'],
				'base_content_hash'             => $base_revision_content_hash,
				'current_content'               => $current['content'],
				'current_content_hash'          => $current_content_hash,
				'content_hash_algorithm'        => 'sha256',
				'restored_sync_meta'            => $revision_scan['sync_meta'],
				'restored_sync_meta_format'     => $revision_scan['sync_meta_format'],
				'restored_sync_meta_position'   => $revision_scan['sync_meta_position'],
				'restored_raw_sync_meta'        => $revision_scan['raw_sync_meta'],
				'recovery_reason_code'          => 'de_rtc_sync_meta_restored_from_revision',
				'missing_sync_meta_reason_code' => 'de_rtc_missing_sync_meta',
			),
		);
	}

	return array(
		'decision'                   => 'manual_resolution_required',
		'post_id'                    => (int) $post->ID,
		'recovery_required'          => true,
		'restorable'                 => false,
		'manual_resolution_required' => true,
		'reason'                     => wp_de_rtc_get_reason_data(
			'de_rtc_sync_meta_unrecoverable',
			array(
				'detail'                 => 'missing_sync_meta_no_restorable_revision',
				'scanned_revisions'      => $revision_scan['scanned_revisions'],
				'malformed_revision_ids' => $revision_scan['malformed_revision_ids'],
			)
		),
		'current_content'            => $current['content'],
		'current_content_hash'       => $current_content_hash,
		'content_hash_algorithm'     => 'sha256',
		'current_sync_meta'          => null,
		'current_sync_meta_format'   => null,
		'current_sync_meta_position' => null,
		'current_raw_sync_meta'      => null,
		'base_revision'              => null,
		'base_revision_content_hash' => null,
		'revision_scan'              => $revision_scan,
		'external_change'            => null,
	);
}

/**
 * Plans a read-only Distributed Editing sync-meta recovery update.
 *
 * This helper accepts either a recovery decision array from
 * wp_de_rtc_get_post_sync_meta_recovery_decision() or a post ID/object from
 * which the decision should be derived. It only returns candidate data for a
 * later write path; it does not update posts, restore revisions, create
 * revisions, change locks, register REST behavior, or save.
 *
 * @since 7.1.0
 *
 * @param array|int|WP_Post|WP_Error $decision_or_post Recovery decision, post ID/object, or existing error.
 * @return array|WP_Error {
 *     Read-only recovery update plan on success, or a WP_Error when the source decision is invalid.
 *
 *     @type string      $plan                              Plan label.
 *     @type string      $decision                          Source recovery decision label.
 *     @type int         $post_id                           Post ID.
 *     @type bool        $can_apply                         Whether a later mutation path may apply this plan.
 *     @type bool        $recovery_required                 Whether recovery is required.
 *     @type bool        $manual_resolution_required        Whether manual resolution is required.
 *     @type array|null  $reason                            Canonical reason data, or null for no-op plans.
 *     @type string|null $reason_code                       Canonical reason code, or null for no-op plans.
 *     @type string      $current_content                   Current post content without sync metadata.
 *     @type string      $current_content_hash              SHA-256 hash of current content without sync metadata.
 *     @type string      $content_hash_algorithm            Content hash algorithm.
 *     @type string|null $base_revision_content_hash        SHA-256 hash of base revision content, if available.
 *     @type string|null $candidate_stripped_content        Candidate content without sync metadata.
 *     @type string|null $candidate_stripped_content_hash   SHA-256 hash of candidate stripped content.
 *     @type string|null $candidate_post_content            Candidate post_content, or null when blocked.
 *     @type string|null $candidate_post_content_hash       SHA-256 hash of candidate post_content.
 *     @type array|null  $restored_sync_meta                Restored sync metadata, if available.
 *     @type string|null $restored_sync_meta_format         Restored sync metadata format, if available.
 *     @type string|null $restored_sync_meta_position       Restored sync metadata position, if available.
 *     @type string|null $restored_raw_sync_meta            Generated restored sync metadata SCRIPT, if available.
 *     @type array|null  $external_change                   External-change evidence for later persistence.
 * }
 */
function wp_de_rtc_plan_sync_meta_recovery_update( $decision_or_post ) {
	if ( is_wp_error( $decision_or_post ) ) {
		return $decision_or_post;
	}

	$current_post_content = null;

	if ( is_array( $decision_or_post ) && isset( $decision_or_post['decision'] ) ) {
		$decision = $decision_or_post;
	} else {
		$post = get_post( $decision_or_post );

		if ( $post ) {
			$current_post_content = $post->post_content;
		}

		$decision = wp_de_rtc_get_post_sync_meta_recovery_decision( $decision_or_post );
	}

	if ( is_wp_error( $decision ) ) {
		return $decision;
	}

	if (
		! is_array( $decision ) ||
		empty( $decision['decision'] ) ||
		! isset( $decision['post_id'], $decision['current_content'] ) ||
		! is_string( $decision['decision'] ) ||
		! is_string( $decision['current_content'] )
	) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_unrecoverable',
			__( 'Distributed Editing could not plan sync metadata recovery because the recovery decision is invalid.' ),
			array(
				'detail' => 'invalid_recovery_decision',
			)
		);
	}

	$current_content      = $decision['current_content'];
	$current_content_hash = isset( $decision['current_content_hash'] ) && is_string( $decision['current_content_hash'] )
		? $decision['current_content_hash']
		: wp_de_rtc_hash_content( $current_content );

	if ( 'no_recovery_required' === $decision['decision'] ) {
		if ( null === $current_post_content ) {
			if (
				isset( $decision['current_raw_sync_meta'], $decision['current_sync_meta_position'] ) &&
				is_string( $decision['current_raw_sync_meta'] ) &&
				is_string( $decision['current_sync_meta_position'] )
			) {
				if ( 'prefix' === $decision['current_sync_meta_position'] ) {
					$current_post_content = $decision['current_raw_sync_meta'] . $current_content;
				} elseif ( 'trailer' === $decision['current_sync_meta_position'] ) {
					$current_post_content = $current_content . $decision['current_raw_sync_meta'];
				}
			}

			if ( null === $current_post_content ) {
				$current_post_content = $current_content;
			}
		}

		return array(
			'plan'                            => 'sync_meta_recovery_update',
			'decision'                        => $decision['decision'],
			'post_id'                         => (int) $decision['post_id'],
			'can_apply'                       => false,
			'recovery_required'               => false,
			'manual_resolution_required'      => false,
			'reason'                          => null,
			'reason_code'                     => null,
			'current_content'                 => $current_content,
			'current_content_hash'            => $current_content_hash,
			'content_hash_algorithm'          => 'sha256',
			'base_revision_content_hash'      => null,
			'candidate_stripped_content'      => $current_content,
			'candidate_stripped_content_hash' => wp_de_rtc_hash_content( $current_content ),
			'candidate_post_content'          => $current_post_content,
			'candidate_post_content_hash'     => wp_de_rtc_hash_content( $current_post_content ),
			'restored_sync_meta'              => null,
			'restored_sync_meta_format'       => null,
			'restored_sync_meta_position'     => null,
			'restored_raw_sync_meta'          => null,
			'external_change'                 => null,
		);
	}

	if ( 'manual_resolution_required' === $decision['decision'] ) {
		$reason      = isset( $decision['reason'] ) && is_array( $decision['reason'] )
			? $decision['reason']
			: wp_de_rtc_get_reason_data(
				'de_rtc_sync_meta_unrecoverable',
				array(
					'detail' => 'manual_resolution_required',
				)
			);
		$reason_code = isset( $reason['reason_code'] ) && is_string( $reason['reason_code'] ) ? $reason['reason_code'] : null;

		return array(
			'plan'                            => 'sync_meta_recovery_update',
			'decision'                        => $decision['decision'],
			'post_id'                         => (int) $decision['post_id'],
			'can_apply'                       => false,
			'recovery_required'               => true,
			'manual_resolution_required'      => true,
			'reason'                          => $reason,
			'reason_code'                     => $reason_code,
			'current_content'                 => $current_content,
			'current_content_hash'            => $current_content_hash,
			'content_hash_algorithm'          => 'sha256',
			'base_revision_content_hash'      => null,
			'candidate_stripped_content'      => null,
			'candidate_stripped_content_hash' => null,
			'candidate_post_content'          => null,
			'candidate_post_content_hash'     => null,
			'restored_sync_meta'              => null,
			'restored_sync_meta_format'       => null,
			'restored_sync_meta_position'     => null,
			'restored_raw_sync_meta'          => null,
			'external_change'                 => null,
		);
	}

	if (
		'recovery_required_restorable' !== $decision['decision'] ||
		empty( $decision['base_revision'] ) ||
		! is_array( $decision['base_revision'] ) ||
		empty( $decision['base_revision']['sync_meta_format'] ) ||
		empty( $decision['base_revision']['sync_meta_position'] ) ||
		! isset( $decision['base_revision']['sync_meta'] ) ||
		! is_string( $decision['base_revision']['sync_meta_format'] ) ||
		! is_string( $decision['base_revision']['sync_meta_position'] )
	) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_unrecoverable',
			__( 'Distributed Editing could not plan sync metadata recovery because the restorable decision is invalid.' ),
			array(
				'detail'   => 'invalid_restorable_recovery_decision',
				'decision' => $decision['decision'],
			)
		);
	}

	$base_revision          = $decision['base_revision'];
	$restored_raw_sync_meta = wp_de_rtc_format_sync_meta( $base_revision['sync_meta_format'], $base_revision['sync_meta'] );

	if ( is_wp_error( $restored_raw_sync_meta ) ) {
		return $restored_raw_sync_meta;
	}

	$restored_raw_sync_meta = rtrim( $restored_raw_sync_meta );

	$candidate_post_content = wp_de_rtc_add_sync_meta_to_post_content(
		$current_content,
		$base_revision['sync_meta_format'],
		$base_revision['sync_meta'],
		$base_revision['sync_meta_position']
	);

	if ( is_wp_error( $candidate_post_content ) ) {
		return $candidate_post_content;
	}

	$candidate_stripped_content_hash = wp_de_rtc_hash_content( $current_content );
	$candidate_post_content_hash     = wp_de_rtc_hash_content( $candidate_post_content );
	$base_revision_content_hash      = isset( $decision['base_revision_content_hash'] ) && is_string( $decision['base_revision_content_hash'] )
		? $decision['base_revision_content_hash']
		: null;

	if ( null === $base_revision_content_hash && isset( $base_revision['content'] ) && is_string( $base_revision['content'] ) ) {
		$base_revision_content_hash = wp_de_rtc_hash_content( $base_revision['content'] );
	}

	$source_reason_code = null;

	if ( isset( $decision['reason']['reason_code'] ) && is_string( $decision['reason']['reason_code'] ) ) {
		$source_reason_code = $decision['reason']['reason_code'];
	}

	$reason = wp_de_rtc_get_reason_data(
		'de_rtc_sync_meta_restored_from_revision',
		array_filter(
			array(
				'detail'             => 'planned_sync_meta_recovery_update',
				'source_reason_code' => $source_reason_code,
				'base_revision_id'   => isset( $base_revision['revision_id'] ) ? (int) $base_revision['revision_id'] : null,
			)
		)
	);

	$external_change = isset( $decision['external_change'] ) && is_array( $decision['external_change'] )
		? $decision['external_change']
		: array();

	$external_change = array_merge(
		$external_change,
		array(
			'candidate_stripped_content_hash' => $candidate_stripped_content_hash,
			'candidate_post_content_hash'     => $candidate_post_content_hash,
			'candidate_sync_meta_format'      => $base_revision['sync_meta_format'],
			'candidate_sync_meta_position'    => $base_revision['sync_meta_position'],
			'candidate_reason_code'           => 'de_rtc_sync_meta_restored_from_revision',
		)
	);

	return array(
		'plan'                            => 'sync_meta_recovery_update',
		'decision'                        => $decision['decision'],
		'post_id'                         => (int) $decision['post_id'],
		'can_apply'                       => true,
		'recovery_required'               => true,
		'manual_resolution_required'      => false,
		'reason'                          => $reason,
		'reason_code'                     => 'de_rtc_sync_meta_restored_from_revision',
		'current_content'                 => $current_content,
		'current_content_hash'            => $current_content_hash,
		'content_hash_algorithm'          => 'sha256',
		'base_revision_content_hash'      => $base_revision_content_hash,
		'candidate_stripped_content'      => $current_content,
		'candidate_stripped_content_hash' => $candidate_stripped_content_hash,
		'candidate_post_content'          => $candidate_post_content,
		'candidate_post_content_hash'     => $candidate_post_content_hash,
		'restored_sync_meta'              => $base_revision['sync_meta'],
		'restored_sync_meta_format'       => $base_revision['sync_meta_format'],
		'restored_sync_meta_position'     => $base_revision['sync_meta_position'],
		'restored_raw_sync_meta'          => $restored_raw_sync_meta,
		'external_change'                 => $external_change,
	);
}

/**
 * Dry-runs a Distributed Editing sync-meta recovery update plan.
 *
 * This helper validates candidate recovery output from
 * wp_de_rtc_plan_sync_meta_recovery_update() but never applies it. It creates
 * an explicit dry-run boundary for a later apply helper and does not update
 * posts, restore revisions, create revisions, change locks, register REST
 * behavior, or save.
 *
 * @since 7.1.0
 *
 * @param array|int|WP_Post|WP_Error $plan_decision_or_post Recovery plan, recovery decision, post ID/object, or existing error.
 * @return array|WP_Error {
 *     Dry-run result on success, or a WP_Error when the plan cannot be validated.
 *
 *     @type string      $mode                    Dry-run mode label.
 *     @type string      $result                  Dry-run result label.
 *     @type string      $validation_status       Validation status label.
 *     @type string      $decision                Source recovery decision label.
 *     @type int         $post_id                 Post ID.
 *     @type bool        $valid                   Whether the candidate is valid.
 *     @type bool        $can_apply               Whether a later apply helper may apply the plan.
 *     @type bool        $would_apply             Always false for this dry-run helper.
 *     @type bool        $recovery_required       Whether recovery is required.
 *     @type bool        $manual_resolution_required Whether manual resolution is required.
 *     @type array|null  $reason                  Canonical reason data, or null for no-op results.
 *     @type string|null $reason_code             Canonical reason code, or null for no-op results.
 *     @type array       $checks                  Validation checks.
 *     @type array       $plan                    Source recovery update plan.
 * }
 */
function wp_de_rtc_dry_run_sync_meta_recovery_update( $plan_decision_or_post ) {
	if ( is_wp_error( $plan_decision_or_post ) ) {
		return $plan_decision_or_post;
	}

	if ( is_array( $plan_decision_or_post ) && isset( $plan_decision_or_post['plan'] ) ) {
		$plan = $plan_decision_or_post;
	} else {
		$plan = wp_de_rtc_plan_sync_meta_recovery_update( $plan_decision_or_post );
	}

	if ( is_wp_error( $plan ) ) {
		return $plan;
	}

	if (
		! is_array( $plan ) ||
		empty( $plan['plan'] ) ||
		'sync_meta_recovery_update' !== $plan['plan'] ||
		empty( $plan['decision'] ) ||
		! isset( $plan['post_id'], $plan['current_content'] ) ||
		! is_string( $plan['decision'] ) ||
		! is_string( $plan['current_content'] )
	) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_unrecoverable',
			__( 'Distributed Editing could not dry-run sync metadata recovery because the recovery plan is invalid.' ),
			array(
				'detail' => 'invalid_recovery_update_plan',
			)
		);
	}

	if ( 'no_recovery_required' === $plan['decision'] ) {
		return wp_de_rtc_create_recovery_dry_run_result(
			$plan,
			'no_update_required',
			'noop',
			true,
			false,
			array(
				'candidate_required'     => false,
				'candidate_content_safe' => true,
			)
		);
	}

	if ( 'manual_resolution_required' === $plan['decision'] ) {
		return wp_de_rtc_create_recovery_dry_run_result(
			$plan,
			'manual_resolution_required',
			'blocked',
			false,
			false,
			array(
				'candidate_required'     => false,
				'candidate_content_safe' => false,
			)
		);
	}

	if ( 'recovery_required_restorable' !== $plan['decision'] ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_unrecoverable',
			__( 'Distributed Editing could not dry-run sync metadata recovery because the plan decision is unsupported.' ),
			array(
				'detail'   => 'unsupported_recovery_update_plan',
				'decision' => $plan['decision'],
			)
		);
	}

	$checks = wp_de_rtc_validate_recovery_update_candidate( $plan );
	$valid  = ! in_array( false, $checks, true );

	return wp_de_rtc_create_recovery_dry_run_result(
		$plan,
		$valid ? 'candidate_update_valid' : 'candidate_update_invalid',
		$valid ? 'valid_candidate' : 'invalid_candidate',
		$valid,
		$valid && ! empty( $plan['can_apply'] ),
		$checks
	);
}

/**
 * Applies a Distributed Editing sync-meta recovery update plan when explicitly requested.
 *
 * This helper creates the first guarded write boundary for sync-meta recovery.
 * It always dry-runs and validates the candidate first. It does not write
 * unless the caller passes an options array with `mode` set to `apply`.
 *
 * @since 7.1.0
 *
 * @param array|int|WP_Post|WP_Error $plan_decision_or_post Recovery plan, recovery decision, post ID/object, or existing error.
 * @param array                      $options               Optional. Apply options. Requires `mode => 'apply'` to persist.
 * @return array|WP_Error Apply result on success, guarded no-op result when apply was not requested, or a WP_Error.
 */
function wp_de_rtc_apply_sync_meta_recovery_update( $plan_decision_or_post, $options = array() ) {
	if ( is_wp_error( $plan_decision_or_post ) ) {
		return $plan_decision_or_post;
	}

	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$dry_run = wp_de_rtc_dry_run_sync_meta_recovery_update( $plan_decision_or_post );

	if ( is_wp_error( $dry_run ) ) {
		return $dry_run;
	}

	$requested_mode = isset( $options['mode'] ) && is_string( $options['mode'] ) ? $options['mode'] : 'dry_run';

	if ( 'apply' !== $requested_mode ) {
		return wp_de_rtc_create_recovery_apply_result(
			$dry_run,
			'apply_not_requested',
			false,
			array(
				'requested_mode' => $requested_mode,
			)
		);
	}

	if ( empty( $dry_run['valid'] ) || empty( $dry_run['can_apply'] ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing could not apply sync metadata recovery because the dry-run candidate is not valid.' ),
			array(
				'detail'                => 'invalid_recovery_update_candidate',
				'dry_run_result'        => isset( $dry_run['result'] ) ? $dry_run['result'] : null,
				'dry_run_valid'         => ! empty( $dry_run['valid'] ),
				'dry_run_can_apply'     => ! empty( $dry_run['can_apply'] ),
				'dry_run_reason_code'   => isset( $dry_run['reason_code'] ) ? $dry_run['reason_code'] : null,
				'dry_run_check_results' => isset( $dry_run['checks'] ) ? $dry_run['checks'] : array(),
			)
		);
	}

	if (
		! isset( $dry_run['plan']['candidate_post_content'], $dry_run['plan']['candidate_post_content_hash'] ) ||
		! is_string( $dry_run['plan']['candidate_post_content'] ) ||
		! is_string( $dry_run['plan']['candidate_post_content_hash'] )
	) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_unrecoverable',
			__( 'Distributed Editing could not apply sync metadata recovery because the candidate post content is missing.' ),
			array(
				'detail' => 'missing_recovery_update_candidate',
			)
		);
	}

	$post_id                     = (int) $dry_run['post_id'];
	$revision_ids_before_apply   = wp_de_rtc_get_post_revision_ids( $post_id );
	$candidate_post_content_hash = $dry_run['plan']['candidate_post_content_hash'];

	$updated_post_id = wp_update_post(
		wp_slash(
			array(
				'ID'           => $post_id,
				'post_content' => $dry_run['plan']['candidate_post_content'],
			)
		),
		true
	);

	if ( is_wp_error( $updated_post_id ) ) {
		return $updated_post_id;
	}

	if ( ! $updated_post_id ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_storage_failure',
			__( 'Distributed Editing could not apply sync metadata recovery because the post update failed.' ),
			array(
				'detail'  => 'post_update_failed',
				'post_id' => $post_id,
			)
		);
	}

	$revision_ids_after_apply = wp_de_rtc_get_post_revision_ids( $post_id );
	$created_revision_ids     = array_values( array_diff( $revision_ids_after_apply, $revision_ids_before_apply ) );

	return wp_de_rtc_create_recovery_apply_result(
		$dry_run,
		'candidate_update_applied',
		true,
		array(
			'updated_post_id'             => (int) $updated_post_id,
			'candidate_post_content_hash' => $candidate_post_content_hash,
			'revision_ids_before_apply'   => $revision_ids_before_apply,
			'revision_ids_after_apply'    => $revision_ids_after_apply,
			'created_revision_ids'        => $created_revision_ids,
			'revision_created'            => ! empty( $created_revision_ids ),
		)
	);
}

/**
 * Creates a WP_Error with canonical Distributed Editing reason data.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $reason_code Canonical DE-RTC reason code.
 * @param string $message     Error message.
 * @param array  $data        Optional. Additional error data.
 * @return WP_Error Error with status and reason-code data.
 */
function wp_de_rtc_get_reason_error( $reason_code, $message, $data = array() ) {
	$codes  = wp_de_rtc_get_reason_codes();
	$status = isset( $codes[ $reason_code ] ) ? $codes[ $reason_code ] : 500;

	return new WP_Error(
		$reason_code,
		$message,
		array_merge(
			array(
				'status'      => $status,
				'reason_code' => $reason_code,
			),
			$data
		)
	);
}

/**
 * Creates canonical Distributed Editing reason data.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $reason_code Canonical DE-RTC reason code.
 * @param array  $data        Optional. Additional reason data.
 * @return array Reason data with status and reason-code fields.
 */
function wp_de_rtc_get_reason_data( $reason_code, $data = array() ) {
	$codes  = wp_de_rtc_get_reason_codes();
	$status = isset( $codes[ $reason_code ] ) ? $codes[ $reason_code ] : 500;

	return array_merge(
		array(
			'status'      => $status,
			'reason_code' => $reason_code,
		),
		$data
	);
}

/**
 * Creates a DE-RTC dry-run result for a recovery update plan.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array  $plan              Recovery update plan.
 * @param string $result            Result label.
 * @param string $validation_status Validation status label.
 * @param bool   $valid             Whether the dry run validated successfully.
 * @param bool   $can_apply         Whether a later apply helper may apply the plan.
 * @param array  $checks            Validation checks.
 * @return array Dry-run result.
 */
function wp_de_rtc_create_recovery_dry_run_result( $plan, $result, $validation_status, $valid, $can_apply, $checks ) {
	return array(
		'mode'                       => 'dry_run',
		'result'                     => $result,
		'validation_status'          => $validation_status,
		'decision'                   => $plan['decision'],
		'post_id'                    => (int) $plan['post_id'],
		'valid'                      => $valid,
		'can_apply'                  => $can_apply,
		'would_apply'                => false,
		'recovery_required'          => ! empty( $plan['recovery_required'] ),
		'manual_resolution_required' => ! empty( $plan['manual_resolution_required'] ),
		'reason'                     => isset( $plan['reason'] ) && is_array( $plan['reason'] ) ? $plan['reason'] : null,
		'reason_code'                => isset( $plan['reason_code'] ) && is_string( $plan['reason_code'] ) ? $plan['reason_code'] : null,
		'checks'                     => $checks,
		'plan'                       => $plan,
	);
}

/**
 * Creates a DE-RTC apply result for a recovery update dry run.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array  $dry_run Apply source dry-run result.
 * @param string $result  Apply result label.
 * @param bool   $applied Whether the candidate was applied.
 * @param array  $extra   Optional. Additional result data.
 * @return array Apply result.
 */
function wp_de_rtc_create_recovery_apply_result( $dry_run, $result, $applied, $extra = array() ) {
	return array_merge(
		array(
			'mode'                       => 'apply',
			'result'                     => $result,
			'validation_status'          => isset( $dry_run['validation_status'] ) ? $dry_run['validation_status'] : null,
			'decision'                   => isset( $dry_run['decision'] ) ? $dry_run['decision'] : null,
			'post_id'                    => isset( $dry_run['post_id'] ) ? (int) $dry_run['post_id'] : 0,
			'valid'                      => ! empty( $dry_run['valid'] ),
			'can_apply'                  => ! empty( $dry_run['can_apply'] ),
			'would_apply'                => $applied,
			'applied'                    => $applied,
			'recovery_required'          => ! empty( $dry_run['recovery_required'] ),
			'manual_resolution_required' => ! empty( $dry_run['manual_resolution_required'] ),
			'reason'                     => isset( $dry_run['reason'] ) && is_array( $dry_run['reason'] ) ? $dry_run['reason'] : null,
			'reason_code'                => isset( $dry_run['reason_code'] ) && is_string( $dry_run['reason_code'] ) ? $dry_run['reason_code'] : null,
			'checks'                     => isset( $dry_run['checks'] ) && is_array( $dry_run['checks'] ) ? $dry_run['checks'] : array(),
			'dry_run'                    => $dry_run,
		),
		$extra
	);
}

/**
 * Returns current revision IDs for a post.
 *
 * @since 7.1.0
 * @access private
 *
 * @param int $post_id Post ID.
 * @return int[] Revision IDs in WordPress revision query order.
 */
function wp_de_rtc_get_post_revision_ids( $post_id ) {
	return array_map(
		'intval',
		array_keys(
			wp_get_post_revisions(
				$post_id,
				array(
					'check_enabled' => false,
				)
			)
		)
	);
}

/**
 * Validates candidate post content from a recovery update plan.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array $plan Recovery update plan.
 * @return bool[] Validation checks.
 */
function wp_de_rtc_validate_recovery_update_candidate( $plan ) {
	$checks = array(
		'candidate_post_content_present'          => isset( $plan['candidate_post_content'] ) && is_string( $plan['candidate_post_content'] ) && '' !== $plan['candidate_post_content'],
		'candidate_post_content_hash_matches'     => false,
		'candidate_stripped_content_hash_matches' => false,
		'candidate_parseable'                     => false,
		'candidate_stripped_content_matches'      => false,
		'candidate_sync_meta_matches'             => false,
		'candidate_sync_meta_format_matches'      => false,
	);

	if ( ! $checks['candidate_post_content_present'] ) {
		return $checks;
	}

	$checks['candidate_post_content_hash_matches'] = (
		isset( $plan['candidate_post_content_hash'] ) &&
		is_string( $plan['candidate_post_content_hash'] ) &&
		hash_equals( $plan['candidate_post_content_hash'], wp_de_rtc_hash_content( $plan['candidate_post_content'] ) )
	);

	$checks['candidate_stripped_content_hash_matches'] = (
		isset( $plan['candidate_stripped_content'], $plan['candidate_stripped_content_hash'] ) &&
		is_string( $plan['candidate_stripped_content'] ) &&
		is_string( $plan['candidate_stripped_content_hash'] ) &&
		hash_equals( $plan['candidate_stripped_content_hash'], wp_de_rtc_hash_content( $plan['candidate_stripped_content'] ) )
	);

	$parsed = wp_de_rtc_parse_post_content_sync_meta( $plan['candidate_post_content'] );

	if ( is_wp_error( $parsed ) || ! is_array( $parsed ) ) {
		return $checks;
	}

	$checks['candidate_parseable'] = true;

	$checks['candidate_stripped_content_matches'] = (
		isset( $plan['candidate_stripped_content'] ) &&
		is_string( $plan['candidate_stripped_content'] ) &&
		$parsed['content'] === $plan['candidate_stripped_content']
	);

	$checks['candidate_sync_meta_matches'] = (
		isset( $plan['restored_sync_meta'] ) &&
		$parsed['sync_meta'] === $plan['restored_sync_meta']
	);

	$checks['candidate_sync_meta_format_matches'] = (
		isset( $plan['restored_sync_meta_format'] ) &&
		is_string( $plan['restored_sync_meta_format'] ) &&
		$parsed['sync_meta_format'] === $plan['restored_sync_meta_format']
	);

	return $checks;
}

/**
 * Hashes stripped post content for DE-RTC base-evidence comparisons.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $content Post content without sync metadata.
 * @return string SHA-256 content hash.
 */
function wp_de_rtc_hash_content( $content ) {
	return hash( 'sha256', $content );
}

/**
 * Returns the next sync-meta version label for a retry save.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $current_version Current sync-meta version.
 * @param string $content_hash    Proposed stripped content hash.
 * @return string Next sync-meta version.
 */
function wp_de_rtc_get_next_sync_meta_version( $current_version, $content_hash ) {
	$current_version = (string) $current_version;
	$content_hash    = (string) $content_hash;

	if ( ctype_digit( $current_version ) ) {
		return (string) ( (int) $current_version + 1 );
	}

	return substr( hash( 'sha256', $current_version . '|' . $content_hash ), 0, 16 );
}

/**
 * Normalizes a sync-meta format label.
 *
 * @since 7.1.0
 * @access private
 *
 * @param mixed $format Sync-meta format label.
 * @return string Normalized label.
 */
function wp_de_rtc_normalize_sync_meta_format( $format ) {
	if ( ! is_string( $format ) ) {
		return '';
	}

	return strtolower( trim( $format ) );
}

/**
 * Matches a possible sync-meta SCRIPT element at one content edge.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $content  Post content.
 * @param string $position Edge position, either 'prefix' or 'trailer'.
 * @return array|false Matched SCRIPT data, or false when no edge script exists.
 */
function wp_de_rtc_match_edge_sync_meta_script( $content, $position ) {
	$script_pattern = '(<script\b[^>]*>(.*?)</script\s*>)';

	if ( 'prefix' === $position ) {
		$pattern = '~\A[ \t\r\n]*' . $script_pattern . '[ \t\r\n]*~is';
	} else {
		$pattern = '~[ \t\r\n]*' . $script_pattern . '[ \t\r\n]*\z~is';
	}

	if ( ! preg_match( $pattern, $content, $matches ) ) {
		return false;
	}

	return array(
		'match'  => $matches[0],
		'script' => $matches[1],
		'json'   => $matches[2],
	);
}

/**
 * Parses a possible Distributed Editing sync-meta SCRIPT element.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $script SCRIPT element HTML.
 * @param string $json   SCRIPT text content.
 * @return array|false|WP_Error Parsed sync metadata, false for non-DE-RTC scripts, or a WP_Error.
 */
function wp_de_rtc_parse_sync_meta_script( $script, $json ) {
	$processor = new WP_HTML_Tag_Processor( $script );

	if ( ! $processor->next_tag( 'script' ) ) {
		return false;
	}

	$type = $processor->get_attribute( 'type' );

	if ( ! is_string( $type ) || 'wp/post-sync-meta' !== strtolower( trim( $type ) ) ) {
		return false;
	}

	$format = wp_de_rtc_normalize_sync_meta_format( $processor->get_attribute( 'data-sync-meta-format' ) );

	if ( '' === $format ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'The Distributed Editing sync metadata format is missing.' ),
			array(
				'detail' => 'missing_sync_meta_format',
			)
		);
	}

	if ( ! in_array( $format, wp_de_rtc_get_supported_sync_meta_formats(), true ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_unknown_sync_meta_format',
			__( 'The Distributed Editing sync metadata format is not supported.' ),
			array(
				'format' => $format,
			)
		);
	}

	$sync_meta = json_decode( trim( $json ), true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $sync_meta ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'The Distributed Editing sync metadata JSON is malformed.' ),
			array(
				'detail'          => 'malformed_json',
				'json_error_code' => json_last_error(),
			)
		);
	}

	return array(
		'sync_meta'        => $sync_meta,
		'sync_meta_format' => $format,
	);
}
