<?php

add_action(
	'template_redirect',
	function() {
		ob_start();

		global $server_timing_values, $timestart;

		if ( ! is_array( $server_timing_values ) ) {
			$server_timing_values = array();
		}

		$server_timing_values['before-template'] = microtime( true ) - $timestart;

		add_action(
			'shutdown',
			function() {

				global $server_timing_values, $timestart;

				if ( ! is_array( $server_timing_values ) ) {
					$server_timing_values = array();
				}

				$server_timing_values['template'] = microtime( true ) - $timestart;

				$server_timing_values['total'] = $server_timing_values['before-template'] + $server_timing_values['template'];

				$output = ob_get_clean();

				$header_values = array();
				foreach ( $server_timing_values as $slug => $value ) {
					if ( is_float( $value ) ) {
						$value = round( $value * 1000.0, 2 );
					}
					$header_values[] = sprintf( 'wp-%1$s;dur=%2$s', $slug, $value );
				}
				header( 'Server-Timing: ' . implode( ', ', $header_values ) );

				echo $output;
			},
			-9999
		);
	},
	-9999
);
