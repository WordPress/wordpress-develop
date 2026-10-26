<?php

/**
 * Tests for wp_kses_hook().
 *
 * @group kses
 *
 * @covers ::wp_kses_hook
 */
class Tests_Kses_wpKsesHook extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_content_unchanged_without_filters() {
		remove_all_filters( 'pre_kses' );

		$content = '<p>Hello</p>';

		$this->assertSame(
			$content,
			wp_kses_hook( $content, 'post', wp_allowed_protocols() )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_pre_kses_filter_can_modify_content() {
		add_filter(
			'pre_kses',
			static function ( $content ) {
				return 'filtered:' . $content;
			}
		);

		$this->assertSame(
			'filtered:<p>Hello</p>',
			wp_kses_hook( '<p>Hello</p>', 'post', wp_allowed_protocols() )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_pre_kses_filter_receives_context_arguments() {
		$captured = null;

		add_filter(
			'pre_kses',
			static function ( $content, $allowed_html, $allowed_protocols ) use ( &$captured ) {
				$captured = array( $allowed_html, $allowed_protocols );
				return $content;
			},
			10,
			3
		);

		$protocols = array( 'http', 'https' );
		wp_kses_hook( 'content', 'data', $protocols );

		$this->assertSame( array( 'data', $protocols ), $captured );
	}
}
