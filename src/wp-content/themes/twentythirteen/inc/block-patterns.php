<?php
/**
 * Block Patterns
 *
 * @link https://developer.wordpress.org/reference/functions/register_block_pattern/
 * @link https://developer.wordpress.org/reference/functions/register_block_pattern_category/
 *
 * @package WordPress
 * @subpackage Twenty_Thirteen
 * @since Twenty Thirteen 3.3
 */

/**
 * Register Block Pattern Category.
 */
if ( function_exists( 'register_block_pattern_category' ) ) {

	register_block_pattern_category(
		'twentythirteen',
		array( 'label' => esc_attr__( 'Twenty Thirteen', 'twentythirteen' ) )
	);
}

/**
 * Register Block Patterns.
 */
if ( function_exists( 'register_block_pattern' ) ) {
	// Decorative Gallery.
	register_block_pattern(
		'twentythirteen/decorative-gallery',
		array(
			'title'      => esc_attr__( 'Decorative Gallery', 'twentythirteen' ),
			'categories' => array( 'twentythirteen' ),
			'content'    => '<!-- wp:cover {"overlayColor":"yellow","minHeight":100,"minHeightUnit":"vh","align":"full"} -->
			<div class="wp-block-cover alignfull has-yellow-background-color has-background-dim" style="min-height:100vh"><div class="wp-block-cover__inner-container"><!-- wp:gallery {"ids":[null,null,null,null,null,null,null,null,null],"linkTo":"none"} -->
			<figure class="wp-block-gallery columns-3 is-cropped"><ul class="blocks-gallery-grid"><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/torus-interior.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/torus-interior.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/cylinder-interior.jpg" alt="' . esc_attr__( 'NASA Space Colony illustration, interior view of a cylindrical space colony, looking out through large windows. The interior contains fields, forests, and a river snaking from the foreground into the background. Low clouds hang over the land.', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/cylinder-interior.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/orange.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/orange.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/toroidal-colony.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/toroidal-colony.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/bernal-cutaway.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/bernal-cutaway.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" ' . esc_attr__( '', 'twentythirteen' ) . ' data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li></ul></figure>
			<!-- /wp:gallery --></div></div>
			<!-- /wp:cover -->',
		)
	);
}