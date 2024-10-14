<?php
/**
 * Title: Static page title and content
 * Slug: twentytwentyfour/static-page-content
 * Categories: page,starter
 * Description: Static page title and content, to embed in the front page.
 */
?>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
	<!-- wp:post-title {"align":"wide","style":{"typography":{"lineHeight":"1"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|40"}}},"fontSize":"x-large"} /-->

	<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:post-content {"lock":{"move":false,"remove":true},"layout":{"type":"constrained"}} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
