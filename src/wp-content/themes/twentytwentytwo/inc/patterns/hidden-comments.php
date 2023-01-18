<?php
/**
 * Comments block (for WP >= 6.2), or legacy Post Comments block.
 */

if ( WP_Block_Type_Registry::get_instance()->is_registered( 'core/comments' ) && version_compare( $GLOBALS['wp_version'], '6.2', '>=' ) ) {
	return array(
		'title'    => __( 'Comments block', 'twentytwentytwo' ),
		'inserter' => false,
		'content'  => '<!-- wp:comments -->
						<div class="wp-block-comments"><!-- wp:comments-title /-->

						<!-- wp:comment-template -->
						<!-- wp:columns -->
						<div class="wp-block-columns"><!-- wp:column {"width":"40px"} -->
						<div class="wp-block-column" style="flex-basis:40px"><!-- wp:avatar {"size":40,"style":{"border":{"radius":"20px"}}} /--></div>
						<!-- /wp:column -->

						<!-- wp:column -->
						<div class="wp-block-column"><!-- wp:comment-author-name {"fontSize":"small"} /-->

						<!-- wp:group {"layout":{"type":"flex"},"style":{"spacing":{"margin":{"top":"0px","bottom":"0px"}}}} -->
						<div class="wp-block-group" style="margin-top:0px;margin-bottom:0px"><!-- wp:comment-date {"fontSize":"small"} /-->

						<!-- wp:comment-edit-link {"fontSize":"small"} /--></div>
						<!-- /wp:group -->

						<!-- wp:comment-content /-->

						<!-- wp:comment-reply-link {"fontSize":"small"} /--></div>
						<!-- /wp:column --></div>
						<!-- /wp:columns -->
						<!-- /wp:comment-template -->

						<!-- wp:comments-pagination -->
						<!-- wp:comments-pagination-previous /-->

						<!-- wp:comments-pagination-numbers /-->

						<!-- wp:comments-pagination-next /-->
						<!-- /wp:comments-pagination -->

						<!-- wp:post-comments-form /--></div>
						<!-- /wp:comments -->',
	);
}
return array(
	'title'    => __( 'Post Comments block', 'twentytwentytwo' ),
	'inserter' => false,
	'content'  => '<!-- wp:post-comments /-->',
);
