<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\Plugins;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * WordPress_Sniffs_Theme_CorrectTGMPAVersionSniff.
 *
 * Verifies that if the TGM Plugin Activation library is included, the correct version is used.
 * - Check whether the version included is up to date.
 * - Check whether the version included is downloaded via the Custom Generator with
 *   the correct settings.
 * - Check against a persistent manual search & replace error made by theme authors.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/...... @todo
 *
 * @since 0.1.0
 *
 * {@internal This sniff currently has not (yet) been detached from the WordPressCS\WordPress\Sniff class
 * as the `detect_manual_editing()` method uses the WPCS `get_function_call_parameter()` and
 * `strip_quotes()` methods.
 * If/when WPCS re-organizes the generic methods into traits as is being discussed in
 * {@link https://github.com/WordPress/WordPress-Coding-Standards/issues/1465},
 * this sniff could be decoupled and use the trait(s) instead.}}
 */
class CorrectTGMPAVersionSniff extends Sniff {

	const GITHUB_TGMPA_API_URL = 'https://api.github.com/repos/TGMPA/TGM-Plugin-Activation/releases/latest';

	const GITHUB_API_OAUTH_QUERY = '?access_token=%s';

	/**
	 * TGMPA flavour required.
	 *
	 * By default, any flavour is ok. If this property is set in a custom ruleset an additional
	 * check will be run to verify if the correct flavour of TGMPA is used.
	 *
	 * Note: The Custom Generator only has flavours for themes, so this property is only
	 * relevant when checking a theme.
	 *
	 * Valid values:
	 * - 'wporg'
	 * - 'themeforest'
	 *
	 * @var string
	 */
	public $required_flavour = '';

	/**
	 * GitHub oAuth token.
	 *
	 * Intended to be set in the ruleset.
	 *
	 * This is to prevent issues with rate limiting if a lot of requests are made from the same server.
	 *
	 * "Normal" users generaly won't need to set this, but if the sniffs are run for all themes
	 * uploaded to wordpress.org, that IP address might run into the rate limit of 60 calls per hour.
	 * Setting a oauth token in the custom ruleset used will prevent this.
	 *
	 * Alternatively, the token can also be set via an environment key called `GITHUB_OAUTH_TOKEN`.
	 *
	 * @var string
	 */
	public $github_oauth_token = '';

	/**
	 * Fall-back for the latest version number for if the API call fails.
	 *
	 * @var string
	 */
	private $current_version = '2.6.1';

	/**
	 * Whether or not a call has been made to the GitHub API to retrieve the current TGMPA version number.
	 *
	 * Note: an API call will only be made once per PHPCS run and only when the TGMPA library
	 * has been positively identified.
	 * The API call will be skipped when running the unit tests.
	 *
	 * @var bool
	 */
	private $gh_call_made = false;

	/**
	 * Classes and functions declared by TGMPA.
	 *
	 * @var array
	 */
	private $tgmpa_classes_functions = [
		// Classes.
		'TGM_Plugin_Activation'      => true,
		'TGMPA_List_Table'           => true,
		'TGM_Bulk_Installer'         => true,
		'TGM_Bulk_Installer_Skin'    => true,
		'TGMPA_Bulk_Installer'       => true,
		'TGMPA_Bulk_Installer_Skin'  => true,
		'TGMPA_Utils'                => true,
		// Functions.
		'load_tgm_plugin_activation' => true,
		'tgmpa_initialize'           => true, // New in v 2.6.2.
		'tgmpa'                      => true,
		'tgmpa_load_bulk_installer'  => true,
	];

	/**
	 * List of available TGMPA flavours - other than the default.
	 *
	 * Used to validate `$required_flavour` property.
	 *
	 * Keys are the allowed values for the flavour property.
	 * Value is how this is expanded in the @version tag used by TGMPA.
	 *
	 * @var array
	 */
	private $valid_flavours = [
		'wporg'       => 'WordPress.org',
		'themeforest' => 'ThemeForest',
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return [
			T_OPEN_TAG,
		];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_token( $stackPtr ) {

		$has_class_function = $this->phpcsFile->findNext( [ T_CLASS, T_FUNCTION ], ( $stackPtr + 1 ) );
		if ( false === $has_class_function ) {
			// No class, function or constant declaration found, definitely not TGMPA file.
			// Skip this file from further checks.
			return ( $this->phpcsFile->numTokens + 1 );
		}

		$is_tgmpa = false;

		// First check based on filename.
		$file_name = basename( $this->phpcsFile->getFileName() );
		if ( false !== $file_name ) {
			$file_name = strtolower( $file_name );
			if ( strpos( $file_name, 'class-tgm-plugin-activation.php' ) !== false ) {
				$is_tgmpa = true;
			} elseif ( defined( 'PHP_CODESNIFFER_IN_TESTS' ) && preg_match( '`class-tgm-plugin-activation[^.]+\.inc`', $file_name, $discard ) > 0 ) {
				$is_tgmpa = true;
			}
		}

		/*
		 * Otherwise, check whether any of the TGMPA classes or function names are encountered.
		 *
		 * Will detect TGMPA, even when:
		 * - the class and function prefix has been changed
		 * - the file has been split up into several files
		 * - the file is combined with other code
		 */
		if ( false === $is_tgmpa ) {
			while ( false !== $has_class_function ) {
				$name = $this->phpcsFile->getDeclarationName( $has_class_function );
				if ( ! empty( $name ) ) {
					if ( isset( $this->tgmpa_classes_functions[ $name ] ) ) {
						$is_tgmpa = true;
						break;
					} elseif ( strpos( $name, '_Plugin_Activation' ) !== false ) {
						// This may be TGMPA with renamed prefixes, so look for typical class comment.
						$prev = $this->phpcsFile->findPrevious( T_WHITESPACE, ( $has_class_function - 1 ), null, true );
						while ( false !== $prev && isset( Tokens::$commentTokens[ $this->tokens[ $prev ]['code'] ] ) ) {
							if ( ( T_COMMENT === $this->tokens[ $prev ]['code']
								|| T_DOC_COMMENT_STRING === $this->tokens[ $prev ]['code'] )
								&& ( strpos( $this->tokens[ $prev ]['content'], 'Automatic plugin installation and activation library' ) !== false
								|| strpos( $this->tokens[ $prev ]['content'], 'Automatic plugin installation and activation class' ) !== false )
							) {
								$is_tgmpa = true;
								break;
							}

							$prev = $this->phpcsFile->findPrevious( T_WHITESPACE, ( $prev - 1 ), null, true );
						}
					}
				}

				$start = ( $has_class_function + 1 );
				if ( isset( $this->tokens[ $has_class_function ]['scope_condition'], $this->tokens[ $stackPtr ]['scope_closer'] )
					&& $this->tokens[ $has_class_function ]['scope_condition'] === $has_class_function
				) {
					// Skip past anything within the class or function.
					$start = $this->tokens[ $stackPtr ]['scope_closer'];
				}

				$has_class_function = $this->phpcsFile->findNext( [ T_CLASS, T_FUNCTION ], $start );
			}
		}

		// If we're still not 100% sure this is TGMPA, exclude the file from further checks.
		if ( false === $is_tgmpa ) {
			return ( $this->phpcsFile->numTokens + 1 );
		}

		// Check whether the correct version of TGMPA is being used.
		$version = $this->uses_latest_version();

		// Check for typical manual search & replace error.
		$this->detect_manual_editing( $version );

		// No need to check the same file again.
		return ( $this->phpcsFile->numTokens + 1 );
	}

	/**
	 * Check whether the latest version of TGMPA is being used.
	 */
	protected function uses_latest_version() {
		if ( false === $this->gh_call_made ) {
			// Get the current version number for TGMPA from GitHub.
			$this->update_current_version();
			$this->gh_call_made = true;
		}

		/*
		 * Walk the doc block comments to find if this is the correct version of TGMPA.
		 * Normally this will be in the first doc block encountered, so this is not as 'heavy' as it looks.
		 */
		$next_doc_block    = 0;
		$version           = false;
		$pcre_esc_flavours = array_map(
			'preg_quote',
			$this->valid_flavours,
			array_fill( 0, count( $this->valid_flavours ), '`' )
		);
		$pcre_flavours     = implode( '|', $pcre_esc_flavours );

		do {
			$next_doc_block = $this->phpcsFile->findNext( T_DOC_COMMENT_OPEN_TAG, ( $next_doc_block + 1 ) );

			if ( false === $next_doc_block ) {
				break;
			}

			$tags = $this->get_docblock_tags( $next_doc_block );
			if ( empty( $tags ) ) {
				continue;
			}

			if ( ! isset( $tags['package'], $tags['version'] ) || 'TGM-Plugin-Activation' !== $tags['package'] ) {
				continue;
			}

			if ( preg_match( '`^([0-9\.]+(?:-(?:alpha|beta|RC)(?:[0-9])?)?)`', $tags['version'], $matches ) > 0 ) {

				$version = $matches[1];
				$this->phpcsFile->recordMetric( 0, 'Version', $version );

				if ( true === version_compare( $this->current_version, $version, '>' ) ) {
					$error = 'Upgrade of the included TGM plugin activation library required. Current version: %s. Found version: %s';
					$data  = [
						$this->current_version,
						$version,
					];
					$this->phpcsFile->addError( $error, 0, 'upgradeRequired', $data );

				} elseif ( true === version_compare( $this->current_version, $version, '<' ) ) {
					$error = 'Non-stable version of the TGM plugin activation library found. The current version is %s. Found version: %s';
					$data  = [
						$this->current_version,
						$version,
					];
					$this->phpcsFile->addError( $error, 0, 'unstableVersion', $data );
				}
				unset( $matches, $error, $data );

				if ( strpos( $tags['version'], 'for parent theme' ) !== false ) {
					$this->phpcsFile->recordMetric( 0, 'Used in', 'parent theme' );
				} elseif ( strpos( $tags['version'], 'for child theme' ) !== false ) {
					$this->phpcsFile->recordMetric( 0, 'Used in', 'child theme' );
				} else {
					$this->phpcsFile->recordMetric( 0, 'Used in', 'unknown' );
				}

				if ( preg_match( '`for publication on (' . $pcre_flavours . ')`i', $tags['version'], $flavour_match ) > 0 ) {
					$this->phpcsFile->recordMetric( 0, 'Publication Channel', $flavour_match[1] );
				} else {
					$this->phpcsFile->recordMetric( 0, 'Publication Channel', 'n/a' );
				}

				if ( ! empty( $this->required_flavour ) && isset( $this->valid_flavours[ $this->required_flavour ] ) ) {
					$flavour_phrase = sprintf( 'for publication on %s', $this->valid_flavours[ $this->required_flavour ] );
					if ( strpos( $tags['version'], $flavour_phrase ) === false ) {
						$warning = 'You are required to use a version of the TGM Plugin Activation library downloaded through the Custom TGMPA Generator. Download a fresh copy and make sure you select "%s" as your publication channel to get the correct version. http://tgmpluginactivation.com/download/';
						$this->phpcsFile->addWarning(
							$warning,
							0,
							'wrongVersion',
							[ $this->valid_flavours[ $this->required_flavour ] ]
						);
					}
				}
			}
			break;

		} while ( false !== $next_doc_block );

		// The file was recognized as TGMPA, but no valid file doc block for TGMPA was found.
		if ( false === $version ) {

			$this->phpcsFile->recordMetric( 0, 'Version', 'unknown' );

			$error = 'The TGM Plugin Activation library was detected, but the version could not be determined. Ensure you use the latest stable release of the TGM Plugin Activation library (%s). Download a fresh copy now using the Custom TGMPA Generator at http://tgmpluginactivation.com/download/';
			$data  = [ $this->current_version ];
			$this->phpcsFile->addError( $error, 0, 'versionUndetermined', $data );
			$has_error = true;
		}

		return $version;
	}

	/**
	 * Check against a typical manual search & replace error often encountered.
	 *
	 * In that case the `tgmpa` in `if ( ! function_exists( 'tgmpa' ) )` has been replaced
	 * with the theme slug causing fatal errors when an end-user also uses a plugin using TGMPA.
	 *
	 * @param string $version The version of TGMPA found.
	 */
	protected function detect_manual_editing( $version ) {
		// Skip this check for TGMPA versions which didn't have the `tgmpa()` function or
		// didn't have the `function_exists()` wrapper.
		if ( false === $version || true === version_compare( $version, '2.2.0', '<' ) ) {
			return;
		}

		$checkTokens = [
			// This is what we're looking for.
			T_FUNCTION         => true,
			// These are just here to be able to skip as much as we can.
			T_CLASS            => true,
			T_ARRAY            => true,
			T_OPEN_SHORT_ARRAY => true,
		];

		for ( $ptr = 0; $ptr < $this->phpcsFile->numTokens; $ptr++ ) {
			if ( ! isset( $checkTokens[ $this->tokens[ $ptr ]['code'] ] ) ) {
				continue;
			}

			// Skip as much as we can.
			if ( T_CLASS === $this->tokens[ $ptr ]['code'] && isset( $this->tokens[ $ptr ]['scope_closer'] ) ) {
				$ptr = $this->tokens[ $ptr ]['scope_closer'];
				continue;
			} elseif ( T_OPEN_SHORT_ARRAY === $this->tokens[ $ptr ]['code'] && isset( $this->tokens[ $ptr ]['bracket_closer'] ) ) {
				$ptr = $this->tokens[ $ptr ]['bracket_closer'];
				continue;
			} elseif ( T_ARRAY === $this->tokens[ $ptr ]['code'] && isset( $this->tokens[ $ptr ]['parenthesis_closer'] ) ) {
				$ptr = $this->tokens[ $ptr ]['parenthesis_closer'];
				continue;
			}

			// Detect whether this is the `tgmpa()` function declaration.
			if ( T_FUNCTION === $this->tokens[ $ptr ]['code'] ) {
				$function_name = $this->phpcsFile->getDeclarationName( $ptr );
				if ( 'tgmpa' !== $function_name ) {
					if ( isset( $this->tokens[ $ptr ]['scope_closer'] ) ) {
						// Skip the rest of the function.
						$ptr = $this->tokens[ $ptr ]['scope_closer'];
					}
					continue;
				}

				// Ok, found the tgmpa function declaration. Now let's check for the typical
				// manual text-domain replacement error.
				$function_exists = $this->phpcsFile->findPrevious( T_STRING, ( $ptr - 1 ), null, false, 'function_exists' );
				$param           = false;
				if ( false !== $function_exists ) {
					$param = $this->get_function_call_parameter( $function_exists, 1 );
					$param = $this->strip_quotes( $param['raw'] );
				}

				if ( false === $function_exists || 'tgmpa' !== $param ) {
					$this->phpcsFile->recordMetric( 0, 'Manual editing detected', 'yes' );
					$this->phpcsFile->addError(
						'Manual editing of the TGM Plugin Activation file detected. Your edit will cause fatal errors for end-users. Download an official copy using the Custom TGMPA Generator. http://tgmpluginactivation.com/download/',
						0,
						'ManualEditDetected',
						[],
						9
					);
				} else {
					$this->phpcsFile->recordMetric( 0, 'Manual editing detected', 'no' );
				}
				break;
			}
		}
	}

	/**
	 * Retrieve an array with the doc block tags from a T_DOC_COMMENT_OPEN_TAG.
	 *
	 * @param int $comment_opener The position of the comment opener.
	 *
	 * @return array
	 */
	protected function get_docblock_tags( $comment_opener ) {
		$tags   = [];
		$opener = $this->tokens[ $comment_opener ];

		if ( ! isset( $opener['comment_tags'] ) ) {
			return $tags;
		}

		$closer = null;
		if ( isset( $opener['comment_closer'] ) ) {
			$closer = $opener['comment_closer'];
		}

		$tag_count = count( $opener['comment_tags'] );

		for ( $i = 0; $i < $tag_count; $i++ ) {
			$tag_token = $opener['comment_tags'][ $i ];
			$tag       = trim( $this->tokens[ $tag_token ]['content'], '@' );

			$search_end = $closer;
			if ( ( $i + 1 ) < $tag_count ) {
				$search_end = $opener['comment_tags'][ ( $i + 1 ) ];
			}

			$value_token  = $this->phpcsFile->findNext( T_DOC_COMMENT_STRING, ( $tag_token + 1 ), $search_end );
			$tags[ $tag ] = trim( $this->tokens[ $value_token ]['content'] );
			unset( $tag_token, $tag, $search_end, $value );
		}

		return $tags;
	}

	/**
	 * Get the version number (tag_name) of the latest TGMPA release from the GitHub API.
	 */
	protected function update_current_version() {
		if ( defined( 'PHP_CODESNIFFER_IN_TESTS' ) || true === $this->gh_call_made ) {
			return;
		}

		$api_url     = self::GITHUB_TGMPA_API_URL;
		$oauth_token = false;
		if ( '' !== $this->github_oauth_token && is_string( $this->github_oauth_token ) ) {
			$oauth_token = $this->github_oauth_token;
		} elseif ( false !== getenv( 'GITHUB_OAUTH_TOKEN' ) ) {
			$oauth_token = getenv( 'GITHUB_OAUTH_TOKEN' );
		}

		if ( false !== $oauth_token ) {
			$api_url .= sprintf( self::GITHUB_API_OAUTH_QUERY, $oauth_token );
		}

		$stream_options = [
			'http' => [
				'method'           => 'GET',
				'user_agent'       => 'WordPress-Coding-Standards/Theme-Review-Sniffs',
				'protocol_version' => 1.1,
			],
		];
		$stream_context = stream_context_create( $stream_options );
		$response       = file_get_contents( $api_url, false, $stream_context );
		$headers        = $this->parse_response_headers( $http_response_header );

		// Check for invalid oAuth token response.
		if ( 401 === $headers['response_code'] && false !== $oauth_token ) {
			$this->phpcsFile->addWarning(
				'The GITHUB_OAUTH_TOKEN you provided is invalid. Please update the token in your custom ruleset or environment properties.',
				0,
				'githubOauthTokenInvalid'
			);
			$this->oauth_error = false;
			return;
		}

		// Check for rate limit error response.
		if ( 403 === $headers['response_code'] && '0' === $headers['X-RateLimit-Remaining'] ) {
			// @todo Add link to GH wiki page documenting the properties.
			$this->phpcsFile->addWarning(
				'You are running PHPCS more than 60 times per hour. You may want to consider setting the `github_oauth_token` property in your custom ruleset for Theme Review. For more information see: ... (GH wiki page).',
				0,
				'githubRateLimitReached'
			);
			$this->rate_limit_error = false;
			return;
		}

		if ( 200 !== $headers['response_code'] ) {
			// Something unexpected going on, just ignore it.
			return;
		}

		// Ok, we have received a valid response.
		$response = json_decode( $response );
		if ( ! empty( $response->tag_name ) && ( ! isset( $response->prerelease ) || false === $response->prerelease ) ) {
			// Should there be a check for `v` at the start of a version number ?
			$this->current_version = $response->tag_name;
		}
	}

	/**
	 * Parse HTTP response headers array to a more usable format.
	 *
	 * Based on http://php.net/manual/en/reserved.variables.httpresponseheader.php#117203
	 *
	 * @param array $headers HTTP response headers array.
	 *
	 * @return array
	 */
	private function parse_response_headers( $headers ) {
		$head = [];
		foreach ( $headers as $key => $value ) {
			$tag = explode( ':', $value, 2 );
			if ( isset( $tag[1] ) ) {
				$head[ trim( $tag[0] ) ] = trim( $tag[1] );
			} else {
				$head[] = $value;
				if ( preg_match( '`HTTP/[0-9\.]+\s+([0-9]+)`', $value, $out ) ) {
					$head['response_code'] = intval( $out[1] );
				}
			}
		}
		return $head;
	}

}
