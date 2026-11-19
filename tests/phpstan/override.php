<?php
/**
 * Enables PHPStan's check for methods that override a parent or interface method but are
 * missing the `#[\Override]` attribute.
 *
 * PHPStan running on PHP 7.4 parses `#[\Override]` as a comment, so it can only validate the
 * attribute on PHP 8.
 *
 * @package WordPress
 */

return array(
	'parameters' => array(
		'checkMissingOverrideMethodAttribute' => PHP_VERSION_ID >= 80000,
	),
);
