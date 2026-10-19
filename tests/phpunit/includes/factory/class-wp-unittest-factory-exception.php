<?php

/**
 * Exception thrown when a factory method fails to create, update, or retrieve an object.
 *
 * Factory methods used to return a WP_Error object on failure. Because the vast
 * majority of callers expect an object ID or an object, returning a WP_Error only
 * moved the failure further down the test, where it surfaced as a confusing
 * assertion failure. Throwing an exception instead stops the test at the point
 * where the fixture could not be created.
 *
 * @since 7.2.0
 */
class WP_UnitTest_Factory_Exception extends Exception {}
