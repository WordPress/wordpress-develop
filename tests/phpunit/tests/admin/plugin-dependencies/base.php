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

	/**
	 * Stores a list of static properties and their default values.
	 * for resetting after each test runs.
	 *
	 * @var array
	 */
	protected static $static_properties = array(
		'plugins'               => array(),
		'plugin_dirnames'       => array(),
		'plugin_dirnames_cache' => array(),
		'dependencies'          => array(),
		'dependency_slugs'      => array(),
		'dependent_slugs'       => array(),
		'dependency_api_data'   => array(),
		'dependency_filepaths'  => array(),
	);

	/**
	 * Sets up the WP_Plugin_Dependencies instance before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . WPINC . '/class-wp-plugin-dependencies.php';
		self::$instance = new WP_Plugin_Dependencies();
	}

	/**
	 * Resets all static properties to a default value after each test.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( self::$static_properties as $name => $default_value ) {
			$this->set_property_value( $name, $default_value );
		}
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
	 * Temporarily modifies the accessibility of a method to invoke it
	 * and return its result.
	 *
	 * @param string $method  The method's name.
	 * @param mixed  ...$args Arguments for the method.
	 * @return mixed The result of the method call.
	 */
	protected function call_method( $method, ...$args ) {
		$reflection_method = new ReflectionMethod( self::$instance, $method );
		$reflection_method->setAccessible( true );
		$value = $reflection_method->invokeArgs( self::$instance, $args );
		$reflection_method->setAccessible( false );
		return $value;
	}
}
