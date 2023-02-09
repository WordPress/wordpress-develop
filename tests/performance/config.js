const gitRepoOwner = '10up';

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
