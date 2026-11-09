<?php

/**
 * Base class for testing image manipulation functionality.
 */
abstract class WP_Image_UnitTestCase extends WP_UnitTestCase {

	/**
	 * The image editing library class to use in tests.
	 *
	 * @var string
	 */
	public $editor_engine;

	/**
	 * Set the image editor engine according to the unit test's specification
	 */
	public function set_up() {
		parent::set_up();

		if ( ! call_user_func( array( $this->editor_engine, 'test' ) ) ) {
			$this->markTestSkipped( sprintf( 'The image editor engine %s is not supported on this system.', $this->editor_engine ) );
		}

		add_filter( 'wp_image_editors', array( $this, 'setEngine' ), 10, 2 );
	}

	/**
	 * Override the image editor engine
	 *
	 * @return string
	 */
	public function setEngine( $editors ) {
		return array( $this->editor_engine );
	}

	/**
	 * Helper assertion for testing alpha on images using GD library
	 *
	 * @param  string $image_path
	 * @param  array $point      array(x,y)
	 * @param  int $alpha
	 */
	protected function assertImageAlphaAtPointGD( $image_path, $point, $alpha ) {
		$im  = imagecreatefrompng( $image_path );
		$rgb = imagecolorat( $im, $point[0], $point[1] );

		$colors = imagecolorsforindex( $im, $rgb );

		$this->assertSame( $alpha, $colors['alpha'] );
	}

	/**
	 * Helper assertion for testing alpha on images using Imagick
	 *
	 * @param string $image_path
	 * @param array $point      array(x,y)
	 * @param int $expected
	 */
	protected function assertImageAlphaAtPointImagick( $image_path, $point, $expected ) {
		$im    = new Imagick( $image_path );
		$pixel = $im->getImagePixelColor( $point[0], $point[1] );
		$color = $pixel->getColorValue( imagick::COLOR_ALPHA );
		$this->assertSame( $expected, $color );
	}

	/**
	 * Helper assertion to check actual image dimensions on disk
	 *
	 * @param string $filename Image filename.
	 * @param int    $width    Width to verify.
	 * @param int    $height   Height to verify.
	 */
	protected function assertImageDimensions( $filename, $width, $height ) {
		$detected_width  = 0;
		$detected_height = 0;
		$image_size      = getimagesize( $filename );

		if ( isset( $image_size[0] ) ) {
			$detected_width = $image_size[0];
		}

		if ( isset( $image_size[1] ) ) {
			$detected_height = $image_size[1];
		}

		$this->assertSame( $width, $detected_width );
		$this->assertSame( $height, $detected_height );
	}

	/**
	 * Gets the value of a protected or private property from an image editor instance.
	 *
	 * @param WP_Image_Editor|object $editor        The image editor instance.
	 * @param string                 $property_name Optional. The property name. Default 'image'.
	 * @return mixed The value of the property.
	 */
	protected function get_property_value( $editor, $property_name = 'image' ) {
		$property = new ReflectionProperty( $editor, $property_name );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property->getValue( $editor );
	}

	/**
	 * Sets the value of a protected or private property on an image editor instance.
	 *
	 * @param WP_Image_Editor|object $editor        The image editor instance.
	 * @param string                 $property_name The property name.
	 * @param mixed                  $value         The value to set.
	 */
	protected function set_property_value( $editor, $property_name, $value ) {
		$property = new ReflectionProperty( $editor, $property_name );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( $editor, $value );
	}
}
