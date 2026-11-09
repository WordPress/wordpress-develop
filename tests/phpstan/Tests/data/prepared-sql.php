<?php

$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . $_GET['title'] . "';" ); // Bad.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '{$_GET['title']}';" ); // Bad.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '$var';" ); // Bad.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE 'Hello World!';" ); // Ok.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '{$_GET['title']}';" ) ); // Bad.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '$var';" ) ); // Bad.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE %s;", $_GET['title'] ) ); // Ok.

$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . $escaped_var . "';" ); // Bad: old-style ignore comment. WPCS: unprepared SQL OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '{$escaped_var}';" ); //  Bad: old-style ignore comment. WPCS: unprepared SQL OK.

$wpdb->query( $wpdb->prepare( "SELECT SUBSTRING( post_name, %d + 1 ) REGEXP '^[0-9]+$'", array( 123 ) ) ); // Ok.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title = 'The \$_GET var can be evil.' AND ID = %s", array( 123 ) ) ); // Ok.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title = 'The $_GET[foo] var is evil.' AND ID = %s", array( 123 ) ) ); // Bad.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title = 'The \\$_GET[foo]// var is evil again.' AND ID = %s", array( 123 ) ) ); // Bad.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title = 'The \$_GET var can be evil, but $_GET[foo] var is evil.' AND ID = %s", array( 123 ) ) ); // Bad.

$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Bad.
$wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ) ); // Bad.

$wpdb->query( "SELECT * FROM " . $wpdb->posts . " WHERE post_title LIKE 'foo';" ); // Ok.

// All OK.
$all_post_meta = $wpdb->get_results( $wpdb->prepare( sprintf(
	'SELECT `post_id`, `meta_value` FROM `%s` WHERE `meta_key` = "sort_order" AND `post_id` IN (%s)',
	$wpdb->postmeta,
	implode( ',', array_fill( 0, count( $post_ids ), '%d' ) )
), $post_ids ) );

$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . esc_sql( $foo ) . "';" ); // Ok.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE ID = " . ABSINT( $foo ) . ";" ); // Ok.

// Test multi-line strings.
$all_post_meta = $wpdb->get_results( $wpdb->prepare( sprintf(
	'SELECT `post_id`, `meta_value`
	FROM `%s`
	WHERE `meta_key` = "sort_order"
		AND `post_id` IN (%s)',
	$wpdb->postmeta,
	\implode( ',', \array_fill( 0, \count( $post_ids ), '%d' ) )
), $post_ids ) ); // Ok.

$wpdb->query( "
	SELECT *
	FROM $wpdb->posts
	WHERE post_title LIKE '" . esc_sql( $foo ) . "';"
); // Ok.

$wpdb->query( $wpdb->prepare( "
	SELECT *
	FROM $wpdb->posts
	WHERE post_title = 'The \\$_GET[foo]// var is evil again.'
		AND ID = %s",
	array( 123 )
) ); // Bad.


// Test heredoc & nowdoc for query.
$wpdb->query( <<<EOT
	SELECT *
	FROM {$wpdb->posts}
	WHERE ID = {$foo};
EOT
); // Bad.

$wpdb->query( <<<"HD"
	SELECT *
	FROM {$wpdb->posts}
	WHERE post_title LIKE '{$var}';
HD
); // Bad.

$all_post_meta = $wpdb->get_results( $wpdb->prepare( sprintf( <<<'ND'
	SELECT `post_id`, `meta_value`
	FROM `%s`
	WHERE `meta_key` = "sort_order"
		AND `post_id` IN (%s)
ND
	, $wpdb->postmeta,
	IMPLODE( ',', array_fill( 0, COUNT( $post_ids ), '%d' ) )
), $post_ids ) ); // OK.

wpdb::prepare( "SELECT * FROM $wpdb?->posts WHERE post_title LIKE '" . foo() . "';" ); // Bad.

$wpdb->query( // Some arbitrary comment.
	"SELECT *
		FROM $wpdb->posts
		WHERE post_title LIKE '" . $escaped_var . "';"
); // Bad x 1.

$wpdb->query( "SELECT * FROM $wpdb->posts WHERE ID = " . (int) $foo . ";" ); // Ok.

$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . (float) $foo . ";" ); // Ok.

$wpdb->query(
	"SELECT * FROM $wpdb->posts
	WHERE ID = "
	. absint // Ok.
		( $foo )
	. ";"
);

// Test handling of more complex embedded variables and expressions.
$wpdb->query( "SELECT * FROM {$wpdb->bar()} WHERE post_title LIKE '{$title->sub()}';" ); // Bad x 1.
$wpdb->query( "SELECT * FROM ${wpdb->bar} WHERE post_title LIKE '${title->sub}';" ); // Bad x 1.
$wpdb->query( "SELECT * FROM ${wpdb->{$baz}} WHERE post_title LIKE '${title->{$sub}}';" ); // Bad x 1.
$wpdb->query( "SELECT * FROM ${wpdb->{${'a'}}} WHERE post_title LIKE '${title->{${'sub'}}}';" ); // Bad x 1.

// More defensive variable checking
$wpdb->query( "SELECT * FROM $wpdb" ); // Bad x 1, $wpdb on its own is not valid.

$wpdb
	-> /*comment*/ query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . $_GET['title'] . "';" ); // Bad.

$wpdb?->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . (int) $foo . "';" ); // OK.
$wpdb?->query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Bad.

WPDB::prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Bad.
$wpdb->Query( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Bad.

// The sniff's fixture has a line here that is not valid PHP: `" . {$foo} . "`. Left out so the line numbers below still match.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . (array) $foo . ";" ); // Bad - on $foo, not on the (array).
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . -10 . ";" ); // OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . +1.0 . ";" ); // OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . 10 / 2.5 . ";" ); // OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . ++$foo . ";" ); // Bad - on $foo, not on the ++.

// Safeguard handling of PHP 8.0+ nullsafe object operators found within a query.
$wpdb->query( $wpdb->prepare( 'SELECT * FROM ' . $wpdb::TABLE_NAME . " WHERE post_title LIKE '%s';", '%something' ) ); // OK.
$wpdb->query( $wpdb->prepare( 'SELECT * FROM ' . $notwpdb?->posts . " WHERE post_title LIKE '%s';", '%something' ) ); // Bad.

// Safeguard handling of PHP 7.4+ numeric literals with undersocres and PHP 8.1 explicit octals.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . 10_000 . ";" ); // OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE value = " . 0o34 . ";" ); // OK.

// Not a method call.
$wpdb = new WPDB();
$foo = $wpdb->propertyAccess;
echo $wpdb::CONSTANT_NAME;

// Not an identifiable method call.
$wpdb->{$methodName}('query');

/*
 * Safeguard correct handling of all types of namespaced calls to the wpdb::prepare() method.
 *
 * Note that calling wpdb::prepare() statically will result in an error. Still, the tests are included here since the
 * sniff handles those calls.
 *
 * Related to: https://github.com/WordPress/WordPress-Coding-Standards/issues/2710.
 */
\wpdb::prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Bad.
\WPDB::prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Bad.
MyNamespace\wpdb::prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Ok.
\MyNamespace\WPDB::prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Ok.
namespace\wpdb::prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Ok. The sniff should start flagging this once it can resolve relative namespaces as this test file is not namespaced.
namespace\Sub\wpdb::prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE '" . foo() . "';" ); // Ok.

/*
 * The fixture above is WordPress-Coding-Standards/WordPress/Tests/DB/PreparedSQLUnitTest.1.inc
 * at 3.4.1, MIT license. What follows extends it.
 */

// Flexible heredoc, from PreparedSQLUnitTest.2.inc.
$wpdb->query( <<<EOT
		SELECT *
		FROM {$wpdb->posts}
		WHERE ID = {$foo};
	EOT
); // Bad.

// A docblock does not reach a hook, and neither does it reach a query.
/**
 * @param string $foo Foo.
 */
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE ID = $foo" ); // Bad.

// Each interpolated variable is reported, on the line it is on.
$wpdb->query(
	"SELECT *
	FROM $wpdb->posts
	WHERE post_author = $author
		AND post_status = {$status}
		AND post_type = $wpdb->prefix"
); // Bad x 2.

// A query passed by name is examined like one passed by position.
$wpdb->query( query: "SELECT * FROM $wpdb->posts WHERE ID = $foo" ); // Bad.
$wpdb->prepare( args: array( $foo ), query: "SELECT * FROM $wpdb->posts WHERE ID = $foo" ); // Bad.

// Only the query is examined.
$wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", "$foo" ); // OK.
$wpdb->get_results( "SELECT * FROM $wpdb->posts", "$output" ); // OK.

// A query that is not a string literal has nothing interpolated.
$wpdb->query( $query ); // OK.
$wpdb->query( ...$args ); // OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE ID = " . $foo ); // OK here, reported by the sniff as NotPrepared.

// Strings nested in other calls within the query are examined.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE ID IN (" . implode( ',', array( "$foo", 'bar' ) ) . ")" ); // Bad.
$wpdb->query( strtoupper( "SELECT * FROM $wpdb->posts WHERE ID = $foo" ) ); // Bad.
$wpdb->query( $condition ? "SELECT * FROM $wpdb->posts WHERE ID = $foo" : "SELECT * FROM $wpdb->posts WHERE ID = $bar" ); // Bad x 2.

// Strings nested in a call to an escaping function are not.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title = '" . esc_sql( "$foo" ) . "'" ); // OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE ID = " . intval( "$foo" ) ); // OK.
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE ID = " . $this->count( "$foo" ) ); // OK.

// A nested query call is examined once, as its own call.
$wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = $foo AND post_status = %s", $status ) ); // Bad x 1.
$wpdb->get_var( "SELECT * FROM ( " . $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = $foo", $bar ) . " ) AS t WHERE t.ID = $baz" ); // Bad x 2.

// Members of the database object, however they are reached.
$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE ID = {$wpdb?->blogid} AND x = {$wpdb->tables['posts']} AND y = {$wpdb->get_blog_prefix( 1 )}posts" ); // OK.
$wpdb->query( "SELECT * FROM {$wpdb['posts']}" ); // Bad, not a property.
$wpdb->query( "SELECT * FROM {$db->posts}" ); // Bad, not the database object by name or by type.

// The database object recognized by its type rather than its name.
/**
 * @param wpdb $db Database.
 */
function wp_tests_prepared_sql_typed( wpdb $db, $foo ) {
	$db->query( "SELECT * FROM $db->posts WHERE ID = $foo" ); // Bad.
	$db->query( "SELECT * FROM $db->posts WHERE ID = 1" ); // OK.
	$db?->get_row( "SELECT * FROM $db->posts WHERE ID = $foo" ); // Bad.
}

// A property documented as the database object is examined too; see PreparedSQLTestClass.php.

// Other methods are not examined.
$wpdb->esc_like( "$foo" ); // OK.
$wpdb->insert( "$table", array( 'ID' => $foo ) ); // OK.

// A string in a closure within the query is examined, as any other token.
$wpdb->query( array_reduce( $parts, function ( $carry, $part ) use ( $wpdb ) { return $carry . " OR post_name = $part"; }, "SELECT * FROM $wpdb->posts WHERE 1 = 0" ) ); // Bad.
