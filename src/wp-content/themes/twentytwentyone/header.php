<?php
/**
 * The header.
 *
 * This is the template that displays all of the <head> section and everything up until main.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

?>
<!doctype html>
<html <?php language_attributes(); ?> <?php twentytwentyone_the_html_classes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php wp_head(); ?>
</head>

<body
	<?php body_class(); ?>
	data-wp-interactive='{"namespace": "twentytwentyone"}'
	data-wp-class--primary-navigation-open="state.isPrimaryMenuOpen"
	data-wp-class--lock-scrolling="state.isPrimaryMenuOpen"
	data-wp-class--is-dark-theme="state.isDarkMode"
	data-wp-init--iframes="callbacks.makeIframesResponsive"
	data-wp-on-window--resize="callbacks.makeIframesResponsive"
	data-wp-init--darkmode="callbacks.initDarkMode"
	data-wp-watch--darkmode-cache="callbacks.storeDarkMode"
	data-wp-watch--darkmode-class="callbacks.refreshHtmlElementDarkMode"
>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#content">
		<?php
		/* translators: Hidden accessibility text. */
		esc_html_e( 'Skip to content', 'twentytwentyone' );
		?>
	</a>

	<?php get_template_part( 'template-parts/header/site-header' ); ?>

	<div id="content" class="site-content">
		<div id="primary" class="content-area">
			<main id="main" class="site-main">
