<?php declare(strict_types = 1);

/*
 * The SQLite connection uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 */

/**
 * SQLite connection.
 *
 * This class configures and encapsulates the connection to an SQLite database.
 * It requires PDO with the SQLite driver, and currently, it is only a simple
 * wrapper that leaks some of the PDO APIs (returns PDOStatement values, etc.).
 * In the future, we may abstract it away from PDO and support SQLite3 as well.
 *
 * @access private
 */
class WP_SQLite_Connection {
	/**
	 * The default time in milliseconds for SQLite to wait for a lock.
	 */
	const DEFAULT_SQLITE_BUSY_TIMEOUT = 10000;

	/**
	 * The supported SQLite journal modes.
	 *
	 * See: https://www.sqlite.org/pragma.html#pragma_journal_mode
	 */
	const SQLITE_JOURNAL_MODES = array(
		'DELETE',
		'TRUNCATE',
		'PERSIST',
		'MEMORY',
		'WAL',
		'OFF',
	);

	/**
	 * The supported SQLite synchronous settings.
	 *
	 * The list is indexed by the corresponding numeric setting values (0 to 3).
	 *
	 * See: https://www.sqlite.org/pragma.html#pragma_synchronous
	 */
	const SQLITE_SYNCHRONOUS_SETTINGS = array(
		'OFF',
		'NORMAL',
		'FULL',
		'EXTRA',
	);

	/**
	 * The PDO connection for SQLite.
	 *
	 * @var PDO
	 */
	private $pdo;

	/**
	 * A query logger callback.
	 *
	 * @var (callable(string, array): void)|null
	 */
	private $query_logger = null;

	/**
	 * Constructor.
	 *
	 * Set up an SQLite connection.
	 *
	 * @param array $options {
	 *     An array of options.
	 *
	 *     @type string|null     $path         Optional. SQLite database path.
	 *                                         For in-memory database, use ':memory:'.
	 *                                         Must be set when PDO instance is not provided.
	 *     @type PDO|null        $pdo          Optional. PDO instance with SQLite connection.
	 *                                         If not provided, a new PDO instance will be created.
	 *     @type int|null        $busy_timeout Optional. SQLite busy timeout in milliseconds.
	 *                                         The time to wait for a lock. Defaults to 10000.
	 *     @type string|null     $journal_mode Optional. SQLite journal mode. Defaults to WAL.
	 *     @type string|int|null $synchronous  Optional. SQLite synchronous setting. Defaults to
	 *                                         NORMAL when the effective journal mode is WAL.
	 *     @type array           $pdo_options  Optional. PDO constructor options.
	 * }
	 *
	 * @throws InvalidArgumentException When some connection options are invalid.
	 * @throws PDOException             When the driver initialization fails.
	 */
	public function __construct( array $options ) {
		// Setup PDO connection.
		if ( isset( $options['pdo'] ) && $options['pdo'] instanceof PDO ) {
			$this->pdo = $options['pdo'];
		} else {
			if ( ! isset( $options['path'] ) || ! is_string( $options['path'] ) ) {
				throw new InvalidArgumentException( 'Option "path" is required when "pdo" is not provided.' );
			}
			$pdo_class   = PHP_VERSION_ID >= 80400 ? Pdo\Sqlite::class : PDO::class;
			$pdo_options = $options['pdo_options'] ?? array();

			// Internal driver operations require exceptions regardless of the
			// caller-visible WP_MySQL_On_SQLite::ATTR_ERRMODE setting.
			$pdo_options[ PDO::ATTR_ERRMODE ] = PDO::ERRMODE_EXCEPTION;
			$this->pdo                        = new $pdo_class( 'sqlite:' . $options['path'], null, null, $pdo_options );
		}

		// Throw exceptions on error.
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// Configure SQLite busy timeout. SQLite stores it as a 32-bit signed integer.
		$busy_timeout = $options['busy_timeout'] ?? self::DEFAULT_SQLITE_BUSY_TIMEOUT;
		if ( ! is_int( $busy_timeout ) || $busy_timeout < 0 || $busy_timeout > 2147483647 ) {
			throw new InvalidArgumentException(
				'Invalid SQLite busy timeout. It must be an integer from 0 to 2147483647.'
			);
		}
		$this->query( 'PRAGMA busy_timeout = ' . $busy_timeout );

		// Configure SQLite journal mode. Default to WAL for best throughput.
		$effective_journal_mode = null;
		$journal_mode           = $options['journal_mode'] ?? 'WAL';
		if ( is_string( $journal_mode ) ) {
			$journal_mode = strtoupper( $journal_mode );
		}
		if ( ! in_array( $journal_mode, self::SQLITE_JOURNAL_MODES, true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid SQLite journal mode: %s.', $options['journal_mode'] )
			);
		}
		try {
			$effective_journal_mode = strtoupper(
				(string) $this->query( 'PRAGMA journal_mode = ' . $journal_mode )->fetchColumn()
			);
		} catch ( PDOException $e ) {
			// WAL may be unavailable in some environments, such as on network
			// filesystems. When it is explicitly configured, surface the error.
			// Otherwise, fall back to the default SQLite behavior.
			if ( isset( $options['journal_mode'] ) ) {
				throw $e;
			}
		}

		/*
		 * Configure SQLite synchronous setting. Default to NORMAL for WAL mode.
		 *
		 * WAL improves read/write concurrency and "synchronous = NORMAL" avoids
		 * frequent sync to the main database, which could become a bottleneck.
		 * In WAL mode, NORMAL is safe and recommended. From the SQLite docs:
		 *
		 *   The synchronous=NORMAL setting provides the best balance between
		 *   performance and safety for most applications running in WAL mode.
		 *   You lose durability across power loss with synchronous NORMAL in WAL
		 *   mode, but that is not important for most applications. Transactions
		 *   are still atomic, consistent, and isolated, which are the most
		 *   important characteristics in most use cases.
		 *
		 * SQLite defaults to "synchronous = FULL" to avoid data corruption with
		 * other journal modes. With WAL, this is not necessary.
		 *
		 * See: https://sqlite.org/pragma.html#pragma_synchronous
		 */
		$synchronous = $options['synchronous'] ?? null;
		if ( isset( $synchronous ) ) {
			// Validate and normalize explicitly provided synchronous value.
			if ( is_int( $synchronous ) && isset( self::SQLITE_SYNCHRONOUS_SETTINGS[ $synchronous ] ) ) {
				$synchronous = self::SQLITE_SYNCHRONOUS_SETTINGS[ $synchronous ];
			} elseif ( is_string( $synchronous ) ) {
				$synchronous = strtoupper( $synchronous );
			}
			if ( ! in_array( $synchronous, self::SQLITE_SYNCHRONOUS_SETTINGS, true ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Invalid SQLite synchronous setting: %s.', $options['synchronous'] )
				);
			}
		} elseif ( 'WAL' === $effective_journal_mode ) {
			// Default to NORMAL for WAL mode.
			$synchronous = 'NORMAL';
		}
		if ( in_array( $synchronous, self::SQLITE_SYNCHRONOUS_SETTINGS, true ) ) {
			$this->query( 'PRAGMA synchronous = ' . $synchronous );
		}
	}

	/**
	 * Execute a query in SQLite.
	 *
	 * @param  string $sql   The query to execute.
	 * @param  array $params The query parameters.
	 * @throws PDOException  When the query execution fails.
	 * @return PDOStatement  The PDO statement object.
	 */
	public function query( string $sql, array $params = array() ): PDOStatement {
		if ( $this->query_logger ) {
			( $this->query_logger )( $sql, $params );
		}
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( $params );
		return $stmt;
	}

	/**
	 * Prepare a SQLite query for execution.
	 *
	 * @param  string $sql  The query to prepare.
	 * @return PDOStatement The prepared statement.
	 * @throws PDOException When the query preparation fails.
	 */
	public function prepare( string $sql ): PDOStatement {
		if ( $this->query_logger ) {
			( $this->query_logger )( $sql, array() );
		}
		return $this->pdo->prepare( $sql );
	}

	/**
	 * Returns the ID of the last inserted row.
	 *
	 * @return string The ID of the last inserted row.
	 */
	public function get_last_insert_id(): string {
		return $this->pdo->lastInsertId();
	}

	/**
	 * Quote a value for use in a query.
	 *
	 * @param  mixed  $value The value to quote.
	 * @param  int    $type  The type of the value.
	 * @return string        The quoted value.
	 */
	public function quote( $value, int $type = PDO::PARAM_STR ): string {
		return $this->pdo->quote( $value, $type );
	}

	/**
	 * Quote an SQLite identifier.
	 *
	 * Wraps the identifier in backticks and escapes backtick characters within.
	 *
	 * ---
	 *
	 * Quoted identifiers in SQLite are represented by string constants:
	 *
	 *   A string constant is formed by enclosing the string in single quotes (').
	 *   A single quote within the string can be encoded by putting two single
	 *   quotes in a row - as in Pascal. C-style escapes using the backslash
	 *   character are not supported because they are not standard SQL.
	 *
	 * See: https://www.sqlite.org/lang_expr.html#literal_values_constants_
	 *
	 * Although sparsely documented, this applies to backtick and double quoted
	 * string constants as well, so only the quote character needs to be escaped.
	 *
	 * For more details, see the grammar for SQLite table and column names:
	 *
	 *   - https://github.com/sqlite/sqlite/blob/873fc5dff2a781251f2c9bd2c791a5fac45b7a2b/src/tokenize.c#L395-L419
	 *   - https://github.com/sqlite/sqlite/blob/873fc5dff2a781251f2c9bd2c791a5fac45b7a2b/src/parse.y#L321-L338
	 *
	 * ---
	 *
	 * We use backtick quotes instead of the SQL standard double quotes, due to
	 * an SQLite quirk causing double-quoted strings to be accepted as literals:
	 *
	 *   This misfeature means that a misspelled double-quoted identifier will
	 *   be interpreted as a string literal, rather than generating an error.
	 *
	 * See: https://www.sqlite.org/quirks.html#double_quoted_string_literals_are_accepted
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	public function quote_identifier( string $unquoted_identifier ): string {
		return '`' . str_replace( '`', '``', $unquoted_identifier ) . '`';
	}

	/**
	 * Get the PDO object.
	 *
	 * @return PDO
	 */
	public function get_pdo(): PDO {
		return $this->pdo;
	}

	/**
	 * Set or clear a logger for SQLite queries.
	 *
	 * @param (callable(string, array): void)|null $logger A query logger callback, or null to clear it.
	 */
	public function set_query_logger( ?callable $logger ): void {
		$this->query_logger = $logger;
	}
}
