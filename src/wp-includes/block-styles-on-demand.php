<?php
/**
 * 
 * Load Block Styles on Command in Classic Themes
 * 
 * Delaying the output of CSS until after the content is loaded, this way we know what blocks will be on the page, and only load the needed CSS.
 *
 * @package WordPress
 * @subpackage Theme_Compat
 * @since 6.9
 */
function always_load_block_styles_on_demand_init() {
    if ( wp_is_block_theme() || ! function_exists( 'wp_should_output_buffer_template_for_enhancement' ) ) {
        return;
    }
	/*
	 * Make sure that wp_should_output_buffer_template_for_enhancement() returns true even if there aren't any
	 * `wp_template_enhancement_output_buffer` filters added, but do so at priority zero so that applications which
	 * wish to stream responses can more easily turn this off.
	 */
	add_filter( 'wp_should_output_buffer_template_for_enhancement', '__return_true', 0 );

	if ( ! wp_should_output_buffer_template_for_enhancement() ) {
		return;
	}

	// Load separate block styles so that the large block-library stylesheet is not enqueued unconditionally,
	// and so that block-specific styles will only be enqueued when they are used on the page.
	add_filter( 'should_load_separate_core_block_assets', '__return_true' );

	// Also ensure that block assets are loaded on demand (although the default value is should_load_separate_core_block_assets).
	add_filter( 'should_load_block_assets_on_demand', '__return_true' );

	// Add hooks which require the presence of the output buffer. Ideally the above two filters could be added here, but they run too early.
	add_action( 'wp_template_enhancement_output_buffer_started','add_hooks_for_output_buffer' );
}

add_action( 'after_setup_theme', 'always_load_block_styles_on_demand_init' );

/**
 * Adds hooks for the output buffer.
 */
function add_hooks_for_output_buffer(): void {

	// While normally late styles are printed, there is a filter to disable late styles, so this makes sure they are printed.
	add_filter( 'print_late_styles', '__return_true', 100 );

	// Print a placeholder comment to inject late styles right after the head styles are printed.
	$placeholder = sprintf( '<!--%s:%s-->', 'late_styles', wp_generate_uuid4() );
	remove_action( 'wp_head', 'wp_print_styles', 8 );
	add_action(
		'wp_head',
		static function () use ( $placeholder ) {
			wp_print_styles();
			echo $placeholder;
		},
		8
	);

	// Replace logic that prints scripts and styles in the footer.
	$late_styles = '';
	remove_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
	add_action(
		'wp_print_footer_scripts',
		static function () use ( &$late_styles ) {
			ob_start();
			print_late_styles();
			$late_styles = ob_get_clean();

			print_footer_scripts();
		}
	);

	// Replace placeholder with the captured late styles.
	add_filter(
		'wp_template_enhancement_output_buffer',
		static function ( $buffer ) use ( $placeholder, &$late_styles ) {
			return str_replace( $placeholder, $late_styles, $buffer );
		}
	);
}
