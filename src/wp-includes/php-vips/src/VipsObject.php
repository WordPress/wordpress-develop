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
 * This class holds a pointer to a VipsObject (the libvips base class) and
 * manages properties.
 *
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/libvips/php-vips
 */
abstract class VipsObject extends \Jcupitt\Vips\GObject
{
    /**
     * A pointer to the underlying VipsObject.
     *
     * @internal
     */
    private \FFI\CData $pointer;
    /**
     * A pointer to the underlying GObject. This is the same as the
     * VipsObject, just cast.
     *
     * @internal
     */
    private \FFI\CData $gObject;
    public function __construct(\FFI\CData $pointer)
    {
        $this->pointer = \Jcupitt\Vips\FFI::vips()->cast(\Jcupitt\Vips\FFI::ctypes("VipsObject"), $pointer);
        $this->gObject = \Jcupitt\Vips\FFI::vips()->cast(\Jcupitt\Vips\FFI::ctypes("GObject"), $pointer);
        parent::__construct($pointer);
    }
    // print a table of all active vipsobjects ... handy for debugging
    public static function printAll(): void
    {
        \Jcupitt\Vips\FFI::vips()->vips_object_print_all();
    }
    public function getDescription(): string
    {
        return \Jcupitt\Vips\FFI::vips()->vips_object_get_description($this->pointer);
    }
    // get the pspec for a property
    // NULL for no such name
    // very slow! avoid if possible
    // FIXME add a cache for this thing, see code in pyvips
    public function getPspec(string $name): ?\FFI\CData
    {
        $name = str_replace("-", "_", $name);
        $pspec_array = \Jcupitt\Vips\FFI::gobject()->new("GParamSpec*[1]");
        $argument_class_array = \Jcupitt\Vips\FFI::vips()->new("VipsArgumentClass*[1]");
        $argument_instance_array = \Jcupitt\Vips\FFI::vips()->new("VipsArgumentInstance*[1]");
        $result = \Jcupitt\Vips\FFI::vips()->vips_object_get_argument($this->pointer, $name, $pspec_array, $argument_class_array, $argument_instance_array);
        if ($result != 0) {
            $pspec = null;
        } else {
            /* php-ffi seems to leak if we do the obvious $pspec_array[0] to
             * get the return result ... instead, we must make a new pointer
             * object and copy the value ourselves
             *
             * the returns values from vips_object_get_argument() are static,
             * so this is safe
             */
            $pspec = \Jcupitt\Vips\FFI::gobject()->new("GParamSpec*");
            $to_pointer = \FFI::addr($pspec);
            $from_pointer = \FFI::addr($pspec_array);
            $size = \FFI::sizeof($from_pointer);
            \FFI::memcpy($to_pointer, $from_pointer, $size);
        }
        return $pspec;
    }
    // get the type of a property from a VipsObject
    // 0 if no such property
    public function getType(string $name): int
    {
        $pspec = $this->getPspec($name);
        if (\FFI::isNull($pspec)) {
            # need to clear any error, this is horrible
            \Jcupitt\Vips\FFI::vips()->vips_error_clear();
            return 0;
        } else {
            return $pspec->value_type;
        }
    }
    public function getBlurb(string $name): string
    {
        $pspec = $this->getPspec($name);
        return \Jcupitt\Vips\FFI::gobject()->g_param_spec_get_blurb($pspec);
    }
    public function getArgumentDescription(string $name): string
    {
        $pspec = $this->getPspec($name);
        return \Jcupitt\Vips\FFI::gobject()->g_param_spec_get_description($pspec);
    }
    /**
     * @throws Exception
     */
    public function get(string $name)
    {
        $name = str_replace("-", "_", $name);
        $gvalue = new \Jcupitt\Vips\GValue();
        $gvalue->setType($this->getType($name));
        \Jcupitt\Vips\FFI::gobject()->g_object_get_property($this->gObject, $name, $gvalue->pointer);
        $value = $gvalue->get();
        \Jcupitt\Vips\Utils::debugLog("get", [$name => var_export($value, \true)]);
        return $value;
    }
    /**
     * @throws Exception
     */
    public function set(string $name, $value): void
    {
        \Jcupitt\Vips\Utils::debugLog("set", [$name => $value]);
        $name = str_replace("-", "_", $name);
        $gvalue = new \Jcupitt\Vips\GValue();
        $gvalue->setType($this->getType($name));
        $gvalue->set($value);
        \Jcupitt\Vips\FFI::gobject()->g_object_set_property($this->gObject, $name, $gvalue->pointer);
    }
    public function setString(string $string_options): bool
    {
        $result = \Jcupitt\Vips\FFI::vips()->vips_object_set_from_string($this->pointer, $string_options);
        return $result == 0;
    }
    public function unrefOutputs(): void
    {
        \Jcupitt\Vips\FFI::vips()->vips_object_unref_outputs($this->pointer);
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
