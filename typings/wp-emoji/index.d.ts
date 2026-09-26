/**
 * Types for the emoji settings and for the vendored Twemoji library.
 *
 * These live here rather than beside either script because two files share them: the emoji loader
 * (js/_enqueues/lib/emoji-loader.js) reads the settings and records which emoji the browser
 * supports, and wp-emoji (js/_enqueues/wp/emoji.js) then reads both back.
 */

/**
 * Emoji script source URLs.
 *
 * Either `concatemoji` alone, or `wpemoji` together with `twemoji`, depending on whether the
 * concatenated script is in use.
 */
interface WPEmojiSettingsSource {
	/** The URL for the concatenated emoji script. */
	concatemoji?: string;
	/** The URL for the Twemoji script. */
	twemoji?: string;
	/** The URL for the WP Emoji script. */
	wpemoji?: string;
}

/**
 * Which emoji the browser supports.
 *
 * The individual test results are absent until the support tests have completed.
 */
interface EmojiSupports {
	/** Whether the browser passed every test. */
	everything: boolean;
	/** Whether the browser passed every test but the flag test. */
	everythingExceptFlag: boolean;
	/** Whether the browser renders flag emoji. */
	flag?: boolean;
	/** Whether the browser renders emoji. */
	emoji?: boolean;
}

/**
 * Emoji settings as exported in PHP via `_print_emoji_detection_script()`.
 */
interface WPEmojiSettings {
	/**
	 * Base URL for the PNG emoji images.
	 *
	 * No longer read by any script, since wp-emoji always uses the SVG images. Still exported, both
	 * for anything reading the settings itself and because the emoji_url filter behind it also
	 * feeds wp_staticize_emoji().
	 */
	baseUrl: string;
	/** File extension for the PNG emoji images. See the note on baseUrl. */
	ext: string;
	/** Base URL for the SVG emoji images. */
	svgUrl: string;
	/** File extension for the SVG emoji images. */
	svgExt: string;
	/** Emoji script source URLs. */
	source?: WPEmojiSettingsSource;
	/**
	 * Which emoji the browser supports.
	 *
	 * Not exported from PHP; populated by the emoji loader.
	 */
	supports: EmojiSupports;
}

/**
 * The emoji settings, as published by the emoji loader for other scripts to read.
 */
declare var _wpemojiSettings: WPEmojiSettings;

/**
 * Options accepted by `twemoji.parse()`.
 *
 * Taken from the vendored copy in js/_enqueues/vendor/twemoji.js rather than from the library's own
 * documentation, since that copy is patched: `doNotParse` is WordPress's own addition and is not
 * part of the library upstream.
 */
interface TwemojiParseOptions {
	/** Base URL to prepend to each image source. */
	base?: string;
	/** File extension to append to each image source. */
	ext?: string;
	/** Asset size, squared into a path segment: 72 becomes 72x72. */
	size?: string | number;
	/** Path segment to use in place of the size, when the assets are not in a square named folder. */
	folder?: string;
	/** Class name to give each generated image. */
	className?: string;
	/** Returns the source for an icon, or false to leave the character as it is. */
	callback?: ( icon: string, options: TwemojiResolvedParseOptions ) => string | false;
	/**
	 * Returns the attributes to set on each generated image.
	 *
	 * Null is allowed, and is what Twemoji itself falls back to: the result is only ever read with
	 * `for...in`, which does nothing when given it.
	 */
	attributes?: ( rawText: string, iconId: string ) => Record< string, string > | null;
	/** Runs on the generated image when it fails to load, with the image as `this`. */
	onerror?: ( this: HTMLImageElement ) => void;
	/**
	 * Returns true to leave an element, and everything under it, unparsed.
	 *
	 * Twemoji only calls this for element nodes, never for anything within an SVG, and never for
	 * script, style and the like, so callers do not have to test for any of those.
	 */
	doNotParse?: ( element: Element ) => boolean;
}

/**
 * The options as Twemoji passes them on to `callback`, once it has filled in its own defaults for
 * whatever the caller left out.
 */
interface TwemojiResolvedParseOptions extends TwemojiParseOptions {
	base: string;
	ext: string;
	size: string | number;
	className: string;
}

/**
 * The vendored Twemoji library.
 *
 * Absent until js/_enqueues/vendor/twemoji.js has loaded, so callers must guard with a `typeof`
 * check.
 *
 * Only what WordPress itself uses is declared. The library also exposes `replace()`, `test()`,
 * `convert`, and the defaults behind the options above, and `parse()` additionally accepts a
 * callback in place of the options object. None of that is described here.
 */
interface Twemoji {
	/** Replaces the emoji in an element, in place. */
	parse( node: HTMLElement, options?: TwemojiParseOptions ): HTMLElement;
	/** Replaces the emoji in a string and returns the result. */
	parse( text: string, options?: TwemojiParseOptions ): string;
	/** Replaces the emoji in whichever of the two was given. */
	parse( nodeOrText: HTMLElement | string, options?: TwemojiParseOptions ): HTMLElement | string;
}

declare var twemoji: Twemoji | undefined;
