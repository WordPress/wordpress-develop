<?php

$path = '/var/xhgui/vendor/autoload.php';

// Bail early if XHGui is not installed.
if ( ! file_exists( $path ) || ! defined( 'WP_PHP_XHPROF' ) || ! WP_PHP_XHPROF ) {
	return;
}

require_once $path;



use Xhgui\Profiler\Profiler;
use Xhgui\Profiler\ProfilingFlags;

// Add this block inside some bootstrapper or other "early central point in execution"
try {

	/**
	 * The constructor will throw an exception if the environment
	 * isn't fit for profiling (extensions missing, other problems)
	 */
	$profiler = new Profiler(
		array(
			'save.handler.upload' => array(
				// Use docker's internal networking to connect containers.
				'url' => 'http://host.docker.internal:8142/run/import',
			),
			'profiler.flags'      => array(
				ProfilingFlags::CPU,
				ProfilingFlags::MEMORY,
				// Uncomment the following line to ignore built in PHP functions.
				// ProfilingFlags::NO_BUILTINS,
			),
		)
	);

	// The profiler itself checks whether it should be enabled
	// for request (executes lambda function from config)
	$profiler->start();
} catch ( Exception $e ) {
	// throw away or log error about profiling instantiation failure
	error_log( $e->getMessage() );
}
