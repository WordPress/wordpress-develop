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
