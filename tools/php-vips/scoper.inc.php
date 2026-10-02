<?php
/**
 * PHP-Scoper configuration for bundling php-vips dependencies.
 *
 * Scopes the PSR-3 logging interfaces (Psr\Log\*) to WordPress\VipsDependencies\*
 * to avoid conflicts with plugin-bundled versions. The php-vips namespace itself
 * (Jcupitt\Vips\*) is excluded, so it stays usable by plugins that ship the same
 * library.
 *
 * @package WordPress
 */

use Isolated\Symfony\Component\Finder\Finder;

return array(
	'prefix' => 'WordPress\\VipsDependencies',

	'finders' => array(
		// Include all PHP files in vendor (dependencies) so their namespaces get scoped.
		Finder::create()
			->files()
			->ignoreVCS( true )
			->notName( '/LICENSE|.*\\.md|.*\\.dist|Makefile/' )
			->exclude( array( 'composer', 'doc', 'test', 'test_old', 'tests', 'Tests', 'vendor-bin' ) )
			->in( 'vendor' ),

		// Include the php-vips source files so `use` statements referencing scoped
		// dependency namespaces get updated. The php-vips namespace is excluded below,
		// so its `namespace` declarations stay unchanged.
		Finder::create()
			->files()
			->ignoreVCS( true )
			->name( '*.php' )
			->in( 'src' ),
	),

	'exclude-namespaces' => array(
		// The php-vips namespace must not be scoped.
		'Jcupitt\\Vips',
	),

	'exclude-files' => array(),

	'exclude-constants' => array(
		// Preserve WordPress-compatible constants.
		'/^ABSPATH$/',
		'/^WPINC$/',
	),

	'exclude-functions' => array(),

	'patchers' => array(),
);
