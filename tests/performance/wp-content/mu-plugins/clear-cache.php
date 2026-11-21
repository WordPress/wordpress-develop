<?php

add_action(
	'plugins_loaded',
	static function () {
		if ( isset( $_GET['clear_cache'] ) ) {
			if ( function_exists( 'opcache_reset' ) ) {
				opcache_reset();
			}

			if ( function_exists( 'apcu_clear_cache' ) ) {
				apcu_clear_cache();
			}

			wp_cache_flush();

			delete_expired_transients( true );

			clearstatcache( true );

			status_header( 202 );

			die;
		}
	},
	1
);
