<?php
/**
 * What WP_PHPUnit_Leak_Listener snapshots, see tests/phpunit/README.md.
 *
 * Patterns are fnmatch() globs over snapshot keys, "area/key", without escapes. "!" negates.
 * The last pattern matching a key decides. A bare area name means all its keys, e.g. "mc".
 *
 * 'areas' holds each scope's defaults; WP_TESTS_LEAKS_AREAS replaces them. A test file's
 * patterns are its scope's defaults, then the 'areas' of each 'files' rule whose 'paths'
 * match it, in order. Paths are globs from the repo root; "*" matches "/" too.
 */
return array(
	'areas' => array(
		'test'  => array(
			'db/*',
			'dbcount/*',
			'autoinc/*',
			'schema/*',
			'temptable/*',
			'cache/*',
			'mc/*',
			'stale/*',
			'global/*',
			'static/*',
			'fstatic/*',
			'const/*',
			'ini/*',
			'env/*',
			'proc/*',
			'file/*',
		),
		/*
		 * What the next class inherits. Not row counts, they repeat the db rows, nor AUTO_INCREMENT
		 * counters, which nearly every class's fixtures bump, nor temporary tables: a child's own
		 * connection can't see them.
		 */
		'class' => array(
			'db/*',
			'schema/*',
			'mc/*',
			'file/*',
			'stale/*',
		),
	),
	'files' => array(
		array(
			'why'   => 'Leak probe: a later pattern keeps a key an earlier one drops, see check-probes.php.',
			'paths' => array(
				'tests/phpunit/leaks/probe/leakProbe.php',
			),
			'areas' => array(
				'!global/leak_probe_filtered_*',
				'global/leak_probe_filtered_kept',
			),
		),
		array(
			'why'   => 'Leak probe: a file no pattern keeps is not snapshotted, see check-probes.php.',
			'paths' => array(
				'tests/phpunit/leaks/probe/leakProbeSkipped.php',
			),
			'areas' => array(
				'!*',
			),
		),
	),
);
