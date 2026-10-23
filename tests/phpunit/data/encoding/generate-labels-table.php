<?php

function generate_charset_labels_table() {
	$use_color = function_exists( '\posix_isatty' ) && \posix_isatty( STDOUT );

	/**
	 * Stores a mapping from character set labels to supported name.
	 *
	 * The encodings are grouped by heading for logical purposes.
	 *
	 * Example:
	 *
	 *     $encoding = $encodings[0]['encodings'][0];
	 *     $encoding['name']   === 'UTF-8';
	 *     $encoding['labels'] === array( 'unicode-1-1-utf-8', …, 'utf-8', 'utf8', … );
	 *
	 * @link https://https://encoding.spec.whatwg.org/encodings.json
	 *
	 * @var $encoding_data list<{heading: string, encodings: list<{name: string, labels: string[]}>}>.
	 */
	$encoding_data = \json_decode(
		\file_get_contents( __DIR__ . '/encodings.json' ),
		JSON_OBJECT_AS_ARRAY
	);

	$mappings = array();

	$longest_name = 0;
	foreach ( $encoding_data as $encoding ) {
		foreach( $encoding['encodings'] as $name_labels ) {
			$mappings[ $name_labels['name'] ] = $name_labels['labels'];

			$longest_name = \max( $longest_name, \strlen( $name_labels['name'] ) );
		}
	}

	$printed_mappings = "array(\n";
	foreach ( $mappings as $name => $labels ) {
		if ( \strlen( $name ) !== \strcspn( $name, " \t\f\r\n;" ) ) {
			if ( $use_color ) {
				echo "\e[31mERROR\e[90m: Encoding name contains unsupported characters '\e[34m{$name}\e[90m': skipping…\e[m\n";
			} else {
				echo "ERROR: Encoding name contains unsupported characters '\{$name}': skipping…\n";
			}

			continue;
		}

		foreach ( $labels as $label ) {
			if ( \strlen( $label ) !== \strcspn( $label, " \t\f\r\n;" ) ) {
				if ( $use_color ) {
					echo "\e[31mERROR\e[90m: Encoding name contains unsupported characters '\e[34m{$label}\e[90m': skipping…\e[m\n";
				} else {
					echo "ERROR: Encoding name contains unsupported characters '\{$label}': skipping…\n";
				}

				continue 2;
			}
		}

		$joined_labels     = \implode( ' ', $labels );
		$name_padding      = \str_pad( '', $longest_name - \strlen( $name ), ' ' );
		$printed_mappings .= "\t'{$name}'{$name_padding} => ' {$joined_labels} ',\n";
	}
	$printed_mappings .= ")";

	/**
	 * Contains the new contents for the auto-generated module.
	 *
	 * Note that in this template, the `$` is escaped with `\$` so that it
	 * comes through as a `$` in the output. Without escaping, PHP will look
	 * for a variable of the given name to interpolate into the template.
	 *
	 * @var string
	 */
	$module_contents = <<<EOF
	<?php

	/**
	 * Auto-generated class for mapping character set labels to supported names.
	 *
	 * ⚠️ !!! THIS ENTIRE FILE IS AUTOMATICALLY GENERATED !!! ⚠️
	 * Do not modify this file directly.
	 *
	 * To regenerate, run the generation script directly.
	 *
	 * Example:
	 *
	 *     php tests/phpunit/data/encoding/generate-labels-table.php
	 *
	 * @package WordPress
	 * @since 7.2.0
	 */

	// phpcs:disable

	/**
	 * Mapping from encoding name to space-separated list of its labels.
	 *
	 * > The table below lists all encodings and their labels user agents must
	 * > support. User agents must not support any other encodings or labels.
	 *
	 * Every label will be surrounded on each side by spaces, as labels containing
	 * spaces are rejected. This allows for efficient string lookup.
	 *
	 * @see \generate_charset_labels_table()
	 *
	 * @since 7.2.0
	 *
	 * @type array<non-empty-string, non-empty-string>
	 */
	return {$printed_mappings};

	EOF;

	\file_put_contents(
		__DIR__ . '/../../../../src/wp-includes/wp-supported-encodings.php',
		$module_contents
	);

	if ( $use_color ) {
		echo "\e[1;32mOK\e[0;90m: \e[mSuccessfully generated character set mapping.\n";
	} else {
		echo "OK: Successfully generated character set mapping.\n";
	}
}

\generate_charset_labels_table();
