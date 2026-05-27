<?php
/**
 * Template for displaying search forms in Twenty Sixteen
 *
 * @package WordPress
 * @subpackage Twenty_Sixteen
 * @since Twenty Sixteen 1.0
 */

/** @var array{ wrap_in_search: bool, aria_label: string } $args */

?>

<?php $twentysixteen_wrap_in_search = ! empty( $args['wrap_in_search'] ); ?>
<?php if ( $twentysixteen_wrap_in_search ) : ?>
<search>
<?php endif; ?>
<form <?php echo $twentysixteen_wrap_in_search ? '' : 'role="search" '; ?>method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text">
			<?php
			/* translators: Hidden accessibility text. */
			echo _x( 'Search for:', 'label', 'twentysixteen' );
			?>
		</span>
		<input type="search" class="search-field" placeholder="<?php echo esc_attr_x( 'Search &hellip;', 'placeholder', 'twentysixteen' ); ?>" value="<?php echo get_search_query(); ?>" name="s" />
	</label>
	<button type="submit" class="search-submit"><span class="screen-reader-text">
		<?php
		/* translators: Hidden accessibility text. */
		echo _x( 'Search', 'submit button', 'twentysixteen' );
		?>
	</span></button>
</form>
<?php if ( $twentysixteen_wrap_in_search ) : ?>
</search>
<?php endif; ?>
