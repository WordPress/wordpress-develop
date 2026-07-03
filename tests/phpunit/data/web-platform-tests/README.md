# Web Platform Tests

This directory contains a third-party test suite used for testing the WordPress HTML API.

The tree-construction tests are maintained by the Web Platform Tests project.
The raw `.dat` fixtures can be found on GitHub at
[web-platform-tests/wpt/html/syntax/parsing/resources](https://github.com/web-platform-tests/wpt/tree/master/html/syntax/parsing/resources).

The necessary files have been copied to this directory:

- `AUTHORS.rst`
- `LICENSE`
- `tree-construction/README.md`
- `tree-construction/*.dat`

The version of the WPT-copied files was taken from the git commit with
SHA [`c469a8a72a2ce58c04601255a45504ab9f5cc763`](https://github.com/web-platform-tests/wpt/commit/c469a8a72a2ce58c04601255a45504ab9f5cc763).

The `AUTHORS.rst` and `LICENSE` files document the original html5lib test suite
attribution and license.

## Updating

If there have been changes to the Web Platform Tests repository, this test suite can be updated. In
order to update:

1. Check out the latest version of git repository mentioned above.
1. Copy `README.md` and `*.dat` from `html/syntax/parsing/resources/` into `tree-construction/`.
1. Update the SHA mentioned in this README file with the new Web Platform Tests SHA.
