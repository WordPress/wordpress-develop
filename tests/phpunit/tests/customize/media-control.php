<?php
/**
 * Tests for the WP_Customize_Media_Control class.
 *
 * @coversDefaultClass WP_Customize_Media_Control
 *
 * @group customize
 */
class Test_WP_Customize_Media_Control extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
	}

	/**
	 * @ticket 64557
	 *
	 * @covers ::to_json
	 */
	public function test_to_json() {
		$manager = new WP_Customize_Manager();

		$manager->add_setting(
			'some_jpg',
			array(
				'default' => 'https://example.com/image.jpg',
			)
		);
		$manager->add_setting(
			'some_avif',
			array(
				'default' => 'https://example.com/image.avif',
			)
		);
		$manager->add_setting(
			'some_pdf',
			array(
				'default' => 'https://example.com/image.pdf',
			)
		);
		$manager->add_setting( 'no_default' );

		$some_jpg_control   = $manager->add_control( new WP_Customize_Media_Control( $manager, 'some_jpg' ) );
		$some_avif_control  = $manager->add_control( new WP_Customize_Media_Control( $manager, 'some_avif' ) );
		$some_pdf_control   = $manager->add_control( new WP_Customize_Media_Control( $manager, 'some_pdf' ) );
		$no_default_control = $manager->add_control( new WP_Customize_Media_Control( $manager, 'no_default' ) );

		$some_jpg_control_json  = $some_jpg_control->json();
		$some_avif_control_json = $some_avif_control->json();
		$some_pdf_control_json  = $some_pdf_control->json();

		$this->assertSame( 'image', $some_jpg_control_json['defaultAttachment']['type'] );
		$this->assertSame( 'image', $some_avif_control_json['defaultAttachment']['type'] );
		$this->assertSame( 'document', $some_pdf_control_json['defaultAttachment']['type'] );
		$this->assertArrayNotHasKey( 'defaultAttachment', $no_default_control->json() );
	}
}
