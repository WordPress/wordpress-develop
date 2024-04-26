<?php
/**
 * @ticket 60862
 * @group dependencies
 * @group scripts
 * @covers ::wp_localize_script
 */
class Test_Dependencies_LocalizeScript extends WP_UnitTestCase {
	/**
	 * Test if wp_localize_script() works.
	 */
	public function test_wp_localize_script_works_before_enqueue_script() {
		$this->assertTrue(
			wp_localize_script(
				'wp-util',
				'salcodeExample',
				array(
					'answerToTheUltimateQuestionOfLifeTheUniverseAndEverything' => 42,
				)
			)
		);
	}
}
