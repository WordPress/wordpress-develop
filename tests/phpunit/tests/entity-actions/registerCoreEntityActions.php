<?php
/**
 * Tests for the actions WordPress core registers through the entity actions API.
 *
 * @package WordPress
 * @subpackage Entity Actions
 *
 * @group entity-actions
 *
 * @covers ::register_core_entity_actions
 */
class Tests_Entity_Actions_RegisterCoreEntityActions extends WP_UnitTestCase {

	/**
	 * The script module of the actions of every post type.
	 */
	const POST_TYPE_SUPPORTS = '@wordpress/core-actions/post_type_supports';

	/**
	 * The script module of the actions of pages.
	 */
	const PAGE = '@wordpress/core-actions/page';

	/**
	 * The script module of the actions of patterns.
	 */
	const WP_BLOCK = '@wordpress/core-actions/wp_block';

	/**
	 * The script module of the actions of template parts.
	 */
	const WP_TEMPLATE_PART = '@wordpress/core-actions/wp_template_part';

	/**
	 * The actions every post type has, each checking which posts it applies
	 * to, in the order they are shown.
	 */
	const EVERY_POST_TYPE = array( 'restore', 'reset-post', 'delete-post', 'move-to-trash', 'permanently-delete' );

	/**
	 * The actions of the `page` module, for the users who can manage the
	 * site options, shown after the others.
	 */
	const PAGE_ACTIONS = array( 'set-as-homepage', 'set-as-posts-page' );

	/**
	 * Administrator user id.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Editor user id (lacks `edit_theme_options` and `manage_options`).
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Contributor user id (lacks `publish_posts` and `edit_pages`).
	 *
	 * @var int
	 */
	protected static $contributor_id;

	/**
	 * Creates shared users.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$contributor_id = $factory->user->create( array( 'role' => 'contributor' ) );
	}

	/**
	 * Deletes shared users.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$editor_id );
		self::delete_user( self::$contributor_id );
	}

	/**
	 * Sets up each test.
	 *
	 * The core actions are copied from the Gutenberg build, so the tests are
	 * skipped until the pinned build includes them.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'register_core_entity_actions' ) ) {
			$this->markTestSkipped( 'The pinned Gutenberg build does not include the core actions.' );
		}
	}

	/**
	 * Tears down each test.
	 *
	 * Resetting the registry drops the actions registered for the user and
	 * theme of the test; the next read fires `wp_entity_actions_api_init` again.
	 */
	public function tear_down() {
		unregister_post_type( 'test_book' );
		unregister_post_type( 'test_note' );
		unregister_post_type( 'test_hidden' );

		self::reset_registry();

		parent::tear_down();
	}

	/**
	 * Resets the registry: drops the singleton instance, so the next
	 * get_instance() creates an empty registry and its first read fires
	 * `wp_entity_actions_api_init` again.
	 */
	private static function reset_registry() {
		$instance = new ReflectionProperty( WP_Entity_Actions_Registry::class, 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$instance->setAccessible( true );
		}
		$instance->setValue( null, null );
	}

	/**
	 * Returns the ids of the actions registered for a post type, from a
	 * registry built anew for the current user and theme, as each request
	 * builds it.
	 *
	 * @param string $post_type The post type.
	 * @return string[] The ids of the actions, in registration order.
	 */
	private function get_action_ids( $post_type ) {
		self::reset_registry();
		return array_column( wp_get_registered_entity_actions( 'postType', $post_type ), 'id' );
	}

	/**
	 * The core actions are registered at priority 0, so a plugin hooking the
	 * action at the default priority sees them.
	 *
	 * @ticket 66343
	 */
	public function test_the_core_actions_are_registered_at_priority_0() {
		$this->assertSame( 0, has_action( 'wp_entity_actions_api_init', 'register_core_entity_actions' ) );
	}

	/**
	 * Pages are viewable and support revisions, titles, and page attributes,
	 * and the user can manage the site options: they have every action of the
	 * `post_type_supports` module, then the actions of the `page` module, in
	 * the order the actions are shown.
	 *
	 * @ticket 66343
	 */
	public function test_the_actions_of_pages() {
		wp_set_current_user( self::$admin_id );

		$ids = array_merge( array( 'view-post', 'view-post-revisions', 'rename-post', 'order-pages' ), self::EVERY_POST_TYPE );
		$this->assertSame( array_merge( $ids, self::PAGE_ACTIONS ), $this->get_action_ids( 'page' ), 'Pages have these actions, in this order.' );
		$this->assertSame(
			array(
				self::POST_TYPE_SUPPORTS => $ids,
				self::PAGE               => self::PAGE_ACTIONS,
			),
			array_column( wp_get_registered_entity_action_modules( 'postType', 'page' ), 'actions', 'id' ),
			'Each action of pages is registered with the module of its folder.'
		);
		$this->assertSame( array( 'core' ), array_unique( array_column( array_column( wp_get_registered_entity_actions( 'postType', 'page' ), 'origin' ), 'registeredBy' ) ), 'Every action is registered by core.' );
	}

	/**
	 * Posts do not support page attributes, so they cannot be ordered.
	 *
	 * @ticket 66343
	 */
	public function test_the_actions_of_posts() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array_merge( array( 'view-post', 'view-post-revisions', 'rename-post' ), self::EVERY_POST_TYPE ),
			$this->get_action_ids( 'post' ),
			'Posts have these actions, in this order.'
		);
	}

	/**
	 * Patterns are not viewable and are duplicated and exported by the
	 * actions of the `wp_block` module, placed among the others in the order
	 * the actions are shown.
	 *
	 * @ticket 66343
	 */
	public function test_the_actions_of_patterns() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array_merge( array( 'view-post-revisions', 'duplicate-pattern', 'rename-post', 'export-pattern' ), self::EVERY_POST_TYPE ),
			$this->get_action_ids( 'wp_block' ),
			'Patterns have these actions, in this order.'
		);
		$this->assertSame(
			array(
				self::POST_TYPE_SUPPORTS => array_merge( array( 'view-post-revisions', 'rename-post' ), self::EVERY_POST_TYPE ),
				self::WP_BLOCK           => array( 'duplicate-pattern', 'export-pattern' ),
			),
			array_column( wp_get_registered_entity_action_modules( 'postType', 'wp_block' ), 'actions', 'id' ),
			'The actions of patterns come from the `wp_block` module.'
		);
	}

	/**
	 * Templates are not duplicated: the editor creates them from the theme.
	 *
	 * @ticket 66343
	 */
	public function test_the_actions_of_templates() {
		wp_set_current_user( self::$admin_id );
		switch_theme( 'block-theme' );

		$this->assertSame(
			array_merge( array( 'view-post-revisions', 'rename-post' ), self::EVERY_POST_TYPE ),
			$this->get_action_ids( 'wp_template' ),
			'Templates have these actions, in this order.'
		);
	}

	/**
	 * Template parts are duplicated by the action of the `wp_template_part`
	 * module, with a block theme only.
	 *
	 * @ticket 66343
	 */
	public function test_template_parts_are_only_duplicated_with_a_block_theme() {
		wp_set_current_user( self::$admin_id );

		switch_theme( 'block-theme' );
		$this->assertSame(
			array_merge( array( 'view-post-revisions', 'duplicate-template-part', 'rename-post' ), self::EVERY_POST_TYPE ),
			$this->get_action_ids( 'wp_template_part' ),
			'A block theme can duplicate template parts.'
		);
		$this->assertSame(
			array( 'duplicate-template-part' ),
			array_column( wp_get_registered_entity_action_modules( 'postType', 'wp_template_part' ), 'actions', 'id' )[ self::WP_TEMPLATE_PART ],
			'The action comes from the `wp_template_part` module.'
		);

		switch_theme( 'default' );
		$this->assertNotContains( 'duplicate-template-part', $this->get_action_ids( 'wp_template_part' ), 'A classic theme cannot.' );
	}

	/**
	 * The "Duplicate…" action of the other post types is only registered by
	 * the Gutenberg plugin.
	 *
	 * @ticket 66343
	 */
	public function test_posts_are_not_duplicated() {
		wp_set_current_user( self::$admin_id );

		foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $post_type ) {
			$this->assertNotContains( 'duplicate-post', $this->get_action_ids( $post_type ), "The $post_type post type has no duplicate action." );
		}
	}

	/**
	 * The duplicate actions are only registered for the users who can create
	 * the posts they duplicate into: the registry is built for the current
	 * user.
	 *
	 * @ticket 66343
	 */
	public function test_the_duplicate_actions_depend_on_the_current_user() {
		switch_theme( 'block-theme' );

		wp_set_current_user( self::$admin_id );
		$this->assertContains( 'duplicate-pattern', $this->get_action_ids( 'wp_block' ), 'Administrators can create patterns.' );
		$this->assertContains( 'duplicate-template-part', $this->get_action_ids( 'wp_template_part' ), 'Administrators can create template parts.' );

		wp_set_current_user( self::$editor_id );
		$this->assertContains( 'duplicate-pattern', $this->get_action_ids( 'wp_block' ), 'Editors can create patterns.' );
		$this->assertNotContains( 'duplicate-template-part', $this->get_action_ids( 'wp_template_part' ), 'Editors cannot create template parts.' );

		wp_set_current_user( self::$contributor_id );
		$this->assertNotContains( 'duplicate-pattern', $this->get_action_ids( 'wp_block' ), 'Contributors cannot create patterns.' );
		$this->assertContains( 'export-pattern', $this->get_action_ids( 'wp_block' ), 'Contributors can export them.' );
	}

	/**
	 * The actions of a custom post type follow what it supports.
	 *
	 * @ticket 66343
	 */
	public function test_the_actions_of_a_custom_post_type_follow_its_supports() {
		register_post_type(
			'test_book',
			array(
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'revisions' ),
			)
		);
		register_post_type(
			'test_note',
			array(
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => array( 'editor' ),
			)
		);
		register_post_type(
			'test_hidden',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array_merge( array( 'view-post', 'view-post-revisions', 'rename-post' ), self::EVERY_POST_TYPE ),
			$this->get_action_ids( 'test_book' ),
			'A viewable post type with titles and revisions.'
		);
		$this->assertSame(
			self::EVERY_POST_TYPE,
			$this->get_action_ids( 'test_note' ),
			'A post type that is not viewable, without titles or revisions.'
		);
		$this->assertSame( array(), $this->get_action_ids( 'test_hidden' ), 'A post type not exposed in the REST API has no action.' );
	}

	/**
	 * Setting the homepage or the posts page changes the site settings, so
	 * the actions of the `page` module are only registered for the users who
	 * can manage the site options.
	 *
	 * @ticket 66343
	 */
	public function test_the_homepage_actions_depend_on_the_current_user() {
		wp_set_current_user( self::$admin_id );
		$this->assertSame( self::PAGE_ACTIONS, array_values( array_intersect( $this->get_action_ids( 'page' ), self::PAGE_ACTIONS ) ), 'Administrators can manage the site options.' );

		wp_set_current_user( self::$editor_id );
		$this->assertSame( array(), array_intersect( $this->get_action_ids( 'page' ), self::PAGE_ACTIONS ), 'Editors cannot manage the site options.' );
	}

	/**
	 * A `front-page` template shows on the front page whatever the site
	 * settings say, so the actions of the `page` module are not registered
	 * when the theme has one.
	 *
	 * @ticket 66343
	 */
	public function test_the_homepage_actions_are_not_registered_with_a_front_page_template() {
		wp_set_current_user( self::$admin_id );
		switch_theme( 'block-theme' );
		$this->assertContains( 'set-as-homepage', $this->get_action_ids( 'page' ), 'The theme has no `front-page` template.' );

		$template_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'front-page',
				'post_title'   => 'Front Page',
				'post_content' => '<!-- wp:paragraph --><p>Front page</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_post_terms( $template_id, get_stylesheet(), 'wp_theme' );

		$this->assertSame( array(), array_intersect( $this->get_action_ids( 'page' ), self::PAGE_ACTIONS ), 'The `front-page` template removes the actions.' );
	}

	/**
	 * The definitions are the ones of the `action.php` files, with the id
	 * first.
	 *
	 * @ticket 66343
	 */
	public function test_the_definitions_come_from_the_action_files() {
		wp_set_current_user( self::$admin_id );

		$actions = array_column( wp_get_registered_entity_actions( 'postType', 'page' ), null, 'id' );
		$this->assertSame(
			array(
				'id'                => 'move-to-trash',
				'label'             => 'Trash',
				'isPrimary'         => true,
				'supportsBulk'      => true,
				'hideModalHeader'   => true,
				'modalFocusOnMount' => 'firstContentElement',
				'origin'            => array( 'registeredBy' => 'core' ),
			),
			$actions['move-to-trash'],
			'The definition of the trash action.'
		);
		$this->assertSame(
			array(
				'id'      => 'view-post-revisions',
				'label'   => 'View revisions',
				'context' => 'list',
				'origin'  => array( 'registeredBy' => 'core' ),
			),
			$actions['view-post-revisions'],
			'The definition of the revisions action.'
		);
	}

	/**
	 * The labels are translated with the default text domain of WordPress.
	 *
	 * @ticket 66343
	 */
	public function test_the_labels_are_translated_with_the_default_text_domain() {
		$domains = array();
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) use ( &$domains ) {
				if ( 'Trash' === $text ) {
					$domains[] = $domain;
				}
				return $translation;
			},
			10,
			3
		);
		add_filter(
			'gettext_with_context',
			static function ( $translation, $text, $context, $domain ) use ( &$domains ) {
				if ( 'Trash' === $text ) {
					$domains[] = $domain;
				}
				return $translation;
			},
			10,
			4
		);
		wp_set_current_user( self::$admin_id );

		$this->get_action_ids( 'page' );

		$this->assertSame( array( 'default' ), array_unique( $domains ), 'The label of the trash action is translated in the default text domain.' );
	}

	/**
	 * The REST route serves the core actions of each post type, with their
	 * script modules, in a response that validates against its schema.
	 *
	 * @ticket 66343
	 */
	public function test_the_rest_route_serves_the_core_actions() {
		wp_set_current_user( self::$admin_id );
		switch_theme( 'block-theme' );
		$schema = ( new WP_REST_Entity_Actions_Controller() )->get_item_schema();

		$modules = array(
			'page'             => array( self::POST_TYPE_SUPPORTS, self::PAGE ),
			'wp_block'         => array( self::POST_TYPE_SUPPORTS, self::WP_BLOCK ),
			'wp_template_part' => array( self::POST_TYPE_SUPPORTS, self::WP_TEMPLATE_PART ),
		);
		foreach ( $modules as $post_type => $module_ids ) {
			self::reset_registry();
			$request = new WP_REST_Request( 'GET', '/wp/v2/entity-actions' );
			$request->set_query_params(
				array(
					'kind' => 'postType',
					'name' => $post_type,
				)
			);
			$data = rest_get_server()->dispatch( $request )->get_data();

			$this->assertSame( array_column( wp_get_registered_entity_actions( 'postType', $post_type ), 'id' ), array_column( $data['actions'], 'id' ), "The actions of $post_type are served." );
			$this->assertSame( $module_ids, array_column( $data['script_modules'], 'id' ), "The script modules of $post_type are served." );
			$this->assertTrue( rest_validate_value_from_schema( $data, $schema ), "The response for $post_type matches the schema." );
		}
	}

	/**
	 * The script modules of the core actions are registered, so the editor
	 * script can add them to the import map.
	 *
	 * @ticket 66343
	 */
	public function test_the_script_modules_of_the_core_actions_are_registered() {
		wp_set_current_user( self::$admin_id );

		foreach ( wp_get_all_registered_entity_action_modules()['postType'] as $post_type => $modules ) {
			foreach ( $modules as $module ) {
				$this->assertNotNull( wp_script_modules()->get_registered( $module ), "The $module module of $post_type is registered." );
			}
		}
	}

	/**
	 * A plugin hooking the action at the default priority can remove a core
	 * action, and replace one: it unregisters the action and registers its
	 * own definition with the script module of the core action, which keeps
	 * its JavaScript parts. The replaced action moves after the others.
	 *
	 * @ticket 66343
	 */
	public function test_a_plugin_can_remove_and_replace_core_actions() {
		$callback = static function () {
			wp_unregister_entity_actions( 'postType', 'page', array( 'order-pages' ) );
			$trash = wp_unregister_entity_actions( 'postType', 'page', array( 'move-to-trash' ) );
			wp_register_entity_actions(
				'test-plugin',
				'postType',
				'page',
				array( array_merge( $trash[0], array( 'label' => 'Bin…' ) ) ),
				'@wordpress/core-actions/post_type_supports'
			);
		};
		add_action( 'wp_entity_actions_api_init', $callback );
		wp_set_current_user( self::$admin_id );

		$ids = array( 'view-post', 'view-post-revisions', 'rename-post', 'restore', 'reset-post', 'delete-post', 'permanently-delete' );
		$this->assertSame( array_merge( $ids, self::PAGE_ACTIONS, array( 'move-to-trash' ) ), $this->get_action_ids( 'page' ), 'The order action is removed and the trash action moves last.' );
		$this->assertSame(
			array(
				self::POST_TYPE_SUPPORTS => array_merge( $ids, array( 'move-to-trash' ) ),
				self::PAGE               => self::PAGE_ACTIONS,
			),
			array_column( wp_get_registered_entity_action_modules( 'postType', 'page' ), 'actions', 'id' ),
			'The replaced action keeps the module of the core action.'
		);

		$actions = array_column( wp_get_registered_entity_actions( 'postType', 'page' ), null, 'id' );
		$this->assertSame( 'Bin…', $actions['move-to-trash']['label'], 'The label of the plugin is used.' );
		$this->assertSame( 'test-plugin', $actions['move-to-trash']['origin']['registeredBy'], 'The plugin registered the action.' );
		$this->assertTrue( $actions['move-to-trash']['isPrimary'], 'The rest of the core definition is kept.' );
	}
}
