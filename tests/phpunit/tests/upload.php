<?php
/**
 * @group upload
 * @group media
 */
class Tests_Upload extends WP_UnitTestCase {
	public $siteurl;

	public function set_up() {
		parent::set_up();
		$this->reset_options();
	}

	private function reset_options() {
		// System defaults.
		update_option( 'upload_path', 'wp-content/uploads' );
		update_option( 'upload_url_path', '' );
		update_option( 'uploads_use_yearmonth_folders', 1 );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';
		require_once DIR_TESTROOT . '/includes/class-wp-test-strict-dir-stream.php';
		stream_wrapper_register( 'wptestdir', 'WP_Test_Strict_Dir_Stream' );
	}

	public static function wpTearDownAfterClass() {
		stream_wrapper_unregister( 'wptestdir' );
	}

	public function test_upload_dir_default() {
		// wp_upload_dir() with default parameters.
		$info   = wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( get_option( 'siteurl' ) . '/wp-content/uploads' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	public function test_upload_dir_relative() {
		// wp_upload_dir() with a relative upload path that is not 'wp-content/uploads'.
		update_option( 'upload_path', 'foo/bar' );
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( get_option( 'siteurl' ) . '/foo/bar' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'foo/bar' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	/**
	 * @ticket 5953
	 */
	public function test_upload_dir_absolute() {
		$path = get_temp_dir() . 'wp-unit-test';

		// wp_upload_dir() with an absolute upload path.
		update_option( 'upload_path', $path );

		// Doesn't make sense to use an absolute file path without setting the url path.
		update_option( 'upload_url_path', '/baz' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( '/baz' . $subdir, $info['url'] );
		$this->assertSame( $path . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	public function test_upload_dir_no_yearnum() {
		update_option( 'uploads_use_yearmonth_folders', 0 );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		$info = _wp_upload_dir();

		$this->assertSame( get_option( 'siteurl' ) . '/wp-content/uploads', $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads', $info['path'] );
		$this->assertSame( '', $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	public function test_upload_path_absolute() {
		update_option( 'upload_url_path', 'http://' . WP_TESTS_DOMAIN . '/asdf' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/asdf' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	public function test_upload_dir_empty() {
		// Upload path setting is empty - it should default to 'wp-content/uploads'.
		update_option( 'upload_path', '' );

		// Use `_wp_upload_dir()` directly to bypass caching and work with the changed options.
		// It doesn't create the /year/month directories.
		$info   = _wp_upload_dir();
		$subdir = date_format( date_create( 'now' ), '/Y/m' );

		$this->assertSame( get_option( 'siteurl' ) . '/wp-content/uploads' . $subdir, $info['url'] );
		$this->assertSame( ABSPATH . 'wp-content/uploads' . $subdir, $info['path'] );
		$this->assertSame( $subdir, $info['subdir'] );
		$this->assertFalse( $info['error'] );
	}

	/**
	 * @ticket 42838
	 */
	public function test_wp_upload_bits_should_support_stream_wrapper_directories() {
		$filter = static function ( $uploads ) {
			$uploads['path']    = 'wptestdir://uploads-test/uploads';
			$uploads['basedir'] = 'wptestdir://uploads-test/uploads';
			$uploads['subdir']  = '';
			$uploads['url']     = 'https://example.org/uploads';
			$uploads['baseurl'] = 'https://example.org/uploads';

			return $uploads;
		};

		WP_Test_Stream::$data = array(
			'uploads-test' => array(
				'/uploads/' => 'DIRECTORY',
			),
		);

		add_filter( 'upload_dir', $filter );

		$upload = wp_upload_bits( 'stream.txt', null, 'stream wrapper contents' );

		remove_filter( 'upload_dir', $filter );

		$this->assertSame( 'stream wrapper contents', WP_Test_Stream::$data['uploads-test']['/uploads/stream.txt'] );
		$this->assertSame( 'https://example.org/uploads/stream.txt', $upload['url'] );
		$this->assertFalse( $upload['error'] );
	}
}
