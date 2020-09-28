<?php
/**
 * Twenty Twenty Theme: Block Patterns
 *
 * @package Twenty Seventeen
 * @since 2.4
 */

/**
 * Register Block Pattern Category.
 */
if ( function_exists( 'register_block_pattern_category' ) ) {

	register_block_pattern_category(
		'twentyseventeen',
		array( 'label' => __( 'Twenty Seventeen', 'twentyseventeen' ) )
	);
}

/**
 * Register Block Patterns.
 */
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'twentyseventeen/large-heading-with-button',
		array(
			'title'  => __( 'Large Heading with Button', 'twentyseventeen' ),
			'categories' => array( 'twentyseventeen' ),
			'content'    => '<!-- wp:heading {"level":1,"textColor":"black","style":{"typography":{"fontSize":50}}} -->
            <h1 class="has-black-color has-text-color" style="font-size:50px">' . __( 'The content of your <a href="#">Static Front Page</a> is displayed here. This is a great place to add your call to action with a brief message.' ) . '</h1>
            <!-- /wp:heading -->

            <!-- wp:buttons -->
            <div class="wp-block-buttons"><!-- wp:button {"borderRadius":0,"className":"is-style-fill"} -->
            <div class="wp-block-button is-style-fill"><a class="wp-block-button__link no-border-radius">' . __( 'Our Services' ) . '</a></div>
            <!-- /wp:button --></div>
            <!-- /wp:buttons -->',
		)
	);

	register_block_pattern(
		'twentyseventeen/images-with-text-and-link',
		array(
			'title'      => __( 'Images with Text & Link', 'twentyseventeen' ),
			'categories' => array( 'twentyseventeen' ),
			'content'    => '<!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->
            <!-- wp:columns -->
            <div class="wp-block-columns"><!-- wp:column -->
            <div class="wp-block-column">
			<!-- wp:image {"className":"size-large"} -->
			<figure class="wp-block-image size-large"><img src="' .get_template_directory_uri(). '/assets/images/stripes.jpg" alt="Black Stripes"/></figure>
			<!-- /wp:image -->
            <!-- wp:heading {"textColor":"black","style":{"typography":{"fontSize":45}}} -->
            <h2 class="has-black-color has-text-color" style="font-size:45px">Black Stripes</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"textColor":"black","style":{"typography":{"lineHeight":"1.8"}}} -->
            <p class="has-black-color has-text-color" style="line-height:1.8">' . __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.' ) . '</p>
            <!-- /wp:paragraph -->
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"3"}}} -->
            <p style="line-height:3"><a href="http://wordpress.org/"><strong>' . __( 'See Case Study' ) . ' →</strong></a></p>
            <!-- /wp:paragraph --></div>
            <!-- /wp:column -->
            <!-- wp:column -->
            <div class="wp-block-column"><!-- wp:spacer {"height":254} -->
            <div style="height:254px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->
			<!-- wp:image {"className":"size-large"} -->
			<figure class="wp-block-image size-large"><img src="' .get_template_directory_uri(). '/assets/images/white-border.jpg" alt="White border"/></figure>
			<!-- /wp:image -->
            <!-- wp:heading {"textColor":"black","style":{"typography":{"fontSize":45}}} -->
            <h2 class="has-black-color has-text-color" style="font-size:45px">White Border</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"textColor":"black","style":{"typography":{"lineHeight":"1.8"}}} -->
            <p class="has-black-color has-text-color" style="line-height:1.8">' . __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.' ) . '</p>
            <!-- /wp:paragraph -->
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"3.0"}}} -->
            <p style="line-height:3.0"><a href="http://wordpress.org/"><strong>' . __( 'See Case Study' ) . ' →</strong></a></p>
            <!-- /wp:paragraph --></div>
            <!-- /wp:column --></div>
            <!-- /wp:columns -->',
		)
	);

	register_block_pattern(
		'twentyseventeen/images-with-link',
		array(
			'title'      => __( 'Images with Link', 'twentyseventeen' ),
			'categories' => array( 'twentyseventeen' ),
			'content'    => '<!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->
            <!-- wp:columns {"verticalAlignment":"center"} -->
            <div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} -->
            <div class="wp-block-column is-vertically-aligned-center"><!-- wp:group -->
            <div class="wp-block-group"><div class="wp-block-group__inner-container">
			<!-- wp:image {"align":"center","sizeSlug":"large","className":"is-style-default"} -->
			<div class="wp-block-image is-style-default"><figure class="aligncenter size-large"><img src="' .get_template_directory_uri(). '/assets/images/stripes.jpg" alt="Black Stripes"/></figure></div>
			<!-- /wp:image -->
            <!-- wp:heading {"align":"left","textColor":"black","style":{"typography":{"fontSize":30}}} -->
            <h2 class="has-text-align-left has-black-color has-text-color" style="font-size:30px">Black Stripes</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"align":"left"} -->
            <p class="has-text-align-left"><a href="http://wordpress.org">' . __( 'See Case Study' ) . ' →</a></p>
            <!-- /wp:paragraph --></div></div>
            <!-- /wp:group --></div>
            <!-- /wp:column -->
            <!-- wp:column {"verticalAlignment":"center"} -->
            <div class="wp-block-column is-vertically-aligned-center"><!-- wp:group -->
            <div class="wp-block-group"><div class="wp-block-group__inner-container">
			<!-- wp:image {"align":"center","sizeSlug":"large","className":"is-style-default"} -->
			<div class="wp-block-image is-style-default"><figure class="aligncenter size-large"><img src="' .get_template_directory_uri(). '/assets/images/white-border.jpg" alt="White border"/></figure></div>
			<!-- /wp:image -->
            <!-- wp:heading {"align":"left","textColor":"black","style":{"typography":{"fontSize":30}}} -->
            <h2 class="has-text-align-left has-black-color has-text-color" style="font-size:30px">White Border</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"align":"left"} -->
            <p class="has-text-align-left"><a href="http://wordpress.org/">' . __( 'See Case Study' ) . ' →</a></p>
            <!-- /wp:paragraph --></div></div>
            <!-- /wp:group --></div>
            <!-- /wp:column -->
            <!-- wp:column {"verticalAlignment":"center"} -->
            <div class="wp-block-column is-vertically-aligned-center"><!-- wp:group -->
            <div class="wp-block-group"><div class="wp-block-group__inner-container">
			<!-- wp:image {"align":"center","sizeSlug":"large","className":"is-style-default"} -->
			<div class="wp-block-image is-style-default"><figure class="aligncenter size-large"><img src="' .get_template_directory_uri(). '/assets/images/direct-light.jpg" alt="Direct Light"/></figure></div>
			<!-- /wp:image -->
            <!-- wp:heading {"align":"left","textColor":"black","style":{"typography":{"fontSize":30}}} -->
            <h2 class="has-text-align-left has-black-color has-text-color" style="font-size:30px">Direct Light</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"align":"left"} -->
            <p class="has-text-align-left"><a href="http://wordpress.org/">' . __( 'See Case Study' ) . ' →</a></p>
            <!-- /wp:paragraph --></div></div>
            <!-- /wp:group --></div>
            <!-- /wp:column --></div>
            <!-- /wp:columns -->
            <!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->',
		)
    );
    
    register_block_pattern(
		'twentyseventeen/images-with-link',
		array(
			'title'      => __( 'Services', 'twentyseventeen' ),
			'categories' => array( 'twentyseventeen' ),
			'content'    => '<!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->
            
            <!-- wp:heading {"level":1,"style":{"typography":{"fontSize":50}}} -->
            <h1 style="font-size:50px">' . __( 'Our Services' ) . '</h1>
            <!-- /wp:heading -->
            
            <!-- wp:columns -->
            <div class="wp-block-columns"><!-- wp:column -->
            <div class="wp-block-column">
            <!-- wp:paragraph {"style":{"typography":{"fontSize":21, "lineHeight":"2.5"}}} -->
            <p style="font-size:21px"><a href="http://wordpress.org/">' . __( 'Branding' ) . ' →</a><br><a href="http://wordpress.org/">' . __( 'Webdesign' ) . ' →</a><br><a href="http://wordpress.org/">' . __( 'Web Development' ) . ' →</a></p>
            <!-- /wp:paragraph -->
            </div>
            <!-- /wp:column -->
            
            <!-- wp:column -->
            <div class="wp-block-column">
            <!-- wp:paragraph {"style":{"typography":{"fontSize":21, "lineHeight":"2.5"}}} -->
            <p style="font-size:21px"><a href="http://wordpress.org/">' . __( 'Content Strategy' ) . ' →</a><br><a href="http://wordpress.org/">' . __( 'Marketing &amp; SEO' ) . ' →</a><br><a href="http://wordpress.org/">' . __( 'Video Production' ) . ' →</a></p>
            <!-- /wp:paragraph --></div>
            <!-- /wp:column --></div>
            <!-- /wp:columns -->
            
            <!-- wp:spacer -->
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
            <!-- /wp:spacer -->',
        )
    );

    register_block_pattern(
		'twentyseventeen/images-with-link',
		array(
			'title'      => __( 'Contact Us', 'twentyseventeen' ),
			'categories' => array( 'twentyseventeen' ),
			'content'    => '<!-- wp:cover {"customOverlayColor":"#93aab8","minHeight":900,"align":"center"} -->
            <div class="wp-block-cover aligncenter has-background-dim" style="background-color:#93aab8;min-height:900px"><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"left","placeholder":"Write title…","textColor":"white","style":{"typography":{"fontSize":50}}} -->
            <p class="has-text-align-left has-white-color has-text-color" style="font-size:50px">' . __( 'Outstanding clients have allowed us to produce work we are proud of' ) . '</p>
            <!-- /wp:paragraph -->
            
            <!-- wp:buttons -->
            <div class="wp-block-buttons"><!-- wp:button {"borderRadius":0,"backgroundColor":"black","textColor":"white","className":"is-style-fill"} -->
            <div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background no-border-radius">Contact us</a></div>
            <!-- /wp:button --></div>
            <!-- /wp:buttons --></div></div>
            <!-- /wp:cover -->',
        )
    );
}
