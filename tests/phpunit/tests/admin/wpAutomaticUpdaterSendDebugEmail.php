<?php

/**
 * Tests for WP_Automatic_Updater::send_debug_email().
 *
 * @group admin
 * @group upgrade
 * @group plugins
 *
 * @covers WP_Automatic_Updater::send_debug_email
 */
class Tests_Admin_WpAutomaticUpdaterSendDebugEmail extends WP_UnitTestCase {

	/**
	 * The arguments passed to wp_mail().
	 *
	 * @var array|null
	 */
	private $mail;

	/**
	 * Loads the automatic updater class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
	}

	/**
	 * Captures the email instead of sending it.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'blogname', 'Test Blog' );

		$this->mail = null;
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	/**
	 * Tests the email for successful updates of every type.
	 *
	 * @ticket 65819
	 */
	public function test_should_report_successful_updates() {
		$this->send(
			array(
				'core'        => array( $this->result( 'WordPress 9.9', true, array( 'Downloading update&#8230;', 'Update &amp; done' ) ) ),
				'plugin'      => array( $this->result( 'Plugin A', true ), $this->result( 'Plugin B', true ) ),
				'theme'       => array( $this->result( 'Theme A', true ) ),
				'translation' => array( $this->result( 'German', true ) ),
			)
		);

		$this->assertSame( get_site_option( 'admin_email' ), $this->mail['to'], 'The email should go to the network admin.' );
		$this->assertSame( '[Test Blog] Background Update Finished', $this->mail['subject'], 'The subject should report a finished update.' );
		$this->assertSame(
			implode(
				"\n",
				array(
					'WordPress site: http://' . WP_TESTS_DOMAIN . '/',
					'SUCCESS: WordPress was successfully updated to WordPress 9.9',
					'',
					'The following plugins were successfully updated:',
					' * SUCCESS: Plugin A',
					' * SUCCESS: Plugin B',
					'',
					'The following themes were successfully updated:',
					' * SUCCESS: Theme A',
					'',
					'The following translations were successfully updated:',
					' * SUCCESS: German',
					'',
					"UPDATE LOG\n==========",
					'',
					'WordPress 9.9',
					'-------------',
					'  Downloading update...',
					'  Update & done',
					'',
					'Plugin A',
					'--------',
					'',
					'Plugin B',
					'--------',
					'',
					'Theme A',
					'-------',
					'',
					'German',
					'------',
					'',
				)
			),
			$this->mail['message'],
			'The body should list each update.'
		);
	}

	/**
	 * Tests the email for failed updates.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_failed_core_results
	 *
	 * @param false|WP_Error $core_result The failed core update result.
	 */
	public function test_should_report_failed_updates( $core_result ) {
		$filter = new MockAction();
		add_filter( 'automatic_updates_debug_email', array( $filter, 'filter' ), 10, 3 );

		$update_results = array(
			'core'   => array( $this->result( 'WordPress 9.9', $core_result ) ),
			'plugin' => array( $this->result( 'Plugin A', true ), $this->result( 'Plugin B', false ), $this->result( 'Plugin C', null ) ),
		);

		$this->send( $update_results );

		$this->assertSame( '[Test Blog] Background Update Failed', $this->mail['subject'], 'The subject should report a failed update.' );
		$this->assertStringStartsWith(
			implode(
				"\n",
				array(
					'WordPress site: http://' . WP_TESTS_DOMAIN . '/',
					'FAILED: WordPress failed to update to WordPress 9.9',
					'',
					'The following plugins were successfully updated:',
					' * SUCCESS: Plugin A',
					'The following plugins failed to update:',
					' * FAILED: Plugin B',
					' * FAILED: Plugin C',
					'',
					'BETA TESTING?',
				)
			),
			$this->mail['message'],
			'The body should list the failures before the beta testing notice.'
		);

		$args = $filter->get_args();

		$this->assertSame( 3, $args[0][1], 'The filter should receive the number of failures.' );
		$this->assertSame( $update_results, $args[0][2], 'The filter should receive the update results.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_failed_core_results() {
		return array(
			'false result' => array( false ),
			'error result' => array( new WP_Error( 'core_update_failed', 'Core update failed.' ) ),
		);
	}

	/**
	 * Tests that only failed updates are listed when every update of a type failed.
	 *
	 * @ticket 65819
	 */
	public function test_should_only_list_failures_when_all_updates_of_a_type_failed() {
		$this->send( array( 'theme' => array( $this->result( 'Theme A', false ) ) ) );

		$this->assertStringStartsWith(
			'WordPress site: http://' . WP_TESTS_DOMAIN . "/\nThe following themes failed to update:\n * FAILED: Theme A\n\nBETA TESTING?",
			$this->mail['message']
		);
	}

	/**
	 * Tests that update errors and rollback errors are added to the update log.
	 *
	 * @ticket 65819
	 */
	public function test_should_log_update_and_rollback_errors() {
		$rollback_required = new WP_Error(
			'rollback_was_required',
			'Rollback was required.',
			array(
				'update'   => new WP_Error( 'copy_failed', 'Could not copy files.', array( 'a.php', 'b.php' ) ),
				'rollback' => new WP_Error( 'rollback_failed', 'Could not restore.' ),
				'other'    => 'Not an error.',
			)
		);

		$this->send(
			array(
				'plugin' => array(
					$this->result( 'Plugin A', new WP_Error( 'download_failed', 'Download failed.' ), array( 'Downloading&#8230;' ) ),
					$this->result( 'Plugin B', $rollback_required ),
				),
			)
		);

		$this->assertStringEndsWith(
			implode(
				"\n",
				array(
					"UPDATE LOG\n==========",
					'',
					'Plugin A',
					'--------',
					'  Downloading...',
					'  Error: [download_failed] Download failed.',
					'',
					'Plugin B',
					'--------',
					'  Error: [copy_failed] Could not copy files.',
					'         a.php, b.php',
					'  Rollback Error: [rollback_failed] Could not restore.',
					'',
				)
			),
			$this->mail['message']
		);
	}

	/**
	 * Tests that the site host is used in the subject without a site title.
	 *
	 * @ticket 65819
	 */
	public function test_should_use_host_without_site_title() {
		update_option( 'blogname', '' );
		update_option( 'home', 'https://example.com:8080/blog' );

		$this->send( array( 'plugin' => array( $this->result( 'Plugin A', true ) ) ) );

		$this->assertSame( '[example.com] Background Update Finished', $this->mail['subject'] );
	}

	/**
	 * Tests that the site title is decoded in the subject.
	 *
	 * @ticket 65819
	 */
	public function test_should_decode_site_title_in_subject() {
		update_option( 'blogname', 'Tom &amp; Jerry&#039;s' );

		$this->send( array( 'plugin' => array( $this->result( 'Plugin A', true ) ) ) );

		$this->assertSame( "[Tom & Jerry's] Background Update Finished", $this->mail['subject'] );
	}

	/**
	 * Tests that the email can be filtered.
	 *
	 * @ticket 65819
	 */
	public function test_should_send_filtered_email() {
		add_filter(
			'automatic_updates_debug_email',
			static function ( $email ) {
				$email['to']      = 'debug@example.org';
				$email['subject'] = 'Debug &amp; log';
				$email['body']    = 'Custom body';
				$email['headers'] = 'X-Debug: 1';

				return $email;
			}
		);

		$this->send( array( 'plugin' => array( $this->result( 'Plugin A', true ) ) ) );

		$this->assertSame(
			array(
				'to'      => 'debug@example.org',
				'subject' => 'Debug & log',
				'message' => 'Custom body',
				'headers' => 'X-Debug: 1',
			),
			array_intersect_key( $this->mail, array_flip( array( 'to', 'subject', 'message', 'headers' ) ) )
		);
	}

	/**
	 * Returns an update result.
	 *
	 * @param string $name     The item name.
	 * @param mixed  $result   The result.
	 * @param array  $messages The upgrader messages.
	 * @return object The update result.
	 */
	private function result( $name, $result, $messages = array() ) {
		return (object) array(
			'name'     => $name,
			'result'   => $result,
			'messages' => $messages,
		);
	}

	/**
	 * Sends the debug email for the given update results.
	 *
	 * @param array $update_results The update results.
	 */
	private function send( $update_results ) {
		$updater = new WP_Automatic_Updater();

		$property = new ReflectionProperty( $updater, 'update_results' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $updater, $update_results );

		$method = new ReflectionMethod( $updater, 'send_debug_email' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$method->invoke( $updater );
	}

	/**
	 * Captures the arguments passed to wp_mail() and stops the email.
	 *
	 * @param null|bool $short_circuit Short-circuit return value.
	 * @param array     $atts   The wp_mail() arguments.
	 * @return bool False, so that no email is sent.
	 */
	public function capture_mail( $short_circuit, $atts ) {
		$this->mail = $atts;

		return false;
	}
}
