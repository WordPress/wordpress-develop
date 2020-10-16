<?php
/**
 * Twenty Sixteen Theme: Block Patterns
 *
 * @package Twenty Sixteen
 * @since 2.2
 */

/**
 * Register Block Pattern Category.
 */
if ( function_exists( 'register_block_pattern_category' ) ) {

	register_block_pattern_category(
		'twentysixteen',
		array( 'label' => __( 'Twenty Sixteen', 'twentysixteen' ) )
	);
}

/**
 * Register Block Patterns.
 */
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'twentysixteen/large-heading-short-description',
		array(
			'title'      => __( 'Large heading with short description', 'twentysixteen' ),
			'categories' => array( 'twentysixteen' ),
			'content'    => '<!-- wp:group {"align":"full","backgroundColor":"background"} -->
            <div class="wp-block-group alignfull has-background-background-color has-background"><div class="wp-block-group__inner-container"><!-- wp:spacer {"height":60} -->
            <div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->
            <!-- wp:heading {"level":6,"style":{"typography":{"lineHeight":"1.5","fontSize":35}}} -->
            <h6 style="font-size:35px;line-height:1.5"><strong>' . esc_html__( 'Twenty Sixteen is a modernized take on an ever-popular WordPress layout — the horizontal masthead with an optional right sidebar that works perfectly for blogs and websites.', 'twentysixteen' ) . '</strong></h6>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.8"}}} -->
            <p style="line-height:1.8">' . esc_html__( 'It has custom color options with beautiful default color schemes, a harmonious fluid grid using a mobile-first approach, and impeccable polish in every detail. Twenty Sixteen will make your WordPress look beautiful everywhere.', 'twentysixteen' ) . '</p>
            <!-- /wp:paragraph -->
            <!-- wp:spacer {"height":60} -->
            <div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer --></div></div>
            <!-- /wp:group -->',
		)
	);

	register_block_pattern(
		'twentysixteen/big-title-two-columns-text',
		array(
			'title'      => __( 'Big Title with Two Columns Text', 'twentysixteen' ),
			'categories' => array( 'twentysixteen' ),
			'content'    => '<!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->

            <!-- wp:heading {"level":1,"style":{"typography":{"fontSize":55}}} -->
            <h1 style="font-size:55px">' . esc_html__( 'Lonesome ghosts hovering their chairs', 'twentysixteen' ) . '</h1>
            <!-- /wp:heading -->

            <!-- wp:spacer {"height":30} -->
            <div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->

            <!-- wp:columns -->
            <div class="wp-block-columns"><!-- wp:column -->
            <div class="wp-block-column"><!-- wp:paragraph {"dropCap":true} -->
            <p class="has-drop-cap">' . esc_html__( 'It might have been said that there he was only protecting his own. From the first he had been admitted to live in the intimacy of the family of the hotel-keeper who was a countryman of his. Old Giorgio Viola, a Genoese with a shaggy white leonine head—often called simply “the Garibaldino” (as Mohammedans are called after their prophet)—was, to use Captain Mitchell’s own words, the “respectable married friend” by whose advice Nostromo had left his ship to try for a run of shore luck in Costaguana.', 'twentysixteen' ) . '</p>
            <!-- /wp:paragraph -->

            <!-- wp:paragraph -->
            <p>' . esc_html__( 'The old republican did not believe in saints, or in prayers, or in what he called “priest’s religion.” Liberty and Garibaldi were his divinities; but he tolerated “superstition” in women, preserving in these matters a lofty and silent attitude.', 'twentysixteen' ) . '</p>
            <!-- /wp:paragraph --></div>
            <!-- /wp:column -->

            <!-- wp:column -->
            <div class="wp-block-column"><!-- wp:paragraph -->
            <p>' . esc_html__( 'The old man, full of scorn for the populace, as your austere republican so often is, had disregarded the preliminary sounds of trouble. He went on that day as usual pottering about the “casa” in his slippers, muttering angrily to himself his contempt of the non-political nature of the riot, and shrugging his shoulders. In the end he was taken unawares by the out-rush of the rabble. It was too late then to remove his family, and, indeed, where could he have run to with the portly Signora Teresa and two little girls on that great plain? So, barricading every opening, the old man sat down sternly in the middle of the darkened cafe with an old shot-gun on his knees. His wife sat on another chair by his side, muttering pious invocations to all the saints of the calendar.', 'twentysixteen' ) . '</p>
            <!-- /wp:paragraph --></div>
            <!-- /wp:column --></div>
            <!-- /wp:columns -->

            <!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->',
		)
	);

	register_block_pattern(
		'twentysixteen/large-blockquote',
		array(
			'title'      => __( 'Large Blockquote', 'twentysixteen' ),
			'categories' => array( 'twentysixteen' ),
			'content'    => '<!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->

            <!-- wp:separator {"color":"dark-gray","className":"is-style-wide"} -->
            <hr class="wp-block-separator has-text-color has-background has-dark-gray-background-color has-dark-gray-color is-style-wide"/>
            <!-- /wp:separator -->

            <!-- wp:heading {"style":{"typography":{"lineHeight":"1.5","fontSize":40}}} -->
            <h2 style="font-size:40px;line-height:1.5"><em>' . esc_html__( 'Twenty Sixteen will make your WordPress look beautiful everywhere.', 'twentysixteen' ) . '</em></h2>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"textColor":"medium-gray"} -->
            <p class="has-medium-gray-color has-text-color">' . esc_html__( '— Takashi Irie', 'twentysixteen' ) . '</p>
            <!-- /wp:paragraph -->

            <!-- wp:spacer {"height":20} -->
            <div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->

            <!-- wp:separator {"color":"dark-gray","className":"is-style-wide"} -->
            <hr class="wp-block-separator has-text-color has-background has-dark-gray-background-color has-dark-gray-color is-style-wide"/>
            <!-- /wp:separator -->

            <!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->',
		)
	);

	register_block_pattern(
		'twentysixteen/call-to-action',
		array(
			'title'      => __( 'Call to Action', 'twentysixteen' ),
			'categories' => array( 'twentysixteen' ),
			'content'    => '<!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->

            <!-- wp:separator {"color":"dark-gray","className":"is-style-wide"} -->
            <hr class="wp-block-separator has-text-color has-background has-dark-gray-background-color has-dark-gray-color is-style-wide"/>
            <!-- /wp:separator -->

            <!-- wp:heading {"level":1,"style":{"typography":{"fontSize":35,"lineHeight":"1.5"}}} -->
            <h1 style="font-size:35px;line-height:1.5">' . esc_html__( 'My new book “Sail On” is available for pre-order on Amazon.', 'twentysixteen' ) . '</h1>
            <!-- /wp:heading -->

            <!-- wp:columns -->
            <div class="wp-block-columns"><!-- wp:column -->
            <div class="wp-block-column"><!-- wp:buttons -->
            <div class="wp-block-buttons"><!-- wp:button {"borderRadius":0,"backgroundColor":"bright-blue"} -->
            <div class="wp-block-button"><a class="wp-block-button__link has-bright-blue-background-color has-background no-border-radius">' . esc_html__( 'Pre-Order Now', 'twentysixteen' ) . '</a></div>
            <!-- /wp:button --></div>
            <!-- /wp:buttons --></div>
            <!-- /wp:column -->

            <!-- wp:column -->
            <div class="wp-block-column"><!-- wp:spacer {"height":54} -->
            <div style="height:54px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer --></div>
            <!-- /wp:column --></div>
            <!-- /wp:columns -->

            <!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->',
		)
	);
}
