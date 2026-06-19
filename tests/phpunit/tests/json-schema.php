<?php
/**
 * JSON Schema functions.
 *
 * @package WordPress
 * @subpackage JSON_Schema
 */

/**
 * @group json-schema
 */
class Tests_JSON_Schema extends WP_UnitTestCase {

	/**
	 * @ticket 64955
	 */
	public function test_wp_get_json_schema_allowed_keywords_uses_rest_keywords_by_default() {
		$this->assertSame( rest_get_allowed_schema_keywords(), wp_get_json_schema_allowed_keywords() );
		$this->assertSame( rest_get_allowed_schema_keywords(), wp_get_json_schema_allowed_keywords( 'rest-api' ) );
		$this->assertSame( rest_get_allowed_schema_keywords(), wp_get_json_schema_allowed_keywords( 'unknown-context' ) );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_get_json_schema_allowed_keywords_includes_draft_04_keywords() {
		$keywords = wp_get_json_schema_allowed_keywords( 'draft-04' );

		// Keywords the draft-04 profile adds on top of the REST keyword set.
		foreach ( array( '$schema', 'id', '$ref', 'required', 'allOf', 'not', 'definitions', 'dependencies', 'additionalItems' ) as $keyword ) {
			$this->assertContains( $keyword, $keywords );
		}

		// 'type' is a base REST keyword, not a draft-04 addition. Checking it
		// confirms the draft-04 profile is a superset that keeps the REST keywords.
		$this->assertContains( 'type', $keywords );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_get_json_schema_allowed_keywords_filter_receives_schema_profile() {
		$schema_profiles = array();
		$filter          = static function ( $keywords, $schema_profile ) use ( &$schema_profiles ) {
			$schema_profiles[] = $schema_profile;
			$keywords[]        = 'xCustomKeyword';

			return $keywords;
		};

		add_filter( 'wp_json_schema_allowed_keywords', $filter, 10, 2 );
		$keywords = wp_get_json_schema_allowed_keywords( 'draft-04' );

		$this->assertContains( 'xCustomKeyword', $keywords );
		$this->assertSame( array( 'draft-04' ), $schema_profiles );
	}
}
