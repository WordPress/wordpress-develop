<?php
/**
 * Test cases for the `wp_privacy_generate_personal_data_export_group_html()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.2.0
 */

/**
 * Tests_Privacy_WpPrivacyGeneratePersonalDataExportGroupHtml class.
 *
 * @group privacy
 * @covers ::wp_privacy_generate_personal_data_export_group_html
 *
 * @since 5.2.0
 */
class Tests_Privacy_WpPrivacyGeneratePersonalDataExportGroupHtml extends WP_UnitTestCase {

	/**
	 * Test when a single data item is passed.
	 *
	 * @ticket 44044
	 */
	public function test_group_html_generation_single_data_item() {
		$data = array(
			'group_label' => 'Test Data Group',
			'items'       => array(
				array(
					array(
						'name'  => 'Field 1 Name',
						'value' => 'Field 1 Value',
					),
					array(
						'name'  => 'Field 2 Name',
						'value' => 'Field 2 Value',
					),
				),
			),
		);

		$actual                = wp_privacy_generate_personal_data_export_group_html( $data, 'test-data-group', 2 );
		$expected_table_markup = '<table><tbody><tr><th>Field 1 Name</th><td>Field 1 Value</td></tr><tr><th>Field 2 Name</th><td>Field 2 Value</td></tr></tbody></table>';

		$this->assertContains( '<h2 id="test-data-group-test-data-group">Test Data Group</h2>', $actual );
		$this->assertContains( $expected_table_markup, $actual );
	}

	/**
	 * Test when a multiple data items are passed.
	 *
	 * @ticket 44044
	 * @ticket 46895 Updated to remove </h2> from test to avoid Count introducing failure.
	 */
	public function test_group_html_generation_multiple_data_items() {
		$data = array(
			'group_label' => 'Test Data Group',
			'items'       => array(
				array(
					array(
						'name'  => 'Field 1 Name',
						'value' => 'Field 1 Value',
					),
					array(
						'name'  => 'Field 2 Name',
						'value' => 'Field 2 Value',
					),
				),
				array(
					array(
						'name'  => 'Field 1 Name',
						'value' => 'Another Field 1 Value',
					),
					array(
						'name'  => 'Field 2 Name',
						'value' => 'Another Field 2 Value',
					),
				),
			),
		);

		$actual = wp_privacy_generate_personal_data_export_group_html( $data, 'test-data-group', 2 );

		$this->assertContains( '<h2 id="test-data-group-test-data-group">Test Data Group', $actual );
		$this->assertContains( '<td>Field 1 Value', $actual );
		$this->assertContains( '<td>Another Field 1 Value', $actual );
		$this->assertContains( '<td>Field 2 Value', $actual );
		$this->assertContains( '<td>Another Field 2 Value', $actual );
		$this->assertSame( 2, substr_count( $actual, '<th>Field 1 Name' ) );
		$this->assertSame( 2, substr_count( $actual, '<th>Field 2 Name' ) );
		$this->assertSame( 4, substr_count( $actual, '<tr>' ) );
	}

	/**
	 * Values that appear to be links should be wrapped in `<a>` tags.
	 *
	 * @ticket 44044
	 */
	public function test_links_become_anchors() {
		$data = array(
			'group_label' => 'Test Data Group',
			'items'       => array(
				array(
					array(
						'name'  => 'HTTP Link',
						'value' => 'http://wordpress.org',
					),
					array(
						'name'  => 'HTTPS Link',
						'value' => 'https://wordpress.org',
					),
					array(
						'name'  => 'Link with Spaces',
						'value' => 'https://wordpress.org not a link.',
					),
				),
			),
		);

		$actual = wp_privacy_generate_personal_data_export_group_html( $data, 'test-data-group', 2 );

		$this->assertContains( '<a href="http://wordpress.org">http://wordpress.org</a>', $actual );
		$this->assertContains( '<a href="https://wordpress.org">https://wordpress.org</a>', $actual );
		$this->assertContains( 'https://wordpress.org not a link.', $actual );
	}

	/**
	 * HTML in group labels should be escaped.
	 *
	 * @ticket 44044
	 */
	public function test_group_labels_escaped() {
		$data = array(
			'group_label' => '<div>Escape HTML in group labels</div>',
			'items'       => array(),
		);

		$actual = wp_privacy_generate_personal_data_export_group_html( $data, 'escape-html-in-group-labels', 2 );

		$this->assertContains( '<h2 id="escape-html-in-group-labels-escape-html-in-group-labels">&lt;div&gt;Escape HTML in group labels&lt;/div&gt;</h2>', $actual );
	}

	/**
	 * Test that the exported data should contain allowed HTML.
	 *
	 * @ticket 44044
	 */
	public function test_allowed_html_not_stripped() {
		$data = array(
			'group_label' => 'Test Data Group',
			'items'       => array(
				array(
					'links'      => array(
						'name'  => 'Links are allowed',
						'value' => '<a href="http://wordpress.org">http://wordpress.org</a>',
					),
					'formatting' => array(
						'name'  => 'Simple formatting is allowed',
						'value' => '<b>bold</b>, <em>emphasis</em>, <i>italics</i>, and <strong>strong</strong> are allowed.',
					),
				),
			),
		);

		$actual = wp_privacy_generate_personal_data_export_group_html( $data, 'test-data-group', 2 );
		$this->assertContains( $data['items'][0]['links']['value'], $actual );
		$this->assertContains( $data['items'][0]['formatting']['value'], $actual );
	}

	/**
	 * Test that the exported data should not contain disallowed HTML.
	 *
	 * @ticket 44044
	 */
	public function test_disallowed_html_is_stripped() {
		$data = array(
			'group_label' => 'Test Data Group',
			'items'       => array(
				array(
					'scripts' => array(
						'name'  => 'Script tags are not allowed.',
						'value' => '<script>Testing that script tags are stripped.</script>',
					),
					'images'  => array(
						'name'  => 'Images are not allowed',
						'value' => '<img src="https://example.com/logo.jpg" alt="Alt text" />',
					),
				),
			),
		);

		$actual = wp_privacy_generate_personal_data_export_group_html( $data, 'test-data-group', 2 );

		$this->assertNotContains( $data['items'][0]['scripts']['value'], $actual );
		$this->assertContains( '<td>Testing that script tags are stripped.</td>', $actual );

		$this->assertNotContains( $data['items'][0]['images']['value'], $actual );
		$this->assertContains( '<th>Images are not allowed</th><td></td>', $actual );
	}

	/**
	 * Test group count is displayed for multiple items.
	 *
	 * @ticket 46895
	 */
	public function test_group_html_generation_should_display_group_count_when_multiple_items() {
		$data = array(
			'group_label' => 'Test Data Group',
			'items'       => array(
				array(
					array(
						'name'  => 'Field 1 Name',
						'value' => 'Field 1 Value',
					),
				),
				array(
					array(
						'name'  => 'Field 2 Name',
						'value' => 'Field 2 Value',
					),
				),
			),
		);

		$actual = wp_privacy_generate_personal_data_export_group_html( $data, 'test-data-group', 2 );

		$this->assertContains( '<h2 id="test-data-group-test-data-group">Test Data Group', $actual );
		$this->assertContains( '<span class="count">(2)</span></h2>', $actual );
		$this->assertSame( 2, substr_count( $actual, '<table>' ) );
	}

	/**
	 * Test group count is not displayed for a single item.
	 *
	 * @ticket 46895
	 */
	public function test_group_html_generation_should_not_display_group_count_when_single_item() {
		$data = array(
			'group_label' => 'Test Data Group',
			'items'       => array(
				array(
					array(
						'name'  => 'Field 1 Name',
						'value' => 'Field 1 Value',
					),
				),
			),
		);

		$actual = wp_privacy_generate_personal_data_export_group_html( $data, 'test-data-group', 2 );

		$this->assertContains( '<h2 id="test-data-group-test-data-group">Test Data Group</h2>', $actual );
		$this->assertNotContains( '<span class="count">', $actual );
		$this->assertSame( 1, substr_count( $actual, '<table>' ) );
	}
}
