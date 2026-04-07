<?php

/**
 * @group admin
 */
class Tests_Admin_IncludesMisc extends WP_UnitTestCase {

	/**
	 * @covers ::url_shorten
	 */
	public function test_shorten_url() {
		$tests = array(
			'wordpress\.org/about/philosophy'
				=> 'wordpress\.org/about/philosophy',     // No longer strips slashes.
			'wordpress.org/about/philosophy'
				=> 'wordpress.org/about/philosophy',
			'http://wordpress.org/about/philosophy/'
				=> 'wordpress.org/about/philosophy',      // Remove http, trailing slash.
			'http://www.wordpress.org/about/philosophy/'
				=> 'wordpress.org/about/philosophy',      // Remove http, www.
			'http://wordpress.org/about/philosophy/#box'
				=> 'wordpress.org/about/philosophy/#box',      // Don't shorten 35 characters.
			'http://wordpress.org/about/philosophy/#decisions'
				=> 'wordpress.org/about/philosophy/#&hellip;', // Shorten to 32 if > 35 after cleaning.
		);
		foreach ( $tests as $k => $v ) {
			$this->assertSame( $v, url_shorten( $k ) );
		}
	}

	/**
	 * Tests that _wp_sort_file_tree() places folders before files, both alphabetically.
	 *
	 * @ticket 47544
	 *
	 * @covers ::_wp_sort_file_tree
	 */
	public function test_wp_sort_file_tree_folders_before_files() {
		$tree = array(
			'readme.txt'    => 'plugin/readme.txt',
			'assets'        => array(
				'logo.png' => 'plugin/assets/logo.png',
			),
			'composer.json' => 'plugin/composer.json',
			'classes'       => array(
				'Foo.php' => 'plugin/classes/Foo.php',
			),
		);

		$sorted = _wp_sort_file_tree( $tree );
		$keys   = array_keys( $sorted );

		$this->assertSame( 0, array_search( 'assets', $keys, true ), 'assets folder should be first.' );
		$this->assertSame( 1, array_search( 'classes', $keys, true ), 'classes folder should be second.' );
		$this->assertSame( 2, array_search( 'composer.json', $keys, true ), 'composer.json file should be third.' );
		$this->assertSame( 3, array_search( 'readme.txt', $keys, true ), 'readme.txt file should be last.' );
	}

	/**
	 * Tests that _wp_sort_file_tree() sorts recursively within subdirectories.
	 *
	 * @ticket 47544
	 *
	 * @covers ::_wp_sort_file_tree
	 */
	public function test_wp_sort_file_tree_sorts_recursively() {
		$tree = array(
			'src' => array(
				'zebra.php' => 'plugin/src/zebra.php',
				'inc'       => array(
					'b.php' => 'plugin/src/inc/b.php',
					'a.php' => 'plugin/src/inc/a.php',
				),
				'apple.php' => 'plugin/src/apple.php',
			),
		);

		$sorted   = _wp_sort_file_tree( $tree );
		$src_keys = array_keys( $sorted['src'] );

		$this->assertSame( 'inc', $src_keys[0], 'inc folder should be first inside src/.' );
		$this->assertSame( 'apple.php', $src_keys[1], 'apple.php should be before zebra.php.' );
		$this->assertSame( 'zebra.php', $src_keys[2], 'zebra.php should be last inside src/.' );

		$inc_keys = array_keys( $sorted['src']['inc'] );
		$this->assertSame( array( 'a.php', 'b.php' ), $inc_keys );
	}

	/**
	 * Tests that wp_make_plugin_file_tree() places the main plugin file first,
	 * then folders, then files, all alphabetically.
	 *
	 * @ticket 47544
	 *
	 * @covers ::wp_make_plugin_file_tree
	 */
	public function test_wp_make_plugin_file_tree_main_file_first_then_folders_then_files() {
		$plugin_files = array(
			'my-plugin/my-plugin.php',
			'my-plugin/readme.txt',
			'my-plugin/assets/logo.png',
			'my-plugin/classes/Foo.php',
			'my-plugin/composer.json',
		);

		$tree = wp_make_plugin_file_tree( $plugin_files );
		$keys = array_keys( $tree );

		$this->assertSame( 'my-plugin.php', $keys[0], 'Main plugin file should be first.' );
		$this->assertSame( 'assets', $keys[1], 'assets folder should come before files.' );
		$this->assertSame( 'classes', $keys[2], 'classes folder should come before files.' );
		$this->assertSame( 'composer.json', $keys[3], 'composer.json should be sorted alphabetically among files.' );
		$this->assertSame( 'readme.txt', $keys[4], 'readme.txt should be last.' );
	}

	/**
	 * Tests that wp_make_theme_file_tree() places style.css first, functions.php
	 * second, then folders, then files alphabetically.
	 *
	 * @ticket 47544
	 *
	 * @covers ::wp_make_theme_file_tree
	 */
	public function test_wp_make_theme_file_tree_main_files_first_then_folders_then_files() {
		$allowed_files = array(
			'readme.txt'     => '/theme/readme.txt',
			'inc/extras.php' => '/theme/inc/extras.php',
			'functions.php'  => '/theme/functions.php',
			'style.css'      => '/theme/style.css',
			'404.php'        => '/theme/404.php',
		);

		$tree = wp_make_theme_file_tree( $allowed_files );
		$keys = array_keys( $tree );

		$this->assertSame( 'style.css', $keys[0], 'style.css should be first.' );
		$this->assertSame( 'functions.php', $keys[1], 'functions.php should be second.' );
		$this->assertSame( 'inc', $keys[2], 'inc folder should come before loose files.' );
		$this->assertSame( '404.php', $keys[3], '404.php should be sorted alphabetically among files.' );
		$this->assertSame( 'readme.txt', $keys[4], 'readme.txt should be last.' );
	}

	/**
	 * @ticket 59520
	 */
	public function test_new_admin_email_subject_filter() {
		// Default value.
		$mailer = tests_retrieve_phpmailer_instance();
		update_option_new_admin_email( 'old@example.com', 'new@example.com' );
		$this->assertSame( '[Test Blog] New Admin Email Address', $mailer->get_sent()->subject );

		// Filtered value.
		add_filter(
			'new_admin_email_subject',
			function () {
				return 'Filtered Admin Email Address';
			},
			10,
			1
		);

		$mailer->mock_sent = array();

		$mailer = tests_retrieve_phpmailer_instance();
		update_option_new_admin_email( 'old@example.com', 'new@example.com' );
		$this->assertSame( 'Filtered Admin Email Address', $mailer->get_sent()->subject );
	}
}
