<?php
/**
 * WordPress VIPS Image Editor
 *
 * @package WordPress
 * @subpackage Image_Editor
 */

/**
 * WordPress Image Editor Class for Image Manipulation through VIPS
 *
 * @since 6.8.0
 *
 * @see WP_Image_Editor
 */
class WP_Image_Editor_Vips extends WP_Image_Editor {
	/**
	 * VIPS Image object.
	 *
	 * @var Jcupitt\Vips\Image
	 */
	protected $image;

	/**
	 * Original image object used for multi_resize operations.
	 *
	 * @var Jcupitt\Vips\Image|null
	 */
	protected $original_image;

	public function __destruct() {
		if ( $this->image ) {
			// VIPS uses reference counting and automatic memory management.
			$this->image = null;
		}

		if ( $this->original_image ) {
			$this->original_image = null;
		}
	}

	/**
	 * Checks to see if current environment supports VIPS.
	 *
	 * @since 6.8.0
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
	 * @since 6.8.0
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		$vips_extension = strtoupper( self::get_extension( $mime_type ) );

		if ( ! $vips_extension ) {
			return false;
		}

		// Check if VIPS supports this format.
		try {
			// Map common MIME types to VIPS format names.
			$format_map = array(
				'JPEG' => 'jpeg',
				'JPG'  => 'jpeg',
				'PNG'  => 'png',
				'WEBP' => 'webp',
				'GIF'  => 'gif',
				'TIFF' => 'tiff',
				'TIF'  => 'tiff',
				'HEIC' => 'heif',
				'AVIF' => 'heif', // AVIF is part of HEIF family.
			);

			$format = isset( $format_map[ $vips_extension ] ) ? $format_map[ $vips_extension ] : strtolower( $vips_extension );

			// VIPS can usually handle these formats.
			$supported_formats = array( 'jpeg', 'png', 'webp', 'gif', 'tiff', 'heif' );
			return in_array( $format, $supported_formats, true );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Loads image from $this->file into new VIPS Image object.
	 *
	 * @since 6.8.0
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

			if ( ! $this->image ) {
				return new WP_Error( 'invalid_image', __( 'File is not an image.' ), $this->file );
			}

			// Get image size.
			$width  = $this->image->width;
			$height = $this->image->height;

			if ( ! $width || ! $height ) {
				return new WP_Error( 'invalid_image', __( 'Could not read image size.' ), $this->file );
			}

			// Set the mime type.
			$size = wp_getimagesize( $this->file );
			if ( $size ) {
				$this->mime_type = $size['mime'];
			} else {
				$this->mime_type = wp_get_image_mime( $this->file );
			}

			if ( ! $this->mime_type ) {
				$this->mime_type = $this->default_mime_type;
			}

			$this->update_size( $width, $height );
			$this->original_image = $this->image->copy();

			return $this->set_quality();
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_image', $e->getMessage(), $this->file );
		}
	}

	/**
	 * Sets or updates current image size.
	 *
	 * @since 6.8.0
	 *
	 * @param int $width
	 * @param int $height
	 * @return true
	 */
	protected function update_size( $width = false, $height = false ) {
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
	 * @since 6.8.0
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

		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __( 'Could not calculate resized image dimensions' ) );
		}

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		if ( $crop ) {
			return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h );
		}

		try {
			// Use resize instead of thumbnail_image for more control.
			// Calculate scale factor.
			$h_scale = $dst_w / $this->size['width'];
			$v_scale = $dst_h / $this->size['height'];

			$this->image = $this->image->resize( $h_scale, array( 'vscale' => $v_scale ) );

			$this->update_size( $dst_w, $dst_h );

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_resize_error', $e->getMessage() );
		}
	}

	/**
	 * Resize multiple images from a single source.
	 *
	 * @since 6.8.0
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
		$orig_size = $this->size;

		if ( ! $this->original_image ) {
			try {
				$this->original_image = $this->image->copy();
			} catch ( Exception $e ) {
				return $metadata;
			}
		}

		foreach ( $sizes as $size => $size_data ) {
			try {
				$this->image = $this->original_image->copy();
			} catch ( Exception $e ) {
				continue;
			}

			$this->size = $orig_size;

			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
				continue;
			}

			if ( ! isset( $size_data['width'] ) ) {
				$size_data['width'] = null;
			}

			if ( ! isset( $size_data['height'] ) ) {
				$size_data['height'] = null;
			}

			if ( ! isset( $size_data['crop'] ) ) {
				$size_data['crop'] = false;
			}

			$resize_result = $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] );
			$duplicate = ( ( $orig_size['width'] === $size_data['width'] ) && ( $orig_size['height'] === $size_data['height'] ) );

			if ( ! is_wp_error( $resize_result ) && ! $duplicate ) {
				$resized = $this->_save( $this->image );

				if ( ! is_wp_error( $resized ) && $resized ) {
					unset( $resized['path'] );
					$metadata[ $size ] = $resized;
				}
			}

		}

		// Restore original image and dimensions.
		try {
			$this->image = $this->original_image->copy();
			$this->size = $orig_size;
		} catch ( Exception $e ) {
			$this->size = $orig_size;
		}

		return $metadata;
	}

	/**
	 * Crops Image.
	 *
	 * @since 6.8.0
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
		// If destination width/height isn't specified, use same as source
		if ( $dst_w === null ) {
			$dst_w = $src_w;
		}

		if ( $dst_h === null ) {
			$dst_h = $src_h;
		}

		try {
			// Crop the image.
			$cropped = $this->image->crop( (int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h );

			// Resize if needed.
			if ( $src_w !== $dst_w || $src_h !== $dst_h ) {
				$h_scale = $dst_w / $src_w;
				$v_scale = $dst_h / $src_h;
				$cropped = $cropped->resize( $h_scale, array( 'vscale' => $v_scale ) );
			}

			$this->image = $cropped;
			$this->update_size( $dst_w, $dst_h );

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_crop_error', $e->getMessage() );
		}
	}

	/**
	 * Rotates current image counter-clockwise by $angle.
	 *
	 * @since 6.8.0
	 *
	 * @param float $angle
	 * @return true|WP_Error
	 */
	public function rotate( $angle ) {
		// VIPS rotate is clockwise, WordPress expects counter-clockwise,
		// so we need to invert the angle.
		$angle = 360 - $angle;

		try {
			$this->image = $this->image->rotate( $angle );

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
	 * Flips current image.
	 *
	 * @since 6.8.0
	 *
	 * @param bool $horz Flip along Horizontal Axis
	 * @param bool $vert Flip along Vertical Axis
	 * @return true|WP_Error
	 */
	public function flip( $horz, $vert ) {
		try {
			if ( $horz ) {
				$this->image = $this->image->fliphor();
			}

			if ( $vert ) {
				$this->image = $this->image->flipver();
			}

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'image_flip_error', $e->getMessage() );
		}
	}

	/**
	 * Saves current image to file.
	 *
	 * @since 6.8.0
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
	 * @since 6.8.0
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

			switch ( $mime_type ) {
				case 'image/jpeg':
					$save_options['Q'] = $this->get_quality();
					$save_options['strip'] = true;
					break;

				case 'image/png':
					// PNG quality in VIPS is compression level (0-9).
					// Convert WP quality (0-100) to VIPS compression (9-0).
					$quality = $this->get_quality();
					$compression = 9 - round( ( $quality / 100 ) * 9 );
					$save_options['compression'] = max( 0, min( 9, $compression ) );
					$save_options['strip'] = true;
					break;

				case 'image/webp':
					$save_options['Q'] = $this->get_quality();
					$save_options['strip'] = true;
					break;

				case 'image/gif':
					$save_options['strip'] = true;
					break;

				case 'image/avif':
					$save_options['Q'] = $this->get_quality();
					$save_options['strip'] = true;
					break;
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
	 * @since 6.8.0
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
					$quality = $this->get_quality();
					$compression = 9 - round( ( $quality / 100 ) * 9 );
					$save_options['compression'] = max( 0, min( 9, $compression ) );
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
