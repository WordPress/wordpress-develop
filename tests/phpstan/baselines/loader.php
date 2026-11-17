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
 * `composer phpstan:baselines` sets WP_PHPSTAN_GENERATING_BASELINES to 1 while
 * it analyzes, so that the errors the baselines suppress are reported again for
 * it to write back out. Checking it here suppresses them however the
 * configuration includes this file, even through another configuration that
 * includes `phpstan.neon.dist`. Any other value, such as 0 or an empty string,
 * leaves the baselines in force, so that a variable left set by mistake cannot
 * silently drop them all.
 *
 * @package WordPress
 */

if ( '1' === getenv( 'WP_PHPSTAN_GENERATING_BASELINES' ) ) {
	return array( 'includes' => array() );
}

$found = glob( __DIR__ . '/*.neon' );
if ( false === $found ) {
	$found = array();
}

/*
 * Only a file named for an error identifier is a baseline, matched with the
 * pattern PHPStan validates identifiers with, so that a stray file such as a
 * merge tool's `argument.type_BACKUP_1234.neon` is not loaded along with the
 * real one. find_baselines() in `generate-baselines.php` matches the same names.
 */
$baselines = array();
foreach ( $found as $file ) {
	if ( 1 === preg_match( '/^[a-zA-Z0-9](?:[a-zA-Z0-9.]*[a-zA-Z0-9])?\.neon$/', basename( $file ) ) ) {
		$baselines[] = $file;
	}
}

return array(
	'includes' => $baselines,
);
