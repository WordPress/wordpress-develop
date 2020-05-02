# WordPress

[![Build Status](https://img.shields.io/travis/com/WordPress/wordpress-develop/master.svg)](https://travis-ci.com/WordPress/wordpress-develop)

Welcome to the WordPress development repository! Please check out the [contributor handbook](https://make.wordpress.org/core/handbook/) for information about how to open bug reports, contribute patches, test changes, write documentation, or get involved in any way you can.

* [Getting Started](#getting-started)
* [Credentials](#credentials)

## Getting Started

WordPress is a PHP, MySQL, and JavaScript based project, and uses uses Node for its JavaScript dependencies. A local development environment is available to quickly get up and running.

You will need a basic understanding of how to use the command line on your computer. This will allow you to set up the local development environment, to start it and stop it when necessary, and to run the tests.

You will need [Docker](https://www.docker.com/products/docker-desktop) installed and running on your computer. Docker is the virtualization software that powers the local development environment.

### Development Environment Commands

Ensure [Docker](https://www.docker.com/products/docker-desktop) is running before using these commands.

#### To start the development environment for the first time

```
npm install
npm run build:dev
npm run env:start
npm run env:install
```

The environment will be accessible at http://localhost:8889.

#### To watch for changes

If you're making changes to WordPress core files, you should start the file watcher in order to build or copy the files as necessary:

```
npm run watch
```

To stop the watcher, press `ctrl+c`.

#### To run a [WP-CLI](https://make.wordpress.org/cli/handbook/) command

```
npm run env:cli <command>
```

WP-CLI has a lot of [useful commands](https://developer.wordpress.org/cli/commands/) you can use to work on your WordPress site. Where the documentation mentions running `wp`, run `npm run env:cli` instead. For example:

```
npm run env:cli help
```

#### To run the tests

These commands run the PHP and end-to-end test suites, respectively:

```
npm run test:php
npm run test:e2e
```

#### To stop the development environment

You can stop the environment when you're not using it to preserve your computer's power and resources:

```
npm run env:stop
```

#### To start the development environment again

Restarting the environment again is a single command:

```
npm run env:start
```

## Credentials

These are the default environment credentials:

* Database Name: `wordpress_develop`
* Username: `root`
* Password: `password`

To login to the site, navigate to http://localhost:8889/wp-admin.

* Username: `admin`
* Password: `password`

To generate a new password (recommended):

1. Go to the Dashboard
2. Click the Users menu on the left
3. Click the Edit link below the admin user
4. Scroll down and click 'Generate password'. Either use this password (recommended) or change it, then click 'Update User'. If you use the generated password be sure to save it somewhere (password manager, etc).
