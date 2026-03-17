<?php
/**
 * Bootstraps collaborative editing.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Injects the real-time collaboration setting into a global variable.
 *
 * @since 7.0.0
 *
 * @access private
 *
 * @global string $pagenow The filename of the current screen.
 */
function wp_collaboration_inject_setting() {
	global $pagenow;

	if ( ! get_option( 'wp_enable_real_time_collaboration' ) ) {
		return;
	}

	// Disable real-time collaboration on the site editor.
	$enabled = true;
	if ( 'site-editor.php' === $pagenow ) {
		$enabled = false;
	}

	wp_add_inline_script(
		'wp-core-data',
		'window._wpCollaborationEnabled = ' . wp_json_encode( $enabled ) . ';',
		'after'
	);
}

/**
 * Modifies the post list UI and heartbeat responses for real-time collaboration.
 *
 * When RTC is enabled, hides the lock icon and user avatar, replaces the
 * user-specific lock text with "Currently being edited", changes the "Edit"
 * row action to "Join", and re-enables controls that core normally hides
 * for locked posts (since collaborative editing is possible).
 *
 * @since 7.0.0
 *
 * @access private
 *
 * @global string $pagenow The filename of the current screen.
 */
function wp_post_list_collaboration_ui() {
	global $pagenow;

	if ( ! get_option( 'wp_enable_real_time_collaboration' ) ) {
		return;
	}

	// Heartbeat filter applies globally (not just edit.php) since the
	// heartbeat API can fire from any admin page.
	add_filter( 'heartbeat_received', 'wp_filter_locked_posts_heartbeat_for_rtc', 20 );

	// CSS, JS, and row action overrides only apply on the posts list page.
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	add_action( 'admin_head', 'wp_post_list_collaboration_styles' );
	add_filter( 'gettext', 'wp_filter_locked_post_text_for_rtc', 10, 3 );
	add_filter( 'post_row_actions', 'wp_post_list_collaboration_row_actions', 10, 2 );
	add_filter( 'page_row_actions', 'wp_post_list_collaboration_row_actions', 10, 2 );
}

/**
 * Filters the heartbeat response to remove user-specific lock information
 * when real-time collaboration is enabled.
 *
 * WordPress core's wp_check_locked_posts() runs at priority 10 and populates
 * the 'wp-check-locked-posts' key with user name, avatar, and text. This
 * filter runs at priority 20 to replace that data with a generic message,
 * preventing user-specific lock info from reaching the client.
 *
 * @since 7.0.0
 *
 * @access private
 *
 * @param array $response The heartbeat response.
 * @return array Modified heartbeat response.
 */
function wp_filter_locked_posts_heartbeat_for_rtc( $response ) {
	if ( ! empty( $response['wp-check-locked-posts'] ) ) {
		foreach ( $response['wp-check-locked-posts'] as $key => $lock_data ) {
			$response['wp-check-locked-posts'][ $key ]['text'] = __( 'Currently being edited' );
			unset( $response['wp-check-locked-posts'][ $key ]['avatar_src'] );
			unset( $response['wp-check-locked-posts'][ $key ]['avatar_src_2x'] );
		}
	}

	return $response;
}

/**
 * Outputs CSS to hide the post lock icon and user avatar in the post list
 * when real-time collaboration is enabled.
 *
 * Also re-enables checkboxes and row actions that WordPress core hides for
 * locked posts, since collaborative editing means the post is not exclusively
 * locked.
 *
 * @since 7.0.0
 *
 * @access private
 */
function wp_post_list_collaboration_styles() {
	?>
	<style type="text/css">
		/*
		 * Hide the lock indicator icon in the checkbox column.
		 * WordPress core shows it via .wp-locked .locked-indicator { display: block },
		 * so we match that specificity to override it.
		 */
		.wp-locked .locked-indicator {
			display: none;
		}
		/* Hide the user avatar in the locked info area. */
		.wp-locked .locked-info .locked-avatar {
			display: none;
		}
		/*
		 * Re-enable controls that core hides for locked posts,
		 * since RTC allows collaborative editing.
		 */
		.wp-locked .check-column label,
		.wp-locked .check-column input[type="checkbox"] {
			display: revert;
		}
		.wp-locked .row-actions .inline {
			display: revert;
		}
	</style>
	<?php
}

/**
 * Filters the translation of the lock text to replace user-specific
 * "%s is currently editing" with a generic "Currently being edited"
 * message on initial page render.
 *
 * WordPress core outputs this text server-side in WP_Posts_List_Table.
 * Using a gettext filter replaces it before it reaches the browser,
 * avoiding a flash of the original text.
 *
 * @since 7.0.0
 *
 * @access private
 *
 * @param string $translation Translated text.
 * @param string $text        Original text to translate.
 * @param string $domain      Text domain.
 * @return string Modified translation.
 */
function wp_filter_locked_post_text_for_rtc( $translation, $text, $domain ) {
	if ( 'default' === $domain && '%s is currently editing' === $text ) {
		return __( 'Currently being edited' );
	}

	return $translation;
}

/**
 * Filters post row actions to change "Edit" to "Join" for locked posts
 * when real-time collaboration is enabled.
 *
 * @since 7.0.0
 *
 * @access private
 *
 * @param string[] $actions An array of row action links.
 * @param WP_Post  $post    The post object.
 * @return string[] Modified row action links.
 */
function wp_post_list_collaboration_row_actions( $actions, $post ) {
	if ( ! function_exists( 'wp_check_post_lock' ) ) {
		require_once ABSPATH . 'wp-admin/includes/post.php';
	}

	$lock_holder = wp_check_post_lock( $post->ID );
	if ( ! $lock_holder ) {
		return $actions;
	}

	if ( isset( $actions['edit'] ) ) {
		$actions['edit'] = preg_replace(
			'/>Edit</',
			'>' . esc_html__( 'Join' ) . '<',
			$actions['edit']
		);
	}

	return $actions;
}
