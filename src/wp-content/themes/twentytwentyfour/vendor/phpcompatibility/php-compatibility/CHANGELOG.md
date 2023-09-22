# Change Log for the PHPCompatibility standard for PHP Codesniffer

All notable changes to this project will be documented in this file.

This projects adheres to [Keep a CHANGELOG](http://keepachangelog.com/).

Up to version 8.0.0, the `major.minor` version numbers were based on the PHP version for which compatibility check support was added, with `patch` version numbers being specific to this library.
From version 8.0.0 onwards, [Semantic Versioning](http://semver.org/) is used.

<!-- Legend to the icons used: https://github.com/PHPCompatibility/PHPCompatibility/pull/506#discussion_r131650488 -->


## [Unreleased]

_Nothing yet._

## [9.3.5] - 2019-12-27

See all related issues and PRs in the [9.3.5 milestone].

### Added
- :star: `PHPCompatibility.Classes.NewClasses` sniff: recognize the new `FFI` extension related classes as introduced in PHP 7.4. [#949](https://github.com/PHPCompatibility/PHPCompatibility/pull/949)
- :star: `PHPCompatibility.IniDirectives.NewIniDirectives` sniff: detect use of the new `FFI` extension related ini directives as introduced in PHP 7.4. [#949](https://github.com/PHPCompatibility/PHPCompatibility/pull/949)

### Changed
- :pencil: `PHPCompatibility.Syntax.NewShortArray`: improved clarity of the error message and made it consistent with other error messages in this standard. [#934](https://github.com/PHPCompatibility/PHPCompatibility/pull/934)
- :pencil: `PHPCompatibility.Interfaces.NewInterfaces`: updated the URL which is mentioned in select error messages. [#942](https://github.com/PHPCompatibility/PHPCompatibility/pull/942)
- :recycle: Another slew of code documentation fixes. [#937](https://github.com/PHPCompatibility/PHPCompatibility/pull/937), [#939](https://github.com/PHPCompatibility/PHPCompatibility/pull/939), [#940](https://github.com/PHPCompatibility/PHPCompatibility/pull/940), [#941](https://github.com/PHPCompatibility/PHPCompatibility/pull/941), [#943](https://github.com/PHPCompatibility/PHPCompatibility/pull/943), [#944](https://github.com/PHPCompatibility/PHPCompatibility/pull/944), [#951](https://github.com/PHPCompatibility/PHPCompatibility/pull/951), [#950](https://github.com/PHPCompatibility/PHPCompatibility/pull/950). Fixes [#734](https://github.com/PHPCompatibility/PHPCompatibility/issues/734).
- :green_heart: Travis: various tweaks. The builds against PHP 7.4 are no longer allowed to fail. [#935](https://github.com/PHPCompatibility/PHPCompatibility/pull/935), [#938](https://github.com/PHPCompatibility/PHPCompatibility/pull/938)
    For running the sniffs on PHP 7.4, it is recommended to use PHP_CodeSniffer 3.5.0+ as PHP_CodeSniffer itself is
    not compatible with PHP 7.4 until version 3.5.0.

### Fixed
- :bug: `PHPCompatibility.Classes.NewClasses`: two new PHP 7.4 classes were being checked as if they were Exceptions. [#945](https://github.com/PHPCompatibility/PHPCompatibility/pull/945)

### Credits
Thanks go out to [William Entriken] for their contribution to this version. :clap:


## [9.3.4] - 2019-11-15

See all related issues and PRs in the [9.3.4 milestone].

### Fixed
- :bug: `PHPCompatibility.Keywords.ForbiddenNames`: false positive for list when used in a `foreach()` condition. [#930](https://github.com/PHPCompatibility/PHPCompatibility/pull/930). Fixes [#928](https://github.com/PHPCompatibility/PHPCompatibility/issues/928), [#929](https://github.com/PHPCompatibility/PHPCompatibility/pull/929)

### Credits
Thanks go out to [Sergii Bondarenko] for their contribution to this version. :clap:


## [9.3.3] - 2019-11-11

See all related issues and PRs in the [9.3.3 milestone].

### Added
- :star: `PHPCompatibility.Constants.NewConstants` sniff: detection of yet more (undocumented) PHP 7.2 Sodium constants. [#924](https://github.com/PHPCompatibility/PHPCompatibility/pull/924)
- :star: `PHPCompatibility.Keywords.ForbiddenNames` sniff: detect the use of more reserved keywords which are not allowed to be used to name certain constructs. [#923](https://github.com/PHPCompatibility/PHPCompatibility/pull/923). Fixes [#922](https://github.com/PHPCompatibility/PHPCompatibility/issues/922)

### Fixed
- :bug: `PHPCompatibility.FunctionNameRestrictions.RemovedPHP4StyleConstructors`: false positive detecting PHP4-style constructors when declared in interfaces. The class implementing the interface will not have the same name as the interface, so the actual method would not be regarded as a PHP4 style constructor. [#921](https://github.com/PHPCompatibility/PHPCompatibility/pull/921)

### Credits
Thanks go out to [Nikhil] for their contribution to this version. :clap:


## [9.3.2] - 2019-10-16

See all related issues and PRs in the [9.3.2 milestone].

### Added
- :star: `PHPCompatibility.Constants.NewConstants` sniff: detection of the PHP 7.2 `SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13` constant. [#915](https://github.com/PHPCompatibility/PHPCompatibility/pull/915)
- :books: Readme: a list of projects which are build upon or extend PHPCompatibility. [#904](https://github.com/PHPCompatibility/PHPCompatibility/pull/904)

### Changed
- :pushpin: `PHPCompatibility.FunctionNameRestrictions.RemovedPHP4StyleConstructors`: minor efficiency fix to make the sniff faster. [#912](https://github.com/PHPCompatibility/PHPCompatibility/pull/912)
- :pushpin: `PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames`: functions marked as `@deprecated` in the function docblock will now be ignored by this sniff. [#917](https://github.com/PHPCompatibility/PHPCompatibility/pull/917). Fixes [#911](https://github.com/PHPCompatibility/PHPCompatibility/issues/911)
- :pencil: `PHPCompatibility.FunctionDeclarations.ForbiddenToStringParameters`: the `$ooScopeTokens` property is now `protected`, it should never have been `public` in the first place. [#907](https://github.com/PHPCompatibility/PHPCompatibility/pull/907)
- :recycle: More code documentation fixes. [#903](https://github.com/PHPCompatibility/PHPCompatibility/pull/903), [#916](https://github.com/PHPCompatibility/PHPCompatibility/pull/916)
- :books: Readme/Contributing: various tweaks. [#904](https://github.com/PHPCompatibility/PHPCompatibility/pull/904), [#905](https://github.com/PHPCompatibility/PHPCompatibility/pull/905)

### Fixed
- :bug: `PHPCompatibility.FunctionUse.OptionalToRequiredFunctionParameters`: false positive when a class is instantiated which has the same name as one of the affected functions. [#914](https://github.com/PHPCompatibility/PHPCompatibility/pull/914). Fixes [#913](https://github.com/PHPCompatibility/PHPCompatibility/issues/913)
- :bug: `PHPCompatibility.FunctionUse.RequiredToOptionalFunctionParameters`: false positive when a class is instantiated which has the same name as one of the affected functions. [#914](https://github.com/PHPCompatibility/PHPCompatibility/pull/914)
- :bug: `PHPCompatibility.MethodUse.NewDirectCallsToClone`: false positive on calling `__clone()` from within the class being cloned [#910](https://github.com/PHPCompatibility/PHPCompatibility/pull/910). Fixes [#629 (comment)](https://github.com/PHPCompatibility/PHPCompatibility/issues/629#issuecomment-532607809)
- :bug: `PHPCompatibility.Miscellaneous.ValidIntegers`: binary numbers using an uppercase `B` were not always recognized correctly. [#909](https://github.com/PHPCompatibility/PHPCompatibility/pull/909)


## [9.3.1] - 2019-09-06

See all related issues and PRs in the [9.3.1 milestone].

### Changed
- :recycle: A whole slew of code documentation fixes. [#892](https://github.com/PHPCompatibility/PHPCompatibility/pull/892), [#895](https://github.com/PHPCompatibility/PHPCompatibility/pull/895), [#896](https://github.com/PHPCompatibility/PHPCompatibility/pull/896), [#897](https://github.com/PHPCompatibility/PHPCompatibility/pull/897), [#898](https://github.com/PHPCompatibility/PHPCompatibility/pull/898), [#899](https://github.com/PHPCompatibility/PHPCompatibility/pull/899), [#900](https://github.com/PHPCompatibility/PHPCompatibility/pull/900)
- :wrench: Travis: minor tweaks to the build script. [#893](https://github.com/PHPCompatibility/PHPCompatibility/pull/893)

### Fixed
- :bug: `PHPCompatibility.ParameterValues.RemovedImplodeFlexibleParamOrder`: false positive when an array item in the second parameter contained a ternary. [#891](https://github.com/PHPCompatibility/PHPCompatibility/pull/891). Fixes [#890](https://github.com/PHPCompatibility/PHPCompatibility/issues/890)
- :bug: `PHPCompatibility.ParameterValues.RemovedImplodeFlexibleParamOrder`: will now take array casts into account when determining which parameter is `$pieces`. [#891](https://github.com/PHPCompatibility/PHPCompatibility/pull/891).
- :bug: `PHPCompatibility.ParameterValues.RemovedImplodeFlexibleParamOrder`: hardening of the logic to not examine the second parameter when the first is just and only a text string (`$glue`). [#891](https://github.com/PHPCompatibility/PHPCompatibility/pull/891).


## [9.3.0] - 2019-08-29

See all related issues and PRs in the [9.3.0 milestone].

To keep informed of the progress of covering "_everything PHP 7.4_" in PHPCompatibility, please subscribe to issue [#808](https://github.com/PHPCompatibility/PHPCompatibility/issues/808).

### Changes expected in PHPCompatibility 10.0.0
The next version of PHPCompatibility is expected to include a new external dependency.

In this same release, support for PHP < 5.4 and PHP_CodeSniffer < 2.6.0 will be dropped.

The `10.0.0` release is expected around the same time as the release of PHP 7.4 - end of November/beginning of December 2019.

### Added
- :star2: New `PHPCompatibility.Miscellaneous.NewPHPOpenTagEOF` sniff to detect a stand-alone PHP open tag at the end of a file, without trailing newline, as will be supported as of PHP 7.4. [#843](https://github.com/PHPCompatibility/PHPCompatibility/pull/846)
- :star2: New `PHPCompatibility.ParameterValues.ForbiddenStripTagsSelfClosingXHTML` sniff to detect calls to `strip_tags()` passing self-closing XHTML tags in the `$allowable_tags` parameter. This has not been supported since PHP 5.3.4. [#866](https://github.com/PHPCompatibility/PHPCompatibility/pull/866)
- :star2: New `PHPCompatibility.ParameterValues.NewHTMLEntitiesEncodingDefault` sniff to detect calls to `html_entity_decode()`, `htmlentities()` and `htmlspecialchars()` which are impacted by the change to the default value of the `$encoding` parameter in PHP 5.4. [#862](https://github.com/PHPCompatibility/PHPCompatibility/pull/862)
- :star2: New `PHPCompatibility.ParameterValues.NewIconvMbstringCharsetDefault` sniff to detect code impacted by the change in the `default_charset` value in PHP 5.6. [#864](https://github.com/PHPCompatibility/PHPCompatibility/pull/864) Fixes [#839](https://github.com/PHPCompatibility/PHPCompatibility/issues/839)
- :star2: New `PHPCompatibility.ParameterValues.NewIDNVariantDefault` sniff to detect calls to `idn_to_ascii()` and `idn_to_utf8()` impacted by the PHP 7.4 change in the default value for the `$variant` parameter. [#861](https://github.com/PHPCompatibility/PHPCompatibility/pull/861)
- :star2: New `PHPCompatibility.ParameterValues.NewPasswordAlgoConstantValues` sniff to detect calls to `password_hash()` and `password_needs_rehash()` impacted by the changed value of the `PASSWORD_DEFAULT`, `PASSWORD_BCRYPT`, `PASSWORD_ARGON2I` and `PASSWORD_ARGON2ID` constants in PHP 7.4. [#865](https://github.com/PHPCompatibility/PHPCompatibility/pull/865)
- :star2: New `PHPCompatibility.ParameterValues.NewProcOpenCmdArray` sniff to detect calls to `proc_open()` passing an array for the `$cmd` parameter as supported as of PHP 7.4. [#869](https://github.com/PHPCompatibility/PHPCompatibility/pull/869)
- :star2: New `PHPCompatibility.ParameterValues.NewStripTagsAllowableTagsArray` sniff to detect calls to `strip_tags()` passing an array for the `$allowable_tags` parameter as will be supported as of PHP 7.4. [#867](https://github.com/PHPCompatibility/PHPCompatibility/pull/867)
- :star2: New `PHPCompatibility.ParameterValues.RemovedImplodeFlexibleParamOrder` sniff to detect `implode()` being called with `$glue` and `$pieces` in reverse order from the documented argument order. This was previously allowed for historical reasons, but will be deprecated in PHP 7.4. [#846](https://github.com/PHPCompatibility/PHPCompatibility/pull/846)
- :star2: New `PHPCompatibility.ParameterValues.RemovedMbStrrposEncodingThirdParam` sniff to detect the `$encoding` being passed as the third, instead of the fourth parameter, to `mb_strrpos()` as has been soft deprecated since PHP 5.2 and will be hard deprecated as of PHP 7.4. [#860](https://github.com/PHPCompatibility/PHPCompatibility/pull/860)
- :star2: New `PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess` sniff to detect array and string offset access using curly braces as will be deprecated as of PHP 7.4. [#855](https://github.com/PHPCompatibility/PHPCompatibility/pull/855)
    - In contrast to any other sniff in the PHPCompatibility standard, this sniff contains an auto-fixer.
- :star2: New `PHPCompatibility.TextStrings.NewUnicodeEscapeSequence` sniff to detect use of the PHP 7.0+ unicode codepoint escape sequences and issues with invalid sequences. [#856](https://github.com/PHPCompatibility/PHPCompatibility/pull/856)
- :star2: New `PHPCompatibility.Upgrade.LowPHP` sniff to give users of old PHP versions advance warning when support will be dropped in the near future. [#838](https://github.com/PHPCompatibility/PHPCompatibility/pull/838)
    At this moment, the intention is to drop support for PHP 5.3 by the end of this year.
- :star: `PHPCompatibility.Classes.NewClasses` sniff: recognize the new `WeakReference` class as introduced in PHP 7.4. [#857](https://github.com/PHPCompatibility/PHPCompatibility/pull/857)
- :star: `PHPCompatibility.Constants.NewConstants` sniff: detection of new Curl constants as introduced in PHP 7.3.5. [#878](https://github.com/PHPCompatibility/PHPCompatibility/pull/878)
- :star: `PHPCompatibility.Constants.NewConstants` sniff: detection of the revived `T_BAD_CHARACTER` constant as re-introduced in PHP 7.4. [#882](https://github.com/PHPCompatibility/PHPCompatibility/pull/882)
- :star: `PHPCompatibility.Constants.NewConstants` sniff: detection of the new `IMG_FILTER_SCATTER` and `PASSWORD_ARGON2_PROVIDER` constants as introduced in PHP 7.4. [#887](https://github.com/PHPCompatibility/PHPCompatibility/pull/887)
- :star: `PHPCompatibility.Constants.RemovedConstants` sniff: detection of use of the `CURLPIPE_HTTP1` constant which will be deprecated in PHP 7.4. [#879](https://github.com/PHPCompatibility/PHPCompatibility/pull/879)
- :star: `PHPCompatibility.Constants.RemovedConstants` sniff: detection of use of the `FILTER_SANITIZE_MAGIC_QUOTES` constant which will be deprecated in PHP 7.4. [#845](https://github.com/PHPCompatibility/PHPCompatibility/pull/845)
- :star: `PHPCompatibility.Constants.RemovedConstants` sniff: detection of use of the `T_CHARACTER` and `T_BAD_CHARACTER` constants which were removed in PHP 7.0. [#882](https://github.com/PHPCompatibility/PHPCompatibility/pull/882)
- :star: `PHPCompatibility.FunctionDeclarations.NewMagicMethods` sniff: recognize the new `__serialize()` and `__unserialize()` magic methods as introduced in PHP 7.4. [#868](https://github.com/PHPCompatibility/PHPCompatibility/pull/868)
- :star: `PHPCompatibility.FunctionDeclarations.NewMagicMethods` sniff: recognize the PHP 5.0 `__construct()` and `__destruct()` magic methods. [#884](https://github.com/PHPCompatibility/PHPCompatibility/pull/884)
- :star: `PHPCompatibility.FunctionDeclarations.NonStaticMagicMethods` sniff: recognize the new `__serialize()` and `__unserialize()` magic methods as introduced in PHP 7.4. [#868](https://github.com/PHPCompatibility/PHPCompatibility/pull/868)
- :star: `PHPCompatibility.FunctionUse.NewFunctions` sniff: recognize the new PHP 7.4 function `imagecreatefromtga()`. [#873](https://github.com/PHPCompatibility/PHPCompatibility/pull/873)
- :star: `PHPCompatibility.FunctionUse.RemovedFunctionParameters` sniff: recognize the deprecation of the `$age` parameter of the `curl_version()` function. [#874](https://github.com/PHPCompatibility/PHPCompatibility/pull/874)
- :star: `PHPCompatibility.FunctionUse.RemovedFunctions` sniff: recognize the PHP 7.4 deprecated `convert_cyr_string()()`, `ezmlm_hash()`, `get_magic_quotes_gpc()`, `get_magic_quotes_runtime()`, `hebrevc()`, `is_real()`, `money_format()` and `restore_include_path()` functions. [#847](https://github.com/PHPCompatibility/PHPCompatibility/pull/847)
- :star: `PHPCompatibility.IniDirectives.NewIniDirectives` sniff: detect use of the new PHP 7.4 `zend.exception_ignore_args` ini directive. [#871](https://github.com/PHPCompatibility/PHPCompatibility/pull/871)
- :star: `PHPCompatibility.IniDirectives.RemovedIniDirectives` sniff: detect use of the `allow_url_include` ini directive which is deprecated as of PHP 7.4. [#870](https://github.com/PHPCompatibility/PHPCompatibility/pull/870)
- :star: `PHPCompatibility.IniDirectives.RemovedIniDirectives` sniff: detection of use of the `opcache.load_comments` directive which was removed in PHP 7.0. [#883](https://github.com/PHPCompatibility/PHPCompatibility/pull/883)
- :star: `PHPCompatibility.ParameterValues.NewHashAlgorithms`: recognize use of the new PHP 7.4 `crc32c` hash algorithm. [#872](https://github.com/PHPCompatibility/PHPCompatibility/pull/872)
- :star: `PHPCompatibility.TypeCasts.RemovedTypeCasts` sniff: detect usage of the `(real)` type cast which will be deprecated in PHP 7.4. [#844](https://github.com/PHPCompatibility/PHPCompatibility/pull/844)
- :star: Recognize the `recode` extension functionality which will be removed in PHP 7.4 (moved to PECL) in the `RemovedExtensions` and `RemovedFunctions` sniffs. [#841](https://github.com/PHPCompatibility/PHPCompatibility/pull/841)
- :star: Recognize the `OPcache` extension functionality which was be introduced in PHP 5.5, but not yet fully accounted for in the `NewFunctions` and `NewIniDirectives` sniffs.  [#883](https://github.com/PHPCompatibility/PHPCompatibility/pull/883)
- :star: New `getCompleteTextString()` utility method to the `PHPCompatibility\Sniff` class. [#856](https://github.com/PHPCompatibility/PHPCompatibility/pull/856)
- :umbrella: Unit test for the `PHPCompatibility.Upgrade.LowPHPCS` sniff.
- :umbrella: Some extra unit tests for the `PHPCompatibility.ParameterValues.NewNegativeStringOffset`, `PHPCompatibility.ParameterValues.RemovedMbStringModifiers` and sniffs. [#876](https://github.com/PHPCompatibility/PHPCompatibility/pull/876), [#877](https://github.com/PHPCompatibility/PHPCompatibility/pull/877)
- :books: `CONTRIBUTING.md`: Added a list of typical sources for information about changes to PHP. [#875](https://github.com/PHPCompatibility/PHPCompatibility/pull/875)

### Changed
- :pushpin: `PHPCompatibility.FunctionDeclarations.NewExceptionsFromToString` sniff: the sniff will now also examine the function docblock, if available, and will throw an error when a `@throws` tag is found in the docblock. [#880](https://github.com/PHPCompatibility/PHPCompatibility/pull/880). Fixes [#863](https://github.com/PHPCompatibility/PHPCompatibility/issues/863)
- :pushpin: `PHPCompatibility.FunctionDeclarations.NonStaticMagicMethods` sniff: will now also check the visibility and `static` (or not) requirements of the magic `__construct()`, `__destruct()`, `__clone()`, `__debugInfo()`, `__invoke()` and `__set_state()` methods. [#885](https://github.com/PHPCompatibility/PHPCompatibility/pull/885)
- :pushpin: `PHPCompatibility.Syntax.NewArrayStringDereferencing` sniff: the sniff will now also recognize array string dereferencing using curly braces as was (silently) supported since PHP 7.0. [#851](https://github.com/PHPCompatibility/PHPCompatibility/pull/851)
    - The sniff will now also throw errors for each dereference found on the array/string, not just the first one.
- :pushpin: `PHPCompatibility.Syntax.NewClassMemberAccess` sniff: the sniff will now also recognize class member access on instantiation and cloning using curly braces as was (silently) supported since PHP 7.0. [#852](https://github.com/PHPCompatibility/PHPCompatibility/pull/852)
    - The sniff will now also throw errors for each access detected, not just the first one.
    - The line number on which the error is thrown in now set more precisely.
- :pushpin: `PHPCompatibility.Syntax.NewFunctionArrayDereferencing` sniff: the sniff will now also recognize function array dereferencing using curly braces as was (silently) supported since PHP 7.0. [#853](https://github.com/PHPCompatibility/PHPCompatibility/pull/853)
    - The sniff will now also throw errors for each access detected, not just the first one.
    - The line number on which the error is thrown in now set more precisely.
- :recycle: Various code clean-up and improvements. [#849](https://github.com/PHPCompatibility/PHPCompatibility/pull/849), [#850](https://github.com/PHPCompatibility/PHPCompatibility/pull/850)
- :recycle: Various minor inline documentation fixes. [#854](https://github.com/PHPCompatibility/PHPCompatibility/pull/854), [#886](https://github.com/PHPCompatibility/PHPCompatibility/pull/886)
- :wrench: Travis: various tweaks to the build script. [#834](https://github.com/PHPCompatibility/PHPCompatibility/pull/834), [#842](https://github.com/PHPCompatibility/PHPCompatibility/pull/842)

### Fixed
- :bug: `PHPCompatibility.FunctionDeclarations.ForbiddenParametersWithSameName` sniff: variable names are case-sensitive, so recognition of same named parameters should be done in a case-sensitive manner. [#848](https://github.com/PHPCompatibility/PHPCompatibility/pull/848)
- :bug: `PHPCompatibility.FunctionDeclarations.NewExceptionsFromToString` sniff: Exceptions thrown within a `try` should not trigger the sniff. [#880](https://github.com/PHPCompatibility/PHPCompatibility/pull/880). Fixes [#863](https://github.com/PHPCompatibility/PHPCompatibility/issues/863)
- :bug: `PHPCompatibility.FunctionDeclarations.NewExceptionsFromToString` sniff: the `$ooScopeTokens` property should never have been a public property. [#880](https://github.com/PHPCompatibility/PHPCompatibility/pull/880).
- :umbrella: Some of the unit tests for the `PHPCompatibility.Operators.RemovedTernaryAssociativity` sniff were not being run. [#836](https://github.com/PHPCompatibility/PHPCompatibility/pull/836)


## [9.2.0] - 2019-06-28

See all related issues and PRs in the [9.2.0 milestone].

To keep informed of the progress of covering "_everything PHP 7.4_" in PHPCompatibility, please subscribe to issue [#808](https://github.com/PHPCompatibility/PHPCompatibility/issues/808).

### Added
- :star2: New `PHPCompatibility.Classes.ForbiddenAbstractPrivateMethods` sniff to detect methods declared as both `private` as well as `abstract`. This was allowed between PHP 5.0.0 and 5.0.4, but disallowed in PHP 5.1 as the behaviour of `private` and `abstract` are mutually exclusive. [#822](https://github.com/PHPCompatibility/PHPCompatibility/pull/822)
- :star2: New `PHPCompatibility.Classes.NewTypedProperties` sniff to detect PHP 7.4 typed property declarations. [#801](https://github.com/PHPCompatibility/PHPCompatibility/pull/801), [#829](https://github.com/PHPCompatibility/PHPCompatibility/pull/829)
- :star2: New `PHPCompatibility.Classes.RemovedOrphanedParent` sniff to detect the use of the `parent` keyword in classes without a parent (non-extended classes). This code pattern is deprecated in PHP 7.4 and will become a compile-error in PHP 8.0. [#818](https://github.com/PHPCompatibility/PHPCompatibility/pull/818)
- :star2: New `PHPCompatibility.FunctionDeclarations.NewExceptionsFromToString` sniff to detect throwing exceptions from `__toString()` methods. This would previously result in a fatal error, but will be allowed as of PHP 7.4. [#814](https://github.com/PHPCompatibility/PHPCompatibility/pull/814)
- :star2: New `PHPCompatibility.FunctionDeclarations.ForbiddenToStringParameters` sniff to detect `__toString()` function declarations expecting parameters. This was disallowed in PHP 5.3. [#815](https://github.com/PHPCompatibility/PHPCompatibility/pull/815)
- :star2: New `PHPCompatibility.MethodUse.ForbiddenToStringParameters` sniff to detect direct calls to `__toString()` magic methods passing parameters. This was disallowed in PHP 5.3. [#830](https://github.com/PHPCompatibility/PHPCompatibility/pull/830)
- :star2: New `PHPCompatibility.Operators.ChangedConcatOperatorPrecedence` sniff to detect code affected by the upcoming change in operator precedence for the concatenation operator. The concatenation operator precedence will be lowered in PHP 8.0, with deprecation notices for code affected being thrown in PHP 7.4. [#805](https://github.com/PHPCompatibility/PHPCompatibility/pull/805)
- :star2: New `PHPCompatibility.Operators.RemovedTernaryAssociativity` sniff to detect code relying on left-associativity of the ternary operator. This behaviour will be deprecated in PHP 7.4 and removed in PHP 8.0. [#810](https://github.com/PHPCompatibility/PHPCompatibility/pull/810)
- :star2: New `PHPCompatibility.Syntax.NewArrayUnpacking` sniff to detect the use of the spread operator to unpack arrays when declaring a new array, as introduced in PHP 7.4. [#804](https://github.com/PHPCompatibility/PHPCompatibility/pull/804)
- :star: `PHPCompatibility.Classes.NewClasses` sniff: recognize the new `ReflectionReference` class as introduced in PHP 7.4. [#820](https://github.com/PHPCompatibility/PHPCompatibility/pull/820)
- :star: `PHPCompatibility.Constants.NewConstants` sniff: detection of the new PHP 7.4 Core (Standard), MBString, Socket and Tidy constants. [#821](https://github.com/PHPCompatibility/PHPCompatibility/pull/821)
- :star: `PHPCompatibility.FunctionUse.NewFunctions` sniff: detect usage of the new PHP 7.4 `get_mangled_object_vars()`, `mb_str_split()`, `openssl_x509_verify()`, `password_algos()`, `pcntl_unshare()`, `sapi_windows_set_ctrl_handler()` and `sapi_windows_generate_ctrl_event()` functions. [#811](https://github.com/PHPCompatibility/PHPCompatibility/pull/811), [#819](https://github.com/PHPCompatibility/PHPCompatibility/pull/819), [#827](https://github.com/PHPCompatibility/PHPCompatibility/pull/827)
- :star: `PHPCompatibility.FunctionUse.NewFunctions` sniff: recognize the new OCI functions as introduced in PHP 7.2.14 and PHP 7.3.1. [#786](https://github.com/PHPCompatibility/PHPCompatibility/pull/786)
- :star: `PHPCompatibility.FunctionUse.RemovedFunctions` sniff: recognize the PHP 7.4 deprecated `ldap_control_paged_result_response()` and `ldap_control_paged_result()` functions. [#831](https://github.com/PHPCompatibility/PHPCompatibility/pull/831)
- :star: `PHPCompatibility.FunctionUse.RemovedFunctions` sniff: recognize the `Payflow Pro/pfpro` functions as removed in PHP 5.1. [#823](https://github.com/PHPCompatibility/PHPCompatibility/pull/823)
- :star: `PHPCompatibility.FunctionUse.RequiredToOptionalFunctionParameters` sniff: account for the parameters for `array_merge()` and `array_merge_recursive()` becoming optional in PHP 7.4. [#817](https://github.com/PHPCompatibility/PHPCompatibility/pull/817)
- :star: `PHPCompatibility.IniDirectives.RemovedIniDirectives` sniff: recognize the `Payflow Pro/pfpro` ini directives as removed in PHP 5.1. [#823](https://github.com/PHPCompatibility/PHPCompatibility/pull/823)
- :star: Recognize the `interbase/Firebird` extension functionality which will be removed in PHP 7.4 (moved to PECL) in the `RemovedConstants`, `RemovedExtensions`, `RemovedFunctions` and `RemovedIniDirectives` sniffs. [#807](https://github.com/PHPCompatibility/PHPCompatibility/pull/807)
- :star: Recognize the `wddx` extension functionality which will be removed in PHP 7.4 (moved to PECL) in the `RemovedExtensions` and `RemovedFunctions` sniffs. [#826](https://github.com/PHPCompatibility/PHPCompatibility/pull/826)
- :star: New `isShortTernary()` and `isUnaryPlusMinus()` utility methods to the `PHPCompatibility\Sniff` class. [#810](https://github.com/PHPCompatibility/PHPCompatibility/pull/810), [#805](https://github.com/PHPCompatibility/PHPCompatibility/pull/805)

### Changed
- :pencil2: The `PHPCompatibility.Extensions.RemovedExtensions` sniff will now only report on the removed `Payflow Pro` extension when a function uses `pfpro_` as a prefix. Previously, it used the `pfpro` prefix (without underscore) for detection. [#812](https://github.com/PHPCompatibility/PHPCompatibility/pull/812)
- :pencil2: The error message thrown when the `T_ELLIPSIS` token, i.e. the spread operator, is detected. [#803](https://github.com/PHPCompatibility/PHPCompatibility/pull/803)
    PHP 7.4 adds a third use-case for the spread operator. The adjusted error message accounts for this.
- :umbrella: `PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations` is now also tested with parameters using the splat operator. [#802](https://github.com/PHPCompatibility/PHPCompatibility/pull/802)
- :books: The documentation now uses the GitHub repo of `PHP_CodeSniffer` as the canonical entry point for `PHP_CodeSniffer`. Previously, it would point to the PEAR package. [#788](https://github.com/PHPCompatibility/PHPCompatibility/pull/788)
- :books: The links in the changelog now all point to the `PHPCompatibility/PHPCompatibility` repo and no longer to the (deprecated) `wimg/PHPCompatibility` repo. [#828](https://github.com/PHPCompatibility/PHPCompatibility/pull/828)
- :recycle: Various minor inline documentation improvements. [#825](https://github.com/PHPCompatibility/PHPCompatibility/pull/825)
- :wrench: Various performance optimizations and code simplifications. [#783](https://github.com/PHPCompatibility/PHPCompatibility/pull/783), [#784](https://github.com/PHPCompatibility/PHPCompatibility/pull/784), [#795](https://github.com/PHPCompatibility/PHPCompatibility/pull/795), [#813](https://github.com/PHPCompatibility/PHPCompatibility/pull/813)
- :green_heart: Travis: build tests are now being run against PHP 7.4 (unstable) as well. [#790](https://github.com/PHPCompatibility/PHPCompatibility/pull/790)
    Note: the builds are currently not (yet) tested against PHP 8.0 (unstable) as there is no compatible PHPUnit version available (yet).
- :wrench: Travis: The build script has been refactored to use [stages](https://docs.travis-ci.com/user/build-stages/) to get the most relevant results faster. Additionally some more tweaks have been made to improve and/or simplify the build script. [#798](https://github.com/PHPCompatibility/PHPCompatibility/pull/798)
- :wrench: Build/PHPCS: warnings are no longer allowed for the PHPCompatibility native code. [#800](https://github.com/PHPCompatibility/PHPCompatibility/pull/800)
- :wrench: Build/PHPCS: added variable assignment alignment check and file include check to the PHPCompatibility native CS configuration. [#824](https://github.com/PHPCompatibility/PHPCompatibility/pull/824)
- :wrench: The minimum version for the recommended `DealerDirect/phpcodesniffer-composer-installer` Composer plugin has been upped to `0.5.0`. [#791](https://github.com/PHPCompatibility/PHPCompatibility/pull/791)

### Fixed
- :bug: The `PHPCompatibility.Extensions.RemovedExtensions` sniff contained a typo in the alternative recommended for the removed `mcve` extension. [#806](https://github.com/PHPCompatibility/PHPCompatibility/pull/806)
- :bug: The `PHPCompatibility.Extensions.RemovedExtensions` sniff listed the wrong removal version number for the `Payflow Pro/pfpro` extension (PHP 5.3 instead of the correct 5.1). [#823](https://github.com/PHPCompatibility/PHPCompatibility/pull/823)

### Credits
Thanks go out to [Yılmaz] and [Tim Millwood] for their contribution to this version. :clap:


## [9.1.1] - 2018-12-31

See all related issues and PRs in the [9.1.1 milestone].

### Fixed
- :bug: `ForbiddenThisUseContexts`: false positive for unsetting `$this['key']` on objects implementing `ArrayAccess`. [#781](https://github.com/PHPCompatibility/PHPCompatibility/pull/781). Fixes [#780](https://github.com/PHPCompatibility/PHPCompatibility/issues/780)

## [9.1.0] - 2018-12-16

See all related issues and PRs in the [9.1.0 milestone].

### Added
- :star2: New `PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue` sniff to detect code which could be affected by the PHP 7.0 change in the values reported by `func_get_arg()`, `func_get_args()`, `debug_backtrace()` and exception backtraces. [#750](https://github.com/PHPCompatibility/PHPCompatibility/pull/750). Fixes [#585](https://github.com/PHPCompatibility/PHPCompatibility/pull/585).
- :star2: New `PHPCompatibility.MethodUse.NewDirectCallsToClone` sniff to detect direct call to a `__clone()` magic method which wasn't allowed prior to PHP 7.0. [#743](https://github.com/PHPCompatibility/PHPCompatibility/pull/743). Fixes [#629](https://github.com/PHPCompatibility/PHPCompatibility/issues/629).
- :star2: New `PHPCompatibility.Variables.ForbiddenThisUseContext` sniff to detect most of the inconsistencies surrounding the use of the `$this` variable, which were removed in PHP 7.1. [#762](https://github.com/PHPCompatibility/PHPCompatibility/pull/762), [#771](https://github.com/PHPCompatibility/PHPCompatibility/pull/771). Fixes [#262](https://github.com/PHPCompatibility/PHPCompatibility/issues/262) and [#740](https://github.com/PHPCompatibility/PHPCompatibility/issues/740).
- :star: `NewClasses`: detection of more native PHP Exceptions. [#743](https://github.com/PHPCompatibility/PHPCompatibility/pull/743), [#753](https://github.com/PHPCompatibility/PHPCompatibility/pull/753)
- :star: `NewConstants` : detection of the new PHP 7.3 Curl, Stream Crypto and LDAP constants and some more PHP 7.0 Tokenizer constants. [#752](https://github.com/PHPCompatibility/PHPCompatibility/pull/752), [#767](https://github.com/PHPCompatibility/PHPCompatibility/pull/767), [#778](https://github.com/PHPCompatibility/PHPCompatibility/pull/778)
- :star: `NewFunctions` sniff: recognize (more) new LDAP functions as introduced in PHP 7.3. [#768](https://github.com/PHPCompatibility/PHPCompatibility/pull/768)
- :star: `NewFunctionParameters` sniff: recognize the new `$serverctrls` parameter which was added to a number of LDAP functions in PHP 7.3. [#769](https://github.com/PHPCompatibility/PHPCompatibility/pull/769)
- :star: `NewIniDirectives` sniff: recognize the new `imap.enable_insecure_rsh` ini directive as introduced in PHP 7.1.25, 7.2.13 and 7.3.0. [#770](https://github.com/PHPCompatibility/PHPCompatibility/pull/770)
- :star: `NewInterfaces` sniff: recognize two more Session related interfaces which were introduced in PHP 5.5.1 and 7.0 respectively. [#748](https://github.com/PHPCompatibility/PHPCompatibility/pull/748)
- :star: Duplicate of upstream `findStartOfStatement()` method to the `PHPCompatibility\PHPCSHelper` class to allow for PHPCS cross-version usage of that method. [#750](https://github.com/PHPCompatibility/PHPCompatibility/pull/750)

### Changed
- :pushpin: `RemovedPHP4StyleConstructors`: will now also detect PHP4-style constructors when declared in interfaces. [#751](https://github.com/PHPCompatibility/PHPCompatibility/pull/751)
- :pushpin: `Sniff::validDirectScope()`: the return value of this method has changed. Previously it would always be a boolean. It will stil return `false` when no valid direct scope has been found, but it will now return the `stackPtr` to the scope token if a _valid_ direct scope was encountered. [#758](https://github.com/PHPCompatibility/PHPCompatibility/pull/758)
- :rewind: `NewOperators` : updated the version number for `T_COALESCE_EQUAL`. [#746](https://github.com/PHPCompatibility/PHPCompatibility/pull/746)
- :pencil: Minor improvement to an error message in the unit test suite. [#742](https://github.com/PHPCompatibility/PHPCompatibility/pull/742)
- :recycle: Various code clean-up and improvements. [#745](https://github.com/PHPCompatibility/PHPCompatibility/pull/745), [#756](https://github.com/PHPCompatibility/PHPCompatibility/pull/756), [#774](https://github.com/PHPCompatibility/PHPCompatibility/pull/774)
- :recycle: Various minor inline documentation fixes. [#749](https://github.com/PHPCompatibility/PHPCompatibility/pull/749), [#757](https://github.com/PHPCompatibility/PHPCompatibility/pull/757)
- :umbrella: Improved code coverage recording. [#744](https://github.com/PHPCompatibility/PHPCompatibility/pull/744), [#776](https://github.com/PHPCompatibility/PHPCompatibility/pull/776)
- :green_heart: Travis: build tests are now being run against PHP 7.3 as well. [#511](https://github.com/PHPCompatibility/PHPCompatibility/pull/511)
    Note: full PHP 7.3 support is only available in combination with PHP_CodeSniffer 2.9.2 or 3.3.1+ due to an incompatibility within PHP_CodeSniffer itself.

### Fixed
- :white_check_mark: Compatibility with the upcoming release of PHPCS 3.4.0. Deal with changed behaviour of the PHPCS `Tokenizer` regarding binary type casts. [#760](https://github.com/PHPCompatibility/PHPCompatibility/pull/760)
- :bug: `InternalInterfaces`: false negative for implemented/extended interfaces prefixed with a namespace separator. [#775](https://github.com/PHPCompatibility/PHPCompatibility/pull/775)
- :bug: `NewClasses`: the introduction version of various native PHP Exceptions has been corrected. [#743](https://github.com/PHPCompatibility/PHPCompatibility/pull/743), [#753](https://github.com/PHPCompatibility/PHPCompatibility/pull/753)
- :bug: `NewInterfaces`: false negative for implemented/extended interfaces prefixed with a namespace separator. [#775](https://github.com/PHPCompatibility/PHPCompatibility/pull/775)
- :bug: `RemovedPHP4StyleConstructors`: the sniff would examine methods in nested anonymous classes as if they were methods of the higher level class. [#751](https://github.com/PHPCompatibility/PHPCompatibility/pull/751)
- :rewind: `RemovedPHP4StyleConstructors`: the sniff will no longer throw false positives for the first method in an anonymous class when used in combination with PHPCS 2.3.x. [#751](https://github.com/PHPCompatibility/PHPCompatibility/pull/751)
- :rewind: `ReservedFunctionNames`: fixed incorrect error message text for methods in anonymous classes when used in combination with PHPCS 2.3.x. [#755](https://github.com/PHPCompatibility/PHPCompatibility/pull/755)
- :bug: `ReservedFunctionNames`: prevent duplicate errors being thrown for methods in nested anonymous classes. [#755](https://github.com/PHPCompatibility/PHPCompatibility/pull/755)
- :bug: `PHPCSHelper::findEndOfStatement()`: minor bug fix. [#749](https://github.com/PHPCompatibility/PHPCompatibility/pull/749)
- :bug: `Sniff::isClassProperty()`: class properties for classes nested in conditions or function calls were not always recognized as class properties. [#758](https://github.com/PHPCompatibility/PHPCompatibility/pull/758)

### Credits
Thanks go out to [Jonathan Champ] for his contribution to this version. :clap:


## [9.0.0] - 2018-10-07

**IMPORTANT**: This release contains **breaking changes**. Please read the below information carefully before upgrading!

All sniffs have been placed in meaningful categories and a number of sniffs have been renamed to have more consistent, meaningful and future-proof names.

Both the `PHPCompatibilityJoomla` [[GH](https://github.com/PHPCompatibility/PHPCompatibilityJoomla) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-joomla)] as well as the `PHPCompatibilityWP` [[GH](https://github.com/PHPCompatibility/PHPCompatibilityWP)|[Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-wp)] rulesets have already been adjusted for this change and have released a new version which is compatible with this version of PHPCompatibility.

Aside from those CMS-based rulesets, this project now also offers a number of polyfill-library specific rulesets, such as `PHPCompatibilityPasswordCompat` [[GH](https://github.com/PHPCompatibility/PHPCompatibilityPasswordCompat) | [Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-passwordcompat)] for @ircmaxell's [`password_compat`](https://github.com/ircmaxell/password_compat) libary, `PHPCompatibilityParagonieRandomCompat` and `PHPCompatibilityParagonieSodiumCompat` [[GH](https://github.com/PHPCompatibility/PHPCompatibilityParagonie)|[Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-paragonie)] for the [Paragonie polyfills](https://github.com/paragonie?utf8=?&q=polyfill) and a number of rulesets related to various [polyfills offered by the Symfony project](https://github.com/symfony?utf8=?&q=polyfill) [[GH](https://github.com/PHPCompatibility/PHPCompatibilitySymfony)|[Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-symfony)].

If your project uses one of these polyfills, please consider using these special polyfill rulesets to prevent false positives.

Also as of this version, [Juliette Reinders Folmer] is now officially a co-maintainer of this package.

### Upgrade instructions

* If you have `<exclude name="..."/>` directives in your own project's custom ruleset which relate to sniffs from the PHPCompatibility library, you will need to update your ruleset to use the new sniff names.
* If you use the new [PHPCS 3.2+ inline annotations](https://github.com/squizlabs/PHP_CodeSniffer/releases/3.2.0), i.e. `// phpcs:ignore Standard.Category.SniffName`, in combination with PHPCompatibility sniff names, you will need to update these annotations.
* If you use neither of the above, you should be fine and upgrading should be painless.

### Overview of all the sniff renames:

Old Category.SniffName | New Category.SniffName
--- | ---
**PHP**.ArgumentFunctionsUsage | **FunctionUse**.ArgumentFunctionsUsage
**PHP**.CaseSensitiveKeywords | **Keywords**.CaseSensitiveKeywords
**PHP**.ConstantArraysUsingConst | **InitialValue**.**New**ConstantArraysUsingConst
**PHP**.ConstantArraysUsingDefine | **InitialValue**.**New**ConstantArraysUsingDefine
**PHP**.**Deprecated**Functions | **FunctionUse**.**Removed**Functions
**PHP**.**Deprecated**IniDirectives | **IniDirectives**.**Removed**IniDirectives
**PHP**.**Deprecated**MagicAutoload | **FunctionNameRestrictions**.**Removed**MagicAutoload
**PHP**.**Deprecated**NewReference | **Syntax**.**Removed**NewReference
**PHP**.**Deprecated**PHP4StyleConstructors | **FunctionNameRestrictions**.**Removed**PHP4StyleConstructors
**PHP**.**Deprecated**TypeCasts | **TypeCasts**.**Removed**TypeCasts
**PHP**.DiscouragedSwitchContinue | **ControlStructures**.DiscouragedSwitchContinue
**PHP**.DynamicAccessToStatic | **Syntax**.**New**DynamicAccessToStatic
**PHP**.EmptyNonVariable | **LanguageConstructs**.**New**EmptyNonVariable
**PHP**.ForbiddenBreakContinueOutsideLoop | **ControlStructures**.ForbiddenBreakContinueOutsideLoop
**PHP**.ForbiddenBreakContinueVariableArguments | **ControlStructures**.ForbiddenBreakContinueVariableArguments
**PHP**.ForbiddenCallTimePassByReference | **Syntax**.ForbiddenCallTimePassByReference
**PHP**.Forbidden**ClosureUseVariableNames** | **FunctionDeclarations**.Forbidden**VariableNamesInClosureUse**
**PHP**.ForbiddenEmptyListAssignment | **Lists**.ForbiddenEmptyListAssignment
**PHP**.Forbidden**Function**ParametersWithSameName | **FunctionDeclarations**.ForbiddenParametersWithSameName
**PHP**.ForbiddenGlobalVariableVariable | **Variables**.ForbiddenGlobalVariableVariable
**PHP**.ForbiddenNames | **Keywords**.ForbiddenNames
**PHP**.ForbiddenNamesAsDeclared | **Keywords**.ForbiddenNamesAsDeclared
**PHP**.ForbiddenNamesAsInvokedFunctions | **Keywords**.ForbiddenNamesAsInvokedFunctions
**PHP**.ForbiddenNegativeBitshift | **Operators**.ForbiddenNegativeBitshift
**PHP**.ForbiddenSwitchWithMultipleDefaultBlocks | **ControlStructures**.ForbiddenSwitchWithMultipleDefaultBlocks
**PHP**.InternalInterfaces | **Interfaces**.InternalInterfaces
**PHP**.LateStaticBinding | **Classes**.**New**LateStaticBinding
**PHP**.**MbstringReplaceE**Modifier | **ParameterValues**.**RemovedMbstring**Modifier**s**
**PHP**.NewAnonymousClasses | **Classes**.NewAnonymousClasses
**PHP**.NewArrayStringDereferencing | **Syntax**.NewArrayStringDereferencing
**PHP**.NewClasses | **Classes**.NewClasses
**PHP**.NewClassMemberAccess | **Syntax**.NewClassMemberAccess
**PHP**.NewClosure | **FunctionDeclarations**.NewClosure
**PHP**.NewConstants | **Constants**.NewConstants
**PHP**.NewConstantScalarExpressions | **InitialValue**.NewConstantScalarExpressions
**PHP**.NewConstVisibility | **Classes**.NewConstVisibility
**PHP**.NewExecutionDirectives | **ControlStructures**.NewExecutionDirectives
**PHP**.NewFunctionArrayDereferencing | **Syntax**.NewFunctionArrayDereferencing
**PHP**.NewFunctionParameters | **FunctionUse**.NewFunctionParameters
**PHP**.NewFunctions | **FunctionUse**.NewFunctions
**PHP**.NewGeneratorReturn | **Generators**.NewGeneratorReturn
**PHP**.NewGroupUseDeclarations | **UseDeclarations**.NewGroupUseDeclarations
**PHP**.NewHashAlgorithms | **ParameterValues**.NewHashAlgorithms
**PHP**.NewHeredoc**Initialize** | **InitialValue**.NewHeredoc
**PHP**.NewIniDirectives | **IniDirectives**.NewIniDirectives
**PHP**.NewInterfaces | **Interfaces**.NewInterfaces
**PHP**.NewKeywords | **Keywords**.NewKeywords
**PHP**.NewLanguageConstructs | **LanguageConstructs**.NewLanguageConstructs
**PHP**.NewMagicClassConstant | **Constants**.NewMagicClassConstant
**PHP**.NewMagicMethods | **FunctionNameRestrictions**.NewMagicMethods
**PHP**.NewMultiCatch | **ControlStructures**.NewMultiCatch
**PHP**.NewNullableTypes | **FunctionDeclarations**.NewNullableTypes
**PHP**.NewReturnTypeDeclarations | **FunctionDeclarations**.NewReturnTypeDeclarations
**PHP**.New**Scalar**TypeDeclarations | **FunctionDeclarations**.New**Param**TypeDeclarations
**PHP**.NewTrailingComma | **Syntax**.New**FunctionCall**TrailingComma
**PHP**.NewTypeCasts | **TypeCasts**.NewTypeCasts
**PHP**.NewUseConstFunction | **UseDeclarations**.NewUseConstFunction
**PHP**.NonStaticMagicMethods | **FunctionDeclarations**.NonStaticMagicMethods
**PHP**.OptionalRequiredFunctionParameters | **FunctionUse**.Optional**To**RequiredFunctionParameters
**PHP**.ParameterShadowSuperGlobals | **FunctionDeclarations**.**Forbidden**ParameterShadowSuperGlobals
**PHP**.**PCRENew**Modifiers | **ParameterValues**.**NewPCRE**Modifiers
**PHP**.**PregReplaceE**Modifier | **ParameterValues**.**RemovedPCRE**Modifier**s**
**PHP**.RemovedAlternativePHPTags | **Miscellaneous**.RemovedAlternativePHPTags
**PHP**.RemovedConstants | **Constants**.RemovedConstants
**PHP**.RemovedExtensions | **Extensions**.RemovedExtensions
**PHP**.RemovedFunctionParameters | **FunctionUse**.RemovedFunctionParameters
**PHP**.RemovedGlobalVariables | **Variables**.Removed**Predefined**GlobalVariables
**PHP**.RemovedHashAlgorithms | **ParameterValues**.RemovedHashAlgorithms
**PHP**.ReservedFunctionNames | **FunctionNameRestrictions**.ReservedFunctionNames
**PHP**.RequiredOptionalFunctionParameters | **FunctionUse**.Required**To**OptionalFunctionParameters
**PHP**.ShortArray | **Syntax**.**New**ShortArray
**PHP**.Ternary**Operators** | **Operators**.**NewShort**Ternary
**PHP**.ValidIntegers | **Miscellaneous**.ValidIntegers
**PHP**.**VariableVariables** | **Variables**.**NewUniformVariableSyntax**

### Changelog for version 9.0.0

See all related issues and PRs in the [9.0.0 milestone].

### Added
- :star2: New `PHPCompatibility.ControlStructures.NewForeachExpressionReferencing` sniff to detect referencing of `$value` within a `foreach()` when the iterated array is not a variable. This was not supported prior to PHP 5.5. [#664](https://github.com/PHPCompatibility/PHPCompatibility/pull/664)
- :star2: New `PHPCompatibility.ControlStructures.NewListInForeach` sniff to detect unpacking nested arrays into separate variables via the `list()` construct in a `foreach()` statement. This was not supported prior to PHP 5.5. [#657](https://github.com/PHPCompatibility/PHPCompatibility/pull/657)
- :star2: New `PHPCompatibility.FunctionNameRestrictions.RemovedNamespacedAssert` sniff to detect declaring a function called `assert()` within a namespace. This has been deprecated as of PHP 7.3. [#735](https://github.com/PHPCompatibility/PHPCompatibility/pull/735). Partially fixes [#718](https://github.com/PHPCompatibility/PHPCompatibility/issues/718).
- :star2: New `PHPCompatibility.Lists.AssignmentOrder` sniff to detect `list()` constructs affected by the change in assignment order in PHP 7.0. [#656](https://github.com/PHPCompatibility/PHPCompatibility/pull/656)
- :star2: New `PHPCompatibility.Lists.NewKeyedList` sniff to detect usage of keys in `list()`, support for which was added in PHP 7.1. [#655](https://github.com/PHPCompatibility/PHPCompatibility/pull/655). Fixes [#252](https://github.com/PHPCompatibility/PHPCompatibility/issues/252).
- :star2: New `PHPCompatibility.Lists.NewListReferenceAssignment` sniff to detect reference assignments being used in `list()` constructs, support for which has been added in PHP 7.3. [#731](https://github.com/PHPCompatibility/PHPCompatibility/pull/731)
- :star2: New `PHPCompatibility.Lists.NewShortList` sniff to detect the shorthand array syntax `[]` being used for symmetric array destructuring as introduced in PHP 7.1. [#654](https://github.com/PHPCompatibility/PHPCompatibility/pull/654). Fixes [#248](https://github.com/PHPCompatibility/PHPCompatibility/issues/248).
- :star2: New `PHPCompatibility.Operators.NewOperators` sniff which checks for usage of the pow, pow equals, spaceship and coalesce (equals) operators. [#738](https://github.com/PHPCompatibility/PHPCompatibility/pull/738)
    These checks were previously contained within the `PHPCompatibility.LanguageConstructs.NewLanguageConstructs` sniff.
- :star2: New `PHPCompatibility.ParameterValues.ForbiddenGetClassNull` sniff to detect `null` being passed to `get_class()`, support for which has been removed in PHP 7.2 [#659](https://github.com/PHPCompatibility/PHPCompatibility/pull/659). Fixes [#557](https://github.com/PHPCompatibility/PHPCompatibility/issues/557).
- :star2: New `PHPCompatibility.ParameterValues.NewArrayReduceInitialType` sniff to detect non-integers being passed as the `$initial` parameter to the `array_reduce()` function, which was not supported before PHP 5.3. [#666](https://github.com/PHPCompatibility/PHPCompatibility/pull/666). Fixes [#649](https://github.com/PHPCompatibility/PHPCompatibility/issues/649)
- :star2: New `PHPCompatibility.ParameterValues.NewFopenModes` sniff to examine the `$mode` parameter passed to `fopen()` for modes not available in older PHP versions. [#658](https://github.com/PHPCompatibility/PHPCompatibility/pull/658)
- :star2: New `PHPCompatibility.ParameterValues.NewNegativeStringOffset` sniff to detect negative string offsets being passed to string manipulation functions which was not supported before PHP 7.1. [#662](https://github.com/PHPCompatibility/PHPCompatibility/pull/662). Partially fixes [#253](https://github.com/PHPCompatibility/PHPCompatibility/issues/253).
- :star2: New `PHPCompatibility.ParameterValues.NewPackFormats` sniff to examine the `$format` parameter passed to `pack()` for formats not available in older PHP versions. [#665](https://github.com/PHPCompatibility/PHPCompatibility/pull/665)
- :star2: New `PHPCompatibility.ParameterValues.RemovedIconvEncoding` sniff to detect the PHP 5.6 deprecated encoding `$type`s being passed to `iconv_set_encoding()`. [#660](https://github.com/PHPCompatibility/PHPCompatibility/pull/660). Fixes [#475](https://github.com/PHPCompatibility/PHPCompatibility/issues/475).
- :star2: New `PHPCompatibility.ParameterValues.RemovedNonCryptoHashes` sniff to detect non-cryptographic hash algorithms being passed to various `hash_*()` functions. This is no longer accepted as of PHP 7.2. [#663](https://github.com/PHPCompatibility/PHPCompatibility/pull/663). Fixes [#559](https://github.com/PHPCompatibility/PHPCompatibility/issues/559)
- :star2: New `PHPCompatibility.ParameterValues.RemovedSetlocaleString` sniff to detect string literals being passed to the `$category` parameter of the `setlocale()` function. This behaviour was deprecated in PHP 4.2 and support has been removed in PHP 7.0. [#661](https://github.com/PHPCompatibility/PHPCompatibility/pull/661)
- :star2: New `PHPCompatibility.Syntax.NewFlexibleHeredocNowdoc` sniff to detect the new heredoc/nowdoc format as allowed as of PHP 7.3. [#736](https://github.com/PHPCompatibility/PHPCompatibility/pull/736). Fixes [#705](https://github.com/PHPCompatibility/PHPCompatibility/issues/705).
    Note: This sniff is only supported in combination with PHP_CodeSniffer 2.6.0 and higher.
- :star: `PHPCompatibility.Classes.NewClasses` sniff: recognize the new `CompileError` and `JsonException` classes as introduced in PHP 7.3. [#676](https://github.com/PHPCompatibility/PHPCompatibility/pull/676)
- :star: `PHPCompatibility.Constants.NewConstants` sniff: recognize new constants which are being introduced in PHP 7.3. [#678](https://github.com/PHPCompatibility/PHPCompatibility/pull/678)
- :star: `PHPCompatibility.Constants.RemovedConstants` sniff: recognize constants which have been deprecated or removed in PHP 7.3. [#710](https://github.com/PHPCompatibility/PHPCompatibility/pull/710). Partially fixes [#718](https://github.com/PHPCompatibility/PHPCompatibility/issues/718).
- :star: `PHPCompatibility.FunctionUse.NewFunctions` sniff: recognize various new functions being introduced in PHP 7.3. [#679](https://github.com/PHPCompatibility/PHPCompatibility/pull/679)
- :star: `PHPCompatibility.FunctionUse.NewFunctions` sniff: recognize the `sapi_windows_*()`, `hash_hkdf()` and `pcntl_signal_get_handler()` functions as introduced in PHP 7.1. [#728](https://github.com/PHPCompatibility/PHPCompatibility/pull/728)
- :star: `PHPCompatibility.FunctionUse.RemovedFunctionParameters` sniff: recognize the deprecation of the `$case_insensitive` parameter for the `define()` function in PHP 7.3. [#706](https://github.com/PHPCompatibility/PHPCompatibility/pull/706)
- :star: `PHPCompatibility.FunctionUse.RemovedFunctions` sniff: recognize the PHP 7.3 deprecation of the `image2wbmp()`, `fgetss()` and `gzgetss()` functions, as well as the deprecation of undocumented Mbstring function aliases. [#681](https://github.com/PHPCompatibility/PHPCompatibility/pull/681), [#714](https://github.com/PHPCompatibility/PHPCompatibility/pull/714), [#720](https://github.com/PHPCompatibility/PHPCompatibility/pull/720). Partially fixes [#718](https://github.com/PHPCompatibility/PHPCompatibility/issues/718).
- :star: `PHPCompatibility.FunctionUse.RequiredToOptionalFunctionParameters` sniff: account for the second parameter for `array_push()` and `array_unshift()` becoming optional in PHP 7.3, as well as for the `$mode` parameter for a range of `ftp_*()` functions becoming optional. [#680](https://github.com/PHPCompatibility/PHPCompatibility/pull/680)
- :star: `PHPCompatibility.IniDirectives.NewIniDirectives` sniff: recognize new `syslog` and `session` ini directives as introduced in PHP 7.3. [#702](https://github.com/PHPCompatibility/PHPCompatibility/pull/702), [#719](https://github.com/PHPCompatibility/PHPCompatibility/pull/719), [#730](https://github.com/PHPCompatibility/PHPCompatibility/pull/730)
- :star: `PHPCompatibility.IniDirectives.NewIniDirectives` sniff: recognize some more ini directives which were introduced in PHP 7.1. [#727](https://github.com/PHPCompatibility/PHPCompatibility/pull/727)
- :star: `PHPCompatibility.IniDirectives.RemovedIniDirectived` sniff: recognize ini directives removed in PHP 7.3. [#677](https://github.com/PHPCompatibility/PHPCompatibility/pull/677), [#717](https://github.com/PHPCompatibility/PHPCompatibility/pull/717). Partially fixes [#718](https://github.com/PHPCompatibility/PHPCompatibility/issues/718).
- :star: New `isNumericCalculation()` and `isVariable()` utility methods to the `PHPCompatibility\Sniff` class. [#664](https://github.com/PHPCompatibility/PHPCompatibility/pull/664), [#666](https://github.com/PHPCompatibility/PHPCompatibility/pull/666)
- :books: A section about the new sniff naming conventions to the `Contributing` file. [#738](https://github.com/PHPCompatibility/PHPCompatibility/pull/738)

### Changed
- :fire: All sniffs have been placed in meaningful categories and a number of sniffs have been renamed to have more consistent, meaningful and future-proof names. [#738](https://github.com/PHPCompatibility/PHPCompatibility/pull/738). Fixes [#601](https://github.com/PHPCompatibility/PHPCompatibility/issues/601), [#692](https://github.com/PHPCompatibility/PHPCompatibility/issues/692)
    See the table at the top of this changelog for details of all the file renames.
- :umbrella: The unit test files have been moved about as well. [#738](https://github.com/PHPCompatibility/PHPCompatibility/pull/738)
    * The directory structure for these now mirrors the default directory structure used by PHPCS itself.
    * The file names of the unit test files have been adjusted for the changes made in the sniffs.
    * The unit test case files have been renamed and moved to the same directory as the actual test file they apply to.
    * The `BaseSniffTest::sniffFile()` method has been adjusted to match. The signature of this method has changed. Where it previously expected a relative path to the unit test case file, it now expects an absolute path.
    * The unit tests for the utility methods in the `PHPCompatibility\Sniff` class have been moved to a new `PHPCompatibility\Util\Tests\Core` subdirectory.
    * The bootstrap file used for PHPUnit has been moved to the project root directory and renamed `phpunit-bootstrap.php`.
- :twisted_rightwards_arrows: The `PHPCompatibility.LanguageConstructs.NewLanguageConstructs` sniff has been split into two sniffs. [#738](https://github.com/PHPCompatibility/PHPCompatibility/pull/738)
    The `PHPCompatibility.LanguageConstructs.NewLanguageConstructs` sniff now contains just the checks for the namespace separator and the ellipsis.
    The new `PHPCompatibility.Operators.NewOperators` sniff now contains the checks regarding the pow, pow equals, spaceship and coalesce (equals) operators.
- :pushpin: The `PHPCompatibility.ParameterValues.RemovedMbstringModifiers` sniff will now also recognize removed regex modifiers when used within a function call to one of the undocumented Mbstring function aliases for the Mbstring regex functions. [#715](https://github.com/PHPCompatibility/PHPCompatibility/pull/715)
- :pushpin: The `PHPCompatibility\Sniff::getFunctionCallParameter()` utility method now allows for closures called via a variable. [#723](https://github.com/PHPCompatibility/PHPCompatibility/pull/723)
- :pencil2: `PHPCompatibility.Upgrade.LowPHPCS`: the minimum supported PHPCS version is now 2.3.0. [#699](https://github.com/PHPCompatibility/PHPCompatibility/pull/699)
- :pencil2: Minor inline documentation improvements. [#738](https://github.com/PHPCompatibility/PHPCompatibility/pull/738)
- :umbrella: Minor improvements to the unit tests for the `PHPCompatibility.FunctionNameRestrctions.RemovedMagicAutoload` sniff. [#716](https://github.com/PHPCompatibility/PHPCompatibility/pull/716)
- :recycle: Minor other optimizations. [#698](https://github.com/PHPCompatibility/PHPCompatibility/pull/698), [#697](https://github.com/PHPCompatibility/PHPCompatibility/pull/697)
- :wrench: Minor improvements to the build tools. [#701](https://github.com/PHPCompatibility/PHPCompatibility/pull/701)
- :wrench: Removed some unnecessary inline annotations. [#700](https://github.com/PHPCompatibility/PHPCompatibility/pull/700)
- :books: Replaced some of the badges in the Readme file. [#721](https://github.com/PHPCompatibility/PHPCompatibility/pull/721), [#722](https://github.com/PHPCompatibility/PHPCompatibility/pull/722)
- :books: Composer: updated the list of package authors. [#739](https://github.com/PHPCompatibility/PHPCompatibility/pull/739)

### Removed
- :no_entry_sign: Support for PHP_CodeSniffer 1.x and low 2.x versions. The new minimum version of PHP_CodeSniffer to be able to use this library is 2.3.0. [#699](https://github.com/PHPCompatibility/PHPCompatibility/pull/699). Fixes [#691](https://github.com/PHPCompatibility/PHPCompatibility/issues/691).
    The minimum _recommended_ version of PHP_CodeSniffer remains the same, i.e. 2.6.0.
- :no_entry_sign: The `\PHPCompatibility\Sniff::inUseScope()` method has been removed as it is no longer needed now support for PHPCS 1.x has been dropped. [#699](https://github.com/PHPCompatibility/PHPCompatibility/pull/699)
- :no_entry_sign: Composer: The `autoload` section has been removed from the `composer.json` file. [#738](https://github.com/PHPCompatibility/PHPCompatibility/pull/738). Fixes [#568](https://github.com/PHPCompatibility/PHPCompatibility/issues/568).
    Autoloading for this library is done via the PHP_CodeSniffer default mechanism, enhanced with our own autoloader, so the Composer autoloader shouldn't be needed and was causing issues in a particular use-case.

### Fixed
- :bug: `PHPCompatibility.FunctionUse.NewFunctionParameters` sniff: The new `$mode` parameter of the `php_uname()` function was added in PHP 4.3, not in PHP 7.0 as was previously being reported.
    The previous implementation of this check was based on an error in the PHP documentation. The error in the PHP documentation has been rectified and the sniff has followed suit. [#711](https://github.com/PHPCompatibility/PHPCompatibility/pull/711)
- :bug: `PHPCompatibility.Generators.NewGeneratorReturn` sniff: The sniff would throw false positives for `return` statements in nested constructs and did not correctly detect the scope which should be examined. [#725](https://github.com/PHPCompatibility/PHPCompatibility/pull/725). Fixes [#724](https://github.com/PHPCompatibility/PHPCompatibility/pull/724).
- :bug: `PHPCompatibility.Keywords.NewKeywords` sniff: PHP magic constants are case _in_sensitive. This sniff now accounts for this. [#707](https://github.com/PHPCompatibility/PHPCompatibility/pull/707)
- :bug: Various bugs in the `PHPCompatibility.Syntax.ForbiddenCallTimePassByReference` sniff [#723](https://github.com/PHPCompatibility/PHPCompatibility/pull/723):
    - Closures called via a variable will now also be examined. (false negative)
    - References within arrays/closures passed as function call parameters would incorrectly trigger an error. (false positive)
- :green_heart: Compatibility with PHPUnit 7.2. [#712](https://github.com/PHPCompatibility/PHPCompatibility/pull/712)

### Credits
Thanks go out to [Jonathan Champ] for his contribution to this version. :clap:


## [8.2.0] - 2018-07-17

See all related issues and PRs in the [8.2.0 milestone].

### Important changes

#### The repository has moved
As of July 13 2018, the PHPCompatibility repository has moved from the personal account of Wim Godden `wimg` to its own organization `PHPCompatibility`.
Composer users are advised to update their `composer.json`. The dependency is now called `phpcompatibility/php-compatibility`.

#### Framework/CMS specific PHPCompatibility rulesets
Within this new organization, hosting will be offered for framework/CMS specific PHPCompatibility rulesets.

The first two such repositories have been created and are now available for use:
* PHPCompatibilityJoomla [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityJoomla)|[Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-joomla)
* PHPCompatibilityWP [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityWP)|[Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-wp)

If you want to make sure you have all PHPCompatibility rulesets available at any time, you can use the PHPCompatibilityAll package [GitHub](https://github.com/PHPCompatibility/PHPCompatibilityAll)|[Packagist](https://packagist.org/packages/phpcompatibility/phpcompatibility-all).

For more information, see the [Readme](https://github.com/PHPCompatibility/PHPCompatibility#using-a-frameworkcms-specific-ruleset) and [Contributing guidelines](https://github.com/PHPCompatibility/PHPCompatibility/blob/master/.github/CONTRIBUTING.md#frameworkcms-specific-rulesets).

#### Changes expected in PHPCompatibility 9.0.0
The next version of PHPCompatibility will include a major directory layout restructuring which means that the sniff codes of all sniffs will change.

In this same release, support for PHP_CodeSniffer 1.5.x will be dropped. The new minimum supported PHPCS version will be 2.3.0.

For more information about these upcoming changes, please read the [announcement](https://github.com/PHPCompatibility/PHPCompatibility/issues/688).

The `9.0.0` release is expected to be ready later this summer.


### Added
- :star2: New `ArgumentFunctionsUsage` sniff to detect usage of the `func_get_args()`, `func_get_arg()` and `func_num_args()` functions and the changes regarding these functions introduced in PHP 5.3. [#596](https://github.com/PHPCompatibility/PHPCompatibility/pull/596). Fixes [#372](https://github.com/PHPCompatibility/PHPCompatibility/issues/372).
- :star2: New `DiscouragedSwitchContinue` sniff to detect `continue` targetting a `switch` control structure for which `E_WARNINGS` will be thrown as of PHP 7.3. [#687](https://github.com/PHPCompatibility/PHPCompatibility/pull/687)
- :star2: New `NewClassMemberAccess` sniff to detect class member access on instantiation as added in PHP 5.4 and class member access on cloning as added in PHP 7.0. [#619](https://github.com/PHPCompatibility/PHPCompatibility/pull/619). Fixes [#53](https://github.com/PHPCompatibility/PHPCompatibility/issues/53).
- :star2: New `NewConstantScalarExpressions` sniff to detect PHP 5.6 scalar expression in contexts where PHP previously only allowed static values. [#617](https://github.com/PHPCompatibility/PHPCompatibility/pull/617). Fixes [#399](https://github.com/PHPCompatibility/PHPCompatibility/issues/399).
- :star2: New `NewGeneratorReturn` sniff to detect `return` statements within generators as introduced in PHP 7.0. [#618](https://github.com/PHPCompatibility/PHPCompatibility/pull/618)
- :star2: New `PCRENewModifiers` sniff to initially detect the new `J` regex modifier as introduced in PHP 7.2. [#600](https://github.com/PHPCompatibility/PHPCompatibility/pull/600). Fixes [#556](https://github.com/PHPCompatibility/PHPCompatibility/issues/556).
- :star2: New `ReservedFunctionNames` sniff to report on double underscore prefixed functions and methods. This was previously reported via an upstream sniff. [#581](https://github.com/PHPCompatibility/PHPCompatibility/pull/581)
- :star2: New `NewTrailingComma` sniff to detect trailing comma's in function calls, method calls, `isset()`  and `unset()` as will be introduced in PHP 7.3. [#632](https://github.com/PHPCompatibility/PHPCompatibility/pull/632)
- :star2: New `Upgrade/LowPHPCS` sniff to give users of old PHP_CodeSniffer versions advance warning when support will be dropped in the near future. [#693](https://github.com/PHPCompatibility/PHPCompatibility/pull/693)
- :star: `NewClasses` sniff: check for some 40+ additional PHP native classes added in various PHP versions. [#573](https://github.com/PHPCompatibility/PHPCompatibility/pull/573)
- :star: `NewClosure` sniff: check for usage of `self`/`parent`/`static::` being used within closures, support for which was only added in PHP 5.4. [#669](https://github.com/PHPCompatibility/PHPCompatibility/pull/669). Fixes [#668](https://github.com/PHPCompatibility/PHPCompatibility/pull/668).
- :star: `NewConstants` sniff: recognize constants added by the PHP 5.5+ password extension. [#626](https://github.com/PHPCompatibility/PHPCompatibility/pull/626)
- :star: `NewFunctionParameters` sniff: recognize a number of additional function parameters added in PHP 7.0, 7.1 and 7.2. [#602](https://github.com/PHPCompatibility/PHPCompatibility/pull/602)
- :star: `NewFunctions` sniff: recognize the PHP 5.1 SPL extension functions, the PHP 5.1.1 `hash_hmac()` function, the PHP 5.6 `pg_lo_truncate()` function, more PHP 7.2 Sodium functions and the new PHP 7.3 `is_countable()` function. [#606](https://github.com/PHPCompatibility/PHPCompatibility/pull/606), [#625](https://github.com/PHPCompatibility/PHPCompatibility/pull/625), [#640](https://github.com/PHPCompatibility/PHPCompatibility/pull/640), [#651](https://github.com/PHPCompatibility/PHPCompatibility/pull/651)
- :star: `NewHashAlgorithms` sniff: recognize the new hash algorithms which were added in PHP 7.1. [#599](https://github.com/PHPCompatibility/PHPCompatibility/pull/599)
- :star: `NewInterfaces` sniff: check for the PHP 5.0 `Reflector` interface. [#572](https://github.com/PHPCompatibility/PHPCompatibility/pull/572)
- :star: `OptionalRequiredFunctionParameters` sniff: detect missing `$salt` parameter in calls to the `crypt()` function (PHP 5.6+). [#605](https://github.com/PHPCompatibility/PHPCompatibility/pull/605)
- :star: `RequiredOptionalFunctionParameters` sniff: recognize that the `$varname` parameter of `getenv()` and the `$scale` parameter of `bcscale()` have become optional as of PHP 7.1 and 7.3 respectively. [#598](https://github.com/PHPCompatibility/PHPCompatibility/pull/598), [#612](https://github.com/PHPCompatibility/PHPCompatibility/pull/612)
- :star: New `AbstractFunctionCallParameterSniff` to be used as a basis for sniffs examining function call parameters. [#636](https://github.com/PHPCompatibility/PHPCompatibility/pull/636)
- :star: New `getReturnTypeHintName()` utility method to the `PHPCompatibility\Sniff` class. [#578](https://github.com/PHPCompatibility/PHPCompatibility/pull/578), [#642](https://github.com/PHPCompatibility/PHPCompatibility/pull/642)
- :star: New `isNumber()`, `isPositiveNumber()` and `isNegativeNumber()` utility methods to the `PHPCompatibility\Sniff` class. [#610](https://github.com/PHPCompatibility/PHPCompatibility/pull/610), [#650](https://github.com/PHPCompatibility/PHPCompatibility/pull/650)
- :star: New `isShortList()` utility method to the `PHPCompatibility\Sniff` class. [#635](https://github.com/PHPCompatibility/PHPCompatibility/pull/635)
- :star: New `getCommandLineData()` method to the `PHPCompatibility\PHPCSHelper` class to provide PHPCS cross-version compatible access to command line info at run time. [#693](https://github.com/PHPCompatibility/PHPCompatibility/pull/693)
- :star: Duplicate of upstream `findEndOfStatement()` method to the `PHPCompatibility\PHPCSHelper` class to allow for PHPCS cross-version usage of that method. [#614](https://github.com/PHPCompatibility/PHPCompatibility/pull/614)
- :umbrella: additional unit test to confirm that the `PHPCompatibility\Sniff::isUseOfGlobalConstant()` method handles multi-constant declarations correctly. [#587](https://github.com/PHPCompatibility/PHPCompatibility/pull/587)
- :umbrella: additional unit tests to confirm that the `PHPCompatibility\Sniff::isClassProperty()` method handles multi-property declarations correctly. [#583](https://github.com/PHPCompatibility/PHPCompatibility/pull/583)
- :books: [Readme](https://github.com/PHPCompatibility/PHPCompatibility#using-a-frameworkcms-specific-ruleset) & [Contributing](https://github.com/PHPCompatibility/PHPCompatibility/blob/master/.github/CONTRIBUTING.md#frameworkcms-specific-rulesets): add information about the framework/CMS specific rulesets. Related PRs: [#615](https://github.com/PHPCompatibility/PHPCompatibility/pull/615), [#624](https://github.com/PHPCompatibility/PHPCompatibility/pull/624), [#648](https://github.com/PHPCompatibility/PHPCompatibility/pull/648), [#674](https://github.com/PHPCompatibility/PHPCompatibility/pull/674), [#685](https://github.com/PHPCompatibility/PHPCompatibility/pull/685), [#694](https://github.com/PHPCompatibility/PHPCompatibility/pull/694). Related to issue [#530](https://github.com/PHPCompatibility/PHPCompatibility/issues/530).
- :books: Readme: information about the PHPCS 3.3.0 change which allows for a `testVersion` in a custom ruleset to be overruled by the command-line. [#607](https://github.com/PHPCompatibility/PHPCompatibility/pull/607)

### Changed
- :books: Adjusted references to the old repository location throughout the codebase to reflect the move to a GitHub organization. [#689](https://github.com/PHPCompatibility/PHPCompatibility/pull/689)
    This repository will now live in [https://github.com/PHPCompatibility/PHPCompatibility](https://github.com/PHPCompatibility/PHPCompatibility) and the Packagist reference will now be `phpcompatibility/php-compatibility`.
- :white_check_mark: The `getReturnTypeHintToken()` utility method has been made compatible with the changes in the PHPCS tokenizer which were introduced in PHP_CodeSniffer 3.3.0. [#642](https://github.com/PHPCompatibility/PHPCompatibility/pull/642). Fixes [#639](https://github.com/PHPCompatibility/PHPCompatibility/issues/639).
- :pushpin: `ConstantArrayUsingConst`: improved handling of multi-constant declarations. [#593](https://github.com/PHPCompatibility/PHPCompatibility/pull/593)
- :pushpin: `NewHeredocInitialize`: improved handling of constant declarations using the `const` keyword.
    The sniff will now also report on multi-declarations for variables, constants and class properties and on using heredoc as a function parameter default. [#641](https://github.com/PHPCompatibility/PHPCompatibility/pull/641)
- :pushpin: `ForbiddenEmptyListAssignment`: this sniff will now also report on empty list assignments when the PHP 7.1 short list syntax is used. [#653](https://github.com/PHPCompatibility/PHPCompatibility/pull/653)
- :pushpin: The `ForbiddenNegativeBitshift` sniff would previously only report on "bitshift right". As of this version, "bitshift left" and bitshift assignments will also be recognized. [#614](https://github.com/PHPCompatibility/PHPCompatibility/pull/614)
- :pushpin: The `NewClasses` and `NewInterfaces` sniffs will now also report on new classes/interfaces when used as _return type_ declarations. [#578](https://github.com/PHPCompatibility/PHPCompatibility/pull/578)
- :pushpin: The `NewScalarTypeDeclarations` sniff will now recognize `parent` as a valid type declaration.
    The sniff will now also throw an error about using `self` and `parent` when PHP < 5.2 needs to be supported as PHP 5.1 and lower would presume these to be class names instead of keywords. [#595](https://github.com/PHPCompatibility/PHPCompatibility/pull/595)
- :pushpin: The `PregReplaceEModifier` sniff - and the `PCRENewModifiers` sniff by extension - will now correctly examine and report on modifiers in regexes passed via calls to `preg_replace_callback_array()`. [#600](https://github.com/PHPCompatibility/PHPCompatibility/pull/600), [#636](https://github.com/PHPCompatibility/PHPCompatibility/pull/636)
- :pushpin: `getReturnTypeHintToken()` utility method: improved support for interface methods and abstract function declarations. [#652](https://github.com/PHPCompatibility/PHPCompatibility/pull/652)
- :pushpin: The `findExtendedClassName()`, `findImplementedInterfaceNames()`, `getMethodParameters()` utility methods which are duplicates of upstream PHPCS methods, have been moved from the `PHPCompatibility\Sniff` class to the `PHPCompatibility\PHPCSHelper` class and have become static methods. [#613](https://github.com/PHPCompatibility/PHPCompatibility/pull/613)
- :white_check_mark: `getReturnTypeHintToken()` utility method: align returned `$stackPtr` with native PHPCS behaviour by returning the last token of the type declaration. [#575](https://github.com/PHPCompatibility/PHPCompatibility/pull/575)
- :white_check_mark: PHPCS cross-version compatibility: sync `getMethodParameters()` method with improved upstream version. [#643](https://github.com/PHPCompatibility/PHPCompatibility/pull/643)
- :pencil2: The `MbstringReplaceEModifier`, `PregReplaceEModifier` and the `PregReplaceEModifier` sniffs now `extend` the new `AbstractFunctionCallParameterSniff` class. This should yield more accurate results when checking whether one of the target PHP functions was called. [#636](https://github.com/PHPCompatibility/PHPCompatibility/pull/636)
- :pencil2: `DeprecatedNewReference` sniff: minor change to the error text and code - was `Forbidden`, now `Removed` -. Custom rulesets which explicitly excluded this error code will need to be updated. [#594](https://github.com/PHPCompatibility/PHPCompatibility/pull/594)
- :pencil2: `NewScalarTypeDeclarations` sniff: minor change to the error message text.[#644](https://github.com/PHPCompatibility/PHPCompatibility/pull/644)
- :umbrella: The unit test framework now allows for sniffs in categories other than `PHP`. [#634](https://github.com/PHPCompatibility/PHPCompatibility/pull/634)
- :umbrella: Boyscouting: fixed up some (non-relevant) parse errors in a unit test case file. [#576](https://github.com/PHPCompatibility/PHPCompatibility/pull/576)
- :green_heart: Travis: build tests are now also being run against the lowest supported PHPCS 3.x version. Previously only the highest supported PHPCS 3.x version was tested against. [#633](https://github.com/PHPCompatibility/PHPCompatibility/pull/633)
- :books: Readme: Improved Composer install instructions. [#690](https://github.com/PHPCompatibility/PHPCompatibility/pull/690)
- :books: Minor documentation fixes. [#672](https://github.com/PHPCompatibility/PHPCompatibility/pull/672)
- :wrench: Minor performance optimizations and code simplifications. [#592](https://github.com/PHPCompatibility/PHPCompatibility/pull/592), [#630](https://github.com/PHPCompatibility/PHPCompatibility/pull/630), [#671](https://github.com/PHPCompatibility/PHPCompatibility/pull/671)
- :wrench: Composer: Various improvements, including improved information about the suggested packages, suggesting `roave/security-advisories`, allowing for PHPUnit 7.x. [#604](https://github.com/PHPCompatibility/PHPCompatibility/pull/604/files), [#616](https://github.com/PHPCompatibility/PHPCompatibility/pull/616), [#622](https://github.com/PHPCompatibility/PHPCompatibility/pull/622), [#646](https://github.com/PHPCompatibility/PHPCompatibility/pull/646)
- :wrench: Various Travis build script improvements, including tweaks for faster build time, validation of the `composer.json` file, validation of the framework specific rulesets. [#570](https://github.com/PHPCompatibility/PHPCompatibility/pull/570), [#571](https://github.com/PHPCompatibility/PHPCompatibility/pull/571), [#579](https://github.com/PHPCompatibility/PHPCompatibility/pull/579), [#621](https://github.com/PHPCompatibility/PHPCompatibility/pull/621), [#631](https://github.com/PHPCompatibility/PHPCompatibility/pull/631)
- :wrench: Build/PHPCS: made some more CS conventions explicit and start using PHPCS 3.x options for the PHPCompatibility native ruleset. [#586](https://github.com/PHPCompatibility/PHPCompatibility/pull/586), [#667](https://github.com/PHPCompatibility/PHPCompatibility/pull/667), [#673](https://github.com/PHPCompatibility/PHPCompatibility/pull/673)
- :wrench: Some code style clean up and start using the new inline PHPCS 3.2+ annotations where applicable. [#586](https://github.com/PHPCompatibility/PHPCompatibility/pull/586), [#591](https://github.com/PHPCompatibility/PHPCompatibility/pull/591), [#620](https://github.com/PHPCompatibility/PHPCompatibility/pull/620), [#673](https://github.com/PHPCompatibility/PHPCompatibility/pull/673)

### Removed
- :no_entry_sign: PHPCompatibility no longer explicitly supports PHP_CodeSniffer 2.2.0. [#687](https://github.com/PHPCompatibility/PHPCompatibility/pull/687), [#690](https://github.com/PHPCompatibility/PHPCompatibility/pull/690)
- :no_entry_sign: The PHPCompatibility ruleset no longer includes the PHPCS native `Generic.NamingConventions.CamelCapsFunctionName`. Double underscore prefixed function names are now being reported on by a new dedicated sniff. [#581](https://github.com/PHPCompatibility/PHPCompatibility/pull/581)
- :no_entry_sign: PHPCompatibility no longer explicitly supports HHVM and builds are no longer tested against HHVM.
    For now, running PHPCompatibility on HHVM to test PHP code may still work for a little while, but HHVM has announced they are [dropping PHP support](https://hhvm.com/blog/2017/09/18/the-future-of-hhvm.html). [#623](https://github.com/PHPCompatibility/PHPCompatibility/pull/623). Fixes [#603](https://github.com/PHPCompatibility/PHPCompatibility/issues/603).
- :books: Readme: badges from services which are no longer supported or inaccurate. [#609](https://github.com/PHPCompatibility/PHPCompatibility/pull/609), [#628](https://github.com/PHPCompatibility/PHPCompatibility/pull/628)

### Fixed
- :bug: Previously, the PHPCS native `Generic.NamingConventions.CamelCapsFunctionName` sniff was included in PHPCompatibility. Some error codes of this sniff were excluded, as well as some error messages changed (via the ruleset).
    If/when PHPCompatibility would be used in combination with a code style-type ruleset, this could inadvertently lead to underreporting of issues which the CS-type ruleset intends to have reported - i.e. the error codes excluded by PHPCompatibility -. This has now been fixed. [#581](https://github.com/PHPCompatibility/PHPCompatibility/pull/581)
- :bug: The `ForbiddenNegativeBitshift` sniff would incorrectly throw an error when a bitshift was based on a calculation which included a negative number, but would not necessarily result in a negative number. [#614](https://github.com/PHPCompatibility/PHPCompatibility/pull/614). Fixes [#294](https://github.com/PHPCompatibility/PHPCompatibility/issues/294), [#466](https://github.com/PHPCompatibility/PHPCompatibility/issues/466).
- :bug: The `NewClosure` sniff would report the same issue twice when the issue was encountered in a nested closure. [#669](https://github.com/PHPCompatibility/PHPCompatibility/pull/669)
- :bug: The `NewKeywords` sniff would underreport on non-lowercase keywords. [#627](https://github.com/PHPCompatibility/PHPCompatibility/pull/627)
- :bug: The `NewKeywords` sniff would incorrectly report on the use of class constants and class properties using the same name as a keyword. [#627](https://github.com/PHPCompatibility/PHPCompatibility/pull/627)
- :bug: The `NewNullableTypes` sniff would potentially underreport when comments where interspersed in the (return) type declarations. [#577](https://github.com/PHPCompatibility/PHPCompatibility/pull/577)
- :bug: The `Sniff::getFunctionCallParameters()` utility method would in rare cases return incorrect results when it encountered a closure as a parameter. [#682](https://github.com/PHPCompatibility/PHPCompatibility/pull/682)
- :bug: The `Sniff::getReturnTypeHintToken()` utility method would not always return a `$stackPtr`. [#645](https://github.com/PHPCompatibility/PHPCompatibility/pull/645)
- :bug: Minor miscellanous other bugfixes. [#670](https://github.com/PHPCompatibility/PHPCompatibility/pull/670)
- :umbrella: `PHPCompatibility\Tests\BaseClass\MethodTestFrame::getTargetToken()` could potentially not find the correct token to run a test against. [#588](https://github.com/PHPCompatibility/PHPCompatibility/pull/588)

### Credits
Thanks go out to [Michael Babker] and [Juliette Reinders Folmer] for their contributions to this version. :clap:


## [8.1.0] - 2017-12-27

See all related issues and PRs in the [8.1.0 milestone].

### Added
- :star2: New `NewConstants` and `RemovedConstants` sniffs to detect usage of new/removed PHP constants for all PHP versions from PHP 5 up. [#526](https://github.com/PHPCompatibility/PHPCompatibility/pull/525), [#551](https://github.com/PHPCompatibility/PHPCompatibility/pull/551), [#566](https://github.com/PHPCompatibility/PHPCompatibility/pull/566). Fixes [#263](https://github.com/PHPCompatibility/PHPCompatibility/issues/263).
- :star2: New `MagicAutoloadDeprecation` sniff to detect deprecated `__autoload()` functions as deprecated in PHP 7.2. [#540](https://github.com/PHPCompatibility/PHPCompatibility/pull/540)
- :star2: New `OptionalRequiredFunctionParameter` sniff to check for missing function call parameters which were required and only became optional in a later PHP version. [#524](https://github.com/PHPCompatibility/PHPCompatibility/pull/524)
- :star2: New `DynamicAccessToStatic` sniff to detect dynamic access to static methods and properties, as well as class constants, prior to PHP 5.3. [#535](https://github.com/PHPCompatibility/PHPCompatibility/pull/535). Fixes [#534](https://github.com/PHPCompatibility/PHPCompatibility/issues/534).
- :star: `DeprecatedFunctions` sniff: recognize yet more PHP 7.2 deprecated functions. [#561](https://github.com/PHPCompatibility/PHPCompatibility/pull/561),  [#566](https://github.com/PHPCompatibility/PHPCompatibility/pull/566)
- :star: `DeprecatedIniDirectives` sniff: recognize the last of the PHP 7.2 deprecated ini directives.  [#566](https://github.com/PHPCompatibility/PHPCompatibility/pull/566), [#567](https://github.com/PHPCompatibility/PHPCompatibility/pull/567)
- :star: `NewFunctions` : detection of all new PHP 7.2 functions added. [#522](https://github.com/PHPCompatibility/PHPCompatibility/pull/522), [#545](https://github.com/PHPCompatibility/PHPCompatibility/pull/545), [#551](https://github.com/PHPCompatibility/PHPCompatibility/pull/551), [#565](https://github.com/PHPCompatibility/PHPCompatibility/pull/565)
- :star: `RemovedExtensions` : report on usage of the `mcrypt` extension which has been removed in PHP 7.2. [#566](https://github.com/PHPCompatibility/PHPCompatibility/pull/566)
- :star: `RemovedGlobalVariables` : detection of the use of `$php_errormsg` with `track_errors` which has been deprecated in PHP 7.2. [#528](https://github.com/PHPCompatibility/PHPCompatibility/pull/528)
- :books: Documentation : added reporting usage instructions. [#533](https://github.com/PHPCompatibility/PHPCompatibility/pull/533), [#552](https://github.com/PHPCompatibility/PHPCompatibility/pull/552)

### Changed
- :pushpin: `NewClosures` : downgraded "$this found in closure outside class" to warning. [#536](https://github.com/PHPCompatibility/PHPCompatibility/pull/535). Fixes [#527](https://github.com/PHPCompatibility/PHPCompatibility/issues/527).
- :pushpin: `ForbiddenGlobalVariableVariable` : the sniff will now throw an error for each variable in a `global` statement which is no longer supported and show the variable found to make it easier to fix this. Previously only one error would be thrown per `global` statement. [#564](https://github.com/PHPCompatibility/PHPCompatibility/pull/564)
- :pushpin: `ForbiddenGlobalVariableVariable` : the sniff will now throw `warning`s for non-bare variables used in a `global` statement as those are discouraged since PHP 7.0. [#564](https://github.com/PHPCompatibility/PHPCompatibility/pull/564)
- :rewind: `NewLanguageConstructs` : updated the version number for `T_COALESCE_EQUAL`. [#523](https://github.com/PHPCompatibility/PHPCompatibility/pull/523)
- :pencil2: `Sniff::getTestVersion()` : simplified regex logic. [#520](https://github.com/PHPCompatibility/PHPCompatibility/pull/520)
- :green_heart: Travis : build tests are now being run against PHP 7.2 as well. [#511](https://github.com/PHPCompatibility/PHPCompatibility/pull/511)
- :wrench: Improved check for superfluous whitespaces in files. [#542](https://github.com/PHPCompatibility/PHPCompatibility/pull/542)
- :wrench: Build/PHPCS : stabilized the exclude patterns. [#529](https://github.com/PHPCompatibility/PHPCompatibility/pull/529)
- :wrench: Build/PHPCS : added array indentation check. [#538](https://github.com/PHPCompatibility/PHPCompatibility/pull/538)
- :white_check_mark: PHPCS cross-version compatibility : sync `FindExtendedClassname()` method with upstream. [#507](https://github.com/PHPCompatibility/PHPCompatibility/pull/507)
- :wrench: The minimum version for the recommended `DealerDirect/phpcodesniffer-composer-installer` Composer plugin has been upped to `0.4.3`. [#548](https://github.com/PHPCompatibility/PHPCompatibility/pull/548)

### Fixed
- :bug: `ForbiddenCallTimePassByReference` : a false positive was being thrown when a global constant was followed by a _bitwise and_. [#562](https://github.com/PHPCompatibility/PHPCompatibility/pull/562). Fixes [#39](https://github.com/PHPCompatibility/PHPCompatibility/issues/39).
- :bug: `ForbiddenGlobalVariableVariable` : the sniff was overzealous and would also report on `global` in combination with variable variables which are still supported. [#564](https://github.com/PHPCompatibility/PHPCompatibility/pull/564). Fixes [#537](https://github.com/PHPCompatibility/PHPCompatibility/issues/537).
- :bug: `ForbiddenGlobalVariableVariable` : variables interspersed with whitespace and/or comments were not being reported. [#564](https://github.com/PHPCompatibility/PHPCompatibility/pull/564)
- :rewind: `ForbiddenNamesAsInvokedFunctions` : improved recognition of function invocations using forbidden words and prevent warnings for keywords which are no longer forbidden as method names in PHP 7.0+. [#516](https://github.com/PHPCompatibility/PHPCompatibility/pull/516). Fixes [#515](https://github.com/PHPCompatibility/PHPCompatibility/issues/515)
- :bug: `VariableVariables` : variables interspersed with whitespace and/or comments were not being reported. [#563](https://github.com/PHPCompatibility/PHPCompatibility/pull/563)
- :umbrella: Fixed some unintentional syntax errors in test files. [#539](https://github.com/PHPCompatibility/PHPCompatibility/pull/539)
- :umbrella: Tests : fixed case numbering error. [#525](https://github.com/PHPCompatibility/PHPCompatibility/pull/525)
- :books: Tests : added missing test skip explanation. [#521](https://github.com/PHPCompatibility/PHPCompatibility/pull/521)
- :wrench: Fixed PHPCS whitespaces. [#543](https://github.com/PHPCompatibility/PHPCompatibility/pull/543)
- :wrench: Fixed code test coverage verification. [#550](https://github.com/PHPCompatibility/PHPCompatibility/pull/550). Fixes [#549](https://github.com/PHPCompatibility/PHPCompatibility/issues/549).

### Credits
Thanks go out to [Juliette Reinders Folmer] and [Jonathan Van Belle] for their contributions to this version. :clap:


## [8.0.1] - 2017-08-07

See all related issues and PRs in the [8.0.1 milestone].

### Added
- :star2: New `DeprecatedTypeCasts` sniff to detect deprecated and removed type casts, such as the `(unset)` type cast as deprecated in PHP 7.2. [#498](https://github.com/PHPCompatibility/PHPCompatibility/pull/498)
- :star2: New `NewTypeCasts` sniff to detect type casts not present in older PHP versions such as the `(binary)` type cast as added in PHP 5.2.1. [#497](https://github.com/PHPCompatibility/PHPCompatibility/pull/497)
- :star: `NewGroupUseDeclaration`: Detection of PHP 7.2 trailing comma's in group use statements. [#504](https://github.com/PHPCompatibility/PHPCompatibility/pull/504)
- :star: `DeprecatedFunctions` sniff: recognize some more PHP 7.2 deprecated functions. [#501](https://github.com/PHPCompatibility/PHPCompatibility/pull/501)
- :star: `DeprecatedIniDirectives` sniff: recognize more PHP 7.2 deprecated ini directives. [#500](https://github.com/PHPCompatibility/PHPCompatibility/pull/500)
- :star: `ForbiddenNames` sniff: recognize `object` as a forbidden keyword since PHP 7.2. [#499](https://github.com/PHPCompatibility/PHPCompatibility/pull/499)
- :star: `NewReturnTypeDeclarations` sniff: recognize generic `parent`, PHP 7.1 `iterable` and PHP 7.2 `object` return type declarations. [#505](https://github.com/PHPCompatibility/PHPCompatibility/pull/505), [#499](https://github.com/PHPCompatibility/PHPCompatibility/pull/499)
- :star: `NewScalarTypeDeclarations` sniff: recognize PHP 7.2 `object` type declarion. [#499](https://github.com/PHPCompatibility/PHPCompatibility/pull/499)

### Changed
- :pencil2: Improved clarity of the deprecated functions alternative in the error message. [#502](https://github.com/PHPCompatibility/PHPCompatibility/pull/502)

### Fixed
- :fire_engine: Temporary hotfix for installed_paths (pending [upstream fix](https://github.com/squizlabs/PHP_CodeSniffer/issues/1591).) [#503](https://github.com/PHPCompatibility/PHPCompatibility/pull/503)

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:



## [8.0.0] - 2017-08-03

**IMPORTANT**: This release contains a **breaking change**. Please read the below information carefully before upgrading!

The directory layout of the PHPCompatibility standard has been changed for improved compatibility with Composer.
This means that the PHPCompatibility standard no longer extends from the root directory of the repository, but now lives in its own subdirectory `/PHPCompatibility`.

This release also bring compatibility with PHPCS 3.x to the PHPCompatibility standard.

There are two things you will need to be aware of:
* The path to the PHPCompatibility standard has changed.
* If you intend to upgrade to PHPCS 3.x, the path to the `phpcs` script has changed (upstream change).

Please follow the below upgrade instructions carefully. This should be a one-time only action.

### Upgrade instructions

### Before upgrading

If you had previously made accommodations for the old directory layout, you should remove any such _"hacks"_ (meant in the kindest of ways) now.

By this we mean: symlinks for the PHPCompatibility install to the `PHP_CodeSniffer/CodeSniffer/Standards` directory, scripts to move the sniffs files to the PHPCS directory, scripts which made symlinks etc.

So, please remove those first.

> **Side-note**:
>
> If you had previously forked this repository to solve this issue, please consider reverting your fork to the official version or removing it all together.

### Upgrading: re-registering PHPCompatibility with PHP CodeSniffer

External PHP CodeSniffer standards need to be registered with PHP CodeSniffer. You have probably done this the first time you used PHPCompatibility or have a script or Composer plugin in place to do this for you.

As the directory layout of PHPCompatibility has changed, the path previously registered with PHP CodeSniffer will no longer work and running `phpcs -i` will **_not_** list PHPCompatibility as one of the registered standards.

#### Using a Composer plugin

If you use Composer, we recommend you use a Composer plugin to sort this out. In previous install instructions we recommended the SimplyAdmin plugin for this. This plugin has since been abandoned. We now recommend the DealerDirect plugin.
```bash
composer remove --dev simplyadmire/composer-plugins
composer require --dev dealerdirect/phpcodesniffer-composer-installer:^0.4.3
composer install
composer update phpcompatibility/php-compatibility squizlabs/php_codesniffer
vendor/bin/phpcs -i
```
If all went well, you should now see PHPCompatibility listed again in the list of installed standards.

#### Manually re-registering PHPCompatibility

1. First run `phpcs --config-show` to check which path(s) are currently registered with PHP CodeSniffer for external standards.
2. Check in the below table what the new path for PHPCompatibility will be - the path should point to the root directory of your PHPCompatibility install (not to the sub-directory of the same name):

    Install type | Old path | New path
    ------------ | -------- | ---------
    Composer     | `vendor/wimg` | `vendor/phpcompatibility/php-compatibility`
    Unzipped release to arbitrary directory | `path/to/dir/abovePHPCompatibility` | `path/to/dir/abovePHPCompatibility/PHPCompatibility`
    Git checkout | `path/to/dir/abovePHPCompatibility` | `path/to/dir/abovePHPCompatibility/PHPCompatibility`
    PEAR         | If the old install instruction has been followed, not registered. | `path/to/PHPCompatibility`
    
    > **Side-note**:
	>
	> If you used the old install instructions for a PEAR install, i.e. checking out the latest release to the `PHP/CodeSniffer/Standards/PHPCompatibility` directory, and you intend to upgrade to PHP CodeSniffer 3.x, it is recommended you move the PHPCompatibility folder out of the PEAR directory now, as the layout of the PHPCS directory has changed with PHPCS 3.x and you may otherwise lose your PHPCompatibility install when you upgrade PHP CodeSniffer via PEAR.

3. There are two ways in which you can register the new `installed_paths` value with PHP CodeSniffer. Choose your preferred method:
    * Run `phpcs --config-set installed_paths ...` and include all previously installed paths including the _adjusted_ path for the PHPCompatibility standard.

        For example, if the previous value of `installed_paths` was
		
		`/path/to/MyStandard,/path/to/dir/abovePHPCompatibility`

		you should now set it using

		`phpcs --config-set installed_paths /path/to/MyStandard,/path/to/PHPCompatibility`

    * If you use a custom ruleset in combination with PHPCS 2.6.0 or higher, you can pass the value to PHPCS from your custom ruleset:
        ```xml
        <config name="installed_paths" value="vendor/phpcompatibility/php-compatibility" />
        ```
4. Run `phpcs -i` to verify that the PHPCompatibility standard is now listed again in the list of installed standards.


### Upgrading to PHPCS 3.x

The path to the `phpcs` script has changed in PHPCS 3.x which will impact how you call PHPCS.

Version | PHPCS 2.x | PHPCS 3.x
------- | --------- | ---------
Generic `phpcs` Command | `path/to/PHP_CodeSniffer/scripts/phpcs ....` | `path/to/PHP_CodeSniffer/bin/phpcs ....`
Composer command | `vendor/bin/phpcs ...` | `vendor/bin/phpcs ...`

So, for Composer users, nothing changes. For everyone else, you may want to add the `path/to/PHP_CodeSniffer/bin/phpcs` path to your PATH environment variable or adjust any scripts - like build scripts - which call PHPCS.


### Upgrading a Travis build script

If you run PHPCompatibility against your code as part of your Travis build:
* If you use Composer to install PHP CodeSniffer and PHPCompatibility on the travis image and you've made the above mentioned changes, your build should pass again.
* If you use `git clone` to install PHP CodeSniffer and PHPCompatibility on the travis image, your build will fail until you make the following changes:
    1. Check which branch of PHPCS is being checked out. If you previously fixed this to a pre-PHPCS 3.x branch or tag, you can now change this (back) to `master` or a PHPCS 3 tag.
    2. Check to which path PHPCompatibility is being cloned and adjust the path if necessary.
    3. Adjust the `phpcs --config-set installed_paths` command as described above to point to the root of the cloned PHPCompatibility repo.
    4. If you switched to using PHPCS 3.x, adjust the call to PHPCS.



### Changelog for version 8.0.0

See all related issues and PRs in the [8.0.0 milestone].

### Added
- :two_hearts: Support for PHP CodeSniffer 3.x. [#482](https://github.com/PHPCompatibility/PHPCompatibility/pull/482), [#481](https://github.com/PHPCompatibility/PHPCompatibility/pull/481), [#480](https://github.com/PHPCompatibility/PHPCompatibility/pull/480), [#488](https://github.com/PHPCompatibility/PHPCompatibility/pull/488), [#489](https://github.com/PHPCompatibility/PHPCompatibility/pull/489), [#495](https://github.com/PHPCompatibility/PHPCompatibility/pull/495)

### Changed
- :gift: As of this version PHPCompatibility will use semantic versioning.
- :fire: The directory structure of the repository has changed for better compatibility with installation via Composer. [#446](https://github.com/PHPCompatibility/PHPCompatibility/pull/446). Fixes [#102](https://github.com/PHPCompatibility/PHPCompatibility/issues/102), [#107](https://github.com/PHPCompatibility/PHPCompatibility/issues/107)
- :pencil2: The custom `functionWhitelist` property for the `PHPCompatibility.PHP.RemovedExtensions` sniff is now only supported in combination with PHP CodeSniffer 2.6.0 or higher (due to an upstream bug which was fixed in PHPCS 2.6.0). [#482](https://github.com/PHPCompatibility/PHPCompatibility/pull/482)
- :wrench: Improved the information provided to Composer from the `composer.json` file. [#446](https://github.com/PHPCompatibility/PHPCompatibility/pull/446), [#482](https://github.com/PHPCompatibility/PHPCompatibility/pull/482), [#486](https://github.com/PHPCompatibility/PHPCompatibility/pull/486)
- :wrench: Release archives will no longer contain the unit tests and other typical development files. You can still get these by using Composer with `--prefer-source` or by checking out a git clone of the repository. [#494](https://github.com/PHPCompatibility/PHPCompatibility/pull/494)
- :wrench: A variety of minor improvements to the build process. [#485](https://github.com/PHPCompatibility/PHPCompatibility/pull/485), [#486](https://github.com/PHPCompatibility/PHPCompatibility/pull/486), [#487](https://github.com/PHPCompatibility/PHPCompatibility/pull/487)
- :wrench: Some files for use by contributors have been renamed to use `.dist` extensions or moved for easier access. [#478](https://github.com/PHPCompatibility/PHPCompatibility/pull/478), [#479](https://github.com/PHPCompatibility/PHPCompatibility/pull/479), [#483](https://github.com/PHPCompatibility/PHPCompatibility/pull/483), [#493](https://github.com/PHPCompatibility/PHPCompatibility/pull/493)
- :books: The installation instructions in the Readme. [#496](https://github.com/PHPCompatibility/PHPCompatibility/pull/496)
- :books: The unit test instructions in the Contributing file. [#496](https://github.com/PHPCompatibility/PHPCompatibility/pull/496)
- :books: Improved the example code in the Readme. [#490](https://github.com/PHPCompatibility/PHPCompatibility/pull/490)

### Removed
- :no_entry_sign: Support for PHP 5.1 and 5.2.

    The sniffs can now only be run on PHP 5.3 or higher in combination with PHPCS 1.5.6 or 2.x and on PHP 5.4 or higher in combination with PHPCS 3.x. [#484](https://github.com/PHPCompatibility/PHPCompatibility/pull/484), [#482](https://github.com/PHPCompatibility/PHPCompatibility/pull/482)

### Credits
Thanks go out to [Gary Jones] and [Juliette Reinders Folmer] for their contributions to this version. :clap:


## [7.1.5] - 2017-07-17

See all related issues and PRs in the [7.1.5 milestone].

### Added
- :star: The `NewKeywords` sniff will now also sniff for `yield from` which was introduced in PHP 7.0. [#477](https://github.com/PHPCompatibility/PHPCompatibility/pull/477). Fixes [#476](https://github.com/PHPCompatibility/PHPCompatibility/issues/476)
- :books: The LGPL-3.0 license. [#447](https://github.com/PHPCompatibility/PHPCompatibility/pull/447)

### Changed
- :rewind: The `NewExecutionDirectives` sniff will now also report on execution directives when used in combination with PHPCS 2.0.0-2.3.3. [#451](https://github.com/PHPCompatibility/PHPCompatibility/pull/451)
- :rewind: The `getMethodParameters()` utility method will no longer break when used with PHPCS 1.5.x < 1.5.6. This affected a number of sniffs. [#452](https://github.com/PHPCompatibility/PHPCompatibility/pull/452)
- :rewind: The `inUseScope()` utility method will no longer break when used with PHPCS 2.0.0 - 2.2.0. This affected a number of sniffs. [#454](https://github.com/PHPCompatibility/PHPCompatibility/pull/454)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#443](https://github.com/PHPCompatibility/PHPCompatibility/pull/443), [#474](https://github.com/PHPCompatibility/PHPCompatibility/pull/474)
- :pencil2: Renamed a test file for consistency. [#453](https://github.com/PHPCompatibility/PHPCompatibility/pull/453)
- :wrench: Code style clean up. [#429](https://github.com/PHPCompatibility/PHPCompatibility/pull/429)
- :wrench: Prevent Composer installing PHPCS 3.x. **_PHPCS 3.x is not (yet) supported by the PHPCompatibility standard, but will be in the near future._** [#444](https://github.com/PHPCompatibility/PHPCompatibility/pull/444)
- :green_heart: The code base will now be checked for consistent code style during build testing. [#429](https://github.com/PHPCompatibility/PHPCompatibility/pull/429)
- :green_heart: The sniffs are now also tested against HHVM for consistent results. _Note: the sniffs do not contain any HHVM specific checks nor is there any intention to add them at this time._ [#450](https://github.com/PHPCompatibility/PHPCompatibility/pull/450)
- :books: Made it explicit that - at this moment - PHPCS 3.x is not (yet) supported. [#444](https://github.com/PHPCompatibility/PHPCompatibility/pull/444)
- :books: Minor improvements to the Readme. [#448](https://github.com/PHPCompatibility/PHPCompatibility/pull/448), [#449](https://github.com/PHPCompatibility/PHPCompatibility/pull/449), [#468](https://github.com/PHPCompatibility/PHPCompatibility/pull/468)
- :books: Minor improvements to the Contributing guidelines. [#467](https://github.com/PHPCompatibility/PHPCompatibility/pull/467)

### Removed
- :no_entry_sign: The `DefaultTimeZoneRequired` sniff. This sniff was checking server settings rather than code. [#458](https://github.com/PHPCompatibility/PHPCompatibility/pull/458). Fixes [#457](https://github.com/PHPCompatibility/PHPCompatibility/issues/457)
- :no_entry_sign: The `NewMagicClassConstant` sniff as introduced in v 7.1.4 contained two additional checks for not strictly compatibility related issues. One of these was plainly wrong, the other opinionated. Both have been removed. [#442](https://github.com/PHPCompatibility/PHPCompatibility/pull/442). Fixes [#436](https://github.com/PHPCompatibility/PHPCompatibility/issues/436)

### Fixed
- :bug: `NewClass` sniff: was reporting an incorrect introduction version number for a few of the Exception classes. [#441](https://github.com/PHPCompatibility/PHPCompatibility/pull/441). Fixes [#440](https://github.com/PHPCompatibility/PHPCompatibility/issues/440).
- :bug: `ForbiddenBreakContinueVariableArguments` sniff: was incorrectly reporting an error if the `break` or `continue` was followed by a PHP closing tag (breaking out of PHP). [#462](https://github.com/PHPCompatibility/PHPCompatibility/pull/462). Fixes [#460](https://github.com/PHPCompatibility/PHPCompatibility/issues/460)
- :bug: `ForbiddenGlobalVariableVariable` sniff: was incorrectly reporting an error if the `global` statement was followed by a PHP closing tag (breaking out of PHP). [#463](https://github.com/PHPCompatibility/PHPCompatibility/pull/463).
- :bug: `DeprecatedFunctions` sniff: was reporting false positives for classes using the same name as a deprecated function. [#465](https://github.com/PHPCompatibility/PHPCompatibility/pull/465). Fixes [#464](https://github.com/PHPCompatibility/PHPCompatibility/issues/464)

### Credits
Thanks go out to [Juliette Reinders Folmer] and [Mark Clements] for their contributions to this version. :clap:


## [7.1.4] - 2017-05-06

See all related issues and PRs in the [7.1.4 milestone].

### Added
- :star2: New `CaseSensitiveKeywords` sniff to detect use of non-lowercase `self`, `static` and `parent` keywords which could cause compatibility issues pre-PHP 5.5. [#382](https://github.com/PHPCompatibility/PHPCompatibility/pull/382)
- :star2: New `ConstantArraysUsingConst` sniff to detect constants defined using the `const` keyword being assigned an array value which was not supported prior to PHP 5.6. [#397](https://github.com/PHPCompatibility/PHPCompatibility/pull/397)
- :star2: New `ForbiddenClosureUseVariableNames` sniff to detect PHP 7.1 forbidden variable names in closure use statements. [#386](https://github.com/PHPCompatibility/PHPCompatibility/pull/386). Fixes [#374](https://github.com/PHPCompatibility/PHPCompatibility/issues/374)
- :star2: New `NewArrayStringDereferencing` sniff to detect array and string literal dereferencing as introduced in PHP 5.5. [#388](https://github.com/PHPCompatibility/PHPCompatibility/pull/388)
- :star2: New `NewHeredocInitialize` sniff to detect initialization of static variables and class properties/constants using the heredoc syntax which is supported since PHP 5.3. [#391](https://github.com/PHPCompatibility/PHPCompatibility/pull/391). Fixes [#51](https://github.com/PHPCompatibility/PHPCompatibility/issues/51)
- :star2: New `NewMagicClassConstant` sniff to detect use of the magic `::class` constant as introduced in PHP 5.5. [#403](https://github.com/PHPCompatibility/PHPCompatibility/pull/403). Fixes [#364](https://github.com/PHPCompatibility/PHPCompatibility/issues/364).
- :star2: New `NewUseConstFunction` sniff to detect use statements importing constants and functions as introduced in PHP 5.6. [#401](https://github.com/PHPCompatibility/PHPCompatibility/pull/401)
- :star: `DeprecatedFunctions` sniff: recognize PHP 7.2 deprecated GD functions. [#392](https://github.com/PHPCompatibility/PHPCompatibility/pull/392)
- :star: `DeprecatedIniDirectives` sniff: recognize PHP 7.2 deprecated `mbstring.func_overload` directive. [#377](https://github.com/PHPCompatibility/PHPCompatibility/pull/377)
- :star: `NewClasses` sniff: check for the PHP 5.1 `libXMLError` class. [#412](https://github.com/PHPCompatibility/PHPCompatibility/pull/412)
- :star: `NewClasses` sniff: recognize all native PHP Exception classes. [#418](https://github.com/PHPCompatibility/PHPCompatibility/pull/418)
- :star: `NewClosure` sniff: check for closures being declared as static and closures using `$this`. Both of which was not supported pre-PHP 5.4. [#389](https://github.com/PHPCompatibility/PHPCompatibility/pull/389). Fixes [#24](https://github.com/PHPCompatibility/PHPCompatibility/issues/24).
- :star: `NewFunctionParameters` sniff: recognize new `exclude_disabled` parameter for the `get_defined_functions()` function as introduced in PHP 7.0.15. [#375](https://github.com/PHPCompatibility/PHPCompatibility/pull/375)
- :star: `NewFunctions` sniff: recognize new PHP 7.2 socket related functions. [#376](https://github.com/PHPCompatibility/PHPCompatibility/pull/376)
- :star: `NewInterfaces` sniff: check for some more PHP native interfaces. [#411](https://github.com/PHPCompatibility/PHPCompatibility/pull/411)
- :star: New `isClassProperty()`, `isClassConstant()` and `validDirectScope()` utility methods to the `PHPCompatibility_Sniff` class. [#393](https://github.com/PHPCompatibility/PHPCompatibility/pull/393), [#391](https://github.com/PHPCompatibility/PHPCompatibility/pull/391).
- :star: New `getTypeHintsFromFunctionDeclaration()` utility method to the `PHPCompatibility_Sniff` class. [#414](https://github.com/PHPCompatibility/PHPCompatibility/pull/414).
- :umbrella: Unit tests against false positives for the `NewMagicMethods` sniff. [#381](https://github.com/PHPCompatibility/PHPCompatibility/pull/381)
- :umbrella: More unit tests for the `getTestVersion()` utility method. [#405](https://github.com/PHPCompatibility/PHPCompatibility/pull/405), [#430](https://github.com/PHPCompatibility/PHPCompatibility/pull/430)
- :green_heart: The XML of the ruleset will now be validated and checked for consistent code style during the build testing by Travis. [#433](https://github.com/PHPCompatibility/PHPCompatibility/pull/433)
- :books: Readme: information about setting `installed_paths` via a custom ruleset. [#407](https://github.com/PHPCompatibility/PHPCompatibility/pull/407)
- :books: `Changelog.md` file containing a record of notable changes since the first tagged release. [#421](https://github.com/PHPCompatibility/PHPCompatibility/pull/421)

### Changed
- :pushpin: The `ForbiddenNamesAsDeclared` sniff will now emit `warning`s for soft reserved keywords. [#406](https://github.com/PHPCompatibility/PHPCompatibility/pull/406), [#370](https://github.com/PHPCompatibility/PHPCompatibility/pull/370).
- :pushpin: The `ForbiddenNames` sniff will now allow for the more liberal rules for usage of reserved keywords as of PHP 7.0. [#417](https://github.com/PHPCompatibility/PHPCompatibility/pull/417)
- :pushpin: The `InternalInterfaces`, `NewClasses`, `NewConstVisibility`, `NewInterfaces`, `NewMagicMethods`, `NonStaticMagicMethods` and `RemovedGlobalVariables` sniffs will now also sniff for and correctly report violations in combination with anonymous classes. [#378](https://github.com/PHPCompatibility/PHPCompatibility/pull/378), [#383](https://github.com/PHPCompatibility/PHPCompatibility/pull/383), [#393](https://github.com/PHPCompatibility/PHPCompatibility/pull/393), [#394](https://github.com/PHPCompatibility/PHPCompatibility/pull/394), [#395](https://github.com/PHPCompatibility/PHPCompatibility/pull/395), [#396](https://github.com/PHPCompatibility/PHPCompatibility/pull/396). Fixes [#351](https://github.com/PHPCompatibility/PHPCompatibility/issues/351) and [#333](https://github.com/PHPCompatibility/PHPCompatibility/issues/333).
- :pushpin: The `NewClasses` and `NewInterfaces` sniffs will now also report on new classes/interfaces when used as type hints. [#414](https://github.com/PHPCompatibility/PHPCompatibility/pull/414), [#416](https://github.com/PHPCompatibility/PHPCompatibility/pull/416). Fixes [#352](https://github.com/PHPCompatibility/PHPCompatibility/issues/352)
- :pushpin: The `NewClasses` sniff will now also report on Exception classes when used in (multi-)`catch` statements. [#418](https://github.com/PHPCompatibility/PHPCompatibility/pull/418). Fixes [#373](https://github.com/PHPCompatibility/PHPCompatibility/issues/373).
- :pushpin: The `NewScalarTypeDeclarations` sniff will now report on new type hints even when the type hint is nullable. [#379](https://github.com/PHPCompatibility/PHPCompatibility/pull/379)
- :twisted_rightwards_arrows: The `NewNowdoc` sniff has been renamed to `NewNowdocQuotedHeredoc` and will now also check for double quoted heredoc identifiers as introduced in PHP 5.3. [#390](https://github.com/PHPCompatibility/PHPCompatibility/pull/390)
- :rewind: The `NewClasses` sniff will now also report anonymous classes which `extend` a new sniff when used in combination with PHPCS 2.4.0-2.8.0. [#432](https://github.com/PHPCompatibility/PHPCompatibility/pull/432). Fixes [#334](https://github.com/PHPCompatibility/PHPCompatibility/issues/334).
- :pencil2: `NewFunctionParameter` sniff: version number precision for two parameters. [#384](https://github.com/PHPCompatibility/PHPCompatibility/pull/384), [#428](https://github.com/PHPCompatibility/PHPCompatibility/pull/428)
- :umbrella: Skipping two unit tests for the `ForbiddenClosureUseVariable` sniff when run on PHPCS 2.5.1 as these cause an infinite loop due to an upstream bug. [#408](https://github.com/PHPCompatibility/PHPCompatibility/pull/408)
- :umbrella: Skipping unit tests involving `trait`s in combination with PHP < 5.4 and PHPCS < 2.4.0 as `trait`s are not recognized in those circumstances. [#431](https://github.com/PHPCompatibility/PHPCompatibility/pull/431)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#385](https://github.com/PHPCompatibility/PHPCompatibility/pull/385), [#387](https://github.com/PHPCompatibility/PHPCompatibility/pull/387), [#415](https://github.com/PHPCompatibility/PHPCompatibility/pull/415), [#423](https://github.com/PHPCompatibility/PHPCompatibility/pull/423), [#424](https://github.com/PHPCompatibility/PHPCompatibility/pull/424)
- :recycle: Minor simplification of the PHPUnit 6 compatibility layer and other test code. [#426](https://github.com/PHPCompatibility/PHPCompatibility/pull/426), [#425](https://github.com/PHPCompatibility/PHPCompatibility/pull/425)
- General housekeeping. [#398](https://github.com/PHPCompatibility/PHPCompatibility/pull/398), [#400](https://github.com/PHPCompatibility/PHPCompatibility/pull/400)
- :wrench: Minor tweaks to the Travis build script. [#409](https://github.com/PHPCompatibility/PHPCompatibility/pull/409)
- :green_heart: The sniffs are now also tested against PHP nightly for consistent results. [#380](https://github.com/PHPCompatibility/PHPCompatibility/pull/380)

### Fixed
- :fire: Using unbounded ranges in `testVersion` resulted in unreported errors when used with sniffs using the `supportsBelow()` method. This affected the results of approximately half the sniffs. [#430](https://github.com/PHPCompatibility/PHPCompatibility/pull/430)
- :bug: The `ForbiddenNames` sniff would throw false positives for `use` statements with the `final` modifier in traits. [#402](https://github.com/PHPCompatibility/PHPCompatibility/pull/402).
- :bug: The `ForbiddenNames` sniff would fail to report on functions declared to return by reference using a reserved keyword as the function name. [#413](https://github.com/PHPCompatibility/PHPCompatibility/pull/413)
- :bug: The `ForbiddenNames` sniff would only examine the first part of a namespace and not report on reserved keywords used in subsequent parts of a nested namespace. [#419](https://github.com/PHPCompatibility/PHPCompatibility/pull/419)
- :bug: The `ForbiddenNames` sniff would not always correctly report on use statements importing constants or functions using reserved keywords. [#420](https://github.com/PHPCompatibility/PHPCompatibility/pull/420)
- :bug: The `NewKeywords` sniff would sometimes fail to report on the `const` keyword when used in a class, but not for a class constant. [#424](https://github.com/PHPCompatibility/PHPCompatibility/pull/424)
- :green_heart: PHPCS has released version 3.0 and updated the `master` branch to reflect this. This was causing the builds to fail. [#422](https://github.com/PHPCompatibility/PHPCompatibility/pull/422)

### Credits
Thanks go out to [Juliette Reinders Folmer] and [Mark Clements] for their contributions to this version. :clap:


## [7.1.3] - 2017-04-02

See all related issues and PRs in the [7.1.3 milestone].

### Added
- :zap: The `testVersion` config parameter now allows for specifying unbounded ranges.
    For example: specifying `-5.6` means: check for compatibility with all PHP versions up to and including PHP 5.6;
    Specifying `7.0-` means: check for compatibility with all PHP versions from PHP 7.0 upwards.
    For more information about setting the `testVersion`, see [Using the compatibility sniffs](https://github.com/PHPCompatibility/PHPCompatibility#using-the-compatibility-sniffs) in the readme.
- :umbrella: Unit test for multi-line short arrays for the `ShortArray` sniff. [#347](https://github.com/PHPCompatibility/PHPCompatibility/pull/347)
- :umbrella: Various additional unit tests against false positives. [#345](https://github.com/PHPCompatibility/PHPCompatibility/pull/345), [#369](https://github.com/PHPCompatibility/PHPCompatibility/pull/369)
- :umbrella: Unit tests for the `supportsBelow()`, `supportsAbove()` and `getTestVersion()` utility methods. [#363](https://github.com/PHPCompatibility/PHPCompatibility/pull/363)
- :books: Readme: information about installation of the standard using git check-out. [#349](https://github.com/PHPCompatibility/PHPCompatibility/pull/349)
- :books: `Contributing.md` file with information about reporting bugs, requesting features, making pull requests and running the unit tests. [#350](https://github.com/PHPCompatibility/PHPCompatibility/pull/350)

### Changed
- :pushpin: The `ForbiddenFunctionParametersWithSameName`, `NewScalarTypeDeclarations`, `ParameterShadowSuperGlobals` sniff will now also sniff for and report violations in closures. [#331](https://github.com/PHPCompatibility/PHPCompatibility/pull/331)
- :twisted_rightwards_arrows: :rewind: The check for the PHP 5.3 `nowdoc` structure has been moved from the `NewKeywords` sniff to a new stand-alone `NewNowdoc` sniff which will now also recognize this structure when the sniffs are run on PHP 5.2. [#335](https://github.com/PHPCompatibility/PHPCompatibility/pull/335)
- :rewind: The `ForbiddenNames` sniff will now also correctly recognize reserved keywords used in a declared namespace when run on PHP 5.2. [#362](https://github.com/PHPCompatibility/PHPCompatibility/pull/362)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#360](https://github.com/PHPCompatibility/PHPCompatibility/pull/360)
- :recycle: The unit tests would previously run each test case file against all PHPCompatibility sniffs. Now, they will only be tested against the sniff which the test case file is intended to test. This allows for more test cases to be tested, more precise testing in combination with `testVersion` settings and makes the unit tests run ~6 x faster.
    Relevant additional unit tests have been added and others adjusted. [#369](https://github.com/PHPCompatibility/PHPCompatibility/pull/369)
- :recycle: Refactoring/tidying up of some unit test code. [#343](https://github.com/PHPCompatibility/PHPCompatibility/pull/343), [#345](https://github.com/PHPCompatibility/PHPCompatibility/pull/345), [#356](https://github.com/PHPCompatibility/PHPCompatibility/pull/356), [#355](https://github.com/PHPCompatibility/PHPCompatibility/pull/355), [#359](https://github.com/PHPCompatibility/PHPCompatibility/pull/359)
- General housekeeping. [#346](https://github.com/PHPCompatibility/PHPCompatibility/pull/346)
- :books: Readme: Clarify minimum requirements and influence on the results. [#348](https://github.com/PHPCompatibility/PHPCompatibility/pull/348)

### Removed
- :twisted_rightwards_arrows: Removed the `LongArrays` sniff. The checks it contained have been moved into the `RemovedGlobalVariables` sniff. Both sniffs essentially did the same thing, just for different PHP native superglobals. [#354](https://github.com/PHPCompatibility/PHPCompatibility/pull/354)

### Fixed
- :bug: The `PregReplaceEModifier` sniff would throw a false positive if a quote character was used as the regex delimiter. [#357](https://github.com/PHPCompatibility/PHPCompatibility/pull/357)
- :bug: `RemovedGlobalVariables` sniff would report false positives for class properties shadowing the removed `$HTTP_RAW_POST_DATA` variables. [#354](https://github.com/PHPCompatibility/PHPCompatibility/pull/354).
- :bug: The `getFQClassNameFromNewToken()` utility function could go into an infinite loop causing PHP to run out of memory when examining unfinished code (examination during live coding). [#338](https://github.com/PHPCompatibility/PHPCompatibility/pull/338), [#342](https://github.com/PHPCompatibility/PHPCompatibility/pull/342)
- :bug: The `determineNamespace()` utility method would in certain cases not break out a loop. [#358](https://github.com/PHPCompatibility/PHPCompatibility/pull/358)
- :wrench: Travis script: Minor tweak for PHP 5.2 compatibility. [#341](https://github.com/PHPCompatibility/PHPCompatibility/pull/341)
- :wrench: The unit test suite is now also compatible with PHPUnit 6. [#365](https://github.com/PHPCompatibility/PHPCompatibility/pull/365)
- :books: Readme: Typo in the composer instructions. [#344](https://github.com/PHPCompatibility/PHPCompatibility/pull/344)

### Credits
Thanks go out to [Arthur Edamov], [Juliette Reinders Folmer], [Mark Clements] and [Tadas Juozapaitis] for their contributions to this version. :clap:


## [7.1.2] - 2017-02-17

See all related issues and PRs in the [7.1.2 milestone].

### Added
- :star2: New `VariableVariables` sniff to detect variables variables for which the behaviour has changed in PHP 7.0. [#310](https://github.com/PHPCompatibility/PHPCompatibility/pull/310) Fixes [#309](https://github.com/PHPCompatibility/PHPCompatibility/issues/309).
- :star: The `NewReturnTypeDeclarations` sniff will now also sniff for non-scalar return type declarations, i.e. `array`, `callable`, `self` or a class name. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323)
- :star: The `NewLanguageConstructs` sniff will now also sniff for the null coalesce equal operator `??=`. This operator is slated to be introduced in PHP 7.2 and PHPCS already accounts for it. [#340](https://github.com/PHPCompatibility/PHPCompatibility/pull/340)
- :star: New `getReturnTypeHintToken()` utility method to the `PHPCompatibility_Sniff` class to retrieve return type hints from function declarations in a cross-PHPCS-version compatible way. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323).
- :star: New `stripVariables()` utility method to the `PHPCompatibility_Sniff` class to strip variables from interpolated text strings. [#341](https://github.com/PHPCompatibility/PHPCompatibility/pull/314).
- :umbrella: Additional unit tests covering previously uncovered code. [#308](https://github.com/PHPCompatibility/PHPCompatibility/pull/308)

### Changed
- :pushpin: The `MbstringReplaceEModifier`, `PregReplaceEModifier` and `NewExecutionDirectives` sniffs will now also correctly interpret double quoted text strings with interpolated variables. [#341](https://github.com/PHPCompatibility/PHPCompatibility/pull/314), [#324](https://github.com/PHPCompatibility/PHPCompatibility/pull/324).
- :pushpin: The `NewNullableTypes` sniff will now also report on nullable (return) type hints when used with closures. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323)
- :pushpin: The `NewReturnTypeDeclarations` sniff will now also report on return type hints when used with closures. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323)
- :pushpin: Allow for anonymous classes in the `inClassScope()` utility method. [#315](https://github.com/PHPCompatibility/PHPCompatibility/pull/315)
- :pushpin: The function call parameter related utility functions can now also be used to get the individual items from an array declaration. [#300](https://github.com/PHPCompatibility/PHPCompatibility/pull/300)
- :twisted_rightwards_arrows: The `NewScalarReturnTypeDeclarations` sniff has been renamed to `NewReturnTypeDeclarations`. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323)
- :rewind: The `ForbiddenNames` sniff will now also correctly ignore anonymous classes when used in combination with PHPCS < 2.3.4. [#319](https://github.com/PHPCompatibility/PHPCompatibility/pull/319)
- :rewind: The `NewAnonymousClasses` sniff will now correctly recognize and report on anonymous classes when used in combination with PHPCS < 2.5.2. [#325](https://github.com/PHPCompatibility/PHPCompatibility/pull/325)
- :rewind: The `NewGroupUseDeclarations` sniff will now correctly recognize and report on group use statements when used in combination with PHPCS < 2.6.0. [#320](https://github.com/PHPCompatibility/PHPCompatibility/pull/320)
- :rewind: The `NewNullableTypes` sniff will now correctly recognize and report on nullable return types when used in combination with PHPCS < 2.6.0. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323)
- :rewind: The `NewReturnTypeDeclarations` sniff will now correctly recognize and report on new return types when used in combination with PHPCS < 2.6.0. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#317](https://github.com/PHPCompatibility/PHPCompatibility/pull/317)
- :recycle: Defer to upstream `hasCondition()` utility method where appropriate. [#315](https://github.com/PHPCompatibility/PHPCompatibility/pull/315)
- :recycle: Minor refactoring of some unit test code. [#304](https://github.com/PHPCompatibility/PHPCompatibility/pull/304), [#303](https://github.com/PHPCompatibility/PHPCompatibility/pull/303), [#318](https://github.com/PHPCompatibility/PHPCompatibility/pull/318)
- :wrench: All unit tests now have appropriate `@group` annotations allowing for quicker/easier testing of a select group of tests/sniffs. [#305](https://github.com/PHPCompatibility/PHPCompatibility/pull/305)
- :wrench: All unit tests now have appropriate `@covers` annotations to improve code coverage reporting and remove bleed through of accidental coverage. [#307](https://github.com/PHPCompatibility/PHPCompatibility/pull/307)
- :wrench: Minor tweaks to the travis script. [#322](https://github.com/PHPCompatibility/PHPCompatibility/pull/322)
- :green_heart: The PHPCompatibility code base itself will now be checked for cross-version compatibility during build testing. [#322](https://github.com/PHPCompatibility/PHPCompatibility/pull/322)

### Fixed
- :bug: The `ConstantArraysUsingDefine` sniff would throw false positives if the value of the `define()` was retrieved via a function call and an array parameter was passed. [#327](https://github.com/PHPCompatibility/PHPCompatibility/pull/327)
- :bug: The `ForbiddenCallTimePassByReference` sniff would throw false positives on assign by reference within function calls or conditions. [#302](https://github.com/PHPCompatibility/PHPCompatibility/pull/302) Fixes the last two cases reported in [#68](https://github.com/PHPCompatibility/PHPCompatibility/issues/68#issuecomment-231366445)
- :bug: The `ForbiddenGlobalVariableVariableSniff` sniff would only examine the first variable in a `global ...` statement causing unreported issues if subsequent variables were variable variables. [#316](https://github.com/PHPCompatibility/PHPCompatibility/pull/316)
- :bug: The `NewKeywords` sniff would throw a false positive for the `const` keyword when encountered in an interface. [#312](https://github.com/PHPCompatibility/PHPCompatibility/pull/312)
- :bug: The `NewNullableTypes` sniff would not report on nullable return types for namespaced classnames used as a type hint. [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323)
- :bug: The `PregReplaceEModifier` sniff would always consider the first parameter passed as a single regex, while it could also be an array of regexes. This led to false positives and potentially unreported use of the `e` modifier when an array of regexes was passed. [#300](https://github.com/PHPCompatibility/PHPCompatibility/pull/300)
- :bug: The `PregReplaceEModifier` sniff could misidentify the regex delimiter when the regex to be examined was concatenated together from various text strings taken from a compound parameter leading to false positives. [#300](https://github.com/PHPCompatibility/PHPCompatibility/pull/300)
- :white_check_mark: Compatibility with PHPCS 2.7.x. Deal with changed behaviour of the upstream PHP tokenizer and utility function(s). [#313](https://github.com/PHPCompatibility/PHPCompatibility/pull/313), [#323](https://github.com/PHPCompatibility/PHPCompatibility/pull/323), [#326](https://github.com/PHPCompatibility/PHPCompatibility/pull/326), [#340](https://github.com/PHPCompatibility/PHPCompatibility/pull/340)

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.1.1] - 2016-12-14

See all related issues and PRs in the [7.1.1 milestone].

### Added
- :star: `ForbiddenNamesAsDeclared` sniff: detection of the PHP 7.1 `iterable` and `void` reserved keywords when used to name classes, interfaces or traits. [#298](https://github.com/PHPCompatibility/PHPCompatibility/pull/298)

### Fixed
- :bug: The `ForbiddenNamesAsInvokedFunctions` sniff would incorrectly throw an error if the `clone` keyword was used with parenthesis. [#299](https://github.com/PHPCompatibility/PHPCompatibility/pull/299). Fixes [#284](https://github.com/PHPCompatibility/PHPCompatibility/issues/284)

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.1.0] - 2016-12-14

See all related issues and PRs in the [7.1.0 milestone].

### Added
- :star: New `stringToErrorCode()`, `arrayKeysToLowercase()` and `addMessage()` utility methods to the `PHPCompatibility_Sniff` class. [#291](https://github.com/PHPCompatibility/PHPCompatibility/pull/291).

### Changed
- :pushpin: All sniff error messages now have modular error codes allowing for selectively disabling individual checks - and even selectively disabling individual sniff for specific files - without disabling the complete sniff. [#291](https://github.com/PHPCompatibility/PHPCompatibility/pull/291)
- :pencil2: Minor changes to some of the error message texts for consistency across sniffs. [#291](https://github.com/PHPCompatibility/PHPCompatibility/pull/291)
- :recycle: Refactored the complex version sniffs to reduce code duplication. [#291](https://github.com/PHPCompatibility/PHPCompatibility/pull/291)
- :recycle: Miscellaneous other refactoring for improved performance and sniff accuracy. [#291](https://github.com/PHPCompatibility/PHPCompatibility/pull/291)
- :umbrella: The unit tests for the `RemovedExtensions` sniff now verify that the correct alternative extension is being suggested. [#291](https://github.com/PHPCompatibility/PHPCompatibility/pull/291)

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.0.8] - 2016-10-31 - :ghost: Spooky! :jack_o_lantern:

See all related issues and PRs in the [7.0.8 milestone].

### Added
- :star2: New `ForbiddenNamesAsDeclared` sniff: detection of the [other reserved keywords](http://php.net/manual/en/reserved.other-reserved-words.php) which are reserved as of PHP 7.0 (or higher). [#287](https://github.com/PHPCompatibility/PHPCompatibility/pull/287). Fixes [#115](https://github.com/PHPCompatibility/PHPCompatibility/issues/115).
    These were previously sniffed for by the `ForbiddenNames` and `ForbiddenNamesAsInvokedFunctions` sniffs causing false positives as the rules for their reservation are different from the rules for "normal" [reserved keywords](http://php.net/manual/en/reserved.keywords.php).
- :star: New `inUseScope()` utility method to the `PHPCompatibility_Sniff` class which handles PHPCS cross-version compatibility when determining the scope of a `use` statement. [#271](https://github.com/PHPCompatibility/PHPCompatibility/pull/271).
- :umbrella: More unit tests for the `ForbiddenNames` sniff. [#271](https://github.com/PHPCompatibility/PHPCompatibility/pull/271).

### Changed
- :pushpin: _Deprecated_ functionality should throw a `warning`. _Removed_ or otherwise unavailable functionality should throw an `error`. This distinction was previously not consistently applied everywhere. [#286](https://github.com/PHPCompatibility/PHPCompatibility/pull/286)
    This change affects the following sniffs:
    * `DeprecatedPHP4StyleConstructors` will now throw a `warning` instead of an `error` for deprecated PHP4 style class constructors.
    * `ForbiddenCallTimePassByReference` will now throw a `warning` if the `testVersion` is `5.3` and an `error` if the `testVersion` if `5.4` or higher.
    * `MbstringReplaceEModifier` will now throw a `warning` instead of an `error` for usage of the deprecated `e` modifier.
    * `PregReplaceEModifier` will now throw a `warning` if the `testVersion` is `5.5` or `5.6` and an `error` if the `testVersion` if `7.0` or higher. Fixes [#290](https://github.com/PHPCompatibility/PHPCompatibility/issues/290).
    * `TernaryOperators` will now throw an `error` when the `testVersion` < `5.3` and the middle part has been omitted.
    * `ValidIntegers` will now throw a `warning` when an invalid binary integer is detected.
- :pencil2: `DeprecatedFunctions` and `DeprecatedIniDirectives` sniffs: minor change in the sniff error message text. Use _"removed"_ rather than the ominous _"forbidden"_. [#285](https://github.com/PHPCompatibility/PHPCompatibility/pull/285)
    Also updated relevant internal variable names and documentation to match.

### Fixed
- :bug: `ForbiddenNames` sniff would throw false positives for `use` statements which changed the visibility of methods in traits. [#271](https://github.com/PHPCompatibility/PHPCompatibility/pull/271).
- :bug: `ForbiddenNames` sniff would not report reserved keywords when used in combination with `use function` or `use const`. [#271](https://github.com/PHPCompatibility/PHPCompatibility/pull/271).
- :bug: `ForbiddenNames` sniff would potentially - unintentionally - skip over tokens, thereby - potentially - not reporting all errors. [#271](https://github.com/PHPCompatibility/PHPCompatibility/pull/271).
- :wrench: Composer config: `prefer-stable` should be a root element of the json file. Fixes [#277](https://github.com/PHPCompatibility/PHPCompatibility/issues/277).

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.0.7] - 2016-10-20

See all related issues and PRs in the [7.0.7 milestone].

### Added
- :star2: New `ForbiddenBreakContinueOutsideLoop` sniff: verify that `break`/`continue` is not used outside of a loop structure. This will cause fatal errors since PHP 7.0. [#278](https://github.com/PHPCompatibility/PHPCompatibility/pull/278). Fixes [#275](https://github.com/PHPCompatibility/PHPCompatibility/issues/275)
- :star2: New `NewConstVisibility` sniff: detect visibility indicators for `class` and `interface` constants as introduced in PHP 7.1. [#280](https://github.com/PHPCompatibility/PHPCompatibility/pull/280). Fixes [#249](https://github.com/PHPCompatibility/PHPCompatibility/issues/249)
- :star2: New `NewHashAlgorithms` sniff to check used hash algorithms against the PHP version in which they were introduced. [#242](https://github.com/PHPCompatibility/PHPCompatibility/pull/242)
- :star2: New `NewMultiCatch` sniff: detect catch statements catching multiple Exceptions as introduced in PHP 7.1. [#281](https://github.com/PHPCompatibility/PHPCompatibility/pull/281). Fixes [#251](https://github.com/PHPCompatibility/PHPCompatibility/issues/251)
- :star2: New `NewNullableTypes` sniff: detect nullable parameter and return type hints (only supported in PHPCS >= 2.3.4) as introduced in PHP 7.1. [#282](https://github.com/PHPCompatibility/PHPCompatibility/pull/282). Fixes [#247](https://github.com/PHPCompatibility/PHPCompatibility/issues/247)
- :star: `DeprecatedIniDirectives` sniff: recognize PHP 7.1 removed `session` ini directives. [#256](https://github.com/PHPCompatibility/PHPCompatibility/pull/256)
- :star: `NewFunctions` sniff: recognize new `socket_export_stream()` function as introduced in PHP 7.0.7. [#264](https://github.com/PHPCompatibility/PHPCompatibility/pull/264)
- :star: `NewFunctions` sniff: recognize new `curl_...()`, `is_iterable()`, `pcntl_async_signals()`, `session_create_id()`, `session_gc()` functions as introduced in PHP 7.1. [#273](https://github.com/PHPCompatibility/PHPCompatibility/pull/273)
- :star: `NewFunctionParameters` sniff: recognize new OpenSSL function parameters as introduced in PHP 7.1. [#258](https://github.com/PHPCompatibility/PHPCompatibility/pull/258)
- :star: `NewIniDirectives` sniff: recognize new `session` ini directives as introduced in PHP 7.1. [#259](https://github.com/PHPCompatibility/PHPCompatibility/pull/259)
- :star: `NewScalarReturnTypeDeclarations` sniff: recognize PHP 7.1 `void` return type hint. [#250](https://github.com/PHPCompatibility/PHPCompatibility/pull/250)
- :star: `NewScalarTypeDeclarations` sniff: recognize PHP 7.1 `iterable` type hint. [#255](https://github.com/PHPCompatibility/PHPCompatibility/pull/255)
- :star: Recognize the PHP 7.1 deprecated `mcrypt` functionality in the `RemovedExtensions`, `DeprecatedFunctions` and `DeprecatedIniDirectives` sniffs. [#257](https://github.com/PHPCompatibility/PHPCompatibility/pull/257)

### Changed
- :pushpin: `LongArrays` sniff used to only throw `warning`s. It will now throw `error`s for PHP versions in which the long superglobals have been removed. [#270](https://github.com/PHPCompatibility/PHPCompatibility/pull/270)
- :pushpin: The `NewIniDirectives` sniff used to always throw an `warning`. Now it will throw an `error` when a new ini directive is used in combination with `ini_set()`. [#246](https://github.com/PHPCompatibility/PHPCompatibility/pull/246).
- :pushpin: `RemovedHashAlgorithms` sniff: also recognize removed algorithms when used with the PHP 5.5+ `hash_pbkdf2()` function. [#240](https://github.com/PHPCompatibility/PHPCompatibility/pull/240)
- :pushpin: Properly recognize nullable type hints in the `getMethodParameters()` utility method. [#282](https://github.com/PHPCompatibility/PHPCompatibility/pull/282)
- :pencil2: `DeprecatedPHP4StyleConstructors` sniff: minor error message text fix. [#236](https://github.com/PHPCompatibility/PHPCompatibility/pull/236)
- :pencil2: `NewIniDirectives` sniff: improved precision for the introduction version numbers being reported. [#246](https://github.com/PHPCompatibility/PHPCompatibility/pull/246)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#238](https://github.com/PHPCompatibility/PHPCompatibility/pull/238), [#244](https://github.com/PHPCompatibility/PHPCompatibility/pull/244), [#240](https://github.com/PHPCompatibility/PHPCompatibility/pull/240), [#276](https://github.com/PHPCompatibility/PHPCompatibility/pull/276)
- :umbrella: Re-activate the unit tests for the `NewScalarReturnTypeDeclarations` sniff. [#250](https://github.com/PHPCompatibility/PHPCompatibility/pull/250)

### Fixed
- :bug: The `DeprecatedPHP4StyleConstructors` sniff would not report errors when the case of the class name and the PHP4 constructor function name did not match. [#236](https://github.com/PHPCompatibility/PHPCompatibility/pull/236)
- :bug: `LongArrays` sniff would report false positives for class properties shadowing removed PHP superglobals. [#270](https://github.com/PHPCompatibility/PHPCompatibility/pull/270). Fixes [#268](https://github.com/PHPCompatibility/PHPCompatibility/issues/268).
- :bug: The `NewClasses` sniff would not report errors when the case of the class name used and "official" class name did not match. [#237](https://github.com/PHPCompatibility/PHPCompatibility/pull/237)
- :bug: The `NewIniDirectives` sniff would report violations against the PHP version in which the ini directive was introduced. This should be the version below it. [#246](https://github.com/PHPCompatibility/PHPCompatibility/pull/246)
- :bug: `PregReplaceEModifier` sniff would report false positives for compound regex parameters with different quote types. [#266](https://github.com/PHPCompatibility/PHPCompatibility/pull/266). Fixes [#265](https://github.com/PHPCompatibility/PHPCompatibility/issues/265).
- :bug: `RemovedGlobalVariables` sniff would report false positives for lowercase/mixed cased variables shadowing superglobals. [#245](https://github.com/PHPCompatibility/PHPCompatibility/pull/245).
- :bug: The `RemovedHashAlgorithms` sniff would not report errors when the case of the hash function name used and "official" class name did not match. [#240](https://github.com/PHPCompatibility/PHPCompatibility/pull/240)
- :bug: The `ShortArray` sniff would report all violations on the line of the PHP open tag, not on the lines of the short array open/close tags. [#238](https://github.com/PHPCompatibility/PHPCompatibility/pull/238)

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.0.6] - 2016-09-23

See all related issues and PRs in the [7.0.6 milestone].

### Added
- :star: New `stripQuotes()` utility method in the `PHPCompatibility_Sniff` base class to strip quotes which surround text strings in a consistent manner. [#224](https://github.com/PHPCompatibility/PHPCompatibility/pull/224)
- :books: Readme: Add _PHP Version Support_ section. [#225](https://github.com/PHPCompatibility/PHPCompatibility/pull/225)

### Changed
- :pushpin: The `ForbiddenCallTimePassByReference` sniff will now also report the deprecation as of PHP 5.3, not just its removal as of PHP 5.4. [#203](https://github.com/PHPCompatibility/PHPCompatibility/pull/203)
- :pushpin: The `NewFunctionArrayDereferencing` sniff will now also check _method_ calls for array dereferencing, not just function calls. [#229](https://github.com/PHPCompatibility/PHPCompatibility/pull/229). Fixes [#227](https://github.com/PHPCompatibility/PHPCompatibility/issues/227).
- :pencil2: The `NewExecutionDirectives` sniff will now throw `warning`s instead of `error`s for invalid values encountered in execution directives. [#223](https://github.com/PHPCompatibility/PHPCompatibility/pull/223)
- :pencil2: Minor miscellaneous fixes. [#231](https://github.com/PHPCompatibility/PHPCompatibility/pull/231)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#219](https://github.com/PHPCompatibility/PHPCompatibility/pull/219), [#203](https://github.com/PHPCompatibility/PHPCompatibility/pull/203)
- :recycle: Defer to upstream `findImplementedInterfaceNames()` utility method when it exists. [#222](https://github.com/PHPCompatibility/PHPCompatibility/pull/222)
- :wrench: Exclude the test files from analysis by Scrutinizer CI. [#230](https://github.com/PHPCompatibility/PHPCompatibility/pull/230)

### Removed
- :no_entry_sign: Some redundant code. [#232](https://github.com/PHPCompatibility/PHPCompatibility/pull/232)

### Fixed
- :bug: The `EmptyNonVariable` sniff would throw false positives for variable variables and for array access with a (partially) variable array index. [#212](https://github.com/PHPCompatibility/PHPCompatibility/pull/212). Fixes [#210](https://github.com/PHPCompatibility/PHPCompatibility/issues/210).
- :bug: The `NewFunctionArrayDereferencing` sniff would throw false positives for lines of code containing both a function call as well as square brackets, even when they were unrelated. [#228](https://github.com/PHPCompatibility/PHPCompatibility/pull/228). Fixes [#226](https://github.com/PHPCompatibility/PHPCompatibility/issues/226).
- :bug: `ParameterShadowSuperGlobals` sniff would report false positives for lowercase/mixed cased variables shadowing superglobals. [#218](https://github.com/PHPCompatibility/PHPCompatibility/pull/218). Fixes [#214](https://github.com/PHPCompatibility/PHPCompatibility/issues/214).
- :bug: The `determineNamespace()` utility method now accounts properly for namespaces within scoped `declare()` statements. [#221](https://github.com/PHPCompatibility/PHPCompatibility/pull/221)
- :books: Readme: Logo alignment in the Credits section. [#233](https://github.com/PHPCompatibility/PHPCompatibility/pull/233)

### Credits
Thanks go out to [Jason Stallings], [Juliette Reinders Folmer] and [Mark Clements] for their contributions to this version. :clap:


## [7.0.5] - 2016-09-08

See all related issues and PRs in the [7.0.5 milestone].

### Added
- :star2: New `MbstringReplaceEModifier` sniff to detect the use of the PHP 7.1 deprecated `e` modifier in Mbstring regex functions. [#202](https://github.com/PHPCompatibility/PHPCompatibility/pull/202)
- :star: The `ForbiddenBreakContinueVariableArguments` sniff will now also report on `break 0`/`continue 0` which is not allowed since PHP 5.4. [#209](https://github.com/PHPCompatibility/PHPCompatibility/pull/209)
- :star: New `getFunctionCallParameters()`, `getFunctionCallParameter()` utility methods in the `PHPCompatibility_Sniff` base class. [#170](https://github.com/PHPCompatibility/PHPCompatibility/pull/170)
- :star: New `tokenHasScope()` utility method in the `PHPCompatibility_Sniff` base class. [#189](https://github.com/PHPCompatibility/PHPCompatibility/pull/189)
- :umbrella: Unit test for `goto` and `callable` detection and some other miscellanous extra unit tests for the `NewKeywords` sniff. [#189](https://github.com/PHPCompatibility/PHPCompatibility/pull/189)
- :books: Readme: Information for sniff developers about running unit tests for _other_ sniff libraries using the PHPCS native test framework without running into conflicts with the PHPCompatibility specific unit test framework. [#217](https://github.com/PHPCompatibility/PHPCompatibility/pull/217)

### Changed
- :pushpin: The `ForbiddenNames` sniff will now also check interface declarations for usage of reserved keywords. [#200](https://github.com/PHPCompatibility/PHPCompatibility/pull/200)
- :pushpin: `PregReplaceEModifier` sniff: improved handling of regexes build up of a combination of variables, function calls and/or text strings. [#201](https://github.com/PHPCompatibility/PHPCompatibility/pull/201)
- :rewind: The `NewKeywords` sniff will now also correctly recognize new keywords when used in combination with older PHPCS versions and/or run on older PHP versions. [#189](https://github.com/PHPCompatibility/PHPCompatibility/pull/189)
- :pencil2: `PregReplaceEModifier` sniff: minor improvement to the error message text. [#201](https://github.com/PHPCompatibility/PHPCompatibility/pull/201)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#170](https://github.com/PHPCompatibility/PHPCompatibility/pull/170), [#188](https://github.com/PHPCompatibility/PHPCompatibility/pull/188), [#189](https://github.com/PHPCompatibility/PHPCompatibility/pull/189), [#199](https://github.com/PHPCompatibility/PHPCompatibility/pull/199), [#200](https://github.com/PHPCompatibility/PHPCompatibility/pull/200), [#201](https://github.com/PHPCompatibility/PHPCompatibility/pull/201), [#208](https://github.com/PHPCompatibility/PHPCompatibility/pull/208)
- :wrench: The unit tests for the utility methods have been moved to their own subdirectory within `Tests`. [#215](https://github.com/PHPCompatibility/PHPCompatibility/pull/215)
- :green_heart: The sniffs are now also tested against PHP 7.1 for consistent results. [#216](https://github.com/PHPCompatibility/PHPCompatibility/pull/216)

### Removed
- :no_entry_sign: Some redundant code. [26d0b6](https://github.com/PHPCompatibility/PHPCompatibility/commit/26d0b6cf0921f75d93a4faaf09c390f386dde9ff) and [841616](https://github.com/PHPCompatibility/PHPCompatibility/commit/8416162ea81f4067226324f5948f4a50f7958a9b)

### Fixed
- :bug: `ConstantArraysUsingDefine` sniff: the version check logic was reversed causing the error not to be reported in certain circumstances. [#199](https://github.com/PHPCompatibility/PHPCompatibility/pull/199)
- :bug: The `DeprecatedIniDirectives` and `NewIniDirectives` sniffs could potentially trigger on the ini value instead of the ini directive name. [#170](https://github.com/PHPCompatibility/PHPCompatibility/pull/170)
- :bug: `ForbiddenNames` sniff: Reserved keywords when used as the name of a constant declared using `define()` would always be reported independently of the `testVersion` set. [#200](https://github.com/PHPCompatibility/PHPCompatibility/pull/200)
- :bug: `PregReplaceEModifier` sniff would not report errors when the function name used was not in lowercase. [#201](https://github.com/PHPCompatibility/PHPCompatibility/pull/201)
- :bug: `TernaryOperators` sniff: the version check logic was reversed causing the error not to be reported in certain circumstances. [#188](https://github.com/PHPCompatibility/PHPCompatibility/pull/188)
- :bug: The `getFQClassNameFromNewToken()` and `getFQClassNameFromDoubleColonToken()` utility methods would get confused when the class name was a variable instead of being hard-coded, resulting in a PHP warning being thown. [#206](https://github.com/PHPCompatibility/PHPCompatibility/pull/206). Fixes [#205](https://github.com/PHPCompatibility/PHPCompatibility/issues/205).
- :bug: The `getFunctionCallParameters()` utility method would incorrectly identify an extra parameter if the last parameter passed to a function would have an - unnecessary - comma after it. The `getFunctionCallParameters()` utility method also did not handle parameters passed as short arrays correctly. [#213](https://github.com/PHPCompatibility/PHPCompatibility/pull/213). Fixes [#211](https://github.com/PHPCompatibility/PHPCompatibility/issues/211).
- :umbrella: Unit tests for the `NewFunctionArrayDereferencing` sniff were not being run due to a naming error. [#208](https://github.com/PHPCompatibility/PHPCompatibility/pull/208)
- :books: Readme: Information about setting the `testVersion` from a custom ruleset was incorrect. [#204](https://github.com/PHPCompatibility/PHPCompatibility/pull/204)
- :wrench: Path to PHPCS in the unit tests breaking for non-Composer installs. [#198](https://github.com/PHPCompatibility/PHPCompatibility/pull/198)

### Credits
Thanks go out to [Juliette Reinders Folmer] and [Yoshiaki Yoshida] for their contributions to this version. :clap:


## [7.0.4] - 2016-08-20

See all related issues and PRs in the [7.0.4 milestone].

### Added
- :star2: New `EmptyNonVariable` sniff: detection of empty being used on non-variables for PHP < 5.5. [#187](https://github.com/PHPCompatibility/PHPCompatibility/pull/187)
- :star2: New `NewMagicMethods` sniff: detection of declaration of magic methods before the method became "magic". Includes a check for the changed behaviour for the `__toString()` magic method in PHP 5.2. [#176](https://github.com/PHPCompatibility/PHPCompatibility/pull/176). Fixes [#64](https://github.com/PHPCompatibility/PHPCompatibility/issues/64).
- :star2: New `RemovedAlternativePHPTags` sniff: detection of ASP and script open tags for which support was removed in PHP 7.0. [#184](https://github.com/PHPCompatibility/PHPCompatibility/pull/184), [#193](https://github.com/PHPCompatibility/PHPCompatibility/pull/193). Fixes [#127](https://github.com/PHPCompatibility/PHPCompatibility/issues/127).
- :star: `NonStaticMagicMethods` sniff: detection of the `__callStatic()`, `__sleep()`, `__toString()` and `__set_state()` magic methods.
- :green_heart: Lint all non-test case files for syntax errors during the build testing by Travis. [#192](https://github.com/PHPCompatibility/PHPCompatibility/pull/192)

### Changed
- :pushpin: `NonStaticMagicMethods` sniff: will now also sniff `trait`s for magic methods. [#174](https://github.com/PHPCompatibility/PHPCompatibility/pull/174)
- :pushpin: `NonStaticMagicMethods` sniff: will now also check for magic methods which should be declared as `static`. [#174](https://github.com/PHPCompatibility/PHPCompatibility/pull/174)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#178](https://github.com/PHPCompatibility/PHPCompatibility/pull/178), [#179](https://github.com/PHPCompatibility/PHPCompatibility/pull/179), [#174](https://github.com/PHPCompatibility/PHPCompatibility/pull/174), [#171](https://github.com/PHPCompatibility/PHPCompatibility/pull/171)
- :recycle: The unit test suite now internally caches PHPCS run results in combination with a set `testVersion` to speed up the running of the unit tests. These are now ~3 x faster. [#148](https://github.com/PHPCompatibility/PHPCompatibility/pull/148)
- :books: Readme: Minor clarification of the minimum requirements.
- :books: Readme: Advise to use the latest stable version of this repository.
- :wrench: The unit tests can now be run with PHPCS installed in an arbitrary location by passing the location through an environment option. [#191](https://github.com/PHPCompatibility/PHPCompatibility/pull/191).
- :wrench: Improved coveralls configuration and compatibility. [#194](https://github.com/PHPCompatibility/PHPCompatibility/pull/194)
- :green_heart: The sniffs are now also tested against PHP 5.2 for consistent results. Except for namespace, trait and group use related errors, most sniffs work as intended on PHP 5.2. [#196](https://github.com/PHPCompatibility/PHPCompatibility/pull/196)

### Fixed
- :bug: The `ForbiddenBreakContinueVariableArguments` sniff would not report on `break`/`continue` with a closure as an argument. [#171](https://github.com/PHPCompatibility/PHPCompatibility/pull/171)
- :bug: The `ForbiddenNamesAsInvokedFunctions` sniff would not report on reserved keywords which were not lowercase. [#186](https://github.com/PHPCompatibility/PHPCompatibility/pull/186)
- :bug: The `ForbiddenNamesAsInvokedFunctions` sniff would not report on the `goto` and `namespace` keywords when run on PHP 5.2. [#193](https://github.com/PHPCompatibility/PHPCompatibility/pull/193)
- :bug: `NewAnonymousClasses` sniff: the version check logic was reversed causing the error not to be reported in certain circumstances. [#195](https://github.com/PHPCompatibility/PHPCompatibility/pull/195).
- :bug: `NewGroupUseDeclarations` sniff: the version check logic was reversed causing the error not to be reported in certain circumstances. [#190](https://github.com/PHPCompatibility/PHPCompatibility/pull/190).
- :bug: The `NonStaticMagicMethods` sniff would not report on magic methods when the function name as declared was not in the same case as used in the PHP manual. [#174](https://github.com/PHPCompatibility/PHPCompatibility/pull/174)
- :wrench: The unit tests would exit with `0` if PHPCS could not be found. [#191](https://github.com/PHPCompatibility/PHPCompatibility/pull/191)
- :green_heart: The PHPCompatibility library itself was not fully compatible with PHP 5.2. [#193](https://github.com/PHPCompatibility/PHPCompatibility/pull/193)

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.0.3] - 2016-08-18

See all related issues and PRs in the [7.0.3 milestone].

### Added
- :star2: New `InternalInterfaces` sniff: detection of internal PHP interfaces being which should not be implemented by user land classes. [#144](https://github.com/PHPCompatibility/PHPCompatibility/pull/144)
- :star2: New `LateStaticBinding` sniff: detection of PHP 5.3 late static binding. [#177](https://github.com/PHPCompatibility/PHPCompatibility/pull/177)
- :star2: New `NewExecutionDirectives` sniff: verify execution directives set with `declare()`. [#169](https://github.com/PHPCompatibility/PHPCompatibility/pull/169)
- :star2: New `NewInterfaces` sniff: detection of the use of newly introduced PHP native interfaces. This sniff will also detect unsupported methods when a class implements the `Serializable` interface. [#144](https://github.com/PHPCompatibility/PHPCompatibility/pull/144)
- :star2: New `RequiredOptionalFunctionParameters` sniff: detection of missing function parameters which were required in earlier PHP versions only to become optional in later versions. [#165](https://github.com/PHPCompatibility/PHPCompatibility/pull/165)
- :star2: New `ValidIntegers` sniff: detection of binary integers for PHP < 5.4, detection of hexademical numeric strings for which recognition as hex integers was removed in PHP 7.0, detection of invalid binary and octal integers. [#160](https://github.com/PHPCompatibility/PHPCompatibility/pull/160). Fixes [#55](https://github.com/PHPCompatibility/PHPCompatibility/issues/55).
- :star: `DeprecatedExtensions` sniff: detect removal of the `ereg` extension in PHP 7. [#149](https://github.com/PHPCompatibility/PHPCompatibility/pull/149)
- :star: `DeprecatedFunctions` sniff: detection of the PHP 5.0.5 deprecated `php_check_syntax()` and PHP 5.4 deprecated `mysqli_get_cache_stats()` functions. [#155](https://github.com/PHPCompatibility/PHPCompatibility/pull/155).
- :star: `DeprecatedFunctions` sniff: detect deprecation of a number of the `mysqli` functions in PHP 5.3. [#149](https://github.com/PHPCompatibility/PHPCompatibility/pull/149)
- :star: `DeprecatedFunctions` sniff: detect removal of the `call_user_method()`, `ldap_sort()`, `ereg_*()` and `mysql_*()` functions in PHP 7.0. [#149](https://github.com/PHPCompatibility/PHPCompatibility/pull/149)
- :star: `DeprecatedIniDirectives` sniff: detection of a _lot_ more deprecated/removed ini directives. [#146](https://github.com/PHPCompatibility/PHPCompatibility/pull/146)
- :star: `NewFunctionParameters` sniff: detection of a _lot_ more new function parameters. [#164](https://github.com/PHPCompatibility/PHPCompatibility/pull/164)
- :star: `NewFunctions` sniff: detection of numerous extra new functions. [#161](https://github.com/PHPCompatibility/PHPCompatibility/pull/161)
- :star: `NewIniDirectives` sniff: detection of a _lot_ more new ini directives. [#146](https://github.com/PHPCompatibility/PHPCompatibility/pull/146)
- :star: `NewLanguageConstructs` sniff: detection of the PHP 5.6 ellipsis `...` construct. [#175](https://github.com/PHPCompatibility/PHPCompatibility/pull/175)
- :star: `NewScalarTypeDeclarations` sniff: detection of PHP 5.1 `array` and PHP 5.4 `callable` type hints. [#168](https://github.com/PHPCompatibility/PHPCompatibility/pull/168)
- :star: `RemovedFunctionParameters` sniff: detection of a few extra removed function parameters. [#163](https://github.com/PHPCompatibility/PHPCompatibility/pull/163)
- :star: Detection of functions and methods with a double underscore prefix as these are reserved by PHP for future use. The existing upstream `Generic.NamingConventions.CamelCapsFunctionName` sniff is re-used for this with some customization. [#173](https://github.com/PHPCompatibility/PHPCompatibility/pull/173)
- :star: New `getFQClassNameFromNewToken()`, `getFQExtendedClassName()`, `getFQClassNameFromDoubleColonToken()`, `getFQName()`, `isNamespaced()`, `determineNamespace()` and `getDeclaredNamespaceName()` utility methods in the `PHPCompatibility_Sniff` base class for namespace determination. [#162](https://github.com/PHPCompatibility/PHPCompatibility/pull/162)
- :recycle: New `inClassScope()` utility method in the `PHPCompatibility_Sniff` base class. [#168](https://github.com/PHPCompatibility/PHPCompatibility/pull/168)
- :recycle: New `doesFunctionCallHaveParameters()` and `getFunctionCallParameterCount()` utility methods in the `PHPCompatibility_Sniff` base class. [#153](https://github.com/PHPCompatibility/PHPCompatibility/pull/153)
- :umbrella: Unit test for `__halt_compiler()` detection by the `NewKeywords` sniff.
- :umbrella: Unit tests for the `NewFunctions` sniff. [#161](https://github.com/PHPCompatibility/PHPCompatibility/pull/161)
- :umbrella: Unit tests for the `ParameterShadowSuperGlobals` sniff. [#180](https://github.com/PHPCompatibility/PHPCompatibility/pull/180)
- :wrench: Minimal config for Scrutinizer CI. [#145](https://github.com/PHPCompatibility/PHPCompatibility/pull/145).

### Changed
- :pushpin: The `DeprecatedIniDirectives` and the `NewIniDirectives` sniffs will now indicate an alternative ini directive in case the directive has been renamed. [#146](https://github.com/PHPCompatibility/PHPCompatibility/pull/146)
- :pushpin: The `NewClasses` sniff will now also report on new classes being extended by child classes. [#140](https://github.com/PHPCompatibility/PHPCompatibility/pull/140).
- :pushpin: The `NewClasses` sniff will now also report on static use of new classes. [#162](https://github.com/PHPCompatibility/PHPCompatibility/pull/162).
- :pushpin: The `NewScalarTypeDeclarations` sniff will now throw an error on use of type hints pre-PHP 5.0. [#168](https://github.com/PHPCompatibility/PHPCompatibility/pull/168)
- :pushpin: The `NewScalarTypeDeclarations` sniff will now verify type hints used against typical mistakes. [#168](https://github.com/PHPCompatibility/PHPCompatibility/pull/168)
- :pushpin: The `ParameterShadowSuperGlobals` sniff will now do a case-insensitive variable name compare. [#180](https://github.com/PHPCompatibility/PHPCompatibility/pull/180)
- :pushpin: The `RemovedFunctionParameters` sniff will now also report `warning`s on deprecation of function parameters. [#163](https://github.com/PHPCompatibility/PHPCompatibility/pull/163)
- :twisted_rightwards_arrows: The check for `JsonSerializable` has been moved from the `NewClasses` sniff to the `NewInterfaces` sniff. [#162](https://github.com/PHPCompatibility/PHPCompatibility/pull/162)
- :rewind: The `NewLanguageConstructs` sniff will now also recognize new language constructs when used in combination with PHPCS 1.5.x. [#175](https://github.com/PHPCompatibility/PHPCompatibility/pull/175)
- :pencil2: `NewFunctionParameters` sniff: use correct name for the new parameter for the `dirname()` function. [#164](https://github.com/PHPCompatibility/PHPCompatibility/pull/164)
- :pencil2: `NewScalarTypeDeclarations` sniff: minor change in the sniff error message text. [#168](https://github.com/PHPCompatibility/PHPCompatibility/pull/168)
- :pencil2: `RemovedFunctionParameters` sniff: minor change in the sniff error message text. [#163](https://github.com/PHPCompatibility/PHPCompatibility/pull/163)
- :pencil2: The `ParameterShadowSuperGlobals` sniff now extends the `PHPCompatibility_Sniff` class. [#180](https://github.com/PHPCompatibility/PHPCompatibility/pull/180)
- :recycle: Various (minor) refactoring for improved performance and sniff accuracy. [#181](https://github.com/PHPCompatibility/PHPCompatibility/pull/181), [#182](https://github.com/PHPCompatibility/PHPCompatibility/pull/182), [#166](https://github.com/PHPCompatibility/PHPCompatibility/pull/166), [#167](https://github.com/PHPCompatibility/PHPCompatibility/pull/167), [#172](https://github.com/PHPCompatibility/PHPCompatibility/pull/172), [#180](https://github.com/PHPCompatibility/PHPCompatibility/pull/180), [#146](https://github.com/PHPCompatibility/PHPCompatibility/pull/146), [#138](https://github.com/PHPCompatibility/PHPCompatibility/pull/138)
- :recycle: Various refactoring to remove code duplication in the unit tests and add proper test skip notifications where relevant. [#139](https://github.com/PHPCompatibility/PHPCompatibility/pull/139), [#149](https://github.com/PHPCompatibility/PHPCompatibility/pull/149)

### Fixed
- :bug: The `DeprecatedFunctions` sniff was reporting an incorrect deprecation/removal version number for a few functions. [#149](https://github.com/PHPCompatibility/PHPCompatibility/pull/149)
- :bug: The `DeprecatedIniDirectives` sniff was in select cases reporting deprecation of an ini directive prior to removal, while the ini directive was never deprecated prior to its removal. [#146](https://github.com/PHPCompatibility/PHPCompatibility/pull/146)
- :bug: The `DeprecatedPHP4StyleConstructors` sniff would cause false positives for methods with the same name as the class in namespaced classes. [#167](https://github.com/PHPCompatibility/PHPCompatibility/pull/167)
- :bug: The `ForbiddenEmptyListAssignment` sniff did not report errors when there were only comments or parentheses between the list parentheses. [#166](https://github.com/PHPCompatibility/PHPCompatibility/pull/166)
- :bug: The `ForbiddenEmptyListAssignment` sniff will no longer cause false positives during live coding. [#166](https://github.com/PHPCompatibility/PHPCompatibility/pull/166)
- :bug: The `NewClasses` sniff would potentially misidentify namespaced classes as PHP native classes. [#161](https://github.com/PHPCompatibility/PHPCompatibility/pull/162)
- :bug: The `NewFunctions` sniff would fail to identify called functions when the function call was not lowercase. [#161](https://github.com/PHPCompatibility/PHPCompatibility/pull/161)
- :bug: The `NewFunctions` sniff would potentially misidentify namespaced userland functions as new functions. [#161](https://github.com/PHPCompatibility/PHPCompatibility/pull/161)
- :bug: The `NewIniDirectives` sniff was reporting an incorrect introduction version number for a few ini directives. [#146](https://github.com/PHPCompatibility/PHPCompatibility/pull/146)
- :bug: `NewKeywords` sniff: the use of the `const` keyword should only be reported when used outside of a class for PHP < 5.3. [#147](https://github.com/PHPCompatibility/PHPCompatibility/pull/147). Fixes [#129](https://github.com/PHPCompatibility/PHPCompatibility/issues/129).
- :bug: The `RemovedExtensions` sniff was incorrectly reporting a number of extensions as being removed in PHP 5.3 while they were actually removed in PHP 5.1. [#156](https://github.com/PHPCompatibility/PHPCompatibility/pull/156)
- :bug: :recycle: The `NewFunctionParameters` and `RemovedFunctionParameters` now use the new `doesFunctionCallHaveParameters()` and `getFunctionCallParameterCount()` utility methods for improved accuracy in identifying function parameters. This fixes several false positives. [#153](https://github.com/PHPCompatibility/PHPCompatibility/pull/153) Fixes [#120](https://github.com/PHPCompatibility/PHPCompatibility/issues/120), [#151](https://github.com/PHPCompatibility/PHPCompatibility/issues/151), [#152](https://github.com/PHPCompatibility/PHPCompatibility/issues/152).
- :bug: A number of sniffs would return `false` if the examined construct was not found. This could potentially cause race conditions/infinite sniff loops. [#138](https://github.com/PHPCompatibility/PHPCompatibility/pull/138)
- :wrench: The unit tests would fail to run when used in combination with a PEAR install of PHPCS. [#157](https://github.com/PHPCompatibility/PHPCompatibility/pull/157).
- :green_heart: Unit tests failing against PHPCS 2.6.1. [#158](https://github.com/PHPCompatibility/PHPCompatibility/pull/158)
    The unit tests *will* still fail against PHPCS 2.6.2 due to a bug in PHPCS itself. This bug does not affect the running of the sniffs outside of a unit test context.

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.0.2] - 2016-08-03

See all related issues and PRs in the [7.0.2 milestone].

### Added
- :star: `RemovedExtensions` sniff: ability to whitelist userland functions for which the function prefix overlaps with a prefix of a deprecated/removed extension. [#130](https://github.com/PHPCompatibility/PHPCompatibility/pull/130). Fixes [#123](https://github.com/PHPCompatibility/PHPCompatibility/issues/123).
    To use this feature, add the `functionWhitelist` property in your custom ruleset. For more information, see the [README](https://github.com/PHPCompatibility/PHPCompatibility#phpcompatibility-specific-options).

### Changed
- :pencil2: A number of sniffs contained `public` class properties. Within PHPCS, `public` properties can be overruled via a custom ruleset. This was not the intention, so the visibility of these properties has been changed to `protected`. [#135](https://github.com/PHPCompatibility/PHPCompatibility/pull/135)
- :wrench: Composer config: Stable packages are preferred over unstable/dev.
- :pencil2: Ruleset name. [#134](https://github.com/PHPCompatibility/PHPCompatibility/pull/134)

### Credits
Thanks go out to [Juliette Reinders Folmer] for her contributions to this version. :clap:


## [7.0.1] - 2016-08-02

See all related issues and PRs in the [7.0.1 milestone].

### Changed
- :pushpin: The `DeprecatedIniDirectives` sniff used to throw an `error` when a deprecated ini directive was used in combination with `ini_get()`. It will now throw a `warning` instead. [#122](https://github.com/PHPCompatibility/PHPCompatibility/pull/122) Fixes [#119](https://github.com/PHPCompatibility/PHPCompatibility/issues/119).
    Usage of deprecated ini directives in combination with `ini_set()` will still throw an `error`.
- :pushpin: The `PregReplaceEModifier` sniff now also detects the `e` modifier when used with the `preg_filter()` function. While this is undocumented, the `e` modifier was supported by the `preg_filter()` function as well. [#128](https://github.com/PHPCompatibility/PHPCompatibility/pull/128)
- :pencil2: The `RemovedExtensions` sniff contained superfluous deprecation information in the error message. [#131](https://github.com/PHPCompatibility/PHPCompatibility/pull/131)

### Removed
- :wrench: Duplicate builds from the Travis CI build matrix. [#132](https://github.com/PHPCompatibility/PHPCompatibility/pull/132)

### Fixed
- :bug: The `ForbiddenNames` sniff did not allow for the PHP 5.6 `use function ...` and `use const ...` syntax. [#126](https://github.com/PHPCompatibility/PHPCompatibility/pull/126) Fixes [#124](https://github.com/PHPCompatibility/PHPCompatibility/issues/124).
- :bug: The `NewClasses` sniff failed to detect new classes when the class was instantiated without parenthesis, i.e. `new NewClass;`. [#121](https://github.com/PHPCompatibility/PHPCompatibility/pull/121)
- :bug: The `PregReplaceEModifier` sniff failed to detect the `e` modifier when using bracket delimiters for the regex other than the `{}` brackets. [#128](https://github.com/PHPCompatibility/PHPCompatibility/pull/128)
- :green_heart: Unit tests failing against PHPCS 2.6.1.

### Credits
Thanks go out to [Jason Stallings], [Juliette Reinders Folmer] and [Ryan Neufeld] for their contributions to this version. :clap:


## [7.0] - 2016-07-02

See all related issues and PRs in the [7.0 milestone].

### Added
- :zap: Ability to specify a range of PHP versions against which to test your code base for compatibility, i.e. `--runtime-set testVersion 5.0-5.4` will now test your code for compatibility with PHP 5.0 up to PHP 5.4. [#99](https://github.com/PHPCompatibility/PHPCompatibility/pull/99)
- :star2: New `NewFunctionArrayDereferencing` sniff to detect function array dereferencing as introduced in PHP 5.4. Fixes [#52](https://github.com/PHPCompatibility/PHPCompatibility/issues/52).
- :star2: New `ShortArray` sniff to detect short array syntax as introduced in PHP 5.4. [#97](https://github.com/PHPCompatibility/PHPCompatibility/pull/97). Fixes [#47](https://github.com/PHPCompatibility/PHPCompatibility/issues/47).
- :star2: New `TernaryOperators` sniff to detect ternaries without the middle part (`elvis` operator) as introduced in PHP 5.3. [#101](https://github.com/PHPCompatibility/PHPCompatibility/pull/101), [#103](https://github.com/PHPCompatibility/PHPCompatibility/pull/103). Fixes [#49](https://github.com/PHPCompatibility/PHPCompatibility/issues/49).
- :star2: New `ConstantArraysUsingDefine` sniff to detect constants declared using `define()` being assigned an `array` value which was not allowed prior to PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `DeprecatedPHP4StyleConstructors` sniff to detect PHP 4 style class constructor methods which are deprecated as of PHP 7. [#109](https://github.com/PHPCompatibility/PHPCompatibility/pull/109).
- :star2: New `ForbiddenEmptyListAssignment` sniff to detect empty list() assignments which have been removed in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `ForbiddenFunctionParametersWithSameName` sniff to detect functions declared with multiple same-named parameters which is no longer accepted since PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `ForbiddenGlobalVariableVariable` sniff to detect variable variables being made `global` which is not allowed since PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `ForbiddenNegativeBitshift` sniff to detect bitwise shifts by negative number which will throw an ArithmeticError in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `ForbiddenSwitchWithMultipleDefaultBlocks` sniff to detect switch statements with multiple default blocks which is not allowed since PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `NewAnonymousClasses` sniff to detect anonymous classes as introduced in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `NewClosure` sniff to detect anonymous functions as introduced in PHP 5.3. Fixes [#35](https://github.com/PHPCompatibility/PHPCompatibility/issues/35)
- :star2: New `NewFunctionParameters` sniff to detect use of new parameters in build-in PHP functions. Initially only sniffing for the new PHP 7.0 function parameters and the new PHP 5.3+ `before_needle` parameter for the `strstr()` function. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110), [#112](https://github.com/PHPCompatibility/PHPCompatibility/pull/112). Fixes [#27](https://github.com/PHPCompatibility/PHPCompatibility/issues/27).
- :star2: New `NewGroupUseDeclarations` sniff to detect group use declarations as introduced in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `NewScalarReturnTypeDeclarations` sniff to detect scalar return type hints as introduced in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `NewScalarTypeDeclarations` sniff to detect scalar function parameter type hints as introduced in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `RemovedFunctionParameters` sniff to detect use of removed parameters in build-in PHP functions. Initially only sniffing for the function parameters removed in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star2: New `RemovedGlobalVariables` sniff to detect the PHP 7.0 removed `$HTTP_RAW_POST_DATA` superglobal. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `DeprecatedFunctions` sniff: detection of the PHP 5.4 deprecated OCI8 functions. [#93](https://github.com/PHPCompatibility/PHPCompatibility/pull/93)
- :star: `ForbiddenNamesAsInvokedFunctions` sniff: recognize PHP 5.5 `finally` as a reserved keywords when invoked as a function. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `NewKeywords` sniff: detection of the use of the PHP 5.1+ `__halt_compiler` keyword. Fixes [#50](https://github.com/PHPCompatibility/PHPCompatibility/issues/50).
- :star: `NewKeywords` sniff: detection of the PHP 5.3+ `nowdoc` syntax. Fixes [#48](https://github.com/PHPCompatibility/PHPCompatibility/issues/48).
- :star: `NewKeywords` sniff: detection of the use of the `const` keyword outside of a class for PHP < 5.3. Fixes [#50](https://github.com/PHPCompatibility/PHPCompatibility/issues/50).
- :star: `DeprecatedFunctions` sniff: recognize PHP 7.0 deprecated and removed functions. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `DeprecatedIniDirectives` sniff: recognize PHP 7.0 removed ini directives. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `ForbiddenNamesAsInvokedFunctions` sniff: recognize new PHP 7.0 reserved keywords when invoked as functions. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `ForbiddenNames` sniff: recognize new PHP 7.0 reserved keywords. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `NewFunctions` sniff: recognize new functions as introduced in PHP 7.0. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `NewLanguageConstructs` sniff: recognize new PHP 7.0 `<=>` "spaceship" and `??` null coalescing operators. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :star: `RemovedExtensions` sniff: recognize PHP 7.0 removed `ereg`, `mssql`, `mysql` and `sybase_ct` extensions. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :umbrella: Additional unit tests for the `NewLanguageConstructs` sniff. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :books: Readme: New section containing information about the use of the `testVersion` config variable.
- :books: Readme: Sponsor credits.

### Changed
- :pushpin: The `DeprecatedIniDirectives` sniff used to always throw an `warning`. Now it will throw an `error` when a removed ini directive is used. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110).
- :pushpin: The `DeprecatedNewReference` sniff will now throw an error when the `testVersion` includes PHP 7.0 or higher. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :pushpin: The `ForbiddenNames` sniff now supports detection of reserved keywords when used in combination with PHP 7 anonymous classes. [#108](https://github.com/PHPCompatibility/PHPCompatibility/pull/108), [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110).
- :pushpin: The `PregReplaceEModifier` sniff will now throw an error when the `testVersion` includes PHP 7.0 or higher. [#110](https://github.com/PHPCompatibility/PHPCompatibility/pull/110)
- :pencil2: `NewKeywords` sniff: clarified the error message text for the `use` keyword. Fixes [#46](https://github.com/PHPCompatibility/PHPCompatibility/issues/46).
- :recycle: Minor refactor of the `testVersion` related utility functions. [#98](https://github.com/PHPCompatibility/PHPCompatibility/pull/98)
- :wrench: Add autoload to the `composer.json` file. [#96](https://github.com/PHPCompatibility/PHPCompatibility/pull/96) Fixes [#67](https://github.com/PHPCompatibility/PHPCompatibility/issues/67).
- :wrench: Minor other updates to the `composer.json` file. [#75](https://github.com/PHPCompatibility/PHPCompatibility/pull/75)
- :wrench: Improved creation of the code coverage reports needed by coveralls via Travis.
- :green_heart: The sniffs are now also tested against PHP 7.0 for consistent results.

### Fixed
- :bug: The `ForbiddenCallTimePassByReference` sniff was throwing `Undefined index` notices when used in combination with PHPCS 2.2.0. [#100](https://github.com/PHPCompatibility/PHPCompatibility/pull/100). Fixes [#42](https://github.com/PHPCompatibility/PHPCompatibility/issues/42).
- :bug: The `ForbiddenNamesAsInvokedFunctions` sniff would incorrectly throw an error if the `throw` keyword was used with parenthesis. Fixes [#118](https://github.com/PHPCompatibility/PHPCompatibility/issues/118).
- :bug: The `PregReplaceEModifier` sniff incorrectly identified `e`'s in the pattern as the `e` modifier when using `{}` bracket delimiters for the regex. [#94](https://github.com/PHPCompatibility/PHPCompatibility/pull/94)
- :bug: The `RemovedExtensions` sniff was throwing an `error` instead of a `warning` for deprecated, but not (yet) removed extensions. Fixes [#62](https://github.com/PHPCompatibility/PHPCompatibility/issues/62).

### Credits
Thanks go out to AlexMiroshnikov, [Chris Abernethy], [dgudgeon], [djaenecke], [Eugene Maslovich], [Ken Guest], Koen Eelen, [Komarov Alexey], [Mark Clements] and [Remko van Bezooijen] for their contributions to this version. :clap:


## [5.6] - 2015-09-14

See all related issues and PRs in the [5.6 milestone].

### Added
- :star2: New: `NewLanguageConstructs` sniff. The initial version of this sniff checks for the PHP 5.6 `**` power operator and the `**=` power assignment operator. [#87](https://github.com/PHPCompatibility/PHPCompatibility/pull/87). Fixes [#60](https://github.com/PHPCompatibility/PHPCompatibility/issues/60).
- :star2: New: `ParameterShadowSuperGlobals` sniff which covers the PHP 5.4 change _Parameter names that shadow super globals now cause a fatal error.`_. [#74](https://github.com/PHPCompatibility/PHPCompatibility/pull/74)
- :star2: New: `PregReplaceEModifier` sniff which detects usage of the `e` modifier in literal regular expressions used with `preg_replace()`. The `e` modifier will not (yet) be detected when the regex passed is a variable or constant. [#81](https://github.com/PHPCompatibility/PHPCompatibility/pull/81), [#84](https://github.com/PHPCompatibility/PHPCompatibility/pull/84). Fixes [#71](https://github.com/PHPCompatibility/PHPCompatibility/issues/71), [#83](https://github.com/PHPCompatibility/PHPCompatibility/issues/83).
- :star: `DeprecatedIniDirectives` sniff: PHP 5.6 deprecated ini directives.
- :star: `NewKeywords` sniff: detection of the `goto` keyword introduced in PHP 5.3 and the `callable` keyword introduced in PHP 5.4. [#57](https://github.com/PHPCompatibility/PHPCompatibility/pull/57)
- :recycle: `PHPCompatibility_Sniff` base class initially containing the `supportsAbove()` and `supportsBelow()` utility methods. (Nearly) All sniffs now extend this base class and use these methods to determine whether or not violations should be reported for a set `testVersion`. [#77](https://github.com/PHPCompatibility/PHPCompatibility/pull/77)
- :books: Readme: Composer installation instructions. [#32](https://github.com/PHPCompatibility/PHPCompatibility/pull/32), [#61](https://github.com/PHPCompatibility/PHPCompatibility/pull/61)
- :wrench: `.gitignore` to ignore vendor and IDE related directories. [#78](https://github.com/PHPCompatibility/PHPCompatibility/pull/78)
- :green_heart: Code coverage checking via coveralls.

### Changed
- :twisted_rightwards_arrows: The check for the `\` namespace separator has been moved from the `NewKeywords` sniff to the `NewLanguageConstructs` sniff. [#88](https://github.com/PHPCompatibility/PHPCompatibility/pull/88)
- :pencil2: `DeprecatedIniDirectives` sniff: minor change in the sniff error message text.
- :pencil2: `DeprecatedFunctions` sniff: minor change in the sniff error message text.
- :wrench: Minor updates to the `composer.json` file. [#31](https://github.com/PHPCompatibility/PHPCompatibility/pull/31), [34](https://github.com/PHPCompatibility/PHPCompatibility/pull/34), [#70](https://github.com/PHPCompatibility/PHPCompatibility/pull/70)
- :wrench: Tests: The unit tests can now be run without configuration.
- :wrench: Tests: Skipped unit tests will now be annotated as such. [#85](https://github.com/PHPCompatibility/PHPCompatibility/pull/85)
- :green_heart: The sniffs are now also tested against PHP 5.6 for consistent results.
- :green_heart: The sniffs are now also tested against PHPCS 2.0+.
- :green_heart: The sniffs are now tested using the new container-based infrastructure in Travis CI. [#37](https://github.com/PHPCompatibility/PHPCompatibility/pull/37)

### Fixed
- :bug: The `ForbiddenCallTimePassByReference` sniff was throwing false positives when a bitwise and `&` was used in combination with class constants and class properties within function calls. [#44](https://github.com/PHPCompatibility/PHPCompatibility/pull/44). Fixes [#39](https://github.com/PHPCompatibility/PHPCompatibility/issues/39).
- :bug: The `ForbiddenNamesAsInvokedFunctions` sniff was throwing false positives in certain cases when a comment separated a `try` from the `catch` block. [#29](https://github.com/PHPCompatibility/PHPCompatibility/pull/29)
- :bug: The `ForbiddenNamesAsInvokedFunctions` sniff was incorrectly reporting `instanceof` as being introduced in PHP 5.4 while it has been around since PHP 5.0. [#80](https://github.com/PHPCompatibility/PHPCompatibility/pull/80)
- :white_check_mark: Compatibility with PHPCS 2.0 - 2.3. [#63](https://github.com/PHPCompatibility/PHPCompatibility/pull/63), [#65](https://github.com/PHPCompatibility/PHPCompatibility/pull/65)

### Credits
Thanks go out to Daniel Jänecke, [Declan Kelly], [Dominic], [Jaap van Otterdijk], [Marin Crnkovic], [Mark Clements], [Nick Pack], [Oliver Klee], [Rowan Collins] and [Sam Van der Borght] for their contributions to this version. :clap:


## 5.5 - 2014-04-04

First tagged release.

See all related issues and PRs in the [5.5 milestone].



[Unreleased]: https://github.com/PHPCompatibility/PHPCompatibility/compare/master...HEAD
[9.3.5]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.3.4...9.3.5
[9.3.4]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.3.3...9.3.4
[9.3.3]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.3.2...9.3.3
[9.3.2]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.3.1...9.3.2
[9.3.1]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.3.0...9.3.1
[9.3.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.2.0...9.3.0
[9.2.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.1.1...9.2.0
[9.1.1]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.1.0...9.1.1
[9.1.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/9.0.0...9.1.0
[9.0.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/8.2.0...9.0.0
[8.2.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/8.1.0...8.2.0
[8.1.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/8.0.1...8.1.0
[8.0.1]: https://github.com/PHPCompatibility/PHPCompatibility/compare/8.0.0...8.0.1
[8.0.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.1.5...8.0.0
[7.1.5]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.1.4...7.1.5
[7.1.4]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.1.3...7.1.4
[7.1.3]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.1.2...7.1.3
[7.1.2]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.1.1...7.1.2
[7.1.1]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.1.0...7.1.1
[7.1.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.8...7.1.0
[7.0.8]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.7...7.0.8
[7.0.7]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.6...7.0.7
[7.0.6]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.5...7.0.6
[7.0.5]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.4...7.0.5
[7.0.4]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.3...7.0.4
[7.0.3]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.2...7.0.3
[7.0.2]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0.1...7.0.2
[7.0.1]: https://github.com/PHPCompatibility/PHPCompatibility/compare/7.0...7.0.1
[7.0]: https://github.com/PHPCompatibility/PHPCompatibility/compare/5.6...7.0
[5.6]: https://github.com/PHPCompatibility/PHPCompatibility/compare/5.5...5.6

[9.3.5 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/34
[9.3.4 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/33
[9.3.3 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/32
[9.3.2 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/31
[9.3.1 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/30
[9.3.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/29
[9.2.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/28
[9.1.1 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/27
[9.1.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/25
[9.0.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/24
[8.2.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/22
[8.1.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/21
[8.0.1 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/20
[8.0.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/19
[7.1.5 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/17
[7.1.4 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/15
[7.1.3 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/14
[7.1.2 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/13
[7.1.1 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/12
[7.1.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/11
[7.0.8 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/10
[7.0.7 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/9
[7.0.6 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/8
[7.0.5 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/7
[7.0.4 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/6
[7.0.3 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/5
[7.0.2 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/4
[7.0.1 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/3
[7.0 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/2
[5.6 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/1
[5.5 milestone]: https://github.com/PHPCompatibility/PHPCompatibility/milestone/16

[Arthur Edamov]: https://github.com/edamov
[Chris Abernethy]: https://github.com/cabernet-zerve
[Declan Kelly]: https://github.com/declank
[dgudgeon]: https://github.com/dgudgeon
[djaenecke]: https://github.com/djaenecke
[Dominic]: https://github.com/dol
[Eugene Maslovich]: https://github.com/ehpc
[Gary Jones]: https://github.com/GaryJones
[Jaap van Otterdijk]: https://github.com/jaapio
[Jason Stallings]: https://github.com/octalmage
[Jonathan Champ]: https://github.com/jrchamp
[Jonathan Van Belle]: https://github.com/Grummfy
[Juliette Reinders Folmer]: https://github.com/jrfnl
[Ken Guest]: https://github.com/kenguest
[Komarov Alexey]: https://github.com/erdraug
[Marin Crnkovic]: https://github.com/anorgan
[Mark Clements]: https://github.com/MarkMaldaba
[Michael Babker]: https://github.com/mbabker
[Nick Pack]: https://github.com/nickpack
[Nikhil]: https://github.com/Nikschavan
[Oliver Klee]: https://github.com/oliverklee
[Remko van Bezooijen]: https://github.com/emkookmer
[Rowan Collins]: https://github.com/IMSoP
[Ryan Neufeld]: https://github.com/ryanneufeld
[Sam Van der Borght]: https://github.com/samvdb
[Sergii Bondarenko]: https://github.com/BR0kEN-
[Tadas Juozapaitis]: https://github.com/kasp3r
[Tim Millwood]: https://github.com/timmillwood
[William Entriken]: https://github.com/fulldecent
[Yılmaz]: https://github.com/edigu
[Yoshiaki Yoshida]: https://github.com/kakakakakku
