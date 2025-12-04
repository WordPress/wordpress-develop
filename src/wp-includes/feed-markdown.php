<?php
/**
 * Markdown Feed Template for displaying posts as Markdown text.
 *
 * Accessible via /?feed=markup or /feed/markup/ depending on permalink settings.
 *
 * @package WordPress
 * @since 6.7.0
 */

// Output Markdown so clients can render appropriately.
header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ), true );

// Ensure full content is used.
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Used intentionally to ensure full content in feed.
$more = 1;

// Register a simple autoloader for the bundled League HTMLToMarkdown library.
spl_autoload_register( function ( $class ) {
	if ( 0 === strpos( $class, 'League\\HTMLToMarkdown\\' ) ) {
		$relative = str_replace( 'League\\HTMLToMarkdown\\', '', $class );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file = ABSPATH . WPINC . '/html-to-markdown/' . $relative . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
} );

// Create a converter instance; tune options if desired.
$__wp_md_converter = new \League\HTMLToMarkdown\HtmlConverter( array(
	'header_style' => 'atx',
	'suppress_errors' => true,
) );

// Feed header as Markdown.
echo '# ' . wp_strip_all_tags( get_bloginfo( 'name' ) ) . ' — ' . __( 'Markdown Feed', 'default' ) . "\n\n";
$desc = get_bloginfo( 'description' );
if ( $desc ) {
	echo wp_strip_all_tags( $desc ) . "\n\n";
}

echo __( 'Feed URL:', 'default' ) . ' <' . esc_url_raw( get_self_link() ) . ">\n\n";

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$title     = wp_strip_all_tags( get_the_title() );
		$permalink = get_permalink();
		$date_r    = get_post_time( 'r', true );
		$content   = get_post_field( 'post_content', get_the_ID() );

		// Post block in Markdown.
		echo "## [" . $title . "]("
			. $permalink . ")\n";
		echo '*' . __( 'Published:', 'default' ) . '* ' . $date_r . "\n\n";

		$html = apply_filters( 'the_content', $content );
		$md   = $__wp_md_converter->convert( (string) $html );
		$md   = trim( $md );
		if ( $md !== '' ) {
			echo $md . "\n\n";
		}

		// Separator.
		echo "---\n\n";
	endwhile;
else :
	echo __( 'No posts found.', 'default' );
endif;
