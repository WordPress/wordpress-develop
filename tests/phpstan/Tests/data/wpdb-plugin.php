<?php
/**
 * Fixture for WpdbQueryDynamicMethodReturnTypeExtension as configured for a plugin or theme, in tests-plugin.neon.
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

/**
 * A column declared for a core table is typed as declared, but a `*` over the table is still not expanded, since other
 * plugins may add other columns.
 */
function wp_tests_wpdb_declared_columns( wpdb $wpdb ): void {
	assertType( 'array{name: string, term_order: decimal-int-string|null}|null', $wpdb->get_row( "SELECT t.name, t.term_order FROM $wpdb->terms t", ARRAY_A ) );
	assertType( 'list<decimal-int-string|null>', $wpdb->get_col( "SELECT term_order FROM $wpdb->terms ORDER BY term_order" ) );
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT * FROM $wpdb->terms", ARRAY_A ) );
}

/**
 * A declared table is named by its `wpdb` property or by its name after the prefix, and a `*` over it is expanded to
 * the columns declared.
 */
function wp_tests_wpdb_declared_tables( wpdb $wpdb, string $unknown ): void {
	assertType( 'array{id: decimal-int-string, status: string, total: numeric-string|null, date_created_gmt: non-falsy-string|null}|null', $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wp_tests_orders", ARRAY_A ) );
	assertType( 'array{decimal-int-string, string, numeric-string|null, non-falsy-string|null}|null', $wpdb->get_row( "SELECT * FROM {$wpdb->base_prefix}wp_tests_orders", ARRAY_N ) );
	assertType( 'array{id: decimal-int-string, status: string}|null', $wpdb->get_row( "SELECT id, o.status FROM `{$wpdb->prefix}wp_tests_orders` AS o", ARRAY_A ) );
	assertType( 'array{status: string}|null', $wpdb->get_row( 'SELECT status FROM ' . $wpdb->prefix . 'wp_tests_orders', ARRAY_A ) );
	assertType( 'array{id: decimal-int-string, status: string}|null', $wpdb->get_row( "SELECT {$wpdb->prefix}wp_tests_orders.id, status FROM {$wpdb->prefix}wp_tests_orders", ARRAY_A ) );
	assertType( 'list<decimal-int-string>', $wpdb->get_col( "SELECT id FROM $wpdb->wp_tests_orders" ) );

	// Joined to a core table, on the outer side of a join.
	assertType( 'array{ID: decimal-int-string, status: string|null}|null', $wpdb->get_row( "SELECT p.ID, o.status FROM $wpdb->posts p LEFT JOIN {$wpdb->prefix}wp_tests_orders o ON o.id = p.ID", ARRAY_A ) );

	// A core table by its name after the prefix.
	assertType( 'list<decimal-int-string>', $wpdb->get_col( "SELECT ID FROM {$wpdb->prefix}posts" ) );

	// A table which is not declared, or whose name is not known.
	assertType( 'array{id: string|null}|null', $wpdb->get_row( "SELECT id FROM {$wpdb->prefix}wp_tests_other", ARRAY_A ) );
	assertType( 'array{id: string|null}|null', $wpdb->get_row( "SELECT id FROM {$wpdb->prefix}wp_tests_orders{$unknown}", ARRAY_A ) );
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}{$unknown}", ARRAY_A ) );
}
