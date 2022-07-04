<?php
/**
 * Dummy theme.
 */

load_theme_textdomain( 'custom-internationalized-theme', get_template_directory() . '/languages' );

function custom_i18n_theme_test() {
	return __( 'This is a dummy theme', 'custom-internationalized-theme' );
}
