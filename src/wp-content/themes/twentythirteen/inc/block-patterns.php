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
			<figure class="wp-block-gallery columns-3 is-cropped"><ul class="blocks-gallery-grid"><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" alt="' . esc_attr__( 'Solid red square', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/torus-interior.jpg" alt="' . esc_attr__( 'NASA Space Colony illustration, interior view of torus colony. Public spaces appear in the foreground of the torus, while housing, rolling hills, and a river snake up into the background.', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/torus-interior.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" alt="' . esc_attr__( 'Solid red square', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/cylinder-interior.jpg" alt="alt="' . esc_attr__( 'NASA Space Colony illustration, interior view of a cylindrical space colony, looking out through large windows. The interior contains fields, forests, and a river snaking from the foreground into the background. Low clouds hang over the land.', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/cylinder-interior.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/orange.jpg" alt="' . esc_attr__( 'Solid orange square', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/orange.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/toroidal-colony.jpg" alt="' . esc_attr__( 'NASA Space Colony illustration, cutaway view, exposing the interior of a toroidal colony. Trees and densely-packed housing line the inside of the torus.', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/toroidal-colony.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" alt="' . esc_attr__( 'Solid red square', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/bernal-cutaway.jpg" alt="' . esc_attr__( 'NASA Space Colony illustration, cutaway view of Bernal Sphere. The interior of the sphere is filled with greenery and houses, and a star shines brightly behind the colony.', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/bernal-cutaway.jpg" data-link="#"/></figure></li><li class="blocks-gallery-item"><figure><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" alt="' . esc_attr__( 'Solid red square', 'twentythirteen' ) . '" data-full-url="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/dark-red.jpg" data-link="#"/></figure></li></ul></figure>
			<!-- /wp:gallery --></div></div>
			<!-- /wp:cover -->',
		)
	);

	// Informational Section.
	register_block_pattern(
		'twentythirteen/informational-section',
		array(
			'title'      => esc_attr__( 'Informational Section', 'twentythirteen' ),
			'categories' => array( 'twentythirteen' ),
			'content'    => '',
		)
	);

	// Decorative Columns.
	register_block_pattern(
		'twentythirteen/decorative-columns',
		array(
			'title'      => esc_attr__( 'Decorative Columns', 'twentythirteen' ),
			'categories' => array( 'twentythirteen' ),
			'content'    => '',
		)
	);

	// Callout Quote.
	register_block_pattern(
		'twentythirteen/callout-quote',
		array(
			'title'      => esc_attr__( 'Callout Quote', 'twentythirteen' ),
			'categories' => array( 'twentythirteen' ),
			'content'    => '<!-- wp:columns {"verticalAlignment":"center"} -->
			<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center"><!-- wp:separator {"className":"is-style-wide"} -->
			<hr class="wp-block-separator is-style-wide"/>
			<!-- /wp:separator --></div>
			<!-- /wp:column -->
			
			<!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center"><!-- wp:quote -->
			<blockquote class="wp-block-quote"><p>' . wp_kses_post( __( 'When you look at <br>the stars and the galaxy, you feel that you are not <br>just from any particular piece of land, but from the solar system.', 'twentythirteen' ) ) . '</p><cite>' . esc_html__( 'Kalpana Chawla', 'twentythirteen' ) . '</cite></blockquote>
			<!-- /wp:quote --></div>
			<!-- /wp:column --></div>
			<!-- /wp:columns -->',
		)
	);

	// Big Quote.
	register_block_pattern(
		'twentythirteen/big-quote',
		array(
			'title'      => esc_attr__( 'Big Quote', 'twentythirteen' ),
			'categories' => array( 'twentythirteen' ),
			'content'    => '<!-- wp:cover {"overlayColor":"dark-gray","minHeight":100,"minHeightUnit":"vh","align":"full"} -->
			<div class="wp-block-cover alignfull has-dark-gray-background-color has-background-dim" style="min-height:100vh"><div class="wp-block-cover__inner-container"><!-- wp:image {"align":"center","sizeSlug":"thumbnail","linkDestination":"none","className":"is-style-rounded"} -->
			<div class="wp-block-image is-style-rounded"><figure class="aligncenter size-thumbnail"><img src="' . esc_url( get_template_directory_uri() ) . '/images/block-patterns/bernal-cutaway.jpg" alt="alt="' . esc_attr__( 'NASA Space Colony illustration, cutaway view of Bernal Sphere. The interior of the sphere is filled with greenery and houses, and a star shines brightly behind the colony.', 'twentythirteen' ) . '"/></figure></div>
			<!-- /wp:image -->

			<!-- wp:quote {"align":"center","className":"is-style-large"} -->
			<blockquote class="wp-block-quote has-text-align-center is-style-large"><p>' . esc_html__( 'When you look at the stars and the galaxy,&nbsp;you&nbsp;feel that&nbsp;you&nbsp;are not just from any particular piece of land, but from the solar system.', 'twentythirteen' ) . '</p><cite>' . esc_html__( 'Kalpana Chawla', 'twentythirteen' ) . '</cite></blockquote>
			<!-- /wp:quote --></div></div>
			<!-- /wp:cover -->',
		)
	);

	// Informational List.
	register_block_pattern(
		'twentythirteen/informational-list',
		array(
			'title'      => esc_attr__( 'Informational List', 'twentythirteen' ),
			'categories' => array( 'twentythirteen' ),
			'content'    => '<!-- wp:cover {"overlayColor":"red","contentPosition":"center center","align":"wide"} -->
			<div class="wp-block-cover alignwide has-red-background-color has-background-dim"><div class="wp-block-cover__inner-container"><!-- wp:paragraph -->
			<p><strong>' . esc_html__( 'FAMOUS ASTRONAUTS', 'twentythirteen' ) . '</strong></p>
			<!-- /wp:paragraph -->
			
			<!-- wp:columns -->
			<div class="wp-block-columns"><!-- wp:column -->
			<div class="wp-block-column"><!-- wp:paragraph -->
			<p>' . wp_kses_post( __( 'Yuri Gagarin<br>Alan B. Shepard Jr.<br>Valentina Tereshkova<br>John Glenn Jr.', 'twentythirteen' ) ) . '</p>
			<!-- /wp:paragraph --></div>
			<!-- /wp:column -->
			
			<!-- wp:column -->
			<div class="wp-block-column"><!-- wp:paragraph -->
			<p>' . wp_kses_post( __( 'Neil Armstrong<br>James Lovell Jr.<br>Dr. Sally Ride<br>Chris Hadfield', 'twentythirteen' ) ) . '</p>
			<!-- /wp:paragraph --></div>
			<!-- /wp:column --></div>
			<!-- /wp:columns --></div></div>
			<!-- /wp:cover -->',
		)
	);

}
