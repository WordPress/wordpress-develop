<?php

/**
 * Tests for rest_is_boolean().
 *
 * Schema sanitization/validation already covers boolean-like acceptance paths
 * and common string/int rejections. This class only checks a remaining gap:
 * non-bool/string/int values hit the final rejection branch.
 *
 * @group restapi
 *
 * @covers ::rest_is_boolean
 */
class Tests_REST_API_RestSanitizeBoolean extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_rest_is_boolean_rejects_non_scalar_values() {
		$this->assertFalse( rest_is_boolean( null ) );
	}
}
