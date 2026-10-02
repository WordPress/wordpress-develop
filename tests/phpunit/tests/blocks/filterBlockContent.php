<?php

/**
 * Tests for filter_block_content().
 *
 * @group blocks
 *
 * @covers ::filter_block_content
 */
class Tests_Blocks_FilterBlockContent extends WP_UnitTestCase {

	/**
	 * Tests that the Template Part block's `tagName` attribute is replaced with an
	 * empty string unless it is a string naming an allowed HTML element.
	 *
	 * @covers ::filter_block_core_template_part_attributes
	 *
	 * @dataProvider data_template_part_tag_name
	 *
	 * @param string $content  The block content to filter.
	 * @param string $expected The expected filtered content.
	 */
	public function test_template_part_tag_name( $content, $expected ) {
		$this->assertSame( $expected, filter_block_content( $content, 'post' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_template_part_tag_name() {
		return array(
			'an object value'           => array(
				'<!-- wp:template-part {"tagName":{"x":"script"}} /-->',
				'<!-- wp:template-part {"tagName":""} /-->',
			),
			'a list value'              => array(
				'<!-- wp:template-part {"tagName":["script"]} /-->',
				'<!-- wp:template-part {"tagName":""} /-->',
			),
			'a boolean value'           => array(
				'<!-- wp:template-part {"tagName":true} /-->',
				'<!-- wp:template-part {"tagName":""} /-->',
			),
			'a number value'            => array(
				'<!-- wp:template-part {"tagName":42} /-->',
				'<!-- wp:template-part {"tagName":""} /-->',
			),
			'a disallowed element name' => array(
				'<!-- wp:template-part {"tagName":"script"} /-->',
				'<!-- wp:template-part {"tagName":""} /-->',
			),
			'an allowed element name'   => array(
				'<!-- wp:template-part {"tagName":"strong"} /-->',
				'<!-- wp:template-part {"tagName":"strong"} /-->',
			),
		);
	}

	/**
	 * Tests that a `tagName` attribute on a block other than the Template Part block
	 * is left alone.
	 *
	 * @covers ::filter_block_core_template_part_attributes
	 */
	public function test_tag_name_of_other_block_is_not_filtered() {
		$content = '<!-- wp:group {"tagName":{"x":"script"}} /-->';

		$this->assertSame( $content, filter_block_content( $content, 'post' ) );
	}
}
