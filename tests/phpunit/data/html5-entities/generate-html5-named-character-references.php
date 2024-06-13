<?php

require_once __DIR__ . '/../../../../src/wp-includes/class-wp-token-map.php';

/**
 * Stores a mapping from HTML5 named character reference to its transformation metadata.
 *
 * Example:
 *
 *     $entities['&copy;'] === array(
 *         'codepoints' => array( 0xA9 ),
 *         'characters' => '©',
 *     );
 *
 * @see https://html.spec.whatwg.org/entities.json
 *
 * @var array.
 */
$entities = json_decode(
	file_get_contents( __DIR__ . '/entities.json' ),
	JSON_OBJECT_AS_ARRAY
);

/**
 * Direct mapping from character reference name to UTF-8 string.
 *
 * Example:
 *
 *     $character_references['&copy;'] === '©';
 *
 * @var array.
 */
$character_references = array();
foreach ( $entities as $reference => $metadata ) {
	$reference_without_ampersand_prefix                          = substr( $reference, 1 );
	$character_references[ $reference_without_ampersand_prefix ] = $metadata['characters'];
}

$html5_map = WP_Token_Map::from_array( $character_references );

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
 * Auto-generated class for looking up HTML named character references.
 *
 * ⚠️ !!! THIS ENTIRE FILE IS AUTOMATICALLY GENERATED !!! ⚠️
 * Do not modify this file directly.
 *
 * To regenerate, run the generation script directly.
 *
 * Example:
 *
 *     php tests/phpunit/data/html5-entities/generate-html5-named-character-references.php
 *
 * @package WordPress
 * @since 6.6.0
 */

// phpcs:disable

global \$html5_named_character_references;

/**
 * Set of named character references in the HTML5 specification.
 *
 * This list will never change, according to the spec. Each named
 * character reference is case-sensitive and the presence or absence
 * of the semicolon is significant. Without the semicolon, the rules
 * for an ambiguous ampersand govern whether the following text is
 * to be interpreted as a character reference or not.
 *
 * The list of entities is sourced directly from the WHATWG server
 * and cached in the test directory to avoid needing to download it
 * every time this file is updated.
 *
 * @link https://html.spec.whatwg.org/entities.json.
 */
\$html5_named_character_references = {$html5_map->precomputed_php_source_table()};

EOF;

file_put_contents(
	__DIR__ . '/../../../../src/wp-includes/html-api/html5-named-character-references.php',
	$module_contents
);

if ( posix_isatty( STDOUT ) ) {
	echo "\e[1;32mOK\e[0;90m: \e[mSuccessfully generated optimized lookup class.\n";
} else {
	echo "OK: Successfully generated optimized lookup class.\n";
}
