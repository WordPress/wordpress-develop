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
 * @package WordPress
 */

$baselines = glob( __DIR__ . '/*.neon' );

return array(
	'includes' => false === $baselines ? array() : $baselines,
);
