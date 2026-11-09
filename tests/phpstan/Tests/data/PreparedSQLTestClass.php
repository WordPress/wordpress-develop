<?php
/**
 * Fixture for PreparedSQLRule: the database object held in a property.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests\data;

use wpdb;

/**
 * Queries through a property.
 */
class PreparedSQLTestClass {

	/**
	 * Database.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Something else.
	 *
	 * @var object
	 */
	private $other;

	/**
	 * Queries.
	 *
	 * @param int $foo Foo.
	 */
	public function run( $foo ): void {
		$this->db->query( "SELECT * FROM {$this->db->posts} WHERE ID = $foo" ); // Bad.
		$this->db->query( "SELECT * FROM {$this->db->posts} WHERE ID = 1" ); // OK.
		$this->other->query( "SELECT * FROM {$this->other->posts} WHERE ID = $foo" ); // OK, not the database object.
		$this->db->prepare( 'SELECT * FROM `table`', $foo ); // Not this rule's concern.
	}
}
