<?php
/**
 * WordPress Bits system, for replacing placeholder tokens in a document,
 * for semantically independent content types, with externally-sourced data.
 *
 * @package    WordPress
 * @subpackage Bits
 * @since      {WP_VERSION}
 */

/**
 * Hello Dolly Bit Rendering function.
 *
 * @param string     $name        Fully-qualified name of the matched Bit.
 *                                E.g. "core/post-author".
 * @param string     $output_type Either "rich-text" or "plaintext" depending on where the Bit is found.
 *                                E.g. when inside an HTML attribute or TITLE element, only plaintext is allowed,
 *                                But when found inside a P element, rich formatting is allowed.
 * @param array|null $attributes  Configured parameters of the Bit, if provided.
 *                                E.g. `<//wp-bit:hello-dolly year="2024">` produces `array( 'year' => '2024' )`.
 * @param mixed      $context     Context passed into the Bit from the surrounding system.
 *                                This argument is not yet specified and will always be `null`.
 *
 * @return mixed An HTML template for rendering into the page, either as a plain string or in array form.
 */
function core_bit_hello_dolly( string $name, string $output_type, ?array $attributes, mixed $context ): mixed {
	static $vocalists = array(
		'Julie Dahle Aagård',
		'Mindi Abair',
		'Lorez Alexandria',
		'Karrin Allyson',
		'Michelle Amato',
		'Ernestine Anderson',
		'Ivie Anderson',
	);

	$vocalist = $vocalists[ wp_rand( 0, count( $vocalists ) - 1 ) ];

	switch ( $output_type ) {
		case 'plaintext':
			return $vocalist;

		case 'rich-text':
			return array(
				'<span data-vocalist="</%name>"></%name></span>',
				array( 'name' => $vocalist ),
			);
	}
}

