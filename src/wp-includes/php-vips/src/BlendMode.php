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
 * The BlendMode enum.
 * @category  Images
 * @package   Jcupitt\Vips
 * @author    John Cupitt <jcupitt@gmail.com>
 * @copyright 2016 John Cupitt
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/jcupitt/php-vips
 */
abstract class BlendMode
{
    const CLEAR = 'clear';
    const SOURCE = 'source';
    const OVER = 'over';
    const IN = 'in';
    const OUT = 'out';
    const ATOP = 'atop';
    const DEST = 'dest';
    const DEST_OVER = 'dest-over';
    const DEST_IN = 'dest-in';
    const DEST_OUT = 'dest-out';
    const DEST_ATOP = 'dest-atop';
    const XOR1 = 'xor';
    const ADD = 'add';
    const SATURATE = 'saturate';
    const MULTIPLY = 'multiply';
    const SCREEN = 'screen';
    const OVERLAY = 'overlay';
    const DARKEN = 'darken';
    const LIGHTEN = 'lighten';
    const COLOUR_DODGE = 'colour-dodge';
    const COLOUR_BURN = 'colour-burn';
    const HARD_LIGHT = 'hard-light';
    const SOFT_LIGHT = 'soft-light';
    const DIFFERENCE = 'difference';
    const EXCLUSION = 'exclusion';
}
