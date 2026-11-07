<?php

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';

/**
 * Tests for the small meta boxes of the post editing screen.
 *
 * @group admin
 */
class Tests_Admin_includesMetaBoxes extends WP_UnitTestCase {

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original post formats of the theme.
	 *
	 * @var mixed
	 */
	private $original_post_formats;

	/**
	 * Creates the administrator.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role'         => 'administrator',
				'user_login'   => 'metaadmin',
				'display_name' => 'Meta Admin',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		$this->original_post_formats = $GLOBALS['_wp_theme_features']['post-formats'] ?? null;
	}

	public function tear_down() {
		if ( null === $this->original_post_formats ) {
			unset( $GLOBALS['_wp_theme_features']['post-formats'] );
		} else {
			$GLOBALS['_wp_theme_features']['post-formats'] = $this->original_post_formats;
		}

		unregister_taxonomy( 'media_group' );

		parent::tear_down();
	}

	/**
	 * Returns the output of a meta box.
	 *
	 * @param callable $callback The function of the meta box.
	 * @param mixed    ...$args  The arguments.
	 * @return string The output.
	 */
	private function get_output( $callback, ...$args ) {
		ob_start();
		$callback( ...$args );

		return ob_get_clean();
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $query ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the values of the post format radio buttons, with whether they are checked.
	 *
	 * @param string $markup The markup.
	 * @return array The values and whether they are checked.
	 */
	private function get_formats( $markup ) {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$formats   = array();

		while ( $processor->next_tag( array( 'class_name' => 'post-format' ) ) ) {
			$formats[ $processor->get_attribute( 'value' ) ] = null !== $processor->get_attribute( 'checked' );
		}

		return $formats;
	}

	/**
	 * Tests the post formats of the theme.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_format_meta_box
	 */
	public function test_post_format_meta_box_lists_the_formats_of_the_theme() {
		add_theme_support( 'post-formats', array( 'aside', 'gallery' ) );

		$post   = get_post( self::factory()->post->create() );
		$output = $this->get_output( 'post_format_meta_box', $post, array() );

		$this->assertSame(
			array(
				'0'       => true,
				'aside'   => false,
				'gallery' => false,
			),
			$this->get_formats( $output )
		);
		$this->assertStringContainsString( '<div id="post-formats-select">', $output );
		$this->assertStringContainsString( 'class="post-format-icon post-format-standard">Standard</label>', $output );
		$this->assertStringContainsString( 'class="post-format-icon post-format-aside">Aside</label>', $output );
		$this->assertStringContainsString( 'class="post-format-icon post-format-gallery">Gallery</label>', $output );
	}

	/**
	 * Tests the current post format.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_format_meta_box
	 */
	public function test_post_format_meta_box_checks_the_format_of_the_post() {
		add_theme_support( 'post-formats', array( 'aside', 'gallery' ) );

		$post_id = self::factory()->post->create();

		set_post_format( $post_id, 'gallery' );

		$formats = $this->get_formats( $this->get_output( 'post_format_meta_box', get_post( $post_id ), array() ) );

		$this->assertSame(
			array(
				'0'       => false,
				'aside'   => false,
				'gallery' => true,
			),
			$formats
		);
	}

	/**
	 * Tests that a format of the theme is not listed twice when it is the current one.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_format_meta_box
	 */
	public function test_post_format_meta_box_does_not_list_a_supported_format_twice() {
		add_theme_support( 'post-formats', array( 'aside', 'gallery' ) );

		$post_id = self::factory()->post->create();

		set_post_format( $post_id, 'aside' );

		$output = $this->get_output( 'post_format_meta_box', get_post( $post_id ), array() );

		$this->assertSame( 3, substr_count( $output, 'name="post_format"' ) );
	}

	/**
	 * Tests the format of the post that the theme does not support.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_format_meta_box
	 */
	public function test_post_format_meta_box_adds_the_format_that_the_theme_does_not_support() {
		add_theme_support( 'post-formats', array( 'aside' ) );

		$post_id = self::factory()->post->create();

		set_post_format( $post_id, 'video' );

		$formats = $this->get_formats( $this->get_output( 'post_format_meta_box', get_post( $post_id ), array() ) );

		$this->assertSame(
			array(
				'0'     => false,
				'aside' => false,
				'video' => true,
			),
			$formats
		);
	}

	/**
	 * Tests that nothing is output for a theme or a post type without the post formats.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_format_meta_box
	 */
	public function test_post_format_meta_box_is_empty_without_the_support() {
		$post = get_post( self::factory()->post->create() );
		$page = get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) );

		unset( $GLOBALS['_wp_theme_features']['post-formats'] );

		$this->assertSame( '', $this->get_output( 'post_format_meta_box', $post, array() ), 'The theme has no support.' );

		add_theme_support( 'post-formats', array( 'aside' ) );

		$this->assertSame( '', $this->get_output( 'post_format_meta_box', $page, array() ), 'The page has no support.' );
	}

	/**
	 * Tests the tags meta box.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_tags_meta_box
	 */
	public function test_post_tags_meta_box() {
		$post_id = self::factory()->post->create();

		wp_set_object_terms( $post_id, array( 'alpha', 'beta' ), 'post_tag' );

		$output   = $this->get_output( 'post_tags_meta_box', get_post( $post_id ), array() );
		$textarea = $this->get_element( $output, 'textarea' );

		$this->assertSame( 'post_tag', $this->get_element( $output, array( 'class_name' => 'tagsdiv' ) )['id'] );
		$this->assertSame( 'tax_input[post_tag]', $textarea['name'] );
		$this->assertSame( 'tax-input-post_tag', $textarea['id'] );
		$this->assertArrayNotHasKey( 'disabled', $textarea );
		$this->assertStringContainsString( '>alpha, beta</textarea>', $output );
		$this->assertStringContainsString( 'name="newtag[post_tag]"', $output );
		$this->assertStringContainsString( 'class="button tagadd" value="Add"', $output );
		$this->assertStringContainsString( 'id="link-post_tag"', $output );
		$this->assertStringContainsString( 'Separate tags with commas', $output );
		$this->assertStringContainsString( 'Choose from the most used tags', $output );
		$this->assertStringNotContainsString( 'No tags', $output );
	}

	/**
	 * Tests the tags meta box with the taxonomy of the arguments.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_tags_meta_box
	 */
	public function test_post_tags_meta_box_uses_the_taxonomy_of_the_arguments() {
		register_taxonomy( 'media_group', 'post', array( 'label' => 'Groups' ) );

		$post_id = self::factory()->post->create();

		wp_set_object_terms( $post_id, array( 'gamma' ), 'media_group' );

		$output = $this->get_output( 'post_tags_meta_box', get_post( $post_id ), array( 'args' => array( 'taxonomy' => 'media_group' ) ) );

		$this->assertSame( 'media_group', $this->get_element( $output, array( 'class_name' => 'tagsdiv' ) )['id'] );
		$this->assertSame( 'tax_input[media_group]', $this->get_element( $output, 'textarea' )['name'] );
		$this->assertStringContainsString( '>gamma</textarea>', $output );

		$ignored = $this->get_output( 'post_tags_meta_box', get_post( $post_id ), array( 'args' => 'media_group' ) );

		$this->assertSame( 'post_tag', $this->get_element( $ignored, array( 'class_name' => 'tagsdiv' ) )['id'], 'Arguments that are not an array should be ignored.' );
	}

	/**
	 * Tests the tags meta box for a user who cannot assign terms.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_tags_meta_box
	 */
	public function test_post_tags_meta_box_for_a_user_who_cannot_assign_terms() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$output = $this->get_output( 'post_tags_meta_box', get_post( $post_id ), array() );

		$this->assertArrayHasKey( 'disabled', $this->get_element( $output, 'textarea' ) );
		$this->assertStringNotContainsString( 'newtag[post_tag]', $output );
		$this->assertStringNotContainsString( 'tagcloud-link', $output );
		$this->assertStringContainsString( '<p>No tags</p>', $output );

		wp_set_object_terms( $post_id, array( 'alpha' ), 'post_tag' );

		$this->assertStringNotContainsString( 'No tags', $this->get_output( 'post_tags_meta_box', get_post( $post_id ), array() ), 'The message should only be shown without tags.' );
	}

	/**
	 * Tests the excerpt meta box.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_excerpt_meta_box
	 */
	public function test_post_excerpt_meta_box() {
		$post = get_post( self::factory()->post->create( array( 'post_excerpt' => 'A hand-crafted summary.' ) ) );

		$output   = $this->get_output( 'post_excerpt_meta_box', $post );
		$textarea = $this->get_element( $output, 'textarea' );

		$this->assertSame( 'excerpt', $textarea['name'] );
		$this->assertSame( 'excerpt', $textarea['id'] );
		$this->assertStringContainsString( '>A hand-crafted summary.</textarea>', $output );
		$this->assertStringContainsString( '<label class="screen-reader-text" for="excerpt">', $output );
		$this->assertStringContainsString( '<a href="https://wordpress.org/documentation/article/what-is-an-excerpt-classic-editor/">Learn more about manual excerpts</a>', $output );
	}

	/**
	 * Tests the trackback meta box.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_trackback_meta_box
	 */
	public function test_post_trackback_meta_box() {
		$post = get_post(
			self::factory()->post->create(
				array(
					'to_ping' => "https://example.org/one/\nhttps://example.org/two/",
				)
			)
		);

		$output = $this->get_output( 'post_trackback_meta_box', $post );
		$input  = $this->get_element( $output, 'input' );

		$this->assertSame( 'trackback_url', $input['name'] );
		$this->assertSame( 'https://example.org/one/ https://example.org/two/', $input['value'], 'The URLs should be separated by spaces.' );
		$this->assertSame( 'trackback-url-desc', $input['aria-describedby'] );
		$this->assertStringContainsString( 'Send trackbacks to:', $output );
		$this->assertStringContainsString( 'Separate multiple URLs with spaces', $output );
		$this->assertStringContainsString( '<a href="https://wordpress.org/documentation/article/introduction-to-blogging/#comments">pingbacks</a>', $output );
		$this->assertStringNotContainsString( 'Already pinged', $output );
	}

	/**
	 * Tests the escaping of the URLs to ping.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_trackback_meta_box
	 */
	public function test_post_trackback_meta_box_escapes_the_urls_to_ping() {
		$post          = get_post( self::factory()->post->create() );
		$post->to_ping = "https://example.org/?a=\"b\"\nhttps://example.org/<c>";

		$input = $this->get_element( $this->get_output( 'post_trackback_meta_box', $post ), 'input' );

		$this->assertSame( 'https://example.org/?a="b" https://example.org/<c>', $input['value'] );
	}

	/**
	 * Tests the URLs that were pinged.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_trackback_meta_box
	 */
	public function test_post_trackback_meta_box_lists_the_pinged_urls() {
		$post         = get_post( self::factory()->post->create() );
		$post->pinged = "\nhttps://example.org/one/\nhttps://example.org/<two>/\n";

		$output = $this->get_output( 'post_trackback_meta_box', $post );

		$this->assertStringContainsString( '<p>Already pinged:</p><ul>', $output );
		$this->assertStringContainsString( '<li>https://example.org/one/</li>', $output );
		$this->assertStringContainsString( '<li>https://example.org/&lt;two&gt;/</li>', $output );
		$this->assertSame( 2, substr_count( $output, '<li>' ) );
		$this->assertStringEndsWith( '</ul>', $output );
	}

	/**
	 * Tests the comment status meta box.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_comment_status_meta_box
	 *
	 * @dataProvider data_comment_statuses
	 *
	 * @param string $comment_status The comment status of the post.
	 * @param string $ping_status    The ping status of the post.
	 */
	public function test_post_comment_status_meta_box( $comment_status, $ping_status ) {
		$post = get_post(
			self::factory()->post->create(
				array(
					'comment_status' => $comment_status,
					'ping_status'    => $ping_status,
				)
			)
		);

		$output = $this->get_output( 'post_comment_status_meta_box', $post );

		$this->assertSame( 'hidden', $this->get_element( $output, 'input' )['type'] );
		$this->assertSame( 'advanced_view', $this->get_element( $output, 'input' )['name'] );
		$processor = new WP_HTML_Tag_Processor( $output );
		$checked   = array();

		while ( $processor->next_tag( 'input' ) ) {
			if ( 'checkbox' === $processor->get_attribute( 'type' ) ) {
				$checked[ $processor->get_attribute( 'id' ) ] = null !== $processor->get_attribute( 'checked' );
			}
		}

		$this->assertSame(
			array(
				'comment_status' => 'open' === $comment_status,
				'ping_status'    => 'open' === $ping_status,
			),
			$checked
		);
		$this->assertStringContainsString( 'Allow comments', $output );
		$this->assertStringContainsString( '<a href="https://wordpress.org/documentation/article/introduction-to-blogging/#managing-comments">trackbacks and pingbacks</a>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_comment_statuses() {
		return array(
			'both open'      => array( 'open', 'open' ),
			'both closed'    => array( 'closed', 'closed' ),
			'comments only'  => array( 'open', 'closed' ),
			'pingbacks only' => array( 'closed', 'open' ),
		);
	}

	/**
	 * Tests the action of the comment status meta box.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_comment_status_meta_box
	 */
	public function test_post_comment_status_meta_box_runs_the_options_action() {
		$post     = get_post( self::factory()->post->create() );
		$received = null;

		add_action(
			'post_comment_status_meta_box-options',
			static function ( $action_post ) use ( &$received ) {
				$received = $action_post;

				echo '<span id="extra-option"></span>';
			}
		);

		$output = $this->get_output( 'post_comment_status_meta_box', $post );

		$this->assertSame( $post, $received );
		$this->assertStringContainsString( '<span id="extra-option"></span>', $output );
	}

	/**
	 * Tests the slug meta box.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_slug_meta_box
	 */
	public function test_post_slug_meta_box() {
		$post = get_post( self::factory()->post->create( array( 'post_name' => 'the-slug' ) ) );

		$output = $this->get_output( 'post_slug_meta_box', $post );
		$input  = $this->get_element( $output, 'input' );

		$this->assertSame( 'post_name', $input['name'] );
		$this->assertSame( 'post_name', $input['id'] );
		$this->assertSame( 'the-slug', $input['value'] );
		$this->assertSame( 'large-text', $input['class'] );
		$this->assertStringContainsString( '<label class="screen-reader-text" for="post_name">', $output );
	}

	/**
	 * Tests the filter of the slug that can be edited.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_slug_meta_box
	 */
	public function test_post_slug_meta_box_filters_and_escapes_the_slug() {
		$post     = get_post( self::factory()->post->create( array( 'post_name' => 'the-slug' ) ) );
		$received = null;

		add_filter(
			'editable_slug',
			static function ( $slug, $filtered_post ) use ( &$received ) {
				$received = array( $slug, $filtered_post );

				return 'a "quoted" slug';
			},
			10,
			2
		);

		$input = $this->get_element( $this->get_output( 'post_slug_meta_box', $post ), 'input' );

		$this->assertSame( array( 'the-slug', $post ), $received );
		$this->assertSame( 'a "quoted" slug', $input['value'] );
	}

	/**
	 * Tests the author meta box.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_author_meta_box
	 */
	public function test_post_author_meta_box() {
		$author_id     = self::factory()->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'theauthor',
				'display_name' => 'The Author',
			)
		);
		$subscriber_id = self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_login'   => 'thesubscriber',
				'display_name' => 'The Subscriber',
			)
		);
		$post          = get_post( self::factory()->post->create( array( 'post_author' => $author_id ) ) );

		$output = $this->get_output( 'post_author_meta_box', $post );
		$select = $this->get_element( $output, 'select' );

		$this->assertSame( 'post_author_override', $select['name'] );
		$this->assertStringContainsString( '<label class="screen-reader-text" for="post_author_override">', $output );
		$this->assertStringContainsString( "value='$author_id' selected='selected'>The Author (theauthor)</option>", $output );
		$this->assertStringContainsString( "value='" . self::$admin_id . "'>Meta Admin (metaadmin)</option>", $output );
		$this->assertStringNotContainsString( "value='$subscriber_id'", $output, 'A user who cannot edit posts should not be listed.' );
	}

	/**
	 * Tests that the author of the post is listed although the user cannot edit posts.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_author_meta_box
	 */
	public function test_post_author_meta_box_includes_the_selected_author() {
		$subscriber_id = self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_login'   => 'thesubscriber',
				'display_name' => 'The Subscriber',
			)
		);
		$post          = get_post( self::factory()->post->create( array( 'post_author' => $subscriber_id ) ) );

		$this->assertStringContainsString(
			"value='$subscriber_id' selected='selected'>The Subscriber (thesubscriber)</option>",
			$this->get_output( 'post_author_meta_box', $post )
		);
	}

	/**
	 * Tests that the current user is selected for a new post.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_author_meta_box
	 */
	public function test_post_author_meta_box_selects_the_current_user_for_a_new_post() {
		$GLOBALS['user_ID'] = self::$admin_id;

		$post            = new WP_Post( new stdClass() );
		$post->post_type = 'post';

		$this->assertStringContainsString(
			"value='" . self::$admin_id . "' selected='selected'>Meta Admin (metaadmin)</option>",
			$this->get_output( 'post_author_meta_box', $post )
		);
	}

	/**
	 * Tests the users who can be the author of a page.
	 *
	 * @ticket 66604
	 *
	 * @covers ::post_author_meta_box
	 */
	public function test_post_author_meta_box_uses_the_capability_of_the_post_type() {
		$editor_id = self::factory()->user->create(
			array(
				'role'         => 'editor',
				'user_login'   => 'theeditor',
				'display_name' => 'The Editor',
			)
		);
		$author_id = self::factory()->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'theauthor',
				'display_name' => 'The Author',
			)
		);

		$page   = get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		$output = $this->get_output( 'post_author_meta_box', $page );

		$this->assertStringContainsString( "value='$editor_id'", $output );
		$this->assertStringNotContainsString( "value='$author_id'", $output, 'An author cannot edit pages.' );
	}
}
