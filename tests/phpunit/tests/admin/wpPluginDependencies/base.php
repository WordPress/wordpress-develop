<?php
/**
 * Test case for the Plugin Dependencies tests.
 *
 * @package WP_Plugin_Dependencies
 *
 * Abstracts the common properties and tasks for the Plugin Dependencies tests.
 */
abstract class WP_PluginDependencies_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Stores an instance of WP_Plugin_Dependencies
	 * for managing property visibility during tests.
	 *
	 * @var WP_Plugin_Dependencies
	 */
	protected static $instance;

	protected static $static_properties = array(
		'plugins'                         => array(),
		'plugin_dirnames'                 => array(),
		'plugin_dirnames_cache'           => array(),
		'dependencies'                    => array(),
		'dependency_slugs'                => array(),
		'dependent_slugs'                 => array(),
		'dependency_api_data'             => array(),
		'uninstalled_dependency_api_data' => array(),
		'plugin_card_data'                => array(),
	);

	/**
	 * Stores the plugins directory.
	 *
	 * @var string
	 */
	protected static $plugins_dir;

	/**
	 * Sets up the WP_Plugin_Dependencies instance and plugins directory before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		
		self::$instance    = new WP_Plugin_Dependencies();
		self::$plugins_dir = WP_PLUGIN_DIR . '/wp_plugin_dependencies_plugin';
		@mkdir( self::$plugins_dir );
	}

	/**
	 * Removes the plugins directory after all tests run.
	 */
	public static function tear_down_after_class() {
		array_map( 'unlink', array_filter( (array) glob( self::$plugins_dir . '/*' ) ) );
		rmdir( self::$plugins_dir );

		parent::tear_down_after_class();
	}

	/**
	 * Resets all static properties to a default value after each test.
	 */
	public function tear_down() {
		foreach ( self::$static_properties as $name => $default_value ) {
			$this->set_property_value( $name, $default_value );
		}

		parent::tear_down();
	}

	/**
	 * Temporarily modifies the accessibility of a property to change its value.
	 *
	 * @param string $property The property's name.
	 * @param mixed  $value The new value.
	 */
	public function set_property_value( $property, $value ) {
		$reflection_property = new ReflectionProperty( self::$instance, $property );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( self::$instance, $value );
		$reflection_property->setAccessible( false );
	}

	/**
	 * Temporarily modifies the accessibility of a property to get its value.
	 *
	 * @param string $property The property's name.
	 * @return mixed The value of the property.
	 */
	public function get_property_value( $property ) {
		$reflection_property = new ReflectionProperty( self::$instance, $property );
		$reflection_property->setAccessible( true );
		$value = $reflection_property->getValue( self::$instance );
		$reflection_property->setAccessible( false );
		return $value;
	}

	/**
	 * Makes a class method accessible.
	 *
	 * @param string $method The class method.
	 * @return ReflectionMethod The accessible method.
	 */
	protected function make_method_accessible( $method ) {
		$method = new ReflectionMethod( self::$instance, $method );
		$method->setAccessible( true );
		return $method;
	}
}
