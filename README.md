# WordPress

Welcome to the WordPress development repository! Please check out the [contributor handbook](https://make.wordpress.org/core/handbook/) for information about how to open bug reports, contribute patches, test changes, write documentation, or get involved in any way you can.

* [Getting Started](#getting-started)
* [Credentials](#credentials)

## Getting Started

### Local development

WordPress is a PHP, MySQL, and JavaScript based project, and uses Node for its JavaScript dependencies. A local development environment is available to quickly get up and running.

You will need a basic understanding of how to use the command line on your computer. This will allow you to set up the local development environment, to start it and stop it when necessary, and to run the tests.

You will need Node and npm installed on your computer. Node is a JavaScript runtime used for developer tooling, and npm is the package manager included with Node. If you have a package manager installed for your operating system, setup can be as straightforward as:

* macOS: `brew install node`
* Windows: `choco install nodejs`
* Ubuntu: `apt install nodejs npm`

If you are not using a package manager, see the [Node.js download page](https://nodejs.org/en/download/) for installers and binaries.

**Note:** WordPress currently only officially supports Node.js `20.x` and npm `10.x`.

You will also need a container environment such as [Docker Desktop](https://www.docker.com/products/docker-desktop) installed and running on your computer. The container environment is the virtualization software that powers the local development environment and can be installed just like any other regular application.

**Note:** WordPress currently only officially supports Docker but several container environments are available and should generally be compatible, such as [Colima](https://github.com/abiosoft/colima), [OrbStack](https://orbstack.dev/), [Podman Desktop](https://podman-desktop.io/), and [Rancher Desktop](https://rancherdesktop.io/).

### Development Environment Commands

Ensure your container environment is running before using these commands.

#### To start the development environment for the first time

You can get started using the local development environment with these steps:

1. Go to https://github.com/WordPress/wordpress-develop and fork the repository to your own GitHub account. 
1. Then clone the forked repository to your computer using `git clone https://github.com/<your-username>/wordpress-develop.git`.
1. Navigate into the directory for the cloned repository using `cd wordpress-develop`.
1. Add the origin repo as an `upstream` remote via `git remote add upstream https://github.com/WordPress/wordpress-develop.git`.
1. Then you can keep your branches up to date via `git pull --ff upstream/trunk`, for example.

Alternatively, if you have the [GitHub CLI](https://cli.github.com/) installed, you can simply run `gh repo fork WordPress/wordpress-develop --clone --remote` ([docs](https://cli.github.com/manual/gh_repo_fork)). This command will:
1. Fork the repository to your account (use the `--org` flag to clone into an organization).
1. Clone the repository to your machine. 
1. Add `WordPress/wordpress-develop` as `upstream` and set it to the default `remote` repository

After this, remember to run `cd wordpress-develop`.

Once you have forked and cloned the repository to your computer, run the following commands in a terminal:

```
npm install
npm run build:dev
npm run env:start
npm run env:install
```

Your WordPress site will be accessible at http://localhost:8889. You can see or change configurations in the `.env` file located at the root of the project directory.

#### To watch for changes

If you're making changes to WordPress core files, you should start the file watcher in order to build or copy the files as necessary:

```
npm run dev
```

To stop the watcher, press `ctrl+c`.

#### To run a [WP-CLI](https://make.wordpress.org/cli/handbook/) command

```
npm run env:cli -- <command>
```

WP-CLI has [many useful commands](https://developer.wordpress.org/cli/commands/) you can use to work on your WordPress site. Where the documentation mentions running `wp`, run `npm run env:cli --` instead. For example:

```
npm run env:cli -- help
```

#### To run the tests

These commands run the PHP and end-to-end test suites, respectively:

```
npm run test:php
npm run test:e2e
```

You can pass extra parameters into the PHP tests by adding `--` and then the [command-line options](https://docs.phpunit.de/en/10.4/textui.html#command-line-options):

```
npm run test:php -- --filter <test name>
npm run test:php -- --group <group name or ticket number>
```

#### Generating a code coverage report
PHP code coverage reports are [generated daily](https://github.com/WordPress/wordpress-develop/actions/workflows/test-coverage.yml) and [submitted to Codecov.io](https://app.codecov.io/gh/WordPress/wordpress-develop).

After the local container environment has [been installed and started](#to-start-the-development-environment-for-the-first-time), the following command can be used to generate a code coverage report. 

```
npm run test:coverage
```

The command will generate three coverage reports in HTML, PHP, and text formats, saving them in the `coverage` folder.

**Note:** xDebug is required to generate a code coverage report, which can slow down PHPUnit significantly. Passing selection-based options such as `--group` or `--filter` can decrease the overall time required but will result in an incomplete report.

#### To restart the development environment

You may want to restart the environment if you've made changes to the configuration in the `docker-compose.yml` or `.env` files. Restart the environment with:

```
npm run env:restart
```

#### To stop the development environment

You can stop the environment when you're not using it to preserve your computer's power and resources:

```
npm run env:stop
```

#### To start the development environment again

Starting the environment again is a single command:

```
npm run env:start
```

#### Resetting the development environment

The development environment can be reset. This will destroy the database and attempt to remove the pulled container images.

```
npm run env:reset
```

### Apple Silicon machines and old MySQL/MariaDB versions

Older MySQL and MariaDB container images do not support Apple Silicon processors (M1, M2, etc.). This is true for:

- MySQL versions 5.7 and earlier
- MariaDB 5.5

When using these versions on an Apple Silicon machine, you must create a `docker-compose.override.yml` file with the following contents:

```
services:

  mysql:
    platform: linux/amd64
```

Additionally, the "Use Rosetta for x86/AMD64 emulation on Apple Silicon" setting in your container environment (if applicable) needs to be disabled for this workaround.

## Credentials

These are the default environment credentials:

* Database Name: `wordpress_develop`
* Username: `root`
* Password: `password`

To login to the site, navigate to http://localhost:8889/wp-admin.

* Username: `admin`
* Password: `password`

**Note:** With Codespaces, open the portforwarded URL from the ports tab in the terminal, and append `/wp-admin` to login to the site.

To generate a new password (recommended):

1. Go to the Dashboard
2. Click the Users menu on the left
3. Click the Edit link below the admin user
4. Scroll down and click 'Generate password'. Either use this password (recommended) or change it, then click 'Update User'. If you use the generated password be sure to save it somewhere (password manager, etc).
