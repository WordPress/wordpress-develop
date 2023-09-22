<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\CoreFunctionality;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids deregistering of core scripts (javascript).
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/#stylesheets-and-scripts
 *
 * @since 0.1.0
 */
class NoDeregisterCoreScriptSniff extends AbstractFunctionParameterSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $group_name = 'wp_deregister_script';

	/**
	 * Array of function and script handles used in core.
	 *
	 * {@internal List was probably based on the documentation of the wp_enqueue_script function.
	 *            List needs further verification and update.
	 *
	 *            @link https://developer.wordpress.org/reference/functions/wp_enqueue_script/}}
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	protected $target_functions = [
		'wp_deregister_script' => [
			'jcrop'                    => true,
			'swfobject'                => true,
			'swfupload'                => true,
			'swfupload-degrade'        => true,
			'swfupload-queue'          => true,
			'swfupload-handlers'       => true,
			'jquery'                   => true,
			'jquery-form'              => true,
			'jquery-color'             => true,
			'jquery-masonry'           => true,
			'masonry'                  => true,
			'jquery-ui-core'           => true,
			'jquery-ui-widget'         => true,
			'jquery-ui-accordion'      => true,
			'jquery-ui-autocomplete'   => true,
			'jquery-ui-button'         => true,
			'jquery-ui-datepicker'     => true,
			'jquery-ui-dialog'         => true,
			'jquery-ui-draggable'      => true,
			'jquery-ui-droppable'      => true,
			'jquery-ui-menu'           => true,
			'jquery-ui-mouse'          => true,
			'jquery-ui-position'       => true,
			'jquery-ui-progressbar'    => true,
			'jquery-ui-selectable'     => true,
			'jquery-ui-resizable'      => true,
			'jquery-ui-selectmenu'     => true,
			'jquery-ui-sortable'       => true,
			'jquery-ui-slider'         => true,
			'jquery-ui-spinner'        => true,
			'jquery-ui-tooltip'        => true,
			'jquery-ui-tabs'           => true,
			'jquery-effects-core'      => true,
			'jquery-effects-blind'     => true,
			'jquery-effects-bounce'    => true,
			'jquery-effects-clip'      => true,
			'jquery-effects-drop'      => true,
			'jquery-effects-explode'   => true,
			'jquery-effects-fade'      => true,
			'jquery-effects-fold'      => true,
			'jquery-effects-highlight' => true,
			'jquery-effects-pulsate'   => true,
			'jquery-effects-scale'     => true,
			'jquery-effects-shake'     => true,
			'jquery-effects-slide'     => true,
			'jquery-effects-transfer'  => true,
			'wp-mediaelement'          => true,
			'schedule'                 => true,
			'suggest'                  => true,
			'thickbox'                 => true,
			'hoverIntent'              => true,
			'jquery-hotkeys'           => true,
			'sack'                     => true,
			'quicktags'                => true,
			'iris'                     => true,
			'farbtastic'               => true,
			'colorpicker'              => true,
			'tiny_mce'                 => true,
			'autosave'                 => true,
			'wp-ajax-response'         => true,
			'wp-lists'                 => true,
			'common'                   => true,
			'editorremov'              => true,
			'editor-functions'         => true,
			'ajaxcat'                  => true,
			'admin-categories'         => true,
			'admin-tags'               => true,
			'admin-custom-fields'      => true,
			'password-strength-meter'  => true,
			'admin-comments'           => true,
			'admin-users'              => true,
			'admin-forms'              => true,
			'xfn'                      => true,
			'upload'                   => true,
			'postbox'                  => true,
			'slug'                     => true,
			'post'                     => true,
			'page'                     => true,
			'link'                     => true,
			'comment'                  => true,
			'comment-reply'            => true,
			'admin-gallery'            => true,
			'media-upload'             => true,
			'admin-widgets'            => true,
			'word-count'               => true,
			'theme-preview'            => true,
			'json2'                    => true,
			'plupload'                 => true,
			'plupload-all'             => true,
			'plupload-html4'           => true,
			'plupload-html5'           => true,
			'plupload-flash'           => true,
			'plupload-silverlight'     => true,
			'underscore'               => true,
			'backbone'                 => true,
			'imagesloaded'             => true,
		],
	];

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param array  $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {

		if ( ! isset( $parameters[1] ) ) {
			return;
		}

		$matched_parameter = $this->strip_quotes( $parameters[1]['raw'] );
		$first_param_token = $this->phpcsFile->findNext(
			Tokens::$emptyTokens,
			$parameters[1]['start'],
			( $parameters[1]['end'] + 1 ),
			true
		);

		if ( isset( $this->target_functions[ $matched_content ][ $matched_parameter ] ) ) {
			$this->throw_prohibited_error( $first_param_token, [ $matched_parameter ] );
			return;
		}

		// More extensive check to prevent people circumventing the sniff.
		$text                 = '';
		$found_variable_token = false;

		for ( $i = $parameters[1]['start']; $i <= $parameters[1]['end']; $i++ ) {
			if ( isset( Tokens::$stringTokens[ $this->tokens[ $i ]['code'] ] ) === false ) {
				if ( T_VARIABLE === $this->tokens[ $i ]['code']
					|| T_STRING === $this->tokens[ $i ]['code']
				) {
					$found_variable_token = true;
				}

				continue;
			}

			$text .= $this->strip_quotes( $this->tokens[ $i ]['content'] );
		}

		if ( isset( $this->target_functions[ $matched_content ][ $text ] ) ) {
			$this->throw_prohibited_error( $first_param_token, [ $text ] );
			return;
		}

		if ( true === $found_variable_token ) {
			$this->throw_variable_handle_warning( $first_param_token, [ $matched_content, $matched_parameter ] );
		}
	}

	/**
	 * Throw the error for deregistering a core script.
	 *
	 * @param int   $stackPtr The position of the first non-empty parameter token in the stack.
	 * @param array $data     Optional input for the data replacements.
	 */
	public function throw_prohibited_error( $stackPtr, $data = [] ) {
		$this->phpcsFile->addError(
			'Deregistering core script "%s" is prohibited.',
			$stackPtr,
			'Found',
			$data
		);
	}

	/**
	 * Throw a warning when a variable handle for deregistering a script is detected.
	 *
	 * @param int   $stackPtr The position of the first non-empty parameter token in the stack.
	 * @param array $data     Optional input for the data replacements.
	 */
	public function throw_variable_handle_warning( $stackPtr, $data = [] ) {
		$this->phpcsFile->addWarning(
			'Deregistering core scripts is prohibited. A variable script handle was found. Inspection of the %s() call needed. Found: %s',
			$stackPtr,
			'VariableHandleFound',
			$data
		);
	}
}
