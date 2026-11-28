<?php
/**
 * Fixture for WpdbSchema, in the form of `wp_get_db_schema()`.
 *
 * Only the text of this file is read.
 *
 * @package WordPress
 */

$charset_collate = '';

$tables = "CREATE TABLE $wpdb->things (
	thing_ID bigint(20) unsigned NOT NULL auto_increment,
	thing_name varchar(200) NOT NULL default '',
	thing_note longtext,
	thing_score double NOT NULL default 0,
	thing_date datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (thing_ID),
	KEY thing_name (thing_name),
	UNIQUE KEY thing_score (thing_score)
) $charset_collate;\n";

$users_single_table = "CREATE TABLE $wpdb->users (
	ID bigint(20) unsigned NOT NULL auto_increment,
	user_login varchar(60) NOT NULL default '',
	PRIMARY KEY  (ID)
) $charset_collate;\n";

$users_multi_table = "CREATE TABLE $wpdb->users (
	ID bigint(20) unsigned NOT NULL auto_increment,
	user_login varchar(60) NOT NULL default '',
	spam tinyint(2) NOT NULL default '0',
	PRIMARY KEY  (ID)
) $charset_collate;\n";
