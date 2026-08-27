<?php

/**
 * Tests for the iis7_rewrite_rule_exists() function.
 *
 * @group admin
 *
 * @covers ::iis7_rewrite_rule_exists
 */
class Tests_iis7_rewrite_rule_exists extends WP_UnitTestCase {

	/**
	 * Path to the temporary file.
	 *
	 * @var string
	 */
	private $temp_file;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$this->temp_file = wp_tempnam( 'web.config' );
	}

	public function tear_down() {
		if ( file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}

		parent::tear_down();
	}

	/**
	 * Tests that iis7_rewrite_rule_exists() correctly identifies the existence of rules.
	 *
	 * @ticket 65148
	 *
	 * @dataProvider data_iis7_rewrite_rule_exists
	 *
	 * @phpstan-return array<string, array{expected: bool, content: string}>
	 *
	 * @param bool   $expected Whether the rule is expected to exist.
	 * @param string $content  The XML content of the file.
	 */
	public function test_iis7_rewrite_rule_exists( $expected, $content ) {
		if ( 'Not XML' === $content ) {
			file_put_contents( $this->temp_file, $content );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->assertSame( $expected, @iis7_rewrite_rule_exists( $this->temp_file ) );
		} else {
			file_put_contents( $this->temp_file, $content );
			$this->assertSame( $expected, iis7_rewrite_rule_exists( $this->temp_file ) );
		}
	}

	/**
	 * Data provider for test_iis7_rewrite_rule_exists.
	 *
	 * @return array<string, array{expected: bool, content: string}>
	 */
	public function data_iis7_rewrite_rule_exists(): array {
		return array(
			'Rule with name starting with "wordpress" exists' => array(
				'expected' => true,
				'content'  => '<configuration><system.webServer><rewrite><rules><rule name="wordpress-rules" /></rules></rewrite></system.webServer></configuration>',
			),
			'Rule with name "wordpress" exists' => array(
				'expected' => true,
				'content'  => '<configuration><system.webServer><rewrite><rules><rule name="wordpress" /></rules></rewrite></system.webServer></configuration>',
			),
			'Rule with name "WordPress" exists' => array(
				'expected' => true,
				'content'  => '<configuration><system.webServer><rewrite><rules><rule name="WordPress" /></rules></rewrite></system.webServer></configuration>',
			),
			'Rule does not exist'               => array(
				'expected' => false,
				'content'  => '<configuration><system.webServer><rewrite><rules><rule name="other-rule" /></rules></rewrite></system.webServer></configuration>',
			),
			'Empty configuration'               => array(
				'expected' => false,
				'content'  => '<configuration />',
			),
			'Invalid XML'                       => array(
				'expected' => false,
				'content'  => 'Not XML',
			),
		);
	}

	/**
	 * Tests that iis7_rewrite_rule_exists() returns false if the file does not exist.
	 *
	 * @ticket 65148
	 */
	public function test_iis7_rewrite_rule_exists_non_existent_file() {
		$this->assertFalse( iis7_rewrite_rule_exists( '/non/existent/file' ) );
	}
}
