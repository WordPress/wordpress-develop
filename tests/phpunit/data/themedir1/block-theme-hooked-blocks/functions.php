<?php

add_action( 'init', 'register_theme_hooked_blocks' );

function register_theme_hooked_blocks() {
	register_block_type( __DIR__ . 'blocks/hooked-before' );
	register_block_type( __DIR__ . 'blocks/hooked-after' );
	register_block_type( __DIR__ . 'blocks/hooked-first-child' );
	register_block_type( __DIR__ . 'blocks/hooked-last-child' );
}
