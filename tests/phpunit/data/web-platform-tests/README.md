# Web Platform Tests

This directory contains a third-party test suite used for testing the WordPress HTML API.

The tests are maintained by the Web Platform Tests project.
The current tests can be found on GitHub at
[`web-platform-tests/wpt/html/syntax/parsing/resources`](https://github.com/web-platform-tests/wpt/tree/master/html/syntax/parsing/resources).

The version of the WPT files was taken from the git commit with
SHA [`1ae157bacf6cb1db64a60875800a30c80a4fc93c`](https://github.com/web-platform-tests/wpt/tree/1ae157bacf6cb1db64a60875800a30c80a4fc93c/html/syntax/parsing/resources).

## Updating

If there have been changes to the Web Platform Tests repository, this test suite can be updated. In
order to update:

1. Check out the latest version of git repository mentioned above.
1. Replace the local `html_syntax_parsing_resources/` directory with the WPT repo's `html/syntax/parsing/resources` directory.
1. Update the SHA mentioned in this README file with the new Web Platform Tests SHA.
