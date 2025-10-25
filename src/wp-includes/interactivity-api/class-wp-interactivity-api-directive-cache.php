<?php
/**
 * Interactivity API: WP_Interactivity_API_Directive_Cache class
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.10.0
 */

/**
 * Caches pre-parsed directive information for efficient template re-rendering.
 *
 * This class addresses a performance bottleneck in `data-wp-each` processing where
 * the same template is rendered N times (once per array item), causing O(N×M)
 * complexity. By caching the directive parsing results, we reduce redundant work.
 *
 * The cache maps tag occurrence indices to pre-parsed directive data. Since the
 * WP_HTML_Tag_Processor requires sequential scanning, we use tag index to identify
 * which tag's metadata to retrieve.
 *
 * What gets cached:
 * - Parsed directive names (prefix, suffix, unique_id)
 * - Extracted directive values (namespace, value)
 * - Directive entries for each prefix type
 *
 * What does NOT get cached:
 * - Directive evaluation results (these depend on context)
 * - Modified HTML (each iteration needs a fresh template)
 * - The HTML scanning itself (unavoidable with Tag Processor architecture)
 *
 * @since 6.10.0
 *
 * @access private
 *
 * @see WP_Interactivity_API::data_wp_each_processor()
 */
class WP_Interactivity_API_Directive_Cache {
	/**
	 * Cached directive entries organized by tag index and directive prefix.
	 *
	 * Structure:
	 * [
	 *   '{tag_index}:{directive_prefix}' => [
	 *     ['namespace' => 'plugin', 'value' => 'context.foo', 'suffix' => null, 'unique_id' => null],
	 *     ...
	 *   ],
	 *   ...
	 * ]
	 *
	 * @since 6.10.0
	 * @var array
	 */
	private $cache = array();

	/**
	 * Cached attribute names with 'data-wp-' prefix for each tag.
	 *
	 * Structure:
	 * [
	 *   {tag_index} => ['data-wp-text', 'data-wp-class--active', ...],
	 *   ...
	 * ]
	 *
	 * @since 6.10.0
	 * @var array
	 */
	private $directive_attributes_cache = array();

	/**
	 * Gets directive entries for a specific tag and prefix.
	 *
	 * If cached, returns the cached value. Otherwise, calls the parser function,
	 * caches the result, and returns it.
	 *
	 * @since 6.10.0
	 *
	 * @param int      $tag_index The tag occurrence index (0-based, increments with each tag that has directives).
	 * @param string   $prefix    The directive prefix (e.g., 'text', 'bind', 'class').
	 * @param callable $parser    Function to call if not cached. Signature: function($tag_index, $prefix): array
	 * @return array The directive entries for this tag and prefix.
	 */
	public function get_or_parse_entries( int $tag_index, string $prefix, callable $parser ): array {
		$cache_key = "{$tag_index}:{$prefix}";

		if ( ! isset( $this->cache[ $cache_key ] ) ) {
			$this->cache[ $cache_key ] = $parser( $tag_index, $prefix );
		}

		return $this->cache[ $cache_key ];
	}

	/**
	 * Caches the list of directive attribute names for a tag.
	 *
	 * @since 6.10.0
	 *
	 * @param int   $tag_index           The tag occurrence index.
	 * @param array $directive_attributes The array of directive attribute names.
	 */
	public function cache_directive_attributes( int $tag_index, array $directive_attributes ): void {
		$this->directive_attributes_cache[ $tag_index ] = $directive_attributes;
	}

	/**
	 * Gets cached directive attribute names for a tag.
	 *
	 * @since 6.10.0
	 *
	 * @param int $tag_index The tag occurrence index.
	 * @return array|null The directive attribute names, or null if not cached.
	 */
	public function get_directive_attributes( int $tag_index ): ?array {
		return $this->directive_attributes_cache[ $tag_index ] ?? null;
	}

	/**
	 * Checks if directive entries are cached for a specific tag and prefix.
	 *
	 * @since 6.10.0
	 *
	 * @param int    $tag_index The tag occurrence index.
	 * @param string $prefix    The directive prefix.
	 * @return bool Whether the entries are cached.
	 */
	public function has_cached_entries( int $tag_index, string $prefix ): bool {
		$cache_key = "{$tag_index}:{$prefix}";
		return isset( $this->cache[ $cache_key ] );
	}

	/**
	 * Clears all cached data.
	 *
	 * @since 6.10.0
	 */
	public function clear(): void {
		$this->cache                      = array();
		$this->directive_attributes_cache = array();
	}

	/**
	 * Gets the total number of cached entries.
	 *
	 * Useful for debugging and testing.
	 *
	 * @since 6.10.0
	 *
	 * @return int The number of cached directive entry sets.
	 */
	public function get_cache_size(): int {
		return count( $this->cache );
	}
}

