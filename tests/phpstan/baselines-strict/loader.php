<?php
/**
 * Includes every PHPStan baseline in this directory.
 *
 * A configuration includes this file rather than listing the baselines one by
 * one. `composer phpstan:baselines` writes it on every run, so it is not to be
 * edited by hand.
 *
 * @see \WordPress\PHPStan\get_baseline_includes()
 *
 * @package WordPress
 */

require_once __DIR__ . '/../baseline-includes.php';

return \WordPress\PHPStan\get_baseline_includes( __DIR__ );
