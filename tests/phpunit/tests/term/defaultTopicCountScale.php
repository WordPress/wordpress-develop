<?php

/**
 * Tests for default_topic_count_scale().
 *
 * @group taxonomy
 *
 * @covers ::default_topic_count_scale
 */
class Tests_Term_DefaultTopicCountScale extends WP_UnitTestCase {

	/**
	 * Tests that a count is scaled logarithmically.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_counts
	 *
	 * @param int|string $count    Number of posts with the tag.
	 * @param int        $expected Expected scaled count.
	 */
	public function test_scales_count_logarithmically( $count, $expected ) {
		$this->assertSame( $expected, default_topic_count_scale( $count ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: int|string, 1: int}>
	 */
	public static function data_counts() {
		return array(
			'zero'                => array( 0, 0 ),
			'one'                 => array( 1, 30 ),
			'rounds down'         => array( 2, 48 ),
			'rounds up'           => array( 4, 70 ),
			'nine'                => array( 9, 100 ),
			'ninety-nine'         => array( 99, 200 ),
			'nine hundred ninety' => array( 999, 300 ),
			'numeric string'      => array( '9', 100 ),
		);
	}

	/**
	 * Tests that wp_generate_tag_cloud() uses this scale for font sizes by default.
	 *
	 * With counts of 1, 4 and 9 the scaled counts are 30, 70 and 100, so the middle tag
	 * gets 16pt between 8pt and 22pt. A linear scale would give it 13.25pt.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_generate_tag_cloud
	 */
	public function test_tag_cloud_uses_the_scale_by_default() {
		$tags = array();
		foreach ( array( 1, 4, 9 ) as $count ) {
			$tag        = get_term( self::factory()->tag->create( array( 'name' => "Count $count" ) ), 'post_tag' );
			$tag->count = $count;
			$tag->link  = get_term_link( $tag );
			$tags[]     = $tag;
		}

		$links = wp_generate_tag_cloud(
			$tags,
			array(
				'smallest' => 8,
				'largest'  => 22,
				'unit'     => 'pt',
				'orderby'  => 'count',
				'format'   => 'array',
			)
		);

		$this->assertCount( 3, $links, 'There should be one link per tag.' );
		$this->assertStringContainsString( 'font-size: 8pt;', $links[0], 'The least used tag should get the smallest size.' );
		$this->assertStringContainsString( 'font-size: 16pt;', $links[1], 'The middle tag should be sized by the logarithmic scale.' );
		$this->assertStringContainsString( 'font-size: 22pt;', $links[2], 'The most used tag should get the largest size.' );
	}
}
