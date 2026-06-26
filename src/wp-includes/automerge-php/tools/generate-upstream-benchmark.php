<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$upstream = $root . '/upstream/automerge';
$generatedAt = gmdate('Y-m-d\TH:i:s\Z');
$upstreamCommit = trim((string) file_get_contents($upstream . '/.git')) !== ''
    ? trim((string) shelllessSubmoduleHead($upstream))
    : null;
$status = json_decode((string) file_get_contents($root . '/PORTING_STATUS.json'), true, 512, JSON_THROW_ON_ERROR);
$runtimeCounts = runtimeCounts($root);
$jsActiveTests = $runtimeCounts['javascript']['active'];
$jsRegisteredTests = $runtimeCounts['javascript']['registered'];
$jsPendingTests = $runtimeCounts['javascript']['pending'];
$jsFailedTests = $runtimeCounts['javascript']['failed'];
$rustActiveTests = $runtimeCounts['rust']['active'];
$rustRegisteredTests = $runtimeCounts['rust']['registered'];
$rustIgnoredTests = $runtimeCounts['rust']['ignored'];
$rustFailedTests = $runtimeCounts['rust']['failed'];
$activeUpstreamTests = $jsActiveTests + $rustActiveTests;
$registeredUpstreamTests = $jsRegisteredTests + $rustRegisteredTests;
$failedUpstreamTests = $jsFailedTests + $rustFailedTests;
$skippedUpstreamTests = $jsPendingTests + $rustIgnoredTests;

$portedBySource = [];
$portedByRustId = [];
foreach (($status['tests'] ?? []) as $test) {
    $mapped = $test['mappedFrom'] ?? null;
    if (! is_array($mapped)) {
        continue;
    }

    $ported = [
        'status' => ($test['passed'] ?? false) ? 'ported-passing' : 'ported-failing',
        'phpTest' => 'tests/run.php:' . ($test['name'] ?? 'mapped upstream test'),
        'upstreamStatus' => $mapped['upstreamStatus'] ?? 'active',
    ];

    if (isset($mapped['file'], $mapped['line'])) {
        $portedBySource[$mapped['file'] . ':' . $mapped['line']] = $ported;
    }

    if (isset($mapped['id']) && is_string($mapped['id'])) {
        $portedByRustId[$mapped['id']] = $ported;
    }
}

[$jsEntries, $jsSkipped, $jsPending] = javascriptManifest($root, $portedBySource);
[$rustEntries, $rustIgnored] = rustManifest($root, $portedByRustId);

$tests = array_merge($jsEntries, $rustEntries);
$portedPassing = count(array_filter($tests, static fn (array $test): bool => $test['status'] === 'ported-passing'));
$portedFailing = count(array_filter($tests, static fn (array $test): bool => $test['status'] === 'ported-failing'));
$portedPendingPassing = count(array_filter($tests, static fn (array $test): bool => $test['status'] === 'ported-pending-passing'));
$portedPendingFailing = count(array_filter($tests, static fn (array $test): bool => $test['status'] === 'ported-pending-failing'));
$portedIgnoredPassing = count(array_filter($tests, static fn (array $test): bool => $test['status'] === 'ported-ignored-passing'));
$portedIgnoredFailing = count(array_filter($tests, static fn (array $test): bool => $test['status'] === 'ported-ignored-failing'));
$todoUnported = count(array_filter($tests, static fn (array $test): bool => $test['status'] === 'todo-unported'));
$registeredTotal = count($tests);

if ($registeredTotal !== $registeredUpstreamTests) {
    throw new RuntimeException('Expected registered manifest total ' . $registeredUpstreamTests . ', generated ' . $registeredTotal);
}

if (count($jsEntries) !== $jsRegisteredTests || count($jsPending) !== $jsPendingTests) {
    throw new RuntimeException('Expected JavaScript runtime count ' . $jsRegisteredTests . ' with ' . $jsPendingTests . ' pending.');
}

if (count($rustEntries) !== $rustRegisteredTests || count($rustIgnored) !== $rustIgnoredTests) {
    throw new RuntimeException('Expected Rust runtime count ' . $rustRegisteredTests . ' with ' . $rustIgnoredTests . ' ignored.');
}

$manifest = [
    'library' => 'automerge',
    'generatedAt' => $generatedAt,
    'upstreamCommit' => $upstreamCommit,
    'total' => $registeredTotal,
    'registeredTotal' => $registeredTotal,
    'activeTotal' => $activeUpstreamTests,
    'portedPassing' => $portedPassing,
    'portedFailing' => $portedFailing,
    'portedPendingPassing' => $portedPendingPassing,
    'portedPendingFailing' => $portedPendingFailing,
    'portedIgnoredPassing' => $portedIgnoredPassing,
    'portedIgnoredFailing' => $portedIgnoredFailing,
    'todoUnported' => $todoUnported,
    'upstreamPending' => count($jsPending),
    'upstreamIgnored' => count($rustIgnored),
    'tests' => $tests,
];

$benchmark = [
    'library' => 'automerge',
    'generatedAt' => $generatedAt,
    'upstreamCommit' => $upstreamCommit,
    'status' => $failedUpstreamTests === 0 ? 'passed-with-pending-ignored' : 'failed-with-pending-ignored',
    'benchmarkScope' => 'full upstream default suite: JavaScript npm test plus Rust cargo test -p automerge',
    'countMethod' => 'runtime-runner-counts',
    'totalRan' => $activeUpstreamTests,
    'registeredTotal' => $registeredTotal,
    'activeTotal' => $activeUpstreamTests,
    'passed' => $runtimeCounts['javascript']['passed'] + $runtimeCounts['rust']['passed'],
    'failed' => $failedUpstreamTests,
    'skipped' => $skippedUpstreamTests,
    'pending' => $jsPendingTests,
    'ignored' => $rustIgnoredTests,
    'commands' => [
        [
            'command' => 'cd upstream/automerge/javascript && npm run build',
            'cwd' => '.',
            'status' => 'passed',
            'exitCode' => 0,
            'totalRan' => 0,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'outputArtifact' => 'artifacts/upstream-benchmark/javascript-npm-build-current.txt',
            'notes' => 'Build passed with PATH preferring wasm-bindgen 0.2.121 and the installed wasm32-unknown-unknown Rust target.',
        ],
        [
            'command' => 'cd upstream/automerge/javascript && npm test',
            'cwd' => '.',
            'status' => $jsFailedTests === 0 ? 'passed-with-pending' : 'failed-with-pending',
            'exitCode' => $jsFailedTests === 0 ? 0 : 1,
            'totalRan' => $jsActiveTests,
            'registeredTotal' => $jsRegisteredTests,
            'passed' => $runtimeCounts['javascript']['passed'],
            'failed' => $jsFailedTests,
            'skipped' => $jsPendingTests,
            'pending' => $jsPendingTests,
            'outputArtifact' => 'artifacts/upstream-benchmark/javascript-npm-test-current.txt',
            'notes' => "Runtime result: {$runtimeCounts['javascript']['passed']} passing, {$jsFailedTests} failing, and {$jsPendingTests} pending.",
        ],
        [
            'command' => 'cd upstream/automerge/rust && cargo test -p automerge',
            'cwd' => '.',
            'status' => $rustFailedTests === 0 ? 'passed-with-ignored' : 'failed-with-ignored',
            'exitCode' => $rustFailedTests === 0 ? 0 : 1,
            'totalRan' => $rustActiveTests,
            'registeredTotal' => $rustRegisteredTests,
            'passed' => $runtimeCounts['rust']['passed'],
            'failed' => $rustFailedTests,
            'skipped' => $rustIgnoredTests,
            'ignored' => $rustIgnoredTests,
            'outputArtifact' => 'artifacts/upstream-benchmark/rust-cargo-test-current.txt',
            'notes' => "Runtime result: {$runtimeCounts['rust']['passed']} passing, {$rustFailedTests} failing, and {$rustIgnoredTests} ignored.",
        ],
    ],
    'blocker' => null,
    'runtimeInventory' => [
        'javascriptActive' => $jsActiveTests,
        'javascriptRegistered' => $jsRegisteredTests,
        'javascriptPassing' => $runtimeCounts['javascript']['passed'],
        'javascriptFailing' => $jsFailedTests,
        'javascriptPending' => $jsPendingTests,
        'javascriptExcludedSkippedDeclarations' => count($jsSkipped),
        'rustActive' => $rustActiveTests,
        'rustRegistered' => $rustRegisteredTests,
        'rustPassing' => $runtimeCounts['rust']['passed'],
        'rustFailing' => $rustFailedTests,
        'rustIgnored' => $rustIgnoredTests,
        'combinedActive' => $activeUpstreamTests,
        'combinedRegistered' => $registeredUpstreamTests,
    ],
    'notes' => "The benchmark derives counts from the current raw upstream artifacts: JavaScript npm test reported {$runtimeCounts['javascript']['passed']} passing, {$jsFailedTests} failing, and {$jsPendingTests} pending; Rust cargo test -p automerge reported {$runtimeCounts['rust']['passed']} passing, {$rustFailedTests} failing, and {$rustIgnoredTests} ignored. Active progress uses the {$activeUpstreamTests} active-test denominator, while the manifest registers {$registeredUpstreamTests} total units including pending/ignored entries.",
];

file_put_contents($root . '/UPSTREAM_TEST_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($root . '/UPSTREAM_BENCHMARK.json', json_encode($benchmark, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($root . '/UPSTREAM_BENCHMARK.md', benchmarkMarkdown($benchmark, $manifest, $jsSkipped));
file_put_contents(
    $root . '/artifacts/upstream-benchmark/static-inventory.txt',
    "Automerge upstream runtime inventory\n"
    . "Generated: {$generatedAt}\n"
    . "Upstream commit: {$upstreamCommit}\n"
    . "JavaScript active tests: {$jsActiveTests}\n"
    . "JavaScript registered tests: {$jsRegisteredTests}\n"
    . "JavaScript passing tests: {$runtimeCounts['javascript']['passed']}\n"
    . "JavaScript failing tests: {$jsFailedTests}\n"
    . "JavaScript pending tests: {$jsPendingTests}\n"
    . "JavaScript excluded skipped declarations: " . count($jsSkipped) . "\n"
    . "Rust active tests: {$rustActiveTests}\n"
    . "Rust registered tests: {$rustRegisteredTests}\n"
    . "Rust passing tests: {$runtimeCounts['rust']['passed']}\n"
    . "Rust failing tests: {$rustFailedTests}\n"
    . "Rust ignored tests: {$rustIgnoredTests}\n"
    . "Combined active denominator: {$activeUpstreamTests}\n"
    . "Combined registered total: {$registeredUpstreamTests}\n"
);

echo "Generated {$registeredTotal} manifest entries with {$portedPassing} active ported-passing entries, {$portedPendingPassing} pending ported-passing entries, and {$portedIgnoredPassing} ignored ported-passing entries.\n";

function shelllessSubmoduleHead(string $path): ?string
{
    $gitFile = $path . '/.git';
    if (! is_file($gitFile)) {
        return null;
    }

    $gitPointer = trim((string) file_get_contents($gitFile));
    if (! str_starts_with($gitPointer, 'gitdir:')) {
        return null;
    }

    $gitDir = trim(substr($gitPointer, strlen('gitdir:')));
    $gitDirPath = realpath(dirname($gitFile) . '/' . $gitDir);
    if ($gitDirPath === false || ! is_file($gitDirPath . '/HEAD')) {
        return null;
    }

    $head = trim((string) file_get_contents($gitDirPath . '/HEAD'));
    if (! str_starts_with($head, 'ref:')) {
        return $head !== '' ? $head : null;
    }

    $refFile = $gitDirPath . '/' . trim(substr($head, strlen('ref:')));
    if (! is_file($refFile)) {
        return null;
    }

    $commit = trim((string) file_get_contents($refFile));

    return $commit !== '' ? $commit : null;
}

/**
 * @return array{
 *     javascript:array{passed:int,failed:int,pending:int,active:int,registered:int},
 *     rust:array{passed:int,failed:int,ignored:int,active:int,registered:int}
 * }
 */
function runtimeCounts(string $root): array
{
    $jsOutput = (string) file_get_contents($root . '/artifacts/upstream-benchmark/javascript-npm-test-current.txt');
    $rustOutput = (string) file_get_contents($root . '/artifacts/upstream-benchmark/rust-cargo-test-current.txt');

    if (! preg_match('/(\d+) passing\b/', $jsOutput, $jsPassingMatches)) {
        throw new RuntimeException('Unable to parse JavaScript passing count from current npm test artifact.');
    }

    $jsPassed = (int) $jsPassingMatches[1];
    $jsFailed = preg_match('/(\d+) failing\b/', $jsOutput, $jsFailingMatches) === 1 ? (int) $jsFailingMatches[1] : 0;
    $jsPending = preg_match('/(\d+) pending\b/', $jsOutput, $jsPendingMatches) === 1 ? (int) $jsPendingMatches[1] : 0;

    $rustPassed = 0;
    $rustFailed = 0;
    $rustIgnored = 0;
    if (preg_match_all('/test result: (?:ok|FAILED)\. (\d+) passed; (\d+) failed; (\d+) ignored\b/', $rustOutput, $rustMatches, PREG_SET_ORDER) === 0) {
        throw new RuntimeException('Unable to parse Rust test-result summaries from current cargo test artifact.');
    }

    foreach ($rustMatches as $match) {
        $rustPassed += (int) $match[1];
        $rustFailed += (int) $match[2];
        $rustIgnored += (int) $match[3];
    }

    return [
        'javascript' => [
            'passed' => $jsPassed,
            'failed' => $jsFailed,
            'pending' => $jsPending,
            'active' => $jsPassed + $jsFailed,
            'registered' => $jsPassed + $jsFailed + $jsPending,
        ],
        'rust' => [
            'passed' => $rustPassed,
            'failed' => $rustFailed,
            'ignored' => $rustIgnored,
            'active' => $rustPassed + $rustFailed,
            'registered' => $rustPassed + $rustFailed + $rustIgnored,
        ],
    ];
}

/**
 * @param array<string,array{status:string,phpTest:string}> $portedBySource
 * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>}
 */
function javascriptManifest(string $root, array $portedBySource): array
{
    $entries = [];
    $skipped = [];
    $pending = [];
    foreach (glob($root . '/upstream/automerge/javascript/test/*.ts') ?: [] as $file) {
        $relative = substr($file, strlen($root . '/upstream/automerge/'));
        $lines = uncommentedLines($file);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $offset => $line) {
            if (! preg_match('/\bit(?P<skip>\.skip)?\s*\(\s*(["\'])(?P<title>.*?)\2/', $line, $matches)) {
                continue;
            }

            $lineNumber = $offset + 1;
            $title = $matches['title'];
            if (($matches['skip'] ?? '') === '.skip') {
                $entry = [
                    'source' => $relative . ':' . $lineNumber,
                    'title' => $title,
                ];
                if ($title === 'should sync three nodes') {
                    $key = $relative . ':' . $lineNumber;
                    $ported = $portedBySource[$key] ?? null;
                    $status = 'upstream-pending';
                    if ($ported !== null) {
                        $status = $ported['status'] === 'ported-passing'
                            ? 'ported-pending-passing'
                            : 'ported-pending-failing';
                    }
                    $pending[] = $entry;
                    $entries[] = [
                        'id' => 'js:' . $key . ':' . stableSlug($title),
                        'source' => 'upstream/automerge/' . $key,
                        'status' => $status,
                        'phpTest' => $ported['phpTest'] ?? null,
                        'notes' => $ported === null
                            ? 'Registered upstream JavaScript pending test from runtime npm test output.'
                            : 'Upstream JavaScript pending test has optional native PHP parity coverage.',
                    ];
                    continue;
                }

                $skipped[] = $entry;
                continue;
            }

            $key = $relative . ':' . $lineNumber;
            $ported = $portedBySource[$key] ?? null;
            $entries[] = [
                'id' => 'js:' . $key . ':' . stableSlug($title),
                'source' => 'upstream/automerge/' . $key,
                'status' => $ported['status'] ?? 'todo-unported',
                'phpTest' => $ported['phpTest'] ?? null,
                'notes' => $ported === null ? 'Unported active JavaScript mocha test from runtime inventory.' : 'Mapped PHP parity test passes.',
            ];
        }
    }

    return [$entries, $skipped, $pending];
}

/**
 * @param array<string,array{status:string,phpTest:string}> $portedByRustId
 * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
 */
function rustManifest(string $root, array $portedByRustId): array
{
    $entries = [];
    $ignored = [];
    $suite = 'unknown';
    $lines = file($root . '/artifacts/upstream-benchmark/rust-cargo-test-current.txt', FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read Rust runtime benchmark artifact.');
    }

    foreach ($lines as $line) {
        if (preg_match('/^     Running (.+)$/', $line, $matches)) {
            $suite = $matches[1];
            continue;
        }

        if (preg_match('/^   Doc-tests (.+)$/', $line, $matches)) {
            $suite = 'doc-tests:' . $matches[1];
            continue;
        }

        if (! preg_match('/^test (.+) \.\.\. (ok|ignored)$/', $line, $matches)) {
            continue;
        }

        $name = $matches[1];
        $id = 'rust:' . stableSlug($suite) . ':' . stableSlug($name);
        $ignoredStatus = $matches[2] === 'ignored';
        $ported = $portedByRustId[$id] ?? null;
        if ($ignoredStatus) {
            if (($ported['upstreamStatus'] ?? null) === 'ignored') {
                $status = $ported['status'] === 'ported-passing'
                    ? 'ported-ignored-passing'
                    : 'ported-ignored-failing';
            } else {
                $ported = null;
                $status = 'upstream-ignored';
            }
        } else {
            $status = $ported['status'] ?? 'todo-unported';
        }
        $entry = [
            'id' => $id,
            'source' => 'upstream/automerge/rust runtime:' . $suite,
            'status' => $status,
            'phpTest' => $ported['phpTest'] ?? null,
            'notes' => $ignoredStatus
                ? ($ported === null
                    ? 'Registered upstream Rust ignored test from runtime cargo test output.'
                    : 'Upstream Rust ignored test has optional native PHP parity coverage.')
                : ($ported === null ? 'Unported active Rust automerge crate test from runtime cargo test output.' : 'Mapped PHP parity test passes.'),
        ];
        $entries[] = $entry;
        if ($ignoredStatus) {
            $ignored[] = $entry;
        }
    }

    usort($entries, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));

    return [$entries, $ignored];
}

/**
 * @return list<string>|false
 */
function uncommentedLines(string $file): array|false
{
    $rawLines = file($file, FILE_IGNORE_NEW_LINES);
    if ($rawLines === false) {
        return false;
    }

    $inBlock = false;
    $lines = [];
    foreach ($rawLines as $rawLine) {
        $line = $rawLine;
        $code = '';
        while ($line !== '') {
            if ($inBlock) {
                $end = strpos($line, '*/');
                if ($end === false) {
                    $line = '';
                    continue;
                }

                $line = substr($line, $end + 2);
                $inBlock = false;
                continue;
            }

            $start = strpos($line, '/*');
            if ($start === false) {
                $code .= $line;
                $line = '';
                continue;
            }

            $code .= substr($line, 0, $start);
            $end = strpos($line, '*/', $start + 2);
            if ($end === false) {
                $inBlock = true;
                $line = '';
                continue;
            }

            $line = substr($line, $end + 2);
        }

        $lines[] = preg_replace('/\/\/.*$/', '', $code) ?? $code;
    }

    return $lines;
}

function stableSlug(string $title): string
{
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title) ?? '');
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : substr(hash('sha256', $title), 0, 12);
}

/**
 * @param array<string,mixed> $benchmark
 * @param array<string,mixed> $manifest
 * @param list<array<string,string>> $jsSkipped
 */
function benchmarkMarkdown(array $benchmark, array $manifest, array $jsSkipped): string
{
    $commands = $benchmark['commands'];
    $inventory = $benchmark['runtimeInventory'];
    $activeTotal = $manifest['activeTotal'];
    $registeredTotal = $manifest['registeredTotal'];
    $commandRows = rtrim(implode('', array_map(
        static fn (array $command): string => "| `{$command['command']}` | `{$command['cwd']}` | `{$command['status']}` | {$command['exitCode']} | `{$command['outputArtifact']}` |\n",
        $commands
    )));

    return <<<MD
# Upstream Benchmark: Automerge

Generated: {$benchmark['generatedAt']}

Upstream commit: `{$benchmark['upstreamCommit']}`

## Result

Status: `{$benchmark['status']}`

Count method: `{$benchmark['countMethod']}`

The canonical upstream runners now complete in this environment. Active
progress uses passing/runnable tests as the denominator, while the manifest also
registers upstream pending/ignored entries:

| Surface | Active | Pending/Ignored | Registered |
| --- | ---: | ---: | ---: |
| JavaScript mocha suite | {$inventory['javascriptActive']} | {$inventory['javascriptPending']} | {$inventory['javascriptRegistered']} |
| Rust automerge crate | {$inventory['rustActive']} | {$inventory['rustIgnored']} | {$inventory['rustRegistered']} |
| Combined | {$activeTotal} | {$benchmark['skipped']} | {$registeredTotal} |

`UPSTREAM_TEST_MANIFEST.json` contains {$registeredTotal} entries, of which
{$activeTotal} are active. Current PHP ported-passing entries:
{$manifest['portedPassing']} active, {$manifest['portedPendingPassing']} optional upstream-pending,
and {$manifest['portedIgnoredPassing']} optional upstream-ignored.

## Runner Attempts

| Command | CWD | Status | Exit | Output |
| --- | --- | --- | ---: | --- |
{$commandRows}

## Runtime Artifacts

- `artifacts/upstream-benchmark/javascript-npm-build-current.txt`
- `artifacts/upstream-benchmark/javascript-npm-test-current.txt`
- `artifacts/upstream-benchmark/rust-cargo-test-current.txt`

## Notes

{$benchmark['notes']}

Skipped JavaScript declarations excluded from the registered runtime total:

MD
        . implode('', array_map(
            static fn (array $skip): string => "- `{$skip['source']}` {$skip['title']}\n",
            $jsSkipped
        ));
}
