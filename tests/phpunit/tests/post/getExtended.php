<?php
/**
 * Tests for get_extended().
 *
 * @group post
 *
 * @covers ::get_extended
 */
class Tests_Post_GetExtended extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_get_extended
	 *
	 * @param string $content  Post content.
	 * @param array  $expected Expected split result.
	 */
	public function test_get_extended( $content, $expected ) {
		$this->assertSame( $expected, get_extended( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_extended() {
		return array(
			'no more tag'                => array(
				'Hello world.',
				array(
					'main'      => 'Hello world.',
					'extended'  => '',
					'more_text' => '',
				),
			),
			'basic more split'           => array(
				"Before.\n<!--more-->\nAfter.",
				array(
					'main'      => 'Before.',
					'extended'  => 'After.',
					'more_text' => '',
				),
			),
			'custom more text'           => array(
				'Intro<!--more Continue Reading-->Rest',
				array(
					'main'      => 'Intro',
					'extended'  => 'Rest',
					'more_text' => 'Continue Reading',
				),
			),
			'trims surrounding newlines' => array(
				"\nBefore\n<!--more Read-->\nAfter\n",
				array(
					'main'      => 'Before',
					'extended'  => 'After',
					'more_text' => 'Read',
				),
			),
			'spaced more marker ignored' => array(
				'Before<!-- more -->After',
				array(
					'main'      => 'Before<!-- more -->After',
					'extended'  => '',
					'more_text' => '',
				),
			),
		);
	}
}
