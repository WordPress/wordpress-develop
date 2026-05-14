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
		'de_rtc_review_approval_requires_unfiltered_html' => 403,
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
						'accepted_review_approval_proof' => array(
							'description' => __( 'Accepted hash-only unfiltered HTML review approval proof for guarded retry-save.' ),
							'type'        => 'object',
						),
						'review_approval_proof'        => array(
							'description' => __( 'Hash-only unfiltered HTML review approval proof for guarded retry-save.' ),
							'type'        => 'object',
						),
					),
				),
			)
		);

		register_rest_route(
			'wp/v2',
			'/' . $rest_base . '/(?P<id>[\d]+)/distributed-editing/review-approval',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the post.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'wp_de_rtc_rest_review_approval_endpoint',
					'permission_callback' => 'wp_de_rtc_rest_review_approval_permissions_check',
					'args'                => array(
						'client_base_version'                   => array(
							'description' => __( 'Distributed Editing sync version that the reviewed retry is based on.' ),
							'type'        => 'string',
						),
						'accepted_proof_server_version'         => array(
							'description' => __( 'Server sync version from the accepted retry-submit proof.' ),
							'type'        => 'string',
						),
						'pending_change_count'                  => array(
							'description' => __( 'Number of pending local change groups the reviewed retry carries.' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 1,
						),
						'proposed_post_content_hash'            => array(
							'description' => __( 'SHA-256 hash of the proposed post content without sync metadata.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'reviewed_proposed_content_hash'        => array(
							'description' => __( 'SHA-256 hash of the proposed post content that the reviewer approved.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'candidate_post_content_hash'           => array(
							'description' => __( 'SHA-256 hash of the server candidate post content from the review-required contract.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'reviewed_candidate_content_hash'       => array(
							'description' => __( 'SHA-256 hash of the server candidate post content that the reviewer approved.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'kses_filtered_proposed_content_hash'   => array(
							'description' => __( 'SHA-256 hash of the proposed content after post KSES filtering, when review evidence included it.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'kses_filtered_candidate_content_hash'  => array(
							'description' => __( 'SHA-256 hash of the server candidate after post KSES filtering, when review evidence included it.' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'reviewed_block_items'                  => array(
							'description' => __( 'Hash-only risky block review items approved by the reviewer.' ),
							'type'        => 'array',
							'default'     => array(),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'                             => array(
										'type' => 'string',
									),
									'block_client_id'                => array(
										'type' => 'string',
									),
									'block_name'                     => array(
										'type' => 'string',
									),
									'block_label'                    => array(
										'type' => 'string',
									),
									'block_path'                     => array(
										'type'  => 'array',
										'items' => array(
											'type' => 'integer',
										),
									),
									'change_kind'                    => array(
										'type' => 'string',
									),
									'risk_reason'                    => array(
										'type' => 'string',
									),
									'base_content_hash'              => array(
										'type'    => 'string',
										'pattern' => '^[a-f0-9]{64}$',
									),
									'proposed_content_hash'          => array(
										'type'    => 'string',
										'pattern' => '^[a-f0-9]{64}$',
									),
									'reviewed_proposed_content_hash' => array(
										'type'    => 'string',
										'pattern' => '^[a-f0-9]{64}$',
									),
									'kses_filtered_content_hash'     => array(
										'type'    => 'string',
										'pattern' => '^[a-f0-9]{64}$',
									),
									'review_status'                  => array(
										'type' => 'string',
									),
									'review_evidence_type'           => array(
										'type' => 'string',
									),
									'content_review_policy'          => array(
										'type' => 'string',
									),
								),
							),
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
 * Checks permissions for the review-approval proof REST endpoint.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return true|WP_Error True when the request may proceed, otherwise a WP_Error.
 */
function wp_de_rtc_rest_review_approval_permissions_check( $request ) {
	$post = get_post( (int) $request['id'] );

	if ( ! $post || ! wp_de_rtc_rest_review_approval_request_matches_post_type( $request, $post ) ) {
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

	if ( ! current_user_can( 'unfiltered_html' ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_review_approval_requires_unfiltered_html',
			__( 'Distributed Editing reviewer approval requires an unfiltered HTML-capable reviewer.' ),
			array(
				'detail'                             => 'review_approval_requires_unfiltered_html_reviewer',
				'post_id'                            => (int) $post->ID,
				'rest_route'                         => 'post_retry_save_review_approval',
				'requires_edit_post'                 => true,
				'requires_unfiltered_html'           => true,
				'unfiltered_html_allowed'            => false,
				'authorship_review_required'         => true,
				'content_capability_review_required' => true,
				'review_status'                      => 'reviewer_capability_missing',
				'review_action'                      => 'request_unfiltered_html_reviewer',
				'approval_action'                    => 'retry_save_with_reviewer_approval',
				'review_required_capability'         => 'unfiltered_html',
				'reviewer_capability'                => 'unfiltered_html',
				'review_scope'                       => 'collaborative_post_content',
				'raw_content_included'               => false,
				'saves_post'                         => false,
				'mutates_post_content'               => false,
				'creates_revision'                   => false,
				'claims_saved'                       => false,
				'permission_contract'                => wp_de_rtc_get_rest_recovery_permission_contract( $post ),
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
 * Returns whether the REST review-approval request matches the post type route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @param WP_Post         $post    Post object.
 * @return bool Whether the requested route matches the post type REST base.
 */
function wp_de_rtc_rest_review_approval_request_matches_post_type( $request, $post ) {
	$requested_rest_base = wp_de_rtc_get_rest_review_approval_request_rest_base( $request );
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
 * Returns the post type REST base from a review-approval request route.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return string Requested post type REST base, or empty string.
 */
function wp_de_rtc_get_rest_review_approval_request_rest_base( $request ) {
	$route = $request->get_route();

	if ( ! is_string( $route ) ) {
		return '';
	}

	if ( ! preg_match( '#^/wp/v2/([^/]+)/\d+/distributed-editing/review-approval$#', $route, $matches ) ) {
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
			'review_approval_proof'              => $request->has_param( 'accepted_review_approval_proof' ) ? $request->get_param( 'accepted_review_approval_proof' ) : $request->get_param( 'review_approval_proof' ),
		)
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response( $result );
}

/**
 * Handles the unfiltered-HTML review approval proof REST endpoint.
 *
 * This endpoint accepts only hash and version evidence from the existing
 * retry-save review-required contract. It does not save, mutate post content,
 * create revisions, apply recovery, replace post locks, or claim saved state.
 *
 * @since 7.1.0
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response|WP_Error REST response on success, otherwise an error.
 */
function wp_de_rtc_rest_review_approval_endpoint( $request ) {
	$result = wp_de_rtc_get_unfiltered_html_review_approval_result(
		(int) $request['id'],
		array(
			'client_base_version'                  => $request->get_param( 'client_base_version' ),
			'accepted_proof_server_version'        => $request->get_param( 'accepted_proof_server_version' ),
			'pending_change_count'                 => $request->get_param( 'pending_change_count' ),
			'proposed_post_content_hash'           => $request->get_param( 'proposed_post_content_hash' ),
			'reviewed_proposed_content_hash'       => $request->get_param( 'reviewed_proposed_content_hash' ),
			'candidate_post_content_hash'          => $request->get_param( 'candidate_post_content_hash' ),
			'reviewed_candidate_content_hash'      => $request->get_param( 'reviewed_candidate_content_hash' ),
			'kses_filtered_proposed_content_hash'  => $request->get_param( 'kses_filtered_proposed_content_hash' ),
			'kses_filtered_candidate_content_hash' => $request->get_param( 'kses_filtered_candidate_content_hash' ),
			'reviewed_block_items'                 => $request->get_param( 'reviewed_block_items' ),
			'raw_request_params'                   => $request->get_params(),
		)
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response( $result );
}

/**
 * Returns a proof-only unfiltered-HTML review approval result.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @param array       $args {
 *     Review approval arguments.
 *
 *     @type mixed $client_base_version                  Client base version after local rebase.
 *     @type mixed $accepted_proof_server_version        Server version from accepted proof.
 *     @type mixed $pending_change_count                 Pending local change count. Default 1.
 *     @type mixed $proposed_post_content_hash           Proposed stripped post-content hash.
 *     @type mixed $reviewed_proposed_content_hash       Reviewer-approved proposed stripped post-content hash.
 *     @type mixed $candidate_post_content_hash          Retry-save candidate post-content hash.
 *     @type mixed $reviewed_candidate_content_hash      Reviewer-approved retry-save candidate hash.
 *     @type mixed $kses_filtered_proposed_content_hash  Optional filtered proposed-content hash.
 *     @type mixed $kses_filtered_candidate_content_hash Optional filtered retry-save candidate hash.
 *     @type array $reviewed_block_items                 Optional approved risky-block review items.
 *     @type array $raw_request_params                   Full request parameters for raw-content rejection.
 * }
 * @return array|WP_Error Review approval result, or rejection.
 */
function wp_de_rtc_get_unfiltered_html_review_approval_result( $post, $args = array() ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	$raw_content_param_paths = isset( $args['raw_request_params'] ) && is_array( $args['raw_request_params'] )
		? wp_de_rtc_find_raw_post_content_param_paths( $args['raw_request_params'] )
		: array();

	if ( ! empty( $raw_content_param_paths ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the review approval because raw post content is not allowed.' ),
			array(
				'detail'                       => 'review_approval_raw_post_content_rejected',
				'post_id'                      => (int) $post->ID,
				'rest_route'                   => 'post_retry_save_review_approval',
				'request_raw_content_included' => true,
				'raw_content_included'         => false,
				'raw_content_param_paths'      => $raw_content_param_paths,
				'saves_post'                   => false,
				'mutates_post_content'         => false,
				'creates_revision'             => false,
				'claims_saved'                 => false,
			)
		);
	}

	$client_base_version            = isset( $args['client_base_version'] ) ? sanitize_text_field( (string) $args['client_base_version'] ) : '';
	$accepted_proof_server_version = isset( $args['accepted_proof_server_version'] ) ? sanitize_text_field( (string) $args['accepted_proof_server_version'] ) : '';
	$server_version                 = wp_de_rtc_get_post_sync_meta_version( $post );
	$pending_change_count           = array_key_exists( 'pending_change_count', $args ) ? max( 0, (int) $args['pending_change_count'] ) : 1;

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
				'rest_route'               => 'post_retry_save_review_approval_stale_base',
				'saves_post'               => false,
				'mutates_post_content'     => false,
				'creates_revision'         => false,
				'claims_saved'             => false,
			)
		);
	}

	$proposed_post_content_hash      = wp_de_rtc_get_request_hash_evidence( $args, 'proposed_post_content_hash' );
	$reviewed_proposed_content_hash  = wp_de_rtc_get_request_hash_evidence( $args, 'reviewed_proposed_content_hash' );
	$candidate_post_content_hash     = wp_de_rtc_get_request_hash_evidence( $args, 'candidate_post_content_hash' );
	$reviewed_candidate_content_hash = wp_de_rtc_get_request_hash_evidence( $args, 'reviewed_candidate_content_hash' );
	$missing_hash_fields             = array();

	foreach (
		array(
			'proposed_post_content_hash'      => $proposed_post_content_hash,
			'reviewed_proposed_content_hash'  => $reviewed_proposed_content_hash,
			'candidate_post_content_hash'     => $candidate_post_content_hash,
			'reviewed_candidate_content_hash' => $reviewed_candidate_content_hash,
		) as $field => $hash
	) {
		if ( ! wp_de_rtc_is_sha256_hash( $hash ) ) {
			$missing_hash_fields[] = $field;
		}
	}

	$kses_filtered_proposed_content_hash  = wp_de_rtc_get_request_hash_evidence( $args, 'kses_filtered_proposed_content_hash' );
	$kses_filtered_candidate_content_hash = wp_de_rtc_get_request_hash_evidence( $args, 'kses_filtered_candidate_content_hash' );

	foreach (
		array(
			'kses_filtered_proposed_content_hash'  => $kses_filtered_proposed_content_hash,
			'kses_filtered_candidate_content_hash' => $kses_filtered_candidate_content_hash,
		) as $field => $hash
	) {
		if ( null !== $hash && ! wp_de_rtc_is_sha256_hash( $hash ) ) {
			$missing_hash_fields[] = $field;
		}
	}

	if ( ! empty( $missing_hash_fields ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing rejected the review approval because hash evidence is incomplete.' ),
			array(
				'detail'                       => 'missing_review_approval_hash_evidence',
				'post_id'                      => (int) $post->ID,
				'rest_route'                   => 'post_retry_save_review_approval',
				'missing_hash_evidence_fields' => $missing_hash_fields,
				'raw_content_included'         => false,
				'saves_post'                   => false,
				'mutates_post_content'         => false,
				'creates_revision'             => false,
				'claims_saved'                 => false,
			)
		);
	}

	$mismatched_hash_fields = array();

	if ( ! hash_equals( $proposed_post_content_hash, $reviewed_proposed_content_hash ) ) {
		$mismatched_hash_fields[] = 'reviewed_proposed_content_hash';
	}

	if ( ! hash_equals( $candidate_post_content_hash, $reviewed_candidate_content_hash ) ) {
		$mismatched_hash_fields[] = 'reviewed_candidate_content_hash';
	}

	if ( ! empty( $mismatched_hash_fields ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the review approval because the approved hash evidence does not match.' ),
			array(
				'detail'                          => 'review_approval_hash_evidence_mismatch',
				'post_id'                         => (int) $post->ID,
				'rest_route'                      => 'post_retry_save_review_approval',
				'mismatched_hash_evidence_fields' => $mismatched_hash_fields,
				'proposed_post_content_hash'      => $proposed_post_content_hash,
				'reviewed_proposed_content_hash'  => $reviewed_proposed_content_hash,
				'candidate_post_content_hash'     => $candidate_post_content_hash,
				'reviewed_candidate_content_hash' => $reviewed_candidate_content_hash,
				'raw_content_included'            => false,
				'saves_post'                      => false,
				'mutates_post_content'            => false,
				'creates_revision'                => false,
				'claims_saved'                    => false,
			)
		);
	}

	$reviewed_block_items = wp_de_rtc_get_normalized_review_approval_block_items( $post, $args );

	if ( is_wp_error( $reviewed_block_items ) ) {
		return $reviewed_block_items;
	}

	$review_approval_proof = array(
		'type'                              => 'unfiltered_html_retry_save_review_approval',
		'status'                            => 'approved_by_unfiltered_html_reviewer',
		'reviewer_user_id'                  => get_current_user_id(),
		'reviewer_capability'               => 'unfiltered_html',
		'review_scope'                      => 'collaborative_post_content',
		'server_version'                    => $server_version,
		'client_base_version'               => $client_base_version,
		'accepted_proof_server_version'     => $accepted_proof_server_version,
		'proposed_post_content_hash'        => $proposed_post_content_hash,
		'reviewed_proposed_content_hash'    => $reviewed_proposed_content_hash,
		'candidate_post_content_hash'       => $candidate_post_content_hash,
		'reviewed_candidate_content_hash'   => $reviewed_candidate_content_hash,
		'raw_content_included'              => false,
		'saves_post'                        => false,
		'mutates_post_content'              => false,
		'creates_revision'                  => false,
		'claims_saved'                      => false,
		'reviewed_block_items'              => $reviewed_block_items,
		'reviewed_block_item_count'         => count( $reviewed_block_items ),
		'block_review_status'               => ! empty( $reviewed_block_items ) ? 'approved_for_retry_save' : null,
	);

	if ( null !== $kses_filtered_proposed_content_hash ) {
		$review_approval_proof['kses_filtered_proposed_content_hash'] = $kses_filtered_proposed_content_hash;
	}

	if ( null !== $kses_filtered_candidate_content_hash ) {
		$review_approval_proof['kses_filtered_candidate_content_hash'] = $kses_filtered_candidate_content_hash;
	}

	return array(
		'result'                              => 'review_approval_accepted_for_retry_save',
		'review_approval_accepted'            => true,
		'unfiltered_html_review_approved'     => true,
		'rest_route'                          => 'post_retry_save_review_approval',
		'post_id'                             => (int) $post->ID,
		'reviewer_user_id'                    => get_current_user_id(),
		'client_base_version'                 => $client_base_version,
		'accepted_proof_server_version'       => $accepted_proof_server_version,
		'server_version'                      => $server_version,
		'pending_change_count'                => $pending_change_count,
		'approval_status'                     => 'approved_for_retry_save',
		'approval_action'                     => 'retry_save_with_reviewer_approval',
		'review_status'                       => 'approved_by_unfiltered_html_reviewer',
		'review_action'                       => 'request_unfiltered_html_reviewer',
		'review_required_capability'          => 'unfiltered_html',
		'reviewer_capability'                 => 'unfiltered_html',
		'review_scope'                        => 'collaborative_post_content',
		'proposed_post_content_hash'          => $proposed_post_content_hash,
		'reviewed_proposed_content_hash'      => $reviewed_proposed_content_hash,
		'candidate_post_content_hash'         => $candidate_post_content_hash,
		'reviewed_candidate_content_hash'     => $reviewed_candidate_content_hash,
		'kses_filtered_proposed_content_hash'  => $kses_filtered_proposed_content_hash,
		'kses_filtered_candidate_content_hash' => $kses_filtered_candidate_content_hash,
		'reviewed_block_items'                => $reviewed_block_items,
		'reviewed_block_item_count'           => count( $reviewed_block_items ),
		'block_review_status'                 => ! empty( $reviewed_block_items ) ? 'approved_for_retry_save' : null,
		'raw_content_included'                => false,
		'requires_server_state_refetch'       => false,
		'requires_manual_conflict_resolution' => false,
		'can_export_local_updates'            => $pending_change_count > 0,
		'save_path_required'                  => true,
		'saves_post'                          => false,
		'mutates_post_content'                => false,
		'creates_revision'                    => false,
		'claims_saved'                        => false,
		'review_approval_proof'               => $review_approval_proof,
		'permission_contract'                 => wp_de_rtc_get_rest_recovery_permission_contract( $post ),
	);
}

/**
 * Returns normalized hash-only risky-block approval proof items.
 *
 * @since 7.1.0
 * @access private
 *
 * @param WP_Post $post Post object.
 * @param array   $args Review approval arguments.
 * @return array[]|WP_Error Normalized block review items, or a rejection.
 */
function wp_de_rtc_get_normalized_review_approval_block_items( $post, $args ) {
	if (
		! array_key_exists( 'reviewed_block_items', $args ) ||
		null === $args['reviewed_block_items']
	) {
		return array();
	}

	if ( ! is_array( $args['reviewed_block_items'] ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing rejected the review approval because block review proof is malformed.' ),
			array(
				'detail'                       => 'malformed_review_approval_block_items',
				'post_id'                      => (int) $post->ID,
				'rest_route'                   => 'post_retry_save_review_approval',
				'raw_content_included'         => false,
				'saves_post'                   => false,
				'mutates_post_content'         => false,
				'creates_revision'             => false,
				'claims_saved'                 => false,
			)
		);
	}

	$normalized_items  = array();
	$missing_fields    = array();
	$mismatched_fields = array();
	$unapproved_items  = array();

	foreach ( $args['reviewed_block_items'] as $index => $item ) {
		if ( is_object( $item ) ) {
			$item = get_object_vars( $item );
		}

		if ( ! is_array( $item ) ) {
			$missing_fields[] = 'reviewed_block_items.' . $index;
			continue;
		}

		if (
			! empty( $item['raw_content_included'] ) ||
			! empty( $item['exposes_raw_content'] )
		) {
			return wp_de_rtc_get_reason_error(
				'de_rtc_sync_meta_tampered',
				__( 'Distributed Editing rejected the review approval because a block review item included raw content evidence.' ),
				array(
					'detail'                       => 'review_approval_block_item_raw_content_rejected',
					'post_id'                      => (int) $post->ID,
					'rest_route'                   => 'post_retry_save_review_approval',
					'reviewed_block_item_index'    => $index,
					'request_raw_content_included' => true,
					'raw_content_included'         => false,
					'saves_post'                   => false,
					'mutates_post_content'         => false,
					'creates_revision'             => false,
					'claims_saved'                 => false,
				)
			);
		}

		$id                             = isset( $item['id'] ) ? sanitize_text_field( (string) $item['id'] ) : '';
		$review_status                  = isset( $item['review_status'] ) ? sanitize_key( (string) $item['review_status'] ) : '';
		$proposed_content_hash          = wp_de_rtc_get_request_hash_evidence( $item, 'proposed_content_hash' );
		$reviewed_proposed_content_hash = wp_de_rtc_get_request_hash_evidence( $item, 'reviewed_proposed_content_hash' );
		$base_content_hash              = wp_de_rtc_get_request_hash_evidence( $item, 'base_content_hash' );
		$kses_filtered_content_hash     = wp_de_rtc_get_request_hash_evidence( $item, 'kses_filtered_content_hash' );
		$review_evidence_type           = isset( $item['review_evidence_type'] )
			? sanitize_key( (string) $item['review_evidence_type'] )
			: 'kses_block_hash_only_change';
		$content_review_policy          = isset( $item['content_review_policy'] )
			? sanitize_key( (string) $item['content_review_policy'] )
			: 'kses';

		if ( '' === $id ) {
			$missing_fields[] = 'reviewed_block_items.' . $index . '.id';
		}

		if ( 'approved_for_retry_save' !== $review_status ) {
			$unapproved_items[] = $id ? $id : (string) $index;
		}

		if ( ! wp_de_rtc_is_sha256_hash( $proposed_content_hash ) ) {
			$missing_fields[] = 'reviewed_block_items.' . $index . '.proposed_content_hash';
		}

		if ( null === $reviewed_proposed_content_hash ) {
			$reviewed_proposed_content_hash = $proposed_content_hash;
		}

		if ( ! wp_de_rtc_is_sha256_hash( $reviewed_proposed_content_hash ) ) {
			$missing_fields[] = 'reviewed_block_items.' . $index . '.reviewed_proposed_content_hash';
		}

		if ( null !== $base_content_hash && ! wp_de_rtc_is_sha256_hash( $base_content_hash ) ) {
			$missing_fields[] = 'reviewed_block_items.' . $index . '.base_content_hash';
		}

		if ( null !== $kses_filtered_content_hash && ! wp_de_rtc_is_sha256_hash( $kses_filtered_content_hash ) ) {
			$missing_fields[] = 'reviewed_block_items.' . $index . '.kses_filtered_content_hash';
		}

		if (
			null !== $proposed_content_hash &&
			null !== $reviewed_proposed_content_hash &&
			! hash_equals( $proposed_content_hash, $reviewed_proposed_content_hash )
		) {
			$mismatched_fields[] = 'reviewed_block_items.' . $index . '.reviewed_proposed_content_hash';
		}

		if ( 'kses_block_hash_only_change' !== $review_evidence_type ) {
			$missing_fields[] = 'reviewed_block_items.' . $index . '.review_evidence_type';
		}

		if ( 'kses' !== $content_review_policy ) {
			$missing_fields[] = 'reviewed_block_items.' . $index . '.content_review_policy';
		}

		$normalized_items[] = array(
			'id'                             => $id,
			'block_client_id'                => isset( $item['block_client_id'] )
				? sanitize_text_field( (string) $item['block_client_id'] )
				: '',
			'block_name'                     => isset( $item['block_name'] )
				? sanitize_text_field( (string) $item['block_name'] )
				: '',
			'block_label'                    => isset( $item['block_label'] )
				? sanitize_text_field( (string) $item['block_label'] )
				: '',
			'block_path'                     => isset( $item['block_path'] ) && is_array( $item['block_path'] )
				? array_map( 'absint', $item['block_path'] )
				: array(),
			'change_kind'                    => isset( $item['change_kind'] )
				? sanitize_key( (string) $item['change_kind'] )
				: '',
			'risk_reason'                    => isset( $item['risk_reason'] )
				? sanitize_key( (string) $item['risk_reason'] )
				: '',
			'base_content_hash'              => $base_content_hash,
			'proposed_content_hash'          => $proposed_content_hash,
			'reviewed_proposed_content_hash' => $reviewed_proposed_content_hash,
			'kses_filtered_content_hash'     => $kses_filtered_content_hash,
			'review_status'                  => 'approved_for_retry_save',
			'review_evidence_type'           => 'kses_block_hash_only_change',
			'content_review_policy'          => 'kses',
			'raw_content_included'           => false,
			'exposes_raw_content'            => false,
		);
	}

	if ( ! empty( $missing_fields ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing rejected the review approval because block review hash evidence is incomplete.' ),
			array(
				'detail'                       => 'missing_review_approval_block_item_evidence',
				'post_id'                      => (int) $post->ID,
				'rest_route'                   => 'post_retry_save_review_approval',
				'missing_hash_evidence_fields' => $missing_fields,
				'raw_content_included'         => false,
				'saves_post'                   => false,
				'mutates_post_content'         => false,
				'creates_revision'             => false,
				'claims_saved'                 => false,
			)
		);
	}

	if ( ! empty( $unapproved_items ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing rejected the review approval because not every block review item was approved.' ),
			array(
				'detail'                         => 'review_approval_block_item_not_approved',
				'post_id'                        => (int) $post->ID,
				'rest_route'                     => 'post_retry_save_review_approval',
				'unapproved_review_item_ids'     => $unapproved_items,
				'raw_content_included'           => false,
				'saves_post'                     => false,
				'mutates_post_content'           => false,
				'creates_revision'               => false,
				'claims_saved'                   => false,
			)
		);
	}

	if ( ! empty( $mismatched_fields ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the review approval because block review hash evidence does not match.' ),
			array(
				'detail'                          => 'review_approval_block_item_hash_evidence_mismatch',
				'post_id'                         => (int) $post->ID,
				'rest_route'                      => 'post_retry_save_review_approval',
				'mismatched_hash_evidence_fields' => $mismatched_fields,
				'raw_content_included'            => false,
				'saves_post'                      => false,
				'mutates_post_content'            => false,
				'creates_revision'                => false,
				'claims_saved'                    => false,
			)
		);
	}

	return $normalized_items;
}

/**
 * Validates hash-only review-approval proof before retry-save persistence.
 *
 * @since 7.1.0
 * @access private
 *
 * @param WP_Post $post Post object.
 * @param array   $args {
 *     Proof consumption arguments.
 *
 *     @type mixed  $review_approval_proof         Review approval proof object.
 *     @type string $client_base_version           Client base version.
 *     @type string $accepted_proof_server_version Accepted proof server version.
 *     @type string $server_version                Current server sync version.
 *     @type int    $pending_change_count          Pending change count.
 *     @type string $proposed_post_content_hash    Hash of proposed content without sync metadata.
 *     @type string $candidate_post_content_hash   Hash of candidate post_content with sync metadata.
 *     @type bool   $proof_required                Whether a proof is required before persistence.
 * }
 * @return array|WP_Error Proof consumption result, or rejection.
 */
function wp_de_rtc_get_retry_save_review_approval_proof_consumption_result( $post, $args ) {
	$proof_required = ! empty( $args['proof_required'] );
	$proof          = isset( $args['review_approval_proof'] ) ? $args['review_approval_proof'] : null;
	$post_id        = $post ? (int) $post->ID : 0;

	if ( is_object( $proof ) ) {
		$proof = get_object_vars( $proof );
	}

	if ( null === $proof || '' === $proof ) {
		if ( ! $proof_required ) {
			return array(
				'review_approval_proof_consumed' => false,
				'reviewed_block_item_count'      => 0,
			);
		}

		return new WP_Error(
			'de_rtc_unfiltered_html_would_change_content',
			__( 'Distributed Editing requires unfiltered HTML review before retry save.' )
		);
	}

	if ( ! is_array( $proof ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing rejected the retry save because review approval proof is malformed.' ),
			array(
				'detail'               => 'malformed_retry_save_review_approval_proof',
				'post_id'              => $post_id,
				'rest_route'           => 'post_retry_save',
				'saves_post'           => false,
				'mutates_post_content' => false,
				'creates_revision'     => false,
				'claims_saved'         => false,
			)
		);
	}

	$raw_content_param_paths = wp_de_rtc_find_raw_post_content_param_paths( $proof, 'review_approval_proof' );
	$proof_raw_content_flag  = ! empty( $proof['raw_content_included'] ) || ! empty( $proof['exposes_raw_content'] );

	if ( $proof_raw_content_flag || ! empty( $raw_content_param_paths ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the retry save because review approval proof included raw content.' ),
			array(
				'detail'                       => 'retry_save_review_approval_raw_content_rejected',
				'post_id'                      => $post_id,
				'rest_route'                   => 'post_retry_save',
				'request_raw_content_included' => true,
				'raw_content_included'         => false,
				'raw_content_param_paths'      => $raw_content_param_paths,
				'saves_post'                   => false,
				'mutates_post_content'         => false,
				'creates_revision'             => false,
				'claims_saved'                 => false,
			)
		);
	}

	$proof_type      = isset( $proof['type'] ) ? sanitize_key( (string) $proof['type'] ) : '';
	$proof_status    = isset( $proof['status'] ) ? sanitize_key( (string) $proof['status'] ) : '';
	$proof_status    = '' !== $proof_status || ! isset( $proof['proof_status'] ) ? $proof_status : sanitize_key( (string) $proof['proof_status'] );
	$reviewer_cap    = isset( $proof['reviewer_capability'] ) ? sanitize_key( (string) $proof['reviewer_capability'] ) : '';
	$proof_saves     = ! empty( $proof['saves_post'] ) && wp_validate_boolean( $proof['saves_post'] );
	$proof_mutates   = ! empty( $proof['mutates_post_content'] ) && wp_validate_boolean( $proof['mutates_post_content'] );
	$proof_revisions = ! empty( $proof['creates_revision'] ) && wp_validate_boolean( $proof['creates_revision'] );
	$proof_claims    = ! empty( $proof['claims_saved'] ) && wp_validate_boolean( $proof['claims_saved'] );

	if (
		'unfiltered_html_retry_save_review_approval' !== $proof_type ||
		'approved_by_unfiltered_html_reviewer' !== $proof_status ||
		'unfiltered_html' !== $reviewer_cap ||
		$proof_saves ||
		$proof_mutates ||
		$proof_revisions ||
		$proof_claims
	) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the retry save because review approval proof is contradictory.' ),
			array(
				'detail'               => 'retry_save_review_approval_proof_rejected',
				'post_id'              => $post_id,
				'rest_route'           => 'post_retry_save',
				'saves_post'           => false,
				'mutates_post_content' => false,
				'creates_revision'     => false,
				'claims_saved'         => false,
			)
		);
	}

	$client_base_version            = isset( $args['client_base_version'] ) ? sanitize_text_field( (string) $args['client_base_version'] ) : '';
	$accepted_proof_server_version = isset( $args['accepted_proof_server_version'] ) ? sanitize_text_field( (string) $args['accepted_proof_server_version'] ) : '';
	$server_version                 = isset( $args['server_version'] ) ? sanitize_text_field( (string) $args['server_version'] ) : '';
	$proof_client_base_version      = isset( $proof['client_base_version'] ) ? sanitize_text_field( (string) $proof['client_base_version'] ) : '';
	$proof_accepted_server_version  = isset( $proof['accepted_proof_server_version'] ) ? sanitize_text_field( (string) $proof['accepted_proof_server_version'] ) : '';
	$proof_server_version           = isset( $proof['server_version'] ) ? sanitize_text_field( (string) $proof['server_version'] ) : '';
	$pending_change_count           = array_key_exists( 'pending_change_count', $args ) ? max( 0, (int) $args['pending_change_count'] ) : 1;

	if (
		'' === $proof_client_base_version ||
		'' === $proof_accepted_server_version ||
		'' === $proof_server_version ||
		$proof_client_base_version !== $client_base_version ||
		$proof_accepted_server_version !== $accepted_proof_server_version ||
		$proof_server_version !== $server_version
	) {
		return wp_de_rtc_get_stale_base_rejection_error(
			$post,
			array(
				'client_base_version'      => $client_base_version,
				'server_version'           => $server_version,
				'pending_change_count'     => $pending_change_count,
				'remote_change_count'      => 1,
				'can_attempt_local_rebase' => false,
				'rest_route'               => 'post_retry_save_review_approval_consumption_stale_base',
				'saves_post'               => false,
				'mutates_post_content'     => false,
				'creates_revision'         => false,
				'claims_saved'             => false,
			)
		);
	}

	$proposed_hash                      = isset( $args['proposed_post_content_hash'] ) ? sanitize_text_field( (string) $args['proposed_post_content_hash'] ) : '';
	$candidate_hash                     = isset( $args['candidate_post_content_hash'] ) ? sanitize_text_field( (string) $args['candidate_post_content_hash'] ) : '';
	$proof_proposed_hash                = wp_de_rtc_get_request_hash_evidence( $proof, 'proposed_post_content_hash' );
	$proof_reviewed_proposed            = wp_de_rtc_get_request_hash_evidence( $proof, 'reviewed_proposed_content_hash' );
	$proof_candidate_hash               = wp_de_rtc_get_request_hash_evidence( $proof, 'candidate_post_content_hash' );
	$proof_reviewed_candidate           = wp_de_rtc_get_request_hash_evidence( $proof, 'reviewed_candidate_content_hash' );
	$missing_hash_fields                = array();
	$proof_candidate_hash_scope         = isset( $proof['candidate_post_content_hash_scope'] ) ? sanitize_key( (string) $proof['candidate_post_content_hash_scope'] ) : '';
	$proof_requires_unfiltered_saver    = ! empty( $proof['requires_unfiltered_html_saver'] ) && wp_validate_boolean( $proof['requires_unfiltered_html_saver'] );
	$can_consume_handoff_candidate_hash = current_user_can( 'unfiltered_html' )
		&& $proof_requires_unfiltered_saver
		&& 'low_privileged_saver_candidate' === $proof_candidate_hash_scope;

	foreach (
		array(
			'review_approval_proof.proposed_post_content_hash'           => $proof_proposed_hash,
			'review_approval_proof.reviewed_proposed_content_hash'       => $proof_reviewed_proposed,
			'review_approval_proof.candidate_post_content_hash'          => $proof_candidate_hash,
			'review_approval_proof.reviewed_candidate_content_hash'      => $proof_reviewed_candidate,
		) as $field => $hash
	) {
		if ( ! wp_de_rtc_is_sha256_hash( $hash ) ) {
			$missing_hash_fields[] = $field;
		}
	}

	if ( ! empty( $missing_hash_fields ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing rejected the retry save because review approval hash evidence is incomplete.' ),
			array(
				'detail'                       => 'missing_retry_save_review_approval_hash_evidence',
				'post_id'                      => $post_id,
				'rest_route'                   => 'post_retry_save',
				'missing_hash_evidence_fields' => $missing_hash_fields,
				'raw_content_included'         => false,
				'saves_post'                   => false,
				'mutates_post_content'         => false,
				'creates_revision'             => false,
				'claims_saved'                 => false,
			)
		);
	}

	$mismatched_fields = array();

	if ( ! hash_equals( $proposed_hash, $proof_proposed_hash ) ) {
		$mismatched_fields[] = 'review_approval_proof.proposed_post_content_hash';
	}

	if ( ! hash_equals( $proposed_hash, $proof_reviewed_proposed ) ) {
		$mismatched_fields[] = 'review_approval_proof.reviewed_proposed_content_hash';
	}

	if ( ! hash_equals( $candidate_hash, $proof_candidate_hash ) && ! $can_consume_handoff_candidate_hash ) {
		$mismatched_fields[] = 'review_approval_proof.candidate_post_content_hash';
	}

	if ( ! hash_equals( $proof_candidate_hash, $proof_reviewed_candidate ) ) {
		$mismatched_fields[] = 'review_approval_proof.reviewed_candidate_content_hash';
	} elseif ( ! hash_equals( $candidate_hash, $proof_reviewed_candidate ) && ! $can_consume_handoff_candidate_hash ) {
		$mismatched_fields[] = 'review_approval_proof.reviewed_candidate_content_hash';
	}

	if ( ! empty( $mismatched_fields ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_sync_meta_tampered',
			__( 'Distributed Editing rejected the retry save because review approval hash evidence does not match.' ),
			array(
				'detail'                          => 'retry_save_review_approval_hash_evidence_mismatch',
				'post_id'                         => $post_id,
				'rest_route'                      => 'post_retry_save',
				'mismatched_hash_evidence_fields' => $mismatched_fields,
				'raw_content_included'            => false,
				'saves_post'                      => false,
				'mutates_post_content'            => false,
				'creates_revision'                => false,
				'claims_saved'                    => false,
			)
		);
	}

	$reviewed_block_items = wp_de_rtc_get_normalized_review_approval_block_items(
		$post,
		array(
			'reviewed_block_items' => isset( $proof['reviewed_block_items'] ) ? $proof['reviewed_block_items'] : array(),
		)
	);

	if ( is_wp_error( $reviewed_block_items ) ) {
		$code = $reviewed_block_items->get_error_code();
		$data = $reviewed_block_items->get_error_data( $code );

		if ( is_array( $data ) ) {
			$data['rest_route'] = 'post_retry_save';
			$reviewed_block_items->add_data( $data, $code );
		}

		return $reviewed_block_items;
	}

	$requires_unfiltered_html_saver = ! current_user_can( 'unfiltered_html' );
	$accepted_review_approval_proof = array(
		'type'                              => 'unfiltered_html_retry_save_review_approval',
		'status'                            => 'approved_by_unfiltered_html_reviewer',
		'reviewer_capability'               => 'unfiltered_html',
		'review_scope'                      => 'collaborative_post_content',
		'review_status'                     => 'approved_by_unfiltered_html_reviewer',
		'approval_status'                   => 'approved_for_retry_save',
		'review_action'                     => 'request_unfiltered_html_reviewer',
		'approval_action'                   => $requires_unfiltered_html_saver ? 'retry_save_with_unfiltered_html_saver' : 'retry_save_with_reviewer_approval',
		'review_required_capability'        => 'unfiltered_html',
		'client_base_version'               => $client_base_version,
		'accepted_proof_server_version'     => $accepted_proof_server_version,
		'server_version'                    => $server_version,
		'proposed_post_content_hash'        => $proof_proposed_hash,
		'reviewed_proposed_content_hash'    => $proof_reviewed_proposed,
		'candidate_post_content_hash'       => $proof_candidate_hash,
		'reviewed_candidate_content_hash'   => $proof_reviewed_candidate,
		'candidate_post_content_hash_scope' => $requires_unfiltered_html_saver ? 'low_privileged_saver_candidate' : 'current_saver_candidate',
		'requires_unfiltered_html_saver'    => $requires_unfiltered_html_saver,
		'reviewed_block_items'              => $reviewed_block_items,
		'reviewed_block_item_count'         => count( $reviewed_block_items ),
		'block_review_status'               => ! empty( $reviewed_block_items ) ? 'approved_for_retry_save' : null,
		'raw_content_included'              => false,
		'saves_post'                        => false,
		'mutates_post_content'              => false,
		'creates_revision'                  => false,
		'claims_saved'                      => false,
	);

	return array(
		'review_approval_proof_consumed' => true,
		'accepted_review_approval_proof' => $accepted_review_approval_proof,
		'reviewed_block_items'           => $reviewed_block_items,
		'reviewed_block_item_count'      => count( $reviewed_block_items ),
		'block_review_status'            => ! empty( $reviewed_block_items ) ? 'approved_for_retry_save' : null,
	);
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
 *     @type array $review_approval_proof               Hash-only unfiltered HTML review approval proof.
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
	$review_approval_proof          = isset( $args['review_approval_proof'] ) ? $args['review_approval_proof'] : null;
	$review_approval_consumption    = null;

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

	$candidate_hash = wp_de_rtc_hash_content( $candidate_post_content );

	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$proposed_post_content_kses_review = wp_de_rtc_get_kses_post_content_review_evidence( $proposed_post_content );

		if ( ! empty( $proposed_post_content_kses_review['would_change_content'] ) ) {
			$review_approval_consumption = wp_de_rtc_get_retry_save_review_approval_proof_consumption_result(
				$post,
				array(
					'review_approval_proof'         => $review_approval_proof,
					'client_base_version'           => $client_base_version,
					'accepted_proof_server_version' => $accepted_proof_server_version,
					'server_version'                => $server_version,
					'pending_change_count'          => $pending_change_count,
					'proposed_post_content_hash'    => $proposed_post_content_hash,
					'candidate_post_content_hash'   => $candidate_hash,
					'proof_required'                => true,
				)
			);

			if ( is_wp_error( $review_approval_consumption ) ) {
				if ( 'de_rtc_unfiltered_html_would_change_content' === $review_approval_consumption->get_error_code() ) {
					return wp_de_rtc_get_unfiltered_html_review_rejection_error(
						$post,
						array(
							'pending_change_count'              => $pending_change_count,
							'rest_route'                        => 'post_retry_save',
							'proposed_post_content_hash'        => $proposed_post_content_hash,
							'candidate_post_content_hash'       => $candidate_hash,
							'proposed_post_content_kses_review' => $proposed_post_content_kses_review,
						)
					);
				}

				return $review_approval_consumption;
			}
		}

		$candidate_post_content_kses_review = wp_de_rtc_get_kses_post_content_review_evidence(
			$candidate_post_content,
			array(
				'allow_sync_meta_script' => true,
			)
		);

		if ( ! empty( $candidate_post_content_kses_review['would_change_content'] ) ) {
			$review_approval_consumption = wp_de_rtc_get_retry_save_review_approval_proof_consumption_result(
				$post,
				array(
					'review_approval_proof'         => $review_approval_proof,
					'client_base_version'           => $client_base_version,
					'accepted_proof_server_version' => $accepted_proof_server_version,
					'server_version'                => $server_version,
					'pending_change_count'          => $pending_change_count,
					'proposed_post_content_hash'    => $proposed_post_content_hash,
					'candidate_post_content_hash'   => $candidate_hash,
					'proof_required'                => true,
				)
			);

			if ( is_wp_error( $review_approval_consumption ) ) {
				if ( 'de_rtc_unfiltered_html_would_change_content' === $review_approval_consumption->get_error_code() ) {
					return wp_de_rtc_get_unfiltered_html_review_rejection_error(
						$post,
						array(
							'pending_change_count'               => $pending_change_count,
							'rest_route'                         => 'post_retry_save',
							'proposed_post_content_hash'         => $proposed_post_content_hash,
							'candidate_post_content_hash'        => $candidate_hash,
							'proposed_post_content_kses_review'  => $proposed_post_content_kses_review,
							'candidate_post_content_kses_review' => $candidate_post_content_kses_review,
						)
					);
				}

				return $review_approval_consumption;
			}
		}
	} elseif ( null !== $review_approval_proof ) {
		$review_approval_consumption = wp_de_rtc_get_retry_save_review_approval_proof_consumption_result(
			$post,
			array(
				'review_approval_proof'         => $review_approval_proof,
				'client_base_version'           => $client_base_version,
				'accepted_proof_server_version' => $accepted_proof_server_version,
				'server_version'                => $server_version,
				'pending_change_count'          => $pending_change_count,
				'proposed_post_content_hash'    => $proposed_post_content_hash,
				'candidate_post_content_hash'   => $candidate_hash,
				'proof_required'                => false,
			)
		);

		if ( is_wp_error( $review_approval_consumption ) ) {
			return $review_approval_consumption;
		}
	}

	if (
		is_array( $review_approval_consumption ) &&
		! empty( $review_approval_consumption['review_approval_proof_consumed'] ) &&
		! current_user_can( 'unfiltered_html' )
	) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_review_approval_requires_unfiltered_html',
			__( 'Distributed Editing requires an unfiltered HTML reviewer to save approved HTML changes.' ),
			array(
				'detail'                         => 'retry_save_review_approval_requires_unfiltered_html_saver',
				'post_id'                        => (int) $post->ID,
				'rest_route'                     => 'post_retry_save',
				'pending_change_count'           => $pending_change_count,
				'review_approval_proof_accepted' => true,
				'review_approval_proof_consumed' => false,
				'accepted_review_approval_proof_available' => true,
				'accepted_review_approval_proof' => $review_approval_consumption['accepted_review_approval_proof'],
				'reviewed_block_item_count'      => $review_approval_consumption['reviewed_block_item_count'],
				'requires_unfiltered_html'       => true,
				'requires_unfiltered_html_saver' => true,
				'unfiltered_html_allowed'        => false,
				'review_status'                  => 'approved_by_unfiltered_html_reviewer',
				'approval_status'                => 'approved_for_retry_save',
				'review_action'                  => 'request_unfiltered_html_reviewer',
				'approval_action'                => 'retry_save_with_unfiltered_html_saver',
				'review_required_capability'     => 'unfiltered_html',
				'reviewer_capability'            => 'unfiltered_html',
				'review_scope'                   => 'collaborative_post_content',
				'raw_content_included'           => false,
				'can_export_local_updates'       => true,
				'saves_post'                     => false,
				'mutates_post_content'           => false,
				'creates_revision'               => false,
				'claims_saved'                   => false,
			)
		);
	}

	$revision_ids_before_save = wp_de_rtc_get_post_revision_ids( $post->ID );
	$allow_sync_meta_script_during_save = ! current_user_can( 'unfiltered_html' );

	if ( $allow_sync_meta_script_during_save ) {
		wp_de_rtc_enable_sync_meta_script_kses_allowance();
	}

	try {
		$updated_post_id = wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $post->ID,
					'post_content' => $candidate_post_content,
				)
			),
			true
		);
	} finally {
		if ( $allow_sync_meta_script_during_save ) {
			wp_de_rtc_disable_sync_meta_script_kses_allowance();
		}
	}

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
		'review_approval_proof_consumed'      => is_array( $review_approval_consumption ) && ! empty( $review_approval_consumption['review_approval_proof_consumed'] ),
		'reviewed_block_item_count'           => is_array( $review_approval_consumption ) ? $review_approval_consumption['reviewed_block_item_count'] : 0,
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
			'saves_post'                          => ! empty( $args['saves_post'] ) && wp_validate_boolean( $args['saves_post'] ),
			'mutates_post_content'                => ! empty( $args['mutates_post_content'] ) && wp_validate_boolean( $args['mutates_post_content'] ),
			'creates_revision'                    => ! empty( $args['creates_revision'] ) && wp_validate_boolean( $args['creates_revision'] ),
			'claims_saved'                        => ! empty( $args['claims_saved'] ) && wp_validate_boolean( $args['claims_saved'] ),
			'permission_contract'                 => $permission_contract,
		)
	);
}

/**
 * Returns KSES comparison evidence for post content.
 *
 * This helper mirrors the post-content KSES save filter in memory so DE-RTC can
 * reject before persistence when a non-unfiltered user submits content that the
 * save path would alter. It does not save, update posts, or create revisions.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $post_content Post content to inspect.
 * @param array  $args {
 *     Optional KSES review behavior.
 *
 *     @type bool $allow_sync_meta_script Whether to allow server-owned sync-meta SCRIPT markup.
 *                                        Default false.
 * }
 * @return array KSES comparison evidence.
 */
function wp_de_rtc_get_kses_post_content_review_evidence( $post_content, $args = array() ) {
	$post_content           = (string) $post_content;
	$allow_sync_meta_script = ! empty( $args['allow_sync_meta_script'] );

	if ( $allow_sync_meta_script ) {
		wp_de_rtc_enable_sync_meta_script_kses_allowance();
	}

	try {
		$filtered_post_content = wp_unslash( wp_filter_post_kses( wp_slash( $post_content ) ) );
	} finally {
		if ( $allow_sync_meta_script ) {
			wp_de_rtc_disable_sync_meta_script_kses_allowance();
		}
	}

	return array(
		'filter'                  => 'wp_filter_post_kses',
		'filter_context'          => 'content_save_pre',
		'allows_sync_meta_script' => $allow_sync_meta_script,
		'would_change_content'    => $filtered_post_content !== $post_content,
		'content_hash'            => wp_de_rtc_hash_content( $post_content ),
		'filtered_content_hash'   => wp_de_rtc_hash_content( $filtered_post_content ),
	);
}

/**
 * Classifies block-level KSES review items for proposed post content.
 *
 * This helper compares the current stripped post content with proposed stripped
 * content and returns hash-only evidence for changed blocks that intersect
 * unfiltered HTML capability boundaries. It does not return raw block content,
 * save posts, create revisions, register REST behavior, replace post locks, or
 * claim saved state.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post                  Post ID or object.
 * @param string      $proposed_post_content Proposed post content without sync metadata.
 * @param array       $args {
 *     Optional classification arguments.
 *
 *     @type string|null $base_post_content        Base post content without sync metadata. Defaults to the current post content.
 *     @type mixed       $client_base_version      Client base version. Defaults to the current sync-meta version.
 *     @type mixed       $server_version           Server version. Defaults to the current sync-meta version.
 *     @type bool        $user_can_unfiltered_html Whether the editing user can publish unfiltered HTML. Defaults to current user capability.
 *     @type int|null    $author_id                Author of the proposed changes, when known.
 * }
 * @return array|WP_Error Hash-only risky block review classification.
 */
function wp_de_rtc_classify_kses_risky_block_review_items( $post, $proposed_post_content, $args = array() ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.' ),
			array( 'status' => 404 )
		);
	}

	if ( ! is_string( $proposed_post_content ) ) {
		return wp_de_rtc_get_reason_error(
			'de_rtc_malformed_sync_payload',
			__( 'Distributed Editing could not classify KSES review items because proposed post content is missing.' ),
			array(
				'detail'               => 'missing_kses_block_review_proposed_content',
				'post_id'              => (int) $post->ID,
				'raw_content_included' => false,
				'saves_post'           => false,
				'mutates_post_content' => false,
				'creates_revision'     => false,
				'claims_saved'         => false,
			)
		);
	}

	$current = wp_de_rtc_parse_post_content_sync_meta( $post->post_content );

	if ( is_wp_error( $current ) ) {
		return $current;
	}

	$base_post_content = isset( $args['base_post_content'] ) && is_string( $args['base_post_content'] )
		? $args['base_post_content']
		: $current['content'];
	$server_version    = isset( $args['server_version'] ) ? sanitize_text_field( (string) $args['server_version'] ) : wp_de_rtc_get_post_sync_meta_version( $post );
	$client_base_version = isset( $args['client_base_version'] ) ? sanitize_text_field( (string) $args['client_base_version'] ) : $server_version;
	$user_can_unfiltered_html = array_key_exists( 'user_can_unfiltered_html', $args )
		? (bool) $args['user_can_unfiltered_html']
		: current_user_can( 'unfiltered_html' );
	$author_id         = isset( $args['author_id'] ) ? (int) $args['author_id'] : get_current_user_id();

	$review_items = array();

	if ( ! $user_can_unfiltered_html ) {
		$base_records     = wp_de_rtc_get_kses_block_review_records( $base_post_content );
		$proposed_records = wp_de_rtc_get_kses_block_review_records( $proposed_post_content );
		$base_records_by_hash = wp_de_rtc_get_kses_block_review_records_by_hash( $base_records );
		$matched_base_record_keys = array();
		$matched_proposed_record_keys = array();

		foreach ( $proposed_records as $path_key => $proposed_record ) {
			$base_record = isset( $base_records[ $path_key ] ) ? $base_records[ $path_key ] : null;

			if ( is_array( $base_record ) && $base_record['serialized_block'] === $proposed_record['serialized_block'] ) {
				$matched_base_record_keys[ $path_key ]     = true;
				$matched_proposed_record_keys[ $path_key ] = true;
			}
		}

		foreach ( $proposed_records as $path_key => $proposed_record ) {
			if ( isset( $matched_proposed_record_keys[ $path_key ] ) ) {
				continue;
			}

			$matched_base_record_key = wp_de_rtc_find_matching_kses_block_review_record_key(
				$base_records_by_hash,
				wp_de_rtc_hash_content( $proposed_record['serialized_block'] ),
				$matched_base_record_keys
			);

			if ( null !== $matched_base_record_key ) {
				$matched_base_record_keys[ $matched_base_record_key ] = true;
				$matched_proposed_record_keys[ $path_key ]            = true;
			}
		}

		foreach ( $proposed_records as $path_key => $proposed_record ) {
			if ( isset( $matched_proposed_record_keys[ $path_key ] ) ) {
				continue;
			}

			$base_record = isset( $base_records[ $path_key ] ) && ! isset( $matched_base_record_keys[ $path_key ] ) ? $base_records[ $path_key ] : null;

			$proposed_review = wp_de_rtc_get_kses_post_content_review_evidence( $proposed_record['serialized_block'] );
			$base_review     = is_array( $base_record ) ? wp_de_rtc_get_kses_post_content_review_evidence( $base_record['serialized_block'] ) : null;

			if (
				empty( $proposed_review['would_change_content'] ) &&
				( ! is_array( $base_review ) || empty( $base_review['would_change_content'] ) )
			) {
				continue;
			}

			$change_kind = is_array( $base_record ) ? 'modified_block' : 'added_block';
			$review_items[] = wp_de_rtc_create_kses_block_review_item(
				$proposed_record,
				array(
					'change_kind'                 => $change_kind,
					'risk_reason'                 => wp_de_rtc_get_kses_block_review_risk_reason( $change_kind, $base_review, $proposed_review, $proposed_record['serialized_block'] ),
					'author_id'                   => $author_id,
					'base_version'                => $client_base_version,
					'server_version'              => $server_version,
					'base_content_hash'           => is_array( $base_record ) ? wp_de_rtc_hash_content( $base_record['serialized_block'] ) : wp_de_rtc_hash_content( '' ),
					'proposed_content_hash'       => $proposed_review['content_hash'],
					'kses_filtered_content_hash'  => $proposed_review['filtered_content_hash'],
				)
			);

			if ( is_array( $base_record ) ) {
				$matched_base_record_keys[ $path_key ] = true;
			}
		}

		foreach ( $base_records as $path_key => $base_record ) {
			if ( isset( $matched_base_record_keys[ $path_key ] ) ) {
				continue;
			}

			$base_review = wp_de_rtc_get_kses_post_content_review_evidence( $base_record['serialized_block'] );

			if ( empty( $base_review['would_change_content'] ) ) {
				continue;
			}

			$review_items[] = wp_de_rtc_create_kses_block_review_item(
				$base_record,
				array(
					'change_kind'                 => 'deleted_block',
					'risk_reason'                 => 'unfiltered_html_block_deleted',
					'author_id'                   => $author_id,
					'base_version'                => $client_base_version,
					'server_version'              => $server_version,
					'base_content_hash'           => $base_review['content_hash'],
					'proposed_content_hash'       => wp_de_rtc_hash_content( '' ),
					'kses_filtered_content_hash'  => $base_review['filtered_content_hash'],
				)
			);
		}
	}

	$review_required = ! $user_can_unfiltered_html && ! empty( $review_items );

	return array(
		'result'                      => $review_required ? 'block_review_required' : 'no_review_required',
		'reason_code'                 => $review_required ? 'de_rtc_unfiltered_html_would_change_content' : null,
		'post_id'                     => (int) $post->ID,
		'rest_base'                   => wp_de_rtc_get_post_type_rest_base( $post->post_type ),
		'user_can_unfiltered_html'    => $user_can_unfiltered_html,
		'required_capability'         => 'unfiltered_html',
		'content_review_policy'       => 'kses',
		'review_evidence_type'        => 'kses_block_hash_only_change',
		'server_version'              => $server_version,
		'client_base_version'         => $client_base_version,
		'review_items'                => $review_items,
		'review_item_count'           => count( $review_items ),
		'pending_review_item_count'   => $review_required ? count( $review_items ) : 0,
		'pre_publish_review_required' => $review_required,
		'save_action'                 => $review_required ? 'open_pre_publish_review' : 'continue_save',
		'raw_content_included'        => false,
		'exposes_raw_content'         => false,
		'saves_post'                  => false,
		'mutates_post_content'        => false,
		'creates_revision'            => false,
		'claims_saved'                => false,
	);
}

/**
 * Returns block review records keyed by serialized block hash.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array[] $records Block records.
 * @return array[] Block record keys grouped by serialized block hash.
 */
function wp_de_rtc_get_kses_block_review_records_by_hash( $records ) {
	$records_by_hash = array();

	foreach ( $records as $path_key => $record ) {
		$hash = wp_de_rtc_hash_content( $record['serialized_block'] );

		if ( ! isset( $records_by_hash[ $hash ] ) ) {
			$records_by_hash[ $hash ] = array();
		}

		$records_by_hash[ $hash ][] = $path_key;
	}

	return $records_by_hash;
}

/**
 * Finds an unmatched base block record with the same serialized block hash.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array[] $records_by_hash          Block record keys grouped by hash.
 * @param string  $hash                     Serialized proposed block hash.
 * @param bool[]  $matched_base_record_keys Matched base record keys.
 * @return string|null Matching record key, or null when none is available.
 */
function wp_de_rtc_find_matching_kses_block_review_record_key( $records_by_hash, $hash, $matched_base_record_keys ) {
	if ( empty( $records_by_hash[ $hash ] ) || ! is_array( $records_by_hash[ $hash ] ) ) {
		return null;
	}

	foreach ( $records_by_hash[ $hash ] as $path_key ) {
		if ( empty( $matched_base_record_keys[ $path_key ] ) ) {
			return $path_key;
		}
	}

	return null;
}

/**
 * Returns flattened block records for KSES block review classification.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $post_content Post content.
 * @return array[] Block records keyed by path.
 */
function wp_de_rtc_get_kses_block_review_records( $post_content ) {
	$records = array();

	wp_de_rtc_collect_kses_block_review_records(
		parse_blocks( (string) $post_content ),
		array(),
		$records
	);

	return $records;
}

/**
 * Collects flattened block records for KSES block review classification.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array $blocks  Parsed block list.
 * @param int[] $path    Current block path.
 * @param array $records Collected block records.
 */
function wp_de_rtc_collect_kses_block_review_records( $blocks, $path, &$records ) {
	foreach ( (array) $blocks as $index => $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		$block_path       = array_merge( $path, array( (int) $index ) );
		$block_name       = ! empty( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : 'core/freeform';
		$serialized_block = serialize_block( $block );
		$path_key         = implode( '.', $block_path );

		$records[ $path_key ] = array(
			'path_key'         => $path_key,
			'block_path'       => $block_path,
			'block_name'       => $block_name,
			'block_label'      => wp_de_rtc_get_kses_block_review_label( $block_name ),
			'serialized_block' => $serialized_block,
		);

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			wp_de_rtc_collect_kses_block_review_records( $block['innerBlocks'], $block_path, $records );
		}
	}
}

/**
 * Creates a hash-only risky block review item.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array $block_record Block record.
 * @param array $args         Review item arguments.
 * @return array Hash-only review item.
 */
function wp_de_rtc_create_kses_block_review_item( $block_record, $args ) {
	$id_source = implode( '/', $block_record['block_path'] ) . '|' . $block_record['block_name'] . '|' . $args['base_content_hash'] . '|' . $args['proposed_content_hash'];

	return array(
		'id'                         => 'kses-review-' . substr( hash( 'sha256', $id_source ), 0, 16 ),
		'block_client_id'            => 'server-block-' . str_replace( '.', '-', $block_record['path_key'] ),
		'block_name'                 => $block_record['block_name'],
		'block_label'                => $block_record['block_label'],
		'block_path'                 => $block_record['block_path'],
		'change_kind'                => $args['change_kind'],
		'risk_reason'                => $args['risk_reason'],
		'author_id'                  => $args['author_id'],
		'base_version'               => null === $args['base_version'] ? null : (string) $args['base_version'],
		'server_version'             => null === $args['server_version'] ? null : (string) $args['server_version'],
		'base_content_hash'          => $args['base_content_hash'],
		'proposed_content_hash'      => $args['proposed_content_hash'],
		'kses_filtered_content_hash' => $args['kses_filtered_content_hash'],
		'review_status'              => 'pending_review',
		'review_evidence_type'       => 'kses_block_hash_only_change',
		'content_review_policy'      => 'kses',
		'raw_content_included'       => false,
		'exposes_raw_content'        => false,
	);
}

/**
 * Returns a readable block label for KSES review output.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string $block_name Block name.
 * @return string Block label.
 */
function wp_de_rtc_get_kses_block_review_label( $block_name ) {
	if ( 'core/freeform' === $block_name ) {
		return 'Classic';
	}

	if ( 'core/html' === $block_name ) {
		return 'HTML';
	}

	if ( 0 === strpos( $block_name, 'core/' ) ) {
		$core_label = substr( $block_name, 5 );
		$core_label = str_replace( array( '-', '_' ), ' ', $core_label );

		return ucwords( $core_label );
	}

	return $block_name;
}

/**
 * Returns the risk reason for a block-level KSES review item.
 *
 * @since 7.1.0
 * @access private
 *
 * @param string     $change_kind     Change kind.
 * @param array|null $base_review     Base block KSES evidence.
 * @param array      $proposed_review Proposed block KSES evidence.
 * @param string     $block_content   Serialized proposed block content.
 * @return string Risk reason.
 */
function wp_de_rtc_get_kses_block_review_risk_reason( $change_kind, $base_review, $proposed_review, $block_content ) {
	if ( 'deleted_block' === $change_kind ) {
		return 'unfiltered_html_block_deleted';
	}

	if ( ! empty( $proposed_review['would_change_content'] ) ) {
		if ( false !== stripos( $block_content, '<script' ) ) {
			return 'kses_would_remove_script';
		}

		if ( false !== stripos( $block_content, 'javascript:' ) || preg_match( '/\son[a-z]+\s*=/i', $block_content ) ) {
			return 'kses_would_alter_attributes';
		}

		return 'kses_would_modify_html';
	}

	if ( is_array( $base_review ) && ! empty( $base_review['would_change_content'] ) ) {
		return 'unfiltered_html_block_modified';
	}

	return 'kses_would_modify_html';
}

/**
 * Adds the scoped KSES allowance for server-owned sync metadata SCRIPT markup.
 *
 * @since 7.1.0
 * @access private
 */
function wp_de_rtc_enable_sync_meta_script_kses_allowance() {
	add_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance', 10, 2 );
}

/**
 * Removes the scoped KSES allowance for server-owned sync metadata SCRIPT markup.
 *
 * @since 7.1.0
 * @access private
 */
function wp_de_rtc_disable_sync_meta_script_kses_allowance() {
	remove_filter( 'wp_kses_allowed_html', 'wp_de_rtc_filter_sync_meta_script_kses_allowance', 10 );
}

/**
 * Allows the DE-RTC sync-meta SCRIPT element through post KSES in guarded saves.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array[]|string $tags    Allowed HTML tags.
 * @param string         $context KSES context.
 * @return array[]|string Filtered allowed HTML tags.
 */
function wp_de_rtc_filter_sync_meta_script_kses_allowance( $tags, $context ) {
	if ( 'post' !== $context || ! is_array( $tags ) ) {
		return $tags;
	}

	$tags['script'] = array(
		'type'                  => true,
		'data-sync-meta-format' => true,
	);

	return $tags;
}

/**
 * Returns the escalation reason for an unfiltered HTML review rejection.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array|null $proposed_post_content_kses_review  Proposed-content KSES evidence.
 * @param array|null $candidate_post_content_kses_review Retry-save candidate KSES evidence.
 * @return string Escalation reason.
 */
function wp_de_rtc_get_unfiltered_html_review_escalation_reason(
	$proposed_post_content_kses_review,
	$candidate_post_content_kses_review
) {
	$proposed_would_change  = is_array( $proposed_post_content_kses_review ) && ! empty( $proposed_post_content_kses_review['would_change_content'] );
	$candidate_would_change = is_array( $candidate_post_content_kses_review ) && ! empty( $candidate_post_content_kses_review['would_change_content'] );

	if ( $proposed_would_change && $candidate_would_change ) {
		return 'proposed_content_and_retry_save_candidate_would_change_by_kses';
	}

	if ( $proposed_would_change ) {
		return 'proposed_content_would_change_by_kses';
	}

	if ( $candidate_would_change ) {
		return 'retry_save_candidate_would_change_by_kses';
	}

	return 'content_capability_review_required';
}

/**
 * Returns the canonical unfiltered HTML review rejection error.
 *
 * This helper only defines the rejection vocabulary for collaborative content
 * authority. It does not inspect content, save posts, create revisions,
 * register REST behavior, replace post locks, or wire normal save paths.
 *
 * @since 7.1.0
 *
 * @param int|WP_Post $post Post ID or object.
 * @param array       $args {
 *     Optional rejection data.
 *
 *     @type mixed  $pending_change_count               Pending local change count. Default 1.
 *     @type string $rest_route                         Response route label. Default 'post_content_capability_review'.
 *     @type string $proposed_post_content_hash         Proposed stripped post-content hash.
 *     @type string $candidate_post_content_hash        Retry-save candidate post-content hash.
 *     @type array  $proposed_post_content_kses_review  Proposed-content KSES comparison evidence.
 *     @type array  $candidate_post_content_kses_review Retry-save candidate KSES comparison evidence.
 * }
 * @return WP_Error Unfiltered HTML review rejection error with normalized DE-RTC data.
 */
function wp_de_rtc_get_unfiltered_html_review_rejection_error( $post, $args = array() ) {
	$post                 = get_post( $post );
	$post_id              = $post ? (int) $post->ID : 0;
	$pending_change_count = array_key_exists( 'pending_change_count', $args ) ? max( 0, (int) $args['pending_change_count'] ) : 1;
	$rest_route           = isset( $args['rest_route'] ) ? sanitize_key( $args['rest_route'] ) : 'post_content_capability_review';
	$permission_contract  = wp_de_rtc_get_rest_recovery_permission_contract( $post );

	$proposed_post_content_kses_review = null;
	if ( isset( $args['proposed_post_content_kses_review'] ) && is_array( $args['proposed_post_content_kses_review'] ) ) {
		$proposed_post_content_kses_review = $args['proposed_post_content_kses_review'];
	}

	$candidate_post_content_kses_review = null;
	if ( isset( $args['candidate_post_content_kses_review'] ) && is_array( $args['candidate_post_content_kses_review'] ) ) {
		$candidate_post_content_kses_review = $args['candidate_post_content_kses_review'];
	}

	$proposed_would_change = null;
	if ( is_array( $proposed_post_content_kses_review ) && array_key_exists( 'would_change_content', $proposed_post_content_kses_review ) ) {
		$proposed_would_change = (bool) $proposed_post_content_kses_review['would_change_content'];
	}

	$candidate_would_change = null;
	if ( is_array( $candidate_post_content_kses_review ) && array_key_exists( 'would_change_content', $candidate_post_content_kses_review ) ) {
		$candidate_would_change = (bool) $candidate_post_content_kses_review['would_change_content'];
	}

	$content_would_change = true === $proposed_would_change || true === $candidate_would_change;
	$escalation_reason    = wp_de_rtc_get_unfiltered_html_review_escalation_reason( $proposed_post_content_kses_review, $candidate_post_content_kses_review );

	$proposed_hash = null;
	if ( isset( $args['proposed_post_content_hash'] ) ) {
		$proposed_hash = sanitize_text_field( (string) $args['proposed_post_content_hash'] );
	} elseif ( is_array( $proposed_post_content_kses_review ) && isset( $proposed_post_content_kses_review['content_hash'] ) ) {
		$proposed_hash = sanitize_text_field( (string) $proposed_post_content_kses_review['content_hash'] );
	}

	$filtered_proposed_hash = null;
	if ( is_array( $proposed_post_content_kses_review ) && isset( $proposed_post_content_kses_review['filtered_content_hash'] ) ) {
		$filtered_proposed_hash = sanitize_text_field( (string) $proposed_post_content_kses_review['filtered_content_hash'] );
	}

	$candidate_hash = null;
	if ( isset( $args['candidate_post_content_hash'] ) ) {
		$candidate_hash = sanitize_text_field( (string) $args['candidate_post_content_hash'] );
	} elseif ( is_array( $candidate_post_content_kses_review ) && isset( $candidate_post_content_kses_review['content_hash'] ) ) {
		$candidate_hash = sanitize_text_field( (string) $candidate_post_content_kses_review['content_hash'] );
	}

	$filtered_candidate_hash = null;
	if ( is_array( $candidate_post_content_kses_review ) && isset( $candidate_post_content_kses_review['filtered_content_hash'] ) ) {
		$filtered_candidate_hash = sanitize_text_field( (string) $candidate_post_content_kses_review['filtered_content_hash'] );
	}

	$review_contract = array(
		'status'                                 => 'requires_reviewer_escalation',
		'type'                                   => 'unfiltered_html_content_capability_review',
		'review_action'                          => 'request_unfiltered_html_reviewer',
		'review_required_capability'             => 'unfiltered_html',
		'review_scope'                           => 'collaborative_post_content',
		'reviewer_capability'                    => 'unfiltered_html',
		'escalation_required'                    => true,
		'escalation_reason'                      => $escalation_reason,
		'content_filter'                         => 'wp_filter_post_kses',
		'content_filter_context'                 => 'content_save_pre',
		'content_would_change_by_kses'           => $content_would_change,
		'proposed_content_would_change_by_kses'  => $proposed_would_change,
		'candidate_content_would_change_by_kses' => $candidate_would_change,
		'proposed_content_hash'                  => $proposed_hash,
		'kses_filtered_proposed_content_hash'    => $filtered_proposed_hash,
		'candidate_content_hash'                 => $candidate_hash,
		'kses_filtered_candidate_content_hash'   => $filtered_candidate_hash,
		'raw_content_included'                   => false,
	);

	return wp_de_rtc_get_reason_error(
		'de_rtc_unfiltered_html_would_change_content',
		__( 'Distributed Editing rejected the update because collaborative content changes require unfiltered HTML review.' ),
		array(
			'detail'                              => 'collaborative_unfiltered_html_review_required',
			'post_id'                             => $post_id,
			'rest_route'                          => $rest_route,
			'pending_change_count'                => $pending_change_count,
			'requires_edit_post'                  => true,
			'requires_unfiltered_html'            => true,
			'unfiltered_html_allowed'             => current_user_can( 'unfiltered_html' ),
			'authorship_review_required'          => true,
			'content_capability_review_required'  => true,
			'review_status'                       => 'requires_reviewer_escalation',
			'reviewer_capability'                 => 'unfiltered_html',
			'escalation_required'                 => true,
			'escalation_reason'                   => $escalation_reason,
			'requires_reviewer_escalation'        => true,
			'review_action'                       => 'request_unfiltered_html_reviewer',
			'review_required_capability'          => 'unfiltered_html',
			'review_scope'                        => 'collaborative_post_content',
			'content_filter'                      => 'wp_filter_post_kses',
			'content_filter_context'              => 'content_save_pre',
			'content_would_change_by_kses'        => $content_would_change,
			'proposed_content_hash'               => $proposed_hash,
			'kses_filtered_proposed_content_hash' => $filtered_proposed_hash,
			'candidate_content_hash'              => $candidate_hash,
			'kses_filtered_candidate_content_hash' => $filtered_candidate_hash,
			'raw_content_included'                => false,
			'review_contract'                     => $review_contract,
			'recovery_actions'                    => array(
				'export_local_updates',
				'request_unfiltered_html_reviewer',
				'refetch_server_state',
			),
			'requires_manual_conflict_resolution' => true,
			'can_export_local_updates'            => $pending_change_count > 0,
			'saves_post'                          => false,
			'mutates_post_content'                => false,
			'creates_revision'                    => false,
			'claims_saved'                        => false,
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

	if ( ! wp_de_rtc_is_enabled() ) {
		return false;
	}

	/**
	 * Filters whether Distributed Editing is enabled for a post.
	 *
	 * @since 7.1.0
	 *
	 * @param bool    $enabled Whether Distributed Editing is enabled for the post.
	 * @param WP_Post $post    Post object.
	 */
	return (bool) apply_filters( 'wp_de_rtc_enabled_for_post', true, $post );
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
			'post_id'                            => 0,
			'requires_edit_post'                 => true,
			'feature_enabled'                    => false,
			'unfiltered_html_review_required'    => true,
			'unfiltered_html_allowed'            => current_user_can( 'unfiltered_html' ),
			'authorship_review_required'         => true,
			'content_capability_review_required' => true,
			'unfiltered_html_rejection_code'     => 'de_rtc_unfiltered_html_would_change_content',
			'unfiltered_html_review_action'      => 'request_unfiltered_html_reviewer',
			'unfiltered_html_review_capability'  => 'unfiltered_html',
			'unfiltered_html_review_scope'       => 'collaborative_post_content',
		);
	}

	return array(
		'post_id'                            => (int) $post->ID,
		'post_type'                          => $post->post_type,
		'post_type_rest_base'                => wp_de_rtc_get_post_type_rest_base( $post->post_type ),
		'requires_edit_post'                 => true,
		'feature_enabled'                    => wp_de_rtc_is_enabled_for_post( $post ),
		'unfiltered_html_review_required'    => true,
		'unfiltered_html_allowed'            => current_user_can( 'unfiltered_html' ),
		'authorship_review_required'         => true,
		'content_capability_review_required' => true,
		'unfiltered_html_rejection_code'     => 'de_rtc_unfiltered_html_would_change_content',
		'unfiltered_html_review_action'      => 'request_unfiltered_html_reviewer',
		'unfiltered_html_review_capability'  => 'unfiltered_html',
		'unfiltered_html_review_scope'       => 'collaborative_post_content',
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
 * Returns normalized request hash evidence.
 *
 * @since 7.1.0
 * @access private
 *
 * @param array  $args Request arguments.
 * @param string $key  Hash argument key.
 * @return string|null Lowercase SHA-256 hash candidate, or null when absent.
 */
function wp_de_rtc_get_request_hash_evidence( $args, $key ) {
	if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] ) {
		return null;
	}

	return strtolower( sanitize_text_field( (string) $args[ $key ] ) );
}

/**
 * Returns whether a value is lowercase SHA-256 hash evidence.
 *
 * @since 7.1.0
 * @access private
 *
 * @param mixed $hash Hash candidate.
 * @return bool Whether the value is a SHA-256 hash.
 */
function wp_de_rtc_is_sha256_hash( $hash ) {
	return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash );
}

/**
 * Finds raw post-content parameter paths in a request payload.
 *
 * @since 7.1.0
 * @access private
 *
 * @param mixed  $payload Request payload.
 * @param string $prefix  Current parameter path.
 * @return string[] Raw post-content parameter paths.
 */
function wp_de_rtc_find_raw_post_content_param_paths( $payload, $prefix = '' ) {
	if ( is_object( $payload ) ) {
		$payload = get_object_vars( $payload );
	}

	if ( ! is_array( $payload ) ) {
		return array();
	}

	$raw_content_keys = array(
		'content',
		'post_content',
		'proposed_post_content',
		'candidate_post_content',
		'saved_post_content',
		'raw_content',
		'raw_post_content',
	);
	$paths            = array();

	foreach ( $payload as $key => $value ) {
		$key_string = is_string( $key ) ? $key : (string) $key;
		$path       = '' === $prefix ? $key_string : $prefix . '.' . $key_string;

		if ( in_array( $key_string, $raw_content_keys, true ) ) {
			$paths[] = $path;
		}

		$paths = array_merge( $paths, wp_de_rtc_find_raw_post_content_param_paths( $value, $path ) );
	}

	return $paths;
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

		if ( ! preg_match( $pattern, $content, $matches ) ) {
			return false;
		}

		return array(
			'match'  => $matches[0],
			'script' => $matches[1],
			'json'   => $matches[2],
		);
	}

	$trimmed_content = rtrim( $content );
	$script_start    = strripos( $trimmed_content, '<script' );

	if ( false === $script_start ) {
		return false;
	}

	while ( $script_start > 0 && preg_match( '/[ \t\r\n]/', $content[ $script_start - 1 ] ) ) {
		--$script_start;
	}

	$trailer = substr( $content, $script_start );
	$pattern = '~\A[ \t\r\n]*' . $script_pattern . '[ \t\r\n]*\z~is';

	if ( ! preg_match( $pattern, $trailer, $matches ) ) {
		return false;
	}

	return array(
		'match'  => $trailer,
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
