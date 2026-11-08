<?php

add_action( 'init', 'register_theme_blocks' );

function register_theme_blocks() {
	register_block_type( __DIR__ . 'blocks/example-block' );
}
