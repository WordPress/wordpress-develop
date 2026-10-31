<?php
/**
 * Tests for the iis7_add_rewrite_rule() function.
 *
 * @group admin
 *
 * @covers ::iis7_add_rewrite_rule
 */
class Tests_iis7_add_rewrite_rule extends WP_UnitTestCase {
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
	 * Tests that iis7_add_rewrite_rule() correctly adds WordPress rules.
	 *
	 * @ticket 65172
	 *
	 * @dataProvider data_iis7_add_rewrite_rule
	 *
	 * @param string $initial_content  The initial XML content of the file.
	 * @param string $rewrite_rule     The rule to add.
	 * @param string $expected_content The expected XML content after adding.
	 */
	public function test_iis7_add_rewrite_rule( $initial_content, $rewrite_rule, $expected_content ) {
		if ( null !== $initial_content ) {
			file_put_contents( $this->temp_file, $initial_content );
		} else {
			if ( file_exists( $this->temp_file ) ) {
				unlink( $this->temp_file );
			}
		}

		$result = iis7_add_rewrite_rule( $this->temp_file, $rewrite_rule );

		$this->assertTrue( $result, 'iis7_add_rewrite_rule() should return true on success.' );
		$this->assertXmlStringEqualsXmlFile( $this->temp_file, $expected_content, 'The XML content should match the expected output.' );
	}

	/**
	 * Data provider for test_iis7_add_rewrite_rule.
	 *
	 * @return array<string, array{
	 *     initial_content:  string|null,
	 *     rewrite_rule:     string,
	 *     expected_content: string,
	 * }>
	 */
	public function data_iis7_add_rewrite_rule(): array {
		$rule = '<rule name="wordpress" patternSyntax="Wildcard"><match url="*" /><conditions><add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" /><add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" /></conditions><action type="Rewrite" url="index.php" /></rule>';

		return array(
			'File does not exist'                  => array(
				'initial_content'  => null,
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules>' . $rule . '</rules></rewrite></system.webServer></configuration>',
			),
			'Empty configuration'                  => array(
				'initial_content'  => '<configuration/>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules>' . $rule . '</rules></rewrite></system.webServer></configuration>',
			),
			'Existing system.webServer'            => array(
				'initial_content'  => '<configuration><system.webServer/></configuration>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules>' . $rule . '</rules></rewrite></system.webServer></configuration>',
			),
			'Existing rewrite'                     => array(
				'initial_content'  => '<configuration><system.webServer><rewrite/></system.webServer></configuration>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules>' . $rule . '</rules></rewrite></system.webServer></configuration>',
			),
			'Existing rules'                       => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules/></rewrite></system.webServer></configuration>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules>' . $rule . '</rules></rewrite></system.webServer></configuration>',
			),
			'Existing other rule'                  => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="other" /></rules></rewrite></system.webServer></configuration>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="other" />' . $rule . '</rules></rewrite></system.webServer></configuration>',
			),
			'Rule already exists (lowercase)'      => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="wordpress" /></rules></rewrite></system.webServer></configuration>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="wordpress" /></rules></rewrite></system.webServer></configuration>',
			),
			'Rule already exists (CamelCase)'      => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="WordPress" /></rules></rewrite></system.webServer></configuration>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="WordPress" /></rules></rewrite></system.webServer></configuration>',
			),
			'Rule with same prefix already exists' => array(
				'initial_content'  => '<configuration><system.webServer><rewrite><rules><rule name="wordpress-rules" /></rules></rewrite></system.webServer></configuration>',
				'rewrite_rule'     => $rule,
				'expected_content' => '<configuration><system.webServer><rewrite><rules><rule name="wordpress-rules" /></rules></rewrite></system.webServer></configuration>',
			),
		);
	}

	/**
	 * Tests that iis7_add_rewrite_rule() returns false for invalid XML.
	 *
	 * @ticket 65172
	 */
	public function test_iis7_add_rewrite_rule_invalid_xml() {
		file_put_contents( $this->temp_file, 'Not XML' );
		$rule = '<rule name="wordpress" />';
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( @iis7_add_rewrite_rule( $this->temp_file, $rule ), 'Should return false for invalid XML.' );
	}
}
