<?php
/**
 * Blocks API: WP_Block_Editor_Context class
 *
 * @package WordPress
 * @since 5.8.0
 */

/**
 * Contains information about a block editor being rendered. For example, which
 * type of editor it is or what post is being edited.
 *
 * @since 5.8.0
 */
final class WP_Block_Editor_Context {
	/**
	 * String that identifies which type of block editor is being rendered. Can
	 * be one of:
	 *
	 * - `'post'`    - The post editor, at `/wp-admin/edit.php`.
	 * - `'widgets'` - The widgets editor, at `/wp-admin/widgets.php` or `/wp-admin/customize.php`.
	 * - `'site'`    - The site editor, at `/wp-admin/site-editor.php`.
	 *
	 * Defaults to 'post'.
	 *
	 * @since 6.0.0
	 *
	 * @var string
	 */
	public $type = 'post';

	/**
	 * The post being edited by the block editor. Optional.
	 *
	 * @since 5.8.0
	 *
	 * @var WP_Post|null
	 */
	public $post = null;

	/**
	 * Whether the block editor is rendered within the Customizer at
	 * `/wp-admin/customize.php`. Defaults to `false`.
	 *
	 * @since 6.0.0
	 *
	 * @var bool
	 */
	public $is_customizer = false;

	/**
	 * Constructor.
	 *
	 * Populates optional properties for a given block editor context.
	 *
	 * @since 5.8.0
	 *
	 * @param array $settings The list of optional settings to expose in a given context.
	 */
	public function __construct( array $settings = array() ) {
		if ( isset( $settings['type'] ) ) {
			$this->type = $settings['type'];
		}
		if ( isset( $settings['post'] ) ) {
			$this->post = $settings['post'];
		}
		if ( isset( $settings['is_customizer'] ) ) {
			$this->is_customizer = $settings['is_customizer'];
		}
	}
}
