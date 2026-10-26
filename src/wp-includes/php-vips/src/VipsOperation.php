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
 * This class holds a pointer to a VipsOperation (the libvips operation base
 * class) and manages argument introspection and operation call.
 *
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/libvips/php-vips
 */
class VipsOperation extends \Jcupitt\Vips\VipsObject
{
    /**
     * A pointer to the underlying VipsOperation. This is the same as the
     * GObject, just cast to VipsOperation to help FFI.
     *
     * @internal
     */
    public \FFI\CData $pointer;
    /**
     * Introspection data for this operation.
     */
    public \Jcupitt\Vips\Introspect $introspect;
    public function __construct(\FFI\CData $pointer)
    {
        $this->pointer = \Jcupitt\Vips\FFI::vips()->cast(\Jcupitt\Vips\FFI::ctypes("VipsOperation"), $pointer);
        parent::__construct($pointer);
    }
    /**
     * @throws Exception
     */
    public static function newFromName($name): \Jcupitt\Vips\VipsOperation
    {
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_operation_new($name);
        if ($pointer == null) {
            throw new \Jcupitt\Vips\Exception();
        }
        $operation = new \Jcupitt\Vips\VipsOperation($pointer);
        return $operation;
    }
    public function setMatch($name, $match_image, $value): void
    {
        $flags = $this->introspect->arguments[$name]["flags"];
        $gtype = $this->introspect->arguments[$name]["type"];
        if ($match_image != null) {
            if ($gtype == \Jcupitt\Vips\FFI::gtypes("VipsImage")) {
                $value = $match_image->imageize($value);
            } elseif ($gtype == \Jcupitt\Vips\FFI::gtypes("VipsArrayImage") && is_array($value)) {
                $new_value = [];
                foreach ($value as $x) {
                    $new_value[] = $match_image->imageize($x);
                }
                $value = $new_value;
            }
        }
        # MODIFY args need to be copied before they are set
        if (($flags & \Jcupitt\Vips\ArgumentFlags::MODIFY) != 0) {
            # logger.debug('copying MODIFY arg %s', name)
            # make sure we have a unique copy
            $value = $value->copyMemory();
        }
        parent::set($name, $value);
    }
    private static function introspect($name): \Jcupitt\Vips\Introspect
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $cache[$name] = new \Jcupitt\Vips\Introspect($name);
        }
        return $cache[$name];
    }
    private static function findInside($predicate, $x)
    {
        if ($predicate($x)) {
            return $x;
        }
        if (is_array($x)) {
            foreach ($x as $y) {
                $result = self::findInside($predicate, $y);
                if ($result != null) {
                    return $result;
                }
            }
        }
        return null;
    }
    /**
     * Is $value a VipsImage.
     *
     * @param mixed $value The thing to test.
     *
     * @return bool true if this is a ffi VipsImage*.
     *
     * @internal
     */
    private static function isImagePointer($value): bool
    {
        return $value instanceof \FFI\CData && \FFI::typeof($value) == \Jcupitt\Vips\FFI::ctypes("VipsImage");
    }
    /**
     * Wrap up the result of a vips_ call ready to return it to PHP. We do
     * two things:
     *
     * - If the array is a singleton, we strip it off. For example, many
     *   operations return a single result and there's no sense handling
     *   this as an array of values, so we transform ['out' => x] -> x.
     *
     * - Any VipsImage resources are rewrapped as instances of Image.
     *
     * @param mixed $result Wrap this up.
     *
     * @return mixed $result, but wrapped up as a php class.
     *
     * @internal
     */
    private static function wrapResult($result)
    {
        if (!is_array($result)) {
            $result = ['x' => $result];
        }
        array_walk_recursive($result, function (&$item) {
            if (self::isImagePointer($item)) {
                $item = new \Jcupitt\Vips\Image($item);
            }
        });
        if (count($result) === 1) {
            $result = array_shift($result);
        }
        return $result;
    }
    /**
     * Call any vips operation. The final element of $arguments can be
     * (but doesn't have to be) an array of options to pass to the operation.
     *
     * We can't have a separate arg for the options since this will be run from
     * __call(), which cannot know which args are required and which are
     * optional. See call() below for a version with the options broken out.
     *
     * @param string     $operation_name      The operation name.
     * @param Image|null $instance  The instance this operation is being invoked
     *      from.
     * @param array      $arguments An array of arguments to pass to the
     *      operation.
     *
     * @throws Exception
     *
     * @return mixed The result(s) of the operation.
     */
    public static function callBase(string $operation_name, ?\Jcupitt\Vips\Image $instance, array $arguments)
    {
        \Jcupitt\Vips\Utils::debugLog($operation_name, ['instance' => $instance, 'arguments' => $arguments]);
        $operation = self::newFromName($operation_name);
        $operation->introspect = self::introspect($operation_name);
        $introspect = $operation->introspect;
        /* the first image argument is the thing we expand constants to
         * match ... look inside tables for images, since we may be passing
         * an array of images as a single param.
         */
        if ($instance != null) {
            $match_image = $instance;
        } else {
            $match_image = self::findInside(fn($x) => $x instanceof \Jcupitt\Vips\Image, $arguments);
        }
        /* Because of the way php callStatic works, we can sometimes be given
         * an instance even when no instance was given.
         *
         * We must loop over the required args and set them from the supplied
         * args, using instance if required, and only check the nargs after
         * this pass.
         */
        $n_required = count($introspect->required_input);
        $n_supplied = count($arguments);
        $n_used = 0;
        foreach ($introspect->required_input as $name) {
            if ($name == $introspect->member_this) {
                if (!$instance) {
                    $operation->unrefOutputs();
                    throw new \Jcupitt\Vips\Exception("instance argument not supplied");
                }
                $operation->setMatch($name, $match_image, $instance);
            } elseif ($n_used < $n_supplied) {
                $operation->setMatch($name, $match_image, $arguments[$n_used]);
                $n_used += 1;
            } else {
                $operation->unrefOutputs();
                throw new \Jcupitt\Vips\Exception("{$n_required} arguments required, " . "but {$n_supplied} supplied");
            }
        }
        /* If there's one extra arg and it's an array, use it as our options.
         */
        $options = [];
        if ($n_supplied == $n_used + 1 && is_array($arguments[$n_used])) {
            $options = array_pop($arguments);
            $n_supplied -= 1;
        }
        if ($n_supplied != $n_used) {
            $operation->unrefOutputs();
            throw new \Jcupitt\Vips\Exception("{$n_required} arguments required, " . "but {$n_supplied} supplied");
        }
        /* set any string options before any args so they can't be
         * overridden.
         */
        if (array_key_exists("string_options", $options)) {
            $string_options = $options["string_options"];
            unset($options["string_options"]);
            $operation->setString($string_options);
        }
        /* Set optional.
         */
        foreach ($options as $name => $value) {
            $name = str_replace("-", "_", $name);
            if (!in_array($name, $introspect->optional_input) && !in_array($name, $introspect->optional_output)) {
                $operation->unrefOutputs();
                throw new \Jcupitt\Vips\Exception("optional argument '{$name}' does not exist");
            }
            $operation->setMatch($name, $match_image, $value);
        }
        /* Build the operation
         */
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_cache_operation_build($operation->pointer);
        if ($pointer == null) {
            $operation->unrefOutputs();
            throw new \Jcupitt\Vips\Exception();
        }
        $operation = new \Jcupitt\Vips\VipsOperation($pointer);
        $operation->introspect = $introspect;
        # TODO .. need to attach input refs to output, see _find_inside in
        # pyvips
        /* Fetch required output args (and modified input args).
         */
        $result = [];
        foreach ($introspect->required_output as $name) {
            $result[$name] = $operation->get($name);
        }
        /* Any optional output args.
         */
        $option_keys = array_keys($options);
        foreach ($introspect->optional_output as $name) {
            $name = str_replace("-", "_", $name);
            if (in_array($name, $option_keys)) {
                $result[$name] = $operation->get($name);
            }
        }
        /* Free any outputs we've not used.
         */
        $operation->unrefOutputs();
        $result = self::wrapResult($result);
        \Jcupitt\Vips\Utils::debugLog($operation_name, ['result' => var_export($result, \true)]);
        return $result;
    }
    /**
     * Call any vips operation, with an explicit set of options. This is more
     * convenient than callBase() if you have a set of known options.
     *
     * @param string     $name      The operation name.
     * @param Image|null $instance  The instance this operation is being invoked
     *      from.
     * @param array      $arguments An array of arguments to pass to the
     *      operation.
     * @param array      $options   An array of optional arguments to pass to
     *      the operation.
     *
     * @throws Exception
     *
     * @return mixed The result(s) of the operation.
     */
    public static function call(string $name, ?\Jcupitt\Vips\Image $instance, array $arguments, array $options = [])
    {
        return self::callBase($name, $instance, array_merge($arguments, [$options]));
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
