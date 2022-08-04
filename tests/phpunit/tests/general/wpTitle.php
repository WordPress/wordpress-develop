<?php

/**
 * @group general
 * @group template
 * @covers ::wp_title
 */
class Tests_General_WpTitle extends WP_UnitTestCase {

	/**
	 * @ticket 31521
	 *
	 * @dataProvider data_wp_title_archive
	 */
	public function test_wp_title_archive( $query, $expected ) {
		self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
				'post_type'   => 'post',
				'post_date'   => '2021-11-01 18:52:17',
			)
		);
		$this->go_to( '?m=' . $query );

		$this->assertSame( $expected, wp_title( '&raquo;', false ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_title_archive() {
		return array(
			'year with posts'                => array(
				'query'    => '2021',
				'expected' => ' &raquo; 2021',
			),
			'year without posts'             => array(
				'query'    => '1910',
				'expected' => ' &raquo; Page not found',
			),
			'year and month with posts'      => array(
				'query'    => '202111',
				'expected' => ' &raquo; 2021 &raquo; November',
			),
			'year and month without posts'   => array(
				'query'    => '202101',
				'expected' => ' &raquo; Page not found',
			),
			'year, month, day with posts'    => array(
				'query'    => '20211101',
				'expected' => ' &raquo; 2021 &raquo; November &raquo; 1',
			),
			'year, month, day without posts' => array(
				'query'    => '20210101',
				'expected' => ' &raquo; Page not found',
			),
		);
	}
}
