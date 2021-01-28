<?php
/*
 * Plugin Name: Hello Dolly
 * Author: Matt Mullenweg
 * Description: This is not Hello Dolly, and should not be detected as such, but the above headers were copy-pasted.
 * Version: 0.1
 * Update URI: dd32.id.au/example-hello-dolly
*/

add_filter( 'update_plugins_' . 'dd32.id.au', function( $update, $headers, $file, $locales ) {
	if ( 'dd32.id.au/example-hello-dolly' === $headers['UpdateURI'] ) {
		return [
			//'slug'       => $headers['UpdateURI'], // Make sure to filter the plugin details iframe for this value.
			'version'      => '2.0',
			'url'          => 'https://dd32.id.au/example-hello-dolly',
			'package'      => 'https://example.com/example-404.zip',
			'tested'       => '5.7',
			'requires_php' => '8.0'
		];
	}

	return $update;
}, 10, 4 );