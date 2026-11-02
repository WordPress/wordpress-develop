<?php
/**
 * Plugin Name: Fixture: Fatal on load
 * Description: Calls an undefined function as soon as it is loaded. Expected to fail activation.
 * Version: 1.0.0
 */

fixture_function_that_does_not_exist();
