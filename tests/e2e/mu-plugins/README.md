# Login fixture

The E2E runner temporarily installs this must-use plugin to disable login autofocus. WordPress’s delayed autofocus can clear a password after automated typing.

The runner creates its own copy under `LOCAL_DIR/wp-content/mu-plugins` (`src` by default). It removes that copy on normal exit or when interrupted by `SIGHUP`, `SIGINT`, `SIGQUIT`, or `SIGTERM`.

If the tested WordPress installation is elsewhere, install `disable-login-autofocus.php` in that installation’s `wp-content/mu-plugins` for the test run, then remove it afterward.
