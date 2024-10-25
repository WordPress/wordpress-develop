<?php
/**
 * REST API: WP_REST_Edit_Site_Export_Controller class
 *
 * @package    WordPress
 * @subpackage REST_API
 */

/**
 * Controller which provides REST endpoint for exporting current templates
 * and template parts.
 *
 * @since 5.9.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Edit_Site_Export_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 5.9.0
	 */
	public function __construct() {
		$this->namespace = 'wp-block-editor/v1';
		$this->rest_base = 'export';
	}

	/**
	 * Registers the site export route.
	 *
	 * @since 5.9.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Checks whether a given request has permission to export.
	 *
	 * @since 5.9.0
	 *
	 * @return true|WP_Error True if the request has access, or WP_Error object.
	 */
	public function permissions_check() {
		$allowed_user_capability = apply_filters( 'allowed_user_role_to_export_theme', 'export' );

		if ( current_user_can( $allowed_user_capability ) ) {
			return true;
		}

		return new WP_Error(
			'rest_cannot_export_templates',
			__( 'Sorry, you are not allowed to export templates and template parts.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Output a ZIP file with an export of the current templates
	 * and template parts from the site editor, and close the connection.
	 *
	 * @since 5.9.0
	 *
	 * @return void|WP_Error
	 */
	public function export() {
		// Generate the export file.
		$filename = wp_generate_block_templates_export_file();

		if ( is_wp_error( $filename ) ) {
			$filename->add_data( array( 'status' => 500 ) );

			return $filename;
		}

		$theme_name = basename( get_stylesheet() );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename=' . $theme_name . '.zip' );
		header( 'Content-Length: ' . filesize( $filename ) );
		flush();
		readfile( $filename );
		unlink( $filename );
		exit;
	}
}
