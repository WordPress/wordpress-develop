<?php
/**
 * A field whose file reuses the variables of the loader as locals.
 *
 * @package WordPress
 */

$file   = basename( __DIR__ );
$fields = array( 'draft', 'publish' );

$elements = array();
foreach ( $fields as $value ) {
	$elements[] = array(
		'value' => $value,
		'label' => ucfirst( $value ),
	);
}

return array(
	'type'     => 'text',
	'label'    => ucfirst( $file ),
	'elements' => $elements,
);
