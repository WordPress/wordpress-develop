/**
 * Types for the teletype settings, printed by `wp_enqueue_command_palette_assets()`.
 *
 * These live here rather than beside either script because two files share them: the loader
 * (js/_enqueues/admin/teletype-loader.js) reads the trigger, and the teletype
 * (js/_enqueues/admin/teletype.js) reads the styles and plays the strings.
 */

/**
 * Translated strings for the teletype.
 */
interface WPTeletypeI18n {
	/** Phrase that starts the scene when entered in the command palette. */
	trigger: string;
	/** Accessible name of the dialog. */
	label: string;
	/** Text of the Exit button. */
	exit: string;
	/** Description of the rain for screen readers. */
	rain: string;
	/** Lines typed on the white screen. */
	actOne: string[];
	/** The 2 and 1 typed after the white screen's last line. */
	countdown: string[];
	/** Lines typed on the black screen; %s is the display name. */
	actTwo: string[];
}

/**
 * Teletype settings.
 */
interface WPTeletypeSettings {
	/** URL of the teletype script, loaded on demand. */
	src: string;
	/** The current user's display name. */
	name: string;
	/** Styles injected while the scene plays. */
	styles: string;
	/** Translated strings. */
	i18n: WPTeletypeI18n;
}

interface Window {
	wpTeletype?: WPTeletypeSettings;
}
