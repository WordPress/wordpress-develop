<?php
/**
 * @group editor
 *
 * @covers ::wp_tinymce_inline_scripts
 */
class Tests_Editor_wpTinyMceInlineScripts extends WP_UnitTestCase {

	/**
	 * Tests that the function applies the `wp_editor_settings` filter
	 * and merges the resulting array with the rest of TinyMCE init settings.
	 *
	 * @ticket 61754
	 */
	public function test_wp_tinymce_inline_scripts_array_merge() {
		$merged_settings = array();

		add_filter(
			'wp_editor_settings',
			static function ( $settings ) {
				$settings['tinymce'] = array( 'wp_autoresize_on' => true );
				return $settings;
			}
		);

		add_filter(
			'tiny_mce_before_init',
			static function ( $tinymce_settings ) use ( &$merged_settings ) {
				$merged_settings = $tinymce_settings;
				return $tinymce_settings;
			}
		);

		wp_scripts();
		wp_tinymce_inline_scripts();

		$this->assertArrayHasKey( 'wp_autoresize_on', $merged_settings );
	}
}
