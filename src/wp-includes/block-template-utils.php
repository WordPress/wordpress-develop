<?php
/**
 * Utilities used to fetch and create templates.
 *
 * @package WordPress
 * @since 5.8.0
 */

/**
 * Build a unified template object based a post Object.
 *
 * @access private
 * @since 5.8.0
 *
 * @param WP_Post $post Template post.
 *
 * @return WP_Block_Template|WP_Error Template.
 */
function _build_template_result_from_post( $post ) {
	$template_type = $post->post_type;

	if ( 'wp_template' !== $template_type ) {
		return new WP_Error( 'template_wrong_post_type', __( 'An invalid post was provided for this template.', 'gutenberg' ) );
	}

	$ids    = get_theme_mod( $template_type, array() );
	$active = in_array( $post->ID, $ids, true );

	// Temporarily disable inactive access for 5.8 version.
	if ( ! $active ) {
		return new WP_Error( 'template_missing_theme', __( 'No theme is defined for this template.', 'gutenberg' ) );
	}

	$theme          = wp_get_theme()->get_stylesheet();
	$slug           = array_search( $post->ID, $ids, true );

	$template                 = new WP_Block_Template();
	$template->wp_id          = $post->ID;
	$template->id             = $theme . '//' . $slug;
	$template->theme          = $theme;
	$template->content        = $post->post_content;
	$template->slug           = $slug;
	$template->source         = 'custom';
	$template->type           = $post->post_type;
	$template->description    = $post->post_excerpt;
	$template->title          = $post->post_title;
	$template->status         = $post->post_status;
	$template->has_theme_file = false;

	return $template;
}

/**
 * Retrieves a list of unified template objects based on a query.
 *
 * @since 5.8.0
 *
 * @param array $query {
 *     Optional. Arguments to retrieve templates.
 *
 *     @type array  $slug__in List of slugs to include.
 *     @type int    $wp_id Post ID of customized template.
 * }
 * @param string $template_type wp_template.
 *
 * @return array Templates.
 */
function get_block_templates( $query = array(), $template_type = 'wp_template' ) {
	$theme_slugs = get_theme_mod( $template_type, array() );

	$wp_query_args = array(
		'post_status'    => array( 'auto-draft', 'draft', 'publish' ),
		'post_type'      => $template_type,
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'post__in'       => array_values( $theme_slugs ),
	);

	if ( isset( $query['slug__in'] ) ) {
		$wp_query_args['post__in'] = array();
		foreach ( $query['slug__in'] as $slug ) {
			if ( ! empty( $theme_slugs[ $slug ] ) ) {
				$wp_query_args['post__in'][] = $theme_slugs[ $slug ];
			}
		}
	}

	// This is only needed for the regular templates CPT listing and editor.
	if ( isset( $query['wp_id'] ) ) {
		$wp_query_args['p'] = $query['wp_id'];
	} else {
		$wp_query_args['post_status'] = 'publish';
	}

	$query_result = array();

	// See https://core.trac.wordpress.org/ticket/28099 for context.
	if ( ! isset( $wp_query_args['post__in'] ) || array() !== $wp_query_args['post__in'] ) {
		$template_query = new WP_Query( $wp_query_args );
		foreach ( $template_query->get_posts() as $post ) {
			$template = _build_template_result_from_post( $post, $template_type );

			if ( ! is_wp_error( $template ) ) {
				$query_result[] = $template;
			}
		}
	}

	return $query_result;
}

/**
 * Retrieves a single unified template object using its id.
 *
 * @since 5.8.0
 *
 * @param string $id Template unique identifier (example: theme_slug//template_slug).
 * @param string $template_type wp_template.
 *
 * @return WP_Block_Template|null Template.
 */
function get_block_template( $id, $template_type = 'wp_template' ) {
	$parts = explode( '//', $id, 2 );
	if ( count( $parts ) < 2 ) {
		return null;
	}
	list( $theme, $slug ) = $parts;

	$active = wp_get_theme()->get_stylesheet() === $theme;

	if ( ! $active ) {
		return null;
	}

	$ids = get_theme_mod( $template_type, array() );

	if ( ! empty( $ids[ $slug ] ) ) {
		$post = get_post( $ids[ $slug ] );
	}

	if ( $post && $template_type === $post->post_type ) {
		$template = _build_template_result_from_post( $post, $template_type );

		if ( ! is_wp_error( $template ) ) {
			return $template;
		}
	}

	return null;
}
