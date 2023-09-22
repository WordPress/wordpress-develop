<div aria-hidden="true">

[![Latest Stable Version](https://poser.pugx.org/wp-coding-standards/wpcs/v/stable)](https://packagist.org/packages/wp-coding-standards/wpcs)
[![Travis Build Status](https://travis-ci.com/WordPress/WordPress-Coding-Standards.svg?branch=master)](https://travis-ci.com/WordPress/WordPress-Coding-Standards)
[![Release Date of the Latest Version](https://img.shields.io/github/release-date/WordPress/WordPress-Coding-Standards.svg?maxAge=1800)](https://github.com/WordPress/WordPress-Coding-Standards/releases)
:construction:
[![Latest Unstable Version](https://img.shields.io/badge/unstable-dev--develop-e68718.svg?maxAge=2419200)](https://packagist.org/packages/wp-coding-standards/wpcs#dev-develop)
[![Travis Build Status](https://travis-ci.com/WordPress/WordPress-Coding-Standards.svg?branch=develop)](https://travis-ci.com/WordPress/WordPress-Coding-Standards)
[![Last Commit to Unstable](https://img.shields.io/github/last-commit/WordPress/WordPress-Coding-Standards/develop.svg)](https://github.com/WordPress/WordPress-Coding-Standards/commits/develop)

[![Minimum PHP Version](https://img.shields.io/packagist/php-v/wp-coding-standards/wpcs.svg?maxAge=3600)](https://packagist.org/packages/wp-coding-standards/wpcs)
[![Tested on PHP 5.4 to 7.4 snapshot](https://img.shields.io/badge/tested%20on-PHP%205.4%20|%205.5%20|%205.6%20|%207.0%20|%207.1%20|%207.2%20|%207.3%20|%207.4snapshot-green.svg?maxAge=2419200)](https://travis-ci.com/WordPress/WordPress-Coding-Standards)

[![License: MIT](https://poser.pugx.org/wp-coding-standards/wpcs/license)](https://github.com/WordPress/WordPress-Coding-Standards/blob/develop/LICENSE)
[![Total Downloads](https://poser.pugx.org/wp-coding-standards/wpcs/downloads)](https://packagist.org/packages/wp-coding-standards/wpcs/stats)
[![Number of Contributors](https://img.shields.io/github/contributors/WordPress/WordPress-Coding-Standards.svg?maxAge=3600)](https://github.com/WordPress/WordPress-Coding-Standards/graphs/contributors)

</div>


# WordPress Coding Standards for PHP_CodeSniffer

* [Introduction](#introduction)
* [Project history](#project-history)
* [Installation](#installation)
    + [Requirements](#requirements)
    + [Composer](#composer)
    + [Standalone](#standalone)
* [Rulesets](#rulesets)
    + [Standards subsets](#standards-subsets)
    + [Using a custom ruleset](#using-a-custom-ruleset)
    + [Customizing sniff behaviour](#customizing-sniff-behaviour)
    + [Recommended additional rulesets](#recommended-additional-rulesets)
* [How to use](#how-to-use)
    + [Command line](#command-line)
    + [Using PHPCS and WPCS from within your IDE](#using-phpcs-and-wpcs-from-within-your-ide)
* [Running your code through WPCS automatically using CI tools](#running-your-code-through-wpcs-automatically-using-ci-tools)
    + [Travis CI](#travis-ci)
* [Fixing errors or whitelisting them](#fixing-errors-or-whitelisting-them)
    + [Tools shipped with WPCS](#tools-shipped-with-wpcs)
* [Contributing](#contributing)
* [License](#license)

## Introduction

This project is a collection of [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) rules (sniffs) to validate code developed for WordPress. It ensures code quality and adherence to coding conventions, especially the official [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/).

## Project history

 - On 22nd April 2009, the original project from [Urban Giraffe](https://urbangiraffe.com/articles/wordpress-codesniffer-standard/) was packaged and published.
 - In May 2011 the project was forked and [added](https://github.com/WordPress/WordPress-Coding-Standards/commit/04fd547c691ca2baae3fa8e195a46b0c9dd671c5) to GitHub by [Chris Adams](https://chrisadams.me.uk/).
 - In April 2012 [XWP](https://xwp.co/) started to dedicate resources to develop and lead the creation of the sniffs and rulesets for `WordPress-Core`, `WordPress-VIP` (WordPress.com VIP), and `WordPress-Extra`.
 - In May 2015, an initial documentation ruleset was [added](https://github.com/WordPress/WordPress-Coding-Standards/commit/b1a4bf8232a22563ef66f8a529357275a49f47dc#diff-a17c358c3262a26e9228268eb0a7b8c8) as `WordPress-Docs`.
 - In 2015, [J.D. Grimes](https://github.com/JDGrimes) began significant contributions, along with maintenance from [Gary Jones](https://github.com/GaryJones).
 - In 2016, [Juliette Reinders Folmer](https://github.com/jrfnl) began contributing heavily, adding more commits in a year than anyone else in the five years since the project was added to GitHub.
 - In July 2018, version [`1.0.0`](https://github.com/WordPress/WordPress-Coding-Standards/releases/tag/1.0.0) of the project was released.

## Installation

### Requirements

The WordPress Coding Standards require PHP 5.4 or higher and [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) version **3.3.1** or higher.

### Composer

Standards can be installed with the [Composer](https://getcomposer.org/) dependency manager:

    composer create-project wp-coding-standards/wpcs --no-dev

Running this command will:

1. Install WordPress standards into `wpcs` directory.
2. Install PHP_CodeSniffer.
3. Register WordPress standards in PHP_CodeSniffer configuration.
4. Make `phpcs` command available from `wpcs/vendor/bin`.

For the convenience of using `phpcs` as a global command, you may want to add the path to the `wpcs/vendor/bin` directory to a `PATH` environment variable for your operating system.

#### Installing WPCS as a dependency

When installing the WordPress Coding Standards as a dependency in a larger project, the above mentioned step 3 will not be executed automatically.

There are two actively maintained Composer plugins which can handle the registration of standards with PHP_CodeSniffer for you:
* [composer-phpcodesniffer-standards-plugin](https://github.com/higidi/composer-phpcodesniffer-standards-plugin)
* [phpcodesniffer-composer-installer](https://github.com/DealerDirect/phpcodesniffer-composer-installer):"^0.6"

It is strongly suggested to `require` one of these plugins in your project to handle the registration of external standards with PHPCS for you.

### Standalone

1. Install PHP_CodeSniffer by following its [installation instructions](https://github.com/squizlabs/PHP_CodeSniffer#installation) (via Composer, Phar file, PEAR, or Git checkout).

   Do ensure that PHP_CodeSniffer's version matches our [requirements](#requirements), if, for example, you're using [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV).

2. Clone the WordPress standards repository:

        git clone -b master https://github.com/WordPress/WordPress-Coding-Standards.git wpcs

3. Add its path to the PHP_CodeSniffer configuration:

        phpcs --config-set installed_paths /path/to/wpcs

   **Pro-tip:** Alternatively, you can tell PHP_CodeSniffer the path to the WordPress standards by adding the following snippet to your custom ruleset:
   ```xml
   <config name="installed_paths" value="/path/to/wpcs" />
   ```

To summarize:

```bash
cd ~/projects
git clone https://github.com/squizlabs/PHP_CodeSniffer.git phpcs
git clone -b master https://github.com/WordPress/WordPress-Coding-Standards.git wpcs
cd phpcs
./bin/phpcs --config-set installed_paths ../wpcs
```

And then add the `~/projects/phpcs/bin` directory to your `PATH` environment variable via your `.bashrc`.

You should then see `WordPress-Core` et al listed when you run `phpcs -i`.

##  Rulesets

### Standards subsets

The project encompasses a super-set of the sniffs that the WordPress community may need. If you use the `WordPress` standard you will get all the checks.

You can use the following as standard names when invoking `phpcs` to select sniffs, fitting your needs:

* `WordPress` - complete set with all of the sniffs in the project
  - `WordPress-Core` - main ruleset for [WordPress core coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/)
  - `WordPress-Docs` - additional ruleset for [WordPress inline documentation standards](https://make.wordpress.org/core/handbook/best-practices/inline-documentation-standards/)
  - `WordPress-Extra` - extended ruleset for recommended best practices, not sufficiently covered in the WordPress core coding standards
    - includes `WordPress-Core`

**Note:** The WPCS package used to include a `WordPress-VIP` ruleset and associated sniffs, prior to WPCS 2.0.0.
The `WordPress-VIP` ruleset was originally intended to aid with the [WordPress.com VIP coding requirements](https://vip.wordpress.com/documentation/vip-go/code-review-blockers-warnings-notices/), but has been superseded. It is recommended to use the [official VIP coding standards](https://github.com/Automattic/VIP-Coding-Standards) ruleset instead for checking code against the VIP platform requirements.

### Using a custom ruleset

If you need to further customize the selection of sniffs for your project - you can create a custom ruleset file. When you name this file either `.phpcs.xml`, `phpcs.xml`, `.phpcs.xml.dist` or `phpcs.xml.dist`, PHP_CodeSniffer will automatically locate it as long as it is placed in the directory from which you run the CodeSniffer or in a directory above it. If you follow these naming conventions you don't have to supply a `--standard` arg. For more info, read about [using a default configuration file](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Advanced-Usage#using-a-default-configuration-file). See also provided [`phpcs.xml.dist.sample`](phpcs.xml.dist.sample) file and [fully annotated example](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Annotated-ruleset.xml) in the PHP_CodeSniffer documentation.

### Customizing sniff behaviour

The WordPress Coding Standard contains a number of sniffs which are configurable. This means that you can turn parts of the sniff on or off, or change the behaviour by setting a property for the sniff in your custom `.phpcs.xml.dist` file.

You can find a complete list of all the properties you can change in the [wiki](https://github.com/WordPress/WordPress-Coding-Standards/wiki/Customizable-sniff-properties).

### Recommended additional rulesets

The [PHPCompatibility](https://github.com/PHPCompatibility/PHPCompatibility) ruleset and its subset [PHPCompatibilityWP](https://github.com/PHPCompatibility/PHPCompatibilityWP) come highly recommended.
The [PHPCompatibility](https://github.com/PHPCompatibility/PHPCompatibility) sniffs are designed to analyse your code for cross-PHP version compatibility.

The [PHPCompatibilityWP](https://github.com/PHPCompatibility/PHPCompatibilityWP) ruleset is based on PHPCompatibility, but specifically crafted to prevent false positives for projects which expect to run within the context of WordPress, i.e. core, plugins and themes.

Install either as a separate ruleset and run it separately against your code or add it to your custom ruleset, like so:
```xml
<config name="testVersion" value="5.2-"/>
<rule ref="PHPCompatibilityWP">
    <include-pattern>*\.php$</include-pattern>
</rule>
```

Whichever way you run it, do make sure you set the `testVersion` to run the sniffs against. The `testVersion` determines for which PHP versions you will receive compatibility information. The recommended setting for this at this moment is  `5.2-` to support the same PHP versions as WordPress Core supports.

For more information about setting the `testVersion`, see:
* [PHPCompatibility: Sniffing your code for compatibility with specific PHP version(s)](https://github.com/PHPCompatibility/PHPCompatibility#sniffing-your-code-for-compatibility-with-specific-php-versions)
* [PHPCompatibility: Using a custom ruleset](https://github.com/PHPCompatibility/PHPCompatibility#using-a-custom-ruleset)

## How to use

### Command line

Run the `phpcs` command line tool on a given file or directory, for example:

    phpcs --standard=WordPress wp-load.php

Will result in following output:

	------------------------------------------------------------------------------------------
	FOUND 8 ERRORS AND 10 WARNINGS AFFECTING 11 LINES
	------------------------------------------------------------------------------------------
	 24 | WARNING | [ ] error_reporting() can lead to full path disclosure.
	 24 | WARNING | [ ] error_reporting() found. Changing configuration at runtime is rarely
	    |         |     necessary.
	 37 | WARNING | [x] "require_once" is a statement not a function; no parentheses are
	    |         |     required
	 39 | WARNING | [ ] Silencing errors is discouraged
	 39 | WARNING | [ ] Silencing errors is discouraged
	 42 | WARNING | [x] "require_once" is a statement not a function; no parentheses are
	    |         |     required
	 46 | ERROR   | [ ] Inline comments must end in full-stops, exclamation marks, or
	    |         |     question marks
	 46 | ERROR   | [x] There must be no blank line following an inline comment
	 49 | WARNING | [x] "require_once" is a statement not a function; no parentheses are
	    |         |     required
	 54 | WARNING | [x] "require_once" is a statement not a function; no parentheses are
	    |         |     required
	 63 | WARNING | [ ] Detected access of super global var $_SERVER, probably needs manual
	    |         |     inspection.
	 63 | ERROR   | [ ] Detected usage of a non-validated input variable: $_SERVER
	 63 | ERROR   | [ ] Missing wp_unslash() before sanitization.
	 63 | ERROR   | [ ] Detected usage of a non-sanitized input variable: $_SERVER
	 69 | WARNING | [x] "require_once" is a statement not a function; no parentheses are
	    |         |     required
	 74 | ERROR   | [ ] Inline comments must end in full-stops, exclamation marks, or
	    |         |     question marks
	 92 | ERROR   | [ ] All output should be run through an escaping function (see the
	    |         |     Security sections in the WordPress Developer Handbooks), found
	    |         |     '$die'.
	 92 | ERROR   | [ ] All output should be run through an escaping function (see the
	    |         |     Security sections in the WordPress Developer Handbooks), found '__'.
	------------------------------------------------------------------------------------------
	PHPCBF CAN FIX THE 6 MARKED SNIFF VIOLATIONS AUTOMATICALLY
	------------------------------------------------------------------------------------------

### Using PHPCS and WPCS from within your IDE

* **PhpStorm** : Please see "[PHP Code Sniffer with WordPress Coding Standards Integration](https://confluence.jetbrains.com/display/PhpStorm/WordPress+Development+using+PhpStorm#WordPressDevelopmentusingPhpStorm-PHPCodeSnifferwithWordPressCodingStandardsIntegrationinPhpStorm)" in the PhpStorm documentation.
* **Sublime Text** : Please see "[Setting up WPCS to work in Sublime Text](https://github.com/WordPress/WordPress-Coding-Standards/wiki/Setting-up-WPCS-to-work-in-Sublime-Text)" in the wiki.
* **Atom**: Please see "[Setting up WPCS to work in Atom](https://github.com/WordPress/WordPress-Coding-Standards/wiki/Setting-up-WPCS-to-work-in-Atom)" in the wiki.
* **Visual Studio**: Please see "[Setting up PHP CodeSniffer in Visual Studio Code](https://tommcfarlin.com/php-codesniffer-in-visual-studio-code/)", a tutorial by Tom McFarlin.
* **Eclipse with XAMPP**: Please see "[Setting up WPCS when using Eclipse with XAMPP](https://github.com/WordPress/WordPress-Coding-Standards/wiki/How-to-use-WPCS-with-Eclipse-and-XAMPP)" in the wiki.


## Running your code through WPCS automatically using CI tools

### [Travis CI](https://travis-ci.com/)

To integrate PHPCS with WPCS with Travis CI, you'll need to install both `before_install` and add the run command to the `script`.
If your project uses Composer, the typical instructions might be different.

If you use a matrix setup in Travis to test your code against different PHP and/or WordPress versions, you don't need to run PHPCS on each variant of the matrix as the results will be same.
You can set an environment variable in the Travis matrix to only run the sniffs against one setup in the matrix.

#### Travis CI example
```yaml
language: php

matrix:
  include:
    # Arbitrary PHP version to run the sniffs against.
    - php: '7.0'
      env: SNIFF=1

before_install:
  - if [[ "$SNIFF" == "1" ]]; then export PHPCS_DIR=/tmp/phpcs; fi
  - if [[ "$SNIFF" == "1" ]]; then export SNIFFS_DIR=/tmp/sniffs; fi
  # Install PHP_CodeSniffer.
  - if [[ "$SNIFF" == "1" ]]; then git clone -b master --depth 1 https://github.com/squizlabs/PHP_CodeSniffer.git $PHPCS_DIR; fi
  # Install WordPress Coding Standards.
  - if [[ "$SNIFF" == "1" ]]; then git clone -b master --depth 1 https://github.com/WordPress/WordPress-Coding-Standards.git $SNIFFS_DIR; fi
  # Set install path for WordPress Coding Standards.
  - if [[ "$SNIFF" == "1" ]]; then $PHPCS_DIR/bin/phpcs --config-set installed_paths $SNIFFS_DIR; fi
  # After CodeSniffer install you should refresh your path.
  - if [[ "$SNIFF" == "1" ]]; then phpenv rehash; fi

script:
  # Run against WordPress Coding Standards.
  # If you use a custom ruleset, change `--standard=WordPress` to point to your ruleset file,
  # for example: `--standard=wpcs.xml`.
  # You can use any of the normal PHPCS command line arguments in the command:
  # https://github.com/squizlabs/PHP_CodeSniffer/wiki/Usage
  - if [[ "$SNIFF" == "1" ]]; then $PHPCS_DIR/bin/phpcs -p . --standard=WordPress; fi
```

More examples and advice about integrating PHPCS in your Travis build tests can be found here: https://github.com/jrfnl/make-phpcs-work-for-you/tree/master/travis-examples


## Fixing errors or whitelisting them

You can find information on how to deal with some of the more frequent issues in the [wiki](https://github.com/WordPress/WordPress-Coding-Standards/wiki).

### Tools shipped with WPCS

Since version 1.2.0, WPCS has a special sniff category `Utils`.

This sniff category contains some tools which, generally speaking, will only be needed to be run once over a codebase and for which the fixers can be considered _risky_, i.e. very careful review by a developer is needed before accepting the fixes made by these sniffs.

The sniffs in this category are disabled by default and can only be activated by adding some properties for each sniff via a custom ruleset.

At this moment, WPCS offer the following tools:
* `WordPress.Utils.I18nTextDomainFixer` - This sniff can replace the text domain used in a code-base.
    The sniff will fix the text domains in both I18n function calls as well as in a plugin/theme header.
    Passing the following properties will activate the sniff:
    - `old_text_domain`: an array with one or more (old) text domain names which need to be replaced;
    - `new_text_domain`: the correct (new) text domain as a string.


## Contributing

See [CONTRIBUTING](.github/CONTRIBUTING.md), including information about [unit testing](.github/CONTRIBUTING.md#unit-testing) the standard.

## License

See [LICENSE](LICENSE) (MIT).
