<?php

/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_MediaElementjs extends WP_UnitTestCase {
	/**
	 * Test if the MediaElement.js Flash fallbacks have been re-added.
	 *
	 * MediaElement's Flash fallbacks were removed in WordPress 4.9.2 due to limited use cases and
	 * a history of security vulnerabilities. It's unlikely that there'll ever be a need to
	 * restore them in the future, and doing so could introduce security vulnerabilities. If you
	 * want to re-add them, please discuss that with the Security team first.
	 *
	 * @since 5.1.0
	 *
	 * @ticket 42720
	 */
	function test_exclusion_of_flash() {
		$mejs_folder = ABSPATH . WPINC . '/js/mediaelement';
		$js_files    = glob( $mejs_folder . '/*.js' );

		/*
		 * The path in $mejs_folder is hardcoded, so this is just a sanity check to make sure the
		 * correct directory is used, in case it gets renamed in the future.
		 */
		$this->assertGreaterThan( 0, count( $js_files ) );

		$mejs_directory_iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $mejs_folder ) );
		$mejs_swf_iterator       = new RegexIterator( $mejs_directory_iterator, '/\.swf$/i', RecursiveRegexIterator::GET_MATCH );

		// Make sure the Flash files haven't been re-added accidentally.
		$this->assertCount( 0, iterator_to_array( $mejs_swf_iterator ) );
	}
}
