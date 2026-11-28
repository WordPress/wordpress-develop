<?php
/**
 * Fixture for WpdbQueryVisitor and WpdbQueryDynamicMethodReturnTypeExtension.
 *
 * @package WordPress
 */

declare(strict_types=1);

use function PHPStan\Testing\assertType;

/**
 * The query methods, with each output format.
 *
 * `get_var()` gives null for an empty string, and `get_col()` gets each value
 * with `get_var()`, so neither gives an empty string. Each assertion is of a
 * column which is never empty, so that it differs from the declared type.
 */
function wp_tests_wpdb_methods( wpdb $wpdb, int $id ): void {
	assertType( '(object{ID: decimal-int-string, post_title: string}&stdClass)|null', $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_title FROM $wpdb->posts WHERE ID = %d", $id ) ) );
	assertType( 'array{option_name: string, option_value: string}|null', $wpdb->get_row( "SELECT option_name, option_value FROM $wpdb->options", ARRAY_A ) );
	assertType( 'array{string, string}|null', $wpdb->get_row( "SELECT option_name, option_value FROM $wpdb->options", ARRAY_N ) );
	assertType( '(list<object{option_name: string, option_value: string}&stdClass>)|null', $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options" ) );
	assertType( 'list<array{option_name: string, option_value: string}>', $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options", ARRAY_A ) );
	assertType( 'list<array{string, string}>', $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options", ARRAY_N ) );
	assertType( 'array<int, object{ID: decimal-int-string, post_name: string}&stdClass>', $wpdb->get_results( "SELECT ID, post_name FROM $wpdb->posts", OBJECT_K ) );
	assertType( 'non-falsy-string|null', $wpdb->get_var( "SELECT post_date FROM $wpdb->posts" ) );
	assertType( 'decimal-int-string|null', $wpdb->get_var( "SELECT post_title, ID FROM $wpdb->posts", 1 ) );
	assertType( 'null', $wpdb->get_var( "SELECT ID, post_title FROM $wpdb->posts", 2 ) );
	assertType( 'decimal-int-string|non-falsy-string|null', $wpdb->get_var( "SELECT ID, post_date FROM $wpdb->posts", $id ) );
	assertType( 'list<decimal-int-string>', $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta" ) );
	assertType( 'list<non-falsy-string>', $wpdb->get_col( "SELECT post_title, post_date FROM $wpdb->posts", 1 ) );
	assertType( 'list<decimal-int-string|null>', $wpdb->get_col( "SELECT ID, post_parent FROM $wpdb->posts", $id ) );
}

/**
 * `query()` gives true for a few statements, and the number of rows otherwise.
 */
function wp_tests_wpdb_query( wpdb $wpdb, int $id, string $unknown ): void {
	assertType( 'bool', $wpdb->query( "CREATE TABLE {$wpdb->prefix}custom ( id bigint(20) )" ) );
	assertType( 'bool', $wpdb->query( "  alter TABLE $wpdb->posts ADD extra text" ) );
	assertType( 'bool', $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}custom" ) );
	assertType( 'bool', $wpdb->query( "DROP TABLE IF EXISTS $unknown" ) );
	assertType( 'int<0, max>|false', $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d", $id ) ) );
	assertType( 'int<0, max>|false', $wpdb->query( "UPDATE $wpdb->posts SET post_status = 'draft'" ) );
	assertType( 'int<0, max>|false', $wpdb->query( "SELECT ID FROM $wpdb->posts" ) );
	assertType( 'int<0, max>|false', $wpdb->query( "SHOW TABLES LIKE '$unknown'" ) );
	assertType( 'int<0, max>|false', $wpdb->query( 'START TRANSACTION' ) );

	// Statements told apart by `query()` only by a keyword followed by whitespace.
	assertType( 'int<0, max>|false', $wpdb->query( 'DROP' ) );
	assertType( 'int<0, max>|false', $wpdb->query( "/* comment */ DROP TABLE $unknown" ) );

	// Unknown text which could begin the query, or complete a keyword.
	assertType( 'bool|int<0, max>',$wpdb->query( $unknown ) );
	assertType( 'bool|int<0, max>',$wpdb->query( "$unknown FROM $wpdb->posts" ) );
	assertType( 'bool|int<0, max>',$wpdb->query( "DRO$unknown" ) );
	assertType( 'bool|int<0, max>',$wpdb->query( "DROP$unknown" ) );
	assertType( 'int<0, max>|false', $wpdb->query( "DROPS$unknown" ) );

	// Built up in a variable.
	$query  = "DELETE FROM $wpdb->posts";
	$query .= $wpdb->prepare( ' WHERE ID = %d', $id );
	assertType( 'int<0, max>|false', $wpdb->query( $query ) );
}

/**
 * An output format which is not one constant leaves the rows as declared.
 *
 * @param 'ARRAY_A'|'ARRAY_N' $array_output Either array format.
 * @param string              $output       Any format.
 */
function wp_tests_wpdb_output( wpdb $wpdb, string $array_output, string $output ): void {
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT ID FROM $wpdb->posts", $array_output ) );
	assertType( 'list<array<mixed>>', $wpdb->get_results( "SELECT ID FROM $wpdb->posts", $array_output ) );
	assertType( 'array<mixed>|stdClass|null', $wpdb->get_row( "SELECT ID FROM $wpdb->posts", $output ) );
}

/**
 * The columns of core tables are typed from the schema.
 */
function wp_tests_wpdb_schema( wpdb $wpdb, string $unknown ): void {
	// Every column of the table, with those without `NOT NULL` nullable.
	assertType( '(object{signup_id: decimal-int-string, domain: string, path: string, title: string, user_login: string, user_email: string, registered: non-falsy-string, activated: non-falsy-string, active: decimal-int-string, activation_key: string, meta: string|null}&stdClass)|null', $wpdb->get_row( "SELECT * FROM $wpdb->signups" ) );

	// The columns which `users` only has on multisite are optional.
	assertType( '(object{ID: decimal-int-string, user_login: string, user_pass: string, user_nicename: string, user_email: string, user_url: string, user_registered: non-falsy-string, user_activation_key: string, user_status: decimal-int-string, display_name: string, spam?: decimal-int-string, deleted?: decimal-int-string}&stdClass)|null', $wpdb->get_row( "SELECT * FROM $wpdb->users" ) );
	assertType( 'array{decimal-int-string, string, string, string, string, string, non-falsy-string, string, decimal-int-string, string, decimal-int-string, decimal-int-string}|array{decimal-int-string, string, string, string, string, string, non-falsy-string, string, decimal-int-string, string}|null', $wpdb->get_row( "SELECT * FROM $wpdb->users", ARRAY_N ) );
	assertType( 'decimal-int-string|null', $wpdb->get_var( "SELECT * FROM $wpdb->users", 10 ) );

	// The columns of a table which is not a core table are unknown.
	assertType( '(object{foo: string|null, c: decimal-int-string}&stdClass)|null', $wpdb->get_row( "SELECT foo, COUNT(*) AS c FROM {$wpdb->prefix}custom" ) );
	assertType( 'stdClass|null', $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}custom" ) );

	// A core table name extended by more text is another table.
	assertType( '(object{ID: string|null, extra: string|null}&stdClass)|null', $wpdb->get_row( "SELECT ID, extra FROM {$wpdb->posts}_archive" ) );
	assertType( '(object{ID: string|null}&stdClass)|null', $wpdb->get_row( "SELECT ID FROM {$wpdb->posts}{$unknown}" ) );
	assertType( '(object{ID: string|null}&stdClass)|null', $wpdb->get_row( "SELECT ID FROM `{$wpdb->posts}_archive`" ) );

	// A core table in backticks.
	assertType( "array{option_name: string, autoload: string}|null", $wpdb->get_row( "SELECT option_name, o.autoload FROM `$wpdb->options` AS o", ARRAY_A ) );
	assertType( "array{option_value: string}|null", $wpdb->get_row( "SELECT `o`.`option_value` FROM `$wpdb->options` `o`", ARRAY_A ) );

	// Unknown text in the `FROM` clause may join more tables, whose columns `*` would include.
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT * FROM $wpdb->terms $unknown WHERE 1=1", ARRAY_A ) );
	assertType( 'array{term_id: decimal-int-string, name: string, slug: string, term_group: decimal-int-string}|null', $wpdb->get_row( "SELECT t.* FROM $wpdb->terms t $unknown WHERE 1=1", ARRAY_A ) );
	assertType( 'array{name: string}|null', $wpdb->get_row( "SELECT name FROM $wpdb->terms $unknown WHERE 1=1", ARRAY_A ) );
}

/**
 * Select expressions are named and typed as MySQL does.
 */
function wp_tests_wpdb_expressions( wpdb $wpdb ): void {
	assertType( 'decimal-int-string|null', $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ) );
	assertType( "array{'COUNT(*)': decimal-int-string, total: numeric-string|null, 'AVG(menu_order)': numeric-string|null, 'MIN(post_date)': non-falsy-string|null, 'MAX(post_parent)': decimal-int-string|null}|null", $wpdb->get_row( "SELECT COUNT(*), SUM(comment_count) AS total, AVG(menu_order), MIN(post_date), MAX(post_parent) FROM $wpdb->posts", ARRAY_A ) );
	assertType( '(object{term_id: decimal-int-string, name: string, slug: string, term_group: decimal-int-string, tax: string}&stdClass)|null', $wpdb->get_row( "SELECT t.*, tt.taxonomy tax FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id" ) );

	// Date functions, which give NULL only for a value which is not a date or time.
	assertType( "array{year: decimal-int-string, month: decimal-int-string, 'DAYOFMONTH(post_date_gmt)': decimal-int-string, 'HOUR(post_modified)': decimal-int-string}|null", $wpdb->get_row( "SELECT YEAR(post_date) AS `year`, MONTH( post_date ) AS month, DAYOFMONTH(post_date_gmt), HOUR(post_modified) FROM $wpdb->posts", ARRAY_A ) );
	assertType( "array{'YEAR(post_title)': decimal-int-string|null, 'YEAR(NOW())': decimal-int-string|null, 'MONTH(foo)': decimal-int-string|null}|null", $wpdb->get_row( "SELECT YEAR(post_title), YEAR(NOW()), MONTH(foo) FROM $wpdb->posts", ARRAY_A ) );
	assertType( "array{'YEAR(p.post_date)': decimal-int-string, 'MONTH(c.comment_date)': decimal-int-string|null}|null", $wpdb->get_row( "SELECT YEAR(p.post_date), MONTH(c.comment_date) FROM $wpdb->posts p LEFT JOIN $wpdb->comments c ON c.comment_post_ID = p.ID", ARRAY_A ) );
	assertType( "array{'DAYOFWEEK(post_date)': string|null}|null", $wpdb->get_row( "SELECT DAYOFWEEK(post_date) FROM $wpdb->posts", ARRAY_A ) );

	// A column in parentheses is still named and typed by the column.
	assertType( 'array{post_author: decimal-int-string, ID: decimal-int-string, \'(ID) + (1)\': string|null}|null', $wpdb->get_row( "SELECT DISTINCT(post_author), ((p.ID)), (ID) + (1) FROM $wpdb->posts p", ARRAY_A ) );

	// Comments, and a `--` which is not one.
	assertType( "array{ID: decimal-int-string, '5--3': string|null, post_title: string}|null", $wpdb->get_row( "SELECT ID, # post_name,\n 5--3, -- post_date,\n post_title /* , post_type */ FROM $wpdb->posts", ARRAY_A ) );

	// The subtotal rows of `WITH ROLLUP` have NULL for the columns grouped by.
	assertType( 'array{post_type: string|null, c: decimal-int-string|null}|null', $wpdb->get_row( "SELECT post_type, COUNT(*) AS c FROM $wpdb->posts GROUP BY post_type WITH ROLLUP", ARRAY_A ) );

	// Quoted aliases.
	assertType( 'array{id: decimal-int-string, title: string, name: string}|null', $wpdb->get_row( "SELECT ID AS 'id', post_title \"title\", post_name `name` FROM $wpdb->posts", ARRAY_A ) );

	// Literals. Only an integer written as MySQL gives it back is typed as a constant.
	assertType( "array{abc: 'abc', a: string|null, day: '1', 42: '42', '007': string|null, '1.5': string|null, '0x41': string|null, '1e3': string|null}|null", $wpdb->get_row( "SELECT 'abc', 'a' 'b', 1 day, 42, 007, 1.5, 0x41, 1e3 FROM $wpdb->posts", ARRAY_A ) );
	assertType( 'array{string|null, string|null}|null', $wpdb->get_row( "SELECT x'41', _utf8mb4'abc' FROM $wpdb->posts", ARRAY_N ) );

	// Operators, which are not followed by an alias.
	assertType( "array{'post_date + INTERVAL 1 DAY': string|null, 'post_title COLLATE utf8mb4_bin': string|null, 'BINARY post_name': string|null, 'NOT ID': string|null}|null", $wpdb->get_row( "SELECT post_date + INTERVAL 1 DAY, post_title COLLATE utf8mb4_bin, BINARY post_name, NOT ID FROM $wpdb->posts", ARRAY_A ) );

	// A column name repeated, which holds the last value of that name in the first position of it.
	assertType( '(object{ID: decimal-int-string}&stdClass)|null', $wpdb->get_row( "SELECT post_title AS ID, ID FROM $wpdb->posts" ) );
	assertType( 'decimal-int-string|null', $wpdb->get_var( "SELECT post_title AS ID, ID FROM $wpdb->posts" ) );
	assertType( 'array{decimal-int-string, string, string, decimal-int-string, decimal-int-string, string, string, decimal-int-string, decimal-int-string}|null', $wpdb->get_row( "SELECT t.*, tt.* FROM $wpdb->terms t INNER JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id", ARRAY_N ) );
	assertType( 'array<string, object{ID: string}&stdClass>', $wpdb->get_results( "SELECT ID, post_title AS ID FROM $wpdb->posts", OBJECT_K ) );
}

/**
 * Outer joins make the columns of the tables which may have no row nullable.
 */
function wp_tests_wpdb_joins( wpdb $wpdb ): void {
	assertType( '(object{ID: decimal-int-string, post_id: decimal-int-string|null, meta_id: decimal-int-string|null}&stdClass)|null', $wpdb->get_row( "SELECT p.ID, pm.post_id, pm.meta_id FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id" ) );
	assertType( '(object{ID: decimal-int-string|null, meta_id: decimal-int-string}&stdClass)|null', $wpdb->get_row( "SELECT p.ID, pm.meta_id FROM $wpdb->posts p RIGHT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id" ) );
	assertType( '(object{term_id: decimal-int-string|null, meta_id: decimal-int-string|null, meta_key: string|null, meta_value: string|null}&stdClass)|null', $wpdb->get_row( "SELECT t.term_id, tm.* FROM $wpdb->terms t LEFT OUTER JOIN $wpdb->termmeta tm ON t.term_id = tm.term_id" ) );
	assertType( 'decimal-int-string|null', $wpdb->get_var( "SELECT meta_id FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id" ) );
}

/**
 * Unknown text in the select list.
 */
function wp_tests_wpdb_unknown( wpdb $wpdb, string $unknown ): void {
	assertType( 'list<stdClass>|null', $wpdb->get_results( "SELECT $unknown FROM $wpdb->posts" ) );
	assertType( 'decimal-int-string|null', $wpdb->get_var( "SELECT ID, $unknown FROM $wpdb->posts" ) );
	assertType( 'stdClass|null', $wpdb->get_row( "SELECT `$unknown`, ID FROM $wpdb->posts" ) );
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT '$unknown', ID FROM $wpdb->posts", ARRAY_A ) );
	assertType( 'list<stdClass>|null', $wpdb->get_results( $unknown ) );

	// A `sprintf()` conversion is unknown text, which may be any number of values.
	assertType( '(list<object{ID: decimal-int-string, post_parent: decimal-int-string}&stdClass>)|null', $wpdb->get_results( sprintf( "SELECT $wpdb->posts.ID, $wpdb->posts.post_parent FROM $wpdb->posts WHERE ID IN (%s)", $unknown ) ) );
	assertType( 'list<array{option_name: string, option_value: string}>|null', $wpdb->get_results( $wpdb->prepare( sprintf( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name IN (%s)", $unknown ), 'a' ), ARRAY_A ) );
	assertType( 'list<stdClass>|null', $wpdb->get_results( sprintf( "SELECT ID, %s FROM $wpdb->posts", $unknown ) ) );
	assertType( '(object{ID: decimal-int-string, 100%: string|null}&stdClass)|null', $wpdb->get_row( sprintf( "SELECT ID, '100%%' FROM $wpdb->posts" ) ) );

	// A `prepare()` placeholder is a single unknown value.
	assertType( '(object{ID: decimal-int-string, x: string|null}&stdClass)|null', $wpdb->get_row( $wpdb->prepare( "SELECT ID, %s AS x FROM $wpdb->posts WHERE post_title LIKE '100%%'", 'a' ) ) );
	assertType( 'array{decimal-int-string, string|null, string|null}|null', $wpdb->get_row( $wpdb->prepare( "SELECT ID, %d x, %s FROM $wpdb->posts", 1, 'a' ), ARRAY_N ) );
}

/**
 * A query built up in variables is followed.
 */
function wp_tests_wpdb_variables( wpdb $wpdb, int $id, string $unknown, bool $flag ): void {
	$from   = "FROM $wpdb->comments AS r INNER JOIN $wpdb->comments AS n";
	$from  .= ' ON n.comment_ID = r.comment_parent';
	$where  = "WHERE r.comment_type = 'reaction' AND ";
	$where .= $wpdb->prepare( 'r.comment_parent IN (' . $unknown . ')', $id );
	$query  = "SELECT r.comment_parent, COUNT(*) AS reaction_count $from $where GROUP BY r.comment_parent";
	assertType( '(list<object{comment_parent: decimal-int-string, reaction_count: decimal-int-string}&stdClass>)|null', $wpdb->get_results( $query ) );

	// Appended to in a branch.
	$query = "SELECT MAX(post_date) AS latest FROM $wpdb->posts WHERE 1=1";
	if ( $flag ) {
		$query .= ' AND post_author = 1';
	}
	assertType( '(object{latest: non-falsy-string|null}&stdClass)|null', $wpdb->get_row( $query ) );

	// Not followed through a loop which writes to it.
	$query = "SELECT ID FROM $wpdb->posts";
	foreach ( array( 1 ) as $i ) {
		assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );
		$query = $unknown;
	}

	// Not followed past a condition which assigns it.
	$query = "SELECT ID FROM $wpdb->posts";
	if ( $query = get_option( 'query' ) ) {
		assertType( 'stdClass|null', $wpdb->get_row( $query ) );
	}

	// Followed into a branch which does not write to it.
	$query = "SELECT ID FROM $wpdb->posts";
	if ( $flag ) {
		assertType( '(object{ID: decimal-int-string}&stdClass)|null', $wpdb->get_row( $query ) );
	}

	// Not followed past a write in the same statement.
	$query  = "SELECT ID FROM $wpdb->posts";
	$result = array( $query = "SELECT post_title FROM $wpdb->posts", assertType( 'non-empty-string|null', $wpdb->get_var( $query ) ) );

	// Followed when the result is assigned to the query variable itself.
	$query = "SELECT ID FROM $wpdb->posts";
	$query = assertType( 'decimal-int-string|null', $wpdb->get_var( $query ) );
}

/**
 * A variable is not followed past a write which is not an assignment.
 *
 * @param array<string, string> $values Variables to extract.
 */
function wp_tests_wpdb_indirect_writes( wpdb $wpdb, string $unknown, array $values ): void {
	$query = "SELECT ID FROM $wpdb->posts";
	preg_match( '/x/', $unknown, $query );
	assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );

	$query     = "SELECT ID FROM $wpdb->posts";
	$reference = &$query;
	$reference = $unknown;
	assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );

	$query   = "SELECT ID FROM $wpdb->posts";
	$closure = function () use ( &$query ): void {};
	assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );

	$query = "SELECT ID FROM $wpdb->posts";
	extract( $values );
	assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );

	$query     = "SELECT ID FROM $wpdb->posts";
	$$unknown  = $unknown;
	assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );

	$query = "SELECT ID FROM $wpdb->posts";
	include $unknown;
	assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );

	// A call which only reads the variable.
	$query = "SELECT ID FROM $wpdb->posts";
	$key   = md5( $query );
	assertType( 'decimal-int-string|null', $wpdb->get_var( $query ) );
}

/**
 * An arrow function parameter is not the variable of the same name outside it.
 */
function wp_tests_wpdb_arrow_function( wpdb $wpdb ): void {
	$query = "SELECT ID FROM $wpdb->posts";
	$f     = fn( $query ) => assertType( 'non-empty-string|null', $wpdb->get_var( $query ) );
	$g     = fn() => assertType( 'decimal-int-string|null', $wpdb->get_var( $query ) );
}

/**
 * A union's columns may take their values from any of its queries, so it is left as declared, but a union in a
 * subquery does not affect the outer query.
 */
function wp_tests_wpdb_unions( wpdb $wpdb ): void {
	assertType( 'non-empty-string|null', $wpdb->get_var( "SELECT ID FROM $wpdb->posts UNION SELECT comment_ID FROM $wpdb->comments" ) );
	assertType( 'non-empty-string|null', $wpdb->get_var( "(SELECT ID FROM $wpdb->posts) UNION (SELECT comment_ID FROM $wpdb->comments)" ) );
	assertType( 'decimal-int-string|null', $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE ID IN ( SELECT post_id FROM $wpdb->postmeta UNION SELECT comment_post_ID FROM $wpdb->comments )" ) );
	assertType( 'array{ID: decimal-int-string, x: string|null}|null', $wpdb->get_row( "SELECT ID, ( SELECT 1 UNION SELECT 2 LIMIT 1 ) AS x FROM $wpdb->posts", ARRAY_A ) );
}

/**
 * The columns of a derived table are not known, and the tables in a subquery are not those of the outer query.
 */
function wp_tests_wpdb_subqueries( wpdb $wpdb ): void {
	assertType( 'array{c: string|null}|null', $wpdb->get_row( "SELECT d.c FROM ( SELECT COUNT(*) AS c FROM $wpdb->posts ) d", ARRAY_A ) );
	assertType( 'array<mixed>|null', $wpdb->get_row( "SELECT * FROM ( SELECT ID FROM $wpdb->posts ) AS d", ARRAY_A ) );
	assertType( 'array{ID: decimal-int-string, c: string|null}|null', $wpdb->get_row( "SELECT p.ID, d.c FROM $wpdb->posts p INNER JOIN ( SELECT post_id, COUNT(*) c FROM $wpdb->postmeta GROUP BY post_id ) d ON d.post_id = p.ID", ARRAY_A ) );
	assertType( 'array{post_title: string, post_name: string}|null', $wpdb->get_row( "SELECT p.post_title, p.post_name FROM $wpdb->posts p INNER JOIN ( SELECT post_id FROM $wpdb->postmeta ) d ON d.post_id = p.ID", ARRAY_A ) );
	assertType( 'array{ID: decimal-int-string, n: string|null}|null', $wpdb->get_row( "SELECT ID, ( SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = ID ) AS n FROM $wpdb->posts", ARRAY_A ) );
	assertType( 'array{post_title: string}|null', $wpdb->get_row( "SELECT post_title FROM $wpdb->posts WHERE ID IN ( SELECT post_id FROM $wpdb->postmeta )", ARRAY_A ) );
}

/**
 * A `%i` placeholder is an identifier which is not known.
 */
function wp_tests_wpdb_identifier_placeholders( wpdb $wpdb, string $column, string $table, string $value ): void {
	// A column, whose name is not known, though its position is.
	assertType( 'array<mixed>|null', $wpdb->get_row( $wpdb->prepare( "SELECT %i, ID FROM $wpdb->posts", $column ), ARRAY_A ) );
	assertType( 'array{string|null, decimal-int-string}|null', $wpdb->get_row( $wpdb->prepare( "SELECT %i, ID FROM $wpdb->posts", $column ), ARRAY_N ) );

	// A table, which is not a core table.
	assertType( 'array{ID: string|null}|null', $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM %i", $table ), ARRAY_A ) );
	assertType( 'array{ID: string|null}|null', $wpdb->get_row( $wpdb->prepare( "SELECT p.ID FROM %i p", $table ), ARRAY_A ) );
	assertType( 'array<mixed>|null', $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i", $table ), ARRAY_A ) );

	// Outside the select list and the `FROM` clause.
	assertType( 'array{post_title: string}|null', $wpdb->get_row( $wpdb->prepare( "SELECT post_title FROM $wpdb->posts WHERE %i = %s", $column, $value ), ARRAY_A ) );
}

/**
 * Tables joined with commas.
 */
function wp_tests_wpdb_comma_joins( wpdb $wpdb ): void {
	assertType( 'array{ID: decimal-int-string, meta_value: string|null}|null', $wpdb->get_row( "SELECT p.ID, pm.meta_value FROM $wpdb->posts p, $wpdb->postmeta pm WHERE pm.post_id = p.ID", ARRAY_A ) );
	assertType( 'array{term_id: decimal-int-string, name: string, slug: string, term_group: decimal-int-string, term_taxonomy_id: decimal-int-string, taxonomy: string, description: string, parent: decimal-int-string, count: decimal-int-string}|null', $wpdb->get_row( "SELECT * FROM $wpdb->terms t, $wpdb->term_taxonomy tt", ARRAY_A ) );
	assertType( 'array{ID: decimal-int-string, comment_ID: decimal-int-string|null}|null', $wpdb->get_row( "SELECT p.ID, c.comment_ID FROM $wpdb->posts p, $wpdb->postmeta pm LEFT JOIN $wpdb->comments c ON c.comment_post_ID = pm.post_id", ARRAY_A ) );
	assertType( 'array{ID: decimal-int-string, c: string|null}|null', $wpdb->get_row( "SELECT ID, c FROM $wpdb->posts, ( SELECT 1 AS c ) d", ARRAY_A ) );
}
