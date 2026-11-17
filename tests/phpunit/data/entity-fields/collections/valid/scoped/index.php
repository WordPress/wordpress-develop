<?php
/**
 * A collection whose files reuse the variables of the loader as locals.
 *
 * @package WordPress
 */

$directory = __DIR__ . '/scoped';
$slug      = basename( $directory );

return array(
	'origin' => 'fixture',
	'kind'   => 'postType',
	'name'   => 'test_' . $slug,
);
