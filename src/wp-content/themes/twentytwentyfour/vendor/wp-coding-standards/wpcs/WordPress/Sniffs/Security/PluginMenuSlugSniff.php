<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\Security;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;

/**
 * Warn about __FILE__ for page registration.
 *
 * @link    https://vip.wordpress.com/documentation/vip-go/code-review-blockers-warnings-notices/#using-__file__-for-page-registration
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.3.0
 * @since   0.11.0 Refactored to extend the new WordPressCS native
 *                 `AbstractFunctionParameterSniff` class.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   1.0.0  This sniff has been moved from the `VIP` category to the `Security` category.
 */
class PluginMenuSlugSniff extends AbstractFunctionParameterSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * @since 0.11.0
	 *
	 * @var string
	 */
	protected $group_name = 'add_menu_functions';

	/**
	 * Functions which can be used to add pages to the WP Admin menu.
	 *
	 * @since 0.3.0
	 * @since 0.11.0 Renamed from $add_menu_functions to $target_functions
	 *               and changed visibility to protected.
	 *
	 * @var array <string function name> => <array target parameter positions>
	 */
	protected $target_functions = array(
		'add_menu_page'       => array( 4 ),
		'add_object_page'     => array( 4 ),
		'add_utility_page'    => array( 4 ),
		'add_submenu_page'    => array( 1, 5 ),
		'add_dashboard_page'  => array( 4 ),
		'add_posts_page'      => array( 4 ),
		'add_media_page'      => array( 4 ),
		'add_links_page'      => array( 4 ),
		'add_pages_page'      => array( 4 ),
		'add_comments_page'   => array( 4 ),
		'add_theme_page'      => array( 4 ),
		'add_plugins_page'    => array( 4 ),
		'add_users_page'      => array( 4 ),
		'add_management_page' => array( 4 ),
		'add_options_page'    => array( 4 ),
	);

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since 0.11.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {
		foreach ( $this->target_functions[ $matched_content ] as $position ) {
			if ( isset( $parameters[ $position ] ) ) {
				$file_constant = $this->phpcsFile->findNext( \T_FILE, $parameters[ $position ]['start'], ( $parameters[ $position ]['end'] + 1 ) );

				if ( false !== $file_constant ) {
					$this->phpcsFile->addWarning( 'Using __FILE__ for menu slugs risks exposing filesystem structure.', $stackPtr, 'Using__FILE__' );
				}
			}
		}
	}

}
