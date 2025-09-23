# WordPress

Welcome to the WordPress development repository!  
Please check out the [Contributor Handbook](https://make.wordpress.org/core/handbook/) for information about opening bug reports, contributing patches, testing changes, writing documentation, or getting involved in any way you can.

* [Getting Started](#getting-started)
* [Windows Users](#windows-users)
* [Credentials](#credentials)

---

## Getting Started

### Using GitHub Codespaces

To get started, create a codespace for this repository by clicking below:

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=trunk&repo=75645659)

A codespace will open in a web-based version of Visual Studio Code. The [dev container](.devcontainer/devcontainer.json) is fully configured with all required software.

**Note:**  
Dev containers are supported by [GitHub Codespaces](https://github.com/codespaces) and [other tools](https://containers.dev/supporting).

In some browsers, the keyboard shortcut for opening the command palette (Ctrl/Command + Shift + P) may conflict with browser shortcuts. You can open the command palette via the `F1` key or the cog icon in the bottom left of the editor.

When opening your codespace, wait for the `postCreateCommand` to finish running to ensure your WordPress install is set up. This may take a few minutes.

### Local Development

WordPress is a PHP, MySQL, and JavaScript-based project, using Node for JavaScript dependencies.  
A local development environment is available for quick setup.

You will need:

- Basic command line knowledge
- [Node.js](https://nodejs.org/en/download/) (version 20.x recommended)
- [npm](https://www.npmjs.com/) (comes with Node.js)
- [Docker Desktop](https://www.docker.com/products/docker-desktop) or a compatible container environment

Install Node and npm using your OS package manager:

* macOS: `brew install node`
* Windows: `choco install nodejs`
* Ubuntu: `apt install nodejs npm`

If you are not using a package manager, see the [Node.js download page](https://nodejs.org/en/download/) for installers.

**Note:**  
WordPress officially supports Node.js `20.x` and npm `10.x`.

You will also need a container environment such as Docker Desktop. Other compatible options include [Colima](https://github.com/abiosoft/colima), [OrbStack](https://orbstack.dev/), [Podman Desktop](https://podman-desktop.io/), and [Rancher Desktop](https://rancherdesktop.io/).

---

## Windows Users

If you are on Windows, you may encounter specific warnings or errors during setup.  
This section provides solutions and tips for a smooth experience.

### Common Issues & Solutions

#### 1. Unknown npm config warnings

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

#### 2. ENOENT: package.json not found

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

#### 3. Windows Convenience Script

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

## Development Environment Commands

Ensure your container environment is running before using these commands.

### To start the development environment for the first time

Clone the repository:

```bash
git clone https://github.com/WordPress/wordpress-develop.git
cd wordpress-develop
npm install
npm run build:dev
npm run env:start
npm run env:install
```

Your WordPress site will be accessible at [http://localhost:8889](http://localhost:8889).  
Configuration options are in the `.env` file at the project root.

### To watch for changes

If you're making changes to WordPress core files, start the file watcher:

```bash
npm run dev
```

To stop the watcher, press `Ctrl + C`.

### To run a [WP-CLI](https://make.wordpress.org/cli/handbook/) command

```bash
npm run env:cli -- <command>
# Example:
npm run env:cli -- help
```

WP-CLI has [many useful commands](https://developer.wordpress.org/cli/commands/).  
Where documentation mentions running `wp`, use `npm run env:cli --` instead.

### To run the tests

Run the PHP and end-to-end test suites:

```bash
npm run test:php
npm run test:e2e
```

You can pass extra parameters into the PHP tests:

```bash
npm run test:php -- --filter <test name>
npm run test:php -- --group <group name or ticket number>
```

### Generating a code coverage report

PHP code coverage reports are [generated daily](https://github.com/WordPress/wordpress-develop/actions/workflows/test-coverage.yml) and [submitted to Codecov.io](https://app.codecov.io/gh/WordPress/wordpress-develop).

After the local container environment has [been installed and started](#to-start-the-development-environment-for-the-first-time), generate a code coverage report:

```bash
npm run test:coverage
```

The command will generate three coverage reports in HTML, PHP, and text formats, saving them in the `coverage` folder.

**Note:**  
xDebug is required to generate a code coverage report, which can slow down PHPUnit significantly. Passing selection-based options such as `--group` or `--filter` can decrease the overall time required but will result in an incomplete report.

### To restart the development environment

Restart the environment if you've made changes to the configuration in the `docker-compose.yml` or `.env` files:

```bash
npm run env:restart
```

### To stop the development environment

Stop the environment when you're not using it to preserve your computer's power and resources:

```bash
npm run env:stop
```

### To start the development environment again

Start the environment again with:

```bash
npm run env:start
```

### Resetting the development environment

Resetting will destroy the database and attempt to remove the pulled container images:

```bash
npm run env:reset
```

---

## Apple Silicon machines and old MySQL/MariaDB versions

Older MySQL and MariaDB container images do not support Apple Silicon processors (M1, M2, etc.):

- MySQL versions 5.7 and earlier
- MariaDB 5.5

When using these versions on Apple Silicon, create a `docker-compose.override.yml` file:

```yaml
services:
  mysql:
    platform: linux/amd64
```

Disable "Use Rosetta for x86/AMD64 emulation on Apple Silicon" in your container environment if applicable.

---

## Credentials

Default environment credentials:

* **Database Name:** `wordpress_develop`
* **Username:** `root`
* **Password:** `password`

To log in to the site, navigate to [http://localhost:8889/wp-admin](http://localhost:8889/wp-admin):

* **Username:** `admin`
* **Password:** `password`

**Note:**  
With Codespaces, open the port-forwarded URL from the ports tab in the terminal, and append `/wp-admin` to log in.

To generate a new password (recommended):

1. Go to the Dashboard
2. Click the Users menu on the left
3. Click Edit under the admin user
4. Scroll down and click 'Generate password'. Use or change the password, then click 'Update User'.  
   If you use the generated password, be sure to save it somewhere safe.

---

## Need Help?

For more information, troubleshooting, or to get involved, visit the [WordPress Contributor Handbook](https://make.wordpress.org/core/handbook/).

Happy coding!
