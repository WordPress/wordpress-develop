<?php
/**
 * Title: Header inside full-width background image
 * Slug: twentytwentyfive/header-inside-full-width-background-image
 * Categories: header, banner
 * Block Types: core/template-part/header
 * Description: Simple header with logo, site title, navigation and a full-width background image with dark overlay.
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.5
 */

?>
<!-- wp:group {"align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull">
	<!-- wp:cover {"url":"<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/link-in-bio-background.webp","dimRatio":50,"overlayColor":"black","focalPoint":{"x":0.5,"y":0.5},"minHeight":50,"contentPosition":"center center","isDark":false,"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","right":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30"}}}} -->
	<div class="wp-block-cover alignfull is-light" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30);min-height:50px">
		<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-50 has-background-dim"></span>
		<img class="wp-block-cover__image-background" alt="<?php esc_attr_e( 'Photo of a field full of flowers, a blue sky and a tree.', 'twentytwentyfive' ); ?>" src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/link-in-bio-background.webp" style="object-position:50% 50%" data-object-fit="cover" data-object-position="50% 50%"/>
		<div class="wp-block-cover__inner-container">
			<!-- wp:group {"align":"wide","style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"base","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
			<div class="wp-block-group alignwide has-base-color has-text-color has-link-color" style="margin-top:0;margin-bottom:0">
				<!-- wp:group {"layout":{"type":"flex"}} -->
				<div class="wp-block-group">
					<!-- wp:site-logo {"className":"is-style-default"} /-->

					<!-- wp:site-title {"style":{"elements":{"link":{"color":{"text":"var:preset|color|background"}}}},"textColor":"white","fontSize":"medium"} /-->
				</div>
				<!-- /wp:group -->

				<!-- wp:navigation {"textColor":"white","overlayBackgroundColor":"base","overlayTextColor":"contrast","layout":{"type":"flex","setCascadingProperties":true,"justifyContent":"right"}} /-->
			</div>
			<!-- /wp:group -->

			<!-- wp:spacer {"height":"33vw"} -->
			<div style="height:33vw" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer -->
		</div>
	</div>
	<!-- /wp:cover -->
</div>
<!-- /wp:group -->
