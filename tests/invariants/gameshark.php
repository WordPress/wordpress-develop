<?php
/**
 * The invariants file for the gameshark PHP extension.
 *
 * The extension loads it at the start of every PHP process when it runs with
 *
 *     GAMESHARK_INVARIANTS=1
 *     GAMESHARK_INVARIANTS_FILE=/absolute/path/to/tests/invariants/gameshark.php
 *
 * It runs before PHPUnit and before WordPress, and returns the hooks for every
 * invariant in specs/.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once __DIR__ . '/includes/load.php';

return WP_Invariant::load_specs( __DIR__ . '/specs' );
