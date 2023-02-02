const gitRepoOwner = 'mukeshpanchal27';

/**
 * @typedef WPPluginCLIConfig
 *
 * @property {string} slug                    Slug.
 * @property {string} name                    Name.
 * @property {string} team                    GitHub Team Name.
 * @property {string} versionMilestoneFormat  printf template for milestone
 *                                            version name. Expected to be called
 *                                            with a merged object of the config
 *                                            and semver-parsed version parts.
 * @property {string} githubRepositoryOwner   GitHub Repository Owner.
 * @property {string} githubRepositoryName    GitHub Repository Name.
 * @property {string} pluginEntryPoint        Plugin Entry Point File.
 * @property {string} buildZipCommand         Build Plugin ZIP command.
 * @property {string} githubRepositoryURL     GitHub Repository URL.
 * @property {string} wpRepositoryReleasesURL WordPress Repository Tags URL.
 * @property {string} gitRepositoryURL        Git Repository URL.
 * @property {string} svnRepositoryURL        SVN Repository URL.
 */

/**
 * @type {WPPluginCLIConfig}
 */
const config = {
	slug: 'wordpress-develop',
	name: 'WordPress Develop',
	team: 'Performance Core',
	versionMilestoneFormat: '%(name)s %(major)s.%(minor)s',
	githubRepositoryOwner: gitRepoOwner,
	githubRepositoryName: 'wordpress-core',
	githubRepositoryURL: 'https://github.com/' + gitRepoOwner + '/wordpress-develop/',
	gitRepositoryURL: 'https://github.com/' + gitRepoOwner + '/wordpress-develop.git',
};

module.exports = config;