<?php

function classic_with_presets_after_setup_theme() {
	add_theme_support(
		'editor-font-sizes',
		array(
			array(
				'name'      => 'Small',
				'size'      => 18,
				'slug'      => 'small',
			),
			array(
				'name'      => 'Large',
				'size'      => 26.25,
				'slug'      => 'large',
			),
		)
	);
}
add_action( 'after_setup_theme', 'classic_with_presets_after_setup_theme' );
