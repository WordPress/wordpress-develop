<?php

/**
 * Tests for the `the_date_xml()` function.
 *
 * @group general
 * @group template
 * @group post
 *
 * @covers ::the_date_xml
 */
class Tests_General_TheDateXml extends WP_UnitTestCase {

	/**
	 * Tests that the_date_xml() outputs the formatted date for the global post.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_the_date_xml
	 *
	 * @param string $post_date Post date in MySQL format.
	 * @param string $expected  Expected output formatted as Y-m-d.
	 */
	public function test_the_date_xml_should_output_formatted_date( $post_date, $expected ) {
		global $post;

		$post = self::factory()->post->create_and_get(
			array(
				'post_date' => $post_date,
			)
		);

		ob_start();
		the_date_xml();
		$output = ob_get_clean();

		$this->assertSame( $expected, $output );
	}

	/**
	 * Data provider for test_the_date_xml_should_output_formatted_date().
	 *
	 * @return array[]
	 */
	public static function data_the_date_xml() {
		return array(
			'standard date'         => array(
				'post_date' => '2026-05-15 12:30:00',
				'expected'  => '2026-05-15',
			),
			'leap year date'        => array(
				'post_date' => '2024-02-29 08:15:30',
				'expected'  => '2024-02-29',
			),
			'end of year date'      => array(
				'post_date' => '2025-12-31 23:59:59',
				'expected'  => '2025-12-31',
			),
			'start of century date' => array(
				'post_date' => '2000-01-01 00:00:00',
				'expected'  => '2000-01-01',
			),
		);
	}

	/**
	 * Tests that the_date_xml() works correctly inside the loop.
	 *
	 * @ticket 65819
	 */
	public function test_the_date_xml_in_the_loop() {
		$post_id = self::factory()->post->create(
			array(
				'post_date' => '2026-01-15 12:00:00',
			)
		);

		$query = new WP_Query(
			array(
				'p' => $post_id,
			)
		);

		$this->assertTrue( $query->have_posts(), 'Query should have posts.' );

		while ( $query->have_posts() ) {
			$query->the_post();

			ob_start();
			the_date_xml();
			$output = ob_get_clean();

			$this->assertSame( '2026-01-15', $output );
		}

		wp_reset_postdata();
	}
}
