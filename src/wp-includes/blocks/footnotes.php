<?php
/**
 * Server-side rendering of the `core/footnotes` block.
 *
 * @package WordPress
 */

/**
 * Renders the `core/footnotes` block on the server.
 *
 * @since 6.3.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the HTML representing the footnotes.
 */
function render_block_core_footnotes( $attributes, $content, $block ) {
	// Bail out early if the post ID is not set for some reason.
	if ( empty( $block->context['postId'] ) ) {
		return '';
	}

	if ( post_password_required( $block->context['postId'] ) ) {
		return;
	}

	$footnotes = get_post_meta( $block->context['postId'], 'footnotes', true );

	if ( ! $footnotes ) {
		return;
	}

	$footnotes = json_decode( $footnotes, true );

	if ( count( $footnotes ) === 0 ) {
		return '';
	}

	$wrapper_attributes = get_block_wrapper_attributes();

	$block_content = '';

	foreach ( $footnotes as $footnote ) {
		$block_content .= sprintf(
			'<li id="%1$s">%2$s <a href="#%1$s-link">↩︎</a></li>',
			$footnote['id'],
			$footnote['content']
		);
	}

	return sprintf(
		'<ol %1$s>%2$s</ol>',
		$wrapper_attributes,
		$block_content
	);
}

/**
 * Registers the `core/footnotes` block on the server.
 *
 * @since 6.3.0
 */
function register_block_core_footnotes() {
	foreach ( array( 'post', 'page' ) as $post_type ) {
		register_post_meta(
			$post_type,
			'footnotes',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'revisions_enabled' => true,
			)
		);
	}
	register_block_type_from_metadata(
		__DIR__ . '/footnotes',
		array(
			'render_callback' => 'render_block_core_footnotes',
		)
	);
}
add_action( 'init', 'register_block_core_footnotes' );

/**
 * Adds the footnotes field to the revision.
 *
 * @since 6.3.0
 *
 * @param array $fields The revision fields.
 * @return array The revision fields.
 */
function wp_add_footnotes_to_revision( $fields ) {
	$fields['footnotes'] = __( 'Footnotes' );
	return $fields;
}
add_filter( '_wp_post_revision_fields', 'wp_add_footnotes_to_revision' );

/**
 * Gets the footnotes field from the revision.
 *
 * @since 6.3.0
 *
 * @param string $revision_field The field value, but $revision->$field
 *                               (footnotes) does not exist.
 * @param string $field          The field name, in this case "footnotes".
 * @param object $revision       The revision object to compare against.
 * @return string The field value.
 */
function wp_get_footnotes_from_revision( $revision_field, $field, $revision ) {
	$footnotes = json_decode( get_metadata( 'post', $revision->ID, $field, true ) );
	if ( empty( $footnotes ) ) {
		return $revision_field;
	}
	$footnotes_html = '';
	$x = 1;
	foreach( $footnotes as $footnote ) {
		$footnotes_html .= sprintf(
			"%s.  %s\n",
			$x++,
			$footnote->content
		);
	}
	return $footnotes_html;
}
add_filter( '_wp_post_revision_field_footnotes', 'wp_get_footnotes_from_revision', 10, 3 );

/**
 * The REST API autosave endpoint doesn't save meta, so we can use the
 * `wp_creating_autosave` when it updates an exiting autosave, and
 * `_wp_put_post_revision` when it creates a new autosave.
 *
 * @since 6.3.0
 *
 * @param int|array $autosave The autosave ID or array.
 */
function _wp_rest_api_autosave_meta( $autosave ) {
	// Ensure it's a REST API request.
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return;
	}

	$body = rest_get_server()->get_raw_data();
	$body = json_decode( $body, true );

	if ( ! isset( $body['meta']['footnotes'] ) ) {
		return;
	}

	// `wp_creating_autosave` passes the array,
	// `_wp_put_post_revision` passes the ID.
	$id = is_int( $autosave ) ? $autosave : $autosave['ID'];

	if ( ! $id ) {
		return;
	}

	update_post_meta( $id, 'footnotes', $body['meta']['footnotes'] );
}
// See https://github.com/WordPress/wordpress-develop/blob/2103cb9966e57d452c94218bbc3171579b536a40/src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php#L391C1-L391C1.
add_action( 'wp_creating_autosave', '_wp_rest_api_autosave_meta' );
// See https://github.com/WordPress/wordpress-develop/blob/2103cb9966e57d452c94218bbc3171579b536a40/src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php#L398.
// Then https://github.com/WordPress/wordpress-develop/blob/2103cb9966e57d452c94218bbc3171579b536a40/src/wp-includes/revision.php#L367.
add_action( '_wp_put_post_revision', '_wp_rest_api_autosave_meta' );

/**
 * This is a workaround for the autosave endpoint returning early if the
 * revision field are equal. The problem is that "footnotes" is not real
 * revision post field, so there's nothing to compare against.
 *
 * This trick sets the "footnotes" field (value doesn't matter), which will
 * cause the autosave endpoint to always update the latest revision. That should
 * be fine, it should be ok to update the revision even if nothing changed. Of
 * course, this is temporary fix.
 *
 * @since 6.3.0
 *
 * @param WP_Post         $prepared_post The prepared post object.
 * @param WP_REST_Request $request       The request object.
 *
 * See https://github.com/WordPress/wordpress-develop/blob/2103cb9966e57d452c94218bbc3171579b536a40/src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php#L365-L384.
 * See https://github.com/WordPress/wordpress-develop/blob/2103cb9966e57d452c94218bbc3171579b536a40/src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php#L219.
 */
function _wp_rest_api_force_autosave_difference( $prepared_post, $request ) {
	// We only want to be altering POST requests.
	if ( $request->get_method() !== 'POST' ) {
		return $prepared_post;
	}

	// Only alter requests for the '/autosaves' route.
	if ( substr( $request->get_route(), -strlen( '/autosaves' ) ) !== '/autosaves' ) {
		return $prepared_post;
	}

	$prepared_post->footnotes = '[]';
	return $prepared_post;
}

add_filter( 'rest_pre_insert_post', '_wp_rest_api_force_autosave_difference', 10, 2 );
