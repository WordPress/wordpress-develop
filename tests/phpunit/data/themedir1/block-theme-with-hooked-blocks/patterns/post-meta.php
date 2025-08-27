<?php
/**
 * Title: Post Meta
 * Slug: block-theme-with-hooked-blocks/post-meta
 * Categories: query
 * Keywords: post meta
 * Block Types: core/template-part/post-meta
 */
?>
<!-- wp:spacer {"height":"0"} -->
<div style="height:0" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:separator {"opacity":"css","align":"wide","className":"is-style-wide"} -->
	<hr class="wp-block-separator alignwide has-css-opacity is-style-wide"/>
	<!-- /wp:separator -->

	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"layout":{"type":"flex"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph -->
				<p>
					<?php echo esc_html_x( 'Posted', 'Verb to explain the publication status of a post', 'block-theme-with-hooked-blocks' ); ?>
				</p>
				<!-- /wp:paragraph -->

				<!-- wp:post-date /-->

				<!-- wp:paragraph -->
				<p>
					<?php echo esc_html_x( 'in', 'Preposition to show the relationship between the post and its categories', 'block-theme-with-hooked-blocks' ); ?>
				</p>
				<!-- /wp:paragraph -->

				<!-- wp:post-terms {"term":"category"} /-->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"layout":{"type":"flex"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph -->
				<p>
					<?php echo esc_html_x( 'by', 'Preposition to show the relationship between the post and its author', 'block-theme-with-hooked-blocks' ); ?>
				</p>
				<!-- /wp:paragraph -->

				<!-- wp:post-author {"showAvatar":false} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph -->
				<p>
					<?php echo esc_html_x( 'Tags:', 'Label for a list of post tags', 'block-theme-with-hooked-blocks' ); ?>
				</p>
				<!-- /wp:paragraph -->

				<!-- wp:post-terms {"term":"post_tag"} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
