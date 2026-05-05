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
	 * Tests that iis7_delete_rewrite_rule() correctly deletes WordPress rules.
	 *
	 * @ticket 65170
	 *
	 * @dataProvider data_iis7_delete_rewrite_rule
	 *
	 * @param string $initial_content  The initial XML content of the file.
	 * @param string $expected_content The expected XML content after deletion.
	 */
	public function test_iis7_delete_rewrite_rule( $initial_content, $expected_content ) {
		file_put_contents( $this->temp_file, $initial_content );

		$result = iis7_delete_rewrite_rule( $this->temp_file );

		$this->assertTrue( $result, 'iis7_delete_rewrite_rule() should return true on success.' );

		if ( '' === $expected_content ) {
			$this->assertStringEqualsFile( $this->temp_file, '', 'The file should be empty.' );
		} else {
			$this->assertXmlStringEqualsXmlFile( $this->temp_file, $expected_content, 'The XML content should match the expected output.' );
		}
	}

	/**
	 * Data provider for test_iis7_delete_rewrite_rule.
	 *
	 * @return array[]
	 */
	public function data_iis7_delete_rewrite_rule(): array {
		return array(
			'Rule with name "wordpress" exists'            => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="wordpress" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules/></rewrite></system.webServer></configuration>',
			),
			'Rule with name starting with "wordpress" exists' => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="wordpress-rules" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules/></rewrite></system.webServer></configuration>',
			),
			'Rule with name "WordPress" exists'            => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="WordPress" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules/></rewrite></system.webServer></configuration>',
			),
			'Multiple rules, only WordPress rule is deleted' => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="other-rule" /><rule name="wordpress" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="other-rule" /></rules></rewrite></system.webServer></configuration>',
			),
			'WordPress rule is first among multiple rules' => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="wordpress" /><rule name="other-rule" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="other-rule" /></rules></rewrite></system.webServer></configuration>',
			),
			'No WordPress rule exists'                     => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="other-rule" /></rules></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="other-rule" /></rules></rewrite></system.webServer></configuration>',
			),
			'Empty rules tag'                              => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules/></rewrite></system.webServer></configuration>',
				'expected_content' => '<configuration><system.webServer><rewrite><rules/></rewrite></system.webServer></configuration>',
			),
		);
	}

	/**
	 * Tests that iis7_delete_rewrite_rule() returns true if the file does not exist.
	 *
	 * @ticket 65170
	 */
	public function test_iis7_delete_rewrite_rule_non_existent_file() {
		$non_existent_file = dirname( $this->temp_file ) . '/non-existent-web.config';
		if ( file_exists( $non_existent_file ) ) {
			unlink( $non_existent_file );
		}

		$this->assertTrue( iis7_delete_rewrite_rule( $non_existent_file ), 'Should return true if the file does not exist.' );
	}

	/**
	 * Tests that iis7_delete_rewrite_rule() returns false for invalid XML.
	 *
	 * @ticket 65170
	 */
	public function test_iis7_delete_rewrite_rule_invalid_xml() {
		file_put_contents( $this->temp_file, 'Not XML' );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( @iis7_delete_rewrite_rule( $this->temp_file ), 'Should return false for invalid XML.' );
	}
}
