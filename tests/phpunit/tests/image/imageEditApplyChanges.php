<?php

/**
 * Tests for image_edit_apply_changes().
 *
 * @group image
 * @group media
 *
 * @covers ::image_edit_apply_changes
 */
class Tests_Image_ImageEditApplyChanges extends WP_UnitTestCase {

	/**
	 * Editor operations recorded by get_recording_editor(), in order.
	 *
	 * @var array[]
	 */
	private $operations = array();

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
	}

	/**
	 * Gets an image editor mock that records the rotate, flip and crop calls made on it.
	 *
	 * @param int        $width      Image width returned by get_size().
	 * @param int        $height     Image height returned by get_size().
	 * @param array|null $operations Optional. Array to record the calls in. Default the operations property.
	 * @return WP_Image_Editor&PHPUnit\Framework\MockObject\MockObject Image editor mock.
	 */
	private function get_recording_editor( $width = 1200, $height = 900, ?array &$operations = null ) {
		if ( null === $operations ) {
			$operations = &$this->operations;
		}

		$editor = $this->createMock( WP_Image_Editor::class );

		$editor->method( 'get_size' )->willReturn(
			array(
				'width'  => $width,
				'height' => $height,
			)
		);

		// Number of arguments to record for each method, leaving out optional arguments the function never passes.
		$arg_counts = array(
			'rotate' => 1,
			'flip'   => 2,
			'crop'   => 4,
		);

		foreach ( $arg_counts as $method => $arg_count ) {
			$editor->method( $method )->willReturnCallback(
				static function ( ...$args ) use ( $method, $arg_count, &$operations ) {
					$operations[] = array_merge( array( $method ), array_slice( $args, 0, $arg_count ) );

					return true;
				}
			);
		}

		return $editor;
	}

	/**
	 * Builds the change operations as they are decoded from the image editor history.
	 *
	 * @param array[] $changes Change operations as associative arrays.
	 * @return object[] Change operations as objects.
	 */
	private static function to_changes( array $changes ) {
		return json_decode( wp_json_encode( $changes ) );
	}

	/**
	 * Tests that the image is returned unchanged when the changes are not an array.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_changes_that_are_not_an_array
	 *
	 * @param mixed $changes Changes.
	 */
	public function test_returns_image_unchanged_when_changes_are_not_an_array( $changes ) {
		$editor = $this->get_recording_editor();

		$filter = new MockAction();
		add_filter( 'wp_image_editor_before_change', array( $filter, 'filter' ) );

		$this->assertSame( $editor, image_edit_apply_changes( $editor, $changes ), 'The editor should be returned.' );
		$this->assertSame( array(), $this->operations, 'No operation should be applied.' );
		$this->assertSame( 0, $filter->get_call_count(), 'The filter should not run.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_changes_that_are_not_an_array() {
		return array(
			'null'         => array( null ),
			'false'        => array( false ),
			'empty string' => array( '' ),
			'string'       => array( '[{"r":90}]' ),
			'object'       => array( (object) array( 'r' => 90 ) ),
		);
	}

	/**
	 * Tests the editor operations applied for a list of changes.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_changes
	 *
	 * @param array[] $changes  Change operations.
	 * @param array[] $expected Expected editor calls, each as the method name followed by its arguments.
	 */
	public function test_applies_changes_to_editor( $changes, $expected ) {
		$editor = $this->get_recording_editor();

		$this->assertSame( $editor, image_edit_apply_changes( $editor, self::to_changes( $changes ) ), 'The editor should be returned.' );
		$this->assertSame( $expected, $this->operations );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_changes() {
		return array(
			'no changes'                               => array( array(), array() ),
			'rotate'                                   => array(
				array( array( 'r' => 90 ) ),
				array( array( 'rotate', 90 ) ),
			),
			'consecutive rotations are summed'         => array(
				array( array( 'r' => 90 ), array( 'r' => 90 ), array( 'r' => -45 ) ),
				array( array( 'rotate', 135 ) ),
			),
			'rotations that cancel out'                => array(
				array( array( 'r' => 90 ), array( 'r' => -90 ) ),
				array(),
			),
			'zero rotation'                            => array(
				array( array( 'r' => 0 ) ),
				array(),
			),
			'horizontal flip'                          => array(
				array( array( 'f' => 1 ) ),
				array( array( 'flip', true, false ) ),
			),
			'vertical flip'                            => array(
				array( array( 'f' => 2 ) ),
				array( array( 'flip', false, true ) ),
			),
			'consecutive flips are combined'           => array(
				array( array( 'f' => 1 ), array( 'f' => 2 ) ),
				array( array( 'flip', true, true ) ),
			),
			'flips that cancel out'                    => array(
				array( array( 'f' => 1 ), array( 'f' => 1 ) ),
				array(),
			),
			'crop with an explicit scale'              => array(
				array(
					array(
						'c' => array(
							'x' => 10,
							'y' => 20,
							'w' => 30,
							'h' => 40,
							'r' => 1.5,
						),
					),
				),
				array( array( 'crop', 15, 30, 45, 60 ) ),
			),
			'crop scaled from the preview'             => array(
				array(
					array(
						'c' => array(
							'x' => 10,
							'y' => 20,
							'w' => 30,
							'h' => 40,
						),
					),
				),
				// The 1200x900 image is previewed at 600x450, so the selection is doubled.
				array( array( 'crop', 20, 40, 60, 80 ) ),
			),
			'crops are not combined'                   => array(
				array(
					array(
						'c' => array(
							'x' => 1,
							'y' => 2,
							'w' => 3,
							'h' => 4,
							'r' => 1,
						),
					),
					array(
						'c' => array(
							'x' => 5,
							'y' => 6,
							'w' => 7,
							'h' => 8,
							'r' => 1,
						),
					),
				),
				array( array( 'crop', 1, 2, 3, 4 ), array( 'crop', 5, 6, 7, 8 ) ),
			),
			'different operations keep their order'    => array(
				array( array( 'r' => 90 ), array( 'f' => 1 ), array( 'r' => 90 ) ),
				array( array( 'rotate', 90 ), array( 'flip', true, false ), array( 'rotate', 90 ) ),
			),
			'only consecutive operations are combined' => array(
				array( array( 'f' => 1 ), array( 'r' => 90 ), array( 'r' => 180 ), array( 'f' => 2 ), array( 'f' => 2 ) ),
				array( array( 'flip', true, false ), array( 'rotate', 270 ) ),
			),
		);
	}

	/**
	 * Tests that the crop selection is not scaled for images that fit the preview.
	 *
	 * @ticket 65819
	 */
	public function test_crop_selection_is_not_scaled_for_small_images() {
		$editor = $this->get_recording_editor( 300, 200 );

		$changes = self::to_changes(
			array(
				array(
					'c' => array(
						'x' => 10,
						'y' => 20,
						'w' => 30,
						'h' => 40,
					),
				),
			)
		);

		image_edit_apply_changes( $editor, $changes );

		$this->assertSame( array( array( 'crop', 10, 20, 30, 40 ) ), $this->operations );
	}

	/**
	 * Tests that the filter receives the editor and the expanded, combined changes, and can replace the editor.
	 *
	 * @ticket 65819
	 */
	public function test_wp_image_editor_before_change_filter() {
		$editor_operations      = array();
		$replacement_operations = array();

		$editor      = $this->get_recording_editor( 1200, 900, $editor_operations );
		$replacement = $this->get_recording_editor( 1200, 900, $replacement_operations );

		$filter = new MockAction();
		add_filter( 'wp_image_editor_before_change', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_image_editor_before_change',
			static function () use ( $replacement ) {
				return $replacement;
			},
			20
		);

		$result = image_edit_apply_changes( $editor, self::to_changes( array( array( 'r' => 90 ), array( 'r' => 90 ), array( 'f' => 2 ) ) ) );

		$this->assertSame( $replacement, $result, 'The filtered editor should be returned.' );

		$args = $filter->get_args();

		$this->assertCount( 1, $args, 'The filter should run once.' );
		$this->assertSame( $editor, $args[0][0], 'The filter should receive the editor.' );
		$this->assertSame(
			array(
				array(
					'type'  => 'rotate',
					'angle' => 180,
				),
				array(
					'type' => 'flip',
					'axis' => 2,
				),
			),
			json_decode( wp_json_encode( $args[0][1] ), true ),
			'The filter should receive the expanded and combined changes.'
		);

		$this->assertSame( array(), $editor_operations, 'No operation should be applied to the original editor.' );
		$this->assertSame(
			array(
				array( 'rotate', 180 ),
				array( 'flip', false, true ),
			),
			$replacement_operations,
			'The operations should be applied to the filtered editor.'
		);
	}

	/**
	 * Tests that a GD image is still accepted, with a deprecation notice, and has the changes applied.
	 *
	 * @ticket 65819
	 *
	 * @requires function imagecreatetruecolor
	 *
	 * @expectedDeprecated image_edit_apply_changes
	 * @expectedDeprecated _rotate_image_resource
	 */
	public function test_applies_changes_to_gd_image() {
		$image = imagecreatetruecolor( 40, 20 );

		$result = image_edit_apply_changes( $image, self::to_changes( array( array( 'r' => 90 ) ) ) );

		$this->assertTrue( is_gd_image( $result ), 'A GD image should be returned.' );
		$this->assertSame( 20, imagesx( $result ), 'The image should be rotated.' );
		$this->assertSame( 40, imagesy( $result ), 'The image should be rotated.' );
	}
}
