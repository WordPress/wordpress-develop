<?php

abstract class Admin_WpListTable_TestCase extends WP_UnitTestCase {

	/**
	 * List table.
	 *
	 * @var WP_List_Table $list_table
	 */
	protected $list_table;

	/**
	 * Original value of $GLOBALS['hook_suffix'].
	 *
	 * @var string
	 */
	private static $original_hook_suffix;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( isset( $GLOBALS['hook_suffix'] ) ) {
			self::$original_hook_suffix = $GLOBALS['hook_suffix'];
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	public function set_up() {
		parent::set_up();
		global $hook_suffix;
		$hook_suffix      = '_wp_tests';
		$this->list_table = new WP_List_Table();
	}

	public function clean_up_global_scope() {
		global $hook_suffix;

		if ( isset( static::$original_hook_suffix ) ) {
			$hook_suffix = self::$original_hook_suffix;
		} else {
			unset( $hook_suffix );
		}

		parent::clean_up_global_scope();
	}
}
