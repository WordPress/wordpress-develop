PHP Compatibility Coding Standard for PHP CodeSniffer
=====================================================
[![Latest Stable Version](https://poser.pugx.org/phpcompatibility/php-compatibility/v/stable.png)](https://packagist.org/packages/phpcompatibility/php-compatibility)
[![Latest Unstable Version](https://poser.pugx.org/phpcompatibility/php-compatibility/v/unstable.png)](https://packagist.org/packages/phpcompatibility/php-compatibility)
![Awesome](https://img.shields.io/badge/awesome%3F-yes!-brightgreen.svg)
[![License](https://poser.pugx.org/phpcompatibility/php-compatibility/license.png)](https://github.com/PHPCompatibility/PHPCompatibility/blob/master/LICENSE)
[![Flattr this git repo](http://api.flattr.com/button/flattr-badge-large.png)](https://flattr.com/submit/auto?user_id=wimg&url=https://github.com/PHPCompatibility/PHPCompatibility&title=PHPCompatibility&language=&tags=github&category=software)

[![Build Status](https://travis-ci.org/PHPCompatibility/PHPCompatibility.svg?branch=develop)](https://travis-ci.org/PHPCompatibility/PHPCompatibility)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/PHPCompatibility/PHPCompatibility/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/PHPCompatibility/PHPCompatibility/)
[![Coverage Status](https://coveralls.io/repos/github/PHPCompatibility/PHPCompatibility/badge.svg?branch=develop)](https://coveralls.io/github/PHPCompatibility/PHPCompatibility?branch=develop)

[![Minimum PHP Version](https://img.shields.io/packagist/php-v/phpcompatibility/php-compatibility.svg?maxAge=3600)](https://packagist.org/packages/phpcompatibility/php-compatibility)
[![Tested on PHP 5.3 to nightly](https://img.shields.io/badge/tested%20on-PHP%205.3%20|%205.4%20|%205.5%20|%205.6%20|%207.0%20|%207.1%20|%207.2%20|%207.3%20|%207.4%20-brightgreen.svg?maxAge=2419200)](https://travis-ci.org/PHPCompatibility/PHPCompatibility)


This is a set of sniffs for [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) that checks for PHP cross-version compatibility.
It will allow you to analyse your code for compatibility with higher and lower versions of PHP. 

* [PHP Version Support](#php-version-support)
* [Requirements](#requirements)
* [Thank you](#thank-you)
* [Upgrading to PHPCompatibility 9.0.0](#warning-upgrading-to-phpcompatibility-900-warning)
* [Installation in a Composer project (method 1)](#installation-in-a-composer-project-method-1)
* [Installation via a git check-out to an arbitrary directory (method 2)](#installation-via-a-git-check-out-to-an-arbitrary-directory-method-2)
* [Sniffing your code for compatibility with specific PHP version(s)](#sniffing-your-code-for-compatibility-with-specific-php-versions)
    + [Using a framework/CMS/polyfill specific ruleset](#using-a-frameworkcmspolyfill-specific-ruleset)
* [Using a custom ruleset](#using-a-custom-ruleset)
    + [`testVersion` in the ruleset versus command-line](#testversion-in-the-ruleset-versus-command-line)
    + [PHPCompatibility specific options](#phpcompatibility-specific-options)
* [Projects extending PHPCompatibility](#projects-extending-phpcompatibility)
* [Contributing](#contributing)
* [License](#license)

PHP Version Support
-------

The project aims to cover all PHP compatibility changes introduced since PHP 5.0 up to the latest PHP release. This is an ongoing process and coverage is not yet 100% (if, indeed, it ever could be). Progress is tracked on [our GitHub issue tracker](https://github.com/PHPCompatibility/PHPCompatibility/issues).

Pull requests that check for compatibility issues in PHP 4 code - in particular between PHP 4 and PHP 5.0 - are very welcome as there are still situations where people need help upgrading legacy systems. However, coverage for changes introduced before PHP 5.1 will remain patchy as sniffs for this are not actively being developed at this time.

Requirements
-------

* PHP 5.3+ for use with PHP CodeSniffer 2.x.
* PHP 5.4+ for use with PHP CodeSniffer 3.x.

PHP CodeSniffer: 2.3.0+ or 3.0.2+.

The sniffs are designed to give the same results regardless of which PHP version you are using to run PHP CodeSniffer. You should get reasonably consistent results independently of the PHP version used in your test environment, though for the best results it is recommended to run the sniffs on PHP 5.4 or higher.

PHP CodeSniffer 2.3.0 is required for 90% of the sniffs, PHP CodeSniffer 2.6.0 or later is required for full support, notices may be thrown on older versions.

For running the sniffs on PHP 7.3, it is recommended to use PHP_CodeSniffer 3.3.1+, or, if needs be, PHP_CodeSniffer 2.9.2.
PHP_CodeSniffer < 2.9.2/3.3.1 is not fully compatible with PHP 7.3, which effectively means that PHPCompatibility can't be either.
While the sniffs will still work in _most_ cases, you can expect PHP warnings to be thrown.

For running the sniffs on PHP 7.4, it is recommended to use PHP_CodeSniffer 3.5.0+.

As of version 8.0.0, the PHPCompatibility standard can also be used with PHP CodeSniffer 3.x.

As of version 9.0.0, support for PHP CodeSniffer 1.5.x and low 2.x versions < 2.3.0 has been dropped.


Thank you
---------
Thanks to all [contributors](https://github.com/PHPCompatibility/PHPCompatibility/graphs/contributors) for their valuable contributions.

Thanks to [WP Engine](https://wpengine.com) for their support on the PHP 7.0 sniffs.


:warning: Upgrading to PHPCompatibility 9.0.0 :warning:
--------
This library has been reorganized. All sniffs have been placed in categories and a significant number of sniffs have been renamed.

If you use the complete `PHPCompatibility` standard without `exclude` directives in a custom ruleset and do not (yet) use the new-style PHP_CodeSniffer annotation as introduced in [PHP_CodeSniffer 3.2.0](https://github.com/squizlabs/PHP_CodeSniffer/releases/tag/3.2.0), this will have no noticeable effect and everything should work as before.

However, if you do use `exclude` directives for PHPCompatibility sniffs in a custom ruleset or if you use the [new-style PHP_CodeSniffer inline annotations](https://github.com/squizlabs/PHP_CodeSniffer/releases/3.2.0), you will need to update these when upgrading. This should be a one-time only change.
The changelog contains detailed information about all the sniff renames.

Please read the changelog for version [9.0.0](https://github.com/PHPCompatibility/PHPCompatibility/releases/tag/9.0.0) carefully before upgrading.


Installation in a Composer project (method 1)
-------------------------------------------

* Add the following lines to the `require-dev` section of your `composer.json` file.
    ```json
    "require-dev": {
        "phpcompatibility/php-compatibility": "*"
    },
    "prefer-stable" : true
    ```
* Next, PHP CodeSniffer has to be informed of the location of the standard.
    - If PHPCompatibility is the **_only_** external PHP CodeSniffer standard you use, you can add the following to your `composer.json` file to automatically run the necessary command:
        ```json
        "scripts": {
            "post-install-cmd": "\"vendor/bin/phpcs\" --config-set installed_paths vendor/phpcompatibility/php-compatibility",
            "post-update-cmd" : "\"vendor/bin/phpcs\" --config-set installed_paths vendor/phpcompatibility/php-compatibility"
        }
        ```
    - Alternatively - and **_strongly recommended_** if you use more than one external PHP CodeSniffer standard - you can use any of the following Composer plugins to handle this for you.

       Just add the Composer plugin you prefer to the `require-dev` section of your `composer.json` file.

       * [DealerDirect/phpcodesniffer-composer-installer](https://github.com/DealerDirect/phpcodesniffer-composer-installer):"^0.5.0"
       * [higidi/composer-phpcodesniffer-standards-plugin](https://github.com/higidi/composer-phpcodesniffer-standards-plugin)
       * [SimplyAdmire/ComposerPlugins](https://github.com/SimplyAdmire/ComposerPlugins). This plugin *might* still work, but appears to be abandoned.
    - As a last alternative in case you use a custom ruleset, _and only if you use PHP CodeSniffer version 2.6.0 or higher_, you can tell PHP CodeSniffer the path to the PHPCompatibility standard by adding the following snippet to your custom ruleset:
        ```xml
        <config name="installed_paths" value="vendor/phpcompatibility/php-compatibility" />
        ```
* Run `composer update --lock` to install both PHP CodeSniffer, the PHPCompatibility coding standard and - optionally - the Composer plugin.
* Verify that the PHPCompatibility standard is registered correctly by running `./vendor/bin/phpcs -i` on the command line. PHPCompatibility should be listed as one of the available standards.
* Now you can use the following command to inspect your code:
    ```bash
    ./vendor/bin/phpcs -p . --standard=PHPCompatibility
    ```

Installation via a git check-out to an arbitrary directory (method 2)
-----------------------

* Install [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) via [your preferred method](https://github.com/squizlabs/PHP_CodeSniffer#installation).

    PHP CodeSniffer offers a variety of installation methods to suit your work-flow: Composer, [PEAR](http://pear.php.net/PHP_CodeSniffer), a Phar file, zipped/tarred release archives or checking the repository out using Git.

    **Pro-tip:** Register the path to PHPCS in your system `$PATH` environment variable to make the `phpcs` command available from anywhere in your file system.
* Download the [latest PHPCompatibility release](https://github.com/PHPCompatibility/PHPCompatibility/releases) and unzip/untar it into an arbitrary directory.

    You can also choose to clone the repository using git to easily update your install regularly.
* Add the path to the directory in which you placed your copy of the PHPCompatibility repo to the PHP CodeSniffer configuration using the below command from the command line:
   ```bash
   phpcs --config-set installed_paths /path/to/PHPCompatibility
   ```
   I.e. if you placed the `PHPCompatibility` repository in the `/my/custom/standards/PHPCompatibility` directory, you will need to add that directory to the PHP CodeSniffer [`installed_paths` configuration variable](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Configuration-Options#setting-the-installed-standard-paths).

   **Warning**: :warning: The `installed_paths` command overwrites any previously set `installed_paths`. If you have previously set `installed_paths` for other external standards, run `phpcs --config-show` first and then run the `installed_paths` command with all the paths you need separated by comma's, i.e.:
   ```bash
   phpcs --config-set installed_paths /path/1,/path/2,/path/3
   ```

   **Pro-tip:** Alternatively, in case you use a custom ruleset _and only if you use PHP CodeSniffer version 2.6.0 or higher_, you can tell PHP CodeSniffer the path to the PHPCompatibility standard(s) by adding the following snippet to your custom ruleset:
   ```xml
   <config name="installed_paths" value="/path/to/PHPCompatibility" />
   ```
* Verify that the PHPCompatibility standard is registered correctly by running `phpcs -i` on the command line. PHPCompatibility should be listed as one of the available standards.
* Now you can use the following command to inspect your code:
    ```bash
    phpcs -p . --standard=PHPCompatibility
    ```

Sniffing your code for compatibility with specific PHP version(s)
------------------------------
* Run the coding standard from the command-line with `phpcs -p . --standard=PHPCompatibility`.
* By default, you will only receive notifications about deprecated and/or removed PHP features.
* To get the most out of the PHPCompatibility standard, you should specify a `testVersion` to check against. That will enable the checks for both deprecated/removed PHP features as well as the detection of code using new PHP features.
    - You can run the checks for just one specific PHP version by adding `--runtime-set testVersion 5.5` to your command line command.
    - You can also specify a range of PHP versions that your code needs to support. In this situation, compatibility issues that affect any of the PHP versions in that range will be reported: `--runtime-set testVersion 5.3-5.5`.
    - As of PHPCompatibility 7.1.3, you can omit one part of the range if you want to support everything above or below a particular version, i.e. use `--runtime-set testVersion 7.0-` to run all the checks for PHP 7.0 and above.
* By default the report will be sent to the console, if you want to save the report to a file, add the following to the command line command: `--report-full=path/to/report-file`.
    For more information and other reporting options, check the [PHP CodeSniffer wiki](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Reporting).


### Using a framework/CMS/polyfill specific ruleset

As of mid 2018, a limited set of framework/CMS specific rulesets is available. These rulesets are hosted in their own repositories.
* `PHPCompatibilityJoomla` [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityJoomla) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-joomla)
* `PHPCompatibilityWP` [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityWP) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-wp)

Since the autumn of 2018, there are also a number of PHP polyfill specific rulesets available:
* `PHPCompatibilityPasswordCompat` [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityPasswordCompat) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-passwordcompat): accounts for @ircmaxell's [`password_compat`](https://github.com/ircmaxell/password_compat) polyfill library.
* `PHPCompatibilityParagonie` [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityParagonie) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-paragonie): contains two rulesets which account for the Paragonie [`random_compat`](https://github.com/paragonie/random_compat) and [`sodium_compat`](https://github.com/paragonie/sodium_compat) polyfill libraries respectively.
* `PHPCompatibilitySymfony` [GitHub](https://github.com/PHPCompatibility/PHPCompatibilitySymfony) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-symfony): contains a number of rulesets which account for various PHP polyfill libraries offered by the Symfony project. For more details about the available rulesets, please check out the [README of the PHPCompatibilitySymfony](https://github.com/PHPCompatibility/PHPCompatibilitySymfony/blob/master/README.md) repository.

If you want to make sure you have all PHPCompatibility rulesets available at any time, you can use the `PHPCompatibilityAll` package [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityAll) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-all).

**IMPORTANT:** Framework/CMS/Polyfill specific rulesets do not set the minimum PHP version for your project, so you will still need to pass a `testVersion` to get the most accurate results.


Using a custom ruleset
------------------------------
Like with any PHP CodeSniffer standard, you can add PHPCompatibility to a custom PHP CodeSniffer ruleset.

```xml
<?xml version="1.0"?>
<ruleset name="Custom ruleset">
    <description>My rules for PHP CodeSniffer</description>

    <!-- Run against the PHPCompatibility ruleset -->
    <rule ref="PHPCompatibility"/>

    <!-- Run against a second ruleset -->
    <rule ref="PSR2"/>

</ruleset>
```

You can also set the `testVersion` from within the ruleset:
```xml
    <!-- Check for cross-version support for PHP 5.6 and higher. -->
    <config name="testVersion" value="5.6-"/>
```

Other advanced options, such as changing the message type or severity of select sniffs, as described in the [PHPCS Annotated ruleset](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Annotated-ruleset.xml) wiki page are, of course, also supported.

### `testVersion` in the ruleset versus command-line

In PHPCS 3.2.0 and lower, once you set the `testVersion` in the ruleset, you could not overrule it from the command-line anymore.
Starting with PHPCS 3.3.0, a `testVersion` set via the command-line will overrule the `testVersion` in the ruleset.

This allows for more flexibility when, for instance, your project needs to comply with PHP `5.5-`, but you have a bootstrap file which needs to be compatible with PHP `5.2-`.


### PHPCompatibility specific options

At this moment, there is one sniff which has a property which can be set via the ruleset. More custom properties may become available in the future.

The `PHPCompatibility.Extensions.RemovedExtensions` sniff checks for removed extensions based on the function prefix used for these extensions.
This might clash with userland functions using the same function prefix.

To whitelist userland functions, you can pass a comma-delimited list of function names to the sniff.
```xml
    <!-- Whitelist the mysql_to_rfc3339() and mysql_another_function() functions. -->
    <rule ref="PHPCompatibility.Extensions.RemovedExtensions">
        <properties>
            <property name="functionWhitelist" type="array" value="mysql_to_rfc3339,mysql_another_function"/>
        </properties>
    </rule>
```

This property was added in PHPCompatibility version 7.0.1.
As of PHPCompatibility version 8.0.0, this custom property is only supported in combination with PHP CodeSniffer > 2.6.0 due to an upstream bug (which was fixed in PHPCS 2.6.0).

Projects extending PHPCompatibility
--------------------------------------
There are hundreds of public projects using PHPCompatibility or extending on top of it. A short list of some that you might know or have a look at :
* [adamculp/php-code-quality](https://github.com/adamculp/php-code-quality) - a Docker image doing a lot of code quality checks
* [VFAC/PHP7Compatibility](https://vfac.fr/projects/php7compatibility) - a Docker container to check PHP7 Compatibility
* [grumphp-php-compatibility](https://github.com/wunderio/grumphp-php-compatibility) - A plugin for [GrumPHP](https://github.com/phpro/grumphp)
* PHPCompatibility Checker WordPress plugin : [Wordpress site](https://wordpress.org/plugins/php-compatibility-checker/) and [Github](https://github.com/wpengine/phpcompat/)
* [WordPress Tide project](https://wptide.org/)
* [PHPStorm has built-in support for PHPCompatibility](https://www.jetbrains.com/help/phpstorm/using-php-code-sniffer.html#788c81b6)

Contributing
-------
Contributions are very welcome. Please read the [CONTRIBUTING](.github/CONTRIBUTING.md) documentation to get started.

License
-------
This code is released under the GNU Lesser General Public License (LGPL). For more information, visit http://www.gnu.org/copyleft/lesser.html
