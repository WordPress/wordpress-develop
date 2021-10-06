<?php
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . WPINC . '/class-IXR.php';
require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';

abstract class WP_XMLRPC_UnitTestCase extends WP_UnitTestCase {
	protected $myxmlrpcserver;

	function set_up() {
		parent::set_up();

		add_filter( 'pre_option_enable_xmlrpc', '__return_true' );

		$this->myxmlrpcserver = new wp_xmlrpc_server();
	}

	function tear_down() {
		remove_filter( 'pre_option_enable_xmlrpc', '__return_true' );

		$this->remove_added_uploads();

		parent::tear_down();
	}

	protected static function make_user_by_role( $role ) {
		return self::factory()->user->create(
			array(
				'user_login' => $role,
				'user_pass'  => $role,
				'role'       => $role,
			)
		);
	}

}
