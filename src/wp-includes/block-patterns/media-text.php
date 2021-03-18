<?php
/**
 * Media + text 
 *
 * @package WordPress
 */

return array(
	'title'       => __( 'Media + text' ),
	'categories'  => array( 'nature' ),
	'content'     => '<!-- wp:cover {"customOverlayColor":"#ffffff","minHeight":100,"minHeightUnit":"vh","contentPosition":"center center","align":"full"} -->
	<div class="wp-block-cover alignfull has-background-dim" style="background-color:#ffffff;min-height:100vh"><div class="wp-block-cover__inner-container"><!-- wp:media-text {"mediaId":2501,"mediaLink":"https://mywptesting.site/2021/02/button-bug/sand-rock-texture-dry-brown-soil-726223-pxhere-com_/","mediaType":"image","mediaWidth":56,"verticalAlignment":"center"} -->
	<div class="wp-block-media-text alignwide is-stacked-on-mobile is-vertically-aligned-center" style="grid-template-columns:56% auto"><figure class="wp-block-media-text__media"><img src="https://mywptesting.site/wp-content/uploads/2021/02/sand-rock-texture-dry-brown-soil-726223-pxhere.com_-1024x681.jpg" alt="" class="wp-image-2501 size-full"/></figure><div class="wp-block-media-text__content"><!-- wp:heading {"style":{"typography":{"fontSize":"32px"}},"textColor":"black"} -->
	<h2 class="has-black-color has-text-color" style="font-size:32px"><strong>'. __("What's the problem?") . '</strong></h2>
	<!-- /wp:heading -->
	
	<!-- wp:paragraph {"style":{"typography":{"fontSize":"17px"}},"textColor":"black"} -->
	<p class="has-black-color has-text-color" style="font-size:17px">'. __("Trees are more important today than ever before. More than 10,000 products are reportedly made from trees. Through chemistry, the humble woodpile is yielding chemicals, plastics and fabrics that were beyond comprehension when an axe first felled a Texas tree.") . '</p>
	<!-- /wp:paragraph -->
	
	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"black","className":"is-style-fill"} -->
	<div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-black-background-color has-background">'. __("Learn more") . '</a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons --></div></div>
	<!-- /wp:media-text --></div></div>
	<!-- /wp:cover -->',
	'description' => _x( 'Full height image cover with a quote on top of it', 'Block pattern description' ),
);
