<?php

$sql = $wpdb->prepare( $sql, $replacements ); // OK - no query available to examine - this will be handled by the PreparedSQL sniff.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", 1, "admin" ); // OK.
$sql = $wpdb?->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", array( 1, "admin" ) ); // OK.
$sql = $wpdb->prepare( 'SELECT * FROM `table` WHERE `column` = %s AND `field` = %d', 'foo', 1337 ); // OK.
$sql = $wpdb->prepare( 'SELECT DATE_FORMAT(`field`, "%%c") FROM `table` WHERE `column` = %s', 'foo' ); // OK.

/*
 * No placeholders, no need to use prepare().
 */
$sql = $wpdb->prepare( 'SELECT * FROM `table`' ); // Warning.
$sql = $wpdb->prepare( 'SELECT * FROM `table` WHERE id = ' . $id ); // OK - this will be handled by the PreparedSQL sniff.
$sql = $wpdb->prepare( "SELECT * FROM `table` WHERE id = $id" ); // OK - this will be handled by the PreparedSQL sniff.
$sql = $wpdb->prepare( "SELECT * FROM `table` WHERE id = {$id['some%sing']}" ); // OK - this will be handled by the PreparedSQL sniff.
$sql = $wpdb?->prepare( 'SELECT * FROM ' . $wpdb->users ); // Warning.
$sql = $wpdb->prepare( "SELECT * FROM `{$wpdb->users}`" );  // Warning.
$sql = $wpdb->prepare( "SELECT * FROM `{$wpdb->users}` WHERE id = $id" ); // OK - this will be handled by the PreparedSQL sniff.

/*
 * No placeholders found, but replacement variable(s) are being passed.
 */
$sql = $wpdb->prepare( 'SELECT * FROM `table`', $something ); // Warning.

/*
 * Test passing invalid replacement placeholder.
 */
$sql = $wpdb->prepare( '%d %1$e %%% % %A %h', 1 ); // Bad x 5.
$sql = $wpdb->prepare( '%%%s', 1 ); // OK.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb?->users WHERE id = %1\$d AND user_login = %2\$s", 1, "admin" ); // OK. 2 x warning for unquoted complex placeholders.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb?->users WHERE id = %01.2f AND user_login = %10.10X", 1, "admin" ); // Bad x 1 + 1 warning unquoted complex placeholders + 1 warning nr of replacements.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %'.09F AND user_login = %1\$04x", 1, "admin" ); // Bad x 1 + 1 warning unquoted complex placeholders + 1 warning nr of replacements.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = \"%1\$c\" AND user_login = '%2\$e'", 1, "admin" ); // Bad x 2 + 1 warning.
$sql = $wpdb->prepare( 'SELECT * FROM ' . $wpdb?->users . ' WHERE id = \'%1\$b\' AND user_login = "%2\$o"', 1, "admin" ); // Bad x 2 + 1 warning.

/*
 * Test passing quoted simple replacement placeholder and unquoted complex placeholder.
 */
$sql = $wpdb->prepare( '"%f"', 1.1 ); // Bad.
$sql = $wpdb->prepare( 'SELECT * FROM `table` WHERE `field` = \'%s\'', 'string' ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM `table` WHERE `id` = \"%d\"", 1 ); // Bad.
$sql = $wpdb->prepare( <<<EOD
	SELECT *
	FROM `%1\$s`
	WHERE id = %2\$d
		AND `%3\$s` = "%4\$s"
EOD
	, $wpdb->users, 1, 'user_login', "admin"
); // Warning x 3, unquoted complex placeholder.

/*
 * Test passing an incorrect amount of replacement parameters.
 */
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s" ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", 1 ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", 1, "admin", $variable ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", array( 1 ) ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", [1, "admin", $variable] ); // Bad.

$replacements = [1, "admin", $variable];
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", $replacements ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", $replacements ); // Bad - old-style ignore comment. WPCS: PreparedSQLPlaceholders replacement count OK.

// Valid test case as found in WP core /wp-admin/includes/export.php
$esses = array_fill( 0, count($post_types), '%s' );
$where = $wpdb->prepare( "{$wpdb->posts}.post_type IN (" . implode( ',', $esses ) . ')', $post_types ); // Warning.
// Testing that ignore comment works for this mismatch too.
$where = $wpdb->prepare( "{$wpdb->posts}.post_type IN (" . implode( ',', $esses ) . ')', $post_types ); // Bad - old-style ignore comment. WPCS: PreparedSQLPlaceholders replacement count OK.

/*
 * Test correctly recognizing queries using IN in combination with dynamic placeholder creation.
 */
// The proper way to write a query using `IN` won't throw a warning:
$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', array_fill( 0, count($post_types), '%s' ) )
	),
	$post_types
); // OK.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)
		AND {$wpdb->posts}.post_status IN (%s)",
		implode( ',', array_fill( 0, count($post_types), '%s' ), ),
		IMPLODE( ',', Array_Fill( 0, count($post_statusses), '%s' ), ),
	),
	array_merge( $post_types, $post_statusses, ),
); // OK.

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN ("
		. implode( ',', array_fill( 0, count($post_types), '%s' ) )
		. ") AND {$wpdb->posts}.post_status IN ("
		. implode( ',', array_fill( 0, count($post_statusses), '%s' ) )
		. ')',
	array_merge( $post_types, $post_statusses )
); // OK.

$query = $wpdb->prepare(
	sprintf(
		'SELECT COUNT(ID)
		  FROM `%s`
		 WHERE ID IN (%s)
		   AND post_status = "publish"',
		$wpdb->posts,
		implode( ',', array_fill( 0, count( $post_ids ), '%d' ) )
	) . ' AND post_type = %s',
	array_merge( $post_ids, array( $this->get_current_post_type() ) ),
); // OK.

$results = $wpdb->get_results(
	$wpdb->prepare( '
		SELECT ID
		FROM ' . $wpdb->posts . '
		WHERE ID NOT IN( SELECT post_id FROM ' . $wpdb?->postmeta . ' WHERE meta_key = %s AND meta_value = %s )
			AND post_status in( "future", "draft", "pending", "private", "publish" )
			AND post_type in( ' . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ' )
		LIMIT %d',
		$replacements
	),
	ARRAY_A
); // OK.

$query = $wpdb->prepare(
	sprintf(
		'SELECT COUNT(ID)
		  FROM `%s`
		 WHERE ID in (%s)
		   AND post_status = "publish"',
		$wpdb->posts,
		implode( ',', array_fill( 0, count( $post_ids ), '%d' ) )
	) . ' AND post_type = %s',
	array_merge( $post_ids, array( $this->get_current_post_type() ) ),
	$another
); // Error - second replacement param is incorrect, with a variable nr of placeholders you always need to pass a replacement array.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN ('%s')",
		implode( ',', array_fill( 0, count($post_types), '%s' ) ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad x 2 - %s is quoted, so this won't work properly, will throw incorrect nr of replacements error + quotes found.

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN (\""
		. implode( ',', array_fill( 0, count($post_types), '%s' ) )
		. "\") AND {$wpdb->posts}.post_status IN ('"
		. \implode( ',', array_fill( 0, count($post_statusses), '%s' ) )
		. '\')',
	array_merge( $post_types, $post_statusses )
); // Bad x 2 - quotes between the () for the IN.

/*
 * Test distinguishing wildcard _ and %'s in LIKE statements.
 */
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE %s", $like ); // OK.
$sql = $wpdb->prepare( 'SELECT * FROM ' . $wpdb->posts . ' WHERE post_content LIKE \'a string\'' ); // Warning x 2. Like without wildcard, no need for prepare.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE 'a string'" ); // Warning x 2. Like without wildcard, no need for prepare.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE '%a string' AND post_status = %s", $status ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE 'a string%' AND post_status = %s", $status ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE '%a string%' AND post_status = %s", $status ); // Bad.
$sql = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE 'a_string' AND post_status = %s", $status ); // Bad.

// Some VALID test cases as found in plugins published on WP.org.
$comment_id = $wpdb->get_var($wpdb->prepare('SELECT comment_ID FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d AND comment_agent LIKE %s', intval($post->ID), 'Disqus/1.0:' . $comment_id)); // OK.

$sql = $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", self::CACHE_KEY_PREFIX . '%' ); // OK.

$wpdb->query($wpdb->prepare('UPDATE '.$wpdb->prefix.'posts SET post_content = REPLACE(post_content, %s, %s) WHERE post_type = "page" AND post_content LIKE %s', $meta_before, $meta_after, '%'.$wpdb->esc_like($meta_before).'%')); // OK.

$query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}options WHERE `option_name` LIKE %s OR `option_name` LIKE %s", "%{$transient_name}%", "%{$transient_timeout_name}%"); // OK.

// Some INVALID test cases as found in plugins published on WP.org.
$wpdb->prepare( "UPDATE $wpdb->posts SET post_status = 'pending' WHERE (post_type LIKE 'product_variation' or post_type LIKE 'product') AND NOT ID IN (". implode(",", $imported_ids ) .")"); // Error x 1 for `product_variation`; warning x 1 for wrong use of LIKE with `product`; the PreparedSQL sniff will also kick in and throw an error about `$imported_ids`.

$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid LIKE '%%%s%%' LIMIT 1;", $img_url ) ); // Bad.

$result = $wpdb->get_col($wpdb->prepare("SELECT guid FROM $wpdb->posts WHERE guid LIKE '%%%s' and post_parent=%d;", $atts['model'], $post->ID )); // Bad.

$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_agent NOT LIKE 'Disqus/%%'", $post->ID) ); // Bad.

$sql = $wpdb->prepare( "SELECT count(*) FROM $this->fontsTable WHERE name LIKE '%%%s%%' OR status LIKE '%%%s%%' OR metadata LIKE '%%%s%%'", $search, $search, $search ); // Bad x 3, the PreparedSQL sniff will also kick in and throw an error about `$this`.

$additional_where = $wpdb->prepare(' AND (network like "%%%s%%" OR ProgramTitle like "%%%s%%" OR TransactionStatus like "%%%s%%" ) ', $search, $search, $search); // Bad x 3.

$robots_query    = $wpdb->prepare( "SELECT name FROM $robots_table WHERE %s LIKE concat('%%',name,'%%')", $http_user_agent ); // Bad, the PreparedSQL sniff will also kick in.

$sql = $wpdb->prepare('SELECT * FROM ' . $wpdb->avatar_privacy . ' WHERE email LIKE "%s"', $email); // Bad (quotes).

$res = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->posts . ' SET post_name="feed" WHERE post_name LIKE "feed-%" AND LENGTH(post_name)=6 AND post_type=%s', BAWAS_POST_TYPE ) ); // Bad.

$sql = $wpdb->prepare( "SELECT ID FROM $wpdb->users AS us INNER JOIN $wpdb->usermeta AS mt ON ( us.ID = mt.user_id ) WHERE ( mt.meta_key = 'bbp_last_login' AND mt.meta_value < %s ) AND user_id IN ( SELECT user_id FROM $wpdb->usermeta AS mt WHERE (mt.meta_key = '{$wpdb->prefix}capabilities' AND mt.meta_value LIKE '%%bbp_user%%' ))", $beforegmdate ); // Bad.

$paged_events = $wpdb->get_results( $wpdb->prepare( "SELECT id as event_id FROM {$bp->events->table_name} WHERE ( name LIKE '{$filter}%%' OR description LIKE '{$filter}%%' ) AND id IN ({$gids}) " . $oldevents . " {$pag_sql}" ) ); // Bad x 2, the PreparedSQL sniff will also kick in and throw six errors.

$query = $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE '%%post_%%' AND element_id = %d", $post_ID ); // Bad.

$postID = $wpdb->get_var($wpdb->prepare("SELECT `postID` FROM `".EPDataBase::$table_name."` WHERE `path` like '".$filePath."';")); // OK, the PreparedSQL sniff will kick in and throw four errors.

$wpdb->query($wpdb->prepare("show tables like '$this->table_name'")) > 0; // OK, the PreparedSQL sniff will kick in.

$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->wp_options WHERE option_name LIKE '%widget_gigya%'" ) ); // Bad.

$where .= $wpdb->prepare( " AND `name` LIKE '%%%%" . '%s' . "%%%%' ", $args['name'] ); // Bad x 2.

$wpdb->query($wpdb->prepare("delete from wp_postmeta where post_id = $target_postId AND meta_key like 'google_snippets'")); // Bad, the PreparedSQL sniff will also kick in and throw an error about `$target_postId`.

// phpcs:set WordPress.DB.PreparedSQLPlaceholders minimum_wp_version 6.2

$wpdb->prepare( 'WHERE %i = %s', $field, $value ); // OK.
$wpdb->prepare( 'WHERE %i = %s', $value ); // ReplacementsWrongNumber.
$wpdb->prepare( 'WHERE %i = %x', $field, $value ); // UnsupportedPlaceholder & ReplacementsWrongNumber.
$wpdb->prepare( 'WHERE %i = %2$s', $field, $value ); // UnquotedComplexPlaceholder.
$wpdb->prepare( 'WHERE %i = %10s', $field, $value ); // UnquotedComplexPlaceholder.
$wpdb->prepare( 'WHERE %i = %2$-10s', $field, $value ); // UnquotedComplexPlaceholder.

$wpdb->prepare( 'WHERE %i = "%s"', $field, $value ); // QuotedSimplePlaceholder.
$wpdb->prepare( 'WHERE "%i" = %s', $field, $value ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( "WHERE \"%i\" = %s", $field, $value ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( "WHERE '%i' = %s", $field, $value ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE \'%i\' = %s', $field, $value ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE `%i` = %s', $field, $value ); // QuotedIdentifierPlaceholder.

$wpdb->prepare( "WHERE '%10i' IS NULL", $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE "%10i" IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE \'%1$i\' IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( "WHERE \"%10i\" IS NULL", $field ); // QuotedIdentifierPlaceholder.

$wpdb->prepare( 'WHERE %1$i IS NULL', $field ); // OK.
$wpdb->prepare( 'WHERE %10i IS NULL', $field ); // OK.
$wpdb->prepare( 'WHERE % i IS NULL', $field ); // UnescapedLiteral & UnfinishedPrepare (while this is valid, should avoid).
$wpdb->prepare( 'WHERE %1$-10i IS NULL', $field ); // OK.
$wpdb->prepare( 'WHERE %1$-10.3i IS NULL', $field ); // OK.
$wpdb->prepare( 'WHERE %1$+10.3i IS NULL', $field ); // OK.
$wpdb->prepare( 'WHERE %1$ 10.3i IS NULL', $field ); // UnsupportedPlaceholder (parsed as "%1$", which is valid, but should avoid).
$wpdb->prepare( 'WHERE %1$010.3i IS NULL', $field ); // OK.
$wpdb->prepare( "WHERE %1$'x10.3i IS NULL", $field ); // OK.

$wpdb->prepare( 'WHERE %.2i IS NULL', 'a``b' ); // Currently ignore, but it might cause a problem (most likely a parse error) "WHERE `a`` IS NULL".

$wpdb->prepare( 'WHERE `%1$i` IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE `%10i` IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE `% i` IS NULL', $field ); // UnescapedLiteral & UnfinishedPrepare (if RegEx matched, then it should be QuotedIdentifierPlaceholder).
$wpdb->prepare( 'WHERE `%1$-10i` IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE `%1$-10.3i` IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE `%1$+10.3i` IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( 'WHERE `%1$ 10.3i` IS NULL', $field ); // QuotedIdentifierPlaceholder, and UnsupportedPlaceholder (parsed as "%1$", which is valid, but should avoid).
$wpdb->prepare( 'WHERE `%1$010.3i` IS NULL', $field ); // QuotedIdentifierPlaceholder.
$wpdb->prepare( "WHERE `%1$'x10.3i` IS NULL", $field ); // QuotedIdentifierPlaceholder.

$wpdb->prepare( 'SELECT ID FROM `%2$i` WHERE `%1$i` = "%3$s"', $field, $wpdb->posts, $value ); // QuotedIdentifierPlaceholder (x2).

$wpdb->prepare( 'SELECT ID FROM %i.%i WHERE %i = "false"', $db, $table, $field ); // OK.

$wpdb->prepare(
	sprintf(
		'xxx IN (%s)',
		implode( ',', array_fill( 0, count($fields), '%i' ) )
	),
	$fields
); // ReplacementsWrongNumber + IdentifierWithinIN.

$wpdb->prepare( 'xxx IN ( ' . implode( ',', array_fill( 0, count( $post_types ),
	'%i' ) ) . ' )',
	$fields
); // IdentifierWithinIN.

$wpdb->prepare( '
	xxx IN ( ' . implode( ',', array_fill( 0, count( $post_types ), "%i" ) ) . ' )',
	$fields
); // IdentifierWithinIN.

// phpcs:set WordPress.DB.PreparedSQLPlaceholders minimum_wp_version 5.8

$wpdb->prepare( 'WHERE %1$+10.3i = %s', $field, $value ); // UnsupportedIdentifierPlaceholder.
$wpdb->prepare( "WHERE '%10i' IS NULL", $field ); // UnsupportedIdentifierPlaceholder + QuotedIdentifierPlaceholder.
$wpdb->prepare( 'xxx IN ( ' . implode( ',', array_fill( 0, count( $post_types ), '%i' ) ) . ' )', $fields ); // UnsupportedIdentifierPlaceholder + IdentifierWithinIN.

// phpcs:set WordPress.DB.PreparedSQLPlaceholders minimum_wp_version

$wpdb->prepare(); // Ignore.

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN (\""
		. implode()
		. "\") AND {$wpdb->posts}.post_id = %s",
	$post_id
); // Bad, dynamic placeholder generation quotes with invalid implode call (no params).

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_status IN ('"
		. implode( ',' )
		. '\') AND {$wpdb->posts}.post_id = %s',
	$post_id
); // Bad, dynamic placeholder generation quotes with invalid implode call (missing second param).

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_status IN ('"
		. implode( '|', array_fill( 0, count($post_stati), '%s' ) )
		. '\') AND {$wpdb->posts}.post_id = %s',
	$post_id
); // Bad x2, dynamic placeholder generation quotes with invalid implode call (separator parameter does not contain expected ',' value).

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN (\""
		. implode( ',', $array_fill )
		. "\") AND {$wpdb->posts}.post_id = %s",
	$post_id
); // Bad, dynamic placeholder generation quotes with invalid implode call (array param is not call to array_fill).

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN (\""
		. implode( ',', array_fill() )
		. "\") AND {$wpdb->posts}.post_id = %s",
	$post_id
); // Bad, dynamic placeholder generation quotes with invalid implode call (array param is call to array_fill() without params).

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN (\""
		. implode( ',', array_fill( 0, count($post_types) ) )
		. "\") AND {$wpdb->posts}.post_id = %s",
	$post_id
); // Bad, dynamic placeholder generation quotes with invalid implode call (array param is call to array_fill(), but missing third param).

// Safeguard that short array as $args is handled correctly.
$wpdb->get_col(
	$wpdb->prepare(
		"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s LIMIT %d",
		[ 'taxonomy_name', $limit ]
	) // Ok.
);

// Disregard comments in the implode() $separator param.
$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',' /*comment*/, array_fill( 0, count($post_types), '%s' ) )
	),
	$post_types
); // OK.

// Disregard comments in the array_fill() $value param.
// Also test handling of %i when minimum supported WP version is not high enough yet.
// phpcs:set WordPress.DB.PreparedSQLPlaceholders minimum_wp_version 6.0
$wpdb->prepare( 'xxx IN ( ' . implode( ',', array_fill( 0, count( $post_types ), '%i' /*comment*/ ) ) . ' )', $fields ); // IdentifierWithinIN.
// phpcs:set WordPress.DB.PreparedSQLPlaceholders minimum_wp_version

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN ("
		. implode( ',', array_fill( 0, count($post_types), '%C' ) )
		. ')',
	array_merge( $post_types )
); // Bad x 2, UnsupportedPlaceholder + UnfinishedPrepare.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', array_fill( 0, count($post_types), '%C' ) )
	),
	$post_types
); // Bad, ReplacementsWrongNumber due to unrecognized placeholder in array_fill().

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN ("
		. implode( ',', array_fill( 0, count($post_types), /*comment*/ '%s' ) )
		. ')',
	array_merge( $post_types )
); // OK.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', array_fill( 0, count($post_types), "%s" /*comment*/ ) )
	),
	$post_types
); // OK.

// Safeguard that FQN function calls to implode() and array_fill() are handled correctly.
$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		\implode( ',', array_fill( 0, count($post_types), '%s' ) )
	),
	$post_types
); // OK.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', \array_fill( 0, count($post_types), '%s' ) )
	),
	$post_types
); // OK.

$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN ("
		. implode( ',', \array_fill( 0, count($post_types), '%s' ) )
		. ") AND {$wpdb->posts}.post_status IN ("
		. implode( ',', \array_fill( 0, count($post_statusses), '%s' ) )
		. ')',
	array_merge( $post_types, $post_statusses )
); // OK.

/*
 * Safeguard support for PHP 8.0+ named parameters.
 */
// WPDB::prepare() with named params. Named args not supported with ...$args, but that's not the concern of this sniff.
$query = $wpdb->prepare(
	args  : $replacements,
	query : 'SELECT ID
		FROM ' . $wpdb->posts . '
		WHERE post_type = %s',
); // OK, named args not supported with ...$args, but that's not the concern of this sniff.

$query = $wpdb->prepare(
	query : 'SELECT ID
		FROM ' . $wpdb->posts . '
		WHERE post_type = %s',
); // Bad, missing replacements.

$query = $wpdb->prepare(
	args  : $replacements,
	queri : 'SELECT ID
		FROM ' . $wpdb->posts . '
		WHERE post_type = %s',
); // Ignore, incorrect name used for named param `query`, so param not recognized.

// Implode() with named params.
$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( array: array_fill( 0, count( $post_types ), '%s' ), separator: ',', ),
	),
	array_merge( $post_types, $post_statusses )
); // Okay.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( array: $something, separator: ',', ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad - will throw incorrect nr of replacements error - `array` param is not an array_fill() function call.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( arrays: array_fill( 0, count( $post_types ), '%s' ), separator: ',', ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad - will throw incorrect nr of replacements error - incorrect name used for named param `array`, so param not recognized.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( separator: ',', ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad - will throw incorrect nr of replacements error - missing `array` param.

// Array_fill() with named params.
$where = $wpdb->prepare(
	"{$wpdb->posts}.post_type IN ("
		. implode( ',', array_fill( start_index: 0, count: count($post_types), value: '%s' ) ) // Expected order.
		. ") AND {$wpdb->posts}.post_status IN ("
		. implode( ',', array_fill( value: '%s', start_index: 0, count: count($post_statusses), ) ) // Unconventional order.
		. ')',
	array_merge( $post_types, $post_statusses )
); // OK.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', array_fill( start_index: 0, count: count($post_types) ) ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad - will throw incorrect nr of replacements error - missing $value param in array_fill().

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', array_fill( start_index: 0, values: '%s', count: count($post_types) ) ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad - will throw incorrect nr of replacements error - incorrect $values param name in array_fill().

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', array_fill( value: 's', start_index: 0, count: count($post_types) ) ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad - will throw incorrect nr of replacements error - $value param name in array_fill() does not contain placeholder.

$where = $wpdb->prepare(
	sprintf(
		"{$wpdb->posts}.post_type IN (%s)",
		implode( ',', array_fill( value: $s, start_index: 0, count: count($post_types) ) ),
	),
	array_merge( $post_types, $post_statusses )
); // Bad - will throw incorrect nr of replacements error - $value param name in array_fill() does not contain plain text placeholder.

// Sprintf() with named params. This is invalid as variadic functions do not support named params.
// The sniff will ignore the sprintf() as it cannot be analyzed correctly.
$where = $wpdb->prepare(
	sprintf(
		values: implode( ',', array_fill( 0, count($post_types), '%s' ) ),
		format: "{$wpdb->posts}.post_type IN ('%s')",
	),
	array_merge( $post_types, $post_statusses )
); // OK, well not really, but not something we can reliably analyze.

/*
 * Safeguard handling of $wpdb->prepare as PHP 8.1+ first class callable.
 */
$callback = $wpdb->prepare(...); // OK.

/*
 * Safeguard correct handling of all types of namespaced calls to the wpdb::prepare() method.
 *
 * Note that calling wpdb::prepare() statically will result in an error. Still, the tests are included here since the
 * sniff handles those calls.
 *
 * Related to: https://github.com/WordPress/WordPress-Coding-Standards/issues/2710.
 */
$sql = \wpdb::prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s" ); // Error.
$sql = \WPDB::prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s" ); // Error.
$sql = MyNamespace\wpdb::prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s" ); // OK.
$sql = \MyNamespace\WPDB::prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s" ); // OK.
$sql = namespace\wpdb::prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s" ); // Ok. The sniff should start flagging this once it can resolve relative namespaces as this test file is not namespaced.
$sql = namespace\Sub\wpdb::prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s" ); // OK.

/*
 * The fixture above is WordPress-Coding-Standards/WordPress/Tests/DB/PreparedSQLPlaceholdersUnitTest.inc
 * at 3.4.1, MIT license. What follows extends it.
 */

// Replacements passed in every way the method accepts them.
$wpdb->prepare( 'SELECT * FROM `table`', array( $something ) ); // Warning.
$wpdb->prepare( 'SELECT * FROM `table`', ...$replacements ); // Warning.
$wpdb->prepare( query: 'SELECT * FROM `table`', args: $something ); // Warning.
$wpdb->prepare( 'SELECT * FROM `table`', $something, $other ); // Warning.
$wpdb?->prepare( 'SELECT * FROM `table`', $something ); // Warning.
wpdb::prepare( 'SELECT * FROM `table`', $something ); // Warning.

// The query is reported on its last line.
$wpdb->prepare(
	'SELECT *
	FROM `table`',
	$something
); // Warning.

$wpdb->prepare( <<<SQL
	SELECT *
	FROM `table`
	SQL,
	$something
); // Warning.

$wpdb->prepare(
	'SELECT * FROM `table`'
	, $something
); // Warning.

// Placeholders anywhere in the query text satisfy the check.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id = ' . ( $condition ? '%d' : '%s' ), $something ); // OK.
$wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE id = " . '%d', $something ); // OK.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id = %i', $something ); // OK.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id = %%', $something ); // Warning, a literal percent sign is no placeholder.
$wpdb->prepare( 'SELECT * FROM `table` WHERE name = "%s"', $something ); // OK here, reported by the sniff as QuotedSimplePlaceholder.
$wpdb->prepare( "SELECT * FROM `table` WHERE id = {$id['%d']}", $something ); // Warning, a placeholder in an embedded variable is not in the query.

// An `IN` clause with a single placeholder, or with a valid dynamic placeholder list.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN (%s)', $ids ); // OK.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ' )', $ids ); // OK.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ',', array_fill( 0, count( $ids ), '%F' ) ) . ' )', $ids ); // OK.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ',', array_fill( 0, count( $ids ), $placeholder ) ) . ' )', $ids ); // Warning, the repeated value is not a literal placeholder.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ',', array_fill( 0, count( $ids ), $wpdb->placeholder_escape() ) ) . ' )', $ids ); // Warning, as above.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ',', array_fill( 0, count( $ids ), '%C' ) ) . ' )', $ids ); // Warning, the repeated value is not a placeholder.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ',', $placeholders ) . ' )', $ids ); // Warning, the list is not built with array_fill().

// An implode() that is not recognized is query text like any other, and so is the
// placeholder repeated by its array_fill(). These are the sniff's own blind spots.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ';', array_fill( 0, count( $ids ), '%d' ) ) . ' )', $ids ); // OK here, though the separator is not a comma.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN ( ' . implode( ',', array_fill( 0, count( $ids ), '%i' ) ) . ' )', $ids ); // OK here, though an identifier placeholder is not a value.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id = ( ' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' )', $ids ); // OK here, though not an IN clause.
$wpdb->prepare( implode( ',', array_fill( 0, count( $ids ), '%d' ) ), $ids ); // OK here, though the implode() follows no IN clause.
$wpdb->prepare( 'SELECT * FROM `table` WHERE id IN (' . join( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids ); // OK here, though join() is not recognized.

// A sprintf() formats the query; its arguments are not query text.
$wpdb->prepare( sprintf( 'SELECT * FROM `table` WHERE id = %%d' ), $id ); // Warning, `%%d` is a literal in the format.
$wpdb->prepare( sprintf( 'SELECT * FROM `table` WHERE id = ', '%d' ), $id ); // Warning, the placeholder is in an argument, which is not query text.
$wpdb->prepare( sprintf( 'SELECT * FROM `%s` WHERE id = 1', $wpdb->posts ), $id ); // OK here, a sprintf() placeholder passes for one of prepare()'s.
$wpdb->prepare( sprintf( format: 'SELECT * FROM `%s`', values: $wpdb->posts ), $id ); // OK, a sprintf() with named arguments is not analyzed.

// A query that holds no text has nothing to examine.
$wpdb->prepare( $query, $something ); // OK.
$wpdb->prepare( $this->get_query(), $something ); // OK.
$wpdb->prepare( $query . $suffix, $something ); // OK.
$wpdb->prepare( ...$args ); // OK.

// The database object recognized by its type rather than its name.
/**
 * @param wpdb $db Database.
 */
function wp_tests_placeholders_typed( wpdb $db, $something ) {
	$db->prepare( 'SELECT * FROM `table`', $something ); // Warning.
}
