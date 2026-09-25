<?php
/**
 * @group phpunit
 */
class Tests_PHPUnit_GroupFilters extends WP_UnitTestCase {

	public function tear_down() {
		WP_PHPUnit_Util_Getopt::reset();
		parent::tear_down();
	}

	public function test_single_site_default_excluded_groups_are_reported() {
		$this->assertSame(
			array( 'ajax', 'external-http' ),
			WP_UnitTestCase_Base::get_default_excluded_groups_for_test( array( 'ajax', 'external-http', 'query' ) )
		);
	}

	public function test_requested_group_is_not_reported_as_default_excluded() {
		ob_start();
		new WP_PHPUnit_Util_Getopt(
			array(
				'phpunit',
				'--group',
				'ajax',
			)
		);
		ob_end_clean();

		$this->assertSame(
			array(),
			WP_UnitTestCase_Base::get_default_excluded_groups_for_test( array( 'ajax' ) )
		);
	}

	public function test_exclude_group_does_not_clear_default_excluded_groups() {
		ob_start();
		new WP_PHPUnit_Util_Getopt(
			array(
				'phpunit',
				'--exclude-group',
				'import',
			)
		);
		ob_end_clean();

		$multisite_excluded_group = is_multisite() ? 'ms-excluded' : 'ms-required';

		$this->assertSame(
			array( 'ajax', $multisite_excluded_group ),
			WP_UnitTestCase_Base::get_default_excluded_groups_for_test( array( 'ajax', $multisite_excluded_group ) )
		);
	}
}
