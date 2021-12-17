<?php
/**
 * WordPress partial query builder class.
 *
 * @package WordPress
 * @subpackage Database
 */
/**
 * Class WP_DB_Partial_Query.
 *
 * This class is used as a query builder to generate partial escaped queries.
 */
final class WP_DB_Partial_Query {

	/**
	 * Actual prepared query.
	 *
	 * @var string|void
	 */
	private $query;

	/**
	 * WP_DB_Partial_Query constructor.
	 *
	 * Accepts exactly the same parameters as $wpdb->prepare().
	 *
	 * @param $query
	 * @param ...$args
	 */
	public function __construct( $query, ...$args ) {
		global $wpdb;
		$this->query = $wpdb->prepare( $query, ...$args );
	}

	/**
	 * Get prepared query.
	 *
	 * @return string|void The prepared query.
	 */
	public function get_query() {
		return $this->query;
	}

	/**
	 * Implementing this magic method allows object of this class to be interpolated in sprintf, vprintf etc methods.
	 *
	 * @return string The prepared query.
	 */
	public function __toString() {
		return $this->get_query();
	}
}
