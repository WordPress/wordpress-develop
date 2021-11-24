<?php

if ( is_multisite() ) :

	/**
	 * @ticket 50136
	 * @group ms-site
	 * @group multisite
	 */
	class Tests_Multisite_Upload_Mimes extends WP_UnitTestCase {

		public function test_file_extensions_are_allowed_on_full_match() {
			update_site_option( 'upload_filetypes', 'txt xf rt t' );

			$mimes = [
				'srt|txt|png' => 'foo',
				'tx'          => 'bar',
				't'           => 'baz',
			];

			$allowed_mimes = check_upload_mimes( $mimes );

			$this->assertTrue( isset( $allowed_mimes['srt|txt|png'] ) );
			$this->assertTrue( isset( $allowed_mimes['t'] ) );
			$this->assertFalse( isset( $allowed_mimes['tx'] ) );
			$this->assertFalse( isset( $allowed_mimes['rt'] ) );
			$this->assertFalse( isset( $allowed_mimes['xf'] ) );
		}
	}

endif;
