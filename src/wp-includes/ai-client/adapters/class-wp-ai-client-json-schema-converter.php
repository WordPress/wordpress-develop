<?php
/**
 * WP AI Client: WP_AI_Client_JSON_Schema_Converter class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

/**
 * Converter for JSON Schema to WordPress REST API Schema.
 *
 * @since 7.0.0
 */
class WP_AI_Client_JSON_Schema_Converter {

	/**
	 * Converts a standard JSON Schema to a WordPress REST API compatible schema.
	 *
	 * Specifically, this converts the "required" array property to "required" boolean attributes
	 * on individual properties, as expected by WordPress REST API validation.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string, mixed> $schema The standard JSON schema.
	 * @return array<string, mixed> The WordPress compatible schema.
	 */
	public static function convert( array $schema ): array {
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			$required_props = isset( $schema['required'] ) && is_array( $schema['required'] )
				? $schema['required']
				: array();

			// Remove the required array from the parent object.
			unset( $schema['required'] );

			foreach ( $schema['properties'] as $prop_name => $prop_schema ) {
				if ( ! is_array( $prop_schema ) ) {
					continue;
				}

				/** @var array<string, mixed> $prop_schema */
				$schema['properties'][ $prop_name ] = self::convert( $prop_schema );

				// Set required boolean if property is in required array.
				if ( in_array( $prop_name, $required_props, true ) ) {
					$schema['properties'][ $prop_name ]['required'] = true;
				}
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			/** @var array<string, mixed> $items */
			$items = $schema['items'];

			$schema['items'] = self::convert( $items );
		}

		// Handle oneOf, anyOf, allOf.
		foreach ( array( 'oneOf', 'anyOf', 'allOf' ) as $combiner ) {
			if ( isset( $schema[ $combiner ] ) && is_array( $schema[ $combiner ] ) ) {
				foreach ( $schema[ $combiner ] as $index => $sub_schema ) {
					if ( ! is_array( $sub_schema ) ) {
						continue;
					}

					/** @var array<string, mixed> $sub_schema */
					$schema[ $combiner ][ $index ] = self::convert( $sub_schema );
				}
			}
		}

		return $schema;
	}
}
