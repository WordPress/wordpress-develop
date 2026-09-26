<?php
/**
 * WordPress VIPS Image Editor
 *
 * @package WordPress
 * @subpackage Image_Editor
 */

// The php-vips library is vendored in wp-includes/php-vips.
if ( ! class_exists( 'Jcupitt\Vips\Image' ) ) {
	require_once ABSPATH . WPINC . '/php-vips/autoload.php';
}

/**
 * WordPress Image Editor Class for Image Manipulation through VIPS
 *
 * Requires the FFI extension with `ffi.enable` turned on, and the libvips shared
 * library. Without them, test() reports the editor as unsupported and image editing
 * falls back to Imagick or GD.
 *
 * @since 7.2.0
 *
 * @see WP_Image_Editor
 */
class WP_Image_Editor_Vips extends WP_Image_Editor {
	/**
	 * VIPS Image object.
	 *
	 * @var Jcupitt\Vips\Image|null
	 */
	protected $image;

	/**
	 * Whether the image has been resized or cropped since it was loaded.
	 *
	 * Metadata is only stripped on such saves, matching the Imagick editor, so saving
	 * an image that was merely loaded keeps its metadata.
	 *
	 * @var bool
	 */
	protected $resized = false;

	/**
	 * Whether the image is a lossless WebP that must be re-saved losslessly.
	 *
	 * @var bool
	 */
	protected $lossless = false;

	/**
	 * Whether the loaded image is a paletted PNG.
	 *
	 * libvips expands the palette on load, so the original colour type has to be read
	 * from the file in order to ask for a palette back when saving.
	 *
	 * @var bool
	 */
	protected $paletted = false;

	/**
	 * Cache of mime type support checks.
	 *
	 * Dynamic writeToBuffer probe is used because VIPS support depends on runtime configuration
	 * and optional dependencies. Cache prevents repeated expensive encoder checks.
	 *
	 * @var array<string,bool>
	 */
	protected static $mime_support_cache = array();

	/**
	 * Checks to see if current environment supports VIPS.
	 *
	 * @since 7.2.0
	 *
	 * @param array $args
	 * @return bool
	 */
	public static function test( $args = array() ) {
		// Check if FFI extension is available.
		if ( ! extension_loaded( 'ffi' ) ) {
			return false;
		}

		// Check if the Jcupitt\Vips classes are available via Composer.
		if ( ! class_exists( 'Jcupitt\Vips\Config' ) || ! class_exists( 'Jcupitt\Vips\Image' ) ) {
			return false;
		}

		// Try to get the libvips version to confirm it's working.
		try {
			$version = Jcupitt\Vips\Config::version();
			if ( empty( $version ) ) {
				return false;
			}

			// Require libvips 8.7 or later
			if ( version_compare( $version, '8.7', '<' ) ) {
				return false;
			}
		} catch ( Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @since 7.2.0
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		if ( isset( self::$mime_support_cache[ $mime_type ] ) ) {
			return self::$mime_support_cache[ $mime_type ];
		}

		$vips_extension = strtoupper( self::get_extension( $mime_type ) );

		if ( ! $vips_extension ) {
			self::$mime_support_cache[ $mime_type ] = false;

			return false;
		}

		if ( ! self::test() ) {
			self::$mime_support_cache[ $mime_type ] = false;

			return false;
		}

		$extension_map = array(
			'JPEG' => 'jpg',
			'JPG'  => 'jpg',
			'PNG'  => 'png',
			'WEBP' => 'webp',
			'GIF'  => 'gif',
			'TIFF' => 'tif',
			'TIF'  => 'tif',
			'HEIC' => 'heic',
			'HEIF' => 'heif',
			'AVIF' => 'avif',
			'JXL'  => 'jxl',
		);

		$target_extension = isset( $extension_map[ $vips_extension ] ) ? $extension_map[ $vips_extension ] : strtolower( $vips_extension );

		// Probe encoder support directly.
		// Use Image::black() to test write support (encoding) rather than findLoad() which only tests read support (decoding).
		try {
			$test_image = Jcupitt\Vips\Image::black( 1, 1 );

			// libvips picks the encoder from the suffix, so the buffer is given one.
			// Nothing is written to disk, so there is no temp file to clean up.
			$buffer = $test_image->writeToBuffer( '.' . $target_extension );

			$supported = ! empty( $buffer );
		} catch ( Exception $e ) {
			$supported = false;
		}

		self::$mime_support_cache[ $mime_type ] = $supported;

		return $supported;
	}

	/**
	 * Loads image from $this->file into new VIPS Image object.
	 *
	 * @since 7.2.0
	 *
	 * @return true|WP_Error True if loaded successfully; WP_Error on failure.
	 */
	public function load() {
		if ( $this->image ) {
			return true;
		}

		if ( ! is_file( $this->file ) && ! wp_is_stream( $this->file ) ) {
			return new WP_Error( 'error_loading_image', __( 'File does not exist?' ), $this->file );
		}

		// VIPS is memory-efficient but still raise the limit.
		wp_raise_memory_limit( 'image' );

		try {
			// Load the image.
			if ( wp_is_stream( $this->file ) ) {
				$file_contents = file_get_contents( $this->file );
				if ( false === $file_contents ) {
					return new WP_Error( 'error_loading_image', __( 'File does not exist?' ), $this->file );
				}
				$this->image = Jcupitt\Vips\Image::newFromBuffer( $file_contents );
			} else {
				$this->image = Jcupitt\Vips\Image::newFromFile( $this->file );
			}

			// Get image size.
			$width  = $this->image->width;
			$height = $this->image->height;

			if ( ! $width || ! $height ) {
				return new WP_Error( 'invalid_image', __( 'Could not read image size.' ), $this->file );
			}

			// Set the mime type.
			//
			// This must not use wp_getimagesize(): for HEIC and AVIF files it falls back
			// to the editors, which would recurse straight back into load().
			$this->mime_type = wp_get_image_mime( $this->file );

			if ( ! $this->mime_type ) {
				$this->mime_type = $this->default_mime_type;
			}

			// PNG colour type 3 is an indexed palette. See the property for why it is read
			// from the file rather than from the loaded image.
			if ( 'image/png' === $this->mime_type ) {
				$ihdr = @file_get_contents( $this->file, false, null, 25, 1 );

				$this->paletted = ( false !== $ihdr && 3 === ord( $ihdr ) );
			}

			$this->update_size( $width, $height );

			return $this->set_quality();
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_image', $e->getMessage(), $this->file );
		}
	}

	/**
	 * Sets Image Compression quality on a 1-100% scale.
	 *
	 * @since 7.2.0
	 *
	 * @param int   $quality Compression Quality. Range: [1,100].
	 * @param array $dims    Optional. Image dimensions array with 'width' and 'height' keys.
	 * @return true|WP_Error True if set successfully; WP_Error on failure.
	 */
	public function set_quality( $quality = null, $dims = array() ) {
		$quality_result = parent::set_quality( $quality, $dims );

		if ( is_wp_error( $quality_result ) ) {
			return $quality_result;
		}

		// A lossless WebP has no quality to preserve, so it is re-saved losslessly at 100,
		// matching the GD and Imagick editors.
		if ( 'image/webp' === $this->mime_type ) {
			$webp_info = wp_get_webp_info( $this->file );

			if ( 'lossless' === $webp_info['type'] ) {
				$this->lossless = true;
				parent::set_quality( 100 );
			}
		}

		return true;
	}

	/**
	 * Sets or updates current image size.
	 *
	 * @since 7.2.0
	 *
	 * @param int|null $width  Image width.
	 * @param int|null $height Image height.
	 * @return true
	 */
	protected function update_size( $width = null, $height = null ) {
		if ( ! $width ) {
			$width = $this->image->width;
		}

		if ( ! $height ) {
			$height = $this->image->height;
		}

		return parent::update_size( $width, $height );
	}

	/**
	 * Resizes current image.
	 *
	 * At minimum, either a height or width must be provided. If one of the two is set
	 * to null, the resize will maintain aspect ratio according to the provided dimension.
	 *
	 * @since 7.2.0
	 *
	 * @param int|null   $max_w Image width.
	 * @param int|null   $max_h Image height.
	 * @param bool|array $crop  {
	 *     Optional. Image cropping behavior. If false, the image will be scaled (default).
	 *     If true, image will be cropped to the specified dimensions using center positions.
	 *     If an array, the image will be cropped using the array to specify the crop location:
	 *
	 *     @type string $0 The x crop position. Accepts 'left', 'center', or 'right'.
	 *     @type string $1 The y crop position. Accepts 'top', 'center', or 'bottom'.
	 * }
	 * @return true|WP_Error
	 */
	public function resize( $max_w, $max_h, $crop = false ) {
		if ( ( $this->size['width'] === $max_w ) && ( $this->size['height'] === $max_h ) ) {
			return true;
		}

		$image = $this->_resize( $max_w, $max_h, $crop );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$this->image = $image;

		return true;
	}

	/**
	 * Resizes the image and returns it, leaving `$this->image` alone.
	 *
	 * `$this->size` is updated to the destination dimensions because the save
	 * methods report the image size from there. Callers that need the previous
	 * dimensions back are expected to restore them.
	 *
	 * @since 7.2.0
	 *
	 * @param int|null   $max_w Image width.
	 * @param int|null   $max_h Image height.
	 * @param bool|array $crop  Optional. Image cropping behavior. Default false.
	 * @return Jcupitt\Vips\Image|WP_Error Resized image, or WP_Error on failure.
	 */
	protected function _resize( $max_w, $max_h, $crop = false ) {
		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __( 'Could not calculate resized image dimensions' ), $this->file );
		}

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		try {
			$image = $this->image;

			// Crop to the source region first, when it is smaller than the whole image.
			if ( (int) $src_w !== $this->size['width'] || (int) $src_h !== $this->size['height'] ) {
				$image = $image->crop( (int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h );
			}

			// Then scale that region to the destination dimensions.
			if ( (int) $src_w !== $dst_w || (int) $src_h !== $dst_h ) {
				$image = $image->resize( $dst_w / (int) $src_w, array( 'vscale' => $dst_h / (int) $src_h ) );
			}

			$this->update_size( $dst_w, $dst_h );
			$this->resized = true;

			return $image;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_resize_error', $e->getMessage() );
		}
	}

	/**
	 * Resize multiple images from a single source.
	 *
	 * @since 7.2.0
	 *
	 * @param array $sizes {
	 *     An array of image size arrays. Default sizes are 'small', 'medium', 'large'.
	 *
	 *     @type array ...$0 {
	 *         @type int        $width  Image width.
	 *         @type int        $height Image height.
	 *         @type bool|array $crop   Optional. Whether to crop the image. Default false.
	 *     }
	 * }
	 * @return array An array of resized images metadata by size.
	 */
	public function multi_resize( $sizes ) {
		$metadata = array();

		foreach ( $sizes as $size => $size_data ) {
			$meta = $this->make_subsize( $size_data );

			if ( ! is_wp_error( $meta ) ) {
				$metadata[ $size ] = $meta;
			}
		}

		return $metadata;
	}

	/**
	 * Creates an image sub-size and returns the image meta data value for it.
	 *
	 * @since 7.2.0
	 *
	 * @param array $size_data {
	 *     Array of size data.
	 *
	 *     @type int        $width  The maximum width in pixels.
	 *     @type int        $height The maximum height in pixels.
	 *     @type bool|array $crop   Whether to crop the image to exact dimensions.
	 * }
	 * @return array|WP_Error The image data array for inclusion in the `sizes` array in the image meta,
	 *                        WP_Error object on error.
	 */
	public function make_subsize( $size_data ) {
		if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
			return new WP_Error( 'image_subsize_create_error', __( 'Cannot resize the image. Both width and height are not set.' ) );
		}

		$orig_size  = $this->size;
		$orig_image = $this->image;

		$size_data['width']  = isset( $size_data['width'] ) ? $size_data['width'] : null;
		$size_data['height'] = isset( $size_data['height'] ) ? $size_data['height'] : null;
		$size_data['crop']   = isset( $size_data['crop'] ) ? $size_data['crop'] : false;

		if ( ( $orig_size['width'] === $size_data['width'] ) && ( $orig_size['height'] === $size_data['height'] ) ) {
			return new WP_Error( 'image_subsize_create_error', __( 'The image already has the requested size.' ) );
		}

		$resized = $this->_resize( $size_data['width'], $size_data['height'], $size_data['crop'] );

		if ( is_wp_error( $resized ) ) {
			$this->image = $orig_image;
			$this->size  = $orig_size;

			return $resized;
		}

		$saved = $this->_save( $resized );

		// The editor keeps the image it was loaded with, so further sub-sizes are all
		// derived from the same original rather than from the previous sub-size.
		$this->image = $orig_image;
		$this->size  = $orig_size;

		if ( ! is_wp_error( $saved ) ) {
			unset( $saved['path'] );
		}

		return $saved;
	}

	/**
	 * Crops Image.
	 *
	 * @since 7.2.0
	 *
	 * @param int      $src_x   The start x position to crop from.
	 * @param int      $src_y   The start y position to crop from.
	 * @param int      $src_w   The width to crop.
	 * @param int      $src_h   The height to crop.
	 * @param int|null $dst_w   Optional. The destination width.
	 * @param int|null $dst_h   Optional. The destination height.
	 * @param bool     $src_abs Optional. If the destination crop values are absolute.
	 * @return true|WP_Error
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		// If destination width/height isn't specified, use same as source.
		if ( ! $dst_w ) {
			$dst_w = $src_w;
		}

		if ( ! $dst_h ) {
			$dst_h = $src_h;
		}

		// Validate all dimension parameters match GD pattern for consistency.
		foreach ( array( $src_w, $src_h, $dst_w, $dst_h ) as $value ) {
			if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
				return new WP_Error( 'image_crop_error', __( 'Image crop failed.' ), $this->file );
			}
		}

		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}

		try {
			// Clamp crop dimensions to image bounds (matching GD behavior).
			$actual_src_w = min( (int) $src_w, $this->image->width - (int) $src_x );
			$actual_src_h = min( (int) $src_h, $this->image->height - (int) $src_y );

			// Crop the image with clamped dimensions.
			$cropped = $this->image->crop( (int) $src_x, (int) $src_y, $actual_src_w, $actual_src_h );

			// Resize to destination dimensions (GD resamples to $dst_w x $dst_h regardless of actual cropped size).
			if ( $actual_src_w !== $dst_w || $actual_src_h !== $dst_h ) {
				$h_scale = $dst_w / $actual_src_w;
				$v_scale = $dst_h / $actual_src_h;
				$cropped = $cropped->resize( $h_scale, array( 'vscale' => $v_scale ) );
			}

			$this->image = $cropped;
			$this->update_size( $dst_w, $dst_h );
			$this->resized = true;

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_crop_error', $e->getMessage() );
		}
	}

	/**
	 * Rotates current image counter-clockwise by $angle.
	 *
	 * @since 7.2.0
	 *
	 * @param float $angle
	 * @return true|WP_Error
	 */
	public function rotate( $angle ) {
		$angle = -$angle;
		$angle = ( 360 + ( $angle % 360 ) ) % 360;

		try {
			// Use fast rot90/rot180/rot270 paths for right angles to avoid interpolation and improve speed.
			if ( 90 === $angle ) {
				$this->image = $this->image->rot90();
			} elseif ( 180 === $angle ) {
				$this->image = $this->image->rot180();
			} elseif ( 270 === $angle ) {
				$this->image = $this->image->rot270();
			} else {
				$this->image = $this->image->rotate( $angle );
			}

			// The pixels no longer match the EXIF orientation tag, and a stale tag
			// orients the saved file a second time when it is loaded again.
			$this->reset_exif_orientation();

			// Update size since rotation may change dimensions.
			$result = $this->update_size();
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_rotate_error', $e->getMessage() );
		}
	}

	/**
	 * Checks if the image has an EXIF Orientation tag and rotates it if needed.
	 *
	 * @since 7.2.0
	 *
	 * @return bool|WP_Error True if the image was rotated. False if not rotated.
	 *                       WP_Error if error while rotating.
	 */
	public function maybe_exif_rotate() {
		$orientation = null;

		if ( 'image/jpeg' === $this->mime_type ) {
			$exif_data = @exif_read_data( $this->file );

			if ( ! empty( $exif_data['Orientation'] ) ) {
				$orientation = (int) $exif_data['Orientation'];
			}
		}

		/** This filter is documented in wp-includes/class-wp-image-editor.php */
		$orientation = apply_filters( 'wp_image_maybe_exif_rotate', $orientation, $this->file );

		if ( ! $orientation || 1 === $orientation ) {
			return false;
		}

		/*
		 * libvips applies all eight EXIF orientations itself, and clears the orientation
		 * field as it goes. Rotating or flipping per the parent's switch would leave that
		 * field in place, so the saved image would be oriented a second time when it is
		 * loaded again. This is the "EXIF Orientation can be reset afterwards" requirement
		 * the parent documents.
		 */
		if ( ! is_callable( array( $this->image, 'autorot' ) ) ) {
			return new WP_Error( 'write_exif_error', __( 'The image cannot be rotated because the embedded meta data cannot be updated.' ) );
		}

		try {
			$this->image = $this->image->autorot();
		} catch ( Exception $e ) {
			return new WP_Error( 'image_rotate_error', $e->getMessage() );
		}

		$result = $this->update_size();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Flips current image.
	 *
	 * @since 7.2.0
	 *
	 * @param bool $horz Flip along Horizontal Axis
	 * @param bool $vert Flip along Vertical Axis
	 * @return true|WP_Error
	 */
	public function flip( $horz, $vert ) {
		try {
			// WordPress flip convention: $vert=flip vertically (use fliphor to flip along horizontal axis).
			// WordPress flip convention: $horz=flip horizontally (use flipver to flip along vertical axis).
			if ( $vert ) {
				$this->image = $this->image->fliphor();
			}

			if ( $horz ) {
				$this->image = $this->image->flipver();
			}

			// Flipping leaves the tag describing the unflipped pixels.
			$this->reset_exif_orientation();

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_flip_error', $e->getMessage() );
		}
	}

	/**
	 * Resets the EXIF orientation tag after the pixels have been transformed.
	 *
	 * The tag describes the original pixels, so leaving it in place orients the saved
	 * file a second time when it is loaded again. This is the equivalent of the Imagick
	 * editor resetting the tag with `setImageOrientation()`.
	 *
	 * libvips writes JPEG EXIF from the EXIF block rather than from the orientation
	 * field, so setting the field on its own never reaches the file. `autorot()` is the
	 * operation that rewrites the block. Declaring the image upright first means it has
	 * nothing to apply, which leaves the pixels alone and only corrects the tag.
	 *
	 * @since 7.2.0
	 */
	protected function reset_exif_orientation() {
		$this->image->set( 'orientation', 1 );
		$this->image = $this->image->autorot();
	}

	/**
	 * Returns the bit depth that a libvips band format stores.
	 *
	 * @since 7.2.0
	 *
	 * @param string $format A libvips band format, such as `uchar` or `ushort`.
	 * @return int The bit depth of that format.
	 */
	protected static function get_format_bit_depth( $format ) {
		switch ( $format ) {
			case 'ushort':
			case 'short':
				return 16;
			case 'uint':
			case 'int':
			case 'float':
				return 32;
			case 'double':
			case 'complex':
				return 64;
			case 'dpcomplex':
				return 128;
			default:
				return 8;
		}
	}

	/**
	 * Returns the bit depth to save an image at, honouring `image_max_bit_depth`.
	 *
	 * libvips stores samples in a fixed set of band formats rather than at an arbitrary
	 * bit depth, and only some encoders accept a bit depth at all. This picks the nearest
	 * depth the encoder offers and returns null for everything else, which leaves the
	 * image at its own depth.
	 *
	 * @since 7.2.0
	 *
	 * @param Jcupitt\Vips\Image $image     The image being saved.
	 * @param string             $mime_type The mime type it is being saved as.
	 * @return int|null The bit depth to save at, or null to leave it unchanged.
	 */
	protected function get_save_bit_depth( $image, $mime_type ) {
		$image_depth = self::get_format_bit_depth( $image->format );

		/** This filter is documented in wp-includes/class-wp-image-editor-imagick.php */
		$max_depth = apply_filters( 'image_max_bit_depth', $image_depth, $image_depth );

		if ( $max_depth >= $image_depth ) {
			return null;
		}

		switch ( $mime_type ) {
			case 'image/png':
				// pngsave writes 8 or 16 bits per sample.
				return $max_depth <= 8 ? 8 : null;

			case 'image/avif':
			case 'image/heic':
			case 'image/heif':
				// heifsave accepts 8, 10 or 12 bits per sample.
				if ( $max_depth <= 8 ) {
					return 8;
				}

				return $max_depth <= 10 ? 10 : 12;
		}

		return null;
	}

	/**
	 * Saves current image to file.
	 *
	 * @since 7.2.0
	 *
	 * @param string $destfilename Optional. Destination filename. Default null.
	 * @param string $mime_type    Optional. The mime-type. Default null.
	 * @return array|WP_Error {
	 *     Array on success or WP_Error if the file failed to save.
	 *
	 *     @type string $path      Path to the image file.
	 *     @type string $file      Name of the image file.
	 *     @type int    $width     Image width.
	 *     @type int    $height    Image height.
	 *     @type string $mime-type The mime type of the image.
	 *     @type int    $filesize  File size of the image.
	 * }
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		$saved = $this->_save( $this->image, $destfilename, $mime_type );

		if ( ! is_wp_error( $saved ) ) {
			$this->file      = $saved['path'];
			$this->mime_type = $saved['mime-type'];
		}

		return $saved;
	}

	/**
	 * Internal save method.
	 *
	 * @since 7.2.0
	 *
	 * @param Jcupitt\Vips\Image $image
	 * @param string             $filename
	 * @param string             $mime_type
	 * @return array|WP_Error
	 */
	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		try {
			// Prepare save options based on mime type.
			$save_options = array();

			/** This filter is documented in wp-includes/class-wp-image-editor-imagick.php */
			$strip_meta = apply_filters( 'image_strip_meta', true );

			// Only strip when the image was resized or cropped. This matches the Imagick
			// editor, which strips during thumbnail generation rather than on every save,
			// so saving an image that was merely loaded keeps its metadata.
			$strip_meta = $strip_meta && $this->resized;

			switch ( $mime_type ) {
				case 'image/jpeg':
					$save_options['Q']     = $this->get_quality();
					$save_options['strip'] = $strip_meta;
					break;

				case 'image/png':
					// PNG is lossless, so there is no quality to trade away, and the Imagick
					// editor saves at maximum deflate compression. Deriving a level from the
					// quality instead wrote files far larger than the source they came from.
					$save_options['compression'] = 9;
					$save_options['strip']       = $strip_meta;

					// Asking for a palette back keeps an indexed PNG indexed. Without it the
					// resized image is written as true colour, which is much larger than the
					// file it came from. The option requires libvips 8.13.
					if ( $this->paletted && version_compare( Jcupitt\Vips\Config::version(), '8.13', '>=' ) ) {
						$save_options['palette'] = true;
					}
					break;

				case 'image/webp':
					$save_options['Q']     = $this->get_quality();
					$save_options['strip'] = $strip_meta;

					if ( $this->lossless ) {
						$save_options['lossless'] = true;
					}
					break;

				case 'image/gif':
					$save_options['strip'] = $strip_meta;
					break;

				case 'image/avif':
					$save_options['Q']     = $this->get_quality();
					$save_options['strip'] = $strip_meta;
					break;
			}

			// Of the formats above, only PNG and the HEIF family can be asked to store
			// fewer bits per sample.
			$bit_depth = $this->get_save_bit_depth( $image, $mime_type );

			if ( null !== $bit_depth ) {
				$save_options['bitdepth'] = $bit_depth;
			}

			if ( wp_is_stream( $filename ) ) {
				$buffer = $image->writeToBuffer( '.' . $extension, $save_options );
				if ( false === file_put_contents( $filename, $buffer ) ) {
					return new WP_Error(
						'image_save_error',
						sprintf(
							/* translators: %s: PHP function name. */
							__( '%s failed while writing image to stream.' ),
							'<code>file_put_contents()</code>'
						),
						$filename
					);
				}
			} else {
				$dirname = dirname( $filename );

				if ( ! wp_mkdir_p( $dirname ) ) {
					return new WP_Error(
						'image_save_error',
						sprintf(
							/* translators: %s: Directory path. */
							__( 'Unable to create directory %s. Is its parent directory writable by the server?' ),
							esc_html( $dirname )
						)
					);
				}

				$image->writeToFile( $filename, $save_options );

				// Set correct file permissions.
				$stat  = stat( $dirname );
				$perms = $stat['mode'] & 0000666;
				chmod( $filename, $perms );
			}

			return array(
				'path'      => $filename,
				/**
				 * Filters the name of the saved image file.
				 *
				 * @since 2.6.0
				 *
				 * @param string $filename Name of the file.
				 */
				'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
				'width'     => $this->size['width'],
				'height'    => $this->size['height'],
				'mime-type' => $mime_type,
				'filesize'  => wp_filesize( $filename ),
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
		}
	}

	/**
	 * Streams current image to browser.
	 *
	 * @since 7.2.0
	 *
	 * @param string $mime_type Optional. The mime type of the image. Default is the original mime type.
	 * @return true|WP_Error True on success, WP_Error object on failure.
	 */
	public function stream( $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

		try {
			$save_options = array();

			switch ( $mime_type ) {
				case 'image/png':
					// PNG is lossless; see the note in _save().
					$save_options['compression'] = 9;
					break;

				case 'image/webp':
				case 'image/jpeg':
				case 'image/avif':
					$save_options['Q'] = $this->get_quality();
					break;
			}

			// Get the image buffer.
			$buffer = $this->image->writeToBuffer( '.' . $extension, $save_options );

			// Set the appropriate header.
			header( "Content-Type: $mime_type" );
			print $buffer;

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_stream_error', $e->getMessage() );
		}
	}
}
