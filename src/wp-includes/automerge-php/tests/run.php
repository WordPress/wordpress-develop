<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Counter.php';
require_once __DIR__ . '/../src/BytesValue.php';
require_once __DIR__ . '/../src/BigIntValue.php';
require_once __DIR__ . '/../src/ImmutableString.php';
require_once __DIR__ . '/../src/RawString.php';
require_once __DIR__ . '/../src/UndefinedValue.php';
require_once __DIR__ . '/../src/TextValue.php';
require_once __DIR__ . '/../src/DocumentObjectReference.php';
require_once __DIR__ . '/../src/Document.php';
require_once __DIR__ . '/../src/BackendView.php';
require_once __DIR__ . '/../src/IsolatedDocument.php';
require_once __DIR__ . '/../src/NativePort.php';
require_once __DIR__ . '/../src/Transaction.php';

use WordPress\DistributedEditing\Automerge\BytesValue;
use WordPress\DistributedEditing\Automerge\BigIntValue;
use WordPress\DistributedEditing\Automerge\Counter;
use WordPress\DistributedEditing\Automerge\Document;
use WordPress\DistributedEditing\Automerge\ImmutableString;
use WordPress\DistributedEditing\Automerge\IsolatedDocument;
use WordPress\DistributedEditing\Automerge\RawString;
use WordPress\DistributedEditing\Automerge\NativePort;
use WordPress\DistributedEditing\Automerge\TextValue;
use WordPress\DistributedEditing\Automerge\Transaction;

function submoduleHeadCommit(string $submodulePath): ?string
{
    $gitFile = $submodulePath . '/.git';
    if (! is_file($gitFile)) {
        return null;
    }

    $gitPointer = trim((string) file_get_contents($gitFile));
    if (! str_starts_with($gitPointer, 'gitdir:')) {
        return null;
    }

    $gitDir = trim(substr($gitPointer, strlen('gitdir:')));
    $gitDirPath = realpath(dirname($gitFile) . '/' . $gitDir);
    if ($gitDirPath === false) {
        return null;
    }

    $headFile = $gitDirPath . '/HEAD';
    if (! is_file($headFile)) {
        return null;
    }

    $head = trim((string) file_get_contents($headFile));
    if (! str_starts_with($head, 'ref:')) {
        return $head !== '' ? $head : null;
    }

    $ref = trim(substr($head, strlen('ref:')));
    $refFile = $gitDirPath . '/' . $ref;
    if (! is_file($refFile)) {
        return null;
    }

    $commit = trim((string) file_get_contents($refFile));

    return $commit !== '' ? $commit : null;
}

/**
 * @param array<string,mixed> $left
 * @param array<string,mixed> $right
 */
function sameArray(array $left, array $right, string $message): void
{
    if ($left !== $right) {
        throw new RuntimeException($message . ' Expected ' . json_encode($right) . ', got ' . json_encode($left));
    }
}

function same(mixed $left, mixed $right, string $message): void
{
    if ($left !== $right) {
        throw new RuntimeException($message . ' Expected ' . json_encode($right) . ', got ' . json_encode($left));
    }
}

function truthy(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function throwsLike(callable $callback, string $needle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if (str_contains($throwable->getMessage(), $needle)) {
            return;
        }

        throw new RuntimeException($message . ' Unexpected error: ' . $throwable->getMessage());
    }

    throw new RuntimeException($message . ' No exception was thrown.');
}

/**
 * @param list<mixed> $options
 */
function oneOf(mixed $actual, array $options, string $message): void
{
    foreach ($options as $option) {
        if ($actual === $option) {
            return;
        }
    }

    throw new RuntimeException($message . ' Got ' . json_encode($actual));
}

function dateMillis(DateTimeInterface $value): int
{
    return ((int) $value->format('U')) * 1000 + intdiv((int) $value->format('u'), 1000);
}

/**
 * @param array<string,mixed>|null $leftSync
 * @param array<string,mixed>|null $rightSync
 * @return array{0:Document,1:Document,2:array<string,mixed>,3:array<string,mixed>}
 */
function syncDocuments(NativePort $port, Document $left, Document $right, ?array $leftSync = null, ?array $rightSync = null): array
{
    $leftSync ??= $port->initSyncState();
    $rightSync ??= $port->initSyncState();
    $iterations = 0;

    do {
        [$leftSync, $leftToRight] = $port->generateSyncMessage($left, $leftSync);
        [$rightSync, $rightToLeft] = $port->generateSyncMessage($right, $rightSync);

        if ($leftToRight !== null) {
            [$right, $rightSync] = $port->receiveSyncMessage($right, $rightSync, $leftToRight);
        }

        if ($rightToLeft !== null) {
            [$left, $leftSync] = $port->receiveSyncMessage($left, $leftSync, $rightToLeft);
        }

        if (++$iterations > 10) {
            throw new RuntimeException('Native PHP sync helper did not converge within 10 iterations.');
        }
    } while ($leftToRight !== null || $rightToLeft !== null);

    return [$left, $right, $leftSync, $rightSync];
}

$port = new NativePort();
$tests = [];
$wordpressScenarios = [];

$mapped = static function (string $name, string $upstreamFile, int $upstreamLine, string $upstreamTest, callable $run) use (&$tests): void {
    try {
        $run();
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'file' => $upstreamFile,
                'line' => $upstreamLine,
                'test' => $upstreamTest,
            ],
            'passed' => true,
        ];
    } catch (Throwable $throwable) {
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'file' => $upstreamFile,
                'line' => $upstreamLine,
                'test' => $upstreamTest,
            ],
            'passed' => false,
            'failure' => $throwable->getMessage(),
        ];
    }
};

$pendingMapped = static function (string $name, string $upstreamFile, int $upstreamLine, string $upstreamTest, callable $run) use (&$tests): void {
    try {
        $run();
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'file' => $upstreamFile,
                'line' => $upstreamLine,
                'test' => $upstreamTest,
                'upstreamStatus' => 'pending',
            ],
            'passed' => true,
        ];
    } catch (Throwable $throwable) {
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'file' => $upstreamFile,
                'line' => $upstreamLine,
                'test' => $upstreamTest,
                'upstreamStatus' => 'pending',
            ],
            'passed' => false,
            'failure' => $throwable->getMessage(),
        ];
    }
};

$ignoredMapped = static function (string $name, string $manifestId, string $upstreamTest, callable $run, string $upstreamFile) use (&$tests): void {
    try {
        $run();
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'id' => $manifestId,
                'file' => $upstreamFile,
                'test' => $upstreamTest,
                'upstreamStatus' => 'ignored',
            ],
            'passed' => true,
        ];
    } catch (Throwable $throwable) {
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'id' => $manifestId,
                'file' => $upstreamFile,
                'test' => $upstreamTest,
                'upstreamStatus' => 'ignored',
            ],
            'passed' => false,
            'failure' => $throwable->getMessage(),
        ];
    }
};

$rustMapped = static function (string $name, string $manifestId, string $upstreamTest, callable $run, string $upstreamFile = 'rust/automerge/tests/batch_insert.rs') use (&$tests): void {
    try {
        $run();
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'id' => $manifestId,
                'file' => $upstreamFile,
                'test' => $upstreamTest,
            ],
            'passed' => true,
        ];
    } catch (Throwable $throwable) {
        $tests[] = [
            'name' => $name,
            'mappedFrom' => [
                'id' => $manifestId,
                'file' => $upstreamFile,
                'test' => $upstreamTest,
            ],
            'passed' => false,
            'failure' => $throwable->getMessage(),
        ];
    }
};

$wordpress = static function (string $name, callable $run) use (&$wordpressScenarios): void {
    try {
        $run();
        $wordpressScenarios[] = [
            'name' => $name,
            'passed' => true,
        ];
    } catch (Throwable $throwable) {
        $wordpressScenarios[] = [
            'name' => $name,
            'passed' => false,
            'failure' => $throwable->getMessage(),
        ];
    }
};

$mapped(
    'init clone and free creates independent PHP document values',
    'javascript/test/basic_test.ts',
    12,
    'should init clone and free',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->clone($doc1);

        truthy($doc1 !== $doc2, 'clone must create a distinct object');
        sameArray($doc1->toArray(), [], 'init should create an empty root object');
        sameArray($doc2->toArray(), [], 'clone should preserve empty root object');
    }
);

$mapped(
    'basic view materializes a document at specific heads',
    'javascript/test/basic_test.ts',
    21,
    'should be able to make a view with specifc heads',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->set($doc1, 'value', 1);
        $heads2 = $port->getHeads($doc2);
        $doc3 = $port->set($doc2, 'value', 2);
        $doc2View = $port->view($doc3, $heads2);
        $doc2Clone = $port->clone($doc2View, 'ddeeff');

        sameArray($doc2View->toArray(), $doc2->toArray(), 'view should materialize the document state at the requested heads');
        sameArray($port->getHeads($doc2View), $heads2, 'view should expose the requested heads');
        sameArray($doc2Clone->toArray(), $doc2->toArray(), 'clone of a view should preserve the view materialization');
        same($port->getActorId($doc2Clone), 'ddeeff', 'clone of a view should accept a new actor id');
    }
);

$mapped(
    'basic clone of a view can be changed independently',
    'javascript/test/basic_test.ts',
    33,
    'should allow you to change a clone of a view',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aabbcc'), 'key', 'value');
        $heads = $port->getHeads($doc1);
        $doc1 = $port->set($doc1, 'key', 'value2');
        $fork = $port->clone($port->view($doc1, $heads), 'ddeeff');

        sameArray($fork->toArray(), ['key' => 'value'], 'clone of a view should preserve the older visible state');
        $fork = $port->set($fork, 'key', 'value3');
        sameArray($fork->toArray(), ['key' => 'value3'], 'clone of a view should accept later native changes');
        sameArray($doc1->toArray(), ['key' => 'value2'], 'changing the view clone should not mutate the source document');
    }
);

$mapped(
    'legacy initialization starts as an empty map',
    'javascript/test/legacy_tests.ts',
    23,
    'should initially be an empty map',
    function () use ($port): void {
        sameArray($port->init('aaaaaa')->toArray(), [], 'init should materialize an empty map');
    }
);

$mapped(
    'legacy initialization accepts an existing object',
    'javascript/test/legacy_tests.ts',
    28,
    'should allow instantiating from an existing object',
    function () use ($port): void {
        $initialState = ['birds' => ['wrens' => 3, 'magpies' => 4]];
        sameArray($port->from($initialState, 'aaaaaa')->toArray(), $initialState, 'from should materialize the supplied root map');
    }
);

$mapped(
    'legacy initialization merges an object initialized with from',
    'javascript/test/legacy_tests.ts',
    34,
    'should allow merging of an object initialized with `from`',
    function () use ($port): void {
        $doc1 = $port->from(['cards' => []], 'aaaaaa');
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);

        sameArray($doc2->toArray(), ['cards' => []], 'merge should preserve the from-created object root');
    }
);

$mapped(
    'legacy initialization preserves actor id from from',
    'javascript/test/legacy_tests.ts',
    40,
    'should allow passing an actorId when instantiating from an existing object',
    function () use ($port): void {
        $doc = $port->from(['foo' => 1], '1234');

        same($port->getActorId($doc), '1234', 'from should preserve the explicit actor id');
    }
);

$mapped(
    'legacy initialization accepts an empty object',
    'javascript/test/legacy_tests.ts',
    46,
    'accepts an empty object as initial state',
    function () use ($port): void {
        sameArray($port->from([], 'aaaaaa')->toArray(), [], 'from should accept an empty initial map');
    }
);

$mapped(
    'legacy initialization converts array input to root map entries',
    'javascript/test/legacy_tests.ts',
    51,
    'accepts an array as initial state, but converts it to an object',
    function () use ($port): void {
        sameArray($port->from(['a', 'b', 'c'], 'aaaaaa')->toArray(), ['a', 'b', 'c'], 'from should keep array values under numeric root keys');
    }
);

$mapped(
    'legacy initialization converts string input to character entries',
    'javascript/test/legacy_tests.ts',
    57,
    'accepts strings as initial values, but treats them as an array of characters',
    function () use ($port): void {
        sameArray($port->from('abc', 'aaaaaa')->toArray(), ['a', 'b', 'c'], 'from should split string initial state into character entries');
    }
);

$mapped(
    'legacy initialization ignores numeric initial values',
    'javascript/test/legacy_tests.ts',
    63,
    'ignores numbers provided as initial values',
    function () use ($port): void {
        sameArray($port->from(123, 'aaaaaa')->toArray(), [], 'from should ignore numeric initial state');
    }
);

$mapped(
    'legacy initialization ignores boolean initial values',
    'javascript/test/legacy_tests.ts',
    69,
    'ignores booleans provided as initial values',
    function () use ($port): void {
        sameArray($port->from(false, 'aaaaaa')->toArray(), [], 'from should ignore false initial state');
        sameArray($port->from(true, 'bbbbbb')->toArray(), [], 'from should ignore true initial state');
    }
);

$mapped(
    'root map set/read materializes PHP array in insertion order',
    'javascript/test/basic_test.ts',
    44,
    'handle basic set and read on root object',
    function () use ($port): void {
        $doc = $port->init('aabbcc');
        $doc = $port->set($doc, 'hello', 'world');
        $doc = $port->set($doc, 'big', 'little');
        $doc = $port->set($doc, 'zip', 'zop');
        $doc = $port->set($doc, 'app', 'dap');

        sameArray(
            $doc->toArray(),
            [
                'hello' => 'world',
                'big' => 'little',
                'zip' => 'zop',
                'app' => 'dap',
            ],
            'root map values should be readable after set'
        );
    }
);

$mapped(
    'legacy sequential changes do not mutate the input document',
    'javascript/test/legacy_tests.ts',
    85,
    'should not mutate objects',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->set($doc1, 'foo', 'bar');

        sameArray($doc1->toArray(), [], 'set should leave the input document unchanged');
        sameArray($doc2->toArray(), ['foo' => 'bar'], 'set should materialize the change on the returned document');
    }
);

$mapped(
    'legacy changes expose the last local change',
    'javascript/test/legacy_tests.ts',
    91,
    'changes should be retrievable',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $change1 = $port->getLastLocalChange($doc1);
        $doc2 = $port->set($doc1, 'foo', 'bar');
        $change2 = $port->decodeChange($port->getLastLocalChange($doc2) ?? []);

        same($change1, null, 'empty document should not have a last local change');
        same($change2['actor'], 'aabbcc', 'last local change should expose its actor');
        same($change2['seq'], 1, 'last local change should expose its sequence');
        same($change2['startOp'], 1, 'last local change should expose its start operation');
        same($change2['message'], null, 'last local change should expose a null message when none was supplied');
        sameArray($change2['deps'], [], 'first local change should have no dependencies');
        truthy(is_string($change2['hash'] ?? null) && $change2['hash'] !== '', 'last local change should expose a hash');
        sameArray(
            $change2['ops'],
            [
                [
                    'action' => 'set',
                    'key' => 'foo',
                    'value' => ['type' => 'scalar', 'value' => 'bar'],
                ],
            ],
            'last local change should expose the native root set operation'
        );
    }
);

$mapped(
    'legacy sequential repeated assignment records no conflicts',
    'javascript/test/legacy_tests.ts',
    135,
    'should not register any conflicts on repeated assignment',
    function () use ($port): void {
        $doc = $port->init('aabbcc');
        same($port->getConflicts($doc, 'foo'), null, 'empty document should report no conflict for an absent key');
        $doc = $port->set($doc, 'foo', 'one');
        same($port->getConflicts($doc, 'foo'), null, 'first assignment should report no conflict');
        $doc = $port->set($doc, 'foo', 'two');
        same($port->getConflicts($doc, 'foo'), null, 'sequential overwrite should report no conflict');
    }
);

$mapped(
    'legacy changes group multiple root assignments',
    'javascript/test/legacy_tests.ts',
    144,
    'should group several changes',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->setMany($doc1, ['first' => 'one', 'second' => 'two'], 'change message');
        $history = $port->getHistory($doc2);

        sameArray($doc1->toArray(), [], 'grouped change should not mutate the input document');
        sameArray($doc2->toArray(), ['first' => 'one', 'second' => 'two'], 'grouped change should materialize all assignments');
        same(count($history), 1, 'grouped assignments should record one native change');
        same($history[0]['change']['message'], 'change message', 'grouped change should preserve its message');
        same(count($history[0]['change']['ops']), 2, 'grouped change should record both root assignment operations');
    }
);

$mapped(
    'legacy sequential repeated writes keep the final value',
    'javascript/test/legacy_tests.ts',
    189,
    'should allow repeated reading and writing of values',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->set($doc1, 'value', 'a');
        same($doc2->toArray()['value'], 'a', 'first write should be readable');
        $doc2 = $port->set($doc2, 'value', 'b');
        $doc2 = $port->set($doc2, 'value', 'c');

        sameArray($doc1->toArray(), [], 'repeated writes should not mutate the input document');
        sameArray($doc2->toArray(), ['value' => 'c'], 'repeated writes should keep the final value');
    }
);

$mapped(
    'legacy sequential same-field writes have no conflicts',
    'javascript/test/legacy_tests.ts',
    201,
    'should not record conflicts when writing the same field several times within one change',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'value', 'a');
        $doc = $port->set($doc, 'value', 'b');
        $doc = $port->set($doc, 'value', 'c');

        same($doc->toArray()['value'], 'c', 'sequential same-field writes should materialize the final value');
        same($port->getConflicts($doc, 'value'), null, 'sequential same-field writes should not produce conflicts');
    }
);

$mapped(
    'legacy sequential no-op change returns the same document',
    'javascript/test/legacy_tests.ts',
    211,
    'should return the unchanged state object if nothing changed',
    function () use ($port): void {
        $doc = $port->init('aabbcc');

        truthy($port->changeNoop($doc) === $doc, 'no-op change should preserve document identity');
    }
);

$mapped(
    'legacy sequential existing-value updates are ignored',
    'javascript/test/legacy_tests.ts',
    216,
    'should ignore field updates that write the existing value',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aabbcc'), 'field', 123);
        $doc2 = $port->set($doc1, 'field', 123);

        truthy($doc2 === $doc1, 'setting an existing identical value should return the same document');
        same(count($port->getAllChanges($doc2)), 1, 'existing-value update should not append a native change');
    }
);

$mapped(
    'legacy root existing-value update resolves conflicts',
    'javascript/test/legacy_tests.ts',
    222,
    'should not ignore field updates that resolve a conflict',
    function () use ($port): void {
        $doc1 = $port->init('bbbbbb');
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);
        $doc1 = $port->set($doc1, 'field', 123);
        $doc2 = $port->set($doc2, 'field', 321);
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $winner = $doc1->toArray()['field'];
        $changeCount = count($port->getAllChanges($doc1));

        same(count($port->getConflicts($doc1, 'field') ?? []), 2, 'concurrent root updates should create two conflict values');
        $resolved = $port->set($doc1, 'field', $winner);

        truthy($resolved !== $doc1, 'writing the visible winner should create a conflict-resolution document');
        sameArray($resolved->toArray(), ['field' => $winner], 'conflict resolution should keep the visible root value');
        same($port->getConflicts($resolved, 'field'), null, 'conflict resolution should clear root conflicts');
        same(count($port->getAllChanges($resolved)), $changeCount + 1, 'conflict resolution should append a native change');
    }
);

$mapped(
    'legacy sequential list existing-value updates are ignored',
    'javascript/test/legacy_tests.ts',
    237,
    'should ignore list element updates that write the existing value',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aabbcc'), 'list', [123]);
        $doc2 = $port->setListElement($doc1, 'list', 0, 123);

        truthy($doc2 === $doc1, 'setting an existing identical list element should return the same document');
        same(count($port->getAllChanges($doc2)), 1, 'existing-value list element update should not append a native change');
    }
);

$mapped(
    'legacy list existing-value update resolves conflicts',
    'javascript/test/legacy_tests.ts',
    243,
    'should not ignore list element updates that resolve a conflict',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'list', [1]);
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);
        $doc1 = $port->setListElement($doc1, 'list', 0, 123);
        $doc2 = $port->setListElement($doc2, 'list', 0, 321);
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $winner = $doc1->toArray()['list'][0];
        $changeCount = count($port->getAllChanges($doc1));

        sameArray(
            $port->getListElementConflicts($doc1, 'list', 0) ?? [],
            [
                '2@aaaaaa' => 123,
                '2@bbbbbb' => 321,
            ],
            'concurrent list element updates should create two conflict values'
        );

        $resolved = $port->setListElement($doc1, 'list', 0, $winner);

        truthy($resolved !== $doc1, 'writing the visible list winner should create a conflict-resolution document');
        sameArray($resolved->toArray(), $doc1->toArray(), 'list conflict resolution should keep the visible list value');
        same($port->getListElementConflicts($resolved, 'list', 0), null, 'list conflict resolution should clear element conflicts');
        same($port->getListElementConflicts($port->load($port->save($resolved)), 'list', 0), null, 'list conflict resolution should survive save/load');
        same(count($port->getAllChanges($resolved)), $changeCount + 1, 'list conflict resolution should append a native change');
    }
);

$mapped(
    'legacy concurrent updates of the same list element expose conflicts',
    'javascript/test/legacy_tests.ts',
    1134,
    'should detect concurrent updates of the same list element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['finch']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->setListElement($doc1, 'birds', 0, 'greenfinch');
        $doc2 = $port->setListElement($doc2, 'birds', 0, 'goldfinch_');
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['birds' => ['greenfinch']], 'larger actor id should win the visible list element value');
        sameArray(
            $port->getListElementConflicts($merged, 'birds', 0) ?? [],
            [
                '2@aaaaaa' => 'goldfinch_',
                '2@bbbbbb' => 'greenfinch',
            ],
            'same-list-element concurrent updates should expose both conflict values'
        );
        sameArray(
            $port->getListElementConflicts($port->load($port->save($merged)), 'birds', 0) ?? [],
            [
                '2@aaaaaa' => 'goldfinch_',
                '2@bbbbbb' => 'greenfinch',
            ],
            'same-list-element conflict values should survive save/load'
        );
    }
);

$mapped(
    'legacy conflicting list element maps retain nested changes',
    'javascript/test/legacy_tests.ts',
    1176,
    'should handle changes within a conflicting list element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['hello']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->setListElement($doc1, 'list', 0, ['map1' => true]);
        $doc1 = $port->setNested($doc1, ['list', 0, 'key'], 1);
        $doc2 = $port->setListElement($doc2, 'list', 0, ['map2' => true]);
        $doc2 = $port->setNested($doc2, ['list', 0, 'key'], 2);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['list' => [['map1' => true, 'key' => 1]]], 'larger actor id should win the visible nested list map value');
        $expectedConflicts = [
            '2@aaaaaa' => ['map2' => true, 'key' => 2],
            '2@bbbbbb' => ['map1' => true, 'key' => 1],
        ];
        sameArray(
            $port->getListElementConflicts($merged, 'list', 0) ?? [],
            $expectedConflicts,
            'changes inside conflicting list element maps should be retained in conflict values'
        );
        sameArray(
            $port->getListElementConflicts($port->load($port->save($merged)), 'list', 0) ?? [],
            $expectedConflicts,
            'conflicting list element map changes should survive save/load'
        );
    }
);

$mapped(
    'legacy concurrent insertions at different list positions merge cleanly',
    'javascript/test/legacy_tests.ts',
    1222,
    'should handle concurrent insertions at different list positions',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['one', 'three']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->insertListElements($doc1, 'list', 1, ['two']);
        $doc2 = $port->pushList($doc2, 'list', ['four']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['list' => ['one', 'two', 'three', 'four']], 'concurrent insertions at different list positions should merge against the shared base');
        same($port->getConflicts($merged, 'list'), null, 'different-position concurrent list insertions should not create a root conflict');
        sameArray($port->load($port->save($merged))->toArray(), ['list' => ['one', 'two', 'three', 'four']], 'merged different-position list insertions should survive save/load');
    }
);

$mapped(
    'legacy concurrent insertions at the same list position merge cleanly',
    'javascript/test/legacy_tests.ts',
    1232,
    'should handle concurrent insertions at the same list position',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['parakeet']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->pushList($doc1, 'birds', ['starling']);
        $doc2 = $port->pushList($doc2, 'birds', ['chaffinch']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['birds' => ['parakeet', 'starling', 'chaffinch']], 'same-position concurrent insertions should keep both inserted values in deterministic order');
        same($port->getConflicts($merged, 'birds'), null, 'same-position concurrent list insertions should not create a root conflict');
        sameArray($port->mergeDocuments($doc2, $merged)->toArray(), $merged->toArray(), 'merging the resolved same-position insertion state back should converge');
        sameArray($port->load($port->save($merged))->toArray(), ['birds' => ['parakeet', 'starling', 'chaffinch']], 'same-position list insertions should survive save/load');
    }
);

$mapped(
    'legacy concurrent assignment and deletion of a map entry is add-wins',
    'javascript/test/legacy_tests.ts',
    1247,
    'should handle concurrent assignment and deletion of a map entry',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'bestBird', 'robin');
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->delete($doc1, 'bestBird');
        $doc2 = $port->set($doc2, 'bestBird', 'magpie');
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($doc1->toArray(), [], 'deleted branch should not retain the removed map entry');
        sameArray($doc2->toArray(), ['bestBird' => 'magpie'], 'assignment branch should materialize the replacement map entry');
        sameArray($merged->toArray(), ['bestBird' => 'magpie'], 'concurrent assignment should win over deletion for map entries');
        same($port->getConflicts($merged, 'bestBird'), null, 'concurrent assignment and deletion should not create a conflict');
        sameArray($port->load($port->save($merged))->toArray(), ['bestBird' => 'magpie'], 'add-wins map entry result should survive save/load');
    }
);

$mapped(
    'legacy concurrent assignment and deletion of a list element is add-wins',
    'javascript/test/legacy_tests.ts',
    1260,
    'should handle concurrent assignment and deletion of a list element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['blackbird', 'thrush', 'goldfinch']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->setListElement($doc1, 'birds', 1, 'starling');
        $doc2 = $port->deleteListElements($doc2, 'birds', 1);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($doc1->toArray()['birds'], ['blackbird', 'starling', 'goldfinch'], 'assignment branch should replace the middle list element');
        sameArray($doc2->toArray()['birds'], ['blackbird', 'goldfinch'], 'delete branch should remove the middle list element');
        sameArray($merged->toArray()['birds'], ['blackbird', 'starling', 'goldfinch'], 'concurrent assignment should resurrect the deleted list element');
        same($port->getConflicts($merged, 'birds'), null, 'concurrent list assignment and deletion should not create a root conflict');
        same($port->getListElementConflicts($merged, 'birds', 1), null, 'concurrent list assignment and deletion should not create element conflicts');
        sameArray($port->load($port->save($merged))->toArray()['birds'], ['blackbird', 'starling', 'goldfinch'], 'add-wins list element result should survive save/load');
    }
);

$mapped(
    'legacy insertion after a concurrently deleted list element survives',
    'javascript/test/legacy_tests.ts',
    1278,
    'should handle insertion after a deleted list element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['blackbird', 'thrush', 'goldfinch']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->deleteListElements($doc1, 'birds', 1, 2);
        $doc2 = $port->insertListElements($doc2, 'birds', 2, ['starling']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['birds' => ['blackbird', 'starling']], 'insertion after deleted list elements should survive without resurrecting deleted base values');
        sameArray($port->mergeDocuments($doc2, $merged)->toArray(), ['birds' => ['blackbird', 'starling']], 'merging the insertion branch with the merged document should remain stable');
        same($port->getConflicts($merged, 'birds'), null, 'insertion after deleted list elements should not create a root conflict');
    }
);

$mapped(
    'legacy concurrent deletion of the same list element is idempotent',
    'javascript/test/legacy_tests.ts',
    1293,
    'should handle concurrent deletion of the same element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['albatross', 'buzzard', 'cormorant']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->deleteListElements($doc1, 'birds', 1);
        $doc2 = $port->deleteListElements($doc2, 'birds', 1);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['birds'], ['albatross', 'cormorant'], 'concurrent deletion of the same original element should delete it once');
        same($port->getConflicts($merged, 'birds'), null, 'concurrent deletion of the same list element should not create a root conflict');
    }
);

$mapped(
    'legacy concurrent deletion of different list elements removes both originals',
    'javascript/test/legacy_tests.ts',
    1305,
    'should handle concurrent deletion of different elements',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['albatross', 'buzzard', 'cormorant']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->deleteListElements($doc1, 'birds', 0);
        $doc2 = $port->deleteListElements($doc2, 'birds', 1);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['birds'], ['cormorant'], 'concurrent deletion of different original elements should remove both');
        same($port->getConflicts($merged, 'birds'), null, 'concurrent deletion of different list elements should not create a root conflict');
    }
);

$mapped(
    'legacy concurrent sequence insertions at the same position stay grouped',
    'javascript/test/legacy_tests.ts',
    1355,
    'should not interleave sequence insertions at the same position',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'wisdom', []);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->insertListElements($doc1, 'wisdom', 0, ['to', 'be', 'is', 'to', 'do']);
        $doc2 = $port->insertListElements($doc2, 'wisdom', 0, ['to', 'do', 'is', 'to', 'be']);
        $merged = $port->mergeDocuments($doc1, $doc2);
        $wisdom = $merged->toArray()['wisdom'];

        $leftFirst = ['to', 'be', 'is', 'to', 'do', 'to', 'do', 'is', 'to', 'be'];
        $rightFirst = ['to', 'do', 'is', 'to', 'be', 'to', 'be', 'is', 'to', 'do'];
        truthy($wisdom === $leftFirst || $wisdom === $rightFirst, 'concurrent insertion groups at the same position should not interleave');
        same($port->getConflicts($merged, 'wisdom'), null, 'grouped concurrent insertions should not create a root conflict');
    }
);

$mapped(
    'legacy list insertion works when the inserting actor id is greater',
    'javascript/test/legacy_tests.ts',
    1374,
    'should handle insertion by greater actor ID',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaa'), 'list', ['two']);
        $doc2 = $port->mergeDocuments($port->init('bbbb'), $doc1);
        $doc2 = $port->insertListElements($doc2, 'list', 0, ['one']);

        sameArray($doc2->toArray()['list'], ['one', 'two'], 'greater actor id insertion should appear before the existing element');
    }
);

$mapped(
    'legacy list insertion works when the inserting actor id is lesser',
    'javascript/test/legacy_tests.ts',
    1383,
    'should handle insertion by lesser actor ID',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbb'), 'list', ['two']);
        $doc2 = $port->mergeDocuments($port->init('aaaa'), $doc1);
        $doc2 = $port->insertListElements($doc2, 'list', 0, ['one']);

        sameArray($doc2->toArray()['list'], ['one', 'two'], 'lesser actor id insertion should appear before the existing element');
    }
);

$mapped(
    'legacy list insertion before an existing element is actor-id independent',
    'javascript/test/legacy_tests.ts',
    1392,
    'should handle insertion regardless of actor ID',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['two']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);
        $doc2 = $port->insertListElements($doc2, 'list', 0, ['one']);

        sameArray($doc2->toArray()['list'], ['one', 'two'], 'list insertion before an existing element should not depend on actor id');
    }
);

$mapped(
    'legacy causal list prepends maintain insertion order',
    'javascript/test/legacy_tests.ts',
    1399,
    'should make insertion order consistent with causality',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['four']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc2 = $port->insertListElements($doc2, 'list', 0, ['three']);
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $doc1 = $port->insertListElements($doc1, 'list', 0, ['two']);
        $doc2 = $port->mergeDocuments($doc2, $doc1);
        $doc2 = $port->insertListElements($doc2, 'list', 0, ['one']);

        sameArray($doc2->toArray()['list'], ['one', 'two', 'three', 'four'], 'causally ordered list prepends should materialize in causal order');
    }
);

$mapped(
    'legacy delete higher in a tree wins over a concurrent subtree update',
    'javascript/test/legacy_tests.ts',
    1317,
    'should handle concurrent updates at different levels of the tree',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'animals', [
            'birds' => ['pink' => 'flamingo', 'black' => 'starling'],
            'mammals' => ['badger'],
        ]);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->setNested($doc1, ['animals', 'birds', 'brown'], 'sparrow');
        $doc2 = $port->deleteNested($doc2, ['animals', 'birds']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray(
            $doc1->toArray()['animals'],
            ['birds' => ['pink' => 'flamingo', 'black' => 'starling', 'brown' => 'sparrow'], 'mammals' => ['badger']],
            'subtree update branch should retain the added nested bird'
        );
        sameArray($doc2->toArray()['animals'], ['mammals' => ['badger']], 'delete branch should remove the nested birds map');
        sameArray($merged->toArray()['animals'], ['mammals' => ['badger']], 'delete of a higher tree level should win over concurrent subtree update');
        same($port->getConflicts($merged, 'animals'), null, 'higher-level delete should not create a root conflict');
    }
);

$mapped(
    'legacy updates inside concurrently deleted objects do not resurrect the object',
    'javascript/test/legacy_tests.ts',
    1343,
    'should handle updates of concurrently deleted objects',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['blackbird' => ['feathers' => 'black']]);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->deleteNested($doc1, ['birds', 'blackbird']);
        $doc2 = $port->setNested($doc2, ['birds', 'blackbird', 'beak'], 'orange');
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($doc1->toArray(), ['birds' => []], 'delete branch should remove the nested object');
        sameArray($merged->toArray(), ['birds' => []], 'concurrent update under a deleted object should not resurrect it');
        same($port->getConflicts($merged, 'birds'), null, 'concurrently deleted object should not create a root conflict');
    }
);

$mapped(
    'legacy change API sanity-checks document root arguments',
    'javascript/test/legacy_tests.ts',
    259,
    'should sanity-check arguments',
    function () use ($port): void {
        $doc = $port->change(
            $port->init('aabbcc'),
            static function (Document $draft): void {
                $draft->set('nested', []);
            }
        );
        $nested = $doc->toArray()['nested'];

        throwsLike(
            static fn () => $port->change([], static fn (Document $draft): Document => $draft),
            'must be the document root',
            'change should reject a plain PHP array as a root argument'
        );
        throwsLike(
            static fn () => $port->change($nested, static fn (Document $draft): Document => $draft),
            'must be the document root',
            'change should reject a nested materialized object as a root argument'
        );
    }
);

$mapped(
    'legacy change API rejects nested and outdated change attempts',
    'javascript/test/legacy_tests.ts',
    271,
    'should not allow nested change blocks',
    function () use ($port): void {
        $doc = $port->init('aabbcc');

        throwsLike(
            static function () use ($port, $doc): void {
                $port->change(
                    $doc,
                    static function (Document $draft) use ($port): void {
                        $port->change(
                            $draft,
                            static function (Document $nestedDraft): void {
                                $nestedDraft->set('foo', 'bar');
                            }
                        );
                    }
                );
            },
            'Calls to Automerge.change cannot be nested',
            'change should reject nested change calls on the active draft'
        );

        throwsLike(
            static function () use ($port, $doc): void {
                $port->change(
                    $doc,
                    static function (Document $draft) use ($port, $doc): void {
                        $port->change(
                            $doc,
                            static function (Document $otherDraft): void {
                                $otherDraft->set('two', 2);
                            }
                        );
                        $draft->set('one', 1);
                    }
                );
            },
            'Attempting to change an outdated document',
            'change should reject reusing the base document while a draft is active'
        );
    }
);

$mapped(
    'legacy change API rejects reusing the same base document',
    'javascript/test/legacy_tests.ts',
    288,
    'should not allow the same base document to be used for multiple changes',
    function () use ($port): void {
        $doc = $port->init('aabbcc');
        $changed = $port->change(
            $doc,
            static function (Document $draft): void {
                $draft->set('one', 1);
            }
        );

        same($changed->toArray(), ['one' => 1], 'first change should materialize the base edit');
        throwsLike(
            static fn () => $port->change(
                $doc,
                static function (Document $draft): void {
                    $draft->set('two', 2);
                }
            ),
            'Attempting to change an outdated document',
            'change should reject reusing a previously changed base document'
        );
    }
);

$mapped(
    'error handling throws an exception object for invalid list assignment',
    'javascript/test/error.ts',
    5,
    'proxy handler throws an error, not a string',
    function () use ($port): void {
        $doc = $port->from(['d' => ['test']], 'aabbcc');
        try {
            $port->setListElement($doc, 'd', 2, 'oops');
        } catch (Throwable $throwable) {
            truthy($throwable instanceof OutOfBoundsException, 'invalid list assignment should throw a native exception object');
            same($throwable->getMessage(), 'List assignment index is out of bounds.', 'invalid list assignment should explain the bounds failure');
            return;
        }

        throw new RuntimeException('invalid list assignment did not throw');
    }
);

$mapped(
    'legacy sequential clone can diverge independently',
    'javascript/test/legacy_tests.ts',
    295,
    'should allow a document to be cloned',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aabbcc'), 'zero', 0);
        $doc2 = $port->clone($doc1, 'ddeeff');
        $doc1 = $port->set($doc1, 'one', 1);
        $doc2 = $port->set($doc2, 'two', 2);

        sameArray($doc1->toArray(), ['zero' => 0, 'one' => 1], 'original branch should keep its own later write');
        sameArray($doc2->toArray(), ['zero' => 0, 'two' => 2], 'cloned branch should keep its own later write');
    }
);

$mapped(
    'legacy sequential object assign style replacement works',
    'javascript/test/legacy_tests.ts',
    306,
    'should work with Object.assign merges',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'stuff', ['foo' => 'bar', 'baz' => 'blur']);
        $stuff = $doc->toArray()['stuff'];
        $stuff['baz'] = 'updated!';
        $doc = $port->set($doc, 'stuff', $stuff);

        sameArray($doc->toArray(), ['stuff' => ['foo' => 'bar', 'baz' => 'updated!']], 'object replacement should preserve untouched keys and update changed keys');
    }
);

$mapped(
    'legacy sequential supports Date objects in maps',
    'javascript/test/legacy_tests.ts',
    316,
    'should support Date objects in maps',
    function () use ($port): void {
        $now = new DateTimeImmutable('2026-05-22T01:02:03.456Z');
        $doc = $port->set($port->init('aabbcc'), 'now', $now);
        $applied = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc));
        $materialized = $applied->toArray()['now'];

        truthy($materialized instanceof DateTimeInterface, 'replayed map date should materialize as a DateTime value');
        same(dateMillis($materialized), dateMillis($now), 'replayed map date should preserve milliseconds');
    }
);

$mapped(
    'legacy sequential supports Date objects in lists',
    'javascript/test/legacy_tests.ts',
    325,
    'should support Date objects in lists',
    function () use ($port): void {
        $now = new DateTimeImmutable('2026-05-22T01:02:03.789Z');
        $doc = $port->set($port->init('aabbcc'), 'list', [$now]);
        $applied = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc));
        $materialized = $applied->toArray()['list'][0];

        truthy($materialized instanceof DateTimeInterface, 'replayed list date should materialize as a DateTime value');
        same(dateMillis($materialized), dateMillis($now), 'replayed list date should preserve milliseconds');
    }
);

$mapped(
    'legacy patch callback receives list assignment patches',
    'javascript/test/legacy_tests.ts',
    334,
    'should call patchCallback if supplied',
    function () use ($port): void {
        $callbacks = [];
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->setWithPatchCallback(
            $doc1,
            'birds',
            ['Goldfinch'],
            static function (array $patches, array $info) use (&$callbacks): void {
                $callbacks[] = [
                    'patches' => $patches,
                    'before' => $info['before'],
                    'after' => $info['after'],
                ];
            }
        );

        same(count($callbacks), 1, 'explicit patch callback should be called exactly once');
        sameArray(
            $callbacks[0]['patches'],
            [
                ['action' => 'put', 'path' => ['birds'], 'value' => []],
                ['action' => 'insert', 'path' => ['birds', 0], 'values' => ['']],
                ['action' => 'splice', 'path' => ['birds', 0, 0], 'value' => 'Goldfinch'],
            ],
            'list assignment should emit the expected put/insert/splice patches'
        );
        sameArray($callbacks[0]['before']->toArray(), [], 'patch callback should receive the pre-change document');
        sameArray($callbacks[0]['after']->toArray(), $doc2->toArray(), 'patch callback should receive the post-change document');
        sameArray($doc2->toArray(), ['birds' => ['Goldfinch']], 'list assignment should still materialize the assigned value');
    }
);

$mapped(
    'legacy initialization-level patch callback receives string assignment patches',
    'javascript/test/legacy_tests.ts',
    374,
    'should call a patchCallback set up on document initialisation',
    function () use ($port): void {
        $callbacks = [];
        $callbackPort = $port->withPatchCallback(
            static function (array $patches, array $info) use (&$callbacks): void {
                $callbacks[] = [
                    'patches' => $patches,
                    'before' => $info['before'],
                    'after' => $info['after'],
                ];
            }
        );

        $doc1 = $callbackPort->init('aabbcc');
        $doc2 = $callbackPort->set($doc1, 'bird', 'Goldfinch');

        same(count($callbacks), 1, 'initialization-level patch callback should be called once');
        sameArray(
            $callbacks[0]['patches'],
            [
                ['action' => 'put', 'path' => ['bird'], 'value' => ''],
                ['action' => 'splice', 'path' => ['bird', 0], 'value' => 'Goldfinch'],
            ],
            'string assignment should emit the expected put/splice patches'
        );
        sameArray($callbacks[0]['before']->toArray(), [], 'initial patch callback should receive the pre-change document');
        sameArray($callbacks[0]['after']->toArray(), $doc2->toArray(), 'initial patch callback should receive the post-change document');
        sameArray($doc2->toArray(), ['bird' => 'Goldfinch'], 'string assignment should still materialize the assigned value');
    }
);

$mapped(
    'legacy load invokes a supplied patch callback',
    'javascript/test/legacy_tests.ts',
    1570,
    'should call patchCallback if supplied to load',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'birds', ['Goldfinch']);
        $doc = $port->pushList($doc, 'birds', ['Chaffinch']);
        $callbacks = [];

        $loaded = $port->loadWithPatchCallback(
            $port->save($doc),
            static function (array $patches, array $info) use (&$callbacks): void {
                $callbacks[] = [
                    'patches' => $patches,
                    'before' => $info['before'],
                    'after' => $info['after'],
                    'source' => $info['source'] ?? null,
                ];
            },
            'cccccc'
        );

        same(count($callbacks), 1, 'load patch callback should be called once');
        sameArray(
            $callbacks[0]['patches'],
            [
                ['action' => 'put', 'path' => ['birds'], 'value' => []],
                ['action' => 'insert', 'path' => ['birds', 0], 'values' => ['', '']],
                ['action' => 'splice', 'path' => ['birds', 0, 0], 'value' => 'Goldfinch'],
                ['action' => 'splice', 'path' => ['birds', 1, 0], 'value' => 'Chaffinch'],
            ],
            'load callback should receive patches needed to construct the loaded document'
        );
        sameArray($callbacks[0]['before']->toArray(), [], 'load callback before document should be empty');
        sameArray($callbacks[0]['after']->toArray(), $loaded->toArray(), 'load callback after document should be the loaded document');
        same($callbacks[0]['source'], 'load', 'load callback should identify the patch source');
        sameArray($loaded->toArray(), ['birds' => ['Goldfinch', 'Chaffinch']], 'loaded document should materialize both list entries');
    }
);

$mapped(
    'legacy applyChanges invokes a supplied patch callback',
    'javascript/test/legacy_tests.ts',
    1780,
    'should call patchCallback if supplied when applying changes',
    function () use ($port): void {
        $source = $port->set($port->init('aabbcc'), 'birds', ['Goldfinch']);
        $callbacks = [];

        $after = $port->applyChangesWithPatchCallback(
            $port->init('bbbbbb'),
            $port->getAllChanges($source),
            static function (array $patches, array $info) use (&$callbacks): void {
                $callbacks[] = [
                    'patches' => $patches,
                    'before' => $info['before'],
                    'after' => $info['after'],
                    'source' => $info['source'] ?? null,
                ];
            }
        );

        same(count($callbacks), 1, 'applyChanges patch callback should be called once');
        sameArray(
            $callbacks[0]['patches'],
            [
                ['action' => 'put', 'path' => ['birds'], 'value' => []],
                ['action' => 'insert', 'path' => ['birds', 0], 'values' => ['']],
                ['action' => 'splice', 'path' => ['birds', 0, 0], 'value' => 'Goldfinch'],
            ],
            'applyChanges callback should receive patches needed to apply the incoming change'
        );
        sameArray($callbacks[0]['before']->toArray(), [], 'applyChanges callback before document should be unchanged');
        sameArray($callbacks[0]['after']->toArray(), $after->toArray(), 'applyChanges callback after document should be the applied document');
        same($callbacks[0]['source'], 'applyChanges', 'applyChanges callback should identify the patch source');
        sameArray($after->toArray(), ['birds' => ['Goldfinch']], 'applied document should materialize the incoming list value');
    }
);

$mapped(
    'legacy applyChanges merges multiple string-list changes into one patch batch',
    'javascript/test/legacy_tests.ts',
    1820,
    'should merge multiple applied changes into one patch',
    function () use ($port): void {
        $source = $port->set($port->init('aabbcc'), 'birds', ['Goldfinch']);
        $source = $port->pushList($source, 'birds', ['Chaffinch']);
        $patches = [];

        $port->applyChangesWithPatchCallback(
            $port->init('bbbbbb'),
            $port->getAllChanges($source),
            static function (array $patchBatch) use (&$patches): void {
                array_push($patches, ...$patchBatch);
            }
        );

        sameArray(
            $patches,
            [
                ['action' => 'put', 'path' => ['birds'], 'value' => []],
                ['action' => 'insert', 'path' => ['birds', 0], 'values' => ['', '']],
                ['action' => 'splice', 'path' => ['birds', 0, 0], 'value' => 'Goldfinch'],
                ['action' => 'splice', 'path' => ['birds', 1, 0], 'value' => 'Chaffinch'],
            ],
            'applyChanges should report one coalesced string-list patch batch'
        );
    }
);

$mapped(
    'legacy applyChanges invokes patch callback registered on document initialization',
    'javascript/test/legacy_tests.ts',
    1838,
    'should call a patchCallback registered on doc initialisation',
    function () use ($port): void {
        $source = $port->set($port->init('aabbcc'), 'bird', 'Goldfinch');
        $patches = [];
        $before = $port->initWithPatchCallback(
            static function (array $patchBatch) use (&$patches): void {
                array_push($patches, ...$patchBatch);
            },
            'bbbbbb'
        );

        $after = $port->applyChanges($before, $port->getAllChanges($source));

        sameArray(
            $patches,
            [
                ['action' => 'put', 'path' => ['bird'], 'value' => ''],
                ['action' => 'splice', 'path' => ['bird', 0], 'value' => 'Goldfinch'],
            ],
            'document initialization patch callback should observe applied changes'
        );
        sameArray($after->toArray(), ['bird' => 'Goldfinch'], 'applyChanges should still materialize the applied scalar string');
    }
);

$mapped(
    'root map delete keeps only the live property across repeated changes',
    'javascript/test/basic_test.ts',
    66,
    'should be able to insert and delete a large number of properties',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'k1', true);

        for ($idx = 1; $idx <= 200; ++$idx) {
            $doc = $port->delete($doc, 'k' . $idx);
            $doc = $port->set($doc, 'k' . ($idx + 1), true);
            same(count($doc->toArray()), 1, 'only one property should remain live after each delete/set');
        }

        sameArray($doc->toArray(), ['k201' => true], 'last live property should be k201');
    }
);

$mapped(
    'basic isAutomerge detects only native documents',
    'javascript/test/basic_test.ts',
    82,
    'can detect an automerge doc with isAutomerge()',
    function () use ($port): void {
        $doc = $port->from(['sub' => ['object' => true]], 'aaaaaa');
        $jsObject = $port->toJS($doc);

        truthy($port->isAutomerge($doc), 'native Document should be reported as Automerge data');
        truthy(! $port->isAutomerge($doc->toArray()['sub']), 'materialized sub-objects should not be reported as Automerge documents');
        truthy(! $port->isAutomerge('String'), 'strings should not be reported as Automerge documents');
        truthy(! $port->isAutomerge(['sub' => ['object' => true]]), 'plain arrays should not be reported as Automerge documents');
        truthy(! $port->isAutomerge(null), 'null should not be reported as Automerge data');
        truthy(! $port->isAutomerge($jsObject), 'materialized arrays should not be reported as Automerge documents');
        sameArray($jsObject, $doc->toArray(), 'toJS should materialize the document as plain PHP arrays and scalars');
    }
);

$mapped(
    'basic freeze option recursively marks document materialization frozen',
    'javascript/test/basic_test.ts',
    94,
    'it should recursively freeze the document if requested',
    function () use ($port): void {
        $doc1 = $port->initFrozen('aabbcc');
        $doc2 = $port->init('ddeeff');

        truthy($doc1->isFrozen(), 'init with freeze should mark the document frozen');
        truthy(! $doc2->isFrozen(), 'plain init should not mark the document frozen');

        $doc1 = $port->change(
            $doc1,
            static function (Document $draft): void {
                $draft->set('book', ['title' => 'how to win friends']);
            }
        );
        $doc2 = $port->mergeDocuments($doc2, $doc1);

        truthy($doc1->isFrozen(), 'change should preserve the frozen flag on the returned document');
        truthy($doc1->isFrozenPath(['book']), 'freeze should cover nested materialized map values');
        truthy(! $doc2->isFrozen(), 'merge into a non-frozen document should keep the destination non-frozen');
        truthy(! $doc2->isFrozenPath(['book']), 'merge into a non-frozen document should not freeze nested values');

        $doc3 = $port->fromFrozen(['sub' => ['obj' => 'inner']], '112233');
        truthy($doc3->isFrozen(), 'from with freeze should mark the document frozen');
        truthy($doc3->isFrozenPath(['sub']), 'from with freeze should mark nested maps frozen');

        $doc4 = $port->loadFrozen($port->save($doc3), '445566');
        truthy($doc4->isFrozen(), 'load with freeze should mark the document frozen');
        truthy($doc4->isFrozenPath(['sub']), 'load with freeze should mark nested maps frozen');

        $doc5 = $port->clone($doc4, '778899');
        truthy($doc5->isFrozen(), 'clone should preserve the frozen flag');
        truthy($doc5->isFrozenPath(['sub']), 'clone should preserve recursive frozen state');

        $jsObject = $port->toJS($doc5);
        truthy(is_array($jsObject), 'toJS should return a plain PHP array');
        truthy(! $port->isAutomerge($jsObject), 'toJS output should not be an Automerge document');
        sameArray($jsObject, ['sub' => ['obj' => 'inner']], 'toJS should preserve frozen document content');
    }
);

$mapped(
    'legacy freeze rejects direct document mutation outside change',
    'javascript/test/legacy_tests.ts',
    158,
    'should freeze objects if desired',
    function () use ($port): void {
        $doc1 = $port->initFrozen('aabbcc');
        $doc2 = $port->change(
            $doc1,
            static function (Document $draft): void {
                $draft->set('foo', 'bar');
            }
        );

        throwsLike(
            static fn () => $doc2->set('foo', 'lemon'),
            'frozen document cannot be modified directly',
            'direct root assignment on a frozen document should be rejected'
        );
        same($doc2->toArray()['foo'], 'bar', 'failed direct assignment should leave the frozen value unchanged');

        throwsLike(
            static fn () => $doc2->delete('foo'),
            'frozen document cannot be modified directly',
            'direct root deletion on a frozen document should be rejected'
        );
        same($doc2->toArray()['foo'], 'bar', 'failed direct deletion should leave the frozen value unchanged');

        $port->change(
            $doc2,
            static function (Document $draft) use ($doc2): void {
                throwsLike(
                    static fn () => $doc2->set('foo', 'lemon'),
                    'frozen document cannot be modified directly',
                    'direct mutation of the frozen base inside change should still be rejected'
                );
                same($draft->toArray()['foo'], 'bar', 'change draft should be a mutable copy of the frozen base');
            }
        );

        throwsLike(
            static fn () => $doc2->setMany(['x' => 4]),
            'frozen document cannot be modified directly',
            'bulk direct assignment on a frozen document should be rejected'
        );
        truthy(! array_key_exists('x', $doc2->toArray()), 'failed bulk assignment should not add a root key');
    }
);

$mapped(
    'basic root sets over many changes preserve scalar and typed values',
    'javascript/test/basic_test.ts',
    132,
    'handle basic sets over many changes',
    function () use ($port): void {
        $timestamp = new DateTimeImmutable('2026-05-22T03:51:00.123Z');
        $counter = new Counter(100);
        $bytes = new BytesValue([10, 11, 12]);
        $doc = $port->init('aabbcc');
        $doc = $port->set($doc, 'hello', 'world');
        $doc = $port->set($doc, 'counter1', $counter);
        $doc = $port->set($doc, 'timestamp1', $timestamp);
        $doc = $port->set($doc, 'app', null);
        $doc = $port->set($doc, 'bytes1', $bytes);
        $doc = $port->setMany($doc, [
            'uint' => 1,
            'int' => -1,
            'float64' => 5.5,
            'number1' => 100,
            'number2' => -45.67,
            'true' => true,
            'false' => false,
        ]);
        $materialized = $doc->toArray();

        same($materialized['hello'], 'world', 'string root value should survive many changes');
        truthy($materialized['counter1'] instanceof Counter, 'counter root value should remain a native Counter');
        same($materialized['counter1']->value(), 100, 'counter root value should preserve its value');
        truthy($materialized['timestamp1'] instanceof DateTimeInterface, 'timestamp root value should remain a DateTime value');
        same(dateMillis($materialized['timestamp1']), dateMillis($timestamp), 'timestamp root value should preserve milliseconds');
        same($materialized['app'], null, 'null root value should survive many changes');
        truthy($materialized['bytes1'] instanceof BytesValue, 'byte-array root value should stay a native BytesValue');
        sameArray($materialized['bytes1']->bytes(), $bytes->bytes(), 'byte-array root value should survive many changes');
        same($materialized['uint'], 1, 'uint-like root value should be readable');
        same($materialized['int'], -1, 'int-like root value should be readable');
        same($materialized['float64'], 5.5, 'float-like root value should be readable');
        same($materialized['number1'], 100, 'positive number root value should be readable');
        same($materialized['number2'], -45.67, 'negative float root value should be readable');
        same($materialized['true'], true, 'true root value should be readable');
        same($materialized['false'], false, 'false root value should be readable');
    }
);

$mapped(
    'basic object ids return null for scalar-like values',
    'javascript/test/basic_test.ts',
    558,
    'should return null for scalar values',
    function () use ($port): void {
        $doc = $port->from([
            'string' => 'string',
            'number' => 1,
            'null' => null,
            'date' => new DateTimeImmutable('2026-05-22T03:52:00Z'),
            'counter' => new Counter(),
            'bytes' => new BytesValue(array_fill(0, 10, 0)),
        ], 'aaaaaa');
        $root = $doc->toArray();

        same($port->getObjectId($root['string']), null, 'string values should not have object ids');
        same($port->getObjectId($root['number']), null, 'number values should not have object ids');
        same($port->getObjectId($root['null']), null, 'null values should not have object ids');
        same($port->getObjectId($root['date']), null, 'date values should not have object ids');
        same($port->getObjectId($root['counter']), null, 'counter values should not have object ids');
        same($port->getObjectId($root['bytes']), null, 'bytes values should not have object ids');
    }
);

$mapped(
    'basic object id returns root for a native document',
    'javascript/test/basic_test.ts',
    567,
    'should return _root for the root object',
    function () use ($port): void {
        $doc = $port->from(['map' => []], 'aaaaaa');

        same($port->getObjectId($doc), '_root', 'native document root should expose the root object id');
    }
);

$mapped(
    'basic object ids distinguish containers from text scalars',
    'javascript/test/basic_test.ts',
    571,
    'should return non-null for map, list, text, and objects',
    function () use ($port): void {
        $doc = $port->from([
            'text' => '',
            'list' => [],
            'map' => ['nested' => true],
        ], 'aaaaaa');
        $root = $doc->toArray();

        same($port->getObjectId($root['text']), null, 'materialized text strings should not expose object ids in the native PHP API');
        truthy($port->getObjectId($root['list']) !== null, 'list containers should expose a native container object id');
        truthy($port->getObjectId($root['map']) !== null, 'map containers should expose a native container object id');
    }
);

$mapped(
    'block split inserts a block boundary and exposes spans',
    'javascript/test/block_test.ts',
    18,
    'can split a block',
    function () use ($port): void {
        $block = ['parents' => ['div'], 'type' => 'p', 'attrs' => []];
        $callbacks = [];
        $callbackPort = $port->withPatchCallback(static function (array $patches) use (&$callbacks): void {
            $callbacks[] = $patches;
        });

        $doc = $port->from(['text' => 'aaabbbccc']);
        $doc = $callbackPort->splitBlock($doc, ['text'], 3, $block);
        $loaded = $port->load($port->save($doc));

        same($doc->toArray()['text'], "aaa\u{FFFC}bbbccc", 'splitBlock should insert the object replacement character');
        sameArray($loaded->toArray(), $doc->toArray(), 'split block should round-trip through save/load');
        sameArray($port->block($loaded, ['text'], 3) ?? [], $block, 'block metadata should survive save/load');
        sameArray($callbacks[0] ?? [], [[
            'action' => 'insert',
            'path' => ['text', 3],
            'values' => [[]],
        ]], 'splitBlock should emit the upstream insert patch shape');
        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'aaa'],
            ['type' => 'block', 'value' => $block],
            ['type' => 'text', 'value' => 'bbbccc'],
        ], 'spans should expose text and block segments');

        $doc = $port->splice($doc, 'text', 7, 0, 'ADD');
        $doc = $port->splice($doc, 'text', 0, 7, 'REMOVE');
        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'REMOVEADDccc'],
        ], 'splicing across the block should remove the block span');
    }
);

$rustMapped(
    'rust marks in spans cross block markers',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:marks-in-spans-cross-block-markers',
    'marks_in_spans_cross_block_markers',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'lix');
        $doc = $port->mark($doc, ['text'], 0, 3, 'bold', true, 'after');
        $doc = $port->splitBlock($doc, ['text'], 1, []);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'l', 'marks' => ['bold' => true]],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
            ['type' => 'text', 'value' => 'ix', 'marks' => ['bold' => true]],
        ], 'mark coverage should continue across a block marker in spans');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust block diff emits block insertion updates',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:diff-emits-block-updates',
    'diff_emits_block_updates',
    function () use ($port): void {
        $block = ['parents' => [], 'type' => '', 'attrs' => []];
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, $block);
        $heads = $port->getHeads($doc);

        $expected = [
            ['action' => 'put', 'path' => ['text'], 'value' => ''],
            [
                'action' => 'splice',
                'path' => ['text', 0],
                'value' => "\u{FFFC}",
                'marks' => ['__automerge_block' => $block],
            ],
        ];

        sameArray($port->diff($doc, [], $heads), $expected, 'diff from empty heads should emit text creation and block insertion');

        $advanced = $port->splice($doc, 'text', 0, 0, 'hello world');
        sameArray($port->diff($advanced, [], $heads), $expected, 'diff to historical block heads should ignore later text changes');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust merge produces block insertion diffs',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:merge-produces-block-insertion-diffs',
    'merge_produces_block_insertion_diffs',
    function () use ($port): void {
        $block = ['parents' => [], 'type' => '', 'attrs' => []];
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $peer = $port->clone($doc, 'bbbbbb');
        $doc = $port->splitBlock($doc, ['text'], 0, $block);
        $headsBefore = $port->getHeads($peer);

        $merged = $port->mergeDocuments($peer, $doc);
        $patches = $port->diff($merged, $headsBefore, $port->getHeads($merged));

        $blockInsertion = array_values(array_filter(
            $patches,
            static fn (array $patch): bool => ($patch['action'] ?? null) === 'splice'
                && ($patch['path'] ?? null) === ['text', 0]
                && ($patch['value'] ?? null) === "\u{FFFC}"
                && ($patch['marks']['__automerge_block'] ?? null) === $block
        ));

        same(count($blockInsertion), 1, 'merge diff should expose the incoming block insertion exactly once');
        sameArray($port->spans($merged, ['text']), [
            ['type' => 'block', 'value' => $block],
        ], 'merged document should materialize the incoming block span');
    },
    'rust/automerge/tests/block_tests.rs'
);

$mapped(
    'block join removes a block boundary',
    'javascript/test/block_test.ts',
    61,
    'can join a block',
    function () use ($port): void {
        $block = ['parents' => ['div'], 'type' => 'p', 'attrs' => []];

        $doc = $port->from(['text' => 'aaabbbccc']);
        $doc = $port->splitBlock($doc, ['text'], 3, $block);
        $doc = $port->joinBlock($doc, ['text'], 3);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'aaabbbccc'],
        ], 'joinBlock should remove the block boundary and restore plain text spans');
        same($port->block($doc, ['text'], 3), null, 'joinBlock should remove block metadata at the joined offset');
    }
);

$mapped(
    'block updateSpans replaces text and all block spans',
    'javascript/test/block_test.ts',
    81,
    'allows updating all blocks at once',
    function () use ($port): void {
        $doc = $port->from(['text' => '']);
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'parents' => [],
            'type' => 'ordered-list-item',
            'attrs' => [],
        ]);
        $doc = $port->splice($doc, 'text', 1, 0, 'first thing');
        $doc = $port->splitBlock($doc, ['text'], 7, [
            'parents' => [],
            'type' => 'ordered-list-item',
            'attrs' => [],
        ]);
        $doc = $port->splice($doc, 'text', 8, 0, 'second thing');

        $paragraph = ['parents' => [], 'type' => 'paragraph', 'attrs' => []];
        $nestedList = [
            'parents' => ['ordered-list-item'],
            'type' => 'unordered-list-item',
            'attrs' => [],
        ];
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'paragraph', 'parents' => [], 'attrs' => []]],
            ['type' => 'text', 'value' => 'the first thing'],
            ['type' => 'block', 'value' => [
                'type' => 'unordered-list-item',
                'parents' => ['ordered-list-item'],
                'attrs' => [],
            ]],
            ['type' => 'text', 'value' => 'the second thing'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => $paragraph],
            ['type' => 'text', 'value' => 'the first thing'],
            ['type' => 'block', 'value' => $nestedList],
            ['type' => 'text', 'value' => 'the second thing'],
        ], 'updateSpans should replace both text runs and block metadata');
        sameArray($port->spans($port->load($port->save($doc)), ['text']), $port->spans($doc, ['text']), 'updated spans should round-trip through save/load');
    }
);

$rustMapped(
    'rust block updateSpans noop leaves diff cursor quiet',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-blocks-noop',
    'update_blocks_noop',
    function () use ($port): void {
        $block = [
            'parents' => [],
            'type' => 'ordered-list-item',
            'attrs' => [],
        ];
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, $block);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'item 1');
        $headsBefore = $port->getHeads($doc);
        $port->updateDiffCursor($doc);

        $updated = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => $block],
            ['type' => 'text', 'value' => 'item 1'],
        ]);

        truthy($updated === $doc, 'no-op updateSpans should preserve the same native document instance');
        sameArray($port->getHeads($updated), $headsBefore, 'no-op updateSpans should not append a change');
        sameArray($port->diffIncremental($updated), [], 'no-op updateSpans should not emit incremental patches');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust block updateSpans changes block properties',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-blocks-change-block-properties',
    'update_blocks_change_block_properties',
    function () use ($port): void {
        $ordered = ['parents' => [], 'type' => 'ordered-list-item', 'attrs' => []];
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, $ordered);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'item 1');
        $doc = $port->splitBlock($doc, ['text'], 7, $ordered);
        $doc = $port->spliceAtPath($doc, ['text'], 8, 0, 'item 2');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'paragraph', 'parents' => [], 'attrs' => []]],
            ['type' => 'text', 'value' => 'item 1'],
            ['type' => 'block', 'value' => [
                'type' => 'unordered-list-item',
                'parents' => ['ordered-list-item'],
                'attrs' => ['key' => 1],
            ]],
            ['type' => 'text', 'value' => 'item 2'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'paragraph', 'attrs' => []]],
            ['type' => 'text', 'value' => 'item 1'],
            ['type' => 'block', 'value' => [
                'parents' => ['ordered-list-item'],
                'type' => 'unordered-list-item',
                'attrs' => ['key' => 1],
            ]],
            ['type' => 'text', 'value' => 'item 2'],
        ], 'updateSpans should change block metadata while preserving block order');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust block updateSpans updates text',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-blocks-updates-text',
    'update_blocks_updates_text',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, []);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'first thing');
        $doc = $port->splitBlock($doc, ['text'], 12, []);
        $doc = $port->spliceAtPath($doc, ['text'], 13, 0, 'second thing');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'ordered-list-item', 'parents' => [], 'attrs' => []]],
            ['type' => 'text', 'value' => 'the first thing'],
            ['type' => 'block', 'value' => ['type' => 'paragraph', 'parents' => [], 'attrs' => []]],
            ['type' => 'text', 'value' => 'the things are done'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'ordered-list-item', 'attrs' => []]],
            ['type' => 'text', 'value' => 'the first thing'],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'paragraph', 'attrs' => []]],
            ['type' => 'text', 'value' => 'the things are done'],
        ], 'updateSpans should replace text between existing block markers');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust block updateSpans updates marks',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-blocks-updates-marks',
    'update_blocks_updates_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'onetwo');
        $doc = $port->splitBlock($doc, ['text'], 6, []);
        $doc = $port->spliceAtPath($doc, ['text'], 7, 0, 'threefour');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'one'],
            ['type' => 'text', 'value' => 'two', 'marks' => ['bold' => true]],
            ['type' => 'block', 'value' => []],
            ['type' => 'text', 'value' => 'three', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'four'],
            ['type' => 'block', 'value' => []],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'one'],
            ['type' => 'text', 'value' => 'two', 'marks' => ['bold' => true]],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
            ['type' => 'text', 'value' => 'three', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'four'],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
        ], 'updateSpans should apply marks across text and block spans');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust block updateSpans updates text and blocks together',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-blocks-updates-text-and-blocks-at-once',
    'update_blocks_updates_text_and_blocks_at_once',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, ['type' => 'paragraph', 'parents' => [], 'attrs' => []]);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'hello world');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'unordered-list-item', 'parents' => [], 'attrs' => []]],
            ['type' => 'text', 'value' => 'goodbye world'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'unordered-list-item', 'attrs' => []]],
            ['type' => 'text', 'value' => 'goodbye world'],
        ], 'updateSpans should update text content and block metadata in one pass');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust block metadata supports complex text-like properties',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:text-complex-block-properties',
    'text_complex_block_properties',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'type' => new ImmutableString('ordered-list-item'),
            'parents' => [new ImmutableString('div')],
            'attrs' => [],
        ]);
        $block = $port->block($doc, ['text'], 0) ?? [];

        truthy(($block['type'] ?? null) instanceof ImmutableString, 'block type should preserve ImmutableString metadata');
        same((string) $block['type'], 'ordered-list-item', 'block type string value should survive');
        truthy(($block['parents'][0] ?? null) instanceof ImmutableString, 'block parent should preserve ImmutableString metadata');
        same((string) $block['parents'][0], 'div', 'block parent string value should survive');
    },
    'rust/automerge/tests/block_tests.rs'
);

$mapped(
    'block updateSpans emits ImmutableString parent insert patches',
    'javascript/test/block_test.ts',
    135,
    'emits insert patches with ImmutableString for attribute updatese',
    function () use ($port): void {
        $doc = $port->from(['text' => '']);
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'parents' => [],
            'type' => 'paragraph',
            'attrs' => [],
        ]);

        $patches = [];
        $callbackPort = $port->withPatchCallback(static function (array $patchBatch) use (&$patches): void {
            array_push($patches, ...$patchBatch);
        });
        $doc = $callbackPort->updateSpans($doc, ['text'], [[
            'type' => 'block',
            'value' => [
                'type' => 'paragraph',
                'parents' => [new ImmutableString('someparent')],
                'attrs' => [],
            ],
        ]]);

        same(count($patches), 1, 'updateSpans should emit one parent insertion patch');
        same($patches[0]['action'] ?? null, 'insert', 'parent update patch should be an insert');
        sameArray($patches[0]['path'] ?? [], ['text', 0, 'parents', 0], 'parent update patch should target the block parents list');
        same(count($patches[0]['values'] ?? []), 1, 'parent update patch should insert one value');
        truthy(($patches[0]['values'][0] ?? null) instanceof ImmutableString, 'parent update patch should preserve ImmutableString values');
        same((string) $patches[0]['values'][0], 'someparent', 'parent update patch should preserve the ImmutableString text');
        $block = $port->block($doc, ['text'], 0);
        truthy(($block['parents'][0] ?? null) instanceof ImmutableString, 'block metadata should retain the ImmutableString parent before serialization');
    }
);

$rustMapped(
    'rust updateSpans deletes a block attribute list entry',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-spans-delete-attribute',
    'update_spans_delete_attribute',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'type' => 'ordered-list-item',
            'parents' => ['div'],
            'attrs' => [],
        ]);
        $doc = $port->updateSpans($doc, ['text'], [[
            'type' => 'block',
            'value' => [
                'type' => 'ordered-list-item',
                'parents' => [],
                'attrs' => [],
            ],
        ]]);

        sameArray($port->spans($doc, ['text']), [[
            'type' => 'block',
            'value' => [
                'parents' => [],
                'type' => 'ordered-list-item',
                'attrs' => [],
            ],
        ]], 'updateSpans should remove the previous block parent entry');
    },
    'rust/automerge/tests/block_tests.rs'
);

$mapped(
    'block updateSpans materializes marks in spans',
    'javascript/test/block_test.ts',
    179,
    'should update marks',
    function () use ($port): void {
        $doc = $port->from(['text' => 'hello world']);
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => ' world', 'marks' => ['italic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => ' world', 'marks' => ['italic' => true]],
        ], 'updateSpans should preserve active mark maps when materializing spans');
    }
);

$rustMapped(
    'rust updateSpans diffs existing and desired marks',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-spans-diffs-marks',
    'update_spans_diffs_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 0, 5, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['italic' => true]],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => 'world', 'marks' => ['bold' => true, 'italic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['italic' => true]],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => 'world', 'marks' => ['bold' => true, 'italic' => true]],
        ], 'updateSpans should replace existing mark coverage with the desired mark sets');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustDiffMarksSuite = 'rust:tests-diff-marks-rs-target-debug-deps-diff-marks-faa6fc17c830fc19:';

$rustMapped(
    'rust diff marks expands an existing mark range',
    $rustDiffMarksSuite . 'mark-expands',
    'mark_expands',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold text');
        $doc = $port->mark($doc, ['text'], 0, 4, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold text', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold text', 'marks' => ['bold' => true]],
        ], 'updateSpans should expand an existing mark to cover the desired text range');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks contracts an existing mark range',
    $rustDiffMarksSuite . 'mark-contracts',
    'mark_contracts',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold text');
        $doc = $port->mark($doc, ['text'], 0, 9, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text'],
        ], 'updateSpans should contract an existing mark to the desired range');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks shifts a mark position',
    $rustDiffMarksSuite . 'mark-shifts-position',
    'mark_shifts_position',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold text');
        $doc = $port->mark($doc, ['text'], 0, 4, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'text '],
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'text '],
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
        ], 'updateSpans should move a mark to the desired span');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks splits one mark into two ranges',
    $rustDiffMarksSuite . 'mark-splits',
    'mark_splits',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold text here');
        $doc = $port->mark($doc, ['text'], 0, 14, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text '],
            ['type' => 'text', 'value' => 'here', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text '],
            ['type' => 'text', 'value' => 'here', 'marks' => ['bold' => true]],
        ], 'updateSpans should split one mark into two separated ranges');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks merges adjacent same-valued marks',
    $rustDiffMarksSuite . 'adjacent-marks-merge',
    'adjacent_marks_merge',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold text');
        $doc = $port->mark($doc, ['text'], 0, 4, 'bold', true, 'both');
        $doc = $port->mark($doc, ['text'], 5, 9, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold text', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold text', 'marks' => ['bold' => true]],
        ], 'adjacent desired ranges with the same mark should materialize as one marked span');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks keeps separated same-valued marks apart',
    $rustDiffMarksSuite . 'adjacent-marks-stay-separate',
    'adjacent_marks_stay_separate',
    function () use ($port): void {
        $spans = [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => 'text', 'marks' => ['bold' => true]],
        ];
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold text');
        $doc = $port->updateSpans($doc, ['text'], $spans);
        $doc = $port->updateSpans($doc, ['text'], $spans);

        sameArray($port->spans($doc, ['text']), $spans, 'same-valued marks separated by unmarked text should stay separated');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks keeps different adjacent marks separate',
    $rustDiffMarksSuite . 'different-adjacent-marks',
    'different_adjacent_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bolditalic');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'italic', 'marks' => ['italic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'italic', 'marks' => ['italic' => true]],
        ], 'different adjacent marks should materialize as separate spans');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks removes empty spans between equal marks',
    $rustDiffMarksSuite . 'empty-spans-between-marks',
    'empty_spans_between_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'a', 'marks' => ['mark' => true]],
            ['type' => 'text', 'value' => ''],
            ['type' => 'text', 'value' => 'b', 'marks' => ['mark' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'ab', 'marks' => ['mark' => true]],
        ], 'empty spans should not break adjacent equal mark ranges');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks updateSpans is idempotent',
    $rustDiffMarksSuite . 'idempotent-update-spans',
    'idempotent_update_spans',
    function () use ($port): void {
        $spans = [
            ['type' => 'text', 'value' => 'hello ', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'world', 'marks' => ['italic' => true]],
        ];
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], $spans);
        $heads = $port->getHeads($doc);
        $doc2 = $port->updateSpans($doc, ['text'], $spans);
        $doc3 = $port->updateSpans($doc2, ['text'], $spans);

        sameArray($port->spans($doc3, ['text']), $spans, 'idempotent updateSpans should preserve the desired spans');
        sameArray($port->getHeads($doc2), $heads, 'second identical updateSpans should not append a change');
        sameArray($port->getHeads($doc3), $heads, 'third identical updateSpans should not append a change');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks preserves many marks on the same text',
    $rustDiffMarksSuite . 'many-marks-on-same-text',
    'many_marks_on_same_text',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'formatted');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'formatted', 'marks' => [
                'bold' => true,
                'italic' => true,
                'link' => 'https://example.com',
                'underline' => true,
            ]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'formatted', 'marks' => [
                'bold' => true,
                'italic' => true,
                'link' => 'https://example.com',
                'underline' => true,
            ]],
        ], 'multiple marks with different names should survive on one text span');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks ignores marks on empty strings',
    $rustDiffMarksSuite . 'mark-on-empty-string',
    'mark_on_empty_string',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => '', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [], 'marking an empty string should produce no visible spans');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks applies marks to whitespace text',
    $rustDiffMarksSuite . 'mark-on-whitespace',
    'mark_on_whitespace',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => ' ', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => "\n", 'marks' => ['italic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => ' ', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => "\n", 'marks' => ['italic' => true]],
        ], 'marks should apply to whitespace text spans');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks changes a color mark value',
    $rustDiffMarksSuite . 'mark-value-changes-color',
    'mark_value_changes_color',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'colored');
        $doc = $port->mark($doc, ['text'], 0, 7, 'color', 'red', 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'colored', 'marks' => ['color' => 'blue']],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'colored', 'marks' => ['color' => 'blue']],
        ], 'updateSpans should replace a color mark value');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks changes a link mark value',
    $rustDiffMarksSuite . 'mark-value-changes-link-url',
    'mark_value_changes_link_url',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'click here');
        $doc = $port->mark($doc, ['text'], 0, 10, 'link', 'https://old.com', 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'click here', 'marks' => ['link' => 'https://new.com']],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'click here', 'marks' => ['link' => 'https://new.com']],
        ], 'updateSpans should replace a string mark value');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks changes a mark value type',
    $rustDiffMarksSuite . 'mark-value-type-changes',
    'mark_value_type_changes',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'text');
        $doc = $port->mark($doc, ['text'], 0, 4, 'custom', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'text', 'marks' => ['custom' => 'value']],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'text', 'marks' => ['custom' => 'value']],
        ], 'updateSpans should replace a boolean mark value with a string value');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks alternates mark changes',
    $rustDiffMarksSuite . 'alternating-mark-changes',
    'alternating_mark_changes',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'text');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'text', 'marks' => ['bold' => true]],
        ]);
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'text'],
        ]);
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'text', 'marks' => ['italic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'text', 'marks' => ['italic' => true]],
        ], 'alternating mark updates should leave only the final desired mark');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks handles complex unicode text',
    $rustDiffMarksSuite . 'complex-unicode-text',
    'complex_unicode_text',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'Hello '],
            ['type' => 'text', 'value' => '😊', 'marks' => ['emoji' => true]],
            ['type' => 'text', 'value' => ' 世界 ', 'marks' => ['chinese' => true]],
            ['type' => 'text', 'value' => '🌍', 'marks' => ['emoji' => true]],
            ['type' => 'text', 'value' => ' مرحبا', 'marks' => ['arabic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'Hello '],
            ['type' => 'text', 'value' => '😊', 'marks' => ['emoji' => true]],
            ['type' => 'text', 'value' => ' 世界 ', 'marks' => ['chinese' => true]],
            ['type' => 'text', 'value' => '🌍', 'marks' => ['emoji' => true]],
            ['type' => 'text', 'value' => ' مرحبا', 'marks' => ['arabic' => true]],
        ], 'unicode text spans should preserve their mark boundaries');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks applies marks to emoji graphemes',
    $rustDiffMarksSuite . 'marks-on-emoji',
    'marks_on_emoji',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'Hello 👨‍👩‍👧‍👦 world');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'Hello '],
            ['type' => 'text', 'value' => '👨‍👩‍👧‍👦', 'marks' => ['emoji' => true]],
            ['type' => 'text', 'value' => ' world'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'Hello '],
            ['type' => 'text', 'value' => '👨‍👩‍👧‍👦', 'marks' => ['emoji' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], 'emoji grapheme spans should preserve marks');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks applies marks to accented text',
    $rustDiffMarksSuite . 'marks-on-combining-characters',
    'marks_on_combining_characters',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'café');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'café', 'marks' => ['accented' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'café', 'marks' => ['accented' => true]],
        ], 'accented text should preserve marks and content');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks spans a mark across a block marker',
    $rustDiffMarksSuite . 'mark-spans-across-block',
    'mark_spans_across_block',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold');
        $doc = $port->splitBlock($doc, ['text'], 4, []);
        $doc = $port->spliceAtPath($doc, ['text'], 5, 0, 'text');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'block', 'value' => []],
            ['type' => 'text', 'value' => 'text', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
            ['type' => 'text', 'value' => 'text', 'marks' => ['bold' => true]],
        ], 'marks should materialize on both text spans around a block marker');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks ends a mark at a block boundary',
    $rustDiffMarksSuite . 'mark-ends-at-block-boundary',
    'mark_ends_at_block_boundary',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold');
        $doc = $port->splitBlock($doc, ['text'], 4, []);
        $doc = $port->spliceAtPath($doc, ['text'], 5, 0, 'text');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'block', 'value' => []],
            ['type' => 'text', 'value' => 'text'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
            ['type' => 'text', 'value' => 'text'],
        ], 'a mark ending before a block marker should not apply after the marker');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks changes block properties while preserving marks',
    $rustDiffMarksSuite . 'block-properties-change-with-marks',
    'block_properties_change_with_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splitBlock($doc, ['text'], 0, []);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'marked text');
        $doc = $port->mark($doc, ['text'], 1, 7, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'paragraph', 'level' => 1]],
            ['type' => 'text', 'value' => 'marked', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'paragraph', 'attrs' => [], 'level' => 1]],
            ['type' => 'text', 'value' => 'marked', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text'],
        ], 'block property changes should preserve adjacent text marks');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks survive block updates',
    $rustDiffMarksSuite . 'marks-survive-block-updates',
    'marks_survive_block_updates',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'p']],
            ['type' => 'text', 'value' => 'marked', 'marks' => ['bold' => true]],
        ]);
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'h1', 'level' => 1]],
            ['type' => 'text', 'value' => 'marked', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'h1', 'attrs' => [], 'level' => 1]],
            ['type' => 'text', 'value' => 'marked', 'marks' => ['bold' => true]],
        ], 'updating block properties should leave following text marks intact');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks boundary insert expands existing mark',
    $rustDiffMarksSuite . 'update-spans-which-inserts-at-the-end-of-expand-mark-doesnt-generate-mark-changes',
    'update_spans_which_inserts_at_the_end_of_expand_mark_doesnt_generate_mark_changes',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 6, 11, 'bold', true, 'both');

        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello '],
            ['type' => 'text', 'value' => 'wworldd', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello '],
            ['type' => 'text', 'value' => 'wworldd', 'marks' => ['bold' => true]],
        ], 'updateSpans should extend text at an expand-both mark boundary without an extra mark segment');
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);
        same(count($decoded['ops'] ?? []), 2, 'native updateSpans boundary insertion should remain a compact two-operation change');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks removes all text from a marked span',
    $rustDiffMarksSuite . 'removing-all-text-from-marked-span',
    'removing_all_text_from_marked_span',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 0, 5, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => ' world'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => ' world'],
        ], 'removing marked text should clear the removed mark span');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks preserves nested marks',
    $rustDiffMarksSuite . 'nested-marks',
    'nested_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'italic bold and italic just italic');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'italic ', 'marks' => ['italic' => true]],
            ['type' => 'text', 'value' => 'bold and italic', 'marks' => ['italic' => true, 'bold' => true]],
            ['type' => 'text', 'value' => ' just italic', 'marks' => ['italic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'italic ', 'marks' => ['italic' => true]],
            ['type' => 'text', 'value' => 'bold and italic', 'marks' => ['bold' => true, 'italic' => true]],
            ['type' => 'text', 'value' => ' just italic', 'marks' => ['italic' => true]],
        ], 'nested marks should materialize as adjacent span runs');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks unmarks part of a range',
    $rustDiffMarksSuite . 'unmark-part-of-range',
    'unmark_part_of_range',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'bold text here');
        $doc = $port->mark($doc, ['text'], 0, 14, 'bold', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text '],
            ['type' => 'text', 'value' => 'here', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'bold', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' text '],
            ['type' => 'text', 'value' => 'here', 'marks' => ['bold' => true]],
        ], 'unmarking the middle of a range should split the marked run');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks creates gaps when unmarking',
    $rustDiffMarksSuite . 'unmark-creates-gaps',
    'unmark_creates_gaps',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'a b c d e');
        $doc = $port->mark($doc, ['text'], 0, 9, 'mark', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'a', 'marks' => ['mark' => true]],
            ['type' => 'text', 'value' => ' b '],
            ['type' => 'text', 'value' => 'c', 'marks' => ['mark' => true]],
            ['type' => 'text', 'value' => ' d '],
            ['type' => 'text', 'value' => 'e', 'marks' => ['mark' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'a', 'marks' => ['mark' => true]],
            ['type' => 'text', 'value' => ' b '],
            ['type' => 'text', 'value' => 'c', 'marks' => ['mark' => true]],
            ['type' => 'text', 'value' => ' d '],
            ['type' => 'text', 'value' => 'e', 'marks' => ['mark' => true]],
        ], 'unmarking multiple gaps should preserve alternating marked runs');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks combines different expand behaviors',
    $rustDiffMarksSuite . 'multiple-marks-different-expand-behaviors',
    'multiple_marks_different_expand_behaviors',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'text', 'marks' => ['before' => true, 'after' => true, 'none' => true]],
        ], [
            'perMarkExpand' => ['before' => 'before', 'after' => 'after', 'none' => 'none'],
        ]);
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'a');
        $doc = $port->spliceAtPath($doc, ['text'], 5, 0, 'b');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'a', 'marks' => ['before' => true]],
            ['type' => 'text', 'value' => 'text', 'marks' => ['after' => true, 'before' => true, 'none' => true]],
            ['type' => 'text', 'value' => 'b', 'marks' => ['after' => true]],
        ], 'per-mark expand settings should affect only their own boundary insertions');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks respects expand none at boundaries',
    $rustDiffMarksSuite . 'marks-with-expand-none-at-boundaries',
    'marks_with_expand_none_at_boundaries',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'text', 'marks' => ['mark' => true]],
        ], ['defaultExpand' => 'none']);
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'before ');
        $doc = $port->spliceAtPath($doc, ['text'], 11, 0, ' after');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'before '],
            ['type' => 'text', 'value' => 'text', 'marks' => ['mark' => true]],
            ['type' => 'text', 'value' => ' after'],
        ], 'expand none should keep boundary insertions outside the mark');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks supports marked block content',
    $rustDiffMarksSuite . 'block-with-marked-content',
    'block_with_marked_content',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'heading', 'level' => 1]],
            ['type' => 'text', 'value' => 'Chapter '],
            ['type' => 'text', 'value' => 'One', 'marks' => ['emphasis' => true]],
            ['type' => 'block', 'value' => ['type' => 'paragraph']],
            ['type' => 'text', 'value' => 'This is the '],
            ['type' => 'text', 'value' => 'first', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' chapter.'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'heading', 'attrs' => [], 'level' => 1]],
            ['type' => 'text', 'value' => 'Chapter '],
            ['type' => 'text', 'value' => 'One', 'marks' => ['emphasis' => true]],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'paragraph', 'attrs' => []]],
            ['type' => 'text', 'value' => 'This is the '],
            ['type' => 'text', 'value' => 'first', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' chapter.'],
        ], 'block spans should preserve marked text content');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks preserves same-name different values',
    $rustDiffMarksSuite . 'marks-with-different-values-same-name',
    'marks_with_different_values_same_name',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'red blue green');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'red', 'marks' => ['color' => 'red']],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => 'blue', 'marks' => ['color' => 'blue']],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => 'green', 'marks' => ['color' => 'green']],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'red', 'marks' => ['color' => 'red']],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => 'blue', 'marks' => ['color' => 'blue']],
            ['type' => 'text', 'value' => ' '],
            ['type' => 'text', 'value' => 'green', 'marks' => ['color' => 'green']],
        ], 'same-name marks with different values should remain separate');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks updates spans to only blocks',
    $rustDiffMarksSuite . 'update-spans-with-only-blocks',
    'update_spans_with_only_blocks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'text');
        $doc = $port->splitBlock($doc, ['text'], 4, []);
        $doc = $port->spliceAtPath($doc, ['text'], 5, 0, 'more');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => []],
            ['type' => 'block', 'value' => []],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
        ], 'updateSpans should be able to replace all text with block markers');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks removes one overlapping mark and keeps another',
    $rustDiffMarksSuite . 'overlapping-marks-remove-one-keep-other',
    'overlapping_marks_remove_one_keep_other',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 6, 11, 'bold', true, 'both');
        $doc = $port->mark($doc, ['text'], 6, 11, 'italic', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello '],
            ['type' => 'text', 'value' => 'world', 'marks' => ['bold' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello '],
            ['type' => 'text', 'value' => 'world', 'marks' => ['bold' => true]],
        ], 'updateSpans should remove the italic overlap while keeping bold');
        sameArray($port->marksAt($doc, ['text'], 6), ['bold' => true], 'only bold should remain active on world');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks changes overlapping mark boundaries',
    $rustDiffMarksSuite . 'overlapping-marks-change-boundaries',
    'overlapping_marks_change_boundaries',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello beautiful world');
        $doc = $port->mark($doc, ['text'], 0, 15, 'bold', true, 'both');
        $doc = $port->mark($doc, ['text'], 6, 21, 'italic', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' beautiful '],
            ['type' => 'text', 'value' => 'world', 'marks' => ['italic' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' beautiful '],
            ['type' => 'text', 'value' => 'world', 'marks' => ['italic' => true]],
        ], 'updateSpans should move overlapping mark boundaries independently');
        sameArray($port->marksAt($doc, ['text'], 0), ['bold' => true], 'hello should keep bold');
        sameArray($port->marksAt($doc, ['text'], 6), [], 'middle text should be unmarked');
        sameArray($port->marksAt($doc, ['text'], 16), ['italic' => true], 'world should keep italic');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$rustMapped(
    'rust diff marks adds a third overlapping mark',
    $rustDiffMarksSuite . 'overlapping-marks-add-third-mark',
    'overlapping_marks_add_third_mark',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 0, 11, 'bold', true, 'both');
        $doc = $port->mark($doc, ['text'], 6, 11, 'italic', true, 'both');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hel', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'lo wo', 'marks' => ['bold' => true, 'underline' => true]],
            ['type' => 'text', 'value' => 'rld', 'marks' => ['bold' => true, 'italic' => true, 'underline' => true]],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hel', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'lo wo', 'marks' => ['bold' => true, 'underline' => true]],
            ['type' => 'text', 'value' => 'rld', 'marks' => ['bold' => true, 'italic' => true, 'underline' => true]],
        ], 'updateSpans should add a third mark over existing overlaps');
        sameArray($doc->toArray(), ['text' => 'hello world'], 'text content should be preserved while adding the third overlap');
    },
    'rust/automerge/tests/diff_marks.rs'
);

$mapped(
    'block updateSpans honors default mark expand none',
    'javascript/test/block_test.ts',
    200,
    'allows configuring the default expand value of created marks',
    function () use ($port): void {
        $doc = $port->from(['text' => '']);
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], ['defaultExpand' => 'none']);
        $doc = $port->spliceAtPath($doc, ['text'], 5, 0, '!');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => '! world'],
        ], 'defaultExpand=none should keep end-boundary insertions outside the mark');
    }
);

$mapped(
    'block updateSpans honors per mark expand override',
    'javascript/test/block_test.ts',
    225,
    'should allow overriding the default expand on a per mark basis',
    function () use ($port): void {
        $doc = $port->from(['text' => '']);
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], ['defaultExpand' => 'none', 'perMarkExpand' => ['bold' => 'both']]);
        $doc = $port->spliceAtPath($doc, ['text'], 5, 0, '!');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello!', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], 'perMarkExpand should override defaultExpand for matching mark names');
    }
);

$rustMapped(
    'rust block updateSpans accepts upstream after expand config',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:update-spans-uses-expand-config',
    'update_spans_uses_expand_config',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], [
            'defaultExpand' => 'none',
            'perMarkExpand' => ['bold' => 'after'],
        ]);

        $doc = $port->spliceAtPath($doc, ['text'], 5, 0, '!');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'Oh ');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'Oh '],
            ['type' => 'text', 'value' => 'hello!', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], 'upstream after expand should include end-boundary insertions and exclude start-boundary insertions');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust marked splice replacement keeps only expanding marks',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:test-splice-with-mark',
    'test_splice_with_mark',
    function () use ($port): void {
        $doc = $port->from(['txt' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['txt'], 0, 0, 'abc');
        $doc = $port->mark($doc, ['txt'], 1, 2, 'some_nonexpanding_mark_type', 'marked', 'none');
        $doc = $port->mark($doc, ['txt'], 1, 2, 'some_expanding_mark_type', 'marked', 'both');

        sameArray($port->spans($doc, ['txt']), [
            ['type' => 'text', 'value' => 'a'],
            ['type' => 'text', 'value' => 'b', 'marks' => [
                'some_expanding_mark_type' => 'marked',
                'some_nonexpanding_mark_type' => 'marked',
            ]],
            ['type' => 'text', 'value' => 'c'],
        ], 'setup should expose both marks on the replaced character');

        $doc = $port->spliceAtPath($doc, ['txt'], 1, 1, 'd');

        sameArray($port->spans($doc, ['txt']), [
            ['type' => 'text', 'value' => 'a'],
            ['type' => 'text', 'value' => 'd', 'marks' => ['some_expanding_mark_type' => 'marked']],
            ['type' => 'text', 'value' => 'c'],
        ], 'replacement text should keep the expanding mark and drop the non-expanding mark');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust deleted marked text does not mark a later insertion at the same index',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:test-mark-behavior-on-delete-insert',
    'test_mark_behavior_on_delete_insert',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello');
        $doc = $port->mark($doc, ['text'], 0, 5, 'bold', true, 'both');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 5, '');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hi');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hi'],
        ], 'new text inserted after deleting a marked range should not inherit the deleted mark');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust spans consolidate deleted mark gaps',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:spans-consolidates-marks-which-are-empty-due-to-deleted-marks',
    'spans_consolidates_marks_which_are_empty_due_to_deleted_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello middle world');
        $doc = $port->mark($doc, ['text'], 0, 9, 'bold', true, 'none');
        $doc = $port->mark($doc, ['text'], 9, 18, 'italic', true, 'none');
        $doc = $port->mark($doc, ['text'], 6, 9, 'bold', null, 'none');
        $doc = $port->mark($doc, ['text'], 9, 12, 'italic', null, 'none');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello ', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => 'middle'],
            ['type' => 'text', 'value' => ' world', 'marks' => ['italic' => true]],
        ], 'null-valued mark operations should delete mark intervals and consolidate unmarked gaps');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust spans consolidate a fully deleted mark interval',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:spans-consolidates-marks-with-deleted-marks-followed-by-empty-marks',
    'spans_consolidates_marks_with_deleted_marks_followed_by_empty_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 0, 6, 'bold', true, 'none');
        $doc = $port->mark($doc, ['text'], 0, 6, 'bold', null, 'none');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello world'],
        ], 'null-valued mark covering the whole interval should leave a single unmarked span');
    },
    'rust/automerge/tests/block_tests.rs'
);

$rustMapped(
    'rust spans consolidate a fully deleted trailing mark interval',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:spans-consolidates-marks-with-empty-marks-followed-by-deleted-marks',
    'spans_consolidates_marks_with_empty_marks_followed_by_deleted_marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 6, 11, 'bold', true, 'none');
        $doc = $port->mark($doc, ['text'], 6, 11, 'bold', null, 'none');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'hello world'],
        ], 'null-valued trailing mark should leave one unmarked span');
    },
    'rust/automerge/tests/block_tests.rs'
);

$mapped(
    'block updateSpans accepts partial or omitted mark config',
    'javascript/test/block_test.ts',
    250,
    'should allow omitting any part of the update spans config',
    function () use ($port): void {
        $expected = [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ];
        $doc = $port->from(['text' => '']);
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], ['defaultExpand' => 'none']);
        sameArray($port->spans($doc, ['text']), $expected, 'updateSpans should accept defaultExpand without perMarkExpand');

        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], ['perMarkExpand' => ['bold' => 'none']]);
        sameArray($port->spans($doc, ['text']), $expected, 'updateSpans should accept perMarkExpand without defaultExpand');

        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ]);
        sameArray($port->spans($doc, ['text']), $expected, 'updateSpans should accept omitted mark config');
    }
);

$mapped(
    'block attributes preserve ImmutableString attrs when loading blocks',
    'javascript/test/block_test.ts',
    291,
    'when loading blocks',
    function () use ($port): void {
        $doc = $port->from(['text' => '']);
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'parents' => [],
            'type' => new ImmutableString('ordered-list-item'),
            'attrs' => ['data-foo' => new ImmutableString('someval')],
        ]);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'first thing');

        $block = $port->block($doc, ['text'], 0);
        truthy(is_array($block), 'block should be available at the split position');
        truthy(($block['attrs']['data-foo'] ?? null) instanceof ImmutableString, 'block attrs should preserve ImmutableString values');
        same((string) $block['attrs']['data-foo'], 'someval', 'block attr ImmutableString should preserve its text');
    }
);

$mapped(
    'block attributes preserve ImmutableString metadata in spans',
    'javascript/test/block_test.ts',
    308,
    'when loading spans',
    function () use ($port): void {
        $doc = $port->from(['text' => '']);
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'parents' => [new ImmutableString('div')],
            'type' => new ImmutableString('ordered-list-item'),
            'attrs' => ['data-foo' => new ImmutableString('someval')],
        ]);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'first thing');

        $spans = $port->spans($doc, ['text']);
        same($spans[0]['type'] ?? null, 'block', 'first span should be the inserted block');
        $block = $spans[0]['value'] ?? null;
        truthy(is_array($block), 'block span should carry block metadata');
        truthy(($block['parents'][0] ?? null) instanceof ImmutableString, 'block span parents should preserve ImmutableString values');
        same((string) $block['parents'][0], 'div', 'block span parent ImmutableString should preserve its text');
        truthy(($block['attrs']['data-foo'] ?? null) instanceof ImmutableString, 'block span attrs should preserve ImmutableString values');
        same((string) $block['attrs']['data-foo'], 'someval', 'block span attr ImmutableString should preserve its text');
        truthy(($block['type'] ?? null) instanceof ImmutableString, 'block span type should preserve ImmutableString values');
        same((string) $block['type'], 'ordered-list-item', 'block span type ImmutableString should preserve its text');
    }
);

$mapped(
    'block updateSpans metadata-only changes remain editable',
    'javascript/test/block_test.ts',
    331,
    'updates the document even if the only change was to a block attribute',
    function () use ($port): void {
        $doc = $port->from(['text' => '']);
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'parents' => [],
            'type' => 'paragraph',
            'attrs' => [],
        ]);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'item');

        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => [
                'type' => 'paragraph',
                'parents' => ['ordered-list-item'],
                'attrs' => [],
            ]],
            ['type' => 'text', 'value' => 'item'],
        ]);

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => [
                'parents' => ['ordered-list-item'],
                'type' => 'paragraph',
                'attrs' => [],
            ]],
            ['type' => 'text', 'value' => 'item'],
        ], 'metadata-only updateSpans should update block metadata');

        $doc = $port->spliceAtPath($doc, ['text'], 0, 1, 'A');
        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'Aitem'],
        ], 'document should remain editable after a metadata-only updateSpans change');
    }
);

$mapped(
    'block view shows historical marks',
    'javascript/test/block_test.ts',
    371,
    'should show historical marks',
    function () use ($port): void {
        $doc = $port->from(['text' => 'hello world']);
        $doc = $port->mark($doc, ['text'], 0, 5, 'bold', true);
        $headsBefore = $port->getHeads($doc);
        $doc = $port->mark($doc, ['text'], 5, 11, 'italic', true);

        sameArray($port->spans($port->view($doc, $headsBefore), ['text']), [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], 'view at earlier heads should include earlier marks and exclude later marks');
    }
);

$rustMapped(
    'rust spans at heads respect historical marks',
    'rust:tests-block-tests-rs-target-debug-deps-block-tests-405aaf2cd395742f:marks-on-spans-respect-heads',
    'marks_on_spans_respect_heads',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 0, 5, 'bold', true, 'after');
        $headsBefore = $port->getHeads($doc);
        $doc = $port->mark($doc, ['text'], 5, 11, 'italic', true, 'after');

        sameArray($port->spans($port->view($doc, $headsBefore), ['text']), [
            ['type' => 'text', 'value' => 'hello', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], 'spans at saved heads should exclude marks added by later changes');
    },
    'rust/automerge/tests/block_tests.rs'
);

$mapped(
    'block attributes preserve very small floating point values',
    'javascript/test/block_test.ts',
    388,
    'can allow small values in block attributes',
    function () use ($port): void {
        $smallnum = 1.401298464324817e-45;
        $doc = $port->from(['text' => '']);
        $doc = $port->splitBlock($doc, ['text'], 0, ['smallnum' => $smallnum]);
        $block = $port->block($doc, ['text'], 0);

        truthy(is_array($block), 'block should be available at the split position');
        same($block['smallnum'] ?? null, $smallnum, 'small block metadata floats should not be coerced to zero');
    }
);

$mapped(
    'legacy root handles single-property assignment',
    'javascript/test/legacy_tests.ts',
    429,
    'should handle single-property assignment',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'foo', 'bar');
        $doc = $port->set($doc, 'zip', 'zap');

        same($doc->toArray()['foo'], 'bar', 'first root property should be readable');
        same($doc->toArray()['zip'], 'zap', 'second root property should be readable');
        sameArray($doc->toArray(), ['foo' => 'bar', 'zip' => 'zap'], 'root map should contain both assigned properties');
    }
);

$mapped(
    'legacy root allows floating point values',
    'javascript/test/legacy_tests.ts',
    437,
    'should allow floating-point values',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'number', 1589032171.1);

        same($doc->toArray()['number'], 1589032171.1, 'floating point root value should materialize exactly');
    }
);

$mapped(
    'legacy root handles multi-property assignment',
    'javascript/test/legacy_tests.ts',
    442,
    'should handle multi-property assignment',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar', 'answer' => 42], 'aabbcc');

        same($doc->toArray()['foo'], 'bar', 'first multi-assigned property should be readable');
        same($doc->toArray()['answer'], 42, 'second multi-assigned property should be readable');
        sameArray($doc->toArray(), ['foo' => 'bar', 'answer' => 42], 'root map should contain all multi-assigned properties');
    }
);

$mapped(
    'legacy root handles property deletion',
    'javascript/test/legacy_tests.ts',
    451,
    'should handle root property deletion',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'foo', 'bar');
        $doc = $port->set($doc, 'something', null);
        $doc = $port->delete($doc, 'foo');

        truthy(! array_key_exists('foo', $doc->toArray()), 'deleted root property should be absent');
        sameArray($doc->toArray(), ['something' => null], 'deleting one root property should preserve unrelated null value');
    }
);

$mapped(
    'legacy root follows JavaScript delete behavior',
    'javascript/test/legacy_tests.ts',
    464,
    'should follow JS delete behavior',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'foo', 'bar');
        [$doc, $deleted] = $port->deleteWithResult($doc, 'foo');
        same($deleted, true, 'deleting an existing root property should report true');
        [$doc, $deletedMissing] = $port->deleteWithResult($doc, 'baz');
        same($deletedMissing, true, 'deleting a missing root property should report true');
        sameArray($doc->toArray(), [], 'deleting a missing root property should leave state unchanged');
    }
);

$mapped(
    'legacy root allows property type changes',
    'javascript/test/legacy_tests.ts',
    482,
    'should allow the type of a property to be changed',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'prop', 123);
        same($doc->toArray()['prop'], 123, 'numeric root property should materialize');
        $doc = $port->set($doc, 'prop', '123');
        same($doc->toArray()['prop'], '123', 'string replacement should materialize');
        $doc = $port->set($doc, 'prop', null);
        same($doc->toArray()['prop'], null, 'null replacement should materialize');
        $doc = $port->set($doc, 'prop', true);
        same($doc->toArray()['prop'], true, 'boolean replacement should materialize');
    }
);

$mapped(
    'legacy root allows empty string keys',
    'javascript/test/legacy_tests.ts',
    493,
    'should not error on empty string keys',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), '', 'x');

        same($doc->toArray()[''], 'x', 'empty string root key should be readable');
        sameArray($doc->toArray(), ['' => 'x'], 'empty string root key should materialize');
    }
);

$mapped(
    'legacy root rejects unsupported assignment datatypes',
    'javascript/test/legacy_tests.ts',
    502,
    'should not allow assignment of unsupported datatypes',
    function () use ($port): void {
        $doc = $port->init('aabbcc');

        throwsLike(
            static fn () => $port->set($doc, 'foo', static fn (): int => 1),
            'Cannot assign function value at /foo',
            'function assignment should be rejected at the root path'
        );
        throwsLike(
            static fn () => $port->set($doc, 'foo', ['prop' => static fn (): int => 1]),
            'Cannot assign function value at /foo/prop',
            'nested unsupported assignment should include the nested path'
        );

        $resource = fopen('php://memory', 'rb');
        truthy($resource !== false, 'memory resource should open for unsupported datatype coverage');
        try {
            throwsLike(
                static fn () => $port->set($doc, 'foo', $resource),
                'Cannot assign resource value at /foo',
                'resource assignment should be rejected at the root path'
            );
        } finally {
            fclose($resource);
        }

        same($port->getAllChanges($doc), [], 'rejected assignments should not append changes');
    }
);

$mapped(
    'legacy nested maps expose Automerge-shaped object ids',
    'javascript/test/legacy_tests.ts',
    521,
    'should assign an objectId to nested maps',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'nested', []);
        $nested = $doc->toArray()['nested'];
        $objectId = $port->getObjectId($nested);

        truthy(is_string($objectId), 'nested map should expose an object id');
        truthy((bool) preg_match('/^[0-9]+@([0-9a-f][0-9a-f])*$/', $objectId), 'nested map object id should match upstream op-id shape');
        truthy($objectId !== '_root', 'nested map object id should not be the root id');
    }
);

$mapped(
    'legacy nested maps handle assignment of a nested property',
    'javascript/test/legacy_tests.ts',
    533,
    'should handle assignment of a nested property',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'nested', []);
        $doc = $port->setNested($doc, ['nested', 'foo'], 'bar');
        $doc = $port->setNested($doc, ['nested', 'one'], 1);

        same($doc->toArray()['nested']['foo'], 'bar', 'nested string property should materialize');
        same($doc->toArray()['nested']['one'], 1, 'nested numeric property should materialize');
        sameArray($doc->toArray(), ['nested' => ['foo' => 'bar', 'one' => 1]], 'nested map should include both assigned properties');

        $replayed = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc));
        sameArray($replayed->toArray(), $doc->toArray(), 'nested map assignment should replay from changes');
    }
);

$mapped(
    'legacy nested maps handle assignment of an object literal',
    'javascript/test/legacy_tests.ts',
    547,
    'should handle assignment of an object literal',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'textStyle', ['bold' => false, 'fontSize' => 12]);

        same($doc->toArray()['textStyle']['bold'], false, 'nested object literal boolean should materialize');
        same($doc->toArray()['textStyle']['fontSize'], 12, 'nested object literal number should materialize');
        sameArray($doc->toArray(), ['textStyle' => ['bold' => false, 'fontSize' => 12]], 'object literal should materialize as a nested map');
    }
);

$mapped(
    'legacy nested maps handle multiple nested property assignment',
    'javascript/test/legacy_tests.ts',
    559,
    'should handle assignment of multiple nested properties',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'textStyle', ['bold' => false, 'fontSize' => 12]);
        $doc = $port->setNested($doc, ['textStyle', 'typeface'], 'Optima');
        $doc = $port->setNested($doc, ['textStyle', 'fontSize'], 14);
        $style = $doc->toArray()['textStyle'];

        same($style['typeface'], 'Optima', 'new nested property should materialize');
        same($style['bold'], false, 'existing nested boolean should be preserved');
        same($style['fontSize'], 14, 'existing nested numeric property should update');
    }
);

$mapped(
    'legacy nested maps handle arbitrary-depth nesting',
    'javascript/test/legacy_tests.ts',
    574,
    'should handle arbitrary-depth nesting',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'a', ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 'h']]]]]]);
        $doc = $port->setNested($doc, ['a', 'b', 'c', 'd', 'e', 'f', 'i'], 'j');
        $root = $doc->toArray();

        same($root['a']['b']['c']['d']['e']['f']['g'], 'h', 'existing deep nested property should remain');
        same($root['a']['b']['c']['d']['e']['f']['i'], 'j', 'new deep nested property should materialize');
        sameArray($root, ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 'h', 'i' => 'j']]]]]]], 'deep nested map should materialize');
    }
);

$mapped(
    'legacy nested maps allow object replacement',
    'javascript/test/legacy_tests.ts',
    588,
    'should allow an old object to be replaced with a new one',
    function () use ($port): void {
        $doc1 = $port->set(
            $port->init('aabbcc'),
            'myPet',
            ['species' => 'dog', 'legs' => 4, 'breed' => 'dachshund']
        );
        $doc2 = $port->set(
            $doc1,
            'myPet',
            [
                'species' => 'koi',
                'variety' => "\u{7d05}\u{767d}",
                'colors' => ['red' => true, 'white' => true, 'black' => false],
            ]
        );

        same($doc1->toArray()['myPet']['breed'], 'dachshund', 'old nested object should remain immutable');
        truthy(! array_key_exists('breed', $doc2->toArray()['myPet']), 'replacement object should remove old fields');
        same($doc2->toArray()['myPet']['variety'], "\u{7d05}\u{767d}", 'replacement object should keep unicode scalar values');
        sameArray(
            $doc2->toArray()['myPet']['colors'],
            ['red' => true, 'white' => true, 'black' => false],
            'replacement object should preserve nested map fields'
        );
    }
);

$mapped(
    'legacy nested maps allow primitive map type changes',
    'javascript/test/legacy_tests.ts',
    615,
    'should allow fields to be changed between primitive and nested map',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'color', '#ff7f00');
        same($doc->toArray()['color'], '#ff7f00', 'primitive color should materialize');

        $doc = $port->set($doc, 'color', ['red' => 255, 'green' => 127, 'blue' => 0]);
        sameArray($doc->toArray()['color'], ['red' => 255, 'green' => 127, 'blue' => 0], 'nested map replacement should materialize');

        $doc = $port->set($doc, 'color', '#ff7f00');
        same($doc->toArray()['color'], '#ff7f00', 'primitive replacement should materialize again');
    }
);

$mapped(
    'legacy nested maps reject references to existing document objects',
    'javascript/test/legacy_tests.ts',
    627,
    'should not allow several references to the same map object',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'object', ['species' => 'dog']);
        $existing = Document::fromArray(['species' => 'koi'], 'ddeeff');

        throwsLike(
            static fn (): Document => $port->set($doc, 'x', $doc),
            'Cannot create a reference to an existing document object at /x',
            'assigning the current document object should be rejected'
        );
        throwsLike(
            static fn (): Document => $port->set($doc, 'x', $existing),
            'Cannot create a reference to an existing document object at /x',
            'assigning an existing document object should be rejected'
        );
        throwsLike(
            static fn (): Document => $port->set($doc, 'x', ['nested' => $existing]),
            'Cannot create a reference to an existing document object at /x/nested',
            'assigning an existing document object through a nested map should be rejected'
        );
        sameArray($doc->toArray(), ['object' => ['species' => 'dog']], 'rejected object references should not alter the source document');
    }
);

$mapped(
    'legacy nested maps reject object-copying idioms',
    'javascript/test/legacy_tests.ts',
    647,
    'should not allow object-copying idioms',
    function () use ($port): void {
        $doc = $port->set(
            $port->init('aabbcc'),
            'items',
            [
                ['id' => 'id1', 'name' => 'one'],
                ['id' => 'id2', 'name' => 'two'],
            ]
        );

        throwsLike(
            static fn (): Document => $port->set(
                $doc,
                'items',
                [
                    $port->objectReference($doc, ['items', 0]),
                    $port->objectReference($doc, ['items', 1]),
                    ['id' => 'id3', 'name' => 'three'],
                ]
            ),
            'Cannot create a reference to an existing document object at /items/0',
            'copying existing list element objects into a replacement list should be rejected'
        );
        sameArray(
            $doc->toArray(),
            [
                'items' => [
                    ['id' => 'id1', 'name' => 'one'],
                    ['id' => 'id2', 'name' => 'two'],
                ],
            ],
            'rejected object-copying idiom should not alter the source list'
        );
    }
);

$mapped(
    'legacy nested maps handle deletion of properties within a map',
    'javascript/test/legacy_tests.ts',
    664,
    'should handle deletion of properties within a map',
    function () use ($port): void {
        $doc = $port->set(
            $port->init('aabbcc'),
            'textStyle',
            ['typeface' => 'Optima', 'bold' => false, 'fontSize' => 12]
        );
        $doc = $port->deleteNested($doc, ['textStyle', 'bold']);
        $style = $doc->toArray()['textStyle'];

        truthy(! array_key_exists('bold', $style), 'deleted nested map property should be absent');
        sameArray($style, ['typeface' => 'Optima', 'fontSize' => 12], 'nested map deletion should preserve sibling properties');

        $replayed = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc));
        sameArray($replayed->toArray(), $doc->toArray(), 'nested map deletion should replay from changes');
    }
);

$mapped(
    'legacy nested maps handle deletion of references to a map',
    'javascript/test/legacy_tests.ts',
    676,
    'should handle deletion of references to a map',
    function () use ($port): void {
        $doc = $port->set(
            $port->init('aabbcc'),
            'title',
            'Hello'
        );
        $doc = $port->set($doc, 'textStyle', ['typeface' => 'Optima', 'fontSize' => 12]);
        $doc = $port->delete($doc, 'textStyle');

        truthy(! array_key_exists('textStyle', $doc->toArray()), 'deleted root map reference should be absent');
        sameArray($doc->toArray(), ['title' => 'Hello'], 'root map deletion should preserve unrelated fields');
    }
);

$mapped(
    'legacy lists allow elements to be inserted',
    'javascript/test/legacy_tests.ts',
    690,
    'should allow elements to be inserted',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', []);
        $doc = $port->insertListElements($doc, 'noodles', 0, ['udon', 'soba']);
        $doc = $port->insertListElements($doc, 'noodles', 1, ['ramen']);

        sameArray($doc->toArray(), ['noodles' => ['udon', 'ramen', 'soba']], 'list insertions should preserve order');
        same($doc->toArray()['noodles'][0], 'udon', 'first inserted list element should be readable');
        same($doc->toArray()['noodles'][1], 'ramen', 'middle inserted list element should be readable');
        same($doc->toArray()['noodles'][2], 'soba', 'last inserted list element should be readable');
    }
);

$mapped(
    'legacy lists handle assignment of a list literal',
    'javascript/test/legacy_tests.ts',
    704,
    'should handle assignment of a list literal',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', ['udon', 'ramen', 'soba']);

        sameArray($doc->toArray(), ['noodles' => ['udon', 'ramen', 'soba']], 'list literal should materialize');
        truthy(! array_key_exists(3, $doc->toArray()['noodles']), 'list literal should not expose a fourth element');
    }
);

$mapped(
    'legacy lists handle deletion of list elements',
    'javascript/test/legacy_tests.ts',
    738,
    'should handle deletion of list elements',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', ['udon', 'ramen', 'soba']);
        $doc = $port->deleteListElements($doc, 'noodles', 1);
        sameArray($doc->toArray()['noodles'], ['udon', 'soba'], 'first list deletion should compact the list');

        $doc = $port->deleteListElements($doc, 'noodles', 1);
        sameArray($doc->toArray()['noodles'], ['udon'], 'second list deletion should leave only the first element');
        truthy(! array_key_exists(1, $doc->toArray()['noodles']), 'deleted list index should be absent after compaction');
    }
);

$mapped(
    'legacy lists accept only numeric indexes',
    'javascript/test/legacy_tests.ts',
    718,
    'should only allow numeric indexes',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', ['udon', 'ramen', 'soba']);
        $doc = $port->setListKey($doc, 'noodles', 1, 'Ramen!');
        same($doc->toArray()['noodles'][1], 'Ramen!', 'integer list index should update the requested element');

        $doc = $port->setListKey($doc, 'noodles', '1', 'RAMEN!!!');
        same($doc->toArray()['noodles'][1], 'RAMEN!!!', 'numeric string list index should update the requested element');

        foreach (['favourite', '', '1e6'] as $invalidIndex) {
            throwsLike(
                static fn (): Document => $port->setListKey($doc, 'noodles', $invalidIndex, 'udon'),
                'list index must be a number',
                'non-numeric list index should be rejected'
            );
        }
    }
);

$mapped(
    'legacy lists handle assignment of individual list indexes',
    'javascript/test/legacy_tests.ts',
    753,
    'should handle assignment of individual list indexes',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'japaneseFood', ['udon', 'ramen', 'soba']);
        $doc = $port->setListElement($doc, 'japaneseFood', 1, 'sushi');

        sameArray($doc->toArray()['japaneseFood'], ['udon', 'sushi', 'soba'], 'list index assignment should replace the requested element');
    }
);

$mapped(
    'legacy concurrent list insertions with equal counters use reverse actor order',
    'javascript/test/legacy_tests.ts',
    767,
    'concurrent edits insert in reverse actorid order if counters equal',
    function () use ($port): void {
        $doc1 = $port->init('aaaa');
        $doc2 = $port->init('bbbb');
        $doc1 = $port->set($doc1, 'list', []);
        $doc2 = $port->mergeDocuments($doc2, $doc1);

        $doc1 = $port->spliceList($doc1, 'list', 0, 0, ['2@aaaa']);
        $doc2 = $port->spliceList($doc2, 'list', 0, 0, ['2@bbbb']);
        $merged = $port->mergeDocuments($doc2, $doc1);

        sameArray($merged->toArray()['list'], ['2@bbbb', '2@aaaa'], 'same-position concurrent list inserts should use reverse actor id order when counters match');
        same($port->getConflicts($merged, 'list'), null, 'concurrent list inserts should not become a root list conflict');
    }
);

$mapped(
    'legacy concurrent list insertions with different counters use reverse counter order',
    'javascript/test/legacy_tests.ts',
    778,
    'concurrent edits insert in reverse counter order if different',
    function () use ($port): void {
        $doc1 = $port->init('aaaa');
        $doc2 = $port->init('bbbb');
        $doc1 = $port->set($doc1, 'list', []);
        $doc2 = $port->mergeDocuments($doc2, $doc1);

        $doc1 = $port->spliceList($doc1, 'list', 0, 0, ['2@aaaa']);
        $doc2 = $port->set($doc2, 'foo', '2@bbbb');
        $doc2 = $port->spliceList($doc2, 'list', 0, 0, ['3@bbbb']);
        $merged = $port->mergeDocuments($doc2, $doc1);

        sameArray($merged->toArray()['list'], ['3@bbbb', '2@aaaa'], 'same-position concurrent list inserts should use reverse operation counter order');
        same($port->getConflicts($merged, 'list'), null, 'different-counter concurrent list inserts should not become a root list conflict');
    }
);

$mapped(
    'legacy lists treat out-by-one assignment as insertion',
    'javascript/test/legacy_tests.ts',
    790,
    'should treat out-by-one assignment as insertion',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'japaneseFood', ['udon']);
        $doc = $port->setListElement($doc, 'japaneseFood', 1, 'sushi');

        sameArray($doc->toArray()['japaneseFood'], ['udon', 'sushi'], 'one-past-end list assignment should append');
    }
);

$mapped(
    'legacy lists reject out-of-range assignment',
    'javascript/test/legacy_tests.ts',
    800,
    'should not allow out-of-range assignment',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'japaneseFood', ['udon']);

        throwsLike(
            static fn (): Document => $port->setListElement($doc, 'japaneseFood', 4, 'ramen'),
            'out of bounds',
            'assignment beyond one-past-end should be rejected'
        );
    }
);

$mapped(
    'legacy lists allow bulk assignment of multiple list indexes',
    'javascript/test/legacy_tests.ts',
    807,
    'should allow bulk assignment of multiple list indexes',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', ['udon', 'ramen', 'soba']);
        $doc = $port->setListElement($doc, 'noodles', 0, "\u{3046}\u{3069}\u{3093}");
        $doc = $port->setListElement($doc, 'noodles', 2, "\u{305d}\u{3070}");

        sameArray($doc->toArray()['noodles'], ["\u{3046}\u{3069}\u{3093}", 'ramen', "\u{305d}\u{3070}"], 'bulk-style list assignment should update multiple indexes');
    }
);

$mapped(
    'legacy lists handle nested objects',
    'javascript/test/legacy_tests.ts',
    822,
    'should handle nested objects',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', [['type' => 'ramen', 'dishes' => ['tonkotsu', 'shoyu']]]);
        $doc = $port->insertListElements($doc, 'noodles', 1, [['type' => 'udon', 'dishes' => ['tempura udon']]]);
        $doc = $port->applyPatches($doc, [['action' => 'insert', 'path' => ['noodles', 0, 'dishes', 2], 'values' => ['miso']]]);

        sameArray(
            $doc->toArray()['noodles'],
            [
                ['type' => 'ramen', 'dishes' => ['tonkotsu', 'shoyu', 'miso']],
                ['type' => 'udon', 'dishes' => ['tempura udon']],
            ],
            'nested object list updates should materialize'
        );
    }
);

$mapped(
    'legacy lists handle nested lists',
    'javascript/test/legacy_tests.ts',
    848,
    'should handle nested lists',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodleMatrix', [['ramen', 'tonkotsu', 'shoyu']]);
        $doc = $port->insertListElements($doc, 'noodleMatrix', 1, [['udon', 'tempura udon']]);
        $doc = $port->applyPatches($doc, [['action' => 'insert', 'path' => ['noodleMatrix', 0, 3], 'values' => ['miso']]]);

        sameArray(
            $doc->toArray()['noodleMatrix'],
            [
                ['ramen', 'tonkotsu', 'shoyu', 'miso'],
                ['udon', 'tempura udon'],
            ],
            'nested list updates should materialize'
        );
    }
);

$mapped(
    'legacy lists handle replacement of the entire list',
    'javascript/test/legacy_tests.ts',
    911,
    'should handle replacement of the entire list',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', ['udon', 'soba', 'ramen']);
        $doc = $port->set($doc, 'japaneseNoodles', $doc->toArray()['noodles']);
        $doc = $port->set($doc, 'noodles', ['wonton', 'pho']);

        sameArray(
            $doc->toArray(),
            ['noodles' => ['wonton', 'pho'], 'japaneseNoodles' => ['udon', 'soba', 'ramen']],
            'whole-list replacement should not mutate the copied list'
        );
        truthy(! array_key_exists(2, $doc->toArray()['noodles']), 'replacement list should expose only the replacement elements');
    }
);

$mapped(
    'legacy lists allow assignment to change the type of a list element',
    'javascript/test/legacy_tests.ts',
    932,
    'should allow assignment to change the type of a list element',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', ['udon', 'soba', 'ramen']);
        $doc = $port->setListElement($doc, 'noodles', 1, ['type' => 'soba', 'options' => ['hot', 'cold']]);
        sameArray($doc->toArray()['noodles'], ['udon', ['type' => 'soba', 'options' => ['hot', 'cold']], 'ramen'], 'list element should change from scalar to map');

        $doc = $port->setListElement($doc, 'noodles', 1, ['hot soba', 'cold soba']);
        sameArray($doc->toArray()['noodles'], ['udon', ['hot soba', 'cold soba'], 'ramen'], 'list element should change from map to list');

        $doc = $port->setListElement($doc, 'noodles', 1, 'soba is the best');
        sameArray($doc->toArray()['noodles'], ['udon', 'soba is the best', 'ramen'], 'list element should change from list to scalar');
    }
);

$mapped(
    'legacy lists allow list creation and assignment in one logical callback',
    'javascript/test/legacy_tests.ts',
    964,
    'should allow list creation and assignment in the same change callback',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'letters', ['a', 'b', 'c']);
        $doc = $port->setListElement($doc, 'letters', 1, 'd');

        same($doc->toArray()['letters'][1], 'd', 'list assignment after creation should update the target index');
    }
);

$mapped(
    'legacy lists allow adding and removing list elements in one logical callback',
    'javascript/test/legacy_tests.ts',
    972,
    'should allow adding and removing list elements in the same change callback',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'noodles', []);
        $doc = $port->insertListElements($doc, 'noodles', 0, ['udon']);
        $doc = $port->deleteListElements($doc, 'noodles', 0);
        sameArray($doc->toArray(), ['noodles' => []], 'first add/remove list cycle should leave an empty list');

        $doc = $port->insertListElements($doc, 'noodles', 0, ['soba']);
        $doc = $port->deleteListElements($doc, 'noodles', 0);
        sameArray($doc->toArray(), ['noodles' => []], 'second add/remove list cycle should leave an empty list');
    }
);

$mapped(
    'legacy lists handle arbitrary-depth nesting',
    'javascript/test/legacy_tests.ts',
    994,
    'should handle arbitrary-depth nesting',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'maze', [[[[[[[['noodles', ['here']]]]]]]]]);
        $doc = $port->applyPatches($doc, [['action' => 'insert', 'path' => ['maze', 0, 0, 0, 0, 0, 0, 0, 1, 0], 'values' => ['found']]]);

        sameArray(
            $doc->toArray()['maze'],
            [[[[[[[['noodles', ['found', 'here']]]]]]]]],
            'deep nested list insertion should materialize at the targeted depth'
        );
        sameArray($port->load($port->save($doc))->toArray(), $doc->toArray(), 'deep nested list should round trip through native save/load');
    }
);

$mapped(
    'legacy lists reject references to existing document objects',
    'javascript/test/legacy_tests.ts',
    1010,
    'should not allow several references to the same list object',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'list', []);

        throwsLike(
            static fn (): Document => $port->set($doc, 'x', $port->objectReference($doc, ['list'])),
            'Cannot create a reference to an existing document object at /x',
            'assigning an existing list object to another root key should be rejected'
        );
        throwsLike(
            static fn (): Document => $port->set($doc, 'x', ['copy' => $port->objectReference($doc, ['list'])]),
            'Cannot create a reference to an existing document object at /x/copy',
            'assigning an existing list object through a nested object should be rejected'
        );
        sameArray($doc->toArray(), ['list' => []], 'rejected list references should not alter the source document');
    }
);

$mapped(
    'legacy nested maps and lists handle deep mixed mutations',
    'javascript/test/legacy_tests.ts',
    870,
    'should handle deep nesting',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'nesting', [
            'maps' => ['m1' => ['m2' => ['foo' => 'bar', 'baz' => []], 'm2a' => []]],
            'lists' => [[1, 2, 3], [[3, 4, 5, [6]], 7]],
            'mapsinlists' => [['foo' => 'bar'], [['bar' => 'baz']]],
            'listsinmaps' => ['foo' => [1, 2, 3], 'bar' => [[['baz' => '123']]]],
        ]);

        $doc = $port->setNested($doc, ['nesting', 'maps', 'm1a'], '123');
        $doc = $port->setNested($doc, ['nesting', 'maps', 'm1', 'm2', 'baz', 'xxx'], '123');
        $doc = $port->deleteNested($doc, ['nesting', 'maps', 'm1', 'm2a']);
        $doc = $port->applyPatches($doc, [['action' => 'del', 'path' => ['nesting', 'lists', 0]]]);
        $doc = $port->applyPatches($doc, [['action' => 'del', 'path' => ['nesting', 'lists', 0, 0, 3]]]);
        $doc = $port->applyPatches($doc, [['action' => 'insert', 'path' => ['nesting', 'lists', 0, 0, 3], 'values' => [100]]]);
        $doc = $port->setNested($doc, ['nesting', 'mapsinlists', 0, 'foo'], 'baz');
        $doc = $port->setNested($doc, ['nesting', 'mapsinlists', 1, 0, 'foo'], 'bar');
        $doc = $port->applyPatches($doc, [['action' => 'del', 'path' => ['nesting', 'mapsinlists', 1]]]);
        $doc = $port->applyPatches($doc, [['action' => 'insert', 'path' => ['nesting', 'listsinmaps', 'foo', 3], 'values' => [4]]]);
        $doc = $port->setNested($doc, ['nesting', 'listsinmaps', 'bar', 0, 0, 'baz'], '456');
        $doc = $port->deleteNested($doc, ['nesting', 'listsinmaps', 'bar']);

        sameArray(
            $doc->toArray(),
            [
                'nesting' => [
                    'maps' => [
                        'm1' => ['m2' => ['foo' => 'bar', 'baz' => ['xxx' => '123']]],
                        'm1a' => '123',
                    ],
                    'lists' => [[[3, 4, 5, 100], 7]],
                    'mapsinlists' => [['foo' => 'baz']],
                    'listsinmaps' => ['foo' => [1, 2, 3, 4]],
                ],
            ],
            'deep mixed map/list mutations should materialize like the upstream proxy update sequence'
        );
    }
);

$mapped(
    'legacy save/load allows a reloaded list to be mutated',
    'javascript/test/legacy_tests.ts',
    1547,
    'should allow a reloaded list to be mutated',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'foo', []);
        $doc = $port->load($port->save($doc));
        $doc = $port->pushList($doc, 'foo', [1]);
        $doc = $port->load($port->save($doc));

        sameArray($doc->toArray()['foo'], [1], 'reloaded lists should remain mutable and survive another save/load round trip');
    }
);

$mapped(
    'legacy save/load reloads a large inserted list',
    'javascript/test/legacy_tests.ts',
    1555,
    'should reload a document containing deflated columns',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'list', []);
        for ($i = 0; $i < 200; ++$i) {
            $index = $i === 0 ? 0 : ($i * 37) % ($i + 1);
            $doc = $port->insertListElements($doc, 'list', $index, ['a']);
        }

        $loaded = $port->load($port->save($doc));

        same(count($loaded->toArray()['list']), 200, 'large reloaded list should preserve its length');
        sameArray($loaded->toArray()['list'], array_fill(0, 200, 'a'), 'large reloaded list should preserve every inserted value');
    }
);

$mapped(
    'legacy counters can be incremented and deleted from nested maps',
    'javascript/test/legacy_tests.ts',
    1033,
    'should allow deleting counters from maps',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aabbcc'), 'birds', ['wrens' => new Counter(1)]);
        $doc2 = $port->incrementCounter($doc1, ['birds', 'wrens'], 2);
        $doc3 = $port->deleteNested($doc2, ['birds', 'wrens']);
        $wrens = $doc2->toArray()['birds']['wrens'];

        truthy($wrens instanceof Counter, 'nested counter should materialize as a native Counter');
        same($wrens->value(), 3, 'nested counter increment should add to the original value');
        sameArray($doc3->toArray(), ['birds' => []], 'deleting a nested counter should leave the containing map');
    }
);

$mapped(
    'legacy concurrent merge preserves updates of different root properties',
    'javascript/test/legacy_tests.ts',
    1070,
    'should merge concurrent updates of different properties',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aabbcc'), 'foo', 'bar');
        $doc2 = $port->set($port->init('ddeeff'), 'hello', 'world');
        $doc3 = $port->mergeDocuments($doc1, $doc2);

        sameArray($doc3->toArray(), ['foo' => 'bar', 'hello' => 'world'], 'different-property concurrent merge should preserve both values');
        same($port->getConflicts($doc3, 'foo'), null, 'different-property merge should report no foo conflict');
        same($port->getConflicts($doc3, 'hello'), null, 'different-property merge should report no hello conflict');
        sameArray($port->load($port->save($doc3))->toArray(), $doc3->toArray(), 'different-property merge should round trip through save/load');
    }
);

$mapped(
    'legacy counters add concurrent increments of the same property',
    'javascript/test/legacy_tests.ts',
    1083,
    'should add concurrent increments of the same property',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aabbcc'), 'counter', new Counter());
        $doc2 = $port->mergeDocuments($port->init('ddeeff'), $doc1);
        $doc1 = $port->incrementCounter($doc1, ['counter']);
        $doc2 = $port->incrementCounter($doc2, ['counter'], 2);
        $doc3 = $port->mergeDocuments($doc1, $doc2);

        $counter1 = $doc1->toArray()['counter'];
        $counter2 = $doc2->toArray()['counter'];
        $counter3 = $doc3->toArray()['counter'];
        truthy($counter1 instanceof Counter && $counter2 instanceof Counter && $counter3 instanceof Counter, 'counter values should materialize as native counters');
        same($counter1->value(), 1, 'first branch counter should include its local increment');
        same($counter2->value(), 2, 'second branch counter should include its local increment');
        same($counter3->value(), 3, 'merged counter should add concurrent increments with the same counter identity');
        same($port->getConflicts($doc3, 'counter'), null, 'same-counter increments should not create a conflict');
        same($port->load($port->save($doc3))->toArray()['counter']->value(), 3, 'merged counter should round trip through save/load');
    }
);

$mapped(
    'legacy counters add increments only to the values they precede',
    'javascript/test/legacy_tests.ts',
    1097,
    'should add increments only to the values they precede',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'counter', new Counter(0));
        $doc1 = $port->incrementCounter($doc1, ['counter']);
        $doc2 = $port->set($port->init('bbbbbb'), 'counter', new Counter(100));
        $doc2 = $port->incrementCounter($doc2, ['counter'], 3);
        $merged = $port->mergeDocuments($doc1, $doc2);

        $winner = $merged->toArray()['counter'];
        truthy($winner instanceof Counter, 'different-counter conflict should materialize a counter winner');
        same($winner->value(), 103, 'higher actor counter assignment should materialize with only its own increment');

        $conflicts = $port->getConflicts($merged, 'counter') ?? [];
        truthy($conflicts['1@aaaaaa'] instanceof Counter, 'first counter assignment should remain conflicted under its assignment op');
        truthy($conflicts['1@bbbbbb'] instanceof Counter, 'second counter assignment should remain conflicted under its assignment op');
        same($conflicts['1@aaaaaa']->value(), 1, 'first counter conflict should include only the increment that follows it');
        same($conflicts['1@bbbbbb']->value(), 103, 'second counter conflict should include only the increment that follows it');

        $loadedConflicts = $port->getConflicts($port->load($port->save($merged)), 'counter') ?? [];
        same($loadedConflicts['1@aaaaaa']->value(), 1, 'first counter conflict should survive save/load');
        same($loadedConflicts['1@bbbbbb']->value(), 103, 'second counter conflict should survive save/load');
    }
);

$mapped(
    'legacy concurrent same-field updates retain conflict values',
    'javascript/test/legacy_tests.ts',
    1119,
    'should detect concurrent updates of the same field',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'field', 'one');
        $doc2 = $port->set($port->init('aaaaaa'), 'field', 'two');
        $doc3 = $port->mergeDocuments($doc1, $doc2);

        sameArray($doc3->toArray(), ['field' => 'one'], 'same-field merge should materialize the deterministic winner');
        sameArray(
            $port->getConflicts($doc3, 'field') ?? [],
            [
                '1@aaaaaa' => 'two',
                '1@bbbbbb' => 'one',
            ],
            'same-field merge should report both conflicting assignments'
        );
        sameArray(
            $port->getConflicts($port->load($port->save($doc3)), 'field') ?? [],
            $port->getConflicts($doc3, 'field') ?? [],
            'same-field conflicts should round trip through save/load'
        );
    }
);

$mapped(
    'legacy concurrent same-field assignments retain different-type conflicts',
    'javascript/test/legacy_tests.ts',
    1151,
    'should handle assignment conflicts of different types',
    function () use ($port): void {
        $doc1 = $port->set($port->init('cccccc'), 'field', 'string');
        $doc2 = $port->set($port->init('bbbbbb'), 'field', ['list']);
        $doc3 = $port->set($port->init('aaaaaa'), 'field', ['thing' => 'map']);
        $merged = $port->mergeDocuments($port->mergeDocuments($doc1, $doc2), $doc3);

        same($merged->toArray()['field'], 'string', 'different-type conflict should materialize the deterministic winner');
        sameArray(
            $port->getConflicts($merged, 'field') ?? [],
            [
                '1@aaaaaa' => ['thing' => 'map'],
                '1@bbbbbb' => ['list'],
                '1@cccccc' => 'string',
            ],
            'different-type conflict should keep each branch value'
        );
    }
);

$mapped(
    'legacy concurrent root conflicts keep nested map changes on the assigned object id',
    'javascript/test/legacy_tests.ts',
    1164,
    'should handle changes within a conflicting map field',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'field', 'string');
        $doc2 = $port->set($port->init('aaaaaa'), 'field', []);
        $doc2 = $port->setNested($doc2, ['field', 'innerKey'], 42);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['field' => 'string'], 'root conflict should materialize the deterministic winner');
        sameArray(
            $port->getConflicts($merged, 'field') ?? [],
            [
                '1@aaaaaa' => ['innerKey' => 42],
                '1@bbbbbb' => 'string',
            ],
            'nested changes inside a conflicted map should remain attached to the original root assignment op'
        );
    }
);

$mapped(
    'basic conflict values are stable regardless of merge order',
    'javascript/test/basic_test.ts',
    526,
    'should obtain the same conflicts, regardless of merge order',
    function () use ($port): void {
        $doc1 = $port->setMany($port->init('aaaaaa'), ['x' => 1, 'y' => 2]);
        $doc2 = $port->setMany($port->init('bbbbbb'), ['x' => 3, 'y' => 4]);
        $merge12 = $port->mergeDocuments($port->clone($doc1), $port->clone($doc2));
        $merge21 = $port->mergeDocuments($port->clone($doc2), $port->clone($doc1));

        sameArray(
            $port->getConflicts($merge12, 'x') ?? [],
            $port->getConflicts($merge21, 'x') ?? [],
            'root conflicts should expose the same values independent of merge order'
        );
    }
);

$mapped(
    'basic merge conflict materialization matches after save and load',
    'javascript/test/basic_test.ts',
    342,
    'it should handle conflicts the same in merges as with loads',
    function () use ($port): void {
        $doc1 = $port->from(['sub' => ['x' => 0, 'y' => 0]], 'aaaaaa');
        $doc2 = $port->clone($doc1, 'bbbbbb');
        $doc3 = $port->clone($doc1, 'cccccc');
        $doc4 = $port->clone($doc1, 'dddddd');

        $doc1 = $port->setNested($doc1, ['sub', 'x'], 1);
        $doc2 = $port->setNested($doc2, ['sub', 'x'], 2);
        $doc3 = $port->setNested($doc3, ['sub', 'x'], 3);
        $doc4 = $port->setNested($doc4, ['sub', 'x'], 4);

        $doc1 = $port->setNested($doc1, ['sub', 'y'], 1);

        $doc2 = $port->setNested($doc2, ['sub', 'y'], 2);
        $doc2 = $port->setNested($doc2, ['sub', 'y'], 3);

        $doc3 = $port->setNested($doc3, ['sub', 'y'], 4);
        $doc3 = $port->setNested($doc3, ['sub', 'y'], 5);
        $doc3 = $port->setNested($doc3, ['sub', 'y'], 6);

        $doc4 = $port->setNested($doc4, ['sub', 'y'], 7);
        $doc4 = $port->setNested($doc4, ['sub', 'y'], 8);
        $doc4 = $port->setNested($doc4, ['sub', 'y'], 9);
        $doc4 = $port->setNested($doc4, ['sub', 'y'], 10);

        $merged = $port->init('eeeeee');
        $merged = $port->mergeDocuments($merged, $doc1);
        $merged = $port->mergeDocuments($merged, $doc2);
        $merged = $port->mergeDocuments($merged, $doc3);
        $merged = $port->mergeDocuments($merged, $doc4);

        $loaded = $port->load($port->save($merged));

        sameArray($merged->toArray()['sub'], $loaded->toArray()['sub'], 'loaded conflict winner should match merged conflict winner');
    }
);

$mapped(
    'conflict inspection returns detached map values',
    'javascript/test/conflicts.ts',
    5,
    'should not allow updating values inside a conflict outside of the change callback',
    function () use ($port): void {
        $doc1 = $port->from(['user' => ['name' => 'alice']], 'aaaaaa');
        $doc2 = $port->clone($doc1, 'bbbbbb');

        $doc1 = $port->set($doc1, 'user', ['name' => 'bob']);
        $doc2 = $port->set($doc2, 'user', ['name' => 'charlie']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        $inspected = $port->getConflicts($merged, 'user') ?? [];
        foreach ($inspected as $operationId => $value) {
            if (is_array($value) && ($value['name'] ?? null) === 'bob') {
                $inspected[$operationId]['name'] = 'Attila';
            }
        }

        $conflicts = $port->getConflicts($merged, 'user') ?? [];
        $names = array_map(
            static fn (mixed $value): mixed => is_array($value) ? ($value['name'] ?? null) : null,
            array_values($conflicts)
        );
        sort($names);

        sameArray($names, ['bob', 'charlie'], 'mutating inspected conflict maps should not mutate document conflicts');
    }
);

$mapped(
    'conflicted map values can be updated together',
    'javascript/test/conflicts.ts',
    56,
    'should allow updating  values inside a conflicted map',
    function () use ($port): void {
        $doc1 = $port->from(['user' => []], 'aaaaaa');
        $doc2 = $port->clone($doc1, 'bbbbbb');
        $doc3 = $port->clone($doc1, 'cccccc');

        $doc2 = $port->set($doc2, 'user', ['name' => 'alice']);
        $doc3 = $port->set($doc3, 'user', ['name' => 'charlie']);
        $doc1 = $port->set($doc1, 'user', ['name' => 'bob']);

        $merged = $port->mergeDocuments($port->mergeDocuments($doc1, $doc2), $doc3);
        sameArray(
            $port->getConflicts($merged, 'user') ?? [],
            [
                '2@aaaaaa' => ['name' => 'bob'],
                '2@bbbbbb' => ['name' => 'alice'],
                '2@cccccc' => ['name' => 'charlie'],
            ],
            'root user conflict should expose all concurrently assigned maps before nested update'
        );

        $updated = $port->setRootConflictMapValue($merged, 'user', 'name', 'Attila');
        $expected = [
            '2@aaaaaa' => ['name' => 'Attila'],
            '2@bbbbbb' => ['name' => 'Attila'],
            '2@cccccc' => ['name' => 'Attila'],
        ];

        sameArray($port->getConflicts($updated, 'user') ?? [], $expected, 'nested update should rewrite each conflicted map value');
        sameArray(
            $port->getConflicts($port->load($port->save($updated)), 'user') ?? [],
            $expected,
            'updated conflicted map values should survive save/load'
        );
    }
);

$mapped(
    'conflicted list element map values can be updated together',
    'javascript/test/conflicts.ts',
    100,
    'should allow updating  values inside a conflicted list',
    function () use ($port): void {
        $doc1 = $port->from(['users' => [['name' => 'ignored']]], 'aaaaaa');
        $doc2 = $port->clone($doc1, 'bbbbbb');
        $doc3 = $port->clone($doc1, 'cccccc');

        $doc2 = $port->setListElement($doc2, 'users', 0, ['name' => 'alice']);
        $doc3 = $port->setListElement($doc3, 'users', 0, ['name' => 'charlie']);
        $doc1 = $port->setListElement($doc1, 'users', 0, ['name' => 'bob']);

        $merged = $port->mergeDocuments($port->mergeDocuments($doc1, $doc2), $doc3);
        sameArray(
            $port->getListElementConflicts($merged, 'users', 0) ?? [],
            [
                '2@aaaaaa' => ['name' => 'bob'],
                '2@bbbbbb' => ['name' => 'alice'],
                '2@cccccc' => ['name' => 'charlie'],
            ],
            'users[0] conflict should expose all concurrently assigned maps before nested update'
        );

        $updated = $port->setRootConflictListElementMapValue($merged, 'users', 0, 'name', 'Attila');
        $expected = [
            '2@aaaaaa' => ['name' => 'Attila'],
            '2@bbbbbb' => ['name' => 'Attila'],
            '2@cccccc' => ['name' => 'Attila'],
        ];

        sameArray($port->getListElementConflicts($updated, 'users', 0) ?? [], $expected, 'nested list-element update should rewrite each conflicted map value');
        sameArray(
            $port->getListElementConflicts($port->load($port->save($updated)), 'users', 0) ?? [],
            $expected,
            'updated conflicted list-element map values should survive save/load'
        );
    }
);

$mapped(
    'legacy concurrent nested map assignments stay conflicted at the root',
    'javascript/test/legacy_tests.ts',
    1195,
    'should not merge concurrently assigned nested maps',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'config', ['background' => 'blue']);
        $doc2 = $port->set($port->init('aaaaaa'), 'config', ['logo_url' => 'logo.png']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['config'], ['background' => 'blue'], 'concurrent nested map assignment should keep the deterministic root winner');
        sameArray(
            $port->getConflicts($merged, 'config') ?? [],
            [
                '1@aaaaaa' => ['logo_url' => 'logo.png'],
                '1@bbbbbb' => ['background' => 'blue'],
            ],
            'concurrent nested map assignment should report root-level map conflicts'
        );
    }
);

$mapped(
    'legacy root assignment clears prior same-field conflicts',
    'javascript/test/legacy_tests.ts',
    1210,
    'should clear conflicts after assigning a new value',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'field', 'one');
        $doc2 = $port->set($port->init('aaaaaa'), 'field', 'two');
        $doc3 = $port->mergeDocuments($doc1, $doc2);
        $doc3 = $port->set($doc3, 'field', 'three');

        sameArray($doc3->toArray(), ['field' => 'three'], 'new assignment after conflict should materialize the replacement value');
        same($port->getConflicts($doc3, 'field'), null, 'new assignment should clear local conflict metadata');

        $doc2 = $port->mergeDocuments($doc2, $doc3);
        sameArray($doc2->toArray(), ['field' => 'three'], 'causally newer assignment should win during later merge');
        same($port->getConflicts($doc2, 'field'), null, 'causally newer assignment should not recreate the old conflict');
    }
);

$mapped(
    'root map overwrites retain the last scalar value',
    'javascript/test/basic_test.ts',
    183,
    'handle overwrites to values',
    function () use ($port): void {
        $doc = $port->init('aabbcc');
        foreach (['world1', 'world2', 'world3', 'world4'] as $value) {
            $doc = $port->set($doc, 'hello', $value);
        }

        sameArray($doc->toArray(), ['hello' => 'world4'], 'last scalar write should win in a linear document');
    }
);

$mapped(
    'object values materialize through root map set',
    'javascript/test/basic_test.ts',
    200,
    'handle set with object value',
    function () use ($port): void {
        $doc = $port->set(
            $port->init('aabbcc'),
            'subobj',
            ['hello' => 'world', 'subsubobj' => ['zip' => 'zop']]
        );

        sameArray(
            $doc->toArray(),
            ['subobj' => ['hello' => 'world', 'subsubobj' => ['zip' => 'zop']]],
            'nested object value should materialize deterministically'
        );
    }
);

$mapped(
    'list creation materializes an empty PHP list',
    'javascript/test/basic_test.ts',
    210,
    'handle simple list creation',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'list', []);

        sameArray($doc->toArray(), ['list' => []], 'empty list should materialize as an empty PHP array');
    }
);

$mapped(
    'simple list values can be read and replaced',
    'javascript/test/basic_test.ts',
    216,
    'handle simple lists',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'list', [1, 2, 3]);
        same(count($doc->toArray()['list']), 3, 'list should have three values');
        same($doc->toArray()['list'][1], 2, 'list item should be readable by index');

        $nextList = $doc->toArray()['list'];
        $nextList[1] = 'a';
        $doc = $port->set($doc, 'list', $nextList);
        sameArray($doc->toArray(), ['list' => [1, 'a', 3]], 'list replacement should materialize');
    }
);

$mapped(
    'basic getChanges/applyChanges replays simple list assignment',
    'javascript/test/basic_test.ts',
    238,
    'handle simple lists',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->set($doc1, 'list', [1, 2, 3]);
        $changes = $port->getChanges($doc1, $doc2);
        $docB2 = $port->applyChanges($port->init('bbbbbb'), $changes);

        sameArray($docB2->toArray(), $doc2->toArray(), 'simple list assignment should replay from changes');
    }
);

$mapped(
    'basic getChanges/applyChanges replays text splice at arbitrary root key',
    'javascript/test/basic_test.ts',
    248,
    'handle text',
    function () use ($port): void {
        $doc1 = $port->init('aabbcc');
        $doc2 = $port->set($doc1, 'list', 'hello');
        $doc2 = $port->splice($doc2, 'list', 2, 0, 'Z');
        $changes = $port->getChanges($doc1, $doc2);
        $docB2 = $port->applyChanges($port->init('bbbbbb'), $changes);

        same($docB2->toArray()['list'], 'heZllo', 'root-key text splice should replay visible text');
        sameArray($docB2->toArray(), $doc2->toArray(), 'text splice change set should replay from changes');
    }
);

$mapped(
    'text insertion exposes length, index access, and string materialization',
    'javascript/test/text_test.ts',
    17,
    'should support insertion',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'a');
        $text = $doc->text('text');

        same($text->length(), 1, 'inserted text should have length one');
        same($text->charAt(0), 'a', 'inserted text should be readable by index');
        same($text->toString(), 'a', 'inserted text should materialize to a string');
    }
);

$rustMapped(
    'rust text encoding length counts code points code units and graphemes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:length',
    'length',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, "hello👩‍👩‍👧‍👦");

        same($port->textLength($doc, ['text'], 'UnicodeCodePoint'), 12, 'unicode code point length should match upstream text encoding length');
        same($port->textLength($doc, ['text'], 'Utf8CodeUnit'), 30, 'UTF-8 code unit length should match upstream text encoding length');
        same($port->textLength($doc, ['text'], 'Utf16CodeUnit'), 16, 'UTF-16 code unit length should match upstream text encoding length');
        same($port->textLength($doc, ['text'], 'GraphemeCluster'), 6, 'grapheme cluster length should match upstream text encoding length');
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding splice maps code point code unit and grapheme indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:splice-text',
    'splice_text',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "hello 👩‍👩‍👧‍👦 world");
        $cases = [
            ['UnicodeCodePoint', 14],
            ['Utf8CodeUnit', 32],
            ['Utf16CodeUnit', 18],
            ['GraphemeCluster', 8],
        ];

        foreach ($cases as [$encoding, $index]) {
            $doc = $port->spliceTextEncoded($base, ['text'], $index, 0, 'beautiful ', $encoding);
            same(
                $doc->toArray()['text'],
                "hello 👩‍👩‍👧‍👦 beautiful world",
                $encoding . ' splice index should insert at the upstream text boundary'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding get maps code point code unit and grapheme indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:get',
    'get',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, "he👩‍👩‍👧‍👦lo");
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            same(
                $port->textAtEncodedIndex($doc, ['text'], $index, $encoding),
                'l',
                $encoding . ' get index should read the upstream text element'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding put maps code point code unit and grapheme indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:put',
    'put',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $doc = $port->putTextEncoded($base, ['text'], $index, 'L', $encoding);
            same(
                $doc->toArray()['text'],
                "he👩‍👩‍👧‍👦Llo",
                $encoding . ' put index should replace the upstream text element'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding insert maps code point code unit and grapheme indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:insert',
    'insert',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $doc = $port->insertTextEncoded($base, ['text'], $index, 'L', $encoding);
            same(
                $doc->toArray()['text'],
                "he👩‍👩‍👧‍👦Lllo",
                $encoding . ' insert index should insert at the upstream text boundary'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding delete maps code point code unit and grapheme indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:delete',
    'delete',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $doc = $port->deleteTextEncoded($base, ['text'], $index, $encoding);
            same(
                $doc->toArray()['text'],
                "he👩‍👩‍👧‍👦lo",
                $encoding . ' delete index should remove the upstream text element'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding mark maps code point code unit and grapheme ranges',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:mark',
    'mark',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
        $cases = [
            ['UnicodeCodePoint', 11, [[1, 11]]],
            ['Utf8CodeUnit', 27, [[1, 27]]],
            ['Utf16CodeUnit', 13, [[1, 13]]],
            ['GraphemeCluster', 4, [[1, 4]]],
        ];

        foreach ($cases as [$encoding, $endIndex, $expected]) {
            $doc = $port->markTextEncoded($base, ['text'], 1, $endIndex, 'bold', true, $encoding, 'both');
            $ranges = array_map(
                static fn (array $mark): array => [$mark['start'], $mark['end']],
                $port->marksEncoded($doc, ['text'], $encoding)
            );
            sameArray($ranges, $expected, $encoding . ' mark range should round trip through encoded indexes');
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding unmark maps code point code unit and grapheme ranges',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:unmark',
    'unmark',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
        $cases = [
            ['UnicodeCodePoint', 11],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 4],
        ];

        foreach ($cases as [$encoding, $endIndex]) {
            $doc = $port->markTextEncoded($base, ['text'], 1, $endIndex, 'bold', true, $encoding, 'both');
            $doc = $port->unmarkTextEncoded($doc, ['text'], 1, $endIndex, 'bold', $encoding);
            sameArray($port->marksEncoded($doc, ['text'], $encoding), [], $encoding . ' unmark range should remove the encoded mark');
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding splitBlock maps code point code unit and grapheme indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:split-block',
    'split_block',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $doc = $port->splitBlockEncoded($base, ['text'], $index, $encoding);
            $textSpans = array_values(array_map(
                static fn (array $span): string => $span['value'],
                array_filter($port->spans($doc, ['text']), static fn (array $span): bool => ($span['type'] ?? null) === 'text')
            ));
            sameArray($textSpans, ["he👩‍👩‍👧‍👦", 'llo'], $encoding . ' splitBlock index should split at the upstream text boundary');
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding cursors report encoded positions after edits',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:cursors',
    'cursors',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
        $cases = [
            ['UnicodeCodePoint', 9, 16],
            ['Utf8CodeUnit', 27, 52],
            ['Utf16CodeUnit', 13, 24],
            ['GraphemeCluster', 3, 4],
        ];

        foreach ($cases as [$encoding, $cursorIndex, $expectedPosition]) {
            $cursor = $port->getCursorEncoded($base, ['text'], $cursorIndex, $encoding);
            $doc = $port->spliceTextEncoded($base, ['text'], 2, 0, "👩‍👩‍👧‍👦", $encoding);
            same(
                $port->getCursorPositionEncoded($doc, ['text'], $cursor, $encoding),
                $expectedPosition,
                $encoding . ' cursor position should track through an inserted emoji'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding putSeq patches report encoded indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:patch-put-seq',
    'patch_put_seq',
    function () use ($port): void {
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $base = $port->set($port->init('aaaaaa'), 'text', '');
            $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
            $port->updateDiffCursor($base);
            $doc = $port->putTextEncoded($base, ['text'], $index, 'L', $encoding);
            sameArray(
                $port->diffIncrementalEncoded($doc, ['text'], $encoding),
                [['action' => 'putSeq', 'path' => ['text', $index], 'value' => 'L']],
                $encoding . ' putSeq patch should report the upstream encoded index'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding insert patches report encoded indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:patch-insert',
    'patch_insert',
    function () use ($port): void {
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $base = $port->set($port->init('aaaaaa'), 'text', '');
            $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
            $port->updateDiffCursor($base);
            $doc = $port->insertTextEncoded($base, ['text'], $index, 'L', $encoding);
            sameArray(
                $port->diffIncrementalEncoded($doc, ['text'], $encoding),
                [['action' => 'splice', 'path' => ['text', $index], 'value' => 'L']],
                $encoding . ' insert patch should report the upstream encoded index'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding splice patches report encoded indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:patch-splice-text',
    'patch_splice_text',
    function () use ($port): void {
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $base = $port->set($port->init('aaaaaa'), 'text', '');
            $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
            $port->updateDiffCursor($base);
            $doc = $port->spliceTextEncoded($base, ['text'], $index, 0, 'L', $encoding);
            sameArray(
                $port->diffIncrementalEncoded($doc, ['text'], $encoding),
                [['action' => 'splice', 'path' => ['text', $index], 'value' => 'L']],
                $encoding . ' splice patch should report the upstream encoded index'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding delete patches report encoded indexes',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:patch-delete',
    'patch_delete',
    function () use ($port): void {
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $index]) {
            $base = $port->set($port->init('aaaaaa'), 'text', '');
            $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
            $port->updateDiffCursor($base);
            $doc = $port->deleteTextEncoded($base, ['text'], $index, $encoding);
            sameArray(
                $port->diffIncrementalEncoded($doc, ['text'], $encoding),
                [['action' => 'del', 'path' => ['text', $index], 'length' => 1]],
                $encoding . ' delete patch should report the upstream encoded index'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$rustMapped(
    'rust text encoding mark patches report encoded ranges',
    'rust:tests-text-encoding-rs-target-debug-deps-text-encoding-3bef81c1b0c759ee:patch-mark',
    'patch_mark',
    function () use ($port): void {
        $cases = [
            ['UnicodeCodePoint', 9],
            ['Utf8CodeUnit', 27],
            ['Utf16CodeUnit', 13],
            ['GraphemeCluster', 3],
        ];

        foreach ($cases as [$encoding, $endIndex]) {
            $base = $port->set($port->init('aaaaaa'), 'text', '');
            $base = $port->splice($base, 'text', 0, 0, "he👩‍👩‍👧‍👦llo");
            $port->updateDiffCursor($base);
            $doc = $port->markTextEncoded($base, ['text'], 1, $endIndex, 'bold', true, $encoding, 'both');
            sameArray(
                $port->diffIncrementalEncoded($doc, ['text'], $encoding),
                [[
                    'action' => 'mark',
                    'path' => ['text'],
                    'marks' => [['name' => 'bold', 'value' => true, 'start' => 1, 'end' => $endIndex]],
                ]],
                $encoding . ' mark patch should report the upstream encoded range'
            );
        }
    },
    'rust/automerge/tests/text_encoding.rs'
);

$mapped(
    'text deletion removes the visible character at the splice range',
    'javascript/test/text_test.ts',
    25,
    'should support deletion',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'abc');
        $doc = $port->splice($doc, 'text', 1, 1);
        $text = $doc->text('text');

        same($text->length(), 2, 'deleted text should have two visible characters');
        same($text->charAt(0), 'a', 'first visible char should remain');
        same($text->charAt(1), 'c', 'third inserted char should shift into second position');
        same($text->toString(), 'ac', 'deleted text should materialize without tombstoned char');
    }
);

$mapped(
    'text zero-length splice after deletion is a no-op',
    'javascript/test/text_test.ts',
    36,
    'should support implicit and explicit deletion',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'abc');
        $doc = $port->splice($doc, 'text', 1, 1);
        $doc = $port->splice($doc, 'text', 1, 0);

        same($doc->text('text')->toString(), 'ac', 'zero-length splice should not change visible text');
    }
);

$rustMapped(
    'rust UTF-16 splice deletion inside multibyte characters snaps after the character',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:deleting-in-middle-of-multibyte-char-moves-the-cursor-to-after-the-character',
    'deleting_in_middle_of_multibyte_char_moves_the_cursor_to_after_the_character',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->spliceTextEncoded($base, ['text'], 0, 0, '🐻🐻🐻🐻🐻🐻', 'Utf16CodeUnit');
        same($doc->toArray()['text'], '🐻🐻🐻🐻🐻🐻', 'UTF-16 setup should preserve bear emoji text');

        $doc = $port->spliceTextEncoded($doc, ['text'], 2, 2, 'A🐻A', 'Utf16CodeUnit');
        same($doc->toArray()['text'], '🐻A🐻A🐻🐻🐻🐻', 'UTF-16 boundary splice should replace one full emoji');

        $replaceOne = $port->spliceTextEncoded($doc, ['text'], 4, 1, 'X', 'Utf16CodeUnit');
        same($replaceOne->toArray()['text'], '🐻A🐻X🐻🐻🐻🐻', 'UTF-16 splice inside an emoji should delete the following character');

        $replaceTwo = $port->spliceTextEncoded($doc, ['text'], 4, 2, 'Y', 'Utf16CodeUnit');
        same($replaceTwo->toArray()['text'], '🐻A🐻Y🐻🐻🐻', 'UTF-16 splice inside an emoji should measure deletion after the snapped boundary');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust legacy multi-character text op splices on op boundaries',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:splicing-into-multibyte-characters',
    'splicing_into_multibyte_characters',
    function () use ($port): void {
        $legacyText = new TextValue([
            ['id' => '1@aaaaaa', 'actor' => 'aaaaaa', 'seq' => 1, 'char' => 'A', 'visible' => true, 'after' => null, 'inserted' => false],
            ['id' => '2@bbbbbb', 'actor' => 'bbbbbb', 'seq' => 2, 'char' => 'BBBBB', 'visible' => true, 'after' => '1@aaaaaa', 'inserted' => false],
            ['id' => '3@bbbbbb', 'actor' => 'bbbbbb', 'seq' => 3, 'char' => "\u{fffc}", 'visible' => true, 'after' => '2@bbbbbb', 'inserted' => false],
            ['id' => '4@bbbbbb', 'actor' => 'bbbbbb', 'seq' => 4, 'char' => 'C', 'visible' => true, 'after' => '3@bbbbbb', 'inserted' => false],
        ]);
        $doc = $port->set($port->init('aaaaaa'), 'text', $legacyText);
        same($doc->text('text')->toString(), "ABBBBB\u{fffc}C", 'legacy setup should materialize the multi-character text op payload');

        $deleteThroughTail = $port->spliceTextEncoded($doc, ['text'], 3, 4, 'X', 'Utf16CodeUnit');
        same($deleteThroughTail->text('text')->toString(), 'ABBBBBX', 'deleting from the middle of a legacy multi-character op should snap after that op');

        $insertInside = $port->spliceTextEncoded($doc, ['text'], 3, 0, 'X', 'Utf16CodeUnit');
        same($insertInside->text('text')->toString(), "ABBBBBX\u{fffc}C", 'inserting inside a legacy multi-character op should insert after that op');

        $deleteObject = $port->spliceTextEncoded($doc, ['text'], 3, 1, '', 'Utf16CodeUnit');
        same($deleteObject->text('text')->toString(), 'ABBBBBC', 'deleting inside a legacy multi-character op should delete the following object marker');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text diff common prefix length matches byte ranges',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:text-diff-utils-test-common-prefix-len',
    'text_diff::utils::test_common_prefix_len',
    function () use ($port): void {
        same($port->textDiffCommonPrefixLen('', 0, 0, '', 0, 0), 0, 'empty ranges should have no common prefix');
        same($port->textDiffCommonPrefixLen('foobarbaz', 0, 9, 'foobarblah', 0, 10), 7, 'common prefix should count equal bytes from range starts');
        same($port->textDiffCommonPrefixLen('foobarbaz', 0, 9, 'blablabla', 0, 9), 0, 'different range starts should have no common prefix');
        same($port->textDiffCommonPrefixLen('foobarbaz', 3, 9, 'foobarblah', 3, 10), 4, 'offset ranges should count the shared byte prefix');
    },
    'rust/automerge/src/text_diff/utils.rs'
);

$rustMapped(
    'rust text diff common suffix length matches byte ranges',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:text-diff-utils-test-common-suffix-len',
    'text_diff::utils::test_common_suffix_len',
    function () use ($port): void {
        same($port->textDiffCommonSuffixLen('', 0, 0, '', 0, 0), 0, 'empty ranges should have no common suffix');
        same($port->textDiffCommonSuffixLen('1234', 0, 4, 'X0001234', 0, 8), 4, 'common suffix should count equal bytes from range ends');
        same($port->textDiffCommonSuffixLen('1234', 0, 4, 'Xxxx', 0, 4), 0, 'different range ends should have no common suffix');
        same($port->textDiffCommonSuffixLen('1234', 2, 4, '01234', 2, 5), 2, 'offset ranges should count the shared byte suffix');
    },
    'rust/automerge/src/text_diff/utils.rs'
);

$rustMapped(
    'rust clock covers op ids by actor counter',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:clock-tests-covers',
    'clock::tests::covers',
    function () use ($port): void {
        $clock = $port->clock(4);
        $clock = $port->clockInclude($clock, 1, 20);
        $clock = $port->clockInclude($clock, 2, 10);

        truthy($port->clockCovers($clock, 10, 1), 'clock should cover earlier actor 1 counters');
        truthy($port->clockCovers($clock, 20, 1), 'clock should cover exact actor 1 counter');
        truthy(! $port->clockCovers($clock, 30, 1), 'clock should not cover later actor 1 counters');

        truthy($port->clockCovers($clock, 5, 2), 'clock should cover earlier actor 2 counters');
        truthy($port->clockCovers($clock, 10, 2), 'clock should cover exact actor 2 counter');
        truthy(! $port->clockCovers($clock, 15, 2), 'clock should not cover later actor 2 counters');

        truthy(! $port->clockCovers($clock, 1, 3), 'clock should not cover unseen actor 3 counters');
        truthy(! $port->clockCovers($clock, 100, 3), 'clock should not cover large unseen actor 3 counters');
    },
    'rust/automerge/src/clock.rs'
);

$rustMapped(
    'rust clock comparison handles ordering and concurrency',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:clock-tests-comparison',
    'clock::tests::comparison',
    function () use ($port): void {
        $baseClock = $port->clock(4);
        $baseClock = $port->clockInclude($baseClock, 1, 1);
        $baseClock = $port->clockInclude($baseClock, 2, 1);

        $afterClock = $port->clockInclude($baseClock, 1, 2);

        same($port->clockCompare($afterClock, $baseClock), 'greater', 'after clock should dominate the base clock');
        same($port->clockCompare($baseClock, $afterClock), 'less', 'base clock should predate the after clock');
        same($port->clockCompare($baseClock, $baseClock), 'equal', 'identical clocks should compare equal');

        $newActorClock = $port->clockInclude($baseClock, 3, 1);

        same($port->clockCompare($baseClock, $newActorClock), 'less', 'adding a new actor should make that clock greater than base');
        same($port->clockCompare($newActorClock, $baseClock), 'greater', 'new actor clock should dominate the base clock');
        same($port->clockCompare($afterClock, $newActorClock), null, 'independent actor advances should be concurrent');
        same($port->clockCompare($newActorClock, $afterClock), null, 'concurrent comparison should be symmetric');
    },
    'rust/automerge/src/clock.rs'
);

$rustMapped(
    'rust change graph derives sequence clocks for heads',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:change-graph-tests-clock-by-heads',
    'change_graph::tests::clock_by_heads',
    function () use ($port): void {
        $changes = [
            ['hash' => 'change1', 'actor' => 0, 'seq' => 1, 'deps' => []],
            ['hash' => 'change2', 'actor' => 1, 'seq' => 1, 'deps' => ['change1']],
            ['hash' => 'change3', 'actor' => 2, 'seq' => 1, 'deps' => ['change1']],
            ['hash' => 'change4', 'actor' => 0, 'seq' => 2, 'deps' => ['change2', 'change3']],
        ];

        sameArray(
            $port->changeGraphSeqClockForHeads($changes, ['change4']),
            [2, 1, 1],
            'sequence clock should include the highest actor sequence reachable from the requested heads'
        );
    },
    'rust/automerge/src/change_graph.rs'
);

$rustMapped(
    'rust change graph removes ancestors of selected heads',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:change-graph-tests-remove-ancestors',
    'change_graph::tests::remove_ancestors',
    function () use ($port): void {
        $changes = [
            ['hash' => 'change1', 'actor' => 0, 'seq' => 1, 'deps' => []],
            ['hash' => 'change2', 'actor' => 1, 'seq' => 1, 'deps' => ['change1']],
            ['hash' => 'change3', 'actor' => 2, 'seq' => 1, 'deps' => ['change1']],
            ['hash' => 'change4', 'actor' => 0, 'seq' => 2, 'deps' => ['change2', 'change3']],
        ];

        sameArray(
            $port->changeGraphRemoveAncestors($changes, ['change1', 'change2', 'change3', 'change4'], ['change2']),
            ['change3', 'change4'],
            'ancestor removal should drop the selected head and its dependencies from the candidate set'
        );
    },
    'rust/automerge/src/change_graph.rs'
);

$rustMapped(
    'rust columnar unsigned LEB128 size examples match encoded byte counts',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-encoding-leb128-tests-ulebsize-examples',
    'columnar::encoding::leb128::tests::ulebsize_examples',
    function () use ($port): void {
        $examples = [
            '0' => 1,
            '1' => 1,
            '127' => 1,
            '128' => 2,
            '129' => 2,
            '169' => 2,
            '18446744073709551615' => 10,
        ];

        foreach ($examples as $value => $expectedSize) {
            same($port->uleb128Size($value), $expectedSize, 'unsigned LEB128 size should match upstream example value ' . $value);
        }
    },
    'rust/automerge/src/columnar/encoding/leb128.rs'
);

$rustMapped(
    'rust columnar signed LEB128 size examples match encoded byte counts',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-encoding-leb128-tests-lebsize-examples',
    'columnar::encoding::leb128::tests::lebsize_examples',
    function () use ($port): void {
        $examples = [
            '0' => 1,
            '1' => 1,
            '-1' => 1,
            '63' => 1,
            '64' => 2,
            '-64' => 1,
            '-65' => 2,
            '127' => 2,
            '128' => 2,
            '-127' => 2,
            '-128' => 2,
            '-2097152' => 4,
            '169' => 2,
            '-9223372036854775808' => 10,
            '9223372036854775807' => 10,
        ];

        foreach ($examples as $value => $expectedSize) {
            same($port->leb128Size($value), $expectedSize, 'signed LEB128 size should match upstream example value ' . $value);
        }
    },
    'rust/automerge/src/columnar/encoding/leb128.rs'
);

$rustMapped(
    'rust columnar unsigned LEB128 property boundary sizes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-encoding-leb128-tests-test-ulebsize',
    'columnar::encoding::leb128::tests::test_ulebsize',
    function () use ($port): void {
        $boundaries = [
            '0' => 1,
            '127' => 1,
            '128' => 2,
            '16383' => 2,
            '16384' => 3,
            '2097151' => 3,
            '2097152' => 4,
            '268435455' => 4,
            '268435456' => 5,
            '34359738367' => 5,
            '34359738368' => 6,
            '4398046511103' => 6,
            '4398046511104' => 7,
            '562949953421311' => 7,
            '562949953421312' => 8,
            '72057594037927935' => 8,
            '72057594037927936' => 9,
            '9223372036854775807' => 9,
            '9223372036854775808' => 10,
            '18446744073709551615' => 10,
        ];

        foreach ($boundaries as $value => $expectedSize) {
            same($port->uleb128Size($value), $expectedSize, 'unsigned LEB128 boundary size should match encoded byte count for ' . $value);
        }
    },
    'rust/automerge/src/columnar/encoding/leb128.rs'
);

$rustMapped(
    'rust columnar signed LEB128 property boundary sizes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-encoding-leb128-tests-test-lebsize',
    'columnar::encoding::leb128::tests::test_lebsize',
    function () use ($port): void {
        $boundaries = [
            '-64' => 1,
            '63' => 1,
            '-65' => 2,
            '64' => 2,
            '-8192' => 2,
            '8191' => 2,
            '-8193' => 3,
            '8192' => 3,
            '-1048576' => 3,
            '1048575' => 3,
            '-1048577' => 4,
            '1048576' => 4,
            '-134217728' => 4,
            '134217727' => 4,
            '-134217729' => 5,
            '134217728' => 5,
            '-17179869184' => 5,
            '17179869183' => 5,
            '-17179869185' => 6,
            '17179869184' => 6,
            '-9223372036854775808' => 10,
            '9223372036854775807' => 10,
        ];

        foreach ($boundaries as $value => $expectedSize) {
            same($port->leb128Size($value), $expectedSize, 'signed LEB128 boundary size should match encoded byte count for ' . $value);
        }
    },
    'rust/automerge/src/columnar/encoding/leb128.rs'
);

$ignoredMapped(
    'ignored Rust storage parse doc example consumes three bytes and leaves remaining input',
    'rust:doc-tests-automerge:automerge-src-storage-parse-rs-storage-parse-line-17',
    'automerge/src/storage/parse.rs - basic usage doc example',
    function () use ($port): void {
        $input = $port->storageParseInput([ord('1'), ord('2'), ord('3'), ord('4'), ord('5')]);
        [$input, $a] = $port->storageInputTakeOne($input);
        [$input, $b] = $port->storageInputTakeOne($input);
        [$input, $c] = $port->storageInputTakeOne($input);

        sameArray([$a, $b, $c], [ord('1'), ord('2'), ord('3')], 'storage parser doc example should parse three bytes in order');
        same($input['position'], 3, 'storage parser input position should advance after each take');
        sameArray($port->storageInputRemainingBytes($input), [ord('4'), ord('5')], 'storage parser input should retain unconsumed bytes');
    },
    'rust/automerge/src/storage/parse.rs'
);

$ignoredMapped(
    'ignored Rust storage parse split doc example separates chunk bytes from remaining input',
    'rust:doc-tests-automerge:automerge-src-storage-parse-rs-storage-parse-input-a-split-line-264',
    'automerge/src/storage/parse.rs - Input::split doc example',
    function () use ($port): void {
        $input = $port->storageParseInput([3, ord('a'), ord('b'), ord('c'), ord('x'), ord('y')]);
        [$input, $chunkLength] = $port->storageInputTakeOne($input);
        $split = $port->storageInputSplit($input, $chunkLength);

        sameArray($port->storageInputRemainingBytes($split['first']), [ord('a'), ord('b'), ord('c')], 'split first input should contain exactly the length-delimited chunk bytes');
        same($split['first']['position'], 1, 'split first input should preserve the caller position after parsing the length header');
        sameArray($port->storageInputRemainingBytes($split['remaining']), [ord('x'), ord('y')], 'split remaining input should contain bytes after the chunk');
        same($split['remaining']['position'], 4, 'split remaining input should advance past the length header and chunk bytes');
    },
    'rust/automerge/src/storage/parse.rs'
);

$ignoredMapped(
    'ignored Rust storage parse range_of doc example records the consumed byte range',
    'rust:doc-tests-automerge:automerge-src-storage-parse-rs-storage-parse-range-of-line-561',
    'automerge/src/storage/parse.rs - range_of doc example',
    function () use ($port): void {
        $input = $port->storageParseInput([ord('m'), ord('s'), ord('g')]);
        [$input, $parsed] = $port->storageInputRangeOfTakeOne($input);

        same($parsed['value'], ord('m'), 'range_of should return the parser result value');
        sameArray($parsed['range'], ['start' => 0, 'end' => 1], 'range_of should record the input range consumed by the parser');
        same($input['position'], 1, 'range_of should advance the caller input to the parser output position');
        sameArray($port->storageInputRemainingBytes($input), [ord('s'), ord('g')], 'range_of should preserve bytes after the parser output');
    },
    'rust/automerge/src/storage/parse.rs'
);

$ignoredMapped(
    'ignored Rust storage parse split remaining doc example exposes post-split backing bytes',
    'rust:doc-tests-automerge:automerge-src-storage-parse-rs-storage-parse-split-remaining-line-325',
    'automerge/src/storage/parse.rs - Split::remaining doc example',
    function () use ($port): void {
        $input = $port->storageParseInput([3, ord('a'), ord('b'), ord('c'), ord('x'), ord('y')]);
        [$input, $chunkLength] = $port->storageInputTakeOne($input);
        $split = $port->storageInputSplit($input, $chunkLength);

        sameArray($port->storageInputBytes($split['remaining']), [ord('x'), ord('y')], 'split remaining bytes() should expose only bytes after the split chunk');
        sameArray($port->storageInputRemainingBytes($split['remaining']), [ord('x'), ord('y')], 'split remaining unconsumed bytes should match the post-split bytes');
        same($split['remaining']['position'], 4, 'split remaining should preserve the absolute position reached after the split chunk');
    },
    'rust/automerge/src/storage/parse.rs'
);

$ignoredMapped(
    'ignored Rust storage document parse doc example materializes a parsed fixture',
    'rust:doc-tests-automerge:automerge-src-storage-document-rs-storage-document-document-a-parse-line-54',
    'automerge/src/storage/document.rs - Document::parse doc example',
    function () use ($port): void {
        $payload = file_get_contents(__DIR__ . '/../upstream/automerge/rust/automerge/tests/fixtures/two_change_chunks.automerge');
        truthy(is_string($payload), 'document parse fixture should be readable');
        $bytes = array_values(unpack('C*', $payload));
        $loaded = $port->storageDocumentFromBytes($bytes, 'bbbbbb');

        sameArray($loaded->toArray(), ['a' => ['a' => 'b']], 'document parse should materialize the fixture after header parsing');
    },
    'rust/automerge/src/storage/document.rs'
);

$ignoredMapped(
    'ignored Rust storage parse error doc example wraps parser-specific errors',
    'rust:doc-tests-automerge:automerge-src-storage-parse-rs-storage-parse-line-56',
    'automerge/src/storage/parse.rs - ParseError::Error doc example',
    function () use ($port): void {
        sameArray(
            $port->storageParseApplicationError('MyError'),
            ['type' => 'error', 'value' => 'MyError'],
            'parser-specific errors should be represented as application parse errors'
        );
    },
    'rust/automerge/src/storage/parse.rs'
);

$ignoredMapped(
    'ignored Rust storage parse lift doc example maps application errors and preserves incomplete errors',
    'rust:doc-tests-automerge:automerge-src-storage-parse-rs-storage-parse-line-69',
    'automerge/src/storage/parse.rs - ParseError::lift doc example',
    function () use ($port): void {
        sameArray(
            $port->storageParseErrorLift($port->storageParseApplicationError('BadString'), 'String'),
            ['type' => 'error', 'value' => ['variant' => 'String', 'source' => 'BadString']],
            'lift should wrap parser-specific errors in the combined error variant'
        );
        sameArray(
            $port->storageParseErrorLift($port->storageParseIncomplete(2), 'Number'),
            ['type' => 'incomplete', 'needed' => 2],
            'lift should leave incomplete parser errors unchanged'
        );
    },
    'rust/automerge/src/storage/parse.rs'
);

$rustMapped(
    'rust storage parser decodes canonical u64 LEB128 values',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:storage-parse-leb128-tests-leb-128-u64',
    'storage::parse::leb128::tests::leb_128_u64',
    function () use ($port): void {
        sameArray($port->storageParseLeb128U64([0b00000001]), ['value' => '1', 'offset' => 1], 'u64 parser should decode one-byte value');
        sameArray($port->storageParseLeb128U64([0b10000001, 0b00000001]), ['value' => '129', 'offset' => 2], 'u64 parser should decode multi-byte value');
        sameArray($port->storageParseLeb128U64([0b00000001, 0b00000011]), ['value' => '1', 'offset' => 1], 'u64 parser should stop after first complete value');

        $success = [
            [[0], '0'],
            [[0x7f], '127'],
            [[0x80, 0x01], '128'],
            [[0xff, 0x7f], '16383'],
            [[0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0x01], '18446744073709551615'],
        ];
        foreach ($success as [$bytes, $expected]) {
            same($port->storageParseLeb128U64($bytes)['value'], $expected, 'u64 parser should accept canonical value ' . $expected);
        }

        foreach ([[129, 129, 129, 129, 129, 129, 129, 129, 129, 129, 129, 129], [129, 129, 129, 129, 129, 129, 129, 129, 129, 2]] as $bytes) {
            throwsLike(
                static fn (): array => $port->storageParseLeb128U64($bytes),
                'too large for u64',
                'u64 parser should reject oversized encodings'
            );
        }
        throwsLike(
            static fn (): array => $port->storageParseLeb128U64([129, 0]),
            'overlong',
            'u64 parser should reject overlong encodings'
        );
        throwsLike(
            static fn (): array => $port->storageParseLeb128U64([255]),
            'Truncated',
            'u64 parser should reject truncated encodings'
        );
    },
    'rust/automerge/src/storage/parse/leb128.rs'
);

$rustMapped(
    'rust storage parser decodes canonical u32 LEB128 values',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:storage-parse-leb128-tests-leb-128-u32',
    'storage::parse::leb128::tests::leb_128_u32',
    function () use ($port): void {
        $success = [
            [[0], '0'],
            [[0x7f], '127'],
            [[0x80, 0x01], '128'],
            [[0xff, 0x7f], '16383'],
            [[0xff, 0xff, 0xff, 0xff, 0x0f], '4294967295'],
        ];
        foreach ($success as [$bytes, $expected]) {
            same($port->storageParseLeb128U32($bytes)['value'], $expected, 'u32 parser should accept canonical value ' . $expected);
        }

        foreach ([[129, 129, 129, 129, 129, 129, 129, 129, 129, 129, 129, 129], [0xff, 0xff, 0xff, 0xff, 0x1f]] as $bytes) {
            throwsLike(
                static fn (): array => $port->storageParseLeb128U32($bytes),
                'too large',
                'u32 parser should reject oversized encodings'
            );
        }
        throwsLike(
            static fn (): array => $port->storageParseLeb128U32([129, 0]),
            'overlong',
            'u32 parser should reject overlong encodings'
        );
        throwsLike(
            static fn (): array => $port->storageParseLeb128U32([0xaa]),
            'Truncated',
            'u32 parser should reject truncated encodings'
        );
    },
    'rust/automerge/src/storage/parse/leb128.rs'
);

$rustMapped(
    'rust storage parser decodes canonical i64 LEB128 values',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:storage-parse-leb128-tests-leb-128-i64',
    'storage::parse::leb128::tests::leb_128_i64',
    function () use ($port): void {
        $success = [
            [[0], '0'],
            [[0x7f], '-1'],
            [[0x3f], '63'],
            [[0x40], '-64'],
            [[0x80, 0x01], '128'],
            [[0xff, 0x3f], '8191'],
            [[0x80, 0x40], '-8192'],
            [[0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0x00], '9223372036854775807'],
            [[0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x7f], '-9223372036854775808'],
        ];
        foreach ($success as [$bytes, $expected]) {
            same($port->storageParseLeb128I64($bytes)['value'], $expected, 'i64 parser should accept canonical value ' . $expected);
        }

        foreach (
            [
                [129, 129, 129, 129, 129, 129, 129, 129, 129, 129, 129, 129],
                [0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0x01],
                [0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x7e],
            ] as $bytes
        ) {
            throwsLike(
                static fn (): array => $port->storageParseLeb128I64($bytes),
                'too large for i64',
                'i64 parser should reject oversized encodings'
            );
        }
        foreach ([[0xbf, 0], [0x81, 0xff, 0x7f]] as $bytes) {
            throwsLike(
                static fn (): array => $port->storageParseLeb128I64($bytes),
                'overlong',
                'i64 parser should reject overlong encodings'
            );
        }
        throwsLike(
            static fn (): array => $port->storageParseLeb128I64([0x90]),
            'Truncated',
            'i64 parser should reject truncated encodings'
        );
    },
    'rust/automerge/src/storage/parse/leb128.rs'
);

$rustMapped(
    'rust change encoding expanded change round-trips raw storage bytes',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:test-change-encoding-expanded-change-round-trip',
    'test_change_encoding_expanded_change_round_trip',
    function () use ($port): void {
        $changeBytes = [
            0x85, 0x6f, 0x4a, 0x83,
            0xb2, 0x98, 0x9e, 0xa9,
            1, 61, 0, 2, 0x12, 0x34,
            1, 1, 252, 250, 220, 255, 5,
            14, 73, 110, 105, 116, 105, 97, 108, 105, 122, 97, 116, 105, 111, 110,
            0, 6,
            0x15, 3, 0x34, 1, 0x42, 2,
            0x56, 2, 0x57, 1, 0x70, 2,
            0x7f, 1, 0x78,
            1,
            0x7f, 1,
            0x7f, 19,
            1,
            0x7f, 0,
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9,
        ];

        $change = $port->storageChangeFromBytes($changeBytes);
        sameArray($port->storageChangeRawBytes($change), $changeBytes, 'decoded storage change should retain the exact raw chunk bytes');
        same($change['checksum'], 'b2989ea9', 'change checksum should match the upstream fixture header');
        same(substr($change['hash'], 0, 8), 'b2989ea9', 'computed change hash should supply the header checksum bytes');
        same($change['actor'], '1234', 'binary actor ID should decode as the fixture actor');
        same($change['seq'], 1, 'change sequence should decode from unsigned LEB128');
        same($change['startOp'], 1, 'change startOp should decode from nonzero unsigned LEB128');
        same($change['time'], 1610038652, 'change timestamp should decode from signed LEB128');
        same($change['message'], 'Initialization', 'change message should decode as UTF-8');
        sameArray($change['deps'], [], 'fixture change should have no dependencies');
        sameArray($change['otherActors'], [], 'fixture change should have no other actors');
        sameArray(
            array_column($change['rawColumns'], 'spec'),
            [0x15, 0x34, 0x42, 0x56, 0x57, 0x70],
            'raw column specs should decode in normalized storage order'
        );
        sameArray(
            array_column($change['rawColumns'], 'length'),
            [3, 1, 2, 2, 1, 2],
            'raw column byte lengths should match the fixture metadata'
        );
        sameArray($change['opsData'], [0x7f, 1, 0x78, 1, 0x7f, 1, 0x7f, 19, 1, 0x7f, 0], 'operation data bytes should be split from trailing bytes');
        sameArray($change['extraBytes'], [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], 'trailing bytes should be preserved');

        $expanded = $port->storageExpandedChangeFromChange($change);
        $unexpanded = $port->storageChangeFromExpandedChange($expanded);
        sameArray($port->storageChangeRawBytes($unexpanded), $changeBytes, 'expanded change should collapse back to identical raw bytes');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust compressed change chunks inflate to the original raw change bytes',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:test-compressed-changes',
    'test_compressed_changes',
    function () use ($port): void {
        $baseChangeBytes = [
            0x85, 0x6f, 0x4a, 0x83,
            0xb2, 0x98, 0x9e, 0xa9,
            1, 61, 0, 2, 0x12, 0x34,
            1, 1, 252, 250, 220, 255, 5,
            14, 73, 110, 105, 116, 105, 97, 108, 105, 122, 97, 116, 105, 111, 110,
            0, 6,
            0x15, 3, 0x34, 1, 0x42, 2,
            0x56, 2, 0x57, 1, 0x70, 2,
            0x7f, 1, 0x78,
            1,
            0x7f, 1,
            0x7f, 19,
            1,
            0x7f, 0,
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9,
        ];
        $toBytes = static function (string $bytes): array {
            return $bytes === '' ? [] : array_values(unpack('C*', $bytes));
        };
        $toString = static function (array $bytes): string {
            $raw = '';
            foreach ($bytes as $byte) {
                $raw .= chr($byte);
            }

            return $raw;
        };
        $encodeUleb = static function (int $value): array {
            $bytes = [];
            do {
                $byte = $value & 0x7f;
                $value = intdiv($value, 128);
                if ($value !== 0) {
                    $byte |= 0x80;
                }
                $bytes[] = $byte;
            } while ($value !== 0);

            return $bytes;
        };

        $body = array_merge(array_slice($baseChangeBytes, 10), array_fill(0, 300, 10));
        $lengthBytes = $encodeUleb(count($body));
        $hashInput = $toString([1]) . $toString($lengthBytes) . $toString($body);
        $checksum = $toBytes(substr(hash('sha256', $hashInput, true), 0, 4));
        $uncompressed = array_merge([0x85, 0x6f, 0x4a, 0x83], $checksum, [1], $lengthBytes, $body);

        truthy(count($uncompressed) > 256, 'uncompressed fixture should cross the upstream deflate threshold');
        $decoded = $port->storageChangeFromBytes($uncompressed);
        sameArray($decoded['extraBytes'], array_merge(range(0, 9), array_fill(0, 300, 10)), 'expanded uncompressed change should retain trailing bytes before compression');

        $compressed = $port->storageCompressChangeBytes($uncompressed);
        truthy(count($compressed) < count($uncompressed), 'compressed change chunk should be smaller than the uncompressed chunk');
        same($compressed[8] ?? null, 2, 'compressed change chunk should use the Automerge compressed chunk type');

        sameArray($port->storageDecompressChangeBytes($compressed), $uncompressed, 'compressed chunk should inflate to the original raw change bytes');
        $reloaded = $port->storageChangeFromBytes($compressed);
        sameArray($port->storageChangeRawBytes($reloaded), $uncompressed, 'loading a compressed change should expose the original raw change bytes');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust compressed document columns ignore deflate bit while preserving storage order',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:test-compressed-doc-cols',
    'test_compressed_doc_cols',
    function () use ($port): void {
        $keyCtrSpec = $port->columnSpecEncode(1, 'DeltaInteger');
        $keyStrSpec = $port->columnSpecEncode(1, 'String');
        $keyCtrBytes = array_fill(0, 320, 7);
        $keyStrBytes = [ord('l'), ord('i'), ord('s'), ord('t')];
        $data = array_merge($keyCtrBytes, $keyStrBytes);

        $compressed = $port->storageCompressRawColumns(
            [
                ['spec' => $keyCtrSpec, 'length' => count($keyCtrBytes)],
                ['spec' => $keyStrSpec, 'length' => count($keyStrBytes)],
            ],
            $data,
            256
        );

        $deflatedKeyCtrSpec = $port->columnSpecDeflated($keyCtrSpec);
        sameArray(
            array_column($compressed['columns'], 'spec'),
            [$deflatedKeyCtrSpec, $keyStrSpec],
            'long key counter column should be deflated while the short key string column stays inflated'
        );
        truthy($deflatedKeyCtrSpec > $keyStrSpec, 'fixture should prove raw spec ordering would reject the compressed column');
        truthy(
            $compressed['columns'][0]['normalized'] < $compressed['columns'][1]['normalized'],
            'compressed document columns should compare normalized specs with the deflate bit ignored'
        );
        truthy(count($compressed['data']) < count($data), 'compressed document columns should be smaller than uncompressed column data');

        $inflated = $port->storageDecompressRawColumns($compressed['columns'], $compressed['data']);
        sameArray($inflated['data'], $data, 'compressed document columns should inflate back to original column bytes');
        sameArray(
            array_column($inflated['columns'], 'spec'),
            [$keyCtrSpec, $keyStrSpec],
            'inflating compressed document columns should clear the deflate bit'
        );
        sameArray(
            array_column($inflated['columns'], 'length'),
            [count($keyCtrBytes), count($keyStrBytes)],
            'inflated document column lengths should match the original storage layout'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust load rejects overlong counter LEB128 fixture encodings',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:overlong-leb',
    'overlong_leb',
    function () use ($port): void {
        throwsLike(
            static fn (): array => $port->storageParseLeb128U64Exact([0x01, 0x00]),
            'trailing bytes',
            'counter value metadata declaring two bytes for a one-byte LEB should be rejected'
        );
        throwsLike(
            static fn (): array => $port->storageParseLeb128U64Exact([0x80, 0x00]),
            'overlong',
            'counter value encoded in two bytes when one byte suffices should be rejected'
        );
        sameArray(
            $port->storageParseLeb128U64Exact([0xd0, 0x0f]),
            ['value' => '2000', 'offset' => 2],
            'canonical counter fixture LEB should decode to the fixture counter value'
        );

        $doc = $port->set($port->init('aaaaaa'), 'a', new Counter(2000));
        $loaded = $port->load($port->save($doc), 'bbbbbb');
        $counter = $loaded->toArray()['a'] ?? null;
        truthy($counter instanceof Counter, 'valid counter fixture should load as a native counter');
        same($counter->value(), 2000, 'valid counter fixture should preserve the counter value');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust load materializes two-change storage fixtures',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:load',
    'load',
    function () use ($port): void {
        foreach ([
            'two_change_chunks.automerge',
            'two_change_chunks_compressed.automerge',
            'two_change_chunks_out_of_order.automerge',
        ] as $fixtureName) {
            $payload = file_get_contents(__DIR__ . '/../upstream/automerge/rust/automerge/tests/fixtures/' . $fixtureName);
            truthy(is_string($payload), 'storage fixture should be readable: ' . $fixtureName);

            $loaded = $port->loadStorageDocument($payload, 'bbbbbb');
            sameArray(
                $loaded->toArray(),
                ['a' => ['a' => 'b']],
                'storage fixture should load the nested map value: ' . $fixtureName
            );
        }
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust load rejects fuzz-crasher fixture bytes',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:fuzz-crashers',
    'fuzz_crashers',
    function () use ($port): void {
        $fixtures = glob(__DIR__ . '/../upstream/automerge/rust/automerge/tests/fuzz-crashers/*');
        sort($fixtures);
        same(count($fixtures), 8, 'pinned upstream fuzz-crasher inventory should contain eight fixtures');

        foreach ($fixtures as $fixture) {
            $payload = file_get_contents($fixture);
            truthy(is_string($payload), 'fuzz-crasher fixture should be readable: ' . basename($fixture));
            try {
                $port->load($payload);
            } catch (Throwable) {
                continue;
            }

            throw new RuntimeException('fuzz-crasher fixture unexpectedly loaded: ' . basename($fixture));
        }
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust ExId root serializes and parses as the root object id',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:exid-tests-test-root-roundtrip',
    'exid::tests::test_root_roundtrip',
    function () use ($port): void {
        $bytes = $port->exIdToBytes(['type' => 'root']);

        sameArray($bytes, [0], 'root ExId should serialize to the version/type tag only');
        sameArray($port->exIdFromBytes($bytes), ['type' => 'root', 'display' => '_root'], 'root ExId should parse back to root');
        same($port->exIdDisplay(['type' => 'root']), '_root', 'root ExId display should be _root');
    },
    'rust/automerge/src/exid.rs'
);

$rustMapped(
    'rust ExId non-root object ids round-trip through bytes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:exid-tests-objid-roundtrip',
    'exid::tests::objid_roundtrip',
    function () use ($port): void {
        $vectors = [
            ['type' => 'id', 'counter' => '1', 'actor' => '01234567', 'actorIndex' => '0'],
            ['type' => 'id', 'counter' => '128', 'actor' => 'deadbeef', 'actorIndex' => '3'],
            ['type' => 'id', 'counter' => '18446744073709551615', 'actor' => '', 'actorIndex' => '9223372036854775807'],
        ];

        sameArray(
            $port->exIdToBytes(['type' => 'id', 'counter' => '128', 'actor' => '01234567', 'actorIndex' => '3']),
            [0x10, 0x04, 0x01, 0x23, 0x45, 0x67, 0x03, 0x80, 0x01],
            'non-root ExId bytes should follow version/type, actor length, actor bytes, actor index, counter'
        );

        foreach ($vectors as $vector) {
            $parsed = $port->exIdFromBytes($port->exIdToBytes($vector));
            same($parsed['type'], 'id', 'parsed ExId should remain non-root');
            same($parsed['counter'], $vector['counter'], 'parsed ExId counter should round-trip');
            same($parsed['actor'], strtolower($vector['actor']), 'parsed ExId actor bytes should round-trip');
            same($parsed['actorIndex'], $vector['actorIndex'], 'parsed ExId actor index hint should round-trip');
            same($parsed['display'], $port->exIdDisplay($vector), 'parsed ExId display should match upstream Display shape');
        }

        throwsLike(
            static fn (): array => $port->exIdFromBytes([]),
            'version tag',
            'ExId parser should reject missing version tags'
        );
        throwsLike(
            static fn (): array => $port->exIdFromBytes([0x01]),
            'version',
            'ExId parser should reject unsupported versions'
        );
        throwsLike(
            static fn (): array => $port->exIdFromBytes([0x20]),
            'type',
            'ExId parser should reject unsupported type tags'
        );
    },
    'rust/automerge/src/exid.rs'
);

$rustMapped(
    'rust 64-bit object ids do not truncate to root',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:obj-id-64bits',
    'obj_id_64bits',
    function () use ($port): void {
        $largeCounter = '4398046511104';
        $id = ['type' => 'id', 'counter' => $largeCounter, 'actor' => '0123456789abcdef', 'actorIndex' => '0'];
        $bytes = $port->exIdToBytes($id);
        $parsed = $port->exIdFromBytes($bytes);

        truthy($bytes !== [0], '64-bit object id should not encode as the root id');
        same($parsed['type'], 'id', '64-bit object id should parse as a non-root id');
        same($parsed['counter'], $largeCounter, '64-bit object id counter should not truncate on PHP');
        same($parsed['display'], $largeCounter . '@0123456789abcdef', '64-bit object id display should preserve the large counter');
        same($port->exIdDisplay($id), $parsed['display'], 'display formatting should match the parsed 64-bit object id');
        sameArray($port->exIdFromBytes([0]), ['type' => 'root', 'display' => '_root'], 'root id should remain distinguishable from large counters');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust storage column specifications encode id type and deflate bit',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:storage-columns-column-specification-tests-column-spec-encoding',
    'storage::columns::column_specification::tests::column_spec_encoding',
    function () use ($port): void {
        $scenarios = [
            ['id' => 7, 'type' => 'Group', 'raw' => 112],
            ['id' => 0, 'type' => 'Actor', 'raw' => 1],
            ['id' => 0, 'type' => 'Integer', 'raw' => 2],
            ['id' => 1, 'type' => 'DeltaInteger', 'raw' => 19],
            ['id' => 3, 'type' => 'Boolean', 'raw' => 52],
            ['id' => 1, 'type' => 'String', 'raw' => 21],
            ['id' => 5, 'type' => 'ValueMetadata', 'raw' => 86],
            ['id' => 5, 'type' => 'Value', 'raw' => 87],
        ];

        foreach ($scenarios as $index => $scenario) {
            $raw = $port->columnSpecEncode($scenario['id'], $scenario['type']);
            same($raw, $scenario['raw'], 'column spec scenario ' . ($index + 1) . ' should encode to the upstream raw integer');

            $decoded = $port->columnSpecDecode($raw);
            same($decoded['id'], $scenario['id'], 'column spec scenario ' . ($index + 1) . ' should decode the column id');
            same($decoded['type'], $scenario['type'], 'column spec scenario ' . ($index + 1) . ' should decode the column type');
            same($decoded['deflate'], false, 'column spec scenario ' . ($index + 1) . ' should default to inflated');
            same($decoded['normalized'], $raw, 'column spec scenario ' . ($index + 1) . ' should normalize to the raw value when inflated');

            $deflated = $port->columnSpecEncode($scenario['id'], $scenario['type'], true);
            same($port->columnSpecDecode($deflated)['id'], $scenario['id'], 'deflated column spec should preserve id');
            same($port->columnSpecDecode($deflated)['type'], $scenario['type'], 'deflated column spec should preserve type');
            same($port->columnSpecDecode($deflated)['deflate'], true, 'deflated column spec should expose the deflate flag');
            same($deflated, $scenario['raw'] | 0x08, 'deflated column spec should set bit 3');
            same($port->columnSpecNormalize($deflated), $raw, 'deflated column spec should normalize to inflated raw value');
            same($port->columnSpecInflated($deflated), $raw, 'inflated helper should clear the deflate bit');
            same($port->columnSpecDeflated($raw), $deflated, 'deflated helper should set the deflate bit');
        }
    },
    'rust/automerge/src/storage/columns/column_specification.rs'
);

$rustMapped(
    'rust sequence tree push appends values at the back',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sequence-tree-tests-push-back',
    'sequence_tree::tests::push_back',
    function () use ($port): void {
        $tree = $port->sequenceTreeNew();
        foreach ([1, 2, 3, 4, 5, 6, 8, 100] as $value) {
            $tree = $port->sequenceTreePush($tree, $value);
        }

        same($port->sequenceTreeLen($tree), 8, 'SequenceTree push should update length');
        sameArray($port->sequenceTreeIter($tree), [1, 2, 3, 4, 5, 6, 8, 100], 'SequenceTree push should append in order');
        same($port->sequenceTreeGet($tree, 7), 100, 'SequenceTree get should read the final pushed value');
    },
    'rust/automerge/src/sequence_tree.rs'
);

$rustMapped(
    'rust sequence tree insert accepts interior and prefix insertions',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sequence-tree-tests-insert',
    'sequence_tree::tests::insert',
    function () use ($port): void {
        $tree = $port->sequenceTreeNew();
        foreach ([0, 1, 0, 0, 0, 3, 4] as $offset => $index) {
            $tree = $port->sequenceTreeInsert($tree, $index, $offset + 1);
        }

        sameArray($port->sequenceTreeIter($tree), [5, 4, 3, 6, 7, 1, 2], 'SequenceTree insert should match Vec-style indexed insertion order');
        same($port->sequenceTreeLen($tree), 7, 'SequenceTree insert should update length for every insertion');
    },
    'rust/automerge/src/sequence_tree.rs'
);

$rustMapped(
    'rust sequence tree repeated book insertions remain iterable',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sequence-tree-tests-insert-book',
    'sequence_tree::tests::insert_book',
    function () use ($port): void {
        $tree = $port->sequenceTreeNew();
        for ($i = 0; $i < 100; ++$i) {
            $tree = $port->sequenceTreeInsert($tree, $i % 2, $i);
        }

        same($port->sequenceTreeLen($tree), 100, 'SequenceTree repeated book insertion should preserve every value');
        same(count($port->sequenceTreeIter($tree)), 100, 'SequenceTree iterator should yield every inserted value');
    },
    'rust/automerge/src/sequence_tree.rs'
);

$rustMapped(
    'rust sequence tree indexed insertion matches vector behavior',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sequence-tree-tests-insert-book-vec',
    'sequence_tree::tests::insert_book_vec',
    function () use ($port): void {
        $tree = $port->sequenceTreeNew();
        $vector = [];
        for ($i = 0; $i < 100; ++$i) {
            $index = $i % 3;
            $tree = $port->sequenceTreeInsert($tree, $index, $i);
            array_splice($vector, $index, 0, [$i]);

            sameArray($port->sequenceTreeIter($tree), array_values($vector), 'SequenceTree should match vector insertion after step ' . $i);
        }
    },
    'rust/automerge/src/sequence_tree.rs'
);

$rustMapped(
    'rust sequence tree proptest insert workload matches vector behavior',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sequence-tree-tests-proptest-insert',
    'sequence_tree::tests::proptest_insert',
    function () use ($port): void {
        $tree = $port->sequenceTreeNew();
        $vector = [];
        $indices = [
            0, 1, 0, 2, 1, 3, 0, 4, 2, 5,
            1, 6, 3, 7, 2, 8, 0, 9, 5, 10,
            4, 11, 6, 12, 8, 13, 7, 14, 9, 15,
            11, 16, 10, 17, 12, 18, 14, 19, 13, 20,
        ];

        foreach ($indices as $step => $i) {
            if ($i > count($vector)) {
                throw new RuntimeException('SequenceTree insert property fixture contains an out-of-bounds index.');
            }

            $index = $i % 3;
            $tree = $port->sequenceTreeInsert($tree, $index, $i);
            array_splice($vector, $index, 0, [$i]);

            same(true, $port->sequenceTreeEqualsList($tree, array_values($vector)), 'SequenceTree proptest insert should match vector after step ' . $step);
        }
    },
    'rust/automerge/src/sequence_tree.rs'
);

$rustMapped(
    'rust sequence tree proptest remove workload matches vector behavior',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sequence-tree-tests-proptest-remove',
    'sequence_tree::tests::proptest_remove',
    function () use ($port): void {
        $tree = $port->sequenceTreeNew();
        $vector = [];
        $inserts = [
            0, 1, 1, 2, 0, 3, 2, 4, 1, 5,
            3, 6, 0, 7, 5, 8, 2, 9, 4, 10,
        ];
        $removes = [3, 0, 5, 1, 4, 2, 0, 6, 1, 0];

        foreach ($inserts as $step => $i) {
            if ($i > count($vector)) {
                throw new RuntimeException('SequenceTree remove property insert fixture contains an out-of-bounds index.');
            }

            $tree = $port->sequenceTreeInsert($tree, $i, $i);
            array_splice($vector, $i, 0, [$i]);

            same(true, $port->sequenceTreeEqualsList($tree, array_values($vector)), 'SequenceTree proptest remove insert phase should match vector after step ' . $step);
        }

        foreach ($removes as $step => $i) {
            if ($i >= count($vector)) {
                throw new RuntimeException('SequenceTree remove property fixture contains an out-of-bounds index.');
            }

            $removed = $port->sequenceTreeRemove($tree, $i);
            $expected = array_splice($vector, $i, 1)[0];
            $tree = $removed['tree'];

            same($removed['value'], $expected, 'SequenceTree proptest remove should return vector value after step ' . $step);
            same(true, $port->sequenceTreeEqualsList($tree, array_values($vector)), 'SequenceTree proptest remove should match vector after step ' . $step);
        }
    },
    'rust/automerge/src/sequence_tree.rs'
);

$rustMapped(
    'rust columnar boolean encoder round-trips boolean runs',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-encoding-boolean-tests-encode-decode-bools',
    'columnar::encoding::boolean::tests::encode_decode_bools',
    function () use ($port): void {
        $vectors = [
            [],
            [false],
            [true],
            [false, false, true, true, true, false],
            [true, false, true, false, true],
            array_fill(0, 99, true),
            array_merge(array_fill(0, 64, false), array_fill(0, 35, true)),
        ];

        foreach ($vectors as $values) {
            sameArray(
                $port->columnarDecodeBooleans($port->columnarEncodeBooleans($values)),
                $values,
                'boolean column encoding should round-trip ' . json_encode($values)
            );
        }

        same(bin2hex($port->columnarEncodeBooleans([])), '', 'empty boolean column should encode as no bytes');
        same(bin2hex($port->columnarEncodeBooleans([true])), '0001', 'leading true values should encode an initial zero false run');
        same(bin2hex($port->columnarEncodeBooleans([false, false, true, true, true, false])), '020301', 'boolean run counts should be unsigned LEB128 values');
    },
    'rust/automerge/src/columnar/encoding/boolean.rs'
);

$rustMapped(
    'rust columnar RLE integer encoder round-trips runs and literals',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-rle-tests-rle-int-round-trip',
    'columnar::column_range::rle::tests::rle_int_round_trip',
    function () use ($port): void {
        $values = [1, 1, 2, 2, 3, 2, 3, 1, 3];
        $encoded = $port->columnarEncodeRleInts($values);

        same(bin2hex($encoded), '020102027b0302030103', 'RLE integer encoding should match upstream run/literal layout');
        sameArray($port->columnarDecodeRleInts($encoded), $values, 'RLE integer decoder should recover the source values');
    },
    'rust/automerge/src/columnar/column_range/rle.rs'
);

$rustMapped(
    'rust columnar RLE integer encoder preserves inserted value position',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-rle-tests-rle-int-insert',
    'columnar::column_range::rle::tests::rle_int_insert',
    function () use ($port): void {
        $values = [1, 1, 2, 2, 5, 3, 2, 3, 1, 3];
        $encoded = $port->columnarEncodeRleInts($values);

        same(bin2hex($encoded), '020102027a050302030103', 'RLE integer encoding should include the inserted literal at index four');
        sameArray($port->columnarDecodeRleInts($encoded), $values, 'RLE integer decoder should recover the inserted value sequence');
    },
    'rust/automerge/src/columnar/column_range/rle.rs'
);

$rustMapped(
    'rust columnar RLE integer splice replaces optional integer ranges',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-rle-tests-splice-ints',
    'columnar::column_range::rle::tests::splice_ints',
    function () use ($port): void {
        $initial = [1, 1, 2, 2, null, 3, 3, 4];
        $encoded = $port->columnarEncodeRleInts($initial);

        $inserted = $port->columnarSpliceRleInts($encoded, 4, 0, [5]);
        sameArray(
            $port->columnarDecodeRleInts($inserted),
            [1, 1, 2, 2, 5, null, 3, 3, 4],
            'RLE integer splice should insert without deleting'
        );

        $replaced = $port->columnarSpliceRleInts($encoded, 2, 3, [7, null, 8]);
        sameArray(
            $port->columnarDecodeRleInts($replaced),
            [1, 1, 7, null, 8, 3, 3, 4],
            'RLE integer splice should replace a mixed value/null range'
        );

        $deleted = $port->columnarSpliceRleInts($encoded, 0, 2, []);
        sameArray(
            $port->columnarDecodeRleInts($deleted),
            [2, 2, null, 3, 3, 4],
            'RLE integer splice should delete a prefix range'
        );
    },
    'rust/automerge/src/columnar/column_range/rle.rs'
);

$rustMapped(
    'rust columnar RLE string splice replaces optional string ranges',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-rle-tests-splice-strings',
    'columnar::column_range::rle::tests::splice_strings',
    function () use ($port): void {
        $initial = ['alpha', 'alpha', null, "\u{03b2}", '', "\u{03b2}"];
        $encoded = $port->columnarEncodeRleStrings($initial);

        same(bin2hex($encoded), '0205616c70686100017d02ceb20002ceb2', 'RLE string encoding should preserve runs, nulls, empty strings, and UTF-8 byte lengths');
        sameArray($port->columnarDecodeRleStrings($encoded), $initial, 'RLE string decoder should recover the source values');

        $replaced = $port->columnarSpliceRleStrings($encoded, 2, 2, ['omega', null]);
        sameArray(
            $port->columnarDecodeRleStrings($replaced),
            ['alpha', 'alpha', 'omega', null, '', "\u{03b2}"],
            'RLE string splice should replace a mixed null/string range'
        );

        $deleted = $port->columnarSpliceRleStrings($encoded, 0, 2, []);
        sameArray(
            $port->columnarDecodeRleStrings($deleted),
            [null, "\u{03b2}", '', "\u{03b2}"],
            'RLE string splice should delete a prefix range'
        );

        same(bin2hex($port->columnarEncodeRleStrings([null, null])), '', 'all-null RLE string columns should encode as no bytes');
    },
    'rust/automerge/src/columnar/column_range/rle.rs'
);

$rustMapped(
    'rust columnar delta encoder round-trips the upstream regression vector',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-delta-tests-bugbug',
    'columnar::column_range::delta::tests::bugbug',
    function () use ($port): void {
        $values = [6, 5, 8, 9, 10, 11, 12, 13];
        $encoded = $port->columnarEncodeDeltaInts($values);

        same(bin2hex($encoded), '7d067f030501', 'delta encoding should match the upstream regression byte layout');
        sameArray($port->columnarDecodeDeltaInts($encoded), $values, 'delta decoder should recover the regression vector');
    },
    'rust/automerge/src/columnar/column_range/delta.rs'
);

$rustMapped(
    'rust columnar delta encoder round-trips optional absolute integers',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-delta-tests-encode-decode-delta',
    'columnar::column_range::delta::tests::encode_decode_delta',
    function () use ($port): void {
        $vectors = [
            [],
            [null, null, null],
            [0, 1, 3, null, 10, 10, 11],
            [6, 5, 8, 9, 10, 11, 12, 13],
        ];

        foreach ($vectors as $values) {
            $encoded = $port->columnarEncodeDeltaInts($values);
            $allNull = true;
            foreach ($values as $value) {
                if ($value !== null) {
                    $allNull = false;
                    break;
                }
            }
            $expected = $allNull ? [] : $values;
            sameArray(
                $port->columnarDecodeDeltaInts($encoded),
                $expected,
                'delta column encoding should round-trip ' . json_encode($values)
            );
        }

        same(bin2hex($port->columnarEncodeDeltaInts([null, null])), '', 'all-null delta columns should encode as no bytes');
    },
    'rust/automerge/src/columnar/column_range/delta.rs'
);

$rustMapped(
    'rust columnar delta splice preserves optional absolute integer order',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-delta-tests-splice-delta',
    'columnar::column_range::delta::tests::splice_delta',
    function () use ($port): void {
        $initial = [1, 3, 6, null, 8, 8, 10];
        $encoded = $port->columnarEncodeDeltaInts($initial);

        $inserted = $port->columnarSpliceDeltaInts($encoded, 2, 0, [4, 5]);
        sameArray(
            $port->columnarDecodeDeltaInts($inserted),
            [1, 3, 4, 5, 6, null, 8, 8, 10],
            'delta splice should insert absolute values without deleting existing values'
        );

        $replaced = $port->columnarSpliceDeltaInts($encoded, 3, 2, [null, 20]);
        sameArray(
            $port->columnarDecodeDeltaInts($replaced),
            [1, 3, 6, null, 20, 8, 10],
            'delta splice should replace a mixed null/value range'
        );

        $deleted = $port->columnarSpliceDeltaInts($encoded, 0, 2, []);
        sameArray(
            $port->columnarDecodeDeltaInts($deleted),
            [6, null, 8, 8, 10],
            'delta splice should delete a prefix range'
        );
    },
    'rust/automerge/src/columnar/column_range/delta.rs'
);

$rustMapped(
    'rust columnar OpId-list encoder round-trips grouped operation ids',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-opid-list-tests-encode-decode-opid-list',
    'columnar::column_range::opid_list::tests::encode_decode_opid_list',
    function () use ($port): void {
        $opid = static fn (int $counter, int $actor): array => ['actor' => $actor, 'counter' => $counter];
        $groups = [
            [$opid(1, 2), $opid(2, 2)],
            [],
            [$opid(10, 3), $opid(13, 3), $opid(14, 4)],
        ];
        $encoded = $port->columnarEncodeOpIdLists($groups);

        same(bin2hex($encoded['bytes']), '7d020003020202037f0402017d080301', 'OpId-list encoding should concatenate num, actor, and counter ranges');
        sameArray($encoded['ranges'], ['num' => [0, 4], 'actor' => [4, 10], 'counter' => [10, 16]], 'OpId-list ranges should split the encoded columns');
        sameArray($port->columnarDecodeOpIdLists($encoded), $groups, 'OpId-list decoder should recover grouped operation ids');
    },
    'rust/automerge/src/columnar/column_range/opid_list.rs'
);

$rustMapped(
    'rust columnar OpId-list splice replaces grouped operation ids',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-opid-list-tests-splice-opid-list',
    'columnar::column_range::opid_list::tests::splice_opid_list',
    function () use ($port): void {
        $opid = static fn (int $counter, int $actor): array => ['actor' => $actor, 'counter' => $counter];
        $initial = [
            [$opid(1, 2), $opid(2, 2)],
            [],
            [$opid(10, 3)],
        ];
        $replacement = [
            [$opid(3, 2)],
            [$opid(20, 5), $opid(21, 5)],
        ];

        $spliced = $port->columnarSpliceOpIdLists(
            $port->columnarEncodeOpIdLists($initial),
            1,
            1,
            $replacement
        );

        sameArray(
            $port->columnarDecodeOpIdLists($spliced),
            [
                [$opid(1, 2), $opid(2, 2)],
                [$opid(3, 2)],
                [$opid(20, 5), $opid(21, 5)],
                [$opid(10, 3)],
            ],
            'OpId-list splice should apply Vec::splice-style group replacement'
        );
    },
    'rust/automerge/src/columnar/column_range/opid_list.rs'
);

$rustMapped(
    'rust storage change op columns round-trip mixed operations',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:storage-change-change-op-columns-tests-test-encode-decode-change-ops',
    'storage::change::change_op_columns::tests::test_encode_decode_change_ops',
    function () use ($port): void {
        $op = static fn (int $counter, int $actor): array => ['counter' => $counter, 'actor' => $actor];
        $ops = [
            [
                'obj' => ['type' => 'root'],
                'key' => ['type' => 'map', 'value' => 'title'],
                'val' => ['type' => 'scalar', 'datatype' => 'str', 'value' => 'Hello'],
                'pred' => [],
                'action' => 'put',
                'insert' => false,
                'expand' => false,
                'markName' => null,
            ],
            [
                'obj' => $op(1, 0),
                'key' => ['type' => 'seq', 'elem' => $op(2, 0)],
                'val' => ['type' => 'object', 'objectType' => 'map'],
                'pred' => [],
                'action' => 'make',
                'insert' => true,
                'expand' => false,
                'markName' => null,
            ],
            [
                'obj' => $op(1, 0),
                'key' => ['type' => 'map', 'value' => 'title'],
                'val' => null,
                'pred' => [$op(1, 0), $op(2, 0)],
                'action' => 'delete',
                'insert' => false,
                'expand' => false,
                'markName' => null,
            ],
            [
                'obj' => $op(3, 0),
                'key' => ['type' => 'seq', 'elem' => $op(4, 0)],
                'val' => ['type' => 'scalar', 'datatype' => 'bool', 'value' => true],
                'pred' => [],
                'action' => 'markBegin',
                'insert' => false,
                'expand' => true,
                'markName' => 'strong',
            ],
        ];

        $encoded = $port->storageChangeEncodeChangeOps($ops);
        same($encoded['rowCount'], count($ops), 'change op column encoder should record the row count');
        sameArray($port->storageChangeDecodeChangeOps($encoded), $ops, 'change op column decoder should reconstruct mixed operation rows');
    },
    'rust/automerge/src/storage/change/change_op_columns.rs'
);

$rustMapped(
    'rust op_set2 ValueMeta accumulator tracks raw value offsets',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-meta-tests-column-data-meta-group',
    'op_set2::meta::tests::column_data_meta_group',
    function () use ($port): void {
        $metas = [
            1,
            6 + (30 << 4),
            6 + (10 << 4),
            3,
            4,
        ];

        $rows = $port->opSet2ValueMetaWithAccumulator($metas);
        sameArray(
            $rows[0],
            ['value' => 1, 'typeCode' => 1, 'length' => 0, 'acc' => 0],
            'first ValueMeta row should start at raw offset zero'
        );
        sameArray(
            $rows[1],
            ['value' => 486, 'typeCode' => 6, 'length' => 30, 'acc' => 0],
            'second ValueMeta string row should still start at raw offset zero'
        );
        sameArray(
            $rows[2],
            ['value' => 166, 'typeCode' => 6, 'length' => 10, 'acc' => 30],
            'third ValueMeta string row should see the prior string length'
        );
        sameArray(
            $rows[3],
            ['value' => 3, 'typeCode' => 3, 'length' => 0, 'acc' => 40],
            'fourth ValueMeta row should accumulate both previous string byte lengths'
        );

        $advanced = $port->opSet2ValueMetaWithAccumulator($metas, 3);
        sameArray(
            $advanced[0],
            ['value' => 3, 'typeCode' => 3, 'length' => 0, 'acc' => 40],
            'advance_by(3) should resume with the same accumulated raw offset'
        );

        $range = $port->opSet2ValueMetaWithAccumulator($metas, 3, 2);
        sameArray(
            $range[0],
            ['value' => 3, 'typeCode' => 3, 'length' => 0, 'acc' => 40],
            'iter_range(3..5) should preserve the global raw offset accumulator'
        );
    },
    'rust/automerge/src/op_set2/meta.rs'
);

$rustMapped(
    'rust op_set2 object id iterator seeks exact and missing ranges',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-op-set-op-iter-tests-obj-id-iter-seek',
    'op_set2::op_set::op_iter::tests::obj_id_iter_seek',
    function () use ($port): void {
        $root = ['type' => 'root'];
        $op = static fn (int $counter, int $actor): array => ['type' => 'op', 'counter' => $counter, 'actor' => $actor];
        $o11 = $op(1, 1);
        $o12 = $op(1, 2);
        $o21 = $op(2, 1);
        $o22 = $op(2, 2);
        $o31 = $op(3, 1);
        $o32 = $op(3, 2);
        $objects = [
            $root,
            $root,
            $root,
            $root,
            $o11,
            $o11,
            $o12,
            $o21,
            $o21,
            $o21,
            $o22,
            $o22,
            $o32,
            $o32,
        ];

        $assertSeek = static function (array $target, array $range, array $expectedValues, string $label) use ($port, $objects): void {
            $seek = $port->opSet2ObjectIdSeek($objects, $target);
            sameArray($seek['range'], $range, $label . ' should return the expected range');
            same($seek['pos'], $range[0], $label . ' should position the iterator at the range start');
            sameArray($seek['values'], $expectedValues, $label . ' should read only the matching object ids');
        };

        $normalizedRoot = ['type' => 'root', 'counter' => 0, 'actor' => 0];
        $assertSeek($root, [0, 4], [$normalizedRoot, $normalizedRoot, $normalizedRoot, $normalizedRoot], 'root seek');
        $assertSeek($o11, [4, 6], [$o11, $o11], '1@1 seek');
        $assertSeek($o12, [6, 7], [$o12], '1@2 seek');
        $assertSeek($o21, [7, 10], [$o21, $o21, $o21], '2@1 seek');
        $assertSeek($o22, [10, 12], [$o22, $o22], '2@2 seek');
        $assertSeek($o31, [12, 12], [], 'missing 3@1 seek');
        $assertSeek($o32, [12, 14], [$o32, $o32], '3@2 seek');

        sameArray($port->opSet2ObjectIdSeek($objects, $o11)['range'], [4, 6], 'odd seek should find 1@1 without reading prior values');
        sameArray($port->opSet2ObjectIdSeek($objects, $o21)['range'], [7, 10], 'odd seek should find 2@1 without reading prior values');
        sameArray($port->opSet2ObjectIdSeek($objects, $o31)['range'], [12, 12], 'odd seek should lower-bound missing 3@1');
        sameArray($port->opSet2ObjectIdSeek($objects, $o12)['range'], [6, 7], 'even seek should find 1@2');
        sameArray($port->opSet2ObjectIdSeek($objects, $o22)['range'], [10, 12], 'even seek should find 2@2');
        sameArray($port->opSet2ObjectIdSeek($objects, $o32)['range'], [12, 14], 'even seek should find 3@2');
    },
    'rust/automerge/src/op_set2/op_set/op_iter.rs'
);

$rustMapped(
    'rust op_set2 skip iterator selects op ids by counter and successor ranges',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-op-set-op-iter-tests-skip-op-ids',
    'op_set2::op_set::op_iter::tests::skip_op_ids',
    function () use ($port): void {
        $op = static fn (int $counter, int $actor): array => ['counter' => $counter, 'actor' => $actor];

        $ids = [
            $op(1, 0),
            $op(2, 0),
            $op(3, 0),
            $op(12, 0),
            $op(13, 0),
            $op(4, 0),
            $op(5, 0),
            $op(10, 1),
            $op(10, 3),
            $op(6, 0),
            $op(7, 0),
            $op(8, 0),
            $op(9, 0),
        ];

        $successors = [
            [],
            [],
            [$op(12, 0)],
            [$op(13, 0)],
            [],
            [],
            [$op(10, 0), $op(10, 1), $op(10, 2), $op(10, 3)],
            [],
            [],
            [],
            [$op(11, 0)],
            [],
            [],
        ];

        sameArray(
            $port->opSet2OperationIdsInCounterRange($ids, 3, 5),
            [$op(3, 0), $op(4, 0)],
            'CtrWalker 3..5 should select op ids 3@0 and 4@0 in op-set order'
        );
        sameArray(
            $port->opSet2OperationIdsInCounterRange($ids, 0, 4),
            [$op(1, 0), $op(2, 0), $op(3, 0)],
            'CtrWalker 0..4 should select existing ids below counter 4'
        );
        sameArray(
            $port->opSet2OperationIdsInCounterRange($ids, 9, 20),
            [$op(12, 0), $op(13, 0), $op(10, 1), $op(10, 3), $op(9, 0)],
            'CtrWalker 9..20 should select high-counter operation ids in storage order'
        );

        sameArray(
            $port->opSet2OperationIdsWithSuccessorsInCounterRange($ids, $successors, 10, 12),
            [$op(5, 0), $op(7, 0)],
            'SuccWalker 10..12 should select operations with delete/overwrite successors in that counter range'
        );
        sameArray(
            $port->opSet2OperationIdsWithSuccessorsInCounterRange($ids, $successors, 10, 99),
            [$op(3, 0), $op(12, 0), $op(5, 0), $op(7, 0)],
            'SuccWalker 10..99 should include operations superseded by later counters'
        );
        sameArray(
            $port->opSet2OperationIdsWithSuccessorsInCounterRange($ids, $successors, 10, 13),
            [$op(3, 0), $op(5, 0), $op(7, 0)],
            'SuccWalker 10..13 should exclude op 12@0 because its successor counter is 13'
        );
        sameArray(
            $port->opSet2OperationIdsWithSuccessorsInCounterRange($ids, $successors, 0, 99),
            [$op(3, 0), $op(12, 0), $op(5, 0), $op(7, 0)],
            'SuccWalker 0..99 should preserve op-set order while scanning all successor counters'
        );
        sameArray(
            $port->opSet2IterCounterRange($ids, $successors, 9, 99),
            [$op(3, 0), $op(12, 0), $op(13, 0), $op(5, 0), $op(10, 1), $op(10, 3), $op(7, 0), $op(9, 0)],
            'iter_ctr_range 9..99 should combine own-counter and successor-counter hits without duplication'
        );
    },
    'rust/automerge/src/op_set2/op_set/op_iter.rs'
);

$rustMapped(
    'rust op_set2 mixed workload survives save load and change replay',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-op-set-tests-suspend-resume-op-set-iter',
    'op_set2::op_set::tests::suspend_resume_op_set_iter',
    function () use ($port): void {
        $largeText = str_repeat('Ab9', 333) . 'Z';
        $doc = $port->init('aaaaaa');

        foreach (['aaa_int', 'mid_int', 'zzz_int'] as $key) {
            $doc = $port->set($doc, $key, 123);
        }
        foreach (['aaa_str', 'mid_str', 'zzz_str'] as $key) {
            $doc = $port->set($doc, $key, $port->immutableString('abc'));
        }

        $doc = $port->set($doc, 'text', new TextValue());
        $doc = $port->splice($doc, 'text', 0, 0, $largeText);
        $doc = $port->splice($doc, 'text', 100, 100, '');
        $expectedText = substr($largeText, 0, 100) . substr($largeText, 200);

        $doc = $port->set($doc, 'a_large', $port->immutableString($largeText));
        $doc = $port->set($doc, 'z_large', $port->immutableString($largeText));
        $doc = $port->set($doc, 'a_large', new Counter(100));
        $doc = $port->set($doc, 'z_large', new Counter(200));
        for ($i = 0; $i < 100; ++$i) {
            $doc = $port->incrementCounter($doc, ['a_large']);
            $doc = $port->incrementCounter($doc, ['z_large']);
        }

        $materialized = $doc->toArray();
        same($materialized['text'] ?? null, $expectedText, 'mixed op-set workload should preserve text after middle deletion');
        truthy(($materialized['a_large'] ?? null) instanceof Counter, 'a_large should materialize as a counter');
        truthy(($materialized['z_large'] ?? null) instanceof Counter, 'z_large should materialize as a counter');
        same($materialized['a_large']->value(), 200, 'a_large counter should include all increments');
        same($materialized['z_large']->value(), 300, 'z_large counter should include all increments');

        $loaded = $port->load($port->save($doc), 'bbbbbb');
        same(json_encode($loaded->toArray()), json_encode($doc->toArray()), 'mixed op-set workload should survive save/load materialization');
        sameArray($port->getHeads($loaded), $port->getHeads($doc), 'mixed op-set workload should preserve heads after save/load');

        $replayed = $port->applyChanges($port->init('cccccc'), $port->getAllChanges($doc));
        same(json_encode($replayed->toArray()), json_encode($doc->toArray()), 'mixed op-set workload should replay from recorded changes');
        sameArray($port->getHeads($replayed), $port->getHeads($doc), 'mixed op-set workload replay should preserve heads');
        truthy(count($port->getChangesMetaSince($doc, [])) >= 212, 'mixed op-set workload should record the large operation stream');
    },
    'rust/automerge/src/op_set2/op_set.rs'
);

$rustMapped(
    'rust op_set2 mark index encodes start and end operation ids',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-op-set-mark-index-tests-column-data-delta-simple',
    'op_set2::op_set::mark_index::tests::column_data_delta_simple',
    function () use ($port): void {
        $start = ['type' => 'start', 'counter' => 7, 'actor' => 3];
        $end = ['type' => 'end', 'counter' => 9, 'actor' => 3];

        same(
            $port->opSet2EncodeMarkIndexValue($start),
            12884901895,
            'MarkIndexValue::Start should pack actor in the high 32 bits and counter in the low 32 bits'
        );
        same(
            $port->opSet2EncodeMarkIndexValue($end),
            -12884901897,
            'MarkIndexValue::End should use the negative packed operation id'
        );
        sameArray(
            $port->opSet2DecodeMarkIndexValue(12884901895),
            $start,
            'positive mark-index values should decode as Start op ids'
        );
        sameArray(
            $port->opSet2DecodeMarkIndexValue(-12884901897),
            $end,
            'negative mark-index values should decode as End op ids'
        );

        $values = [
            $start,
            $start,
            null,
            $end,
            ['type' => 'start', 'counter' => 12, 'actor' => 1],
        ];
        $encoded = $port->opSet2EncodeMarkIndexColumn($values);
        sameArray(
            $port->opSet2DecodeMarkIndexColumn($encoded),
            $values,
            'MarkIndex column should round-trip start/end values and null spans through signed RLE integers'
        );
    },
    'rust/automerge/src/op_set2/op_set/mark_index.rs'
);

$rustMapped(
    'rust op_set2 column data iterates saved operation rows',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-op-set-tests-column-data-basic-iteration',
    'op_set2::op_set::tests::column_data_basic_iteration',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', new TextValue());
        $doc = $port->splice($doc, 'text', 0, 0, 'hello');
        $doc = $port->set($doc, 'key', 'value');
        $doc = $port->set($doc, 'key2', 'value2');
        $doc = $port->delete($doc, 'key2');

        $liveRows = $port->opSet2DecodeOperationColumns($port->opSet2EncodeOperationColumns($doc));
        $loaded = $port->load($port->save($doc), 'aaaaaa');
        $loadedRows = $port->opSet2DecodeOperationColumns($port->opSet2EncodeOperationColumns($loaded));

        sameArray($loadedRows, $liveRows, 'loaded document operation columns should iterate the same rows as the live op set');
        sameArray(
            array_column($liveRows, 'action'),
            ['set', 'splice', 'set', 'set', 'delete'],
            'basic op set iteration should preserve text creation splice put put delete order'
        );
        sameArray(
            array_column($liveRows, 'pos'),
            [0, 1, 2, 3, 4],
            'operation column positions should be dense and stable'
        );
        sameArray($loaded->toArray(), ['text' => 'hello', 'key' => 'value'], 'loaded document should materialize the same visible map');
    },
    'rust/automerge/src/op_set2/op_set.rs'
);

$rustMapped(
    'rust op_set2 column data iter_range scopes rows to one object',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-op-set-tests-column-data-iter-range',
    'op_set2::op_set::tests::column_data_iter_range',
    function () use ($port): void {
        $op = static fn (int $counter, int $actor): array => ['counter' => $counter, 'actor' => $actor];
        $rows = [
            [
                'id' => $op(1, 1),
                'obj' => 'root',
                'action' => 'makeMap',
                'value' => null,
                'key' => 'key',
                'insert' => false,
                'succs' => [$op(5, 1), $op(6, 1), $op(10, 1)],
            ],
            [
                'id' => $op(2, 1),
                'obj' => 'root',
                'action' => 'set',
                'value' => 'value1',
                'key' => 'key1',
                'insert' => false,
                'succs' => [],
            ],
            [
                'id' => $op(3, 1),
                'obj' => 'root',
                'action' => 'set',
                'value' => 'value2',
                'key' => 'key2',
                'insert' => false,
                'succs' => [$op(6, 1)],
            ],
            [
                'id' => $op(4, 1),
                'obj' => '1@1',
                'action' => 'set',
                'value' => 'inner_value1',
                'key' => 'inner_key1',
                'insert' => false,
                'succs' => [$op(7, 1), $op(8, 2), $op(9, 1)],
            ],
            [
                'id' => $op(5, 1),
                'obj' => '1@1',
                'action' => 'set',
                'value' => 'inner_value2',
                'key' => 'inner_key2',
                'insert' => false,
                'succs' => [],
            ],
        ];

        $range = $port->opSet2OperationRowsForObject($rows, '1@1');
        sameArray($range['range'], [3, 5], 'scope_to_obj should locate the contiguous range for object 1@1');
        same($range['pos'], 3, 'iter_range should start at the object range position');
        sameArray($range['rows'], [$rows[3], $rows[4]], 'iter_range should yield only operations owned by object 1@1');
    },
    'rust/automerge/src/op_set2/op_set.rs'
);

$rustMapped(
    'rust op_set2 column data op iterators group visible and top rows',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-op-set-tests-column-data-op-iterators',
    'op_set2::op_set::tests::column_data_op_iterators',
    function () use ($port): void {
        $op = static fn (int $counter, int $actor): array => ['counter' => $counter, 'actor' => $actor];
        $rows = [
            ['id' => $op(1, 1), 'obj' => 'root', 'action' => 'makeMap', 'value' => null, 'key' => 'map', 'insert' => false, 'succs' => []],
            ['id' => $op(2, 1), 'obj' => 'root', 'action' => 'makeMap', 'value' => null, 'key' => 'list', 'insert' => false, 'succs' => []],
            ['id' => $op(3, 1), 'obj' => '1@1', 'action' => 'set', 'value' => 'value1', 'key' => 'key1', 'insert' => false, 'succs' => []],
            ['id' => $op(4, 1), 'obj' => '1@1', 'action' => 'set', 'value' => 'value2a', 'key' => 'key2', 'insert' => false, 'succs' => []],
            ['id' => $op(4, 2), 'obj' => '1@1', 'action' => 'set', 'value' => 'value2b', 'key' => 'key2', 'insert' => false, 'succs' => [$op(5, 2)]],
            ['id' => $op(5, 2), 'obj' => '1@1', 'action' => 'set', 'value' => 'value2c', 'key' => 'key2', 'insert' => false, 'succs' => []],
            ['id' => $op(6, 1), 'obj' => '1@1', 'action' => 'set', 'value' => 'value3a', 'key' => 'key3', 'insert' => false, 'succs' => [$op(7, 2)]],
            ['id' => $op(7, 2), 'obj' => '1@1', 'action' => 'set', 'value' => 'value3b', 'key' => 'key3', 'insert' => false, 'succs' => []],
            ['id' => $op(8, 1), 'obj' => '2@1', 'action' => 'set', 'value' => 'a', 'key' => '_head', 'insert' => true, 'succs' => []],
            ['id' => $op(9, 1), 'obj' => '2@1', 'action' => 'set', 'value' => 'b', 'key' => '8@1', 'insert' => true, 'succs' => []],
        ];

        sameArray(
            $port->opSet2OperationRowsForObject($rows, '1@1')['rows'],
            array_slice($rows, 2, 6),
            'iter_obj should yield every row belonging to object 1@1'
        );
        sameArray(
            $port->opSet2OperationRowsForProperty($rows, '1@1', 'key2')['rows'],
            array_slice($rows, 3, 3),
            'prop_range should yield all rows for key2 on object 1@1'
        );
        sameArray(
            $port->opSet2TopOperationRows($rows, '1@1'),
            [$rows[2], $rows[5], $rows[7]],
            'top_ops should keep the last row in each object-key group'
        );
        sameArray(
            $port->opSet2OperationRowsGroupedByKey($rows, '1@1'),
            [[$rows[2]], array_slice($rows, 3, 3), array_slice($rows, 6, 2)],
            'key_ops should group contiguous object rows by key'
        );
        sameArray(
            $port->opSet2OperationRowsGroupedByKey($rows, '1@1', true),
            [[$rows[2]], [$rows[3], $rows[5]], [$rows[7]]],
            'visible_slow key_ops should remove rows that have successors while retaining same-key conflicts'
        );
        sameArray(
            $port->opSet2TopOperationRows($rows, '1@1', true),
            [$rows[2], $rows[5], $rows[7]],
            'visible_slow top_ops should keep the last visible row in each object-key group'
        );
    },
    'rust/automerge/src/op_set2/op_set.rs'
);

$rustMapped(
    'rust op_set2 parents reports invisible deleted list object parent',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-parents-tests-test-invisible-parents',
    'op_set2::parents::tests::test_invisible_parents',
    function () use ($port): void {
        $rows = [
            [
                'id' => '1@1',
                'obj' => 'root',
                'action' => 'makeList',
                'key' => 'list',
                'objectType' => 'list',
                'insert' => false,
                'succs' => [],
            ],
            [
                'id' => '2@1',
                'obj' => '1@1',
                'action' => 'makeMap',
                'key' => 0,
                'objectType' => 'map',
                'insert' => true,
                'succs' => ['5@1'],
            ],
            [
                'id' => '3@1',
                'obj' => '1@1',
                'action' => 'makeMap',
                'key' => 1,
                'objectType' => 'map',
                'insert' => true,
                'succs' => [],
            ],
            [
                'id' => '4@1',
                'obj' => '2@1',
                'action' => 'set',
                'key' => 'key',
                'value' => 'value',
                'insert' => false,
                'succs' => [],
            ],
            [
                'id' => '5@1',
                'obj' => '1@1',
                'action' => 'delete',
                'key' => 0,
                'pred' => ['2@1'],
                'insert' => false,
                'succs' => [],
            ],
        ];

        sameArray(
            $port->opSet2ParentPath($rows, '2@1'),
            [
                [
                    'obj' => '_root',
                    'prop' => ['type' => 'map', 'value' => 'list'],
                    'typ' => 'map',
                    'visible' => true,
                ],
                [
                    'obj' => '1@1',
                    'prop' => ['type' => 'seq', 'value' => 0],
                    'typ' => 'list',
                    'visible' => false,
                ],
            ],
            'parents for a deleted list object should retain the root list path and mark the deleted sequence parent invisible'
        );
    },
    'rust/automerge/src/op_set2/parents.rs'
);

$rustMapped(
    'rust op_set2 batch apply matches iterative map changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-map-batch-apply',
    'op_set2::change::batch::tests::map_batch_apply',
    function () use ($port): void {
        $base = $port->from(['map' => ['key1' => 'val1', 'key2' => 'val2']], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);

        $doc2 = $port->clone($base, 'bbbbbb');
        foreach (['val3a', 'val3a.1', 'val3a.2'] as $value) {
            $doc2 = $port->setNested($doc2, ['map', 'key1'], $value);
        }
        $doc2 = $port->deleteNested($doc2, ['map', 'key2']);
        $doc2 = $port->setNested($doc2, ['map', 'key3'], 'val4a');
        $doc2 = $port->setNested($doc2, ['map', 'map2'], []);
        $doc2 = $port->setNested($doc2, ['map', 'map2', 'key1'], 'val5a');

        $doc1 = $port->setNested($base, ['map', 'map3'], []);
        $doc1 = $port->setNested($doc1, ['map', 'key1'], 'val6a');
        $doc1 = $port->setNested($doc1, ['map', 'map3', 'key1'], 'val7a');

        $doc3 = $port->clone($doc1, 'aaaaaa');
        $doc3 = $port->setNested($doc3, ['map', 'key1'], 'val3b');
        $doc3 = $port->setNested($doc3, ['map', 'key3'], 'val4b');

        $changes = array_merge($port->getChanges($baseView, $doc2), $port->getChanges($baseView, $doc3));
        $iterative = $doc1;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($doc1, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched map apply should materialize the same state as iterative apply');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched map apply should retain the same heads as iterative apply');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch apply matches iterative list changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-list-batch-apply',
    'op_set2::change::batch::tests::list_batch_apply',
    function () use ($port): void {
        $base = $port->from(['list' => ['val1', 'val2', 'val3']], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);

        $doc2 = $port->clone($base, 'bbbbbb');
        foreach ([[1, 'val4a'], [1, 'val4b'], [2, 'val4c'], [0, 'val4d'], [0, 'val4e'], [0, 'val4f']] as [$index, $value]) {
            $doc2 = $port->insertListElements($doc2, 'list', $index, [$value]);
        }

        $doc3 = $port->clone($base, 'aaaaaa');
        foreach ([[1, 'val5a'], [1, 'val5b'], [2, 'val5c'], [3, 'val5d'], [1, 'val5e'], [1, 'val5f'], [0, 'val5g'], [0, 'val5h']] as [$index, $value]) {
            $doc3 = $port->insertListElements($doc3, 'list', $index, [$value]);
        }

        $changes = array_merge($port->getChanges($baseView, $doc2), $port->getChanges($baseView, $doc3));
        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched list apply should materialize the same state as iterative apply');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched list apply should retain the same heads as iterative apply');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch apply matches iterative text changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-text-batch-apply',
    'op_set2::change::batch::tests::text_batch_apply',
    function () use ($port): void {
        $base = $port->set($port->init('cccccc'), 'text', new TextValue());
        $base = $port->splice($base, 'text', 0, 0, 'the quick fox jumped over the lazy dog');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);

        $doc2 = $port->clone($base, 'bbbbbb');
        $doc2 = $port->splice($doc2, 'text', 0, 0, 'abc');

        $doc3 = $port->clone($base, 'aaaaaa');
        $doc3 = $port->splice($doc3, 'text', 3, 1, 'aalks');

        $changes = array_merge($port->getChanges($baseView, $doc2), $port->getChanges($baseView, $doc3));
        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched text apply should materialize the same state as iterative apply');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched text apply should retain the same heads as iterative apply');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch apply handles many concurrent list puts',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-multi-put-batch-apply',
    'op_set2::change::batch::tests::multi_put_batch_apply',
    function () use ($port): void {
        $base = $port->from(['list' => ['a', 'b', 'c']], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $merged = $port->clone($base, '999999');

        foreach (['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff'] as $offset => $actor) {
            $tmp = $port->clone($base, $actor);
            $tmp = $port->setListElement($tmp, 'list', 0, $offset);
            $merged = $port->mergeDocuments($merged, $tmp);
        }

        $changes = $port->getChanges($baseView, $merged);
        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched concurrent list puts should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched concurrent list puts should retain iterative heads');
        sameArray($batched->toArray(), $merged->toArray(), 'batched concurrent list puts should match the merged source document');
        same(count($port->getListElementConflicts($batched, 'list', 0) ?? []), count($port->getListElementConflicts($iterative, 'list', 0) ?? []), 'batched list puts should retain the iterative conflict count');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch apply handles many concurrent list inserts',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-multi-insert-batch-apply',
    'op_set2::change::batch::tests::multi_insert_batch_apply',
    function () use ($port): void {
        $base = $port->from(['list' => ['a', 'b', 'c']], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $merged = $port->clone($base, '999999');

        foreach (['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff'] as $offset => $actor) {
            $tmp = $port->clone($base, $actor);
            $tmp = $port->insertListElements($tmp, 'list', 1, [$offset]);
            $merged = $port->mergeDocuments($merged, $tmp);
        }

        $changes = $port->getChanges($baseView, $merged);
        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched concurrent list inserts should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched concurrent list inserts should retain iterative heads');
        same(count($batched->toArray()['list']), 8, 'batched concurrent list inserts should preserve all inserted elements');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch apply handles repeated concurrent list updates',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-multi-update-batch-apply',
    'op_set2::change::batch::tests::multi_update_batch_apply',
    function () use ($port): void {
        $base = $port->from(['list' => ['a', 'b', 'c']], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $merged = $port->clone($base, '999999');

        foreach (['aaaaaa', 'bbbbbb', 'dddddd'] as $offset => $actor) {
            $tmp = $port->clone($base, $actor);
            $tmp = $port->setListElement($tmp, 'list', 2, $offset);
            $merged = $port->mergeDocuments($merged, $tmp);
        }

        $changes = $port->getChanges($baseView, $merged);
        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched repeated list updates should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched repeated list updates should retain iterative heads');
        sameArray($batched->toArray(), $merged->toArray(), 'batched repeated list updates should match the merged source document');
        same(count($port->getListElementConflicts($batched, 'list', 2) ?? []), count($port->getListElementConflicts($iterative, 'list', 2) ?? []), 'batched repeated list updates should retain the iterative conflict count');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch apply preserves map key conflicts',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-map-key-conflict',
    'op_set2::change::batch::tests::map_key_conflict',
    function () use ($port): void {
        $doc = $port->set($port->init('cccccc'), 'key1', 'value1');
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff'];

        for ($cycle = 0; $cycle < 3; ++$cycle) {
            $baseHeads = $port->getHeads($doc);
            $baseView = $port->view($doc, $baseHeads);
            $changes = [];
            foreach ($actors as $offset => $actor) {
                $branch = $port->clone($doc, $actor);
                for ($step = 0; $step < 4; ++$step) {
                    $key = 'key' . (($offset + $step) % 4);
                    $branch = $port->set($branch, $key, 'value' . $cycle . '-' . $offset . '-' . $step);
                }
                $branch = $port->delete($branch, 'key' . (($offset + 1) % 4));
                $changes = array_merge($changes, $port->getChanges($baseView, $branch));
            }

            $iterative = $doc;
            foreach ($changes as $change) {
                $iterative = $port->applyChanges($iterative, [$change]);
            }
            $batched = $port->applyChangesBatch($doc, $changes);

            sameArray($batched->toArray(), $iterative->toArray(), 'batched map key conflict cycle ' . $cycle . ' should match iterative materialization');
            sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched map key conflict cycle ' . $cycle . ' should retain iterative heads');
            $doc = $batched;
        }

        truthy($port->getConflicts($doc, 'key0') !== null || $port->getConflicts($doc, 'key1') !== null, 'map key conflict workload should retain visible conflicts');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch apply preserves list element conflicts',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-list-element-conflict',
    'op_set2::change::batch::tests::list_element_conflict',
    function () use ($port): void {
        $doc = $port->from(['list' => ['_', '_', '_']], 'cccccc');
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff', '111111'];

        for ($cycle = 0; $cycle < 3; ++$cycle) {
            $baseHeads = $port->getHeads($doc);
            $baseView = $port->view($doc, $baseHeads);
            $changes = [];
            foreach ($actors as $offset => $actor) {
                $branch = $port->clone($doc, $actor);
                for ($step = 0; $step < 3; ++$step) {
                    $index = ($offset + $step) % 3;
                    $branch = $port->setListElement($branch, 'list', $index, 'value' . $cycle . '-' . $offset . '-' . $step);
                }
                $changes = array_merge($changes, $port->getChanges($baseView, $branch));
            }

            $iterative = $doc;
            foreach ($changes as $change) {
                $iterative = $port->applyChanges($iterative, [$change]);
            }
            $batched = $port->applyChangesBatch($doc, $changes);

            sameArray($batched->toArray(), $iterative->toArray(), 'batched list element conflict cycle ' . $cycle . ' should match iterative materialization');
            sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched list element conflict cycle ' . $cycle . ' should retain iterative heads');
            $doc = $batched;
        }

        truthy($port->getListElementConflicts($doc, 'list', 0) !== null || $port->getListElementConflicts($doc, 'list', 1) !== null, 'list element conflict workload should retain visible conflicts');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 isolation conflict workload applies without index corruption',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-conflicts-with-isolate',
    'op_set2::change::batch::tests::conflicts_with_isolate',
    function () use ($port): void {
        $doc = $port->from(['list' => ['_'], 'map' => ['key' => '_']], 'cccccc');
        $docs = [
            $port->clone($doc, 'aaaaaa'),
            $port->clone($doc, 'bbbbbb'),
            $port->clone($doc, 'dddddd'),
        ];
        $heads = [$port->getHeads($doc)];
        $operationPlan = [
            [false, true, false],
            [true, false, false],
            [false, false, true],
        ];

        foreach ($operationPlan as $cycle => $deletePlan) {
            $changes = [];
            foreach ($docs as $offset => $branch) {
                $branch = $port->mergeDocuments($branch, $doc);
                $branchStart = $port->view($branch, $port->getHeads($branch));
                $head = $heads[($cycle + $offset) % count($heads)];
                $isolated = $port->isolate($branch, $head);

                for ($step = 0; $step < 3; ++$step) {
                    $visible = $port->isolatedDocument($isolated)->toArray();
                    $listLength = is_array($visible['list'] ?? null) ? count($visible['list']) : 0;
                    if ($deletePlan[$step]) {
                        if ($listLength > 0) {
                            $isolated = $port->deleteListElementsInIsolation($isolated, 'list', 0);
                        }
                        $isolated = $port->deleteNestedInIsolation($isolated, ['map', 'key']);
                        continue;
                    }

                    $value = 'value' . $cycle . '-' . $offset . '-' . $step;
                    if ($listLength > 0) {
                        $isolated = $port->setListElementInIsolation($isolated, 'list', 0, $value);
                    } else {
                        $isolated = $port->insertListElementsInIsolation($isolated, 'list', 0, [$value]);
                    }
                    $isolated = $port->setNestedInIsolation($isolated, ['map', 'key'], $value);
                }

                $branch = $port->integrate($isolated);
                $docs[$offset] = $branch;
                $changes = array_merge($changes, $port->getChanges($branchStart, $branch));

                $materialized = $branch->toArray();
                truthy(is_array($materialized['list'] ?? null), 'isolated conflict branch should retain a list container');
                truthy(is_array($materialized['map'] ?? null), 'isolated conflict branch should retain a map container');
            }

            $iterative = $doc;
            foreach ($changes as $change) {
                $iterative = $port->applyChanges($iterative, [$change]);
            }
            $batched = $port->applyChangesBatch($doc, $changes);

            sameArray($batched->toArray(), $iterative->toArray(), 'isolated conflict cycle ' . $cycle . ' should match iterative materialization');
            sameArray($port->getHeads($batched), $port->getHeads($iterative), 'isolated conflict cycle ' . $cycle . ' should retain iterative heads');
            truthy(is_array($batched->toArray()['list'] ?? null), 'batched isolated conflict cycle should retain the list container');
            truthy(is_array($batched->toArray()['map'] ?? null), 'batched isolated conflict cycle should retain the map container');

            $doc = $batched;
            $heads[] = $port->getHeads($doc);
        }
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 fuzz batch list apply matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-fuzz-batch-list-apply',
    'op_set2::change::batch::tests::fuzz_batch_list_apply',
    function () use ($port): void {
        $base = $port->from(['list' => ['a', 'b', 'c']], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff', '111111'];
        $changes = [];
        $value = 0;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            for ($step = 0; $step < 4; ++$step) {
                $list = $branch->toArray()['list'];
                $position = ($offset + $step) % max(1, count($list));
                ++$value;
                $branch = $port->insertListElements($branch, 'list', $position, [$value]);
            }
            for ($step = 0; $step < 3; ++$step) {
                $list = $branch->toArray()['list'];
                $position = ($offset + ($step * 2)) % count($list);
                ++$value;
                $branch = $port->setListElement($branch, 'list', $position, $value);
            }
            if ($offset % 2 === 0) {
                $list = $branch->toArray()['list'];
                $position = ($offset + 1) % count($list);
                $branch = $port->deleteListElements($branch, 'list', $position);
            }
            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'fuzzed batched list apply should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'fuzzed batched list apply should retain iterative heads');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 fuzz batch nested map apply matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-fuzz-batch-map1-apply',
    'op_set2::change::batch::tests::fuzz_batch_map1_apply',
    function () use ($port): void {
        $base = $port->from(['map1' => ['map2' => ['map3' => []]]], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff', '111111'];
        $mapPaths = [['map1'], ['map1', 'map2'], ['map1', 'map2', 'map3']];
        $changes = [];
        $value = 0;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            $written = [];
            for ($step = 0; $step < 6; ++$step) {
                $path = $mapPaths[($offset + $step) % count($mapPaths)];
                $key = 'key' . (($offset * 7 + $step) % 20);
                ++$value;
                $fullPath = array_merge($path, [$key]);
                $branch = $port->setNested($branch, $fullPath, $value);
                $written[] = $fullPath;
            }
            if ($offset % 2 === 0) {
                $branch = $port->deleteNested($branch, $written[1]);
            }
            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'fuzzed batched nested map apply should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'fuzzed batched nested map apply should retain iterative heads');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 fuzz batch sparse nested map apply matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-fuzz-batch-map2-apply',
    'op_set2::change::batch::tests::fuzz_batch_map2_apply',
    function () use ($port): void {
        $base = $port->from(['map1' => ['map2' => ['map3' => []]]], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff', '111111'];
        $mapPaths = [['map1'], ['map1', 'map2'], ['map1', 'map2', 'map3']];
        $changes = [];
        $value = 0;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            $written = [];
            for ($step = 0; $step < 6; ++$step) {
                $path = $mapPaths[($offset + $step) % count($mapPaths)];
                $key = 'key' . (($offset * 101 + $step * 17) % 1000);
                ++$value;
                $fullPath = array_merge($path, [$key]);
                $branch = $port->setNested($branch, $fullPath, $value);
                $written[] = $fullPath;
            }
            if ($offset % 2 === 1) {
                $branch = $port->deleteNested($branch, $written[2]);
            }
            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'fuzzed batched sparse nested map apply should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'fuzzed batched sparse nested map apply should retain iterative heads');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 fuzz batch nested counter map apply matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-fuzz-batch-map-counter-apply',
    'op_set2::change::batch::tests::fuzz_batch_map_counter_apply',
    function () use ($port): void {
        $base = $port->from([
            'map1' => [
                'key1' => new Counter(35),
                'map2' => [
                    'key1' => new Counter(102),
                    'map3' => [
                        'key1' => new Counter(1030),
                    ],
                ],
            ],
        ], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff', '111111'];
        $counterPaths = [['map1', 'key1'], ['map1', 'map2', 'key1'], ['map1', 'map2', 'map3', 'key1']];
        $mapPaths = [['map1'], ['map1', 'map2'], ['map1', 'map2', 'map3']];
        $changes = [];
        $value = 0;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            foreach ($counterPaths as $step => $path) {
                $branch = $port->incrementCounter($branch, $path, $offset + $step + 1);
            }
            for ($step = 0; $step < 4; ++$step) {
                $path = $mapPaths[($offset + $step) % count($mapPaths)];
                $key = 'key' . (($offset * 11 + $step) % 30);
                ++$value;
                $branch = $port->setNested($branch, array_merge($path, [$key]), new Counter($value));
            }
            if ($offset % 2 === 0) {
                $branch = $port->deleteNested($branch, ['map1', 'map2', 'key1']);
                $branch = $port->setNested($branch, ['map1', 'map2', 'key1'], new Counter(200 + $offset));
                $branch = $port->incrementCounter($branch, ['map1', 'map2', 'key1'], $offset + 1);
            }
            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        same(json_encode($batched->toArray()), json_encode($iterative->toArray()), 'fuzzed batched nested counter map apply should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'fuzzed batched nested counter map apply should retain iterative heads');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch list patch diff matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-batch-list-patch',
    'op_set2::change::batch::tests::batch_list_patch',
    function () use ($port): void {
        $base = $port->from(['list1' => [1, 2, 3]], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee'];
        $changes = [];
        $value = 3;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            for ($step = 0; $step < 4; ++$step) {
                $list = $branch->toArray()['list1'];
                $position = ($offset + $step) % count($list);
                ++$value;
                $branch = $port->setListElement($branch, 'list1', $position, $value);
            }
            for ($step = 0; $step < 3; ++$step) {
                $list = $branch->toArray()['list1'];
                $position = ($offset + $step + 1) % count($list);
                ++$value;
                $branch = $port->insertListElements($branch, 'list1', $position, [$value]);
            }
            if ($offset % 2 === 0) {
                $list = $branch->toArray()['list1'];
                $position = ($offset + 2) % count($list);
                $branch = $port->deleteListElements($branch, 'list1', $position);
            }
            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched list patch workload should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched list patch workload should retain iterative heads');
        sameArray($port->diff($batched, $baseHeads, $port->getHeads($batched)), $port->diff($iterative, $baseHeads, $port->getHeads($iterative)), 'batched list patch diff should match iterative diff');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch text patch diff matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-batch-text-patch',
    'op_set2::change::batch::tests::batch_text_patch',
    function () use ($port): void {
        $base = $port->from(['text1' => '--------'], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee', 'ffffff'];
        $changes = [];
        $value = 0;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            for ($step = 0; $step < 5; ++$step) {
                $text = $branch->toArray()['text1'];
                $length = max(1, mb_strlen($text));
                $position = ($offset + $step * 2) % $length;
                $delete = ($step + $offset) % 2;
                ++$value;
                $branch = $port->splice($branch, 'text1', $position, $delete, '[' . $value . ']');
            }
            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched text patch workload should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched text patch workload should retain iterative heads');
        sameArray($port->diff($batched, $baseHeads, $port->getHeads($batched)), $port->diff($iterative, $baseHeads, $port->getHeads($iterative)), 'batched text patch diff should match iterative diff');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch counter list patch diff matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-batch-counter-list-patch',
    'op_set2::change::batch::tests::batch_counter_list_patch',
    function () use ($port): void {
        $base = $port->from(['list1' => [new Counter(1), new Counter(2), new Counter(3)]], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee'];
        $changes = [];
        $value = 3;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            for ($step = 0; $step < 3; ++$step) {
                $list = $branch->toArray()['list1'];
                $position = ($offset + $step) % count($list);
                $branch = $port->incrementCounter($branch, ['list1', $position], $offset + $step + 1);
            }
            for ($step = 0; $step < 2; ++$step) {
                $list = $branch->toArray()['list1'];
                $position = ($offset + $step + 1) % count($list);
                ++$value;
                $branch = $port->insertListElements($branch, 'list1', $position, [new Counter($value)]);
            }
            if ($offset % 2 === 0) {
                $list = $branch->toArray()['list1'];
                $position = ($offset + 2) % count($list);
                $branch = $port->deleteListElements($branch, 'list1', $position);
            }
            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        same(json_encode($batched->toArray()), json_encode($iterative->toArray()), 'batched counter list patch workload should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched counter list patch workload should retain iterative heads');
        same(json_encode($port->diff($batched, $baseHeads, $port->getHeads($batched))), json_encode($port->diff($iterative, $baseHeads, $port->getHeads($iterative))), 'batched counter list patch diff should match iterative diff');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust op_set2 batch marks patch diff matches iterative application',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:op-set2-change-batch-tests-batch-marks-patch',
    'op_set2::change::batch::tests::batch_marks_patch',
    function () use ($port): void {
        $base = $port->from(['text1' => '---------------------'], 'cccccc');
        $baseHeads = $port->getHeads($base);
        $baseView = $port->view($base, $baseHeads);
        $actors = ['aaaaaa', 'bbbbbb', 'dddddd', 'eeeeee'];
        $changes = [];
        $value = 0;

        foreach ($actors as $offset => $actor) {
            $branch = $port->clone($base, $actor);
            for ($step = 0; $step < 4; ++$step) {
                $text = $branch->toArray()['text1'];
                $length = max(1, mb_strlen($text));
                $position = ($offset + ($step * 3)) % $length;
                $delete = ($offset + $step) % 2;
                ++$value;
                $branch = $port->splice($branch, 'text1', $position, $delete, '[' . $value . ']');
            }

            for ($step = 0; $step < 3; ++$step) {
                $text = $branch->toArray()['text1'];
                $length = max(1, mb_strlen($text));
                $start = ($offset + ($step * 4)) % $length;
                $end = min($length, $start + 3 + $step);
                if ($start === $end) {
                    continue;
                }
                ++$value;
                $branch = $port->mark($branch, ['text1'], $start, $end, 'bold', $value, 'after');
            }

            $changes = array_merge($changes, $port->getChanges($baseView, $branch));
        }

        $iterative = $base;
        foreach ($changes as $change) {
            $iterative = $port->applyChanges($iterative, [$change]);
        }
        $batched = $port->applyChangesBatch($base, $changes);

        sameArray($batched->toArray(), $iterative->toArray(), 'batched marks patch workload should match iterative materialization');
        sameArray($port->getHeads($batched), $port->getHeads($iterative), 'batched marks patch workload should retain iterative heads');
        sameArray($port->diff($batched, $baseHeads, $port->getHeads($batched)), $port->diff($iterative, $baseHeads, $port->getHeads($iterative)), 'batched marks patch diff should match iterative diff');
        sameArray($port->spans($batched, ['text1']), $port->spans($iterative, ['text1']), 'batched marks patch spans should match iterative spans');
    },
    'rust/automerge/src/op_set2/change/batch.rs'
);

$rustMapped(
    'rust columnar value ULEB metadata decodes unsigned scalars',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-value-tests-test-value-uleb',
    'columnar::column_range::value::tests::test_value_uleb',
    function () use ($port): void {
        $values = [
            ['type' => 'uint', 'value' => 127],
            ['type' => 'uint', 'value' => 183],
        ];
        $encoded = $port->columnarEncodeScalarValues($values);

        same(bin2hex($encoded['bytes']), '7e13237fb701', 'value column should encode ULEB metadata and raw unsigned integers');
        sameArray($encoded['ranges'], ['meta' => [0, 3], 'raw' => [3, 6]], 'value column ranges should split metadata from raw values');
        sameArray($port->columnarDecodeScalarValues($encoded), $values, 'value column decoder should recover ULEB unsigned scalars');
    },
    'rust/automerge/src/columnar/column_range/value.rs'
);

$rustMapped(
    'rust columnar value initialization round-trips scalar values',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-value-tests-test-initialize-splice',
    'columnar::column_range::value::tests::test_initialize_splice',
    function () use ($port): void {
        $values = [
            ['type' => 'null', 'value' => null],
            ['type' => 'boolean', 'value' => false],
            ['type' => 'boolean', 'value' => true],
            ['type' => 'uint', 'value' => 183],
            ['type' => 'int', 'value' => -64],
            ['type' => 'float64', 'value' => 5.5],
            ['type' => 'string', 'value' => "\u{03b2}eta"],
            ['type' => 'bytes', 'value' => [0, 255, 17]],
            ['type' => 'counter', 'value' => -3],
            ['type' => 'timestamp', 'value' => 1700000000123],
            ['type' => 'unknown', 'code' => 11, 'value' => [1, 2, 3]],
        ];
        $encoded = $port->columnarEncodeScalarValues($values);

        sameArray($port->columnarDecodeScalarValues($encoded), $values, 'value column should round-trip the scalar value set');
    },
    'rust/automerge/src/columnar/column_range/value.rs'
);

$rustMapped(
    'rust columnar value row-wise and column-wise encoders match',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-value-tests-encode-row-wise-and-columnwise-equal',
    'columnar::column_range::value::tests::encode_row_wise_and_columnwise_equal',
    function () use ($port): void {
        $values = [
            ['type' => 'string', 'value' => 'alpha'],
            ['type' => 'string', 'value' => 'alpha'],
            ['type' => 'int', 'value' => 64],
            ['type' => 'uint', 'value' => 65],
            ['type' => 'bytes', 'value' => [1, 2]],
            ['type' => 'unknown', 'code' => 12, 'value' => []],
        ];

        sameArray(
            $port->columnarEncodeScalarValuesRowWise($values),
            $port->columnarEncodeScalarValues($values),
            'row-wise and column-wise scalar value encoders should produce identical bytes and ranges'
        );
    },
    'rust/automerge/src/columnar/column_range/value.rs'
);

$rustMapped(
    'rust columnar value splice replaces scalar value ranges',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:columnar-column-range-value-tests-test-splice-values',
    'columnar::column_range::value::tests::test_splice_values',
    function () use ($port): void {
        $initial = [
            ['type' => 'uint', 'value' => 1],
            ['type' => 'string', 'value' => 'left'],
            ['type' => 'boolean', 'value' => true],
            ['type' => 'counter', 'value' => 10],
        ];
        $replacement = [
            ['type' => 'null', 'value' => null],
            ['type' => 'timestamp', 'value' => -500],
            ['type' => 'bytes', 'value' => [10, 20, 30]],
        ];
        $spliced = $port->columnarSpliceScalarValues(
            $port->columnarEncodeScalarValues($initial),
            1,
            2,
            $replacement
        );

        sameArray(
            $port->columnarDecodeScalarValues($spliced),
            [
                ['type' => 'uint', 'value' => 1],
                ['type' => 'null', 'value' => null],
                ['type' => 'timestamp', 'value' => -500],
                ['type' => 'bytes', 'value' => [10, 20, 30]],
                ['type' => 'counter', 'value' => 10],
            ],
            'value column splice should apply Vec::splice-style scalar replacement'
        );
    },
    'rust/automerge/src/columnar/column_range/value.rs'
);

$rustMapped(
    'rust Myers text diff finds the middle snake',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:text-diff-myers-test-find-middle-snake',
    'text_diff::myers::test_find_middle_snake',
    function () use ($port): void {
        sameArray(
            $port->textDiffFindMiddleSnake('ABCABBA', 0, 7, 'CBABAC', 0, 6),
            [4, 1],
            'Myers middle snake should match the upstream byte-coordinate example'
        );
    },
    'rust/automerge/src/text_diff/myers.rs'
);

$mapped(
    'concurrent text insertion deterministically preserves both branches',
    'javascript/test/text_test.ts',
    48,
    'should handle concurrent insertion',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'text', '');
        $s1 = $port->splice($base, 'text', 0, 0, 'abc');
        $s2 = $port->splice($port->clone($base, 'bbbbbb'), 'text', 0, 0, 'xyz');
        $merged = $port->mergeDocuments($s1, $s2);
        $text = $merged->text('text')->toString();

        same($merged->text('text')->length(), 6, 'merged concurrent text should have both insertions');
        oneOf($text, ['abcxyz', 'xyzabc'], 'merged concurrent text should match one accepted upstream ordering');
    }
);

$rustMapped(
    'rust text updateText merges independent replacements',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:simple-update-text',
    'simple_update_text',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'Hello, world!');
        $doc2 = $port->clone($doc, 'bbbbbb');
        $doc2 = $port->updateText($doc2, 'text', 'Goodbye, world!');
        $doc = $port->updateText($doc, 'text', 'Hello, friends!');
        $merged = $port->mergeDocuments($doc, $doc2);

        same($merged->text('text')->toString(), 'Goodbye, friends!', 'independent updateText replacements should merge into one text value');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text updateText merges multicodepoint graphemes',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:update-text-big-ole-graphemes',
    'update_text_big_ole_graphemes',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'left👨‍👩‍👦right');
        $doc2 = $port->clone($doc, 'bbbbbb');
        $doc2 = $port->updateText($doc2, 'text', 'left👨‍👩‍👧right');
        $doc = $port->updateText($doc, 'text', 'left👨‍👩‍👦‍👦right');
        $merged = $port->mergeDocuments($doc, $doc2);

        same($merged->text('text')->toString(), 'left👨‍👩‍👧👨‍👩‍👦‍👦right', 'updateText should merge independent grapheme replacements without splitting clusters');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text updateText at historical heads integrates later insertions',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:update-text-change-at',
    'update_text_change_at',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->updateText($doc, 'text', "a\n");
        $initialHeads = $port->getHeads($doc);

        $doc = $port->updateText($doc, 'text', "a\nb\n");
        $doc = $port->updateTextAtHeads($doc, $initialHeads, 'text', "a\nc\n");

        same($doc->text('text')->toString(), "a\nc\nb\n", 'historical updateText should integrate with the later insertion');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text can create separated marks after insertion',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:mark-created-after-insertion',
    'mark_created_after_insertion',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, '12345');
        $doc = $port->mark($doc, ['text'], 1, 2, 'strong', true, 'both');
        $doc = $port->mark($doc, ['text'], 3, 4, 'strong', true, 'both');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => '1'],
            ['type' => 'text', 'value' => '2', 'marks' => ['strong' => true]],
            ['type' => 'text', 'value' => '3'],
            ['type' => 'text', 'value' => '4', 'marks' => ['strong' => true]],
            ['type' => 'text', 'value' => '5'],
        ], 'separated same-name marks should not throw or collapse intervening text');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text local patches are segmented by marks',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:local-patches-created-for-marks',
    'local_patches_created_for_marks',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'the quick fox jumps over the lazy dog');
        $doc = $port->mark($doc, ['text'], 0, 37, 'bold', true, 'both');
        $doc = $port->mark($doc, ['text'], 4, 19, 'italic', true, 'both');
        $doc = $port->mark($doc, ['text'], 10, 13, 'comment:somerandomcommentid', 'foxes are my favorite animal!', 'both');

        sameArray($port->diffIncremental($doc), [
            ['action' => 'put', 'path' => ['text'], 'value' => ''],
            ['action' => 'splice', 'path' => ['text', 0], 'value' => 'the ', 'marks' => ['bold' => true]],
            ['action' => 'splice', 'path' => ['text', 4], 'value' => 'quick ', 'marks' => ['bold' => true, 'italic' => true]],
            ['action' => 'splice', 'path' => ['text', 10], 'value' => 'fox', 'marks' => [
                'bold' => true,
                'comment:somerandomcommentid' => 'foxes are my favorite animal!',
                'italic' => true,
            ]],
            ['action' => 'splice', 'path' => ['text', 13], 'value' => ' jumps', 'marks' => ['bold' => true, 'italic' => true]],
            ['action' => 'splice', 'path' => ['text', 19], 'value' => ' over the lazy dog', 'marks' => ['bold' => true]],
        ], 'full diff patches should split inserted text by active mark sets');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text consolidates zero-length mark spans',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:spans-are-consolidated-in-the-presence-of-zero-length-spans',
    'spans_are_consolidated_in_the_presence_of_zero_length_spans',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, '1234');
        $doc = $port->mark($doc, ['text'], 1, 1, 'strong', true, 'both');
        $doc = $port->mark($doc, ['text'], 2, 2, 'strong', true, 'both');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => '1234'],
        ], 'zero-length marks should not split visible text spans');
    },
    'rust/automerge/tests/text.rs'
);

const MARKS_PROPERTY_SENTINEL = '__am_php_no_previous_marks__';

$rustMapped(
    'rust text empty marks before block markers do not repeat text',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:empty-marks-before-block-marker-dont-repeat-text',
    'empty_marks_before_block_marker_dont_repeat_text',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splitBlock($doc, ['text'], 0, []);
        $doc = $port->splitBlock($doc, ['text'], 0, []);
        $doc = $port->mark($doc, ['text'], 1, 1, 'strong', true, 'both');
        $doc = $port->splice($doc, 'text', 2, 0, 'a');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => '', 'attrs' => []]],
            ['type' => 'text', 'value' => 'a'],
        ], 'empty marks before block markers should not duplicate text spans');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text marks property keeps spans consolidated',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:marks-are-okay',
    'marks_are_okay',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->spliceAtPath($doc, ['text'], 0, 0, 'abcd');
        $expected = 'abcd';
        $doc = $port->mark($doc, ['text'], 1, 4, 'tone', 'warm', 'both');
        $doc = $port->splitBlock($doc, ['text'], 2, []);
        $expected = substr($expected, 0, 2) . "\n" . substr($expected, 2);
        $doc = $port->spliceAtPath($doc, ['text'], 4, 0, 'XY');
        $expected = substr($expected, 0, 4) . 'XY' . substr($expected, 4);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 1, '');
        $expected = substr($expected, 0, 1) . substr($expected, 2);

        $spanText = '';
        $lastMarks = MARKS_PROPERTY_SENTINEL;
        foreach ($port->spans($doc, ['text']) as $span) {
            if (($span['type'] ?? null) === 'block') {
                $spanText .= "\n";
                $lastMarks = MARKS_PROPERTY_SENTINEL;
                continue;
            }

            $marks = $span['marks'] ?? null;
            truthy($marks !== $lastMarks, 'adjacent text spans should not repeat the same mark set');
            $spanText .= (string) ($span['value'] ?? '');
            $lastMarks = $marks;
        }

        same($spanText, $expected, 'span text should match the expected text with block markers as newlines');
        same(str_replace("\u{FFFC}", "\n", $doc->toArray()['text']), $expected, 'document text should match the expected text with block markers normalized');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text removed marks do not appear in get marks',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:removed-marks-should-not-appear-in-get-marks',
    'removed_marks_should_not_appear_in_get_marks',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'abcdefg');
        $doc = $port->mark($doc, ['text'], 0, 1, 'name1', 1, 'none');
        $doc = $port->mark($doc, ['text'], 0, 1, 'name1', null, 'none');

        sameArray($port->marksAt($doc, ['text'], 0), [], 'marksAt should not report marks removed with a null mark operation');
        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'abcdefg'],
        ], 'removed marks should leave the text unmarked');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text incremental splices inherit active marks',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:incremental-splice-patches-include-marks',
    'incremental_splice_patches_include_marks',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, '12345');
        $doc = $port->mark($doc, ['text'], 1, 2, 'strong', true, 'both');
        $doc = $port->splice($doc, 'text', 1, 0, '-');
        $doc = $port->splice($doc, 'text', 2, 0, '-');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => '1'],
            ['type' => 'text', 'value' => '--2', 'marks' => ['strong' => true]],
            ['type' => 'text', 'value' => '345'],
        ], 'splices at both boundaries of an expand-both mark should inherit the mark');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text expand-both marks cover boundary insertions',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:expand-marks-are-reported-in-patches',
    'expand_marks_are_reported_in_patches',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'aaabbbccc');
        $doc = $port->mark($doc, ['text'], 3, 6, 'strong', true, 'both');
        $doc = $port->splice($doc, 'text', 6, 0, '<');
        $doc = $port->splice($doc, 'text', 3, 0, '>');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'aaa'],
            ['type' => 'text', 'value' => '>bbb<', 'marks' => ['strong' => true]],
            ['type' => 'text', 'value' => 'ccc'],
        ], 'insertions at both ends of an expand-both mark should stay marked');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text remote expand-after mark merge matches local insertion',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:test-remote-patches-for-marks-with-expand-after',
    'test_remote_patches_for_marks_with_expand_after',
    function () use ($port): void {
        $docA = $port->set($port->init('aaaaaa'), 'text', '');
        $docA = $port->splice($docA, 'text', 0, 0, 'fox');
        $docA = $port->mark($docA, ['text'], 0, 3, 'strong', true, 'after');
        $docB = $port->clone($docA, 'bbbbbb');

        $docA = $port->splice($docA, 'text', 3, 0, 'a');
        $docB = $port->mergeDocuments($docB, $docA);

        $expected = [
            ['type' => 'text', 'value' => 'foxa', 'marks' => ['strong' => true]],
        ];
        sameArray($port->spans($docA, ['text']), $expected, 'local insertion after an expand-after mark should be marked');
        sameArray($port->spans($docB, ['text']), $expected, 'remote merge should materialize the same marked insertion');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust mark patches at end of text survive incremental load',
    'rust:tests-test-mark-patches-rs-target-debug-deps-test-mark-patches-5c7d6b43cf1dbe46:mark-patches-at-end-of-text',
    'mark_patches_at_end_of_text',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'text', '');
        $doc1 = $port->splice($doc1, 'text', 0, 0, 'sample');
        $headsBeforeMark = $port->getHeads($doc1);
        $patches = [];
        $doc2 = $port->cloneWithPatchCallback(
            $port->load($port->save($doc1), 'bbbbbb'),
            static function (array $patchBatch) use (&$patches): void {
                array_push($patches, ...$patchBatch);
            },
            'bbbbbb'
        );

        $doc1 = $port->mark($doc1, ['text'], 5, 6, 'bold', true, 'after');
        $doc2 = $port->loadIncremental($doc2, $port->saveSince($doc1, $headsBeforeMark));

        sameArray($patches, [[
            'action' => 'mark',
            'path' => ['text'],
            'marks' => [['name' => 'bold', 'value' => true, 'start' => 5, 'end' => 6]],
        ]], 'incremental load should emit a mark patch for an end-of-text mark');
        sameArray($port->spans($doc2, ['text']), [
            ['type' => 'text', 'value' => 'sampl'],
            ['type' => 'text', 'value' => 'e', 'marks' => ['bold' => true]],
        ], 'incremental mark patch should materialize at the end of text');
    },
    'rust/automerge/tests/test_mark_patches.rs'
);

$rustMapped(
    'rust text insertions after noexpand spans stay unmarked',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:insertions-after-noexpand-spans-are-not-marked',
    'insertions_after_noexpand_spans_are_not_marked',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splitBlock($doc, ['text'], 0, [
            'type' => 'heading',
            'parents' => [],
            'attrs' => [],
        ]);
        $doc = $port->splice($doc, 'text', 1, 0, 'Heading');
        $doc = $port->splitBlock($doc, ['text'], 8, [
            'type' => 'paragraph',
            'parents' => [],
            'attrs' => [],
        ]);
        $doc = $port->splice($doc, 'text', 9, 0, 'a');
        $doc = $port->mark($doc, ['text'], 9, 9, 'strong', true, 'none');
        $doc = $port->updateSpans($doc, ['text'], [
            ['type' => 'block', 'value' => ['type' => 'heading', 'parents' => [], 'attrs' => []]],
            ['type' => 'text', 'value' => 'Heading'],
            ['type' => 'block', 'value' => ['type' => 'paragraph', 'parents' => [], 'attrs' => []]],
            ['type' => 'text', 'value' => 'a'],
            ['type' => 'block', 'value' => ['type' => 'paragraph', 'parents' => [], 'attrs' => []]],
        ]);
        $doc = $port->splice($doc, 'text', 11, 0, 'a');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'heading', 'attrs' => []]],
            ['type' => 'text', 'value' => 'Heading'],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'paragraph', 'attrs' => []]],
            ['type' => 'text', 'value' => 'a'],
            ['type' => 'block', 'value' => ['parents' => [], 'type' => 'paragraph', 'attrs' => []]],
            ['type' => 'text', 'value' => 'a'],
        ], 'insertions after noexpand zero-length spans should remain unmarked');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text noexpand terminal mark excludes following insertions',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:noexpand-marks-at-the-end-of-text-should-not-emit-marked-patches-on-following-insertions',
    'noexpand_marks_at_the_end_of_text_should_not_emit_marked_patches_on_following_insertions',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'Hello world');
        $doc = $port->mark($doc, ['text'], 10, 11, 'strong', true, 'none');
        $doc = $port->splice($doc, 'text', 11, 0, 'a');

        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'Hello worl'],
            ['type' => 'text', 'value' => 'd', 'marks' => ['strong' => true]],
            ['type' => 'text', 'value' => 'a'],
        ], 'text inserted after a noexpand terminal mark should not inherit the mark');
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust text far-end insertions after crossed marks stay unmarked',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:marks-which-cross-optree-boundaries-are-not-double-counted-in-splice-patches',
    'marks_which_cross_optree_boundaries_are_not_double_counted_in_splice_patches',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, str_repeat('a', 32));
        $doc = $port->mark($doc, ['text'], 15, 17, 'strong', true, 'none');

        for ($i = 0; $i < 100; ++$i) {
            $doc = $port->splitBlock($doc, ['text'], $doc->text('text')->length(), []);
            $insertAt = $doc->text('text')->length();
            $doc = $port->splice($doc, 'text', $insertAt, 0, 'a');

            sameArray($port->marksAt($doc, ['text'], $insertAt), [], 'far-end insertion after an ended mark should stay unmarked');
        }

        sameArray($port->marksAt($doc, ['text'], 15), ['strong' => true], 'original crossed mark should still apply at its start');
        sameArray($port->marksAt($doc, ['text'], 17), [], 'original crossed mark should not leak past its end');
    },
    'rust/automerge/tests/text.rs'
);

$mapped(
    'text and scalar root operations can happen in the same document state',
    'javascript/test/text_test.ts',
    60,
    'should handle text and other ops in the same change',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'text', '');
        $doc = $port->set($doc, 'foo', 'bar');
        $doc = $port->splice($doc, 'text', 0, 0, 'a');

        same($doc->toArray()['foo'], 'bar', 'scalar op should materialize beside text');
        same($doc->text('text')->toString(), 'a', 'text op should materialize beside scalar op');
    }
);

$mapped(
    'document JSON encoding serializes text as a plain string',
    'javascript/test/text_test.ts',
    70,
    'should serialize to JSON as a simple string',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'a"b');

        same(json_encode($doc, JSON_THROW_ON_ERROR), '{"text":"a\"b"}', 'JSON serialization should expose text as a string');
    }
);

$mapped(
    'text can be modified after assignment to a document root key',
    'javascript/test/text_test.ts',
    77,
    'should allow modification after an object is assigned to a document',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'text', '');
        $doc = $port->splice($doc, 'text', 0, 0, 'abcd');
        $doc = $port->splice($doc, 'text', 2, 1);

        same($doc->text('text')->toString(), 'abd', 'assigned text should remain mutable through native splice API');
    }
);

$mapped(
    'public text splice rejects documents outside a change callback',
    'javascript/test/text_test.ts',
    87,
    'should not allow modification outside of a change callback',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'text', '');

        throwsLike(
            static fn (): Document => $port->spliceInChange($doc, ['text'], 0, 0, 'a'),
            'object cannot be modified outside of a change block',
            'strict splice should reject a non-draft document'
        );

        $changed = $port->change(
            $doc,
            static function (Document $draft) use ($port): void {
                $port->spliceInChange($draft, ['text'], 0, 0, 'a');
            }
        );
        same($changed->toArray()['text'], 'a', 'strict splice should mutate the active change draft');
    }
);

$mapped(
    'unicode text values survive root materialization',
    'javascript/test/text_test.ts',
    115,
    'should support unicode when creating text',
    function () use ($port): void {
        $doc = $port->from(['text' => '🐦'], 'aabbcc');

        same($doc->toArray()['text'], '🐦', 'unicode text should materialize unchanged');
    }
);

$mapped(
    'text splice can target string values nested in arrays',
    'javascript/test/text_test.ts',
    122,
    'should allow splicing into text in arrays',
    function () use ($port): void {
        $doc = $port->from(['dom' => [['world']]], 'aabbcc');
        $doc = $port->spliceAtPath($doc, ['dom', 0, 0], 0, 0, 'Hello ');

        sameArray($doc->toArray(), ['dom' => [['Hello world']]], 'nested array text splice should update the string value in place');
    }
);

$mapped(
    'from initializes text values with string length and index access',
    'javascript/test/text_test.ts',
    95,
    'should initialize text in Automerge.from()',
    function () use ($port): void {
        $doc = $port->from(['text' => 'init'], 'aabbcc');
        $text = $doc->text('text');

        same($text->length(), 4, 'initial text should expose string length');
        same($text->charAt(0), 'i', 'first initial character should be readable');
        same($text->charAt(1), 'n', 'second initial character should be readable');
        same($text->charAt(2), 'i', 'third initial character should be readable');
        same($text->charAt(3), 't', 'fourth initial character should be readable');
        same($text->toString(), 'init', 'initial text should materialize as the original string');
    }
);

$mapped(
    'from encodes the initial root as one replayable native change',
    'javascript/test/text_test.ts',
    105,
    'should encode the initial value as a change',
    function () use ($port): void {
        $doc = $port->from(['text' => 'init'], 'aabbcc');
        $changes = $port->getAllChanges($doc);
        same(count($changes), 1, 'from({text: "init"}) should produce one native initial change');

        $applied = $port->applyChanges($port->init('ddeeff'), $changes);
        same($applied->text('text')->toString(), 'init', 'applying the initial change should reconstruct text');
    }
);

$mapped(
    'updateText merges non-overlapping replacements from concurrent actors',
    'javascript/test/text_test.ts',
    132,
    'should calculate a diff when updating text',
    function () use ($port): void {
        $doc1 = $port->from(['text' => 'Hello world!'], 'aaaaaa');
        $doc2 = $port->clone($doc1, 'bbbbbb');

        $doc2 = $port->updateText($doc2, 'text', 'Goodbye world!');
        $doc1 = $port->updateText($doc1, 'text', 'Hello friends!');

        $merged = $port->mergeDocuments($doc1, $doc2);
        same($merged->text('text')->toString(), 'Goodbye friends!', 'concurrent updateText edits should merge by preserved anchors');
    }
);

$mapped(
    'updateText handles multi-character grapheme clusters',
    'javascript/test/text_test.ts',
    148,
    'should handle multi character grapheme clusters',
    function () use ($port): void {
        $doc1 = $port->from(['text' => 'left👨‍👩‍👦right'], 'aaaaaa');
        $doc2 = $port->clone($doc1, 'bbbbbb');

        $doc2 = $port->updateText($doc2, 'text', 'left👨‍👩‍👧right');
        $doc1 = $port->updateText($doc1, 'text', 'left👨‍👩‍👦‍👦right');

        $merged = $port->mergeDocuments($doc1, $doc2);

        same($merged->text('text')->toString(), 'left👨‍👩‍👧👨‍👩‍👦‍👦right', 'concurrent grapheme replacements should preserve both clusters');
    }
);

$mapped(
    'change metadata since heads matches decoded native changes',
    'javascript/test/basic_test.ts',
    300,
    'get change metadata',
    function () use ($port): void {
        $doc = $port->from(['text' => 'hello world'], 'aaaaaa');
        $heads = $port->getHeads($doc);
        $doc = $port->set($doc, 'foo', 'bar');
        $doc = $port->set($doc, 'zip', 'zop');

        $changes = array_map(
            static fn (array $change): array => $port->decodeChange($change),
            $port->getChangesSince($doc, $heads)
        );
        $meta = $port->getChangesMetaSince($doc, $heads);

        same(count($changes), 2, 'two changes should be returned after the saved heads');
        same(count($meta), 2, 'two metadata entries should be returned after the saved heads');
        for ($i = 0; $i < 2; ++$i) {
            same($changes[$i]['actor'], $meta[$i]['actor'], 'metadata actor should match decoded change');
            same($changes[$i]['hash'], $meta[$i]['hash'], 'metadata hash should match decoded change');
            same($changes[$i]['message'], $meta[$i]['message'], 'metadata message should match decoded change');
            same($changes[$i]['time'], $meta[$i]['time'], 'metadata time should match decoded change');
            sameArray($changes[$i]['deps'], $meta[$i]['deps'], 'metadata deps should match decoded change');
            same($changes[$i]['startOp'], $meta[$i]['startOp'], 'metadata startOp should match decoded change');
        }
    }
);

$mapped(
    'basic load can explicitly allow a change with missing dependencies',
    'javascript/test/basic_test.ts',
    327,
    'should work in unstable',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->set($doc1, 'list', [1, 2, 3]);
        $doc3 = $port->pushList($doc2, 'list', [4]);
        $changes = $port->getChanges($doc2, $doc3);

        same(count($changes), 1, 'single later change should be selected after doc2 heads');
        throwsLike(
            static fn (): Document => $port->loadChange($changes[0]),
            'missing dependencies',
            'loading a change with missing dependencies should fail unless explicitly allowed'
        );

        $loaded = $port->loadChange($changes[0], true, 'bbbbbb');
        sameArray($loaded->toArray(), ['list' => [1, 2, 3, 4]], 'allowing missing dependencies should load the isolated change payload');
    }
);

$rustMapped(
    'rust save retains orphaned changes until missing deps arrive',
    'rust:tests-test-save-load-orphans-rs-target-debug-deps-test-save-load-orphans-f9b6758020e89e58:save-orphaned-changes',
    'save_orphaned_changes',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'key', 'value');
        $doc2 = $port->clone($doc1, 'bbbbbb');

        $doc2 = $port->set($doc2, 'key', 'value2');
        $missingChange = $port->getLastLocalChange($doc2);
        $doc2 = $port->set($doc2, 'key', 'value3');
        $orphanedChange = $port->getLastLocalChange($doc2);

        $doc1 = $port->applyChanges($doc1, [$orphanedChange]);
        $loaded = $port->load($port->save($doc1), 'cccccc');
        $loaded = $port->applyChanges($loaded, [$missingChange]);

        sameArray($loaded->toArray(), ['key' => 'value3'], 'saved orphaned change should apply after the missing dependency arrives');
    },
    'rust/automerge/tests/test_save_load_orphans.rs'
);

$rustMapped(
    'rust save can discard orphaned changes',
    'rust:tests-test-save-load-orphans-rs-target-debug-deps-test-save-load-orphans-f9b6758020e89e58:discard-orphans',
    'discard_orphans',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'key', 'value');
        $doc2 = $port->clone($doc1, 'bbbbbb');

        $doc2 = $port->set($doc2, 'key', 'value2');
        $missingChange = $port->getLastLocalChange($doc2);
        $doc2 = $port->set($doc2, 'key', 'value3');
        $orphanedChange = $port->getLastLocalChange($doc2);

        $doc1 = $port->applyChanges($doc1, [$orphanedChange]);
        $loaded = $port->load($port->saveWithOptions($doc1, ['retainOrphans' => false]), 'cccccc');
        $loaded = $port->applyChanges($loaded, [$missingChange]);

        sameArray($loaded->toArray(), ['key' => 'value2'], 'discarded orphaned dependent change should not reappear after the missing dependency arrives');
    },
    'rust/automerge/tests/test_save_load_orphans.rs'
);

$rustMapped(
    'rust loading standalone incremental change without deps throws',
    'rust:tests-test-save-load-orphans-rs-target-debug-deps-test-save-load-orphans-f9b6758020e89e58:load-incremental-change-without-deps-throws',
    'load_incremental_change_without_deps_throws',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');
        $port->saveIncremental($doc);
        $doc = $port->set($doc, 'key', 'value2');
        $orphan = $port->saveIncremental($doc);

        throwsLike(
            static fn (): Document => $port->load($orphan, 'bbbbbb'),
            'missing dependencies',
            'loading an incremental change chunk without its document chunk should fail'
        );
    },
    'rust/automerge/tests/test_save_load_orphans.rs'
);

$mapped(
    'change metadata defaults to the current timestamp',
    'javascript/test/change_time.ts',
    7,
    'should default to current timestamp',
    function () use ($port): void {
        $before = time();
        $doc = $port->set($port->init('aaaaaa'), 'answer', 42);
        $after = time();
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        truthy(
            is_int($decoded['time'] ?? null) && $before <= $decoded['time'] && $decoded['time'] <= $after,
            'default change timestamp should be recorded in seconds'
        );
    }
);

$mapped(
    'change metadata accepts a user-provided timestamp',
    'javascript/test/change_time.ts',
    18,
    'should allow user provided timestamp',
    function () use ($port): void {
        $doc = $port->setWithTime($port->init('aaaaaa'), 'answer', 42, 12345);
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        same($decoded['time'], 12345, 'user-provided change timestamp should be recorded');
    }
);

$mapped(
    'change metadata can explicitly record no timestamp',
    'javascript/test/change_time.ts',
    27,
    'should allow no timestamp',
    function () use ($port): void {
        $doc = $port->setWithoutTime($port->init('aaaaaa'), 'answer', 42);
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        same($decoded['time'], 0, 'explicit no-timestamp changes should record time zero');
    }
);

$mapped(
    'emptyChange metadata defaults to the current timestamp',
    'javascript/test/change_time.ts',
    37,
    'should default to current timestamp',
    function () use ($port): void {
        $before = time();
        $doc = $port->emptyChange($port->init('aaaaaa'));
        $after = time();
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        truthy(
            is_int($decoded['time'] ?? null) && $before <= $decoded['time'] && $decoded['time'] <= $after,
            'default emptyChange timestamp should be recorded in seconds'
        );
    }
);

$mapped(
    'emptyChange metadata accepts a user-provided timestamp',
    'javascript/test/change_time.ts',
    48,
    'should allow user provided timestamp',
    function () use ($port): void {
        $doc = $port->emptyChangeWithTime($port->init('aaaaaa'), 12345);
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        same($decoded['time'], 12345, 'user-provided emptyChange timestamp should be recorded');
    }
);

$mapped(
    'emptyChange metadata can explicitly record no timestamp',
    'javascript/test/change_time.ts',
    57,
    'should allow no timestamp',
    function () use ($port): void {
        $doc = $port->emptyChangeWithoutTime($port->init('aaaaaa'));
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        same($decoded['time'], 0, 'explicit no-timestamp emptyChange should record time zero');
    }
);

$mapped(
    'emptyChange advances document heads with a new hash',
    'javascript/test/basic_test.ts',
    396,
    'should generate a hash',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');
        $port->save($doc);
        $headsBefore = $port->getHeads($doc);
        sort($headsBefore);

        $doc = $port->emptyChange($doc, 'empty change');
        $headsAfter = $port->getHeads($doc);
        sort($headsAfter);

        truthy($headsBefore !== $headsAfter, 'emptyChange should generate a distinct head hash');
    }
);

$mapped(
    'legacy emptyChange appends an empty change to history',
    'javascript/test/legacy_tests.ts',
    402,
    'should append an empty change to the history',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'field', 123);
        $after = $port->emptyChange($doc, 'empty change');

        truthy($after !== $doc, 'emptyChange should return a distinct document instance');
        same($after->toArray(), $doc->toArray(), 'emptyChange should not alter materialized document data');

        $history = $port->getHistory($after);
        sameArray(
            array_map(static fn (array $entry): ?string => $entry['change']['message'], $history),
            [null, 'empty change'],
            'history should include the set change and appended empty change'
        );
        same($history[1]['change']['ops'], [], 'appended empty change should contain no operations');
    }
);

$mapped(
    'legacy emptyChange references merged dependencies',
    'javascript/test/legacy_tests.ts',
    413,
    'should reference dependencies',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'field', 123);
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);
        $doc2 = $port->set($doc2, 'other', 'hello');
        $doc1 = $port->emptyChange($port->mergeDocuments($doc1, $doc2));

        $history = $port->getHistory($doc1);
        same(count($history), 3, 'history should contain two data changes plus the empty change');

        $emptyChange = $history[2]['change'];
        $expectedDeps = [$history[0]['change']['hash'], $history[1]['change']['hash']];
        $actualDeps = $emptyChange['deps'];
        sort($expectedDeps);
        sort($actualDeps);

        sameArray($actualDeps, $expectedDeps, 'empty change should reference both merged heads');
        same($emptyChange['ops'], [], 'dependency-only empty change should contain no operations');
    }
);

$mapped(
    'legacy save/load restores an empty document',
    'javascript/test/legacy_tests.ts',
    1413,
    'should save and restore an empty document',
    function () use ($port): void {
        $loaded = $port->load($port->save($port->init('aabbcc')));

        sameArray($loaded->toArray(), [], 'loading a saved empty document should materialize an empty root map');
    }
);

$mapped(
    'legacy save/load assigns a new actor id by default',
    'javascript/test/legacy_tests.ts',
    1418,
    'should generate a new random actor ID',
    function () use ($port): void {
        $doc1 = $port->init();
        $doc2 = $port->load($port->save($doc1));

        truthy(ctype_xdigit($port->getActorId($doc1)), 'initial actor id should be hex');
        truthy(ctype_xdigit($port->getActorId($doc2)), 'loaded actor id should be hex');
        truthy($port->getActorId($doc1) !== $port->getActorId($doc2), 'loading without an actor should assign a fresh actor id');
    }
);

$mapped(
    'legacy save/load accepts a custom actor id',
    'javascript/test/legacy_tests.ts',
    1432,
    'should allow a custom actor ID to be set',
    function () use ($port): void {
        $doc = $port->load($port->save($port->init('aabbcc')), '333333');

        same($port->getActorId($doc), '333333', 'loading with an explicit actor id should use it');
    }
);

$mapped(
    'legacy save/load reconstitutes nested list and map data',
    'javascript/test/legacy_tests.ts',
    1437,
    'should reconstitute complex datatypes',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'todos', [['title' => 'water plants', 'done' => false]]);
        $loaded = $port->load($port->save($doc));

        sameArray($loaded->toArray(), ['todos' => [['title' => 'water plants', 'done' => false]]], 'nested list/map data should survive save/load');
    }
);

$mapped(
    'legacy save/load keeps map keys containing at-signs',
    'javascript/test/legacy_tests.ts',
    1448,
    'should save and load maps with @ symbols in the keys',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), '123@4567', 'hello');
        $loaded = $port->load($port->save($doc));

        sameArray($loaded->toArray(), ['123@4567' => 'hello'], 'map keys containing at-signs should survive save/load');
    }
);

$mapped(
    'legacy save/load reconstitutes root conflicts',
    'javascript/test/legacy_tests.ts',
    1457,
    'should reconstitute conflicts',
    function () use ($port): void {
        $doc1 = $port->set($port->init('111111'), 'x', 3);
        $doc2 = $port->set($port->init('222222'), 'x', 5);
        $merged = $port->mergeDocuments($doc1, $doc2);
        $loaded = $port->load($port->save($merged));

        same($loaded->toArray()['x'], 5, 'conflicted root value should materialize the deterministic winner after load');
        sameArray(
            $port->getConflicts($loaded, 'x') ?? [],
            [
                '1@111111' => 3,
                '1@222222' => 5,
            ],
            'root conflicts should survive save/load'
        );
    }
);

$mapped(
    'legacy save/load reconstitutes element id counters',
    'javascript/test/legacy_tests.ts',
    1480,
    'should reconstitute element ID counters',
    function () use ($port): void {
        $doc = $port->set($port->init('01234567'), 'list', ['a']);
        $doc = $port->deleteListElements($doc, 'list', 0);
        $loaded = $port->load($port->save($doc), '01234567');
        $loaded = $port->pushList($loaded, 'list', ['b']);
        $changes = array_map(
            static fn (array $change): array => $port->decodeChange($change),
            $port->getAllChanges($loaded)
        );

        sameArray($loaded->toArray(), ['list' => ['b']], 'reloaded list should accept a later insertion after deletion');
        same(count($changes), 3, 'initial list creation, deletion, and insertion should remain in history');
        same($changes[2]['actor'], '01234567', 'post-load insertion should use the requested actor id');
        same($changes[2]['seq'], 3, 'post-load insertion should continue the actor sequence');
        same($changes[2]['startOp'], 5, 'post-load insertion should continue after list/text element operation ids');
        sameArray($changes[2]['deps'], [$changes[1]['hash']], 'post-load insertion should depend on the delete change');
    }
);

$mapped(
    'legacy history returns an empty list for an empty document',
    'javascript/test/legacy_tests.ts',
    1596,
    'should return an empty history for an empty document',
    function () use ($port): void {
        sameArray($port->getHistory($port->init('aabbcc')), [], 'empty document should expose no history entries');
    }
);

$mapped(
    'legacy history exposes past document snapshots',
    'javascript/test/legacy_tests.ts',
    1600,
    'should make past document states accessible',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'config', ['background' => 'blue']);
        $doc = $port->set($doc, 'birds', ['mallard']);
        $doc = $port->insertListElements($doc, 'birds', 0, ['oystercatcher']);
        $snapshots = array_map(
            static fn (array $entry): array => $entry['snapshot']->toArray(),
            $port->getHistory($doc)
        );

        sameArray(
            $snapshots,
            [
                ['config' => ['background' => 'blue']],
                ['config' => ['background' => 'blue'], 'birds' => ['mallard']],
                ['config' => ['background' => 'blue'], 'birds' => ['oystercatcher', 'mallard']],
            ],
            'history should expose each materialized past snapshot'
        );
    }
);

$mapped(
    'legacy history exposes change messages',
    'javascript/test/legacy_tests.ts',
    1618,
    'should make change messages accessible',
    function () use ($port): void {
        $doc = $port->setWithMessage($port->init('aabbcc'), 'books', [], 'Empty Bookshelf');
        $doc = $port->insertListElementsWithMessage($doc, 'books', 0, ['Nineteen Eighty-Four'], 'Add Orwell');
        $doc = $port->insertListElementsWithMessage($doc, 'books', 1, ['Brave New World'], 'Add Huxley');

        sameArray($doc->toArray()['books'], ['Nineteen Eighty-Four', 'Brave New World'], 'message-bearing list updates should materialize the final list');
        sameArray(
            array_map(static fn (array $entry): ?string => $entry['change']['message'], $port->getHistory($doc)),
            ['Empty Bookshelf', 'Add Orwell', 'Add Huxley'],
            'history should expose each change message'
        );
    }
);

$mapped(
    'hasHeads returns true for heads present in the document history',
    'javascript/test/basic_test.ts',
    685,
    'should return true if the document in question has all the heads',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 'b');
        $heads = $port->getHeads($doc);

        truthy($port->hasHeads($doc, $heads), 'document should report its own heads as present');
    }
);

$mapped(
    'hasHeads returns false for heads absent from another document',
    'javascript/test/basic_test.ts',
    692,
    'should return false if the document does not have the heads',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 'b');
        $heads = $port->getHeads($doc);
        $otherDoc = $port->init('bbbbbb');

        truthy(! $port->hasHeads($otherDoc, $heads), 'different document should not report unknown heads as present');
    }
);

$mapped(
    'basic topo history traversal returns merged change hashes in order',
    'javascript/test/basic_test.ts',
    703,
    'should return the correct history',
    function () use ($port): void {
        $doc = $port->from(['a' => 'a'], 'aaaaaa');
        $hash1 = $port->decodeChange($port->getLastLocalChange($doc) ?? [])['hash'];
        $doc2 = $port->clone($doc, 'bbbbbb');
        $doc = $port->set($doc, 'a', 'b');
        $hash2 = $port->decodeChange($port->getLastLocalChange($doc) ?? [])['hash'];
        $doc2 = $port->set($doc2, 'a', 'c');
        $hash3 = $port->decodeChange($port->getLastLocalChange($doc2) ?? [])['hash'];
        $doc = $port->mergeDocuments($doc, $doc2);

        sameArray(
            $port->topoHistoryTraversal($doc),
            [$hash1, $hash2, $hash3],
            'topological history traversal should expose merged change hashes in dependency order'
        );
    }
);

$mapped(
    'basic inspectChange returns decoded change metadata',
    'javascript/test/basic_test.ts',
    730,
    'should return a decoded representation of the change',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $doc = $port->setWithTime($doc, 'a', 'a', 123);
        $hash = $port->topoHistoryTraversal($doc)[0];

        sameArray(
            $port->inspectChange($doc, $hash) ?? [],
            [
                'actor' => 'aaaaaa',
                'deps' => [],
                'hash' => $hash,
                'message' => null,
                'ops' => [
                    [
                        'action' => 'makeText',
                        'key' => 'a',
                        'obj' => '_root',
                        'pred' => [],
                    ],
                    [
                        'action' => 'set',
                        'elemId' => '_head',
                        'insert' => true,
                        'obj' => '1@aaaaaa',
                        'pred' => [],
                        'value' => 'a',
                    ],
                ],
                'seq' => 1,
                'startOp' => 1,
                'time' => 123,
            ],
            'inspectChange should expose decoded metadata and text operations'
        );
    }
);

$mapped(
    'basic stats reports native change and operation counts',
    'javascript/test/basic_test.ts',
    765,
    'should return stats about the document',
    function () use ($port): void {
        $doc = $port->set($port->init('aabbcc'), 'a', 1);
        $doc = $port->set($doc, 'a', 2);
        $stats = $port->stats($doc);

        same($stats['numChanges'], 2, 'stats should report two native changes');
        same($stats['numOps'], 2, 'stats should report two native operations');
        truthy(is_string($stats['cargoPackageName']) && $stats['cargoPackageName'] !== '', 'stats should expose a package-name string');
        truthy(is_string($stats['cargoPackageVersion']) && $stats['cargoPackageVersion'] !== '', 'stats should expose a package-version string');
        truthy(is_string($stats['rustcVersion']) && $stats['rustcVersion'] !== '', 'stats should expose a runtime-version string');
    }
);

$mapped(
    'native save/load round trip hydrates a materialized document',
    'javascript/test/basic_test.ts',
    578,
    'can load a doc without checking the heads',
    function () use ($port): void {
        $doc = $port->from(['count' => 260], 'aaaaaa');
        $loaded = $port->load($port->save($doc));

        sameArray($loaded->toArray(), ['count' => 260], 'loaded native payload should materialize the saved root');
    }
);

$mapped(
    'legacy changes API returns an empty list for an empty document',
    'javascript/test/legacy_tests.ts',
    1639,
    'should return an empty list on an empty document',
    function () use ($port): void {
        sameArray($port->getAllChanges($port->init('aaaaaa')), [], 'empty document should have no changes');
    }
);

$mapped(
    'legacy changes API returns an empty list when nothing changed',
    'javascript/test/legacy_tests.ts',
    1644,
    'should return an empty list when nothing changed',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'birds', ['Chaffinch']);

        sameArray($port->getChanges($doc, $doc), [], 'getChanges should be empty for identical document heads');
    }
);

$mapped(
    'legacy changes API does nothing when applying an empty change list',
    'javascript/test/legacy_tests.ts',
    1652,
    'should do nothing when applying an empty list of changes',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'birds', ['Chaffinch']);
        $applied = $port->applyChanges($doc, []);

        sameArray($applied->toArray(), $doc->toArray(), 'applying no changes should preserve the document');
    }
);

$mapped(
    'legacy changes API returns all changes compared to an empty document',
    'javascript/test/legacy_tests.ts',
    1660,
    'should return all changes when compared to an empty document',
    function () use ($port): void {
        $empty = $port->init('aaaaaa');
        $doc = $port->set($empty, 'birds', ['Chaffinch']);
        $doc = $port->set($doc, 'birds', ['Chaffinch', 'Bullfinch']);

        same(count($port->getChanges($empty, $doc)), 2, 'empty-to-current diff should return both changes');
    }
);

$mapped(
    'legacy changes API reconstructs a document copy from scratch',
    'javascript/test/legacy_tests.ts',
    1673,
    'should allow a document copy to be reconstructed from scratch',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'birds', ['Chaffinch']);
        $doc = $port->set($doc, 'birds', ['Chaffinch', 'Bullfinch']);
        $copy = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc));

        sameArray($copy->toArray(), ['birds' => ['Chaffinch', 'Bullfinch']], 'all changes should reconstruct the list value');
    }
);

$mapped(
    'legacy changes API returns changes since the last given version',
    'javascript/test/legacy_tests.ts',
    1687,
    'should return changes since the last given version',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'birds', ['Chaffinch']);
        $doc2 = $port->set($doc1, 'birds', ['Chaffinch', 'Bullfinch']);

        same(count($port->getAllChanges($doc1)), 1, 'first document should have one change');
        same(count($port->getChanges($doc1, $doc2)), 1, 'diff from first to second document should have one change');
    }
);

$mapped(
    'legacy changes API incrementally applies changes since the last version',
    'javascript/test/legacy_tests.ts',
    1702,
    'should incrementally apply changes since the last given version',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'birds', ['Chaffinch']);
        $changes1 = $port->getAllChanges($doc1);
        $doc2 = $port->set($doc1, 'birds', ['Chaffinch', 'Bullfinch']);
        $changes2 = $port->getChanges($doc1, $doc2);

        $applied1 = $port->applyChanges($port->init('bbbbbb'), $changes1);
        $applied2 = $port->applyChanges($applied1, $changes2);

        sameArray($applied1->toArray(), ['birds' => ['Chaffinch']], 'first batch should reconstruct the first list value');
        sameArray($applied2->toArray(), ['birds' => ['Chaffinch', 'Bullfinch']], 'second batch should add the second list value');
    }
);

$mapped(
    'legacy changes API handles updates to a list element',
    'javascript/test/legacy_tests.ts',
    1719,
    'should handle updates to a list element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'birds', ['Chaffinch', 'Bullfinch']);
        $doc2 = $port->set($doc1, 'birds', ['Goldfinch', 'Bullfinch']);
        $applied = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc2));
        $birds = $applied->toArray()['birds'];

        sameArray($birds, ['Goldfinch', 'Bullfinch'], 'all changes should replay the latest list element value');
        same($port->getConflicts($birds, 0), null, 'single-writer list element update should report no conflict');
    }
);

$mapped(
    'legacy changes API handles updates to a text object',
    'javascript/test/legacy_tests.ts',
    1734,
    'should handle updates to a text object',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'text', 'ab');
        $doc2 = $port->splice($doc1, 'text', 0, 1, 'A');
        $applied = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc2));
        $text = $applied->text('text');

        same($text->charAt(0), 'A', 'text replay should replace the first character');
        same($text->charAt(1), 'b', 'text replay should preserve the second character');
        same($text->toString(), 'Ab', 'text replay should materialize the updated string');
    }
);

$mapped(
    'legacy changes API reports missing dependencies for out-of-order applyChanges',
    'javascript/test/legacy_tests.ts',
    1764,
    'should report missing dependencies with out-of-order applyChanges',
    function () use ($port): void {
        $doc0 = $port->init('aaaaaa');
        $doc1 = $port->set($doc0, 'test', ['a']);
        $changes01 = $port->getAllChanges($doc1);

        $doc2 = $port->set($doc1, 'test', ['b']);
        $changes12 = $port->getChanges($doc1, $doc2);

        $doc3 = $port->set($doc2, 'test', ['c']);
        $changes23 = $port->getChanges($doc2, $doc3);

        $outOfOrder = $port->applyChanges($port->init('bbbbbb'), $changes23);
        $outOfOrder = $port->applyChanges($outOfOrder, $changes12);
        $firstChange = $port->decodeChange($changes01[0]);

        sameArray(
            $port->getMissingDeps($outOfOrder, []),
            [$firstChange['hash']],
            'out-of-order applyChanges should report the omitted initial change hash'
        );
    }
);

$mapped(
    'sync protocol sends an empty-data sync message',
    'javascript/test/sync_test.ts',
    54,
    'should send a sync message implying no local data',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        [$syncState, $message] = $port->generateSyncMessage($doc, $port->initSyncState());
        $decoded = $port->decodeSyncMessage($message);

        truthy($decoded !== null, 'first empty sync attempt should produce a message');
        sameArray($decoded['heads'], [], 'empty sync message should advertise no heads');
        sameArray($decoded['need'], [], 'empty sync message should request no changes');
        same(count($decoded['have']), 1, 'empty sync message should include one have entry');
        sameArray($decoded['have'][0]['lastSync'], [], 'empty sync message should have no last sync heads');
        same($decoded['have'][0]['bloom']['byteLength'], 0, 'empty sync message should use an empty bloom filter');
        sameArray($decoded['changes'], [], 'empty sync message should include no changes');
        truthy(is_array($syncState), 'sync state should remain a PHP array after message generation');
    }
);

$rustMapped(
    'rust sync message encoding round-trips an empty message',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-encode-decode-empty-message',
    'sync::tests::encode_decode_empty_message',
    function () use ($port): void {
        $message = [
            'heads' => [],
            'need' => [],
            'have' => [],
            'changes' => [],
            'readOnly' => false,
            'syncReset' => false,
        ];

        $encoded = $port->encodeSyncMessage($message);
        $decoded = $port->decodeEncodedSyncMessage($encoded);

        truthy($encoded !== '', 'empty sync message should encode to a non-empty native payload');
        sameArray($decoded ?? [], $message, 'empty sync message should parse back to the normalized message fields');
    },
    'rust/automerge/src/sync.rs'
);

$mapped(
    'sync protocol does not keep replying for two empty documents',
    'javascript/test/sync_test.ts',
    68,
    'should not reply after the first round if we have no data as well',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->init('bbbbbb');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        if ($message1 !== null) {
            [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message1);
        }

        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);
        if ($message2 !== null) {
            [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $message2);
        }

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        same($message1, null, 'first empty peer should stop sending once the empty state is known');
        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);
        same($message2, null, 'second empty peer should not reply with no local data');
    }
);

$mapped(
    'sync protocol equal heads do not need a reply message',
    'javascript/test/sync_test.ts',
    91,
    'repos with equal heads do not need a reply message',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $items = [];
        $doc1 = $port->set($doc1, 'n', $items);
        for ($i = 0; $i < 10; ++$i) {
            $items[] = $i;
            $doc1 = $port->set($doc1, 'n', $items);
        }

        $doc2 = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc1));
        sameArray($doc2->toArray(), $doc1->toArray(), 'peer documents should start with equal materialized state');

        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();
        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        sameArray($sync1['lastSentHeads'], $port->getHeads($doc1), 'first peer should remember advertised heads');

        if ($message1 !== null) {
            [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message1);
        }

        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);
        sameArray($sync2['lastSentHeads'], $port->getHeads($doc2), 'second peer should remember known equal heads');
        if ($message2 !== null) {
            $decoded = $port->decodeSyncMessage($message2);
            sameArray($decoded['heads'] ?? [], $port->getHeads($doc2), 'optional first response should advertise the equal heads');
            [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $message2);
        }

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        same($message1, null, 'first peer should need no further sync message');
        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);
        same($message2, null, 'second peer should need no further sync message');
    }
);

$rustMapped(
    'rust sync message generation is quiet after first send',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-generate-sync-message-twice-does-nothing',
    'sync::tests::generate_sync_message_twice_does_nothing',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');
        $syncState = $port->initSyncState();

        [$syncState, $message] = $port->generateSyncMessage($doc, $syncState);
        truthy($message !== null, 'first sync message should advertise local heads and changes');

        [$syncState, $message] = $port->generateSyncMessage($doc, $syncState);
        same($message, null, 'second sync generation without new data should be quiet');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync first response is sent even with no missing changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-first-response-is-some-even-if-no-changes',
    'sync::tests::first_response_is_some_even_if_no_changes',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'key', 'value');
        $doc2 = $port->clone($doc1, 'bbbbbb');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        truthy($message1 !== null, 'first peer should send the initial sync message');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message1);
        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);

        truthy($message2 !== null, 'peer should send its first response even when it has no missing changes');
        sameArray(($port->decodeSyncMessage($message2)['changes'] ?? []), [], 'first response with equal heads should not resend changes');
    },
    'rust/automerge/src/sync.rs'
);

$mapped(
    'sync protocol offers all changes to an empty peer',
    'javascript/test/sync_test.ts',
    127,
    'n1 should offer all changes to n2 when starting from nothing',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $items = [];
        $doc1 = $port->set($doc1, 'n', $items);
        for ($i = 0; $i < 10; ++$i) {
            $items[] = $i;
            $doc1 = $port->set($doc1, 'n', $items);
        }

        $doc2 = $port->init('bbbbbb');
        truthy($doc1->toArray() !== $doc2->toArray(), 'one-sided sync test should start with different documents');

        [, $message] = $port->generateSyncMessage($doc1, $port->initSyncState());
        $decoded = $port->decodeSyncMessage($message);
        truthy($decoded !== null, 'data-bearing peer should produce an initial sync message');
        same(count($decoded['changes']), count($port->getAllChanges($doc1)), 'initial one-sided sync message should offer every local change');

        [$after1, $after2] = syncDocuments($port, $doc1, $doc2);
        sameArray($after1->toArray(), $after2->toArray(), 'one-sided sync should converge materialized state');
        sameArray($port->getHeads($after1), $port->getHeads($after2), 'one-sided sync should converge heads');
    }
);

$mapped(
    'sync protocol synchronizes peers when one has commits',
    'javascript/test/sync_test.ts',
    141,
    'should sync peers where one has commits the other does not',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $items = [];
        $doc1 = $port->set($doc1, 'n', $items);
        for ($i = 0; $i < 10; ++$i) {
            $items[] = $i;
            $doc1 = $port->set($doc1, 'n', $items);
        }

        $doc2 = $port->init('bbbbbb');
        truthy($doc1->toArray() !== $doc2->toArray(), 'peer missing commits should start behind');

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2);
        sameArray($doc1->toArray(), $doc2->toArray(), 'sync should copy missing commits to the empty peer');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'sync should leave both peers at the same heads');
    }
);

$mapped(
    'sync protocol converges with prior sync state',
    'javascript/test/sync_test.ts',
    155,
    'should work with prior sync state',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->init('bbbbbb');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 5; ++$i) {
            $doc1 = $port->set($doc1, 'x', $i);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), $doc2->toArray(), 'initial sync should converge before reusing sync state');

        for ($i = 5; $i < 10; ++$i) {
            $doc1 = $port->set($doc1, 'x', $i);
        }

        truthy($doc1->toArray() !== $doc2->toArray(), 'prior-state sync should start with new local commits on one peer');
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), $doc2->toArray(), 'prior-state sync should converge after later commits');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'prior-state sync should converge heads after later commits');
    }
);

$mapped(
    'sync protocol records shared heads after synchronization',
    'javascript/test/sync_test.ts',
    403,
    'should ensure non-empty state after sync',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 3; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($sync1['sharedHeads'], $port->getHeads($doc1), 'first sync state should retain converged shared heads');
        sameArray($sync2['sharedHeads'], $port->getHeads($doc1), 'second sync state should retain converged shared heads');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'documents should converge to the same heads');
    }
);

$mapped(
    'sync protocol resyncs after peer crash with older data',
    'javascript/test/sync_test.ts',
    417,
    'should re-sync after one node crashed with data loss',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 3; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $recovered = $port->clone($doc2);
        $recoveredSync = json_decode(json_encode($sync2, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        for ($i = 3; $i < 6; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge before crash recovery');
        sameArray($doc1->toArray(), $doc2->toArray(), 'peers should materialize the same state before crash recovery');

        for ($i = 6; $i < 9; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        $sync1 = json_decode(json_encode($sync1, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        truthy($port->getHeads($doc1) !== $port->getHeads($recovered), 'recovered peer should start behind current heads');
        sameArray($doc1->toArray(), ['x' => 8], 'current peer should contain the latest value');
        sameArray($recovered->toArray(), ['x' => 2], 'recovered peer should contain only the pre-crash value');

        [$doc1, $recovered, $sync1, $recoveredSync] = syncDocuments($port, $doc1, $recovered, $sync1, $recoveredSync);
        sameArray($port->getHeads($doc1), $port->getHeads($recovered), 'recovered peer should converge to current heads');
        sameArray($doc1->toArray(), $recovered->toArray(), 'recovered peer should converge to current materialized state');
    }
);

$mapped(
    'sync protocol resyncs after peer data loss without reconnect',
    'javascript/test/sync_test.ts',
    459,
    'should resync after one node experiences data loss without disconnecting',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 3; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge before data loss');
        sameArray($doc1->toArray(), $doc2->toArray(), 'peers should materialize the same state before data loss');

        $doc2AfterDataLoss = $port->init('89abcdef');
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2AfterDataLoss, $sync1, $port->initSyncState());

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peer with data loss should regain current heads');
        sameArray($doc1->toArray(), $doc2->toArray(), 'peer with data loss should regain current materialized state');
        sameArray($doc2->toArray(), ['x' => 2], 'recovered peer should contain the pre-loss value');
    }
);

$mapped(
    'sync protocol converges diverged documents without prior sync state',
    'javascript/test/sync_test.ts',
    350,
    'should work without prior sync state',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');

        for ($i = 0; $i < 10; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2);

        for ($i = 10; $i < 15; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        for ($i = 15; $i < 18; ++$i) {
            $doc2 = $port->setWithTime($doc2, 'x', $i, 0);
        }

        truthy($doc1->toArray() !== $doc2->toArray(), 'diverged peers should start with different materialized values');

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'diverged peers without prior state should converge heads');
        sameArray($doc1->toArray(), $doc2->toArray(), 'diverged peers without prior state should converge materialized state');
        sameArray($port->getConflicts($doc1, 'x') ?? [], $port->getConflicts($doc2, 'x') ?? [], 'diverged peers should converge conflict values');
    }
);

$mapped(
    'sync protocol converges diverged documents with prior sync state',
    'javascript/test/sync_test.ts',
    374,
    'should work with prior sync state',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 10; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        for ($i = 10; $i < 15; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        for ($i = 15; $i < 18; ++$i) {
            $doc2 = $port->setWithTime($doc2, 'x', $i, 0);
        }

        $sync1 = json_decode(json_encode($sync1, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $sync2 = json_decode(json_encode($sync2, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        truthy($doc1->toArray() !== $doc2->toArray(), 'diverged peers with prior state should start with different materialized values');

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'diverged peers with prior state should converge heads');
        sameArray($doc1->toArray(), $doc2->toArray(), 'diverged peers with prior state should converge materialized state');
        sameArray($port->getConflicts($doc1, 'x') ?? [], $port->getConflicts($doc2, 'x') ?? [], 'diverged peers with prior state should converge conflict values');
    }
);

$mapped(
    'sync protocol handles changes concurrent to last sync heads',
    'javascript/test/sync_test.ts',
    482,
    'should handle changes concurrent to the last sync heads',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $doc3 = $port->init('fedcba98');
        $sync12 = $port->initSyncState();
        $sync21 = $port->initSyncState();
        $sync23 = $port->initSyncState();
        $sync32 = $port->initSyncState();

        $doc1 = $port->setWithTime($doc1, 'x', 1, 0);
        [$doc1, $doc2, $sync12, $sync21] = syncDocuments($port, $doc1, $doc2, $sync12, $sync21);
        [$doc2, $doc3, $sync23, $sync32] = syncDocuments($port, $doc2, $doc3, $sync23, $sync32);

        $doc1 = $port->setWithTime($doc1, 'x', 2, 0);
        [$doc1, $doc2, $sync12, $sync21] = syncDocuments($port, $doc1, $doc2, $sync12, $sync21);

        $doc1 = $port->setWithTime($doc1, 'x', 3, 0);
        $doc2 = $port->setWithTime($doc2, 'x', 4, 0);
        $doc3 = $port->setWithTime($doc3, 'x', 5, 0);

        $doc3Change = $port->getLastLocalChange($doc3);
        truthy($doc3Change !== null, 'third peer should expose its latest local change');
        $doc2 = $port->applyChanges($doc2, [$doc3Change]);

        [$doc1, $doc2, $sync12, $sync21] = syncDocuments($port, $doc1, $doc2, $sync12, $sync21);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'sync should converge heads with a third-party concurrent change');
        sameArray($doc1->toArray(), $doc2->toArray(), 'sync should converge materialized state with a third-party concurrent change');
    }
);

$mapped(
    'sync protocol handles branching and merging histories',
    'javascript/test/sync_test.ts',
    518,
    'should handle histories with lots of branching and merging',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $doc3 = $port->init('fedcba98');

        $doc1 = $port->setWithTime($doc1, 'x', 0, 0);
        $initialChange = $port->getLastLocalChange($doc1);
        truthy($initialChange !== null, 'initial peer change should be available');
        $doc2 = $port->applyChanges($doc2, [$initialChange]);
        $doc3 = $port->applyChanges($doc3, [$initialChange]);
        $doc3 = $port->setWithTime($doc3, 'x', 1, 0);

        for ($i = 1; $i < 20; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'n1', $i, 0);
            $doc2 = $port->setWithTime($doc2, 'n2', $i, 0);

            $change1 = $port->getLastLocalChange($doc1);
            $change2 = $port->getLastLocalChange($doc2);
            truthy($change1 !== null && $change2 !== null, 'branch peers should expose latest changes');
            $doc1 = $port->applyChanges($doc1, [$change2]);
            $doc2 = $port->applyChanges($doc2, [$change1]);
        }

        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $thirdPeerChange = $port->getLastLocalChange($doc3);
        truthy($thirdPeerChange !== null, 'third peer concurrent change should be available');
        $doc2 = $port->applyChanges($doc2, [$thirdPeerChange]);
        $doc1 = $port->setWithTime($doc1, 'n1', 'final', 0);
        $doc2 = $port->setWithTime($doc2, 'n2', 'final', 0);

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $left = $doc1->toArray();
        $right = $doc2->toArray();
        ksort($left);
        ksort($right);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'branching sync should converge heads');
        sameArray($left, $right, 'branching sync should converge materialized state');
        sameArray($left, ['n1' => 'final', 'n2' => 'final', 'x' => 1], 'branching sync should include the late third-peer change and final local edits');
    }
);

$rustMapped(
    'rust sync handles lots of branching and merging',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-should-handle-lots-of-branching-and-merging',
    'sync::tests::should_handle_lots_of_branching_and_merging',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $doc3 = $port->init('fedcba98');

        $doc1 = $port->setWithTime($doc1, 'x', 0, 0);
        $initialChange = $port->getLastLocalChange($doc1);
        truthy($initialChange !== null, 'initial branch change should be available');
        $doc2 = $port->applyChanges($doc2, [$initialChange]);
        $doc3 = $port->applyChanges($doc3, [$initialChange]);
        $doc3 = $port->setWithTime($doc3, 'x', 1, 0);

        for ($i = 1; $i < 20; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'n1', $i, 0);
            $doc2 = $port->setWithTime($doc2, 'n2', $i, 0);
            $change1 = $port->getLastLocalChange($doc1);
            $change2 = $port->getLastLocalChange($doc2);
            truthy($change1 !== null && $change2 !== null, 'branch peers should expose latest local changes');
            $doc1 = $port->applyChanges($doc1, [$change2]);
            $doc2 = $port->applyChanges($doc2, [$change1]);
        }

        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $thirdPeerChange = $port->getLastLocalChange($doc3);
        truthy($thirdPeerChange !== null, 'third peer concurrent change should be available');
        $doc2 = $port->applyChanges($doc2, [$thirdPeerChange]);
        $doc1 = $port->setWithTime($doc1, 'n1', 'final', 0);
        $doc2 = $port->setWithTime($doc2, 'n1', 'final', 0);

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $left = $doc1->toArray();
        $right = $doc2->toArray();
        ksort($left);
        ksort($right);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'rust branching sync should converge heads');
        sameArray($left, $right, 'rust branching sync should converge materialized state');
        same($left['n1'] ?? null, 'final', 'rust branching sync should keep the final shared n1 value');
        same($left['n2'] ?? null, 19, 'rust branching sync should retain the second branch sequence value');
        same($left['x'] ?? null, 1, 'rust branching sync should include the late third-peer change');
    },
    'rust/automerge/src/sync.rs'
);

$mapped(
    'sync protocol converges two nodes without connection reset',
    'javascript/test/sync_test.ts',
    657,
    'should sync two nodes without connection reset',
    function () use ($port): void {
        $doc1 = $port->setWithTime($port->init('aaaaaa'), 'x', 'initial @ n1', 0);
        $doc2 = $port->init('bbbbbb');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $doc1 = $port->setWithTime($doc1, 'x', 'final @ n1', 0);
        $doc2 = $port->setWithTime($doc2, 'x', 'final @ n2', 0);
        $expectedHeads = array_values(array_unique(array_merge($port->getHeads($doc1), $port->getHeads($doc2))));
        sort($expectedHeads);

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($port->getHeads($doc1), $expectedHeads, 'first peer should retain both concurrent heads after sync');
        sameArray($port->getHeads($doc2), $expectedHeads, 'second peer should retain both concurrent heads after sync');
        sameArray($doc1->toArray(), $doc2->toArray(), 'two-node sync without reset should converge materialized state');
        sameArray($port->getConflicts($doc1, 'x') ?? [], $port->getConflicts($doc2, 'x') ?? [], 'two-node sync without reset should converge conflict values');
    }
);

$mapped(
    'sync protocol converges two nodes with connection reset',
    'javascript/test/sync_test.ts',
    664,
    'should sync two nodes with connection reset',
    function () use ($port): void {
        $doc1 = $port->setWithTime($port->init('aaaaaa'), 'x', 'initial @ n1', 0);
        $doc2 = $port->init('bbbbbb');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $doc1 = $port->setWithTime($doc1, 'x', 'final @ n1', 0);
        $doc2 = $port->setWithTime($doc2, 'x', 'final @ n2', 0);
        $expectedHeads = array_values(array_unique(array_merge($port->getHeads($doc1), $port->getHeads($doc2))));
        sort($expectedHeads);

        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($port->getHeads($doc1), $expectedHeads, 'first peer should retain both concurrent heads after reset sync');
        sameArray($port->getHeads($doc2), $expectedHeads, 'second peer should retain both concurrent heads after reset sync');
        sameArray($doc1->toArray(), $doc2->toArray(), 'two-node sync with reset should converge materialized state');
        sameArray($port->getConflicts($doc1, 'x') ?? [], $port->getConflicts($doc2, 'x') ?? [], 'two-node sync with reset should converge conflict values');
    }
);

$mapped(
    'sync protocol explicitly recovers a false-positive advertised head',
    'javascript/test/sync_test.ts',
    565,
    'should handle a false-positive head',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 10; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));

        $doc1 = $port->setWithTime($doc1, 'x', 'final @ n1', 0);
        $doc2 = $port->setWithTime($doc2, 'x', 'final @ n2', 0);
        $head1 = $port->getHeads($doc1);
        $head2 = $port->getHeads($doc2);
        $expectedHeads = array_values(array_unique(array_merge($head1, $head2)));
        sort($expectedHeads);

        [$sync1, $messageFrom1] = $port->generateSyncMessage($doc1, $sync1);
        $falsePositive = $port->decodeSyncMessage($messageFrom1);
        truthy($falsePositive !== null && count($falsePositive['changes']) > 0, 'first peer should initially have the missing change available');
        $falsePositive['changes'] = [];

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $falsePositive);
        [$sync2, $requestForHead1] = $port->generateSyncMessage($doc2, $sync2);
        $decodedRequestForHead1 = $port->decodeSyncMessage($requestForHead1);
        truthy($decodedRequestForHead1 !== null, 'second peer should reply after seeing an advertised-but-missing head');
        sameArray($decodedRequestForHead1['need'], $head1, 'second peer should explicitly request the false-positive head');
        sameArray($decodedRequestForHead1['changes'], [], 'second peer should not pretend the missing false-positive change was received');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedRequestForHead1);
        [$sync1, $responseWithHead1] = $port->generateSyncMessage($doc1, $sync1);
        $decodedResponseWithHead1 = $port->decodeSyncMessage($responseWithHead1);
        truthy($decodedResponseWithHead1 !== null && count($decodedResponseWithHead1['changes']) > 0, 'first peer should answer the explicit false-positive request');
        same($decodedResponseWithHead1['changes'][0]['hash'] ?? null, $head1[0], 'first peer should send the requested false-positive head first');
        sameArray($decodedResponseWithHead1['need'], $head2, 'first peer should request the second peer concurrent head while answering');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decodedResponseWithHead1);
        [$sync2, $responseWithHead2] = $port->generateSyncMessage($doc2, $sync2);
        $decodedResponseWithHead2 = $port->decodeSyncMessage($responseWithHead2);
        truthy($decodedResponseWithHead2 !== null && count($decodedResponseWithHead2['changes']) > 0, 'second peer should answer the reciprocal explicit need');
        same($decodedResponseWithHead2['changes'][0]['hash'] ?? null, $head2[0], 'second peer should send its requested concurrent head first');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedResponseWithHead2);

        sameArray($port->getHeads($doc1), $expectedHeads, 'first peer should converge after false-positive recovery');
        sameArray($port->getHeads($doc2), $expectedHeads, 'second peer should converge after false-positive recovery');
        sameArray($doc1->toArray(), $doc2->toArray(), 'false-positive recovery should converge materialized state');
    }
);

$rustMapped(
    'rust sync handles a false-positive advertised head',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-should-handle-false-positive-head',
    'sync::tests::should_handle_false_positive_head',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 10; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));

        $doc1 = $port->setWithTime($doc1, 'x', '0 @ n1', 0);
        $doc2 = $port->setWithTime($doc2, 'x', '0 @ n2', 0);
        $head1 = $port->getHeads($doc1);
        $head2 = $port->getHeads($doc2);
        $expectedHeads = array_values(array_unique(array_merge($head1, $head2)));
        sort($expectedHeads);

        [$sync1, $messageFrom1] = $port->generateSyncMessage($doc1, $sync1);
        $falsePositive = $port->decodeSyncMessage($messageFrom1);
        truthy($falsePositive !== null && count($falsePositive['changes']) > 0, 'rust false-positive peer should initially have the missing change available');
        $falsePositive['changes'] = [];

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $falsePositive);
        [$sync2, $requestForHead1] = $port->generateSyncMessage($doc2, $sync2);
        $decodedRequestForHead1 = $port->decodeSyncMessage($requestForHead1);
        truthy($decodedRequestForHead1 !== null, 'rust false-positive receiver should reply after seeing an advertised-but-missing head');
        sameArray($decodedRequestForHead1['need'], $head1, 'rust false-positive receiver should explicitly request the advertised missing head');
        sameArray($decodedRequestForHead1['changes'], [], 'rust false-positive receiver should not send unrelated changes before request resolution');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedRequestForHead1);
        [$sync1, $responseWithHead1] = $port->generateSyncMessage($doc1, $sync1);
        $decodedResponseWithHead1 = $port->decodeSyncMessage($responseWithHead1);
        truthy($decodedResponseWithHead1 !== null && count($decodedResponseWithHead1['changes']) > 0, 'rust false-positive sender should answer the explicit head request');
        same($decodedResponseWithHead1['changes'][0]['hash'] ?? null, $head1[0], 'rust false-positive sender should send the requested head first');
        sameArray($decodedResponseWithHead1['need'], $head2, 'rust false-positive sender should request the reciprocal concurrent head');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decodedResponseWithHead1);
        [$sync2, $responseWithHead2] = $port->generateSyncMessage($doc2, $sync2);
        $decodedResponseWithHead2 = $port->decodeSyncMessage($responseWithHead2);
        truthy($decodedResponseWithHead2 !== null && count($decodedResponseWithHead2['changes']) > 0, 'rust false-positive receiver should answer the reciprocal explicit need');
        same($decodedResponseWithHead2['changes'][0]['hash'] ?? null, $head2[0], 'rust false-positive receiver should send its requested concurrent head first');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedResponseWithHead2);

        sameArray($port->getHeads($doc1), $expectedHeads, 'rust first peer should converge after false-positive recovery');
        sameArray($port->getHeads($doc2), $expectedHeads, 'rust second peer should converge after false-positive recovery');
        sameArray($doc1->toArray(), $doc2->toArray(), 'rust false-positive recovery should converge materialized state');
    },
    'rust/automerge/src/sync.rs'
);

$mapped(
    'sync protocol allows explicitly requesting a false-positive hash',
    'javascript/test/sync_test.ts',
    818,
    'should allow the false-positive hash to be explicitly requested',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 10; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));

        $doc1 = $port->setWithTime($doc1, 'x', '1 @ n1', 0);
        $doc2 = $port->setWithTime($doc2, 'x', '1 @ n2', 0);
        $head1 = $port->getHeads($doc1);
        $head2 = $port->getHeads($doc2);

        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        $decoded = $port->decodeSyncMessage($message);
        truthy($decoded !== null && count($decoded['changes']) > 0, 'first peer should have a change before simulating the false-positive Bloom filter');
        $decoded['changes'] = [];

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decoded);
        [$sync2, $message] = $port->generateSyncMessage($doc2, $sync2);
        $decoded = $port->decodeSyncMessage($message);
        truthy($decoded !== null, 'second peer should send a response after receiving an advertised missing head');
        sameArray($decoded['changes'], [], 'second peer should not send the false-positive change before it is explicitly requested');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decoded);
        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        $request = $port->decodeSyncMessage($message);
        truthy($request !== null, 'first peer should generate an explicit request after the false-positive response');
        sameArray($request['need'], $head2, 'first peer should explicitly request the false-positive hash');
        truthy(count($request['changes']) > 0, 'first peer should also answer the second peer request for its advertised head');
        same($request['changes'][0]['hash'] ?? null, $head1[0], 'first peer should send its requested head while asking for the false-positive hash');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $request);
        [$sync2, $message] = $port->generateSyncMessage($doc2, $sync2);
        $response = $port->decodeSyncMessage($message);
        truthy($response !== null && count($response['changes']) > 0, 'second peer should fulfill the explicit false-positive request');
        same($response['changes'][0]['hash'] ?? null, $head2[0], 'second peer should send the explicitly requested false-positive head first');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $response);
        $expectedHeads = array_values(array_unique(array_merge($head1, $head2)));
        sort($expectedHeads);
        sameArray($port->getHeads($doc1), $expectedHeads, 'first peer should include both heads after explicit false-positive recovery');
        sameArray($port->getHeads($doc2), $expectedHeads, 'second peer should include both heads after explicit false-positive recovery');
    }
);

$mapped(
    'sync protocol resolves a false-positive dependency without an extra request',
    'javascript/test/sync_test.ts',
    701,
    'should not require an additional request when a false-positive depends on a true-negative',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 5; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));

        $doc1 = $port->setWithTime($doc1, 'x', '1 @ n1', 0);
        $doc1 = $port->setWithTime($doc1, 'x', '2 @ n1', 0);
        $doc1 = $port->setWithTime($doc1, 'x', 'final @ n1', 0);
        $head1 = $port->getHeads($doc1);

        $doc2 = $port->setWithTime($doc2, 'x', '1 @ n2', 0);
        $n2Change1 = $port->getLastLocalChange($doc2);
        $doc2 = $port->setWithTime($doc2, 'x', '2 @ n2', 0);
        $n2Change2 = $port->getLastLocalChange($doc2);
        $doc2 = $port->setWithTime($doc2, 'x', 'final @ n2', 0);
        $n2Change3 = $port->getLastLocalChange($doc2);
        $head2 = $port->getHeads($doc2);
        truthy($n2Change1 !== null && $n2Change2 !== null && $n2Change3 !== null, 'second peer should expose all three branch changes');

        [$sync1, $messageFrom1] = $port->generateSyncMessage($doc1, $sync1);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $messageFrom1);
        [$sync2, $messageFrom2] = $port->generateSyncMessage($doc2, $sync2);
        $truncated = $port->decodeSyncMessage($messageFrom2);
        truthy($truncated !== null && count($truncated['changes']) >= 3, 'second peer response should contain the full branch before simulating a false positive');
        $truncated['changes'] = [$n2Change3];

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $truncated);
        sameArray($sync1['needHeads'], [$n2Change2['hash']], 'receiver should request the omitted dependency rather than the already advertised head');

        [$sync1, $request] = $port->generateSyncMessage($doc1, $sync1);
        $decodedRequest = $port->decodeSyncMessage($request);
        truthy($decodedRequest !== null, 'receiver should generate a dependency request after the truncated response');
        sameArray($decodedRequest['need'], [$n2Change2['hash']], 'dependency request should target the missing false-positive dependency');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decodedRequest);
        [$sync2, $response] = $port->generateSyncMessage($doc2, $sync2);
        $decodedResponse = $port->decodeSyncMessage($response);
        truthy($decodedResponse !== null && count($decodedResponse['changes']) >= 2, 'peer should answer the dependency request with the dependency chain');
        same($decodedResponse['changes'][0]['hash'] ?? null, $n2Change2['hash'], 'response should send the explicitly requested dependency first');
        same($decodedResponse['changes'][1]['hash'] ?? null, $n2Change1['hash'], 'response should include the true-negative ancestor in the same round');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedResponse);
        sameArray($sync1['needHeads'], [], 'receiving the dependency chain should clear the need list without another request');
        sameArray($port->getMissingDeps($doc1), [], 'false-positive dependency recovery should leave no missing dependencies');

        $expectedHeads = array_values(array_unique(array_merge($head1, $head2)));
        sort($expectedHeads);
        sameArray($port->getHeads($doc1), $expectedHeads, 'first peer should converge to both final branch heads');
        sameArray($port->getHeads($doc2), $expectedHeads, 'second peer should retain both final branch heads');
    }
);

$pendingMapped(
    'pending sync protocol surfaces an unresolved false-positive branch to a third node',
    'javascript/test/sync_test.ts',
    672,
    'should sync three nodes',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 10; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));

        $doc1 = $port->setWithTime($doc1, 'x', '29 @ n1', 0);
        $doc1 = $port->setWithTime($doc1, 'x', 'final @ n1', 0);
        $n1Head = $port->getHeads($doc1);

        $doc2 = $port->setWithTime($doc2, 'x', '29 @ n2', 0);
        $n2Change1 = $port->getLastLocalChange($doc2);
        $doc2 = $port->setWithTime($doc2, 'x', 'final @ n2', 0);
        $n2Change2 = $port->getLastLocalChange($doc2);
        truthy($n2Change1 !== null && $n2Change2 !== null, 'second peer should expose the two branch changes used by the pending three-node scenario');

        [$sync1, $messageFrom1] = $port->generateSyncMessage($doc1, $sync1);
        [$sync2, $messageFrom2] = $port->generateSyncMessage($doc2, $sync2);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $messageFrom1);

        $truncatedFor1 = $port->decodeSyncMessage($messageFrom2);
        truthy($truncatedFor1 !== null && count($truncatedFor1['changes']) >= 2, 'second peer should initially offer both branch changes');
        $truncatedFor1['changes'] = [$n2Change2];
        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $truncatedFor1);

        $unresolvedHeads = array_values(array_unique(array_merge($n1Head, [$n2Change2['hash']])));
        sort($unresolvedHeads);
        sameArray($port->getHeads($doc1), $unresolvedHeads, 'first peer should retain the unresolved false-positive branch head while tracking the missing dependency');
        sameArray($sync1['needHeads'], [$n2Change1['hash']], 'first peer should ask for the missing false-positive dependency');
        sameArray($port->getMissingDeps($doc1), [$n2Change1['hash']], 'first peer should expose the unresolved false-positive dependency');

        $doc3 = $port->init('fedcba98');
        $sync13 = $port->initSyncState();
        $sync31 = $port->initSyncState();

        [$sync13, $messageFrom1To3] = $port->generateSyncMessage($doc1, $sync13);
        truthy($messageFrom1To3 !== null, 'first peer should try to sync its unresolved head set to the third peer');
        [$doc3, $sync31] = $port->receiveSyncMessage($doc3, $sync31, $messageFrom1To3);
        sameArray($port->getHeads($doc3), $unresolvedHeads, 'third peer should receive the same unresolved head set');
        sameArray($port->getMissingDeps($doc3), [$n2Change1['hash']], 'third peer should retain the explicit missing dependency request');

        [$sync31, $messageFrom3To1] = $port->generateSyncMessage($doc3, $sync31);
        $decodedFrom3 = $port->decodeSyncMessage($messageFrom3To1);
        truthy($decodedFrom3 !== null, 'third peer should reply with its outstanding dependency request');
        sameArray($decodedFrom3['need'], [$n2Change1['hash']], 'third peer should ask for the false-positive dependency it cannot satisfy locally');
        sameArray($decodedFrom3['changes'], [], 'third peer should not invent a missing false-positive dependency');
    }
);

$mapped(
    'sync protocol handles chains of false-positive dependencies',
    'javascript/test/sync_test.ts',
    769,
    'should handle chains of false-positives',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 5; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));

        $doc1 = $port->setWithTime($doc1, 'x', 5, 0);
        $doc2 = $port->setWithTime($doc2, 'x', '2 @ n2', 0);
        $n2Change1 = $port->getLastLocalChange($doc2);
        $doc2 = $port->setWithTime($doc2, 'x', '141 again', 0);
        $n2Change2 = $port->getLastLocalChange($doc2);
        $doc2 = $port->setWithTime($doc2, 'x', 'final @ n2', 0);
        $n2Change3 = $port->getLastLocalChange($doc2);
        truthy($n2Change1 !== null && $n2Change2 !== null && $n2Change3 !== null, 'second peer should expose chained branch changes');

        [$sync1, $messageFrom1] = $port->generateSyncMessage($doc1, $sync1);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $messageFrom1);
        [$sync2, $messageFrom2] = $port->generateSyncMessage($doc2, $sync2);
        $truncated = $port->decodeSyncMessage($messageFrom2);
        truthy($truncated !== null && count($truncated['changes']) >= 3, 'second peer response should contain the complete chain before simulating false positives');
        $truncated['changes'] = [$n2Change3];

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $truncated);
        sameArray($sync1['needHeads'], [$n2Change2['hash']], 'first peer should request the closest omitted false-positive dependency');

        [$sync1, $request] = $port->generateSyncMessage($doc1, $sync1);
        $decodedRequest = $port->decodeSyncMessage($request);
        truthy($decodedRequest !== null, 'first peer should emit a request for the false-positive chain');
        sameArray($decodedRequest['need'], [$n2Change2['hash']], 'request should name the next missing false-positive dependency');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decodedRequest);
        [$sync2, $response] = $port->generateSyncMessage($doc2, $sync2);
        $decodedResponse = $port->decodeSyncMessage($response);
        truthy($decodedResponse !== null && count($decodedResponse['changes']) >= 2, 'second peer should answer with the requested dependency and its omitted parent');
        same($decodedResponse['changes'][0]['hash'] ?? null, $n2Change2['hash'], 'response should send the requested chained dependency first');
        same($decodedResponse['changes'][1]['hash'] ?? null, $n2Change1['hash'], 'response should include the earlier false-positive parent in the same round');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedResponse);
        $expectedHeads = array_values(array_unique(array_merge($port->getHeads($doc1), $port->getHeads($doc2))));
        sort($expectedHeads);

        sameArray($sync1['needHeads'], [], 'the chained false-positive response should clear outstanding needs');
        sameArray($port->getMissingDeps($doc1), [], 'chained false-positive recovery should leave no missing dependencies');
        sameArray($port->getHeads($doc1), $expectedHeads, 'first peer should converge to both branch heads after chain recovery');
        sameArray($port->getHeads($doc2), $expectedHeads, 'second peer should retain both branch heads after chain recovery');
    }
);

$rustMapped(
    'rust sync handles chains of false-positive dependencies',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-should-handle-chains-of-false-positives',
    'sync::tests::should_handle_chains_of_false_positives',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 10; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));

        $doc1 = $port->setWithTime($doc1, 'x', 5, 0);
        $doc2 = $port->setWithTime($doc2, 'x', '0 at 89abdef', 0);
        $n2Change1 = $port->getLastLocalChange($doc2);
        $doc2 = $port->setWithTime($doc2, 'x', '0 again', 0);
        $n2Change2 = $port->getLastLocalChange($doc2);
        $doc2 = $port->setWithTime($doc2, 'x', 'final @ 89abcdef', 0);
        $n2Change3 = $port->getLastLocalChange($doc2);
        truthy($n2Change1 !== null && $n2Change2 !== null && $n2Change3 !== null, 'rust second peer should expose chained branch changes');

        [$sync1, $messageFrom1] = $port->generateSyncMessage($doc1, $sync1);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $messageFrom1);
        [$sync2, $messageFrom2] = $port->generateSyncMessage($doc2, $sync2);
        $truncated = $port->decodeSyncMessage($messageFrom2);
        truthy($truncated !== null && count($truncated['changes']) >= 3, 'rust second peer response should contain the complete chain before simulating false positives');
        $truncated['changes'] = [$n2Change3];

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $truncated);
        sameArray($sync1['needHeads'], [$n2Change2['hash']], 'rust first peer should request the closest omitted false-positive dependency');

        [$sync1, $request] = $port->generateSyncMessage($doc1, $sync1);
        $decodedRequest = $port->decodeSyncMessage($request);
        truthy($decodedRequest !== null, 'rust first peer should emit a request for the false-positive chain');
        sameArray($decodedRequest['need'], [$n2Change2['hash']], 'rust request should name the next missing false-positive dependency');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decodedRequest);
        [$sync2, $response] = $port->generateSyncMessage($doc2, $sync2);
        $decodedResponse = $port->decodeSyncMessage($response);
        truthy($decodedResponse !== null && count($decodedResponse['changes']) >= 2, 'rust second peer should answer with the requested dependency and its omitted parent');
        same($decodedResponse['changes'][0]['hash'] ?? null, $n2Change2['hash'], 'rust response should send the requested chained dependency first');
        same($decodedResponse['changes'][1]['hash'] ?? null, $n2Change1['hash'], 'rust response should include the earlier false-positive parent in the same round');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedResponse);
        $expectedHeads = array_values(array_unique(array_merge($port->getHeads($doc1), $port->getHeads($doc2))));
        sort($expectedHeads);

        sameArray($sync1['needHeads'], [], 'rust chained false-positive response should clear outstanding needs');
        sameArray($port->getMissingDeps($doc1), [], 'rust chained false-positive recovery should leave no missing dependencies');
        sameArray($port->getHeads($doc1), $expectedHeads, 'rust first peer should converge to both branch heads after chain recovery');
        sameArray($port->getHeads($doc2), $expectedHeads, 'rust second peer should retain both branch heads after chain recovery');
    },
    'rust/automerge/src/sync.rs'
);

$mapped(
    'sync protocol suppresses duplicate in-flight changes from multiple have filters',
    'javascript/test/sync_test.ts',
    882,
    'should allow multiple Bloom filters',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $doc3 = $port->init('76543210');
        $sync13 = $port->initSyncState();
        $sync31 = $port->initSyncState();
        $sync32 = $port->initSyncState();
        $sync23 = $port->initSyncState();

        for ($i = 0; $i < 3; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2);
        [$doc1, $doc3, $sync13, $sync31] = syncDocuments($port, $doc1, $doc3, $sync13, $sync31);
        [$doc3, $doc2, $sync32, $sync23] = syncDocuments($port, $doc3, $doc2, $sync32, $sync23);

        for ($i = 0; $i < 2; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i . ' @ n1', 0);
        }

        for ($i = 0; $i < 2; ++$i) {
            $doc2 = $port->setWithTime($doc2, 'x', $i . ' @ n2', 0);
        }

        $doc1 = $port->applyChanges($doc1, $port->getAllChanges($doc2));
        $doc2 = $port->applyChanges($doc2, $port->getAllChanges($doc1));
        $doc1 = $port->setWithTime($doc1, 'x', '3 @ n1', 0);
        $doc2 = $port->setWithTime($doc2, 'x', '3 @ n2', 0);
        for ($i = 0; $i < 3; ++$i) {
            $doc3 = $port->setWithTime($doc3, 'x', $i . ' @ n3', 0);
        }

        $head1 = $port->getHeads($doc1);
        $head2 = $port->getHeads($doc2);
        $head3 = $port->getHeads($doc3);
        $expectedHeads = array_values(array_unique(array_merge($head1, $head2, $head3)));
        sort($expectedHeads);

        $sync13 = $port->decodeSyncState($port->encodeSyncState($sync13));
        $sync31 = $port->decodeSyncState($port->encodeSyncState($sync31));
        $sync23 = $port->decodeSyncState($port->encodeSyncState($sync23));
        $sync32 = $port->decodeSyncState($port->encodeSyncState($sync32));

        [$sync13, $messageFrom1] = $port->generateSyncMessage($doc1, $sync13);
        $decodedFrom1 = $port->decodeSyncMessage($messageFrom1);
        truthy($decodedFrom1 !== null && count($decodedFrom1['have'][0]['hashes'] ?? []) > 0, 'first peer should advertise deterministic in-flight hashes in its have filter');

        [$doc3, $sync31] = $port->receiveSyncMessage($doc3, $sync31, $decodedFrom1);
        [$sync31, $messageFrom3To1] = $port->generateSyncMessage($doc3, $sync31);
        $decodedFrom3To1 = $port->decodeSyncMessage($messageFrom3To1);
        truthy($decodedFrom3To1 !== null && count($decodedFrom3To1['changes']) > 0, 'third peer should send its branch to the first peer');
        [$doc1, $sync13] = $port->receiveSyncMessage($doc1, $sync13, $decodedFrom3To1);

        [$sync32, $messageFrom3To2] = $port->generateSyncMessage($doc3, $sync32);
        $modifiedFor2 = $port->decodeSyncMessage($messageFrom3To2);
        truthy($modifiedFor2 !== null, 'third peer should generate a sync message for the second peer');
        $modifiedFor2['have'][] = $decodedFrom1['have'][0];
        same(count($modifiedFor2['have']), 2, 'modified sync message should carry two have filters');

        [$doc2, $sync23] = $port->receiveSyncMessage($doc2, $sync23, $modifiedFor2);
        [$sync23, $messageFrom2] = $port->generateSyncMessage($doc2, $sync23);
        $decodedFrom2 = $port->decodeSyncMessage($messageFrom2);
        truthy($decodedFrom2 !== null, 'second peer should reply after receiving multiple have filters');
        same(count($decodedFrom2['changes']), 1, 'second peer should suppress changes already in flight from the first peer');
        same($decodedFrom2['changes'][0]['hash'] ?? null, $head2[0], 'second peer should send only its final branch head');

        [$doc3, $sync32] = $port->receiveSyncMessage($doc3, $sync32, $decodedFrom2);
        [$sync13, $messageFrom1] = $port->generateSyncMessage($doc1, $sync13);
        if ($messageFrom1 !== null) {
            [$doc3, $sync31] = $port->receiveSyncMessage($doc3, $sync31, $messageFrom1);
        }

        sameArray($port->getHeads($doc3), $expectedHeads, 'third peer should converge to all concurrent branch heads');
    }
);

$rustMapped(
    'rust sync v1 messages can drive a v2 receiver',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-v1-compat-test-sync-from-v1-to-v2',
    'sync::v1_compat_test::sync_from_v1_to_v2',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'foo', 'bar');
        $doc2 = $port->set($port->init('bbbbbb'), 'baz', 'quux');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        $iterations = 0;
        do {
            [$sync1, $fromV1] = $port->generateSyncMessageV1($doc1, $sync1);
            if ($fromV1 !== null) {
                same(array_key_exists('readOnly', $fromV1), false, 'v1 sync message should not expose v2 readOnly capability flags');
                same(array_key_exists('syncReset', $fromV1), false, 'v1 sync message should not expose v2 syncReset capability flags');
                same(array_key_exists('hashes', $fromV1['have'][0] ?? []), false, 'v1 sync message should not expose v2 deterministic have hashes');
                [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $fromV1);
            }

            [$sync2, $fromV2] = $port->generateSyncMessage($doc2, $sync2);
            if ($fromV2 !== null) {
                $v1Decoded = $port->syncMessageToV1($fromV2);
                truthy($v1Decoded !== null, 'v2 response should be decodable by the v1 compatibility parser');
                [$doc1, $sync1] = $port->receiveSyncMessageV1($doc1, $sync1, $v1Decoded);
            }

            if (++$iterations > 10) {
                throw new RuntimeException('v1-to-v2 compatibility sync did not converge');
            }
        } while ($fromV1 !== null || $fromV2 !== null);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'v1-to-v2 compatibility sync should converge heads');
        $materialized1 = $doc1->toArray();
        $materialized2 = $doc2->toArray();
        ksort($materialized1);
        ksort($materialized2);
        sameArray($materialized1, $materialized2, 'v1-to-v2 compatibility sync should converge materialized state');
    },
    'rust/automerge/src/sync/v1_compat_test/mod.rs'
);

$rustMapped(
    'rust sync v2 messages can drive a v1 receiver',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-v1-compat-test-sync-from-v2-to-v1',
    'sync::v1_compat_test::sync_from_v2_to_v1',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'foo', 'bar');
        $doc2 = $port->set($port->init('bbbbbb'), 'baz', 'quux');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        $iterations = 0;
        do {
            [$sync2, $fromV2] = $port->generateSyncMessage($doc2, $sync2);
            if ($fromV2 !== null) {
                $v1Decoded = $port->syncMessageToV1($fromV2);
                truthy($v1Decoded !== null, 'v2 starter message should be decodable by the v1 compatibility parser');
                same(array_key_exists('readOnly', $v1Decoded), false, 'v1-decoded sync message should not expose v2 readOnly capability flags');
                same(array_key_exists('syncReset', $v1Decoded), false, 'v1-decoded sync message should not expose v2 syncReset capability flags');
                same(array_key_exists('hashes', $v1Decoded['have'][0] ?? []), false, 'v1-decoded sync message should not expose v2 deterministic have hashes');
                [$doc1, $sync1] = $port->receiveSyncMessageV1($doc1, $sync1, $v1Decoded);
            }

            [$sync1, $fromV1] = $port->generateSyncMessageV1($doc1, $sync1);
            if ($fromV1 !== null) {
                [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $fromV1);
            }

            if (++$iterations > 10) {
                throw new RuntimeException('v2-to-v1 compatibility sync did not converge');
            }
        } while ($fromV2 !== null || $fromV1 !== null);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'v2-to-v1 compatibility sync should converge heads');
        $materialized1 = $doc1->toArray();
        $materialized2 = $doc2->toArray();
        ksort($materialized1);
        ksort($materialized2);
        sameArray($materialized1, $materialized2, 'v2-to-v1 compatibility sync should converge materialized state');
    },
    'rust/automerge/src/sync/v1_compat_test/mod.rs'
);

$rustMapped(
    'rust sync v1 to v2 accepts a large compressed-change payload',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-v1-compat-test-sync-v1-to-v2-with-compressed-change',
    'sync::v1_compat_test::sync_v1_to_v2_with_compressed_change',
    function () use ($port): void {
        $docV1 = $port->init('aaaaaa');
        $docV2 = $port->set($port->init('bbbbbb'), 'list', []);
        $docV2 = $port->insertListElements($docV2, 'list', 0, range(0, 999));
        $syncV1 = $port->initSyncState();
        $syncV2 = $port->initSyncState();

        $iterations = 0;
        do {
            [$syncV1, $fromV1] = $port->generateSyncMessageV1($docV1, $syncV1);
            if ($fromV1 !== null) {
                [$docV2, $syncV2] = $port->receiveSyncMessage($docV2, $syncV2, $fromV1);
            }

            [$syncV2, $fromV2] = $port->generateSyncMessage($docV2, $syncV2);
            if ($fromV2 !== null) {
                $v1Decoded = $port->syncMessageToV1($fromV2);
                truthy($v1Decoded !== null, 'large v2 response should be decodable by the v1 compatibility parser');
                same(array_key_exists('hashes', $v1Decoded['have'][0] ?? []), false, 'large v1-compatible response should omit v2 deterministic have hashes');
                [$docV1, $syncV1] = $port->receiveSyncMessageV1($docV1, $syncV1, $v1Decoded);
            }

            if (++$iterations > 10) {
                throw new RuntimeException('large v1-to-v2 compatibility sync did not converge');
            }
        } while ($fromV1 !== null || $fromV2 !== null);

        sameArray($port->getHeads($docV1), $port->getHeads($docV2), 'large v1-to-v2 compatibility sync should converge heads');
        same(count($docV1->toArray()['list'] ?? []), 1000, 'v1 peer should materialize the full large list payload');
        sameArray($docV1->toArray(), $docV2->toArray(), 'v1 peer should materialize the same large payload as the v2 peer');

        $docV2 = $port->set($docV2, 'foo', 'bar');
        $docV1 = $port->set($docV1, 'baz', 'quux');
        same($docV2->toArray()['foo'] ?? null, 'bar', 'v2 peer should remain writable after large compatibility sync');
        same($docV1->toArray()['baz'] ?? null, 'quux', 'v1 peer should remain writable after large compatibility sync');
    },
    'rust/automerge/src/sync/v1_compat_test/mod.rs'
);

$mapped(
    'sync protocol does not generate messages once synced',
    'javascript/test/sync_test.ts',
    175,
    'should not generate messages once synced',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');

        for ($i = 0; $i < 5; ++$i) {
            $doc1 = $port->set($doc1, 'x', $i);
            $doc2 = $port->set($doc2, 'y', $i);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2);

        sameArray($doc1->toArray(), ['x' => 4, 'y' => 4], 'first peer should converge to both peer key updates');
        sameArray($doc2->toArray(), ['y' => 4, 'x' => 4], 'second peer should converge to both peer key updates');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'synced peers should converge heads');

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);
        same($message1, null, 'first peer should have no sync message after convergence');
        same($message2, null, 'second peer should have no sync message after convergence');
    }
);

$mapped(
    'sync protocol works regardless of which peer initiates later exchange',
    'javascript/test/sync_test.ts',
    327,
    'should work regardless of who initiates the exchange',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->init('bbbbbb');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 5; ++$i) {
            $doc1 = $port->set($doc1, 'x', $i);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), $doc2->toArray(), 'initial exchange should synchronize the second peer');

        for ($i = 5; $i < 10; ++$i) {
            $doc1 = $port->set($doc1, 'x', $i);
        }

        truthy($doc1->toArray() !== $doc2->toArray(), 'later exchange should start with one peer ahead');
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), $doc2->toArray(), 'later exchange should converge regardless of sync initiator');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'later exchange should converge heads');
    }
);

$mapped(
    'sync protocol preserves independent diverged peer changes',
    'javascript/test/sync_test.ts',
    219,
    'should allow simultaneous messages during synchronization',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');

        for ($i = 0; $i < 5; ++$i) {
            $doc1 = $port->set($doc1, 'x', $i);
            $doc2 = $port->set($doc2, 'y', $i);
        }

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $port->initSyncState());
        [$sync2, $message2] = $port->generateSyncMessage($doc2, $port->initSyncState());
        truthy($message1 !== null && $message2 !== null, 'both diverged peers should emit initial sync messages');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $message2);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message1);

        sameArray($doc1->toArray(), ['x' => 4, 'y' => 4], 'first peer should keep its local value and apply the remote independent key');
        sameArray($doc2->toArray(), ['y' => 4, 'x' => 4], 'second peer should keep its local value and apply the remote independent key');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'simultaneous independent exchange should converge heads');
    }
);

$rustMapped(
    'rust sync allows simultaneous independent messages and acknowledgements',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-should-allow-simultaneous-messages-during-synchronisation',
    'sync::tests::should_allow_simultaneous_messages_during_synchronisation',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 5; ++$i) {
            $doc1 = $port->set($doc1, 'x', $i);
            $doc2 = $port->set($doc2, 'y', $i);
        }
        $doc1HeadsBeforeExchange = $port->getHeads($doc1);
        $doc2HeadsBeforeExchange = $port->getHeads($doc2);

        [$sync1, $msg1to2] = $port->generateSyncMessage($doc1, $sync1);
        [$sync2, $msg2to1] = $port->generateSyncMessage($doc2, $sync2);
        truthy($msg1to2 !== null && $msg2to1 !== null, 'both peers should send initial simultaneous sync messages');
        truthy(count($msg1to2['changes'] ?? []) > 0, 'native sync should include first peer changes in the initial message');
        truthy(count($msg2to1['changes'] ?? []) > 0, 'native sync should include second peer changes in the initial message');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $msg2to1);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $msg1to2);
        sameArray($doc1->toArray(), ['x' => 4, 'y' => 4], 'first peer should apply the simultaneous remote changes');
        sameArray($doc2->toArray(), ['y' => 4, 'x' => 4], 'second peer should apply the simultaneous remote changes');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'simultaneous exchange should converge heads');

        [$sync1, $ack1to2] = $port->generateSyncMessage($doc1, $sync1);
        [$sync2, $ack2to1] = $port->generateSyncMessage($doc2, $sync2);
        truthy($ack1to2 !== null && $ack2to1 !== null, 'both peers should send acknowledgement messages after simultaneous receive');
        sameArray($ack1to2['have'][0]['lastSync'] ?? [], $doc2HeadsBeforeExchange, 'first acknowledgement should report the second peer heads it received');
        sameArray($ack2to1['have'][0]['lastSync'] ?? [], $doc1HeadsBeforeExchange, 'second acknowledgement should report the first peer heads it received');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $ack2to1);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $ack1to2);
        same($port->generateSyncMessage($doc1, $sync1)[1], null, 'first peer should be quiet after acknowledgement exchange');
        same($port->generateSyncMessage($doc2, $sync2)[1], null, 'second peer should be quiet after acknowledgement exchange');

        $sharedHeads = $port->getHeads($doc1);
        $doc1 = $port->set($doc1, 'x', 5);
        [$sync1, $later] = $port->generateSyncMessage($doc1, $sync1);
        truthy($later !== null, 'a later local change should produce a sync message');
        sameArray($later['have'][0]['lastSync'] ?? [], $sharedHeads, 'later sync should advertise the previous shared heads');
        truthy(count($later['changes'] ?? []) > 0, 'later sync should include the new local change');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $later);
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge after the later change');
        same($doc2->toArray()['x'] ?? null, 5, 'second peer should receive the later first peer value');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync in-flight acknowledgement does not hide a later local change',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-in-flight-logic-should-not-sabotage-concurrent-changes',
    'sync::tests::in_flight_logic_should_not_sabotage_concurrent_changes',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'initial empty documents should synchronize heads');

        $doc2 = $port->set($doc2, 'x', 0);
        [$sync2, $doc2ToDoc1] = $port->generateSyncMessage($doc2, $sync2);
        truthy($doc2ToDoc1 !== null, 'second peer should send its local change');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $doc2ToDoc1);
        same($doc1->toArray()['x'] ?? null, 0, 'first peer should receive the second peer value before acknowledging');

        $doc1 = $port->set($doc1, 'x', 1);
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'in-flight sync should converge heads after the later local change');
        same($doc1->toArray()['x'] ?? null, 1, 'first peer should keep its causally later value');
        same($doc2->toArray()['x'] ?? null, 1, 'second peer should receive the causally later value');
        same($port->generateSyncMessage($doc1, $sync1)[1], null, 'first peer should not be quietly divergent after convergence');
        same($port->generateSyncMessage($doc2, $sync2)[1], null, 'second peer should not be quietly divergent after convergence');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync sends whole document when first message has no heads',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-if-first-message-has-no-heads-and-supports-v2-message-send-whole-doc',
    'sync::tests::if_first_message_has_no_heads_and_supports_v2_message_send_whole_doc',
    function () use ($port): void {
        $emptyPeer = $port->init('abc123');
        $dataPeer = $port->set($port->init('def456'), 'foo', 'bar');
        $emptyState = $port->initSyncState();
        $dataState = $port->initSyncState();

        [$emptyState, $outgoing] = $port->generateSyncMessage($emptyPeer, $emptyState);
        $decodedOutgoing = $port->decodeSyncMessage($outgoing);
        truthy($decodedOutgoing !== null, 'empty first peer should produce a first-round sync message');
        sameArray($decodedOutgoing['heads'], [], 'empty first peer message should advertise no heads');
        sameArray($decodedOutgoing['changes'], [], 'empty first peer should not send changes');

        [$dataPeer, $dataState] = $port->receiveSyncMessage($dataPeer, $dataState, $outgoing);
        [$dataState, $response] = $port->generateSyncMessage($dataPeer, $dataState);
        $decodedResponse = $port->decodeSyncMessage($response);
        truthy($decodedResponse !== null, 'data peer should respond to an empty-head first message');
        truthy(count($decodedResponse['changes']) > 0, 'data peer should send its document changes in response');

        [$emptyPeer, $emptyState] = $port->receiveSyncMessage($emptyPeer, $emptyState, $response);
        sameArray($emptyPeer->toArray(), ['foo' => 'bar'], 'empty peer should materialize the whole document response');
        sameArray($port->getHeads($emptyPeer), $port->getHeads($dataPeer), 'whole-document response should converge heads');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync does not reply when empty peers have no data after first round',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-should-not-reply-if-we-have-no-data-after-first-round',
    'sync::tests::should_not_reply_if_we_have_no_data_after_first_round',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$sync1, $firstMessage] = $port->generateSyncMessage($doc1, $sync1);
        truthy($firstMessage !== null, 'first empty peer should still send a first-round state message');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $firstMessage);
        [$sync2, $response] = $port->generateSyncMessage($doc2, $sync2);
        truthy($response !== null, 'second empty peer should acknowledge the first round');

        [$sync1, $lateFirstMessage] = $port->generateSyncMessage($doc1, $sync1);
        [$sync2, $lateSecondMessage] = $port->generateSyncMessage($doc2, $sync2);

        same($lateFirstMessage, null, 'first empty peer should not send another message without data');
        same($lateSecondMessage, null, 'second empty peer should not send another message without data');
        sameArray($doc1->toArray(), [], 'first empty peer should remain empty');
        sameArray($doc2->toArray(), [], 'second empty peer should remain empty');
    },
    'rust/automerge/src/sync.rs'
);

$mapped(
    'sync protocol reports when peers have our changes',
    'javascript/test/sync_test.ts',
    1096,
    'should report whether the other end has our changes',
    function () use ($port): void {
        $left = $port->from(['foo' => 'bar'], 'aaaaaa');
        $right = $port->from(['baz' => 'qux'], 'bbbbbb');
        $leftToRight = $port->initSyncState();
        $rightToLeft = $port->initSyncState();
        $iterations = 0;

        while (
            ! $port->hasOurChanges($left, $leftToRight)
            && ! $port->hasOurChanges($right, $rightToLeft)
        ) {
            $quiet = true;

            [$leftToRight, $message] = $port->generateSyncMessage($left, $leftToRight);
            if ($message !== null) {
                $quiet = false;
                [$right, $rightToLeft] = $port->receiveSyncMessage($right, $rightToLeft, $message);
            }

            [$rightToLeft, $message] = $port->generateSyncMessage($right, $rightToLeft);
            if ($message !== null) {
                $quiet = false;
                [$left, $leftToRight] = $port->receiveSyncMessage($left, $leftToRight, $message);
            }

            if ($quiet) {
                throw new RuntimeException('no sync message generated but the sync states say we are not done');
            }

            if (++$iterations > 10) {
                throw new RuntimeException('sync acknowledgement did not converge within 10 iterations');
            }
        }

        truthy($port->hasOurChanges($left, $leftToRight), 'left peer should report its changes acknowledged');
        truthy($port->hasOurChanges($right, $rightToLeft), 'right peer should report its changes acknowledged');
    }
);

$mapped(
    'sync protocol continues sending unacknowledged local changes',
    'javascript/test/sync_test.ts',
    299,
    'should assume sent changes were recieved until we hear otherwise',
    function () use ($port): void {
        $n1 = $port->set($port->init('01234567'), 'items', []);
        $n2 = $port->init('89abcdef');
        [$n1, $n2, $s1] = syncDocuments($port, $n1, $n2);

        foreach (['x', 'y', 'z'] as $value) {
            $n1 = $port->pushList($n1, 'items', [$value]);
            [$s1, $message] = $port->generateSyncMessage($n1, $s1);
            if ($message !== null) {
                $decoded = $port->decodeSyncMessage($message);
                truthy($decoded !== null && count($decoded['changes']) > 0, 'unacknowledged local edits should remain in sync messages');
            }
        }
    }
);

$mapped(
    'sync read-only state does not apply incoming changes',
    'javascript/test/sync_test.ts',
    1139,
    'should not apply incoming changes when read-only',
    function () use ($port): void {
        $doc1 = $port->setWithTime($port->init('aaaaaa'), 'from1', 'hello', 0);
        $doc2 = $port->setWithTime($port->init('bbbbbb'), 'from2', 'world', 0);

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $port->initSyncState(['readOnly' => true]));

        sameArray($doc2->toArray(), ['from2' => 'world', 'from1' => 'hello'], 'read-write peer should receive read-only peer changes');
        sameArray($doc1->toArray(), ['from1' => 'hello'], 'read-only peer should ignore incoming peer changes');
    }
);

$mapped(
    'sync read-only state is reported to the peer',
    'javascript/test/sync_test.ts',
    1155,
    'should discover peer read-only status',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->init('bbbbbb');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        truthy($message !== null, 'read-only peer should generate an initial sync message');
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message);

        truthy($sync2['peerReadOnly'], 'receiving peer should discover read-only status');
        truthy($sync1['readOnly'], 'sending peer should retain read-only status');
    }
);

$mapped(
    'sync read-only state can switch back to read-write',
    'javascript/test/sync_test.ts',
    1172,
    'should allow switching from read-only to read-write',
    function () use ($port): void {
        $doc1 = $port->setWithTime($port->init('aaaaaa'), 'from1', 'hello', 0);
        $doc2 = $port->setWithTime($port->init('bbbbbb'), 'from2', 'world', 0);
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), ['from1' => 'hello'], 'read-only peer should not receive remote changes during the first sync');
        sameArray($doc2->toArray(), ['from2' => 'world', 'from1' => 'hello'], 'read-write peer should receive read-only peer changes');

        $sync1 = $port->decodeSyncState($port->encodeSyncState($sync1));
        $sync2 = $port->decodeSyncState($port->encodeSyncState($sync2));
        $sync1['readOnly'] = false;

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), ['from1' => 'hello', 'from2' => 'world'], 'peer should receive remote changes after switching back to read-write');
        sameArray($doc2->toArray(), ['from2' => 'world', 'from1' => 'hello'], 'other peer should preserve both changes after read-only transition');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'read-only transition sync should converge heads');
    }
);

$rustMapped(
    'rust read-only sync does not apply incoming changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-read-only-sync-does-not-apply-incoming-changes',
    'sync::tests::read_only_sync_does_not_apply_incoming_changes',
    function () use ($port): void {
        $doc1 = $port->setWithTime($port->init('aaaaaa'), 'from_doc1', 'hello', 0);
        $doc2 = $port->setWithTime($port->init('bbbbbb'), 'from_doc2', 'world', 0);

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $port->initSyncState(['readOnly' => true]));

        sameArray($doc1->toArray(), ['from_doc1' => 'hello'], 'read-only peer should ignore incoming changes');
        sameArray($doc2->toArray(), ['from_doc2' => 'world', 'from_doc1' => 'hello'], 'read-write peer should apply read-only peer changes');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync peer discovers remote read-only status',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-peer-discovers-remote-read-only-status',
    'sync::tests::peer_discovers_remote_read_only_status',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->init('bbbbbb');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        truthy($message !== null, 'read-only peer should advertise its state in an initial message');
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message);

        sameArray($doc2->toArray(), [], 'empty read-write peer should remain empty after read-only hello');
        truthy($sync2['peerReadOnly'], 'receiving peer should discover read-only status');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only empty peer syncs with data peer without applying data',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-read-only-empty-peer-syncs-with-data-peer',
    'sync::tests::read_only_empty_peer_syncs_with_data_peer',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->set($port->init('bbbbbb'), 'key', 'value');

        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $port->initSyncState(['readOnly' => true]));

        sameArray($doc1->toArray(), [], 'empty read-only peer should remain empty');
        sameArray($port->getHeads($doc1), [], 'empty read-only peer should keep empty heads');
        sameArray($doc2->toArray(), ['key' => 'value'], 'data peer should remain unchanged');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers ignore each other changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-peers-read-only',
    'sync::tests::both_peers_read_only',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_doc1', 'hello');
        $doc2 = $port->set($port->init('def456'), 'from_doc2', 'world');
        $doc1Heads = $port->getHeads($doc1);
        $doc2Heads = $port->getHeads($doc2);

        [$doc1, $doc2] = syncDocuments(
            $port,
            $doc1,
            $doc2,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState(['readOnly' => true])
        );

        sameArray($doc1->toArray(), ['from_doc1' => 'hello'], 'first read-only peer should ignore second peer changes');
        sameArray($doc2->toArray(), ['from_doc2' => 'world'], 'second read-only peer should ignore first peer changes');
        sameArray($port->getHeads($doc1), $doc1Heads, 'first read-only peer heads should remain unchanged');
        sameArray($port->getHeads($doc2), $doc2Heads, 'second read-only peer heads should remain unchanged');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers converge to no further messages',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-peers-read-only-converges-to-none',
    'sync::tests::both_peers_read_only_converges_to_none',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_doc1', 'hello');
        $doc2 = $port->set($port->init('def456'), 'from_doc2', 'world');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState(['readOnly' => true]);

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);

        same($message1, null, 'first read-only peer should stop sending after convergence');
        same($message2, null, 'second read-only peer should stop sending after convergence');
        truthy($sync1['peerReadOnly'], 'first read-only peer should discover the second is read-only');
        truthy($sync2['peerReadOnly'], 'second read-only peer should discover the first is read-only');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers exchange updated heads for one local writer',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-read-only-one-makes-local-changes',
    'sync::tests::both_read_only_one_makes_local_changes',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState(['readOnly' => true]);

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $doc1 = $port->set($doc1, 'key', 'value1');
        $doc1Heads = $port->getHeads($doc1);
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($sync2['receivedHeads'] ?? [], $doc1Heads, 'second read-only peer should learn first peer updated heads');
        sameArray($doc2->toArray(), [], 'second read-only peer should not apply first peer local changes');

        $doc1 = $port->set($doc1, 'key', 'value2');
        $doc1Heads = $port->getHeads($doc1);
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($sync2['receivedHeads'] ?? [], $doc1Heads, 'second read-only peer should learn the later first peer heads');
        sameArray($doc2->toArray(), [], 'second read-only peer should still not apply first peer local changes');
        same($port->generateSyncMessage($doc1, $sync1)[1], null, 'first read-only peer should be quiet after head exchange');
        same($port->generateSyncMessage($doc2, $sync2)[1], null, 'second read-only peer should be quiet after head exchange');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers exchange updated heads for both local writers',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-read-only-both-make-local-changes',
    'sync::tests::both_read_only_both_make_local_changes',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState(['readOnly' => true]);

        for ($round = 0; $round < 5; ++$round) {
            $doc1 = $port->set($doc1, 'doc1_counter', $round);
            $doc2 = $port->set($doc2, 'doc2_counter', $round);
            $doc1Heads = $port->getHeads($doc1);
            $doc2Heads = $port->getHeads($doc2);

            [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

            sameArray($sync1['receivedHeads'] ?? [], $doc2Heads, 'first read-only peer should learn second peer heads');
            sameArray($sync2['receivedHeads'] ?? [], $doc1Heads, 'second read-only peer should learn first peer heads');
            sameArray($doc1->toArray(), ['doc1_counter' => $round], 'first read-only peer should not apply second peer data');
            sameArray($doc2->toArray(), ['doc2_counter' => $round], 'second read-only peer should not apply first peer data');
            same($port->generateSyncMessage($doc1, $sync1)[1], null, 'first read-only peer should be quiet after each round');
            same($port->generateSyncMessage($doc2, $sync2)[1], null, 'second read-only peer should be quiet after each round');
        }
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers converge after simultaneous local changes during sync',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-read-only-simultaneous-changes-during-sync',
    'sync::tests::both_read_only_simultaneous_changes_during_sync',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState(['readOnly' => true]);

        $doc1 = $port->set($doc1, 'x', 1);
        $doc2 = $port->set($doc2, 'y', 2);
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $doc1 = $port->set($doc1, 'x', 3);
        $doc2 = $port->set($doc2, 'y', 4);
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        same($port->generateSyncMessage($doc1, $sync1)[1], null, 'first read-only peer should have no further message after simultaneous local changes');
        same($port->generateSyncMessage($doc2, $sync2)[1], null, 'second read-only peer should have no further message after simultaneous local changes');
        sameArray($doc1->toArray(), ['x' => 3], 'first read-only peer should not apply second peer data');
        sameArray($doc2->toArray(), ['y' => 4], 'second read-only peer should not apply first peer data');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only publisher sends new local changes between sync rounds',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-read-only-peer-new-changes-between-sync-rounds',
    'sync::tests::read_only_peer_new_changes_between_sync_rounds',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'round1', 'from_doc1');
        $doc2 = $port->set($port->init('def456'), 'round1', 'from_doc2');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        truthy($port->getConflicts($doc2, 'round1') !== null, 'read-write peer should receive the read-only peer round1 conflict');

        $doc1 = $port->set($doc1, 'round2', 'new_from_doc1');
        $doc2 = $port->set($doc2, 'round2', 'new_from_doc2');
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $doc2Round2Values = array_values($port->getConflicts($doc2, 'round2') ?? []);
        sort($doc2Round2Values);
        sameArray($doc2Round2Values, ['new_from_doc1', 'new_from_doc2'], 'read-write peer should retain both round2 conflict values');
        sameArray($doc1->toArray(), ['round1' => 'from_doc1', 'round2' => 'new_from_doc1'], 'read-only peer should not apply read-write peer changes');
        sameArray(array_values($port->getConflicts($doc1, 'round2') ?? []), [], 'read-only peer should not record a conflict for ignored remote round2 data');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only peer can publish a local change made during sync',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-read-only-peer-concurrent-changes-during-sync',
    'sync::tests::read_only_peer_concurrent_changes_during_sync',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $doc2 = $port->set($doc2, 'x', 0);
        [$sync2, $message] = $port->generateSyncMessage($doc2, $sync2);
        truthy($message !== null, 'read-write peer should send its new local change');
        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $message);
        sameArray($doc1->toArray(), [], 'read-only peer should ignore the in-flight read-write change');

        $doc1 = $port->set($doc1, 'y', 1);
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($doc1->toArray(), ['y' => 1], 'read-only peer should keep only its local change');
        sameArray($doc2->toArray(), ['x' => 0, 'y' => 1], 'read-write peer should receive the read-only peer concurrent local change');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync omits changes when peer is known read-only',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-changes-not-sent-to-read-only-peer',
    'sync::tests::changes_not_sent_to_read_only_peer',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->set($port->init('def456'), 'from_b', 'world');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        truthy($message1 !== null, 'read-only peer should advertise its mode');
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message1);
        truthy($sync2['peerReadOnly'], 'read-write peer should learn the remote peer is read-only');

        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);
        $decoded = $port->decodeSyncMessage($message2);
        truthy($decoded !== null, 'read-write peer should still acknowledge heads to the read-only peer');
        sameArray($decoded['changes'], [], 'read-write peer should not send changes to a known read-only peer');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $message2);
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), [], 'read-only peer should not receive read-write peer data');

        $sync1['readOnly'] = false;
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($doc1->toArray(), ['from_b' => 'world'], 'former read-only peer should receive changes after switching to read-write');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge heads after read-only peer switches to read-write');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync advertises read-only after mode change with an in-flight message',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-generate-message-after-set-read-only-even-with-in-flight',
    'sync::tests::generate_message_after_set_read_only_even_with_in_flight',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_a', 'hello');
        $doc2 = $port->set($port->init('def456'), 'from_b', 'world');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $doc2 = $port->set($doc2, 'new_from_b', 'secret');
        [$sync2, $message] = $port->generateSyncMessage($doc2, $sync2);
        truthy($message !== null, 'second peer should produce an in-flight update');
        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $message);

        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        truthy($message !== null, 'first peer should generate an acknowledgement before changing mode');

        $sync1['readOnly'] = true;
        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        $decoded = $port->decodeSyncMessage($message);

        truthy($decoded !== null, 'mode change to read-only should force a control message');
        truthy($decoded['readOnly'], 'mode-change control message should advertise read-only');
        same($decoded['syncReset'], false, 'switching to read-only should not request a sync reset');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync advertises read-write after read-only mode change with an in-flight message',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-generate-message-after-set-read-only-false-even-with-in-flight',
    'sync::tests::generate_message_after_set_read_only_false_even_with_in_flight',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_a', 'hello');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        truthy($message !== null, 'read-only peer should have an in-flight initial message before switching mode');
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message);
        truthy($sync2['peerReadOnly'], 'read-write peer should know the remote peer is read-only before the mode switch');
        $doc2 = $port->set($doc2, 'from_b', 'world');

        $sync1['readOnly'] = false;
        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        $decoded = $port->decodeSyncMessage($message);

        truthy($decoded !== null, 'mode change to read-write should force a control message');
        same($decoded['readOnly'], false, 'mode-change control message should clear read-only');
        truthy($decoded['syncReset'], 'switching to read-write should request resend of previously ignored changes');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message);
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($doc1->toArray(), ['from_a' => 'hello', 'from_b' => 'world'], 'former read-only peer should receive changes after switching to read-write');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge heads after read-write mode change');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust sync switches read-only peer to read-write with old-peer empty-head fallback',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-switch-to-read-write-with-old-peer',
    'sync::tests::switch_to_read_write_with_old_peer',
    function () use ($port): void {
        $docA = $port->set($port->init('abc123'), 'from_a', 'hello');
        $docB = $port->set($port->init('def456'), 'from_b', 'world');
        $syncA = $port->initSyncState(['readOnly' => true]);
        $syncB = $port->initSyncState();

        [$docA, $docB, $syncA, $syncB] = syncDocuments($port, $docA, $docB, $syncA, $syncB);
        sameArray($docA->toArray(), ['from_a' => 'hello'], 'read-only peer should ignore remote data before switching modes');
        same($docB->toArray()['from_a'] ?? null, 'hello', 'remote peer should receive read-only publisher data');
        same($docB->toArray()['from_b'] ?? null, 'world', 'remote peer should retain its local data before mode switch');

        $syncA['readOnly'] = false;
        $syncA['theirCapabilities'] = null;
        [$syncA, $message] = $port->generateSyncMessage($docA, $syncA);
        $decoded = $port->decodeSyncMessage($message);
        truthy($decoded !== null, 'read-write switch for old peer should produce a control message');
        sameArray($decoded['heads'], [], 'old peer fallback should send empty heads instead of a sync-reset flag');
        same($decoded['syncReset'], false, 'old peer fallback should not use the sync-reset capability flag');
        same($decoded['readOnly'], false, 'old peer fallback should advertise the new read-write mode');

        [$docB, $syncB] = $port->receiveSyncMessage($docB, $syncB, $message);
        [$docA, $docB, $syncA, $syncB] = syncDocuments($port, $docA, $docB, $syncA, $syncB);

        sameArray($docA->toArray(), ['from_a' => 'hello', 'from_b' => 'world'], 'former read-only peer should receive old-peer data after fallback reset');
        sameArray($docB->toArray(), ['from_b' => 'world', 'from_a' => 'hello'], 'old peer should retain both document branches after fallback reset');
        sameArray($port->getHeads($docA), $port->getHeads($docB), 'peers should converge heads after old-peer read-write fallback');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers exchange changes after simultaneous read-write toggle',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-toggle-read-only-to-read-write-simultaneously',
    'sync::tests::both_toggle_read_only_to_read_write_simultaneously',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_doc1', 'hello');
        $doc2 = $port->set($port->init('def456'), 'from_doc2', 'world');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState(['readOnly' => true]);

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($doc1->toArray(), ['from_doc1' => 'hello'], 'first peer should ignore second peer while read-only');
        sameArray($doc2->toArray(), ['from_doc2' => 'world'], 'second peer should ignore first peer while read-only');

        $sync1['readOnly'] = false;
        $sync2['readOnly'] = false;
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($doc1->toArray(), ['from_doc1' => 'hello', 'from_doc2' => 'world'], 'first peer should receive second peer after read-write toggle');
        sameArray($doc2->toArray(), ['from_doc2' => 'world', 'from_doc1' => 'hello'], 'second peer should receive first peer after read-write toggle');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'both toggled peers should converge heads');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers exchange original and new changes after read-write toggle',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-toggle-read-only-to-read-write-with-new-changes',
    'sync::tests::both_toggle_read_only_to_read_write_with_new_changes',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'original_1', 'v1');
        $doc2 = $port->set($port->init('def456'), 'original_2', 'v2');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState(['readOnly' => true]);

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        $sync1['readOnly'] = false;
        $sync2['readOnly'] = false;
        $doc1 = $port->set($doc1, 'new_1', 'after_switch');
        $doc2 = $port->set($doc2, 'new_2', 'after_switch');
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($doc1->toArray(), ['original_1' => 'v1', 'new_1' => 'after_switch', 'original_2' => 'v2', 'new_2' => 'after_switch'], 'first peer should receive original and new second-peer changes');
        sameArray($doc2->toArray(), ['original_2' => 'v2', 'new_2' => 'after_switch', 'original_1' => 'v1', 'new_1' => 'after_switch'], 'second peer should receive original and new first-peer changes');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'both peers should converge heads after read-write toggle with new changes');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust both read-only peers exchange accumulated changes after multiple rounds',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-both-toggle-after-multiple-read-only-rounds',
    'sync::tests::both_toggle_after_multiple_read_only_rounds',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState(['readOnly' => true]);

        $expected1 = [];
        $expected2 = [];
        for ($round = 0; $round < 5; ++$round) {
            $expected1['doc1_r' . $round] = $round;
            $expected2['doc2_r' . $round] = $round;
            $doc1 = $port->set($doc1, 'doc1_r' . $round, $round);
            $doc2 = $port->set($doc2, 'doc2_r' . $round, $round);
            [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        }

        sameArray($doc1->toArray(), $expected1, 'first peer should retain only its accumulated changes while read-only');
        sameArray($doc2->toArray(), $expected2, 'second peer should retain only its accumulated changes while read-only');

        $sync1['readOnly'] = false;
        $sync2['readOnly'] = false;
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        sameArray($doc1->toArray(), array_merge($expected1, $expected2), 'first peer should receive all accumulated second-peer changes after toggle');
        sameArray($doc2->toArray(), array_merge($expected2, $expected1), 'second peer should receive all accumulated first-peer changes after toggle');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge heads after multiple read-only rounds');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only peer receives accumulated remote changes after switching read-write mid-session',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-switch-read-only-to-read-write-mid-session',
    'sync::tests::switch_read_only_to_read_write_mid_session',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_a', 'hello');
        $doc2 = $port->set($port->init('def456'), 'from_b', 'world');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        same($doc2->toArray()['from_a'] ?? null, 'hello', 'read-write peer should receive the read-only peer change');
        truthy(! array_key_exists('from_b', $doc1->toArray()), 'read-only peer should ignore remote change before switching');

        $sync1['readOnly'] = false;
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        same($doc1->toArray()['from_b'] ?? null, 'world', 'former read-only peer should receive remote change after switching read-write');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge heads after switching read-write mid-session');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-write peer switched read-only publishes local changes but ignores remote ones',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-switch-read-write-to-read-only-mid-session',
    'sync::tests::switch_read_write_to_read_only_mid_session',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_a', 'hello');
        $doc2 = $port->set($port->init('def456'), 'from_b', 'world');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'initial read-write sync should converge heads');

        $sync1['readOnly'] = true;
        $doc2 = $port->set($doc2, 'new_from_b', 'secret');
        $doc1 = $port->set($doc1, 'new_from_a', 'published');
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        same($doc2->toArray()['new_from_a'] ?? null, 'published', 'read-write peer should receive new changes from the read-only peer');
        truthy(! array_key_exists('new_from_b', $doc1->toArray()), 'read-only peer should ignore remote changes after switching read-only');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only peer receives all accumulated remote rounds after switching read-write',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-switch-read-only-to-read-write-with-multiple-rounds',
    'sync::tests::switch_read_only_to_read_write_with_multiple_rounds',
    function () use ($port): void {
        $doc1 = $port->set($port->init('abc123'), 'from_a', 'initial');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        foreach (['round1', 'round2', 'round3'] as $key) {
            $doc2 = $port->set($doc2, $key, 'from_b');
            [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
            truthy(! array_key_exists($key, $doc1->toArray()), 'read-only peer should ignore ' . $key . ' before switching read-write');
        }

        $sync1['readOnly'] = false;
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);

        foreach (['round1', 'round2', 'round3'] as $key) {
            same($doc1->toArray()[$key] ?? null, 'from_b', 'former read-only peer should receive accumulated ' . $key);
        }
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge heads after accumulated read-only rounds');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only mode can toggle multiple times while preserving withheld changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-toggle-read-only-multiple-times',
    'sync::tests::toggle_read_only_multiple_times',
    function () use ($port): void {
        $doc1 = $port->init('abc123');
        $doc2 = $port->init('def456');
        $sync1 = $port->initSyncState(['readOnly' => true]);
        $sync2 = $port->initSyncState();

        $doc2 = $port->set($doc2, 'b1', 'val');
        $doc1 = $port->set($doc1, 'a1', 'val');
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        same($doc2->toArray()['a1'] ?? null, 'val', 'read-write peer should receive a1 while peer is read-only');
        truthy(! array_key_exists('b1', $doc1->toArray()), 'read-only peer should ignore b1');

        $sync1['readOnly'] = false;
        $doc2 = $port->set($doc2, 'b2', 'val');
        $doc1 = $port->set($doc1, 'a2', 'val');
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        same($doc1->toArray()['b1'] ?? null, 'val', 'read-write peer should catch up with withheld b1');
        same($doc1->toArray()['b2'] ?? null, 'val', 'read-write peer should receive b2');
        same($doc2->toArray()['a2'] ?? null, 'val', 'other peer should receive a2');

        $sync1['readOnly'] = true;
        $doc2 = $port->set($doc2, 'b3', 'val');
        $doc1 = $port->set($doc1, 'a3', 'val');
        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        same($doc2->toArray()['a3'] ?? null, 'val', 'read-write peer should receive a3 while peer is read-only again');
        truthy(! array_key_exists('b3', $doc1->toArray()), 'read-only peer should ignore b3');

        $sync1['readOnly'] = false;
        [$doc1, $doc2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        same($doc1->toArray()['b3'] ?? null, 'val', 'peer should catch up with b3 after toggling back to read-write');
        sameArray($port->getHeads($doc1), $port->getHeads($doc2), 'peers should converge heads after repeated read-only toggles');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only publisher does not relay ignored consumer changes',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-read-only-publisher-to-multiple-consumers',
    'sync::tests::read_only_publisher_to_multiple_consumers',
    function () use ($port): void {
        $publisher = $port->set($port->init('aaaaaa'), 'from_r', 'hello');
        $consumerA = $port->init('bbbbbb');
        $consumerB = $port->init('cccccc');
        $publisherToA = $port->initSyncState(['readOnly' => true]);
        $aToPublisher = $port->initSyncState();

        [$publisher, $consumerA, $publisherToA, $aToPublisher] = syncDocuments($port, $publisher, $consumerA, $publisherToA, $aToPublisher);
        same($consumerA->toArray()['from_r'] ?? null, 'hello', 'first consumer should receive publisher data');

        $consumerA = $port->set($consumerA, 'from_a', 'world');
        [$publisher, $consumerA, $publisherToA, $aToPublisher] = syncDocuments($port, $publisher, $consumerA, $publisherToA, $aToPublisher);
        truthy(! array_key_exists('from_a', $publisher->toArray()), 'read-only publisher should ignore consumer A data');

        [$publisher, $consumerB] = syncDocuments(
            $port,
            $publisher,
            $consumerB,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState()
        );

        same($consumerB->toArray()['from_r'] ?? null, 'hello', 'second consumer should receive publisher data');
        truthy(! array_key_exists('from_a', $consumerB->toArray()), 'second consumer should not receive ignored consumer A data through publisher');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust triangle sync preserves read-only publisher state when changes arrive via two paths',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-triangle-changes-arrive-via-two-paths',
    'sync::tests::triangle_changes_arrive_via_two_paths',
    function () use ($port): void {
        $publisher = $port->set($port->init('aaaaaa'), 'from_r', 'hello');
        $peerA = $port->set($port->init('bbbbbb'), 'from_a', 'world');
        $peerB = $port->init('cccccc');

        [$publisher, $peerA] = syncDocuments(
            $port,
            $publisher,
            $peerA,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState()
        );
        same($peerA->toArray()['from_r'] ?? null, 'hello', 'peer A should receive publisher data');

        [$peerA, $peerB] = syncDocuments($port, $peerA, $peerB, $port->initSyncState(), $port->initSyncState());
        same($peerB->toArray()['from_r'] ?? null, 'hello', 'peer B should receive publisher data via peer A');
        same($peerB->toArray()['from_a'] ?? null, 'world', 'peer B should receive peer A data');

        [$publisher, $peerB] = syncDocuments(
            $port,
            $publisher,
            $peerB,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState()
        );

        sameArray($publisher->toArray(), ['from_r' => 'hello'], 'read-only publisher should still only have its own data');
        same($peerB->toArray()['from_r'] ?? null, 'hello', 'peer B should retain publisher data');
        same($peerB->toArray()['from_a'] ?? null, 'world', 'peer B should retain peer A data');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only fully connected triangle keeps publisher isolated while consumers converge',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-read-only-fully-connected-triangle',
    'sync::tests::read_only_fully_connected_triangle',
    function () use ($port): void {
        $publisher = $port->set($port->init('aaaaaa'), 'from_r', 'r_val');
        $peerA = $port->set($port->init('bbbbbb'), 'from_a', 'a_val');
        $peerB = $port->set($port->init('cccccc'), 'from_b', 'b_val');
        $publisherHeads = $port->getHeads($publisher);

        [$publisher, $peerA] = syncDocuments(
            $port,
            $publisher,
            $peerA,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState()
        );
        [$publisher, $peerB] = syncDocuments(
            $port,
            $publisher,
            $peerB,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState()
        );

        same($peerA->toArray()['from_r'] ?? null, 'r_val', 'peer A should receive publisher data');
        same($peerB->toArray()['from_r'] ?? null, 'r_val', 'peer B should receive publisher data');

        [$peerA, $peerB] = syncDocuments($port, $peerA, $peerB, $port->initSyncState(), $port->initSyncState());

        foreach (['from_a' => 'a_val', 'from_b' => 'b_val', 'from_r' => 'r_val'] as $key => $value) {
            same($peerA->toArray()[$key] ?? null, $value, 'peer A should converge on ' . $key);
            same($peerB->toArray()[$key] ?? null, $value, 'peer B should converge on ' . $key);
        }
        sameArray($port->getHeads($peerA), $port->getHeads($peerB), 'read-write consumers should converge heads');
        sameArray($port->getHeads($publisher), $publisherHeads, 'publisher heads should remain unchanged');
        sameArray($publisher->toArray(), ['from_r' => 'r_val'], 'publisher should still only have its own data');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust stale shared heads after read-only sync do not corrupt direct publisher sync',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-stale-shared-heads-after-read-only-sync',
    'sync::tests::stale_shared_heads_after_read_only_sync',
    function () use ($port): void {
        $publisher = $port->init('aaaaaa');
        for ($i = 0; $i < 10; ++$i) {
            $publisher = $port->set($publisher, 'counter', $i);
        }
        $peerA = $port->set($port->init('bbbbbb'), 'from_a', 'a_val');
        $peerB = $port->init('cccccc');

        [$publisher, $peerA] = syncDocuments(
            $port,
            $publisher,
            $peerA,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState()
        );
        same($peerA->toArray()['counter'] ?? null, 9, 'peer A should receive publisher counter changes');

        [$peerA, $peerB] = syncDocuments($port, $peerA, $peerB, $port->initSyncState(), $port->initSyncState());
        same($peerB->toArray()['counter'] ?? null, 9, 'peer B should receive publisher counter via peer A');
        same($peerB->toArray()['from_a'] ?? null, 'a_val', 'peer B should receive peer A data');

        [$publisher, $peerB] = syncDocuments(
            $port,
            $publisher,
            $peerB,
            $port->initSyncState(['readOnly' => true]),
            $port->initSyncState()
        );

        truthy(! array_key_exists('from_a', $publisher->toArray()), 'read-only publisher should ignore peer A data arriving via peer B');
        same($peerB->toArray()['counter'] ?? null, 9, 'peer B should retain publisher counter after direct publisher sync');
        same($peerB->toArray()['from_a'] ?? null, 'a_val', 'peer B should retain peer A data after direct publisher sync');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust read-only peer handles same remote changes from two peers and continues publishing',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:sync-tests-read-only-peer-receives-same-changes-from-two-peers',
    'sync::tests::read_only_peer_receives_same_changes_from_two_peers',
    function () use ($port): void {
        $publisher = $port->set($port->init('aaaaaa'), 'from_r', 'r_val');
        $peerA = $port->set($port->init('bbbbbb'), 'from_a', 'a_val');
        $peerB = $port->set($port->init('cccccc'), 'from_b', 'b_val');

        [$peerA, $peerB] = syncDocuments($port, $peerA, $peerB, $port->initSyncState(), $port->initSyncState());
        sameArray($port->getHeads($peerA), $port->getHeads($peerB), 'peer A and B should start with the same heads');
        $publisherHeads = $port->getHeads($publisher);

        $publisherToA = $port->initSyncState(['readOnly' => true]);
        $aToPublisher = $port->initSyncState();
        [$publisher, $peerA, $publisherToA, $aToPublisher] = syncDocuments($port, $publisher, $peerA, $publisherToA, $aToPublisher);
        same($peerA->toArray()['from_r'] ?? null, 'r_val', 'peer A should receive publisher data');
        sameArray($port->getHeads($publisher), $publisherHeads, 'publisher should ignore peer A and B data via peer A');

        $publisherToB = $port->initSyncState(['readOnly' => true]);
        $bToPublisher = $port->initSyncState();
        [$publisher, $peerB, $publisherToB, $bToPublisher] = syncDocuments($port, $publisher, $peerB, $publisherToB, $bToPublisher);
        same($peerB->toArray()['from_r'] ?? null, 'r_val', 'peer B should receive publisher data');
        sameArray($port->getHeads($publisher), $publisherHeads, 'publisher should ignore same peer data via peer B');
        sameArray($publisher->toArray(), ['from_r' => 'r_val'], 'publisher should still only have its own initial data');

        $publisher = $port->set($publisher, 'from_r_2', 'new');
        [$publisher, $peerA, $publisherToA, $aToPublisher] = syncDocuments($port, $publisher, $peerA, $publisherToA, $aToPublisher);
        same($peerA->toArray()['from_r_2'] ?? null, 'new', 'peer A should receive later publisher data');
        [$publisher, $peerB] = syncDocuments($port, $publisher, $peerB, $publisherToB, $bToPublisher);
        same($peerB->toArray()['from_r_2'] ?? null, 'new', 'peer B should receive later publisher data');
    },
    'rust/automerge/src/sync.rs'
);

$mapped(
    'sync protocol sends explicitly requested known changes',
    'javascript/test/sync_test.ts',
    956,
    'should allow any change to be requested',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 3; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }
        $lastSync = $port->getHeads($doc1);

        for ($i = 3; $i < 6; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $sync1['sentHeads'] = [];
        $sync1['lastSentHeads'] = [];
        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        $decoded = $port->decodeSyncMessage($message);
        truthy($decoded !== null, 'forced post-sync message should be decodable');

        $decoded['need'] = $lastSync;
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decoded);
        [$sync2, $message] = $port->generateSyncMessage($doc2, $sync2);
        $response = $port->decodeSyncMessage($message);

        truthy($response !== null && count($response['changes']) > 0, 'peer should answer an explicit need request for a known change');
        same($response['changes'][0]['hash'] ?? null, $lastSync[0], 'explicit need response should send the requested change first');
    }
);

$mapped(
    'sync protocol ignores requests for nonexistent changes',
    'javascript/test/sync_test.ts',
    985,
    'should ignore requests for a nonexistent change',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        for ($i = 0; $i < 3; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }

        $doc2 = $port->applyChanges($doc2, $port->getAllChanges($doc1));

        [$sync1, $message1] = $port->generateSyncMessage($doc1, $sync1);
        if ($message1 !== null) {
            [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message1);
        }

        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);
        if ($message2 !== null) {
            [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $message2);
        }

        $decoded = $port->decodeSyncMessage($message1);
        truthy($decoded !== null, 'initial sync message should be decodable before mutating the need list');
        $decoded['need'] = [str_repeat('0', 64)];
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decoded);
        [$sync2, $message2] = $port->generateSyncMessage($doc2, $sync2);

        same($message2, null, 'peer should not reply to a request for an unknown change hash');
        sameArray($doc2->toArray(), $doc1->toArray(), 'ignoring the nonexistent request should leave the synced document unchanged');
    }
);

$mapped(
    'sync protocol requests remaining heads after a subset response',
    'javascript/test/sync_test.ts',
    1022,
    'should allow a subset of changes to be sent',
    function () use ($port): void {
        $doc1 = $port->init('01234567');
        $doc2 = $port->init('89abcdef');
        $doc3 = $port->init('76543210');
        $sync1 = $port->initSyncState();
        $sync2 = $port->initSyncState();

        $doc1 = $port->setWithTime($doc1, 'x', 0, 0);
        $doc3 = $port->applyChanges($doc3, $port->getAllChanges($doc1));
        for ($i = 1; $i <= 2; ++$i) {
            $doc1 = $port->setWithTime($doc1, 'x', $i, 0);
        }
        for ($i = 3; $i <= 4; ++$i) {
            $doc3 = $port->setWithTime($doc3, 'x', $i, 0);
        }
        $c2 = $port->getHeads($doc1)[0];
        $c4 = $port->getHeads($doc3)[0];
        $doc2 = $port->applyChanges($doc2, $port->getAllChanges($doc3));

        [$doc1, $doc2, $sync1, $sync2] = syncDocuments($port, $doc1, $doc2, $sync1, $sync2);
        $expectedInitialSharedHeads = [$c2, $c4];
        sort($expectedInitialSharedHeads);
        sameArray($sync1['sharedHeads'], $expectedInitialSharedHeads, 'subset sync should start with both branch heads shared by peer one');
        sameArray($sync2['sharedHeads'], $expectedInitialSharedHeads, 'subset sync should start with both branch heads shared by peer two');

        $doc3 = $port->setWithTime($doc3, 'x', 5, 0);
        $change5 = $port->getLastLocalChange($doc3);
        $doc3 = $port->setWithTime($doc3, 'x', 6, 0);
        $change6 = $port->getLastLocalChange($doc3);
        $c6 = $port->getHeads($doc3)[0];
        for ($i = 7; $i <= 8; ++$i) {
            $doc3 = $port->setWithTime($doc3, 'x', $i, 0);
        }
        $c8 = $port->getHeads($doc3)[0];
        truthy($change5 !== null && $change6 !== null, 'subset sync source should expose changes five and six');
        $doc2 = $port->mergeDocuments($doc2, $doc3);

        [$sync1, $message] = $port->generateSyncMessage($doc1, $sync1);
        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $message);
        [$sync2, $message] = $port->generateSyncMessage($doc2, $sync2);
        $decoded = $port->decodeSyncMessage($message);
        truthy($decoded !== null, 'subset response should be decodable before truncation');
        $decoded['changes'] = [$change5, $change6];

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decoded);
        $expectedPartialSharedHeads = [$c2, $c6];
        sort($expectedPartialSharedHeads);
        sameArray($sync1['sharedHeads'], $expectedPartialSharedHeads, 'partial response should only share heads actually received');

        [$sync1, $request] = $port->generateSyncMessage($doc1, $sync1);
        $decodedRequest = $port->decodeSyncMessage($request);
        truthy($decodedRequest !== null, 'subset follow-up request should be decodable');
        sameArray($decodedRequest['need'], [$c8], 'subset follow-up should request the advertised missing head');
        sameArray($decodedRequest['have'][0]['lastSync'] ?? [], $expectedPartialSharedHeads, 'subset follow-up should advertise the partial shared heads');

        [$doc2, $sync2] = $port->receiveSyncMessage($doc2, $sync2, $decodedRequest);
        [$sync2, $response] = $port->generateSyncMessage($doc2, $sync2);
        $decodedResponse = $port->decodeSyncMessage($response);
        truthy($decodedResponse !== null && count($decodedResponse['changes']) >= 1, 'peer should answer the explicit missing-head request');
        same($decodedResponse['changes'][0]['hash'] ?? null, $c8, 'missing-head response should send the explicitly requested head first');

        [$doc1, $sync1] = $port->receiveSyncMessage($doc1, $sync1, $decodedResponse);
        $expectedFinalSharedHeads = [$c2, $c8];
        sort($expectedFinalSharedHeads);
        sameArray($port->getHeads($doc1), $expectedFinalSharedHeads, 'requesting the missing head should complete peer one to both branch heads');
        sameArray($sync1['sharedHeads'], $expectedFinalSharedHeads, 'requesting the missing head should advance shared heads through the advertised branch');
    }
);

$mapped(
    'changeAt text splice merges with later text edits',
    'javascript/test/change_at.ts',
    6,
    'should be able to change a doc at a prior state',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'text', 'aaabbbccc');
        $heads1 = $port->getHeads($doc1);

        $doc1 = $port->splice($doc1, 'text', 3, 3, 'BBB');
        same($doc1->toArray()['text'], 'aaaBBBccc', 'current branch should show the later text replacement');

        $historical = $port->view($doc1, $heads1);
        same($historical->toArray()['text'], 'aaabbbccc', 'changeAt draft should materialize the requested historical text');

        $changed = $port->spliceAtHeads($doc1, $heads1, 'text', 2, 3, 'XXX');
        same($changed->toArray()['text'], 'aaXXXBBBccc', 'historical text splice should merge with the later text edit');
    }
);

$mapped(
    'changeAt empty changes preserve forked document heads',
    'javascript/test/change_at.ts',
    22,
    'should leave multiple heads intact on empty changes',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'text', 'aaabbbccc');
        $headsBeforeFork = $port->getHeads($doc1);

        $doc2 = $port->set($port->clone($doc1, 'bbbbbb'), 'doc2', 'doc2');
        $doc1 = $port->set($doc1, 'doc1', 'doc1');
        $doc1 = $port->mergeDocuments($doc1, $doc2);

        same(count($port->getHeads($doc1)), 2, 'setup should create a forked document with two heads');
        $afterEmptyChange = $port->emptyChangeAtHeads($doc1, $headsBeforeFork);
        same(count($port->getHeads($afterEmptyChange)), 2, 'empty changeAt should preserve forked heads');
        sameArray($afterEmptyChange->toArray(), $doc1->toArray(), 'empty changeAt should not alter materialized state');
    }
);

$mapped(
    'changeAt returns the heads of the change document',
    'javascript/test/change_at.ts',
    47,
    'should return the heads of the change document from changeAt',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'text', 'aaabbbccc');

        $doc2 = $port->set($port->clone($doc1, 'bbbbbb'), 'doc2', 'doc2');
        $headsOnFork = $port->getHeads($doc2);

        $doc1 = $port->set($doc1, 'doc1', 'doc1');
        $doc1Heads = $port->getHeads($doc1);

        $merged = $port->mergeDocuments($doc1, $doc2);
        $changed = $port->setAtHeads($merged, $doc1Heads, 'text', 'changed');
        $newHeads = array_values(array_diff($port->getHeads($changed), $headsOnFork));
        $expectedHeads = array_values(array_unique(array_merge($headsOnFork, $newHeads)));
        sort($expectedHeads, SORT_STRING);
        $actualHeads = $port->getHeads($changed);
        sort($actualHeads, SORT_STRING);

        same(count($newHeads), 1, 'changeAt should create one new head for the requested branch');
        sameArray($actualHeads, $expectedHeads, 'changeAt should preserve unrelated fork heads alongside the new change head');
        same($changed->toArray()['text'], 'changed', 'changeAt root assignment should materialize the new value');
        same($changed->toArray()['doc2'], 'doc2', 'changeAt root assignment should preserve the unrelated fork state');
    }
);

$mapped(
    'basic diff emits text patches between before and after heads',
    'javascript/test/basic_test.ts',
    587,
    'can diff a document with before and hafter heads',
    function () use ($port): void {
        $doc = $port->from(['value' => ''], 'aaaaaa');
        $doc = $port->set($doc, 'value', 'aaa');
        $heads1 = $port->getHeads($doc);
        $doc = $port->set($doc, 'value', 'bbb');
        $heads2 = $port->getHeads($doc);

        sameArray(
            $port->diff($doc, $heads1, $heads2),
            [
                ['action' => 'put', 'path' => ['value'], 'value' => ''],
                ['action' => 'splice', 'path' => ['value', 0], 'value' => 'bbb'],
            ],
            'forward text diff should recreate the after text value'
        );
        sameArray(
            $port->diff($doc, $heads2, $heads1),
            [
                ['action' => 'put', 'path' => ['value'], 'value' => ''],
                ['action' => 'splice', 'path' => ['value', 0], 'value' => 'aaa'],
            ],
            'reverse text diff should recreate the before text value'
        );
    }
);

$mapped(
    'basic saveSince matches saveIncremental from the last incremental heads',
    'javascript/test/basic_test.ts',
    606,
    'should be the same as saveIncremental since heads of the last saveIncremental',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $doc = $port->set($doc, 'a', 'b');
        $port->saveIncremental($doc);
        $heads = $port->getHeads($doc);

        $doc = $port->set($doc, 'c', 'd');

        same(
            $port->saveIncremental($doc),
            $port->saveSince($doc, $heads),
            'incremental save should serialize the same native change batch as saveSince'
        );
    }
);

$mapped(
    'extra API loadIncremental applies full and incremental native batches',
    'javascript/test/extra_api_tests.ts',
    6,
    'should allow you to load incrementally',
    function () use ($port): void {
        $doc1 = $port->from(['foo' => 'bar'], 'aaaaaa');
        $doc2 = $port->loadIncremental($port->init('bbbbbb'), $port->save($doc1));

        $doc1 = $port->set($doc1, 'foo2', 'bar2');
        $doc2 = $port->loadIncremental($doc2, $port->saveIncremental($doc1));

        $doc1 = $port->set($doc1, 'foo', 'bar2');
        $doc2 = $port->loadIncremental($doc2, $port->saveIncremental($doc1));

        $doc1 = $port->set($doc1, 'x', 'y');
        $doc2 = $port->loadIncremental($doc2, $port->saveIncremental($doc1));

        sameArray($doc2->toArray(), $doc1->toArray(), 'incremental load should converge with the source document');
        sameArray($port->getHeads($doc2), $port->getHeads($doc1), 'incremental load should advance to the source heads');
    }
);

$mapped(
    'bundle format saves selected changes and loads them incrementally',
    'javascript/test/bundle_test.ts',
    5,
    'should allow saving and loading a bundle',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $startDoc = $port->clone($doc, 'bbbbbb');
        $startHeads = $port->getHeads($doc);
        $doc = $port->set($doc, 'foo', 'baz');
        $doc = $port->set($doc, 'foo', 'qux');
        $changeHashes = array_map(
            static fn (array $change): string => $change['hash'],
            $port->getChangesMetaSince($doc, $startHeads)
        );

        same(count($changeHashes), 2, 'bundle test should select the two changes after the starting heads');
        $bundle = $port->saveBundle($doc, $changeHashes);
        $loaded = $port->loadIncremental($startDoc, $bundle);

        sameArray($loaded->toArray(), ['foo' => 'qux'], 'loading the bundle should apply both selected changes');
    }
);

$mapped(
    'bundle format exposes inspectable changes by hash',
    'javascript/test/bundle_test.ts',
    27,
    'should allow getting the list of changes in a bundle',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $startHeads = $port->getHeads($doc);
        $doc = $port->set($doc, 'foo', 'baz');
        $doc = $port->set($doc, 'foo', 'qux');
        $changeHashes = array_map(
            static fn (array $change): string => $change['hash'],
            $port->getChangesMetaSince($doc, $startHeads)
        );

        $bundle = $port->saveBundle($doc, $changeHashes);
        $bundleChanges = $port->readBundle($bundle)['changes'];
        $changesByHash = [];
        foreach ($bundleChanges as $change) {
            $changesByHash[$change['hash']] = $change;
        }

        foreach ($changeHashes as $hash) {
            sameArray($changesByHash[$hash] ?? [], $port->inspectChange($doc, $hash) ?? [], 'bundle should expose inspectable change metadata by hash');
        }
    }
);

$mapped(
    'bundle format reports dependencies outside the selected changes',
    'javascript/test/bundle_test.ts',
    56,
    'should show the dependencies of a bundle',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $startHeads = $port->getHeads($doc);
        $doc = $port->set($doc, 'foo', 'baz');
        $changeHashes = array_map(
            static fn (array $change): string => $change['hash'],
            $port->getChangesMetaSince($doc, $startHeads)
        );

        $bundle = $port->saveBundle($doc, $changeHashes);
        sameArray($port->readBundle($bundle)['deps'], $startHeads, 'bundle deps should identify heads required before applying selected changes');
    }
);

$rustMapped(
    'rust storage bundle preserves selected changes and loads into a fork',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:storage-bundle-test-make-bundle',
    'storage::bundle::test::make_bundle',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $doc = $port->set($doc, 'aaa', $port->immutableString('aaa'));
        $h0 = $port->getHeads($doc)[0] ?? '';
        $fork = $port->clone($doc, 'bbbbbb');

        $doc = $port->set($doc, 'bbb', $port->immutableString('bbb'));
        $h1 = $port->getHeads($doc)[0] ?? '';
        $doc = $port->set($doc, 'ccc', $port->immutableString('ccc'));
        $h2 = $port->getHeads($doc)[0] ?? '';

        $bundle = $port->saveBundle($doc, [$h0, $h1, $h2]);
        $changes = $port->readBundle($bundle)['changes'];
        same(count($changes), 3, 'bundle should expose the three selected changes');
        same($changes[0]['hash'] ?? null, $h0, 'first bundled change should be h0');
        same($changes[1]['hash'] ?? null, $h1, 'second bundled change should be h1');
        same($changes[2]['hash'] ?? null, $h2, 'third bundled change should be h2');
        same($changes[0]['startOp'] ?? null, 1, 'first bundled change should start at op 1');
        same($changes[1]['startOp'] ?? null, 2, 'second bundled change should start at op 2');
        same($changes[2]['startOp'] ?? null, 3, 'third bundled change should start at op 3');

        $loadedFork = $port->loadIncremental($fork, $bundle);
        same(json_encode($loadedFork->toArray()), json_encode($doc->toArray()), 'loading a full bundle into a fork should converge materialized data');
        sameArray($port->getHeads($loadedFork), $port->getHeads($doc), 'loading a full bundle into a fork should converge heads');

        $partialBundle = $port->saveBundle($doc, [$h0, $h2]);
        $partialChanges = $port->readBundle($partialBundle)['changes'];
        same(count($partialChanges), 2, 'partial bundle should expose only selected changes');
        same($partialChanges[0]['hash'] ?? null, $h0, 'partial bundle should retain selected h0');
        same($partialChanges[1]['hash'] ?? null, $h2, 'partial bundle should retain selected h2');
        same($partialChanges[0]['startOp'] ?? null, 1, 'partial bundle h0 should retain its start op');
        same($partialChanges[1]['startOp'] ?? null, 3, 'partial bundle h2 should retain its start op');
    },
    'rust/automerge/src/storage/bundle.rs'
);

$mapped(
    'patch callback exposes before and after heads',
    'javascript/test/patches.ts',
    7,
    'should provide access to before and after states',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $headsBefore = $port->getHeads($doc);
        $headsAfter = null;
        $callbackPort = $port->withPatchCallback(static function (array $patches, array $info) use ($port, $headsBefore, &$headsAfter): void {
            sameArray($port->getHeads($info['before']), $headsBefore, 'patch callback before document should retain the pre-change heads');
            $headsAfter = $port->getHeads($info['after']);
        });

        $newDoc = $callbackPort->set($doc, 'count', 1);

        sameArray($headsAfter ?? [], $port->getHeads($newDoc), 'patch callback after document should expose the committed heads');
    }
);

$mapped(
    'patch callback exposes before and after states for list deletion',
    'javascript/test/patches.ts',
    27,
    'should provide correct before and after states when an array has a value deleted',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');
        $callbacks = [];
        $callbackPort = $port->withPatchCallback(static function (array $patches, array $info) use (&$callbacks): void {
            $callbacks[] = [
                'before' => $info['before']->toArray(),
                'after' => $info['after']->toArray(),
            ];
        });

        $newDoc = $callbackPort->deleteListElements($doc, 'list', 1);

        sameArray($callbacks[0]['before']['list'], ['a', 'b', 'c'], 'patch callback before state should include the original list');
        sameArray($callbacks[0]['after']['list'], ['a', 'c'], 'patch callback after state should include the deleted list value');
        sameArray($newDoc->toArray(), ['list' => ['a', 'c']], 'list deletion should materialize the expected document');
    }
);

$mapped(
    'patch callback exposes before and after states for nested property deletion',
    'javascript/test/patches.ts',
    49,
    'should provide correct before and after states when an object property has been removed',
    function () use ($port): void {
        $doc = $port->from(['obj' => ['a' => 'a', 'b' => 'b']], 'aaaaaa');
        $callbacks = [];
        $callbackPort = $port->withPatchCallback(static function (array $patches, array $info) use (&$callbacks): void {
            $callbacks[] = [
                'patches' => $patches,
                'before' => $info['before']->toArray(),
                'after' => $info['after']->toArray(),
            ];
        });

        $newDoc = $callbackPort->deleteNested($doc, ['obj', 'b']);

        sameArray($callbacks[0]['patches'], [['action' => 'del', 'path' => ['obj', 'b']]], 'nested delete should emit a native delete patch path');
        sameArray($callbacks[0]['before']['obj'], ['a' => 'a', 'b' => 'b'], 'patch callback before state should include the removed property');
        sameArray($callbacks[0]['after']['obj'], ['a' => 'a'], 'patch callback after state should omit the removed property');
        sameArray($newDoc->toArray(), ['obj' => ['a' => 'a']], 'nested property deletion should materialize the expected document');
    }
);

$mapped(
    'patch diff returns insertion and container patches between heads',
    'javascript/test/patches.ts',
    76,
    'should return a set of patches',
    function () use ($port): void {
        $doc = $port->from(['birds' => ['goldfinch']], 'aaaaaa');
        $before = $port->getHeads($doc);
        $newDoc = $port->pushList($doc, 'birds', ['greenfinch']);
        $newDoc = $port->set($newDoc, 'fish', ['cod']);
        $after = $port->getHeads($newDoc);

        sameArray(
            $port->diff($newDoc, $before, $after),
            [
                ['action' => 'put', 'path' => ['fish'], 'value' => []],
                ['action' => 'insert', 'path' => ['birds', 1], 'values' => ['']],
                ['action' => 'splice', 'path' => ['birds', 1, 0], 'value' => 'greenfinch'],
                ['action' => 'insert', 'path' => ['fish', 0], 'values' => ['']],
                ['action' => 'splice', 'path' => ['fish', 0, 0], 'value' => 'cod'],
            ],
            'diff should emit stable Automerge-style patches between known heads'
        );
    }
);

$mapped(
    'patch diff rejects invalid before and after heads',
    'javascript/test/patches.ts',
    96,
    'should throw a nice exception if before or after are not an array',
    function () use ($port): void {
        $doc = $port->from(['text' => 'hello world'], 'aaaaaa');
        $goodBefore = $port->getHeads($doc);
        $doc = $port->splice($doc, 'text', 0, 0, 'hello ');
        $goodAfter = $port->getHeads($doc);

        foreach ([null, '', 'ab', ['ab']] as $invalidInput) {
            throwsLike(
                static fn () => $port->diff($doc, $invalidInput, $goodAfter),
                'invalid before heads',
                'diff should reject invalid before heads'
            );
            throwsLike(
                static fn () => $port->diff($doc, $goodBefore, $invalidInput),
                'invalid after heads',
                'diff should reject invalid after heads'
            );
        }
    }
);

$mapped(
    'patch diffPath supports nested map scopes and shallow recursion',
    'javascript/test/patches.ts',
    120,
    'should allow diffing a sub-object',
    function () use ($port): void {
        $doc = $port->from(['a' => 1, 'foo' => ['b' => 1, 'bar' => ['c' => 1, 'baz' => ['d' => 1]]]], 'aaaaaa');
        $h1 = $port->getHeads($doc);
        $doc = $port->setNested($doc, ['a'], 2);
        $doc = $port->setNested($doc, ['foo', 'b'], 2);
        $doc = $port->setNested($doc, ['foo', 'bar', 'c'], 2);
        $doc = $port->setNested($doc, ['foo', 'bar', 'baz', 'd'], 2);
        $h2 = $port->getHeads($doc);
        $doc = $port->setNested($doc, ['foo', 'bar', 'baz', 'd'], 3);
        $h3 = $port->getHeads($doc);
        $doc = $port->setNested($doc, ['a'], 4);
        $doc = $port->setNested($doc, ['foo', 'b'], 4);
        $doc = $port->setNested($doc, ['foo', 'bar', 'c'], 4);
        $doc = $port->setNested($doc, ['foo', 'bar', 'baz'], ['d' => 4]);
        $h4 = $port->getHeads($doc);

        $full = [
            ['action' => 'put', 'path' => ['a'], 'value' => 4],
            ['action' => 'put', 'path' => ['foo', 'b'], 'value' => 4],
            ['action' => 'put', 'path' => ['foo', 'bar', 'baz'], 'value' => []],
            ['action' => 'put', 'path' => ['foo', 'bar', 'c'], 'value' => 4],
            ['action' => 'put', 'path' => ['foo', 'bar', 'baz', 'd'], 'value' => 4],
        ];
        $bar = array_slice($full, 2);

        sameArray($port->diff($doc, $h1, $h4), $full, 'diff should include nested replacement container and scalar updates');
        sameArray($port->diffPath($doc, ['foo', 'bar'], $h1, $h4), $bar, 'diffPath should preserve absolute paths under the selected object');
        sameArray(array_values($port->diffPath($doc, ['foo', 'bar'], $h1, $h4, ['recursive' => false])), array_slice($bar, 0, 2), 'shallow diffPath should omit grandchildren');
        sameArray($port->diffPath($doc, ['foo', 'bar', 'baz'], $h1, $h4), [['action' => 'put', 'path' => ['foo', 'bar', 'baz', 'd'], 'value' => 4]], 'diffPath should support deeper map paths');
        sameArray($port->diffPath($doc, ['foo', 'bar'], $h2, $h3), [['action' => 'put', 'path' => ['foo', 'bar', 'baz', 'd'], 'value' => 3]], 'diffPath should compare adjacent head ranges');
        sameArray($port->diffPath($doc, ['foo', 'bar'], $h3, $h2), [['action' => 'put', 'path' => ['foo', 'bar', 'baz', 'd'], 'value' => 2]], 'diffPath should compare reverse head ranges');
        sameArray($port->diffPath($doc, ['foo', 'bar'], [], $h4), $bar, 'diffPath should diff from an empty head set');
        sameArray(array_values($port->diffPath($doc, ['foo', 'bar'], [], $h4, ['recursive' => false])), array_slice($bar, 0, 2), 'shallow diffPath from empty heads should omit grandchildren');
        sameArray($port->diffPath($doc, ['foo', 'bar'], $h3, $h4), $bar, 'diffPath should report replacement containers after later changes');
        sameArray(array_values($port->diffPath($doc, ['foo', 'bar'], $h3, $h4, ['recursive' => false])), array_slice($bar, 0, 2), 'shallow diffPath after later changes should omit grandchildren');
    }
);

$mapped(
    'patch diff reverses deletion of a string list value',
    'javascript/test/patches.ts',
    201,
    'should correctly diff the reverse of deleting a string value on next',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');
        $callbacks = [];
        $callbackPort = $port->withPatchCallback(static function (array $patches, array $info) use ($port, &$callbacks): void {
            $callbacks[] = $port->diff(
                $info['after'],
                $port->getHeads($info['after']),
                $port->getHeads($info['before'])
            );
        });

        $callbackPort->deleteListElements($doc, 'list', 1);

        sameArray(
            $callbacks[0],
            [
                ['action' => 'insert', 'path' => ['list', 1], 'values' => ['']],
                ['action' => 'splice', 'path' => ['list', 1, 0], 'value' => 'b'],
            ],
            'reverse diff should reinsert a deleted string list value'
        );
    }
);

$mapped(
    'patch changeAt style updates do not mix stale scalar content',
    'javascript/test/patches.ts',
    225,
    'should produce correct patches during changeAt',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $beginning = $port->getHeads($doc);
        $doc = $port->set($doc, 'name', str_repeat('a', 100));

        $doc = $port->setAtHeads($doc, $beginning, 'color', 'red');
        $doc = $port->setAtHeads($doc, $beginning, 'color', 'unset');

        same($doc->toArray()['color'], 'unset', 'changeAt-style scalar update should keep the last visible value intact');
        same($doc->toArray()['name'], str_repeat('a', 100), 'changeAt-style update should preserve unrelated larger scalar content');
    }
);

$mapped(
    'patches apply a map update to a document',
    'javascript/test/patches.ts',
    254,
    'should apply a map update',
    function () use ($port): void {
        $doc = $port->from(['foo' => ['bar' => 'baz']], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'put',
                    'path' => ['foo', 'bar'],
                    'value' => 'qux',
                ],
            ]
        );

        same($doc->toArray()['foo']['bar'], 'qux', 'put patch should update a nested map field');
    }
);

$mapped(
    'patches apply a list update to a document',
    'javascript/test/patches.ts',
    267,
    'should apply a list update patch',
    function () use ($port): void {
        $doc = $port->from(['foo' => ['bar']], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'put',
                    'path' => ['foo', 0],
                    'value' => 'baz',
                ],
            ]
        );

        same($doc->toArray()['foo'][0], 'baz', 'put patch should update a list element');
    }
);

$mapped(
    'patches apply a list insertion to a document',
    'javascript/test/patches.ts',
    278,
    'should apply a list insertion patch',
    function () use ($port): void {
        $doc = $port->from(['foo' => ['bar']], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'insert',
                    'path' => ['foo', 1],
                    'values' => ['baz', 'qux'],
                ],
            ]
        );

        sameArray($doc->toArray()['foo'], ['bar', 'baz', 'qux'], 'insert patch should add list values at the requested index');
    }
);

$mapped(
    'patches apply a list deletion without length to a document',
    'javascript/test/patches.ts',
    289,
    'should apply a list deletion patch without length',
    function () use ($port): void {
        $doc = $port->from(['foo' => ['bar', 'baz', 'qux']], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'del',
                    'path' => ['foo', 1],
                ],
            ]
        );

        sameArray($doc->toArray()['foo'], ['bar', 'qux'], 'single del patch should delete one list element');
    }
);

$mapped(
    'patches apply a list deletion with length to a document',
    'javascript/test/patches.ts',
    301,
    'should apply a list deletion patch with length',
    function () use ($port): void {
        $doc = $port->from(['foo' => ['bar', 'baz', 'qux']], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'del',
                    'path' => ['foo', 0],
                    'length' => 2,
                ],
            ]
        );

        sameArray($doc->toArray()['foo'], ['qux'], 'length del patch should delete the requested range');
    }
);

$mapped(
    'patches apply a text splice to a document',
    'javascript/test/patches.ts',
    314,
    'should apply a text splice patch',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'splice',
                    'path' => ['foo', 3],
                    'value' => 'baz',
                ],
            ]
        );

        same($doc->toArray()['foo'], 'barbaz', 'splice patch should insert text at the requested offset');
    }
);

$mapped(
    'patches apply a text deletion without length to a document',
    'javascript/test/patches.ts',
    325,
    'should apply a text deletion patch without length',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'del',
                    'path' => ['foo', 0],
                ],
            ]
        );

        same($doc->toArray()['foo'], 'ar', 'single del patch should delete one text character');
    }
);

$mapped(
    'patches apply a text deletion with length to a document',
    'javascript/test/patches.ts',
    335,
    'should apply a text deletion patch with length',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'del',
                    'path' => ['foo', 0],
                    'length' => 2,
                ],
            ]
        );

        same($doc->toArray()['foo'], 'r', 'length del patch should delete the requested text range');
    }
);

$mapped(
    'patches apply an increment patch to a document counter',
    'javascript/test/patches.ts',
    346,
    'should apply an increment patch',
    function () use ($port): void {
        $doc = $port->from(['foo' => new Counter(1)], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'inc',
                    'path' => ['foo'],
                    'value' => 2,
                ],
            ]
        );

        $counter = $doc->toArray()['foo'];
        truthy($counter instanceof Counter, 'increment patch should keep the field as a native Counter');
        same($counter->value(), 3, 'increment patch should add to the counter value');
    }
);

$mapped(
    'patches apply a mark patch to a document',
    'javascript/test/patches.ts',
    359,
    'should apply a mark patch',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'mark',
                    'path' => ['foo'],
                    'marks' => [
                        [
                            'name' => 'bold',
                            'value' => true,
                            'start' => 0,
                            'end' => 2,
                        ],
                    ],
                ],
            ]
        );

        sameArray($port->marks($doc, ['foo']), [['name' => 'bold', 'value' => true, 'start' => 0, 'end' => 2]], 'mark patch should store text mark metadata');
    }
);

$mapped(
    'patches apply an unmark patch to a document',
    'javascript/test/patches.ts',
    380,
    'should apply an unmark patch',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [
                [
                    'action' => 'mark',
                    'path' => ['foo'],
                    'marks' => [['name' => 'bold', 'value' => true, 'start' => 0, 'end' => 2]],
                ],
            ]
        );
        $doc = $port->applyPatches(
            $doc,
            [['action' => 'unmark', 'path' => ['foo'], 'name' => 'bold', 'start' => 0, 'end' => 2]]
        );

        sameArray($port->marks($doc, ['foo']), [], 'unmark patch should remove matching text mark metadata');
    }
);

$mapped(
    'patches apply a map update to a plain PHP array',
    'javascript/test/patches.ts',
    405,
    'should apply a map update to a nested map',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => ['bar' => 'baz']],
            [['action' => 'put', 'path' => ['foo', 'bar'], 'value' => 'qux']]
        );

        same($doc['foo']['bar'], 'qux', 'plain-array put patch should update a nested map field');
    }
);

$mapped(
    'patches apply a list update to a plain PHP array',
    'javascript/test/patches.ts',
    416,
    'should apply a list update patch',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => ['bar']],
            [['action' => 'put', 'path' => ['foo', 0], 'value' => 'baz']]
        );

        same($doc['foo'][0], 'baz', 'plain-array put patch should update a list element');
    }
);

$mapped(
    'patches apply a list insertion to a plain PHP array',
    'javascript/test/patches.ts',
    427,
    'should apply a list insertion patch',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => ['bar']],
            [['action' => 'insert', 'path' => ['foo', 1], 'values' => ['baz', 'qux']]]
        );

        sameArray($doc['foo'], ['bar', 'baz', 'qux'], 'plain-array insert patch should add list values');
    }
);

$mapped(
    'patches apply a list deletion without length to a plain PHP array',
    'javascript/test/patches.ts',
    438,
    'should apply a list deletion patch without length',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => ['bar', 'baz', 'qux']],
            [['action' => 'del', 'path' => ['foo', 1]]]
        );

        sameArray($doc['foo'], ['bar', 'qux'], 'plain-array del patch should delete one list element');
    }
);

$mapped(
    'patches apply a list deletion with length to a plain PHP array',
    'javascript/test/patches.ts',
    450,
    'should apply a list deletion patch with length',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => ['bar', 'baz', 'qux']],
            [['action' => 'del', 'path' => ['foo', 0], 'length' => 2]]
        );

        sameArray($doc['foo'], ['qux'], 'plain-array length del patch should delete the requested range');
    }
);

$mapped(
    'patches apply a text splice to a plain PHP array',
    'javascript/test/patches.ts',
    463,
    'should apply a text splice patch',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => 'bar'],
            [['action' => 'splice', 'path' => ['foo', 3], 'value' => 'baz']]
        );

        same($doc['foo'], 'barbaz', 'plain-array splice patch should insert text');
    }
);

$mapped(
    'patches apply a text deletion without length to a plain PHP array',
    'javascript/test/patches.ts',
    474,
    'should apply a text deletion patch without length',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => 'bar'],
            [['action' => 'del', 'path' => ['foo', 0]]]
        );

        same($doc['foo'], 'ar', 'plain-array del patch should delete one text character');
    }
);

$mapped(
    'patches apply a text deletion with length to a plain PHP array',
    'javascript/test/patches.ts',
    484,
    'should apply a text deletion patch with length',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => 'bar'],
            [['action' => 'del', 'path' => ['foo', 0], 'length' => 2]]
        );

        same($doc['foo'], 'r', 'plain-array length del patch should delete the requested text range');
    }
);

$mapped(
    'patches apply an increment patch to a plain PHP array',
    'javascript/test/patches.ts',
    495,
    'should apply an increment patch',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => 1],
            [['action' => 'inc', 'path' => ['foo'], 'value' => 2]]
        );

        same($doc['foo'], 3, 'plain-array increment patch should add to the numeric field');
    }
);

$mapped(
    'patches ignore a mark patch on a plain PHP array',
    'javascript/test/patches.ts',
    506,
    'should ignore a mark patch',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => 'bar'],
            [
                [
                    'action' => 'mark',
                    'path' => ['foo'],
                    'marks' => [['name' => 'bold', 'value' => true, 'start' => 0, 'end' => 2]],
                ],
            ]
        );

        sameArray($doc, ['foo' => 'bar'], 'plain-array mark patch should be ignored without mutating text');
    }
);

$mapped(
    'patches ignore an unmark patch on a plain PHP array',
    'javascript/test/patches.ts',
    523,
    'should ignore an unmark patch',
    function () use ($port): void {
        $doc = $port->applyPatchesToArray(
            ['foo' => 'bar'],
            [['action' => 'unmark', 'path' => ['foo'], 'name' => 'bold', 'start' => 0, 'end' => 2]]
        );

        sameArray($doc, ['foo' => 'bar'], 'plain-array unmark patch should be ignored without mutating text');
    }
);

$mapped(
    'patches apply a deep map update to a document',
    'javascript/test/patches.ts',
    535,
    'should apply a map update to a map in a list in a map in a list',
    function () use ($port): void {
        $doc = $port->from(['foo' => [['bar' => [['foo' => 'hehe']]]]], 'aaaaaa');
        $doc = $port->applyPatches(
            $doc,
            [['action' => 'put', 'path' => ['foo', 0, 'bar', 0, 'foo'], 'value' => 'qux']]
        );

        same($doc->toArray()['foo'][0]['bar'][0]['foo'], 'qux', 'deep put patch should update nested map value through lists');
    }
);

$mapped(
    'basic toJS returns each document at its own heads',
    'javascript/test/basic_test.ts',
    778,
    'should return the document at its correct heads',
    function () use ($port): void {
        $doc = $port->from(['x' => 1], 'aaaaaa');
        $doc1 = $port->setMany($doc, ['a' => 123, 'b' => 456]);

        sameArray($port->toJS($doc), ['x' => 1], 'toJS should materialize the unchanged base document');
        sameArray($port->toJS($doc1), ['x' => 1, 'a' => 123, 'b' => 456], 'toJS should materialize the changed document');
    }
);

$mapped(
    'basic immutable string accepts symbol-compatible objects',
    'javascript/test/basic_test.ts',
    792,
    'should treat any class which has the correct symbol as a ImmutableString',
    function () use ($port): void {
        $fake = new class ('something') {
            public bool $isImmutableString = true;

            public function __construct(private readonly string $value)
            {
            }

            public function __toString(): string
            {
                return $this->value;
            }
        };

        $doc = $port->from(['foo' => null], 'aaaaaa');
        $doc = $port->set($doc, 'foo', $fake);
        $foo = $port->toJS($doc)['foo'];

        truthy($foo instanceof ImmutableString, 'symbol-compatible objects should materialize as ImmutableString');
        same($foo->toString(), 'something', 'immutable string content should be preserved');
    }
);

$mapped(
    'next export initializes native documents',
    'javascript/test/next_test.ts',
    5,
    'should expose a next export to maintain backwards compatiblity with 2.0',
    function () use ($port): void {
        $next = $port->next();
        $doc = $next->init('aaaaaa');

        truthy($next instanceof NativePort, 'next export should expose a native port instance');
        truthy($doc instanceof Document, 'next export should support init');
        sameArray($doc->toArray(), [], 'next init should create an empty native document');
    }
);

$mapped(
    'next export has the same public API as the main port',
    'javascript/test/next_test.ts',
    9,
    'should have the same types as the main export',
    function () use ($port): void {
        $next = $port->next();
        $mainMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($port))->getMethods(ReflectionMethod::IS_PUBLIC)
        );
        $nextMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($next))->getMethods(ReflectionMethod::IS_PUBLIC)
        );
        sort($mainMethods);
        sort($nextMethods);

        sameArray($nextMethods, $mainMethods, 'next export should expose the same public method surface as the main port');
    }
);

$mapped(
    'basic RawString aliases ImmutableString semantics',
    'javascript/test/basic_test.ts',
    821,
    'should export RawString and isRawString for backwards compatibility',
    function () use ($port): void {
        $raw = $port->rawString('xyz');

        truthy($raw instanceof RawString, 'rawString should construct the backwards-compatible type');
        truthy($port->isRawString($raw), 'isRawString should accept RawString instances');
        truthy($port->isImmutableString($raw), 'RawString should share ImmutableString predicate semantics');
    }
);

$mapped(
    'basic immutable string predicate distinguishes raw strings',
    'javascript/test/basic_test.ts',
    829,
    'should export a predicate to check if something is an immutablestring',
    function () use ($port): void {
        $doc = $port->from(
            [
                'foo' => $port->immutableString('someval2'),
                'bar' => 'notanimmutablestring',
            ],
            'aaaaaa'
        );
        $value = $port->toJS($doc);

        truthy($port->isImmutableString($value['foo']), 'immutable string values should satisfy the predicate');
        truthy(! $port->isImmutableString($value['bar']), 'plain strings should not satisfy the predicate');

        $doc = $port->set($doc, 'baz', 1);
        $changedValue = $port->toJS($doc);
        truthy($port->isImmutableString($changedValue['foo']), 'changed document should preserve immutable string values');
        truthy(! $port->isImmutableString($changedValue['bar']), 'changed document should preserve plain string values');
    }
);

$mapped(
    'basic transaction rollback preserves the original document',
    'javascript/test/basic_test.ts',
    842,
    'it should be able to roll back a transaction',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        $savedBefore = $port->save($doc);

        throwsLike(
            static function () use ($port, $doc): void {
                $port->changeTransaction(
                    $doc,
                    static function (Document $draft): void {
                        $draft->set('key', 'value');
                        throw new RangeException('no');
                    }
                );
            },
            'no',
            'transaction should rethrow the callback failure'
        );

        same($port->save($doc), $savedBefore, 'failed transaction should leave original document save bytes unchanged');
    }
);

$mapped(
    'basic clone does not copy patch callbacks',
    'javascript/test/basic_test.ts',
    384,
    'should not copy the patchCallback',
    function () use ($port): void {
        $patches = [];
        $callbackPort = $port->withPatchCallback(static function (array $patchBatch) use (&$patches): void {
            $patches[] = $patchBatch;
        });

        $doc = $callbackPort->init('aaaaaa');
        $clone = $port->clone($doc, 'bbbbbb');
        $clone = $port->set($clone, 'foo', 'bar');

        sameArray($patches, [], 'cloned documents should not carry a patch callback from the originating port');
        sameArray($clone->toArray(), ['foo' => 'bar'], 'clone should remain editable after dropping the callback association');
    }
);

$mapped(
    'basic list convenience methods update materialized arrays',
    'javascript/test/basic_test.ts',
    260,
    'have many list methods',
    function () use ($port): void {
        $doc = $port->from(['list' => [1, 2, 3]], 'aaaaaa');

        $doc = $port->spliceList($doc, 'list', 1, 1, [9, 10]);
        sameArray($doc->toArray(), ['list' => [1, 9, 10, 3]], 'spliceList should replace the requested list span');

        $doc = $port->pushList($doc, 'list', [11, 12]);
        sameArray($doc->toArray(), ['list' => [1, 9, 10, 3, 11, 12]], 'pushList should append values');

        $doc = $port->unshiftList($doc, 'list', [2, 2]);
        sameArray($doc->toArray(), ['list' => [2, 2, 1, 9, 10, 3, 11, 12]], 'unshiftList should prepend values');

        $doc = $port->shiftList($doc, 'list');
        sameArray($doc->toArray(), ['list' => [2, 1, 9, 10, 3, 11, 12]], 'shiftList should remove the first value');

        $doc = $port->insertListElements($doc, 'list', 3, [100, 101]);
        sameArray($doc->toArray(), ['list' => [2, 1, 9, 100, 101, 10, 3, 11, 12]], 'insertListElements should insert values at the requested offset');
    }
);

$mapped(
    'basic proxy lists behave like PHP arrays',
    'javascript/test/basic_test.ts',
    412,
    'behave like arrays',
    function () use ($port): void {
        $doc = $port->from([
            'chars' => ['a', 'b', 'c'],
            'numbers' => [20, 3, 100],
            'repeats' => [20, 20, 3, 3, 3, 3, 100, 100],
        ], 'aaaaaa');
        $visited = [];

        sameArray($port->listConcat($doc, 'chars', [1, 2]), ['a', 'b', 'c', 1, 2], 'listConcat should append supplied values');
        sameArray($port->listMap($doc, 'chars', static fn (mixed $value): string => $value . '!'), ['a!', 'b!', 'c!'], 'listMap should map string values');
        sameArray($port->listMap($doc, 'numbers', static fn (mixed $value): int => $value + 10), [30, 13, 110], 'listMap should map numeric values');
        same($port->listJoin($doc, 'numbers'), '20,3,100', 'listJoin should match default array stringification');
        same($port->listJoin($doc, 'numbers', '|'), '20|3|100', 'listJoin should honor custom separators');
        $port->listForEach($doc, 'numbers', static function (mixed $value) use (&$visited): void {
            $visited[] = $value;
        });
        sameArray($visited, [20, 3, 100], 'listForEach should visit every item in order');
        truthy($port->listEvery($doc, 'numbers', static fn (mixed $value): bool => $value > 1), 'listEvery should return true when every item matches');
        truthy(! $port->listEvery($doc, 'numbers', static fn (mixed $value): bool => $value > 10), 'listEvery should return false when an item fails');
        sameArray($port->listFilter($doc, 'numbers', static fn (mixed $value): bool => $value > 10), [20, 100], 'listFilter should preserve matching values');
        same($port->listFind($doc, 'repeats', static fn (mixed $value): bool => $value < 10), 3, 'listFind should return the first matching value');
        same($port->listFind($doc, 'repeats', static fn (mixed $value): bool => $value < 0), null, 'listFind should return null when no value matches');
        same($port->listFindIndex($doc, 'repeats', static fn (mixed $value): bool => $value < 10), 2, 'listFindIndex should return the first matching index');
        same($port->listFindIndex($doc, 'repeats', static fn (mixed $value): bool => $value < 0), -1, 'listFindIndex should return -1 when no value matches');
        truthy($port->listIncludes($doc, 'numbers', 3), 'listIncludes should find present values');
        truthy(! $port->listIncludes($doc, 'numbers', -3), 'listIncludes should reject absent values');
        truthy($port->listSome($doc, 'numbers', static fn (mixed $value): bool => $value === 3), 'listSome should return true for a matching value');
        truthy(! $port->listSome($doc, 'numbers', static fn (mixed $value): bool => $value < 0), 'listSome should return false when no value matches');
        same($port->listReduce($doc, 'numbers', static fn (mixed $sum, mixed $value): int => $sum + $value, 100), 223, 'listReduce should fold left to right');
        same($port->listReduce($doc, 'repeats', static fn (mixed $sum, mixed $value): int => $sum + $value, 100), 352, 'listReduce should fold repeated values');
        same($port->listReduce($doc, 'chars', static fn (mixed $sum, mixed $value): string => $sum . $value, '='), '=abc', 'listReduce should fold strings left to right');
        same($port->listReduceRight($doc, 'chars', static fn (mixed $sum, mixed $value): string => $sum . $value, '='), '=cba', 'listReduceRight should fold strings right to left');
        same($port->listReduceRight($doc, 'numbers', static fn (mixed $sum, mixed $value): int => $sum + $value, 100), 223, 'listReduceRight should fold numeric values right to left');
        same($port->listLastIndexOf($doc, 'repeats', 3), 5, 'listLastIndexOf should return the final matching index');
        same($port->listLastIndexOf($doc, 'repeats', 3, 3), 3, 'listLastIndexOf should honor fromIndex');

        [$doc, $filledNumbers] = $port->fillListWithValues($doc, 'numbers', -1, 1, 2);
        sameArray($filledNumbers, [20, -1, 100], 'fillListWithValues should return the filled list value');
        [$doc, $filledChars] = $port->fillListWithValues($doc, 'chars', 'z', 1, 100);
        sameArray($filledChars, ['a', 'z', 'z'], 'fillListWithValues should clamp the fill end to the list length');
        sameArray($doc->toArray()['numbers'], [20, -1, 100], 'fillListWithValues should update the document list');
        sameArray($doc->toArray()['chars'], ['a', 'z', 'z'], 'fillListWithValues should update string lists');
    }
);

$mapped(
    'proxy list entries iterator exposes indexes and values',
    'javascript/test/proxies.ts',
    28,
    'should return iterable entries',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');

        sameArray($port->listEntries($doc, 'list'), [[0, 'a'], [1, 'b'], [2, 'c']], 'list entries should expose index/value pairs in order');
    }
);

$mapped(
    'proxy list values iterator exposes ordered values',
    'javascript/test/proxies.ts',
    41,
    'should return iterable values',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');

        sameArray($port->listValues($doc, 'list'), ['a', 'b', 'c'], 'list values should expose ordered list contents');
    }
);

$mapped(
    'proxy list keys iterator exposes ordered indexes',
    'javascript/test/proxies.ts',
    53,
    'should return iterable keys',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');

        sameArray($port->listKeys($doc, 'list'), [0, 1, 2], 'list keys should expose contiguous indexes');
    }
);

$rustMapped(
    'rust list range bounds slice ordered values',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:iter-list-range-tests-list-range-bounds',
    'iter::list_range::tests::list_range_bounds',
    function () use ($port): void {
        $doc = $port->from(['list' => [1, 2, 3, 4, 5]], 'aaaaaa');

        sameArray($port->listRange($doc, 'list'), [1, 2, 3, 4, 5], 'unbounded list range should yield all values');
        sameArray($port->listRange($doc, 'list', 2), [3, 4, 5], 'list range with lower bound should yield the suffix');
        sameArray($port->listRange($doc, 'list', 1, 4), [2, 3, 4], 'list range should treat the upper bound as exclusive by default');
        sameArray($port->listRange($doc, 'list', null, 3), [1, 2, 3], 'list range with only an upper bound should yield the prefix');
        sameArray($port->listRange($doc, 'list', null, 3, true), [1, 2, 3, 4], 'inclusive upper bound should include the requested end index');
        sameArray($port->listRange($doc, 'list', 1, 3, true), [2, 3, 4], 'inclusive bounded range should include both requested endpoints');
    },
    'rust/automerge/src/iter/list_range.rs'
);

$rustMapped(
    'rust list range reports conflicting element flags',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:iter-list-range-tests-list-range-conflict',
    'iter::list_range::tests::list_range_conflict',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaaaa'), 'list', [1, 2, 3, 4, 5]);
        $doc2 = $port->clone($doc1, 'bbbbbbbb');

        $doc2 = $port->setListElement($doc2, 'list', 3, 11);
        $doc1 = $port->setListElement($doc1, 'list', 3, 10);
        $merged = $port->mergeDocuments($doc2, $doc1);
        $range = $port->listRangeEntries($merged, 'list');

        sameArray(array_column($range, 'value'), [1, 2, 3, 11, 5], 'conflicting list range should expose the visible values in order');
        sameArray(array_column($range, 'conflict'), [false, false, false, true, false], 'conflicting list range should flag only the conflicted element');
    },
    'rust/automerge/src/iter/list_range.rs'
);

$mapped(
    'proxy list indexOf returns matching string index',
    'javascript/test/proxies.ts',
    72,
    'should return the index of a value for a string in a list of strings',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');

        same($port->listIndexOf($doc, 'list', 'b'), 1, 'listIndexOf should return the matching string index');
    }
);

$mapped(
    'proxy list indexOf returns -1 for a missing value',
    'javascript/test/proxies.ts',
    78,
    'should return -1 if the value is not found',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');

        same($port->listIndexOf($doc, 'list', 'd'), -1, 'listIndexOf should return -1 for missing strings');
    }
);

$mapped(
    'proxy list splice removes a defined number of entries',
    'javascript/test/proxies.ts',
    86,
    'should be able to remove a defined number of list entries',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');
        [$doc, $deleted] = $port->spliceListWithDeleted($doc, 'list', 1, 1);

        sameArray($deleted, ['b'], 'spliceListWithDeleted should return removed values');
        sameArray($doc->toArray()['list'], ['a', 'c'], 'spliceListWithDeleted should remove the requested span');
    }
);

$mapped(
    'proxy list splice replaces removed entries with new values',
    'javascript/test/proxies.ts',
    95,
    'should be able to remove a defined number of list entries and add new ones',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');
        [$doc, $deleted] = $port->spliceListWithDeleted($doc, 'list', 1, 1, ['d', 'e']);

        sameArray($deleted, ['b'], 'replacement splice should return the deleted value');
        sameArray($doc->toArray()['list'], ['a', 'd', 'e', 'c'], 'replacement splice should materialize inserted values');
    }
);

$mapped(
    'proxy list splice inserts new values without deletion',
    'javascript/test/proxies.ts',
    104,
    'should be able to insert new values',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');
        [$doc, $deleted] = $port->spliceListWithDeleted($doc, 'list', 1, 0, ['d', 'e']);

        sameArray($deleted, [], 'insert-only splice should return no deleted values');
        sameArray($doc->toArray()['list'], ['a', 'd', 'e', 'b', 'c'], 'insert-only splice should insert at the requested offset');
    }
);

$mapped(
    'proxy list splice with only a start removes through the end',
    'javascript/test/proxies.ts',
    113,
    'should work with only a start parameter',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');
        [$doc, $deleted] = $port->spliceListWithDeleted($doc, 'list', 1);

        sameArray($deleted, ['b', 'c'], 'start-only splice should return every removed tail value');
        sameArray($doc->toArray()['list'], ['a'], 'start-only splice should leave the prefix');
    }
);

$mapped(
    'proxy list splice rejects undefined inserted values',
    'javascript/test/proxies.ts',
    122,
    'should throw a useful RangeError when attempting to splice undefined values',
    function () use ($port): void {
        $doc = $port->from(['list' => []], 'aaaaaa');

        throwsLike(
            static fn (): Document => $port->insertListElements($doc, 'list', 0, [5, $port->undefined()]),
            'Cannot assign undefined value at /list at index 1 in the input',
            'list splice should reject undefined inserted values with an input index'
        );
    }
);

$mapped(
    'proxy recursive document assignment is rejected',
    'javascript/test/proxies.ts',
    16,
    'should throw a useful RangeError when attempting to set a document inside itself',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');

        throwsLike(
            static fn (): Document => $port->set($doc, 'doc', $doc),
            'Cannot create a reference to an existing document object',
            'assigning an existing native document as a nested value should be rejected'
        );
    }
);

$mapped(
    'proxy map allows null values',
    'javascript/test/proxies.ts',
    133,
    'does allow null values',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'foo', null);

        same($doc->toArray()['foo'], null, 'root map should preserve an explicit null value');
    }
);

$mapped(
    'proxy map rejects undefined values',
    'javascript/test/proxies.ts',
    141,
    'does not allow undefined values',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');

        throwsLike(
            static fn (): Document => $port->set($doc, 'foo', $port->undefined()),
            'Cannot assign undefined value at /foo',
            'root map should reject undefined values'
        );
    }
);

$mapped(
    'proxy map undefined errors include the property path',
    'javascript/test/proxies.ts',
    150,
    'should print the property path in the error when setting an undefined key',
    function () use ($port): void {
        $doc = $port->from(['map' => []], 'aaaaaa');

        throwsLike(
            static fn (): Document => $port->setNested($doc, ['map', 'a'], $port->undefined()),
            'Cannot assign undefined value at /map/a',
            'nested map undefined errors should include the property path'
        );
    }
);

$mapped(
    'proxy list undefined errors include the property path',
    'javascript/test/proxies.ts',
    161,
    'should print the property path in the error when setting an undefined key',
    function () use ($port): void {
        $doc = $port->from(['list' => []], 'aaaaaa');

        throwsLike(
            static fn (): Document => $port->setListElement($doc, 'list', 0, $port->undefined()),
            'Cannot assign undefined value at /list/0',
            'list undefined errors should include the list index path'
        );
    }
);

$mapped(
    'proxy list at returns values by index',
    'javascript/test/proxies.ts',
    170,
    'should support .at() to access values',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b']], 'aaaaaa');

        same($port->listAt($doc, 'list', 0), 'a', 'listAt should return the value at the requested index');
        same($port->listAt($doc, 'list', -1), 'b', 'listAt should support negative indexes from the end');
    }
);

$mapped(
    'proxy structured clone input materializes as a nested map',
    'javascript/test/proxies.ts',
    181,
    'should support objects cloned with structuredClone',
    function () use ($port): void {
        $doc = $port->from(['map' => ['key' => 'value', 'number' => 2]], 'aaaaaa');

        sameArray($doc->toArray(), ['map' => ['key' => 'value', 'number' => 2]], 'array-cloned map input should materialize as nested data');
    }
);

$mapped(
    'basic backend access materializes the document',
    'javascript/test/basic_test.ts',
    285,
    'allows access to the backend',
    function () use ($port): void {
        $doc = $port->from(['hello' => 'world'], 'aaaaaa');

        sameArray($port->getBackend($doc)->materialize(), ['hello' => 'world'], 'backend view should materialize the visible document');
    }
);

$mapped(
    'basic lists and text expose indexOf semantics',
    'javascript/test/basic_test.ts',
    292,
    'lists and text have indexof',
    function () use ($port): void {
        $doc = $port->from([
            'list' => [0, 1, 2, 3, 4, 5, 6],
            'text' => 'hello world',
        ], 'aaaaaa');

        same($port->listIndexOf($doc, 'list', 5), 5, 'listIndexOf should return the matching list offset');
        same($port->listIndexOf($doc, 'list', 42), -1, 'listIndexOf should return -1 for a missing value');
        same($port->textIndexOf($doc, 'text', 'world'), 6, 'textIndexOf should return the matching text offset');
        same($port->textIndexOf($doc, 'text', 'mars'), -1, 'textIndexOf should return -1 for a missing substring');
    }
);

$mapped(
    'basic mark and unmark do not mutate path arguments',
    'javascript/test/basic_test.ts',
    656,
    'mark/unmark',
    function () use ($port): void {
        $path = ['text'];
        $pathCopy = $path;
        $doc = $port->from(['text' => 'hello'], 'aaaaaa');
        $doc = $port->mark($doc, $path, 0, 2, 'bold', true);

        sameArray($path, $pathCopy, 'mark should not mutate the supplied path array');
        sameArray($port->marks($doc, ['text']), [['name' => 'bold', 'value' => true, 'start' => 0, 'end' => 2]], 'mark should record native text metadata');

        $doc = $port->unmark($doc, $path, 0, 2, 'bold');
        sameArray($path, $pathCopy, 'unmark should not mutate the supplied path array');
        sameArray($port->marks($doc, ['text']), [], 'unmark should remove native text metadata');
    }
);

$mapped(
    'basic marks lookup does not mutate path arguments',
    'javascript/test/basic_test.ts',
    673,
    'marks',
    function () use ($port): void {
        $path = ['text'];
        $pathCopy = $path;
        $doc = $port->mark($port->from(['text' => 'hello'], 'aaaaaa'), $path, 0, 2, 'bold', true);

        sameArray($port->marks($doc, $path), [['name' => 'bold', 'value' => true, 'start' => 0, 'end' => 2]], 'marks should return native text mark metadata');
        sameArray($path, $pathCopy, 'marks lookup should not mutate the supplied path array');
    }
);

$mapped(
    'basic marksAt lookup does not mutate path arguments',
    'javascript/test/basic_test.ts',
    678,
    'marksAt',
    function () use ($port): void {
        $path = ['text'];
        $pathCopy = $path;
        $doc = $port->mark($port->from(['text' => 'hello'], 'aaaaaa'), $path, 0, 2, 'bold', true);

        sameArray($port->marksAt($doc, $path, 1), ['bold' => true], 'marksAt should return active marks at the requested offset');
        sameArray($port->marksAt($doc, $path, 3), [], 'marksAt should omit marks outside their range');
        sameArray($path, $pathCopy, 'marksAt lookup should not mutate the supplied path array');
    }
);

$mapped(
    'marks patch callbacks expose mark splits and marked load splices',
    'javascript/test/marks.ts',
    6,
    'should allow marks that can be seen in patches',
    function () use ($port): void {
        $value = 'bold';
        $callbacks = [];
        $doc1 = $port->initWithPatchCallback(
            static function (array $patches) use (&$callbacks): void {
                $callbacks[] = $patches;
            },
            'aaaaaa'
        );
        $doc1 = $port->change(
            $doc1,
            static function (Document $draft): void {
                $draft->set('x', 'the quick fox jumps over the lazy dog');
            }
        );
        $doc1 = $port->change(
            $doc1,
            static function (Document $draft) use ($value): void {
                $draft->markText(['x'], [['name' => 'font-weight', 'start' => 5, 'end' => 10, 'value' => $value]]);
            }
        );
        $doc1 = $port->change(
            $doc1,
            static function (Document $draft): void {
                $draft->unmarkText(['x'], 'font-weight', 7, 9);
            }
        );

        sameArray(
            $callbacks[1],
            [[
                'action' => 'mark',
                'path' => ['x'],
                'marks' => [['name' => 'font-weight', 'value' => $value, 'start' => 5, 'end' => 10]],
            ]],
            'mark callback should expose the added mark range'
        );
        sameArray(
            $callbacks[2],
            [[
                'action' => 'mark',
                'path' => ['x'],
                'marks' => [['name' => 'font-weight', 'value' => null, 'start' => 7, 'end' => 9]],
            ]],
            'unmark callback should expose the removed mark subrange'
        );
        sameArray(
            $port->marks($doc1, ['x']),
            [
                ['name' => 'font-weight', 'value' => $value, 'start' => 5, 'end' => 7],
                ['name' => 'font-weight', 'value' => $value, 'start' => 9, 'end' => 10],
            ],
            'partial unmark should split the original mark'
        );

        $callbacks = [];
        $doc2 = $port->initWithPatchCallback(
            static function (array $patches) use (&$callbacks): void {
                $callbacks[] = $patches;
            },
            'bbbbbb'
        );
        $doc2 = $port->loadIncremental($doc2, $port->save($doc1));

        sameArray(
            $callbacks[0][2],
            [
                'action' => 'splice',
                'path' => ['x', 5],
                'value' => 'ui',
                'marks' => ['font-weight' => $value],
            ],
            'incremental load should segment marked text splices with mark metadata'
        );
        sameArray(
            $port->marks($doc2, ['x']),
            [
                ['name' => 'font-weight', 'value' => $value, 'start' => 5, 'end' => 7],
                ['name' => 'font-weight', 'value' => $value, 'start' => 9, 'end' => 10],
            ],
            'loaded document should preserve split marks'
        );
    }
);

$mapped(
    'marks shift across unicode text splices',
    'javascript/test/marks.ts',
    73,
    'should do unicode sensibly',
    function () use ($port): void {
        $doc = $port->from(['content' => '😀😀'], 'aaaaaa');

        $doc = $port->change(
            $doc,
            static function (Document $draft): void {
                $draft->markText(['content'], [['name' => 'bold', 'value' => true, 'start' => 2, 'end' => 4]]);
                $draft->spliceText('content', 0, 0, '🙃');
            }
        );
        sameArray(
            $port->marks($doc, ['content']),
            [['name' => 'bold', 'value' => true, 'start' => 4, 'end' => 6]],
            'unicode insertion before a marked range should shift mark offsets by UTF-16 code units'
        );

        $doc = $port->change(
            $doc,
            static function (Document $draft): void {
                $draft->unmarkText(['content'], 'bold', 4, 6);
            }
        );
        sameArray($port->marks($doc, ['content']), [], 'unmark should remove the shifted unicode mark range');
    }
);

$mapped(
    'marks expand at splice boundaries and report marked splice patches',
    'javascript/test/marks.ts',
    107,
    'patches properly report marks on end of expand true',
    function () use ($port): void {
        $patches = [];
        $callbackPort = $port->withPatchCallback(
            static function (array $patchBatch) use (&$patches): void {
                array_push($patches, ...$patchBatch);
            }
        );
        $doc = $callbackPort->from(['text' => 'aaabbbccc'], 'aaaaaa');

        $doc = $callbackPort->change(
            $doc,
            static function (Document $draft): void {
                $draft->markText(['text'], [['name' => 'bold', 'value' => true, 'start' => 3, 'end' => 6, 'expand' => 'both']]);
            }
        );
        sameArray(
            $port->marks($doc, ['text']),
            [['name' => 'bold', 'value' => true, 'start' => 3, 'end' => 6]],
            'expand metadata should not leak through the public marks API'
        );

        $doc = $callbackPort->change(
            $doc,
            static function (Document $draft): void {
                $draft->spliceText('text', 6, 0, '<');
                $draft->spliceText('text', 3, 0, '>');
            }
        );
        sameArray(
            $port->marks($doc, ['text']),
            [['name' => 'bold', 'value' => true, 'start' => 3, 'end' => 8]],
            'expand=both mark should include insertions at both boundaries'
        );
        sameArray(
            array_pop($patches),
            ['action' => 'splice', 'path' => ['text', 3], 'value' => '>', 'marks' => ['bold' => true]],
            'patch callback should report marks on the start-boundary insertion'
        );
        sameArray(
            array_pop($patches),
            ['action' => 'splice', 'path' => ['text', 6], 'value' => '<', 'marks' => ['bold' => true]],
            'patch callback should report marks on the end-boundary insertion'
        );
        sameArray($port->marksAt($doc, ['text'], 2), [], 'marksAt should omit text before the expanded range');
        sameArray($port->marksAt($doc, ['text'], 3), ['bold' => true], 'marksAt should include the start-boundary insertion');
        sameArray($port->marksAt($doc, ['text'], 5), ['bold' => true], 'marksAt should include interior text');
        sameArray($port->marksAt($doc, ['text'], 7), ['bold' => true], 'marksAt should include the end-boundary insertion');
        sameArray($port->marksAt($doc, ['text'], 8), [], 'marksAt should omit text after the expanded range');
    }
);

$mapped(
    'basic path splice does not mutate path arguments',
    'javascript/test/basic_test.ts',
    631,
    'splice',
    function () use ($port): void {
        $path = ['text'];
        $pathCopy = $path;
        $doc = $port->from(['text' => 'abc'], 'aaaaaa');
        $doc = $port->spliceAtPath($doc, $path, 0, 0, 'z');

        sameArray($path, $pathCopy, 'spliceAtPath should not mutate the supplied path array');
        same($doc->toArray()['text'], 'zabc', 'spliceAtPath should insert text at the requested path');
    }
);

$mapped(
    'basic path updateText does not mutate path arguments',
    'javascript/test/basic_test.ts',
    638,
    'updateText',
    function () use ($port): void {
        $path = ['text'];
        $pathCopy = $path;
        $doc = $port->from(['text' => 'hello world'], 'aaaaaa');
        $doc = $port->updateTextAtPath($doc, $path, 'hello earth');

        sameArray($path, $pathCopy, 'updateTextAtPath should not mutate the supplied path array');
        same($doc->toArray()['text'], 'hello earth', 'updateTextAtPath should replace text at the requested path');
    }
);

$mapped(
    'cursor from accepts an existing document as shallow copy input',
    'javascript/test/cursors.ts',
    24,
    'should be able to pass a doc to from() to make a shallow copy',
    function () use ($port): void {
        $date = new DateTimeImmutable('2026-05-22T05:12:00.000Z');
        $doc1 = $port->from([
            'text' => 'The sly fox jumped over the lazy dog',
            'x' => 5,
            'y' => $date,
            'z' => [1, 2, 3, ['alpha' => 'bravo']],
        ], 'aaaaaa');
        $doc2 = $port->from($doc1, 'bbbbbb');

        $original = $doc1->toArray();
        $copy = $doc2->toArray();
        same($copy['text'], $original['text'], 'from(Document) should copy string root values');
        same($copy['x'], $original['x'], 'from(Document) should copy numeric root values');
        sameArray($copy['z'], $original['z'], 'from(Document) should copy nested list/map values');
        truthy($copy['y'] instanceof DateTimeInterface, 'from(Document) should copy DateTime values');
        same(dateMillis($copy['y']), dateMillis($original['y']), 'from(Document) should preserve copied DateTime timestamps');
        truthy($doc2 !== $doc1, 'from(Document) should return a distinct native document');
    }
);

$mapped(
    'cursor Date values from one document can be reused in another change',
    'javascript/test/cursors.ts',
    122,
    'should allow dates from an existing document to be used in another document',
    function () use ($port): void {
        $date = new DateTimeImmutable('2026-05-22T05:12:30.123Z');
        $original = $port->set($port->init('aaaaaa'), 'date', $date);
        $original = $port->set($original, 'dates', [$date]);
        $changed = $port->set($original, 'anotherDate', $original->toArray()['date']);
        $changed = $port->setListElement($changed, 'dates', 0, $original->toArray()['dates'][0]);

        $materialized = $changed->toArray();
        truthy($materialized['anotherDate'] instanceof DateTimeInterface, 'reused date should remain a native DateTime value');
        same(dateMillis($materialized['anotherDate']), dateMillis($date), 'reused date should preserve its timestamp');
        truthy($materialized['dates'][0] instanceof DateTimeInterface, 'reused list date should remain a native DateTime value');
        same(dateMillis($materialized['dates'][0]), dateMillis($date), 'reused list date should preserve its timestamp');
    }
);

$mapped(
    'cursor values can be used in splice calls after earlier text edits',
    'javascript/test/cursors.ts',
    5,
    'can use cursors in splice calls',
    function () use ($port): void {
        $doc = $port->from(['value' => 'The sly fox jumped over the lazy dog'], 'aaaaaa');
        $cursor = $port->getCursor($doc, ['value'], 19);

        $doc = $port->spliceAtPath($doc, ['value'], 0, 3, 'Has the');
        same($doc->toArray()['value'], 'Has the sly fox jumped over the lazy dog', 'initial splice should rewrite the text prefix');

        $doc = $port->spliceAtPath($doc, ['value'], $cursor, 0, 'right ');
        same($doc->toArray()['value'], 'Has the sly fox jumped right over the lazy dog', 'cursor splice should track the original offset through the prefix edit');
    }
);

$mapped(
    'cursor values support common text operations with backward deletes',
    'javascript/test/cursors.ts',
    37,
    'can use cursors in common text operations',
    function () use ($port): void {
        $doc = $port->from(['value' => 'The sly fox jumped over the lazy dog'], 'aaaaaa');
        $doc2 = $port->clone($doc, 'bbbbbb');
        $cursor = $port->getCursor($doc, ['value'], 8);

        $doc = $port->spliceAtPath($doc, ['value'], $cursor, 0, 'o');
        $doc = $port->spliceAtPath($doc, ['value'], $cursor, 0, 'l');
        $doc = $port->spliceAtPath($doc, ['value'], $cursor, 0, 'e');

        $doc2 = $port->spliceAtPath($doc2, ['value'], 3, -3, 'A');

        $doc = $port->mergeDocuments($doc, $doc2);
        $doc = $port->spliceAtPath($doc, ['value'], $cursor, -1, 'd');
        $doc = $port->spliceAtPath($doc, ['value'], $cursor, 0, ' ');

        same($doc->toArray()['value'], 'A sly old fox jumped over the lazy dog', 'cursor should survive common splice, backward-delete, and merge operations');
    }
);

$mapped(
    'cursor splices use JavaScript UTF-16 string indices',
    'javascript/test/cursors.ts',
    61,
    'should use javascript string indices',
    function () use ($port): void {
        $doc = $port->from(['value' => "🇬🇧🇩🇪"], 'aaaaaa');
        $cursor = $port->getCursor($doc, ['value'], 4);

        same($port->getCursorPosition($doc, ['value'], $cursor), 4, 'cursor position should report the JavaScript string index before the second flag');

        $doc = $port->change(
            $doc,
            static function (Document $draft) use ($port, $cursor): void {
                $port->spliceInChange($draft, ['value'], $cursor, -2, '');
                $port->spliceInChange($draft, ['value'], $cursor, -2, '');
                $port->spliceInChange($draft, ['value'], $cursor, 0, "🇫🇷");
            }
        );

        same($doc->toArray()['value'], "🇫🇷🇩🇪", 'cursor splice should treat flag emoji offsets as JavaScript UTF-16 indices');
        same($port->getCursorPosition($doc, ['value'], $cursor), 4, 'cursor should remain before the second flag after replacement');
    }
);

$mapped(
    'cursor patch callbacks report their source operation',
    'javascript/test/cursors.ts',
    76,
    'patch callbacks inform where they came from',
    function () use ($port): void {
        $callbacks = [];
        $patchCallback = static function (array $patches, array $info) use (&$callbacks): void {
            if ($patches !== []) {
                $callbacks[] = $info['source'] ?? null;
            }
        };

        $doc1 = $port->fromWithPatchCallback(['hello' => 'world'], $patchCallback, 'aaaaaa');
        $heads1 = $port->getHeads($doc1);
        $doc2 = $port->cloneWithPatchCallback($doc1, $patchCallback, 'bbbbbb');
        $doc2 = $port->change(
            $doc2,
            static function (Document $draft): void {
                $draft->set('a', 'b');
            }
        );
        $doc2 = $port->setAtHeads($doc2, $heads1, 'b', 'c');
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $doc2 = $port->change(
            $doc2,
            static function (Document $draft): void {
                $draft->set('x', 'y');
            }
        );
        $doc1 = $port->loadIncremental($doc1, $port->saveIncremental($doc2));
        $doc2 = $port->change(
            $doc2,
            static function (Document $draft): void {
                $draft->set('n', 'm');
            }
        );

        [$sync1, $doc2ToDoc1] = $port->generateSyncMessage($doc2, $port->initSyncState());
        truthy($doc2ToDoc1 !== null, 'source callback test should generate a sync message with the final remote change');
        [$doc1] = $port->receiveSyncMessage($doc1, $port->initSyncState(), $doc2ToDoc1, $patchCallback);
        same($doc1->toArray()['n'], 'm', 'source callback test should apply the final remote sync change');

        sameArray(
            $callbacks,
            ['from', 'change', 'changeAt', 'merge', 'change', 'loadIncremental', 'change', 'receiveSyncMessage'],
            'patch callbacks should identify each source operation in order'
        );
    }
);

$mapped(
    'cursor start and end sentinels can drive text splices',
    'javascript/test/cursors.ts',
    178,
    'should allow for usage of start/end cursors',
    function () use ($port): void {
        $doc = $port->from(['text' => 'abc'], 'aaaaaa');
        $end = $port->getCursor($doc, ['text'], 'end');
        $start = $port->getCursor($doc, ['text'], 'start');

        $doc = $port->spliceAtPath($doc, ['text'], $end, 0, 'def');
        same($doc->toArray()['text'], 'abcdef', 'end cursor should resolve to the current text end');

        $doc = $port->spliceAtPath($doc, ['text'], $start, 0, 'hello');
        same($doc->toArray()['text'], 'helloabcdef', 'start cursor should resolve to the current text start');
    }
);

$mapped(
    'cursor creation clamps negative indices to start',
    'javascript/test/cursors.ts',
    212,
    'should convert negative indices into a start cursor',
    function () use ($port): void {
        $doc = $port->from(['text' => 'is awesome'], 'aaaaaa');
        $cursor = $port->getCursor($doc, ['text'], -1);

        $doc = $port->spliceAtPath($doc, ['text'], $cursor, 0, 'Automerge ');
        same($doc->toArray()['text'], 'Automerge is awesome', 'negative cursor index should behave like a start cursor');
    }
);

$mapped(
    'cursor creation clamps too-large indices to end',
    'javascript/test/cursors.ts',
    223,
    'should convert indices >= string length into an end cursor',
    function () use ($port): void {
        $doc = $port->from(['text' => 'Alex'], 'aaaaaa');
        $cursorPastEnd = $port->getCursor($doc, ['text'], 1337);
        $cursorAtEnd = $port->getCursor($doc, ['text'], 4);

        $doc1 = $port->spliceAtPath($doc, ['text'], $cursorPastEnd, 0, ' Good');
        $doc2 = $port->spliceAtPath($port->clone($doc), ['text'], $cursorAtEnd, 0, ' Good');

        same($doc1->toArray()['text'], 'Alex Good', 'out-of-range cursor should resolve to the end of the text');
        same($doc2->toArray()['text'], 'Alex Good', 'string-length cursor should also resolve to the end of the text');
    }
);

$mapped(
    'cursor position resolves against a historical view',
    'javascript/test/cursors.ts',
    135,
    'getCursorPosition should work',
    function () use ($port): void {
        $doc = $port->from(['text' => 'abc'], 'aaaaaa');
        $cursor = $port->getCursor($doc, ['text'], 1);

        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'x');
        $heads = $port->getHeads($doc);
        $doc = $port->spliceAtPath($doc, ['text'], 1, 0, 'y');
        $view = $port->view($doc, $heads);

        same($port->getCursorPosition($view, ['text'], $cursor), 2, 'cursor should resolve against the supplied view heads');
    }
);

$mapped(
    'cursor creation respects view heads for before after start and end',
    'javascript/test/cursors.ts',
    153,
    'getCursor should respect heads',
    function () use ($port): void {
        $doc = $port->from(['text' => 'aaa@bbb'], 'aaaaaa');
        $heads = $port->getHeads($doc);
        $doc = $port->spliceAtPath($doc, ['text'], 3, 1, '~~~');
        $view = $port->view($doc, $heads);

        $before = $port->getCursor($view, ['text'], 3, 'before');
        $after = $port->getCursor($view, ['text'], 3, 'after');
        $start = $port->getCursor($view, ['text'], 'start');
        $end = $port->getCursor($view, ['text'], 'end');

        same($port->getCursorPosition($doc, ['text'], $start), 0, 'start cursor should remain at the beginning after a replacement');
        same($port->getCursorPosition($doc, ['text'], $before), 2, 'before cursor should track to the position before the replaced element');
        same($port->getCursorPosition($doc, ['text'], $after), 6, 'after cursor should track past the replacement insertion');
        same($port->getCursorPosition($doc, ['text'], $end), 9, 'end cursor should track to the current text end');
    }
);

$mapped(
    'cursor move before and after survive text replacement',
    'javascript/test/cursors.ts',
    197,
    'should allow for usage of move before/after',
    function () use ($port): void {
        $doc = $port->from(['text' => 'aaa@bbb'], 'aaaaaa');
        $before = $port->getCursor($doc, ['text'], 3, 'before');
        $after = $port->getCursor($doc, ['text'], 3, 'after');

        $doc = $port->spliceAtPath($doc, ['text'], 3, 1, '~~~');

        same($port->getCursorPosition($doc, ['text'], $before), 2, 'move-before cursor should resolve before the replaced element');
        same($port->getCursorPosition($doc, ['text'], $after), 6, 'move-after cursor should resolve after the replacement');
    }
);

$mapped(
    'basic getCursor does not mutate path arguments',
    'javascript/test/basic_test.ts',
    645,
    'getCursor',
    function () use ($port): void {
        $path = ['text'];
        $pathCopy = $path;
        $doc = $port->from(['text' => 'abc'], 'aaaaaa');
        $cursor = $port->getCursor($doc, $path, 0);

        truthy($cursor !== '', 'getCursor should return a stable cursor string');
        sameArray($path, $pathCopy, 'getCursor should not mutate the supplied path array');
    }
);

$mapped(
    'basic getCursorPosition does not mutate path arguments',
    'javascript/test/basic_test.ts',
    650,
    'getCursorPosition',
    function () use ($port): void {
        $path = ['text'];
        $pathCopy = $path;
        $doc = $port->from(['text' => 'abc'], 'aaaaaa');
        $cursor = $port->getCursor($doc, $path, 1);
        $position = $port->getCursorPosition($doc, $path, $cursor);

        same($position, 1, 'getCursorPosition should decode the native cursor offset');
        sameArray($path, $pathCopy, 'getCursorPosition should not mutate the supplied path array');
    }
);

$mapped(
    'new change API supports simple root assignment',
    'javascript/test/new-change-api.ts',
    5,
    'should be able to make simple changes to a document',
    function () use ($port): void {
        $doc = $port->from(['foo' => 'bar'], 'aaaaaa');
        same($doc->toArray()['foo'], 'bar', 'document should expose the initial root value before the change');
        $doc = $port->set($doc, 'foo', 'baz');

        same($doc->toArray()['foo'], 'baz', 'root assignment should update the materialized value');
    }
);

$mapped(
    'new change API supports insertAt-style list insertion',
    'javascript/test/new-change-api.ts',
    17,
    'should be able to insert into a list',
    function () use ($port): void {
        $doc = $port->from(['list' => []], 'aaaaaa');
        $doc = $port->insertListElements($doc, 'list', 0, ['a']);

        sameArray($doc->toArray()['list'], ['a'], 'list insertion should materialize at the requested index');
    }
);

$mapped(
    'new change API supports deleteAt-style list deletion',
    'javascript/test/new-change-api.ts',
    25,
    'should be able to delete from a list',
    function () use ($port): void {
        $doc = $port->from(['list' => ['a', 'b', 'c']], 'aaaaaa');
        $doc = $port->deleteListElements($doc, 'list', 0);

        sameArray($doc->toArray()['list'], ['b', 'c'], 'list deletion should remove the requested index');
    }
);

$mapped(
    'basic save load and change preserve integer and float edge values',
    'javascript/test/basic_test.ts',
    855,
    'it should be able to handle ints and floats at their limits',
    function () use ($port): void {
        $base = [
            'nan' => NAN,
            'inf' => INF,
            'ninf' => -INF,
            'imax' => $port->bigInt('9223372036854775807'),
            'imin' => $port->bigInt('-9223372036854775808'),
            'umax' => $port->bigInt('18446744073709551615'),
        ];

        $assertEdgeValues = static function (array $actual, string $label): void {
            truthy(is_nan($actual['nan']), $label . ' should preserve NaN');
            same($actual['inf'], INF, $label . ' should preserve positive infinity');
            same($actual['ninf'], -INF, $label . ' should preserve negative infinity');
            truthy($actual['imax'] instanceof BigIntValue, $label . ' should preserve signed 64-bit max as BigIntValue');
            truthy($actual['imin'] instanceof BigIntValue, $label . ' should preserve signed 64-bit min as BigIntValue');
            truthy($actual['umax'] instanceof BigIntValue, $label . ' should preserve unsigned 64-bit max as BigIntValue');
            same($actual['imax']->toString(), '9223372036854775807', $label . ' should preserve signed 64-bit max digits');
            same($actual['imin']->toString(), '-9223372036854775808', $label . ' should preserve signed 64-bit min digits');
            same($actual['umax']->toString(), '18446744073709551615', $label . ' should preserve unsigned 64-bit max digits');
        };

        $doc1 = $port->from($base, 'aaaaaa');
        $assertEdgeValues($doc1->toArray(), 'from()');

        $doc2 = $port->load($port->save($doc1), 'bbbbbb');
        $assertEdgeValues($doc2->toArray(), 'save/load');

        $doc3 = $port->change(
            $port->init('cccccc'),
            static function (Document $draft) use ($base): void {
                foreach ($base as $key => $value) {
                    $draft->set($key, $value);
                }
            }
        );
        $assertEdgeValues($doc3->toArray(), 'change()');
    }
);

$rustBatchSuite = 'rust:tests-batch-insert-rs-target-debug-deps-batch-insert-5dd7718f4978a0d4:';
$rustConvertStringSuite = 'rust:tests-convert-string-to-text-rs-target-debug-deps-convert-string-to-text-1c3ac8392298535c:';
$rustCoreSuite = 'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:';
$rustCurrentStateSuite = 'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:automerge-current-state-tests-';
$rustHydrateSuite = 'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:hydrate-tests-';
$rustIterDocSuite = 'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:iter-doc-tests-';
$rustLegacyOpSuite = 'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:legacy-serde-impls-op-tests-';
$rustOwnedTransactionSuite = 'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:transaction-owned-transaction-tests-';
$rustTransactionInnerSuite = 'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:transaction-inner-tests-';

$rustMapped(
    'rust legacy op serde deserializes object ids',
    $rustLegacyOpSuite . 'test-deserialize-obj',
    'legacy::serde_impls::op::tests::test_deserialize_obj',
    function () use ($port): void {
        $root = $port->legacyDeserializeOp([
            'action' => 'inc',
            'obj' => '_root',
            'key' => 'somekey',
            'value' => 1,
            'pred' => [],
        ]);
        sameArray($root['obj'], ['type' => 'root', 'id' => '_root'], 'legacy serde should decode the root object id');

        $opId = '1@7ef48769b04d47e9a88e98a134d62716';
        $op = $port->legacyDeserializeOp([
            'action' => 'inc',
            'obj' => $opId,
            'key' => 'somekey',
            'value' => 1,
            'pred' => [],
        ]);
        sameArray(
            $op['obj'],
            ['type' => 'op', 'id' => $opId, 'counter' => '1', 'actor' => '7ef48769b04d47e9a88e98a134d62716'],
            'legacy serde should decode operation object ids'
        );

        throwsLike(
            static fn (): array => $port->legacyDeserializeOp([
                'action' => 'inc',
                'obj' => 'notanobject',
                'key' => 'somekey',
                'value' => 1,
                'pred' => [],
            ]),
            'A valid ObjectID',
            'legacy serde should reject invalid object ids'
        );
    },
    'rust/automerge/src/legacy/serde_impls/op.rs'
);

$rustMapped(
    'rust legacy op serde serializes map and element id keys',
    $rustLegacyOpSuite . 'test-serialize-key',
    'legacy::serde_impls::op::tests::test_serialize_key',
    function () use ($port): void {
        sameArray($port->legacySerializeOpKey('somekey'), ['key' => 'somekey'], 'map keys should serialize under key');
        sameArray(
            $port->legacySerializeOpKey(['elemId' => '1@7ef48769b04d47e9a88e98a134d62716']),
            ['elemId' => '1@7ef48769b04d47e9a88e98a134d62716'],
            'list element ids should serialize under elemId'
        );
    },
    'rust/automerge/src/legacy/serde_impls/op.rs'
);

$rustMapped(
    'rust legacy op serde deserializes action values',
    $rustLegacyOpSuite . 'test-deserialize-action',
    'legacy::serde_impls::op::tests::test_deserialize_action',
    function () use ($port): void {
        $decodeAction = static function (array $fields) use ($port): array {
            $op = $fields + [
                'obj' => '_root',
                'key' => 'somekey',
                'pred' => [],
            ];

            return $port->legacyDeserializeOp($op)['action'];
        };

        sameArray(
            $decodeAction(['action' => 'set', 'datatype' => 'uint', 'value' => 123]),
            ['type' => 'put', 'value' => ['type' => 'uint', 'value' => 123]],
            'set uint should decode as a uint put action'
        );
        sameArray(
            $decodeAction(['action' => 'set', 'datatype' => 'int', 'value' => -123]),
            ['type' => 'put', 'value' => ['type' => 'int', 'value' => -123]],
            'set int should decode as an int put action'
        );
        sameArray(
            $decodeAction(['action' => 'set', 'datatype' => 'float64', 'value' => -123]),
            ['type' => 'put', 'value' => ['type' => 'float64', 'value' => -123.0]],
            'set float64 should decode as an f64 put action'
        );
        sameArray(
            $decodeAction(['action' => 'set', 'value' => 'somestring']),
            ['type' => 'put', 'value' => ['type' => 'str', 'value' => 'somestring']],
            'set string should decode as a string put action'
        );
        sameArray(
            $decodeAction(['action' => 'set', 'value' => 1.23]),
            ['type' => 'put', 'value' => ['type' => 'float64', 'value' => 1.23]],
            'set f64 should decode as an f64 put action'
        );
        sameArray(
            $decodeAction(['action' => 'set', 'value' => true]),
            ['type' => 'put', 'value' => ['type' => 'boolean', 'value' => true]],
            'set boolean should decode as a boolean put action'
        );
        throwsLike(
            static fn (): array => $decodeAction(['action' => 'set', 'datatype' => 'counter']),
            'missing field value',
            'set with counter datatype should still require a value'
        );
        sameArray(
            $decodeAction(['action' => 'set', 'datatype' => 'counter', 'value' => 123]),
            ['type' => 'put', 'value' => ['type' => 'counter', 'value' => 123]],
            'set counter should decode as a counter put action'
        );
        throwsLike(
            static fn (): array => $decodeAction(['action' => 'set', 'datatype' => 'counter', 'value' => 'somestring']),
            'an integer',
            'counter datatype should reject string values'
        );
        throwsLike(
            static fn (): array => $decodeAction(['action' => 'set', 'datatype' => 'timestamp', 'value' => 'somestring']),
            'an integer',
            'timestamp datatype should reject string values'
        );
        sameArray(
            $decodeAction(['action' => 'inc', 'datatype' => 'counter', 'value' => 12]),
            ['type' => 'increment', 'value' => 12],
            'inc with counter datatype should decode as an increment'
        );
        sameArray(
            $decodeAction(['action' => 'inc', 'value' => 12]),
            ['type' => 'increment', 'value' => 12],
            'inc without datatype should decode as an increment'
        );
        throwsLike(
            static fn (): array => $decodeAction(['action' => 'inc']),
            'missing field value',
            'inc without value should be rejected'
        );
        sameArray(
            $decodeAction(['action' => 'set', 'value' => null]),
            ['type' => 'put', 'value' => ['type' => 'null', 'value' => null]],
            'set null should decode as a null put action'
        );
    },
    'rust/automerge/src/legacy/serde_impls/op.rs'
);

$rustMapped(
    'rust legacy op serde round trips normalized operations',
    $rustLegacyOpSuite . 'test-round-trips',
    'legacy::serde_impls::op::tests::test_round_trips',
    function () use ($port): void {
        $opId = '1@7ef48769b04d47e9a88e98a134d62716';
        $opObject = ['type' => 'op', 'id' => $opId, 'counter' => '1', 'actor' => '7ef48769b04d47e9a88e98a134d62716'];
        $rootObject = ['type' => 'root', 'id' => '_root'];
        $mapKey = ['type' => 'key', 'value' => 'somekey'];
        $elemKey = ['type' => 'elemId', 'id' => $opId];

        $testcases = [
            [
                'action' => ['type' => 'put', 'value' => ['type' => 'uint', 'value' => 12]],
                'obj' => $rootObject,
                'key' => $mapKey,
                'insert' => false,
                'pred' => [],
            ],
            [
                'action' => ['type' => 'increment', 'value' => 12],
                'obj' => $opObject,
                'key' => $mapKey,
                'insert' => false,
                'pred' => [],
            ],
            [
                'action' => ['type' => 'put', 'value' => ['type' => 'uint', 'value' => 12]],
                'obj' => $opObject,
                'key' => $mapKey,
                'insert' => false,
                'pred' => [$opId],
            ],
            [
                'action' => ['type' => 'increment', 'value' => 12],
                'obj' => $rootObject,
                'key' => $mapKey,
                'insert' => false,
                'pred' => [],
            ],
            [
                'action' => ['type' => 'put', 'value' => ['type' => 'str', 'value' => 'seomthing']],
                'obj' => $opObject,
                'key' => $elemKey,
                'insert' => false,
                'pred' => [$opId],
            ],
        ];

        foreach ($testcases as $index => $testcase) {
            $serialized = $port->legacySerializeOp($testcase);
            $encoded = json_encode($serialized, JSON_THROW_ON_ERROR);
            $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
            $roundTripped = $port->legacyDeserializeOp($decoded);

            sameArray($roundTripped, $testcase, 'legacy op serde testcase ' . $index . ' should round trip');
        }
    },
    'rust/automerge/src/legacy/serde_impls/op.rs'
);

$rustMapped(
    'rust AutoSerde serializes a root map as JSON',
    'rust:doc-tests-automerge:automerge-src-autoserde-rs-autoserde-autoserde-line-9',
    'automerge/src/autoserde.rs AutoSerde example',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');

        same(
            json_encode($port->toJS($doc), JSON_THROW_ON_ERROR),
            '{"key":"value"}',
            'AutoSerde-style root materialization should serialize as the expected JSON object'
        );
    },
    'rust/automerge/src/autoserde.rs'
);

$rustMapped(
    'rust lib address book example saves nested contacts',
    'rust:doc-tests-automerge:automerge-src-lib-rs-line-117',
    'automerge/src/lib.rs address book creation example',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'contacts', [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ]);
        $saved = $port->save($doc);
        $loaded = $port->load($saved, 'bbbbbb');

        sameArray(
            $loaded->toArray()['contacts'],
            [
                ['name' => 'Alice', 'email' => 'alice@example.com'],
                ['name' => 'Bob', 'email' => 'bob@example.com'],
            ],
            'address book example should persist a nested contact list through native save/load'
        );
    },
    'rust/automerge/src/lib.rs'
);

$rustMapped(
    'rust lib address book merge example preserves independent nested edits',
    'rust:doc-tests-automerge:automerge-src-lib-rs-line-147',
    'automerge/src/lib.rs address book merge example',
    function () use ($port): void {
        $base = $port->set($port->init('aaaaaa'), 'contacts', [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ]);
        $saved = $port->save($base);

        $doc1 = $port->load($saved, 'bbbbbb');
        $doc1 = $port->setNested($doc1, ['contacts', 0, 'email'], 'alicesnewemail@example.com');

        $doc2 = $port->load($saved, 'cccccc');
        $doc2 = $port->setNested($doc2, ['contacts', 1, 'name'], 'Robert');

        $merged = $port->mergeDocuments($doc1, $doc2);
        $contacts = $merged->toArray()['contacts'];

        same($contacts[0]['email'], 'alicesnewemail@example.com', 'merge should preserve the first peer edit to Alice');
        same($contacts[1]['name'], 'Robert', 'merge should preserve the second peer edit to Bob');
        same($port->getConflicts($merged, 'contacts'), null, 'independent nested contact edits should not create a root conflict');
    },
    'rust/automerge/src/lib.rs'
);

$rustMapped(
    'rust sync protocol doc example converges a new peer',
    'rust:doc-tests-automerge:automerge-src-sync-rs-sync-line-25',
    'automerge/src/sync.rs sync protocol example',
    function () use ($port): void {
        $peer1 = $port->set($port->init('aaaaaa'), 'key', 'value');
        $peer1State = $port->initSyncState();
        [$peer1State, $message1to2] = $port->generateSyncMessage($peer1, $peer1State);

        truthy($message1to2 !== null, 'changed peer should generate an initial sync message');

        $peer2 = $port->init('bbbbbb');
        $peer2State = $port->initSyncState();
        [$peer2, $peer2State] = $port->receiveSyncMessage($peer2, $peer2State, $message1to2);

        for ($iteration = 0; $iteration < 10; ++$iteration) {
            [$peer2State, $twoToOne] = $port->generateSyncMessage($peer2, $peer2State);
            if ($twoToOne !== null) {
                [$peer1, $peer1State] = $port->receiveSyncMessage($peer1, $peer1State, $twoToOne);
            }

            [$peer1State, $oneToTwo] = $port->generateSyncMessage($peer1, $peer1State);
            if ($oneToTwo !== null) {
                [$peer2, $peer2State] = $port->receiveSyncMessage($peer2, $peer2State, $oneToTwo);
            }

            if ($twoToOne === null && $oneToTwo === null) {
                break;
            }
        }

        same($peer2->toArray()['key'] ?? null, 'value', 'sync protocol doc example should copy the root key to peer2');
        same($port->generateSyncMessage($peer1, $peer1State)[1], null, 'peer1 should stop sending after sync convergence');
        same($port->generateSyncMessage($peer2, $peer2State)[1], null, 'peer2 should stop sending after sync convergence');
    },
    'rust/automerge/src/sync.rs'
);

$rustMapped(
    'rust patch log doc example records patches from a sync receive',
    'rust:doc-tests-automerge:automerge-src-patches-patch-log-rs-patches-patch-log-patchlog-line-28-compile',
    'automerge/src/patches/patch_log.rs PatchLog sync receive example',
    function () use ($port): void {
        $source = $port->set($port->init('aaaaaa'), 'key', 'value');
        [$sourceState, $syncMessage] = $port->generateSyncMessage($source, $port->initSyncState());

        truthy($syncMessage !== null, 'source document should generate a sync message with changes');

        $target = $port->init('bbbbbb');
        [$target, $targetState, $patches] = $port->receiveSyncMessageLogPatches($target, $port->initSyncState(), $syncMessage);

        same($target->toArray()['key'] ?? null, 'value', 'patch-log receive should apply the remote change');
        sameArray(
            $patches,
            [
                ['action' => 'put', 'path' => ['key'], 'value' => ''],
                ['action' => 'splice', 'path' => ['key', 0], 'value' => 'value'],
            ],
            'patch-log receive should expose the relative materialization patches'
        );
        truthy(is_array($sourceState) && is_array($targetState), 'sync states should remain native PHP arrays');
    },
    'rust/automerge/src/patches/patch_log.rs'
);

$rustMapped(
    'rust applying changes with a patch log from another document reports mismatch',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:applying-changes-with-patch-log-from-another-document-returns-error-not-panic',
    'applying_changes_with_patch_log_from_another_document_returns_error_not_panic',
    function () use ($port): void {
        $source = $port->set($port->init('bbbbbb'), 'source', 'value');
        $patchLog = $port->initPatchLog();
        [, $patchLog] = $port->applyChangesLogPatches(
            $port->init('aaaaaa'),
            $port->getAllChanges($source),
            $patchLog
        );

        $otherSource = $port->set($port->init('cccccc'), 'source', 'value');
        throwsLike(
            static fn (): array => $port->applyChangesLogPatches(
                $port->init('dddddd'),
                $port->getAllChanges($otherSource),
                $patchLog
            ),
            'PatchLogMismatch',
            'patch log bound to a different document should return a mismatch error'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust transaction with a patch log from another document reports mismatch',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:transaction-with-patch-log-from-another-document-does-not-panic',
    'transaction_with_patch_log_from_another_document_does_not_panic',
    function () use ($port): void {
        $source = $port->set($port->init('bbbbbb'), 'source', 'value');
        $patchLog = $port->initPatchLog();
        [, $patchLog] = $port->applyChangesLogPatches(
            $port->init('aaaaaa'),
            $port->getAllChanges($source),
            $patchLog
        );

        $otherDoc = $port->applyChanges(
            $port->init('dddddd'),
            $port->getAllChanges($port->set($port->init('cccccc'), 'key', 'value'))
        );

        throwsLike(
            static fn (): Transaction => $port->transactionLogPatches($otherDoc, $patchLog),
            'PatchLogMismatch',
            'transaction_log_patches should reject patch logs bound to another document'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust transactionAt with a patch log from another document reports mismatch',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:transaction-at-with-patch-log-from-another-document-does-not-panic',
    'transaction_at_with_patch_log_from_another_document_does_not_panic',
    function () use ($port): void {
        $source = $port->set($port->init('bbbbbb'), 'source', 'value');
        $patchLog = $port->initPatchLog();
        [, $patchLog] = $port->applyChangesLogPatches(
            $port->init('aaaaaa'),
            $port->getAllChanges($source),
            $patchLog
        );

        $otherDoc = $port->applyChanges(
            $port->init('dddddd'),
            $port->getAllChanges($port->set($port->init('cccccc'), 'key', 'value'))
        );

        throwsLike(
            static fn (): Transaction => $port->transactionAtLogPatches($otherDoc, $patchLog, $port->getHeads($otherDoc)),
            'PatchLogMismatch',
            'transaction_at should reject patch logs bound to another document'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust owned transaction with a patch log from another document reports mismatch',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:owned-transaction-with-patch-log-from-another-document-does-not-panic',
    'owned_transaction_with_patch_log_from_another_document_does_not_panic',
    function () use ($port): void {
        $source = $port->set($port->init('bbbbbb'), 'source', 'value');
        $patchLog = $port->initPatchLog();
        [, $patchLog] = $port->applyChangesLogPatches(
            $port->init('aaaaaa'),
            $port->getAllChanges($source),
            $patchLog
        );

        $otherDoc = $port->applyChanges(
            $port->init('dddddd'),
            $port->getAllChanges($port->set($port->init('cccccc'), 'key', 'value'))
        );

        throwsLike(
            static fn (): Transaction => $port->intoTransactionLogPatches($otherDoc, $patchLog),
            'PatchLogMismatch',
            'owned transaction creation should reject patch logs bound to another document'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust big_list patch log reports a large list insertion',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:big-list',
    'big_list',
    function () use ($port): void {
        $source = $port->batchCreateObject($port->init('aaaaaa'), 'list', []);
        $change1 = $port->getLastLocalChange($source);
        truthy($change1 !== null, 'first big_list change should create the list object');

        $source = $port->insertListElements($source, 'list', 0, array_fill(0, 17, []));
        $change2 = $port->getLastLocalChange($source);
        truthy($change2 !== null, 'second big_list change should insert the map values');

        $target = $port->init('bbbbbb');
        $patchLog = $port->initPatchLog();
        [$target, $patchLog] = $port->applyChangesLogPatches($target, [$change1], $patchLog);
        [$target, $patchLog] = $port->applyChangesLogPatches($target, [$change2], $patchLog);
        $patches = $port->makePatchesFromLog($patchLog);
        $lastPatch = $patches[array_key_last($patches)] ?? null;

        same(count($target->toArray()['list'] ?? []), 17, 'big_list target should materialize all inserted maps');
        truthy(is_array($lastPatch), 'big_list patch log should expose at least one patch');
        same($lastPatch['action'] ?? null, 'insert', 'big_list patch log should end with a list insert patch');
        sameArray($lastPatch['path'] ?? [], ['list', 0], 'big_list insert patch should target the first list index');
        same(count($lastPatch['values'] ?? []), 17, 'big_list insert patch should group the contiguous inserted map values');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust large list patches count string list elements as one slot',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:large-patches-in-lists-are-correct',
    'large_patches_in_lists_are_correct',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $headsBefore = $port->getHeads($doc);
        $values = ['123456'];
        for ($i = 1; $i < 501; ++$i) {
            $values[] = ['a' => $i];
        }

        $doc = $port->set($doc, 'list', $values);
        $patches = $port->diff($doc, $headsBefore, $port->getHeads($doc));
        $finalPatch = $patches[array_key_last($patches)] ?? null;

        truthy(is_array($finalPatch), 'large list diff should emit patches');
        same($finalPatch['action'] ?? null, 'insert', 'large list final patch should insert the last map object');
        sameArray($finalPatch['path'] ?? [], ['list', 500], 'string list elements should advance the list index by one slot');
        sameArray(($finalPatch['values'] ?? [])[0] ?? [], ['a' => 500], 'large list final patch should target the 500th inserted map');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust repeated out-of-order changes converge after dependencies arrive',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:handle-repeated-out-of-order-changes',
    'handle_repeated_out_of_order_changes',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'list', ['a']);
        $doc2 = $port->clone($doc1);
        $doc1 = $port->insertListElements($doc1, 'list', 1, ['b']);
        $doc1 = $port->insertListElements($doc1, 'list', 2, ['c']);
        $doc1 = $port->insertListElements($doc1, 'list', 3, ['d']);
        $changes = $port->getChangesSince($doc1, []);

        same(count($changes), 4, 'out-of-order fixture should include the initial list and three insert changes');
        $doc2 = $port->applyChanges($doc2, array_slice($changes, 2));
        $doc2 = $port->applyChanges($doc2, array_slice($changes, 2));
        $doc2 = $port->applyChanges($doc2, $changes);

        sameArray($doc2->toArray(), ['list' => ['a', 'b', 'c', 'd']], 'out-of-order duplicate changes should materialize once dependencies arrive');
        same($port->save($doc2), $port->save($doc1), 'out-of-order duplicate changes should converge to the same saved document');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust reverse diff reinserts deleted text object in a list',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:diff-should-reverse-deletion-of-object-in-list-correctly',
    'diff_should_reverse_deletion_of_object_in_list_correctly',
    function () use ($port): void {
        $sequence = 0;
        $doc = $port->set($port->init('aaaaaa'), 'list', [
            'a',
            TextValue::fromString('b', 'aaaaaa', $sequence),
            'c',
        ]);
        $headsBefore = $port->getHeads($doc);
        $doc = $port->deleteListElements($doc, 'list', 1);
        $headsAfter = $port->getHeads($doc);

        $patches = $port->diff($doc, $headsAfter, $headsBefore);

        sameArray(
            $patches,
            [
                ['action' => 'insert', 'path' => ['list', 1], 'values' => ['']],
                ['action' => 'splice', 'path' => ['list', 1, 0], 'value' => 'b'],
            ],
            'reverse diff should recreate the deleted list text object and its text content'
        );
        sameArray($port->applyPatches($doc, $patches)->toArray(), ['list' => ['a', 'b', 'c']], 'reverse list-object diff patches should restore visible content');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust forked documents save without missing actor ids',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:missing-actors-when-docs-are-forked',
    'missing_actors_when_docs_are_forked',
    function () use ($port): void {
        $doc0 = $port->set($port->init('00'), 'a', 1);
        $doc1 = $port->set($port->clone($doc0, '02'), 'b', 2);
        $doc2 = $port->clone($doc0, '01');
        $doc2 = $port->mergeDocuments($doc2, $doc1);

        $savedBeforeNoopDelete = $port->save($doc2);
        $doc2 = $port->delete($doc2, 'c');
        $savedAfterNoopDelete = $port->save($doc2);

        same($savedAfterNoopDelete, $savedBeforeNoopDelete, 'no-op delete on a forked actor should not perturb saved bytes');
        sameArray($port->load($savedAfterNoopDelete, '03')->toArray(), ['a' => 1, 'b' => 2], 'saved forked document should load with all visible keys');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust reverse diff reinserts deleted text object in a map',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:diff-should-reverse-deletion-of-object-in-map-correctly',
    'diff_should_reverse_deletion_of_object_in_map_correctly',
    function () use ($port): void {
        $sequence = 0;
        $doc = $port->set($port->init('aaaaaa'), 'map', [
            'a' => 'a',
            'b' => TextValue::fromString('b', 'aaaaaa', $sequence),
            'c' => 'c',
        ]);
        $headsBefore = $port->getHeads($doc);
        $doc = $port->deleteNested($doc, ['map', 'b']);
        $headsAfter = $port->getHeads($doc);

        $patches = $port->diff($doc, $headsAfter, $headsBefore);

        sameArray(
            $patches,
            [
                ['action' => 'put', 'path' => ['map', 'b'], 'value' => ''],
                ['action' => 'splice', 'path' => ['map', 'b', 0], 'value' => 'b'],
            ],
            'reverse diff should recreate the deleted map text object and its text content'
        );
        $restored = $port->applyPatches($doc, $patches)->toArray();
        same($restored['map']['a'] ?? null, 'a', 'reverse map-object diff patches should preserve the preceding map value');
        same($restored['map']['b'] ?? null, 'b', 'reverse map-object diff patches should restore the deleted text content');
        same($restored['map']['c'] ?? null, 'c', 'reverse map-object diff patches should preserve the following map value');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust reverse diff reinserts deleted block marker in text',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:diff-should-reverse-deletion-of-block-in-text-correctly',
    'diff_should_reverse_deletion_of_block_in_text_correctly',
    function () use ($port): void {
        $block = ['parents' => [], 'type' => '', 'attrs' => [], 'key' => 'value'];
        $doc = $port->splice($port->init('aaaaaa'), 'text', 0, 0, 'a');
        $doc = $port->splitBlock($doc, ['text'], 1, $block);
        $doc = $port->splice($doc, 'text', 2, 0, 'b');
        $headsBefore = $port->getHeads($doc);

        $doc = $port->joinBlock($doc, ['text'], 1);
        $headsAfter = $port->getHeads($doc);
        $patches = $port->diff($doc, $headsAfter, $headsBefore);

        sameArray($patches, [[
            'action' => 'splice',
            'path' => ['text', 1],
            'value' => "\u{FFFC}",
            'marks' => ['__automerge_block' => $block],
        ]], 'reverse diff should reinsert only the deleted block marker with its block metadata');
        sameArray($port->spans($port->view($doc, $headsBefore), ['text']), [
            ['type' => 'text', 'value' => 'a'],
            ['type' => 'block', 'value' => $block],
            ['type' => 'text', 'value' => 'b'],
        ], 'historical view should retain the deleted block marker and metadata');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust AutoCommit commit_with doc example records message and time',
    'rust:doc-tests-automerge:automerge-src-autocommit-rs-autocommit-autocommit-commit-with-line-638',
    'automerge/src/autocommit.rs AutoCommit::commit_with example',
    function () use ($port): void {
        $now = 1234567890;
        $doc = $port->batchCreateObjectWithCommitOptions(
            $port->init('aaaaaa'),
            'todos',
            [],
            'Create todos list',
            $now
        );
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        sameArray($doc->toArray(), ['todos' => []], 'commit_with doc example should create the todos list');
        same($decoded['message'], 'Create todos list', 'commit_with should preserve the supplied message');
        same($decoded['time'], $now, 'commit_with should preserve the supplied commit time');
    },
    'rust/automerge/src/autocommit.rs'
);

$rustMapped(
    'rust AutoCommit send-equivalent save load boundary stays independent',
    'rust:unittests-src-lib-rs-target-debug-deps-automerge-f7a40786b3c8bc35:autocommit-tests-test-autocommit-is-send',
    'autocommit::tests::test_autocommit_is_send',
    function () use ($port): void {
        $source = $port->set($port->init('aaaaaa'), 'key', 'value');
        $payload = $port->save($source);
        $received = $port->load($payload, 'bbbbbb');

        sameArray($received->toArray(), $source->toArray(), 'document should materialize identically after crossing a save/load boundary');
        sameArray($port->getHeads($received), $port->getHeads($source), 'document heads should survive the send-equivalent boundary');

        $changedReceived = $port->set($received, 'remote', true);
        sameArray($source->toArray(), ['key' => 'value'], 'changing the received document should not mutate the source document');
        sameArray($changedReceived->toArray(), ['key' => 'value', 'remote' => true], 'received document should remain independently changeable');

        $roundTripped = $port->applyChanges($port->init('cccccc'), $port->getAllChanges($changedReceived));
        sameArray($roundTripped->toArray(), $changedReceived->toArray(), 'received document changes should replay in another native document');
        sameArray($port->getHeads($roundTripped), $port->getHeads($changedReceived), 'replayed received document should preserve heads');
    },
    'rust/automerge/src/autocommit.rs'
);

$rustMapped(
    'rust transaction commit_with doc example records message and time',
    'rust:doc-tests-automerge:automerge-src-transaction-manual-transaction-rs-transaction-manual-transaction-transaction-commit-with-line-83',
    'automerge/src/transaction/manual_transaction.rs Transaction::commit_with example',
    function () use ($port): void {
        $now = 1234567890;
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->batchCreateObject('todos', []);
        $doc = $tx->commitWith('Create todos list', $now);
        $decoded = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        sameArray($doc->toArray(), ['todos' => []], 'transaction commit_with should publish the todos list');
        same($decoded['message'], 'Create todos list', 'transaction commit_with should preserve the supplied message');
        same($decoded['time'], $now, 'transaction commit_with should preserve the supplied commit time');
    },
    'rust/automerge/src/transaction/manual_transaction.rs'
);

$rustMapped(
    'rust owned transaction put and get roundtrip',
    $rustOwnedTransactionSuite . 'put-and-get-roundtrip',
    'transaction::owned_transaction::tests::put_and_get_roundtrip',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->set('key', 'value');
        [$doc, $hash] = $tx->commitWithHash();

        truthy($hash !== null, 'owned transaction with a write should report a change hash');
        same($doc->toArray()['key'] ?? null, 'value', 'committed owned transaction should expose the written value');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction reads writes before commit',
    $rustOwnedTransactionSuite . 'read-during-transaction',
    'transaction::owned_transaction::tests::read_during_transaction',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->set('a', '1');

        same($tx->document()->toArray()['a'] ?? null, '1', 'owned transaction draft should expose writes before commit');
        same($tx->commit()->toArray()['a'] ?? null, '1', 'committed owned transaction should preserve draft writes');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction supports nested list objects',
    $rustOwnedTransactionSuite . 'nested-objects',
    'transaction::owned_transaction::tests::nested_objects',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->batchCreateObject('items', []);
        $tx->insertListElements('items', 0, ['first']);
        $tx->insertListElements('items', 1, ['second']);
        [$doc, $hash] = $tx->commitWithHash();

        truthy($hash !== null, 'owned transaction with nested list writes should report a change hash');
        sameArray($doc->toArray()['items'] ?? [], ['first', 'second'], 'committed owned transaction should preserve nested list insertions');
        same(count($doc->toArray()['items'] ?? []), 2, 'owned transaction list object should have the expected length');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction commit_with records options',
    $rustOwnedTransactionSuite . 'commit-with-options',
    'transaction::owned_transaction::tests::commit_with_options',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->set('x', 42);
        $doc = $tx->commitWith('test commit');
        $change = $port->decodeChange($port->getLastLocalChange($doc) ?? []);

        same($doc->toArray()['x'] ?? null, 42, 'owned transaction commit_with should publish the written value');
        same($change['message'], 'test commit', 'owned transaction commit_with should preserve the supplied message');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction logs patches',
    $rustOwnedTransactionSuite . 'log-patches',
    'transaction::owned_transaction::tests::log_patches',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->set('patched', 'yes');
        [$doc, $patches] = $tx->commitWithPatches();

        same($doc->toArray()['patched'] ?? null, 'yes', 'patch-logging transaction should publish the written value');
        truthy(count($patches) > 0, 'patch-logging transaction should emit at least one patch');
        same($patches[0]['action'] ?? null, 'put', 'patch-logging transaction should describe the root put');
        sameArray($patches[0]['path'] ?? [], ['patched'], 'patch-logging transaction should target the written root key');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction at historical heads commits against current document',
    $rustOwnedTransactionSuite . 'owned-transaction-at',
    'transaction::owned_transaction::tests::owned_transaction_at',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->set('v', 1);
        $doc = $tx->commit();
        $headsV1 = $port->getHeads($doc);

        $tx = $port->transaction($doc);
        $tx->set('v', 2);
        $doc = $tx->commit();

        $tx = $port->transactionAt($doc, $headsV1);
        same($tx->document()->toArray()['v'] ?? null, 1, 'historical owned transaction should read the requested heads');

        $tx->set('from_v1', true);
        [$doc, $hash] = $tx->commitWithHash();

        truthy($hash !== null, 'historical owned transaction write should report a change hash');
        same($doc->toArray()['from_v1'] ?? null, true, 'historical owned transaction should commit the new root field');
        same($doc->toArray()['v'] ?? null, 2, 'historical owned transaction should preserve the current branch value');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction exposes pre-transaction heads',
    $rustOwnedTransactionSuite . 'get-heads-returns-pre-tx-heads',
    'transaction::owned_transaction::tests::get_heads_returns_pre_tx_heads',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 1);
        $heads = $port->getHeads($doc);
        $tx = $port->transaction($doc);

        sameArray($tx->getHeads(), $heads, 'owned transaction should expose the document heads from before the transaction started');
        sameArray($tx->commit()->toArray(), ['a' => 1], 'committing a read-only transaction should leave the document data unchanged');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction counts pending operations',
    $rustOwnedTransactionSuite . 'pending-ops',
    'transaction::owned_transaction::tests::pending_ops',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));

        same($tx->pendingOps(), 0, 'new owned transaction should start with no pending ops');
        $tx->set('a', 1);
        same($tx->pendingOps(), 1, 'first owned transaction write should count as one pending op');
        $tx->set('b', 2);
        same($tx->pendingOps(), 2, 'second owned transaction write should count as another pending op');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction empty commit returns null hash',
    $rustOwnedTransactionSuite . 'empty-commit-returns-none-hash',
    'transaction::owned_transaction::tests::empty_commit_returns_none_hash',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $tx = $port->transaction($doc);
        [$committed, $hash] = $tx->commitWithHash();

        same($hash, null, 'empty transaction commit should not report a change hash');
        sameArray($port->getHeads($committed), $port->getHeads($doc), 'empty transaction commit should not advance heads');
        same(count($port->getAllChanges($committed)), 0, 'empty transaction commit should not append a change');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction rollback with no writes cancels nothing',
    $rustOwnedTransactionSuite . 'rollback-discards-ops',
    'transaction::owned_transaction::tests::rollback_discards_ops',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'keep', 'yes');
        $tx = $port->transaction($doc);
        [$rolledBack, $cancelled] = $tx->rollbackWithCancelled();

        same($cancelled, 0, 'rollback without writes should cancel no pending ops');
        sameArray($rolledBack->toArray(), ['keep' => 'yes'], 'rollback without writes should return the original document state');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust owned transaction rollback undoes pending writes',
    $rustOwnedTransactionSuite . 'rollback-undoes-writes',
    'transaction::owned_transaction::tests::rollback_undoes_writes',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $tx = $port->transaction($doc);
        $tx->set('gone', 'soon');
        [$rolledBack, $cancelled] = $tx->rollbackWithCancelled();

        same($cancelled, 1, 'rollback after one write should cancel one pending op');
        sameArray($rolledBack->toArray(), [], 'rollback should discard pending writes from the transaction draft');
    },
    'rust/automerge/src/transaction/owned_transaction.rs'
);

$rustMapped(
    'rust transaction inner map rollback setup reads nested write',
    $rustTransactionInnerSuite . 'map-rollback-doesnt-panic',
    'transaction::inner::tests::map_rollback_doesnt_panic',
    function () use ($port): void {
        $tx = $port->transaction($port->init('aaaaaa'));
        $tx->batchCreateObject('a', []);
        $tx->setNested(['a', 'b'], 1);

        same($tx->document()->toArray()['a']['b'] ?? null, 1, 'transaction should read a nested map write before rollback or commit');
        sameArray($tx->rollback()->toArray(), [], 'rolling back the nested map draft should return the original document');
    },
    'rust/automerge/src/transaction/inner.rs'
);

$rustMapped(
    'rust transaction rollback with no ops after merge is stable',
    $rustCoreSuite . 'rollback-with-no-ops',
    'rollback_with_no_ops',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 1);
        $doc2 = $port->clone($doc, 'bbbbbb');
        $doc2 = $port->transaction($doc2)->commit();

        $doc3 = $port->set($port->clone($doc, 'cccccc'), 'b', 2);
        $merged = $port->mergeDocuments($doc2, $doc3);
        $before = $port->save($merged);
        $rolledBack = $port->transaction($merged)->rollback();

        same($port->save($rolledBack), $before, 'rolling back a no-op transaction after merge should preserve document bytes');
        sameArray($rolledBack->toArray(), ['a' => 1, 'b' => 2], 'rollback after merge should preserve visible merged state');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust rollback with several actors discards complex draft',
    $rustCoreSuite . 'rollback-with-several-actors',
    'rollback_with_several_actors',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'text', new TextValue());
        $doc1 = $port->splice($doc1, 'text', 0, 0, 'the sly fox jumped over the lazy dog');
        $doc1 = $port->set($doc1, 'map_a', ['key1' => 'value1a', 'key2' => 'value2a']);

        $doc2 = $port->clone($doc1, 'cccccc');
        $doc2 = $port->splice($doc2, 'text', 8, 3, 'monkey');
        $doc2 = $port->splice($doc2, 'text', 36, 3, 'pig');
        $doc2 = $port->set($doc2, 'map_c', []);
        $doc2 = $port->setNested($doc2, ['map_a', 'key2'], 'value2c');
        $doc2 = $port->setNested($doc2, ['map_a', 'key3'], 'value3c');
        $doc2 = $port->setNested($doc2, ['map_c', 'key1'], 'value');

        $doc3 = $port->clone($doc2, 'bbbbbb');
        $beforeRollbackSave = $port->save($doc3);
        $beforeRollbackView = $doc3->toArray();

        $tx = $port->transaction($doc3);
        $tx->splice('text', 8, 5, 'zebra');
        $tx->batchCreateObject('map_b', []);
        $tx->setNested(['map_a', 'key1'], 'value3b');
        $tx->setNested(['map_a', 'key3'], 'value3b');
        $tx->setNested(['map_b', 'key1'], 'value');

        truthy($tx->pendingOps() >= 5, 'complex rollback draft should contain pending text and map operations');
        $rolledBack = $tx->rollback();

        same($port->save($rolledBack), $beforeRollbackSave, 'rollback should restore the pre-transaction document bytes');
        sameArray($rolledBack->toArray(), $beforeRollbackView, 'rollback should restore the pre-transaction visible document');
        sameArray($rolledBack->toArray(), $doc2->toArray(), 'rolled-back fork should still match the source actor visible state');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust AutoCommit diff cursor example emits patches',
    'rust:doc-tests-automerge:automerge-src-autocommit-rs-autocommit-autocommit-diff-line-223',
    'automerge/src/autocommit.rs AutoCommit::diff example',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');
        $heads = $port->getHeads($doc);
        $diffCursor = $port->diffCursor($doc);
        $patches = $port->diff($doc, $diffCursor, $heads);
        $port->updateDiffCursor($doc);

        sameArray($diffCursor, [], 'a document without an updated diff cursor should diff from the empty head set');
        sameArray($patches, [['action' => 'put', 'path' => ['key'], 'value' => 'value']], 'diff cursor example should emit the root map insertion patch');
        sameArray($port->diffCursor($doc), $heads, 'updateDiffCursor should advance the stored cursor to the current heads');
    },
    'rust/automerge/src/autocommit.rs'
);

$rustMapped(
    'rust AutoCommit diffIncremental example advances the cursor',
    'rust:doc-tests-automerge:automerge-src-autocommit-rs-autocommit-autocommit-diff-incremental-line-319',
    'automerge/src/autocommit.rs AutoCommit::diff_incremental example',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');
        $patches = $port->diffIncremental($doc);

        sameArray($patches, [['action' => 'put', 'path' => ['key'], 'value' => 'value']], 'first diffIncremental call should emit changes since the empty cursor');
        sameArray($port->diffIncremental($doc), [], 'second diffIncremental call should be empty after the cursor advances');
        sameArray($port->diffCursor($doc), $port->getHeads($doc), 'diffIncremental should leave the cursor at the current heads');
    },
    'rust/automerge/src/autocommit.rs'
);

$rustMapped(
    'rust concurrent increments of the same property are added',
    $rustCoreSuite . 'add-concurrent-increments-of-same-property',
    'add_concurrent_increments_of_same_property',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'counter', new Counter(0));
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);
        $doc1 = $port->incrementCounter($doc1, ['counter'], 1);
        $doc2 = $port->incrementCounter($doc2, ['counter'], 2);
        $merged = $port->mergeDocuments($doc1, $doc2);

        $counter = $merged->toArray()['counter'];
        truthy($counter instanceof Counter, 'merged counter should remain a native counter');
        same($counter->value(), 3, 'concurrent increments on the same counter should be added');
        same($port->getConflicts($merged, 'counter'), null, 'same-counter increments should not create conflicts');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust increments only apply to preceding counter values',
    $rustCoreSuite . 'add-increments-only-to-preceeded-values',
    'add_increments_only_to_preceeded_values',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'counter', new Counter(0));
        $doc1 = $port->incrementCounter($doc1, ['counter'], 1);
        $doc2 = $port->set($port->init('bbbbbb'), 'counter', new Counter(0));
        $doc2 = $port->incrementCounter($doc2, ['counter'], 3);
        $merged = $port->mergeDocuments($doc1, $doc2);

        $conflicts = $port->getConflicts($merged, 'counter') ?? [];
        truthy($conflicts['1@aaaaaa'] instanceof Counter, 'first counter conflict should remain a counter');
        truthy($conflicts['1@bbbbbb'] instanceof Counter, 'second counter conflict should remain a counter');
        same($conflicts['1@aaaaaa']->value(), 1, 'first counter conflict should include only its own increment');
        same($conflicts['1@bbbbbb']->value(), 3, 'second counter conflict should include only its own increment');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust transaction_at applies historical text and scalar edits',
    $rustCoreSuite . 'can-transaction-at',
    'can_transaction_at',
    function () use ($port): void {
        $doc = $port->setMany($port->init('aaaaaa'), [
            'text' => 'aaabbbccc',
            'size' => 100,
        ]);
        $heads = $port->getHeads($doc);

        $doc = $port->splice($doc, 'text', 3, 3, 'QQQ');
        $doc = $port->set($doc, 'size', 200);

        $historical = $port->view($doc, $heads);
        sameArray($historical->toArray(), ['text' => 'aaabbbccc', 'size' => 100], 'historical transaction view should start at the requested heads');

        $doc = $port->spliceAtHeads($doc, $heads, 'text', 3, 3, 'ZZZ');
        $doc = $port->setAtHeads($doc, $heads, 'size', 300);
        same($doc->toArray()['text'], 'aaaZZZQQQccc', 'first historical text replacement should merge with the current branch');
        same($doc->toArray()['size'], 300, 'first historical scalar write should become the root winner');

        $doc = $port->spliceAtHeads($doc, $heads, 'text', 3, 3, 'TTT');
        $doc = $port->setAtHeads($doc, $heads, 'size', 400);
        same($doc->toArray()['text'], 'aaaTTTZZZQQQccc', 'second historical text replacement should merge with both earlier branches');
        same($doc->toArray()['size'], 400, 'second historical scalar write should become the root winner');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust isolate hides later heads until explicit integration',
    $rustCoreSuite . 'can-isolate',
    'can_isolate',
    function () use ($port): void {
        $doc1 = $port->setMany($port->init('aaaaaa'), [
            'text' => 'aaabbbccc',
            'size' => 100,
        ]);
        $heads1 = $port->getHeads($doc1);
        $doc1 = $port->set($doc1, 'size', 150);

        $isolated = $port->isolate($doc1, $heads1);
        truthy($isolated instanceof IsolatedDocument, 'isolate should return an isolated document handle');

        $doc2 = $port->clone($doc1, 'bbbbbb');
        $doc2 = $port->set($doc2, 'other', 999);
        $doc2 = $port->splice($doc2, 'text', 9, 0, '111');

        sameArray($port->isolatedDocument($isolated)->toArray(), ['text' => 'aaabbbccc', 'size' => 100], 'isolate should expose only the requested heads');

        $isolated = $port->spliceInIsolation($isolated, 'text', 3, 3, 'QQQ');
        $isolated = $port->setInIsolation($isolated, 'size', 200);
        sameArray($port->isolatedDocument($isolated)->toArray(), ['text' => 'aaaQQQccc', 'size' => 200], 'isolated edits should materialize against the isolated heads');

        $heads2 = $port->getHeads($port->isolatedDocument($isolated));
        truthy($heads1 !== $heads2, 'isolated writes should advance the visible head set');

        $isolated = $port->mergeIntoIsolation($isolated, $doc2);
        sameArray($port->isolatedDocument($isolated)->toArray(), ['text' => 'aaaQQQccc', 'size' => 200], 'merging while isolated should keep hidden changes invisible');

        $isolated = $port->isolate($isolated, $heads1);
        sameArray($port->isolatedDocument($isolated)->toArray(), ['text' => 'aaabbbccc', 'size' => 100], 're-isolating at the original heads should hide the first isolated branch');

        $isolated = $port->spliceInIsolation($isolated, 'text', 3, 3, 'ZZZ');
        $isolated = $port->setInIsolation($isolated, 'size', 300);
        sameArray($port->isolatedDocument($isolated)->toArray(), ['text' => 'aaaZZZccc', 'size' => 300], 'second isolated branch should remain independent until integration');

        $doc1 = $port->integrate($isolated);
        sameArray($doc1->toArray(), ['text' => 'aaaZZZQQQccc111', 'size' => 300, 'other' => 999], 'integrate should materialize hidden and isolated branches together');

        $isolated = $port->isolate($doc1, $heads1);
        sameArray($port->isolatedDocument($isolated)->toArray(), ['text' => 'aaabbbccc', 'size' => 100], 'integrated documents should still support re-isolation at older heads');

        $isolated = $port->spliceInIsolation($isolated, 'text', 3, 3, 'TTT');
        $isolated = $port->setInIsolation($isolated, 'size', 400);
        sameArray($port->isolatedDocument($isolated)->toArray(), ['text' => 'aaaTTTccc', 'size' => 400], 'third isolated branch should materialize alone before integration');

        $doc1 = $port->integrate($isolated);
        sameArray($doc1->toArray(), ['text' => 'aaaTTTZZZQQQccc111', 'size' => 400, 'other' => 999], 'final integration should preserve deterministic text branch ordering');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust isolate integrate patch log omits stale conflicted text',
    'rust:tests-text-rs-target-debug-deps-text-e79c4b56267af860:incorrect-patches-produced-when-isolating-and-integrating',
    'incorrect_patches_produced_when_isolating_and_integrating',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $beginning = $port->getHeads($doc);

        $doc = $port->splice($doc, 'name', 0, 0, str_repeat('a', 100));

        $isolated = $port->isolate($doc, $beginning);
        $isolated = $port->spliceInIsolation($isolated, 'color', 0, 0, 'red');
        $doc = $port->integrate($isolated);
        same($doc->text('color')->toString(), 'red', 'first isolated color text should materialize');

        $ignoredPatches = $port->diffIncremental($doc);
        truthy($ignoredPatches !== [], 'initial diffIncremental should populate and advance the cursor');
        $doc = $port->emptyChange($doc);

        $isolated = $port->isolate($doc, $beginning);
        $isolated = $port->spliceInIsolation($isolated, 'color', 0, 0, 'unset');
        $visible = $port->emptyChange($port->isolatedDocument($isolated));
        $isolated = $isolated->withVisibleDocument($visible);
        $doc = $port->integrate($isolated);

        same($doc->toArray()['color'] ?? null, 'unset', 'later isolated color object should win the root conflict');
        same(count($port->getConflicts($doc, 'color') ?? []), 2, 'concurrent color text object creations should be root-key conflicts');

        $patches = $port->diffIncremental($doc);
        truthy($patches !== [], 'second diffIncremental should emit patches for the integrated conflicting text');
        foreach ($patches as $patch) {
            if (is_array($patch['path'] ?? null) && ($patch['path'][0] ?? null) === 'color') {
                truthy(($patch['value'] ?? null) !== 'red', 'patches should not reinsert the stale red text conflict');
            }
        }
    },
    'rust/automerge/tests/text.rs'
);

$rustMapped(
    'rust marks survive expansion unmarking and prefix insertions',
    $rustCoreSuite . 'marks',
    'marks',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splice($doc, 'text', 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 0, 5, 'bold', true, 'both');
        $doc = $port->splice($doc, 'text', 5, 0, ' cool');
        $doc = $port->unmark($doc, ['text'], 0, 5, 'bold');
        $doc = $port->splice($doc, 'text', 0, 0, 'why ');

        sameArray($port->marks($doc, ['text']), [[
            'name' => 'bold',
            'value' => true,
            'start' => 9,
            'end' => 14,
        ]], 'mark range should track expansion, unmarking, and prefix insertions');
        sameArray($port->spans($doc, ['text']), [
            ['type' => 'text', 'value' => 'why hello'],
            ['type' => 'text', 'value' => ' cool', 'marks' => ['bold' => true]],
            ['type' => 'text', 'value' => ' world'],
        ], 'only the expanded inserted text should remain bold');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust marks can be queried at historical heads',
    $rustCoreSuite . 'get-marks-at-heads',
    'get_marks_at_heads',
    function () use ($port): void {
        $doc = $port->from(['text' => ''], 'aaaaaa');
        $doc = $port->splice($doc, 'text', 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 0, 10, 'bold', true, 'after');
        $heads = $port->getHeads($doc);

        $docWithPendingRemoval = $port->unmark($doc, ['text'], 0, 10, 'bold');
        sameArray(
            $port->marksAtHeads($docWithPendingRemoval, ['text'], 1, $heads),
            ['bold' => true],
            'marksAtHeads should return the mark active at the supplied historical heads'
        );
        sameArray($port->marksAt($docWithPendingRemoval, ['text'], 1), [], 'current document should have removed the mark');

        $committed = $port->emptyChange($docWithPendingRemoval);
        sameArray(
            $port->marksAtHeads($committed, ['text'], 1, $heads),
            ['bold' => true],
            'historical mark lookup should keep working after later commits'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust sync state reports acknowledged local changes',
    $rustCoreSuite . 'has-our-changes',
    'has_our_changes',
    function () use ($port): void {
        $left = $port->set($port->init('aaaaaa'), 'a', 1);
        $right = $port->set($port->init('bbbbbb'), 'b', 2);
        $leftToRight = $port->initSyncState();
        $rightToLeft = $port->initSyncState();
        $iterations = 0;

        truthy(! $port->hasOurChanges($left, $leftToRight), 'left should not start with acknowledged changes');
        truthy(! $port->hasOurChanges($right, $rightToLeft), 'right should not start with acknowledged changes');

        while (
            ! $port->hasOurChanges($left, $leftToRight)
            || ! $port->hasOurChanges($right, $rightToLeft)
        ) {
            $quiet = true;

            [$leftToRight, $message] = $port->generateSyncMessage($left, $leftToRight);
            if ($message !== null) {
                $quiet = false;
                [$right, $rightToLeft] = $port->receiveSyncMessage($right, $rightToLeft, $message);
            }

            [$rightToLeft, $message] = $port->generateSyncMessage($right, $rightToLeft);
            if ($message !== null) {
                $quiet = false;
                [$left, $leftToRight] = $port->receiveSyncMessage($left, $leftToRight, $message);
            }

            if ($quiet) {
                throw new RuntimeException('no sync messages were sent but the sync state says peers are not in sync');
            }

            if (++$iterations > 10) {
                throw new RuntimeException('sync acknowledgement did not converge within 10 iterations');
            }
        }

        truthy($port->hasOurChanges($left, $leftToRight), 'left changes should be acknowledged after sync exchange');
        truthy($port->hasOurChanges($right, $rightToLeft), 'right changes should be acknowledged after sync exchange');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust local map increment applies to counter conflicts only',
    $rustCoreSuite . 'test-local-inc-in-map',
    'test_local_inc_in_map',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'hello', 'world');
        $doc2 = $port->load($port->save($doc1), 'bbbbbb');
        $doc3 = $port->load($port->save($doc1), 'cccccc');

        $doc1 = $port->set($doc1, 'cnt', 20);
        $doc2 = $port->set($doc2, 'cnt', new Counter(0));
        $doc3 = $port->set($doc3, 'cnt', new Counter(10));
        $merged = $port->mergeDocuments($port->mergeDocuments($doc1, $doc2), $doc3);

        $before = $port->getConflicts($merged, 'cnt') ?? [];
        same(count($before), 3, 'setup should expose the integer and two counter conflicts');
        same($before['2@aaaaaa'] ?? null, 20, 'integer conflict should remain visible before increment');
        truthy(($before['2@bbbbbb'] ?? null) instanceof Counter, 'first counter conflict should be present before increment');
        truthy(($before['2@cccccc'] ?? null) instanceof Counter, 'second counter conflict should be present before increment');
        same($before['2@bbbbbb']->value(), 0, 'first counter should start at zero');
        same($before['2@cccccc']->value(), 10, 'second counter should start at ten');

        $incremented = $port->incrementCounter($merged, ['cnt'], 5);
        $after = $port->getConflicts($incremented, 'cnt') ?? [];

        same($incremented->toArray()['hello'] ?? null, 'world', 'increment should preserve unrelated map values');
        same(count($after), 2, 'incrementing should drop the non-counter conflict and keep both counters');
        truthy(! array_key_exists('2@aaaaaa', $after), 'non-counter conflict should be omitted after counter increment');
        truthy(($after['2@bbbbbb'] ?? null) instanceof Counter, 'first counter conflict should remain after increment');
        truthy(($after['2@cccccc'] ?? null) instanceof Counter, 'second counter conflict should remain after increment');
        same($after['2@bbbbbb']->value(), 5, 'first counter conflict should include the local increment');
        same($after['2@cccccc']->value(), 15, 'second counter conflict should include the local increment');
        same($incremented->toArray()['cnt']->value(), 15, 'incremented counter conflict winner should materialize at the root');

        $loaded = $port->load($port->save($incremented));
        $loadedConflicts = $port->getConflicts($loaded, 'cnt') ?? [];
        same(count($loadedConflicts), 2, 'counter-only conflict set should survive save/load');
        same($loadedConflicts['2@bbbbbb']->value(), 5, 'first incremented counter should survive save/load');
        same($loadedConflicts['2@cccccc']->value(), 15, 'second incremented counter should survive save/load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust list counter conflicts can be incremented and deleted',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:list-counter-del',
    'list_counter_del',
    function () use ($port): void {
        $conflictValues = static function (?array $conflicts): array {
            $values = [];
            foreach ($conflicts ?? [] as $value) {
                $values[] = $value instanceof Counter ? $value->value() : $value;
            }
            sort($values);

            return $values;
        };

        $base = $port->set($port->init('aaaaaa'), 'list', ['a', 'b', 'c']);
        $doc1 = $port->load($port->save($base), 'aaaaaa');
        $doc2 = $port->load($port->save($base), 'bbbbbb');
        $doc3 = $port->load($port->save($base), 'cccccc');

        $doc1 = $port->setListElement($doc1, 'list', 1, new Counter(0));
        $doc2 = $port->setListElement($doc2, 'list', 1, new Counter(10));
        $doc3 = $port->setListElement($doc3, 'list', 1, new Counter(100));

        $doc1 = $port->setListElement($doc1, 'list', 2, new Counter(0));
        $doc2 = $port->setListElement($doc2, 'list', 2, new Counter(10));
        $doc3 = $port->setListElement($doc3, 'list', 2, 100);

        $doc1 = $port->incrementCounter($doc1, ['list', 1], 1);
        $doc1 = $port->incrementCounter($doc1, ['list', 2], 1);
        $merged = $port->mergeDocuments($port->mergeDocuments($doc1, $doc2), $doc3);

        same($merged->toArray()['list'][0] ?? null, 'a', 'merged list should retain the unchanged first element');
        sameArray($conflictValues($port->getListElementConflicts($merged, 'list', 1)), [1, 10, 100], 'first conflicted list element should expose all counter values');
        sameArray($conflictValues($port->getListElementConflicts($merged, 'list', 2)), [1, 10, 100], 'second conflicted list element should expose counter and scalar values');

        $merged = $port->incrementCounter($merged, ['list', 1], 1);
        $merged = $port->incrementCounter($merged, ['list', 2], 1);

        sameArray($conflictValues($port->getListElementConflicts($merged, 'list', 1)), [2, 11, 101], 'incrementing a list conflict should update all counter alternatives');
        sameArray($conflictValues($port->getListElementConflicts($merged, 'list', 2)), [2, 11], 'incrementing should drop the non-counter list conflict alternative');

        $merged = $port->deleteListElements($merged, 'list', 2);
        same(count($merged->toArray()['list']), 2, 'deleting the conflicted tail element should shorten the list');
        same(count($port->load($port->save($merged), 'dddddd')->toArray()['list']), 2, 'tail deletion should survive save/load');

        $merged = $port->deleteListElements($merged, 'list', 1);
        same(count($merged->toArray()['list']), 1, 'deleting the remaining conflicted element should leave one item');
        same(count($port->load($port->save($merged), 'eeeeee')->toArray()['list']), 1, 'second deletion should survive save/load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust save/load preserves concurrent todo map conflicts',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:save-restore-complex1',
    'save_restore_complex1',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'todos', [['title' => 'water plants', 'done' => false]]);
        $doc2 = $port->load($port->save($doc1), 'bbbbbb');

        $doc2 = $port->setListElement($doc2, 'todos', 0, ['title' => 'weed plants', 'done' => false]);
        $doc1 = $port->setListElement($doc1, 'todos', 0, ['title' => 'kill plants', 'done' => false]);
        $loaded = $port->load($port->save($port->mergeDocuments($doc1, $doc2)), 'cccccc');

        $conflicts = $port->getListElementConflicts($loaded, 'todos', 0) ?? [];
        $titles = [];
        foreach ($conflicts as $value) {
            truthy(is_array($value), 'conflicting todo item should remain a map after save/load');
            same($value['done'] ?? null, false, 'conflicting todo item should preserve the unchanged done flag');
            $titles[] = $value['title'] ?? null;
        }
        sort($titles);

        sameArray($titles, ['kill plants', 'weed plants'], 'saved list map conflict should retain both concurrent titles');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust transactional save/load preserves concurrent todo map conflicts',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:save-restore-complex-transactional',
    'save_restore_complex_transactional',
    function () use ($port): void {
        $tx1 = $port->transaction($port->init('aaaaaa'));
        $tx1->set('todos', [['title' => 'water plants', 'done' => false]]);
        $doc1 = $tx1->commit();

        $doc2 = $port->load($port->save($doc1), 'bbbbbb');
        $tx2 = $port->transaction($doc2);
        $tx2->set('todos', [['title' => 'weed plants', 'done' => false]]);
        $doc2 = $tx2->commit();

        $tx1 = $port->transaction($doc1);
        $tx1->set('todos', [['title' => 'kill plants', 'done' => false]]);
        $doc1 = $tx1->commit();

        $loaded = $port->load($port->save($port->mergeDocuments($doc1, $doc2)), 'cccccc');
        $conflicts = $port->getListElementConflicts($loaded, 'todos', 0) ?? [];
        $titles = [];
        foreach ($conflicts as $value) {
            truthy(is_array($value), 'transactional conflicting todo item should remain a map after save/load');
            same($value['done'] ?? null, false, 'transactional conflicting todo item should preserve the unchanged done flag');
            $titles[] = $value['title'] ?? null;
        }
        sort($titles);

        sameArray($titles, ['kill plants', 'weed plants'], 'transactional saved list map conflict should retain both concurrent titles');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust incremental load keeps concurrent heads when one head is common',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:make-sure-load-incremental-doesnt-skip-a-load-with-a-common-head',
    'make_sure_load_incremental_doesnt_skip_a_load_with_a_common_head',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'string', 'hello');
        $doc2 = $port->load($port->save($doc1), 'bbbbbb');
        $doc3 = $port->load($port->save($doc1), 'cccccc');

        same(count($port->getHeads($doc1)), 1, 'initial document should have one head');
        $doc1 = $port->set($doc1, 'concurrent1', '123');
        $hashB = $port->getHeads($doc1)[0] ?? null;

        $doc3 = $port->loadIncremental($doc3, $port->save($doc1));
        $hashC = $port->getHeads($doc3)[0] ?? null;
        same($hashC, $hashB, 'incremental load should apply the first concurrent branch');

        $doc2 = $port->set($doc2, 'concurrent2', 'abc');
        $hashD = $port->getHeads($doc2)[0] ?? null;
        $doc2 = $port->mergeDocuments($doc2, $doc1);
        $heads = $port->getHeads($doc2);

        same(count($heads), 2, 'merged document should retain both concurrent heads');
        truthy(in_array($hashB, $heads, true), 'merged heads should contain the first branch');
        truthy(in_array($hashD, $heads, true), 'merged heads should contain the second branch');

        $doc3 = $port->loadIncremental($doc3, $port->save($doc2));

        sameArray($port->getHeads($doc3), $port->getHeads($doc2), 'incremental load should not skip the load when one head is already common');
        $doc3Materialized = $doc3->toArray();
        $doc2Materialized = $doc2->toArray();
        ksort($doc3Materialized);
        ksort($doc2Materialized);
        sameArray($doc3Materialized, $doc2Materialized, 'incremental load should materialize both concurrent branches');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust mappings allow empty string keys',
    $rustCoreSuite . 'allows-empty-keys-in-mappings',
    'allows_empty_keys_in_mappings',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), '', 1);

        sameArray($doc->toArray(), ['' => 1], 'empty string map key should materialize with its value');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent updates of the same field expose both values',
    $rustCoreSuite . 'concurrent-updates-of-same-field',
    'concurrent_updates_of_same_field',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'field', 'one');
        $doc2 = $port->set($port->init('aaaaaa'), 'field', 'two');
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray(
            $port->getConflicts($merged, 'field') ?? [],
            [
                '1@aaaaaa' => 'two',
                '1@bbbbbb' => 'one',
            ],
            'same-field merge should expose both conflicting scalar values'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust assignment conflicts of different types expose all contenders',
    $rustCoreSuite . 'assignment-conflicts-of-different-types',
    'assignment_conflicts_of_different_types',
    function () use ($port): void {
        $doc1 = $port->set($port->init('cccccc'), 'field', 'string');
        $doc2 = $port->set($port->init('bbbbbb'), 'field', []);
        $doc3 = $port->set($port->init('aaaaaa'), 'field', ['nested' => true]);
        $merged = $port->mergeDocuments($port->mergeDocuments($doc1, $doc2), $doc3);

        same($merged->toArray()['field'], 'string', 'different-type conflict should materialize the deterministic winner');
        sameArray(
            $port->getConflicts($merged, 'field') ?? [],
            [
                '1@aaaaaa' => ['nested' => true],
                '1@bbbbbb' => [],
                '1@cccccc' => 'string',
            ],
            'different-type conflict should retain scalar list and map contenders'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent updates of the same list element expose both values',
    $rustCoreSuite . 'concurrent-updates-of-same-list-element',
    'concurrent_updates_of_same_list_element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['finch']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);
        $doc1 = $port->setListElement($doc1, 'birds', 0, 'greenfinch');
        $doc2 = $port->setListElement($doc2, 'birds', 0, 'goldfinch');
        $merged = $port->mergeDocuments($doc1, $doc2);

        $conflicts = $port->getListElementConflicts($merged, 'birds', 0) ?? [];
        sort($conflicts);
        sameArray($conflicts, ['goldfinch', 'greenfinch'], 'same-list-element merge should expose both conflicting values');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust changes within a conflicting map field stay attached',
    $rustCoreSuite . 'changes-within-conflicting-map-field',
    'changes_within_conflicting_map_field',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'field', 'string');
        $doc2 = $port->set($port->init('aaaaaa'), 'field', []);
        $doc2 = $port->setNested($doc2, ['field', 'innerKey'], 42);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray(
            $port->getConflicts($merged, 'field') ?? [],
            [
                '1@aaaaaa' => ['innerKey' => 42],
                '1@bbbbbb' => 'string',
            ],
            'nested map edits should stay attached to the conflicted root map value'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust changes within a conflicting list element stay attached',
    $rustCoreSuite . 'changes-within-conflicting-list-element',
    'changes_within_conflicting_list_element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['hello']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->setListElement($doc1, 'list', 0, ['map1' => true]);
        $doc1 = $port->setNested($doc1, ['list', 0, 'key'], 1);
        $doc2 = $port->setListElement($doc2, 'list', 0, ['map2' => true]);
        $doc2 = $port->setNested($doc2, ['list', 0, 'key'], 2);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray(
            $port->getListElementConflicts($merged, 'list', 0) ?? [],
            [
                '2@aaaaaa' => ['map2' => true, 'key' => 2],
                '2@bbbbbb' => ['map1' => true, 'key' => 1],
            ],
            'nested list element map edits should stay attached to the conflicted element values'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent assignment and deletion of a map entry is add-wins',
    $rustCoreSuite . 'concurrent-assignment-and-deletion-of-a-map-entry',
    'concurrent_assignment_and_deletion_of_a_map_entry',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'bestBird', 'robin');
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->delete($doc1, 'bestBird');
        $doc2 = $port->set($doc2, 'bestBird', 'magpie');
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['bestBird' => 'magpie'], 'concurrent map assignment should win over deletion');
        same($port->getConflicts($merged, 'bestBird'), null, 'map assignment/delete merge should not create conflicts');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent assignment and deletion of a list entry is add-wins',
    $rustCoreSuite . 'concurrent-assignment-and-deletion-of-list-entry',
    'concurrent_assignment_and_deletion_of_list_entry',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['blackbird', 'thrush', 'goldfinch']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->setListElement($doc1, 'birds', 1, 'starling');
        $doc2 = $port->deleteListElements($doc2, 'birds', 1);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['birds'], ['blackbird', 'starling', 'goldfinch'], 'concurrent list assignment should resurrect the deleted element');
        same($port->getListElementConflicts($merged, 'birds', 1), null, 'list assignment/delete merge should not create element conflicts');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent deletion of the same list element is idempotent',
    $rustCoreSuite . 'concurrent-deletion-of-same-list-element',
    'concurrent_deletion_of_same_list_element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['albatross', 'buzzard', 'cormorant']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->deleteListElements($doc1, 'birds', 1);
        $doc2 = $port->deleteListElements($doc2, 'birds', 1);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['birds'], ['albatross', 'cormorant'], 'concurrent deletion of the same list element should remove it once');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent insertions at different list positions merge cleanly',
    $rustCoreSuite . 'concurrent-insertions-at-different-list-positions',
    'concurrent_insertions_at_different_list_positions',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['one', 'three']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->insertListElements($doc1, 'list', 1, ['two']);
        $doc2 = $port->pushList($doc2, 'list', ['four']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['list'], ['one', 'two', 'three', 'four'], 'different-position concurrent list insertions should merge cleanly');
        same($port->getConflicts($merged, 'list'), null, 'different-position list insertions should not conflict');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent insertions at the same list position merge cleanly',
    $rustCoreSuite . 'concurrent-insertions-at-same-list-position',
    'concurrent_insertions_at_same_list_position',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['parakeet']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->pushList($doc1, 'birds', ['starling']);
        $doc2 = $port->pushList($doc2, 'birds', ['chaffinch']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['birds'], ['parakeet', 'starling', 'chaffinch'], 'same-position concurrent list insertions should keep both values');
        same($port->getConflicts($merged, 'birds'), null, 'same-position list insertions should not conflict');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent updates at different tree levels prefer the delete',
    $rustCoreSuite . 'concurrent-updates-at-different-levels',
    'concurrent_updates_at_different_levels',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'animals', [
            'birds' => ['pink' => 'flamingo', 'black' => 'starling'],
            'mammals' => ['badger'],
        ]);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->setNested($doc1, ['animals', 'birds', 'brown'], 'sparrow');
        $doc2 = $port->deleteNested($doc2, ['animals', 'birds']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['animals'], ['mammals' => ['badger']], 'higher-level delete should win over the subtree update');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent update under a deleted object does not resurrect it',
    $rustCoreSuite . 'concurrent-updates-of-concurrently-deleted-objects',
    'concurrent_updates_of_concurrently_deleted_objects',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['blackbird' => ['feathers' => 'black']]);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->deleteNested($doc1, ['birds', 'blackbird']);
        $doc2 = $port->setNested($doc2, ['birds', 'blackbird', 'beak'], 'orange');
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray(), ['birds' => []], 'nested update under a deleted object should not resurrect it');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrently assigned nested maps do not merge',
    $rustCoreSuite . 'concurrently-assigned-nested-maps-should-not-merge',
    'concurrently_assigned_nested_maps_should_not_merge',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'config', ['background' => 'blue']);
        $doc2 = $port->set($port->init('aaaaaa'), 'config', ['logo_url' => 'logo.png']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray(
            $port->getConflicts($merged, 'config') ?? [],
            [
                '1@aaaaaa' => ['logo_url' => 'logo.png'],
                '1@bbbbbb' => ['background' => 'blue'],
            ],
            'concurrently assigned nested maps should remain root conflicts'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust same-position sequence insertions do not interleave',
    $rustCoreSuite . 'does-not-interleave-sequence-insertions-at-same-position',
    'does_not_interleave_sequence_insertions_at_same_position',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'wisdom', []);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->insertListElements($doc1, 'wisdom', 0, ['to', 'be', 'is', 'to', 'do']);
        $doc2 = $port->insertListElements($doc2, 'wisdom', 0, ['to', 'do', 'is', 'to', 'be']);
        $wisdom = $port->mergeDocuments($doc1, $doc2)->toArray()['wisdom'];

        truthy(
            $wisdom === ['to', 'be', 'is', 'to', 'do', 'to', 'do', 'is', 'to', 'be']
                || $wisdom === ['to', 'do', 'is', 'to', 'be', 'to', 'be', 'is', 'to', 'do'],
            'same-position sequence insertions should stay grouped'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust insertion after a deleted list element survives',
    $rustCoreSuite . 'insertion-after-a-deleted-list-element',
    'insertion_after_a_deleted_list_element',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'birds', ['blackbird', 'thrush', 'goldfinch']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc1 = $port->deleteListElements($doc1, 'birds', 1, 2);
        $doc2 = $port->insertListElements($doc2, 'birds', 2, ['starling']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        sameArray($merged->toArray()['birds'], ['blackbird', 'starling'], 'insertion after deleted list elements should survive');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust causal list insertions preserve insertion order',
    $rustCoreSuite . 'insertion-consistent-with-causality',
    'insertion_consistent_with_causality',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['four']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);

        $doc2 = $port->insertListElements($doc2, 'list', 0, ['three']);
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $doc1 = $port->insertListElements($doc1, 'list', 0, ['two']);
        $doc2 = $port->mergeDocuments($doc2, $doc1);
        $doc2 = $port->insertListElements($doc2, 'list', 0, ['one']);

        sameArray($doc2->toArray()['list'], ['one', 'two', 'three', 'four'], 'causal list insertions should materialize in causal order');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust repeated map assignment has no conflict',
    $rustCoreSuite . 'no-conflict-on-repeated-assignment',
    'no_conflict_on_repeated_assignment',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'foo', 'bar');
        $doc = $port->set($doc, 'foo', 'baz');

        sameArray($doc->toArray(), ['foo' => 'baz'], 'repeated map assignment should keep the latest value');
        same($port->getConflicts($doc, 'foo'), null, 'repeated map assignment should not create conflicts');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust save and restore empty document',
    $rustCoreSuite . 'save-and-restore-empty',
    'save_and_restore_empty',
    function () use ($port): void {
        $loaded = $port->load($port->save($port->init('aaaaaa')), 'bbbbbb');

        sameArray($loaded->toArray(), [], 'saved and restored empty document should stay empty');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust same-position insertion by greater actor id prepends to the list',
    $rustCoreSuite . 'mutliple-insertions-at-same-list-position-with-insertion-by-greater-actor-id',
    'mutliple_insertions_at_same_list_position_with_insertion_by_greater_actor_id',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'list', ['two']);
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);
        $doc2 = $port->insertListElements($doc2, 'list', 0, ['one']);

        sameArray($doc2->toArray()['list'], ['one', 'two'], 'later same-position insertion by greater actor should appear before the existing value');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust same-position insertion by lesser actor id prepends to the list',
    $rustCoreSuite . 'mutliple-insertions-at-same-list-position-with-insertion-by-lesser-actor-id',
    'mutliple_insertions_at_same_list_position_with_insertion_by_lesser_actor_id',
    function () use ($port): void {
        $doc1 = $port->set($port->init('bbbbbb'), 'list', ['two']);
        $doc2 = $port->mergeDocuments($port->init('aaaaaa'), $doc1);
        $doc2 = $port->insertListElements($doc2, 'list', 0, ['one']);

        sameArray($doc2->toArray()['list'], ['one', 'two'], 'later same-position insertion by lesser actor should appear before the existing value');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust save and reload preserves object created without child operations',
    $rustCoreSuite . 'save-and-reload-create-object',
    'save_and_reload_create_object',
    function () use ($port): void {
        $doc = $port->batchCreateObject($port->init('aaaaaa'), 'foo', []);
        $loaded = $port->load($port->save($doc), 'bbbbbb');
        $loaded = $port->insertListElements($loaded, 'foo', 0, [1]);

        sameArray($loaded->toArray(), ['foo' => [1]], 'a saved empty list object should reload and accept later insertions');
        sameArray($port->load($port->save($loaded), 'cccccc')->toArray(), ['foo' => [1]], 'saved list insertion after reload should load again');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust counter changes can be applied to a fresh document',
    $rustCoreSuite . 'observe-counter-change-application',
    'observe_counter_change_application',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'counter', new Counter(1));
        $doc = $port->incrementCounter($doc, ['counter'], 2);
        $doc = $port->incrementCounter($doc, ['counter'], 5);
        $applied = $port->applyChanges($port->init('bbbbbb'), $port->getAllChanges($doc));

        $counter = $applied->toArray()['counter'] ?? null;
        truthy($counter instanceof Counter, 'applied counter changes should materialize as a native counter');
        same($counter->value(), 8, 'applied counter increments should preserve the accumulated counter value');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust negative 64-bit-adjacent integer stores and loads',
    $rustCoreSuite . 'negative-64',
    'negative_64',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', -64);
        $loaded = $port->load($port->save($doc), 'bbbbbb');

        same($loaded->toArray()['a'], -64, 'negative integer scalar should survive save/load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust repeated optree-boundary map updates apply after save-load',
    $rustCoreSuite . 'bad-change-on-optree-node-boundary',
    'bad_change_on_optree_node_boundary',
    function () use ($port): void {
        $doc = $port->setMany($port->init('aaaaaa'), [
            'a' => 'z',
            'b' => 0,
            'c' => 0,
        ]);

        $iterations = 15;
        for ($i = 0; $i < $iterations; ++$i) {
            $doc = $port->setMany($doc, [
                'a' => str_repeat('a', $i),
                'b' => $i + 1,
                'c' => $i + 1,
            ]);
        }

        $loaded = $port->load($port->save($doc), 'bbbbbb');
        $loadedHeads = $port->getHeads($loaded);

        $i = $iterations + 2;
        $doc = $port->setMany($doc, [
            'a' => str_repeat('a', $i),
            'b' => $i,
            'c' => $i,
        ]);

        $changes = $port->getChangesSince($doc, $loadedHeads);
        same(count($changes), 1, 'only one change should be needed after the loaded heads');

        $loaded = $port->applyChanges($loaded, $changes);
        $roundTripped = $port->load($port->save($loaded), 'cccccc');

        sameArray(
            $roundTripped->toArray(),
            ['a' => str_repeat('a', $i), 'b' => $i, 'c' => $i],
            'applied post-load change should survive a second save/load cycle'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust load document with deleted root objects succeeds',
    $rustCoreSuite . 'load-doc-with-deleted-objects',
    'load_doc_with_deleted_objects',
    function () use ($port): void {
        $doc = $port->batchCreateObject($port->init('aaaaaa'), 'list', []);
        $doc = $port->batchCreateObject($doc, 'text', new TextValue());
        $doc = $port->batchCreateObject($doc, 'map', ['child' => 'value']);
        $doc = $port->batchCreateObject($doc, 'table', []);
        foreach (['list', 'text', 'map', 'table'] as $key) {
            $doc = $port->delete($doc, $key);
        }

        sameArray($port->load($port->save($doc), 'bbbbbb')->toArray(), [], 'document containing deleted root objects should load successfully');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust insertion after many map deletes keeps the container valid',
    $rustCoreSuite . 'insert-after-many-deletes',
    'insert_after_many_deletes',
    function () use ($port): void {
        $doc = $port->batchCreateObject($port->init('aaaaaa'), 'object', []);
        for ($i = 0; $i < 100; ++$i) {
            $key = (string) $i;
            $doc = $port->setNested($doc, ['object', $key], $i);
            $doc = $port->deleteNested($doc, ['object', $key]);
        }

        sameArray($doc->toArray(), ['object' => []], 'repeated nested insert/delete should leave an empty map container');
        sameArray($port->load($port->save($doc), 'bbbbbb')->toArray(), ['object' => []], 'empty map container should survive save/load after many deletes');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust simple no-op bad save/load regression loads successfully',
    $rustCoreSuite . 'simple-bad-saveload',
    'simple_bad_saveload',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'count', 0);
        $doc = $port->emptyChange($doc);
        $doc = $port->set($doc, 'count', 0);

        sameArray($port->load($port->save($doc), 'bbbbbb')->toArray(), ['count' => 0], 'save/load should tolerate a no-op set after an empty change');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust load incremental applies complete prefix and ignores corrupted tail',
    $rustCoreSuite . 'load-incremental-with-corrupted-tail',
    'load_incremental_with_corrupted_tail',
    function () use ($port): void {
        $source = $port->set($port->init('aaaaaa'), 'key', 'value');
        $payload = $port->save($source);
        $corruptedTail = "\x01\x02\x03\x04";
        $result = $port->loadIncrementalPrefix($port->init('bbbbbb'), $payload . $corruptedTail);
        $loaded = $result['document'];

        same($result['loadedChanges'], 1, 'loadIncrementalPrefix should apply the single complete change before the corrupted tail');
        same($result['bytesConsumed'], strlen($payload), 'loadIncrementalPrefix should report the complete JSON payload length');
        same($result['trailingBytes'], $corruptedTail, 'loadIncrementalPrefix should expose unconsumed corrupted tail bytes');
        sameArray($loaded->toArray(), ['key' => 'value'], 'document should materialize the complete prefix despite trailing bytes');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust regression nth miscount resolves nested list maps',
    $rustCoreSuite . 'regression-nth-miscount',
    'regression_nth_miscount',
    function () use ($port): void {
        $doc = $port->batchCreateObject($port->init('aaaaaa'), 'listval', []);
        for ($i = 0; $i < 30; ++$i) {
            $doc = $port->insertListElements($doc, 'listval', $i, [[]]);
            $doc = $port->setNested($doc, ['listval', $i, 'test'], $i);
        }

        foreach ($doc->toArray()['listval'] as $index => $item) {
            same($item['test'] ?? null, $index, 'nested list map should remain readable at its insertion index');
        }
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust regression nth miscount smaller resolves overwritten list scalars',
    $rustCoreSuite . 'regression-nth-miscount-smaller',
    'regression_nth_miscount_smaller',
    function () use ($port): void {
        $doc = $port->batchCreateObject($port->init('aaaaaa'), 'listval', []);
        for ($i = 0; $i < 64; ++$i) {
            $doc = $port->insertListElements($doc, 'listval', $i, [null]);
            $doc = $port->setListElement($doc, 'listval', $i, $i);
        }

        foreach ($doc->toArray()['listval'] as $index => $item) {
            same($item, $index, 'overwritten list scalar should remain readable at its insertion index');
        }
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust regression insert opid replays list insertions',
    $rustCoreSuite . 'regression-insert-opid',
    'regression_insert_opid',
    function () use ($port): void {
        $doc = $port->batchCreateObject($port->init('aaaaaa'), 'list', []);
        $change1 = $port->getLastLocalChange($doc);

        for ($i = 0; $i <= 30; ++$i) {
            $doc = $port->insertListElements($doc, 'list', $i, [null]);
            $doc = $port->setListElement($doc, 'list', $i, $i);
        }

        $change2 = $port->getLastLocalChange($doc);
        $replayed = $port->applyChanges($port->init('bbbbbb'), [$change1]);
        $replayed = $port->applyChanges($replayed, [$change2]);

        sameArray($replayed->toArray(), $doc->toArray(), 'replayed list insertion change should match the source document');
        sameArray($port->load($port->save($replayed), 'cccccc')->toArray(), ['list' => range(0, 30)], 'replayed list insertion result should survive save/load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust text splice save/load keeps loaded edits',
    $rustCoreSuite . 'test-merging-test-conflicts-then-saving-and-loading',
    'test_merging_test_conflicts_then_saving_and_loading',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'text', new TextValue());
        $doc1 = $port->splice($doc1, 'text', 0, 0, 'hello');

        $doc2 = $port->load($port->save($doc1), 'bbbbbb');
        sameArray($doc2->toArray(), ['text' => 'hello'], 'loaded text document should expose the original text');

        $doc2 = $port->splice($doc2, 'text', 4, 1, '');
        $doc2 = $port->splice($doc2, 'text', 4, 0, '!');
        $doc2 = $port->splice($doc2, 'text', 5, 0, ' ');
        $doc2 = $port->splice($doc2, 'text', 6, 0, 'world');

        sameArray($doc2->toArray(), ['text' => 'hell! world'], 'text splices should materialize the edited text');
        sameArray($port->load($port->save($doc2), 'cccccc')->toArray(), ['text' => 'hell! world'], 'edited text should survive save/load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust inserting text near deleted marks keeps text coherent',
    $rustCoreSuite . 'inserting-text-near-deleted-marks',
    'inserting_text_near_deleted_marks',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', new TextValue());
        $doc = $port->splice($doc, 'text', 0, 0, 'hello world');
        $doc = $port->mark($doc, ['text'], 2, 8, 'bold', true, 'after');
        $doc = $port->mark($doc, ['text'], 3, 6, 'link', true, 'none');

        $doc = $port->splice($doc, 'text', 1, 10, '');
        sameArray($doc->toArray(), ['text' => 'h'], 'deleting across marked text should leave the first character');

        $doc = $port->splice($doc, 'text', 0, 0, 'a');
        sameArray($doc->toArray(), ['text' => 'ah'], 'inserting before the remaining character should preserve text order');

        $doc = $port->splice($doc, 'text', 2, 0, 'a');
        sameArray($doc->toArray(), ['text' => 'aha'], 'inserting after the remaining character should preserve text order');
        sameArray($port->load($port->save($doc), 'bbbbbb')->toArray(), ['text' => 'aha'], 'post-mark-deletion insertions should survive save/load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust overwriting identical scalar conflicts clears duplicates',
    $rustCoreSuite . 'test-overwriting-a-conflict',
    'test_overwriting_a_conflict',
    function () use ($port): void {
        $doc1 = $port->init('aaaaaa');
        $doc2 = $port->clone($doc1, 'bbbbbb');

        $doc1 = $port->set($doc1, 'key', 'value');
        $doc2 = $port->set($doc2, 'key', 'value');
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $doc2 = $port->mergeDocuments($doc2, $doc1);

        same(count($port->getConflicts($doc1, 'key') ?? []), 2, 'identical concurrent scalar puts should expose both conflict entries in the first document');
        same(count($port->getConflicts($doc2, 'key') ?? []), 2, 'identical concurrent scalar puts should expose both conflict entries in the second document');

        $doc1 = $port->set($doc1, 'key', 'value');
        $doc2 = $port->set($doc2, 'key', 'value');
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $doc2 = $port->mergeDocuments($doc2, $doc1);

        same($port->getConflicts($doc1, 'key'), null, 'overwriting the identical conflict should collapse to one value in the first document');
        same($port->getConflicts($doc2, 'key'), null, 'overwriting the identical conflict should collapse to one value in the second document');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust repeated map assignment resolves existing conflict',
    $rustCoreSuite . 'repeated-map-assignment-which-resolves-conflict-not-ignored',
    'repeated_map_assignment_which_resolves_conflict_not_ignored',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'field', 123);
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);
        $doc2 = $port->set($doc2, 'field', 456);
        $doc1 = $port->set($doc1, 'field', 789);
        $merged = $port->mergeDocuments($doc1, $doc2);

        same(count($port->getConflicts($merged, 'field') ?? []), 2, 'concurrent map assignments should create a two-value conflict');
        $resolved = $port->set($merged, 'field', 123);

        sameArray($resolved->toArray(), ['field' => 123], 'repeated map assignment should resolve the conflict to the assigned value');
        same($port->getConflicts($resolved, 'field'), null, 'resolved map assignment should clear conflicts');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust repeated list assignment resolves existing conflict',
    $rustCoreSuite . 'repeated-list-assignment-which-resolves-conflict-not-ignored',
    'repeated_list_assignment_which_resolves_conflict_not_ignored',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'list', [123]);
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);
        $doc2 = $port->setListElement($doc2, 'list', 0, 456);
        $doc1 = $port->mergeDocuments($doc1, $doc2);
        $doc1 = $port->setListElement($doc1, 'list', 0, 789);

        sameArray($doc1->toArray(), ['list' => [789]], 'repeated list assignment should resolve the element conflict');
        same($port->getListElementConflicts($doc1, 'list', 0), null, 'resolved list assignment should clear element conflicts');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust list deletion removes the selected element',
    $rustCoreSuite . 'list-deletion',
    'list_deletion',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', [123, 456, 789]);
        $doc = $port->deleteListElements($doc, 'list', 1);

        sameArray($doc->toArray(), ['list' => [123, 789]], 'list deletion should remove exactly the selected element');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust concurrent map property updates merge',
    $rustCoreSuite . 'merge-concurrent-map-prop-updates',
    'merge_concurrent_map_prop_updates',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'foo', 'bar');
        $doc2 = $port->set($port->init('bbbbbb'), 'hello', 'world');
        $merged1 = $port->mergeDocuments($doc1, $doc2);
        $merged2 = $port->mergeDocuments($doc2, $merged1);

        same($merged1->toArray()['foo'] ?? null, 'bar', 'different-property concurrent merge should preserve foo');
        same($merged1->toArray()['hello'] ?? null, 'world', 'different-property concurrent merge should preserve hello');
        same($merged2->toArray()['foo'] ?? null, 'bar', 'merging back should preserve foo');
        same($merged2->toArray()['hello'] ?? null, 'world', 'merging back should preserve hello');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust incrementing non-counter map values fails but counters still increment',
    $rustCoreSuite . 'increment-non-counter-map',
    'increment_non_counter_map',
    function () use ($port): void {
        throwsLike(
            static fn () => $port->incrementCounter($port->init('aaaaaa'), ['nothing'], 2),
            'Cannot increment a non-counter value',
            'incrementing a missing map key should fail'
        );

        $doc = $port->set($port->init('aaaaaa'), 'non-counter', 'mystring');
        throwsLike(
            static fn () => $port->incrementCounter($doc, ['non-counter'], 2),
            'Cannot increment a non-counter value',
            'incrementing a scalar map key should fail'
        );

        $doc = $port->set($doc, 'counter', new Counter(1));
        $doc = $port->incrementCounter($doc, ['counter'], 2);
        same($doc->toArray()['counter']->value(), 3, 'map counter should still increment after non-counter failures');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust incrementing non-counter list values fails but counters still increment',
    $rustCoreSuite . 'increment-non-counter-list',
    'increment_non_counter_list',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['mystring']);
        throwsLike(
            static fn () => $port->incrementCounter($doc, ['list', 0], 2),
            'Cannot increment a non-counter value',
            'incrementing a scalar list element should fail'
        );

        $doc = $port->insertListElements($doc, 'list', 0, [new Counter(1)]);
        $doc = $port->incrementCounter($doc, ['list', 0], 2);
        same($doc->toArray()['list'][0]->value(), 3, 'list counter should still increment after non-counter failure');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust list operations reject invalid indexes',
    $rustCoreSuite . 'invalid-index',
    'invalid_index',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', []);
        $doc = $port->insertListElementsStrict($doc, 'a', 0, [1]);
        $doc = $port->setListElementStrict($doc, 'a', 0, 2);

        sameArray($doc->toArray(), ['a' => [2]], 'valid list put should update the existing element');
        throwsLike(
            static fn (): Document => $port->insertListElementsStrict($doc, 'a', 2, [1]),
            'List insertion index is out of bounds',
            'insert should reject an index beyond the list end'
        );
        throwsLike(
            static fn (): Document => $port->setListElementStrict($doc, 'a', 1, 2),
            'List assignment index is out of bounds',
            'put should reject an index at the list end'
        );
        throwsLike(
            static fn (): Document => $port->insertListElementsStrict($doc, 'a', 100, [1]),
            'List insertion index is out of bounds',
            'insert should reject a distant out-of-bounds index'
        );
        throwsLike(
            static fn (): Document => $port->setListElementStrict($doc, 'a', 100, 2),
            'List assignment index is out of bounds',
            'put should reject a distant out-of-bounds index'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust operations on the wrong object type are rejected',
    $rustCoreSuite . 'ops-on-wrong-objets',
    'ops_on_wrong_objets',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['a', 'b']);
        throwsLike(
            static fn (): Document => $port->setNested($doc, ['list', 'a'], 'AAA'),
            'Cannot use a map key on a list value',
            'putting a map key on a list should fail'
        );
        throwsLike(
            static fn (): Document => $port->splice($doc, 'list', 0, 0, 'hello world'),
            'Invalid text operation on non-text value',
            'splicing text into a list should fail'
        );

        $doc = $port->set($doc, 'map', ['a' => 'AAA', 'b' => 'BBB']);
        throwsLike(
            static fn (): Document => $port->spliceList($doc, 'map', 0, 0, ['b']),
            'Invalid list operation on non-list value',
            'inserting a list value into a map should fail'
        );
        throwsLike(
            static fn (): Document => $port->splice($doc, 'map', 0, 0, 'hello world'),
            'Invalid text operation on non-text value',
            'splicing text into a map should fail'
        );

        $doc = $port->set($doc, 'text', 'hello world');
        throwsLike(
            static fn (): Document => $port->setNested($doc, ['text', 'a'], 'AAA'),
            'Cannot modify a scalar value as a container',
            'putting a map key on text should fail'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust save and load incremented counter change metadata',
    $rustCoreSuite . 'save-and-load-incremented-counter',
    'save_and_load_incremented_counter',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'counter', new Counter(1));
        $doc = $port->incrementCounter($doc, ['counter'], 1);
        $changes = $port->getAllChanges($doc);
        $roundTripped = json_decode(json_encode($changes, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        sameArray($roundTripped, $changes, 'expanded native counter changes should round trip through JSON');
        same($port->load($port->save($doc), 'bbbbbb')->toArray()['counter']->value(), 2, 'incremented counter should survive save/load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust get change metadata since heads returns the next change',
    $rustCoreSuite . 'test-get-change-meta',
    'test_get_change_meta',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 1);
        $startHeads = $port->getHeads($doc);
        $doc = $port->set($doc, 'b', 2);
        $changes = $port->getChangesMetaSince($doc, $startHeads);

        same(count($changes), 1, 'change metadata since heads should include one later change');
        same($changes[0]['actor'] ?? null, $port->getActorId($doc), 'change metadata should expose the document actor');
        same($changes[0]['seq'] ?? null, 2, 'change metadata should expose the second sequence number');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust get changes after an empty-change head is empty',
    $rustCoreSuite . 'get-changes-with-hash-of-empty-change-produces-correct-result',
    'get_changes_with_hash_of_empty_change_produces_correct_result',
    function () use ($port): void {
        $doc = $port->emptyChange($port->init('aaaaaa'));
        $heads = $port->getHeads($doc);

        sameArray($port->getChangesSince($doc, $heads), [], 'getChanges since the empty-change head should be empty');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust change graph clock cache regression keeps merged branch ancestors covered',
    'rust:tests-test-rs-target-debug-deps-test-769fe2d72b51cc45:reproduce-clock-cache-bug',
    'reproduce_clock_cache_bug',
    function () use ($port): void {
        $base = $port->init('aaaaaa');
        for ($i = 0; $i < 100; ++$i) {
            $base = $port->set($base, 'initial_commit_' . $i, true);
        }

        $branches = [];
        for ($i = 0; $i < 19; ++$i) {
            $branches[] = $port->clone($base, sprintf('%06x', $i + 1));
        }
        $branches[] = $base;

        foreach ($branches as $branchNo => $branch) {
            for ($commitNo = 0; $commitNo < 2; ++$commitNo) {
                $branch = $port->set($branch, 'branch_' . $branchNo . '-' . $commitNo, true);
                $branch = $port->clone($branch, sprintf('%06x', 100 + ($branchNo * 2) + $commitNo));
            }
            $branches[$branchNo] = $branch;
        }

        $base = array_pop($branches);
        foreach ($branches as $branch) {
            $base = $port->mergeDocuments($base, $branch);
        }

        for ($i = 0; $i < 100; ++$i) {
            $base = $port->set($base, 'after-merge-' . $i, true);
        }

        $heads = $port->getHeads($base);
        same(count($port->getAllChanges($base)), 240, 'clock-cache regression fixture should create the expected branchy graph size');
        sameArray($port->getChangesSince($base, $heads), [], 'getChanges since current heads should treat every branch ancestor as covered');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust last local change hash matches document heads',
    $rustCoreSuite . 'test-get-last-local-change-generation',
    'test_get_last_local_change_generation',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', new TextValue());
        foreach ([[0, 0, 'hello world'], [5, 1, 'X'], [6, 1, ''], [0, 0, 'ten thousand and five hundred']] as $splice) {
            $doc = $port->splice($doc, 'text', $splice[0], $splice[1], $splice[2]);
            $change = $port->getLastLocalChange($doc);
            sameArray($port->getHeads($doc), [$change['hash'] ?? ''], 'last local change hash should be the current document head');
        }
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust stats reports change and operation counts',
    $rustCoreSuite . 'stats-smoke-test',
    'stats_smoke_test',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 1);
        $doc = $port->set($doc, 'b', 2);
        $stats = $port->stats($doc);

        same($stats['numChanges'], 2, 'stats should report two native changes');
        same($stats['numOps'], 2, 'stats should report two native operations');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust zero-length strings and bytes store as values',
    $rustCoreSuite . 'zero-length-data',
    'zero_length_data',
    function () use ($port): void {
        $doc = $port->setMany($port->init('aaaaaa'), [
            'string' => '',
            'bytes' => new BytesValue([]),
        ]);
        $loaded = $port->load($port->save($doc), 'bbbbbb');

        same($loaded->toArray()['string'], '', 'empty string should survive save/load');
        truthy($loaded->toArray()['bytes'] instanceof BytesValue, 'empty bytes should remain a native BytesValue');
        sameArray($loaded->toArray()['bytes']->bytes(), [], 'empty bytes should preserve an empty byte list');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust save with delete-only actor references loads',
    $rustCoreSuite . 'save-with-ops-which-reference-actors-only-via-delete',
    'save_with_ops_which_reference_actors_only_via_delete',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 1);
        $forked = $port->mergeDocuments($port->init('bbbbbb'), $doc);
        $forked = $port->delete($forked, 'a');
        $merged = $port->mergeDocuments($doc, $forked);

        sameArray($port->load($port->save($merged), 'cccccc')->toArray(), [], 'document containing delete-only actor references should load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust delete-only list change survives load and later insert',
    $rustCoreSuite . 'delete-only-change',
    'delete_only_change',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['a']);
        $doc = $port->load($port->save($doc), 'aaaaaa');
        $doc = $port->deleteListElements($doc, 'list', 0);

        sameArray($doc->toArray(), ['list' => []], 'delete-only list change should remove the original element');

        $loaded = $port->load($port->save($doc), 'aaaaaa');
        $loaded = $port->insertListElements($loaded, 'list', 0, ['b']);
        $changes = $port->getAllChanges($loaded);

        sameArray($loaded->toArray(), ['list' => ['b']], 'a reloaded delete-only list change should accept later insertions');
        same(count($changes), 3, 'delete-only change and later insertion should both remain in history');
        truthy(
            ($changes[2]['startOp'] ?? 0) > ($changes[1]['startOp'] ?? 0),
            'operation counters should advance after reloading a delete-only list change'
        );
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust save with empty commits loads',
    $rustCoreSuite . 'save-with-empty-commits',
    'save_with_empty_commits',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 1);
        $forked = $port->mergeDocuments($port->init('bbbbbb'), $doc);
        $forked = $port->emptyChange($forked);
        $merged = $port->mergeDocuments($doc, $forked);

        sameArray($port->load($port->save($merged), 'cccccc')->toArray(), ['a' => 1], 'document merged with an empty commit should load');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust partial incremental load applies selected changes',
    $rustCoreSuite . 'test-load-incremental-partial-load',
    'test_load_incremental_partial_load',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'a', 1);
        $startHeads = $port->getHeads($doc);
        $doc = $port->set($doc, 'b', 2);
        $loaded = $port->loadIncremental($port->init('bbbbbb'), $port->saveSince($doc, $startHeads));

        sameArray($loaded->toArray(), ['b' => 2], 'partial incremental load should apply the selected later change');
        same($port->getMissingDeps($loaded), [$startHeads[0]], 'partial incremental load should report the missing dependency');
    },
    'rust/automerge/tests/test.rs'
);

$rustMapped(
    'rust current_state renders root, map, list, and text values',
    $rustCurrentStateSuite . 'basic-test',
    'automerge::current_state::tests::basic_test',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');
        $doc = $port->set($doc, 'map', []);
        $doc = $port->setNested($doc, ['map', 'nested_key'], 'value');
        $doc = $port->set($doc, 'list', []);
        $doc = $port->insertListElements($doc, 'list', 0, ['value']);
        $doc = $port->set($doc, 'text', new TextValue());
        $doc = $port->splice($doc, 'text', 0, 0, 'a');

        sameArray(
            $doc->toArray(),
            ['key' => 'value', 'map' => ['nested_key' => 'value'], 'list' => ['value'], 'text' => 'a'],
            'current_state basic setup should materialize the expected document'
        );

        $patches = $port->diff($doc, [], $port->getHeads($doc));
        foreach ([['key'], ['map'], ['list'], ['text'], ['map', 'nested_key']] as $path) {
            truthy(
                count(array_filter($patches, static fn (array $patch): bool => ($patch['path'] ?? null) === $path)) >= 1,
                'current_state should include a patch for ' . json_encode($path)
            );
        }
        truthy(
            count(array_filter(
                $patches,
                static fn (array $patch): bool => ($patch['action'] ?? null) === 'insert'
                    && ($patch['path'] ?? null) === ['list', 0]
            )) === 1,
            'current_state should include the list insertion'
        );
        truthy(
            count(array_filter(
                $patches,
                static fn (array $patch): bool => ($patch['action'] ?? null) === 'put'
                    && ($patch['path'] ?? null) === ['text']
                    && ($patch['value'] ?? null) === 'a'
            )) === 1,
            'current_state should include the text materialization'
        );
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state omits deleted operations while keeping live containers',
    $rustCurrentStateSuite . 'test-deleted-ops-omitted',
    'automerge::current_state::tests::test_deleted_ops_omitted',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'value');
        $doc = $port->delete($doc, 'key');
        $doc = $port->set($doc, 'map', []);
        $doc = $port->setNested($doc, ['map', 'nested_key'], 'value');
        $doc = $port->deleteNested($doc, ['map', 'nested_key']);
        $doc = $port->set($doc, 'list', []);
        $doc = $port->insertListElements($doc, 'list', 0, ['value']);
        $doc = $port->deleteListElements($doc, 'list', 0);
        $doc = $port->set($doc, 'text', new TextValue());
        $doc = $port->splice($doc, 'text', 0, 0, 'a');
        $doc = $port->splice($doc, 'text', 0, 1, '');

        foreach (['deleted_map' => [], 'deleted_list' => [], 'deleted_text' => new TextValue()] as $key => $value) {
            $doc = $port->set($doc, $key, $value);
            $doc = $port->delete($doc, $key);
        }

        sameArray($doc->toArray(), ['map' => [], 'list' => [], 'text' => ''], 'current_state should retain only live containers');

        $patches = $port->diff($doc, [], $port->getHeads($doc));
        foreach ([['key'], ['deleted_map'], ['deleted_list'], ['deleted_text'], ['map', 'nested_key'], ['list', 0]] as $path) {
            truthy(
                count(array_filter($patches, static fn (array $patch): bool => ($patch['path'] ?? null) === $path)) === 0,
                'current_state should omit deleted path ' . json_encode($path)
            );
        }
        foreach ([['map'], ['list'], ['text']] as $path) {
            truthy(
                count(array_filter($patches, static fn (array $patch): bool => ($patch['path'] ?? null) === $path)) === 1,
                'current_state should retain live container path ' . json_encode($path)
            );
        }
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state text splice coalesces delete-insert output',
    $rustCurrentStateSuite . 'test-text-spliced',
    'automerge::current_state::tests::test_text_spliced',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'text', new TextValue());
        $doc = $port->splice($doc, 'text', 0, 0, 'a');
        $doc = $port->splice($doc, 'text', 1, 0, 'bcdef');
        $doc = $port->splice($doc, 'text', 2, 2, 'g');
        $patches = $port->diff($doc, [], $port->getHeads($doc));

        same($doc->toArray()['text'], 'abgef', 'text splice should insert at the original splice index after deletion');
        truthy(
            count(array_filter(
                $patches,
                static fn (array $patch): bool => ($patch['action'] ?? null) === 'put'
                    && ($patch['path'] ?? null) === ['text']
                    && ($patch['value'] ?? null) === 'abgef'
            )) === 1,
            'current_state should render the final coalesced text'
        );
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state reports counter conflicts with the current counter value',
    $rustCurrentStateSuite . 'test-counters',
    'automerge::current_state::tests::test_counters',
    function () use ($port): void {
        $doc = $port->init('bbbbbb');
        $doc2 = $port->clone($doc, 'aaaaaa');
        $doc2 = $port->set($doc2, 'key', 'someval');

        $doc = $port->set($doc, 'key', new Counter(1));
        $doc = $port->incrementCounter($doc, ['key'], 2);
        $doc = $port->incrementCounter($doc, ['key'], 3);
        $merged = $port->mergeDocuments($doc, $doc2);

        $value = $merged->toArray()['key'] ?? null;
        truthy($value instanceof Counter, 'counter conflict winner should materialize as a counter');
        same($value->value(), 6, 'counter conflict winner should include both increments');
        same(count($port->getConflicts($merged, 'key') ?? []), 2, 'current_state counter setup should preserve the conflict flag source data');
        truthy(
            count(array_filter(
                $port->diff($merged, [], $port->getHeads($merged)),
                static fn (array $patch): bool => ($patch['path'] ?? null) === ['key']
                    && ($patch['value'] ?? null) instanceof Counter
                    && $patch['value']->value() === 6
            )) === 1,
            'current_state should render the current counter value'
        );
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state load emits counter put patches',
    $rustCurrentStateSuite . 'test-load-changes',
    'automerge::current_state::tests::test_load_changes',
    function () use ($port): void {
        $source = $port->set($port->init('aaaaaa'), 'a', new Counter(2000));
        $callbacks = [];
        $loaded = $port->loadWithPatchCallback(
            $port->save($source),
            static function (array $patches, array $metadata) use (&$callbacks): void {
                $callbacks[] = ['patches' => $patches, 'source' => $metadata['source'] ?? null];
            },
            'bbbbbb'
        );
        $value = $loaded->toArray()['a'] ?? null;

        truthy($value instanceof Counter, 'loaded counter fixture should materialize as a native counter');
        same($value->value(), 2000, 'loaded counter fixture should preserve the counter value');
        same(count($callbacks), 1, 'load should emit one patch callback batch');
        same($callbacks[0]['source'], 'load', 'load patch callback should identify the load source');

        $patch = $callbacks[0]['patches'][0] ?? null;
        truthy(is_array($patch), 'load patch callback should include a put patch');
        same($patch['action'] ?? null, 'put', 'load patch should put the counter at root');
        sameArray($patch['path'] ?? [], ['a'], 'load patch should target the root counter key');
        truthy(($patch['value'] ?? null) instanceof Counter, 'load patch value should be a native counter');
        same($patch['value']->value(), 2000, 'load patch counter value should match the fixture');
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state renders multiple sequential list insertions',
    $rustCurrentStateSuite . 'test-multiple-list-insertions',
    'automerge::current_state::tests::test_multiple_list_insertions',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->insertListElements($doc, 'list', 0, [1]);
        $doc = $port->insertListElements($doc, 'list', 1, [2]);
        $patches = $port->diff($doc, [], $port->getHeads($doc));

        sameArray($doc->toArray(), ['list' => [1, 2]], 'sequential list insertions should materialize in insertion order');
        foreach ([[0, 1], [1, 2]] as [$index, $value]) {
            truthy(
                count(array_filter(
                    $patches,
                    static fn (array $patch): bool => ($patch['action'] ?? null) === 'insert'
                        && ($patch['path'] ?? null) === ['list', $index]
                        && ($patch['values'] ?? null) === [$value]
                )) === 1,
                'current_state should include list insertion at index ' . $index
            );
        }
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state renders concurrent same-index list insertions',
    $rustCurrentStateSuite . 'test-concurrent-insertions-at-same-index',
    'automerge::current_state::tests::test_concurrent_insertions_at_same_index',
    function (): void {
        $port = new NativePort();
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc2 = $port->clone($doc, 'bbbbbb');

        $doc = $port->insertListElements($doc, 'list', 0, [1]);
        $doc2 = $port->insertListElements($doc2, 'list', 0, [2]);
        $merged1 = $port->mergeDocuments($doc, $doc2);
        $merged2 = $port->mergeDocuments($doc2, $merged1);
        $patches = $port->diff($merged1, [], $port->getHeads($merged1));

        sameArray($merged1->toArray(), $merged2->toArray(), 'both peers should hydrate the same concurrent list state');
        sameArray($merged1->toArray(), ['list' => [2, 1]], 'same-index concurrent inserts should use deterministic actor ordering');
        foreach ([[0, 2], [1, 1]] as [$index, $value]) {
            truthy(
                count(array_filter(
                    $patches,
                    static fn (array $patch): bool => ($patch['action'] ?? null) === 'insert'
                        && ($patch['path'] ?? null) === ['list', $index]
                        && ($patch['values'] ?? null) === [$value]
                )) === 1,
                'current_state should include concurrent list insertion at index ' . $index
            );
        }
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state renders inserted map objects in lists',
    $rustCurrentStateSuite . 'test-insert-objects',
    'automerge::current_state::tests::test_insert_objects',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->insertListElements($doc, 'list', 0, [['key' => 'value']]);
        $patches = $port->diff($doc, [], $port->getHeads($doc));

        sameArray($doc->toArray(), ['list' => [['key' => 'value']]], 'inserted object should materialize inside the list');
        truthy(
            count(array_filter(
                $patches,
                static fn (array $patch): bool => ($patch['action'] ?? null) === 'insert'
                    && ($patch['path'] ?? null) === ['list', 0]
                    && ($patch['values'] ?? null) === [['key' => 'value']]
            )) === 1,
            'current_state should include the inserted map object'
        );
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust current_state renders list inserts after element updates',
    $rustCurrentStateSuite . 'test-insert-and-update',
    'automerge::current_state::tests::test_insert_and_update',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->insertListElements($doc, 'list', 0, ['one']);
        $doc = $port->insertListElements($doc, 'list', 1, ['two']);
        $doc = $port->setListElement($doc, 'list', 0, 'three');
        $doc = $port->setListElement($doc, 'list', 1, 'four');

        sameArray($doc->toArray(), ['list' => ['three', 'four']], 'updated list elements should materialize in current state');
        $patches = $port->diff($doc, [], $port->getHeads($doc));
        foreach ([[0, 'three'], [1, 'four']] as [$index, $value]) {
            truthy(
                count(array_filter(
                    $patches,
                    static fn (array $patch): bool => ($patch['action'] ?? null) === 'splice'
                        && ($patch['path'] ?? null) === ['list', $index, 0]
                        && ($patch['value'] ?? null) === $value
                )) === 1,
                'current_state should include updated text-like list element ' . $index
            );
        }
    },
    'rust/automerge/src/automerge/current_state.rs'
);

$rustMapped(
    'rust hydrate materializes root data and applies text patches',
    $rustHydrateSuite . 'simple-hydrate',
    'hydrate::tests::simple_hydrate',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->insertListElements($doc, 'list', 0, [5, 6, 7, 'hello', new Counter(100), [], []]);
        $doc = $port->set($doc, 'text', new TextValue());
        $doc = $port->splice($doc, 'text', 0, 0, 'hello world');

        $hydrated = $port->hydrate($doc);
        same($hydrated['list'][0] ?? null, 5, 'hydrated list should include the first scalar');
        same($hydrated['list'][3] ?? null, 'hello', 'hydrated list should include string scalars');
        truthy(($hydrated['list'][4] ?? null) instanceof Counter, 'hydrated list should retain counter scalars');
        same($hydrated['list'][4]->value(), 100, 'hydrated counter should retain its value');
        sameArray($hydrated['list'][5] ?? ['missing'], [], 'hydrated list should include an empty map object');
        sameArray($hydrated['list'][6] ?? ['missing'], [], 'hydrated list should include an empty list object');
        same($hydrated['text'] ?? null, 'hello world', 'hydrated text should expose the text value');

        $beforeHeads = $port->getHeads($doc);
        $doc = $port->splice($doc, 'text', 6, 0, 'big bad ');
        same($port->hydrate($doc, ['text']), 'hello big bad world', 'path hydration should return the updated text');

        $patches = $port->diff($doc, $beforeHeads, $port->getHeads($doc));
        $hydrated = $port->applyHydratedPatches($hydrated, $patches);

        same($hydrated['text'] ?? null, 'hello big bad world', 'hydrated text should update after applying patches');
    },
    'rust/automerge/src/hydrate/tests.rs'
);

$rustMapped(
    'rust document iterator walks live root and child values',
    $rustIterDocSuite . 'doc-iter',
    'iter::doc::tests::doc_iter',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key01', 'value1');
        $doc = $port->set($doc, 'key02', 'value2');
        $doc = $port->set($doc, 'key03', []);
        $doc = $port->set($doc, 'key04', 'value4');
        $doc = $port->set($doc, 'key05', []);
        $doc = $port->set($doc, 'key06', []);
        $doc = $port->set($doc, 'key07', []);
        $doc = $port->set($doc, 'key08', []);
        $doc = $port->set($doc, 'key09', []);
        $doc = $port->set($doc, 'key10', []);
        $doc = $port->set($doc, 'key11', []);
        $doc = $port->set($doc, 'key12', []);
        $doc = $port->set($doc, 'key13', new TextValue());
        $doc = $port->splice($doc, 'key13', 0, 0, 'hello world');
        $doc = $port->setNested($doc, ['key03', 'm1key1'], 'm1value1');
        $doc = $port->setNested($doc, ['key06', 'm3key1'], 'm3value1');
        $doc = $port->setNested($doc, ['key06', 'm3key2'], 'm3value2');
        $doc = $port->setNested($doc, ['key06', 'm3key3'], 'm3value3');
        $doc = $port->setNested($doc, ['key07', 'm4key1'], 'm4value1');
        $doc = $port->setNested($doc, ['key07', 'm4key2'], 'm4value2');
        $doc = $port->insertListElements($doc, 'key08', 0, ['l1e1', 'l1e2', 'l1e3']);
        $doc = $port->setNested($doc, ['key10', 'm6key1'], 'm6value1');
        $doc = $port->setNested($doc, ['key10', 'm6key2'], 'm6value2');
        $doc = $port->setNested($doc, ['key10', 'm6key3'], 'm6value3');
        $doc = $port->insertListElements($doc, 'key11', 0, ['l2e1']);
        $doc = $port->delete($doc, 'key06');
        $doc = $port->deleteNested($doc, ['key10', 'm6key2']);

        $items = $port->iterDocument($doc);
        $pairs = array_map(
            static fn (array $item): array => [$item['key'], $item['kind'] === 'scalar' ? $item['value'] : $item['kind']],
            $items
        );

        sameArray(
            $pairs,
            [
                ['key01', 'value1'],
                ['key02', 'value2'],
                ['key03', 'map'],
                ['key04', 'value4'],
                ['key05', 'list'],
                ['key07', 'map'],
                ['key08', 'list'],
                ['key09', 'list'],
                ['key10', 'map'],
                ['key11', 'list'],
                ['key12', 'list'],
                ['key13', 'text'],
                ['m1key1', 'm1value1'],
                ['m4key1', 'm4value1'],
                ['m4key2', 'm4value2'],
                ['', 'l1e1'],
                ['', 'l1e2'],
                ['', 'l1e3'],
                ['m6key1', 'm6value1'],
                ['m6key3', 'm6value3'],
                ['', 'l2e1'],
                ['', 'hello world'],
            ],
            'document iterator should match upstream live entry order and omit deleted values'
        );
    },
    'rust/automerge/src/iter/doc.rs'
);

$rustMapped(
    'rust batch insert creates a flat map',
    $rustBatchSuite . 'batch-insert-flat-map',
    'batch_insert_flat_map',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'data', [
            'a' => 'hello',
            'b' => 42,
            'c' => true,
        ]);

        sameArray($doc->toArray()['data'], ['a' => 'hello', 'b' => 42, 'c' => true], 'flat map batch insert should materialize the nested map');
    }
);

$rustMapped(
    'rust batch insert creates nested maps',
    $rustBatchSuite . 'batch-insert-nested-maps',
    'batch_insert_nested_maps',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'nested', [
            'outer' => [
                'inner_a' => 'deep',
                'inner_b' => 99,
            ],
            'top_level' => 'flat',
        ]);

        sameArray(
            $doc->toArray()['nested'],
            ['outer' => ['inner_a' => 'deep', 'inner_b' => 99], 'top_level' => 'flat'],
            'nested map batch insert should preserve nested map values'
        );
    }
);

$rustMapped(
    'rust batch insert map overwrites an existing root key',
    $rustBatchSuite . 'batch-insert-map-overwrites-existing-key',
    'batch_insert_map_overwrites_existing_key',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'key', 'old_value');
        $doc = $port->set($doc, 'key', ['child' => 'new']);

        sameArray($doc->toArray(), ['key' => ['child' => 'new']], 'map batch insert should overwrite an existing scalar key');
    }
);

$rustMapped(
    'rust batch insert rejects scalar object creation',
    $rustBatchSuite . 'batch-insert-scalar-fails',
    'batch_insert_scalar_fails',
    function () use ($port): void {
        throwsLike(
            static fn () => $port->batchCreateObject($port->init('aaaaaa'), 'foo', 1),
            'Batch object creation requires',
            'batch object creation should reject scalar values'
        );
    }
);

$rustMapped(
    'rust batch insert creates a flat list',
    $rustBatchSuite . 'batch-insert-flat-list',
    'batch_insert_flat_list',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'nums', [1, 2, 3]);

        sameArray($doc->toArray()['nums'], [1, 2, 3], 'flat list batch insert should preserve list order');
    }
);

$rustMapped(
    'rust batch insert creates a list with nested objects',
    $rustBatchSuite . 'batch-insert-list-with-nested-objects',
    'batch_insert_list_with_nested_objects',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'users', [
            ['name' => 'alice'],
            ['name' => 'bob'],
        ]);

        sameArray($doc->toArray()['users'], [['name' => 'alice'], ['name' => 'bob']], 'list batch insert should preserve nested map elements');
    }
);

$rustMapped(
    'rust batch insert appends an object to an existing list',
    $rustBatchSuite . 'batch-insert-into-list-at-end',
    'batch_insert_into_list_at_end',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'items', ['first', 'second']);
        $doc = $port->insertListElements($doc, 'items', 2, [['key' => 'third']]);

        sameArray($doc->toArray()['items'], ['first', 'second', ['key' => 'third']], 'list batch insert at end should append the nested map');
    }
);

$rustMapped(
    'rust batch insert inserts an object into the middle of a list',
    $rustBatchSuite . 'batch-insert-into-list-at-middle',
    'batch_insert_into_list_at_middle',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'items', ['a', 'c']);
        $doc = $port->insertListElements($doc, 'items', 1, [['val' => 'b']]);

        sameArray($doc->toArray()['items'], ['a', ['val' => 'b'], 'c'], 'list batch insert in the middle should shift later values');
    }
);

$rustMapped(
    'rust batch put overwrites an existing list element',
    $rustBatchSuite . 'batch-put-overwrites-existing-list-element',
    'batch_put_overwrites_existing_list_element',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'items', ['old_a', 'old_b', 'old_c']);
        $doc = $port->setListElement($doc, 'items', 1, ['replaced' => true]);

        sameArray($doc->toArray()['items'], ['old_a', ['replaced' => true], 'old_c'], 'list batch put should replace the element without changing list length');
    }
);

$rustMapped(
    'rust batch insert supports text values in a map',
    $rustBatchSuite . 'batch-insert-with-text',
    'batch_insert_with_text',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'data', ['greeting' => 'hello world']);

        sameArray($doc->toArray(), ['data' => ['greeting' => 'hello world']], 'batch insert should preserve text-like string values inside maps');
    }
);

$rustMapped(
    'rust batch insert supports text values in a list',
    $rustBatchSuite . 'batch-insert-text-in-list',
    'batch_insert_text_in_list',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'texts', ['one', 'two']);

        sameArray($doc->toArray()['texts'], ['one', 'two'], 'batch insert should preserve text-like list values');
    }
);

$rustMapped(
    'rust batch insert supports deeply nested maps',
    $rustBatchSuite . 'batch-insert-deeply-nested',
    'batch_insert_deeply_nested',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'deep', [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => 'deep_value',
                    ],
                ],
            ],
        ]);

        same($doc->toArray()['deep']['level1']['level2']['level3']['level4'], 'deep_value', 'deep nested batch insert should preserve the leaf value');
    }
);

$rustMapped(
    'rust batch insert supports mixed map and list nesting',
    $rustBatchSuite . 'batch-insert-mixed-nesting',
    'batch_insert_mixed_nesting',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'data', [
            'users' => [
                ['name' => 'alice', 'scores' => [10, 20, 30]],
                ['name' => 'bob', 'scores' => [40, 50]],
            ],
            'count' => 2,
        ]);

        $data = $doc->toArray()['data'];
        same($data['count'], 2, 'mixed nesting batch insert should preserve scalar map entries');
        same($data['users'][0]['name'], 'alice', 'mixed nesting batch insert should preserve first nested map');
        sameArray($data['users'][0]['scores'], [10, 20, 30], 'mixed nesting batch insert should preserve first nested list');
        same($data['users'][1]['name'], 'bob', 'mixed nesting batch insert should preserve second nested map');
        sameArray($data['users'][1]['scores'], [40, 50], 'mixed nesting batch insert should preserve second nested list');
    }
);

$rustMapped(
    'rust batch insert survives save and load',
    $rustBatchSuite . 'batch-insert-survives-save-load',
    'batch_insert_survives_save_load',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'data', [
            'name' => 'test',
            'items' => [1, 2, 3],
            'nested' => ['deep' => true],
        ]);
        $loaded = $port->load($port->save($doc), 'bbbbbb');

        sameArray($loaded->toArray()['data'], ['name' => 'test', 'items' => [1, 2, 3], 'nested' => ['deep' => true]], 'batch insert values should survive native save/load');
    }
);

$rustMapped(
    'rust batch insert merges independent root objects',
    $rustBatchSuite . 'batch-insert-merges-correctly',
    'batch_insert_merges_correctly',
    function () use ($port): void {
        $base = $port->init('aaaaaa');
        $doc1 = $port->set($base, 'obj1', ['from' => 'doc1']);
        $doc2 = $port->set($port->clone($base, 'bbbbbb'), 'obj2', ['from' => 'doc2']);
        $merged = $port->mergeDocuments($doc1, $doc2);

        same($merged->toArray()['obj1']['from'], 'doc1', 'merge should preserve the first independent batch object');
        same($merged->toArray()['obj2']['from'], 'doc2', 'merge should preserve the second independent batch object');
    }
);

$rustMapped(
    'rust multiple batch inserts preserve all root objects',
    $rustBatchSuite . 'multiple-batch-inserts',
    'multiple_batch_inserts',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'first', ['a' => 1]);
        $doc = $port->set($doc, 'second', ['b' => 2]);
        $doc = $port->set($doc, 'third', ['c' => 3]);

        sameArray($doc->toArray(), ['first' => ['a' => 1], 'second' => ['b' => 2], 'third' => ['c' => 3]], 'multiple batch inserts should preserve all root objects');
    }
);

$rustMapped(
    'rust batch insert into an existing map',
    $rustBatchSuite . 'batch-insert-into-existing-map',
    'batch_insert_into_existing_map',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'parent', ['existing' => 'value']);
        $doc = $port->setNested($doc, ['parent', 'child'], ['x' => 1, 'y' => 2]);

        sameArray($doc->toArray()['parent'], ['existing' => 'value', 'child' => ['x' => 1, 'y' => 2]], 'batch insert into an existing map should preserve existing and child entries');
    }
);

$rustMapped(
    'rust batch insert into an existing list',
    $rustBatchSuite . 'batch-insert-into-existing-list',
    'batch_insert_into_existing_list',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['existing']);
        $doc = $port->insertListElements($doc, 'list', 1, [['appended' => true]]);

        sameArray($doc->toArray()['list'], ['existing', ['appended' => true]], 'batch insert into an existing list should append the object');
    }
);

$rustMapped(
    'rust batch insert hydrate output materializes matching data',
    $rustBatchSuite . 'batch-insert-matches-hydrate-output',
    'batch_insert_matches_hydrate_output',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'data', [
            'name' => 'test',
            'count' => 42,
            'tags' => ['alpha', 'beta'],
        ]);

        sameArray($doc->toArray()['data'], ['name' => 'test', 'count' => 42, 'tags' => ['alpha', 'beta']], 'hydrated batch insert output should match the input shape');
    }
);

$rustMapped(
    'rust batch insert works inside a committed transaction',
    $rustBatchSuite . 'batch-insert-with-transaction',
    'batch_insert_with_transaction',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $tx = $port->transaction($doc);

        $tx->batchCreateObject('data', ['key' => 'from_tx']);
        sameArray($tx->document()->toArray()['data'], ['key' => 'from_tx'], 'transaction draft should expose batch-created object data');
        sameArray($doc->toArray(), [], 'transaction draft changes should not mutate the base document before commit');

        $committed = $tx->commit();
        sameArray($committed->toArray()['data'], ['key' => 'from_tx'], 'committed transaction should publish the batch-created object');
    }
);

$rustMapped(
    'rust batch insert transaction rollback discards the draft',
    $rustBatchSuite . 'batch-insert-transaction-rollback',
    'batch_insert_transaction_rollback',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $tx = $port->transaction($doc);

        $tx->batchCreateObject('data', ['key' => 'should_be_gone']);
        $rolledBack = $tx->rollback();

        sameArray($rolledBack->toArray(), [], 'rolled-back transaction should return the original document');
        sameArray($doc->toArray(), [], 'rolled-back transaction should not mutate the base document');
    }
);

$rustMapped(
    'rust batch insert supports various scalar types',
    $rustBatchSuite . 'batch-insert-various-scalar-types',
    'batch_insert_various_scalar_types',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'scalars', [
            'str_val' => 'hello',
            'int_val' => 42,
            'uint_val' => 100,
            'float_val' => 5.14,
            'bool_true' => true,
            'bool_false' => false,
            'null_val' => null,
        ]);

        sameArray(
            $doc->toArray()['scalars'],
            ['str_val' => 'hello', 'int_val' => 42, 'uint_val' => 100, 'float_val' => 5.14, 'bool_true' => true, 'bool_false' => false, 'null_val' => null],
            'batch insert should preserve scalar values'
        );
    }
);

$rustMapped(
    'rust batch insert is equivalent to individual nested operations',
    $rustBatchSuite . 'batch-insert-equivalent-to-individual-ops',
    'batch_insert_equivalent_to_individual_ops',
    function () use ($port): void {
        $batch = $port->set($port->init('aaaaaa'), 'data', ['name' => 'test', 'count' => 5, 'items' => ['a', 'b', 'c']]);
        $individual = $port->set($port->init('aaaaaa'), 'data', []);
        $individual = $port->setNested($individual, ['data', 'name'], 'test');
        $individual = $port->setNested($individual, ['data', 'count'], 5);
        $individual = $port->setNested($individual, ['data', 'items'], ['a', 'b', 'c']);

        sameArray($batch->toArray(), $individual->toArray(), 'batch insert should materialize the same shape as individual nested operations');
    }
);

$rustMapped(
    'rust batch put overwrites a list element with nested structure',
    $rustBatchSuite . 'batch-put-overwrite-with-nested-structure',
    'batch_put_overwrite_with_nested_structure',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'items', ['placeholder', 'keep']);
        $doc = $port->setListElement($doc, 'items', 0, [
            'name' => 'complex',
            'children' => [
                ['id' => 1],
                ['id' => 2],
            ],
        ]);

        sameArray(
            $doc->toArray()['items'],
            [['name' => 'complex', 'children' => [['id' => 1], ['id' => 2]]], 'keep'],
            'batch put should overwrite one list element with a nested object and preserve the rest'
        );
    }
);

$rustMapped(
    'rust splice inserts scalar list values',
    $rustBatchSuite . 'splice-insert-scalars',
    'splice_insert_scalars',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['a', 'd']);
        $doc = $port->spliceList($doc, 'list', 1, 0, ['b', 'c']);

        sameArray($doc->toArray()['list'], ['a', 'b', 'c', 'd'], 'splice should insert scalar values in order');
    }
);

$rustMapped(
    'rust splice inserts object list values',
    $rustBatchSuite . 'splice-insert-objects',
    'splice_insert_objects',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->spliceList($doc, 'list', 0, 0, [['name' => 'alice'], ['name' => 'bob']]);

        sameArray($doc->toArray()['list'], [['name' => 'alice'], ['name' => 'bob']], 'splice should insert object values in order');
    }
);

$rustMapped(
    'rust splice inserts mixed list values',
    $rustBatchSuite . 'splice-insert-mixed',
    'splice_insert_mixed',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->spliceList($doc, 'list', 0, 0, ['hello', ['nested' => true], [1, 2]]);

        sameArray($doc->toArray()['list'], ['hello', ['nested' => true], [1, 2]], 'splice should insert mixed scalar map and list values');
    }
);

$rustMapped(
    'rust splice deletes and inserts list values',
    $rustBatchSuite . 'splice-delete-and-insert',
    'splice_delete_and_insert',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['a', 'b', 'c']);
        $doc = $port->spliceList($doc, 'list', 1, 1, ['x', 'y']);

        sameArray($doc->toArray()['list'], ['a', 'x', 'y', 'c'], 'splice should delete and insert values in one operation');
    }
);

$rustMapped(
    'rust splice merges concurrent list insertions',
    $rustBatchSuite . 'splice-merges-correctly',
    'splice_merges_correctly',
    function () use ($port): void {
        $doc1 = $port->set($port->init('aaaaaa'), 'list', ['shared']);
        $doc2 = $port->mergeDocuments($port->init('bbbbbb'), $doc1);

        $doc1 = $port->spliceList($doc1, 'list', 1, 0, [['from' => 'doc1']]);
        $doc2 = $port->spliceList($doc2, 'list', 1, 0, [['from' => 'doc2']]);
        $merged = $port->mergeDocuments($doc1, $doc2);

        same(count($merged->toArray()['list']), 3, 'merged splice list should contain the shared value and both inserts');
        same($merged->toArray()['list'][0], 'shared', 'merged splice list should retain the shared first element');
        same($port->getConflicts($merged, 'list'), null, 'concurrent splice insertions should merge without a root conflict');
    }
);

$rustMapped(
    'rust batch insert creates an empty map object',
    $rustBatchSuite . 'batch-insert-empty-map',
    'batch_insert_empty_map',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'empty', []);

        sameArray($doc->toArray(), ['empty' => []], 'batch insert should preserve an empty map container');
    }
);

$rustMapped(
    'rust batch insert creates an empty list object',
    $rustBatchSuite . 'batch-insert-empty-list',
    'batch_insert_empty_list',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'empty', []);

        sameArray($doc->toArray()['empty'], [], 'batch insert should preserve an empty list-like container');
    }
);

$rustMapped(
    'rust batch insert creates an empty text object',
    $rustBatchSuite . 'batch-insert-empty-text',
    'batch_insert_empty_text',
    function () use ($port): void {
        $sequence = 0;
        $doc = $port->set($port->init('aaaaaa'), 'empty', TextValue::fromString('', 'aaaaaa', $sequence));

        same($doc->text('empty')->toString(), '', 'batch insert should preserve an empty text object');
        same($doc->toArray()['empty'], '', 'empty text should materialize as an empty string');
    }
);

$rustMapped(
    'rust batch insert supports a list of lists',
    $rustBatchSuite . 'batch-insert-list-of-lists',
    'batch_insert_list_of_lists',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'matrix', [[1, 2], [3, 4]]);

        sameArray($doc->toArray()['matrix'], [[1, 2], [3, 4]], 'batch insert should preserve nested list containers');
    }
);

$rustMapped(
    'rust batch init map creates flat root keys',
    $rustBatchSuite . 'batch-init-map-flat',
    'batch_init_map_flat',
    function () use ($port): void {
        $doc = $port->from(['name' => 'test', 'count' => 42], 'aaaaaa');

        sameArray($doc->toArray(), ['name' => 'test', 'count' => 42], 'batch init map should materialize flat root keys');
    }
);

$rustMapped(
    'rust batch init map creates nested root objects',
    $rustBatchSuite . 'batch-init-map-nested',
    'batch_init_map_nested',
    function () use ($port): void {
        $doc = $port->from([
            'users' => [
                ['name' => 'alice'],
                ['name' => 'bob'],
            ],
            'meta' => ['version' => 1],
        ], 'aaaaaa');

        same($doc->toArray()['users'][0]['name'], 'alice', 'batch init map should preserve first nested list map');
        same($doc->toArray()['users'][1]['name'], 'bob', 'batch init map should preserve second nested list map');
        same($doc->toArray()['meta']['version'], 1, 'batch init map should preserve nested map scalar');
    }
);

$rustMapped(
    'rust batch init map supports text values',
    $rustBatchSuite . 'batch-init-map-with-text',
    'batch_init_map_with_text',
    function () use ($port): void {
        $sequence = 0;
        $doc = $port->from(['greeting' => TextValue::fromString('hello world', 'aaaaaa', $sequence)], 'aaaaaa');

        same($doc->text('greeting')->toString(), 'hello world', 'batch init map should preserve a text object');
        same($doc->toArray()['greeting'], 'hello world', 'batch init text should materialize as a string');
    }
);

$rustMapped(
    'rust batch init map survives save and load',
    $rustBatchSuite . 'batch-init-map-survives-save-load',
    'batch_init_map_survives_save_load',
    function () use ($port): void {
        $doc = $port->from(['name' => 'test', 'items' => [1, 2, 3]], 'aaaaaa');
        $loaded = $port->load($port->save($doc), 'bbbbbb');

        sameArray($loaded->toArray(), ['name' => 'test', 'items' => [1, 2, 3]], 'batch init map should survive native save/load');
    }
);

$rustMapped(
    'rust batch init map is equivalent to individual root operations',
    $rustBatchSuite . 'batch-init-map-equivalent-to-individual-ops',
    'batch_init_map_equivalent_to_individual_ops',
    function () use ($port): void {
        $batch = $port->from(['name' => 'test', 'count' => 5, 'items' => ['a', 'b', 'c']], 'aaaaaa');
        $individual = $port->set($port->init('aaaaaa'), 'name', 'test');
        $individual = $port->set($individual, 'count', 5);
        $individual = $port->set($individual, 'items', ['a', 'b', 'c']);

        sameArray($batch->toArray(), $individual->toArray(), 'batch init map should materialize like individual root operations');
    }
);

$rustMapped(
    'rust batch insert generates root container patches',
    $rustBatchSuite . 'batch-insert-generates-patches',
    'batch_insert_generates_patches',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $before = $port->getHeads($doc);
        $doc = $port->set($doc, 'data', [
            'name' => 'test',
            'items' => [1, 2],
        ]);
        $patches = $port->diff($doc, $before, $port->getHeads($doc));

        truthy($patches !== [], 'batch insert should generate native diff patches');
        truthy(
            count(array_filter(
                $patches,
                static fn (array $patch): bool => ($patch['action'] ?? null) === 'put'
                    && ($patch['path'] ?? null) === ['data']
                    && ($patch['value'] ?? null) === []
            )) === 1,
            'batch insert should include a root map container patch for data'
        );
    }
);

$rustMapped(
    'rust batch init map generates root patches',
    $rustBatchSuite . 'batch-init-map-generates-patches',
    'batch_init_map_generates_patches',
    function () use ($port): void {
        $doc = $port->from([
            'name' => 'test',
            'items' => [1, 2],
        ], 'aaaaaa');
        $patches = $port->diff($doc, [], $port->getHeads($doc));

        truthy($patches !== [], 'batch init map should generate native diff patches');
        truthy(
            count(array_filter(
                $patches,
                static fn (array $patch): bool => ($patch['action'] ?? null) === 'put'
                    && ($patch['path'] ?? null) === ['name']
                    && ($patch['value'] ?? null) === 'test'
            )) === 1,
            'batch init map should include a root put patch for name'
        );
    }
);

$rustMapped(
    'rust batch insert text generates splice patches',
    $rustBatchSuite . 'batch-insert-text-generates-splice-patch',
    'batch_insert_text_generates_splice_patch',
    function () use ($port): void {
        $doc = $port->init('aaaaaa');
        $before = $port->getHeads($doc);
        $doc = $port->set($doc, 'data', ['greeting' => 'hi']);
        $patches = $port->diff($doc, $before, $port->getHeads($doc));

        truthy(
            count(array_filter(
                $patches,
                static fn (array $patch): bool => ($patch['action'] ?? null) === 'splice'
                    && ($patch['path'] ?? null) === ['data', 'greeting', 0]
                    && ($patch['value'] ?? null) === 'hi'
            )) === 1,
            'batch inserting nested text should report a native text splice patch'
        );
    }
);

$rustMapped(
    'rust splice deletes list values without insertion',
    $rustBatchSuite . 'splice-delete-only',
    'splice_delete_only',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['a', 'b', 'c']);
        $doc = $port->spliceList($doc, 'list', 1, 1, []);

        sameArray($doc->toArray()['list'], ['a', 'c'], 'splice should support delete-only list operations');
    }
);

$rustMapped(
    'rust splice inserts text values into a list',
    $rustBatchSuite . 'splice-with-text',
    'splice_with_text',
    function () use ($port): void {
        $sequence = 0;
        $hello = TextValue::fromString('hello', 'aaaaaa', $sequence);
        $world = TextValue::fromString('world', 'aaaaaa', $sequence);
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->spliceList($doc, 'list', 0, 0, [$hello, $world]);

        sameArray($doc->toArray()['list'], ['hello', 'world'], 'splice should preserve text values in list materialization');
    }
);

$rustMapped(
    'rust splice inserts deeply nested list values',
    $rustBatchSuite . 'splice-deeply-nested',
    'splice_deeply_nested',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->spliceList($doc, 'list', 0, 0, [[
            'users' => [
                [
                    'name' => 'alice',
                    'scores' => [10, 20],
                ],
            ],
        ]]);

        same($doc->toArray()['list'][0]['users'][0]['name'], 'alice', 'splice should preserve deeply nested map values');
        sameArray($doc->toArray()['list'][0]['users'][0]['scores'], [10, 20], 'splice should preserve deeply nested list values');
    }
);

$rustMapped(
    'rust splice survives save and load',
    $rustBatchSuite . 'splice-survives-save-load',
    'splice_survives_save_load',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', []);
        $doc = $port->spliceList($doc, 'list', 0, 0, [['key' => 'val'], 42]);
        $loaded = $port->load($port->save($doc), 'bbbbbb');

        sameArray($loaded->toArray()['list'], [['key' => 'val'], 42], 'splice should survive native save/load');
    }
);

$rustMapped(
    'rust string migration converts map strings to text',
    $rustConvertStringSuite . 'test-strings-in-maps-are-converted-to-text',
    'test_strings_in_maps_are_converted_to_text',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'somestring', 'hello');
        $loaded = $port->loadMigratingStringsToText($port->save($doc), 'aaaaaa');
        $payload = json_decode($port->save($loaded), true, 512, JSON_THROW_ON_ERROR);

        same($loaded->toArray()['somestring'], 'hello', 'migrated map text should materialize as the original string');
        same($payload['root']['somestring']['type'] ?? null, 'text', 'migrated map string should be stored as a text object');
    },
    'rust/automerge/tests/convert_string_to_text.rs'
);

$rustMapped(
    'rust string migration converts list strings to text',
    $rustConvertStringSuite . 'test-strings-in-lists-are-converted-to-text',
    'test_strings_in_lists_are_converted_to_text',
    function () use ($port): void {
        $doc = $port->set($port->init('aaaaaa'), 'list', ['hello']);
        $loaded = $port->loadMigratingStringsToText($port->save($doc), 'aaaaaa');
        $payload = json_decode($port->save($loaded), true, 512, JSON_THROW_ON_ERROR);

        same($loaded->toArray()['list'][0], 'hello', 'migrated list text should materialize as the original string');
        same($payload['root']['list']['value'][0]['type'] ?? null, 'text', 'migrated list string should be stored as a text object');
    },
    'rust/automerge/tests/convert_string_to_text.rs'
);

$rustMapped(
    'rust string migration does not grow an empty document',
    $rustConvertStringSuite . 'test-does-not-add-size-when-strings-are-not-converted',
    'test_does_not_add_size_when_strings_are_not_converted',
    function () use ($port): void {
        $emptyDocument = $port->init('aaaaaa');
        $saved = $port->save($emptyDocument);
        $loaded = $port->loadMigratingStringsToText($saved, 'aaaaaa');

        same(strlen($port->save($loaded)), strlen($saved), 'loading an empty document with string migration should not change save size');
    },
    'rust/automerge/tests/convert_string_to_text.rs'
);

$wordpress(
    'Different top-level paragraph blocks edited by two users',
    function () use ($port): void {
        $base = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' . "\n\n"
            . '<!-- wp:paragraph --><p>World</p><!-- /wp:paragraph -->';
        $alice = '<!-- wp:paragraph --><p>Hello Alice</p><!-- /wp:paragraph -->' . "\n\n"
            . '<!-- wp:paragraph --><p>World</p><!-- /wp:paragraph -->';
        $bob = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' . "\n\n"
            . '<!-- wp:paragraph --><p>World Bob</p><!-- /wp:paragraph -->';

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $aliceDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $alice]);
        $bobDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'bbbbbb', 'postContent' => $bob]);

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($aliceDocument),
            $port->encodeUpdate($bobDocument)
        );

        truthy($merged['ok'] === true, 'different paragraph edits should merge without conflict');
        same(
            $merged['postContent'],
            '<!-- wp:paragraph --><p>Hello Alice</p><!-- /wp:paragraph -->' . "\n\n"
                . '<!-- wp:paragraph --><p>World Bob</p><!-- /wp:paragraph -->',
            'merged post content should include both paragraph edits'
        );
    }
);

$wordpress(
    'Same paragraph block concurrent text insertion',
    function () use ($port): void {
        $base = '<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->';
        $alice = '<!-- wp:paragraph --><p>Hello brave world</p><!-- /wp:paragraph -->';
        $bob = '<!-- wp:paragraph --><p>Hello small world</p><!-- /wp:paragraph -->';

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $aliceDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $alice]);
        $bobDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'bbbbbb', 'postContent' => $bob]);

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($aliceDocument),
            $port->encodeUpdate($bobDocument)
        );

        truthy($merged['ok'] === true, 'same paragraph insertions should merge without conflict');
        same(
            $merged['postContent'],
            '<!-- wp:paragraph --><p>Hello brave small world</p><!-- /wp:paragraph -->',
            'merged post content should include both same-paragraph insertions in deterministic order'
        );
    }
);

$wordpress(
    'One-sided edge insertion at start and end of a post',
    function () use ($port): void {
        $base = '<!-- wp:paragraph --><p>Middle</p><!-- /wp:paragraph -->';
        $startInserted = '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->' . "\n\n" . $base;
        $endInserted = $base . "\n\n" . '<!-- wp:paragraph --><p>Outro</p><!-- /wp:paragraph -->';

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $startDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $startInserted]);
        $unchangedForStart = $port->applyLocalEdit($baseDocument, ['actorId' => 'bbbbbb', 'postContent' => $base]);
        $startMerged = $port->merge(
            $base,
            $port->encodeUpdate($startDocument),
            $port->encodeUpdate($unchangedForStart)
        );

        truthy($startMerged['ok'] === true, 'one-sided start insertion should merge without conflict');
        same($startMerged['postContent'], $startInserted, 'merged post content should preserve the inserted start block');

        $endDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $endInserted]);
        $unchangedForEnd = $port->applyLocalEdit($baseDocument, ['actorId' => 'bbbbbb', 'postContent' => $base]);
        $endMerged = $port->merge(
            $base,
            $port->encodeUpdate($endDocument),
            $port->encodeUpdate($unchangedForEnd)
        );

        truthy($endMerged['ok'] === true, 'one-sided end insertion should merge without conflict');
        same($endMerged['postContent'], $endInserted, 'merged post content should preserve the inserted end block');
    }
);

$wordpress(
    'One-sided deletion of an unchanged block',
    function () use ($port): void {
        $first = '<!-- wp:paragraph --><p>Keep first</p><!-- /wp:paragraph -->';
        $middle = '<!-- wp:paragraph --><p>Delete middle</p><!-- /wp:paragraph -->';
        $last = '<!-- wp:paragraph --><p>Keep last</p><!-- /wp:paragraph -->';
        $base = $first . "\n\n" . $middle . "\n\n" . $last;
        $deleted = $first . "\n\n" . $last;

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $deleteDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $deleted]);
        $unchangedDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'bbbbbb', 'postContent' => $base]);

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($deleteDocument),
            $port->encodeUpdate($unchangedDocument)
        );

        truthy($merged['ok'] === true, 'one-sided block deletion should merge without conflict');
        same($merged['postContent'], $deleted, 'merged post content should preserve the deleted middle block');
    }
);

$wordpress(
    'Server wp_update_post edit merges with editor edit on another block',
    function () use ($port): void {
        $first = '<!-- wp:paragraph --><p>Editor block</p><!-- /wp:paragraph -->';
        $second = '<!-- wp:paragraph --><p>Server block</p><!-- /wp:paragraph -->';
        $base = $first . "\n\n" . $second;
        $editor = '<!-- wp:paragraph --><p>Editor block revised in Gutenberg</p><!-- /wp:paragraph -->'
            . "\n\n" . $second;
        $server = $first . "\n\n"
            . '<!-- wp:paragraph --><p>Server block revised by wp_update_post</p><!-- /wp:paragraph -->';

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $editorDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $editor]);
        $serverDocument = $port->applyServerPostUpdate($baseDocument, $server, 'cccccc');

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($editorDocument),
            $port->encodeUpdate($serverDocument)
        );

        truthy($merged['ok'] === true, 'server and editor edits in separate blocks should merge without conflict');
        same(
            $merged['postContent'],
            '<!-- wp:paragraph --><p>Editor block revised in Gutenberg</p><!-- /wp:paragraph -->'
                . "\n\n"
                . '<!-- wp:paragraph --><p>Server block revised by wp_update_post</p><!-- /wp:paragraph -->',
            'merged post content should include both editor and server block updates'
        );
    }
);

$wordpress(
    'Server wp_update_post deletion merges with editor edit on another block',
    function () use ($port): void {
        $first = '<!-- wp:paragraph --><p>Editor block</p><!-- /wp:paragraph -->';
        $second = '<!-- wp:paragraph --><p>Server block to delete</p><!-- /wp:paragraph -->';
        $third = '<!-- wp:paragraph --><p>Shared tail</p><!-- /wp:paragraph -->';
        $base = $first . "\n\n" . $second . "\n\n" . $third;
        $editor = '<!-- wp:paragraph --><p>Editor block revised in Gutenberg</p><!-- /wp:paragraph -->'
            . "\n\n" . $second . "\n\n" . $third;
        $server = $first . "\n\n" . $third;

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $editorDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $editor]);
        $serverDocument = $port->applyServerPostUpdate($baseDocument, $server, 'cccccc');

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($editorDocument),
            $port->encodeUpdate($serverDocument)
        );

        truthy($merged['ok'] === true, 'server deletion and editor edit in separate blocks should merge without conflict');
        same(
            $merged['postContent'],
            '<!-- wp:paragraph --><p>Editor block revised in Gutenberg</p><!-- /wp:paragraph -->'
                . "\n\n" . $third,
            'merged post content should include the editor block update and server-side deletion'
        );
    }
);

$wordpress(
    'Server wp_update_post insertion merges with editor edit on another block',
    function () use ($port): void {
        $first = '<!-- wp:paragraph --><p>Existing intro</p><!-- /wp:paragraph -->';
        $second = '<!-- wp:paragraph --><p>Editor block</p><!-- /wp:paragraph -->';
        $inserted = '<!-- wp:paragraph --><p>Server inserted block</p><!-- /wp:paragraph -->';
        $base = $first . "\n\n" . $second;
        $editor = $first . "\n\n"
            . '<!-- wp:paragraph --><p>Editor block revised in Gutenberg</p><!-- /wp:paragraph -->';
        $server = $inserted . "\n\n" . $base;

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $editorDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $editor]);
        $serverDocument = $port->applyServerPostUpdate($baseDocument, $server, 'cccccc');

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($editorDocument),
            $port->encodeUpdate($serverDocument)
        );

        truthy($merged['ok'] === true, 'server insertion and editor edit in separate blocks should merge without conflict');
        same(
            $merged['postContent'],
            $inserted . "\n\n" . $first . "\n\n"
                . '<!-- wp:paragraph --><p>Editor block revised in Gutenberg</p><!-- /wp:paragraph -->',
            'merged post content should include the server insertion and editor block update'
        );
    }
);

$wordpress(
    'Identical editor and wp_update_post replacements merge without conflict',
    function () use ($port): void {
        $base = '<!-- wp:paragraph --><p>Shared block</p><!-- /wp:paragraph -->';
        $updated = '<!-- wp:paragraph --><p>Shared block revised consistently</p><!-- /wp:paragraph -->';

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $editorDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $updated]);
        $serverDocument = $port->applyServerPostUpdate($baseDocument, $updated, 'cccccc');

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($editorDocument),
            $port->encodeUpdate($serverDocument)
        );

        truthy($merged['ok'] === true, 'identical overlapping editor and server replacements should merge without conflict');
        same($merged['postContent'], $updated, 'merged post content should keep the shared replacement exactly once');
    }
);

$wordpress(
    'Server wp_update_post no-op preserves a Gutenberg editor edit',
    function () use ($port): void {
        $base = '<!-- wp:paragraph --><p>Original editor block</p><!-- /wp:paragraph -->';
        $editor = '<!-- wp:paragraph --><p>Original editor block revised in Gutenberg</p><!-- /wp:paragraph -->';

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $editorDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $editor]);
        $serverDocument = $port->applyServerPostUpdate($baseDocument, $base, 'cccccc');

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($editorDocument),
            $port->encodeUpdate($serverDocument)
        );

        truthy($merged['ok'] === true, 'server no-op should not conflict with a Gutenberg editor edit');
        same($merged['postContent'], $editor, 'merged post content should preserve the editor edit when the server update is a no-op');
    }
);

$wordpress(
    'Overlapping editor and wp_update_post edits report a merge conflict',
    function () use ($port): void {
        $base = '<!-- wp:paragraph --><p>Shared block</p><!-- /wp:paragraph -->';
        $editor = '<!-- wp:paragraph --><p>Gutenberg block</p><!-- /wp:paragraph -->';
        $server = '<!-- wp:paragraph --><p>Server block</p><!-- /wp:paragraph -->';

        $baseDocument = $port->createDocument($base, ['actorId' => 'aaaaaa']);
        $editorDocument = $port->applyLocalEdit($baseDocument, ['actorId' => 'aaaaaa', 'postContent' => $editor]);
        $serverDocument = $port->applyServerPostUpdate($baseDocument, $server, 'cccccc');

        $merged = $port->merge(
            $base,
            $port->encodeUpdate($editorDocument),
            $port->encodeUpdate($serverDocument)
        );

        truthy($merged['ok'] === false, 'overlapping editor and server edits should not be silently merged');
        same($merged['conflict']['reason'] ?? null, 'overlapping-post-content-edits', 'overlapping post-content edits should report the explicit conflict reason');
    }
);

$activeTests = array_values(array_filter(
    $tests,
    static fn (array $test): bool => ($test['mappedFrom']['upstreamStatus'] ?? 'active') === 'active'
));
$optionalRegisteredTests = array_values(array_filter(
    $tests,
    static fn (array $test): bool => ($test['mappedFrom']['upstreamStatus'] ?? 'active') !== 'active'
));
$optionalPendingTests = array_values(array_filter(
    $tests,
    static fn (array $test): bool => ($test['mappedFrom']['upstreamStatus'] ?? 'active') === 'pending'
));
$optionalIgnoredTests = array_values(array_filter(
    $tests,
    static fn (array $test): bool => ($test['mappedFrom']['upstreamStatus'] ?? 'active') === 'ignored'
));
$passing = count(array_filter($activeTests, static fn (array $test): bool => $test['passed']));
$total = count($activeTests);
$registeredPassing = count(array_filter($tests, static fn (array $test): bool => $test['passed']));
$registeredTotal = count($tests);
$optionalPassing = count(array_filter($optionalRegisteredTests, static fn (array $test): bool => $test['passed']));
$optionalPendingPassing = count(array_filter($optionalPendingTests, static fn (array $test): bool => $test['passed']));
$optionalIgnoredPassing = count(array_filter($optionalIgnoredTests, static fn (array $test): bool => $test['passed']));
$rustMappedTests = array_filter(
    $activeTests,
    static fn (array $test): bool => is_string($test['mappedFrom']['id'] ?? null)
        && str_starts_with($test['mappedFrom']['id'], 'rust:')
);
$rustMappedTotal = count($rustMappedTests);
$rustPassing = count(array_filter($rustMappedTests, static fn (array $test): bool => $test['passed']));
$javascriptMappedTotal = $total - $rustMappedTotal;
$javascriptPassing = $passing - $rustPassing;
$wordpressPassing = count(array_filter($wordpressScenarios, static fn (array $test): bool => $test['passed']));
$wordpressTotal = 10;
$knownJavascriptTests = 312;
$registeredJavascriptTests = 313;
$knownRustTests = 368;
$registeredRustTests = 375;
$knownUpstreamTests = $knownJavascriptTests + $knownRustTests;
$registeredUpstreamTests = $registeredJavascriptTests + $registeredRustTests;
$activeParityReached = $passing === $knownUpstreamTests && $total === $knownUpstreamTests;
$registeredParityReached = $registeredPassing === $registeredTotal && $registeredTotal === $registeredUpstreamTests;
$status = [
    'library' => 'automerge',
    'phase' => 'runtime-active-upstream-parity',
    'passPercent' => round(($passing / $knownUpstreamTests) * 100, 2),
    'mappedPassPercent' => $total === 0 ? 0 : round(($passing / $total) * 100, 2),
    'passingTests' => $passing,
    'failingTests' => $total - $passing,
    'skippedTests' => 0,
    'mappedUpstreamTests' => $total,
    'registeredMappedUpstreamTests' => $registeredTotal,
    'registeredMappedPassingTests' => $registeredPassing,
    'optionalMappedUpstreamTests' => count($optionalRegisteredTests),
    'optionalMappedPassingTests' => $optionalPassing,
    'optionalPendingMappedTests' => count($optionalPendingTests),
    'optionalPendingPassingTests' => $optionalPendingPassing,
    'optionalIgnoredMappedTests' => count($optionalIgnoredTests),
    'optionalIgnoredPassingTests' => $optionalIgnoredPassing,
    'totalKnownUpstreamTests' => $knownUpstreamTests,
    'registeredTotalUpstreamTests' => $registeredUpstreamTests,
    'unmappedKnownUpstreamTests' => $knownUpstreamTests - $total,
    'upstreamSuiteBreakdown' => [
        'benchmarkArtifact' => 'UPSTREAM_BENCHMARK.json',
        'manifestArtifact' => 'UPSTREAM_TEST_MANIFEST.json',
        'countMethod' => 'runtime-runner-counts',
        'javascriptMocha' => [
            'mapped' => $javascriptMappedTotal,
            'passing' => $javascriptPassing,
            'knownActive' => $knownJavascriptTests,
            'registered' => $registeredJavascriptTests,
            'pending' => 1,
            'pendingMapped' => count($optionalPendingTests),
            'pendingPassing' => $optionalPendingPassing,
        ],
        'rustAutomerge' => [
            'mapped' => $rustMappedTotal,
            'passing' => $rustPassing,
            'knownActive' => $knownRustTests,
            'registered' => $registeredRustTests,
            'ignored' => 7,
            'ignoredMapped' => count($optionalIgnoredTests),
            'ignoredPassing' => $optionalIgnoredPassing,
        ],
        'combined' => [
            'mapped' => $total,
            'passing' => $passing,
            'knownActive' => $knownUpstreamTests,
            'registered' => $registeredUpstreamTests,
        ],
    ],
    'wordpressScenariosPassing' => $wordpressPassing,
    'wordpressScenariosTotal' => $wordpressTotal,
    'currentTask' => $activeParityReached
        ? ($registeredParityReached
            ? 'Runtime-active and registered upstream parity reached; no unmapped active, pending, or ignored manifest rows remain.'
            : 'Runtime-active upstream parity reached; map remaining optional registered pending/ignored rows outside the active denominator.')
        : 'Fix failing first-slice Automerge map/text tests before expanding coverage.',
    'denominatorNote' => 'The older static 724 audit has been superseded by successful runtime upstream runs: 312 JavaScript passing plus 1 pending, and 368 Rust passing plus 7 ignored. Active parity uses 680; the registered manifest total is 688.',
    'blocker' => null,
    'upstream' => [
        'url' => 'https://github.com/automerge/automerge',
        'submodulePath' => 'upstream/automerge',
        'referenceCommit' => submoduleHeadCommit(__DIR__ . '/../upstream/automerge'),
        'canonicalJavascriptTestCommand' => 'cd upstream/automerge/javascript && npm test',
        'canonicalRustCoreTestCommand' => 'cd upstream/automerge/rust && cargo test -p automerge',
    ],
    'lastTestCommand' => 'composer test',
    'lastTestAt' => gmdate('c'),
    'lastCommit' => null,
    'tests' => $tests,
    'wordpressScenarios' => $wordpressScenarios,
    'unmappedNextTargets' => [],
];

file_put_contents(__DIR__ . '/../PORTING_STATUS.json', json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($registeredPassing === $registeredTotal && $wordpressPassing === count($wordpressScenarios) ? 0 : 1);
