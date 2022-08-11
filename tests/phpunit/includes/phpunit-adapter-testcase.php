<?php

use Yoast\PHPUnitPolyfills\TestCases\TestCase as Polyfill_TestCase;

/**
 * PHPUnit adapter layer.
 *
 * This class enhances the PHPUnit native `TestCase` with polyfills
 * for assertions and expectation methods added between PHPUnit 4.8 - 9.5.
 *
 * Additionally, the Polyfill TestCase offers a workaround for the addition
 * of the `void` return type to PHPUnit fixture methods by providing
 * overloadable snake_case versions of the typical fixture method names and
 * ensuring that PHPUnit handles those correctly.
 *
 * See {@link https://github.com/Yoast/PHPUnit-Polyfills} for full
 * documentation on the available polyfills and other features.
 */
abstract class PHPUnit_Adapter_TestCase extends Polyfill_TestCase {}
