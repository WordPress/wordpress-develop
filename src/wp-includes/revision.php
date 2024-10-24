<?php
/**
 * Post revision functions.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 */

/**
 * Determines which fields of posts are to be saved in revisions.
 *
 * @since 2.6.0
 * @since 4.5.0 A `WP_Post` object can now be passed to the `$post` parameter.
 * @since 4.5.0 The optional `$autosave` parameter was deprecated and renamed to `$deprecated`.
 * @access private
 *
 * @param array|WP_Post $post       Optional. A post array or a WP_Post object being processed
 *                                  for insertion as a post revision. Default empty array.
 * @param bool          $deprecated Not used.
 * @return string[] Array of fields that can be versioned.
 */
function _wp_post_revision_fields( $post = array(), $deprecated = false ) {
	static $fields = null;

	if ( ! is_array( $post ) ) {
		$post = get_post( $post, ARRAY_A );
	}

	if ( is_null( $fields ) ) {
		// Allow these to be versioned.
		$fields = array(
			'post_title'   => __( 'Title' ),
			'post_content' => __( 'Content' ),
			'post_excerpt' => __( 'Excerpt' ),
			'post_meta'    => __( 'Post Meta' ),
		);
	}

	/**
	 * Filters the list of fields saved in post revisions.
	 *
	 * Included by default: 'post_title', 'post_content' and 'post_excerpt'.
	 *
	 * Disallowed fields: 'ID', 'post_name', 'post_parent', 'post_date',
	 * 'post_date_gmt', 'post_status', 'post_type', 'comment_count',
	 * and 'post_author'.
	 *
	 * @since 2.6.0
	 * @since 4.5.0 The `$post` parameter was added.
	 *
	 * @param string[] $fields List of fields to revision. Contains 'post_title',
	 *                         'post_content', and 'post_excerpt' by default.
	 * @param array    $post   A post array being processed for insertion as a post revision.
	 */
	$fields = apply_filters( '_wp_post_revision_fields', $fields, $post );

	// WP uses these internally either in versioning or elsewhere - they cannot be versioned.
	foreach ( array( 'ID', 'post_name', 'post_parent', 'post_date', 'post_date_gmt', 'post_status', 'post_type', 'comment_count', 'post_author' ) as $protect ) {
		unset( $fields[ $protect ] );
	}

	return $fields;
}

/**
 * Returns a post array ready to be inserted into the posts table as a post revision.
 *
 * @since 4.5.0
 * @access private
 *
 * @param array|WP_Post $post     Optional. A post array or a WP_Post object to be processed
 *                                for insertion as a post revision. Default empty array.
 * @param bool          $autosave Optional. Is the revision an autosave? Default false.
 * @return array Post array ready to be inserted as a post revision.
 */
function _wp_post_revision_data( $post = array(), $autosave = false ) {
	if ( ! is_array( $post ) ) {
		$post = get_post( $post, ARRAY_A );
	}

	$fields = _wp_post_revision_fields( $post );

	$revision_data = array();

	foreach ( array_intersect( array_keys( $post ), array_keys( $fields ) ) as $field ) {
		$revision_data[ $field ] = $post[ $field ];
	}

	$revision_data['post_parent']   = $post['ID'];
	$revision_data['post_status']   = 'inherit';
	$revision_data['post_type']     = 'revision';
	$revision_data['post_name']     = $autosave ? "$post[ID]-autosave-v1" : "$post[ID]-revision-v1"; // "1" is the revisioning system version.
	$revision_data['post_date']     = isset( $post['post_modified'] ) ? $post['post_modified'] : '';
	$revision_data['post_date_gmt'] = isset( $post['post_modified_gmt'] ) ? $post['post_modified_gmt'] : '';

	return $revision_data;
}

/**
 * Saves revisions for a post after all changes have been made.
 *
 * @since 6.4.0
 *
 * @param int     $post_id The post id that was inserted.
 * @param WP_Post $post    The post object that was inserted.
 * @param bool    $update  Whether this insert is updating an existing post.
 */
function wp_save_post_revision_on_insert( $post_id, $post, $update ) {
	if ( ! $update ) {
		return;
	}

	if ( ! has_action( 'post_updated', 'wp_save_post_revision' ) ) {
		return;
	}

	wp_save_post_revision( $post_id );
}

/**
 * Creates a revision for the current version of a post.
 *
 * Typically used immediately after a post update, as every update is a revision,
 * and the most recent revision always matches the current post.
 *
 * @since 2.6.0
 *
 * @param int $post_id The ID of the post to save as a revision.
 * @return int|WP_Error|void Void or 0 if error, new revision ID, if success.
 */
function wp_save_post_revision( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Prevent saving post revisions if revisions should be saved on wp_after_insert_post.
	if ( doing_action( 'post_updated' ) && has_action( 'wp_after_insert_post', 'wp_save_post_revision_on_insert' ) ) {
		return;
	}

	$post = get_post( $post_id );

	if ( ! $post ) {
		return;
	}

	if ( ! post_type_supports( $post->post_type, 'revisions' ) ) {
		return;
	}

	if ( 'auto-draft' === $post->post_status ) {
		return;
	}

	if ( ! wp_revisions_enabled( $post ) ) {
		return;
	}

	/*
	 * Compare the proposed update with the last stored revision verifying that
	 * they are different, unless a plugin tells us to always save regardless.
	 * If no previous revisions, save one.
	 */
	$revisions = wp_get_post_revisions( $post_id );
	if ( $revisions ) {
		// Grab the latest revision, but not an autosave.
		foreach ( $revisions as $revision ) {
			if ( str_contains( $revision->post_name, "{$revision->post_parent}-revision" ) ) {
				$latest_revision = $revision;
				break;
			}
		}

		/**
		 * Filters whether the post has changed since the latest revision.
		 *
		 * By default a revision is saved only if one of the revisioned fields has changed.
		 * This filter can override that so a revision is saved even if nothing has changed.
		 *
		 * @since 3.6.0
		 *
		 * @param bool    $check_for_changes Whether to check for changes before saving a new revision.
		 *                                   Default true.
		 * @param WP_Post $latest_revision   The latest revision post object.
		 * @param WP_Post $post              The post object.
		 */
		if ( isset( $latest_revision ) && apply_filters( 'wp_save_post_revision_check_for_changes', true, $latest_revision, $post ) ) {
			$post_has_changed = false;

			foreach ( array_keys( _wp_post_revision_fields( $post ) ) as $field ) {
				if ( normalize_whitespace( $post->$field ) !== normalize_whitespace( $latest_revision->$field ) ) {
					$post_has_changed = true;
					break;
				}
			}

			/**
			 * Filters whether a post has changed.
			 *
			 * By default a revision is saved only if one of the revisioned fields has changed.
			 * This filter allows for additional checks to determine if there were changes.
			 *
			 * @since 4.1.0
			 *
			 * @param bool    $post_has_changed Whether the post has changed.
			 * @param WP_Post $latest_revision  The latest revision post object.
			 * @param WP_Post $post             The post object.
			 */
			$post_has_changed = (bool) apply_filters( 'wp_save_post_revision_post_has_changed', $post_has_changed, $latest_revision, $post );

			// Don't save revision if post unchanged.
			if ( ! $post_has_changed ) {
				return;
			}
		}
	}

	$return = _wp_put_post_revision( $post );

	/*
	 * If a limit for the number of revisions to keep has been set,
	 * delete the oldest ones.
	 */
	$revisions_to_keep = wp_revisions_to_keep( $post );

	if ( $revisions_to_keep < 0 ) {
		return $return;
	}

	$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'ASC' ) );

	/**
	 * Filters the revisions to be considered for deletion.
	 *
	 * @since 6.2.0
	 *
	 * @param WP_Post[] $revisions Array of revisions, or an empty array if none.
	 * @param int       $post_id   The ID of the post to save as a revision.
	 */
	$revisions = apply_filters(
		'wp_save_post_revision_revisions_before_deletion',
		$revisions,
		$post_id
	);

	$delete = count( $revisions ) - $revisions_to_keep;

	if ( $delete < 1 ) {
		return $return;
	}

	$revisions = array_slice( $revisions, 0, $delete );

	for ( $i = 0; isset( $revisions[ $i ] ); $i++ ) {
		if ( str_contains( $revisions[ $i ]->post_name, 'autosave' ) ) {
			continue;
		}

		wp_delete_post_revision( $revisions[ $i ]->ID );
	}

	return $return;
}

/**
 * Retrieves the autosaved data of the specified post.
 *
 * Returns a post object with the information that was autosaved for the specified post.
 * If the optional $user_id is passed, returns the autosave for that user, otherwise
 * returns the latest autosave.
 *
 * @since 2.6.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $post_id The post ID.
 * @param int $user_id Optional. The post author ID. Default 0.
 * @return WP_Post|false The autosaved data or false on failure or when no autosave exists.
 */
function wp_get_post_autosave( $post_id, $user_id = 0 ) {
	global $wpdb;

	$autosave_name = $post_id . '-autosave-v1';
	$user_id_query = ( 0 !== $user_id ) ? "AND post_author = $user_id" : null;

	// Construct the autosave query.
	$autosave_query = "
		SELECT *
		FROM $wpdb->posts
		WHERE post_parent = %d
		AND post_type = 'revision'
		AND post_status = 'inherit'
		AND post_name   = %s " . $user_id_query . '
		ORDER BY post_date DESC
		LIMIT 1';

	$autosave = $wpdb->get_results(
		$wpdb->prepare(
			$autosave_query,
			$post_id,
			$autosave_name
		)
	);

	if ( ! $autosave ) {
		return false;
	}

	return get_post( $autosave[0] );
}

/**
 * Determines if the specified post is a revision.
 *
 * @since 2.6.0
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return int|false ID of revision's parent on success, false if not a revision.
 */
function wp_is_post_revision( $post ) {
	$post = wp_get_post_revision( $post );

	if ( ! $post ) {
		return false;
	}

	return (int) $post->post_parent;
}

/**
 * Determines if the specified post is an autosave.
 *
 * @since 2.6.0
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return int|false ID of autosave's parent on success, false if not a revision.
 */
function wp_is_post_autosave( $post ) {
	$post = wp_get_post_revision( $post );

	if ( ! $post ) {
		return false;
	}

	if ( str_contains( $post->post_name, "{$post->post_parent}-autosave" ) ) {
		return (int) $post->post_parent;
	}

	return false;
}

/**
 * Inserts post data into the posts table as a post revision.
 *
 * @since 2.6.0
 * @access private
 *
 * @param int|WP_Post|array|null $post     Post ID, post object OR post array.
 * @param bool                   $autosave Optional. Whether the revision is an autosave or not.
 *                                         Default false.
 * @return int|WP_Error WP_Error or 0 if error, new revision ID if success.
 */
function _wp_put_post_revision( $post = null, $autosave = false ) {
	if ( is_object( $post ) ) {
		$post = get_object_vars( $post );
	} elseif ( ! is_array( $post ) ) {
		$post = get_post( $post, ARRAY_A );
	}

	if ( ! $post || empty( $post['ID'] ) ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
	}

	if ( isset( $post['post_type'] ) && 'revision' === $post['post_type'] ) {
		return new WP_Error( 'post_type', __( 'Cannot create a revision of a revision' ) );
	}

	$post = _wp_post_revision_data( $post, $autosave );
	$post = wp_slash( $post ); // Since data is from DB.

	$revision_id = wp_insert_post( $post, true );
	if ( is_wp_error( $revision_id ) ) {
		return $revision_id;
	}

	if ( $revision_id ) {
		/**
		 * Fires once a revision has been saved.
		 *
		 * @since 2.6.0
		 * @since 6.4.0 The post_id parameter was added.
		 *
		 * @param int $revision_id Post revision ID.
		 * @param int $post_id     Post ID.
		 */
		do_action( '_wp_put_post_revision', $revision_id, $post['post_parent'] );
	}

	return $revision_id;
}


/**
 * Save the revisioned meta fields.
 *
 * @since 6.4.0
 *
 * @param int $revision_id The ID of the revision to save the meta to.
 * @param int $post_id     The ID of the post the revision is associated with.
 */
function wp_save_revisioned_meta_fields( $revision_id, $post_id ) {
	$post_type = get_post_type( $post_id );
	if ( ! $post_type ) {
		return;
	}

	foreach ( wp_post_revision_meta_keys( $post_type ) as $meta_key ) {
		if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
			_wp_copy_post_meta( $post_id, $revision_id, $meta_key );
		}
	}
}

/**
 * Gets a post revision.
 *
 * @since 2.6.0
 *
 * @param int|WP_Post $post   Post ID or post object.
 * @param string      $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                            correspond to a WP_Post object, an associative array, or a numeric array,
 *                            respectively. Default OBJECT.
 * @param string      $filter Optional sanitization filter. See sanitize_post(). Default 'raw'.
 * @return WP_Post|array|null WP_Post (or array) on success, or null on failure.
 */
function wp_get_post_revision( &$post, $output = OBJECT, $filter = 'raw' ) {
	$revision = get_post( $post, OBJECT, $filter );

	if ( ! $revision ) {
		return $revision;
	}

	if ( 'revision' !== $revision->post_type ) {
		return null;
	}

	if ( OBJECT === $output ) {
		return $revision;
	} elseif ( ARRAY_A === $output ) {
		$_revision = get_object_vars( $revision );
		return $_revision;
	} elseif ( ARRAY_N === $output ) {
		$_revision = array_values( get_object_vars( $revision ) );
		return $_revision;
	}

	return $revision;
}

/**
 * Restores a post to the specified revision.
 *
 * Can restore a past revision using all fields of the post revision, or only selected fields.
 *
 * @since 2.6.0
 *
 * @param int|WP_Post $revision Revision ID or revision object.
 * @param array       $fields   Optional. What fields to restore from. Defaults to all.
 * @return int|false|null Null if error, false if no fields to restore, (int) post ID if success.
 */
function wp_restore_post_revision( $revision, $fields = null ) {
	$revision = wp_get_post_revision( $revision, ARRAY_A );

	if ( ! $revision ) {
		return $revision;
	}

	if ( ! is_array( $fields ) ) {
		$fields = array_keys( _wp_post_revision_fields( $revision ) );
	}

	$update = array();
	foreach ( array_intersect( array_keys( $revision ), $fields ) as $field ) {
		$update[ $field ] = $revision[ $field ];
	}

	if ( ! $update ) {
		return false;
	}

	$update['ID'] = $revision['post_parent'];

	$update = wp_slash( $update ); // Since data is from DB.

	$post_id = wp_update_post( $update );

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Update last edit user.
	update_post_meta( $post_id, '_edit_last', get_current_user_id() );

	/**
	 * Fires after a post revision has been restored.
	 *
	 * @since 2.6.0
	 *
	 * @param int $post_id     Post ID.
	 * @param int $revision_id Post revision ID.
	 */
	do_action( 'wp_restore_post_revision', $post_id, $revision['ID'] );

	return $post_id;
}

/**
 * Restore the revisioned meta values for a post.
 *
 * @since 6.4.0
 *
 * @param int $post_id     The ID of the post to restore the meta to.
 * @param int $revision_id The ID of the revision to restore the meta from.
 */
function wp_restore_post_revision_meta( $post_id, $revision_id ) {
	$post_type = get_post_type( $post_id );
	if ( ! $post_type ) {
		return;
	}

	// Restore revisioned meta fields.
	foreach ( wp_post_revision_meta_keys( $post_type ) as $meta_key ) {

		// Clear any existing meta.
		delete_post_meta( $post_id, $meta_key );

		_wp_copy_post_meta( $revision_id, $post_id, $meta_key );
	}
}

/**
 * Copy post meta for the given key from one post to another.
 *
 * @since 6.4.0
 *
 * @param int    $source_post_id Post ID to copy meta value(s) from.
 * @param int    $target_post_id Post ID to copy meta value(s) to.
 * @param string $meta_key       Meta key to copy.
 */
function _wp_copy_post_meta( $source_post_id, $target_post_id, $meta_key ) {

	foreach ( get_post_meta( $source_post_id, $meta_key ) as $meta_value ) {
		/**
		 * We use add_metadata() function vs add_post_meta() here
		 * to allow for a revision post target OR regular post.
		 */
		add_metadata( 'post', $target_post_id, $meta_key, wp_slash( $meta_value ) );
	}
}

/**
 * Determine which post meta fields should be revisioned.
 *
 * @since 6.4.0
 *
 * @param string $post_type The post type being revisioned.
 * @return array An array of meta keys to be revisioned.
 */
function wp_post_revision_meta_keys( $post_type ) {
	$registered_meta = array_merge(
		get_registered_meta_keys( 'post' ),
		get_registered_meta_keys( 'post', $post_type )
	);

	$wp_revisioned_meta_keys = array();

	foreach ( $registered_meta as $name => $args ) {
		if ( $args['revisions_enabled'] ) {
			$wp_revisioned_meta_keys[ $name ] = true;
		}
	}

	$wp_revisioned_meta_keys = array_keys( $wp_revisioned_meta_keys );

	/**
	 * Filter the list of post meta keys to be revisioned.
	 *
	 * @since 6.4.0
	 *
	 * @param array  $keys      An array of meta fields to be revisioned.
	 * @param string $post_type The post type being revisioned.
	 */
	return apply_filters( 'wp_post_revision_meta_keys', $wp_revisioned_meta_keys, $post_type );
}

/**
 * Check whether revisioned post meta fields have changed.
 *
 * @since 6.4.0
 *
 * @param bool    $post_has_changed Whether the post has changed.
 * @param WP_Post $last_revision    The last revision post object.
 * @param WP_Post $post             The post object.
 * @return bool Whether the post has changed.
 */
function wp_check_revisioned_meta_fields_have_changed( $post_has_changed, WP_Post $last_revision, WP_Post $post ) {
	foreach ( wp_post_revision_meta_keys( $post->post_type ) as $meta_key ) {
		if ( get_post_meta( $post->ID, $meta_key ) !== get_post_meta( $last_revision->ID, $meta_key ) ) {
			$post_has_changed = true;
			break;
		}
	}
	return $post_has_changed;
}

/**
 * Deletes a revision.
 *
 * Deletes the row from the posts table corresponding to the specified revision.
 *
 * @since 2.6.0
 *
 * @param int|WP_Post $revision Revision ID or revision object.
 * @return WP_Post|false|null Null or false if error, deleted post object if success.
 */
function wp_delete_post_revision( $revision ) {
	$revision = wp_get_post_revision( $revision );

	if ( ! $revision ) {
		return $revision;
	}

	$delete = wp_delete_post( $revision->ID );

	if ( $delete ) {
		/**
		 * Fires once a post revision has been deleted.
		 *
		 * @since 2.6.0
		 *
		 * @param int     $revision_id Post revision ID.
		 * @param WP_Post $revision    Post revision object.
		 */
		do_action( 'wp_delete_post_revision', $revision->ID, $revision );
	}

	return $delete;
}

/**
 * Returns all revisions of specified post.
 *
 * @since 2.6.0
 *
 * @see get_children()
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global `$post`.
 * @param array|null  $args Optional. Arguments for retrieving post revisions. Default null.
 * @return WP_Post[]|int[] Array of revision objects or IDs, or an empty array if none.
 */
function wp_get_post_revisions( $post = 0, $args = null ) {
	$post = get_post( $post );

	if ( ! $post || empty( $post->ID ) ) {
		return array();
	}

	$defaults = array(
		'order'         => 'DESC',
		'orderby'       => 'date ID',
		'check_enabled' => true,
	);
	$args     = wp_parse_args( $args, $defaults );

	if ( $args['check_enabled'] && ! wp_revisions_enabled( $post ) ) {
		return array();
	}

	$args = array_merge(
		$args,
		array(
			'post_parent' => $post->ID,
			'post_type'   => 'revision',
			'post_status' => 'inherit',
		)
	);

	$revisions = get_children( $args );

	if ( ! $revisions ) {
		return array();
	}

	return $revisions;
}

/**
 * Returns the latest revision ID and count of revisions for a post.
 *
 * @since 6.1.0
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
 * @return array|WP_Error {
 *     Returns associative array with latest revision ID and total count,
 *     or a WP_Error if the post does not exist or revisions are not enabled.
 *
 *     @type int $latest_id The latest revision post ID or 0 if no revisions exist.
 *     @type int $count     The total count of revisions for the given post.
 * }
 */
function wp_get_latest_revision_id_and_total_count( $post = 0 ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post.' ) );
	}

	if ( ! wp_revisions_enabled( $post ) ) {
		return new WP_Error( 'revisions_not_enabled', __( 'Revisions not enabled.' ) );
	}

	$args = array(
		'post_parent'         => $post->ID,
		'fields'              => 'ids',
		'post_type'           => 'revision',
		'post_status'         => 'inherit',
		'order'               => 'DESC',
		'orderby'             => 'date ID',
		'posts_per_page'      => 1,
		'ignore_sticky_posts' => true,
	);

	$revision_query = new WP_Query();
	$revisions      = $revision_query->query( $args );

	if ( ! $revisions ) {
		return array(
			'latest_id' => 0,
			'count'     => 0,
		);
	}

	return array(
		'latest_id' => $revisions[0],
		'count'     => $revision_query->found_posts,
	);
}

/**
 * Returns the url for viewing and potentially restoring revisions of a given post.
 *
 * @since 5.9.0
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global `$post`.
 * @return string|null The URL for editing revisions on the given post, otherwise null.
 */
function wp_get_post_revisions_url( $post = 0 ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	// If the post is a revision, return early.
	if ( 'revision' === $post->post_type ) {
		return get_edit_post_link( $post );
	}

	if ( ! wp_revisions_enabled( $post ) ) {
		return null;
	}

	$revisions = wp_get_latest_revision_id_and_total_count( $post->ID );

	if ( is_wp_error( $revisions ) || 0 === $revisions['count'] ) {
		return null;
	}

	return get_edit_post_link( $revisions['latest_id'] );
}

/**
 * Determines whether revisions are enabled for a given post.
 *
 * @since 3.6.0
 *
 * @param WP_Post $post The post object.
 * @return bool True if number of revisions to keep isn't zero, false otherwise.
 */
function wp_revisions_enabled( $post ) {
	return wp_revisions_to_keep( $post ) !== 0;
}

/**
 * Determines how many revisions to retain for a given post.
 *
 * By default, an infinite number of revisions are kept.
 *
 * The constant WP_POST_REVISIONS can be set in wp-config to specify the limit
 * of revisions to keep.
 *
 * @since 3.6.0
 *
 * @param WP_Post $post The post object.
 * @return int The number of revisions to keep.
 */
function wp_revisions_to_keep( $post ) {
	$num = WP_POST_REVISIONS;

	if ( true === $num ) {
		$num = -1;
	} else {
		$num = (int) $num;
	}

	if ( ! post_type_supports( $post->post_type, 'revisions' ) ) {
		$num = 0;
	}

	/**
	 * Filters the number of revisions to save for the given post.
	 *
	 * Overrides the value of WP_POST_REVISIONS.
	 *
	 * @since 3.6.0
	 *
	 * @param int     $num  Number of revisions to store.
	 * @param WP_Post $post Post object.
	 */
	$num = apply_filters( 'wp_revisions_to_keep', $num, $post );

	/**
	 * Filters the number of revisions to save for the given post by its post type.
	 *
	 * Overrides both the value of WP_POST_REVISIONS and the {@see 'wp_revisions_to_keep'} filter.
	 *
	 * The dynamic portion of the hook name, `$post->post_type`, refers to
	 * the post type slug.
	 *
	 * Possible hook names include:
	 *
	 *  - `wp_post_revisions_to_keep`
	 *  - `wp_page_revisions_to_keep`
	 *
	 * @since 5.8.0
	 *
	 * @param int     $num  Number of revisions to store.
	 * @param WP_Post $post Post object.
	 */
	$num = apply_filters( "wp_{$post->post_type}_revisions_to_keep", $num, $post );

	return (int) $num;
}

/**
 * Sets up the post object for preview based on the post autosave.
 *
 * @since 2.7.0
 * @access private
 *
 * @param WP_Post $post
 * @return WP_Post|false
 */
function _set_preview( $post ) {
	if ( ! is_object( $post ) ) {
		return $post;
	}

	$preview = wp_get_post_autosave( $post->ID );

	if ( is_object( $preview ) ) {
		$preview = sanitize_post( $preview );

		$post->post_content = $preview->post_content;
		$post->post_title   = $preview->post_title;
		$post->post_excerpt = $preview->post_excerpt;
	}

	add_filter( 'get_the_terms', '_wp_preview_terms_filter', 10, 3 );
	add_filter( 'get_post_metadata', '_wp_preview_post_thumbnail_filter', 10, 3 );
	add_filter( 'get_post_metadata', '_wp_preview_meta_filter', 10, 4 );

	return $post;
}

/**
 * Filters the latest content for preview from the post autosave.
 *
 * @since 2.7.0
 * @access private
 */
function _show_post_preview() {
	if ( isset( $_GET['preview_id'] ) && isset( $_GET['preview_nonce'] ) ) {
		$id = (int) $_GET['preview_id'];

		if ( false === wp_verify_nonce( $_GET['preview_nonce'], 'post_preview_' . $id ) ) {
			wp_die( __( 'Sorry, you are not allowed to preview drafts.' ), 403 );
		}

		add_filter( 'the_preview', '_set_preview' );
	}
}

/**
 * Filters terms lookup to set the post format.
 *
 * @since 3.6.0
 * @access private
 *
 * @param array  $terms
 * @param int    $post_id
 * @param string $taxonomy
 * @return array
 */
function _wp_preview_terms_filter( $terms, $post_id, $taxonomy ) {
	$post = get_post();

	if ( ! $post ) {
		return $terms;
	}

	if ( empty( $_REQUEST['post_format'] ) || $post->ID !== $post_id
		|| 'post_format' !== $taxonomy || 'revision' === $post->post_type
	) {
		return $terms;
	}

	if ( 'standard' === $_REQUEST['post_format'] ) {
		$terms = array();
	} else {
		$term = get_term_by( 'slug', 'post-format-' . sanitize_key( $_REQUEST['post_format'] ), 'post_format' );

		if ( $term ) {
			$terms = array( $term ); // Can only have one post format.
		}
	}

	return $terms;
}

/**
 * Filters post thumbnail lookup to set the post thumbnail.
 *
 * @since 4.6.0
 * @access private
 *
 * @param null|array|string $value    The value to return - a single metadata value, or an array of values.
 * @param int               $post_id  Post ID.
 * @param string            $meta_key Meta key.
 * @return null|array The default return value or the post thumbnail meta array.
 */
function _wp_preview_post_thumbnail_filter( $value, $post_id, $meta_key ) {
	$post = get_post();

	if ( ! $post ) {
		return $value;
	}

	if ( empty( $_REQUEST['_thumbnail_id'] ) || empty( $_REQUEST['preview_id'] )
		|| $post->ID !== $post_id || $post_id !== (int) $_REQUEST['preview_id']
		|| '_thumbnail_id' !== $meta_key || 'revision' === $post->post_type
	) {
		return $value;
	}

	$thumbnail_id = (int) $_REQUEST['_thumbnail_id'];

	if ( $thumbnail_id <= 0 ) {
		return '';
	}

	return (string) $thumbnail_id;
}

/**
 * Gets the post revision version.
 *
 * @since 3.6.0
 * @access private
 *
 * @param WP_Post $revision
 * @return int|false
 */
function _wp_get_post_revision_version( $revision ) {
	if ( is_object( $revision ) ) {
		$revision = get_object_vars( $revision );
	} elseif ( ! is_array( $revision ) ) {
		return false;
	}

	if ( preg_match( '/^\d+-(?:autosave|revision)-v(\d+)$/', $revision['post_name'], $matches ) ) {
		return (int) $matches[1];
	}

	return 0;
}

/**
 * Upgrades the revisions author, adds the current post as a revision and sets the revisions version to 1.
 *
 * @since 3.6.0
 * @access private
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param WP_Post $post      Post object.
 * @param array   $revisions Current revisions of the post.
 * @return bool true if the revisions were upgraded, false if problems.
 */
function _wp_upgrade_revisions_of_post( $post, $revisions ) {
	global $wpdb;

	// Add post option exclusively.
	$lock   = "revision-upgrade-{$post->ID}";
	$now    = time();
	$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'off') /* LOCK */", $lock, $now ) );

	if ( ! $result ) {
		// If we couldn't get a lock, see how old the previous lock is.
		$locked = get_option( $lock );

		if ( ! $locked ) {
			/*
			 * Can't write to the lock, and can't read the lock.
			 * Something broken has happened.
			 */
			return false;
		}

		if ( $locked > $now - HOUR_IN_SECONDS ) {
			// Lock is not too old: some other process may be upgrading this post. Bail.
			return false;
		}

		// Lock is too old - update it (below) and continue.
	}

	// If we could get a lock, re-"add" the option to fire all the correct filters.
	update_option( $lock, $now );

	reset( $revisions );
	$add_last = true;

	do {
		$this_revision = current( $revisions );
		$prev_revision = next( $revisions );

		$this_revision_version = _wp_get_post_revision_version( $this_revision );

		// Something terrible happened.
		if ( false === $this_revision_version ) {
			continue;
		}

		/*
		 * 1 is the latest revision version, so we're already up to date.
		 * No need to add a copy of the post as latest revision.
		 */
		if ( 0 < $this_revision_version ) {
			$add_last = false;
			continue;
		}

		// Always update the revision version.
		$update = array(
			'post_name' => preg_replace( '/^(\d+-(?:autosave|revision))[\d-]*$/', '$1-v1', $this_revision->post_name ),
		);

		/*
		 * If this revision is the oldest revision of the post, i.e. no $prev_revision,
		 * the correct post_author is probably $post->post_author, but that's only a good guess.
		 * Update the revision version only and Leave the author as-is.
		 */
		if ( $prev_revision ) {
			$prev_revision_version = _wp_get_post_revision_version( $prev_revision );

			// If the previous revision is already up to date, it no longer has the information we need :(
			if ( $prev_revision_version < 1 ) {
				$update['post_author'] = $prev_revision->post_author;
			}
		}

		// Upgrade this revision.
		$result = $wpdb->update( $wpdb->posts, $update, array( 'ID' => $this_revision->ID ) );

		if ( $result ) {
			wp_cache_delete( $this_revision->ID, 'posts' );
		}
	} while ( $prev_revision );

	delete_option( $lock );

	// Add a copy of the post as latest revision.
	if ( $add_last ) {
		wp_save_post_revision( $post->ID );
	}

	return true;
}

/**
 * Filters preview post meta retrieval to get values from the autosave.
 *
 * Filters revisioned meta keys only.
 *
 * @since 6.4.0
 *
 * @param mixed  $value     Meta value to filter.
 * @param int    $object_id Object ID.
 * @param string $meta_key  Meta key to filter a value for.
 * @param bool   $single    Whether to return a single value.
 * @return mixed Original meta value if the meta key isn't revisioned, the object doesn't exist,
 *               the post type is a revision or the post ID doesn't match the object ID.
 *               Otherwise, the revisioned meta value is returned for the preview.
 */
function _wp_preview_meta_filter( $value, $object_id, $meta_key, $single ) {
	$post = get_post();

	if ( empty( $post )
		|| $post->ID !== $object_id
		|| ! in_array( $meta_key, wp_post_revision_meta_keys( $post->post_type ), true )
		|| 'revision' === $post->post_type
	) {
		return $value;
	}

	$preview = wp_get_post_autosave( $post->ID );

	if ( false === $preview ) {
		return $value;
	}

	return get_post_meta( $preview->ID, $meta_key, $single );
}
