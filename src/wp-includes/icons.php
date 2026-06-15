<?php
/**
 * Icons API functions.
 *
 * @package WordPress
 * @subpackage Icons
 * @since 7.1.0
 */

/**
 * Registers a new icon collection.
 *
 * @since 7.1.0
 *
 * @param string $slug Icon collection slug.
 * @param array  $args {
 *     Arguments for registering an icon collection.
 *
 *     @type string $label       Required. A human-readable label for the icon collection.
 *     @type string $description Optional. A human-readable description for the icon collection.
 * }
 * @return bool True if the icon collection was registered successfully, else false.
 */
function wp_register_icon_collection( $slug, $args ) {
	return WP_Icon_Collections_Registry::get_instance()->register( $slug, $args );
}

/**
 * Unregisters an icon collection.
 *
 * @since 7.1.0
 *
 * @param string $slug Icon collection slug.
 * @return bool True if the icon collection was unregistered successfully, else false.
 */
function wp_unregister_icon_collection( $slug ) {
	return WP_Icon_Collections_Registry::get_instance()->unregister( $slug );
}

/**
 * Registers a new icon.
 *
 * @since 7.1.0
 *
 * @param string $icon_name Namespaced icon name in the form "collection/icon-name"
 *                          (e.g. "my-plugin/arrow-left"). The "core" collection is
 *                          reserved for WordPress core icons; third-party code should
 *                          register icons under its own collection rather than the
 *                          "core" collection.
 * @param array  $args {
 *     List of properties for the icon.
 *
 *     @type string $label     Required. A human-readable label for the icon.
 *     @type string $content   Optional. SVG markup for the icon.
 *                             If not provided, the content will be retrieved from the `file_path` if set.
 *                             If both `content` and `file_path` are not set, the icon will not be registered.
 *     @type string $file_path Optional. The full path to the file containing the icon content.
 * }
 * @return bool True if the icon was registered successfully, else false.
 */
function wp_register_icon( $icon_name, $args ) {
	return WP_Icons_Registry::get_instance()->register( $icon_name, $args );
}

/**
 * Unregisters an icon.
 *
 * @since 7.1.0
 *
 * @param string $icon_name Namespaced icon name in the form "collection/icon-name"
 *                          (e.g. "core/arrow-left").
 * @return bool True if the icon was unregistered successfully, else false.
 */
function wp_unregister_icon( $icon_name ) {
	return WP_Icons_Registry::get_instance()->unregister( $icon_name );
}

/**
 * Registers the default icon collections.
 *
 * @since 7.1.0
 * @access private
 */
function _wp_register_default_icon_collections() {
	wp_register_icon_collection(
		'core',
		array(
			'label'       => __( 'WordPress' ),
			'description' => __( 'Default icon collection.' ),
		)
	);
}

/**
 * Registers the default core icons from the manifest.
 *
 * @since 7.1.0
 * @access private
 */
function _wp_register_default_icons() {
	$icons_directory = ABSPATH . WPINC . '/images/icon-library/';
	$manifest_path   = ABSPATH . WPINC . '/assets/icon-library-manifest.php';

	if ( ! is_readable( $manifest_path ) ) {
		wp_trigger_error(
			__FUNCTION__,
			__( 'Core icon collection manifest is missing or unreadable.' )
		);
		return;
	}

	$collection = include $manifest_path;

	if ( empty( $collection ) ) {
		wp_trigger_error(
			__FUNCTION__,
			__( 'Core icon collection manifest is empty or invalid.' )
		);
		return;
	}

	foreach ( $collection as $icon_name => $icon_data ) {
		if (
			empty( $icon_data['filePath'] )
			|| ! is_string( $icon_data['filePath'] )
		) {
			_doing_it_wrong(
				__FUNCTION__,
				__( 'Core icon collection manifest must provide a valid "filePath" for each icon.' ),
				'7.0.0'
			);
			return;
		}

		wp_register_icon(
			'core/' . $icon_name,
			array(
				'label'     => $icon_data['label'],
				'file_path' => $icons_directory . $icon_data['filePath'],
			)
		);
	}
}
