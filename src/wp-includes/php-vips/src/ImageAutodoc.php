<?php

/**
 * This file was generated automatically. Do not edit!
 *
 * PHP version 7
 *
 * LICENSE:
 *
 * Copyright (c) 2016 John Cupitt
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/jcupitt/php-vips
 */
namespace Jcupitt\Vips;

/**
 * Autodocs for the Image class.
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/jcupitt/php-vips
 *
 * @method Image CMC2LCh(array $options = []) Transform LCh to CMC.
 *     @throws Exception
 * @method Image CMYK2XYZ(array $options = []) Transform CMYK to XYZ.
 *     @throws Exception
 * @method Image HSV2sRGB(array $options = []) Transform HSV to sRGB.
 *     @throws Exception
 * @method Image LCh2CMC(array $options = []) Transform LCh to CMC.
 *     @throws Exception
 * @method Image LCh2Lab(array $options = []) Transform LCh to Lab.
 *     @throws Exception
 * @method Image Lab2LCh(array $options = []) Transform Lab to LCh.
 *     @throws Exception
 * @method Image Lab2LabQ(array $options = []) Transform float Lab to LabQ coding.
 *     @throws Exception
 * @method Image Lab2LabS(array $options = []) Transform float Lab to signed short.
 *     @throws Exception
 * @method Image Lab2XYZ(array $options = []) Transform CIELAB to XYZ.
 *     @throws Exception
 * @method Image LabQ2Lab(array $options = []) Unpack a LabQ image to float Lab.
 *     @throws Exception
 * @method Image LabQ2LabS(array $options = []) Unpack a LabQ image to short Lab.
 *     @throws Exception
 * @method Image LabQ2sRGB(array $options = []) Convert a LabQ image to sRGB.
 *     @throws Exception
 * @method Image LabS2Lab(array $options = []) Transform signed short Lab to float.
 *     @throws Exception
 * @method Image LabS2LabQ(array $options = []) Transform short Lab to LabQ coding.
 *     @throws Exception
 * @method Image Oklab2Oklch(array $options = []) Transform Oklab to Oklch.
 *     @throws Exception
 * @method Image Oklab2XYZ(array $options = []) Transform Oklab to XYZ.
 *     @throws Exception
 * @method Image Oklch2Oklab(array $options = []) Transform Oklch to Oklab.
 *     @throws Exception
 * @method Image XYZ2CMYK(array $options = []) Transform XYZ to CMYK.
 *     @throws Exception
 * @method Image XYZ2Lab(array $options = []) Transform XYZ to Lab.
 *     @throws Exception
 * @method Image XYZ2Oklab(array $options = []) Transform XYZ to Oklab.
 *     @throws Exception
 * @method Image XYZ2Yxy(array $options = []) Transform XYZ to Yxy.
 *     @throws Exception
 * @method Image XYZ2scRGB(array $options = []) Transform XYZ to scRGB.
 *     @throws Exception
 * @method Image Yxy2XYZ(array $options = []) Transform Yxy to XYZ.
 *     @throws Exception
 * @method Image abs(array $options = []) Absolute value of an image.
 *     @throws Exception
 * @method Image addalpha(array $options = []) Append an alpha channel.
 *     @throws Exception
 * @method Image affine(float[]|float $matrix, array $options = []) Affine transform of an image.
 *     @throws Exception
 * @method static Image analyzeload(string $filename, array $options = []) Load an Analyze6 image.
 *     @throws Exception
 * @method static Image arrayjoin(Image[]|Image $in, array $options = []) Join an array of images.
 *     @throws Exception
 * @method Image autorot(array $options = []) Autorotate image by exif tag.
 *     @throws Exception
 * @method float avg(array $options = []) Find image average.
 *     @throws Exception
 * @method Image bandbool(string $boolean, array $options = []) Boolean operation across image bands.
 *     @see OperationBoolean for possible values for $boolean
 *     @throws Exception
 * @method Image bandfold(array $options = []) Fold up x axis into bands.
 *     @throws Exception
 * @method Image bandjoin_const(float[]|float $c, array $options = []) Append a constant band to an image.
 *     @throws Exception
 * @method Image bandmean(array $options = []) Band-wise average.
 *     @throws Exception
 * @method Image bandunfold(array $options = []) Unfold image bands into x axis.
 *     @throws Exception
 * @method static Image black(integer $width, integer $height, array $options = []) Make a black image.
 *     @throws Exception
 * @method Image boolean(Image $right, string $boolean, array $options = []) Boolean operation on two images.
 *     @see OperationBoolean for possible values for $boolean
 *     @throws Exception
 * @method Image boolean_const(string $boolean, float[]|float $c, array $options = []) Boolean operations against a constant.
 *     @see OperationBoolean for possible values for $boolean
 *     @throws Exception
 * @method Image buildlut(array $options = []) Build a look-up table.
 *     @throws Exception
 * @method Image byteswap(array $options = []) Byteswap an image.
 *     @throws Exception
 * @method Image canny(array $options = []) Canny edge detector.
 *     @throws Exception
 * @method Image case(Image[]|Image $cases, array $options = []) Use pixel values to pick cases from an array of images.
 *     @throws Exception
 * @method Image cast(string $format, array $options = []) Cast an image.
 *     @see BandFormat for possible values for $format
 *     @throws Exception
 * @method Image clamp(array $options = []) Clamp values of an image.
 *     @throws Exception
 * @method Image colourspace(string $space, array $options = []) Convert to a new colorspace.
 *     @see Interpretation for possible values for $space
 *     @throws Exception
 * @method Image compass(Image $mask, array $options = []) Convolve with rotating mask.
 *     @throws Exception
 * @method Image complex(string $cmplx, array $options = []) Perform a complex operation on an image.
 *     @see OperationComplex for possible values for $cmplx
 *     @throws Exception
 * @method Image complex2(Image $right, string $cmplx, array $options = []) Complex binary operations on two images.
 *     @see OperationComplex2 for possible values for $cmplx
 *     @throws Exception
 * @method Image complexform(Image $right, array $options = []) Form a complex image from two real images.
 *     @throws Exception
 * @method Image complexget(string $get, array $options = []) Get a component from a complex image.
 *     @see OperationComplexget for possible values for $get
 *     @throws Exception
 * @method Image composite2(Image $overlay, string $mode, array $options = []) Blend a pair of images with a blend mode.
 *     @see BlendMode for possible values for $mode
 *     @throws Exception
 * @method Image conv(Image $mask, array $options = []) Convolution operation.
 *     @throws Exception
 * @method Image conva(Image $mask, array $options = []) Approximate integer convolution.
 *     @throws Exception
 * @method Image convasep(Image $mask, array $options = []) Approximate separable integer convolution.
 *     @throws Exception
 * @method Image convf(Image $mask, array $options = []) Float convolution operation.
 *     @throws Exception
 * @method Image convi(Image $mask, array $options = []) Int convolution operation.
 *     @throws Exception
 * @method Image convsep(Image $mask, array $options = []) Separable convolution operation.
 *     @throws Exception
 * @method Image copy(array $options = []) Copy an image.
 *     @throws Exception
 * @method float countlines(string $direction, array $options = []) Count lines in an image.
 *     @see Direction for possible values for $direction
 *     @throws Exception
 * @method Image crop(integer $left, integer $top, integer $width, integer $height, array $options = []) Extract an area from an image.
 *     @throws Exception
 * @method static Image csvload(string $filename, array $options = []) Load csv.
 *     @throws Exception
 * @method static Image csvload_source(Source $source, array $options = []) Load csv.
 *     @throws Exception
 * @method void csvsave(string $filename, array $options = []) Save image to csv.
 *     @throws Exception
 * @method void csvsave_target(Target $target, array $options = []) Save image to csv.
 *     @throws Exception
 * @method Image dE00(Image $right, array $options = []) Calculate dE00.
 *     @throws Exception
 * @method Image dE76(Image $right, array $options = []) Calculate dE76.
 *     @throws Exception
 * @method Image dECMC(Image $right, array $options = []) Calculate dECMC.
 *     @throws Exception
 * @method static Image dcrawload(string $filename, array $options = []) Load RAW camera files.
 *     @throws Exception
 * @method static Image dcrawload_buffer(string $buffer, array $options = []) Load RAW camera files.
 *     @throws Exception
 * @method static Image dcrawload_source(Source $source, array $options = []) Load RAW camera files.
 *     @throws Exception
 * @method float deviate(array $options = []) Find image standard deviation.
 *     @throws Exception
 * @method Image draw_circle(float[]|float $ink, integer $cx, integer $cy, integer $radius, array $options = []) Draw a circle on an image.
 *     @throws Exception
 * @method Image draw_flood(float[]|float $ink, integer $x, integer $y, array $options = []) Flood-fill an area.
 *     @throws Exception
 * @method Image draw_image(Image $sub, integer $x, integer $y, array $options = []) Paint an image into another image.
 *     @throws Exception
 * @method Image draw_line(float[]|float $ink, integer $x1, integer $y1, integer $x2, integer $y2, array $options = []) Draw a line on an image.
 *     @throws Exception
 * @method Image draw_mask(float[]|float $ink, Image $mask, integer $x, integer $y, array $options = []) Draw a mask on an image.
 *     @throws Exception
 * @method Image draw_rect(float[]|float $ink, integer $left, integer $top, integer $width, integer $height, array $options = []) Paint a rectangle on an image.
 *     @throws Exception
 * @method Image draw_smudge(integer $left, integer $top, integer $width, integer $height, array $options = []) Blur a rectangle on an image.
 *     @throws Exception
 * @method void dzsave(string $filename, array $options = []) Save image to deepzoom file.
 *     @throws Exception
 * @method string dzsave_buffer(array $options = []) Save image to dz buffer.
 *     @throws Exception
 * @method void dzsave_target(Target $target, array $options = []) Save image to deepzoom target.
 *     @throws Exception
 * @method Image embed(integer $x, integer $y, integer $width, integer $height, array $options = []) Embed an image in a larger image.
 *     @throws Exception
 * @method Image extract_area(integer $left, integer $top, integer $width, integer $height, array $options = []) Extract an area from an image.
 *     @throws Exception
 * @method Image extract_band(integer $band, array $options = []) Extract band from an image.
 *     @throws Exception
 * @method static Image eye(integer $width, integer $height, array $options = []) Make an image showing the eye's spatial response.
 *     @throws Exception
 * @method Image falsecolour(array $options = []) False-color an image.
 *     @throws Exception
 * @method Image fastcor(Image $ref, array $options = []) Fast correlation.
 *     @throws Exception
 * @method Image fill_nearest(array $options = []) Fill image zeros with nearest non-zero pixel.
 *     @throws Exception
 * @method array find_trim(array $options = []) Search an image for non-edge areas.
 *     Return array with: [
 *         'left' => @type integer Left edge of image
 *         'top' => @type integer Top edge of extract area
 *         'width' => @type integer Width of extract area
 *         'height' => @type integer Height of extract area
 *     ];
 *     @throws Exception
 * @method static Image fitsload(string $filename, array $options = []) Load a FITS image.
 *     @throws Exception
 * @method static Image fitsload_source(Source $source, array $options = []) Load FITS from a source.
 *     @throws Exception
 * @method void fitssave(string $filename, array $options = []) Save image to fits file.
 *     @throws Exception
 * @method Image flatten(array $options = []) Flatten alpha out of an image.
 *     @throws Exception
 * @method Image flip(string $direction, array $options = []) Flip an image.
 *     @see Direction for possible values for $direction
 *     @throws Exception
 * @method Image float2rad(array $options = []) Transform float RGB to Radiance coding.
 *     @throws Exception
 * @method static Image fractsurf(integer $width, integer $height, float $fractal_dimension, array $options = []) Make a fractal surface.
 *     @throws Exception
 * @method Image freqmult(Image $mask, array $options = []) Frequency-domain filtering.
 *     @throws Exception
 * @method Image fwfft(array $options = []) Forward FFT.
 *     @throws Exception
 * @method Image gamma(array $options = []) Gamma an image.
 *     @throws Exception
 * @method Image gaussblur(float $sigma, array $options = []) Gaussian blur.
 *     @throws Exception
 * @method static Image gaussmat(float $sigma, float $min_ampl, array $options = []) Make a gaussian image.
 *     @throws Exception
 * @method static Image gaussnoise(integer $width, integer $height, array $options = []) Make a gaussnoise image.
 *     @throws Exception
 * @method array getpoint(integer $x, integer $y, array $options = []) Read a point from an image.
 *     @throws Exception
 * @method static Image gifload(string $filename, array $options = []) Load GIF with libnsgif.
 *     @throws Exception
 * @method static Image gifload_buffer(string $buffer, array $options = []) Load GIF with libnsgif.
 *     @throws Exception
 * @method static Image gifload_source(Source $source, array $options = []) Load gif from source.
 *     @throws Exception
 * @method void gifsave(string $filename, array $options = []) Save as gif.
 *     @throws Exception
 * @method string gifsave_buffer(array $options = []) Save as gif.
 *     @throws Exception
 * @method void gifsave_target(Target $target, array $options = []) Save as gif.
 *     @throws Exception
 * @method Image globalbalance(array $options = []) Global balance an image mosaic.
 *     @throws Exception
 * @method Image gravity(string $direction, integer $width, integer $height, array $options = []) Place an image within a larger image with a certain gravity.
 *     @see CompassDirection for possible values for $direction
 *     @throws Exception
 * @method static Image grey(integer $width, integer $height, array $options = []) Make a grey ramp image.
 *     @throws Exception
 * @method Image grid(integer $tile_height, integer $across, integer $down, array $options = []) Grid an image.
 *     @throws Exception
 * @method static Image heifload(string $filename, array $options = []) Load a HEIF image.
 *     @throws Exception
 * @method static Image heifload_buffer(string $buffer, array $options = []) Load a HEIF image.
 *     @throws Exception
 * @method static Image heifload_source(Source $source, array $options = []) Load a HEIF image.
 *     @throws Exception
 * @method void heifsave(string $filename, array $options = []) Save image in HEIF format.
 *     @throws Exception
 * @method string heifsave_buffer(array $options = []) Save image in HEIF format.
 *     @throws Exception
 * @method void heifsave_target(Target $target, array $options = []) Save image in HEIF format.
 *     @throws Exception
 * @method Image hist_cum(array $options = []) Form cumulative histogram.
 *     @throws Exception
 * @method float hist_entropy(array $options = []) Estimate image entropy.
 *     @throws Exception
 * @method Image hist_equal(array $options = []) Histogram equalisation.
 *     @throws Exception
 * @method Image hist_find(array $options = []) Find image histogram.
 *     @throws Exception
 * @method Image hist_find_indexed(Image $index, array $options = []) Find indexed image histogram.
 *     @throws Exception
 * @method Image hist_find_ndim(array $options = []) Find n-dimensional image histogram.
 *     @throws Exception
 * @method bool hist_ismonotonic(array $options = []) Test for monotonicity.
 *     @throws Exception
 * @method Image hist_local(integer $width, integer $height, array $options = []) Local histogram equalisation.
 *     @throws Exception
 * @method Image hist_match(Image $ref, array $options = []) Match two histograms.
 *     @throws Exception
 * @method Image hist_norm(array $options = []) Normalise histogram.
 *     @throws Exception
 * @method Image hist_plot(array $options = []) Plot histogram.
 *     @throws Exception
 * @method Image hough_circle(array $options = []) Find hough circle transform.
 *     @throws Exception
 * @method Image hough_line(array $options = []) Find hough line transform.
 *     @throws Exception
 * @method Image icc_export(array $options = []) Output to device with ICC profile.
 *     @throws Exception
 * @method Image icc_import(array $options = []) Import from device with ICC profile.
 *     @throws Exception
 * @method Image icc_transform(string $output_profile, array $options = []) Transform between devices with ICC profiles.
 *     @throws Exception
 * @method static Image identity(array $options = []) Make a 1D image where pixel values are indexes.
 *     @throws Exception
 * @method Image insert(Image $sub, integer $x, integer $y, array $options = []) Insert image @sub into @main at @x, @y.
 *     @throws Exception
 * @method Image invert(array $options = []) Invert an image.
 *     @throws Exception
 * @method Image invertlut(array $options = []) Build an inverted look-up table.
 *     @throws Exception
 * @method Image invfft(array $options = []) Inverse FFT.
 *     @throws Exception
 * @method Image join(Image $in2, string $direction, array $options = []) Join a pair of images.
 *     @see Direction for possible values for $direction
 *     @throws Exception
 * @method static Image jp2kload(string $filename, array $options = []) Load JPEG2000 image.
 *     @throws Exception
 * @method static Image jp2kload_buffer(string $buffer, array $options = []) Load JPEG2000 image.
 *     @throws Exception
 * @method static Image jp2kload_source(Source $source, array $options = []) Load JPEG2000 image.
 *     @throws Exception
 * @method void jp2ksave(string $filename, array $options = []) Save image in JPEG2000 format.
 *     @throws Exception
 * @method string jp2ksave_buffer(array $options = []) Save image in JPEG2000 format.
 *     @throws Exception
 * @method void jp2ksave_target(Target $target, array $options = []) Save image in JPEG2000 format.
 *     @throws Exception
 * @method static Image jpegload(string $filename, array $options = []) Load jpeg from file.
 *     @throws Exception
 * @method static Image jpegload_buffer(string $buffer, array $options = []) Load jpeg from buffer.
 *     @throws Exception
 * @method static Image jpegload_source(Source $source, array $options = []) Load image from jpeg source.
 *     @throws Exception
 * @method void jpegsave(string $filename, array $options = []) Save as jpeg.
 *     @throws Exception
 * @method string jpegsave_buffer(array $options = []) Save as jpeg.
 *     @throws Exception
 * @method void jpegsave_mime(array $options = []) Save image to jpeg mime.
 *     @throws Exception
 * @method void jpegsave_target(Target $target, array $options = []) Save as jpeg.
 *     @throws Exception
 * @method static Image jxlload(string $filename, array $options = []) Load JPEG-XL image.
 *     @throws Exception
 * @method static Image jxlload_buffer(string $buffer, array $options = []) Load JPEG-XL image.
 *     @throws Exception
 * @method static Image jxlload_source(Source $source, array $options = []) Load JPEG-XL image.
 *     @throws Exception
 * @method void jxlsave(string $filename, array $options = []) Save image in JPEG-XL format.
 *     @throws Exception
 * @method string jxlsave_buffer(array $options = []) Save image in JPEG-XL format.
 *     @throws Exception
 * @method void jxlsave_target(Target $target, array $options = []) Save image in JPEG-XL format.
 *     @throws Exception
 * @method Image labelregions(array $options = []) Label regions in an image.
 *     @throws Exception
 * @method Image linear(float[]|float $a, float[]|float $b, array $options = []) Calculate (a * in + b).
 *     @throws Exception
 * @method Image linecache(array $options = []) Cache an image as a set of lines.
 *     @throws Exception
 * @method static Image logmat(float $sigma, float $min_ampl, array $options = []) Make a Laplacian of Gaussian image.
 *     @throws Exception
 * @method static Image magickload(string $filename, array $options = []) Load file with ImageMagick7.
 *     @throws Exception
 * @method static Image magickload_buffer(string $buffer, array $options = []) Load buffer with ImageMagick7.
 *     @throws Exception
 * @method static Image magickload_source(Source $source, array $options = []) Load source with ImageMagick7.
 *     @throws Exception
 * @method void magicksave(string $filename, array $options = []) Save file with ImageMagick.
 *     @throws Exception
 * @method string magicksave_buffer(array $options = []) Save image to magick buffer.
 *     @throws Exception
 * @method Image mapim(Image $index, array $options = []) Resample with a map image.
 *     @throws Exception
 * @method Image maplut(Image $lut, array $options = []) Map an image though a lut.
 *     @throws Exception
 * @method static Image mask_butterworth(integer $width, integer $height, float $order, float $frequency_cutoff, float $amplitude_cutoff, array $options = []) Make a butterworth filter.
 *     @throws Exception
 * @method static Image mask_butterworth_band(integer $width, integer $height, float $order, float $frequency_cutoff_x, float $frequency_cutoff_y, float $radius, float $amplitude_cutoff, array $options = []) Make a butterworth_band filter.
 *     @throws Exception
 * @method static Image mask_butterworth_ring(integer $width, integer $height, float $order, float $frequency_cutoff, float $amplitude_cutoff, float $ringwidth, array $options = []) Make a butterworth ring filter.
 *     @throws Exception
 * @method static Image mask_fractal(integer $width, integer $height, float $fractal_dimension, array $options = []) Make fractal filter.
 *     @throws Exception
 * @method static Image mask_gaussian(integer $width, integer $height, float $frequency_cutoff, float $amplitude_cutoff, array $options = []) Make a gaussian filter.
 *     @throws Exception
 * @method static Image mask_gaussian_band(integer $width, integer $height, float $frequency_cutoff_x, float $frequency_cutoff_y, float $radius, float $amplitude_cutoff, array $options = []) Make a gaussian filter.
 *     @throws Exception
 * @method static Image mask_gaussian_ring(integer $width, integer $height, float $frequency_cutoff, float $amplitude_cutoff, float $ringwidth, array $options = []) Make a gaussian ring filter.
 *     @throws Exception
 * @method static Image mask_ideal(integer $width, integer $height, float $frequency_cutoff, array $options = []) Make an ideal filter.
 *     @throws Exception
 * @method static Image mask_ideal_band(integer $width, integer $height, float $frequency_cutoff_x, float $frequency_cutoff_y, float $radius, array $options = []) Make an ideal band filter.
 *     @throws Exception
 * @method static Image mask_ideal_ring(integer $width, integer $height, float $frequency_cutoff, float $ringwidth, array $options = []) Make an ideal ring filter.
 *     @throws Exception
 * @method Image match(Image $sec, integer $xr1, integer $yr1, integer $xs1, integer $ys1, integer $xr2, integer $yr2, integer $xs2, integer $ys2, array $options = []) First-order match of two images.
 *     @throws Exception
 * @method Image math(string $math, array $options = []) Apply a math operation to an image.
 *     @see OperationMath for possible values for $math
 *     @throws Exception
 * @method Image math2(Image $right, string $math2, array $options = []) Binary math operations.
 *     @see OperationMath2 for possible values for $math2
 *     @throws Exception
 * @method Image math2_const(string $math2, float[]|float $c, array $options = []) Binary math operations with a constant.
 *     @see OperationMath2 for possible values for $math2
 *     @throws Exception
 * @method static Image matload(string $filename, array $options = []) Load mat from file.
 *     @throws Exception
 * @method Image matrixinvert(array $options = []) Invert a matrix.
 *     @throws Exception
 * @method static Image matrixload(string $filename, array $options = []) Load matrix.
 *     @throws Exception
 * @method static Image matrixload_source(Source $source, array $options = []) Load matrix.
 *     @throws Exception
 * @method Image matrixmultiply(Image $right, array $options = []) Multiply two matrices.
 *     @throws Exception
 * @method void matrixprint(array $options = []) Print matrix.
 *     @throws Exception
 * @method void matrixsave(string $filename, array $options = []) Save image to matrix.
 *     @throws Exception
 * @method void matrixsave_target(Target $target, array $options = []) Save image to matrix.
 *     @throws Exception
 * @method float max(array $options = []) Find image maximum.
 *     @throws Exception
 * @method Image maxpair(Image $right, array $options = []) Maximum of a pair of images.
 *     @throws Exception
 * @method Image measure(integer $h, integer $v, array $options = []) Measure a set of patches on a color chart.
 *     @throws Exception
 * @method Image merge(Image $sec, string $direction, integer $dx, integer $dy, array $options = []) Merge two images.
 *     @see Direction for possible values for $direction
 *     @throws Exception
 * @method float min(array $options = []) Find image minimum.
 *     @throws Exception
 * @method Image minpair(Image $right, array $options = []) Minimum of a pair of images.
 *     @throws Exception
 * @method Image morph(Image $mask, string $morph, array $options = []) Morphology operation.
 *     @see OperationMorphology for possible values for $morph
 *     @throws Exception
 * @method Image mosaic(Image $sec, string $direction, integer $xref, integer $yref, integer $xsec, integer $ysec, array $options = []) Mosaic two images.
 *     @see Direction for possible values for $direction
 *     @throws Exception
 * @method Image mosaic1(Image $sec, string $direction, integer $xr1, integer $yr1, integer $xs1, integer $ys1, integer $xr2, integer $yr2, integer $xs2, integer $ys2, array $options = []) First-order mosaic of two images.
 *     @see Direction for possible values for $direction
 *     @throws Exception
 * @method Image msb(array $options = []) Pick most-significant byte from an image.
 *     @throws Exception
 * @method static Image niftiload(string $filename, array $options = []) Load NIfTI volume.
 *     @throws Exception
 * @method static Image niftiload_source(Source $source, array $options = []) Load NIfTI volumes.
 *     @throws Exception
 * @method void niftisave(string $filename, array $options = []) Save image to nifti file.
 *     @throws Exception
 * @method static Image openexrload(string $filename, array $options = []) Load an OpenEXR image.
 *     @throws Exception
 * @method static Image openslideload(string $filename, array $options = []) Load file with OpenSlide.
 *     @throws Exception
 * @method static Image openslideload_source(Source $source, array $options = []) Load source with OpenSlide.
 *     @throws Exception
 * @method static Image pdfload(string $filename, array $options = []) Load PDF from file (poppler).
 *     @throws Exception
 * @method static Image pdfload_buffer(string $buffer, array $options = []) Load PDF from buffer (poppler).
 *     @throws Exception
 * @method static Image pdfload_source(Source $source, array $options = []) Load PDF from source (poppler).
 *     @throws Exception
 * @method integer percent(float $percent, array $options = []) Find threshold for percent of pixels.
 *     @throws Exception
 * @method static Image perlin(integer $width, integer $height, array $options = []) Make a perlin noise image.
 *     @throws Exception
 * @method Image phasecor(Image $in2, array $options = []) Calculate phase correlation.
 *     @throws Exception
 * @method static Image pngload(string $filename, array $options = []) Load png from file.
 *     @throws Exception
 * @method static Image pngload_buffer(string $buffer, array $options = []) Load png from buffer.
 *     @throws Exception
 * @method static Image pngload_source(Source $source, array $options = []) Load png from source.
 *     @throws Exception
 * @method void pngsave(string $filename, array $options = []) Save image to file as png.
 *     @throws Exception
 * @method string pngsave_buffer(array $options = []) Save image to buffer as png.
 *     @throws Exception
 * @method void pngsave_target(Target $target, array $options = []) Save image to target as PNG.
 *     @throws Exception
 * @method static Image ppmload(string $filename, array $options = []) Load ppm from file.
 *     @throws Exception
 * @method static Image ppmload_buffer(string $buffer, array $options = []) Load ppm from buffer.
 *     @throws Exception
 * @method static Image ppmload_source(Source $source, array $options = []) Load ppm from source.
 *     @throws Exception
 * @method void ppmsave(string $filename, array $options = []) Save image to ppm file.
 *     @throws Exception
 * @method void ppmsave_target(Target $target, array $options = []) Save to ppm.
 *     @throws Exception
 * @method Image premultiply(array $options = []) Premultiply image alpha.
 *     @throws Exception
 * @method Image prewitt(array $options = []) Prewitt edge detector.
 *     @throws Exception
 * @method array profile(array $options = []) Find image profiles.
 *     Return array with: [
 *         'columns' => @type Image First non-zero pixel in column
 *         'rows' => @type Image First non-zero pixel in row
 *     ];
 *     @throws Exception
 * @method static string profile_load(string $name, array $options = []) Load named ICC profile.
 *     @throws Exception
 * @method array project(array $options = []) Find image projections.
 *     Return array with: [
 *         'columns' => @type Image Sums of columns
 *         'rows' => @type Image Sums of rows
 *     ];
 *     @throws Exception
 * @method Image quadratic(Image $coeff, array $options = []) Resample an image with a quadratic transform.
 *     @throws Exception
 * @method Image rad2float(array $options = []) Unpack Radiance coding to float RGB.
 *     @throws Exception
 * @method static Image radload(string $filename, array $options = []) Load a Radiance image from a file.
 *     @throws Exception
 * @method static Image radload_buffer(string $buffer, array $options = []) Load rad from buffer.
 *     @throws Exception
 * @method static Image radload_source(Source $source, array $options = []) Load rad from source.
 *     @throws Exception
 * @method void radsave(string $filename, array $options = []) Save image to Radiance file.
 *     @throws Exception
 * @method string radsave_buffer(array $options = []) Save image to Radiance buffer.
 *     @throws Exception
 * @method void radsave_target(Target $target, array $options = []) Save image to Radiance target.
 *     @throws Exception
 * @method Image rank(integer $width, integer $height, integer $index, array $options = []) Rank filter.
 *     @throws Exception
 * @method static Image rawload(string $filename, integer $width, integer $height, integer $bands, array $options = []) Load raw data from a file.
 *     @throws Exception
 * @method void rawsave(string $filename, array $options = []) Save image to raw file.
 *     @throws Exception
 * @method string rawsave_buffer(array $options = []) Write raw image to buffer.
 *     @throws Exception
 * @method void rawsave_target(Target $target, array $options = []) Write raw image to target.
 *     @throws Exception
 * @method Image recomb(Image $m, array $options = []) Linear recombination with matrix.
 *     @throws Exception
 * @method Image reduce(float $hshrink, float $vshrink, array $options = []) Reduce an image.
 *     @throws Exception
 * @method Image reduceh(float $hshrink, array $options = []) Shrink an image horizontally.
 *     @throws Exception
 * @method Image reducev(float $vshrink, array $options = []) Shrink an image vertically.
 *     @throws Exception
 * @method Image relational(Image $right, string $relational, array $options = []) Relational operation on two images.
 *     @see OperationRelational for possible values for $relational
 *     @throws Exception
 * @method Image relational_const(string $relational, float[]|float $c, array $options = []) Relational operations against a constant.
 *     @see OperationRelational for possible values for $relational
 *     @throws Exception
 * @method Image remainder_const(float[]|float $c, array $options = []) Remainder after integer division of an image and a constant.
 *     @throws Exception
 * @method Image remosaic(string $old_str, string $new_str, array $options = []) Rebuild an mosaiced image.
 *     @throws Exception
 * @method Image replicate(integer $across, integer $down, array $options = []) Replicate an image.
 *     @throws Exception
 * @method Image resize(float $scale, array $options = []) Resize an image.
 *     @throws Exception
 * @method Image rot(string $angle, array $options = []) Rotate an image.
 *     @see Angle for possible values for $angle
 *     @throws Exception
 * @method Image rot45(array $options = []) Rotate an image.
 *     @throws Exception
 * @method Image rotate(float $angle, array $options = []) Rotate an image by a number of degrees.
 *     @throws Exception
 * @method Image round(string $round, array $options = []) Perform a round function on an image.
 *     @see OperationRound for possible values for $round
 *     @throws Exception
 * @method Image sRGB2HSV(array $options = []) Transform sRGB to HSV.
 *     @throws Exception
 * @method Image sRGB2scRGB(array $options = []) Convert an sRGB image to scRGB.
 *     @throws Exception
 * @method Image scRGB2BW(array $options = []) Convert scRGB to BW.
 *     @throws Exception
 * @method Image scRGB2XYZ(array $options = []) Transform scRGB to XYZ.
 *     @throws Exception
 * @method Image scRGB2sRGB(array $options = []) Convert scRGB to sRGB.
 *     @throws Exception
 * @method Image scale(array $options = []) Scale an image to uchar.
 *     @throws Exception
 * @method Image scharr(array $options = []) Scharr edge detector.
 *     @throws Exception
 * @method static Image sdf(integer $width, integer $height, string $shape, array $options = []) Create an SDF image.
 *     @see SdfShape for possible values for $shape
 *     @throws Exception
 * @method Image sequential(array $options = []) Check sequential access.
 *     @throws Exception
 * @method Image sharpen(array $options = []) Unsharp masking for print.
 *     @throws Exception
 * @method Image shrink(float $hshrink, float $vshrink, array $options = []) Shrink an image.
 *     @throws Exception
 * @method Image shrinkh(integer $hshrink, array $options = []) Shrink an image horizontally.
 *     @throws Exception
 * @method Image shrinkv(integer $vshrink, array $options = []) Shrink an image vertically.
 *     @throws Exception
 * @method Image sign(array $options = []) Unit vector of pixel.
 *     @throws Exception
 * @method Image similarity(array $options = []) Similarity transform of an image.
 *     @throws Exception
 * @method static Image sines(integer $width, integer $height, array $options = []) Make a 2D sine wave.
 *     @throws Exception
 * @method Image smartcrop(integer $width, integer $height, array $options = []) Extract an area from an image.
 *     @throws Exception
 * @method Image sobel(array $options = []) Sobel edge detector.
 *     @throws Exception
 * @method Image spcor(Image $ref, array $options = []) Spatial correlation.
 *     @throws Exception
 * @method Image spectrum(array $options = []) Make displayable power spectrum.
 *     @throws Exception
 * @method Image stats(array $options = []) Find many image stats.
 *     @throws Exception
 * @method Image stdif(integer $width, integer $height, array $options = []) Statistical difference.
 *     @throws Exception
 * @method Image subsample(integer $xfac, integer $yfac, array $options = []) Subsample an image.
 *     @throws Exception
 * @method static Image sum(Image[]|Image $in, array $options = []) Sum an array of images.
 *     @throws Exception
 * @method static Image svgload(string $filename, array $options = []) Load SVG with rsvg.
 *     @throws Exception
 * @method static Image svgload_buffer(string $buffer, array $options = []) Load SVG with rsvg.
 *     @throws Exception
 * @method static Image svgload_source(Source $source, array $options = []) Load svg from source.
 *     @throws Exception
 * @method static Image switch(Image[]|Image $tests, array $options = []) Find the index of the first non-zero pixel in tests.
 *     @throws Exception
 * @method static void system(string $cmd_format, array $options = []) Run an external command.
 *     @throws Exception
 * @method static Image text(string $text, array $options = []) Make a text image.
 *     @throws Exception
 * @method static Image thumbnail(string $filename, integer $width, array $options = []) Generate thumbnail from file.
 *     @throws Exception
 * @method static Image thumbnail_buffer(string $buffer, integer $width, array $options = []) Generate thumbnail from buffer.
 *     @throws Exception
 * @method Image thumbnail_image(integer $width, array $options = []) Generate thumbnail from image.
 *     @throws Exception
 * @method static Image thumbnail_source(Source $source, integer $width, array $options = []) Generate thumbnail from source.
 *     @throws Exception
 * @method static Image tiffload(string $filename, array $options = []) Load tiff from file.
 *     @throws Exception
 * @method static Image tiffload_buffer(string $buffer, array $options = []) Load tiff from buffer.
 *     @throws Exception
 * @method static Image tiffload_source(Source $source, array $options = []) Load tiff from source.
 *     @throws Exception
 * @method void tiffsave(string $filename, array $options = []) Save image to tiff file.
 *     @throws Exception
 * @method string tiffsave_buffer(array $options = []) Save image to tiff buffer.
 *     @throws Exception
 * @method void tiffsave_target(Target $target, array $options = []) Save image to tiff target.
 *     @throws Exception
 * @method Image tilecache(array $options = []) Cache an image as a set of tiles.
 *     @throws Exception
 * @method static Image tonelut(array $options = []) Build a look-up table.
 *     @throws Exception
 * @method Image transpose3d(array $options = []) Transpose3d an image.
 *     @throws Exception
 * @method Image uhdr2scRGB(array $options = []) Transform uhdr to scRGB.
 *     @throws Exception
 * @method static Image uhdrload(string $filename, array $options = []) Load a UHDR image.
 *     @throws Exception
 * @method static Image uhdrload_buffer(string $buffer, array $options = []) Load a UHDR image.
 *     @throws Exception
 * @method static Image uhdrload_source(Source $source, array $options = []) Load a UHDR image.
 *     @throws Exception
 * @method void uhdrsave(string $filename, array $options = []) Save image in UltraHDR format.
 *     @throws Exception
 * @method string uhdrsave_buffer(array $options = []) Save image in UltraHDR format.
 *     @throws Exception
 * @method void uhdrsave_target(Target $target, array $options = []) Save image in UltraHDR format.
 *     @throws Exception
 * @method Image unpremultiply(array $options = []) Unpremultiply image alpha.
 *     @throws Exception
 * @method static Image vipsload(string $filename, array $options = []) Load vips from file.
 *     @throws Exception
 * @method static Image vipsload_source(Source $source, array $options = []) Load vips from source.
 *     @throws Exception
 * @method void vipssave(string $filename, array $options = []) Save image to file in vips format.
 *     @throws Exception
 * @method void vipssave_target(Target $target, array $options = []) Save image to target in vips format.
 *     @throws Exception
 * @method static Image webpload(string $filename, array $options = []) Load webp from file.
 *     @throws Exception
 * @method static Image webpload_buffer(string $buffer, array $options = []) Load webp from buffer.
 *     @throws Exception
 * @method static Image webpload_source(Source $source, array $options = []) Load webp from source.
 *     @throws Exception
 * @method void webpsave(string $filename, array $options = []) Save as WebP.
 *     @throws Exception
 * @method string webpsave_buffer(array $options = []) Save as WebP.
 *     @throws Exception
 * @method void webpsave_mime(array $options = []) Save image to webp mime.
 *     @throws Exception
 * @method void webpsave_target(Target $target, array $options = []) Save as WebP.
 *     @throws Exception
 * @method static Image worley(integer $width, integer $height, array $options = []) Make a worley noise image.
 *     @throws Exception
 * @method Image wrap(array $options = []) Wrap image origin.
 *     @throws Exception
 * @method static Image xyz(integer $width, integer $height, array $options = []) Make an image where pixel values are coordinates.
 *     @throws Exception
 * @method static Image zone(integer $width, integer $height, array $options = []) Make a zone plate.
 *     @throws Exception
 * @method Image zoom(integer $xfac, integer $yfac, array $options = []) Zoom an image.
 *     @throws Exception
 *
 * @property integer $width Image width in pixels
 * @property integer $height Image height in pixels
 * @property integer $bands Number of bands in image
 * @property string $format Pixel format in image
 *     @see BandFormat for possible values
 * @property string $coding Pixel coding
 *     @see Coding for possible values
 * @property string $interpretation Pixel interpretation
 *     @see Interpretation for possible values
 * @property integer $xoffset Horizontal offset of origin
 * @property integer $yoffset Vertical offset of origin
 * @property float $xres Horizontal resolution in pixels/mm
 * @property float $yres Vertical resolution in pixels/mm
 * @property string $filename Image filename
 */
abstract class ImageAutodoc extends \Jcupitt\Vips\VipsObject
{
    abstract public function __set(string $name, $value);
    abstract public function __get(string $name);
}
