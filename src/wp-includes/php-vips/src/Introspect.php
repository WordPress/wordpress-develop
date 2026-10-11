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
 * Introspect a VipsOperation and discover everything we can. This is called
 * on demand once per operation and the results held in a cache.
 *
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/libvips/php-vips
 */
class Introspect
{
    /**
     * The operation nickname (eg. "add").
     */
    public string $name;
    /**
     * The operation description (e.g. "add two images").
     */
    public string $description;
    /**
     * The operation flags (e.g. SEQUENTIAL | DEPRECATED).
     */
    public int $flags;
    /**
     * A hash from arg name to a hash of details.
     */
    public array $arguments;
    /**
     * Arrays of arg names, in order and by category, eg. $this->required_input
     * = ["filename"].
     */
    public array $required_input;
    public array $optional_input;
    public array $required_output;
    public array $optional_output;
    /**
     * The name of the arg this operation uses as "this".
     */
    public string $member_this;
    /**
     * And the required input args, without the "this".
     */
    public array $method_args;
    /**
     * @throws Exception
     */
    public function __construct($operation_name)
    {
        $this->name = $operation_name;
        $operation = \Jcupitt\Vips\VipsOperation::newFromName($operation_name);
        $this->description = $operation->getDescription();
        $p_names = \Jcupitt\Vips\FFI::vips()->new("char**[1]");
        $p_flags = \Jcupitt\Vips\FFI::vips()->new("int*[1]");
        $p_n_args = \Jcupitt\Vips\FFI::vips()->new("int[1]");
        $result = \Jcupitt\Vips\FFI::vips()->vips_object_get_args(\Jcupitt\Vips\FFI::vips()->cast(\Jcupitt\Vips\FFI::ctypes("VipsObject"), $operation->pointer), $p_names, $p_flags, $p_n_args);
        if ($result != 0) {
            throw new \Jcupitt\Vips\Exception();
        }
        $p_names = $p_names[0];
        $p_flags = $p_flags[0];
        $n_args = $p_n_args[0];
        # make a hash from arg name to flags
        $argumentFlags = [];
        for ($i = 0; $i < $n_args; $i++) {
            if (($p_flags[$i] & \Jcupitt\Vips\ArgumentFlags::CONSTRUCT) != 0) {
                # make sure we're using "_" to separate arg components, though
                # I think libvips is "_" everywhere now
                $name = \FFI::string($p_names[$i]);
                $name = str_replace("-", "_", $name);
                $argumentFlags[$name] = $p_flags[$i];
            }
        }
        # make a hash from arg name to detailed arg info
        $this->arguments = [];
        foreach ($argumentFlags as $name => $flags) {
            $this->arguments[$name] = ["name" => $name, "flags" => $flags, "blurb" => $operation->getBlurb($name), "type" => $operation->getType($name)];
        }
        # split args into categories
        $this->required_input = [];
        $this->optional_input = [];
        $this->required_output = [];
        $this->optional_output = [];
        foreach ($this->arguments as $name => $details) {
            $flags = $details["flags"];
            if ($flags & \Jcupitt\Vips\ArgumentFlags::INPUT && $flags & \Jcupitt\Vips\ArgumentFlags::REQUIRED && !($flags & \Jcupitt\Vips\ArgumentFlags::DEPRECATED)) {
                $this->required_input[] = $name;
                # required inputs which we MODIFY are also required outputs
                if ($flags & \Jcupitt\Vips\ArgumentFlags::MODIFY) {
                    $this->required_output[] = $name;
                }
            }
            if ($flags & \Jcupitt\Vips\ArgumentFlags::OUTPUT && $flags & \Jcupitt\Vips\ArgumentFlags::REQUIRED && !($flags & \Jcupitt\Vips\ArgumentFlags::DEPRECATED)) {
                $this->required_output[] = $name;
            }
            # we let deprecated optional args through, but warn about them
            # if they get used, see below
            if ($flags & \Jcupitt\Vips\ArgumentFlags::INPUT && !($flags & \Jcupitt\Vips\ArgumentFlags::REQUIRED)) {
                $this->optional_input[] = $name;
            }
            if ($flags & \Jcupitt\Vips\ArgumentFlags::OUTPUT && !($flags & \Jcupitt\Vips\ArgumentFlags::REQUIRED)) {
                $this->optional_output[] = $name;
            }
        }
        # find the first required input image arg, if any ... that will be self
        $this->member_this = "";
        foreach ($this->required_input as $name) {
            $type = $this->arguments[$name]["type"];
            if ($type == \Jcupitt\Vips\FFI::gtypes("VipsImage")) {
                $this->member_this = $name;
                break;
            }
        }
        # method args are required args, but without the image they are a
        # method on
        $this->method_args = $this->required_input;
        if ($this->member_this != "") {
            $index = array_search($this->member_this, $this->method_args);
            array_splice($this->method_args, $index);
        }
        \Jcupitt\Vips\Utils::debugLog($operation_name, ['introspect' => strval($this)]);
    }
    public function __toString(): string
    {
        $result = "{$this->name}:\n";
        foreach ($this->arguments as $name => $details) {
            $flags = $details["flags"];
            $blurb = $details["blurb"];
            $type = $details["type"];
            $typeName = \Jcupitt\Vips\FFI::gobject()->g_type_name($type);
            $result .= "  {$name}:\n";
            $result .= "    flags: {$flags}\n";
            foreach (\Jcupitt\Vips\ArgumentFlags::NAMES as $flag_name => $flag) {
                if ($flags & $flag) {
                    $result .= "      {$flag_name}\n";
                }
            }
            $result .= "    blurb: {$blurb}\n";
            $result .= "    type: {$typeName}\n";
        }
        $info = implode(", ", $this->required_input);
        $result .= "required input: {$info}\n";
        $info = implode(", ", $this->required_output);
        $result .= "required output: {$info}\n";
        $info = implode(", ", $this->optional_input);
        $result .= "optional input: {$info}\n";
        $info = implode(", ", $this->optional_output);
        $result .= "optional output: {$info}\n";
        $result .= "member_this: {$this->member_this}\n";
        $info = implode(", ", $this->method_args);
        $result .= "method args: {$info}\n";
        return $result;
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
