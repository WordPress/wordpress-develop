<?php

require_once __DIR__ . '/base.php';

/**
 * Tests for the fields WordPress core registers through the entity fields API.
 *
 * The core fields are copied from the Gutenberg build, so the tests are
 * skipped until the pinned build includes them.
 *
 * @package WordPress
 * @subpackage Entity Fields API
 * @since 7.2.0
 *
 * @group entity-fields
 * @requires function wp_register_core_field_collections
 *
 * @covers ::wp_register_core_post_type_supports_fields
 * @covers ::wp_register_core_field_collections
 */
class Tests_Entity_Fields_WpRegisterCoreFieldCollections extends WP_Entity_Fields_UnitTestCase {

	/**
	 * The ids of the default fields every post type gets, whatever it
	 * supports, but for the design post types, which exclude some of them.
	 *
	 * @var string[]
	 */
	const EVERY_POST_TYPE_FIELDS = array( 'date', 'last_edited_date', 'password', 'scheduled_date', 'status', 'template' );

	/**
	 * The script module of the default fields of the post types.
	 */
	const POST_TYPE_SUPPORTS = '@wordpress/core-fields/post_type_supports';

	/**
	 * The theme features before each test, restored on tear down.
	 *
	 * @var array
	 */
	private $theme_features;

	/**
	 * Sets up each test.
	 *
	 * Registers the fields WordPress core registers instead of the fixture
	 * defaults of the other entity fields tests.
	 */
	public function set_up() {
		global $_wp_theme_features;

		parent::set_up();

		$this->theme_features = $_wp_theme_features;
		remove_action( 'wp_entity_fields_api_init', array( static::class, 'register_default_fields' ), 0 );
		add_action( 'wp_entity_fields_api_init', 'wp_register_core_field_collections', 0 );
		self::reset_registry();
	}

	/**
	 * Tears down each test.
	 *
	 * Restores the theme features a test changed.
	 */
	public function tear_down() {
		global $_wp_theme_features;

		$_wp_theme_features = $this->theme_features;

		parent::tear_down();
	}

	/**
	 * Returns the fields registered on an entity, keyed by id.
	 *
	 * @param string $kind The entity kind.
	 * @param string $name The entity name.
	 * @return array[] The field definitions, keyed by id.
	 */
	private static function get_fields( $kind, $name ) {
		return array_column( wp_get_registered_entity_fields( $kind, $name ), null, 'id' );
	}

	/**
	 * Returns the ids of the fields registered on a post type.
	 *
	 * @param string $post_type The post type.
	 * @return string[] The ids, in registration order.
	 */
	private static function get_field_ids( $post_type ) {
		return array_column( wp_get_registered_entity_fields( 'postType', $post_type ), 'id' );
	}

	/**
	 * Returns the ids of the fields registered on a post type, but for the
	 * defaults every post type gets, see EVERY_POST_TYPE_FIELDS.
	 *
	 * @param string $post_type The post type.
	 * @return string[] The ids, in registration order.
	 */
	private static function get_support_field_ids( $post_type ) {
		return array_values( array_diff( self::get_field_ids( $post_type ), self::EVERY_POST_TYPE_FIELDS ) );
	}

	/**
	 * The core fields are registered on `wp_entity_fields_api_init` at
	 * priority 0, before the plugins hooking the action at the default
	 * priority.
	 *
	 * @ticket 66229
	 */
	public function test_the_core_fields_are_registered_at_priority_zero() {
		// The hooks as WordPress sets them up, before the other entity fields
		// tests replace the core fields with their fixture defaults.
		$hook = self::$hooks_saved['wp_filter']['wp_entity_fields_api_init'];

		$this->assertSame( 0, $hook->has_filter( 'wp_entity_fields_api_init', 'wp_register_core_field_collections' ), 'The core fields are registered at priority 0.' );
	}

	/**
	 * The script module of the default fields is declared as a dependency
	 * of the editor script, since the author field of every post type
	 * supporting authors ships its JavaScript parts in it.
	 *
	 * @ticket 66229
	 */
	public function test_the_default_fields_module_is_a_dependency_of_the_editor_script() {
		$scripts = new WP_Scripts();
		$scripts->add( 'wp-editor', '/editor.js' );
		$scripts->enqueue( 'wp-editor' );
		$scripts->add_data( 'wp-editor', 'module_dependencies', array() );

		_wp_add_entity_field_modules_to_editor_script( $scripts );

		$this->assertContains(
			array(
				'id'     => self::POST_TYPE_SUPPORTS,
				'import' => 'dynamic',
			),
			$scripts->get_data( 'wp-editor', 'module_dependencies' ),
			'The module of the default fields is a dynamic dependency of the editor script.'
		);
	}

	/**
	 * The script modules of the core fields are registered, so they reach
	 * the import map.
	 *
	 * @ticket 66229
	 */
	public function test_the_script_modules_of_the_core_fields_are_registered() {
		foreach ( wp_get_all_registered_entity_field_modules() as $kind => $entities ) {
			foreach ( $entities as $name => $modules ) {
				foreach ( $modules as $module ) {
					$this->assertNotNull( wp_script_modules()->get_registered( $module ), "The $module module of $kind $name is registered." );
				}
			}
		}
	}

	/**
	 * The labels are translated with the default text domain of WordPress.
	 *
	 * @ticket 66229
	 */
	public function test_the_labels_are_translated_with_the_default_text_domain() {
		$domains = array();
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) use ( &$domains ) {
				if ( 'Discussion' === $text ) {
					$domains[] = $domain;
				}
				return $translation;
			},
			10,
			3
		);

		self::reset_registry();
		wp_get_registered_entity_fields( 'postType', 'post' );

		$this->assertSame( array( 'default' ), array_unique( $domains ), 'The label of the discussion field is translated in the default text domain.' );
	}

	/**
	 * Any post type supporting authors, comments, or notes, including a
	 * custom one, gets the default fields: the defaults derive from the post
	 * types registered when the action fires.
	 *
	 * @ticket 66229
	 */
	public function test_a_custom_post_type_gets_the_default_fields_of_its_supports() {
		$this->register_post_types(
			array(
				'test_book' => array(
					'title',
					'author',
					'comments',
					'editor' => array( 'notes' => true ),
				),
			)
		);
		self::reset_registry();

		$fields = wp_get_registered_entity_fields( 'postType', 'test_book' );

		$this->assertSame( array( 'author', 'comment_status', 'discussion', 'notesCount', 'post-content-info', 'title' ), self::get_support_field_ids( 'test_book' ), 'The post type gets the fields of its supports.' );
		$this->assertSame( array( 'core' ), array_unique( array_column( array_column( $fields, 'origin' ), 'registeredBy' ) ), 'The fields carry the origin of WordPress core.' );
		$this->assertSame(
			array( self::POST_TYPE_SUPPORTS => array_column( $fields, 'id' ) ),
			wp_get_registered_entity_field_modules( 'postType', 'test_book' ),
			'Every default field is registered with the module of its folder.'
		);
	}

	/**
	 * The notes field follows the `notes` argument of the `editor` support,
	 * which WordPress stores as a list of argument arrays; a bare `editor`
	 * support does not enable it.
	 *
	 * @ticket 66229
	 */
	public function test_the_notes_field_follows_the_editor_support_argument() {
		$this->register_post_types(
			array(
				'test_book' => array( 'editor' => array( 'notes' => true ) ),
				'test_note' => array( 'editor' ),
			)
		);
		self::reset_registry();

		$this->assertContains( 'notesCount', self::get_field_ids( 'test_book' ), 'The post type with notes gets the field.' );
		$this->assertNotContains( 'notesCount', self::get_field_ids( 'test_note' ), 'The post type without notes does not get the field.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] The id of each field and whether the design post types
	 *                 exclude it.
	 */
	public function data_default_fields_of_every_post_type() {
		return array(
			'date'             => array( 'date', true ),
			'last_edited_date' => array( 'last_edited_date', false ),
			'password'         => array( 'password', true ),
			'scheduled_date'   => array( 'scheduled_date', true ),
			'status'           => array( 'status', true ),
			'template'         => array( 'template', true ),
		);
	}

	/**
	 * A default field of every post type is registered on a post type
	 * supporting nothing, and on the design post types unless they exclude
	 * it.
	 *
	 * @ticket 66229
	 *
	 * @dataProvider data_default_fields_of_every_post_type
	 *
	 * @param string $field_id            The id of the field.
	 * @param bool   $excluded_for_design Whether the design post types exclude it.
	 */
	public function test_a_default_field_of_every_post_type( $field_id, $excluded_for_design ) {
		$this->register_post_types( array( 'test_plain' => array( 'title' ) ) );
		self::reset_registry();

		$this->assertContains( $field_id, self::get_field_ids( 'test_plain' ), 'A post type supporting only titles gets the field.' );
		foreach ( array( 'wp_template', 'wp_template_part', 'wp_block', 'wp_navigation' ) as $post_type ) {
			if ( $excluded_for_design ) {
				$this->assertNotContains( $field_id, self::get_field_ids( $post_type ), "$post_type excludes the field." );
			} else {
				$this->assertContains( $field_id, self::get_field_ids( $post_type ), "$post_type gets the field." );
			}
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[] The id of each field and the supports that give it.
	 */
	public function data_default_fields_and_their_supports() {
		return array(
			'discussion'        => array( 'discussion', array( 'comments', 'trackbacks' ) ),
			'excerpt'           => array( 'excerpt', array( 'excerpt' ) ),
			'parent'            => array( 'parent', array( 'page-attributes' ) ),
			'ping_status'       => array( 'ping_status', array( 'trackbacks' ) ),
			'title'             => array( 'title', array( 'title' ) ),
			'post-content-info' => array( 'post-content-info', array( 'editor' ) ),
		);
	}

	/**
	 * A default field is registered on the post types with one of its
	 * supports, and only on them.
	 *
	 * @ticket 66229
	 *
	 * @dataProvider data_default_fields_and_their_supports
	 *
	 * @param string   $field_id The id of the field.
	 * @param string[] $supports The supports that give the field.
	 */
	public function test_a_default_field_follows_its_supports( $field_id, $supports ) {
		$with_support = array();
		foreach ( $supports as $index => $support ) {
			$with_support[ "test_with_$index" ] = array( 'title', $support );
		}
		$this->register_post_types( $with_support + array( 'test_without' => array( 'author' ) ) );
		self::reset_registry();

		foreach ( array_keys( $with_support ) as $post_type ) {
			$this->assertContains( $field_id, self::get_field_ids( $post_type ), "$post_type gets the field." );
		}
		$this->assertNotContains( $field_id, self::get_field_ids( 'test_without' ), 'A post type without the supports does not get the field.' );
	}

	/**
	 * The default fields come from the `field.php` files of the
	 * `post_type_supports` folder.
	 *
	 * @ticket 66229
	 */
	public function test_the_default_fields_come_from_the_post_type_supports_folder() {
		$fields = self::get_fields( 'postType', 'post' );

		$this->assertSame( 'integer', $fields['author']['type'], 'The author field is the post author.' );
		$this->assertSame( 'core', $fields['author']['origin']['registeredBy'], 'The author field is registered by WordPress core.' );
		$this->assertSame( 'radio', $fields['comment_status']['Edit'], 'The comment status field is edited with radio buttons.' );
		$this->assertSame( 'boolean', $fields['ping_status']['type'], 'The ping status field is a boolean.' );
		$this->assertSame( 'Discussion', $fields['discussion']['label'], 'The discussion field has its label.' );
		$this->assertSame( 'textarea', $fields['excerpt']['Edit']['control'], 'The excerpt field is edited in a textarea.' );
		$this->assertTrue( $fields['post-content-info']['readOnly'], 'The content information field is read-only.' );
	}

	/**
	 * The defaults make no exception for any post type: on their own, they
	 * register the author field on templates, template parts, and
	 * attachments, which support authors.
	 *
	 * @ticket 66229
	 */
	public function test_the_defaults_make_no_exception() {
		remove_action( 'wp_entity_fields_api_init', 'wp_register_core_field_collections', 0 );
		$this->on_entity_fields_api_init( 'wp_register_core_post_type_supports_fields', 0 );

		foreach ( array( 'wp_template', 'wp_template_part', 'attachment' ) as $post_type ) {
			$fields = self::get_fields( 'postType', $post_type );
			$this->assertArrayHasKey( 'author', $fields, "The defaults register the author field on $post_type." );
			$this->assertSame( 'integer', $fields['author']['type'], "The default author field of $post_type is the post author." );
		}
	}

	/**
	 * Pages support titles but their title shows the page's role on the
	 * site: they get the title field of their collection, with its own
	 * script module, instead of the default one.
	 *
	 * @ticket 66229
	 */
	public function test_pages_get_their_own_title_field() {
		$fields  = self::get_fields( 'postType', 'page' );
		$modules = wp_get_registered_entity_field_modules( 'postType', 'page' );

		$this->assertFalse( $fields['title']['enableHiding'], 'The page title is the one of the collection.' );
		$this->assertSame( 'core', $fields['title']['origin']['registeredBy'], 'The page title is registered by WordPress core.' );
		$this->assertSame( array( 'title' ), $modules['@wordpress/core-fields/page'], 'The page title ships its JavaScript parts in the module of the collection.' );
		$this->assertNotContains( 'title', $modules[ self::POST_TYPE_SUPPORTS ], 'The default title field is not registered.' );
	}

	/**
	 * Templates support authors but their author is the theme, plugin, site,
	 * or user that provides them: they get the fields of their collection,
	 * with its own script module, instead of the default author and title
	 * fields.
	 *
	 * @ticket 66229
	 */
	public function test_templates_get_their_own_fields() {
		$ids    = array( 'author', 'default_comment_status', 'description', 'description_readonly', 'posts_page_title', 'posts_per_page', 'title' );
		$fields = self::get_fields( 'postType', 'wp_template' );

		$this->assertTrue( post_type_supports( 'wp_template', 'author' ), 'The post type supports authors.' );
		$this->assertSame( $ids, self::get_support_field_ids( 'wp_template' ), 'The fields are the ones of the collection.' );
		$this->assertSame( 'Template', $fields['title']['label'], 'The title is the one of the collection.' );
		$this->assertArrayNotHasKey( 'type', $fields['author'], 'The template author is not the integer post author.' );
		$this->assertSame( 'core', $fields['author']['origin']['registeredBy'], 'The template author is registered by WordPress core.' );
		$this->assertSame(
			$ids,
			wp_get_registered_entity_field_modules( 'postType', 'wp_template' )['@wordpress/core-fields/wp_template'],
			'The fields of templates ship their JavaScript parts in the module of the collection.'
		);
	}

	/**
	 * Template parts support authors but their author is the theme, plugin,
	 * site, or user that provides them: they get the fields of their
	 * collection, with its own script module, instead of the default author
	 * and title fields.
	 *
	 * @ticket 66229
	 */
	public function test_template_parts_get_their_own_fields() {
		$fields = self::get_fields( 'postType', 'wp_template_part' );

		$this->assertTrue( post_type_supports( 'wp_template_part', 'author' ), 'The post type supports authors.' );
		$this->assertSame( array( 'author', 'title' ), self::get_support_field_ids( 'wp_template_part' ), 'The fields are the ones of the collection.' );
		$this->assertArrayNotHasKey( 'type', $fields['author'], 'The template part author is not the integer post author.' );
		$this->assertSame( 'Title', $fields['title']['label'], 'The title is the one of the collection.' );
		$this->assertSame(
			array( 'author', 'title' ),
			wp_get_registered_entity_field_modules( 'postType', 'wp_template_part' )['@wordpress/core-fields/wp_template_part'],
			'The fields of template parts ship their JavaScript parts in the module of the collection.'
		);
	}

	/**
	 * Patterns have their description, title, and sync status in their
	 * collection instead of the default excerpt and title fields.
	 *
	 * @ticket 66229
	 */
	public function test_patterns_get_their_own_fields() {
		$fields  = self::get_fields( 'postType', 'wp_block' );
		$modules = wp_get_registered_entity_field_modules( 'postType', 'wp_block' );

		$this->assertTrue( post_type_supports( 'wp_block', 'excerpt' ), 'The post type supports excerpts.' );
		$this->assertSame( 'Description', $fields['excerpt']['label'], 'The excerpt is the description of the collection.' );
		$this->assertSame( 'Title', $fields['title']['label'], 'The title is the one of the collection.' );
		$this->assertTrue( $fields['sync-status']['readOnly'], 'The sync status is read-only.' );
		$this->assertSame( array( 'excerpt', 'sync-status', 'title' ), $modules['@wordpress/core-fields/wp_block'], 'The fields of patterns ship their JavaScript parts in the module of the collection.' );
		$this->assertNotContains( 'excerpt', $modules[ self::POST_TYPE_SUPPORTS ], 'The default excerpt field is not registered.' );
	}

	/**
	 * Patterns are sorted on the client, where the id of their author is not
	 * a useful order, so their author field cannot sort them.
	 *
	 * @ticket 66229
	 */
	public function test_patterns_cannot_be_sorted_by_author() {
		$author = self::get_fields( 'postType', 'wp_block' )['author'];

		$this->assertFalse( $author['enableSorting'], 'The author of patterns cannot sort them.' );
		$this->assertSame(
			array(
				'registeredBy' => 'core',
				'updatedBy'    => array( 'core' ),
			),
			$author['origin'],
			'WordPress core updates the default author field of patterns.'
		);
	}

	/**
	 * Navigation menus support the editor, but their content is blocks
	 * laying out a site rather than text to read, so they do not get the
	 * content information field. Patterns keep it.
	 *
	 * @ticket 66229
	 */
	public function test_navigation_menus_do_not_get_the_content_information_field() {
		$this->assertTrue( post_type_supports( 'wp_navigation', 'editor' ), 'The post type supports the editor.' );
		$this->assertNotContains( 'post-content-info', self::get_field_ids( 'wp_navigation' ), 'Navigation menus do not get the field.' );
		$this->assertContains( 'post-content-info', self::get_field_ids( 'wp_block' ), 'Patterns get the field.' );
	}

	/**
	 * Attachments support authors and comments but the media editor has its
	 * own fields, so they get the media fields of their collection, with its
	 * script module, instead of every default field.
	 *
	 * @ticket 66229
	 */
	public function test_attachments_get_the_media_fields_instead_of_the_defaults() {
		$ids    = array( 'alt_text', 'attached_to', 'author', 'caption', 'date', 'description', 'filename', 'filesize', 'media_dimensions', 'media_thumbnail', 'mime_type', 'modified', 'title' );
		$fields = self::get_fields( 'postType', 'attachment' );

		$this->assertTrue( post_type_supports( 'attachment', 'author' ), 'The post type supports authors.' );
		$this->assertTrue( post_type_supports( 'attachment', 'comments' ), 'The post type supports comments.' );
		$this->assertSame( $ids, array_keys( $fields ), 'Only the media fields are registered.' );
		$this->assertSame( 'datetime', $fields['date']['type'], 'The date is the one of the collection.' );
		$this->assertSame( 'core', $fields['date']['origin']['registeredBy'], 'The media fields are registered by WordPress core.' );
		$this->assertSame(
			array( '@wordpress/core-fields/attachment' => $ids ),
			wp_get_registered_entity_field_modules( 'postType', 'attachment' ),
			'Every media field is registered with the module of the collection.'
		);
	}

	/**
	 * The site is not a post type: its collection has all of its fields,
	 * the ones the identity screen of the site editor shows, with its
	 * script module.
	 *
	 * @ticket 66229
	 */
	public function test_the_site_gets_the_fields_of_its_collection() {
		$ids    = array( 'description', 'site_icon', 'site_logo', 'title' );
		$fields = self::get_fields( 'root', 'site' );

		$this->assertSame( $ids, array_keys( $fields ), 'The fields are the ones of the collection.' );
		$this->assertSame( 'text', $fields['title']['type'], 'The title of the site is text.' );
		$this->assertSame( 'media', $fields['site_logo']['type'], 'The logo of the site is media.' );
		$this->assertSame( 'core', $fields['title']['origin']['registeredBy'], 'The fields of the site are registered by WordPress core.' );
		$this->assertSame(
			array( '@wordpress/core-fields/root_site' => $ids ),
			wp_get_registered_entity_field_modules( 'root', 'site' ),
			'Every field of the site is registered with the module of the collection.'
		);
	}

	/**
	 * The slug field is registered on the viewable post types, which have a
	 * permalink, but for the design ones.
	 *
	 * @ticket 66229
	 */
	public function test_the_viewable_post_types_get_the_slug_field() {
		register_post_type(
			'test_book',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		register_post_type(
			'test_note',
			array(
				'public'       => false,
				'show_in_rest' => true,
			)
		);
		self::reset_registry();

		$this->assertContains( 'slug', self::get_field_ids( 'test_book' ), 'A viewable post type gets the field.' );
		$this->assertNotContains( 'slug', self::get_field_ids( 'test_note' ), 'A post type that is not viewable has no permalink.' );
		$this->assertContains( 'slug', self::get_field_ids( 'page' ), 'Pages get the field.' );
		$this->assertNotContains( 'slug', self::get_field_ids( 'wp_template' ), 'Templates do not get the field.' );
	}

	/**
	 * Only posts have sticky posts, so only they get the sticky field.
	 *
	 * @ticket 66229
	 */
	public function test_only_posts_get_the_sticky_field() {
		$this->register_post_types( array( 'test_book' => array( 'title', 'editor' ) ) );
		self::reset_registry();

		$this->assertContains( 'sticky', self::get_field_ids( 'post' ), 'Posts get the field.' );
		$this->assertNotContains( 'sticky', self::get_field_ids( 'page' ), 'Pages do not get the field.' );
		$this->assertNotContains( 'sticky', self::get_field_ids( 'test_book' ), 'Custom post types do not get the field.' );
	}

	/**
	 * The featured media field needs the post type to support `thumbnail`
	 * and the theme to support post thumbnails for that post type, which a
	 * theme may opt into for some post types only.
	 *
	 * @ticket 66229
	 */
	public function test_the_featured_media_field_follows_the_post_type_support_and_the_theme() {
		$this->register_post_types(
			array(
				'test_book' => array( 'title', 'thumbnail' ),
				'test_note' => array( 'title', 'thumbnail' ),
				'test_page' => array( 'title' ),
			)
		);

		// Naming post types only narrows the support when the theme does not
		// already support post thumbnails for every post type.
		remove_theme_support( 'post-thumbnails' );
		add_theme_support( 'post-thumbnails', array( 'test_book' ) );
		self::reset_registry();

		$this->assertContains( 'featured_media', self::get_support_field_ids( 'test_book' ), 'The theme opted the post type into post thumbnails.' );
		$this->assertNotContains( 'featured_media', self::get_support_field_ids( 'test_note' ), 'The theme did not opt the post type into post thumbnails.' );
		$this->assertNotContains( 'featured_media', self::get_support_field_ids( 'test_page' ), 'The post type does not support thumbnails.' );

		remove_theme_support( 'post-thumbnails' );
		add_theme_support( 'post-thumbnails' );
		self::reset_registry();

		$this->assertContains( 'featured_media', self::get_support_field_ids( 'test_note' ), 'A theme may opt every post type in at once.' );

		remove_theme_support( 'post-thumbnails' );
		self::reset_registry();

		$this->assertNotContains( 'featured_media', self::get_support_field_ids( 'test_book' ), 'A theme without post thumbnails has no featured image to set.' );
	}

	/**
	 * The format field needs the post type to support `post-formats` and
	 * the theme to support post formats. Its elements are the formats of
	 * the theme, plus `standard`, sorted by label.
	 *
	 * @ticket 66229
	 */
	public function test_the_format_field_follows_the_post_type_support_and_the_theme() {
		$this->register_post_types(
			array(
				'test_book' => array( 'title', 'post-formats' ),
				'test_note' => array( 'title' ),
			)
		);

		add_theme_support( 'post-formats', array( 'gallery', 'aside' ) );
		self::reset_registry();
		$fields = self::get_fields( 'postType', 'test_book' );

		$this->assertArrayHasKey( 'format', $fields, 'The post type supporting post formats gets the field.' );
		$this->assertNotContains( 'format', self::get_field_ids( 'test_note' ), 'A post type that does not support post formats has no format field.' );
		$this->assertSame(
			array( 'aside', 'gallery', 'standard' ),
			array_column( $fields['format']['elements'], 'value' ),
			'The elements are the formats of the theme, plus standard, sorted by label.'
		);

		remove_theme_support( 'post-formats' );
		self::reset_registry();

		$this->assertNotContains( 'format', self::get_field_ids( 'test_book' ), 'A theme without post formats has none to assign.' );
	}

	/**
	 * A plugin hooking the action at the default priority sees the core
	 * fields: it can update a default field, or remove it.
	 *
	 * @ticket 66229
	 */
	public function test_a_plugin_can_alter_the_default_fields() {
		$this->on_entity_fields_api_init(
			static function () {
				wp_update_entity_fields(
					'test-plugin',
					'postType',
					'page',
					array(
						array(
							'id'            => 'comment_status',
							'enableSorting' => true,
						),
					)
				);
				wp_unregister_entity_fields( 'postType', 'page', array( 'author' ) );
			}
		);

		$fields = self::get_fields( 'postType', 'page' );

		$this->assertTrue( $fields['comment_status']['enableSorting'], 'The property is updated.' );
		$this->assertSame( 'text', $fields['comment_status']['type'], 'The rest of the default definition is kept.' );
		$this->assertSame(
			array(
				'registeredBy' => 'core',
				'updatedBy'    => array( 'test-plugin' ),
			),
			$fields['comment_status']['origin'],
			'Updating a field keeps who registered it and records who updated it.'
		);
		$this->assertArrayNotHasKey( 'author', $fields, 'The default field is removed.' );
	}

	/**
	 * A plugin drops a default field from its post type by unregistering
	 * it at the default priority, keeping the others.
	 *
	 * @ticket 66229
	 */
	public function test_a_plugin_can_drop_a_default_field_from_a_post_type() {
		$this->register_post_types(
			array(
				'test_book' => array( 'title', 'author', 'comments' ),
				'test_note' => array( 'title', 'author', 'comments' ),
			)
		);
		$this->on_entity_fields_api_init(
			static function () {
				wp_unregister_entity_fields( 'postType', 'test_book', array( 'comment_status' ) );
			}
		);

		$this->assertSame( array( 'author', 'discussion', 'title' ), self::get_support_field_ids( 'test_book' ), 'The post type drops the field.' );
		$this->assertSame( array( 'author', 'comment_status', 'discussion', 'title' ), self::get_support_field_ids( 'test_note' ), 'The other post types keep the field.' );
	}

	/**
	 * A plugin can replace the author field of templates.
	 *
	 * @ticket 66229
	 */
	public function test_a_plugin_can_replace_the_author_field_of_templates() {
		$this->on_entity_fields_api_init(
			static function () {
				wp_unregister_entity_fields( 'postType', 'wp_template', array( 'author' ) );
				wp_register_entity_fields(
					'my-plugin',
					'postType',
					'wp_template',
					array(
						array(
							'id'    => 'author',
							'type'  => 'text',
							'label' => 'Maker',
						),
					)
				);
			}
		);

		$modules = wp_get_registered_entity_field_modules( 'postType', 'wp_template' );

		$this->assertSame( 'my-plugin', self::get_fields( 'postType', 'wp_template' )['author']['origin']['registeredBy'], 'The author field is the one of the plugin.' );
		$this->assertNotContains( 'author', $modules['@wordpress/core-fields/wp_template'], 'The module of the replaced field no longer applies to it.' );
	}

	/**
	 * A plugin registering attachment fields keeps them next to the media
	 * fields.
	 *
	 * @ticket 66229
	 */
	public function test_attachments_keep_the_fields_plugins_register() {
		$this->register_fields( 'postType', 'attachment', array( $this->field( 'credit' ) ) );

		$ids = self::get_field_ids( 'attachment' );

		$this->assertContains( 'date', $ids, 'The media fields are kept.' );
		$this->assertSame( 'credit', end( $ids ), 'The plugin field follows the media fields.' );
	}
}
