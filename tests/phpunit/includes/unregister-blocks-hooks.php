<?php

// Unhook block registration functions to prevent _doing_it_wrong warnings
// when tests re-trigger the init action. See _unhook_block_registration().
$blocks_dir = ABSPATH . WPINC . '/blocks/';
foreach ( glob( $blocks_dir . '*.php' ) as $block_file ) {
	$block_name = basename( $block_file, '.php' );

	if ( ! is_dir( $blocks_dir . $block_name ) ) {
		continue;
	}

	remove_action( 'init', 'register_block_core_' . str_replace( '-', '_', $block_name ) );
}
