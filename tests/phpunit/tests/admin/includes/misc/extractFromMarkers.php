<?php

/**
 * Tests for the extract_from_markers() function.
 *
 * @group admin
 *
 * @covers ::extract_from_markers
 */
class Tests_extract_from_markers extends WP_UnitTestCase {

	/**
	 * Path to the temporary file used for testing.
	 * @var string
	 */
	private $temp_file;

	public function set_up() {
		parent::set_up();
		$this->temp_file = wp_tempnam( 'markers.txt' );
	}

	public function tear_down() {
		if ( file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}
		parent::tear_down();
	}

	/**
	 * Tests that extract_from_markers() correctly extracts content between markers.
	 *
	 * @ticket 65136
	 *
	 * @dataProvider data_extract_from_markers
	 *
	 * @param array  $expected The expected lines extracted.
	 * @param string $content  The content of the file.
	 * @param string $marker   The marker to extract.
	 */
	public function test_extract_from_markers( $expected, $content, $marker ) {
		file_put_contents( $this->temp_file, $content );

		$this->assertSame( $expected, extract_from_markers( $this->temp_file, $marker ) );
	}

	/**
	 * Data provider for test_extract_from_markers.
	 *
	 * @return array[]
	 */
	public function data_extract_from_markers() {
		return array(
			'Empty file'                          => array(
				'expected' => array(),
				'content'  => '',
				'marker'   => 'WordPress',
			),
			'Standard WordPress markers'          => array(
				'expected' => array( 'Line 1', 'Line 2' ),
				'content'  => "Outside\n# BEGIN WordPress\nLine 1\nLine 2\n# END WordPress\nOutside",
				'marker'   => 'WordPress',
			),
			'Comments inside markers are ignored' => array(
				'expected' => array( 'Real Line' ),
				'content'  => "# BEGIN Test\n# Comment\nReal Line\n# END Test",
				'marker'   => 'Test',
			),
			'Multiple marker blocks'              => array(
				'expected' => array( 'Block 1', 'Block 2' ),
				'content'  => "# BEGIN X\nBlock 1\n# END X\n# BEGIN X\nBlock 2\n# END X",
				'marker'   => 'X',
			),
			'Marker not found'                    => array(
				'expected' => array(),
				'content'  => "Some content\nwithout markers",
				'marker'   => 'WordPress',
			),
			'Partial markers'                     => array(
				'expected' => array( 'Partial' ),
				'content'  => "# BEGIN Y\nPartial",
				'marker'   => 'Y',
			),
			'Full .htaccess example'              => array(
				'expected' => array(
					'<IfModule mod_rewrite.c>',
					'RewriteEngine On',
					'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
					'RewriteBase /',
					'RewriteRule ^index\.php$ - [L]',
					'RewriteCond %{REQUEST_FILENAME} !-f',
					'RewriteCond %{REQUEST_FILENAME} !-d',
					'RewriteRule . /index.php [L]',
					'</IfModule>',
				),
				'content'  => "RewriteCond %{HTTPS} !=on\nRewriteCond %{HTTP:X-Forwarded-Proto} !=https\nRewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n\n# BEGIN WordPress\n# The directives (lines) between \"BEGIN WordPress\" and \"END WordPress\" are\n# dynamically generated, and should only be modified via WordPress filters.\n# Any changes to the directives between these markers will be overwritten.\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase /\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n\n# END WordPress",
				'marker'   => 'WordPress',
			),
		);
	}

	/**
	 * Tests extraction when file does not exist.
	 *
	 * @ticket 65136
	 */
	public function test_extract_from_markers_non_existent_file() {
		$this->assertSame( array(), extract_from_markers( '/non/existent/path', 'WordPress' ) );
	}
}
