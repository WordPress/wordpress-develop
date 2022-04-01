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
class Tests_Privacy_wpPrivacyGeneratePersonalDataExportFile extends WP_UnitTestCase {
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
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
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
	public function set_up() {
		parent::set_up();

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
	public function tear_down() {
		$this->remove_exports_dir();
		error_reporting( $this->_error_level );
		parent::tear_down();
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

		$this->expectException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Invalid request ID when generating personal data export file."}' );
		wp_privacy_generate_personal_data_export_file( $request_id );
	}

	/**
	 * When an invalid request ID is passed an error should be displayed.
	 *
	 * @ticket 44233
	 */
	public function test_invalid_request_id() {
		$this->expectException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Invalid request ID when generating personal data export file."}' );
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

		$this->expectException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Invalid email address when generating personal data export file."}' );
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

		$this->expectException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Unable to create personal data export folder."}' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
	}

	/**
	 * @ticket 51423
	 *
	 * @dataProvider data_export_data_grouped_invalid_type
	 *
	 * @param mixed $groups '_export_data_grouped' post meta value.
	 */
	public function test_doing_it_wrong_for_export_data_grouped_invalid_type( $groups ) {
		update_post_meta( self::$export_request_id, '_export_data_grouped', $groups );

		$this->setExpectedIncorrectUsage( 'wp_privacy_generate_personal_data_export_file' );

		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
	}

	public function data_export_data_grouped_invalid_type() {
		return array(
			array( 10 ),
			array( 'WordPress' ),
			array( null ),
			array( true ),
			array( false ),
			array( new stdClass() ),
			array( serialize( array( 10, 'WordPress', null, true, false ) ) ),
			array(
				json_encode(
					array(
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
					)
				),
			),
		);
	}

	/**
	 * Test that an index.php file can be added to the export directory.
	 *
	 * @ticket 44233
	 */
	public function test_creates_index_in_export_folder() {
		$this->expectOutputString( '' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );

		$this->assertTrue( file_exists( self::$exports_dir . 'index.php' ) );
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
	 * @ticket 46894
	 * @ticket 51423
	 *
	 * @dataProvider data_contents
	 *
	 * @param mixed    $groups           '_export_data_grouped' post meta value.
	 * @param string[] $expected_content Optional. Expected content. Use "html" key for this test.
	 */
	public function test_html_contents( $groups, array $expected_content = array() ) {
		// Set the _doing_it_wrong assertion.
		if ( ! is_array( $groups ) ) {
			$this->setExpectedIncorrectUsage( 'wp_privacy_generate_personal_data_export_file' );
		}

		$request    = wp_get_user_request( self::$export_request_id );
		$report_dir = $this->setup_export_contents_test( $groups );

		$this->assertFileExists( $report_dir . 'index.html' );
		$actual_contents = file_get_contents( $report_dir . 'index.html' );

		$expected  = "<!DOCTYPE html>\n";
		$expected .= "<html>\n";
		$expected .= "<head>\n";
		$expected .= "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />\n";
		$expected .= "<style type='text/css'>body { color: black; font-family: Arial, sans-serif; font-size: 11pt; margin: 15px auto; width: 860px; }table { background: #f0f0f0; border: 1px solid #ddd; margin-bottom: 20px; width: 100%; }th { padding: 5px; text-align: left; width: 20%; }td { padding: 5px; }tr:nth-child(odd) { background-color: #fafafa; }.return-to-top { text-align: right; }</style><title>Personal Data Export for {$request->email}</title></head>\n";
		$expected .= "<body>\n";
		$expected .= '<h1 id="top">Personal Data Export</h1>';

		if ( is_array( $groups ) && isset( $expected_content['html'] ) ) {
			$expected .= $this->replace_timestamp_placeholder( $actual_contents, $expected_content['html'] );
		}

		$expected .= "</body>\n";
		$expected .= "</html>\n";

		$this->assertSame( $expected, $actual_contents );
	}

	/**
	 * Test the export JSON file has all the expected parts.
	 *
	 * @ticket 49029
	 * @ticket 46894
	 * @ticket 51423
	 *
	 * @dataProvider data_contents
	 *
	 * @param mixed    $groups           '_export_data_grouped' post meta value.
	 * @param string[] $expected_content Optional. Expected content. Use "json" key for this test.
	 */
	public function test_json_contents( $groups, array $expected_content = array() ) {
		// Set the _doing_it_wrong assertion.
		if ( ! is_array( $groups ) ) {
			$this->setExpectedIncorrectUsage( 'wp_privacy_generate_personal_data_export_file' );
		}

		$request    = wp_get_user_request( self::$export_request_id );
		$report_dir = $this->setup_export_contents_test( $groups );

		$this->assertFileExists( $report_dir . 'index.html' );
		$actual_json = file_get_contents( $report_dir . 'export.json' );

		$expected = '{"Personal Data Export for ' . $request->email . '":';
		if ( ! is_array( $groups ) ) {
			$expected .= 'null}';
		} else {
			// "About" group: to avoid time difference, use the report's "on" timestamp.
			$about_group = '{"about":{"group_label":"About","group_description":"Overview of export report.","items":{"about-1":[{"name":"Report generated for","value":"' . $request->email . '"},{"name":"For site","value":"Test Blog"},{"name":"At URL","value":"http:\/\/example.org"},{"name":"On","value":"{{TIMESTAMP}}"}]}}';
			$expected   .= $this->replace_timestamp_placeholder( $actual_json, $about_group );
			if ( isset( $expected_content['json'] ) ) {
				$expected .= $expected_content['json'];
			}
			$expected .= '}}';
		}

		$this->assertSame( $expected, $actual_json );
	}

	/**
	 * Sets up the export contents.
	 *
	 * Tasks:
	 * - Delete or update the '_export_data_grouped' post meta.
	 * - Run `wp_privacy_generate_personal_data_export_file()`.
	 * - Unzip the export package in a temporary directory to give the test access to the export files.
	 *
	 * @param mixed $export_data_grouped Optional. '_export_data_grouped' post meta value.
	 *                                   When null, delete the meta; else update to the given value.
	 * @return string Export report directory path.
	 */
	private function setup_export_contents_test( $export_data_grouped = null ) {
		// Delete or update the given meta.
		if ( null === $export_data_grouped ) {
			delete_post_meta( self::$export_request_id, '_export_data_grouped' );
		} else {
			update_post_meta( self::$export_request_id, '_export_data_grouped', $export_data_grouped );
		}

		$this->expectOutputString( '' );

		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
		$this->assertTrue( file_exists( $this->export_file_name ) );

		// Create a temporary export directory for the test's export files.
		$report_dir = trailingslashit( self::$exports_dir . 'test_contents' );
		mkdir( $report_dir );

		// Unzip the current test's export file to give the test access to .html and .json files.
		$zip        = new ZipArchive();
		$opened_zip = $zip->open( $this->export_file_name );
		$this->assertTrue( $opened_zip );
		$zip->extractTo( $report_dir );
		$zip->close();

		return $report_dir;
	}

	/**
	 * Replaces expected content's timestamp placeholder with the actual content's timestamp.
	 *
	 * Used when the expected content has a placeholder, i.e. used to avoid second time differences
	 * between the test and code.
	 *
	 * @param string $actual_content   Content with the actual timestamp.
	 * @param string $expected_content Expected content that has the timestamp placeholder
	 *                                 to be replaced with the actual timestamp.
	 * @return string Updated expected content on success; else original expected content.
	 */
	private function replace_timestamp_placeholder( $actual_content, $expected_content ) {
		$placeholder_pos = stripos( $expected_content, '{{TIMESTAMP}}' );
		if ( false === $placeholder_pos ) {
			return $expected_content;
		}

		$needle     = substr( $expected_content, 0, $placeholder_pos );
		$needle_pos = strpos( $actual_content, $needle ) + strlen( $needle );
		$timestamp  = substr( $actual_content, $needle_pos, 19 );

		return str_replace( '{{TIMESTAMP}}', $timestamp, $expected_content );
	}

	public function data_contents() {
		return array(
			// Unhappy path.
			'should contain null when integer'           => array(
				'groups' => 10,
			),
			'should contain null when boolean'           => array(
				'groups' => true,
			),
			'should contain null when string'            => array(
				'groups' => 'string',
			),
			'should contain null when object'            => array(
				'groups' => new stdClass(),
			),
			'should contain only about when _export_data_grouped does not exist' => array(
				'groups' => null,
			),
			'should contain only about when empty array' => array(
				'groups'           => array(),
				'expected_content' => array(
					'html' => '<h2 id="about-about">About</h2><p>Overview of export report.</p><div><table><tbody><tr><th>Report generated for</th><td>export-requester@example.com</td></tr><tr><th>For site</th><td>Test Blog</td></tr><tr><th>At URL</th><td><a href="http://example.org">http://example.org</a></td></tr><tr><th>On</th><td>{{TIMESTAMP}}</td></tr></tbody></table></div>',
				),
			),
			// Happy path.
			'should contain about and export data groups when single group exists' => array(
				'groups'           => array(
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
				),
				'expected_content' => array(
					'html' => '<div id="table_of_contents"><h2>Table of Contents</h2><ul><li><a href="#about-about">About</a></li><li><a href="#user-user">User</a></li></ul></div><h2 id="about-about">About</h2><p>Overview of export report.</p><div><table><tbody><tr><th>Report generated for</th><td>export-requester@example.com</td></tr><tr><th>For site</th><td>Test Blog</td></tr><tr><th>At URL</th><td><a href="http://example.org">http://example.org</a></td></tr><tr><th>On</th><td>{{TIMESTAMP}}</td></tr></tbody></table><div class="return-to-top"><a href="#top"><span aria-hidden="true">&uarr; </span> Go to top</a></div></div><h2 id="user-user">User</h2><p>User&#8217;s profile data.</p><div><table><tbody><tr><th>User ID</th><td>1</td></tr><tr><th>User Login Name</th><td>user_login</td></tr><tr><th>User Nice Name</th><td>User Name</td></tr><tr><th>User Email</th><td>export-requester@example.com</td></tr><tr><th>User Registration Date</th><td>2020-01-31 19:29:29</td></tr><tr><th>User Display Name</th><td>User Name</td></tr><tr><th>User Nickname</th><td>User</td></tr></tbody></table><div class="return-to-top"><a href="#top"><span aria-hidden="true">&uarr; </span> Go to top</a></div></div>',
					'json' => ',"user":{"group_label":"User","group_description":"User&#8217;s profile data.","items":{"user-1":[{"name":"User ID","value":1},{"name":"User Login Name","value":"user_login"},{"name":"User Nice Name","value":"User Name"},{"name":"User Email","value":"export-requester@example.com"},{"name":"User Registration Date","value":"2020-01-31 19:29:29"},{"name":"User Display Name","value":"User Name"},{"name":"User Nickname","value":"User"}]}}',
				),
			),
			'should contain about and export data groups when multiple groups exist' => array(
				'groups'           => array(
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
									'value' => '<a href="http://localhost:8888/46894/2020/01/31/hello-world/#comment-2" target="_blank" rel="noopener">http://localhost:8888/46894/2020/01/31/hello-world/#comment-2</a>',
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
									'value' => '<a href="http://localhost:8888/46894/2020/01/31/hello-world/#comment-3" target="_blank" rel="noopener">http://localhost:8888/46894/2020/01/31/hello-world/#comment-3</a>',
								),
							),
						),
					),
				),
				'expected_content' => array(
					'html' => '<div id="table_of_contents"><h2>Table of Contents</h2><ul><li><a href="#about-about">About</a></li><li><a href="#user-user">User</a></li><li><a href="#comments-comments">Comments <span class="count">(2)</span></a></li></ul></div><h2 id="about-about">About</h2><p>Overview of export report.</p><div><table><tbody><tr><th>Report generated for</th><td>export-requester@example.com</td></tr><tr><th>For site</th><td>Test Blog</td></tr><tr><th>At URL</th><td><a href="http://example.org">http://example.org</a></td></tr><tr><th>On</th><td>{{TIMESTAMP}}</td></tr></tbody></table><div class="return-to-top"><a href="#top"><span aria-hidden="true">&uarr; </span> Go to top</a></div></div><h2 id="user-user">User</h2><p>User&#8217;s profile data.</p><div><table><tbody><tr><th>User ID</th><td>1</td></tr><tr><th>User Login Name</th><td>user_login</td></tr><tr><th>User Nice Name</th><td>User Name</td></tr><tr><th>User Email</th><td>export-requester@example.com</td></tr><tr><th>User Registration Date</th><td>2020-01-31 19:29:29</td></tr><tr><th>User Display Name</th><td>User Name</td></tr><tr><th>User Nickname</th><td>User</td></tr></tbody></table><div class="return-to-top"><a href="#top"><span aria-hidden="true">&uarr; </span> Go to top</a></div></div><h2 id="comments-comments">Comments <span class="count">(2)</span></h2><p>User&#8217;s comment data.</p><div><table><tbody><tr><th>Comment Author</th><td>User Name</td></tr><tr><th>Comment Author Email</th><td>export-requester@example.com</td></tr><tr><th>Comment Author IP</th><td>::1</td></tr><tr><th>Comment Author User Agent</th><td>Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36</td></tr><tr><th>Comment Date</th><td>2020-01-31 19:55:19</td></tr><tr><th>Comment Content</th><td>Test</td></tr><tr><th>Comment URL</th><td><a href="http://localhost:8888/46894/2020/01/31/hello-world/#comment-2">http://localhost:8888/46894/2020/01/31/hello-world/#comment-2</a></td></tr></tbody></table><table><tbody><tr><th>Comment Author</th><td>User Name</td></tr><tr><th>Comment Author Email</th><td>export-requester@example.com</td></tr><tr><th>Comment Author IP</th><td>::1</td></tr><tr><th>Comment Author User Agent</th><td>Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36</td></tr><tr><th>Comment Date</th><td>2020-01-31 20:55:19</td></tr><tr><th>Comment Content</th><td>Test #2</td></tr><tr><th>Comment URL</th><td><a href="http://localhost:8888/46894/2020/01/31/hello-world/#comment-3">http://localhost:8888/46894/2020/01/31/hello-world/#comment-3</a></td></tr></tbody></table><div class="return-to-top"><a href="#top"><span aria-hidden="true">&uarr; </span> Go to top</a></div></div>',
					'json' => ',"user":{"group_label":"User","group_description":"User&#8217;s profile data.","items":{"user-1":[{"name":"User ID","value":1},{"name":"User Login Name","value":"user_login"},{"name":"User Nice Name","value":"User Name"},{"name":"User Email","value":"export-requester@example.com"},{"name":"User Registration Date","value":"2020-01-31 19:29:29"},{"name":"User Display Name","value":"User Name"},{"name":"User Nickname","value":"User"}]}},"comments":{"group_label":"Comments","group_description":"User&#8217;s comment data.","items":{"comment-2":[{"name":"Comment Author","value":"User Name"},{"name":"Comment Author Email","value":"export-requester@example.com"},{"name":"Comment Author IP","value":"::1"},{"name":"Comment Author User Agent","value":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/79.0.3945.130 Safari\/537.36"},{"name":"Comment Date","value":"2020-01-31 19:55:19"},{"name":"Comment Content","value":"Test"},{"name":"Comment URL","value":"<a href=\"http:\/\/localhost:8888\/46894\/2020\/01\/31\/hello-world\/#comment-2\" target=\"_blank\" rel=\"noopener\">http:\/\/localhost:8888\/46894\/2020\/01\/31\/hello-world\/#comment-2<\/a>"}],"comment-3":[{"name":"Comment Author","value":"User Name"},{"name":"Comment Author Email","value":"export-requester@example.com"},{"name":"Comment Author IP","value":"::1"},{"name":"Comment Author User Agent","value":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/79.0.3945.130 Safari\/537.36"},{"name":"Comment Date","value":"2020-01-31 20:55:19"},{"name":"Comment Content","value":"Test #2"},{"name":"Comment URL","value":"<a href=\"http:\/\/localhost:8888\/46894\/2020\/01\/31\/hello-world\/#comment-3\" target=\"_blank\" rel=\"noopener\">http:\/\/localhost:8888\/46894\/2020\/01\/31\/hello-world\/#comment-3<\/a>"}]}}',
				),
			),
		);
	}

	/**
	 * Test should generate JSON error when JSON encoding fails.
	 *
	 * @ticket 52892
	 */
	public function test_should_generate_json_error_when_json_encoding_fails() {
		add_filter( 'get_post_metadata', array( $this, 'filter_export_data_grouped_metadata' ), 10, 3 );

		// Validate JSON encoding fails and returns `false`.
		$metadata = get_post_meta( self::$export_request_id, '_export_data_grouped', true );
		$this->assertFalse( wp_json_encode( $metadata ) );

		$this->expectException( 'WPDieException' );
		$this->expectOutputString( '{"success":false,"data":"Unable to encode the personal data for export. Error: Type is not supported"}' );
		wp_privacy_generate_personal_data_export_file( self::$export_request_id );
	}

	public function filter_export_data_grouped_metadata( $value, $object_id, $meta_key ) {
		if ( $object_id !== self::$export_request_id ) {
			return $value;
		}

		if ( '_export_data_grouped' !== $meta_key ) {
			return $value;
		}

		$file = fopen( __FILE__, 'r' );

		$value = array(
			'user' => array(
				'group_label'       => 'User',
				'group_description' => 'User&#8217;s profile data.',
				'items'             => array(),
				'resource'          => $file,
			),
		);

		fclose( $file );

		return array( $value );
	}
}
