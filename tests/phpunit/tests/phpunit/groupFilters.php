<?php
/**
 * @group phpunit
 */
class Tests_PHPUnit_GroupFilters extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->parse_arguments( array( 'phpunit' ) );
	}

	public function tear_down() {
		$this->parse_arguments( $_SERVER['argv'] );
		parent::tear_down();
	}

	public function test_single_site_default_excluded_groups_are_reported() {
		$this->assertSame(
			array( 'ajax', 'external-http' ),
			WP_UnitTestCase_Base::get_default_excluded_groups_for_test( array( 'ajax', 'external-http', 'query' ) )
		);
	}

	/**
	 * @dataProvider data_group_argument_syntax
	 *
	 * @param string[] $arguments Command line arguments.
	 */
	public function test_requested_group_is_not_reported_as_default_excluded( $arguments ) {
		$this->parse_arguments( $arguments );

		$this->assertSame(
			array(),
			WP_UnitTestCase_Base::get_default_excluded_groups_for_test( array( 'ajax' ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array<int, array<int, string>>>
	 */
	public static function data_group_argument_syntax() {
		return array(
			'spaced' => array( array( 'phpunit', '--group', 'ajax' ) ),
			'equals' => array( array( 'phpunit', '--group=ajax' ) ),
		);
	}

	public function test_exclude_group_does_not_clear_default_excluded_groups() {
		$this->parse_arguments(
			array(
				'phpunit',
				'--exclude-group',
				'import',
			)
		);

		$multisite_excluded_group = is_multisite() ? 'ms-excluded' : 'ms-required';

		$this->assertSame(
			array( 'ajax', $multisite_excluded_group ),
			WP_UnitTestCase_Base::get_default_excluded_groups_for_test( array( 'ajax', $multisite_excluded_group ) )
		);
	}

	public function test_requested_group_allows_a_test_in_a_default_excluded_group() {
		$this->parse_arguments( array( 'phpunit', '--group', 'xdebug' ) );

		$this->assertSame(
			array(),
			WP_UnitTestCase_Base::get_default_excluded_groups_for_test( array( 'ajax', 'xdebug' ) )
		);
	}

	public function test_default_group_filter_removes_tests_from_nested_suites() {
		$class_suite = class_exists( 'PHPUnit_Framework_TestSuite' ) ? new PHPUnit_Framework_TestSuite() : new PHPUnit\Framework\TestSuite();
		$class_suite->addTest( clone $this, array( 'phpunit' ) );
		$class_suite->addTest( clone $this, array( 'ajax' ) );
		$class_suite->addTest( clone $this, array( 'phpunit' ) );

		$root_suite = new PHPUnit\Framework\TestSuite();
		$root_suite->addTest( $class_suite );

		$filter = new WP_PHPUnit_Default_Group_Filter();
		$filter->startTestSuite( $root_suite );

		$this->assertCount( 2, $root_suite );
		$this->assertCount( 2, $class_suite );
	}

	/**
	 * Parses command line arguments without printing group notices.
	 *
	 * @param string[] $arguments Command line arguments.
	 */
	private function parse_arguments( $arguments ) {
		ob_start();
		new WP_PHPUnit_Util_Getopt( $arguments );
		ob_end_clean();
	}
}
