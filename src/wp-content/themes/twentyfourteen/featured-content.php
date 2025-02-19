<?php
/**
 * The template for displaying featured content
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */

?>

<div id="featured-content" class="featured-content">
	<div class="featured-content-inner" data-prev="<?php esc_attr_e( 'Previous', 'twentyfourteen' ); ?>" data-next="<?php esc_attr_e( 'Next', 'twentyfourteen' ); ?>">
	<?php
		/**
		 * Fires before the Twenty Fourteen featured content.
		 *
		 * @since Twenty Fourteen 1.0
		 */
		do_action( 'twentyfourteen_featured_posts_before' );

		$featured_posts = twentyfourteen_get_featured_posts();

	$GLOBALS['index'] = 1;
	foreach ( (array) $featured_posts as $order => $post ) :
		setup_postdata( $post );

		// Include the featured content template.
		get_template_part( 'content', 'featured-post' );

		++$GLOBALS['index'];
	endforeach;
	unset( $GLOBALS['index'] );

		/**
		 * Fires after the Twenty Fourteen featured content.
		 *
		 * @since Twenty Fourteen 1.0
		 */
		do_action( 'twentyfourteen_featured_posts_after' );

		wp_reset_postdata();
	?>
	</div><!-- .featured-content-inner -->
</div><!-- #featured-content .featured-content -->
