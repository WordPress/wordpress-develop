<?php
/**
 * Modules API: Module functions
 *
 * @since 6.5.0
 *
 * @package WordPress
 * @subpackage Modules
 */

/**
 * Retrieves the main WP_Modules instance.
 *
 * This function provides access to the WP_Modules instance, creating one if it
 * doesn't exist yet.
 *
 * @since 6.5.0
 *
 * @return WP_Modules The main WP_Modules instance.
 */
function wp_modules() {
	static $instance = null;
	if ( is_null( $instance ) ) {
		$instance = new WP_Modules();
	}
	return $instance;
}

/**
 * Registers the module if no module with that module identifier has already
 * been registered.
 *
 * @since 6.5.0
 *
 * @param string            $module_identifier The identifier of the module. Should be unique. It will be used in the
 *                                             final import map.
 * @param string            $src               Full URL of the module, or path of the script relative to the WordPress
 *                                             root directory.
 * @param array             $dependencies      Optional. An array of module identifiers of the dependencies of this
 *                                             module. The dependencies can be strings or arrays. If they are arrays,
 *                                             they need an `id` key with the module identifier, and can contain a
 *                                             `type` key with either `static` or `dynamic`. By default, dependencies
 *                                             that don't contain a type are considered static.
 * @param string|false|null $version           Optional. String specifying module version number. Defaults to false.
 *                                             It is added to the URL as a query string for cache busting purposes. If
 *                                             $version is set to false, the version number is the currently installed
 *                                             WordPress version. If $version is set to null, no version is added.
 */
function wp_register_module( $module_identifier, $src, $dependencies = array(), $version = false ) {
	wp_modules()->register( $module_identifier, $src, $dependencies, $version );
}

/**
 * Marks the module to be enqueued in the page.
 *
 * @since 6.5.0
 *
 * @param string $module_identifier The identifier of the module.
 */
function wp_enqueue_module( $module_identifier ) {
	wp_modules()->enqueue( $module_identifier );
}

/**
 * Unmarks the module so it is no longer enqueued in the page.
 *
 * @since 6.5.0
 *
 * @param string $module_identifier The identifier of the module.
 */
function wp_dequeue_module( $module_identifier ) {
	wp_modules()->dequeue( $module_identifier );
}

/**
 * Prints the import map using a script tag with a type="importmap" attribute.
 *
 * @since 6.5.0
 */
function wp_print_import_map() {
	wp_modules()->print_import_map();
}

/**
 * Prints all the enqueued modules using script tags with type="module"
 * attributes.
 *
 * @since 6.5.0
 */
function wp_print_enqueued_modules() {
	wp_modules()->print_enqueued_modules();
}

/**
 * Prints the the static dependencies of the enqueued modules using link tags
 * with rel="modulepreload" attributes.
 *
 * If a module has already been enqueued, it will not be preloaded.
 *
 * @since 6.5.0
 */
function wp_print_module_preloads() {
	wp_modules()->print_module_preloads();
}
