<?php

if ( ! class_exists( '_WP_Editors', false ) ) {
	require_once ABSPATH . WPINC . '/class-wp-editor.php';
}

/**
 * @group editor
 *
 * @coversDefaultClass _WP_Editors
 */
class Tests_Editor_wpEditors extends WP_UnitTestCase {

	/**
	 * @covers ::wp_link_query
	 */
	public function test_wp_link_query_returns_false_when_nothing_found() {
		$actual = _WP_Editors::wp_link_query( array( 's' => 'foobarbaz' ) );

		$this->assertFalse( $actual );
	}

	/**
	 * @covers ::wp_link_query
	 */
	public function test_wp_link_query_returns_search_results() {
		$post   = self::factory()->post->create_and_get( array( 'post_status' => 'publish' ) );
		$actual = _WP_Editors::wp_link_query( array( 's' => $post->post_title ) );

		$this->assertSameSets(
			array(
				array(
					'ID'        => $post->ID,
					'title'     => $post->post_title,
					'permalink' => get_permalink( $post->ID ),
					'info'      => mysql2date( __( 'Y/m/d' ), $post->post_date ),
				),
			),
			$actual
		);
	}

	/**
	 * @ticket 41825
	 *
	 * @covers ::wp_link_query
	 */
	public function test_wp_link_query_returns_filtered_result_when_nothing_found() {
		add_filter( 'wp_link_query', array( $this, 'wp_link_query_callback' ) );
		$actual = _WP_Editors::wp_link_query( array( 's' => 'foobarbaz' ) );
		remove_filter( 'wp_link_query', array( $this, 'wp_link_query_callback' ) );

		$this->assertSameSets(
			array(
				array(
					'ID'        => 123,
					'title'     => 'foo',
					'permalink' => 'bar',
					'info'      => 'baz',
				),
			),
			$actual
		);
	}

	/**
	 * @covers ::wp_link_query
	 */
	public function test_wp_link_query_returns_filtered_search_results() {
		$post = self::factory()->post->create_and_get( array( 'post_status' => 'publish' ) );

		add_filter( 'wp_link_query', array( $this, 'wp_link_query_callback' ) );
		$actual = _WP_Editors::wp_link_query( array( 's' => $post->post_title ) );
		remove_filter( 'wp_link_query', array( $this, 'wp_link_query_callback' ) );

		$this->assertSameSets(
			array(
				array(
					'ID'        => $post->ID,
					'title'     => $post->post_title,
					'permalink' => get_permalink( $post->ID ),
					'info'      => mysql2date( __( 'Y/m/d' ), $post->post_date ),
				),
				array(
					'ID'        => 123,
					'title'     => 'foo',
					'permalink' => 'bar',
					'info'      => 'baz',
				),
			),
			$actual
		);
	}

	public function wp_link_query_callback( $results ) {
		return array_merge(
			$results,
			array(
				array(
					'ID'        => 123,
					'title'     => 'foo',
					'permalink' => 'bar',
					'info'      => 'baz',
				),
			)
		);
	}

	/**
	 * Tests that buttons.css is force-printed when quicktags is enabled in wp_editor().
	 *
	 * @ticket 64841
	 *
	 * @covers ::editor
	 */
	public function test_wp_editor_prints_buttons_style_when_quicktags_enabled() {
		$old_wp_styles        = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = new WP_Styles();
		wp_default_styles( $GLOBALS['wp_styles'] );

		$output = get_echo(
			'wp_editor',
			array(
				'',
				'test_editor_quicktags',
				array(
					'quicktags' => true,
				),
			)
		);

		$GLOBALS['wp_styles'] = $old_wp_styles;

		$this->assertStringContainsString( "id='buttons-css'", $output );
	}

	/**
	 * Tests that buttons.css is not force-printed when quicktags is disabled in wp_editor().
	 *
	 * @ticket 64841
	 *
	 * @covers ::editor
	 */
	public function test_wp_editor_does_not_print_buttons_style_when_quicktags_disabled() {
		$old_wp_styles        = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = new WP_Styles();
		wp_default_styles( $GLOBALS['wp_styles'] );

		$output = get_echo(
			'wp_editor',
			array(
				'',
				'test_editor_no_quicktags',
				array(
					'quicktags'     => false,
					'media_buttons' => false,
				),
			)
		);

		$GLOBALS['wp_styles'] = $old_wp_styles;

		$this->assertStringNotContainsString( "id='buttons-css'", $output );
	}
}
