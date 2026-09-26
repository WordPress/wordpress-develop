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
 * @link      https://github.com/jcupitt/php-vips
 */
namespace Jcupitt\Vips;

/**
 * Various utilities.
 *
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/jcupitt/php-vips
 */
class Utils
{
    /**
     * Log a debug message.
     *
     * @param string $name      The method creating the messages.
     * @param array  $arguments The method arguments.
     *
     * @return void
     */
    public static function debugLog(string $name, array $arguments): void
    {
        $logger = \Jcupitt\Vips\Config::getLogger();
        if ($logger) {
            $logger->debug($name, $arguments);
        }
    }
    /**
     * Log an error message.
     *
     * @param string          $message   The error message.
     * @param \Exception|null $exception The exception, if any.
     *
     * @return void
     */
    public static function errorLog(string $message, ?\Exception $exception = null): void
    {
        $logger = \Jcupitt\Vips\Config::getLogger();
        if ($logger) {
            $logger->error($message, $exception == null ? [] : ['exception' => $exception]);
        }
    }
    /**
     * Look up the GTyoe from a type name. If the type does not exist,
     * return 0.
     *
     * @param string $name The type name.
     *
     * @return int
     */
    public static function typeFromName(string $name): int
    {
        return \Jcupitt\Vips\FFI::gobject()->g_type_from_name($name);
    }
    public static function filenameGetFilename(string $name): string
    {
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_filename_get_filename($name);
        $filename = \FFI::string($pointer);
        \Jcupitt\Vips\FFI::glib()->g_free($pointer);
        return $filename;
    }
    public static function filenameGetOptions(string $name): string
    {
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_filename_get_options($name);
        $options = \FFI::string($pointer);
        \Jcupitt\Vips\FFI::glib()->g_free($pointer);
        return $options;
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
