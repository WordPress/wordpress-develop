<?php

/**
 * Finds whether a customized block template with the given slug exists for the currently active theme.
 *
 * @access private
 * @since 5.8.0
 *
 * @param string $slug          Template slug.
 * @param string $template_type wp_template.
 *
 * @return bool Whether the template is customized for the currently active theme.
 */
function customized_block_template_exists( $slug, $template_type = 'wp_template' ) {
	$templates = get_theme_mod( $template_type, array() );

	if ( ! isset( $templates[ $slug ] ) ) {
		return false;
	}

	$customized = get_post( $templates[ $slug ] );
	if ( $customized && $customized->post_type === $template_type ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Sets a custom slug when creating new templates.
 *
 * @access private
 * @since 5.8.0
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Update post or new post.
 */
function wp_filter_save_post_wp_template( $post_id, $post, $update ) {
	if ( $update || ! $post->post_name ) {
		return;
	}

	$templates = get_theme_mod( $post->post_type, array() );
	$slug      = $post->post_name;

	if ( customized_block_template_exists( $slug, $post->post_type ) ) {
		$suffix = 2;
		do {
			$slug = _truncate_post_slug( $post->post_name, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
			$suffix++;
		} while ( customized_block_template_exists( $slug, $post->post_type ) );
	}

	$templates[ $slug ] = $post->ID;
	set_theme_mod( $post->post_type, $templates );
}

/**
 * Print the skip-link script & styles.
 *
 * @access private
 * @since 5.8.0
 *
 * @return void
 */
function the_block_template_skip_link() {

	// Early exit if not an FSE theme.
	if ( ! current_theme_supports( 'block-templates' ) ) {
		return;
	}
	?>

	<?php
	/**
	 * Print the skip-link styles.
	 */
	?>
	<style id="skip-link-styles">
		.skip-link.screen-reader-text {
			border: 0;
			clip: rect(1px,1px,1px,1px);
			clip-path: inset(50%);
			height: 1px;
			margin: -1px;
			overflow: hidden;
			padding: 0;
			position: absolute !important;
			width: 1px;
			word-wrap: normal !important;
		}

		.skip-link.screen-reader-text:focus {
			background-color: #eee;
			clip: auto !important;
			clip-path: none;
			color: #444;
			display: block;
			font-size: 1em;
			height: auto;
			left: 5px;
			line-height: normal;
			padding: 15px 23px 14px;
			text-decoration: none;
			top: 5px;
			width: auto;
			z-index: 100000;
		}
	</style>
	<?php
	/**
	 * Print the skip-link script.
	 */
	?>
	<script>
	( function() {
		var skipLinkTarget = document.querySelector( 'main' ),
			parentEl,
			skipLinkTargetID,
			skipLink;

		// Early exit if a skip-link target can't be located.
		if ( ! skipLinkTarget ) {
			return;
		}

		// Get the site wrapper.
		// The skip-link will be injected in the beginning of it.
		parentEl = document.querySelector( '.wp-site-blocks' ) || document.body,

		// Get the skip-link target's ID, and generate one if it doesn't exist.
		skipLinkTargetID = skipLinkTarget.id;
		if ( ! skipLinkTargetID ) {
			skipLinkTargetID = 'wp--skip-link--target';
			skipLinkTarget.id = skipLinkTargetID;
		}

		// Create the skip link.
		skipLink = document.createElement( 'a' );
		skipLink.classList.add( 'skip-link', 'screen-reader-text' );
		skipLink.href = '#' + skipLinkTargetID;
		skipLink.innerHTML = '<?php esc_html_e( 'Skip to content' ); ?>';

		// Inject the skip link.
		parentEl.insertAdjacentElement( 'afterbegin', skipLink );
	}() );
	</script>
	<?php
}

// By default, themes support block templates.
add_theme_support( 'block-templates' );
