# Security Policy

Full details of the WordPress Security Policy can be found on [HackerOne](https://hackerone.com/wordpress). You can also read more in a detailed white paper about [WordPress Security](https://wordpress.org/about/security/).

## Supported Versions

Use this section to tell people about which versions of your project are
currently being supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 5.4.x   | :white_check_mark: |
| 5.3.x   | :white_check_mark: |
| 5.2.x   | :white_check_mark: |
| 5.1.x   | :white_check_mark: |
| 5.0.x   | :white_check_mark: |
| 4.9.x   | :white_check_mark: |
| 4.8.x   | :white_check_mark: |
| 4.7.x   | :white_check_mark: |
| 4.6.x   | :white_check_mark: |
| 4.5.x   | :white_check_mark: |
| 4.4.x   | :white_check_mark: |
| 4.3.x   | :white_check_mark: |
| 4.2.x   | :white_check_mark: |
| 4.1.x   | :white_check_mark: |
| 4.0.x   | :white_check_mark: |
| 3.9.x   | :white_check_mark: |
| 3.8.x   | :white_check_mark: |
| 3.7.x   | :white_check_mark: |
| < 3.7.0 | :x:              |

## Reporting a Vulnerability

[<span>WordPress</span>](https://wordpress.org/) is an open-source publishing platform. Our HackerOne program covers the Core software, as well as a variety of related projects and infrastructure.

Our most critical targets are:

*   WordPress Core [<span>software</span>](https://wordpress.org/download/source/), [<span>API</span>](https://codex.wordpress.org/WordPress.org_API), and [<span>website</span>](https://wordpress.org/).
*   Gutenberg [<span>software</span>](https://github.com/WordPress/gutenberg/) and Classic Editor [<span>software</span>](https://wordpress.org/plugins/classic-editor/).
*   WP-CLI [<span>software</span>](https://github.com/wp-cli/) and [<span>website</span>](https://wp-cli.org/).
*   BuddyPress [<span>software</span>](https://buddypress.org/download/) and [<span>website</span>](https://buddypress.org/).
*   bbPress [<span>software</span>](https://bbpress.org/download/) and [<span>website</span>](https://bbpress.org/).
*   GlotPress [<span>software</span>](https://github.com/glotpress/glotpress-wp) (but not the website).
*   WordCamp.org [<span>website</span>](https://central.wordcamp.org).

Source code for most websites can be found in the Meta repository (`git clone git://meta.git.wordpress.org/`). [<span>The Meta Environment</span>](https://github.com/WordPress/meta-environment) will automatically provision a local copy of some sites for you.

For more targets, see the `In Scope` section below.

_Please note that **WordPress.com is a separate entity** from the main WordPress open source project. Please report vulnerabilities for WordPress.com or the WordPress mobile apps through [Automattic's HackerOne page](https://hackerone.com/automattic)._

## Qualifying Vulnerabilities

Any reproducible vulnerability that has a severe effect on the security or privacy of our users is likely to be in scope for the program. Common examples include XSS, CSRF, SSRF, RCE, SQLi, and privilege escalation.

We generally **aren’t** interested in the following problems:

*   Any vulnerability with a [<span>CVSS 3</span>](https://www.first.org/cvss/calculator/3.0) score lower than `4.0`, unless it can be combined with other vulnerabilities to achieve a higher score.
*   Brute force, DoS, phishing, text injection, or social engineering attacks. Wikis, Tracs, forums, etc are intended to allow users to edit them.
*   Security vulnerabilities in WordPress plugins not _specifically_ listed as an in-scope asset. Out of scope plugins can be [<span>reported to the Plugin Review team</span>](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#how-can-i-send-a-security-report).
*   Reports for hacked websites. The site owner can [<span>learn more about restoring their site</span>](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#ive-been-hacked-what-do-i-do-now).
*   [<span>Users with administrator or editor privileges can post arbitrary JavaScript</span>](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-some-users-allowed-to-post-unfiltered-html)
*   [<span>Disclosure of user IDs</span>](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-disclosures-of-usernames-or-user-ids-not-a-security-issue)
*   Open API endpoints serving public data (Including [<span>usernames and user IDs</span>](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-disclosures-of-usernames-or-user-ids-not-a-security-issue))
*   [<span>Path disclosures for errors, warnings, or notices</span>](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-there-path-disclosures-when-directly-loading-certain-files)
*   WordPress version number disclosure
*   Mixed content warnings for passive assets like images and videos
*   Lack of HTTP security headers (CSP, X-XSS, etc.)
*   Output from automated scans - please manually verify issues and include a valid proof of concept.
*   Any non-severe vulnerability on `irclogs.wordpress.org`, `lists.wordpress.org`, or any other low impact site.
*   Clickjacking with minimal security implications
*   Vulnerabilities in Composer/NPM `devDependencies`, unless there's a practical way to exploit it remotely.
*   Theoretical vulnerabilities where you can't demonstrate a significant security impact with a PoC.

## Guidelines

We're committed to working with security researchers to resolve the vulnerabilities they discover. You can help us by following these guidelines:

*   Follow [<span>HackerOne's disclosure guidelines</span>](https://www.hackerone.com/disclosure-guidelines).
*   Pen-testing Production:
    *   Please **setup a local environment** instead whenever possible. Most of our code is open source (see above).
    *   If that's not possible, **limit any data access/modification** to the bare minimum necessary to reproduce a PoC.
    *   **_Don't_ automate form submissions!** That's very annoying for us, because it adds extra work for the volunteers who manage those systems, and reduces the signal/noise ratio in our communication channels.
    *   If you don't follow these guidelines **we will not award a bounty for the report.**
*   Be Patient - Give us a reasonable time to correct the issue before you disclose the vulnerability. We care deeply about security, but we're an open-source project and our team is mostly comprised of volunteers. WordPress powers over 30% of the Web, so changes must undergo multiple levels of peer-review and testing, to make sure that they don't break millions of websites when they're installed automatically.

We also expect you to comply with all applicable laws. You're responsible to pay any taxes associated with your bounties.
