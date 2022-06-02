<?php

abstract class WP_Test_Adjacent_Image_Link_TestCase extends WP_UnitTestCase {
	/**
	 * Array of 5 attachments for use in the tests.
	 *
	 * @var init{}|WP_Error[]
	 */
	protected static $attachments;

	/**
	 * Default args for the function being tested.
	 *
	 * Defined in each test class.
	 *
	 * @var int[]|WP_Error[] Array of attachment IDs.
	 */
	protected $default_args = array();

	/**
	 * Setup the tests after the data provider but before the tests start.
	 *
	 * @param WP_UnitTest_Factory $factory Instance of the factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$parent_id = $factory->post->create();

		for ( $index = 1; $index <= 5; $index++ ) {
			self::$attachments[ $index ] = $factory->attachment->create_object(
				"image{$index}.jpg",
				$parent_id,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
		}
	}

	/**
	 * Sets up the test scenario.
	 *
	 * @param integer $current_attachment_index  Current attachment's index number in the self::$attachments array.
	 * @param integer $expected_attachment_index Expected attachment's index number in the self::$attachments array.
	 * @param string  $expected                  The expected output string.
	 * @param array   $args                      Array of arguments to pass to the function being tested.
	 * @return array {
	 *     Array of the prepared test parameters.
	 *
	 *     @var string $expected Expected output string.
	 *     @var array  $args     All of the arguments to pass to the function being tested.
	 * }
	 */
	protected function setup_test_scenario( $current_attachment_index, $expected_attachment_index, $expected, array $args = array() ) {
		// This prep code allows the data provider to specify the different arguments needed for the test scenario.
		$args = array_merge( $this->default_args, $args );
		$args = array_values( $args );

		// Replace the attachment ID placeholder.
		if ( isset( self::$attachments[ $expected_attachment_index ] ) ) {
			$expected = str_replace( '%%ID%%', self::$attachments[ $expected_attachment_index ], $expected );
		}

		// Go to the current attachment to set the state for the tests.
		$this->go_to( get_permalink( self::$attachments[ $current_attachment_index ] ) );

		// Return the changed parameters.
		return array( $expected, $args );
	}
}
