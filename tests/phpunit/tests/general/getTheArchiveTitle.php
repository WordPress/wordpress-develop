<?php

/**
 * Tests for the `get_the_archive_title()` function on date archives.
 *
 * @group general
 * @group template
 *
 * @covers ::get_the_archive_title
 */
class Tests_General_GetTheArchiveTitle extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2015-09-22 18:52:17',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		update_option( 'start_of_week', 1 );
		update_option( 'date_format', 'F j, Y' );
	}

	/**
	 * Tests that a weekly archive uses the week's date range, not the year.
	 *
	 * @ticket 66489
	 */
	public function test_get_the_archive_title_on_weekly_archive() {
		$this->go_to( '?m=2015&w=39' );

		$this->assertTrue( is_year(), 'Weekly archive links also set is_year().' );
		$this->assertSame( 'Week: <span>September 21, 2015 &#8211; September 27, 2015</span>', get_the_archive_title() );
	}

	/**
	 * Tests that a weekly archive respects the start_of_week option.
	 *
	 * @ticket 66489
	 */
	public function test_get_the_archive_title_on_weekly_archive_respects_start_of_week() {
		update_option( 'start_of_week', 0 );

		$this->go_to( '?m=2015&w=38' );

		$this->assertSame( 'Week: <span>September 20, 2015 &#8211; September 26, 2015</span>', get_the_archive_title() );
	}

	/**
	 * Tests that a weekly archive without the year uses the week's date range.
	 *
	 * @ticket 66489
	 */
	public function test_get_the_archive_title_on_weekly_archive_without_year() {
		$this->go_to( '?w=39' );

		$this->assertSame( 'Week: <span>September 21, 2015 &#8211; September 27, 2015</span>', get_the_archive_title() );
	}

	/**
	 * @ticket 66489
	 */
	public function test_get_the_archive_title_on_yearly_archive() {
		$this->go_to( '?m=2015' );

		$this->assertSame( 'Year: <span>2015</span>', get_the_archive_title() );
	}

	/**
	 * @ticket 66489
	 */
	public function test_get_the_archive_title_on_monthly_archive() {
		$this->go_to( '?m=201509' );

		$this->assertSame( 'Month: <span>September 2015</span>', get_the_archive_title() );
	}

	/**
	 * @ticket 66489
	 */
	public function test_get_the_archive_title_on_daily_archive() {
		$this->go_to( '?m=20150922' );

		$this->assertSame( 'Day: <span>September 22, 2015</span>', get_the_archive_title() );
	}
}
