<?php
/**
 * Script Modules API: Script Module functions
 *
 * @since 6.5.0
 *
 * @package WordPress
 * @subpackage Script Modules
 */

/**
 * Registers the script module if no script module with that script module
 * identifier has already been registered.
 *
 * @since 6.5.0
 *
 * @param string            $id       The identifier of the script module. Should be unique. It will be used in the
 *                                    final import map.
 * @param string            $src      Optional. Full URL of the script module, or path of the script module relative
 *                                    to the WordPress root directory. If it is provided and the script module has
 *                                    not been registered yet, it will be registered.
 * @param array             $deps     {
 *                                        Optional. List of dependencies.
 *
 *                                        @type string|array $0... {
 *                                            An array of script module identifiers of the dependencies of this script
 *                                            module. The dependencies can be strings or arrays. If they are arrays,
 *                                            they need an `id` key with the script module identifier, and can contain
 *                                            an `import` key with either `static` or `dynamic`. By default,
 *                                            dependencies that don't contain an `import` key are considered static.
 *
 *                                            @type string $id     The script module identifier.
 *                                            @type string $import Optional. Import type. May be either `static` or
 *                                                                 `dynamic`. Defaults to `static`.
 *                                        }
 *                                    }
 * @param string|false|null $version  Optional. String specifying the script module version number. Defaults to false.
 *                                    It is added to the URL as a query string for cache busting purposes. If $version
 *                                    is set to false, the version number is the currently installed WordPress version.
 *                                    If $version is set to null, no version is added.
 */
function wp_register_script_module( string $id, string $src, array $deps = array(), $version = false ) {
	WP_Script_Modules::get_instance()->register( $id, $src, $deps, $version );
}

/**
 * Marks the script module to be enqueued in the page.
 *
 * If a src is provided and the script module has not been registered yet, it
 * will be registered.
 *
 * @since 6.5.0
 *
 * @param string            $id       The identifier of the script module. Should be unique. It will be used in the
 *                                    final import map.
 * @param string            $src      Optional. Full URL of the script module, or path of the script module relative
 *                                    to the WordPress root directory. If it is provided and the script module has
 *                                    not been registered yet, it will be registered.
 * @param array             $deps     {
 *                                        Optional. List of dependencies.
 *
 *                                        @type string|array $0... {
 *                                            An array of script module identifiers of the dependencies of this script
 *                                            module. The dependencies can be strings or arrays. If they are arrays,
 *                                            they need an `id` key with the script module identifier, and can contain
 *                                            an `import` key with either `static` or `dynamic`. By default,
 *                                            dependencies that don't contain an `import` key are considered static.
 *
 *                                            @type string $id     The script module identifier.
 *                                            @type string $import Optional. Import type. May be either `static` or
 *                                                                 `dynamic`. Defaults to `static`.
 *                                        }
 *                                    }
 * @param string|false|null $version  Optional. String specifying the script module version number. Defaults to false.
 *                                    It is added to the URL as a query string for cache busting purposes. If $version
 *                                    is set to false, the version number is the currently installed WordPress version.
 *                                    If $version is set to null, no version is added.
 */
function wp_enqueue_script_module( string $id, string $src = '', array $deps = array(), $version = false ) {
	WP_Script_Modules::get_instance()->enqueue( $id, $src, $deps, $version );
}

/**
 * Unmarks the script module so it is no longer enqueued in the page.
 *
 * @since 6.5.0
 *
 * @param string $id The identifier of the script module.
 */
function wp_dequeue_script_module( string $id ) {
	WP_Script_Modules::get_instance()->dequeue( $id );
}
