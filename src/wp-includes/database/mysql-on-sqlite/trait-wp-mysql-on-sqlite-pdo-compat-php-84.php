<?php

/*
 * The SQLite driver uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 */

/**
 * PDO methods introduced in PHP 8.4.
 *
 * @access private
 */
trait WP_MySQL_On_SQLite_PDO_Compat {
	/**
	 * Create a PDO connection to MySQL on SQLite.
	 *
	 * @param  string      $dsn      MySQL-on-SQLite data source name.
	 * @param  string|null $username Optional. Ignored by this driver.
	 * @param  string|null $password Optional. Ignored by this driver.
	 * @param  array|null  $options  Optional PDO and driver options.
	 * @return static                The new connection.
	 */
	public static function connect(
		string $dsn,
		?string $username = null,
		?string $password = null,
		?array $options = null
	): static {
		return new static( $dsn, $username, $password, $options );
	}
}
