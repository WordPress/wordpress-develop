<?php
/**
 * Includes every PHPStan baseline in this directory.
 *
 * `phpstan.neon.dist` includes this file rather than listing the baselines one by
 * one, so that adding a baseline, or deleting one that has reached zero, needs no
 * edit of the configuration. A NEON configuration can only include files by name,
 * but a PHP one returns the same structure and can find them itself.
 *
 * PHPStan keys its cache of the configuration on the files it includes, so a
 * baseline added or deleted here takes effect on the next run.
 *
 * `composer phpstan:baselines` sets WP_PHPSTAN_GENERATING_BASELINES while it
 * analyzes, so that the errors the baselines suppress are reported again for it
 * to write back out. Checking it here suppresses them however the configuration
 * includes this file, even through another configuration that includes
 * `phpstan.neon.dist`.
 *
 * @package WordPress
 */

if ( false !== getenv( 'WP_PHPSTAN_GENERATING_BASELINES' ) ) {
	return array( 'includes' => array() );
}

$baselines = glob( __DIR__ . '/*.neon' );

return array(
	'includes' => false === $baselines ? array() : $baselines,
);
