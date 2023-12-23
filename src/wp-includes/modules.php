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
 * Registers the module if no module with that module identifier has already
 * been registered.
 *
 * @since 6.5.0
 *
 * @param string            $module_identifier The identifier of the module. Should be unique. It will be used in the final import map.
 * @param string            $src               Full URL of the module, or path of the script relative to the WordPress root directory.
 * @param array             $dependencies      Optional. An array of module identifiers of the dependencies of this module. The dependencies can be strings or arrays. If they are arrays, they need an `id` key with the module identifier, and can contain a `type` key with either `static` or `dynamic`. By default, dependencies that don't contain a type are considered static.
 * @param string|false|null $version           Optional. String specifying module version number. Defaults to false. It is added to the URL as a query string for cache busting purposes. If SCRIPT_DEBUG is true, the version is the current timestamp. If $version is set to false, the version number is the currently installed WordPress version. If $version is set to null, no version is added.
 */
function wp_register_module( $module_identifier, $src, $dependencies = array(), $version = false ) {
	WP_Modules::register( $module_identifier, $src, $dependencies, $version );
}

/**
 * Marks the module to be enqueued in the page.
 *
 * @since 6.5.0
 *
 * @param string $module_identifier The identifier of the module.
 */
function wp_enqueue_module( $module_identifier ) {
	WP_Modules::enqueue( $module_identifier );
}

/**
 * Unmarks the module so it is no longer enqueued in the page.
 *
 * @since 6.5.0
 *
 * @param string $module_identifier The identifier of the module.
 */
function wp_dequeue_module( $module_identifier ) {
	WP_Modules::dequeue( $module_identifier );
}
