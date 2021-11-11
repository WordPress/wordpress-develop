<?php

require_once __DIR__ . '/base.php';

/**
 * @group import
 */
class Tests_Import_Import extends WP_Import_UnitTestCase {
	public function set_up() {
		parent::set_up();

		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
			define( 'WP_LOAD_IMPORTERS', true );
		}

		add_filter( 'import_allow_create_users', '__return_true' );

		if ( ! file_exists( DIR_TESTDATA . '/plugins/wordpress-importer/wordpress-importer.php' ) ) {
			$this->fail( 'This test requires the WordPress Importer plugin to be installed in the test suite. See: https://make.wordpress.org/core/handbook/contribute/git/#unit-tests' );
		}

		require_once DIR_TESTDATA . '/plugins/wordpress-importer/wordpress-importer.php';

		global $wpdb;
		// Crude but effective: make sure there's no residual data in the main tables.
		foreach ( array( 'posts', 'postmeta', 'comments', 'terms', 'term_taxonomy', 'term_relationships', 'users', 'usermeta' ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM {$wpdb->$table}" );
		}
	}

	public function test_small_import() {
		global $wpdb;

		$authors = array(
			'admin'  => false,
			'editor' => false,
			'author' => false,
		);
		$this->_import_wp( DIR_TESTDATA . '/export/small-export.xml', $authors );

		// Ensure that authors were imported correctly.
		$user_count = count_users();
		$this->assertSame( 3, $user_count['total_users'] );
		$admin = get_user_by( 'login', 'admin' );
		$this->assertSame( 'admin', $admin->user_login );
		$this->assertSame( 'local@host.null', $admin->user_email );
		$editor = get_user_by( 'login', 'editor' );
		$this->assertSame( 'editor', $editor->user_login );
		$this->assertSame( 'editor@example.org', $editor->user_email );
		$this->assertSame( 'FirstName', $editor->user_firstname );
		$this->assertSame( 'LastName', $editor->user_lastname );
		$author = get_user_by( 'login', 'author' );
		$this->assertSame( 'author', $author->user_login );
		$this->assertSame( 'author@example.org', $author->user_email );

		// Check that terms were imported correctly.
		$this->assertEquals( 30, wp_count_terms( array( 'taxonomy' => 'category' ) ) );
		$this->assertEquals( 3, wp_count_terms( array( 'taxonomy' => 'post_tag' ) ) );
		$foo = get_term_by( 'slug', 'foo', 'category' );
		$this->assertSame( 0, $foo->parent );
		$bar     = get_term_by( 'slug', 'bar', 'category' );
		$foo_bar = get_term_by( 'slug', 'foo-bar', 'category' );
		$this->assertSame( $bar->term_id, $foo_bar->parent );

		// Check that posts/pages were imported correctly.
		$post_count = wp_count_posts( 'post' );
		$this->assertEquals( 5, $post_count->publish );
		$this->assertEquals( 1, $post_count->private );
		$page_count = wp_count_posts( 'page' );
		$this->assertEquals( 4, $page_count->publish );
		$this->assertEquals( 1, $page_count->draft );
		$comment_count = wp_count_comments();
		$this->assertSame( 1, $comment_count->total_comments );

		$posts = get_posts(
			array(
				'numberposts' => 20,
				'post_type'   => 'any',
				'post_status' => 'any',
				'orderby'     => 'ID',
			)
		);
		$this->assertCount( 11, $posts );

		$post = $posts[0];
		$this->assertSame( 'Many Categories', $post->post_title );
		$this->assertSame( 'many-categories', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 27, $cats );

		$post = $posts[1];
		$this->assertSame( 'Non-standard post format', $post->post_title );
		$this->assertSame( 'non-standard-post-format', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 1, $cats );
		$this->assertTrue( has_post_format( 'aside', $post->ID ) );

		$post = $posts[2];
		$this->assertSame( 'Top-level Foo', $post->post_title );
		$this->assertSame( 'top-level-foo', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
		$this->assertCount( 1, $cats );
		$this->assertSame( 'foo', $cats[0]->slug );

		$post = $posts[3];
		$this->assertSame( 'Foo-child', $post->post_title );
		$this->assertSame( 'foo-child', $post->post_name );
		$this->assertEquals( $editor->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
		$this->assertCount( 1, $cats );
		$this->assertSame( 'foo-bar', $cats[0]->slug );

		$post = $posts[4];
		$this->assertSame( 'Private Post', $post->post_title );
		$this->assertSame( 'private-post', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 1, $cats );
		$tags = wp_get_post_tags( $post->ID );
		$this->assertCount( 3, $tags );
		$this->assertSame( 'tag1', $tags[0]->slug );
		$this->assertSame( 'tag2', $tags[1]->slug );
		$this->assertSame( 'tag3', $tags[2]->slug );

		$post = $posts[5];
		$this->assertSame( '1-col page', $post->post_title );
		$this->assertSame( '1-col-page', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'onecolumn-page.php', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[6];
		$this->assertSame( 'Draft Page', $post->post_title );
		$this->assertSame( '', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[7];
		$this->assertSame( 'Parent Page', $post->post_title );
		$this->assertSame( 'parent-page', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[8];
		$this->assertSame( 'Child Page', $post->post_title );
		$this->assertSame( 'child-page', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $posts[7]->ID, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[9];
		$this->assertSame( 'Sample Page', $post->post_title );
		$this->assertSame( 'sample-page', $post->post_name );
		$this->assertEquals( $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[10];
		$this->assertSame( 'Hello world!', $post->post_title );
		$this->assertSame( 'hello-world', $post->post_name );
		$this->assertEquals( $author->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 1, $cats );
	}

	public function test_double_import() {
		$authors = array(
			'admin'  => false,
			'editor' => false,
			'author' => false,
		);
		$this->_import_wp( DIR_TESTDATA . '/export/small-export.xml', $authors );
		$this->_import_wp( DIR_TESTDATA . '/export/small-export.xml', $authors );

		$user_count = count_users();
		$this->assertSame( 3, $user_count['total_users'] );
		$admin = get_user_by( 'login', 'admin' );
		$this->assertSame( 'admin', $admin->user_login );
		$this->assertSame( 'local@host.null', $admin->user_email );
		$editor = get_user_by( 'login', 'editor' );
		$this->assertSame( 'editor', $editor->user_login );
		$this->assertSame( 'editor@example.org', $editor->user_email );
		$this->assertSame( 'FirstName', $editor->user_firstname );
		$this->assertSame( 'LastName', $editor->user_lastname );
		$author = get_user_by( 'login', 'author' );
		$this->assertSame( 'author', $author->user_login );
		$this->assertSame( 'author@example.org', $author->user_email );

		$this->assertEquals( 30, wp_count_terms( array( 'taxonomy' => 'category' ) ) );
		$this->assertEquals( 3, wp_count_terms( array( 'taxonomy' => 'post_tag' ) ) );
		$foo = get_term_by( 'slug', 'foo', 'category' );
		$this->assertSame( 0, $foo->parent );
		$bar     = get_term_by( 'slug', 'bar', 'category' );
		$foo_bar = get_term_by( 'slug', 'foo-bar', 'category' );
		$this->assertSame( $bar->term_id, $foo_bar->parent );

		$post_count = wp_count_posts( 'post' );
		$this->assertEquals( 5, $post_count->publish );
		$this->assertEquals( 1, $post_count->private );
		$page_count = wp_count_posts( 'page' );
		$this->assertEquals( 4, $page_count->publish );
		$this->assertEquals( 1, $page_count->draft );
		$comment_count = wp_count_comments();
		$this->assertSame( 1, $comment_count->total_comments );
	}

	public function test_ordering_of_importers() {
		global $wp_importers;
		$_wp_importers = $wp_importers; // Preserve global state.
		$wp_importers  = array(
			'xyz1' => array( 'xyz1' ),
			'XYZ2' => array( 'XYZ2' ),
			'abc2' => array( 'abc2' ),
			'ABC1' => array( 'ABC1' ),
			'def1' => array( 'def1' ),
		);
		$this->assertSame(
			array(
				'ABC1' => array( 'ABC1' ),
				'abc2' => array( 'abc2' ),
				'def1' => array( 'def1' ),
				'xyz1' => array( 'xyz1' ),
				'XYZ2' => array( 'XYZ2' ),
			),
			get_importers()
		);
		$wp_importers = $_wp_importers; // Restore global state.
	}

	/**
	 * @ticket 21007
	 */
	public function test_slashes_should_not_be_stripped() {
		global $wpdb;

		$authors = array( 'admin' => false );
		$this->_import_wp( DIR_TESTDATA . '/export/slashes.xml', $authors );

		$alpha = get_term_by( 'slug', 'alpha', 'category' );
		$this->assertSame( 'a \"great\" category', $alpha->name );

		$tag1 = get_term_by( 'slug', 'tag1', 'post_tag' );
		$this->assertSame( "foo\'bar", $tag1->name );

		$posts = get_posts(
			array(
				'post_type'   => 'any',
				'post_status' => 'any',
			)
		);
		$this->assertSame( 'Slashes aren\\\'t \"cool\"', $posts[0]->post_content );
	}
}
