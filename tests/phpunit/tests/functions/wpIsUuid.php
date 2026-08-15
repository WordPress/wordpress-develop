<?php

/**
 * @group functions
 *
 * @covers ::wp_is_uuid
 */
class Tests_Functions_WpIsUuid extends WP_UnitTestCase {
	/**
	 * Tests wp_is_uuid().
	 *
	 * @covers ::wp_is_uuid
	 * @ticket 39778
	 */
	public function test_wp_is_uuid() {
		$uuids_v4 = array(
			'27fe2421-780c-44c5-b39b-fff753092b55',
			'b7c7713a-4ee9-45a1-87ed-944a90390fc7',
			'fbedbe35-7bf5-49cc-a5ac-0343bd94360a',
			'4c58e67e-123b-4290-a41c-5eeb6970fa3e',
			'f54f5b78-e414-4637-84a9-a6cdc94a1beb',
			'd1c533ac-abcf-44b6-9b0e-6477d2c91b09',
			'7fcd683f-e5fd-454a-a8b9-ed15068830da',
			'7962c750-e58c-470a-af0d-ec1eae453ff2',
			'a59878ce-9a67-4493-8ca0-a756b52804b3',
			'6faa519d-1e13-4415-bd6f-905ae3689d1d',
		);

		foreach ( $uuids_v4 as $uuid ) {
			$this->assertTrue( wp_is_uuid( $uuid, 4 ) );
		}

		$uuids = array(
			'00000000-0000-0000-0000-000000000000', // Nil.
			'9e3a0460-d72d-11e4-a631-c8e0eb141dab', // Version 1.
			'2c1d43b8-e6d7-376e-af7f-d4bde997cc3f', // Version 3.
			'39888f87-fb62-5988-a425-b2ea63f5b81e', // Version 5.
		);

		foreach ( $uuids as $uuid ) {
			$this->assertTrue( wp_is_uuid( $uuid ) );
			$this->assertFalse( wp_is_uuid( $uuid, 4 ) );
		}

		$invalid_uuids = array(
			'a19d5192-ea41-41e6-b006',
			'this-is-not-valid',
			1234,
			true,
			array(),
		);

		foreach ( $invalid_uuids as $invalid_uuid ) {
			$this->assertFalse( wp_is_uuid( $invalid_uuid, 4 ) );
			$this->assertFalse( wp_is_uuid( $invalid_uuid ) );
		}
	}
}
