<?php
/**
 * One `@type` entry of a hash, as read by `HashNotationVisitor`.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

/**
 * One `@type` entry of a hash, with the hash it opens, if any.
 *
 * This is a class rather than an array shape because an entry may open a hash of
 * its own, and an array shape cannot refer to itself, while a class can: its
 * `$children` are entries in turn.
 */
final class HashEntry {

	/**
	 * Type as written in the docblock.
	 *
	 * @var non-empty-string
	 */
	public string $type;

	/**
	 * Key name, without the `$`.
	 *
	 * Not a non-falsy string, since the `$0` of a `...$0` entry is a key of `0`.
	 *
	 * @var non-empty-string
	 */
	public string $name;

	/**
	 * Whether the entry is written as `...$name`, describing a repeated value.
	 *
	 * @var bool
	 */
	public bool $variadic;

	/**
	 * Description of the entry.
	 *
	 * Once `HashNotationVisitor::parse_entries()` has read the hash an entry opens,
	 * any text before the entry's `{` and the intro of that hash are on lines of
	 * their own, since the markers are matched per line.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Entries of the hash this entry opens, or empty if it opens none.
	 *
	 * @var list<HashEntry>
	 */
	public array $children = array();

	/**
	 * Constructor.
	 *
	 * @param non-empty-string $type        Type as written in the docblock.
	 * @param non-empty-string $name        Key name, without the `$`.
	 * @param bool             $variadic    Whether the entry is written as `...$name`.
	 * @param string           $description Description of the entry.
	 */
	public function __construct( string $type, string $name, bool $variadic, string $description ) {
		$this->type        = $type;
		$this->name        = $name;
		$this->variadic    = $variadic;
		$this->description = $description;
	}
}
