<div aria-hidden="true">

[![Latest Version](https://poser.pugx.org/wptrt/wpthemereview/v/stable)](https://packagist.org/packages/wptrt/wpthemereview)
[![Travis Build Status](https://travis-ci.org/WPTRT/WPThemeReview.svg?branch=master)](https://travis-ci.org/WPTRT/WPThemeReview)
[![Last Commit to Unstable](https://img.shields.io/github/last-commit/WPTRT/WPThemeReview/develop.svg)](https://github.com/WPTRT/WPThemeReview/commits/develop)

[![Minimum PHP Version](https://img.shields.io/packagist/php-v/wptrt/wpthemereview.svg?maxAge=3600)](https://packagist.org/packages/wptrt/wpthemereview)
[![Tested on PHP 5.3 to nightly](https://img.shields.io/badge/tested%20on-PHP%205.3%20|%205.4%20|%205.5%20|%205.6%20|%207.0%20|%207.1%20|%207.2%20|%20nightly-green.svg?maxAge=2419200)](https://travis-ci.org/WPTRT/WPThemeReview)
[![License: MIT](https://poser.pugx.org/wptrt/wpthemereview/license)](https://github.com/WPTRT/WPThemeReview/blob/develop/LICENSE)
[![Number of Contributors](https://img.shields.io/github/contributors/WPTRT/WPThemeReview.svg?maxAge=3600)](https://github.com/WPTRT/WPThemeReview/graphs/contributors)

</div>


# WPThemeReview Standard for PHP_CodeSniffer

* [Introduction](#introduction)
* [Requirements](#requirements)
* [Installation](#installation)
    + [Installing WPThemeReview globally](#installing-wpthemereview-globally)
    + [Installing WPThemeReview as a project dependency](#installing-wpthemereview-as-a-project-dependency)
    + [Checking your installation was successful](#checking-your-installation-was-successful)
* [Using the WPThemeReview standard](#using-the-wpthemereview-standard)
* [Contributing](#contributing)
* [License](#license)

## Introduction

WordPress Themes for which a hosting application has been made for the theme to be hosted in the theme repository on [wordpress.org](https://wordpress.org/themes/) have to comply with [a set of requirements](https://make.wordpress.org/themes/handbook/review/required/) before such an application can be approved.
Additionally, there are also [recommendations](https://make.wordpress.org/themes/handbook/review/recommended/) for best practices for themes.

This project attempts to automate the code analysis part of the [Theme Review Process](https://make.wordpress.org/themes/handbook/review/) as much as possible using static code analysis.

[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) is the base tool upon which this project is build and is a PHP command-line tool.

**_This project is a work in progress and passing the checks is no guarantee that your theme will be approved._**


## Requirements

The WPThemeReview Standard requires:
* PHP 5.4 or higher.
* [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) version **3.3.1** or higher.
* [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) version **2.1.0** or higher.
* [PHPCompatibilityWP](https://github.com/PHPCompatibility/PHPCompatibilityWP) version **2.0.0** or higher.


## Installation

The only supported installation method is via [Composer](https://getcomposer.org/).

1. Make sure you have PHP installed on your system.
2. If not installed yet, install [Composer](https://getcomposer.org/download/).

### Installing WPThemeReview globally

If you would like to have the WPThemeReview standard available to all projects on your system, you can install it in a central location.

From the command-line, run the following command:
```bash
$ php composer.phar global require wptrt/wpthemereview dealerdirect/phpcodesniffer-composer-installer
```

### Installing WPThemeReview as a project dependency

If you use Composer to manage dependencies from your project anyway or are considering using it, you can also choose to install the WPThemeReview standard for an individual project.

From the command-line, run the following command from the root directory of your project:

```bash
$ php composer.phar require --dev wptrt/wpthemereview:* dealerdirect/phpcodesniffer-composer-installer:^0.5.0
```

> Note:
> * The `--dev` means that WPThemeReview will be installed as a development requirement, not as a requirement for using the Theme.
> * The second package - `dealerdirect/phpcodesniffer-composer-installer` -, is a Composer plugin which will automatically sort out that PHP_CodeSniffer recognizes the WPThemeReview standard and the various WordPress standards.

### Checking your installation was successful

```bash
# For a global install:
$ phpcs -i

# For a project install:
$ vendor/bin/phpcs -i
```

If everything went well, the output should look something like this:
```
The installed coding standards are MySource, PEAR, PSR1, PSR12, PSR2, Squiz, Zend, PHPCompatibility,
PHPCompatibilityParagonieRandomCompat, PHPCompatibilityParagonieSodiumCompat, PHPCompatibilityWP,
WordPress, WordPress-Core, WordPress-Docs, WordPress-Extra and WPThemeReview
```


## Using the WPThemeReview standard

You can now test your theme code against the WPThemeReview standard by running the following command from the root directory of your theme:
```bash
# For a global install:
$ phpcs -p . --standard=WPThemeReview

# For a project install:
$ vendor/bin/phpcs -p . --standard=WPThemeReview
```

If any issues are found, PHP_CodeSniffer will display a report with all the errors (must fix) and warnings (recommended to fix) for each file.

More information about running PHP_CodeSniffer can be found in the [PHP_CodeSniffer Wiki](https://github.com/squizlabs/PHP_CodeSniffer/wiki).


## Contributing

See [CONTRIBUTING](.github/CONTRIBUTING.md), including information about [unit testing](.github/CONTRIBUTING.md#unit-testing) the standard.

## License

See [LICENSE](LICENSE) (MIT).
