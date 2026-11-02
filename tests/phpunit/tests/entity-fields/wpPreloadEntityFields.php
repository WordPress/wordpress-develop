<?php

require_once __DIR__ . '/base.php';

/**
 * Tests for the preloading of the entity fields.
 *
 * @package WordPress
 * @subpackage Entity Fields API
 * @since 7.2.0
 *
 * @group entity-fields
 *
 * @covers ::_wp_add_entity_preload_paths
 * @covers ::_wp_preload_entity_fields
 */
class Tests_Entity_Fields_WpPreloadEntityFields extends WP_Entity_Fields_UnitTestCase {

	public function tear_down() {
		unset( $_GET['p'] );
		parent::tear_down();
	}

	/**
	 * The preloading is hooked to the preload paths of the block editor.
	 *
	 * @ticket 66229
	 */
	public function test_the_fields_are_preloaded_on_the_block_editor_preload_paths() {
		$this->assertSame( 10, has_filter( 'block_editor_rest_api_preload_paths', '_wp_preload_entity_fields' ) );
	}

	/**
	 * The post editor preloads the fields of the edited post type, which the
	 * post summary of its inspector reads, whatever its `p` query arg.
	 *
	 * @ticket 66229
	 */
	public function test_the_post_editor_preloads_the_fields_of_the_post_type() {
		$_GET['p'] = '/wp_template';
		$post      = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$context   = new WP_Block_Editor_Context(
			array(
				'name' => 'core/edit-post',
				'post' => $post,
			)
		);

		$this->assertSame( array( '/wp/v2/entity-fields?kind=postType&name=page' ), _wp_preload_entity_fields( array(), $context ) );
	}

	/**
	 * The preloaded path serves the fields of the post type.
	 *
	 * @ticket 66229
	 */
	public function test_the_preloaded_fields_path_serves_the_fields() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_GET['p'] = '/page';
		$context   = new WP_Block_Editor_Context( array( 'name' => 'core/edit-site' ) );
		$paths     = _wp_preload_entity_fields( array(), $context );

		$preloaded = array_reduce( $paths, 'rest_preload_api_request', array() );

		$this->assertSame( 'page', $preloaded[ $paths[0] ]['body']['name'] );
		$this->assertContains( 'author', wp_list_pluck( $preloaded[ $paths[0] ]['body']['fields'], 'id' ) );
	}

	/**
	 * The site editor preloads the fields of the post types its screen lists,
	 * from the `p` query arg.
	 *
	 * @ticket 66229
	 *
	 * @dataProvider data_site_editor_screens
	 *
	 * @param string   $path       The `p` query arg.
	 * @param string[] $post_types The post types whose fields are preloaded.
	 */
	public function test_the_site_editor_preloads_the_fields_of_its_screen( $path, $post_types ) {
		$_GET['p'] = $path;
		$context   = new WP_Block_Editor_Context( array( 'name' => 'core/edit-site' ) );

		$paths = _wp_preload_entity_fields( array(), $context );
		unset( $_GET['p'] );

		$expected = array();
		foreach ( $post_types as $post_type ) {
			$expected[] = '/wp/v2/entity-fields?kind=postType&name=' . $post_type;
		}
		$this->assertSame( $expected, $paths );
	}

	/**
	 * Data provider for test_the_site_editor_preloads_the_fields_of_its_screen().
	 *
	 * @return array[]
	 */
	public static function data_site_editor_screens() {
		return array(
			'pages'         => array( '/page', array( 'page' ) ),
			'page'          => array( '/page/12', array( 'page' ) ),
			'templates'     => array( '/template', array( 'wp_template' ) ),
			'template'      => array( '/wp_template/emptytheme//index', array( 'wp_template' ) ),
			'patterns'      => array( '/pattern', array( 'wp_block', 'wp_template_part' ) ),
			'pattern'       => array( '/wp_block/12', array( 'wp_block' ) ),
			'template part' => array( '/wp_template_part/emptytheme//header', array( 'wp_template_part' ) ),
			'root'          => array( '/', array() ),
			'styles'        => array( '/styles', array() ),
			'navigation'    => array( '/navigation', array() ),
		);
	}

	/**
	 * The site editor preloads the fields of the edited post, once, next to
	 * those of its screen.
	 *
	 * @ticket 66229
	 */
	public function test_the_site_editor_preloads_the_fields_of_the_edited_post() {
		$post      = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$_GET['p'] = '/page';
		$context   = new WP_Block_Editor_Context(
			array(
				'name' => 'core/edit-site',
				'post' => $post,
			)
		);

		$paths = _wp_preload_entity_fields( array(), $context );
		unset( $_GET['p'] );

		$this->assertSame( array( '/wp/v2/entity-fields?kind=postType&name=page' ), $paths );
	}

	/**
	 * Post types that are not registered are skipped.
	 *
	 * @ticket 66229
	 */
	public function test_unregistered_post_types_are_not_preloaded() {
		$context = new WP_Block_Editor_Context(
			array(
				'name' => 'core/edit-post',
				'post' => new WP_Post( (object) array( 'post_type' => 'not_a_post_type' ) ),
			)
		);

		$this->assertSame( array(), _wp_preload_entity_fields( array(), $context ) );
	}

	/**
	 * Other editors, a post editor context without a post, and a site editor
	 * screen that lists no post fields are left untouched.
	 *
	 * @ticket 66229
	 */
	public function test_other_editors_do_not_preload_the_fields() {
		$site_editor = new WP_Block_Editor_Context( array( 'name' => 'core/edit-site' ) );
		$no_post     = new WP_Block_Editor_Context( array( 'name' => 'core/edit-post' ) );
		$widgets     = new WP_Block_Editor_Context( array( 'name' => 'core/edit-widgets' ) );

		$this->assertSame( array(), _wp_preload_entity_fields( array(), $site_editor ) );
		$this->assertSame( array(), _wp_preload_entity_fields( array(), $no_post ) );
		$this->assertSame( array(), _wp_preload_entity_fields( array(), $widgets ) );
	}

	/**
	 * Empty paths that are not an array become an array the fields are added
	 * to. Other paths that are not an array are returned unchanged.
	 *
	 * @ticket 66229
	 *
	 * @dataProvider data_non_array_paths
	 *
	 * @param mixed $paths    The paths passed to the filter.
	 * @param mixed $expected The filtered paths, or null for the fields path
	 *                        only.
	 */
	public function test_non_array_paths( $paths, $expected ) {
		$post    = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$context = new WP_Block_Editor_Context(
			array(
				'name' => 'core/edit-post',
				'post' => $post,
			)
		);

		if ( null === $expected ) {
			$expected = array( '/wp/v2/entity-fields?kind=postType&name=page' );
		}

		$this->assertSame( $expected, _wp_preload_entity_fields( $paths, $context ) );
	}

	/**
	 * Data provider for test_non_array_paths().
	 *
	 * @return array[]
	 */
	public static function data_non_array_paths() {
		$object = new stdClass();

		return array(
			'null'         => array( null, null ),
			'false'        => array( false, null ),
			'empty string' => array( '', null ),
			'string'       => array( '/wp/v2/types', '/wp/v2/types' ),
			'object'       => array( $object, $object ),
		);
	}

	/**
	 * Called with the paths only, as by a filter added with one accepted
	 * argument, the paths are returned unchanged.
	 *
	 * @ticket 66229
	 */
	public function test_the_paths_are_unchanged_without_a_context() {
		$this->assertSame( array( '/wp/v2/types' ), _wp_preload_entity_fields( array( '/wp/v2/types' ) ) );
		$this->assertSame( array(), _wp_preload_entity_fields( null ) );
	}
}
