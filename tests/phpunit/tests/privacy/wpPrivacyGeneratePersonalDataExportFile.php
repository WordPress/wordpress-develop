<?php
/**
 * Define a class to test `wp_privacy_generate_personal_data_export_file()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.2.0
 */

/**
 * Test cases for `wp_privacy_generate_personal_data_export_file()`.
 *
 * @group privacy
 * @covers ::wp_privacy_generate_personal_data_export_file
 *
 * @since 5.2.0
 */
class Tests_Privacy_WpPrivacyGeneratePersonalDataExportFile extends WP_UnitTestCase {
	/**
	 * An Export Request ID
	 *
	 * @since 5.2.0
	 *
	 * @var int $export_request_id
	 */
	protected static $export_request_id;

	/**
	 * The full path to the export file for the current test method.
	 *
	 * @since 5.2.0
	 *
	 * @var string $export_file_name
	 */
	public $export_file_name = '';

	/**
	 * The full path to the exports directory.
	 *
	 * @since 5.2.0
	 *
	 * @var string $exports_dir
	 */
	public static $exports_dir;

	/**
	 * Create fixtures that are shared by multiple test cases.
	 *
	 * @since 5.2.0
	 *
	 * @param WP_UnitTest_Factory $factory The base factory object.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$export_request_id = wp_create_user_request( 'export-requester@example.com', 'export_personal_data' );
		update_post_meta( self::$export_request_id, '_export_data_grouped', array() );
		self::$exports_dir = wp_privacy_exports_dir();
	}

	/**
	 * Set up the test fixture.
	 *
	 * Override `wp_die()`, pretend to be Ajax, and suppress `E_WARNING`s.
	 *
	 * @since 5.2.0
	 */
	public function setUp() {
		parent::setUp();

		$this->export_file_name = '';

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'The ZipArchive class is missing.' );
		}

		if ( ! $this->remove_exports_dir() ) {
			$this->markTestSkipped( 'Existing exports directory could not be removed. Skipping test.' );
		}

		// We need to override the die handler. Otherwise, the unit tests will die too.
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ), 1, 1 );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_action( 'wp_privacy_personal_data_export_file_created', array( $this, 'action_wp_privacy_personal_data_export_file_created' ) );

		// Suppress warnings from "Cannot modify header information - headers already sent by".
		$this->_error_level = error_reporting();
		error_reporting( $this->_error_level & ~E_WARNING );
	}

	/**
	 * Tear down the test fixture.
	 *
	 * Remove the `wp_die()` override, restore error reporting.
	 *
	 * @since 5.2.0
	 */
	public function tearDown() {
		$this->remove_exports_dir();
		error_reporting( $this->_error_level );
		parent::tearDown();
	}

	/**
	 * Stores the name of the export zip file to check the file is actually created.
	 *
	 * @since 5.2.0
	 *
	 * @param string $archive_name Created export zip file path.
	 */
	public function action_wp_privacy_personal_data_export_file_created( $archive_name ) {
		$this->export_file_name = $archive_name;
	}

	/**
	 * Removes the privacy exports directory, including files and subdirectories.
	 *
	 * Ignores hidden files and has upper limit of nested levels, because of `list_files()`.
	 *
	 * @since 5.2.0
	 *
	 * @return bool Whether the privacy exports directory was removed.
	 */
	private function remove_exports_dir() {
		/**
		 * The `$exports_dir` will be a file after the `test_detect_cannot_create_folder()` test method, or,
		 * if an incorrect value is returned to the `wp_privacy_exports_dir` filter.
		 */
		if ( is_file( untrailingslashit( self::$exports_dir ) ) ) {
			wp_delete_file( untrailingslashit( self::$exports_dir ) );
			return ! is_file( untrailingslashit( self::$exports_dir ) );
		}

		if ( ! is_dir( self::$exports_dir ) ) {
			return true;
		}

		chmod( self::$exports_dir, 0755 );

		$files = list_files( self::$exports_dir );

		// Delete files first, then delete subdirectories.
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		foreach ( $files as $file ) {
			if ( is_dir( $file ) ) {
				rmdir( $file );
			}
		}

		rmdir( self::$exports_dir );

		return ! is_dir( self::$exports_dir );
	}

	/**
	 * When a remove request ID is passed to the export function an error should be displayed.
	 *
	 * @ticket 44233
	 */
	public function test_rejects_remove_requests() {
		$request_id = wp_create_user_request( 'removal-requester@example.com', 'remove_personal_data' );

		$this->setExpectedException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Invalid request ID when generating export file."}' );
		wp_privacy_generate_personal_data_export_file( $request_id );
	}

	/**
	 * When an invalid request ID is passed an error should be displayed.
	 *
	 * @ticket 44233
	 */
	public function test_invalid_request_id() {
		$this->setExpectedException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Invalid request ID when generating export file."}' );
		wp_privacy_generate_personal_data_export_file( 123456789 );
	}

	/**
	 * When the request post title is not a valid email an error should be displayed.
	 *
	 * @ticket 44233
	 */
	public function test_rejects_requests_with_bad_email_addresses() {
		$request_id = wp_create_user_request( 'bad-email-requester@example.com', 'export_personal_data' );

		wp_update_post(
			array(
				'ID'         => $request_id,
				'post_title' => 'not-a-valid-email-address',
			)
		);

		$this->setExpectedException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Invalid email address when generating export file."}' );
		wp_privacy_generate_personal_data_export_file( $request_id );
	}

	/**
	 * When the export directory fails to be created an error should be displayed.
	 *
	 * @ticket 44233
	 */
	public function test_detect_cannot_create_folder() {
		// Create a file with the folder name to ensure the function cannot create a folder.
		touch( untrailingslashit( self::$exports_dir ) );

		$this->setExpectedException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Unable to create export folder."}' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
	}

	/**
	 * Test that an index.html file can be added to the export directory.
	 *
	 * @ticket 44233
	 */
	public function test_creates_index_in_export_folder() {
		$this->expectOutputString( '' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );

		$this->assertTrue( file_exists( self::$exports_dir . 'index.html' ) );
	}

	/**
	 * Test that an export file is successfully created.
	 *
	 * @ticket 44233
	 */
	public function test_can_succeed() {
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );

		$this->assertTrue( file_exists( $this->export_file_name ) );
	}

	/**
	 * Test the export HTML file has all the expected parts.
	 *
	 * @ticket 44233
	 */
	public function test_html_contents() {
		$this->expectOutputString( '' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
		$this->assertTrue( file_exists( $this->export_file_name ) );

		$report_dir = trailingslashit( self::$exports_dir . 'test_contents' );
		mkdir( $report_dir );

		$zip        = new ZipArchive();
		$opened_zip = $zip->open( $this->export_file_name );
		$this->assertTrue( $opened_zip );

		$zip->extractTo( $report_dir );
		$zip->close();
		$this->assertTrue( file_exists( $report_dir . 'index.html' ) );

		$report_contents = file_get_contents( $report_dir . 'index.html' );
		$request         = wp_get_user_request( self::$export_request_id );

		$this->assertContains( '<h1 id="top">Personal Data Export</h1>', $report_contents );
		$this->assertContains( '<h2 id="about-about">About</h2>', $report_contents );
		$this->assertContains( $request->email, $report_contents );
	}

	/**
	 * Test the export JSON file has all the expected parts.
	 *
	 * @ticket 49029
	 */
	public function test_json_contents() {
		$this->expectOutputString( '' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
		$this->assertTrue( file_exists( $this->export_file_name ) );

		$report_dir = trailingslashit( self::$exports_dir . 'test_contents' );
		mkdir( $report_dir );

		$zip        = new ZipArchive();
		$opened_zip = $zip->open( $this->export_file_name );
		$this->assertTrue( $opened_zip );

		$zip->extractTo( $report_dir );
		$zip->close();

		$request = wp_get_user_request( self::$export_request_id );

		$this->assertTrue( file_exists( $report_dir . 'export.json' ) );

		$report_contents_json = file_get_contents( $report_dir . 'export.json' );

		$this->assertContains( '"Personal Data Export for ' . $request->email . '"', $report_contents_json );
		$this->assertContains( '"about"', $report_contents_json );
	}

	/**
	 * Test the export HTML file containing one export group has no table of contents.
	 *
	 * @ticket 46894
	 */
	public function test_single_group_export_no_toc_or_return_to_top() {
		$this->expectOutputString( '' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
		$this->assertTrue( file_exists( $this->export_file_name ) );

		$report_dir = trailingslashit( self::$exports_dir . 'test_contents' );
		mkdir( $report_dir );

		$zip        = new ZipArchive();
		$opened_zip = $zip->open( $this->export_file_name );
		$this->assertTrue( $opened_zip );

		$zip->extractTo( $report_dir );
		$zip->close();
		$this->assertTrue( file_exists( $report_dir . 'index.html' ) );

		$report_contents = file_get_contents( $report_dir . 'index.html' );
		$request         = wp_get_user_request( self::$export_request_id );

		$this->assertNotContains( '<div id="table_of_contents">', $report_contents );
		$this->assertNotContains( '<div class="return_to_top">', $report_contents );
		$this->assertContains( $request->email, $report_contents );
	}

	/**
	 * Test the export HTML file containing ore than one export group has a table of contents.
	 *
	 * @ticket 46894
	 */
	public function test_multiple_group_export_has_toc_and_return_to_top() {
		$this->expectOutputString( '' );

		// Setup Export Data to contain multiple groups
		$export_data_grouped = array(
			'user' => array(
				'group_label'       => 'User',
				'group_description' => 'User&#8217;s profile data.',
				'items'             => array(
					'user-1' => array(
						array(
							'name'  => 'User ID',
							'value' => 1,
						),
						array(
							'name'  => 'User Login Name',
							'value' => 'user_login',
						),
						array(
							'name'  => 'User Nice Name',
							'value' => 'User Name',
						),
						array(
							'name'  => 'User Email',
							'value' => 'export-requester@example.com',
						),
						array(
							'name'  => 'User Registration Date',
							'value' => '2020-01-31 19:29:29',
						),
						array(
							'name'  => 'User Display Name',
							'value' => 'User Name',
						),
						array(
							'name'  => 'User Nickname',
							'value' => 'User',
						),
					),
				),
			),
		);
		update_post_meta( self::$export_request_id, '_export_data_grouped', $export_data_grouped );

		// Generate Export File
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
		$this->assertTrue( file_exists( $this->export_file_name ) );

		// Cleam-up for subsequent tests
		update_post_meta( self::$export_request_id, '_export_data_grouped', array() );

		$report_dir = trailingslashit( self::$exports_dir . 'test_contents' );
		mkdir( $report_dir );

		$zip        = new ZipArchive();
		$opened_zip = $zip->open( $this->export_file_name );
		$this->assertTrue( $opened_zip );

		$zip->extractTo( $report_dir );
		$zip->close();
		$this->assertTrue( file_exists( $report_dir . 'index.html' ) );

		$report_contents = file_get_contents( $report_dir . 'index.html' );
		$request         = wp_get_user_request( self::$export_request_id );

		$this->assertContains( '<div id="table_of_contents">', $report_contents );
		$this->assertContains( '<h2 id="user-user">User</h2>', $report_contents );
		$this->assertContains( '<div class="return_to_top">', $report_contents );
		$this->assertContains( $request->email, $report_contents );
	}

	/**
	 * Test the export HTML file containing multiple export groups with multiple group items
	 * has a table of contents with group count.
	 *
	 * @ticket 46894
	 */
	public function test_multiple_group_export_multiple_items_group_count_in_toc() {
		$this->expectOutputString( '' );

		// Setup Export Data to contain multiple groups
		$export_data_grouped = array(
			'user'     => array(
				'group_label'       => 'User',
				'group_description' => 'User&#8217;s profile data.',
				'items'             => array(
					'user-1' => array(
						array(
							'name'  => 'User ID',
							'value' => 1,
						),
						array(
							'name'  => 'User Login Name',
							'value' => 'user_login',
						),
						array(
							'name'  => 'User Nice Name',
							'value' => 'User Name',
						),
						array(
							'name'  => 'User Email',
							'value' => 'export-requester@example.com',
						),
						array(
							'name'  => 'User Registration Date',
							'value' => '2020-01-31 19:29:29',
						),
						array(
							'name'  => 'User Display Name',
							'value' => 'User Name',
						),
						array(
							'name'  => 'User Nickname',
							'value' => 'User',
						),
					),
				),
			),
			'comments' => array(
				'group_label'       => 'Comments',
				'group_description' => 'User&#8217;s comment data.',
				'items'             => array(
					'comment-2' => array(
						array(
							'name'  => 'Comment Author',
							'value' => 'User Name',
						),
						array(
							'name'  => 'Comment Author Email',
							'value' => 'export-requester@example.com',
						),
						array(
							'name'  => 'Comment Author IP',
							'value' => '::1',
						),
						array(
							'name'  => 'Comment Author User Agent',
							'value' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36',
						),
						array(
							'name'  => 'Comment Date',
							'value' => '2020-01-31 19:55:19',
						),
						array(
							'name'  => 'Comment Content',
							'value' => 'Test',
						),
						array(
							'name'  => 'Comment URL',
							'value' => '<a href="http://localhost:8888/46894/2020/01/31/hello-world/#comment-2" target="_blank" rel="noreferrer noopener">http://localhost:8888/46894/2020/01/31/hello-world/#comment-2</a>',
						),
					),
					'comment-3' => array(
						array(
							'name'  => 'Comment Author',
							'value' => 'User Name',
						),
						array(
							'name'  => 'Comment Author Email',
							'value' => 'export-requester@example.com',
						),
						array(
							'name'  => 'Comment Author IP',
							'value' => '::1',
						),
						array(
							'name'  => 'Comment Author User Agent',
							'value' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36',
						),
						array(
							'name'  => 'Comment Date',
							'value' => '2020-01-31 20:55:19',
						),
						array(
							'name'  => 'Comment Content',
							'value' => 'Test #2',
						),
						array(
							'name'  => 'Comment URL',
							'value' => '<a href="http://localhost:8888/46894/2020/01/31/hello-world/#comment-3" target="_blank" rel="noreferrer noopener">http://localhost:8888/46894/2020/01/31/hello-world/#comment-3</a>',
						),
					),
				),
			),
		);
		update_post_meta( self::$export_request_id, '_export_data_grouped', $export_data_grouped );

		// Generate Export File
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
		$this->assertTrue( file_exists( $this->export_file_name ) );

		// Cleam-up for subsequent tests
		update_post_meta( self::$export_request_id, '_export_data_grouped', array() );

		$report_dir = trailingslashit( self::$exports_dir . 'test_contents' );
		mkdir( $report_dir );

		$zip        = new ZipArchive();
		$opened_zip = $zip->open( $this->export_file_name );
		$this->assertTrue( $opened_zip );

		$zip->extractTo( $report_dir );
		$zip->close();
		$this->assertTrue( file_exists( $report_dir . 'index.html' ) );

		$report_contents = file_get_contents( $report_dir . 'index.html' );
		$request         = wp_get_user_request( self::$export_request_id );

		$this->assertContains( '<div id="table_of_contents">', $report_contents );
		$this->assertContains( '<a href="#comments-comments">Comments <span class="count">(2)</span></a>', $report_contents );
		$this->assertContains( $request->email, $report_contents );
	}

	/**
	 * Test the export HTML file containing multiple export groups with no multiple group items
	 * has a table of contents without group count.
	 *
	 * @ticket 46894
	 */
	public function test_multiple_group_export_single_items_no_group_count_in_toc() {
		$this->expectOutputString( '' );

		// Setup Export Data to contain multiple groups
		$export_data_grouped = array(
			'user'     => array(
				'group_label'       => 'User',
				'group_description' => 'User&#8217;s profile data.',
				'items'             => array(
					'user-1' => array(
						array(
							'name'  => 'User ID',
							'value' => 1,
						),
						array(
							'name'  => 'User Login Name',
							'value' => 'user_login',
						),
						array(
							'name'  => 'User Nice Name',
							'value' => 'User Name',
						),
						array(
							'name'  => 'User Email',
							'value' => 'export-requester@example.com',
						),
						array(
							'name'  => 'User Registration Date',
							'value' => '2020-01-31 19:29:29',
						),
						array(
							'name'  => 'User Display Name',
							'value' => 'User Name',
						),
						array(
							'name'  => 'User Nickname',
							'value' => 'User',
						),
					),
				),
			),
			'comments' => array(
				'group_label'       => 'Comments',
				'group_description' => 'User&#8217;s comment data.',
				'items'             => array(
					'comment-2' => array(
						array(
							'name'  => 'Comment Author',
							'value' => 'User Name',
						),
						array(
							'name'  => 'Comment Author Email',
							'value' => 'export-requester@example.com',
						),
						array(
							'name'  => 'Comment Author IP',
							'value' => '::1',
						),
						array(
							'name'  => 'Comment Author User Agent',
							'value' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36',
						),
						array(
							'name'  => 'Comment Date',
							'value' => '2020-01-31 19:55:19',
						),
						array(
							'name'  => 'Comment Content',
							'value' => 'Test',
						),
						array(
							'name'  => 'Comment URL',
							'value' => '<a href="http://localhost:8888/46894/2020/01/31/hello-world/#comment-2" target="_blank" rel="noreferrer noopener">http://localhost:8888/46894/2020/01/31/hello-world/#comment-2</a>',
						),
					),
				),
			),
		);
		update_post_meta( self::$export_request_id, '_export_data_grouped', $export_data_grouped );

		// Generate Export File
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
		$this->assertTrue( file_exists( $this->export_file_name ) );

		// Cleam-up for subsequent tests
		update_post_meta( self::$export_request_id, '_export_data_grouped', array() );

		$report_dir = trailingslashit( self::$exports_dir . 'test_contents' );
		mkdir( $report_dir );

		$zip        = new ZipArchive();
		$opened_zip = $zip->open( $this->export_file_name );
		$this->assertTrue( $opened_zip );

		$zip->extractTo( $report_dir );
		$zip->close();
		$this->assertTrue( file_exists( $report_dir . 'index.html' ) );

		$report_contents = file_get_contents( $report_dir . 'index.html' );
		$request         = wp_get_user_request( self::$export_request_id );

		$this->assertContains( '<div id="table_of_contents">', $report_contents );
		$this->assertNotContains( '<span class="count">', $report_contents );
		$this->assertContains( $request->email, $report_contents );

	}
}
