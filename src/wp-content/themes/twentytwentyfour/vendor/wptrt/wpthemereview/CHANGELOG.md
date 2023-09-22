# Change Log for the WPThemeReview PHP_CodeSniffer standard

All notable changes to this project will be documented in this file.

This projects adheres to [Semantic Versioning](https://semver.org/) and [Keep a CHANGELOG](https://keepachangelog.com/).

## [Unreleased]

_No documentation available about unreleased changes as of yet._

## [0.2.1] - 2019-11-17

### Changed

- Added new `$in_list` parameter to the `PrefixAllGlobalsSniff::process_variable_assignment()` method, so that the method is compatible with the upstream WPCS method (changed in WPCS 2.2.0).
- Updated the minimum version requirement for the WordPress Coding Standards dependency to version 2.2.0.
- Replaced the deprecated `WordPress.WP.TimezoneChange` with `WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set`
- Enforce the array short syntax when writing sniffs.
- Travis: Minor build script tweaks.

## [0.2.0] - 2019-07-17

### Added

- New `WPThemeReview.Templates.ReservedFileNamePrefix` sniff: checks if the template file is using a prefix which would cause WP to interpret it as a specialized template, meant to apply to only one page on the site.
- New `WPThemeReview.Privacy.ShortenedURLs` sniff: detects the usage of shortened URLs. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#privacy).
- New `WPThemeReview.CoreFunctionality.PostsPerPage` sniff: adds a warning when a high pagination limit is detected, or if `-1` is used in `posts_per_page` setting while querying posts, due to detrimental effects it has on the query speed.
- New `WPThemeReview.CoreFunctionality.PrefixAllGlobals` sniff, which extends the `WordPress.NamingConventions.PrefixAllGlobals`. The new sniff overloads the prefix check for variables only and will bow out if the file being scanned has a typical theme template file name. For all other files, it will fall through to the WPCS native sniff.
  Notes:
    * The new sniff adds a public `$allowed_folders` property to whitelist files in specific folders of a theme as template files.
    The `ruleset.xml` file sets this property to a limited set of folders whitelisted by default.
    * Similar to the WPCS `FileNameSniff`, this sniff does not currently allow for mimetype sublevel only theme template file names, such as `plain.php`.
- Added two new groups to the restricted functions group: `editor-blocks` and `cron-functionality` in the `WPThemeReview.PluginTerritory.ForbiddenFunctions` sniff, which will check against core editor blocks being registered in the themes, and against the usage of cron functions in the theme respectively.
- The `WordPress.PHP.IniSet` rule was added to the ruleset to check against themes setting ini configuration during runtime.
- The `WordPress.WP.DeprecatedParameterValues` rule was added to the ruleset about usage of deprecated parameter values in WP functions. The sniff will suggest an alternative based on the parameter passed.

### Changed

- Added XSD schema tags and validated the ruleset against schema (PHPCS 3.2+/3.3.2+).
- Updated the minimum version requirement for the [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards/blob/develop/CHANGELOG.md) dependency to version 2.1.0.
- Updated the suggested dealerdirect/phpcodesniffer-composer-installer version.
- Updated the `WPThemeReview` codebase, where relevant, for compatibility with WPCS 2.0+.
- Ruleset tweaks: limit PHPCompatibility to PHP files
- Composer tweaks: improve readability of script section
- Travis: Run the code style related and ruleset checks in separate [stages](https://docs.travis-ci.com/user/build-stages/).
- Travis: Unit tests are now also run against PHP 7.4 (dev)
- Travis: Test against high/low WPCS versions due to sniffs that are extending the WPCS native sniffs.
- Travis: Minor tweaks to the Travis script.

### Removed

- Remove `encoding` from the ruleset. The default `encoding` as of PHPCS 3.0.0 is `utf-8`, so we don't actually need to set this.

### Fixed

- Removal of HTML from error message about adding menu pages in `WPThemeReview.PluginTerritory.NoAddAdminPages` sniff.
- Minor grammar changes in the ruleset.


## [0.1.0] - 2018-10-31

### Added

#### Native sniffs
- `WPThemeReview.CoreFunctionality.FileInclude`: checks if a theme uses `include(_once)` or `require(_once)` when `get_template_part()` should be used. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#core-functionality-and-features).
- `WPThemeReview.CoreFunctionality.NoDeregisterCoreScript`: checks if a theme deregisters core scripts (javascript). [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#stylesheets-and-scripts).
- `WPThemeReview.CoreFunctionality.NoFavicon`: checks if a theme is hard coding favicons instead of using core implementation. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#core-functionality-and-features).
- `WPThemeReview.CoreFunctionality.NoTitleTag`: checks if a theme is using a `<title>` tag instead of `add_theme_support( 'title-tag' )`. `<svg>` tag can use a `<title>` tag. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#core-functionality-and-features).
- `WPThemeReview.PluginTerritory.AdminBarRemoval`: checks if a theme is removing the WP admin bar. This sniff was originally part of `WordPressCS`. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#core-functionality-and-features).
- `WPThemeReview.PluginTerritory.ForbiddenFunctions`: checks if a theme is using functions that fall under plugin territory. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#presentation-vs-functionality).
- `WPThemeReview.PluginTerritory.NoAddAdminPages`: checks if a theme is using `add_..._page()` functions, with the exception of `add_theme_page()`. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/theme-check-plugin/#admin-menu).
- `WPThemeReview.PluginTerritory.SessionFunctionsUsage`: Prevents the usage of the session functions in themes. This sniff was originally part of `WordPressCS`.
- `WPThemeReview.PluginTerritory.SessionVariableUsage`: Prevents the usage of the session variables in themes. This sniff was originally part of `WordPressCS`.
- `WPThemeReview.Plugins.CorrectTGMPAVersion`: verifies that if the [TGM Plugin Activation](http://tgmpluginactivation.com/) library is included, the correct version is used. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/theme-check-plugin/#admin-menu).
- `WPThemeReview.ThouShallNotUse.ForbiddenIframe`: checks if a theme is using `<iframe>`. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/theme-check-plugin/#info).
- `WPThemeReview.ThouShallNotUse.NoAutoGenerate`: checks if a theme has been generated using theme generators.

#### Other sniffs in the ruleset
- `PHPCompatibilityWP`: added as an entire ruleset, checking that the theme is compatible with PHP 5.2 and above.
- `Generic.PHP.DisallowShortOpenTag`: prohibits the usage of the PHP short open tags. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/theme-check-plugin/#additional-checks).
- `Generic.PHP.DisallowAlternativePHPTags`: disallows the usage of alternative PHP open tags (`<%` and similar).
- `Squiz.WhiteSpace.SuperfluousWhitespace.StartFile`: files starting with a PHP open tag shouldn't have a whitespace preceding it, to prevent possible `headers already sent` errors.
- `PSR2.Files.ClosingTag.NotAllowed`: files should omit the closing PHP tag at the end of a file, to prevent possible `headers already sent` errors.
- `Internal.LineEndings.Mixed`: mixed line endings are not allowed. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/theme-check-plugin/#line-endings).
- `Internal.Tokenizer.Exception`: minified scripts or files should have original files included. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#stylesheets-and-scripts).
- `Generic.Files.ByteOrderMark`: no ByteOrderMark allowed - important to prevent issues with content being sent before headers.
- `Generic.CodeAnalysis.EmptyStatement`: prohibits empty statements in the code (empty conditionals for instance).
- `WordPress.CodeAnalysis.EmptyStatement`: prohibits empty PHP statements (empty PHP tags with no content or double semi-colons).
- `WordPress.WP.I18n`: check that the I18N functions are used correctly. This sniff can also check the text domain, provided it's passed to `PHPCS`. See the [documentation](https://github.com/WordPress/WordPress-Coding-Standards/wiki/Customizable-sniff-properties#internationalization-setting-your-text-domain) for more details.
- `WordPress.WP.EnqueuedResources`: hard coding of scripts and styles is prohibited. They should be enqueued.
- `WordPress.Security.PluginMenuSlug`: prevent path disclosure when using add_theme_page().
- `Generic.PHP.NoSilencedErrors`: usage of Error Control Operator `@` is forbidden in a theme.
- `WordPress.DB.RestrictedClasses`: the WP abstraction layer should be used to query database if needed.
- `WordPress.DB.RestrictedFunctions`: the WP abstraction layer should be used to query database if needed.
- `WordPress.DB.PreparedSQL`: all SQL queries should be prepared as close to the time of querying the database as possible.
- `WordPress.DB.PreparedSQLPlaceholders`: verify that placeholders in prepared queries are used correctly.
- `WordPress.Security.ValidatedSanitizedInput`: validate and/or sanitize untrusted data before using it.
- `WordPress.Security.EscapeOutput`: all untrusted data should be escaped before output - warning, since translations don't have to be escaped.
- `Generic.PHP.BacktickOperator`: prohibit the use of the backtick operator.
- `WordPress.WP.GlobalVariablesOverride`: prohibit overwriting of WordPress global variables.
- `Squiz.PHP.Eval.Discouraged`: prohibit the use of the eval() PHP language construct.
- `Generic.PHP.DiscourageGoto.Found`: prohibit the use of the `goto` PHP language construct.
- `WordPress.WP.DeprecatedClasses`: check for use of deprecated WordPress classes.
- `WordPress.WP.DeprecatedFunctions`: check for use of deprecated WordPress functions.
- `WordPress.WP.DeprecatedParameters`: check for use of deprecated WordPress function parameters.
- `WordPress.WP.DiscouragedConstants`: check for deprecated WordPress constants.
- `WordPress.NamingConventions.PrefixAllGlobals`: verify that everything in the global namespace is prefixed. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#code). This rule will only work if a prefix is passed. See the [documentation](https://github.com/WordPress/WordPress-Coding-Standards/wiki/Customizable-sniff-properties#naming-conventions-prefix-everything-in-the-global-namespace) for more details.
- `WordPress.WP.CapitalPDangit`: check for correct spelling of WordPress. [Handbook rule](https://make.wordpress.org/themes/handbook/review/required/#naming)
- `WordPress.WP.TimezoneChange`: themes should never touch the timezone.

[Unreleased]: https://github.com/WPTRT/WPThemeReview/compare/master...HEAD
[0.2.1]: https://github.com/WPTRT/WPThemeReview/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/WPTRT/WPThemeReview/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/WPTRT/WPThemeReview/compare/1dabb9876caf78209849a01381c0b863ce583d07...0.1.0
