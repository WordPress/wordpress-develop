<?php

if ( is_multisite() ) :

	/**
	 * @group multisite
	 */
	class Tests_Multisite_wpmuLogNewRegistrations extends WP_UnitTestCase {

		public function test_wpmu_log_new_registrations() {
			global $wpdb;

			$user = new WP_User( 1 );
			$ip   = preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] );

			wpmu_log_new_registrations( 1, 1 );

			// Currently there is no wrapper function for the registration_log.
			$reg_blog = $wpdb->get_col( $wpdb->prepare( "SELECT email FROM {$wpdb->registration_log} WHERE {$wpdb->registration_log}.blog_id = 1 AND IP LIKE %s", $ip ) );
			$this->assertSame( $user->user_email, $reg_blog[ count( $reg_blog ) - 1 ] );
		}
	}

endif;
