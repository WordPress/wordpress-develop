<?php
/**
 * Base test case for the Entity Fields API.
 *
 * @package WordPress
 * @subpackage Entity Fields API
 * @since 7.2.0
 */
abstract class WP_Entity_Fields_UnitTestCase extends WP_UnitTestCase {

	/**
	 * The callbacks a test hooked to `wp_entity_fields_api_init`, as callback and
	 * priority pairs, removed on tear down.
	 *
	 * @var array[]
	 */
	private $callbacks = array();

	/**
	 * The post types a test registered, unregistered on tear down.
	 *
	 * @var string[]
	 */
	private $post_types = array();

	/**
	 * Sets up each test.
	 *
	 * Registers the fixture defaults, see register_default_fields(), at
	 * priority 0, before the callbacks of the tests, in place of the fields
	 * WordPress core registers, and resets the registry so its next read
	 * fires `wp_entity_fields_api_init` anew.
	 */
	public function set_up() {
		parent::set_up();

		remove_action( 'wp_entity_fields_api_init', 'wp_register_core_field_collections', 0 );
		add_action( 'wp_entity_fields_api_init', array( static::class, 'register_default_fields' ), 0 );
		self::reset_registry();
	}

	/**
	 * Tears down each test.
	 *
	 * Resetting the registry drops the fields a test registered along with
	 * the fixture defaults.
	 */
	public function tear_down() {
		foreach ( $this->callbacks as list( $callback, $priority ) ) {
			remove_action( 'wp_entity_fields_api_init', $callback, $priority );
		}
		$this->callbacks = array();
		remove_action( 'wp_entity_fields_api_init', array( static::class, 'register_default_fields' ), 0 );
		foreach ( $this->post_types as $post_type ) {
			unregister_post_type( $post_type );
		}
		$this->post_types = array();
		self::reset_registry();

		parent::tear_down();
	}

	/**
	 * Registers the fixture defaults: fields of pages registered by `core`
	 * with a script module, standing in for the default fields of post types.
	 *
	 * Hooked to `wp_entity_fields_api_init` at priority 0 for every test.
	 */
	public static function register_default_fields() {
		wp_register_entity_fields(
			'core',
			'postType',
			'page',
			array(
				array(
					'id'    => 'author',
					'type'  => 'integer',
					'label' => 'Author',
				),
				array(
					'id'    => 'date',
					'type'  => 'datetime',
					'label' => 'Date',
				),
			),
			'test/defaults'
		);
	}

	/**
	 * Resets the registry: drops the singleton instance, so its first read
	 * fires `wp_entity_fields_api_init` again.
	 */
	protected static function reset_registry() {
		$instance = new ReflectionProperty( WP_Entity_Fields_Registry::class, 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$instance->setAccessible( true );
		}
		$instance->setValue( null, null );
	}

	/**
	 * Hooks a callback to `wp_entity_fields_api_init` for the duration of the test
	 * and fires the action anew, so the registry reflects it right away.
	 *
	 * Registering and unregistering only run on the action, so this is how a
	 * test alters the registry, as a plugin does. The registry is reset and
	 * read, which fires the action: the fixture defaults are registered again
	 * and the callbacks hooked so far replay, in order. The callbacks are
	 * removed on tear down.
	 *
	 * @param callable $callback The callback.
	 * @param int      $priority The priority. Default 10, after the fixture defaults.
	 */
	protected function on_entity_fields_api_init( $callback, $priority = 10 ) {
		add_action( 'wp_entity_fields_api_init', $callback, $priority );
		$this->callbacks[] = array( $callback, $priority );

		self::reset_registry();
		WP_Entity_Fields_Registry::get_instance()->get_all_registered();
	}

	/**
	 * Registers fields on `wp_entity_fields_api_init` for the duration of the test,
	 * with the `test-plugin` origin: the registry is reset on tear down.
	 *
	 * @param string      $kind   The entity kind.
	 * @param string      $name   The entity name.
	 * @param array[]     $fields The field definitions.
	 * @param string|null $module The script module id, if any.
	 * @return string[] The ids of the fields registered.
	 */
	protected function register_fields( $kind, $name, $fields, $module = null ) {
		$registered = array();
		$this->on_entity_fields_api_init(
			static function () use ( &$registered, $kind, $name, $fields, $module ) {
				$registered = wp_register_entity_fields( 'test-plugin', $kind, $name, $fields, $module );
			}
		);
		return $registered;
	}

	/**
	 * Registers the post types a test uses, exposed in the REST API unless
	 * hidden. They are unregistered on tear down.
	 *
	 * @param array<string, array> $post_types The supports, keyed by post type.
	 * @param string[]             $hidden     The post types not exposed in
	 *                                         the REST API.
	 */
	protected function register_post_types( $post_types, $hidden = array() ) {
		foreach ( $post_types as $post_type => $supports ) {
			$this->post_types[] = $post_type;
			register_post_type(
				$post_type,
				array(
					'show_in_rest' => ! in_array( $post_type, $hidden, true ),
					'supports'     => $supports,
				)
			);
		}
	}

	/**
	 * Records the `_doing_it_wrong()` notices a method reports, so a test can
	 * assert what the notice names.
	 *
	 * @param string $method The method whose notices to record.
	 * @return array A list that fills with the reported messages.
	 */
	protected function &record_notices( $method ) {
		$reported = array();
		add_action(
			'doing_it_wrong_run',
			static function ( $function_name, $message ) use ( &$reported, $method ) {
				if ( $method === $function_name ) {
					$reported[] = $message;
				}
			},
			10,
			2
		);

		return $reported;
	}

	/**
	 * Builds a minimal field definition.
	 *
	 * @param string $id The field id.
	 * @return array The field definition.
	 */
	protected function field( $id ) {
		return array(
			'id'    => $id,
			'type'  => 'text',
			'label' => $id,
		);
	}
}
