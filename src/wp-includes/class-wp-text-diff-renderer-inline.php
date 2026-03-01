<?php
/**
 * Diff API: WP_Text_Diff_Renderer_inline class
 *
 * @package WordPress
 * @subpackage Diff
 * @since 4.7.0
 */

/**
 * Improved word splitting with better performance and clarity.
 *
 * @since 2.6.0
 * @extends Text_Diff_Renderer_inline
 */
#[AllowDynamicProperties]
class WP_Text_Diff_Renderer_inline extends Text_Diff_Renderer_inline {

    /**
     * Splits a string into an array of words, handling newlines and special characters.
     *
     * @param string $string The string to split.
     * @param string $newlineEscape The newline escape character (defaults to "\n").
     * @return array An array of words.
     */
    public function _splitOnWords(string $string, string $newlineEscape = "\n"): array
    {
        // Remove null characters.
        $string = str_replace("\0", '', $string);

        // Split words using efficient regular expression.
        $words = preg_split('/(?<!\w)(?!\d)[^\w\d](?!\w)(?!\d)/u', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Replace newlines with the desired escape character.
        return array_map(function ($word) use ($newlineEscape) {
            return ($word === "\n") ? $newlineEscape : $word;
        }, $words);
    }
}
