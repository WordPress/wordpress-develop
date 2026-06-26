<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

use JsonSerializable;

final class Document implements JsonSerializable
{
    private string $actorId;

    private int $sequence;

    /** @var array<string,mixed> */
    private array $root;

    /** @var list<string> */
    private array $heads;

    /** @var list<array<string,mixed>> */
    private array $changes;

    /** @var array<string,array<string,mixed>> */
    private array $conflicts;

    /** @var array<string,list<array{name:string,value:mixed,start:int,end:int,expand?:string}>> */
    private array $marks;

    /** @var list<string>|null */
    private ?array $incrementalHeads;

    private bool $frozen;

    /**
     * @param array<string,mixed> $root
     * @param list<string>       $heads
     * @param list<array<string,mixed>> $changes
     * @param array<string,array<string,mixed>> $conflicts
     * @param array<string,list<array{name:string,value:mixed,start:int,end:int,expand?:string}>> $marks
     */
    public function __construct(
        string $actorId,
        int $sequence = 0,
        array $root = [],
        array $heads = [],
        array $changes = [],
        array $conflicts = [],
        array $marks = [],
        ?array $incrementalHeads = null,
        bool $frozen = false
    ) {
        $this->actorId = $actorId;
        $this->sequence = $sequence;
        $this->root = $this->copyValue($root);
        $this->heads = $this->sortedUniqueHeads($heads);
        $this->changes = $changes;
        $this->conflicts = $this->copyConflicts($conflicts);
        $this->marks = $this->copyMarks($marks);
        $this->incrementalHeads = $incrementalHeads === null ? null : $this->sortedUniqueHeads($incrementalHeads);
        $this->frozen = $frozen;
    }

    public static function init(?string $actorId = null): self
    {
        return new self($actorId ?? self::defaultActorId());
    }

    public static function fromArray(array $root, ?string $actorId = null): self
    {
        $document = self::init($actorId);
        if ($root === []) {
            return $document;
        }

        $deps = $document->heads;
        $document->advanceClock();
        $ops = [];

        foreach ($root as $key => $value) {
            $key = (string) $key;
            $document->assertSupportedValue($value, '/' . $key);
            $storedValue = $document->valueForKey($key, $value);
            $document->root[$key] = $document->copyValue($storedValue);
            $ops[] = [
                'action' => 'set',
                'key' => $key,
                'value' => $document->encodeValue($storedValue),
            ];
        }

        $document->recordChange($deps, $ops);

        return $document;
    }

    /**
     * @param list<array<string,mixed>> $changes
     */
    public static function applyChanges(self $base, array $changes): self
    {
        $document = $base->clone();
        $initialChangeCount = count($document->changes);
        foreach ($changes as $change) {
            $document->applyRecordedChange($change);
        }

        if (count($document->changes) !== $initialChangeCount) {
            $document->rebuildMaterializedStateFromDependencyOrder();
        }

        return $document;
    }

    public static function load(string $payload): self
    {
        return self::loadFromPayload($payload, false);
    }

    public static function loadWithStringMigration(string $payload): self
    {
        return self::loadFromPayload($payload, true);
    }

    private static function loadFromPayload(string $payload, bool $convertStringScalarsToText): self
    {
        $state = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($state) || ($state['format'] ?? null) !== 'wordpress-de/automerge-php-native-v1') {
            throw new \InvalidArgumentException('Unsupported native Automerge PHP save payload.');
        }

        $document = new self(
            is_string($state['actor'] ?? null) ? $state['actor'] : self::defaultActorId(),
            (int) ($state['sequence'] ?? 0),
            [],
            is_array($state['heads'] ?? null) ? array_values($state['heads']) : [],
            is_array($state['changes'] ?? null) ? array_values($state['changes']) : [],
            [],
            is_array($state['marks'] ?? null) ? $state['marks'] : []
        );
        $textSequence = $document->sequence;
        $root = is_array($state['root'] ?? null) ? $state['root'] : [];
        foreach ($root as $key => $value) {
            $document->root[(string) $key] = $document->decodeValue($value, $convertStringScalarsToText, $textSequence);
        }

        $conflicts = is_array($state['conflicts'] ?? null) ? $state['conflicts'] : [];
        foreach ($conflicts as $key => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $operationId => $value) {
                if (is_string($operationId)) {
                    $document->conflicts[(string) $key][$operationId] = $document->decodeValue($value, $convertStringScalarsToText, $textSequence);
                }
            }
        }
        $document->sequence = max($document->sequence, $textSequence);

        return $document;
    }

    public function clone(?string $actorId = null, ?bool $frozen = null): self
    {
        return new self(
            $actorId ?? $this->actorId,
            $this->sequence,
            $this->root,
            $this->heads,
            $this->changes,
            $this->conflicts,
            $this->marks,
            $this->incrementalHeads,
            $frozen ?? $this->frozen
        );
    }

    /**
     * @param list<string> $heads
     */
    public function view(array $heads): self
    {
        $heads = $this->sortedUniqueHeads($heads);
        if ($heads === $this->heads) {
            return $this->clone();
        }

        if ($heads === []) {
            return self::init($this->actorId);
        }

        $changesByHash = [];
        foreach ($this->changes as $change) {
            if (is_string($change['hash'] ?? null)) {
                $changesByHash[$change['hash']] = $change;
            }
        }

        $needed = [];
        $stack = $heads;
        while ($stack !== []) {
            $hash = array_pop($stack);
            if (! is_string($hash) || isset($needed[$hash])) {
                continue;
            }

            if (! isset($changesByHash[$hash])) {
                throw new \InvalidArgumentException('Cannot create a view for unknown heads.');
            }

            $needed[$hash] = true;
            $deps = is_array($changesByHash[$hash]['deps'] ?? null) ? $changesByHash[$hash]['deps'] : [];
            foreach ($deps as $dep) {
                if (is_string($dep)) {
                    $stack[] = $dep;
                }
            }
        }

        $view = self::init($this->actorId);
        foreach ($this->changes as $change) {
            if (is_string($change['hash'] ?? null) && isset($needed[$change['hash']])) {
                $view->applyRecordedChange($change);
            }
        }

        return $view;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * @param list<string|int> $path
     */
    public function isFrozenPath(array $path): bool
    {
        if (! $this->frozen) {
            return false;
        }

        if ($path === []) {
            return true;
        }

        $value = $this->toArray();
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return false;
            }

            $value = $value[$key];
        }

        return is_array($value);
    }

    public function withFrozen(bool $frozen): self
    {
        return $this->clone(null, $frozen);
    }

    public function ensureSequenceAtLeast(int $sequence): void
    {
        $this->assertMutable();
        $this->sequence = max($this->sequence, $sequence);
    }

    /**
     * @return list<string>
     */
    public function heads(): array
    {
        return $this->sortedUniqueHeads($this->heads);
    }

    /**
     * @param list<string> $heads
     */
    public function hasHeads(array $heads): bool
    {
        $known = array_fill_keys($this->heads, true);
        foreach ($this->changes as $change) {
            if (is_string($change['hash'] ?? null)) {
                $known[$change['hash']] = true;
            }
        }

        foreach ($heads as $head) {
            if (! isset($known[$head])) {
                return false;
            }
        }

        return true;
    }

    public function set(string $key, mixed $value, ?string $message = null, ?int $time = null): void
    {
        $this->assertMutable();
        $this->assertSupportedValue($value, '/' . $key);
        $deps = $this->heads;
        $this->advanceClock();

        if ($value instanceof TextValue) {
            $this->root[$key] = $value->copy();
            unset($this->conflicts[$key]);
            $this->recordChange($deps, [['action' => 'set', 'key' => $key, 'value' => $this->encodeValue($value)]], $message, $time);
            return;
        }

        $storedValue = $this->valueForKey($key, $value);
        $this->root[$key] = $this->copyValue($storedValue);
        unset($this->conflicts[$key]);
        $this->recordChange($deps, [['action' => 'set', 'key' => $key, 'value' => $this->encodeValue($storedValue)]], $message, $time);
    }

    /**
     * @param list<string> $heads
     */
    public function setAtHeads(array $heads, string $key, mixed $value): void
    {
        $this->assertMutable();
        $this->assertSupportedValue($value, '/' . $key);
        $deps = $this->sortedUniqueHeads($heads);
        $this->advanceClock();

        $storedValue = $value instanceof TextValue ? $value->copy() : $this->valueForKey($key, $value);
        $this->root[$key] = $this->copyValue($storedValue);
        unset($this->conflicts[$key]);
        $this->recordChange(
            $deps,
            [['action' => 'set', 'key' => $key, 'value' => $this->encodeValue($storedValue)]],
            preserveUnrelatedHeads: true
        );
    }

    /** @param array<string,mixed> $values */
    public function setMany(array $values, ?string $message = null): void
    {
        $this->assertMutable();
        if ($values === []) {
            return;
        }

        foreach ($values as $key => $value) {
            $this->assertSupportedValue($value, '/' . (string) $key);
        }

        $deps = $this->heads;
        $this->advanceClock();
        $ops = [];
        foreach ($values as $key => $value) {
            $key = (string) $key;
            $storedValue = $value instanceof TextValue ? $value->copy() : $this->valueForKey($key, $value);
            $this->root[$key] = $this->copyValue($storedValue);
            unset($this->conflicts[$key]);
            $ops[] = ['action' => 'set', 'key' => $key, 'value' => $this->encodeValue($storedValue)];
        }

        $this->recordChange($deps, $ops, $message);
    }

    /**
     * @param list<string|int> $path
     */
    public function setNested(array $path, mixed $value): void
    {
        $this->assertMutable();
        $path = $this->normalizePath($path);
        $this->assertSupportedValue($value, $this->pathString($path));
        $deps = $this->heads;
        $this->advanceClock();

        $storedValue = $this->copyValue($value);
        $this->assignNestedPath($this->root, $path, $storedValue);
        if (is_string($path[0])) {
            unset($this->conflicts[$path[0]]);
        }
        $this->recordChange($deps, [['action' => 'setNested', 'path' => $path, 'value' => $this->encodeValue($storedValue)]]);
    }

    /**
     * @param list<mixed> $values
     */
    public function insertListValues(string $key, int $index, array $values): void
    {
        $this->assertMutable();
        foreach ($values as $offset => $value) {
            $this->assertSupportedValue($value, '/' . $key . '/' . (string) ($index + (int) $offset));
        }

        $deps = $this->heads;
        $this->advanceClock();
        $list = $this->root[$key] ?? [];
        $list = is_array($list) && array_is_list($list) ? array_values($list) : [];
        $index = max(0, min($index, count($list)));
        $storedValues = array_map(fn (mixed $value): mixed => $this->copyValue($value), array_values($values));
        array_splice($list, $index, 0, $storedValues);
        $this->root[$key] = array_values($list);
        unset($this->conflicts[$key]);
        $this->recordChange($deps, [[
            'action' => 'insertList',
            'key' => $key,
            'index' => $index,
            'values' => array_map(fn (mixed $value): mixed => $this->encodeValue($value), $storedValues),
            'value' => $this->encodeValue($this->root[$key]),
        ]]);
    }

    public function setRootConflictMapValue(string $key, string $nestedKey, mixed $value): void
    {
        $this->assertMutable();
        $this->assertSupportedValue($value, '/' . $key . '/' . $nestedKey);
        if (! isset($this->conflicts[$key])) {
            return;
        }

        $updated = $this->setConflictMapValue($key, $nestedKey, $value);
        if (! $updated) {
            return;
        }

        $deps = $this->heads;
        $this->advanceClock();
        $this->recordChange($deps, [
            [
                'action' => 'setRootConflictMapValue',
                'key' => $key,
                'nestedKey' => $nestedKey,
                'value' => $this->encodeValue($value),
            ],
        ]);
    }

    public function setRootConflictListElementMapValue(string $key, int $index, string $nestedKey, mixed $value): void
    {
        $this->assertMutable();
        $index = max(0, $index);
        $this->assertSupportedValue($value, '/' . $key . '/' . $index . '/' . $nestedKey);
        if (! isset($this->conflicts[$key])) {
            return;
        }

        $updated = $this->setConflictListElementMapValue($key, $index, $nestedKey, $value);
        if (! $updated) {
            return;
        }

        $deps = $this->heads;
        $this->advanceClock();
        $this->recordChange($deps, [[
            'action' => 'setRootConflictListElementMapValue',
            'key' => $key,
            'index' => $index,
            'nestedKey' => $nestedKey,
            'value' => $this->encodeValue($value),
        ]]);
    }

    public function resolveRootConflictListElement(string $key, int $index, mixed $value): void
    {
        $this->assertMutable();
        $index = max(0, $index);
        $this->assertSupportedValue($value, '/' . $key . '/' . $index);
        if (! isset($this->conflicts[$key])) {
            return;
        }

        $updated = $this->resolveConflictListElementValue($key, $index, $value);
        if (! $updated) {
            return;
        }

        $deps = $this->heads;
        $this->advanceClock();
        $this->recordChange($deps, [[
            'action' => 'resolveRootConflictListElement',
            'key' => $key,
            'index' => $index,
            'value' => $this->encodeValue($value),
        ]]);
    }

    public function incrementRootConflictCounters(string $key, int $amount, string $operationId): bool
    {
        $this->assertMutable();
        if (! isset($this->conflicts[$key])) {
            return false;
        }

        $updated = $this->incrementConflictCounters($key, $amount, $operationId);
        if (! $updated) {
            return false;
        }

        $deps = $this->heads;
        $this->advanceClock();
        $this->recordChange($deps, [[
            'action' => 'incrementRootConflictCounters',
            'key' => $key,
            'amount' => $amount,
            'operationId' => $operationId,
        ]]);

        return true;
    }

    public function incrementRootConflictListElementCounters(string $key, int $index, int $amount, string $operationId): bool
    {
        $this->assertMutable();
        $index = max(0, $index);
        if (! isset($this->conflicts[$key])) {
            return false;
        }

        $updated = $this->incrementConflictListElementCounters($key, $index, $amount, $operationId);
        if (! $updated) {
            return false;
        }

        $deps = $this->heads;
        $this->advanceClock();
        $this->recordChange($deps, [[
            'action' => 'incrementRootConflictListElementCounters',
            'key' => $key,
            'index' => $index,
            'amount' => $amount,
            'operationId' => $operationId,
        ]]);

        return true;
    }

    public function get(string $key): mixed
    {
        return $this->root[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->root);
    }

    public function delete(string $key): void
    {
        $this->assertMutable();
        $deps = $this->heads;
        $this->advanceClock();
        unset($this->root[$key]);
        unset($this->conflicts[$key]);
        $this->recordChange($deps, [['action' => 'delete', 'key' => $key]]);
    }

    /**
     * @param list<string|int> $path
     */
    public function deleteNested(array $path): void
    {
        $this->assertMutable();
        $path = $this->normalizePath($path);
        $deps = $this->heads;
        $this->advanceClock();

        $this->deleteNestedPath($this->root, $path);
        if (is_string($path[0])) {
            unset($this->conflicts[$path[0]]);
        }
        $this->recordChange($deps, [['action' => 'deleteNested', 'path' => $path]]);
    }

    public function spliceText(string $key, int $index, int $deleteCount, string $insert = ''): void
    {
        $this->assertMutable();
        $deps = $this->heads;
        $this->advanceClock();
        $value = $this->root[$key] ?? null;
        if (! $value instanceof TextValue) {
            $value = $this->textValueForExistingKey($key, $value);
        }

        $value->splice($index, $deleteCount, $insert, $this->actorId, $this->sequence);
        $this->root[$key] = $value;
        $this->adjustMarksForTextSplice($key, $index, $deleteCount, $insert);
        $this->recordChange(
            $deps,
            [
                [
                    'action' => 'splice',
                    'key' => $key,
                    'index' => $index,
                    'deleteCount' => $deleteCount,
                    'insert' => $insert,
                ],
            ]
        );
    }

    public function putText(string $key, int $index, string $value): void
    {
        $this->assertMutable();
        $deps = $this->heads;
        $this->advanceClock();
        $text = $this->root[$key] ?? null;
        if (! $text instanceof TextValue) {
            $text = $this->textValueForExistingKey($key, $text);
        }

        $text->splice($index, 1, $value, $this->actorId, $this->sequence);
        $this->root[$key] = $text;
        $this->adjustMarksForTextSplice($key, $index, 1, $value);
        $this->recordChange(
            $deps,
            [
                [
                    'action' => 'putText',
                    'key' => $key,
                    'index' => $index,
                    'value' => $value,
                ],
            ]
        );
    }

    public function updateText(string $key, string $newText): void
    {
        $oldText = $this->text($key)->toString();
        if ($oldText === $newText) {
            return;
        }

        [$index, $deleteCount, $insert] = $this->diffText($oldText, $newText);
        $this->spliceText($key, $index, $deleteCount, $insert);
    }

    /**
     * @param list<array{name:string,value:mixed,start:int,end:int,expand?:string}> $marks
     */
    public function replaceTextAndMarks(string $key, string $text, array $marks): void
    {
        $this->assertMutable();
        $deps = $this->heads;
        $this->advanceClock();

        $value = TextValue::fromString($text, $this->actorId, $this->sequence);
        $this->root[$key] = $value;
        unset($this->conflicts[$key]);

        $pathKey = $this->pathKey([$key]);
        if ($marks === []) {
            unset($this->marks[$pathKey]);
        } else {
            $this->marks[$pathKey] = $this->copyMarks([$pathKey => $marks])[$pathKey] ?? [];
        }

        $ops = [[
            'action' => 'set',
            'key' => $key,
            'value' => $this->encodeValue($value),
        ]];
        if ($marks !== []) {
            $ops[] = [
                'action' => 'mark',
                'path' => [$key],
                'marks' => $this->marks[$pathKey],
            ];
        }

        $this->recordChange($deps, $ops);
    }

    /**
     * @param list<string|int> $path
     * @param list<array<string,mixed>> $marks
     */
    public function markText(array $path, array $marks): void
    {
        $this->assertMutable();
        $path = $this->normalizePath($path);
        $normalized = [];
        foreach ($marks as $mark) {
            if (! is_array($mark) || ! is_string($mark['name'] ?? null)) {
                continue;
            }

            $start = max(0, (int) ($mark['start'] ?? 0));
            $end = max($start, (int) ($mark['end'] ?? $start));
            $normalizedMark = [
                'name' => $mark['name'],
                'value' => $mark['value'] ?? true,
                'start' => $start,
                'end' => $end,
            ];
            if (is_string($mark['expand'] ?? null) && in_array($mark['expand'], ['none', 'start', 'end', 'both'], true)) {
                $normalizedMark['expand'] = $mark['expand'];
            }

            $normalized[] = $normalizedMark;
        }

        if ($normalized === []) {
            return;
        }

        $deps = $this->heads;
        $this->advanceClock();
        $pathKey = $this->pathKey($path);
        $this->marks[$pathKey] = array_values(array_merge($this->marks[$pathKey] ?? [], $normalized));
        $this->recordChange($deps, [['action' => 'mark', 'path' => $path, 'marks' => $normalized]]);
    }

    /**
     * @param list<string|int> $path
     */
    public function unmarkText(array $path, string $name, int $start, int $end): void
    {
        $this->assertMutable();
        $path = $this->normalizePath($path);
        $start = max(0, $start);
        $end = max($start, $end);
        $pathKey = $this->pathKey($path);
        $existing = $this->marks[$pathKey] ?? [];
        if ($existing === []) {
            return;
        }

        [$remaining, $changed] = $this->marksAfterUnmark($existing, $name, $start, $end);
        if (! $changed) {
            return;
        }

        $deps = $this->heads;
        $this->advanceClock();
        if ($remaining === []) {
            unset($this->marks[$pathKey]);
        } else {
            $this->marks[$pathKey] = $remaining;
        }

        $this->recordChange($deps, [['action' => 'unmark', 'path' => $path, 'name' => $name, 'start' => $start, 'end' => $end]]);
    }

    /**
     * @param list<string|int> $path
     * @return list<array{name:string,value:mixed,start:int,end:int}>
     */
    public function marksFor(array $path): array
    {
        $pathKey = $this->pathKey($this->normalizePath($path));

        return $this->copyMarks([$pathKey => $this->marks[$pathKey] ?? []], false)[$pathKey] ?? [];
    }

    /**
     * @return list<array{path:list<string|int>,marks:list<array{name:string,value:mixed,start:int,end:int}>}>
     */
    public function allMarks(): array
    {
        $entries = [];
        foreach ($this->marks as $pathKey => $pathMarks) {
            $path = json_decode($pathKey, true);
            if (! is_array($path)) {
                continue;
            }

            $marks = $this->copyMarks([$pathKey => $pathMarks], false)[$pathKey] ?? [];
            if ($marks === []) {
                continue;
            }

            $entries[] = [
                'path' => array_values($path),
                'marks' => $marks,
            ];
        }

        return $entries;
    }

    public function emptyChange(?string $message = null, ?int $time = null): void
    {
        $this->assertMutable();
        $deps = $this->heads;
        $this->advanceClock();
        $this->recordChange($deps, [], $message, $time);
    }

    public function text(string $key): TextValue
    {
        $value = $this->root[$key] ?? null;
        if ($value instanceof TextValue) {
            return $value->copy();
        }

        $sequence = $this->sequence;

        return TextValue::fromString(is_string($value) ? $value : '', $this->actorId, $sequence);
    }

    public function merge(self $other): self
    {
        $merged = $this->clone();
        $commonAncestor = $this->commonAncestorView($other);
        foreach ($other->root as $key => $otherValue) {
            if (isset($merged->root[$key]) && $merged->root[$key] instanceof TextValue && $otherValue instanceof TextValue) {
                $baseHadKey = $commonAncestor !== null && array_key_exists($key, $commonAncestor->root);
                if (! $baseHadKey && ! $other->hasHeads($this->heads) && ! $this->hasHeads($other->heads)) {
                    $merged->recordRootConflict($key, $merged->root[$key], $other, $otherValue);
                    $merged->root[$key] = $merged->rootConflictWinner($merged->conflicts[$key] ?? []);
                    continue;
                }

                $merged->root[$key] = $merged->root[$key]->merge($otherValue);
                continue;
            }

            if (isset($merged->root[$key]) && $merged->root[$key] instanceof Counter && $otherValue instanceof Counter) {
                if ($merged->root[$key]->id() === $otherValue->id()) {
                    $merged->root[$key] = $merged->root[$key]->merge($otherValue);
                    continue;
                }

                $merged->recordRootConflict($key, $merged->root[$key], $other, $otherValue);
                $merged->root[$key] = $merged->rootConflictWinner($merged->conflicts[$key] ?? []);
                continue;
            }

            if (! array_key_exists($key, $merged->root)) {
                $merged->root[$key] = $merged->copyValue($otherValue);
                continue;
            }

            if ($merged->root[$key] === $otherValue) {
                if ($other->hasHeads($this->heads)) {
                    if (isset($other->conflicts[$key]) && is_array($other->conflicts[$key]) && count($other->conflicts[$key]) >= 2) {
                        $merged->conflicts[$key] = $merged->copyValue($other->conflicts[$key]);
                    } else {
                        unset($merged->conflicts[$key]);
                    }

                    continue;
                }

                if ($merged->shouldRecordConcurrentEqualScalarConflict($key, $other, $otherValue, $commonAncestor)) {
                    $merged->recordRootConflict($key, $merged->root[$key], $other, $otherValue);
                    $merged->root[$key] = $merged->rootConflictWinner($merged->conflicts[$key] ?? []);
                }

                continue;
            }

            if ($other->hasHeads($this->heads)) {
                $merged->root[$key] = $merged->copyValue($otherValue);
                unset($merged->conflicts[$key]);
                continue;
            }

            if ($this->hasHeads($other->heads)) {
                unset($merged->conflicts[$key]);
                continue;
            }

            if (is_array($merged->root[$key]) && is_array($otherValue)) {
                $baseValue = $commonAncestor?->root[$key] ?? null;
                if (is_array($baseValue)) {
                    if (array_is_list($baseValue) && array_is_list($merged->root[$key]) && array_is_list($otherValue)) {
                        [$insertionsCleanly, $insertionsValue] = $merged->mergeConcurrentListInsertions(
                            $baseValue,
                            $merged->root[$key],
                            $otherValue,
                            $merged->latestListInsertionForKey($key),
                            $other->latestListInsertionForKey($key)
                        );
                        if ($insertionsCleanly) {
                            $merged->root[$key] = $merged->copyValue($insertionsValue);
                            unset($merged->conflicts[$key]);
                            continue;
                        }
                    }

                    [$mergedCleanly, $mergedValue] = $merged->mergeSharedContainerValue($baseValue, $merged->root[$key], $otherValue);
                    if ($mergedCleanly) {
                        $merged->root[$key] = $merged->copyValue($mergedValue);
                        unset($merged->conflicts[$key]);
                        continue;
                    }
                }
            }

            $merged->recordRootConflict($key, $merged->root[$key], $other, $otherValue);
            $merged->root[$key] = $merged->rootConflictWinner($merged->conflicts[$key] ?? []);
        }

        if ($other->hasHeads($this->heads)) {
            foreach (array_keys($merged->root) as $key) {
                if (! array_key_exists($key, $other->root)) {
                    unset($merged->root[$key], $merged->conflicts[$key]);
                }
            }
        }

        $merged->sequence = max($this->sequence, $other->sequence);
        $merged->heads = array_values(array_unique(array_merge($this->heads, $other->heads)));
        $merged->changes = $this->mergeChangeLists($this->changes, $other->changes);
        if ($other->hasHeads($this->heads)) {
            $merged->marks = $merged->copyMarks($other->marks);
        } elseif (! $this->hasHeads($other->heads)) {
            $merged->marks = $merged->mergeMarkSets($merged->marks, $other->marks);
        }

        return $merged;
    }

    private function commonAncestorView(self $other): ?self
    {
        $leftChanges = [];
        foreach ($this->changes as $change) {
            if (is_string($change['hash'] ?? null)) {
                $leftChanges[$change['hash']] = $change;
            }
        }

        $common = [];
        foreach ($other->changes as $change) {
            if (is_string($change['hash'] ?? null) && isset($leftChanges[$change['hash']])) {
                $common[$change['hash']] = $leftChanges[$change['hash']];
            }
        }

        if ($common === []) {
            return null;
        }

        $commonDeps = [];
        foreach ($common as $change) {
            foreach (is_array($change['deps'] ?? null) ? $change['deps'] : [] as $dep) {
                if (is_string($dep) && isset($common[$dep])) {
                    $commonDeps[$dep] = true;
                }
            }
        }

        $heads = array_values(array_diff(array_keys($common), array_keys($commonDeps)));
        if ($heads === []) {
            return null;
        }

        return $this->view($this->sortedUniqueHeads($heads));
    }

    private function shouldRecordConcurrentEqualScalarConflict(string $key, self $other, mixed $value, ?self $commonAncestor): bool
    {
        if (! is_scalar($value) && $value !== null) {
            return false;
        }

        if ($other->hasHeads($this->heads) || $this->hasHeads($other->heads)) {
            return false;
        }

        $baseHasKey = $commonAncestor !== null && array_key_exists($key, $commonAncestor->root);
        if ($baseHasKey && $commonAncestor->root[$key] === $value) {
            return false;
        }

        return $this->latestOperationIdForKey($key) !== $other->latestOperationIdForKey($key);
    }

    /**
     * @return array{0:bool,1:mixed}
     */
    private function mergeSharedContainerValue(mixed $base, mixed $left, mixed $right): array
    {
        if ($left === $right) {
            return [true, $this->copyValue($left)];
        }

        if ($left === $base) {
            return [true, $this->copyValue($right)];
        }

        if ($right === $base) {
            return [true, $this->copyValue($left)];
        }

        if (! is_array($base) || ! is_array($left) || ! is_array($right)) {
            return [false, null];
        }

        $containerKinds = array_values(array_filter(
            [
                $base === [] ? null : array_is_list($base),
                $left === [] ? null : array_is_list($left),
                $right === [] ? null : array_is_list($right),
            ],
            static fn (?bool $kind): bool => $kind !== null
        ));
        $isList = $containerKinds[0] ?? true;
        foreach ($containerKinds as $containerKind) {
            if ($containerKind !== $isList) {
                return [false, null];
            }
        }

        if ($base !== [] && array_is_list($base) !== $isList) {
            return [false, null];
        }

        if ($isList) {
            [$clean, $value] = $this->mergeListConcurrentDeletions($base, $left, $right);
            if ($clean) {
                return [true, $value];
            }

            [$clean, $value] = $this->mergeListInsertionAndDeletion($base, $left, $right);
            if ($clean) {
                return [true, $value];
            }

            [$clean, $value] = $this->mergeListAssignmentAndDeletion($base, $left, $right);
            if ($clean) {
                return [true, $value];
            }
        }

        $merged = [];
        $keys = array_values(array_unique(array_merge(array_keys($base), array_keys($left), array_keys($right))));
        foreach ($keys as $key) {
            $baseHas = array_key_exists($key, $base);
            $leftHas = array_key_exists($key, $left);
            $rightHas = array_key_exists($key, $right);

            if ($baseHas && $leftHas && $rightHas) {
                [$clean, $value] = $this->mergeSharedContainerValue($base[$key], $left[$key], $right[$key]);
                if (! $clean) {
                    return [false, null];
                }
                $merged[$key] = $value;
                continue;
            }

            if (! $baseHas && $leftHas && $rightHas) {
                if ($left[$key] !== $right[$key]) {
                    return [false, null];
                }
                $merged[$key] = $this->copyValue($left[$key]);
                continue;
            }

            if ($baseHas && $leftHas && ! $rightHas) {
                if ($left[$key] !== $base[$key]) {
                    if ($this->isDescendantOnlyContainerChange($base[$key], $left[$key])) {
                        continue;
                    }

                    return [false, null];
                }
                continue;
            }

            if ($baseHas && ! $leftHas && $rightHas) {
                if ($right[$key] !== $base[$key]) {
                    if ($this->isDescendantOnlyContainerChange($base[$key], $right[$key])) {
                        continue;
                    }

                    return [false, null];
                }
                continue;
            }

            if (! $baseHas && $leftHas) {
                $merged[$key] = $this->copyValue($left[$key]);
                continue;
            }

            if (! $baseHas && $rightHas) {
                $merged[$key] = $this->copyValue($right[$key]);
            }
        }

        if ($isList) {
            ksort($merged);

            return [true, array_values($merged)];
        }

        return [true, $merged];
    }

    private function isDescendantOnlyContainerChange(mixed $base, mixed $candidate): bool
    {
        if (! is_array($base) || ! is_array($candidate) || array_is_list($base) !== array_is_list($candidate)) {
            return false;
        }

        foreach ($base as $key => $value) {
            if (! array_key_exists($key, $candidate)) {
                return false;
            }

            if ($candidate[$key] === $value) {
                continue;
            }

            if (! $this->isDescendantOnlyContainerChange($value, $candidate[$key])) {
                return false;
            }
        }

        return $candidate !== $base;
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @return array{0:bool,1:mixed}
     */
    private function mergeListConcurrentDeletions(array $base, array $left, array $right): array
    {
        $leftDeletion = $this->listDeletionRange($base, $left);
        $rightDeletion = $this->listDeletionRange($base, $right);
        if ($leftDeletion === null || $rightDeletion === null) {
            return [false, null];
        }

        $deleted = [];
        foreach ([$leftDeletion, $rightDeletion] as $range) {
            for ($offset = 0; $offset < $range['length']; ++$offset) {
                $deleted[$range['index'] + $offset] = true;
            }
        }

        $merged = [];
        foreach ($base as $index => $value) {
            if (isset($deleted[$index])) {
                continue;
            }

            $merged[] = $this->copyValue($value);
        }

        return [true, $merged];
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @return array{0:bool,1:mixed}
     */
    private function mergeListInsertionAndDeletion(array $base, array $left, array $right): array
    {
        $leftDeletion = $this->listDeletionRange($base, $left);
        $rightInsertion = $this->listInsertionRange($base, $right);
        if ($leftDeletion !== null && $rightInsertion !== null) {
            return [true, $this->listWithDeletionAndInsertion($base, $leftDeletion, $rightInsertion)];
        }

        $rightDeletion = $this->listDeletionRange($base, $right);
        $leftInsertion = $this->listInsertionRange($base, $left);
        if ($rightDeletion !== null && $leftInsertion !== null) {
            return [true, $this->listWithDeletionAndInsertion($base, $rightDeletion, $leftInsertion)];
        }

        return [false, null];
    }

    /**
     * @param list<mixed> $base
     * @param array{index:int,length:int} $deletion
     * @param array{index:int,values:list<mixed>} $insertion
     * @return list<mixed>
     */
    private function listWithDeletionAndInsertion(array $base, array $deletion, array $insertion): array
    {
        $merged = array_values(array_map(fn (mixed $value): mixed => $this->copyValue($value), $base));
        array_splice($merged, $deletion['index'], $deletion['length']);

        $insertIndex = $insertion['index'];
        $deleteEnd = $deletion['index'] + $deletion['length'];
        if ($insertIndex > $deleteEnd) {
            $insertIndex -= $deletion['length'];
        } elseif ($insertIndex >= $deletion['index']) {
            $insertIndex = $deletion['index'];
        }

        $values = array_map(fn (mixed $value): mixed => $this->copyValue($value), $insertion['values']);
        array_splice($merged, max(0, min($insertIndex, count($merged))), 0, $values);

        return array_values($merged);
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @return array{0:bool,1:mixed}
     */
    private function mergeListAssignmentAndDeletion(array $base, array $left, array $right): array
    {
        $leftAssignmentIndex = $this->singleListAssignmentIndex($base, $left);
        $rightDeletionIndex = $this->singleListDeletionIndex($base, $right);
        if ($leftAssignmentIndex !== null && $rightDeletionIndex === $leftAssignmentIndex) {
            return [true, $this->copyValue($left)];
        }

        $rightAssignmentIndex = $this->singleListAssignmentIndex($base, $right);
        $leftDeletionIndex = $this->singleListDeletionIndex($base, $left);
        if ($rightAssignmentIndex !== null && $leftDeletionIndex === $rightAssignmentIndex) {
            return [true, $this->copyValue($right)];
        }

        return [false, null];
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $candidate
     */
    private function singleListAssignmentIndex(array $base, array $candidate): ?int
    {
        if (count($candidate) !== count($base)) {
            return null;
        }

        $changedIndex = null;
        foreach ($base as $index => $value) {
            if ($candidate[$index] === $value) {
                continue;
            }

            if ($changedIndex !== null) {
                return null;
            }
            $changedIndex = $index;
        }

        return $changedIndex;
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $candidate
     */
    private function singleListDeletionIndex(array $base, array $candidate): ?int
    {
        $range = $this->listDeletionRange($base, $candidate);
        if ($range === null || $range['length'] !== 1) {
            return null;
        }

        return $range['index'];
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $candidate
     * @return array{index:int,length:int}|null
     */
    private function listDeletionRange(array $base, array $candidate): ?array
    {
        $deletedLength = count($base) - count($candidate);
        if ($deletedLength <= 0) {
            return null;
        }

        for ($deletedIndex = 0; $deletedIndex <= count($base) - $deletedLength; ++$deletedIndex) {
            $expected = array_values($base);
            array_splice($expected, $deletedIndex, $deletedLength);
            if ($candidate === $expected) {
                return ['index' => $deletedIndex, 'length' => $deletedLength];
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $candidate
     * @return array{index:int,values:list<mixed>}|null
     */
    private function listInsertionRange(array $base, array $candidate): ?array
    {
        $insertedLength = count($candidate) - count($base);
        if ($insertedLength <= 0) {
            return null;
        }

        for ($insertedIndex = 0; $insertedIndex <= count($base); ++$insertedIndex) {
            $values = array_values(array_slice($candidate, $insertedIndex, $insertedLength));
            $expected = array_values($base);
            array_splice($expected, $insertedIndex, 0, $values);
            if ($candidate === $expected) {
                return ['index' => $insertedIndex, 'values' => $values];
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $base
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @param array{operationId:string,index:int,values:list<mixed>}|null $leftInsertion
     * @param array{operationId:string,index:int,values:list<mixed>}|null $rightInsertion
     * @return array{0:bool,1:mixed}
     */
    private function mergeConcurrentListInsertions(array $base, array $left, array $right, ?array $leftInsertion, ?array $rightInsertion): array
    {
        if ($leftInsertion === null || $rightInsertion === null) {
            return [false, null];
        }

        $leftExpected = $this->listWithInsertion($base, $leftInsertion);
        $rightExpected = $this->listWithInsertion($base, $rightInsertion);
        if ($left !== $leftExpected || $right !== $rightExpected) {
            return [false, null];
        }

        $groupsByIndex = [];
        foreach ([$leftInsertion, $rightInsertion] as $insertion) {
            $index = max(0, min($insertion['index'], count($base)));
            $groupsByIndex[$index][] = $insertion;
        }
        ksort($groupsByIndex, SORT_NUMERIC);

        $merged = [];
        for ($index = 0; $index <= count($base); ++$index) {
            if (isset($groupsByIndex[$index])) {
                usort(
                    $groupsByIndex[$index],
                    fn (array $leftGroup, array $rightGroup): int => $this->compareOperationIds($rightGroup['operationId'], $leftGroup['operationId'])
                );
                foreach ($groupsByIndex[$index] as $group) {
                    foreach ($group['values'] as $value) {
                        $merged[] = $this->copyValue($value);
                    }
                }
            }

            if (array_key_exists($index, $base)) {
                $merged[] = $this->copyValue($base[$index]);
            }
        }

        return [true, $merged];
    }

    /**
     * @param array{operationId:string,index:int,values:list<mixed>} $insertion
     * @return list<mixed>
     */
    private function listWithInsertion(array $base, array $insertion): array
    {
        $list = array_values($base);
        $index = max(0, min($insertion['index'], count($list)));
        $values = array_map(fn (mixed $value): mixed => $this->copyValue($value), $insertion['values']);
        array_splice($list, $index, 0, $values);

        return array_values($list);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $materialized = [];
        foreach ($this->root as $key => $value) {
            $materialized[$key] = $this->materializeValue($value);
        }

        return $materialized;
    }

    /**
     * @return array<string,mixed>
     */
    public function rootValues(): array
    {
        return $this->copyValue($this->root);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getAllChanges(): array
    {
        return $this->changes;
    }

    /**
     * @param list<string> $heads
     * @return list<array<string,mixed>>
     */
    public function getChangesSince(array $heads): array
    {
        if ($heads === []) {
            return $this->changes;
        }

        $changesByHash = [];
        foreach ($this->changes as $change) {
            if (is_string($change['hash'] ?? null)) {
                $changesByHash[$change['hash']] = $change;
            }
        }

        $known = [];
        $stack = $this->sortedUniqueHeads($heads);
        while ($stack !== []) {
            $hash = array_pop($stack);
            if (! is_string($hash) || isset($known[$hash])) {
                continue;
            }

            $known[$hash] = true;
            $change = $changesByHash[$hash] ?? null;
            if (! is_array($change) || ! is_array($change['deps'] ?? null)) {
                continue;
            }

            foreach ($change['deps'] as $dep) {
                if (is_string($dep) && ! isset($known[$dep])) {
                    $stack[] = $dep;
                }
            }
        }

        return array_values(array_filter(
            $this->changes,
            static fn (array $change): bool => ! is_string($change['hash'] ?? null) || ! isset($known[$change['hash']])
        ));
    }

    /**
     * @param list<string> $heads
     * @return list<array{actor:string,hash:string,message:?string,time:?int,deps:list<string>,startOp:int,seq:int}>
     */
    public function getChangesMetaSince(array $heads): array
    {
        return array_map(
            fn (array $change): array => $this->changeMetadata($change),
            $this->getChangesSince($heads)
        );
    }

    /**
     * @return list<array{change:array<string,mixed>,snapshot:self}>
     */
    public function getHistory(): array
    {
        $snapshot = self::init($this->actorId);
        $history = [];
        foreach ($this->changes as $change) {
            $snapshot->applyRecordedChange($change);
            $history[] = [
                'change' => $this->changeMetadata($change) + [
                    'ops' => is_array($change['ops'] ?? null) ? array_values($change['ops']) : [],
                ],
                'snapshot' => $snapshot->clone(),
            ];
        }

        return $history;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getLastLocalChange(): ?array
    {
        for ($index = count($this->changes) - 1; $index >= 0; --$index) {
            if (($this->changes[$index]['actor'] ?? null) === $this->actorId) {
                return $this->changes[$index];
            }
        }

        return null;
    }

    public function amendLastLocalChange(?string $message = null, ?int $time = null): void
    {
        $this->assertMutable();
        for ($index = count($this->changes) - 1; $index >= 0; --$index) {
            if (($this->changes[$index]['actor'] ?? null) !== $this->actorId) {
                continue;
            }

            $oldHash = is_string($this->changes[$index]['hash'] ?? null) ? $this->changes[$index]['hash'] : null;
            $change = $this->changes[$index];
            $change['message'] = $message;
            $change['time'] = $time ?? time();

            $changeForHash = $change;
            unset($changeForHash['hash']);
            $change['hash'] = hash('sha256', json_encode($changeForHash, JSON_THROW_ON_ERROR));
            $this->changes[$index] = $change;

            if ($oldHash !== null) {
                $this->heads = $this->sortedUniqueHeads(array_map(
                    static fn (string $head): string => $head === $oldHash ? $change['hash'] : $head,
                    $this->heads
                ));
            }

            return;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function conflictsFor(string|int $key): ?array
    {
        $conflicts = $this->conflicts[(string) $key] ?? null;
        if (! is_array($conflicts) || count($conflicts) < 2) {
            return null;
        }

        $materialized = [];
        foreach ($conflicts as $operationId => $value) {
            $materialized[$operationId] = $this->materializeValue($value);
        }

        return $materialized;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function listElementConflictsFor(string $key, int $index): ?array
    {
        $conflicts = $this->conflicts[$key] ?? null;
        if (! is_array($conflicts) || count($conflicts) < 2) {
            return null;
        }

        $materialized = [];
        $counterOnly = $this->listElementCounterConflictsOnly($key, $index);
        foreach ($conflicts as $operationId => $value) {
            if (is_array($value) && array_is_list($value) && array_key_exists($index, $value)) {
                if ($counterOnly && ! $value[$index] instanceof Counter) {
                    continue;
                }

                $materialized[$operationId] = $this->materializeValue($value[$index]);
            }
        }

        return count($materialized) < 2 ? null : $materialized;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function inspectChange(string $hash): ?array
    {
        foreach ($this->changes as $change) {
            if (($change['hash'] ?? null) === $hash) {
                return $this->inspectableChange($change);
            }
        }

        return null;
    }

    public function save(): string
    {
        $encodedRoot = [];
        foreach ($this->root as $key => $value) {
            $encodedRoot[$key] = $this->encodeValue($value);
        }

        return json_encode(
            [
                'format' => 'wordpress-de/automerge-php-native-v1',
                'actor' => $this->actorId,
                'sequence' => $this->sequence,
                'heads' => $this->heads,
                'root' => $encodedRoot,
                'changes' => $this->changes,
                'conflicts' => $this->encodeConflicts(),
                'marks' => $this->copyMarks($this->marks),
            ],
            JSON_THROW_ON_ERROR
        );
    }

    public function saveWithoutOrphanedChanges(): string
    {
        $rebuilt = new self($this->actorId, 0, [], [], [], [], [], $this->incrementalHeads, $this->frozen);
        foreach ($this->changesWithSatisfiedDependencies() as $change) {
            $rebuilt->applyRecordedChange($change);
        }

        return $rebuilt->save();
    }

    public function saveIncremental(): string
    {
        $payload = $this->saveSince($this->incrementalHeads ?? []);
        $this->incrementalHeads = $this->heads();

        return $payload;
    }

    /**
     * @param list<string> $heads
     */
    public function saveSince(array $heads): string
    {
        return json_encode(
            [
                'format' => 'wordpress-de/automerge-php-native-incremental-v1',
                'changes' => $this->getChangesSince($heads),
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array{actor:string,sequence:int,heads:list<string>,keys:int,textElements:int,bytes:int}
     */
    public function stats(): array
    {
        $textElements = 0;
        foreach ($this->root as $value) {
            if ($value instanceof TextValue) {
                $textElements += $value->elementCount();
            }
        }

        return [
            'actor' => $this->actorId,
            'sequence' => $this->sequence,
            'heads' => $this->heads,
            'keys' => count($this->root),
            'textElements' => $textElements,
            'bytes' => strlen(json_encode($this, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function defaultActorId(): string
    {
        return bin2hex(random_bytes(4));
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new \RuntimeException('frozen document cannot be modified directly');
        }
    }

    private function advanceClock(): void
    {
        ++$this->sequence;
    }

    /**
     * @param list<string> $heads
     * @param list<string> $deps
     */
    private function headsCoveredByDeps(array $heads, array $deps): bool
    {
        foreach ($heads as $head) {
            if (! in_array($head, $deps, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $change
     */
    private function operationIdForChange(array $change, string $actor): string
    {
        return (int) ($change['seq'] ?? 0) . '@' . $actor;
    }

    /**
     * @param array<string,mixed> $conflicts
     * @return array<string,mixed>
     */
    private function withoutSupersededActorConflicts(array $conflicts, string $actor, int $sequence): array
    {
        foreach (array_keys($conflicts) as $operationId) {
            if (! is_string($operationId)) {
                continue;
            }

            [$conflictSequence, $conflictActor] = $this->splitOperationId($operationId);
            if ($conflictActor === $actor && $conflictSequence <= $sequence) {
                unset($conflicts[$operationId]);
            }
        }

        return $conflicts;
    }

    /**
     * @param array<string,mixed> $conflicts
     */
    private function rootConflictWinner(array $conflicts): mixed
    {
        $winner = null;
        $winnerOperationId = null;
        foreach ($conflicts as $operationId => $value) {
            if (! is_string($operationId)) {
                continue;
            }

            if ($winnerOperationId === null || $this->compareOperationIds($operationId, $winnerOperationId) > 0) {
                $winnerOperationId = $operationId;
                $winner = $value;
            }
        }

        return $this->copyValue($winner);
    }

    private function compareOperationIds(string $left, string $right): int
    {
        [$leftSeq, $leftActor] = $this->splitOperationId($left);
        [$rightSeq, $rightActor] = $this->splitOperationId($right);
        if ($leftSeq !== $rightSeq) {
            return $leftSeq <=> $rightSeq;
        }

        return strcmp($leftActor, $rightActor);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function splitOperationId(string $operationId): array
    {
        [$sequence, $actor] = array_pad(explode('@', $operationId, 2), 2, '');

        return [(int) $sequence, $actor];
    }

    private function copyValue(mixed $value): mixed
    {
        if ($value instanceof TextValue) {
            return $value->copy();
        }

        if ($value instanceof Counter) {
            return $value->copy();
        }

        if ($value instanceof BytesValue) {
            return $value->copy();
        }

        if ($value instanceof BigIntValue) {
            return new BigIntValue($value->toString());
        }

        if ($value instanceof ImmutableString) {
            return new ImmutableString($value->toString());
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_array($value)) {
            $copy = [];
            foreach ($value as $key => $item) {
                $copy[$key] = $this->copyValue($item);
            }

            return $copy;
        }

        return $value;
    }

    /**
     * @param array<string,array<string,mixed>> $conflicts
     * @return array<string,array<string,mixed>>
     */
    private function copyConflicts(array $conflicts): array
    {
        $copy = [];
        foreach ($conflicts as $key => $values) {
            foreach ($values as $operationId => $value) {
                if (is_string($operationId)) {
                    $copy[(string) $key][$operationId] = $this->copyValue($value);
                }
            }
        }

        return $copy;
    }

    /**
     * @param array<string,list<array{name:string,value:mixed,start:int,end:int,expand?:string}>> $marks
     * @return array<string,list<array{name:string,value:mixed,start:int,end:int,expand?:string}>>
     */
    private function copyMarks(array $marks, bool $includeExpand = true): array
    {
        $copy = [];
        foreach ($marks as $pathKey => $pathMarks) {
            if (! is_string($pathKey) || ! is_array($pathMarks)) {
                continue;
            }

            foreach ($pathMarks as $mark) {
                if (! is_array($mark) || ! is_string($mark['name'] ?? null)) {
                    continue;
                }

                $start = max(0, (int) ($mark['start'] ?? 0));
                $end = max($start, (int) ($mark['end'] ?? $start));
                $copiedMark = [
                    'name' => $mark['name'],
                    'value' => $this->copyValue($mark['value'] ?? true),
                    'start' => $start,
                    'end' => $end,
                ];
                if ($includeExpand && is_string($mark['expand'] ?? null) && in_array($mark['expand'], ['none', 'start', 'end', 'both'], true)) {
                    $copiedMark['expand'] = $mark['expand'];
                }

                $copy[$pathKey][] = $copiedMark;
            }
        }

        return $copy;
    }

    /**
     * @param array<string,list<array{name:string,value:mixed,start:int,end:int,expand?:string}>> $left
     * @param array<string,list<array{name:string,value:mixed,start:int,end:int,expand?:string}>> $right
     * @return array<string,list<array{name:string,value:mixed,start:int,end:int,expand?:string}>>
     */
    private function mergeMarkSets(array $left, array $right): array
    {
        $merged = $this->copyMarks($left);
        foreach ($this->copyMarks($right) as $pathKey => $marks) {
            $seen = [];
            foreach ($merged[$pathKey] ?? [] as $mark) {
                $seen[$this->markIdentity($mark)] = true;
            }

            foreach ($marks as $mark) {
                $identity = $this->markIdentity($mark);
                if (isset($seen[$identity])) {
                    continue;
                }

                $merged[$pathKey][] = $mark;
                $seen[$identity] = true;
            }

            usort(
                $merged[$pathKey],
                static fn (array $a, array $b): int => [$a['start'], $a['end'], $a['name']] <=> [$b['start'], $b['end'], $b['name']]
            );
        }

        ksort($merged, SORT_STRING);

        return $merged;
    }

    /**
     * @param array{name:string,value:mixed,start:int,end:int,expand?:string} $mark
     */
    private function markIdentity(array $mark): string
    {
        return json_encode(
            [
                $mark['name'],
                $mark['value'],
                $mark['start'],
                $mark['end'],
                $mark['expand'] ?? null,
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param list<array{name:string,value:mixed,start:int,end:int}> $existing
     * @return array{0:list<array{name:string,value:mixed,start:int,end:int}>,1:bool}
     */
    private function marksAfterUnmark(array $existing, string $name, int $start, int $end): array
    {
        if ($start >= $end) {
            return [$existing, false];
        }

        $remaining = [];
        $changed = false;
        foreach ($existing as $mark) {
            if (
                $mark['name'] !== $name
                || $mark['end'] <= $start
                || $mark['start'] >= $end
            ) {
                $remaining[] = $mark;
                continue;
            }

            $changed = true;
            if ($mark['start'] < $start) {
                $left = $mark;
                $left['end'] = $start;
                $remaining[] = $left;
            }

            if ($end < $mark['end']) {
                $right = $mark;
                $right['start'] = $end;
                $remaining[] = $right;
            }
        }

        return [array_values($remaining), $changed];
    }

    private function adjustMarksForTextSplice(string $key, int $index, int $deleteCount, string $insert): void
    {
        $pathKey = $this->pathKey([$key]);
        if (! isset($this->marks[$pathKey])) {
            return;
        }

        $insertLength = $this->utf16CodeUnitLength($insert);
        $deleteStart = max(0, $index);
        $deleteEnd = $deleteStart + max(0, $deleteCount);
        $delta = $insertLength - max(0, $deleteCount);
        $adjusted = [];

        foreach ($this->marks[$pathKey] as $mark) {
            $start = $mark['start'];
            $end = $mark['end'];
            $expand = is_string($mark['expand'] ?? null) ? $mark['expand'] : 'none';

            if ($deleteCount === 0) {
                if ($deleteStart < $start) {
                    $start += $insertLength;
                    $end += $insertLength;
                } elseif ($deleteStart === $start) {
                    if ($expand === 'start' || $expand === 'both') {
                        $end += $insertLength;
                    } else {
                        $start += $insertLength;
                        $end += $insertLength;
                    }
                } elseif ($start < $deleteStart && $deleteStart < $end) {
                    $end += $insertLength;
                } elseif ($deleteStart === $end && ($expand === 'end' || $expand === 'both')) {
                    $end += $insertLength;
                }
            } elseif ($deleteStart <= $start && $end <= $deleteEnd) {
                if ($insertLength > 0 && in_array($expand, ['start', 'end', 'both'], true)) {
                    $start = $deleteStart;
                    $end = $deleteStart + $insertLength;
                } else {
                    $start = $deleteStart;
                    $end = $deleteStart;
                }
            } elseif ($end <= $deleteStart) {
                // Mark is fully before the edit.
            } elseif ($start >= $deleteEnd) {
                $start += $delta;
                $end += $delta;
            } else {
                $start = min($start, $deleteStart);
                $end = max($start, $end + $delta);
            }

            if ($start < $end) {
                $mark['start'] = max(0, $start);
                $mark['end'] = max($mark['start'], $end);
                $adjusted[] = $mark;
            }
        }

        if ($adjusted === []) {
            unset($this->marks[$pathKey]);
            return;
        }

        $this->marks[$pathKey] = $adjusted;
    }

    private function utf16CodeUnitLength(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    private function setConflictMapValue(string $key, string $nestedKey, mixed $value): bool
    {
        $updated = false;
        foreach ($this->conflicts[$key] ?? [] as $operationId => $conflictValue) {
            if (! is_array($conflictValue)) {
                continue;
            }

            $conflictValue[$nestedKey] = $this->copyValue($value);
            $this->conflicts[$key][$operationId] = $conflictValue;
            $updated = true;
        }

        if ($updated) {
            $this->root[$key] = $this->rootConflictWinner($this->conflicts[$key]);
        }

        return $updated;
    }

    private function setConflictListElementMapValue(string $key, int $index, string $nestedKey, mixed $value): bool
    {
        $updated = false;
        foreach ($this->conflicts[$key] ?? [] as $operationId => $conflictValue) {
            if (! is_array($conflictValue) || ! array_is_list($conflictValue) || ! is_array($conflictValue[$index] ?? null)) {
                continue;
            }

            $conflictValue[$index][$nestedKey] = $this->copyValue($value);
            $this->conflicts[$key][$operationId] = $conflictValue;
            $updated = true;
        }

        if ($updated) {
            $this->root[$key] = $this->rootConflictWinner($this->conflicts[$key]);
        }

        return $updated;
    }

    private function resolveConflictListElementValue(string $key, int $index, mixed $value): bool
    {
        $conflicts = $this->conflicts[$key] ?? null;
        $rootList = $this->root[$key] ?? null;
        if (! is_array($conflicts) || ! is_array($rootList) || ! array_is_list($rootList) || ! array_key_exists($index, $rootList)) {
            return false;
        }

        $storedValue = $this->copyValue($value);
        $rootList[$index] = $this->copyValue($storedValue);
        $this->root[$key] = array_values($rootList);
        $rootFingerprint = $this->valueFingerprint($this->root[$key]);
        $remaining = [];
        $updated = false;

        foreach ($conflicts as $operationId => $conflictValue) {
            if (is_array($conflictValue) && array_is_list($conflictValue) && array_key_exists($index, $conflictValue)) {
                $conflictValue[$index] = $this->copyValue($storedValue);
                $updated = true;
            }

            if ($this->valueFingerprint($conflictValue) !== $rootFingerprint) {
                $remaining[$operationId] = $conflictValue;
            }
        }

        if (! $updated) {
            return false;
        }

        if (count($remaining) < 2) {
            unset($this->conflicts[$key]);
            return true;
        }

        ksort($remaining, SORT_STRING);
        $this->conflicts[$key] = $remaining;
        $this->root[$key] = $this->rootConflictWinner($remaining);

        return true;
    }

    private function incrementConflictCounters(string $key, int $amount, string $operationId): bool
    {
        $conflicts = $this->conflicts[$key] ?? null;
        if (! is_array($conflicts)) {
            return false;
        }

        $remaining = [];
        foreach ($conflicts as $conflictOperationId => $conflictValue) {
            if (! $conflictValue instanceof Counter) {
                continue;
            }

            $remaining[$conflictOperationId] = $conflictValue->incremented($amount, $operationId);
        }

        if ($remaining === []) {
            return false;
        }

        ksort($remaining, SORT_STRING);
        $this->root[$key] = $this->rootConflictWinner($remaining);
        if (count($remaining) < 2) {
            unset($this->conflicts[$key]);
            return true;
        }

        $this->conflicts[$key] = $remaining;

        return true;
    }

    private function incrementConflictListElementCounters(string $key, int $index, int $amount, string $operationId): bool
    {
        $conflicts = $this->conflicts[$key] ?? null;
        $rootList = $this->root[$key] ?? null;
        if (! is_array($conflicts) || ! is_array($rootList) || ! array_is_list($rootList) || ! array_key_exists($index, $rootList)) {
            return false;
        }

        $remaining = $conflicts;
        $updated = false;
        foreach ($conflicts as $conflictOperationId => $conflictValue) {
            if (
                ! is_array($conflictValue)
                || ! array_is_list($conflictValue)
                || ! array_key_exists($index, $conflictValue)
                || ! $conflictValue[$index] instanceof Counter
            ) {
                continue;
            }

            $conflictValue[$index] = $conflictValue[$index]->incremented($amount, $operationId);
            $remaining[$conflictOperationId] = $conflictValue;
            $updated = true;
        }

        if (! $updated) {
            return false;
        }

        ksort($remaining, SORT_STRING);
        $this->root[$key] = $this->rootConflictWinner($remaining);
        if (count($remaining) < 2) {
            unset($this->conflicts[$key]);
            return true;
        }

        $this->conflicts[$key] = $remaining;

        return true;
    }

    private function listElementCounterConflictsOnly(string $key, int $index): bool
    {
        foreach ($this->changes as $change) {
            foreach (is_array($change['ops'] ?? null) ? $change['ops'] : [] as $op) {
                if (
                    ($op['action'] ?? null) === 'incrementRootConflictListElementCounters'
                    && ($op['key'] ?? null) === $key
                    && (int) ($op['index'] ?? -1) === $index
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function recordRootConflict(string $key, mixed $leftValue, self $rightDocument, mixed $rightValue): void
    {
        $conflicts = $this->conflicts[$key] ?? [];
        if ($conflicts === []) {
            $conflicts[$this->latestOperationIdForKey($key)] = $this->copyValue($leftValue);
        }

        $conflicts[$rightDocument->latestOperationIdForKey($key)] = $this->copyValue($rightValue);
        ksort($conflicts, SORT_STRING);
        $this->conflicts[$key] = $conflicts;
    }

    private function latestOperationIdForKey(string $key): string
    {
        for ($changeIndex = count($this->changes) - 1; $changeIndex >= 0; --$changeIndex) {
            $change = $this->changes[$changeIndex];
            $ops = is_array($change['ops'] ?? null) ? $change['ops'] : [];
            for ($opIndex = count($ops) - 1; $opIndex >= 0; --$opIndex) {
                $op = $ops[$opIndex];
                if (! is_array($op) || ! is_string($op['action'] ?? null)) {
                    continue;
                }

                if (is_string($op['key'] ?? null) && $op['key'] === $key && ! $this->isCounterIncrementSetOp($op)) {
                    return (int) ($change['seq'] ?? 0) . '@' . (is_string($change['actor'] ?? null) ? $change['actor'] : $this->actorId);
                }
            }
        }

        for ($changeIndex = count($this->changes) - 1; $changeIndex >= 0; --$changeIndex) {
            $change = $this->changes[$changeIndex];
            $ops = is_array($change['ops'] ?? null) ? $change['ops'] : [];
            for ($opIndex = count($ops) - 1; $opIndex >= 0; --$opIndex) {
                $op = $ops[$opIndex];
                if (! is_array($op) || ! is_string($op['action'] ?? null)) {
                    continue;
                }

                if (is_array($op['path'] ?? null) && ($op['path'][0] ?? null) === $key) {
                    return (int) ($change['seq'] ?? 0) . '@' . (is_string($change['actor'] ?? null) ? $change['actor'] : $this->actorId);
                }
            }
        }

        return '0@' . $this->actorId;
    }

    /**
     * @return array{operationId:string,index:int,values:list<mixed>}|null
     */
    private function latestListInsertionForKey(string $key): ?array
    {
        for ($changeIndex = count($this->changes) - 1; $changeIndex >= 0; --$changeIndex) {
            $change = $this->changes[$changeIndex];
            $ops = is_array($change['ops'] ?? null) ? $change['ops'] : [];
            for ($opIndex = count($ops) - 1; $opIndex >= 0; --$opIndex) {
                $op = $ops[$opIndex];
                if (! is_array($op) || ($op['action'] ?? null) !== 'insertList' || ($op['key'] ?? null) !== $key) {
                    continue;
                }

                $values = [];
                foreach (is_array($op['values'] ?? null) ? $op['values'] : [] as $value) {
                    $values[] = $this->decodeValue($value);
                }

                return [
                    'operationId' => $this->operationIdForChange(
                        $change,
                        is_string($change['actor'] ?? null) ? $change['actor'] : $this->actorId
                    ),
                    'index' => (int) ($op['index'] ?? 0),
                    'values' => $values,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $op
     */
    private function isCounterIncrementSetOp(array $op): bool
    {
        if (($op['action'] ?? null) !== 'set' || ! is_array($op['value'] ?? null)) {
            return false;
        }

        $value = $op['value'];

        return ($value['type'] ?? null) === 'counter'
            && is_array($value['increments'] ?? null)
            && $value['increments'] !== [];
    }

    private function assertSupportedValue(mixed $value, string $path): void
    {
        if (
            $value instanceof TextValue
            || $value instanceof Counter
            || $value instanceof BytesValue
            || $value instanceof BigIntValue
            || $value instanceof ImmutableString
            || $this->isImmutableStringLike($value)
            || $value instanceof \DateTimeInterface
        ) {
            return;
        }

        if ($value instanceof UndefinedValue) {
            throw new \InvalidArgumentException('Cannot assign undefined value at ' . $path);
        }

        if ($value instanceof \Closure) {
            throw new \InvalidArgumentException('Cannot assign function value at ' . $path);
        }

        if (is_resource($value)) {
            throw new \InvalidArgumentException('Cannot assign resource value at ' . $path);
        }

        if ($value instanceof self || $value instanceof DocumentObjectReference) {
            throw new \InvalidArgumentException('Cannot create a reference to an existing document object at ' . $path);
        }

        if (is_object($value)) {
            throw new \InvalidArgumentException('Cannot assign object value at ' . $path);
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            $this->assertSupportedValue($item, $path . '/' . (string) $key);
        }
    }

    /**
     * @param list<string|int> $path
     * @return list<string|int>
     */
    private function normalizePath(array $path): array
    {
        if ($path === []) {
            throw new \InvalidArgumentException('Nested assignment requires a non-empty path.');
        }

        return array_map(
            static function (mixed $key): string|int {
                if (is_int($key) || is_string($key)) {
                    return $key;
                }

                throw new \InvalidArgumentException('Nested assignment path keys must be strings or integers.');
            },
            array_values($path)
        );
    }

    /**
     * @param array<string|int,mixed> $container
     * @param list<string|int> $path
     */
    private function assignNestedPath(array &$container, array $path, mixed $value): void
    {
        $key = array_shift($path);
        if ($path === []) {
            $container[$key] = $this->copyValue($value);
            return;
        }

        if (! array_key_exists($key, $container) || ! is_array($container[$key])) {
            $container[$key] = [];
        }

        $this->assignNestedPath($container[$key], $path, $value);
    }

    /**
     * @param array<string|int,mixed> $container
     * @param list<string|int> $path
     */
    private function deleteNestedPath(array &$container, array $path): void
    {
        $key = array_shift($path);
        if ($path === []) {
            unset($container[$key]);
            return;
        }

        if (! array_key_exists($key, $container) || ! is_array($container[$key])) {
            return;
        }

        $this->deleteNestedPath($container[$key], $path);
    }

    /**
     * @param list<string|int> $path
     */
    private function pathString(array $path): string
    {
        return '/' . implode('/', array_map(static fn (string|int $key): string => (string) $key, $path));
    }

    /**
     * @param list<string|int> $path
     */
    private function pathKey(array $path): string
    {
        return json_encode($path, JSON_THROW_ON_ERROR);
    }

    private function valueForKey(string $key, mixed $value): mixed
    {
        if ($this->isImmutableStringLike($value)) {
            return new ImmutableString((string) $value);
        }

        if (is_string($value) && $this->isTextKey($key)) {
            return TextValue::fromString($value, $this->actorId, $this->sequence);
        }

        return $value;
    }

    private function textValueForExistingKey(string $key, mixed $value): TextValue
    {
        [$originSequence, $originActor] = $this->splitOperationId($this->latestOperationIdForKey($key));
        $seedSequence = max(0, $originSequence);
        $text = TextValue::fromString(
            is_string($value) ? $value : '',
            $originActor !== '' ? $originActor : $this->actorId,
            $seedSequence
        );
        $this->sequence = max($this->sequence, $seedSequence);

        return $text;
    }

    private function isImmutableStringLike(mixed $value): bool
    {
        if ($value instanceof ImmutableString) {
            return true;
        }

        if (! is_object($value)) {
            return false;
        }

        return ($value->isImmutableString ?? false) === true && method_exists($value, '__toString');
    }

    private function isTextKey(string $key): bool
    {
        return $key === 'text' || $key === 'postContent';
    }

    private function materializeValue(mixed $value): mixed
    {
        if ($value instanceof TextValue) {
            return $value->toString();
        }

        if ($value instanceof Counter) {
            return $value->copy();
        }

        if ($value instanceof BytesValue) {
            return $value->copy();
        }

        if ($value instanceof ImmutableString) {
            return new ImmutableString($value->toString());
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_array($value)) {
            $materialized = [];
            foreach ($value as $key => $item) {
                $materialized[$key] = $this->materializeValue($item);
            }

            return $materialized;
        }

        return $value;
    }

    /**
     * @return array{0:int,1:int,2:string}
     */
    private function diffText(string $oldText, string $newText): array
    {
        $old = $this->splitCharacters($oldText);
        $new = $this->splitCharacters($newText);
        $oldLength = count($old);
        $newLength = count($new);
        $prefix = 0;

        while ($prefix < $oldLength && $prefix < $newLength && $old[$prefix] === $new[$prefix]) {
            ++$prefix;
        }

        $suffix = 0;
        while (
            $suffix < ($oldLength - $prefix)
            && $suffix < ($newLength - $prefix)
            && $old[$oldLength - 1 - $suffix] === $new[$newLength - 1 - $suffix]
        ) {
            ++$suffix;
        }

        return [
            $prefix,
            $oldLength - $prefix - $suffix,
            implode('', array_slice($new, $prefix, $newLength - $prefix - $suffix)),
        ];
    }

    /**
     * @return list<string>
     */
    private function splitCharacters(string $text): array
    {
        if (! preg_match_all('/\X/u', $text, $matches)) {
            if ($text === '') {
                return [];
            }

            throw new \InvalidArgumentException('Text must be valid UTF-8.');
        }

        return $matches[0];
    }

    /**
     * @param list<string> $deps
     * @param list<array<string,mixed>> $ops
     */
    private function recordChange(array $deps, array $ops, ?string $message = null, ?int $time = null, bool $preserveUnrelatedHeads = false): void
    {
        $deps = $this->sortedUniqueHeads($deps);
        $previousHeads = $this->heads;
        $change = [
            'actor' => $this->actorId,
            'seq' => $this->sequence,
            'deps' => $deps,
            'message' => $message,
            'time' => $time ?? time(),
            'startOp' => $this->nextStartOp(),
            'ops' => $ops,
        ];
        $change['hash'] = hash('sha256', json_encode($change, JSON_THROW_ON_ERROR));

        $this->changes[] = $change;
        if ($preserveUnrelatedHeads) {
            $remainingHeads = array_values(array_filter(
                $previousHeads,
                static fn (string $head): bool => ! in_array($head, $deps, true)
            ));
            $this->heads = $this->sortedUniqueHeads(array_merge($remainingHeads, [$change['hash']]));
            return;
        }

        $this->heads = [$change['hash']];
    }

    /**
     * @param array<string,mixed> $change
     */
    private function applyRecordedChange(array $change): void
    {
        $hash = is_string($change['hash'] ?? null)
            ? $change['hash']
            : hash('sha256', json_encode($change, JSON_THROW_ON_ERROR));
        if ($this->hasChangeHash($hash)) {
            return;
        }

        $previousHeads = $this->heads;
        $actor = is_string($change['actor'] ?? null) ? $change['actor'] : $this->actorId;
        $ops = is_array($change['ops'] ?? null) ? $change['ops'] : [];
        $deps = $this->sortedUniqueHeads(is_array($change['deps'] ?? null) ? $change['deps'] : []);
        $textReplaySequence = $this->sequence;
        $generatedTextElements = $this->recordedChangeGeneratedTextElementCount($ops);
        if ($generatedTextElements > 0) {
            $textReplaySequence = max($textReplaySequence, (int) ($change['seq'] ?? 0) - $generatedTextElements);
        }

        foreach ($ops as $op) {
            if (! is_array($op) || ! is_string($op['action'] ?? null)) {
                continue;
            }

            if (
                $op['action'] === 'setRootConflictMapValue'
                && is_string($op['key'] ?? null)
                && is_string($op['nestedKey'] ?? null)
            ) {
                $this->setConflictMapValue(
                    $op['key'],
                    $op['nestedKey'],
                    $this->decodeValue($op['value'] ?? null)
                );
                continue;
            }

            if (
                $op['action'] === 'setRootConflictListElementMapValue'
                && is_string($op['key'] ?? null)
                && is_string($op['nestedKey'] ?? null)
            ) {
                $this->setConflictListElementMapValue(
                    $op['key'],
                    (int) ($op['index'] ?? 0),
                    $op['nestedKey'],
                    $this->decodeValue($op['value'] ?? null)
                );
                continue;
            }

            if ($op['action'] === 'resolveRootConflictListElement' && is_string($op['key'] ?? null)) {
                $this->resolveConflictListElementValue(
                    $op['key'],
                    (int) ($op['index'] ?? 0),
                    $this->decodeValue($op['value'] ?? null)
                );
                continue;
            }

            if ($op['action'] === 'incrementRootConflictCounters' && is_string($op['key'] ?? null)) {
                $this->incrementConflictCounters(
                    $op['key'],
                    (int) ($op['amount'] ?? 0),
                    is_string($op['operationId'] ?? null) ? $op['operationId'] : $hash
                );
                continue;
            }

            if ($op['action'] === 'incrementRootConflictListElementCounters' && is_string($op['key'] ?? null)) {
                $this->incrementConflictListElementCounters(
                    $op['key'],
                    (int) ($op['index'] ?? 0),
                    (int) ($op['amount'] ?? 0),
                    is_string($op['operationId'] ?? null) ? $op['operationId'] : $hash
                );
                continue;
            }

            if ($op['action'] === 'setNested' && is_array($op['path'] ?? null)) {
                $this->assignNestedPath(
                    $this->root,
                    $this->normalizePath($op['path']),
                    $this->decodeValue($op['value'] ?? null)
                );
                continue;
            }

            if ($op['action'] === 'insertList' && is_string($op['key'] ?? null)) {
                $key = $op['key'];
                $currentList = $this->root[$key] ?? null;
                $hasExistingListItems = is_array($currentList) && array_is_list($currentList) && count($currentList) > 0;
                if (! $hasExistingListItems && ! $this->headsCoveredByDeps($deps, $previousHeads) && is_array($op['value'] ?? null)) {
                    $this->root[$key] = $this->decodeValue($op['value']);
                    unset($this->conflicts[$key]);
                    continue;
                }

                $list = $currentList ?? [];
                $list = is_array($list) && array_is_list($list) ? array_values($list) : [];
                $values = [];
                foreach (is_array($op['values'] ?? null) ? $op['values'] : [] as $value) {
                    $values[] = $this->decodeValue($value);
                }
                $index = max(0, min((int) ($op['index'] ?? 0), count($list)));
                array_splice($list, $index, 0, $values);
                $this->root[$key] = array_values($list);
                unset($this->conflicts[$key]);
                continue;
            }

            if ($op['action'] === 'deleteNested' && is_array($op['path'] ?? null)) {
                $this->deleteNestedPath($this->root, $this->normalizePath($op['path']));
                continue;
            }

            if ($op['action'] === 'mark' && is_array($op['path'] ?? null) && is_array($op['marks'] ?? null)) {
                $path = $this->normalizePath($op['path']);
                $pathKey = $this->pathKey($path);
                $this->marks[$pathKey] = array_values(array_merge(
                    $this->marks[$pathKey] ?? [],
                    $this->copyMarks([$pathKey => $op['marks']])[$pathKey] ?? []
                ));
                continue;
            }

            if ($op['action'] === 'unmark' && is_array($op['path'] ?? null) && is_string($op['name'] ?? null)) {
                $pathKey = $this->pathKey($this->normalizePath($op['path']));
                $start = max(0, (int) ($op['start'] ?? 0));
                $end = max($start, (int) ($op['end'] ?? $start));
                [$remaining] = $this->marksAfterUnmark($this->marks[$pathKey] ?? [], $op['name'], $start, $end);
                if ($remaining === []) {
                    unset($this->marks[$pathKey]);
                } else {
                    $this->marks[$pathKey] = $remaining;
                }
                continue;
            }

            if (! is_string($op['key'] ?? null)) {
                continue;
            }

            if ($op['action'] === 'set') {
                $key = $op['key'];
                $value = $this->decodeValue($op['value'] ?? null);
                if ($this->headsCoveredByDeps($previousHeads, $deps) || ! array_key_exists($key, $this->root)) {
                    $this->root[$key] = $value;
                    unset($this->conflicts[$key]);
                    continue;
                }

                if (! isset($this->conflicts[$key])) {
                    $this->conflicts[$key][$this->latestOperationIdForKey($key)] = $this->copyValue($this->root[$key]);
                }

                $this->conflicts[$key] = $this->withoutSupersededActorConflicts(
                    $this->conflicts[$key],
                    $actor,
                    (int) ($change['seq'] ?? 0)
                );
                $this->conflicts[$key][$this->operationIdForChange($change, $actor)] = $this->copyValue($value);
                ksort($this->conflicts[$key], SORT_STRING);
                $this->root[$key] = $this->rootConflictWinner($this->conflicts[$key]);
                continue;
            }

            if ($op['action'] === 'delete') {
                unset($this->root[$op['key']]);
                continue;
            }

            if ($op['action'] === 'splice') {
                $value = $this->root[$op['key']] ?? null;
                if (! $value instanceof TextValue) {
                    $value = $this->textValueForExistingKey($op['key'], $value);
                }

                $value->splice(
                    (int) ($op['index'] ?? 0),
                    (int) ($op['deleteCount'] ?? 0),
                    is_string($op['insert'] ?? null) ? $op['insert'] : '',
                    $actor,
                    $textReplaySequence
                );
                $this->sequence = max($this->sequence, $textReplaySequence);
                $this->root[$op['key']] = $value;
            }

            if ($op['action'] === 'putText') {
                $value = $this->root[$op['key']] ?? null;
                if (! $value instanceof TextValue) {
                    $value = $this->textValueForExistingKey($op['key'], $value);
                }

                $value->splice(
                    (int) ($op['index'] ?? 0),
                    1,
                    is_string($op['value'] ?? null) ? $op['value'] : '',
                    $actor,
                    $textReplaySequence
                );
                $this->sequence = max($this->sequence, $textReplaySequence);
                $this->root[$op['key']] = $value;
            }
        }

        $this->sequence = max($this->sequence, (int) ($change['seq'] ?? 0));
        $remainingHeads = array_values(array_filter(
            $previousHeads,
            static fn (string $head): bool => ! in_array($head, $deps, true)
        ));
        $this->heads = $this->sortedUniqueHeads(array_merge($remainingHeads, [$hash]));
        $this->changes[] = $change + ['hash' => $hash, 'deps' => $deps];
    }

    private function nextStartOp(): int
    {
        $next = 1;
        foreach ($this->changes as $change) {
            foreach (is_array($change['ops'] ?? null) ? $change['ops'] : [] as $op) {
                if (is_array($op)) {
                    $next += $this->legacyOperationCount($op);
                }
            }
        }

        return $next;
    }

    /**
     * @param array<string,mixed> $op
     */
    private function legacyOperationCount(array $op): int
    {
        if ($op['action'] === 'set' && is_array($op['value'] ?? null)) {
            return $this->encodedValueOperationCount($op['value']);
        }

        if ($op['action'] === 'insertList' && is_array($op['values'] ?? null)) {
            $count = 0;
            foreach ($op['values'] as $value) {
                $count += $this->encodedValueOperationCount($value);
            }

            return max(1, $count);
        }

        if ($op['action'] === 'setNested' && is_array($op['value'] ?? null)) {
            return $this->encodedValueOperationCount($op['value']);
        }

        return 1;
    }

    /**
     * @param list<array<string,mixed>> $ops
     */
    private function recordedChangeGeneratedTextElementCount(array $ops): int
    {
        $count = 0;
        foreach ($ops as $op) {
            if (! is_array($op) || ! is_string($op['action'] ?? null)) {
                continue;
            }

            if ($op['action'] === 'splice' && is_string($op['insert'] ?? null)) {
                $count += count($this->splitCharacters($op['insert']));
                continue;
            }

            if ($op['action'] === 'putText' && is_string($op['value'] ?? null)) {
                $count += count($this->splitCharacters($op['value']));
            }
        }

        return $count;
    }

    private function encodedValueOperationCount(mixed $encoded): int
    {
        if (! is_array($encoded)) {
            return 1;
        }

        if (($encoded['type'] ?? null) === 'scalar') {
            return is_string($encoded['value'] ?? null) ? 2 : 1;
        }

        if (($encoded['type'] ?? null) === 'text') {
            if (is_int($encoded['elementCount'] ?? null)) {
                return 1 + max(0, $encoded['elementCount']);
            }

            $elements = is_array($encoded['elements'] ?? null) ? $encoded['elements'] : [];

            return 1 + count($elements);
        }

        if (($encoded['type'] ?? null) === 'array') {
            $count = 1;
            foreach (is_array($encoded['value'] ?? null) ? $encoded['value'] : [] as $value) {
                $count += $this->encodedValueOperationCount($value);
            }

            return $count;
        }

        return 1;
    }

    /**
     * @return array{actor:string,hash:string,message:?string,time:?int,deps:list<string>,startOp:int,seq:int}
     */
    private function changeMetadata(array $change): array
    {
        return [
            'actor' => is_string($change['actor'] ?? null) ? $change['actor'] : '',
            'hash' => is_string($change['hash'] ?? null) ? $change['hash'] : '',
            'message' => is_string($change['message'] ?? null) ? $change['message'] : null,
            'time' => is_int($change['time'] ?? null) ? $change['time'] : null,
            'deps' => is_array($change['deps'] ?? null) ? array_values($change['deps']) : [],
            'startOp' => (int) ($change['startOp'] ?? 1),
            'seq' => (int) ($change['seq'] ?? 0),
        ];
    }

    /**
     * @return array{actor:string,deps:list<string>,hash:string,message:?string,ops:list<array<string,mixed>>,seq:int,startOp:int,time:?int}
     */
    private function inspectableChange(array $change): array
    {
        $metadata = $this->changeMetadata($change);

        return [
            'actor' => $metadata['actor'],
            'deps' => $metadata['deps'],
            'hash' => $metadata['hash'],
            'message' => $metadata['message'],
            'ops' => $this->inspectableOps($change),
            'seq' => $metadata['seq'],
            'startOp' => $metadata['startOp'],
            'time' => $metadata['time'],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function inspectableOps(array $change): array
    {
        $ops = [];
        $actor = is_string($change['actor'] ?? null) ? $change['actor'] : $this->actorId;
        $startOp = (int) ($change['startOp'] ?? 1);
        foreach (is_array($change['ops'] ?? null) ? $change['ops'] : [] as $index => $op) {
            if (! is_array($op) || ! is_string($op['action'] ?? null)) {
                continue;
            }

            if (
                $op['action'] === 'set'
                && is_string($op['key'] ?? null)
                && is_array($op['value'] ?? null)
                && ($op['value']['type'] ?? null) === 'scalar'
                && is_string($op['value']['value'] ?? null)
            ) {
                $objectId = ($startOp + $index) . '@' . $actor;
                $ops[] = [
                    'action' => 'makeText',
                    'key' => $op['key'],
                    'obj' => '_root',
                    'pred' => [],
                ];
                $ops[] = [
                    'action' => 'set',
                    'elemId' => '_head',
                    'insert' => true,
                    'obj' => $objectId,
                    'pred' => [],
                    'value' => $op['value']['value'],
                ];
                continue;
            }

            $ops[] = $op;
        }

        return $ops;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function encodeConflicts(): array
    {
        $encoded = [];
        foreach ($this->conflicts as $key => $values) {
            foreach ($values as $operationId => $value) {
                $encoded[$key][$operationId] = $this->encodeValue($value);
            }
        }

        return $encoded;
    }

    private function encodeValue(mixed $value): mixed
    {
        if ($value instanceof TextValue) {
            return $value->encodedValue();
        }

        if ($value instanceof Counter) {
            return [
                'type' => 'counter',
                'id' => $value->id(),
                'initial' => $value->initialValue(),
                'increments' => $value->increments(),
            ];
        }

        if ($value instanceof BytesValue) {
            return [
                'type' => 'bytes',
                'value' => $value->bytes(),
            ];
        }

        if ($value instanceof BigIntValue) {
            return [
                'type' => 'bigint',
                'decimal' => $value->toString(),
            ];
        }

        if ($value instanceof ImmutableString) {
            return [
                'type' => 'immutableString',
                'value' => $value->toString(),
            ];
        }

        if ($value instanceof \DateTimeInterface) {
            return [
                'type' => 'date',
                'millis' => $this->dateMillis($value),
            ];
        }

        if (is_float($value) && is_nan($value)) {
            return [
                'type' => 'float',
                'value' => 'NaN',
            ];
        }

        if (is_float($value) && is_infinite($value)) {
            return [
                'type' => 'float',
                'value' => $value > 0 ? 'Infinity' : '-Infinity',
            ];
        }

        if (is_array($value)) {
            $encoded = [];
            foreach ($value as $key => $item) {
                $encoded[$key] = $this->encodeValue($item);
            }

            return [
                'type' => 'array',
                'value' => $encoded,
            ];
        }

        return [
            'type' => 'scalar',
            'value' => $value,
        ];
    }

    private function valueFingerprint(mixed $value): string
    {
        return json_encode($this->encodeValue($value), JSON_THROW_ON_ERROR);
    }

    private function decodeValue(mixed $encoded, bool $convertStringScalarsToText = false, ?int &$textSequence = null): mixed
    {
        if (! is_array($encoded) || ! is_string($encoded['type'] ?? null)) {
            return $encoded;
        }

        if ($encoded['type'] === 'text') {
            if (is_string($encoded['value'] ?? null)) {
                return TextValue::fromCompactString(
                    $encoded['value'],
                    is_string($encoded['actor'] ?? null) ? $encoded['actor'] : $this->actorId,
                    max(1, (int) ($encoded['startSeq'] ?? 1)),
                    is_int($encoded['elementCount'] ?? null) ? $encoded['elementCount'] : null
                );
            }

            if (is_array($encoded['elements'] ?? null)) {
                return new TextValue($encoded['elements']);
            }
        }

        if ($encoded['type'] === 'counter') {
            $increments = [];
            if (is_array($encoded['increments'] ?? null)) {
                foreach ($encoded['increments'] as $operationId => $amount) {
                    if (is_string($operationId) && is_int($amount)) {
                        $increments[$operationId] = $amount;
                    }
                }
            }

            return new Counter(
                (int) ($encoded['initial'] ?? 0),
                is_string($encoded['id'] ?? null) ? $encoded['id'] : null,
                $increments
            );
        }

        if ($encoded['type'] === 'bytes' && is_array($encoded['value'] ?? null)) {
            return new BytesValue(array_values(array_map(static fn (mixed $byte): int => (int) $byte, $encoded['value'])));
        }

        if ($encoded['type'] === 'bigint' && is_string($encoded['decimal'] ?? null)) {
            return new BigIntValue($encoded['decimal']);
        }

        if ($encoded['type'] === 'float' && is_string($encoded['value'] ?? null)) {
            return match ($encoded['value']) {
                'NaN' => NAN,
                'Infinity' => INF,
                '-Infinity' => -INF,
                default => null,
            };
        }

        if ($encoded['type'] === 'immutableString' && is_string($encoded['value'] ?? null)) {
            return new ImmutableString($encoded['value']);
        }

        if ($encoded['type'] === 'array' && is_array($encoded['value'] ?? null)) {
            $decoded = [];
            foreach ($encoded['value'] as $key => $value) {
                $decoded[$key] = $this->decodeValue($value, $convertStringScalarsToText, $textSequence);
            }

            return $decoded;
        }

        if ($encoded['type'] === 'date') {
            return $this->dateFromMillis((int) ($encoded['millis'] ?? 0));
        }

        if ($convertStringScalarsToText && ($encoded['type'] ?? null) === 'scalar' && is_string($encoded['value'] ?? null)) {
            $textSequence ??= $this->sequence;

            return TextValue::fromString($encoded['value'], $this->actorId, $textSequence);
        }

        return $encoded['value'] ?? null;
    }

    /**
     * @param list<array<string,mixed>> $left
     * @param list<array<string,mixed>> $right
     * @return list<array<string,mixed>>
     */
    private function mergeChangeLists(array $left, array $right): array
    {
        $changes = [];
        foreach (array_merge($left, $right) as $change) {
            $hash = is_string($change['hash'] ?? null)
                ? $change['hash']
                : hash('sha256', json_encode($change, JSON_THROW_ON_ERROR));
            $changes[$hash] = $change + ['hash' => $hash];
        }

        return array_values($changes);
    }

    private function hasChangeHash(string $hash): bool
    {
        foreach ($this->changes as $change) {
            if (($change['hash'] ?? null) === $hash) {
                return true;
            }
        }

        return false;
    }

    private function rebuildMaterializedStateFromDependencyOrder(): void
    {
        $rebuilt = new self($this->actorId, 0, [], [], [], [], [], $this->incrementalHeads, $this->frozen);
        foreach ($this->changesInDependencyOrder($this->changes) as $change) {
            $rebuilt->applyRecordedChange($change);
        }

        $this->sequence = $rebuilt->sequence;
        $this->root = $rebuilt->root;
        $this->heads = $rebuilt->heads;
        $this->changes = $rebuilt->changes;
        $this->conflicts = $rebuilt->conflicts;
        $this->marks = $rebuilt->marks;
    }

    /**
     * @param list<array<string,mixed>> $changes
     * @return list<array<string,mixed>>
     */
    private function changesInDependencyOrder(array $changes): array
    {
        $remaining = [];
        foreach ($changes as $change) {
            $hash = $this->changeHash($change);
            if (! isset($remaining[$hash])) {
                $remaining[$hash] = $change + ['hash' => $hash];
            }
        }

        $ordered = [];
        $orderedHashes = [];
        while ($remaining !== []) {
            $madeProgress = false;
            foreach ($remaining as $hash => $change) {
                if ($this->changeHasUnorderedInternalDependency($change, $remaining, $orderedHashes)) {
                    continue;
                }

                $ordered[] = $change;
                $orderedHashes[$hash] = true;
                unset($remaining[$hash]);
                $madeProgress = true;
            }

            if (! $madeProgress) {
                array_push($ordered, ...array_values($remaining));
                break;
            }
        }

        return $ordered;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function changesWithSatisfiedDependencies(): array
    {
        $retained = [];
        $known = [];
        foreach ($this->changesInDependencyOrder($this->changes) as $change) {
            $deps = $this->sortedUniqueHeads(is_array($change['deps'] ?? null) ? $change['deps'] : []);
            foreach ($deps as $dep) {
                if (! isset($known[$dep])) {
                    continue 2;
                }
            }

            $hash = $this->changeHash($change);
            $retained[] = $change + ['hash' => $hash, 'deps' => $deps];
            $known[$hash] = true;
        }

        return $retained;
    }

    /**
     * @param array<string,mixed> $change
     * @param array<string,array<string,mixed>> $remaining
     * @param array<string,bool> $orderedHashes
     */
    private function changeHasUnorderedInternalDependency(array $change, array $remaining, array $orderedHashes): bool
    {
        foreach (is_array($change['deps'] ?? null) ? $change['deps'] : [] as $dep) {
            if (is_string($dep) && isset($remaining[$dep]) && ! isset($orderedHashes[$dep])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $change
     */
    private function changeHash(array $change): string
    {
        return is_string($change['hash'] ?? null)
            ? $change['hash']
            : hash('sha256', json_encode($change, JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<mixed> $heads
     * @return list<string>
     */
    private function sortedUniqueHeads(array $heads): array
    {
        $unique = [];
        foreach ($heads as $head) {
            if (is_string($head)) {
                $unique[$head] = true;
            }
        }

        $sorted = array_keys($unique);
        sort($sorted, SORT_STRING);

        return $sorted;
    }

    private function dateMillis(\DateTimeInterface $value): int
    {
        return ((int) $value->format('U')) * 1000 + intdiv((int) $value->format('u'), 1000);
    }

    private function dateFromMillis(int $millis): \DateTimeImmutable
    {
        $seconds = intdiv($millis, 1000);
        $microseconds = ($millis % 1000) * 1000;
        $date = \DateTimeImmutable::createFromFormat(
            'U.u',
            sprintf('%d.%06d', $seconds, $microseconds),
            new \DateTimeZone('UTC')
        );
        if (! $date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('Unable to decode native date value.');
        }

        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
