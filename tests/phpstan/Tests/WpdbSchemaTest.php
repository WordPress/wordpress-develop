<?php
/**
 * Tests for WpdbSchema.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPUnit\Framework\TestCase;
use WordPress\PHPStan\WpdbSchema;

/**
 * Checks the columns read from the `CREATE TABLE` statements of a schema.
 */
final class WpdbSchemaTest extends TestCase {

	/**
	 * Gets the schema read from the fixture.
	 *
	 * @return WpdbSchema
	 */
	private static function get_schema(): WpdbSchema {
		return new WpdbSchema( __DIR__ . '/data/wpdb-schema.php' );
	}

	/**
	 * Tests that each column is read, in order, with its type, and that keys are not columns.
	 */
	public function test_get_columns(): void {
		$this->assertSame(
			array(
				array(
					'name'     => 'thing_ID',
					'type'     => 'decimal-int-string',
					'optional' => false,
				),
				array(
					'name'     => 'thing_name',
					'type'     => 'string',
					'optional' => false,
				),
				array(
					'name'     => 'thing_note',
					'type'     => 'string|null',
					'optional' => false,
				),
				array(
					'name'     => 'thing_score',
					'type'     => 'numeric-string',
					'optional' => false,
				),
				array(
					'name'     => 'thing_date',
					'type'     => 'non-falsy-string',
					'optional' => false,
				),
			),
			self::get_schema()->get_columns( 'things' )
		);
	}

	/**
	 * Tests that the columns only a later definition of a table adds are optional.
	 */
	public function test_get_columns_of_redefined_table(): void {
		$this->assertSame(
			array(
				array(
					'name'     => 'ID',
					'type'     => 'decimal-int-string',
					'optional' => false,
				),
				array(
					'name'     => 'user_login',
					'type'     => 'string',
					'optional' => false,
				),
				array(
					'name'     => 'spam',
					'type'     => 'decimal-int-string',
					'optional' => true,
				),
			),
			self::get_schema()->get_columns( 'users' )
		);
	}

	/**
	 * Tests looking up tables and columns.
	 */
	public function test_tables_and_columns(): void {
		$schema = self::get_schema();

		$this->assertTrue( $schema->has_table( 'things' ) );
		$this->assertFalse( $schema->has_table( 'posts' ) );
		$this->assertSame( array(), $schema->get_columns( 'posts' ) );

		$this->assertSame( 'thing_ID', $schema->get_column( 'things', 'THING_id' )['name'] ?? null, 'Column names should be case-insensitive.' );
		$this->assertNull( $schema->get_column( 'things', 'thing_missing' ) );
		$this->assertNull( $schema->get_column( 'posts', 'ID' ) );
	}

	/**
	 * Tests that a schema file which cannot be read has no tables.
	 */
	public function test_missing_schema_file(): void {
		$schema = new WpdbSchema( __DIR__ . '/data/wpdb-schema-missing.php' );

		$this->assertFalse( @$schema->has_table( 'things' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The warning of file_get_contents() is expected.
	}

	/**
	 * Tests that the schema of core is read by default.
	 */
	public function test_core_schema(): void {
		$schema = new WpdbSchema();

		foreach ( array( 'posts', 'postmeta', 'comments', 'terms', 'term_taxonomy', 'options', 'users', 'usermeta', 'blogs', 'signups' ) as $table ) {
			$this->assertTrue( $schema->has_table( $table ), "The $table table should be read." );
		}
		$this->assertSame(
			array(
				'name'     => 'ID',
				'type'     => 'decimal-int-string',
				'optional' => false,
			),
			$schema->get_column( 'posts', 'ID' )
		);
		$this->assertTrue( $schema->get_column( 'users', 'spam' )['optional'] ?? false, 'The multisite users columns should be optional.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: string }>
	 */
	public function data_get_value_type(): array {
		return array(
			'tinyint'    => array( 'tinyint', 'decimal-int-string' ),
			'int'        => array( 'int', 'decimal-int-string' ),
			'BIGINT'     => array( 'BIGINT', 'decimal-int-string' ),
			'decimal'    => array( 'decimal', 'numeric-string' ),
			'float'      => array( 'float', 'numeric-string' ),
			'double'     => array( 'double', 'numeric-string' ),
			'datetime'   => array( 'datetime', 'non-falsy-string' ),
			'date'       => array( 'date', 'non-falsy-string' ),
			'timestamp'  => array( 'timestamp', 'non-falsy-string' ),
			'time'       => array( 'time', 'non-falsy-string' ),
			'varchar'    => array( 'varchar', 'string' ),
			'longtext'   => array( 'longtext', 'string' ),
			'char'       => array( 'char', 'string' ),
			'enum'       => array( 'enum', 'string' ),
			'unexpected' => array( 'geometry', 'string' ),
		);
	}

	/**
	 * Tests the type of the values `wpdb` returns for each column type.
	 *
	 * @dataProvider data_get_value_type
	 *
	 * @param string $sql_type SQL column type.
	 * @param string $expected Expected PHPDoc type.
	 */
	public function test_get_value_type( string $sql_type, string $expected ): void {
		$this->assertSame( $expected, WpdbSchema::get_value_type( $sql_type ) );
	}
}
