<?php
/**
 * Tests for get_intermediate_image_sizes().
 *
 * @group media
 *
 * @covers ::get_intermediate_image_sizes
 */
class Tests_Media_GetIntermediateImageSizes extends WP_UnitTestCase {

	/**
	 * Stored additional image sizes.
	 *
	 * @var array
	 */
	private $additional_sizes;

	public function set_up() {
		parent::set_up();

		$this->additional_sizes                = wp_get_additional_image_sizes();
		$GLOBALS['_wp_additional_image_sizes'] = array();
	}

	public function tear_down() {
		$GLOBALS['_wp_additional_image_sizes'] = $this->additional_sizes;

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_default_image_sizes() {
		$this->assertSame(
			array( 'thumbnail', 'medium', 'medium_large', 'large' ),
			get_intermediate_image_sizes()
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_includes_additional_image_sizes() {
		add_image_size( 'wptests-custom', 100, 100, true );

		$this->assertSame(
			array( 'thumbnail', 'medium', 'medium_large', 'large', 'wptests-custom' ),
			get_intermediate_image_sizes()
		);

		remove_image_size( 'wptests-custom' );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_sizes() {
		add_filter(
			'intermediate_image_sizes',
			static function ( $sizes ) {
				$sizes[] = 'filtered-size';
				return $sizes;
			}
		);

		$this->assertSame(
			array( 'thumbnail', 'medium', 'medium_large', 'large', 'filtered-size' ),
			get_intermediate_image_sizes()
		);
	}
}
