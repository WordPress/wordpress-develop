<?php
/**
 * Fixture for WpdbQueryDynamicMethodReturnTypeExtension, where the core tables may have more columns than the core
 * schema, as where `wpdbTablesMatchSchema` is false.
 *
 * @package WordPress
 */

declare(strict_types=1);

use function PHPStan\Testing\assertType;

/**
 * A `*` over a core table may select columns which are not in the schema, so it is not expanded, but the columns
 * which are named are still typed from the schema.
 */
function wp_tests_wpdb_tables_not_matching_schema( wpdb $wpdb ): void {
	assertType( 'stdClass|null', $wpdb->get_row( "SELECT * FROM $wpdb->posts" ) );
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT t.* FROM $wpdb->terms t", ARRAY_A ) );
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT t.*, tt.taxonomy FROM $wpdb->terms t INNER JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id", ARRAY_A ) );
	assertType( 'array{ID: decimal-int-string, post_title: string}|null', $wpdb->get_row( "SELECT ID, post_title FROM $wpdb->posts", ARRAY_A ) );
}
