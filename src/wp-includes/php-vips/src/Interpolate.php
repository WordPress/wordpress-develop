<?php

/**
 * Vips is a php binding for the vips image processing library
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
 * @link      https://github.com/libvips/php-vips
 */
namespace Jcupitt\Vips;

/**
 * This class holds a pointer to a VipsInterpolate (the libvips
 * base class for interpolators) and manages argument introspection and
 * operation call.
 *
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/libvips/php-vips
 */
class Interpolate extends \Jcupitt\Vips\VipsObject
{
    /**
     * A pointer to the underlying Interpolate. This is the same as the
     * GObject, just cast to Interpolate to help FFI.
     *
     * @internal
     */
    public \FFI\CData $pointer;
    public function __construct(\FFI\CData $pointer)
    {
        $this->pointer = \Jcupitt\Vips\FFI::vips()->cast(\Jcupitt\Vips\FFI::ctypes("VipsInterpolate"), $pointer);
        parent::__construct($pointer);
    }
    /**
     * Make an interpolator from a name.
     *
     * @param string $name Name of the interpolator.
     *
     * Possible interpolators are:
     *  - `'nearest'`: Use nearest neighbour interpolation.
     *  - `'bicubic'`: Use bicubic interpolation.
     *  - `'bilinear'`: Use bilinear interpolation (the default).
     *  - `'nohalo'`: Use Nohalo interpolation.
     *  - `'lbb'`: Use LBB interpolation.
     *  - `'vsqbs'`: Use the VSQBS interpolation.
     *
     * @return Interpolate The interpolator.
     * @throws Exception If unable to make a new interpolator from $name.
     */
    public static function newFromName(string $name): \Jcupitt\Vips\Interpolate
    {
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_interpolate_new($name);
        if ($pointer == null) {
            throw new \Jcupitt\Vips\Exception();
        }
        return new \Jcupitt\Vips\Interpolate($pointer);
    }
}
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: expandtab sw=4 ts=4 fdm=marker
 * vim<600: expandtab sw=4 ts=4
 */
