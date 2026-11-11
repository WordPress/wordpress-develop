<?php

/**
 * @group themes
 *
 * @covers ::twenty_twenty_one_continue_reading_link
 */
class Tests_Theme_TwentyTwentyOneContinueReadingLink extends WP_UnitTestCase {

	/**
	 * The names of the globals these tests overwrite.
	 *
	 * @var string[]
	 */
	private array $global_names = array( 'post', 'more' );

	/**
	 * The values of those globals before the current test ran, keyed by name.
	 *
	 * A name is absent when the global was not set.
	 *
	 * @var array<string, mixed>
	 */
	private array $original_globals = array();

	public function set_up() {
		parent::set_up();

		// `backupGlobals` is disabled for the suite, so these have to be restored by hand.
		foreach ( $this->global_names as $global_name ) {
			if ( array_key_exists( $global_name, $GLOBALS ) ) {
				$this->original_globals[ $global_name ] = $GLOBALS[ $global_name ];
			}
		}

		$template_functions = WP_CONTENT_DIR . '/themes/twentytwentyone/inc/template-functions.php';
		if ( ! file_exists( $template_functions ) ) {
			$this->markTestSkipped( 'The Twenty Twenty-One theme is not installed.' );
		}
		require_once $template_functions;
	}

	public function tear_down() {
		foreach ( $this->global_names as $global_name ) {
			if ( array_key_exists( $global_name, $this->original_globals ) ) {
				$GLOBALS[ $global_name ] = $this->original_globals[ $global_name ];
			} else {
				unset( $GLOBALS[ $global_name ] );
			}
		}

		parent::tear_down();
	}

	private function set_up_post( string $content ): void {
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * @ticket 52683
	 */
	public function test_uses_custom_more_text_when_set() {
		$this->set_up_post( 'Teaser<!--more Read the full story-->Rest' );

		$link = twenty_twenty_one_continue_reading_link( '', 'Read the full story' );

		$this->assertStringContainsString( '>Read the full story <span class="screen-reader-text">', $link );
		$this->assertStringNotContainsString( 'Continue reading', $link );
	}

	/**
	 * @ticket 52683
	 */
	public function test_uses_theme_text_without_custom_more_text() {
		$this->set_up_post( 'Teaser<!--more-->Rest' );

		$link = twenty_twenty_one_continue_reading_link( '', twenty_twenty_one_continue_reading_text() );

		$this->assertStringContainsString( 'Continue reading', $link );
		$this->assertStringContainsString( 'class="more-link"', $link );
	}

	/**
	 * @ticket 52683
	 */
	public function test_escapes_custom_more_text() {
		$this->set_up_post( 'Teaser<!--more Tom & Jerry-->Rest' );

		$link = twenty_twenty_one_continue_reading_link( '', 'Tom & Jerry' );

		$this->assertStringContainsString( 'Tom &amp; Jerry', $link );
	}

	/**
	 * @ticket 52683
	 */
	public function test_the_content_shows_custom_more_text() {
		add_filter( 'the_content_more_link', 'twenty_twenty_one_continue_reading_link', 10, 2 );
		$GLOBALS['more'] = 0;
		$this->set_up_post( 'Teaser<!--more Read the full story-->Rest' );

		$content = get_the_content( twenty_twenty_one_continue_reading_text() );

		$this->assertStringContainsString( 'Read the full story', $content );
		$this->assertStringContainsString( 'more-link-container', $content );
	}
}
