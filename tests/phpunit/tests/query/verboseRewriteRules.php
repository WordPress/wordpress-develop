<?php

require_once __DIR__ . '/conditionals.php';

/**
 * @group query
 * @group rewrite
 * @covers WP_Query
 */
class Tests_Query_VerbosePageRules extends Tests_Query_Conditionals {
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%category%/%year%/%postname%/' );
		create_initial_taxonomies();
	}
}
