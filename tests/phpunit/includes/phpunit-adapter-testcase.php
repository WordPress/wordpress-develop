<?php

use Yoast\PHPUnitPolyfills\Helpers\AssertAttributeHelper;
use Yoast\PHPUnitPolyfills\Polyfills\AssertClosedResource;
use Yoast\PHPUnitPolyfills\Polyfills\AssertEqualsSpecializations;
use Yoast\PHPUnitPolyfills\Polyfills\AssertFileDirectory;
use Yoast\PHPUnitPolyfills\Polyfills\AssertFileEqualsSpecializations;
use Yoast\PHPUnitPolyfills\Polyfills\AssertionRenames;
use Yoast\PHPUnitPolyfills\Polyfills\AssertIsType;
use Yoast\PHPUnitPolyfills\Polyfills\AssertNumericType;
use Yoast\PHPUnitPolyfills\Polyfills\AssertObjectEquals;
use Yoast\PHPUnitPolyfills\Polyfills\AssertStringContains;
use Yoast\PHPUnitPolyfills\Polyfills\EqualToSpecializations;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectExceptionMessageMatches;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectExceptionObject;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

/**
 * PHPUnit adapter layer.
 *
 * This class enhances the PHPUnit native `TestCase` with polyfills
 * for assertions and expectation methods added between PHPUnit 4.8 - 9.5.
 *
 * See {@link https://github.com/Yoast/PHPUnit-Polyfills} for full
 * documentation on the available polyfills.
 */
abstract class PHPUnit_Adapter_TestCase extends PHPUnit\Framework\TestCase {

	use AssertAttributeHelper;
	use AssertClosedResource;
	use AssertEqualsSpecializations;
	use AssertFileDirectory;
	use AssertFileEqualsSpecializations;
	use AssertionRenames;
	use AssertIsType;
	use AssertNumericType;
	use AssertObjectEquals;
	use AssertStringContains;
	use EqualToSpecializations;
	use ExpectException;
	use ExpectExceptionMessageMatches;
	use ExpectExceptionObject;
	use ExpectPHPException;
}
