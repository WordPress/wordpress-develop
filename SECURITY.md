# Security Policy

Full details of the WordPress Security Policy can be found on [HackerOne](https://hackerone.com/wordpress). You can also read more in a detailed white paper about [WordPress Security](https://wordpress.org/about/security/).

## Supported Versions

Use this section to tell people about which versions of your project are
currently being supported with security updates.

| Version | Supported |
| ------- | --------- |
| 6.2.x   | Yes       |
| 6.1.x   | Yes       |
| 6.0.x   | Yes       |
| 5.9.x   | Yes       |
| 5.8.x   | Yes       |
| 5.7.x   | Yes       |
| 5.6.x   | Yes       |
| 5.5.x   | Yes       |
| 5.4.x   | Yes       |
| 5.3.x   | Yes       |
| 5.2.x   | Yes       |
| 5.1.x   | Yes       |
| 5.0.x   | Yes       |
| 4.9.x   | Yes       |
| 4.8.x   | Yes       |
| 4.7.x   | Yes       |
| 4.6.x   | Yes       |
| 4.5.x   | Yes       |
| 4.4.x   | Yes       |
| 4.3.x   | Yes       |
| 4.2.x   | Yes       |
| 4.1.x   | Yes       |
| < 4.1.0 | No        |

## Reporting a Vulnerability

[WordPress](https://wordpress.org/) is an open-source publishing platform. Our HackerOne program covers the Core software, as well as a variety of related projects and infrastructure.

Our most critical targets are:

*   WordPress Core [software](https://wordpress.org/download/source/), [API](https://codex.wordpress.org/WordPress.org_API), and [website](https://wordpress.org/).
*   Gutenberg [software](https://github.com/WordPress/gutenberg/) and Classic Editor [software](https://wordpress.org/plugins/classic-editor/).
*   WP-CLI [software](https://github.com/wp-cli/) and [website](https://wp-cli.org/).
*   BuddyPress [software](https://buddypress.org/download/) and [website](https://buddypress.org/).
*   bbPress [software](https://bbpress.org/download/) and [website](https://bbpress.org/).
*   GlotPress [software](https://github.com/glotpress/glotpress-wp) (but not the website).
*   WordCamp.org [website](https://central.wordcamp.org).

Source code for most websites can be found in the Meta repository (`git clone git://meta.git.wordpress.org/`). [The Meta Environment](https://github.com/WordPress/meta-environment) will automatically provision a local copy of some sites for you.

For more targets, see the `In Scope` section below.

_Please note that **WordPress.com is a separate entity** from the main WordPress open source project. Please report vulnerabilities for WordPress.com or the WordPress mobile apps through [Automattic's HackerOne page](https://hackerone.com/automattic)._

## Qualifying Vulnerabilities

Any reproducible vulnerability that has a severe effect on the security or privacy of our users is likely to be in scope for the program. Common examples include XSS, CSRF, SSRF, RCE, SQLi, and privilege escalation.

We generally **aren’t** interested in the following problems:

*   Any vulnerability with a [CVSS 3](https://www.first.org/cvss/calculator/3.0) score lower than `4.0`, unless it can be combined with other vulnerabilities to achieve a higher score.
*   Brute force, DoS, phishing, text injection, or social engineering attacks. Wikis, Tracs, forums, etc are intended to allow users to edit them.
*   Security vulnerabilities in WordPress plugins not _specifically_ listed as an in-scope asset. Out of scope plugins can be [reported to the Plugin Review team](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#how-can-i-send-a-security-report).
*   Reports for hacked websites. The site owner can [learn more about restoring their site](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#ive-been-hacked-what-do-i-do-now).
*   [Users with administrator or editor privileges can post arbitrary JavaScript](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-some-users-allowed-to-post-unfiltered-html)
*   [Disclosure of user IDs](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-disclosures-of-usernames-or-user-ids-not-a-security-issue)
*   Open API endpoints serving public data (Including [usernames and user IDs](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-disclosures-of-usernames-or-user-ids-not-a-security-issue))
*   [Path disclosures for errors, warnings, or notices](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/#why-are-there-path-disclosures-when-directly-loading-certain-files)
*   WordPress version number disclosure
*   Mixed content warnings for passive assets like images and videos
*   Lack of HTTP security headers (CSP, X-XSS, etc.)
*   Output from automated scans - please manually verify issues and include a valid proof of concept.
*   Any non-severe vulnerability on `irclogs.wordpress.org`, `lists.wordpress.org`, or any other low impact site.
*   Clickjacking with minimal security implications
*   Vulnerabilities in Composer/npm `devDependencies`, unless there's a practical way to exploit it remotely.
*   Theoretical vulnerabilities where you can't demonstrate a significant security impact with a PoC.

## Guidelines

We're committed to working with security researchers to resolve the vulnerabilities they discover. You can help us by following these guidelines:

*   Follow [HackerOne's disclosure guidelines](https://www.hackerone.com/disclosure-guidelines).
*   Pen-testing Production:
    *   Please **setup a local environment** instead whenever possible. Most of our code is open source (see above).
    *   If that's not possible, **limit any data access/modification** to the bare minimum necessary to reproduce a PoC.
    *   **_Don't_ automate form submissions!** That's very annoying for us, because it adds extra work for the volunteers who manage those systems, and reduces the signal/noise ratio in our communication channels.
    *   If you don't follow these guidelines **we will not award a bounty for the report.**
*   Be Patient - Give us a reasonable time to correct the issue before you disclose the vulnerability. We care deeply about security, but we're an open-source project and our team is mostly comprised of volunteers. WordPress powers over 30% of the Web, so changes must undergo multiple levels of peer-review and testing, to make sure that they don't break millions of websites when they're installed automatically.

We also expect you to comply with all applicable laws. You're responsible to pay any taxes associated with your bounties.
