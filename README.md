# WordPress

Welcome to the WordPress development repository! Please check out the [contributor handbook](https://make.wordpress.org/core/handbook/) for information about how to open bug reports, contribute patches, test changes, write documentation, or get involved in any way you can.

* [Getting Started](#getting-started)
* [Credentials](#credentials)

## Getting Started

### Using GitHub Codespaces

To get started, create a codespace for this repository by clicking this 👇 

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=trunk&repo=75645659)

A codespace will open in a web-based version of Visual Studio Code. The [dev container](.devcontainer/devcontainer.json) is fully configured with software needed for this project.

**Note**: Dev containers is an open spec which is supported by [GitHub Codespaces](https://github.com/codespaces) and [other tools](https://containers.dev/supporting).

In some browsers the keyboard shortcut for opening the command palette (Ctrl/Command + Shift + P) may collide with a browser shortcut. The command palette can be opened via the `F1` key or via the cog icon in the bottom left of the editor.

When opening your codespace, be sure to wait for the `postCreateCommand` to finish running to ensure your WordPress install is successfully set up. This can take a few minutes.

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

Clone the current repository using `git clone https://github.com/WordPress/wordpress-develop.git`. Then in your terminal move to the repository folder `cd wordpress-develop` and run the following commands:

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

## Getting Started for Windows Users

WordPress is built with PHP, MySQL, and JavaScript, and uses Node.js for JavaScript tooling.  
You will need:

- [Node.js](https://nodejs.org/en/download/) (version 20.x recommended)
- [npm](https://www.npmjs.com/)
- [Docker Desktop](https://www.docker.com/products/docker-desktop) (or compatible container environment)

Clone the repository and set up your environment:

```bash
git clone https://github.com/WordPress/wordpress-develop.git
cd wordpress-develop
npm install
npm run build:dev
npm run env:start
npm run env:install
```

Your WordPress site will be available at [http://localhost:8889](http://localhost:8889).  
Configuration options are in the `.env` file at the project root.

---

## Windows Users

If you are on Windows, you may encounter warnings or errors when running setup commands.

### Common Issues & Solutions

#### 1. **Unknown npm config warnings**

You may see warnings like:

```
npm WARN Unknown user config "disturl"
npm WARN Unknown user config "chromedriver-cdnurl"
# ...other similar warnings
```

These are harmless and caused by globally set npm configs.  
To remove them, run the following in your terminal:

```powershell
npm config delete disturl
npm config delete chromedriver-cdnurl
npm config delete electron-mirror
# Repeat for other warnings as needed
```

#### 2. **ENOENT: package.json not found**

Error example:

```
npm ERR! enoent Could not read package.json
```

**Solution:**  
Make sure you are in the repository root directory before running any npm commands:

```powershell
cd D:\contributions\wordpress-develop
npm install
npm run build:dev
npm run env:start
```

#### 3. **Windows Convenience Script**

To simplify setup, you can use a helper PowerShell script:

Create a file named `start-windows.ps1` in your repo root with the following content:

```powershell
# start-windows.ps1
Write-Host "Starting WordPress development environment on Windows..." -ForegroundColor Cyan

if (!(Test-Path "package.json")) {
    Write-Error "package.json not found! Ensure you are in the repository root."
    exit
}

Write-Host "Installing dependencies..." -ForegroundColor Green
npm install

Write-Host "Building dev files..." -ForegroundColor Green
npm run build:dev

Write-Host "Starting development environment..." -ForegroundColor Green
npm run env:start
```

Run it in PowerShell (as Administrator):

```powershell
.\start-windows.ps1
```

This script helps avoid ENOENT errors and npm warnings, streamlining setup for Windows developers.

---

## Development Commands

Ensure your container environment (e.g., Docker Desktop) is running before using these commands.

### First-Time Setup

```bash
git clone https://github.com/WordPress/wordpress-develop.git
cd wordpress-develop
npm install
npm run build:dev
npm run env:start
npm run env:install
```

### Watching for Changes

```bash
npm run dev
# Stop with Ctrl + C
```

### Running WP-CLI Commands

```bash
npm run env:cli -- <command>
# Example:
npm run env:cli -- help
```

---

## Testing

Run the PHP and end-to-end test suites:

```bash
npm run test:php
npm run test:e2e
```

Run specific tests:

```bash
npm run test:php -- --filter <test-name>
npm run test:php -- --group <group-name-or-ticket>
```

---

## Code Coverage

Generate a code coverage report (requires xDebug):

```bash
npm run test:coverage
```

Reports are generated in HTML, PHP, and text formats in the `coverage` folder.

---

## Restart / Stop / Reset Environment

```bash
npm run env:restart    # Restart environment
npm run env:stop       # Stop environment
npm run env:start      # Start environment
npm run env:reset      # Reset (destroys DB and images)
```

---

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
