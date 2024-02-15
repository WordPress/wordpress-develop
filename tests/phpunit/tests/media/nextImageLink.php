<?php

require_once __DIR__ . '/testcase-adjacent-image-link.php';

/**
 * @group media
 * @covers ::next_image_link
 */
class Tests_Media_NextImageLink extends WP_Test_Adjacent_Image_Link_TestCase {
	protected $default_args = array(
		'size' => 'thumbnail',
		'text' => false,
	);

	/**
	 * @ticket 45708
	 *
	 * @dataProvider data_next_image_link
	 */
	public function test_next_image_link( $current_attachment_index, $expected_attachment_index, $expected, array $args = array() ) {
		list( $expected, $args ) = $this->setup_test_scenario( $current_attachment_index, $expected_attachment_index, $expected, $args );

		$this->expectOutputString( $expected );
		$this->assertNull( next_image_link( ...$args ) );
	}

	public function data_next_image_link() {
		return array(
			// Happy paths.
			'when has next link'           => array(
				'current_attachment_index'  => 4,
				'expected_attachment_index' => 5,
				'expected'                  => '<a href=\'http://example.org/?attachment_id=%%ID%%\'><img width="1" height="1" src="http://example.org/wp-content/uploads/image5.jpg" class="attachment-thumbnail size-thumbnail" alt="" loading="lazy" /></a>',
			),
			'with text when has next link' => array(
				'current_attachment_index'  => 4,
				'expected_attachment_index' => 5,
				'expected'                  => '<a href=\'http://example.org/?attachment_id=%%ID%%\'>Some text</a>',
				'args'                      => array( 'text' => 'Some text' ),
			),

			// Unhappy paths.
			'when no next link'            => array(
				'current_attachment_index'  => 5,
				'expected_attachment_index' => 0,
				'expected'                  => '',
			),
			'with text when no next link'  => array(
				'current_attachment_index'  => 5,
				'expected_attachment_index' => 0,
				'expected'                  => '',
				'args'                      => array( 'text' => 'Some text' ),
			),
		);
	}
}
