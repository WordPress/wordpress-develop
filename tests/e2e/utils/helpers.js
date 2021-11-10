/**
 * WordPress dependencies
 */
import {
	__experimentalRest as rest,
} from '@wordpress/e2e-test-utils';

/**
 * Get all installed themes
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
