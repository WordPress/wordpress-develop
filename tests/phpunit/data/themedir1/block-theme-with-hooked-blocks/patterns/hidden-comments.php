<?php
/**
 * Title: Hidden Comments
 * Slug: block-theme-with-hooked-blocks/hidden-comments
 * Inserter: no
 */
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group" >
	<!-- wp:comments -->
	<div class="wp-block-comments">
		<!-- wp:heading {"level":2} -->
		<h2><?php echo esc_html_x( 'Comments', 'Title of comments section', 'block-theme-with-hooked-blocks' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:comments-title {"level":3} /-->

		<!-- wp:comment-template -->
			<!-- wp:columns -->
			<div class="wp-block-columns">
				<!-- wp:column -->
				<div class="wp-block-column">
					<!-- wp:avatar /-->
				</div>
				<!-- /wp:column -->

				<!-- wp:column -->
				<div class="wp-block-column">
					<!-- wp:comment-author-name /-->

					<!-- wp:group -->
					<div class="wp-block-group">
						<!-- wp:comment-date /-->
						<!-- wp:comment-edit-link /-->
					</div>
					<!-- /wp:group -->

					<!-- wp:comment-content /-->

					<!-- wp:comment-reply-link /-->
				</div>
				<!-- /wp:column -->
			</div>
			<!-- /wp:columns -->
		<!-- /wp:comment-template -->

		<!-- wp:comments-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"space-between"}} -->
			<!-- wp:comments-pagination-previous /-->
			<!-- wp:comments-pagination-numbers /-->
			<!-- wp:comments-pagination-next /-->
		<!-- /wp:comments-pagination -->

	<!-- wp:post-comments-form /-->
	</div>
	<!-- /wp:comments -->
</div>
<!-- /wp:group -->
