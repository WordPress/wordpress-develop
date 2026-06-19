<?php
/**
 * JSON Schema API: shared functions for working with JSON Schema.
 *
 * @package WordPress
 * @subpackage JSON_Schema
 * @since 7.1.0
 */

/**
 * Gets the JSON Schema keywords allowed for a given schema profile.
 *
 * Use the returned list to decide which keywords to keep when a schema is
 * output as JSON. Pass 'rest-api' for REST API route output. Pass 'draft-04'
 * for the larger set used when publishing schemas to clients, such as the
 * Abilities API.
 *
 * The keywords are allowed to stay in the schema output. This does not mean
 * WordPress validates or sanitizes values against them.
 *
 * @since 7.1.0
 *
 * @param string $schema_profile Optional. Name of the schema profile to get keywords for.
 *                               Accepts 'rest-api' or 'draft-04'. Any other value falls
 *                               back to the 'rest-api' profile. Default 'rest-api'.
 * @return string[] Allowed JSON Schema keywords.
 */
function wp_get_json_schema_allowed_keywords( string $schema_profile = 'rest-api' ): array {
	$rest_keywords = rest_get_allowed_schema_keywords();

	$keywords_by_profile = array(
		'rest-api' => $rest_keywords,
		'draft-04' => array_merge(
			array(
				'$schema',
				'id',
				'$ref',
			),
			$rest_keywords,
			array(
				'required',
				'allOf',
				'not',
				'definitions',
				'dependencies',
				'additionalItems',
			)
		),
	);

	$allowed_keywords = $keywords_by_profile[ $schema_profile ] ?? $rest_keywords;

	/**
	 * Filters the JSON Schema keywords allowed for a given schema profile.
	 *
	 * Adding a keyword lets it stay in the schema output for that profile.
	 * It does not make WordPress validate or sanitize values against the keyword.
	 *
	 * @since 7.1.0
	 *
	 * @param string[] $allowed_keywords Allowed JSON Schema keywords.
	 * @param string   $schema_profile   The schema profile the keywords are for.
	 */
	return apply_filters( 'wp_json_schema_allowed_keywords', $allowed_keywords, $schema_profile );
}
