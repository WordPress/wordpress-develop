<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\CoreFunctionality;

use WordPressCS\WordPress\Sniffs\WP\PostsPerPageSniff as WPCSPostsPerPageSniff;

/**
 * Flag returning high or infinite posts_per_page.
 *
 * This sniff extends the upstream WPCS PostsPerPageSniff. The difference is that this
 * sniff will not only warn against a high pagination limit, but will also warn against
 * using -1 as posts_per_page setting while querying posts, due to detrimental effects
 * it has on query speed.
 *
 * @link https://github.com/WPTRT/WPThemeReview/issues/147
 *
 * @since 0.2.0
 */
class PostsPerPageSniff extends WPCSPostsPerPageSniff {

	/**
	 * Callback to process each confirmed key, to check value.
	 *
	 * @param  string $key   Array index / key.
	 * @param  mixed  $val   Assigned value.
	 * @param  int    $line  Token line.
	 * @param  array  $group Group definition.
	 * @return mixed         FALSE if no match, TRUE if matches, STRING if matches
	 *                       with custom error message passed to ->process().
	 */
	public function callback( $key, $val, $line, $group ) {
		$this->posts_per_page = (int) $this->posts_per_page;

		if ( '-1' === $val ) {
			return 'Disabling pagination is not advised, avoid setting `%s` to `%s`';
		}

		if ( $val > $this->posts_per_page ) {
			return 'Detected high pagination limit, `%s` is set to `%s`';
		}

		return false;
	}
}
