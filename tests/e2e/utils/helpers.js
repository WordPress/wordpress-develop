/**
 * WordPress dependencies
 */
import {
	__experimentalRest as rest,
} from '@wordpress/e2e-test-utils';

/**
 * Get all installed themes
 * @returns {Promise<Array>} Array of installed themes
 */
async function getInstalledThemes() {
	const themes = await rest({
		method: 'GET',
		path: '/wp/v2/themes',
	});
	return themes;
}

/**
 * Check if a theme is installed
 * @param {string} themeSlug The slug of the theme to check
 * @returns {Promise<Object>} Object with a boolean property indicating
 * if the theme is installed and the the theme status
 */
async function checkThemeStatus(themeSlug) {
	const themes = await getInstalledThemes();
	const isThemeInstalled = themes.some(theme => theme.stylesheet === themeSlug);

	if (!isThemeInstalled) {
		return {
			isThemeInstalled
		}
	} else {
		const theme = themes.find(theme => theme.stylesheet === themeSlug);
		const themeStatus = theme.status;
		return {
			isThemeInstalled,
			themeStatus,
		}
	}
}

export {
	getInstalledThemes,
	checkThemeStatus,
};
