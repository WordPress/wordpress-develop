# WordPress

[![Build Status](https://img.shields.io/travis/com/WordPress/wordpress-develop/master.svg)](https://travis-ci.com/WordPress/wordpress-develop)

Welcome to the WordPress development repository! Please check out our [contributor handbook](https://make.wordpress.org/core/handbook/) for information about how to open bug reports, contribute patches, test, documention, or get involved in any way you can.

## Getting Started

WordPress is a PHP/MySQL-based project. We have a basic development environment that you can quickly get up and running with a few commands. First off, you will need to download and install [Docker](https://www.docker.com/products/docker-desktop), if you don't have it already. After that, there are a few commands to run:

### Development Environment Commands

Running these commands will start the development environment:

```
npm install
npm run build:dev
npm run env:start
npm run env:install
```

Additionally, `npm run env:stop` will stop the environment.

`npm run env:cli` runs the [WP-CLI tool](https://make.wordpress.org/cli/handbook/). WP-CLI has a lot of [useful commands](https://developer.wordpress.org/cli/commands/) you can use to work on your WordPress site. Where the documentation mentions running `wp`, run `npm run env:cli` instead. For example, `npm run env:cli help`.

`npm run test:php` and `npm run test:e2e` run the PHP and E2E test suites, respectively.
