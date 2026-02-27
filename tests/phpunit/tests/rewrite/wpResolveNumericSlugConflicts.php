<?php

/**
 * @group rewrite
 * @covers ::wp_resolve_numeric_slug_conflicts
 */
class Tests_Rewrite_wpResolveNumericSlugConflicts extends WP_UnitTestCase {

	/**
	 * Fixed date post ID.
	 *
	 * @var int
	 */
	public static $post_with_date;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_with_date = $factory->post->create(
			array(
				'post_date' => '2020-01-05 12:00:00',
				'post_name' => 'post-with-date',
			)
		);
	}

	/**
	 * @ticket 52252
	 * @dataProvider data_should_not_throw_warning_for_malformed_date_queries
	 *
	 * @param string $permalink_structure Permalink structure.
	 * @param array  $query_vars          Query string parameters.
	 */
	public function test_should_not_throw_warning_for_malformed_date_queries( $permalink_structure, $query_vars ) {
		$this->set_permalink_structure( $permalink_structure );

		/*
		 * For malformed date queries, the function is unable to identify the requested post,
		 * and just returns the initial query vars.
		 */
		$this->assertSame( $query_vars, wp_resolve_numeric_slug_conflicts( $query_vars ) );
	}

	/**
	 * Data provider for test_should_not_throw_warning_for_malformed_date_queries().
	 *
	 * @return array Test data.
	 */
	public function data_should_not_throw_warning_for_malformed_date_queries() {
		return array(
			'/%postname%/ with missing year'         => array(
				'permalink_structure' => '/%postname%/',
				'query'               => array(
					'monthnum' => 1,
					'day'      => 15,
				),
			),
			'/%postname%/ with month only'           => array(
				'permalink_structure' => '/%postname%/',
				'query'               => array(
					'monthnum' => 1,
				),
			),
			'/%year%/%postname%/ with missing month' => array(
				'permalink_structure' => '/%year%/%postname%/',
				'query'               => array(
					'year' => 2020,
					'day'  => 15,
				),
			),
		);
	}
}
