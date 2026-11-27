<?php
/**
 * Tests for the iis7_delete_rewrite_rule() function.
 *
 * @group admin
 *
 * @covers ::iis7_delete_rewrite_rule
 */
class Tests_iis7_delete_rewrite_rule extends WP_UnitTestCase {
	/**
	 * Path to the temporary file.
	 *
	 * @var string
	 */
	private $temp_file;

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		$this->temp_file = wp_tempnam( 'web.config' );
	}

	/**
	 * Clean up the test environment.
	 */
	public function tear_down() {
		if ( file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}
		parent::tear_down();
	}

	/**
	 * Tests that iis7_delete_rewrite_rule() removes the WordPress rule.
	 *
	 * @ticket 45970
	 *
	 * @dataProvider data_iis7_delete_rewrite_rule
	 *
	 * @param string $initial_content  The initial XML content of the file.
	 * @param string $expected_content The expected XML content after deleting.
	 */
	public function test_iis7_delete_rewrite_rule( $initial_content, $expected_content ) {
		file_put_contents( $this->temp_file, $initial_content );

		$result = iis7_delete_rewrite_rule( $this->temp_file );

		$this->assertTrue( $result, 'iis7_delete_rewrite_rule() should return true on success.' );
		$this->assertXmlStringEqualsXmlFile( $this->temp_file, $expected_content, 'The XML content should match the expected output.' );
	}

	/**
	 * Data provider for test_iis7_delete_rewrite_rule.
	 *
	 * @return array<string, array{
	 *     initial_content:  string,
	 *     expected_content: string,
	 * }>
	 */
	public static function data_iis7_delete_rewrite_rule(): array {
		$ns = ' xmlns="http://schemas.microsoft.com/.NetConfiguration/v2.0"';

		return array(
			'Rule exists'                        => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="other" /><rule name="wordpress" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="other" /></rules></rewrite></system.webServer></configuration>',
			),
			'Rule exists with default namespace' => array(
				'initial_content'  => "<configuration$ns><system.webServer><rewrite><rules><rule name=\"other\" /><rule name=\"wordpress\" /></rules></rewrite></system.webServer></configuration>",
				'expected_content' => "<configuration$ns><system.webServer><rewrite><rules><rule name=\"other\" /></rules></rewrite></system.webServer></configuration>",
			),
			'Rule does not exist'                => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="other" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="other" /></rules></rewrite></system.webServer></configuration>',
			),
		);
	}

	/**
	 * Tests that iis7_delete_rewrite_rule() returns true if the file does not exist.
	 *
	 * @ticket 45970
	 */
	public function test_iis7_delete_rewrite_rule_non_existent_file() {
		$this->assertTrue( iis7_delete_rewrite_rule( '/non/existent/file' ) );
	}
}
