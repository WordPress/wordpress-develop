<?php

/**
 * Tests for the wp_get_block_default_classname() function.
 *
 * @group block-supports
 *
 * @covers ::wp_get_block_default_classname
 */
class Tests_Block_Supports_WpGetBlockDefaultClassname extends WP_UnitTestCase {

	/**
	 * Tests that the class name is built from the name of the block.
	 *
	 * @ticket 66509
	 *
	 * @dataProvider data_block_names
	 *
	 * @param string $block_name Block name.
	 * @param string $expected   Expected class name.
	 */
	public function test_should_build_the_class_name_from_the_block_name( $block_name, $expected ) {
		$this->assertSame( $expected, wp_get_block_default_classname( $block_name ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_block_names() {
		return array(
			'core block'                      => array( 'core/paragraph', 'wp-block-paragraph' ),
			'core block with a hyphen'        => array( 'core/post-title', 'wp-block-post-title' ),
			'historical core embed prefix'    => array( 'core-embed/youtube', 'wp-block-embed-youtube' ),
			'block of a plugin'               => array( 'my-plugin/hero', 'wp-block-my-plugin-hero' ),
			'block with several slashes'      => array( 'my-plugin/group/item', 'wp-block-my-plugin-group-item' ),
			'name without a namespace'        => array( 'hero', 'wp-block-hero' ),
			'namespace that starts with core' => array( 'corelike/hero', 'wp-block-corelike-hero' ),
			'core in the middle'              => array( 'my-core/hero', 'wp-block-my-core-hero' ),
			'name that starts with core-'     => array( 'core-hero', 'wp-block-hero' ),
			'empty name'                      => array( '', 'wp-block-' ),
		);
	}

	/**
	 * Tests that the `block_default_classname` filter receives the class name and the name of the block.
	 *
	 * @ticket 66509
	 */
	public function test_should_apply_the_filter_with_the_class_name_and_the_block_name() {
		$received = null;
		add_filter(
			'block_default_classname',
			static function ( $classname, $block_name ) use ( &$received ) {
				$received = array( $classname, $block_name );

				return $classname;
			},
			10,
			2
		);

		wp_get_block_default_classname( 'core/paragraph' );

		$this->assertSame( array( 'wp-block-paragraph', 'core/paragraph' ), $received );
	}

	/**
	 * Tests that the result of the filter is returned.
	 *
	 * @ticket 66509
	 */
	public function test_should_return_the_result_of_the_filter() {
		add_filter(
			'block_default_classname',
			static function ( $classname ) {
				return $classname . ' filtered';
			}
		);

		$this->assertSame( 'wp-block-paragraph filtered', wp_get_block_default_classname( 'core/paragraph' ) );
	}
}
