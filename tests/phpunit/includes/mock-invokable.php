<?php
/**
 * File for Mock_Invokable class.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Class Mock_Invokable.
 *
 * This class is used to mock a class that has an `__invoke` method.
 */
class Mock_Invokable {

	public function __invoke() {}
}
