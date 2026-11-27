<?php
/**
 * The columns of the core database tables, for `WpdbQueryDynamicMethodReturnTypeExtension`.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

/**
 * Reads the columns of the core database tables from `wp_get_db_schema()`.
 *
 * The `CREATE TABLE` statements are read from the source of
 * `wp-admin/includes/schema.php` rather than copied, so that a column added to
 * the schema is known without any change here.
 *
 * Each column is given the PHPDoc type of the values `wpdb` returns for it.
 * `wpdb` does not have mysqli convert values to native types, so every value
 * is a string, or null for SQL NULL. The type narrows the string by the column
 * type, so an integer column is a `decimal-int-string`, for example.
 *
 * A table defined more than once, which is only `users`, has the columns of its
 * first definition, plus those which only a later one adds (`spam` and
 * `deleted`, which only the multisite flavor of `users` has) as optional.
 *
 * @phpstan-type Column array{ name: non-empty-string, type: non-empty-string, optional: bool }
 */
final class WpdbSchema {

	/**
	 * Columns of each table, keyed by the table's `wpdb` property name and the lowercase column name.
	 *
	 * @var array<string, array<string, array{ name: non-empty-string, type: non-empty-string, optional: bool }>>|null
	 * @phpstan-var array<string, array<string, Column>>|null
	 */
	private ?array $tables = null;

	/**
	 * Path to `schema.php`.
	 *
	 * @var string
	 */
	private string $schema_file;

	/**
	 * Constructor.
	 *
	 * @param string|null $schema_file Optional. Path to `schema.php`. Defaults to the one in `src/`.
	 */
	public function __construct( ?string $schema_file = null ) {
		$this->schema_file = $schema_file ?? dirname( __DIR__, 2 ) . '/src/wp-admin/includes/schema.php';
	}

	/**
	 * Determines whether a table is a core table.
	 *
	 * @param string $table Table, by its `wpdb` property name, like `posts`.
	 * @return bool
	 */
	public function has_table( string $table ): bool {
		return isset( $this->get_tables()[ $table ] );
	}

	/**
	 * Gets the columns of a table, in order.
	 *
	 * @param string $table Table, by its `wpdb` property name, like `posts`.
	 * @return list<array{ name: non-empty-string, type: non-empty-string, optional: bool }>
	 * @phpstan-return list<Column>
	 */
	public function get_columns( string $table ): array {
		return array_values( $this->get_tables()[ $table ] ?? array() );
	}

	/**
	 * Gets a column of a table.
	 *
	 * @param string $table  Table, by its `wpdb` property name, like `posts`.
	 * @param string $column Column name, in any case, since MySQL column names are case-insensitive.
	 * @return array{ name: non-empty-string, type: non-empty-string, optional: bool }|null Column, or null if the table
	 *                                                                                      has no such column.
	 * @phpstan-return Column|null
	 */
	public function get_column( string $table, string $column ): ?array {
		return $this->get_tables()[ $table ][ strtolower( $column ) ] ?? null;
	}

	/**
	 * Reads the tables from `schema.php`, once.
	 *
	 * @return array<string, array<string, array{ name: non-empty-string, type: non-empty-string, optional: bool }>>
	 * @phpstan-return array<string, array<string, Column>>
	 */
	private function get_tables(): array {
		if ( null !== $this->tables ) {
			return $this->tables;
		}

		$this->tables = array();

		$source = file_get_contents( $this->schema_file );
		if ( false === $source ) {
			return $this->tables;
		}

		preg_match_all( '/CREATE TABLE \$wpdb->(\w+) \((.*?)\n\) \$charset_collate;/s', $source, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$table       = $match[1];
			$is_redefine = isset( $this->tables[ $table ] );

			foreach ( explode( "\n", $match[2] ) as $line ) {
				if ( ! preg_match( '/^\s*(\w+)\s+(\w+)(.*)$/', $line, $column_match ) ) {
					continue;
				}
				list( , $name, $sql_type, $definition ) = $column_match;
				if ( in_array( strtoupper( $name ), array( 'PRIMARY', 'KEY', 'UNIQUE', 'INDEX', 'FULLTEXT', 'SPATIAL', 'CONSTRAINT', 'FOREIGN' ), true ) ) {
					continue;
				}

				$key = strtolower( $name );
				if ( $is_redefine && isset( $this->tables[ $table ][ $key ] ) ) {
					continue;
				}

				$type = self::get_value_type( $sql_type );
				if ( ! preg_match( '/\bNOT NULL\b/i', $definition ) ) {
					$type .= '|null';
				}

				$this->tables[ $table ][ $key ] = array(
					'name'     => $name,
					'type'     => $type,
					'optional' => $is_redefine,
				);
			}
		}

		return $this->tables;
	}

	/**
	 * Gets the type of the non-null values `wpdb` returns for a column type.
	 *
	 * @param string $sql_type SQL column type, like `bigint` or `varchar`.
	 * @return non-empty-string PHPDoc type.
	 */
	public static function get_value_type( string $sql_type ): string {
		switch ( strtolower( $sql_type ) ) {
			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'int':
			case 'integer':
			case 'bigint':
				return 'decimal-int-string';
			case 'decimal':
			case 'numeric':
			case 'float':
			case 'double':
			case 'real':
				return 'numeric-string';
			case 'datetime':
			case 'date':
			case 'timestamp':
			case 'time':
				// Even the zero value, like '0000-00-00 00:00:00', is not falsy.
				return 'non-falsy-string';
			default:
				return 'string';
		}
	}
}
