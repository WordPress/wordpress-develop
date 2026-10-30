<?php

/*
 * PDO uses camel case naming, enable non-snake case:
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */

/**
 * Exception raised by the MySQL-on-SQLite driver.
 *
 * Provides PDO-style error information and access to the driver that originated
 * the exception.
 */
class WP_MySQL_On_SQLite_Exception extends PDOException {
	/**
	 * The MySQL-on-SQLite driver that originated the exception.
	 *
	 * @var WP_MySQL_On_SQLite
	 */
	private $driver;

	/**
	 * Constructor.
	 *
	 * @param WP_MySQL_On_SQLite $driver     The MySQL-on-SQLite driver that originated the exception.
	 * @param string             $message    The exception message.
	 * @param int|string         $code       The exception code. In PDO, it can be a string with value of SQLSTATE.
	 * @param Throwable|null     $previous   The previous throwable used for the exception chaining.
	 * @param array|null         $error_info PDO-style error information.
	 */
	public function __construct(
		WP_MySQL_On_SQLite $driver,
		string $message,
		$code = 0,
		?Throwable $previous = null,
		?array $error_info = null
	) {
		parent::__construct( $message, 0, $previous );
		$this->code      = $code;
		$this->driver    = $driver;
		$this->errorInfo = $error_info ?? $this->create_error_info( $message, $code, $previous );
	}

	/**
	 * Get the MySQL-on-SQLite driver that originated the exception.
	 *
	 * @return WP_MySQL_On_SQLite The originating driver.
	 */
	public function get_driver(): WP_MySQL_On_SQLite {
		return $this->driver;
	}

	/**
	 * Create PDO-style error information from an originating exception or from
	 * the emulated driver error.
	 *
	 * @param  string         $message  The exception message.
	 * @param  int|string     $code     The exception code.
	 * @param  Throwable|null $previous The previous throwable.
	 * @return array                     PDO-style error information.
	 */
	private function create_error_info( string $message, $code, ?Throwable $previous ): array {
		if ( $previous instanceof PDOException && is_array( $previous->errorInfo ) ) {
			return $previous->errorInfo;
		}

		$sqlstate = is_string( $code ) && 5 === strlen( $code ) ? $code : 'HY000';
		return array( $sqlstate, 1105, $message );
	}
}
