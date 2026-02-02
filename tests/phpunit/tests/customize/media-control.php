<?php
/**
 * Tests for the WP_Customize_Media_Control class.
 *
 * @package WordPress
 *
 * @coversDefaultClass WP_Customize_Media_Control
 *
 * @group customize
 */
class Test_WP_Customize_Media_Control extends WP_UnitTestCase {

	/**
	 * Manager.
	 */
	public ?WP_Customize_Manager $wp_customize;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		$this->wp_customize      = $GLOBALS['wp_customize'];
	}

	/**
	 * @ticket 64557
	 *
	 * @covers ::to_json
	 */
	public function test_to_json() {
		$this->wp_customize->add_setting(
			'some_jpg',
			array(
				'default' => 'https://example.com/image.jpg',
			)
		);
		$this->wp_customize->add_setting(
			'some_avif',
			array(
				'default' => 'https://example.com/image.avif',
			)
		);
		$this->wp_customize->add_setting(
			'some_pdf',
			array(
				'default' => 'https://example.com/image.pdf',
			)
		);
		$this->wp_customize->add_setting( 'no_default' );

		$some_jpg_control   = $this->wp_customize->add_control( new WP_Customize_Media_Control( $this->wp_customize, 'some_jpg' ) );
		$some_avif_control  = $this->wp_customize->add_control( new WP_Customize_Media_Control( $this->wp_customize, 'some_avif' ) );
		$some_pdf_control   = $this->wp_customize->add_control( new WP_Customize_Media_Control( $this->wp_customize, 'some_pdf' ) );
		$no_default_control = $this->wp_customize->add_control( new WP_Customize_Media_Control( $this->wp_customize, 'no_default' ) );

		$some_jpg_control_json  = $some_jpg_control->json();
		$some_avif_control_json = $some_avif_control->json();
		$some_pdf_control_json  = $some_pdf_control->json();

		$this->assertSame( 'image', $some_jpg_control_json['defaultAttachment']['type'] );
		$this->assertSame( 'image', $some_avif_control_json['defaultAttachment']['type'] );
		$this->assertSame( 'document', $some_pdf_control_json['defaultAttachment']['type'] );
		$this->assertArrayNotHasKey( 'defaultAttachment', $no_default_control->json() );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		$this->wp_customize = null;
		unset( $GLOBALS['wp_customize'] );
		parent::tear_down();
	}
}
