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
	concatemoji?: string;
	twemoji?: string;
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
	/** Base URL for the PNG emoji images. */
	baseUrl: string;
	/** File extension for the PNG emoji images. */
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
 * Note that `doNotParse` is not part of the upstream library. The vendored copy in
 * js/_enqueues/vendor/twemoji.js was patched to add it.
 */
interface TwemojiParseOptions {
	/** Base URL to prepend to each image source. */
	base?: string;
	/** File extension to append to each image source. */
	ext?: string;
	/** Class name to give each generated image. */
	className?: string;
	/** Returns the source for an icon, or false to leave the character as it is. */
	callback?: ( icon: string, options: TwemojiResolvedParseOptions ) => string | false;
	/** Returns the attributes to set on each generated image. */
	attributes?: ( rawText: string, iconId: string ) => Record< string, string >;
	/** Runs on the generated image when it fails to load, with the image as `this`. */
	onerror?: ( this: HTMLImageElement ) => void;
	/** Returns true to leave a node, and everything under it, unparsed. */
	doNotParse?: ( node: Node ) => boolean;
}

/**
 * The options as Twemoji passes them on to `callback`, once it has filled in its own defaults for
 * whatever the caller left out.
 */
interface TwemojiResolvedParseOptions extends TwemojiParseOptions {
	base: string;
	ext: string;
	className: string;
}

/**
 * The vendored Twemoji library.
 *
 * Absent until js/_enqueues/vendor/twemoji.js has loaded, so callers must guard with a `typeof`
 * check. Only the members which WordPress itself uses are declared.
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
