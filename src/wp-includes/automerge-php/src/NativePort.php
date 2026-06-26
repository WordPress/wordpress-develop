<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

final class NativePort
{
    private const BLOCK_MARK_NAME = '__automerge_block';
    private const BLOCK_CHARACTER = "\u{FFFC}";

    /** @var callable|null */
    private mixed $patchCallback;

    private ?Document $activeChangeBase = null;

    private ?Document $activeChangeDraft = null;

    private \SplObjectStorage $outdatedChangeBases;

    private \SplObjectStorage $documentPatchCallbacks;

    private \SplObjectStorage $documentDiffCursors;

    public function __construct(?callable $patchCallback = null)
    {
        $this->patchCallback = $patchCallback;
        $this->outdatedChangeBases = new \SplObjectStorage();
        $this->documentPatchCallbacks = new \SplObjectStorage();
        $this->documentDiffCursors = new \SplObjectStorage();
    }

    public function withPatchCallback(callable $patchCallback): self
    {
        return new self($patchCallback);
    }

    public function next(): self
    {
        return new self($this->patchCallback);
    }

    public function init(?string $actorId = null): Document
    {
        return Document::init($actorId);
    }

    public function initFrozen(?string $actorId = null): Document
    {
        return $this->init($actorId)->withFrozen(true);
    }

    public function initWithPatchCallback(callable $patchCallback, ?string $actorId = null): Document
    {
        $document = $this->init($actorId);
        $this->documentPatchCallbacks[$document] = $patchCallback;

        return $document;
    }

    public function from(mixed $root, ?string $actorId = null): Document
    {
        return Document::fromArray($this->initialRootMap($root), $actorId);
    }

    public function fromWithPatchCallback(mixed $root, callable $patchCallback, ?string $actorId = null): Document
    {
        $document = $this->from($root, $actorId);
        $before = $this->init($document->actorId());
        $patches = $this->patchesBetweenDocuments($before, $document);
        if ($patches !== []) {
            $patchCallback($patches, ['before' => $before, 'after' => $document, 'source' => 'from']);
        }
        $this->documentPatchCallbacks[$document] = $patchCallback;

        return $document;
    }

    public function fromFrozen(mixed $root, ?string $actorId = null): Document
    {
        return $this->from($root, $actorId)->withFrozen(true);
    }

    public function clone(Document $document, ?string $actorId = null): Document
    {
        return $document->clone($actorId);
    }

    public function cloneWithPatchCallback(Document $document, callable $patchCallback, ?string $actorId = null): Document
    {
        $cloned = $this->clone($document, $actorId);
        $this->documentPatchCallbacks[$cloned] = $patchCallback;

        return $cloned;
    }

    /**
     * @param list<string> $heads
     */
    public function view(Document $document, array $heads): Document
    {
        return $document->view($heads);
    }

    /**
     * @param list<string> $heads
     */
    public function isolate(Document|IsolatedDocument $document, array $heads): IsolatedDocument
    {
        $hiddenDocument = $document instanceof IsolatedDocument
            ? $this->mergeDocuments($document->hiddenDocument(), $document->visibleDocument())
            : $document;
        $heads = $this->validateDiffHeads($hiddenDocument, $heads, 'isolate');
        $visibleDocument = $hiddenDocument->view($heads)->withFrozen(false);
        $visibleDocument->ensureSequenceAtLeast($hiddenDocument->stats()['sequence']);
        $diffCursor = $document instanceof IsolatedDocument ? $document->diffCursor() : null;
        if (! $document instanceof IsolatedDocument && $this->documentDiffCursors->contains($document)) {
            $cursor = $this->documentDiffCursors[$document];
            $diffCursor = is_array($cursor) ? array_values($cursor) : null;
        }

        return new IsolatedDocument($hiddenDocument, $visibleDocument, $heads, $diffCursor);
    }

    public function isolatedDocument(IsolatedDocument $document): Document
    {
        return $document->visibleDocument();
    }

    public function mergeIntoIsolation(IsolatedDocument $document, Document $other): IsolatedDocument
    {
        return $document->withHiddenDocument($this->mergeDocuments($document->hiddenDocument(), $other));
    }

    public function integrate(IsolatedDocument $document): Document
    {
        $integrated = $this->mergeDocuments($document->hiddenDocument(), $document->visibleDocument());
        $diffCursor = $document->diffCursor();
        if ($diffCursor !== null) {
            $this->documentDiffCursors[$integrated] = $diffCursor;
        }

        return $integrated;
    }

    public function setInIsolation(IsolatedDocument $document, string $key, mixed $value): IsolatedDocument
    {
        return $document->withVisibleDocument($this->set($this->isolationEditDocument($document), $key, $value));
    }

    /**
     * @param list<string|int> $path
     */
    public function setNestedInIsolation(IsolatedDocument $document, array $path, mixed $value): IsolatedDocument
    {
        return $document->withVisibleDocument($this->setNested($this->isolationEditDocument($document), $path, $value));
    }

    public function deleteInIsolation(IsolatedDocument $document, string $key): IsolatedDocument
    {
        return $document->withVisibleDocument($this->delete($this->isolationEditDocument($document), $key));
    }

    /**
     * @param list<string|int> $path
     */
    public function deleteNestedInIsolation(IsolatedDocument $document, array $path): IsolatedDocument
    {
        return $document->withVisibleDocument($this->deleteNested($this->isolationEditDocument($document), $path));
    }

    /**
     * @param list<mixed> $values
     */
    public function insertListElementsInIsolation(IsolatedDocument $document, string $key, int $index, array $values): IsolatedDocument
    {
        return $document->withVisibleDocument($this->insertListElements($this->isolationEditDocument($document), $key, $index, $values));
    }

    public function setListElementInIsolation(IsolatedDocument $document, string $key, int $index, mixed $value): IsolatedDocument
    {
        return $document->withVisibleDocument($this->setListElement($this->isolationEditDocument($document), $key, $index, $value));
    }

    public function deleteListElementsInIsolation(IsolatedDocument $document, string $key, int $index, int $length = 1): IsolatedDocument
    {
        return $document->withVisibleDocument($this->deleteListElements($this->isolationEditDocument($document), $key, $index, $length));
    }

    public function spliceInIsolation(IsolatedDocument $document, string $key, int $index, int $deleteCount, string $insert = ''): IsolatedDocument
    {
        return $document->withVisibleDocument($this->splice($this->isolationEditDocument($document), $key, $index, $deleteCount, $insert));
    }

    public function isAutomerge(mixed $value): bool
    {
        return $value instanceof Document;
    }

    public function immutableString(string $value): ImmutableString
    {
        return new ImmutableString($value);
    }

    public function rawString(string $value): RawString
    {
        return new RawString($value);
    }

    public function bigInt(string|int $decimal): BigIntValue
    {
        return new BigIntValue($decimal);
    }

    public function undefined(): UndefinedValue
    {
        return new UndefinedValue();
    }

    public function isImmutableString(mixed $value): bool
    {
        return $value instanceof ImmutableString;
    }

    public function isRawString(mixed $value): bool
    {
        return $this->isImmutableString($value);
    }

    /**
     * @return array<string,mixed>
     */
    public function toJS(Document $document): array
    {
        return $document->toArray();
    }

    public function getBackend(Document $document): BackendView
    {
        return new BackendView($document->toArray());
    }

    public function getObjectId(mixed $value): ?string
    {
        if ($value instanceof Document) {
            return '_root';
        }

        if (is_array($value)) {
            return '1@' . hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
        }

        return null;
    }

    /**
     * @return array{type:string,id:string,counter?:string,actor?:string}
     */
    public function legacyDeserializeObjectId(string $objectId): array
    {
        if ($objectId === '_root') {
            return ['type' => 'root', 'id' => '_root'];
        }

        if (! preg_match('/^(0|[1-9][0-9]*)@((?:[0-9a-fA-F]{2})+)$/', $objectId, $matches)) {
            throw new \InvalidArgumentException('A valid ObjectID must be _root or an operation id.');
        }

        return [
            'type' => 'op',
            'id' => $matches[1] . '@' . strtolower($matches[2]),
            'counter' => $matches[1],
            'actor' => strtolower($matches[2]),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function legacyDeserializeOp(array $op): array
    {
        if (! is_string($op['obj'] ?? null)) {
            throw new \InvalidArgumentException('A valid ObjectID is required for a legacy op object.');
        }

        $key = null;
        if (array_key_exists('key', $op)) {
            $key = ['type' => 'key', 'value' => (string) $op['key']];
        }

        if (array_key_exists('elemId', $op)) {
            if ($key !== null) {
                throw new \InvalidArgumentException('Legacy op may not contain both key and elemId.');
            }

            $objectId = $this->legacyDeserializeObjectId((string) $op['elemId']);
            if ($objectId['type'] !== 'op') {
                throw new \InvalidArgumentException('Legacy elemId keys must be operation ids.');
            }

            $key = ['type' => 'elemId', 'id' => $objectId['id']];
        }

        if ($key === null) {
            throw new \InvalidArgumentException('Legacy op requires a key or elemId.');
        }

        return [
            'action' => $this->legacyDeserializeOpAction($op),
            'obj' => $this->legacyDeserializeObjectId($op['obj']),
            'key' => $key,
            'insert' => (bool) ($op['insert'] ?? false),
            'pred' => $this->legacyDeserializePred($op),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function legacyDeserializeOpAction(array $op): array
    {
        if (! is_string($op['action'] ?? null)) {
            throw new \InvalidArgumentException('Legacy op requires an action.');
        }

        $datatype = is_string($op['datatype'] ?? null) ? $op['datatype'] : null;

        return match ($op['action']) {
            'makeMap' => ['type' => 'make', 'objectType' => 'map'],
            'makeTable' => ['type' => 'make', 'objectType' => 'table'],
            'makeList' => ['type' => 'make', 'objectType' => 'list'],
            'makeText' => ['type' => 'make', 'objectType' => 'text'],
            'del' => ['type' => 'delete'],
            'set' => ['type' => 'put', 'value' => $this->legacyDeserializeScalarValueFromOp($op, $datatype)],
            'inc' => ['type' => 'increment', 'value' => $this->legacyDeserializeIncrementValue($op, $datatype)],
            'markBegin' => [
                'type' => 'markBegin',
                'name' => (string) ($op['name'] ?? throw new \InvalidArgumentException('Legacy markBegin requires a name.')),
                'value' => $this->legacyDeserializeScalarValueFromOp($op, $datatype),
                'expand' => (bool) ($op['expand'] ?? false),
            ],
            'markEnd' => ['type' => 'markEnd', 'expand' => (bool) ($op['expand'] ?? false)],
            default => throw new \InvalidArgumentException('Unknown legacy op action: ' . $op['action']),
        };
    }

    /**
     * @return array{key?:string,elemId?:string}
     */
    public function legacySerializeOpKey(string|int|array $key): array
    {
        if (is_array($key) && ($key['type'] ?? null) === 'elemId' && is_string($key['id'] ?? null)) {
            $key = ['elemId' => $key['id']];
        }

        if (is_array($key) && ($key['type'] ?? null) === 'key' && array_key_exists('value', $key)) {
            $key = ['key' => $key['value']];
        }

        if (is_array($key) && is_string($key['elemId'] ?? null)) {
            $objectId = $this->legacyDeserializeObjectId($key['elemId']);
            if ($objectId['type'] !== 'op') {
                throw new \InvalidArgumentException('Legacy elemId keys must be operation ids.');
            }

            return ['elemId' => $objectId['id']];
        }

        if (is_array($key) && array_key_exists('key', $key)) {
            return ['key' => (string) $key['key']];
        }

        if (! is_array($key)) {
            return ['key' => (string) $key];
        }

        throw new \InvalidArgumentException('Legacy op key must be a map key or elemId descriptor.');
    }

    /**
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    public function legacySerializeOp(array $op): array
    {
        if (! is_array($op['action'] ?? null)) {
            throw new \InvalidArgumentException('Legacy op serialization requires a normalized action descriptor.');
        }

        $serialized = $this->legacySerializeOpAction($op['action']);
        $serialized['obj'] = $this->legacySerializeObjectId($op['obj'] ?? null);
        $serialized += $this->legacySerializeOpKey($op['key'] ?? throw new \InvalidArgumentException('Legacy op serialization requires a key.'));

        if (($op['insert'] ?? false) === true) {
            $serialized['insert'] = true;
        }

        $serialized['pred'] = array_map(
            fn (mixed $pred): string => $this->legacySerializeObjectId($pred),
            is_array($op['pred'] ?? null) ? array_values($op['pred']) : []
        );

        return $serialized;
    }

    public function legacySerializeObjectId(mixed $objectId): string
    {
        if (is_array($objectId) && is_string($objectId['id'] ?? null)) {
            $objectId = $objectId['id'];
        }

        if (! is_string($objectId)) {
            throw new \InvalidArgumentException('Legacy object id serialization requires an object id string.');
        }

        return $this->legacyDeserializeObjectId($objectId)['id'];
    }

    /**
     * @param array<string,mixed> $op
     * @return list<string>
     */
    private function legacyDeserializePred(array $op): array
    {
        if (! array_key_exists('pred', $op) || ! is_array($op['pred'])) {
            throw new \InvalidArgumentException('Legacy op requires a pred list.');
        }

        $pred = [];
        foreach (array_values($op['pred']) as $id) {
            if (! is_string($id)) {
                throw new \InvalidArgumentException('Legacy pred entries must be operation ids.');
            }

            $objectId = $this->legacyDeserializeObjectId($id);
            if ($objectId['type'] !== 'op') {
                throw new \InvalidArgumentException('Legacy pred entries must be operation ids.');
            }

            $pred[] = $objectId['id'];
        }

        return $pred;
    }

    /**
     * @param array<string,mixed> $op
     * @return array{type:string,value:mixed}
     */
    private function legacyDeserializeScalarValueFromOp(array $op, ?string $datatype): array
    {
        if (! array_key_exists('value', $op)) {
            throw new \InvalidArgumentException('missing field value');
        }

        return $this->legacyDeserializeScalarValue($op['value'], $datatype);
    }

    /**
     * @return array{type:string,value:mixed}
     */
    private function legacyDeserializeScalarValue(mixed $value, ?string $datatype): array
    {
        if ($datatype !== null) {
            return match ($datatype) {
                'uint' => ['type' => 'uint', 'value' => $this->legacyIntegerValue($value, 'an integer')],
                'int' => ['type' => 'int', 'value' => $this->legacyIntegerValue($value, 'an integer')],
                'float64' => ['type' => 'float64', 'value' => $this->legacyNumberValue($value, 'a number')],
                'counter' => ['type' => 'counter', 'value' => $this->legacyIntegerValue($value, 'an integer')],
                'timestamp' => ['type' => 'timestamp', 'value' => $this->legacyIntegerValue($value, 'an integer')],
                default => throw new \InvalidArgumentException('Unknown legacy datatype: ' . $datatype),
            };
        }

        if ($value === null) {
            return ['type' => 'null', 'value' => null];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean', 'value' => $value];
        }

        if (is_int($value)) {
            return ['type' => 'int', 'value' => $value];
        }

        if (is_float($value)) {
            return ['type' => 'float64', 'value' => $value];
        }

        if (is_string($value)) {
            return ['type' => 'str', 'value' => $value];
        }

        throw new \InvalidArgumentException('Legacy scalar value is not supported.');
    }

    /**
     * @param array<string,mixed> $op
     */
    private function legacyDeserializeIncrementValue(array $op, ?string $datatype): int
    {
        $value = $this->legacyDeserializeScalarValueFromOp($op, $datatype);

        return match ($value['type']) {
            'int', 'uint', 'counter', 'timestamp' => (int) $value['value'],
            'float64' => (int) $value['value'],
            default => throw new \InvalidArgumentException('Legacy inc value must be a number.'),
        };
    }

    /**
     * @param array<string,mixed> $action
     * @return array<string,mixed>
     */
    private function legacySerializeOpAction(array $action): array
    {
        $type = $action['type'] ?? null;

        if ($type === 'make') {
            return ['action' => match ($action['objectType'] ?? null) {
                'map' => 'makeMap',
                'table' => 'makeTable',
                'list' => 'makeList',
                'text' => 'makeText',
                default => throw new \InvalidArgumentException('Unknown legacy make object type.'),
            }];
        }

        if ($type === 'delete') {
            return ['action' => 'del'];
        }

        if ($type === 'put') {
            if (! is_array($action['value'] ?? null)) {
                throw new \InvalidArgumentException('Legacy put action requires a scalar value descriptor.');
            }

            return ['action' => 'set'] + $this->legacySerializeScalarValue($action['value']);
        }

        if ($type === 'increment') {
            if (! array_key_exists('value', $action) || (! is_int($action['value']) && ! is_float($action['value']))) {
                throw new \InvalidArgumentException('Legacy increment action requires a numeric value.');
            }

            return ['action' => 'inc', 'value' => (int) $action['value']];
        }

        if ($type === 'markBegin') {
            if (! is_string($action['name'] ?? null) || ! is_array($action['value'] ?? null)) {
                throw new \InvalidArgumentException('Legacy markBegin action requires name and scalar value descriptors.');
            }

            return ['action' => 'markBegin', 'name' => $action['name'], 'expand' => (bool) ($action['expand'] ?? false)]
                + $this->legacySerializeScalarValue($action['value']);
        }

        if ($type === 'markEnd') {
            return ['action' => 'markEnd', 'expand' => (bool) ($action['expand'] ?? false)];
        }

        throw new \InvalidArgumentException('Unknown legacy action descriptor.');
    }

    /**
     * @param array<string,mixed> $scalar
     * @return array<string,mixed>
     */
    private function legacySerializeScalarValue(array $scalar): array
    {
        if (! array_key_exists('type', $scalar) || ! array_key_exists('value', $scalar)) {
            throw new \InvalidArgumentException('Legacy scalar descriptor requires type and value.');
        }

        return match ($scalar['type']) {
            'uint', 'int', 'float64', 'counter', 'timestamp' => [
                'datatype' => $scalar['type'],
                'value' => $scalar['type'] === 'float64' ? (float) $scalar['value'] : (int) $scalar['value'],
            ],
            'str' => ['value' => (string) $scalar['value']],
            'boolean' => ['value' => (bool) $scalar['value']],
            'null' => ['value' => null],
            default => throw new \InvalidArgumentException('Unknown legacy scalar descriptor type.'),
        };
    }

    private function legacyIntegerValue(mixed $value, string $expected): int
    {
        if (! is_int($value)) {
            throw new \InvalidArgumentException('invalid value: expected ' . $expected);
        }

        return $value;
    }

    private function legacyNumberValue(mixed $value, string $expected): float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new \InvalidArgumentException('invalid value: expected ' . $expected);
        }

        return (float) $value;
    }

    /**
     * @return array{type:string,counter:int,actor:int}
     */
    private function opSet2NormalizeObjectId(mixed $objectId): array
    {
        if ($objectId === '_root' || $objectId === 'root' || (is_array($objectId) && ($objectId['type'] ?? null) === 'root')) {
            return ['type' => 'root', 'counter' => 0, 'actor' => 0];
        }

        if (is_string($objectId) && preg_match('/^(0|[1-9][0-9]*)@(0|[1-9][0-9]*)$/', $objectId, $matches)) {
            $objectId = ['type' => 'op', 'counter' => (int) $matches[1], 'actor' => (int) $matches[2]];
        }

        if (is_array($objectId)) {
            $counter = $objectId['counter'] ?? null;
            $actor = $objectId['actor'] ?? null;
            if (is_int($counter) && $counter >= 0 && is_int($actor) && $actor >= 0) {
                return ['type' => 'op', 'counter' => $counter, 'actor' => $actor];
            }
        }

        throw new \InvalidArgumentException('op_set2 object ids must be root or non-negative actor/counter pairs.');
    }

    /**
     * @param array{type:string,counter:int,actor:int} $left
     * @param array{type:string,counter:int,actor:int} $right
     */
    private function opSet2CompareObjectIds(array $left, array $right): int
    {
        if ($left['type'] === 'root' || $right['type'] === 'root') {
            return ($left['type'] === 'root' ? 0 : 1) <=> ($right['type'] === 'root' ? 0 : 1);
        }

        return ($left['counter'] <=> $right['counter']) ?: ($left['actor'] <=> $right['actor']);
    }

    /**
     * @return array{counter:int,actor:int}
     */
    private function opSet2NormalizeOperationId(mixed $operationId): array
    {
        if (is_string($operationId) && preg_match('/^(0|[1-9][0-9]*)@(0|[1-9][0-9]*)$/', $operationId, $matches)) {
            $operationId = ['counter' => (int) $matches[1], 'actor' => (int) $matches[2]];
        }

        if (is_array($operationId)) {
            $counter = $operationId['counter'] ?? null;
            $actor = $operationId['actor'] ?? null;
            if (is_int($counter) && $counter >= 0 && is_int($actor) && $actor >= 0) {
                return ['counter' => $counter, 'actor' => $actor];
            }
        }

        throw new \InvalidArgumentException('op_set2 operation ids must contain non-negative actor/counter pairs.');
    }

    private function validateOpSet2CounterRange(int $start, int $end): void
    {
        if ($start < 0 || $end < $start) {
            throw new \OutOfBoundsException('op_set2 counter ranges must be non-negative and ascending.');
        }
    }

    public function getActorId(Document $document): string
    {
        return $document->actorId();
    }

    /**
     * @param list<string|int> $path
     */
    public function objectReference(Document $document, array $path): DocumentObjectReference
    {
        $path = array_values($path);
        $value = $document->toArray();
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                throw new \InvalidArgumentException('Cannot create a reference to a missing document object.');
            }

            $value = $value[$key];
        }

        if (! is_array($value)) {
            throw new \InvalidArgumentException('Cannot create a reference to a scalar document value.');
        }

        return new DocumentObjectReference($path);
    }

    public function set(Document $document, string $key, mixed $value): Document
    {
        $root = $document->toArray();
        if (array_key_exists($key, $root) && $root[$key] === $value && $document->conflictsFor($key) === null) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->set($key, $value);
        $next = $this->preserveFrozen($document, $next);
        $this->emitPatches($document, $next, $this->assignmentPatches($key, $value));

        return $next;
    }

    public function batchCreateObject(Document $document, string $key, mixed $value): Document
    {
        if (! is_array($value) && ! $value instanceof TextValue) {
            throw new \InvalidArgumentException('Batch object creation requires a map list or text value.');
        }

        return $this->set($document, $key, $value);
    }

    public function batchCreateObjectWithCommitOptions(Document $document, string $key, mixed $value, string $message, int $time): Document
    {
        if (! is_array($value) && ! $value instanceof TextValue) {
            throw new \InvalidArgumentException('Batch object creation requires a map list or text value.');
        }

        $next = $this->mutableClone($document);
        $next->set($key, $value, $message, $time);

        return $this->preserveFrozen($document, $next);
    }

    public function setWithMessage(Document $document, string $key, mixed $value, string $message): Document
    {
        $root = $document->toArray();
        if (array_key_exists($key, $root) && $root[$key] === $value && $document->conflictsFor($key) === null) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->set($key, $value, $message);

        return $this->preserveFrozen($document, $next);
    }

    public function setWithTime(Document $document, string $key, mixed $value, int $time): Document
    {
        $root = $document->toArray();
        if (array_key_exists($key, $root) && $root[$key] === $value && $document->conflictsFor($key) === null) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->set($key, $value, null, $time);

        return $this->preserveFrozen($document, $next);
    }

    public function setWithoutTime(Document $document, string $key, mixed $value): Document
    {
        return $this->setWithTime($document, $key, $value, 0);
    }

    /** @param array<string,mixed> $values */
    public function setMany(Document $document, array $values, ?string $message = null): Document
    {
        if ($values === []) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->setMany($values, $message);

        return $this->preserveFrozen($document, $next);
    }

    public function setWithPatchCallback(Document $document, string $key, mixed $value, callable $patchCallback): Document
    {
        return $this->withPatchCallback($patchCallback)->set($document, $key, $value);
    }

    public function changeNoop(Document $document): Document
    {
        return $document;
    }

    public function changeTransaction(Document $document, callable $change): Document
    {
        $working = $this->mutableClone($document);
        $result = $change($working, $this);

        return $this->preserveFrozen($document, $result instanceof Document ? $result : $working);
    }

    public function transaction(Document $document): Transaction
    {
        return new Transaction($document, $this);
    }

    /**
     * @param list<string> $heads
     */
    public function transactionAt(Document $document, array $heads): Transaction
    {
        $this->validateDiffHeads($document, $heads, 'transactionAt');

        return new Transaction($document, $this, array_values($heads));
    }

    public function change(mixed $document, callable $change): Document
    {
        if (! $document instanceof Document) {
            throw new \InvalidArgumentException('Automerge.change argument must be the document root.');
        }

        if ($this->outdatedChangeBases->contains($document)) {
            throw new \RuntimeException('Attempting to change an outdated document.');
        }

        if ($this->activeChangeDraft !== null) {
            if ($document === $this->activeChangeBase) {
                throw new \RuntimeException('Attempting to change an outdated document.');
            }

            throw new \RuntimeException('Calls to Automerge.change cannot be nested.');
        }

        $working = $this->mutableClone($document);
        $baseChangeCount = count($document->getAllChanges());
        $this->activeChangeBase = $document;
        $this->activeChangeDraft = $working;
        try {
            $result = $change($working, $this);
            $next = $this->preserveFrozen($document, $result instanceof Document ? $result : $working);
            if ($next->heads() === $document->heads()) {
                return $document;
            }
            $this->outdatedChangeBases->attach($document);
            $newChanges = array_slice($next->getAllChanges(), $baseChangeCount);
            $patches = $this->patchesForChanges($next, $newChanges);
            if ($patches === []) {
                $patches = $this->patchesBetweenDocuments($document, $next);
            }
            $this->emitPatches($document, $next, $patches);
            $this->emitDocumentPatchCallback($document, $next, 'change', $patches);

            return $next;
        } finally {
            $this->activeChangeBase = null;
            $this->activeChangeDraft = null;
        }
    }

    public function delete(Document $document, string $key): Document
    {
        if (! array_key_exists($key, $document->toArray())) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->delete($key);

        return $this->preserveFrozen($document, $next);
    }

    /**
     * @return array{0:Document,1:bool}
     */
    public function deleteWithResult(Document $document, string $key): array
    {
        return [$this->delete($document, $key), true];
    }

    /**
     * @param list<string|int> $path
     */
    public function deleteNested(Document $document, array $path): Document
    {
        $next = $this->mutableClone($document);
        $next->deleteNested($path);
        $next = $this->preserveFrozen($document, $next);
        if ($next->toArray() !== $document->toArray()) {
            $this->emitPatches($document, $next, [['action' => 'del', 'path' => array_values($path)]]);
        }

        return $next;
    }

    public function setListElement(Document $document, string $key, int $index, mixed $value): Document
    {
        $this->assertListOperationTarget($document, $key);
        $root = $document->toArray();
        $list = is_array($root[$key] ?? null) ? $root[$key] : [];
        if ($index < 0 || $index > count($list)) {
            throw new \OutOfBoundsException('List assignment index is out of bounds.');
        }

        if (array_key_exists($index, $list) && $list[$index] === $value) {
            if ($document->listElementConflictsFor($key, $index) !== null) {
                $next = $this->mutableClone($document);
                $next->resolveRootConflictListElement($key, $index, $value);

                return $this->preserveFrozen($document, $next);
            }

            return $document;
        }

        $list[$index] = $value;
        ksort($list);

        return $this->set($document, $key, array_values($list));
    }

    public function setListKey(Document $document, string $key, string|int $index, mixed $value): Document
    {
        if (is_int($index)) {
            return $this->setListElement($document, $key, $index, $value);
        }

        if ($index !== '' && ctype_digit($index)) {
            return $this->setListElement($document, $key, (int) $index, $value);
        }

        throw new \InvalidArgumentException('list index must be a number');
    }

    /**
     * @param list<mixed> $values
     */
    public function insertListElements(Document $document, string $key, int $index, array $values): Document
    {
        $this->assertListOperationTarget($document, $key);
        $this->assertListInputHasNoUndefined($key, $values);

        if ($values === []) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->insertListValues($key, $index, $values);
        $next = $this->preserveFrozen($document, $next);
        $this->emitPatches($document, $next, [['action' => 'insert', 'path' => [$key, $index], 'values' => array_values($values)]]);

        return $next;
    }

    public function setListElementStrict(Document $document, string $key, int $index, mixed $value): Document
    {
        $root = $document->toArray();
        $list = is_array($root[$key] ?? null) && array_is_list($root[$key]) ? $root[$key] : [];
        if ($index < 0 || $index >= count($list)) {
            throw new \OutOfBoundsException('List assignment index is out of bounds.');
        }

        return $this->setListElement($document, $key, $index, $value);
    }

    /**
     * @param list<mixed> $values
     */
    public function insertListElementsStrict(Document $document, string $key, int $index, array $values): Document
    {
        $root = $document->toArray();
        $list = is_array($root[$key] ?? null) && array_is_list($root[$key]) ? $root[$key] : [];
        if ($index < 0 || $index > count($list)) {
            throw new \OutOfBoundsException('List insertion index is out of bounds.');
        }

        return $this->insertListElements($document, $key, $index, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function insertListElementsWithMessage(Document $document, string $key, int $index, array $values, string $message): Document
    {
        $this->assertListOperationTarget($document, $key);
        $this->assertListInputHasNoUndefined($key, $values);

        $root = $document->toArray();
        $list = is_array($root[$key] ?? null) ? array_values($root[$key]) : [];
        array_splice($list, $index, 0, $values);

        return $this->setWithMessage($document, $key, array_values($list), $message);
    }

    public function deleteListElements(Document $document, string $key, int $index, int $length = 1): Document
    {
        $this->assertListOperationTarget($document, $key);

        return $this->applyPatches(
            $document,
            [['action' => 'del', 'path' => [$key, $index], 'length' => $length]]
        );
    }

    /**
     * @param list<mixed> $values
     */
    public function spliceList(Document $document, string $key, int $index, int $deleteCount, array $values = []): Document
    {
        [$next] = $this->spliceListWithDeleted($document, $key, $index, $deleteCount, $values);

        return $next;
    }

    /**
     * @param list<mixed> $values
     * @return array{0:Document,1:list<mixed>}
     */
    public function spliceListWithDeleted(Document $document, string $key, int $index, ?int $deleteCount = null, array $values = []): array
    {
        $this->assertListOperationTarget($document, $key);
        $list = $this->listValues($document, $key);
        $index = max(0, min($index, count($list)));
        $deleteCount ??= count($list) - $index;
        $deleteCount = max(0, min($deleteCount, count($list) - $index));
        $deleted = array_values(array_slice($list, $index, $deleteCount));

        $next = $document;
        if ($deleteCount > 0) {
            $next = $this->deleteListElements($next, $key, $index, $deleteCount);
        }

        if ($values !== []) {
            $next = $this->insertListElements($next, $key, $index, $values);
        }

        return [$next, $deleted];
    }

    /**
     * @param list<mixed> $values
     */
    public function pushList(Document $document, string $key, array $values): Document
    {
        $root = $document->toArray();
        $list = is_array($root[$key] ?? null) ? $root[$key] : [];

        return $this->insertListElements($document, $key, count($list), $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function unshiftList(Document $document, string $key, array $values): Document
    {
        return $this->insertListElements($document, $key, 0, $values);
    }

    public function shiftList(Document $document, string $key): Document
    {
        return $this->deleteListElements($document, $key, 0);
    }

    public function listIndexOf(Document $document, string $key, mixed $needle): int
    {
        $index = array_search($needle, $this->listValues($document, $key), true);

        return $index === false ? -1 : (int) $index;
    }

    /**
     * @return list<array{0:int,1:mixed}>
     */
    public function listEntries(Document $document, string $key): array
    {
        $entries = [];
        foreach ($this->listValues($document, $key) as $index => $value) {
            $entries[] = [$index, $value];
        }

        return $entries;
    }

    /**
     * @return list<int>
     */
    public function listKeys(Document $document, string $key): array
    {
        return array_keys($this->listValues($document, $key));
    }

    /**
     * @return list<mixed>
     */
    public function listValues(Document $document, string $key): array
    {
        $root = $document->toArray();
        $list = $root[$key] ?? null;

        return is_array($list) && array_is_list($list) ? array_values($list) : [];
    }

    /**
     * @return list<mixed>
     */
    public function listRange(Document $document, string $key, ?int $start = null, ?int $end = null, bool $endInclusive = false): array
    {
        $values = $this->listValues($document, $key);
        $count = count($values);
        $start = $start === null ? 0 : max(0, min($start, $count));
        $end = $end === null ? $count : max(0, min($end + ($endInclusive ? 1 : 0), $count));
        if ($end < $start) {
            return [];
        }

        return array_values(array_slice($values, $start, $end - $start));
    }

    /**
     * @return list<array{value:mixed,conflict:bool}>
     */
    public function listRangeEntries(Document $document, string $key, ?int $start = null, ?int $end = null, bool $endInclusive = false): array
    {
        $values = $this->listValues($document, $key);
        $count = count($values);
        $start = $start === null ? 0 : max(0, min($start, $count));
        $end = $end === null ? $count : max(0, min($end + ($endInclusive ? 1 : 0), $count));
        if ($end < $start) {
            return [];
        }

        $entries = [];
        for ($index = $start; $index < $end; ++$index) {
            $conflicts = $document->listElementConflictsFor($key, $index);
            $entries[] = [
                'value' => $values[$index],
                'conflict' => $conflicts !== null && $this->hasDistinctValues(array_values($conflicts)),
            ];
        }

        return $entries;
    }

    /**
     * @param list<mixed> $values
     */
    private function hasDistinctValues(array $values): bool
    {
        if (count($values) < 2) {
            return false;
        }

        $seen = [];
        foreach ($values as $value) {
            $seen[serialize($value)] = true;
            if (count($seen) > 1) {
                return true;
            }
        }

        return false;
    }

    private function assertListOperationTarget(Document $document, string $key): void
    {
        $root = $document->toArray();
        if (! array_key_exists($key, $root)) {
            return;
        }

        if (! is_array($root[$key]) || ! array_is_list($root[$key])) {
            throw new \InvalidArgumentException('Invalid list operation on non-list value.');
        }
    }

    public function listAt(Document $document, string $key, int $index): mixed
    {
        $values = $this->listValues($document, $key);
        if ($index < 0) {
            $index = count($values) + $index;
        }

        return $values[$index] ?? null;
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    public function listConcat(Document $document, string $key, array $values): array
    {
        return array_values(array_merge($this->listValues($document, $key), $values));
    }

    public function listJoin(Document $document, string $key, string $separator = ','): string
    {
        return implode($separator, array_map(
            fn (mixed $value): string => $this->stringifyListValue($value),
            $this->listValues($document, $key)
        ));
    }

    /**
     * @return list<mixed>
     */
    public function listMap(Document $document, string $key, callable $callback): array
    {
        $mapped = [];
        foreach ($this->listValues($document, $key) as $index => $value) {
            $mapped[] = $callback($value, $index);
        }

        return $mapped;
    }

    public function listForEach(Document $document, string $key, callable $callback): void
    {
        foreach ($this->listValues($document, $key) as $index => $value) {
            $callback($value, $index);
        }
    }

    public function listEvery(Document $document, string $key, callable $callback): bool
    {
        foreach ($this->listValues($document, $key) as $index => $value) {
            if (! $callback($value, $index)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<mixed>
     */
    public function listFilter(Document $document, string $key, callable $callback): array
    {
        $filtered = [];
        foreach ($this->listValues($document, $key) as $index => $value) {
            if ($callback($value, $index)) {
                $filtered[] = $value;
            }
        }

        return $filtered;
    }

    public function listFind(Document $document, string $key, callable $callback): mixed
    {
        foreach ($this->listValues($document, $key) as $index => $value) {
            if ($callback($value, $index)) {
                return $value;
            }
        }

        return null;
    }

    public function listFindIndex(Document $document, string $key, callable $callback): int
    {
        foreach ($this->listValues($document, $key) as $index => $value) {
            if ($callback($value, $index)) {
                return $index;
            }
        }

        return -1;
    }

    public function listIncludes(Document $document, string $key, mixed $needle): bool
    {
        return $this->listIndexOf($document, $key, $needle) !== -1;
    }

    public function listSome(Document $document, string $key, callable $callback): bool
    {
        return $this->listFindIndex($document, $key, $callback) !== -1;
    }

    public function listReduce(Document $document, string $key, callable $callback, mixed $initial): mixed
    {
        $accumulator = $initial;
        foreach ($this->listValues($document, $key) as $index => $value) {
            $accumulator = $callback($accumulator, $value, $index);
        }

        return $accumulator;
    }

    public function listReduceRight(Document $document, string $key, callable $callback, mixed $initial): mixed
    {
        $accumulator = $initial;
        $values = $this->listValues($document, $key);
        for ($index = count($values) - 1; $index >= 0; --$index) {
            $accumulator = $callback($accumulator, $values[$index], $index);
        }

        return $accumulator;
    }

    public function listLastIndexOf(Document $document, string $key, mixed $needle, ?int $fromIndex = null): int
    {
        $values = $this->listValues($document, $key);
        $index = $fromIndex === null ? count($values) - 1 : min($fromIndex, count($values) - 1);
        for (; $index >= 0; --$index) {
            if ($values[$index] === $needle) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * @return array{0:Document,1:list<mixed>}
     */
    public function fillListWithValues(Document $document, string $key, mixed $value, int $start = 0, ?int $end = null): array
    {
        $values = $this->listValues($document, $key);
        $start = max(0, min($start, count($values)));
        $end = $end === null ? count($values) : max($start, min($end, count($values)));
        for ($index = $start; $index < $end; ++$index) {
            $values[$index] = $value;
        }

        $next = $this->set($document, $key, $values);

        return [$next, $values];
    }

    public function textIndexOf(Document $document, string $key, string $needle): int
    {
        $root = $document->toArray();
        $text = $root[$key] ?? null;
        if (! is_string($text)) {
            return -1;
        }

        $index = mb_strpos($text, $needle);

        return $index === false ? -1 : $index;
    }

    /**
     * @param list<string|int> $path
     */
    public function incrementCounter(Document $document, array $path, int $amount = 1): Document
    {
        $operationId = $document->actorId() . ':' . (count($document->getAllChanges()) + 1) . ':' . implode('/', array_map(
            static fn (string|int $key): string => (string) $key,
            $path
        ));

        if (count($path) === 1 && (is_string($path[0]) || is_int($path[0]))) {
            $key = (string) $path[0];
            $conflicts = $document->conflictsFor($key);
            if (is_array($conflicts)) {
                foreach ($conflicts as $conflictValue) {
                    if (! $conflictValue instanceof Counter) {
                        continue;
                    }

                    $next = $this->mutableClone($document);
                    if ($next->incrementRootConflictCounters($key, $amount, $operationId)) {
                        $next = $this->preserveFrozen($document, $next);
                        $this->emitPatches($document, $next, [['action' => 'inc', 'path' => [$key], 'value' => $amount]]);

                        return $next;
                    }
                }
            }
        }

        if (count($path) === 2 && (is_string($path[0]) || is_int($path[0])) && is_int($path[1])) {
            $key = (string) $path[0];
            $index = $path[1];
            $conflicts = $document->listElementConflictsFor($key, $index);
            if (is_array($conflicts)) {
                foreach ($conflicts as $conflictValue) {
                    if (! $conflictValue instanceof Counter) {
                        continue;
                    }

                    $next = $this->mutableClone($document);
                    if ($next->incrementRootConflictListElementCounters($key, $index, $amount, $operationId)) {
                        $next = $this->preserveFrozen($document, $next);
                        $this->emitPatches($document, $next, [['action' => 'inc', 'path' => [$key, $index], 'value' => $amount]]);

                        return $next;
                    }
                }
            }
        }

        $current = $this->readPath($document, $path);
        if (! $current instanceof Counter) {
            throw new \InvalidArgumentException('Cannot increment a non-counter value.');
        }

        return $this->writePath($document, $path, $current->incremented($amount, $operationId));
    }

    /**
     * @param list<string|int> $path
     */
    public function setNested(Document $document, array $path, mixed $value): Document
    {
        $path = array_values($path);
        $this->assertPathContainerCompatibility($document, $path);
        $next = $this->mutableClone($document);
        $next->setNested($path, $value);

        return $this->preserveFrozen($document, $next);
    }

    public function setRootConflictMapValue(Document $document, string $key, string $nestedKey, mixed $value): Document
    {
        $next = $this->mutableClone($document);
        $next->setRootConflictMapValue($key, $nestedKey, $value);

        return $this->preserveFrozen($document, $next);
    }

    public function setRootConflictListElementMapValue(Document $document, string $key, int $index, string $nestedKey, mixed $value): Document
    {
        $next = $this->mutableClone($document);
        $next->setRootConflictListElementMapValue($key, $index, $nestedKey, $value);

        return $this->preserveFrozen($document, $next);
    }

    public function splice(Document $document, string $key, int $index, int $deleteCount, string $insert = ''): Document
    {
        $this->assertTextOperationTarget($document, [$key]);
        $next = $this->mutableClone($document);
        $next->spliceText($key, $index, $deleteCount, $insert);

        return $this->preserveFrozen($document, $next);
    }

    /**
     * @param list<string|int> $path
     */
    public function spliceInChange(Document $document, array $path, int|string $index, int $deleteCount, string $insert = ''): Document
    {
        if ($this->activeChangeDraft !== $document) {
            throw new \RuntimeException('object cannot be modified outside of a change block');
        }

        [$index, $deleteCount] = $this->resolveTextSpliceRange($document, $path, $index, $deleteCount);
        if (count($path) === 1 && (is_string($path[0]) || is_int($path[0]))) {
            $document->spliceText((string) $path[0], $index, $deleteCount, $insert);

            return $document;
        }

        return $this->spliceAtPath($document, $path, $index, $deleteCount, $insert);
    }

    public function updateText(Document $document, string $key, string $newText): Document
    {
        $next = $this->mutableClone($document);
        $next->updateText($key, $newText);

        return $this->preserveFrozen($document, $next);
    }

    /**
     * @param list<string|int> $path
     */
    public function textLength(Document $document, array $path, string $encoding = 'grapheme'): int
    {
        $value = $this->readPath($document, array_values($path));
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Text length requires a text value.');
        }

        return match ($encoding) {
            'UnicodeCodePoint', 'codepoint', 'code_point', 'unicode-code-point' => mb_strlen($value, 'UTF-8'),
            'Utf8CodeUnit', 'utf8', 'utf8-code-unit' => strlen($value),
            'Utf16CodeUnit', 'utf16', 'utf16-code-unit' => $this->utf16Length($value),
            'GraphemeCluster', 'grapheme', 'grapheme-cluster' => count($this->splitCharacters($value)),
            default => throw new \InvalidArgumentException('Unsupported text length encoding.'),
        };
    }

    public function textDiffCommonPrefixLen(string $old, int $oldStart, int $oldEnd, string $new, int $newStart, int $newEnd): int
    {
        [$oldStart, $oldEnd] = $this->normalizeByteRange($old, $oldStart, $oldEnd);
        [$newStart, $newEnd] = $this->normalizeByteRange($new, $newStart, $newEnd);
        if ($oldStart >= $oldEnd || $newStart >= $newEnd) {
            return 0;
        }

        $length = 0;
        $oldIndex = $oldStart;
        $newIndex = $newStart;
        while ($oldIndex < $oldEnd && $newIndex < $newEnd && $old[$oldIndex] === $new[$newIndex]) {
            ++$length;
            ++$oldIndex;
            ++$newIndex;
        }

        return $length;
    }

    public function textDiffCommonSuffixLen(string $old, int $oldStart, int $oldEnd, string $new, int $newStart, int $newEnd): int
    {
        [$oldStart, $oldEnd] = $this->normalizeByteRange($old, $oldStart, $oldEnd);
        [$newStart, $newEnd] = $this->normalizeByteRange($new, $newStart, $newEnd);
        if ($oldStart >= $oldEnd || $newStart >= $newEnd) {
            return 0;
        }

        $length = 0;
        $oldIndex = $oldEnd - 1;
        $newIndex = $newEnd - 1;
        while ($oldIndex >= $oldStart && $newIndex >= $newStart && $old[$oldIndex] === $new[$newIndex]) {
            ++$length;
            --$oldIndex;
            --$newIndex;
        }

        return $length;
    }

    /**
     * @return list<int>
     */
    public function clock(int $actorCount): array
    {
        if ($actorCount < 0) {
            throw new \InvalidArgumentException('Clock actor count cannot be negative.');
        }

        return array_fill(0, $actorCount, 0);
    }

    /**
     * @param list<int> $clock
     * @return list<int>
     */
    public function clockInclude(array $clock, int $actorIndex, int $counter): array
    {
        $clock = $this->normalizeClock($clock);
        if (! array_key_exists($actorIndex, $clock)) {
            throw new \OutOfBoundsException('Clock actor index is out of range.');
        }

        if ($counter < 0) {
            throw new \InvalidArgumentException('Clock counter cannot be negative.');
        }

        if ($counter > $clock[$actorIndex]) {
            $clock[$actorIndex] = $counter;
        }

        return array_values($clock);
    }

    /**
     * @param list<int> $clock
     */
    public function clockCovers(array $clock, int $counter, int $actorIndex): bool
    {
        $clock = $this->normalizeClock($clock);
        if (! array_key_exists($actorIndex, $clock) || $counter < 0) {
            return false;
        }

        return $clock[$actorIndex] >= $counter;
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     */
    public function clockCompare(array $left, array $right): ?string
    {
        $left = $this->normalizeClock($left);
        $right = $this->normalizeClock($right);
        $maxLength = max(count($left), count($right));
        $leftGreater = false;
        $rightGreater = false;

        for ($index = 0; $index < $maxLength; ++$index) {
            $leftCounter = $left[$index] ?? 0;
            $rightCounter = $right[$index] ?? 0;
            if ($leftCounter > $rightCounter) {
                $leftGreater = true;
            } elseif ($rightCounter > $leftCounter) {
                $rightGreater = true;
            }
        }

        if (! $leftGreater && ! $rightGreater) {
            return 'equal';
        }

        if ($leftGreater && ! $rightGreater) {
            return 'greater';
        }

        if ($rightGreater && ! $leftGreater) {
            return 'less';
        }

        return null;
    }

    /**
     * @param list<array{hash:string,actor:int,seq?:int|null,deps?:list<string>}> $changes
     * @param list<string> $heads
     * @return list<int|null>
     */
    public function changeGraphSeqClockForHeads(array $changes, array $heads): array
    {
        $graph = $this->normalizeChangeGraph($changes);
        $clock = array_fill(0, $graph['actorCount'], null);
        foreach ($this->changeGraphAncestors($graph['nodes'], $heads) as $hash) {
            $node = $graph['nodes'][$hash];
            $seq = $node['seq'];
            if ($seq === null) {
                continue;
            }

            $actor = $node['actor'];
            if ($clock[$actor] === null || $clock[$actor] < $seq) {
                $clock[$actor] = $seq;
            }
        }

        return $clock;
    }

    /**
     * @param list<array{hash:string,actor:int,seq?:int|null,deps?:list<string>}> $changes
     * @param list<string> $candidateHashes
     * @param list<string> $heads
     * @return list<string>
     */
    public function changeGraphRemoveAncestors(array $changes, array $candidateHashes, array $heads): array
    {
        $graph = $this->normalizeChangeGraph($changes);
        $ancestorHashes = array_fill_keys($this->changeGraphAncestors($graph['nodes'], $heads), true);
        $remaining = [];
        foreach ($candidateHashes as $hash) {
            if (! is_string($hash) || $hash === '') {
                throw new \InvalidArgumentException('Change graph candidate hashes must be non-empty strings.');
            }

            if (! isset($ancestorHashes[$hash])) {
                $remaining[$hash] = $hash;
            }
        }

        $remaining = array_values($remaining);
        sort($remaining, SORT_STRING);

        return $remaining;
    }

    public function uleb128Size(int|string $value): int
    {
        $decimal = $this->normalizeUnsignedDecimal($value, '18446744073709551615');
        if ($decimal === '0') {
            return 1;
        }

        return intdiv($this->decimalBitLength($decimal) + 6, 7);
    }

    public function leb128Size(int|string $value): int
    {
        $decimal = $this->normalizeSignedDecimal($value);
        if (str_starts_with($decimal, '-')) {
            $magnitude = $this->decrementDecimalString(substr($decimal, 1));
        } else {
            $magnitude = $decimal;
        }

        $bits = 1 + ($magnitude === '0' ? 0 : $this->decimalBitLength($magnitude));

        return intdiv($bits + 6, 7);
    }

    /**
     * @param list<int> $bytes
     * @return array{bytes:list<int>,position:int,original:list<int>}
     */
    public function storageParseInput(array $bytes): array
    {
        $this->columnarByteListToString($bytes);
        $bytes = array_values($bytes);

        return ['bytes' => $bytes, 'position' => 0, 'original' => $bytes];
    }

    /**
     * @param array{bytes:list<int>,position:int,original:list<int>} $input
     * @return array{0:array{bytes:list<int>,position:int,original:list<int>},1:int}
     */
    public function storageInputTakeOne(array $input): array
    {
        $bytes = array_values($input['bytes'] ?? []);
        if ($bytes === []) {
            throw new \InvalidArgumentException('Parse input is incomplete.');
        }

        $position = (int) ($input['position'] ?? 0);
        $remaining = array_values(array_slice($bytes, 1));

        return [
            [
                'bytes' => $remaining,
                'position' => $position + 1,
                'original' => array_values($input['original'] ?? $bytes),
            ],
            $bytes[0],
        ];
    }

    /**
     * @param array{bytes:list<int>,position:int,original:list<int>} $input
     * @return array{0:array{bytes:list<int>,position:int,original:list<int>},1:array{range:array{start:int,end:int},value:int}}
     */
    public function storageInputRangeOfTakeOne(array $input): array
    {
        $start = (int) ($input['position'] ?? 0);
        [$newInput, $value] = $this->storageInputTakeOne($input);

        return [
            $newInput,
            [
                'range' => [
                    'start' => $start,
                    'end' => (int) $newInput['position'],
                ],
                'value' => $value,
            ],
        ];
    }

    /**
     * @param array{bytes:list<int>,position:int,original:list<int>} $input
     * @return array{first:array{bytes:list<int>,position:int,original:list<int>},remaining:array{bytes:list<int>,position:int,original:list<int>}}
     */
    public function storageInputSplit(array $input, int $length): array
    {
        $bytes = array_values($input['bytes'] ?? []);
        $position = (int) ($input['position'] ?? 0);
        $original = array_values($input['original'] ?? $bytes);
        $length = max(0, min($length, count($bytes)));

        return [
            'first' => [
                'bytes' => array_values(array_slice($bytes, 0, $length)),
                'position' => $position,
                'original' => array_values(array_slice($original, 0, $position + $length)),
            ],
            'remaining' => [
                'bytes' => array_values(array_slice($bytes, $length)),
                'position' => $position + $length,
                'original' => array_values(array_slice($original, $position + $length)),
            ],
        ];
    }

    /**
     * @param array{bytes:list<int>,position:int,original:list<int>} $input
     * @return list<int>
     */
    public function storageInputRemainingBytes(array $input): array
    {
        return array_values($input['bytes'] ?? []);
    }

    /**
     * @param array{bytes:list<int>,position:int,original:list<int>} $input
     * @return list<int>
     */
    public function storageInputBytes(array $input): array
    {
        return array_values($input['original'] ?? []);
    }

    /**
     * @return array{type:string,value:string}
     */
    public function storageParseApplicationError(string $value): array
    {
        return ['type' => 'error', 'value' => $value];
    }

    /**
     * @return array{type:string,needed:int}
     */
    public function storageParseIncomplete(int $needed): array
    {
        if ($needed < 1) {
            throw new \InvalidArgumentException('Incomplete parser errors must request at least one byte.');
        }

        return ['type' => 'incomplete', 'needed' => $needed];
    }

    /**
     * @param array<string,mixed> $error
     * @return array<string,mixed>
     */
    public function storageParseErrorLift(array $error, string $variant): array
    {
        if (($error['type'] ?? null) !== 'error') {
            return $error;
        }

        return [
            'type' => 'error',
            'value' => [
                'variant' => $variant,
                'source' => $error['value'] ?? null,
            ],
        ];
    }

    /**
     * @param list<int> $bytes
     * @return array{value:string,offset:int}
     */
    public function storageParseLeb128U64(array $bytes): array
    {
        [$value, $offset] = $this->parseStorageUnsignedLeb128($this->columnarByteListToString($bytes));

        return ['value' => $value, 'offset' => $offset];
    }

    /**
     * @param list<int> $bytes
     * @return array{value:string,offset:int}
     */
    public function storageParseLeb128U64Exact(array $bytes): array
    {
        $result = $this->storageParseLeb128U64($bytes);
        if ($result['offset'] !== count($bytes)) {
            throw new \InvalidArgumentException('LEB128 value metadata has trailing bytes.');
        }

        return $result;
    }

    /**
     * @param list<int> $bytes
     * @return array{value:string,offset:int}
     */
    public function storageParseLeb128U32(array $bytes): array
    {
        [$value, $offset] = $this->parseStorageUnsignedLeb128($this->columnarByteListToString($bytes));
        if ($this->compareDecimalStrings($value, '4294967295') > 0) {
            throw new \InvalidArgumentException('LEB128 value is too large for u32.');
        }

        return ['value' => $value, 'offset' => $offset];
    }

    /**
     * @param list<int> $bytes
     * @return array{value:string,offset:int}
     */
    public function storageParseLeb128I64(array $bytes): array
    {
        [$value, $offset] = $this->parseStorageSignedLeb128($this->columnarByteListToString($bytes));

        return ['value' => $value, 'offset' => $offset];
    }

    /**
     * @param list<int> $bytes
     * @return array<string,mixed>
     */
    public function storageChangeFromBytes(array $bytes): array
    {
        $raw = $this->columnarByteListToString($bytes);
        if (strlen($raw) < 10) {
            throw new \InvalidArgumentException('Automerge change chunk is too short.');
        }

        if (substr($raw, 0, 4) !== "\x85\x6f\x4a\x83") {
            throw new \InvalidArgumentException('Invalid Automerge storage magic bytes.');
        }

        $offset = 4;
        $checksum = substr($raw, $offset, 4);
        $offset += 4;
        $chunkType = ord($raw[$offset]);
        ++$offset;
        if ($chunkType === 2) {
            return $this->storageChangeFromBytes($this->storageDecompressChangeBytes($bytes));
        }

        if ($chunkType !== 1) {
            throw new \InvalidArgumentException('Automerge storage chunk is not a change chunk.');
        }

        $lengthStart = $offset;
        $dataLength = $this->decodeUnsignedLeb128Int($raw, $offset);
        $headerLength = $offset;
        if ($offset + $dataLength !== strlen($raw)) {
            throw new \InvalidArgumentException('Automerge change chunk length does not match payload bytes.');
        }

        $data = substr($raw, $offset, $dataLength);
        $hashPayload = chr($chunkType) . substr($raw, $lengthStart, $headerLength - $lengthStart) . $data;
        $hash = hash('sha256', $hashPayload, true);
        if (substr($hash, 0, 4) !== $checksum) {
            throw new \InvalidArgumentException('Automerge change chunk checksum mismatch.');
        }

        $bodyOffset = 0;
        $deps = $this->storageChangeReadHashList($data, $bodyOffset);
        $actor = $this->storageChangeReadActorId($data, $bodyOffset);
        $seq = $this->decodeUnsignedLeb128Int($data, $bodyOffset);
        $startOp = $this->decodeUnsignedLeb128Int($data, $bodyOffset);
        if ($startOp < 1) {
            throw new \InvalidArgumentException('Automerge change startOp must be non-zero.');
        }

        $time = $this->decodeSignedLeb128Int($data, $bodyOffset);
        $messageLength = $this->decodeUnsignedLeb128Int($data, $bodyOffset);
        if ($bodyOffset + $messageLength > strlen($data)) {
            throw new \InvalidArgumentException('Automerge change message is truncated.');
        }

        $message = substr($data, $bodyOffset, $messageLength);
        $bodyOffset += $messageLength;
        $otherActors = $this->storageChangeReadActorList($data, $bodyOffset);
        $rawColumns = $this->storageChangeReadRawColumns($data, $bodyOffset);
        $opsDataLength = array_sum(array_map(
            static fn (array $column): int => (int) $column['length'],
            $rawColumns
        ));
        if ($bodyOffset + $opsDataLength > strlen($data)) {
            throw new \InvalidArgumentException('Automerge change operation column data is truncated.');
        }

        $opsData = substr($data, $bodyOffset, $opsDataLength);
        $bodyOffset += $opsDataLength;
        $extraBytes = substr($data, $bodyOffset);

        return [
            'rawBytes' => $this->columnarBytesToList($raw),
            'chunkType' => $chunkType,
            'dataLength' => $dataLength,
            'checksum' => bin2hex($checksum),
            'hash' => bin2hex($hash),
            'deps' => $deps,
            'actor' => $actor,
            'seq' => $seq,
            'startOp' => $startOp,
            'time' => $time,
            'message' => $message === '' ? null : $message,
            'otherActors' => $otherActors,
            'rawColumns' => $rawColumns,
            'opsData' => $this->columnarBytesToList($opsData),
            'extraBytes' => $this->columnarBytesToList($extraBytes),
        ];
    }

    /**
     * @param list<int> $bytes
     */
    public function storageDocumentFromBytes(array $bytes, ?string $actorId = null): Document
    {
        return $this->loadStorageDocument($this->columnarByteListToString($bytes), $actorId);
    }

    public function loadStorageDocument(string $payload, ?string $actorId = null): Document
    {
        $document = $this->init($actorId);
        $objectPaths = [];
        foreach ($this->storageChangesFromPayload($payload) as $change) {
            [$document, $objectPaths] = $this->storageApplyLoadedChange($document, $objectPaths, $change);
        }

        return $document;
    }

    /**
     * @param list<int> $bytes
     * @return list<int>
     */
    public function storageCompressChangeBytes(array $bytes): array
    {
        $change = $this->storageChangeFromBytes($bytes);
        $raw = $this->columnarByteListToString($change['rawBytes']);
        $body = substr($raw, $this->storageChunkBodyOffset($raw));
        $compressed = gzdeflate($body);
        if ($compressed === false) {
            throw new \RuntimeException('Unable to deflate Automerge change body.');
        }

        $checksum = hex2bin((string) $change['checksum']);
        if (! is_string($checksum)) {
            throw new \InvalidArgumentException('Automerge change checksum is not valid hex.');
        }

        return $this->columnarBytesToList(
            "\x85\x6f\x4a\x83"
            . $checksum
            . chr(2)
            . $this->encodeUnsignedLeb128Int(strlen($compressed))
            . $compressed
        );
    }

    /**
     * @param list<int> $bytes
     * @return list<int>
     */
    public function storageDecompressChangeBytes(array $bytes): array
    {
        $raw = $this->columnarByteListToString($bytes);
        if (strlen($raw) < 10) {
            throw new \InvalidArgumentException('Automerge compressed change chunk is too short.');
        }

        if (substr($raw, 0, 4) !== "\x85\x6f\x4a\x83") {
            throw new \InvalidArgumentException('Invalid Automerge storage magic bytes.');
        }

        $offset = 4;
        $checksum = substr($raw, $offset, 4);
        $offset += 4;
        $chunkType = ord($raw[$offset]);
        ++$offset;
        if ($chunkType !== 2) {
            throw new \InvalidArgumentException('Automerge storage chunk is not a compressed change chunk.');
        }

        $dataLength = $this->decodeUnsignedLeb128Int($raw, $offset);
        if ($offset + $dataLength !== strlen($raw)) {
            throw new \InvalidArgumentException('Automerge compressed change chunk length does not match payload bytes.');
        }

        $inflated = gzinflate(substr($raw, $offset, $dataLength));
        if ($inflated === false) {
            throw new \InvalidArgumentException('Unable to inflate Automerge compressed change chunk.');
        }

        $uncompressed = "\x85\x6f\x4a\x83"
            . $checksum
            . chr(1)
            . $this->encodeUnsignedLeb128Int(strlen($inflated))
            . $inflated;

        $this->storageChangeFromBytes($this->columnarBytesToList($uncompressed));

        return $this->columnarBytesToList($uncompressed);
    }

    /**
     * @param array<string,mixed> $change
     * @return array<string,mixed>
     */
    public function storageExpandedChangeFromChange(array $change): array
    {
        $rawBytes = $change['rawBytes'] ?? null;
        if (! is_array($rawBytes)) {
            throw new \InvalidArgumentException('Expanded change conversion requires raw change bytes.');
        }

        return [
            'deps' => $this->normalizeHeads($change['deps'] ?? []),
            'actor' => (string) ($change['actor'] ?? ''),
            'seq' => (int) ($change['seq'] ?? 0),
            'startOp' => (int) ($change['startOp'] ?? 0),
            'time' => (int) ($change['time'] ?? 0),
            'message' => $change['message'] ?? null,
            'otherActors' => is_array($change['otherActors'] ?? null) ? array_values($change['otherActors']) : [],
            'rawColumns' => is_array($change['rawColumns'] ?? null) ? array_values($change['rawColumns']) : [],
            'opsData' => is_array($change['opsData'] ?? null) ? array_values($change['opsData']) : [],
            'extraBytes' => is_array($change['extraBytes'] ?? null) ? array_values($change['extraBytes']) : [],
            'rawBytes' => array_values($rawBytes),
        ];
    }

    /**
     * @param array<string,mixed> $expanded
     * @return array<string,mixed>
     */
    public function storageChangeFromExpandedChange(array $expanded): array
    {
        $rawBytes = $expanded['rawBytes'] ?? null;
        if (! is_array($rawBytes)) {
            throw new \InvalidArgumentException('Expanded change must include raw bytes for deterministic native round-trip.');
        }

        $change = $this->storageChangeFromBytes(array_values($rawBytes));
        $decoded = $this->storageExpandedChangeFromChange($change);
        foreach (['deps', 'actor', 'seq', 'startOp', 'time', 'message', 'otherActors', 'rawColumns', 'opsData', 'extraBytes'] as $field) {
            if (($decoded[$field] ?? null) !== ($expanded[$field] ?? null)) {
                throw new \InvalidArgumentException('Expanded change metadata does not match raw bytes.');
            }
        }

        return $change;
    }

    /**
     * @param array<string,mixed> $change
     * @return list<int>
     */
    public function storageChangeRawBytes(array $change): array
    {
        $rawBytes = $change['rawBytes'] ?? null;
        if (! is_array($rawBytes)) {
            throw new \InvalidArgumentException('Change does not contain raw bytes.');
        }

        return array_values($rawBytes);
    }

    /**
     * @param array{type:string,counter?:int|string,actor?:string,actorIndex?:int|string} $exId
     * @return list<int>
     */
    public function exIdToBytes(array $exId): array
    {
        $type = $exId['type'] ?? null;
        if ($type === 'root') {
            return [0];
        }

        if ($type !== 'id') {
            throw new \InvalidArgumentException('ExId type must be root or id.');
        }

        $actor = $exId['actor'] ?? null;
        if (! is_string($actor) || strlen($actor) % 2 !== 0 || ($actor !== '' && ! ctype_xdigit($actor))) {
            throw new \InvalidArgumentException('ExId actor must be a hex string.');
        }

        $actorBytes = $actor === '' ? '' : (string) hex2bin($actor);
        $counter = $this->normalizeUnsignedDecimal($exId['counter'] ?? 0, '18446744073709551615');
        $actorIndex = $this->normalizeUnsignedDecimal($exId['actorIndex'] ?? 0, '18446744073709551615');

        $bytes = chr(0x10)
            . $this->encodeUnsignedLeb128Decimal((string) strlen($actorBytes))
            . $actorBytes
            . $this->encodeUnsignedLeb128Decimal($actorIndex)
            . $this->encodeUnsignedLeb128Decimal($counter);

        return $this->columnarBytesToList($bytes);
    }

    /**
     * @param list<int> $bytes
     * @return array{type:string,counter?:string,actor?:string,actorIndex?:string,display:string}
     */
    public function exIdFromBytes(array $bytes): array
    {
        $data = $this->columnarByteListToString($bytes);
        if ($data === '') {
            throw new \InvalidArgumentException('ExId is missing a version tag.');
        }

        $offset = 0;
        $tag = ord($data[$offset]);
        ++$offset;

        $version = $tag & 0x0f;
        if ($version !== 0) {
            throw new \InvalidArgumentException('Invalid ExId version tag.');
        }

        $type = $tag >> 4;
        if ($type === 0) {
            return ['type' => 'root', 'display' => '_root'];
        }

        if ($type !== 1) {
            throw new \InvalidArgumentException('Invalid ExId type tag.');
        }

        [$actorLengthDecimal, $used] = $this->parseStorageUnsignedLeb128(substr($data, $offset));
        $offset += $used;
        $actorLength = $this->decimalStringToPhpInt($actorLengthDecimal, 'ExId actor length');
        if (strlen($data) - $offset < $actorLength) {
            throw new \InvalidArgumentException('Not enough bytes in ExId actor ID.');
        }

        $actorBytes = substr($data, $offset, $actorLength);
        $offset += $actorLength;
        [$actorIndex, $used] = $this->parseStorageUnsignedLeb128(substr($data, $offset));
        $offset += $used;
        [$counter] = $this->parseStorageUnsignedLeb128(substr($data, $offset));
        $actor = bin2hex($actorBytes);

        return [
            'type' => 'id',
            'counter' => $counter,
            'actor' => $actor,
            'actorIndex' => $actorIndex,
            'display' => $counter . '@' . $actor,
        ];
    }

    /**
     * @param array{type:string,counter?:int|string,actor?:string,actorIndex?:int|string,display?:string} $exId
     */
    public function exIdDisplay(array $exId): string
    {
        if (($exId['type'] ?? null) === 'root') {
            return '_root';
        }

        if (($exId['type'] ?? null) !== 'id') {
            throw new \InvalidArgumentException('ExId type must be root or id.');
        }

        $counter = $this->normalizeUnsignedDecimal($exId['counter'] ?? 0, '18446744073709551615');
        $actor = $exId['actor'] ?? null;
        if (! is_string($actor) || strlen($actor) % 2 !== 0 || ($actor !== '' && ! ctype_xdigit($actor))) {
            throw new \InvalidArgumentException('ExId actor must be a hex string.');
        }

        return $counter . '@' . strtolower($actor);
    }

    public function columnSpecEncode(int $id, string $type, bool $deflate = false): int
    {
        if ($id < 0 || $id > 0x0fffffff) {
            throw new \InvalidArgumentException('Column spec id must fit in 28 bits.');
        }

        $raw = ($id << 4) | $this->columnTypeCode($type);

        return $deflate ? ($raw | 0x08) : ($raw & ~0x08);
    }

    /**
     * @return array{id:int,type:string,deflate:bool,normalized:int}
     */
    public function columnSpecDecode(int $raw): array
    {
        $this->assertColumnSpecRaw($raw);

        return [
            'id' => $raw >> 4,
            'type' => $this->columnTypeName($raw & 0x07),
            'deflate' => ($raw & 0x08) !== 0,
            'normalized' => $raw & ~0x08,
        ];
    }

    public function columnSpecDeflated(int $raw): int
    {
        $this->assertColumnSpecRaw($raw);

        return $raw | 0x08;
    }

    public function columnSpecInflated(int $raw): int
    {
        $this->assertColumnSpecRaw($raw);

        return $raw & ~0x08;
    }

    public function columnSpecNormalize(int $raw): int
    {
        return $this->columnSpecInflated($raw);
    }

    /**
     * @param list<array{spec:int,length?:int,range?:array{0:int,1:int}}> $columns
     * @param list<int> $data
     * @return array{columns:list<array{spec:int,normalized:int,id:int,type:string,deflate:bool,length:int,range:array{0:int,1:int}}>,data:list<int>}
     */
    public function storageCompressRawColumns(array $columns, array $data, int $threshold = 256): array
    {
        if ($threshold < 1) {
            throw new \InvalidArgumentException('Column compression threshold must be positive.');
        }

        $rawData = $this->columnarByteListToString($data);
        $normalizedColumns = $this->storageNormalizeRawColumns($columns, strlen($rawData));
        $output = '';
        $compressedColumns = [];

        foreach ($normalizedColumns as $column) {
            $slice = substr($rawData, $column['range'][0], $column['length']);
            $spec = $this->columnSpecInflated($column['spec']);
            if ($column['deflate']) {
                $inflated = gzinflate($slice);
                if ($inflated === false) {
                    throw new \InvalidArgumentException('Unable to inflate existing Automerge column data.');
                }

                $slice = $inflated;
            }

            if (strlen($slice) >= $threshold) {
                $deflated = gzdeflate($slice);
                if ($deflated === false) {
                    throw new \RuntimeException('Unable to deflate Automerge column data.');
                }

                if (strlen($deflated) < strlen($slice)) {
                    $slice = $deflated;
                    $spec = $this->columnSpecDeflated($spec);
                }
            }

            $start = strlen($output);
            $output .= $slice;
            $compressedColumns[] = $this->storageRawColumnDescriptor($spec, strlen($slice), $start);
        }

        return [
            'columns' => $compressedColumns,
            'data' => $this->columnarBytesToList($output),
        ];
    }

    /**
     * @param list<array{spec:int,length?:int,range?:array{0:int,1:int}}> $columns
     * @param list<int> $data
     * @return array{columns:list<array{spec:int,normalized:int,id:int,type:string,deflate:bool,length:int,range:array{0:int,1:int}}>,data:list<int>}
     */
    public function storageDecompressRawColumns(array $columns, array $data): array
    {
        $rawData = $this->columnarByteListToString($data);
        $normalizedColumns = $this->storageNormalizeRawColumns($columns, strlen($rawData));
        $output = '';
        $inflatedColumns = [];

        foreach ($normalizedColumns as $column) {
            $slice = substr($rawData, $column['range'][0], $column['length']);
            $spec = $this->columnSpecInflated($column['spec']);
            if ($column['deflate']) {
                $inflated = gzinflate($slice);
                if ($inflated === false) {
                    throw new \InvalidArgumentException('Unable to inflate Automerge column data.');
                }

                $slice = $inflated;
            }

            $start = strlen($output);
            $output .= $slice;
            $inflatedColumns[] = $this->storageRawColumnDescriptor($spec, strlen($slice), $start);
        }

        return [
            'columns' => $inflatedColumns,
            'data' => $this->columnarBytesToList($output),
        ];
    }

    /**
     * @return array{values:list<mixed>}
     */
    public function sequenceTreeNew(): array
    {
        return ['values' => []];
    }

    /**
     * @param array{values?:list<mixed>} $tree
     */
    public function sequenceTreeLen(array $tree): int
    {
        return count($this->sequenceTreeValues($tree));
    }

    /**
     * @param array{values?:list<mixed>} $tree
     * @return array{values:list<mixed>}
     */
    public function sequenceTreePush(array $tree, mixed $value): array
    {
        return $this->sequenceTreeInsert($tree, $this->sequenceTreeLen($tree), $value);
    }

    /**
     * @param array{values?:list<mixed>} $tree
     * @return array{values:list<mixed>}
     */
    public function sequenceTreeInsert(array $tree, int $index, mixed $value): array
    {
        $values = $this->sequenceTreeValues($tree);
        if ($index < 0 || $index > count($values)) {
            throw new \OutOfBoundsException('SequenceTree insert index is out of bounds.');
        }

        array_splice($values, $index, 0, [$value]);

        return ['values' => array_values($values)];
    }

    /**
     * @param array{values?:list<mixed>} $tree
     * @return array{tree:array{values:list<mixed>},value:mixed}
     */
    public function sequenceTreeRemove(array $tree, int $index): array
    {
        $values = $this->sequenceTreeValues($tree);
        if ($index < 0 || $index >= count($values)) {
            throw new \OutOfBoundsException('SequenceTree remove index is out of bounds.');
        }

        $removed = array_splice($values, $index, 1);

        return ['tree' => ['values' => array_values($values)], 'value' => $removed[0]];
    }

    /**
     * @param array{values?:list<mixed>} $tree
     */
    public function sequenceTreeGet(array $tree, int $index): mixed
    {
        $values = $this->sequenceTreeValues($tree);

        return $values[$index] ?? null;
    }

    /**
     * @param array{values?:list<mixed>} $tree
     * @return list<mixed>
     */
    public function sequenceTreeIter(array $tree): array
    {
        return $this->sequenceTreeValues($tree);
    }

    /**
     * @param array{values?:list<mixed>} $tree
     * @param list<mixed> $values
     */
    public function sequenceTreeEqualsList(array $tree, array $values): bool
    {
        return $this->sequenceTreeValues($tree) === array_values($values);
    }

    /**
     * @param list<bool> $values
     */
    public function columnarEncodeBooleans(array $values): string
    {
        $bytes = '';
        $last = false;
        $count = 0;

        foreach ($values as $value) {
            if (! is_bool($value)) {
                throw new \InvalidArgumentException('Boolean column values must be booleans.');
            }

            if ($value === $last) {
                ++$count;
                continue;
            }

            $bytes .= $this->encodeUnsignedLeb128Int($count);
            $last = $value;
            $count = 1;
        }

        if ($count > 0) {
            $bytes .= $this->encodeUnsignedLeb128Int($count);
        }

        return $bytes;
    }

    /**
     * @return list<bool>
     */
    public function columnarDecodeBooleans(string $bytes): array
    {
        $values = [];
        $offset = 0;
        $lastValue = true;
        $length = strlen($bytes);

        while ($offset < $length) {
            $count = $this->decodeUnsignedLeb128Int($bytes, $offset);
            $lastValue = ! $lastValue;
            for ($index = 0; $index < $count; ++$index) {
                $values[] = $lastValue;
            }
        }

        return $values;
    }

    /**
     * @param list<int|null> $values
     */
    public function columnarEncodeRleInts(array $values): string
    {
        $bytes = '';
        $state = ['type' => 'empty'];

        foreach ($values as $value) {
            if ($value === null) {
                $state = $this->appendRleNull($state, $bytes);
                continue;
            }

            if (! is_int($value)) {
                throw new \InvalidArgumentException('RLE integer column values must be integers or null.');
            }

            $state = $this->appendRleIntValue($state, $value, $bytes);
        }

        $this->flushRleIntState($state, $bytes);

        return $bytes;
    }

    /**
     * @return list<int|null>
     */
    public function columnarDecodeRleInts(string $bytes): array
    {
        $values = [];
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $count = $this->decodeSignedLeb128Int($bytes, $offset);
            if ($count > 0) {
                $value = $this->decodeSignedLeb128Int($bytes, $offset);
                for ($index = 0; $index < $count; ++$index) {
                    $values[] = $value;
                }
            } elseif ($count < 0) {
                for ($index = 0; $index < abs($count); ++$index) {
                    $values[] = $this->decodeSignedLeb128Int($bytes, $offset);
                }
            } else {
                $nullCount = $this->decodeUnsignedLeb128Int($bytes, $offset);
                for ($index = 0; $index < $nullCount; ++$index) {
                    $values[] = null;
                }
            }
        }

        return $values;
    }

    /**
     * @param list<int|null> $replacement
     */
    public function columnarSpliceRleInts(string $bytes, int $start, int $deleteCount, array $replacement): string
    {
        $values = $this->columnarDecodeRleInts($bytes);
        $count = count($values);
        if ($start < 0 || $deleteCount < 0 || $start > $count || $start + $deleteCount > $count) {
            throw new \OutOfBoundsException('RLE integer splice range is out of bounds.');
        }

        foreach ($replacement as $value) {
            if ($value !== null && ! is_int($value)) {
                throw new \InvalidArgumentException('RLE integer splice replacements must be integers or null.');
            }
        }

        array_splice($values, $start, $deleteCount, $replacement);

        return $this->columnarEncodeRleInts($values);
    }

    /**
     * @param list<string|null> $values
     */
    public function columnarEncodeRleStrings(array $values): string
    {
        $bytes = '';
        $state = ['type' => 'empty'];

        foreach ($values as $value) {
            if ($value === null) {
                $state = $this->appendRleStringNull($state, $bytes);
                continue;
            }

            if (! is_string($value)) {
                throw new \InvalidArgumentException('RLE string column values must be strings or null.');
            }

            $state = $this->appendRleStringValue($state, $value, $bytes);
        }

        $this->flushRleStringState($state, $bytes);

        return $bytes;
    }

    /**
     * @return list<string|null>
     */
    public function columnarDecodeRleStrings(string $bytes): array
    {
        $values = [];
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $count = $this->decodeSignedLeb128Int($bytes, $offset);
            if ($count > 0) {
                $value = $this->decodeColumnarString($bytes, $offset);
                for ($index = 0; $index < $count; ++$index) {
                    $values[] = $value;
                }
            } elseif ($count < 0) {
                for ($index = 0; $index < abs($count); ++$index) {
                    $values[] = $this->decodeColumnarString($bytes, $offset);
                }
            } else {
                $nullCount = $this->decodeUnsignedLeb128Int($bytes, $offset);
                for ($index = 0; $index < $nullCount; ++$index) {
                    $values[] = null;
                }
            }
        }

        return $values;
    }

    /**
     * @param list<string|null> $replacement
     */
    public function columnarSpliceRleStrings(string $bytes, int $start, int $deleteCount, array $replacement): string
    {
        $values = $this->columnarDecodeRleStrings($bytes);
        $count = count($values);
        if ($start < 0 || $deleteCount < 0 || $start > $count || $start + $deleteCount > $count) {
            throw new \OutOfBoundsException('RLE string splice range is out of bounds.');
        }

        foreach ($replacement as $value) {
            if ($value !== null && ! is_string($value)) {
                throw new \InvalidArgumentException('RLE string splice replacements must be strings or null.');
            }
        }

        array_splice($values, $start, $deleteCount, $replacement);

        return $this->columnarEncodeRleStrings($values);
    }

    /**
     * @param list<int> $values
     */
    public function columnarEncodeRleUints(array $values): string
    {
        $bytes = '';
        $state = ['type' => 'empty'];

        foreach ($values as $value) {
            if (! is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('RLE unsigned integer column values must be non-negative integers.');
            }

            $state = $this->appendRleUintValue($state, $value, $bytes);
        }

        $this->flushRleUintState($state, $bytes);

        return $bytes;
    }

    /**
     * @return list<int|null>
     */
    public function columnarDecodeRleUints(string $bytes): array
    {
        $values = [];
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $count = $this->decodeSignedLeb128Int($bytes, $offset);
            if ($count > 0) {
                $value = $this->decodeUnsignedLeb128Int($bytes, $offset);
                for ($index = 0; $index < $count; ++$index) {
                    $values[] = $value;
                }
            } elseif ($count < 0) {
                for ($index = 0; $index < abs($count); ++$index) {
                    $values[] = $this->decodeUnsignedLeb128Int($bytes, $offset);
                }
            } else {
                $nullCount = $this->decodeUnsignedLeb128Int($bytes, $offset);
                for ($index = 0; $index < $nullCount; ++$index) {
                    $values[] = null;
                }
            }
        }

        return $values;
    }

    /**
     * @param list<int|null> $values
     */
    public function columnarEncodeDeltaInts(array $values): string
    {
        $absolute = 0;
        $deltas = [];

        foreach ($values as $value) {
            if ($value === null) {
                $deltas[] = null;
                continue;
            }

            if (! is_int($value)) {
                throw new \InvalidArgumentException('Delta integer column values must be integers or null.');
            }

            $deltas[] = $value - $absolute;
            $absolute = $value;
        }

        return $this->columnarEncodeRleInts($deltas);
    }

    /**
     * @return list<int|null>
     */
    public function columnarDecodeDeltaInts(string $bytes): array
    {
        $absolute = 0;
        $values = [];

        foreach ($this->columnarDecodeRleInts($bytes) as $delta) {
            if ($delta === null) {
                $values[] = null;
                continue;
            }

            $absolute += $delta;
            $values[] = $absolute;
        }

        return $values;
    }

    /**
     * @param list<int|null> $replacement
     */
    public function columnarSpliceDeltaInts(string $bytes, int $start, int $deleteCount, array $replacement): string
    {
        $values = $this->columnarDecodeDeltaInts($bytes);
        $count = count($values);
        if ($start < 0 || $deleteCount < 0 || $start > $count || $start + $deleteCount > $count) {
            throw new \OutOfBoundsException('Delta integer splice range is out of bounds.');
        }

        foreach ($replacement as $value) {
            if ($value !== null && ! is_int($value)) {
                throw new \InvalidArgumentException('Delta integer splice replacements must be integers or null.');
            }
        }

        array_splice($values, $start, $deleteCount, $replacement);

        return $this->columnarEncodeDeltaInts($values);
    }

    /**
     * @param list<list<array{actor:int,counter:int}>> $opidGroups
     * @return array{bytes:string,ranges:array{num:array{0:int,1:int},actor:array{0:int,1:int},counter:array{0:int,1:int}}}
     */
    public function columnarEncodeOpIdLists(array $opidGroups): array
    {
        $nums = [];
        $actors = [];
        $counters = [];

        foreach ($opidGroups as $group) {
            if (! is_array($group)) {
                throw new \InvalidArgumentException('OpId list groups must be arrays.');
            }

            $nums[] = count($group);
            foreach ($group as $opid) {
                if (! is_array($opid)) {
                    throw new \InvalidArgumentException('OpId list entries must be arrays.');
                }

                $actor = $opid['actor'] ?? null;
                $counter = $opid['counter'] ?? null;
                if (! is_int($actor) || $actor < 0 || ! is_int($counter) || $counter < 0) {
                    throw new \InvalidArgumentException('OpId entries must contain non-negative integer actor and counter values.');
                }

                $actors[] = $actor;
                $counters[] = $counter;
            }
        }

        $numBytes = $this->columnarEncodeRleUints($nums);
        $actorBytes = $this->columnarEncodeRleUints($actors);
        $counterBytes = $this->columnarEncodeDeltaInts($counters);
        $numEnd = strlen($numBytes);
        $actorEnd = $numEnd + strlen($actorBytes);
        $counterEnd = $actorEnd + strlen($counterBytes);

        return [
            'bytes' => $numBytes . $actorBytes . $counterBytes,
            'ranges' => [
                'num' => [0, $numEnd],
                'actor' => [$numEnd, $actorEnd],
                'counter' => [$actorEnd, $counterEnd],
            ],
        ];
    }

    /**
     * @param array{bytes:string,ranges:array{num:array{0:int,1:int},actor:array{0:int,1:int},counter:array{0:int,1:int}}} $encoded
     * @return list<list<array{actor:int,counter:int}>>
     */
    public function columnarDecodeOpIdLists(array $encoded): array
    {
        $bytes = $encoded['bytes'] ?? null;
        $ranges = $encoded['ranges'] ?? null;
        if (! is_string($bytes) || ! is_array($ranges)) {
            throw new \InvalidArgumentException('Encoded OpId lists require bytes and column ranges.');
        }

        $nums = $this->columnarDecodeRleUints($this->columnarRangeSlice($bytes, $ranges['num'] ?? null, 'num'));
        $actors = $this->columnarDecodeRleUints($this->columnarRangeSlice($bytes, $ranges['actor'] ?? null, 'actor'));
        $counters = $this->columnarDecodeDeltaInts($this->columnarRangeSlice($bytes, $ranges['counter'] ?? null, 'counter'));
        $groups = [];
        $offset = 0;

        foreach ($nums as $num) {
            if ($num === null) {
                throw new \InvalidArgumentException('OpId group counts cannot be null.');
            }

            $group = [];
            for ($index = 0; $index < $num; ++$index) {
                if (! array_key_exists($offset, $actors) || ! array_key_exists($offset, $counters)) {
                    throw new \InvalidArgumentException('OpId actor/counter columns ended before all group entries were decoded.');
                }

                $actor = $actors[$offset];
                $counter = $counters[$offset];
                if ($actor === null || $counter === null || $counter < 0) {
                    throw new \InvalidArgumentException('OpId actor and counter columns must contain non-null non-negative values.');
                }

                $group[] = ['actor' => $actor, 'counter' => $counter];
                ++$offset;
            }

            $groups[] = $group;
        }

        if ($offset !== count($actors) || $offset !== count($counters)) {
            throw new \InvalidArgumentException('OpId actor/counter columns contain ungrouped trailing entries.');
        }

        return $groups;
    }

    /**
     * @param array{bytes:string,ranges:array{num:array{0:int,1:int},actor:array{0:int,1:int},counter:array{0:int,1:int}}} $encoded
     * @param list<list<array{actor:int,counter:int}>> $replacement
     * @return array{bytes:string,ranges:array{num:array{0:int,1:int},actor:array{0:int,1:int},counter:array{0:int,1:int}}}
     */
    public function columnarSpliceOpIdLists(array $encoded, int $start, int $deleteCount, array $replacement): array
    {
        $groups = $this->columnarDecodeOpIdLists($encoded);
        $count = count($groups);
        if ($start < 0 || $deleteCount < 0 || $start > $count || $start + $deleteCount > $count) {
            throw new \OutOfBoundsException('OpId list splice range is out of bounds.');
        }

        $replacement = $this->columnarDecodeOpIdLists($this->columnarEncodeOpIdLists($replacement));
        array_splice($groups, $start, $deleteCount, $replacement);

        return $this->columnarEncodeOpIdLists($groups);
    }

    /**
     * @param list<array<string,mixed>> $ops
     * @return array{rowCount:int,obj:string,key:string,val:string,pred:string,action:string,insert:string,expand:string,markName:string}
     */
    public function storageChangeEncodeChangeOps(array $ops): array
    {
        $rows = [];
        foreach ($ops as $op) {
            if (! is_array($op)) {
                throw new \InvalidArgumentException('Change op rows must be arrays.');
            }

            $rows[] = [
                'obj' => $this->storageChangeJsonColumn($op['obj'] ?? null),
                'key' => $this->storageChangeJsonColumn($op['key'] ?? null),
                'val' => $this->storageChangeJsonColumn($op['val'] ?? null),
                'pred' => $this->storageChangeJsonColumn(is_array($op['pred'] ?? null) ? array_values($op['pred']) : []),
                'action' => is_string($op['action'] ?? null) ? $op['action'] : throw new \InvalidArgumentException('Change op action must be a string.'),
                'insert' => (bool) ($op['insert'] ?? false),
                'expand' => (bool) ($op['expand'] ?? false),
                'markName' => is_string($op['markName'] ?? null) ? $op['markName'] : null,
            ];
        }

        return [
            'rowCount' => count($rows),
            'obj' => $this->columnarEncodeRleStrings(array_column($rows, 'obj')),
            'key' => $this->columnarEncodeRleStrings(array_column($rows, 'key')),
            'val' => $this->columnarEncodeRleStrings(array_column($rows, 'val')),
            'pred' => $this->columnarEncodeRleStrings(array_column($rows, 'pred')),
            'action' => $this->columnarEncodeRleStrings(array_column($rows, 'action')),
            'insert' => $this->columnarEncodeBooleans(array_column($rows, 'insert')),
            'expand' => $this->columnarEncodeBooleans(array_column($rows, 'expand')),
            'markName' => $this->columnarEncodeRleStrings(array_column($rows, 'markName')),
        ];
    }

    /**
     * @param array{rowCount:int,obj:string,key:string,val:string,pred:string,action:string,insert:string,expand:string,markName:string} $columns
     * @return list<array{obj:mixed,key:mixed,val:mixed,pred:list<mixed>,action:string,insert:bool,expand:bool,markName:?string}>
     */
    public function storageChangeDecodeChangeOps(array $columns): array
    {
        $rowCount = $columns['rowCount'] ?? null;
        if (! is_int($rowCount) || $rowCount < 0) {
            throw new \InvalidArgumentException('Change op columns require a non-negative row count.');
        }

        $objs = $this->columnarDecodeRleStrings($columns['obj']);
        $keys = $this->columnarDecodeRleStrings($columns['key']);
        $vals = $this->columnarDecodeRleStrings($columns['val']);
        $preds = $this->columnarDecodeRleStrings($columns['pred']);
        $actions = $this->columnarDecodeRleStrings($columns['action']);
        $inserts = $this->columnarDecodeBooleans($columns['insert']);
        $expands = $this->columnarDecodeBooleans($columns['expand']);
        $markNames = $this->columnarDecodeRleStrings($columns['markName']);

        foreach ([$objs, $keys, $vals, $preds, $actions, $inserts, $expands, $markNames] as $column) {
            if (count($column) !== $rowCount) {
                throw new \InvalidArgumentException('Change op column lengths must match the row count.');
            }
        }

        $ops = [];
        for ($index = 0; $index < $rowCount; ++$index) {
            $pred = $this->storageChangeJsonColumnValue($preds[$index], 'pred');
            if (! is_array($pred)) {
                throw new \InvalidArgumentException('Decoded change op pred column must be a list.');
            }

            $ops[] = [
                'obj' => $this->storageChangeJsonColumnValue($objs[$index], 'obj'),
                'key' => $this->storageChangeJsonColumnValue($keys[$index], 'key'),
                'val' => $this->storageChangeJsonColumnValue($vals[$index], 'val'),
                'pred' => array_values($pred),
                'action' => $this->nonNullStringColumnValue($actions[$index], 'action'),
                'insert' => (bool) $inserts[$index],
                'expand' => (bool) $expands[$index],
                'markName' => $markNames[$index],
            ];
        }

        return $ops;
    }

    private function storageChangeJsonColumn(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function storageChangeJsonColumnValue(?string $value, string $column): mixed
    {
        if ($value === null) {
            throw new \InvalidArgumentException('Change op ' . $column . ' column cannot be null.');
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string,mixed>> $values
     * @return array{bytes:string,ranges:array{meta:array{0:int,1:int},raw:array{0:int,1:int}}}
     */
    public function columnarEncodeScalarValues(array $values): array
    {
        $metas = [];
        $raw = '';

        foreach ($values as $value) {
            [$meta, $rawBytes] = $this->columnarEncodeScalarValue($value);
            $metas[] = $meta;
            $raw .= $rawBytes;
        }

        $metaBytes = $this->columnarEncodeRleUints($metas);
        $metaEnd = strlen($metaBytes);
        $rawEnd = $metaEnd + strlen($raw);

        return [
            'bytes' => $metaBytes . $raw,
            'ranges' => [
                'meta' => [0, $metaEnd],
                'raw' => [$metaEnd, $rawEnd],
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $values
     * @return array{bytes:string,ranges:array{meta:array{0:int,1:int},raw:array{0:int,1:int}}}
     */
    public function columnarEncodeScalarValuesRowWise(array $values): array
    {
        $metaBytes = '';
        $metaState = ['type' => 'empty'];
        $raw = '';

        foreach ($values as $value) {
            [$meta, $rawBytes] = $this->columnarEncodeScalarValue($value);
            $metaState = $this->appendRleUintValue($metaState, $meta, $metaBytes);
            $raw .= $rawBytes;
        }

        $this->flushRleUintState($metaState, $metaBytes);
        $metaEnd = strlen($metaBytes);
        $rawEnd = $metaEnd + strlen($raw);

        return [
            'bytes' => $metaBytes . $raw,
            'ranges' => [
                'meta' => [0, $metaEnd],
                'raw' => [$metaEnd, $rawEnd],
            ],
        ];
    }

    /**
     * @param array{bytes:string,ranges:array{meta:array{0:int,1:int},raw:array{0:int,1:int}}} $encoded
     * @return list<array<string,mixed>>
     */
    public function columnarDecodeScalarValues(array $encoded): array
    {
        $bytes = $encoded['bytes'] ?? null;
        $ranges = $encoded['ranges'] ?? null;
        if (! is_string($bytes) || ! is_array($ranges)) {
            throw new \InvalidArgumentException('Encoded scalar values require bytes and column ranges.');
        }

        $metas = $this->columnarDecodeRleUints($this->columnarRangeSlice($bytes, $ranges['meta'] ?? null, 'value meta'));
        $raw = $this->columnarRangeSlice($bytes, $ranges['raw'] ?? null, 'value raw');
        $offset = 0;
        $values = [];

        foreach ($metas as $meta) {
            if ($meta === null) {
                throw new \InvalidArgumentException('Scalar value metadata cannot be null.');
            }

            $values[] = $this->columnarDecodeScalarValue($meta, $raw, $offset);
        }

        if ($offset !== strlen($raw)) {
            throw new \InvalidArgumentException('Scalar value raw column contains trailing bytes.');
        }

        return $values;
    }

    /**
     * @param list<int> $metas
     * @return list<array{value:int,typeCode:int,length:int,acc:int}>
     */
    public function opSet2ValueMetaWithAccumulator(array $metas, int $start = 0, ?int $length = null): array
    {
        foreach ($metas as $meta) {
            if (! is_int($meta) || $meta < 0) {
                throw new \InvalidArgumentException('ValueMeta entries must be non-negative integers.');
            }
        }

        $decoded = $this->columnarDecodeRleUints($this->columnarEncodeRleUints($metas));
        $count = count($decoded);
        if ($start < 0 || $start > $count) {
            throw new \OutOfBoundsException('ValueMeta iterator start is out of bounds.');
        }

        $end = $length === null ? $count : $start + $length;
        if ($length !== null && ($length < 0 || $end > $count)) {
            throw new \OutOfBoundsException('ValueMeta iterator range is out of bounds.');
        }

        $rows = [];
        $acc = 0;
        foreach ($decoded as $index => $meta) {
            if ($meta === null) {
                throw new \InvalidArgumentException('ValueMeta column cannot contain null entries.');
            }

            if ($index >= $start && $index < $end) {
                $rows[] = [
                    'value' => $meta,
                    'typeCode' => $meta & 0b1111,
                    'length' => $meta >> 4,
                    'acc' => $acc,
                ];
            }

            $acc += $meta >> 4;
        }

        return $rows;
    }

    /**
     * @param list<mixed> $objectIds
     * @return array{range:array{0:int,1:int},pos:int,values:list<array{type:string,counter:int,actor:int}>}
     */
    public function opSet2ObjectIdSeek(array $objectIds, mixed $target): array
    {
        $objects = [];
        $previous = null;
        foreach ($objectIds as $objectId) {
            $object = $this->opSet2NormalizeObjectId($objectId);
            if ($previous !== null && $this->opSet2CompareObjectIds($previous, $object) > 0) {
                throw new \InvalidArgumentException('Object id columns must be sorted for op_set2 seeking.');
            }

            $objects[] = $object;
            $previous = $object;
        }

        $targetObject = $this->opSet2NormalizeObjectId($target);
        $low = 0;
        $high = count($objects);
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->opSet2CompareObjectIds($objects[$mid], $targetObject) < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        $start = $low;
        $end = $start;
        while ($end < count($objects) && $this->opSet2CompareObjectIds($objects[$end], $targetObject) === 0) {
            ++$end;
        }

        return [
            'range' => [$start, $end],
            'pos' => $start,
            'values' => array_slice($objects, $start, $end - $start),
        ];
    }

    /**
     * @param list<mixed> $operationIds
     * @return list<array{counter:int,actor:int}>
     */
    public function opSet2OperationIdsInCounterRange(array $operationIds, int $start, int $end): array
    {
        $this->validateOpSet2CounterRange($start, $end);

        $matches = [];
        foreach ($operationIds as $operationId) {
            $id = $this->opSet2NormalizeOperationId($operationId);
            if ($id['counter'] >= $start && $id['counter'] < $end) {
                $matches[] = $id;
            }
        }

        return $matches;
    }

    /**
     * @param list<mixed> $operationIds
     * @param list<list<mixed>> $successorsByIndex
     * @return list<array{counter:int,actor:int}>
     */
    public function opSet2OperationIdsWithSuccessorsInCounterRange(array $operationIds, array $successorsByIndex, int $start, int $end): array
    {
        $this->validateOpSet2CounterRange($start, $end);
        if (count($operationIds) !== count($successorsByIndex)) {
            throw new \InvalidArgumentException('Operation id and successor columns must have the same row count.');
        }

        $matches = [];
        foreach (array_values($operationIds) as $index => $operationId) {
            $hasSuccessorInRange = false;
            foreach ($successorsByIndex[$index] as $successorId) {
                $successor = $this->opSet2NormalizeOperationId($successorId);
                if ($successor['counter'] >= $start && $successor['counter'] < $end) {
                    $hasSuccessorInRange = true;
                    break;
                }
            }

            if ($hasSuccessorInRange) {
                $matches[] = $this->opSet2NormalizeOperationId($operationId);
            }
        }

        return $matches;
    }

    /**
     * @param list<mixed> $operationIds
     * @param list<list<mixed>> $successorsByIndex
     * @return list<array{counter:int,actor:int}>
     */
    public function opSet2IterCounterRange(array $operationIds, array $successorsByIndex, int $start, int $end): array
    {
        $this->validateOpSet2CounterRange($start, $end);
        if (count($operationIds) !== count($successorsByIndex)) {
            throw new \InvalidArgumentException('Operation id and successor columns must have the same row count.');
        }

        $matches = [];
        foreach (array_values($operationIds) as $index => $operationId) {
            $id = $this->opSet2NormalizeOperationId($operationId);
            $include = $id['counter'] >= $start && $id['counter'] < $end;
            foreach ($successorsByIndex[$index] as $successorId) {
                $successor = $this->opSet2NormalizeOperationId($successorId);
                if ($successor['counter'] >= $start && $successor['counter'] < $end) {
                    $include = true;
                    break;
                }
            }

            if ($include) {
                $matches[] = $id;
            }
        }

        return $matches;
    }

    /**
     * @param array{type:string,counter:int,actor:int} $value
     */
    public function opSet2EncodeMarkIndexValue(array $value): int
    {
        $id = $this->opSet2NormalizeOperationId($value);
        if (! in_array($value['type'] ?? null, ['start', 'end'], true)) {
            throw new \InvalidArgumentException('MarkIndexValue entries must have type start or end.');
        }

        if ($id['counter'] > 0xffffffff) {
            throw new \InvalidArgumentException('MarkIndexValue counters must fit in 32 bits.');
        }

        $packed = ($id['actor'] * 4294967296) + $id['counter'];
        if ($packed > PHP_INT_MAX) {
            throw new \InvalidArgumentException('MarkIndexValue actor/counter pair is too large for native PHP integers.');
        }

        if ($value['type'] === 'end' && $packed === 0) {
            throw new \InvalidArgumentException('MarkIndexValue end entries cannot encode the zero operation id.');
        }

        return $value['type'] === 'end' ? -$packed : $packed;
    }

    /**
     * @return array{type:string,counter:int,actor:int}
     */
    public function opSet2DecodeMarkIndexValue(int $value): array
    {
        $type = $value < 0 ? 'end' : 'start';
        $packed = abs($value);

        return [
            'type' => $type,
            'counter' => $packed % 4294967296,
            'actor' => intdiv($packed, 4294967296),
        ];
    }

    /**
     * @param list<array{type:string,counter:int,actor:int}|null> $values
     */
    public function opSet2EncodeMarkIndexColumn(array $values): string
    {
        $encoded = [];
        foreach ($values as $value) {
            $encoded[] = $value === null ? null : $this->opSet2EncodeMarkIndexValue($value);
        }

        return $this->columnarEncodeRleInts($encoded);
    }

    /**
     * @return list<array{type:string,counter:int,actor:int}|null>
     */
    public function opSet2DecodeMarkIndexColumn(string $bytes): array
    {
        return array_map(
            fn (?int $value): ?array => $value === null ? null : $this->opSet2DecodeMarkIndexValue($value),
            $this->columnarDecodeRleInts($bytes)
        );
    }

    /**
     * @return array{rowCount:int,pos:string,id:string,change:string,actor:string,seq:string,opIndex:string,action:string,key:string,insert:string,payload:string}
     */
    public function opSet2EncodeOperationColumns(Document $document): array
    {
        $rows = $this->opSet2OperationRows($document);

        return [
            'rowCount' => count($rows),
            'pos' => $this->columnarEncodeRleUints(array_column($rows, 'pos')),
            'id' => $this->columnarEncodeRleStrings(array_column($rows, 'id')),
            'change' => $this->columnarEncodeRleStrings(array_column($rows, 'change')),
            'actor' => $this->columnarEncodeRleStrings(array_column($rows, 'actor')),
            'seq' => $this->columnarEncodeRleUints(array_column($rows, 'seq')),
            'opIndex' => $this->columnarEncodeRleUints(array_column($rows, 'opIndex')),
            'action' => $this->columnarEncodeRleStrings(array_column($rows, 'action')),
            'key' => $this->columnarEncodeRleStrings(array_column($rows, 'key')),
            'insert' => $this->columnarEncodeBooleans(array_column($rows, 'insert')),
            'payload' => $this->columnarEncodeRleStrings(array_column($rows, 'payload')),
        ];
    }

    /**
     * @param array{rowCount:int,pos:string,id:string,change:string,actor:string,seq:string,opIndex:string,action:string,key:string,insert:string,payload:string} $columns
     * @return list<array{pos:int,id:string,change:string,actor:string,seq:int,opIndex:int,action:string,key:string,insert:bool,payload:string}>
     */
    public function opSet2DecodeOperationColumns(array $columns): array
    {
        $rowCount = $columns['rowCount'];
        if (! is_int($rowCount) || $rowCount < 0) {
            throw new \InvalidArgumentException('Operation column data requires a non-negative row count.');
        }

        $positions = $this->columnarDecodeRleUints($columns['pos']);
        $ids = $this->columnarDecodeRleStrings($columns['id']);
        $changes = $this->columnarDecodeRleStrings($columns['change']);
        $actors = $this->columnarDecodeRleStrings($columns['actor']);
        $seqs = $this->columnarDecodeRleUints($columns['seq']);
        $opIndexes = $this->columnarDecodeRleUints($columns['opIndex']);
        $actions = $this->columnarDecodeRleStrings($columns['action']);
        $keys = $this->columnarDecodeRleStrings($columns['key']);
        $inserts = $this->columnarDecodeBooleans($columns['insert']);
        $payloads = $this->columnarDecodeRleStrings($columns['payload']);

        foreach ([$positions, $ids, $changes, $actors, $seqs, $opIndexes, $actions, $keys, $inserts, $payloads] as $column) {
            if (count($column) !== $rowCount) {
                throw new \InvalidArgumentException('Operation column lengths must match the row count.');
            }
        }

        $rows = [];
        for ($index = 0; $index < $rowCount; ++$index) {
            $rows[] = [
                'pos' => $this->nonNullIntColumnValue($positions[$index], 'pos'),
                'id' => $this->nonNullStringColumnValue($ids[$index], 'id'),
                'change' => $this->nonNullStringColumnValue($changes[$index], 'change'),
                'actor' => $this->nonNullStringColumnValue($actors[$index], 'actor'),
                'seq' => $this->nonNullIntColumnValue($seqs[$index], 'seq'),
                'opIndex' => $this->nonNullIntColumnValue($opIndexes[$index], 'opIndex'),
                'action' => $this->nonNullStringColumnValue($actions[$index], 'action'),
                'key' => $this->nonNullStringColumnValue($keys[$index], 'key'),
                'insert' => $inserts[$index],
                'payload' => $this->nonNullStringColumnValue($payloads[$index], 'payload'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{pos:int,id:string,change:string,actor:string,seq:int,opIndex:int,action:string,key:string,insert:bool,payload:string}>
     */
    public function opSet2OperationRows(Document $document): array
    {
        $rows = [];
        foreach ($document->getAllChanges() as $change) {
            $actor = is_string($change['actor'] ?? null) ? $change['actor'] : '';
            $changeHash = is_string($change['hash'] ?? null) ? $change['hash'] : '';
            $seq = max(0, (int) ($change['seq'] ?? 0));
            $startOp = max(1, (int) ($change['startOp'] ?? 1));
            $ops = is_array($change['ops'] ?? null) ? array_values($change['ops']) : [];

            foreach ($ops as $opIndex => $op) {
                if (! is_array($op)) {
                    continue;
                }

                $rows[] = [
                    'pos' => count($rows),
                    'id' => ($startOp + $opIndex) . '@' . $actor,
                    'change' => $changeHash,
                    'actor' => $actor,
                    'seq' => $seq,
                    'opIndex' => $opIndex,
                    'action' => is_string($op['action'] ?? null) ? $op['action'] : '',
                    'key' => $this->opSet2OperationKey($op),
                    'insert' => (bool) ($op['insert'] ?? false),
                    'payload' => json_encode($op, JSON_THROW_ON_ERROR),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{range:array{0:int,1:int},pos:int,rows:list<array<string,mixed>>}
     */
    public function opSet2OperationRowsForObject(array $rows, mixed $objectId): array
    {
        $target = $this->opSet2NormalizeObjectId($objectId);
        $start = null;
        $end = null;
        $matchedRows = [];
        $leftRange = false;

        foreach (array_values($rows) as $index => $row) {
            if (! array_key_exists('obj', $row)) {
                throw new \InvalidArgumentException('Operation rows must include an obj column for object range iteration.');
            }

            $object = $this->opSet2NormalizeObjectId($row['obj']);
            $matches = $this->opSet2CompareObjectIds($object, $target) === 0;
            if ($matches && $leftRange) {
                throw new \InvalidArgumentException('Operation rows for an object must be stored contiguously.');
            }

            if ($matches) {
                $start ??= $index;
                $end = $index + 1;
                $matchedRows[] = $row;
                continue;
            }

            if ($start !== null) {
                $leftRange = true;
            }
        }

        $start ??= count($rows);
        $end ??= $start;

        return [
            'range' => [$start, $end],
            'pos' => $start,
            'rows' => $matchedRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{range:array{0:int,1:int},pos:int,rows:list<array<string,mixed>>}
     */
    public function opSet2OperationRowsForProperty(array $rows, mixed $objectId, string|int $key): array
    {
        $target = $this->opSet2NormalizeObjectId($objectId);
        $key = (string) $key;
        $start = null;
        $end = null;
        $matchedRows = [];
        $leftRange = false;

        foreach (array_values($rows) as $index => $row) {
            if (! array_key_exists('obj', $row)) {
                throw new \InvalidArgumentException('Operation rows must include an obj column for property range iteration.');
            }

            $object = $this->opSet2NormalizeObjectId($row['obj']);
            $rowKey = array_key_exists('key', $row) && (is_string($row['key']) || is_int($row['key'])) ? (string) $row['key'] : '';
            $matches = $this->opSet2CompareObjectIds($object, $target) === 0 && $rowKey === $key;
            if ($matches && $leftRange) {
                throw new \InvalidArgumentException('Operation rows for an object property must be stored contiguously.');
            }

            if ($matches) {
                $start ??= $index;
                $end = $index + 1;
                $matchedRows[] = $row;
                continue;
            }

            if ($start !== null) {
                $leftRange = true;
            }
        }

        $start ??= count($rows);
        $end ??= $start;

        return [
            'range' => [$start, $end],
            'pos' => $start,
            'rows' => $matchedRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<list<array<string,mixed>>>
     */
    public function opSet2OperationRowsGroupedByKey(array $rows, mixed $objectId, bool $visibleOnly = false): array
    {
        $groups = [];
        foreach ($this->opSet2OperationRowsForObject($rows, $objectId)['rows'] as $row) {
            if ($visibleOnly && ! $this->opSet2OperationRowIsVisible($row)) {
                continue;
            }

            $key = array_key_exists('key', $row) && (is_string($row['key']) || is_int($row['key'])) ? (string) $row['key'] : '';
            $lastIndex = count($groups) - 1;
            $lastKey = $lastIndex >= 0 ? ($groups[$lastIndex][0]['key'] ?? null) : null;
            $lastKey = is_string($lastKey) || is_int($lastKey) ? (string) $lastKey : null;
            if ($lastKey === $key) {
                $groups[$lastIndex][] = $row;
                continue;
            }

            $groups[] = [$row];
        }

        return $groups;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function opSet2TopOperationRows(array $rows, mixed $objectId, bool $visibleOnly = false): array
    {
        $top = [];
        foreach ($this->opSet2OperationRowsGroupedByKey($rows, $objectId, $visibleOnly) as $group) {
            if ($group === []) {
                continue;
            }

            $top[] = $group[array_key_last($group)];
        }

        return $top;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{obj:string,prop:array{type:string,value:string|int},typ:string,visible:bool}>
     */
    public function opSet2ParentPath(array $rows, mixed $objectId): array
    {
        $creators = [];
        foreach ($rows as $row) {
            if (! is_array($row['id'] ?? null) && ! is_string($row['id'] ?? null)) {
                continue;
            }

            if (! is_string($row['obj'] ?? null) && ! is_array($row['obj'] ?? null)) {
                continue;
            }

            $id = $this->opSet2StringOperationId($row['id']);
            if (is_string($row['objectType'] ?? null)) {
                $creators[$id] = $row;
            }
        }

        $path = [];
        $current = $this->opSet2StringOperationId($objectId);
        while (isset($creators[$current])) {
            $row = $creators[$current];
            $parentObject = $row['obj'];
            $parentObjectId = $this->opSet2StringObjectId($parentObject);
            $path[] = [
                'obj' => $parentObjectId === 'root' ? '_root' : $parentObjectId,
                'prop' => $this->opSet2ParentProp($row),
                'typ' => $parentObjectId === 'root' ? 'map' : (string) ($creators[$parentObjectId]['objectType'] ?? 'map'),
                'visible' => $this->opSet2OperationRowIsVisible($row),
            ];

            if ($parentObjectId === 'root') {
                break;
            }

            $current = $parentObjectId;
        }

        return array_reverse($path);
    }

    /**
     * @param array<string,mixed> $op
     */
    private function opSet2OperationKey(array $op): string
    {
        if (array_key_exists('key', $op) && (is_string($op['key']) || is_int($op['key']))) {
            return (string) $op['key'];
        }

        if (array_key_exists('elemId', $op) && (is_string($op['elemId']) || is_int($op['elemId']))) {
            return (string) $op['elemId'];
        }

        if (is_array($op['path'] ?? null)) {
            return json_encode(array_values($op['path']), JSON_THROW_ON_ERROR);
        }

        return '';
    }

    /**
     * @param array<string,mixed> $row
     */
    private function opSet2OperationRowIsVisible(array $row): bool
    {
        return ! is_array($row['succs'] ?? null) || count($row['succs']) === 0;
    }

    private function opSet2StringOperationId(mixed $operationId): string
    {
        $id = $this->opSet2NormalizeOperationId($operationId);

        return $id['counter'] . '@' . $id['actor'];
    }

    private function opSet2StringObjectId(mixed $objectId): string
    {
        $id = $this->opSet2NormalizeObjectId($objectId);
        if ($id['type'] === 'root') {
            return 'root';
        }

        return $id['counter'] . '@' . $id['actor'];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{type:string,value:string|int}
     */
    private function opSet2ParentProp(array $row): array
    {
        $key = $row['key'] ?? null;
        if (($row['insert'] ?? false) === true || is_int($key)) {
            return ['type' => 'seq', 'value' => is_int($key) ? $key : (int) $key];
        }

        return ['type' => 'map', 'value' => is_string($key) ? $key : (string) $key];
    }

    private function nonNullIntColumnValue(?int $value, string $column): int
    {
        if ($value === null) {
            throw new \InvalidArgumentException('Operation column ' . $column . ' cannot contain null entries.');
        }

        return $value;
    }

    private function nonNullStringColumnValue(?string $value, string $column): string
    {
        if ($value === null) {
            throw new \InvalidArgumentException('Operation column ' . $column . ' cannot contain null entries.');
        }

        return $value;
    }

    /**
     * @param array{bytes:string,ranges:array{meta:array{0:int,1:int},raw:array{0:int,1:int}}} $encoded
     * @param list<array<string,mixed>> $replacement
     * @return array{bytes:string,ranges:array{meta:array{0:int,1:int},raw:array{0:int,1:int}}}
     */
    public function columnarSpliceScalarValues(array $encoded, int $start, int $deleteCount, array $replacement): array
    {
        $values = $this->columnarDecodeScalarValues($encoded);
        $count = count($values);
        if ($start < 0 || $deleteCount < 0 || $start > $count || $start + $deleteCount > $count) {
            throw new \OutOfBoundsException('Scalar value splice range is out of bounds.');
        }

        $replacement = $this->columnarDecodeScalarValues($this->columnarEncodeScalarValues($replacement));
        array_splice($values, $start, $deleteCount, $replacement);

        return $this->columnarEncodeScalarValues($values);
    }

    /**
     * @return array{0:int,1:int}|null
     */
    public function textDiffFindMiddleSnake(string $old, int $oldStart, int $oldEnd, string $new, int $newStart, int $newEnd): ?array
    {
        [$oldStart, $oldEnd] = $this->normalizeByteRange($old, $oldStart, $oldEnd);
        [$newStart, $newEnd] = $this->normalizeByteRange($new, $newStart, $newEnd);
        $n = max(0, $oldEnd - $oldStart);
        $m = max(0, $newEnd - $newStart);
        $delta = $n - $m;
        $odd = ($delta & 1) === 1;
        $vf = [1 => 0];
        $vb = [1 => 0];
        $dMax = $this->textDiffMaxD($n, $m);

        for ($d = 0; $d < $dMax; ++$d) {
            for ($k = $d; $k >= -$d; $k -= 2) {
                $x = $k === -$d || ($k !== $d && ($vf[$k - 1] ?? 0) < ($vf[$k + 1] ?? 0))
                    ? ($vf[$k + 1] ?? 0)
                    : ($vf[$k - 1] ?? 0) + 1;
                $y = $x - $k;
                [$x0, $y0] = [$x, $y];
                if ($x < $n && $y >= 0 && $y < $m) {
                    $x += $this->textDiffCommonPrefixLen(
                        $old,
                        $oldStart + $x,
                        $oldEnd,
                        $new,
                        $newStart + $y,
                        $newEnd
                    );
                }

                $vf[$k] = $x;
                if ($odd && abs($k - $delta) <= $d - 1 && $x + ($vb[-($k - $delta)] ?? 0) >= $n) {
                    return [$x0 + $oldStart, $y0 + $newStart];
                }
            }

            for ($k = $d; $k >= -$d; $k -= 2) {
                $x = $k === -$d || ($k !== $d && ($vb[$k - 1] ?? 0) < ($vb[$k + 1] ?? 0))
                    ? ($vb[$k + 1] ?? 0)
                    : ($vb[$k - 1] ?? 0) + 1;
                $y = $x - $k;
                if ($x < $n && $y >= 0 && $y < $m) {
                    $advance = $this->textDiffCommonSuffixLen(
                        $old,
                        $oldStart,
                        $oldStart + $n - $x,
                        $new,
                        $newStart,
                        $newStart + $m - $y
                    );
                    $x += $advance;
                    $y += $advance;
                }

                $vb[$k] = $x;
                if (! $odd && abs($k - $delta) <= $d && $x + ($vf[-($k - $delta)] ?? 0) >= $n) {
                    return [$n - $x + $oldStart, $m - $y + $newStart];
                }
            }
        }

        return null;
    }

    /**
     * @param list<string|int> $path
     */
    public function spliceTextEncoded(Document $document, array $path, int $index, int $deleteCount, string $insert, string $encoding): Document
    {
        $path = array_values($path);
        $value = $this->readPath($document, $path);
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Encoded text splice requires a text value.');
        }

        $rawValue = $this->readRawPath($document, $path);
        if ($rawValue instanceof TextValue) {
            $measure = $this->encodedTextMeasure($encoding);
            [$clusterIndex, $encodedStart] = $rawValue->elementBoundaryAfterMeasuredIndex($index, $measure);
            $clusterDeleteCount = 0;
            if ($deleteCount > 0) {
                [$clusterEnd] = $rawValue->elementBoundaryAfterMeasuredIndex($encodedStart + $deleteCount, $measure);
                $clusterDeleteCount = max(0, $clusterEnd - $clusterIndex);
            }

            return $this->spliceAtPath($document, $path, $clusterIndex, $clusterDeleteCount, $insert);
        }

        [$clusterIndex, $encodedStart] = $this->clusterBoundaryAfterEncodedIndex($value, $index, $encoding);
        $clusterDeleteCount = 0;
        if ($deleteCount > 0) {
            [$clusterEnd] = $this->clusterBoundaryAfterEncodedIndex($value, $encodedStart + $deleteCount, $encoding);
            $clusterDeleteCount = max(0, $clusterEnd - $clusterIndex);
        }

        return $this->spliceAtPath($document, $path, $clusterIndex, $clusterDeleteCount, $insert);
    }

    /**
     * @param list<string|int> $path
     */
    public function textAtEncodedIndex(Document $document, array $path, int $index, string $encoding): ?string
    {
        $path = array_values($path);
        $value = $this->readPath($document, $path);
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Encoded text get requires a text value.');
        }

        $clusters = $this->splitCharacters($value);
        $clusterIndex = $this->clusterIndexFromEncodedIndex($value, $index, $encoding);

        return $clusters[$clusterIndex] ?? null;
    }

    /**
     * @param list<string|int> $path
     */
    public function putTextEncoded(Document $document, array $path, int $index, string $value, string $encoding): Document
    {
        $path = array_values($path);
        $text = $this->readPath($document, $path);
        if (! is_string($text)) {
            throw new \InvalidArgumentException('Encoded text put requires a text value.');
        }

        $clusterIndex = $this->clusterIndexFromEncodedIndex($text, $index, $encoding);
        if (count($path) === 1 && (is_string($path[0]) || is_int($path[0]))) {
            $next = $this->mutableClone($document);
            $next->putText((string) $path[0], $clusterIndex, $value);

            return $this->preserveFrozen($document, $next);
        }

        return $this->spliceAtPath($document, $path, $clusterIndex, 1, $value);
    }

    /**
     * @param list<string|int> $path
     */
    public function insertTextEncoded(Document $document, array $path, int $index, string $value, string $encoding): Document
    {
        return $this->spliceTextEncoded($document, $path, $index, 0, $value, $encoding);
    }

    /**
     * @param list<string|int> $path
     */
    public function deleteTextEncoded(Document $document, array $path, int $index, string $encoding): Document
    {
        $path = array_values($path);
        $text = $this->readPath($document, $path);
        if (! is_string($text)) {
            throw new \InvalidArgumentException('Encoded text delete requires a text value.');
        }

        return $this->spliceAtPath($document, $path, $this->clusterIndexFromEncodedIndex($text, $index, $encoding), 1, '');
    }

    /**
     * @param list<string|int> $path
     */
    public function spliceAtPath(Document $document, array $path, int|string $index, int $deleteCount, string $insert = ''): Document
    {
        $path = array_values($path);
        $this->assertTextOperationTarget($document, $path);
        [$index, $deleteLength] = $this->resolveTextSpliceRange($document, $path, $index, $deleteCount);
        $deleteIndex = $index;

        if ($deleteLength === 0 && $insert === '') {
            return $document;
        }

        if (count($path) === 1 && (is_string($path[0]) || is_int($path[0]))) {
            $next = $this->mutableClone($document);
            $next->spliceText((string) $path[0], $index, $deleteLength, $insert);

            return $this->preserveFrozen($document, $next);
        }

        $patches = [];
        if ($deleteLength > 0) {
            $patches[] = ['action' => 'del', 'path' => array_merge($path, [$deleteIndex]), 'length' => $deleteLength];
        }

        if ($insert !== '') {
            $patches[] = ['action' => 'splice', 'path' => array_merge($path, [$index]), 'value' => $insert];
        }

        return $patches === [] ? $document : $this->applyPatches($document, $patches);
    }

    /**
     * @param list<string|int> $path
     */
    public function updateTextAtPath(Document $document, array $path, string $newText): Document
    {
        return $this->writePath($document, array_values($path), $newText);
    }

    /**
     * @param list<string|int> $path
     */
    public function getCursor(Document $document, array $path, int|string $index, string $move = 'after'): string
    {
        $path = array_values($path);
        $text = $this->cursorTextAtPath($document, $path);
        $length = $this->utf16Length($text);
        $kind = 'index';
        $clusterIndex = 0;

        if ($index === 'start' || (is_int($index) && $index < 0)) {
            $kind = 'start';
            $index = 0;
        } elseif ($index === 'end' || (is_int($index) && $index >= $length)) {
            $kind = 'end';
            $index = $length;
        } else {
            $index = max(0, min((int) $index, $length));
            $clusterIndex = $this->clusterIndexFromUtf16Index($text, $index);
        }

        return base64_encode(json_encode(
            [
                'path' => $path,
                'index' => $clusterIndex,
                'utf16Index' => $index,
                'kind' => $kind,
                'move' => $move === 'before' ? 'before' : 'after',
                'text' => $text,
            ],
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * @param list<string|int> $path
     */
    public function getCursorEncoded(Document $document, array $path, int|string $index, string $encoding, string $move = 'after'): string
    {
        $path = array_values($path);
        $text = $this->cursorTextAtPath($document, $path);
        $clusters = $this->splitCharacters($text);
        $length = $this->encodedIndexForClusterIndex($text, count($clusters), $encoding);
        $kind = 'index';
        $clusterIndex = 0;
        $encodedIndex = 0;

        if ($index === 'start' || (is_int($index) && $index < 0)) {
            $kind = 'start';
        } elseif ($index === 'end' || (is_int($index) && $index >= $length)) {
            $kind = 'end';
            $clusterIndex = count($clusters);
            $encodedIndex = $length;
        } else {
            $encodedIndex = max(0, min((int) $index, $length));
            $clusterIndex = $this->clusterIndexFromEncodedIndex($text, $encodedIndex, $encoding);
        }

        return base64_encode(json_encode(
            [
                'path' => $path,
                'index' => $clusterIndex,
                'encodedIndex' => $encodedIndex,
                'encoding' => $encoding,
                'kind' => $kind,
                'move' => $move === 'before' ? 'before' : 'after',
                'text' => $text,
            ],
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * @param list<string|int> $path
     */
    public function getCursorPosition(Document $document, array $path, string $cursor): int
    {
        $decoded = json_decode(base64_decode($cursor, true) ?: '', true);
        if (! is_array($decoded)) {
            return 0;
        }

        $path = array_values($path);
        $clusterIndex = $this->cursorPositionFromPayload($document, $path, $decoded);

        return $this->utf16IndexForClusterIndex($this->cursorTextAtPath($document, $path), $clusterIndex);
    }

    /**
     * @param list<string|int> $path
     */
    public function getCursorPositionEncoded(Document $document, array $path, string $cursor, string $encoding): int
    {
        $decoded = json_decode(base64_decode($cursor, true) ?: '', true);
        if (! is_array($decoded)) {
            return 0;
        }

        $path = array_values($path);
        $clusterIndex = $this->cursorPositionFromPayload($document, $path, $decoded);

        return $this->encodedIndexForClusterIndex($this->cursorTextAtPath($document, $path), $clusterIndex, $encoding);
    }

    /**
     * @param list<string|int> $path
     */
    public function markTextEncoded(Document $document, array $path, int $start, int $end, string $name, mixed $value, string $encoding, string $expand = 'none'): Document
    {
        $path = array_values($path);
        $text = $this->readPath($document, $path);
        if (! is_string($text)) {
            throw new \InvalidArgumentException('Encoded text mark requires a text value.');
        }

        return $this->mark(
            $document,
            $path,
            $this->clusterIndexFromEncodedIndex($text, $start, $encoding),
            $this->clusterIndexFromEncodedIndex($text, $end, $encoding),
            $name,
            $value,
            $expand
        );
    }

    /**
     * @param list<string|int> $path
     */
    public function unmarkTextEncoded(Document $document, array $path, int $start, int $end, string $name, string $encoding): Document
    {
        $path = array_values($path);
        $text = $this->readPath($document, $path);
        if (! is_string($text)) {
            throw new \InvalidArgumentException('Encoded text unmark requires a text value.');
        }

        return $this->unmark(
            $document,
            $path,
            $this->clusterIndexFromEncodedIndex($text, $start, $encoding),
            $this->clusterIndexFromEncodedIndex($text, $end, $encoding),
            $name
        );
    }

    /**
     * @param list<string|int> $path
     * @return list<array{name:string,value:mixed,start:int,end:int}>
     */
    public function marksEncoded(Document $document, array $path, string $encoding): array
    {
        $path = array_values($path);
        $text = $this->readPath($document, $path);
        if (! is_string($text)) {
            throw new \InvalidArgumentException('Encoded text marks require a text value.');
        }

        return array_map(
            function (array $mark) use ($text, $encoding): array {
                $mark['start'] = $this->encodedIndexForClusterIndex($text, $mark['start'], $encoding);
                $mark['end'] = $this->encodedIndexForClusterIndex($text, $mark['end'], $encoding);

                return $mark;
            },
            $this->marks($document, $path)
        );
    }

    /**
     * @param list<string|int> $path
     */
    public function mark(Document $document, array $path, int $start, int $end, string $name, mixed $value, string $expand = 'none'): Document
    {
        if ($value === null) {
            return $this->unmark($document, $path, $start, $end, $name);
        }

        $expand = $this->normalizeExpandMode($expand, 'none');
        $next = $this->mutableClone($document);
        $next->markText($path, [['name' => $name, 'value' => $value, 'start' => $start, 'end' => $end, 'expand' => $expand]]);
        $next = $this->preserveFrozen($document, $next);
        $this->emitPatches($document, $next, [
            ['action' => 'mark', 'path' => array_values($path), 'marks' => [['name' => $name, 'value' => $value, 'start' => $start, 'end' => $end]]],
        ]);

        return $next;
    }

    /**
     * @param list<string|int> $path
     */
    public function unmark(Document $document, array $path, int $start, int $end, string $name): Document
    {
        $next = $this->mutableClone($document);
        $next->unmarkText($path, $name, $start, $end);
        $next = $this->preserveFrozen($document, $next);
        $this->emitPatches($document, $next, [
            ['action' => 'mark', 'path' => array_values($path), 'marks' => [['name' => $name, 'value' => null, 'start' => $start, 'end' => $end]]],
        ]);

        return $next;
    }

    /**
     * @param list<string|int> $path
     * @param array{parents?:list<string|ImmutableString>,type?:string|ImmutableString,attrs?:array<string,mixed>} $block
     */
    public function splitBlock(Document $document, array $path, int $index, array $block): Document
    {
        $path = array_values($path);
        if (count($path) !== 1 || (! is_string($path[0]) && ! is_int($path[0]))) {
            throw new \InvalidArgumentException('splitBlock currently supports a root text path.');
        }

        $index = max(0, $index);
        $next = $this->mutableClone($document);
        $next->spliceText((string) $path[0], $index, 0, self::BLOCK_CHARACTER);
        $next->markText($path, [[
            'name' => self::BLOCK_MARK_NAME,
            'value' => $this->normalizeBlock($block),
            'start' => $index,
            'end' => $index + 1,
            'expand' => 'none',
        ]]);
        $next = $this->preserveFrozen($document, $next);
        $this->emitPatches($document, $next, [[
            'action' => 'insert',
            'path' => array_merge($path, [$index]),
            'values' => [[]],
        ]]);

        return $next;
    }

    /**
     * @param list<string|int> $path
     * @param array{parents?:list<string|ImmutableString>,type?:string|ImmutableString,attrs?:array<string,mixed>} $block
     */
    public function splitBlockEncoded(Document $document, array $path, int $index, string $encoding, array $block = []): Document
    {
        $path = array_values($path);
        $text = $this->readPath($document, $path);
        if (! is_string($text)) {
            throw new \InvalidArgumentException('Encoded splitBlock requires a text value.');
        }

        return $this->splitBlock($document, $path, $this->clusterIndexFromEncodedIndex($text, $index, $encoding), $block);
    }

    /**
     * @param list<string|int> $path
     */
    public function joinBlock(Document $document, array $path, int $index): Document
    {
        $path = array_values($path);
        if (count($path) !== 1 || (! is_string($path[0]) && ! is_int($path[0]))) {
            throw new \InvalidArgumentException('joinBlock currently supports a root text path.');
        }

        if ($this->block($document, $path, $index) === null) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->spliceText((string) $path[0], max(0, $index), 1, '');
        $next = $this->preserveFrozen($document, $next);
        $this->emitPatches($document, $next, [[
            'action' => 'del',
            'path' => array_merge($path, [max(0, $index)]),
            'length' => 1,
        ]]);

        return $next;
    }

    /**
     * @param list<string|int> $path
     * @return array{parents:list<string|ImmutableString>,type:string|ImmutableString,attrs:array<string,mixed>}|null
     */
    public function block(Document $document, array $path, int $index): ?array
    {
        foreach ($this->blockMarks($document, $path) as $mark) {
            if ($index >= $mark['start'] && $index < $mark['end'] && is_array($mark['value'])) {
                return $this->normalizeBlock($mark['value']);
            }
        }

        return null;
    }

    /**
     * @param list<string|int> $path
     * @return list<array{type:string,value:mixed,marks?:array<string,mixed>}>
     */
    public function spans(Document $document, array $path): array
    {
        $text = $this->readPath($document, array_values($path));
        if (! is_string($text) || $text === '') {
            return [];
        }

        $blocksByIndex = [];
        foreach ($this->blockMarks($document, $path) as $mark) {
            if (is_array($mark['value'])) {
                $blocksByIndex[(int) $mark['start']] = $this->normalizeBlock($mark['value']);
            }
        }

        $spans = [];
        $buffer = '';
        $bufferMarks = [];
        foreach ($this->splitCharacters($text) as $index => $character) {
            if ($character === self::BLOCK_CHARACTER && isset($blocksByIndex[$index])) {
                if ($buffer !== '') {
                    $span = ['type' => 'text', 'value' => $buffer];
                    if ($bufferMarks !== []) {
                        $span['marks'] = $bufferMarks;
                    }
                    $spans[] = $span;
                    $buffer = '';
                    $bufferMarks = [];
                }

                $spans[] = ['type' => 'block', 'value' => $blocksByIndex[$index]];
                continue;
            }

            $marks = $this->marksAt($document, $path, $index);
            if ($buffer !== '' && $marks !== $bufferMarks) {
                $span = ['type' => 'text', 'value' => $buffer];
                if ($bufferMarks !== []) {
                    $span['marks'] = $bufferMarks;
                }
                $spans[] = $span;
                $buffer = '';
            }

            $buffer .= $character;
            $bufferMarks = $marks;
        }

        if ($buffer !== '') {
            $span = ['type' => 'text', 'value' => $buffer];
            if ($bufferMarks !== []) {
                $span['marks'] = $bufferMarks;
            }
            $spans[] = $span;
        }

        return $spans;
    }

    /**
     * @param list<string|int> $path
     * @param list<array{type:string,value:mixed,marks?:array<string,mixed>}> $spans
     * @param array{defaultExpand?:string,perMarkExpand?:array<string,string>} $options
     */
    public function updateSpans(Document $document, array $path, array $spans, array $options = []): Document
    {
        $path = array_values($path);
        if (count($path) !== 1 || (! is_string($path[0]) && ! is_int($path[0]))) {
            throw new \InvalidArgumentException('updateSpans currently supports a root text path.');
        }

        $text = '';
        $marks = [];
        $index = 0;
        $beforeBlocks = $this->blockValuesByIndex($document, $path);
        $desiredSpans = [];
        foreach ($spans as $span) {
            if (($span['type'] ?? null) === 'block' && is_array($span['value'] ?? null)) {
                $block = $this->normalizeBlock($span['value']);
                $text .= self::BLOCK_CHARACTER;
                $marks[] = [
                    'name' => self::BLOCK_MARK_NAME,
                    'value' => $block,
                    'start' => $index,
                    'end' => $index + 1,
                    'expand' => 'none',
                ];
                $desiredSpans[] = ['type' => 'block', 'value' => $block];
                ++$index;
                continue;
            }

            if (($span['type'] ?? null) !== 'text') {
                continue;
            }

            $value = is_string($span['value'] ?? null) ? $span['value'] : '';
            $start = $index;
            $text .= $value;
            $index += count($this->splitCharacters($value));
            $spanMarks = is_array($span['marks'] ?? null) ? $span['marks'] : [];
            $desiredSpan = ['type' => 'text', 'value' => $value];
            if ($spanMarks !== []) {
                $desiredSpan['marks'] = $spanMarks;
            }
            $desiredSpans[] = $desiredSpan;

            if ($spanMarks === []) {
                continue;
            }

            foreach ($spanMarks as $name => $markValue) {
                if (! is_string($name) || $start === $index) {
                    continue;
                }

                $marks[] = [
                    'name' => $name,
                    'value' => $markValue,
                    'start' => $start,
                    'end' => $index,
                    'expand' => $this->expandForMark($name, $options),
                ];
            }
        }

        if ($this->spans($document, $path) === $desiredSpans) {
            return $document;
        }

        $next = $this->mutableClone($document);
        $next->replaceTextAndMarks((string) $path[0], $text, $marks);
        $next = $this->preserveFrozen($document, $next);
        $this->emitPatches($document, $next, $this->blockUpdateSpanPatches($path, $beforeBlocks, $this->blockValuesByIndex($next, $path)));

        return $next;
    }

    /**
     * @param list<string|int> $path
     * @return array<string,mixed>
     */
    public function marksAt(Document $document, array $path, int $index): array
    {
        $active = [];
        foreach ($this->marks($document, $path) as $mark) {
            if ($mark['start'] <= $index && $index < $mark['end']) {
                $active[$mark['name']] = $mark['value'];
            }
        }

        ksort($active, SORT_STRING);

        return $active;
    }

    /**
     * @param list<string|int> $path
     * @param list<string> $heads
     * @return array<string,mixed>
     */
    public function marksAtHeads(Document $document, array $path, int $index, array $heads): array
    {
        $this->validateDiffHeads($document, $heads, 'marksAtHeads');

        return $this->marksAt($document->view(array_values($heads)), $path, $index);
    }

    public function mergeDocuments(Document $left, Document $right): Document
    {
        $merged = $left->merge($right);
        $this->emitDocumentPatchCallback($left, $merged, 'merge');

        return $merged;
    }

    /**
     * @param list<string> $heads
     */
    public function setAtHeads(Document $document, array $heads, string $key, mixed $value): Document
    {
        $this->validateDiffHeads($document, $heads, 'changeAt');

        $next = $this->mutableClone($document);
        $next->setAtHeads($heads, $key, $value);
        $next = $this->preserveFrozen($document, $next);
        $this->emitDocumentPatchCallback($document, $next, 'changeAt');

        return $next;
    }

    /**
     * @param list<string> $heads
     */
    public function spliceAtHeads(Document $document, array $heads, string $key, int $index, int $deleteCount, string $insert = ''): Document
    {
        $this->validateDiffHeads($document, $heads, 'changeAt');

        $historical = $document->view($heads)->withFrozen(false);
        $historical->ensureSequenceAtLeast($document->stats()['sequence']);
        $historical->spliceText($key, $index, $deleteCount, $insert);

        $next = $this->preserveFrozen($document, $document->merge($historical));
        $this->emitDocumentPatchCallback($document, $next, 'changeAt');

        return $next;
    }

    /**
     * @param list<string> $heads
     */
    public function updateTextAtHeads(Document $document, array $heads, string $key, string $newText): Document
    {
        $this->validateDiffHeads($document, $heads, 'changeAt');

        $historical = $document->view($heads)->withFrozen(false);
        $historical->ensureSequenceAtLeast($document->stats()['sequence']);
        $historical->updateText($key, $newText);

        $next = $this->preserveFrozen($document, $document->merge($historical));
        $this->emitDocumentPatchCallback($document, $next, 'changeAt');

        return $next;
    }

    /**
     * @param list<string> $heads
     */
    public function emptyChangeAtHeads(Document $document, array $heads): Document
    {
        $this->validateDiffHeads($document, $heads, 'changeAt');

        return $document;
    }

    /**
     * @param list<array<string,mixed>> $patches
     */
    public function applyPatches(Document $document, array $patches): Document
    {
        $next = $document;
        foreach ($patches as $patch) {
            if (! is_string($patch['action'] ?? null) || ! is_array($patch['path'] ?? null)) {
                continue;
            }

            $path = array_values($patch['path']);
            if ($patch['action'] === 'put') {
                $next = $this->writePath($next, $path, $patch['value'] ?? null);
                continue;
            }

            if ($patch['action'] === 'mark') {
                $marks = is_array($patch['marks'] ?? null) ? array_values($patch['marks']) : [];
                if ($marks !== []) {
                    $next = $this->mutableClone($next);
                    $addMarks = [];
                    foreach ($marks as $mark) {
                        if (! is_array($mark) || ! is_string($mark['name'] ?? null)) {
                            continue;
                        }

                        if (array_key_exists('value', $mark) && $mark['value'] === null) {
                            $next->unmarkText(
                                $path,
                                $mark['name'],
                                (int) ($mark['start'] ?? 0),
                                (int) ($mark['end'] ?? $mark['start'] ?? 0)
                            );
                            continue;
                        }

                        $addMarks[] = $mark;
                    }

                    if ($addMarks !== []) {
                        $next->markText($path, $addMarks);
                    }
                    $next = $this->preserveFrozen($document, $next);
                }

                continue;
            }

            if ($patch['action'] === 'unmark' && is_string($patch['name'] ?? null)) {
                $next = $this->mutableClone($next);
                $next->unmarkText(
                    $path,
                    $patch['name'],
                    (int) ($patch['start'] ?? 0),
                    (int) ($patch['end'] ?? $patch['start'] ?? 0)
                );
                $next = $this->preserveFrozen($document, $next);
                continue;
            }

            if ($patch['action'] === 'inc') {
                $current = $this->readPath($next, $path);
                $amount = $patch['value'] ?? null;
                if ($current instanceof Counter && is_int($amount)) {
                    $next = $this->incrementCounter($next, $path, $amount);
                    continue;
                }

                if ((is_int($current) || is_float($current)) && (is_int($amount) || is_float($amount))) {
                    $next = $this->writePath($next, $path, $current + $amount);
                }

                continue;
            }

            if (
                ($patch['action'] === 'insert' || $patch['action'] === 'splice' || $patch['action'] === 'del')
                && count($path) >= 2
            ) {
                $index = array_pop($path);
                if (! is_int($index)) {
                    continue;
                }

                $value = $this->readPath($next, $path);
                if (is_array($value)) {
                    if ($patch['action'] === 'splice') {
                        continue;
                    }

                    if ($patch['action'] === 'insert') {
                        $values = is_array($patch['values'] ?? null) ? array_values($patch['values']) : [];
                        array_splice($value, $index, 0, $values);
                    } else {
                        $length = max(1, (int) ($patch['length'] ?? 1));
                        array_splice($value, $index, $length);
                    }

                    $next = $this->writePath($next, $path, array_values($value));
                    continue;
                }

                if (is_string($value) && ($patch['action'] === 'splice' || $patch['action'] === 'del')) {
                    $characters = $this->splitCharacters($value);
                    if ($patch['action'] === 'splice') {
                        $insert = is_string($patch['value'] ?? null) ? $this->splitCharacters($patch['value']) : [];
                        array_splice($characters, $index, 0, $insert);
                    } else {
                        $length = max(1, (int) ($patch['length'] ?? 1));
                        array_splice($characters, $index, $length);
                    }

                    $next = $this->writePath($next, $path, implode('', $characters));
                    continue;
                }
            }
        }

        return $next;
    }

    /**
     * @param array<string|int,mixed> $document
     * @param list<array<string,mixed>> $patches
     * @return array<string|int,mixed>
     */
    public function applyPatchesToArray(array $document, array $patches): array
    {
        $next = $document;
        foreach ($patches as $patch) {
            if (! is_string($patch['action'] ?? null) || ! is_array($patch['path'] ?? null)) {
                continue;
            }

            $path = array_values($patch['path']);
            if ($patch['action'] === 'put') {
                $next = $this->writeArrayPath($next, $path, $patch['value'] ?? null);
                continue;
            }

            if ($patch['action'] === 'inc') {
                $current = $this->readArrayPath($next, $path);
                $amount = $patch['value'] ?? null;
                if ((is_int($current) || is_float($current)) && (is_int($amount) || is_float($amount))) {
                    $next = $this->writeArrayPath($next, $path, $current + $amount);
                }

                continue;
            }

            if (
                ($patch['action'] === 'insert' || $patch['action'] === 'splice' || $patch['action'] === 'del')
                && count($path) >= 2
            ) {
                $index = array_pop($path);
                if (! is_int($index)) {
                    continue;
                }

                $value = $this->readArrayPath($next, $path);
                if (is_array($value)) {
                    if ($patch['action'] === 'splice') {
                        continue;
                    }

                    if ($patch['action'] === 'insert') {
                        $values = is_array($patch['values'] ?? null) ? array_values($patch['values']) : [];
                        array_splice($value, $index, 0, $values);
                    } else {
                        $length = max(1, (int) ($patch['length'] ?? 1));
                        array_splice($value, $index, $length);
                    }

                    $next = $this->writeArrayPath($next, $path, array_values($value));
                    continue;
                }

                if (is_string($value) && ($patch['action'] === 'splice' || $patch['action'] === 'del')) {
                    $characters = $this->splitCharacters($value);
                    if ($patch['action'] === 'splice') {
                        $insert = is_string($patch['value'] ?? null) ? $this->splitCharacters($patch['value']) : [];
                        array_splice($characters, $index, 0, $insert);
                    } else {
                        $length = max(1, (int) ($patch['length'] ?? 1));
                        array_splice($characters, $index, $length);
                    }

                    $next = $this->writeArrayPath($next, $path, implode('', $characters));
                    continue;
                }
            }
        }

        return $next;
    }

    /**
     * @return list<string>
     */
    public function getHeads(Document $document): array
    {
        return $document->heads();
    }

    /**
     * @param list<string> $heads
     */
    public function hasHeads(Document $document, array $heads): bool
    {
        return $document->hasHeads($heads);
    }

    public function emptyChange(Document $document, ?string $message = null): Document
    {
        $next = $this->mutableClone($document);
        $next->emptyChange($message);

        return $this->preserveFrozen($document, $next);
    }

    public function emptyChangeWithTime(Document $document, int $time, ?string $message = null): Document
    {
        $next = $this->mutableClone($document);
        $next->emptyChange($message, $time);

        return $this->preserveFrozen($document, $next);
    }

    public function emptyChangeWithoutTime(Document $document, ?string $message = null): Document
    {
        return $this->emptyChangeWithTime($document, 0, $message);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getAllChanges(Document $document): array
    {
        return $document->getAllChanges();
    }

    /**
     * @param list<string> $heads
     * @return list<array<string,mixed>>
     */
    public function getChangesSince(Document $document, array $heads): array
    {
        return $document->getChangesSince($heads);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getChanges(Document $before, Document $after): array
    {
        return $after->getChangesSince($before->heads());
    }

    /**
     * @param list<string> $heads
     * @return list<string>
     */
    public function getMissingDeps(Document $document, array $heads = []): array
    {
        $known = array_fill_keys($this->normalizeHeads($heads), true);
        foreach ($document->getAllChanges() as $change) {
            if (is_string($change['hash'] ?? null)) {
                $known[$change['hash']] = true;
            }
        }

        $missing = [];
        foreach ($document->getAllChanges() as $change) {
            foreach ($this->normalizeHeads($change['deps'] ?? []) as $dep) {
                if (! isset($known[$dep]) && ! isset($missing[$dep])) {
                    $missing[$dep] = true;
                }
            }
        }

        return array_keys($missing);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function diff(Document $document, mixed $beforeHeads, mixed $afterHeads): array
    {
        $beforeHeads = $this->validateDiffHeads($document, $beforeHeads, 'before');
        $afterHeads = $this->validateDiffHeads($document, $afterHeads, 'after');
        $beforeView = $document->view($beforeHeads);
        $afterView = $document->view($afterHeads);

        return $this->diffValues(
            $beforeView->toArray(),
            $afterView->toArray(),
            [],
            $this->replacementContainerPaths($document, $beforeHeads, $afterHeads),
            $this->marksByPathKey($afterView)
        );
    }

    /**
     * @return list<string>
     */
    public function diffCursor(Document $document): array
    {
        if (! $this->documentDiffCursors->contains($document)) {
            return [];
        }

        $cursor = $this->documentDiffCursors[$document];

        return is_array($cursor) ? array_values($cursor) : [];
    }

    public function updateDiffCursor(Document $document): void
    {
        $this->documentDiffCursors[$document] = $document->heads();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function diffIncremental(Document $document): array
    {
        $heads = $document->heads();
        $patches = $this->diff($document, $this->diffCursor($document), $heads);
        $this->updateDiffCursor($document);

        return $patches;
    }

    /**
     * @param list<string|int> $textPath
     * @return list<array<string,mixed>>
     */
    public function diffIncrementalEncoded(Document $document, array $textPath, string $encoding): array
    {
        $patches = $this->patchesForChanges($document, $document->getChangesSince($this->diffCursor($document)));
        $this->updateDiffCursor($document);

        return $this->encodeTextPatchIndexes($document, $patches, array_values($textPath), $encoding);
    }

    /**
     * @param list<string|int> $path
     * @param array{recursive?:bool} $options
     * @return list<array<string,mixed>>
     */
    public function diffPath(Document $document, array $path, mixed $beforeHeads, mixed $afterHeads, array $options = []): array
    {
        $beforeHeads = $this->validateDiffHeads($document, $beforeHeads, 'before');
        $afterHeads = $this->validateDiffHeads($document, $afterHeads, 'after');
        $path = array_values($path);

        $before = $this->readArrayPath($document->view($beforeHeads)->toArray(), $path);
        $after = $this->readArrayPath($document->view($afterHeads)->toArray(), $path);
        $replacementPaths = $this->replacementContainerPaths($document, $beforeHeads, $afterHeads);
        $patches = $before === null && is_array($after)
            ? $this->diffContainerContents($after, $path, $replacementPaths)
            : $this->diffValues($before, $after, $path, $replacementPaths);

        if (($options['recursive'] ?? true) === false) {
            $maxDepth = count($path) + 1;
            $patches = array_values(array_filter(
                $patches,
                static fn (array $patch): bool => is_array($patch['path'] ?? null) && count($patch['path']) <= $maxDepth
            ));
        }

        return $patches;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getConflicts(mixed $object, string|int $key): ?array
    {
        if ($object instanceof Document) {
            return $object->conflictsFor($key);
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getListElementConflicts(Document $document, string $key, int $index): ?array
    {
        return $document->listElementConflictsFor($key, $index);
    }

    /**
     * @param list<string|int> $path
     * @return list<array{name:string,value:mixed,start:int,end:int}>
     */
    public function marks(Document $document, array $path): array
    {
        return array_values(array_filter(
            $document->marksFor($path),
            static fn (array $mark): bool => ($mark['name'] ?? null) !== self::BLOCK_MARK_NAME
        ));
    }

    /**
     * @param array{readOnly?:bool,peerReadOnly?:bool,requestedHeads?:list<string>,needHeads?:list<string>,inFlightHashes?:list<string>,theirCapabilities?:list<string>|null} $options
     * @return array{sentHeads:list<string>|null,lastSentHeads:list<string>|null,lastSentReadOnly:bool|null,receivedHeads:list<string>|null,sharedHeads:list<string>,requestedHeads:list<string>,needHeads:list<string>,inFlightHashes:list<string>,readOnly:bool,peerReadOnly:bool,theirCapabilities:list<string>|null}
     */
    public function initSyncState(array $options = []): array
    {
        $theirCapabilities = array_key_exists('theirCapabilities', $options)
            ? $this->normalizeSyncCapabilities($options['theirCapabilities'])
            : ['syncReset'];

        return [
            'sentHeads' => null,
            'lastSentHeads' => null,
            'lastSentReadOnly' => null,
            'receivedHeads' => null,
            'sharedHeads' => [],
            'requestedHeads' => $this->normalizeHeads($options['requestedHeads'] ?? []),
            'needHeads' => $this->normalizeHeads($options['needHeads'] ?? []),
            'inFlightHashes' => $this->normalizeHeads($options['inFlightHashes'] ?? []),
            'readOnly' => (bool) ($options['readOnly'] ?? false),
            'peerReadOnly' => (bool) ($options['peerReadOnly'] ?? false),
            'theirCapabilities' => $theirCapabilities,
        ];
    }

    /**
     * @param array<string,mixed> $syncState
     */
    public function encodeSyncState(array $syncState): string
    {
        return json_encode(
            [
                'format' => 'wordpress-de/automerge-php-native-sync-state-v1',
                'state' => $this->normalizeSyncState($syncState),
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function decodeSyncState(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (
            ! is_array($decoded)
            || ($decoded['format'] ?? null) !== 'wordpress-de/automerge-php-native-sync-state-v1'
            || ! is_array($decoded['state'] ?? null)
        ) {
            throw new \InvalidArgumentException('Unsupported native Automerge PHP sync-state payload.');
        }

        return $this->normalizeSyncState($decoded['state']);
    }

    /**
     * @param array<string,mixed>|null $message
     */
    public function encodeSyncMessage(?array $message): string
    {
        return json_encode(
            [
                'format' => 'wordpress-de/automerge-php-native-sync-message-v1',
                'message' => $this->decodeSyncMessage($message),
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array{heads:list<string>,need:list<string>,have:list<array{lastSync:list<string>,bloom:array{byteLength:int},hashes:list<string>}>,changes:list<array<string,mixed>>,readOnly:bool,syncReset:bool}|null
     */
    public function decodeEncodedSyncMessage(string $payload): ?array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (
            ! is_array($decoded)
            || ($decoded['format'] ?? null) !== 'wordpress-de/automerge-php-native-sync-message-v1'
            || ! array_key_exists('message', $decoded)
        ) {
            throw new \InvalidArgumentException('Unsupported native Automerge PHP sync-message payload.');
        }

        $message = $decoded['message'];
        if ($message !== null && ! is_array($message)) {
            throw new \InvalidArgumentException('Unsupported native Automerge PHP sync-message payload.');
        }

        return $this->decodeSyncMessage($message);
    }

    /**
     * @param array<string,mixed> $syncState
     * @return array{0:array<string,mixed>,1:array<string,mixed>|null}
     */
    public function generateSyncMessage(Document $document, array $syncState): array
    {
        $state = $this->normalizeSyncState($syncState);
        $heads = $document->heads();
        $requestedChanges = $this->changesMatchingRequestedHeads($document, $state['requestedHeads']);
        $needHeads = $state['needHeads'];
        $readOnlyChanged = $state['lastSentReadOnly'] !== null
            && $state['lastSentReadOnly'] !== $state['readOnly'];
        $oldPeerReadWriteFallback = $state['lastSentReadOnly'] === true
            && ! $state['readOnly']
            && $state['theirCapabilities'] === null;
        $syncReset = $state['lastSentReadOnly'] === true
            && ! $state['readOnly']
            && ! $oldPeerReadWriteFallback;
        $messageHeads = $oldPeerReadWriteFallback ? [] : $heads;

        $outgoingChanges = $state['peerReadOnly']
            ? []
            : ($requestedChanges !== [] ? $requestedChanges : $this->changesWithDependenciesSince($document, $state['receivedHeads'] ?? []));
        $requestedChangesForPeer = $state['peerReadOnly'] ? [] : $requestedChanges;
        if ($requestedChangesForPeer === [] && $state['inFlightHashes'] !== []) {
            $inFlightHashes = array_fill_keys($state['inFlightHashes'], true);
            $outgoingChanges = array_values(array_filter(
                $outgoingChanges,
                static fn (array $change): bool => ! is_string($change['hash'] ?? null) || ! isset($inFlightHashes[$change['hash']])
            ));
        }
        $outgoingHashes = array_values(array_filter(
            array_map(static fn (array $change): mixed => $change['hash'] ?? null, $outgoingChanges),
            static fn (mixed $hash): bool => is_string($hash)
        ));

        if (
            $requestedChangesForPeer === []
            && $needHeads === []
            && ! $readOnlyChanged
            && $state['sentHeads'] === $heads
            && (
                $state['readOnly']
                || $state['peerReadOnly']
                || $state['receivedHeads'] === null
                || $state['receivedHeads'] === $heads
            )
        ) {
            $state['sentHeads'] = $heads;
            $state['lastSentHeads'] = $heads;
            $state['lastSentReadOnly'] = $state['readOnly'];
            return [$state, null];
        }

        $message = [
            'heads' => $messageHeads,
            'need' => $needHeads,
            'have' => [
                [
                    'lastSync' => $state['sharedHeads'],
                    'bloom' => ['byteLength' => 0],
                    'hashes' => $outgoingHashes,
                ],
            ],
            'changes' => $outgoingChanges,
            'readOnly' => $state['readOnly'],
            'syncReset' => $syncReset,
        ];
        $state['sentHeads'] = $heads;
        $state['lastSentHeads'] = $heads;
        $state['lastSentReadOnly'] = $state['readOnly'];
        $state['requestedHeads'] = [];
        $state['inFlightHashes'] = [];

        return [$state, $message];
    }

    /**
     * @param array<string,mixed> $syncState
     * @return array{0:array<string,mixed>,1:array<string,mixed>|null}
     */
    public function generateSyncMessageV1(Document $document, array $syncState): array
    {
        [$state, $message] = $this->generateSyncMessage($document, $syncState);

        return [$state, $this->syncMessageToV1($message)];
    }

    /**
     * @param array<string,mixed> $syncState
     * @param array<string,mixed>|null $message
     * @return array{0:Document,1:array<string,mixed>}
     */
    public function receiveSyncMessage(Document $document, array $syncState, ?array $message, ?callable $patchCallback = null): array
    {
        $state = $this->normalizeSyncState($syncState);
        $decoded = $this->decodeSyncMessage($message);
        if ($decoded === null) {
            return [$document, $state];
        }

        $heads = $this->normalizeHeads($decoded['heads'] ?? []);
        $need = $this->normalizeHeads($decoded['need'] ?? []);
        $alreadyHadHeads = $document->heads() === $heads;
        $changes = is_array($decoded['changes'] ?? null) ? array_values($decoded['changes']) : [];
        $next = $state['readOnly']
            ? $document
            : ($changes === [] ? $document->clone() : Document::applyChanges($document, $changes));
        $missingHeads = $state['readOnly'] ? [] : $this->missingHeads($next, $heads);
        $missingDeps = $state['readOnly'] ? [] : $this->getMissingDeps($next);
        $needHeads = $this->normalizeHeads(array_merge($missingHeads, $missingDeps));
        $sharedHeads = $needHeads === [] ? $this->minimalKnownHeads($next, $heads) : $next->heads();
        $state['receivedHeads'] = $sharedHeads;
        $state['sharedHeads'] = $sharedHeads;
        $state['requestedHeads'] = $need;
        $state['needHeads'] = $needHeads;
        $state['inFlightHashes'] = $this->normalizeHeads(array_merge(
            $state['inFlightHashes'],
            $this->hashesFromSyncHave($decoded['have'] ?? [])
        ));
        $state['peerReadOnly'] = (bool) ($decoded['readOnly'] ?? false);
        if ((bool) ($decoded['syncReset'] ?? false)) {
            $state['sentHeads'] = null;
            $state['lastSentHeads'] = null;
            $state['lastSentReadOnly'] = null;
        }
        if ($alreadyHadHeads && $state['lastSentHeads'] !== null) {
            $state['sentHeads'] = $heads;
            $state['lastSentHeads'] = $heads;
        }
        if ($patchCallback !== null) {
            $patches = $this->patchesBetweenDocuments($document, $next);
            if ($patches !== []) {
                $patchCallback($patches, ['before' => $document, 'after' => $next, 'source' => 'receiveSyncMessage']);
            }
            $this->documentPatchCallbacks[$next] = $patchCallback;
        } else {
            $this->emitDocumentPatchCallback($document, $next, 'receiveSyncMessage');
        }

        return [$next, $state];
    }

    /**
     * @param array<string,mixed> $syncState
     * @param array<string,mixed>|null $message
     * @return array{0:Document,1:array<string,mixed>}
     */
    public function receiveSyncMessageV1(Document $document, array $syncState, ?array $message): array
    {
        return $this->receiveSyncMessage($document, $syncState, $message === null ? null : $this->decodeSyncMessage($message));
    }

    /**
     * @param array<string,mixed> $syncState
     * @param array<string,mixed>|null $message
     * @return array{0:Document,1:array<string,mixed>,2:list<array<string,mixed>>}
     */
    public function receiveSyncMessageLogPatches(Document $document, array $syncState, ?array $message): array
    {
        $patches = [];
        [$next, $state] = $this->receiveSyncMessage(
            $document,
            $syncState,
            $message,
            static function (array $receivedPatches) use (&$patches): void {
                foreach ($receivedPatches as $patch) {
                    if (is_array($patch)) {
                        $patches[] = $patch;
                    }
                }
            }
        );

        return [$next, $state, $patches];
    }

    /**
     * @return array{active:bool,documentHeads:list<string>|null,patches:list<array<string,mixed>>}
     */
    public function initPatchLog(): array
    {
        return ['active' => true, 'documentHeads' => null, 'patches' => []];
    }

    /**
     * @param list<array<string,mixed>> $changes
     * @param array<string,mixed>       $patchLog
     * @return array{0:Document,1:array{active:bool,documentHeads:list<string>|null,patches:list<array<string,mixed>>},2:list<array<string,mixed>>}
     */
    public function applyChangesLogPatches(Document $document, array $changes, array $patchLog): array
    {
        $log = $this->normalizePatchLog($patchLog);
        $this->assertPatchLogMatchesDocument($document, $log);

        $next = Document::applyChanges($document, $changes);
        $patches = $this->patchesBetweenDocuments($document, $next);
        array_push($log['patches'], ...$patches);
        $log['documentHeads'] = $next->heads();

        return [$next, $log, $patches];
    }

    /**
     * @param array<string,mixed> $patchLog
     * @return list<array<string,mixed>>
     */
    public function makePatchesFromLog(array $patchLog): array
    {
        return $this->compactAdjacentListInsertPatches($this->normalizePatchLog($patchLog)['patches']);
    }

    /**
     * @param array<string,mixed> $patchLog
     */
    public function transactionLogPatches(Document $document, array $patchLog): Transaction
    {
        $this->assertPatchLogMatchesDocument($document, $this->normalizePatchLog($patchLog));

        return new Transaction($document, $this);
    }

    /**
     * @param array<string,mixed> $patchLog
     * @param list<string>        $heads
     */
    public function transactionAtLogPatches(Document $document, array $patchLog, array $heads): Transaction
    {
        $this->assertPatchLogMatchesDocument($document, $this->normalizePatchLog($patchLog));

        return $this->transactionAt($document, $heads);
    }

    /**
     * @param array<string,mixed> $patchLog
     */
    public function intoTransactionLogPatches(Document $document, array $patchLog): Transaction
    {
        $this->assertPatchLogMatchesDocument($document, $this->normalizePatchLog($patchLog));

        return new Transaction($document, $this);
    }

    /**
     * @param array<string,mixed> $syncState
     */
    public function hasOurChanges(Document $document, array $syncState): bool
    {
        $state = $this->normalizeSyncState($syncState);
        $heads = $document->heads();

        return $state['sentHeads'] === $heads
            || $state['receivedHeads'] === $heads
            || $state['sharedHeads'] === $heads;
    }

    /**
     * @param array<string,mixed>|null $message
     * @return array{heads:list<string>,need:list<string>,have:list<array{lastSync:list<string>,bloom:array{byteLength:int},hashes:list<string>}>,changes:list<array<string,mixed>>,readOnly:bool,syncReset:bool}|null
     */
    public function decodeSyncMessage(?array $message): ?array
    {
        if ($message === null) {
            return null;
        }

        $have = [];
        foreach (is_array($message['have'] ?? null) ? $message['have'] : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $byteLength = 0;
            if (is_array($entry['bloom'] ?? null)) {
                $byteLength = (int) ($entry['bloom']['byteLength'] ?? 0);
            }

            $have[] = [
                'lastSync' => $this->normalizeHeads($entry['lastSync'] ?? []),
                'bloom' => ['byteLength' => $byteLength],
                'hashes' => $this->normalizeHeads($entry['hashes'] ?? []),
            ];
        }

        return [
            'heads' => $this->normalizeHeads($message['heads'] ?? []),
            'need' => $this->normalizeHeads($message['need'] ?? []),
            'have' => $have,
            'changes' => is_array($message['changes'] ?? null) ? array_values($message['changes']) : [],
            'readOnly' => (bool) ($message['readOnly'] ?? false),
            'syncReset' => (bool) ($message['syncReset'] ?? false),
        ];
    }

    /**
     * @param array<string,mixed>|null $message
     * @return array{heads:list<string>,need:list<string>,have:list<array{lastSync:list<string>,bloom:array{byteLength:int}}>,changes:list<array<string,mixed>>}|null
     */
    public function syncMessageToV1(?array $message): ?array
    {
        $decoded = $this->decodeSyncMessage($message);
        if ($decoded === null) {
            return null;
        }

        $have = [];
        foreach ($decoded['have'] as $entry) {
            $have[] = [
                'lastSync' => $entry['lastSync'],
                'bloom' => $entry['bloom'],
            ];
        }

        return [
            'heads' => $decoded['heads'],
            'need' => $decoded['need'],
            'have' => $have,
            'changes' => $decoded['changes'],
        ];
    }

    /**
     * @param list<string> $heads
     * @return list<array<string,mixed>>
     */
    public function getChangesMetaSince(Document $document, array $heads): array
    {
        return $document->getChangesMetaSince($heads);
    }

    /**
     * @return list<array{change:array<string,mixed>,snapshot:Document}>
     */
    public function getHistory(Document $document): array
    {
        return $document->getHistory();
    }

    /**
     * @return list<string>
     */
    public function topoHistoryTraversal(Document $document): array
    {
        $hashes = [];
        foreach ($document->getAllChanges() as $change) {
            if (is_string($change['hash'] ?? null)) {
                $hashes[] = $change['hash'];
            }
        }

        return $hashes;
    }

    /**
     * @return array<string,mixed>
     */
    public function decodeChange(array $change): array
    {
        return $change;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getLastLocalChange(Document $document): ?array
    {
        return $document->getLastLocalChange();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function inspectChange(Document $document, string $hash): ?array
    {
        return $document->inspectChange($hash);
    }

    public function save(Document $document): string
    {
        return $document->save();
    }

    /**
     * @param array{retainOrphans?:bool} $options
     */
    public function saveWithOptions(Document $document, array $options = []): string
    {
        if (($options['retainOrphans'] ?? true) === false) {
            return $document->saveWithoutOrphanedChanges();
        }

        return $this->save($document);
    }

    public function saveIncremental(Document $document): string
    {
        return $document->saveIncremental();
    }

    /**
     * @param list<string> $heads
     */
    public function saveSince(Document $document, array $heads): string
    {
        return $document->saveSince($heads);
    }

    /**
     * @param list<string> $changeHashes
     */
    public function saveBundle(Document $document, array $changeHashes): string
    {
        $changesByHash = [];
        foreach ($document->getAllChanges() as $change) {
            if (is_string($change['hash'] ?? null)) {
                $changesByHash[$change['hash']] = $change;
            }
        }

        $selected = [];
        $rawChanges = [];
        $inspectableChanges = [];
        $deps = [];
        foreach ($changeHashes as $hash) {
            if (! is_string($hash)) {
                throw new \InvalidArgumentException('Bundle change hashes must be strings.');
            }

            if (isset($selected[$hash])) {
                continue;
            }

            if (! isset($changesByHash[$hash])) {
                throw new \InvalidArgumentException('Cannot save bundle for unknown change hash.');
            }

            $selected[$hash] = true;
            $change = $changesByHash[$hash];
            $rawChanges[] = $change;
            $inspectableChanges[] = $document->inspectChange($hash) ?? $change;
        }

        foreach ($rawChanges as $change) {
            foreach (is_array($change['deps'] ?? null) ? $change['deps'] : [] as $dep) {
                if (is_string($dep) && ! isset($selected[$dep])) {
                    $deps[$dep] = true;
                }
            }
        }

        $depList = array_keys($deps);
        sort($depList, SORT_STRING);

        return json_encode(
            [
                'format' => 'wordpress-de/automerge-php-native-bundle-v1',
                'deps' => $depList,
                'changes' => $inspectableChanges,
                'rawChanges' => $rawChanges,
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array{changes:list<array<string,mixed>>,deps:list<string>,rawChanges:list<array<string,mixed>>}
     */
    public function readBundle(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ($decoded['format'] ?? null) !== 'wordpress-de/automerge-php-native-bundle-v1') {
            throw new \InvalidArgumentException('Unsupported native Automerge PHP bundle payload.');
        }

        return [
            'changes' => is_array($decoded['changes'] ?? null) ? array_values($decoded['changes']) : [],
            'deps' => $this->normalizeHeads($decoded['deps'] ?? []),
            'rawChanges' => is_array($decoded['rawChanges'] ?? null) ? array_values($decoded['rawChanges']) : [],
        ];
    }

    public function loadIncremental(Document $document, string $payload): Document
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! is_string($decoded['format'] ?? null)) {
            throw new \InvalidArgumentException('Unsupported native Automerge PHP incremental payload.');
        }

        if ($decoded['format'] === 'wordpress-de/automerge-php-native-v1') {
            $after = Document::applyChanges($document, Document::load($payload)->getAllChanges());
            $this->emitDocumentPatchCallback($document, $after, 'loadIncremental');

            return $after;
        }

        if ($decoded['format'] === 'wordpress-de/automerge-php-native-bundle-v1') {
            $changes = is_array($decoded['rawChanges'] ?? null) ? array_values($decoded['rawChanges']) : [];

            $after = Document::applyChanges($document, $changes);
            $this->emitDocumentPatchCallback($document, $after, 'loadIncremental');

            return $after;
        }

        if ($decoded['format'] === 'wordpress-de/automerge-php-native-incremental-v1') {
            $changes = is_array($decoded['changes'] ?? null) ? array_values($decoded['changes']) : [];

            $after = Document::applyChanges($document, $changes);
            $this->emitDocumentPatchCallback($document, $after, 'loadIncremental');

            return $after;
        }

        throw new \InvalidArgumentException('Unsupported native Automerge PHP incremental payload.');
    }

    /**
     * @return array{document:Document,loadedChanges:int,bytesConsumed:int,trailingBytes:string}
     */
    public function loadIncrementalPrefix(Document $document, string $payload): array
    {
        $prefixLength = $this->jsonObjectPrefixLength($payload);
        $prefix = substr($payload, 0, $prefixLength);
        $beforeChanges = count($document->getAllChanges());
        $after = $this->loadIncremental($document, $prefix);

        return [
            'document' => $after,
            'loadedChanges' => max(0, count($after->getAllChanges()) - $beforeChanges),
            'bytesConsumed' => $prefixLength,
            'trailingBytes' => substr($payload, $prefixLength),
        ];
    }

    public function load(string $payload, ?string $actorId = null): Document
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (is_array($decoded) && ($decoded['format'] ?? null) === 'wordpress-de/automerge-php-native-incremental-v1') {
            $changes = is_array($decoded['changes'] ?? null) ? array_values($decoded['changes']) : [];
            foreach ($changes as $change) {
                if (is_array($change) && $this->normalizeHeads($change['deps'] ?? []) !== []) {
                    throw new \InvalidArgumentException('Cannot load incremental change with missing dependencies.');
                }
            }

            return Document::applyChanges($this->init($actorId), $changes);
        }

        return Document::load($payload)->clone($actorId ?? bin2hex(random_bytes(4)));
    }

    public function loadFrozen(string $payload, ?string $actorId = null): Document
    {
        return $this->load($payload, $actorId)->withFrozen(true);
    }

    public function loadMigratingStringsToText(string $payload, ?string $actorId = null): Document
    {
        return Document::loadWithStringMigration($payload)->clone($actorId ?? bin2hex(random_bytes(4)));
    }

    public function loadWithPatchCallback(string $payload, callable $patchCallback, ?string $actorId = null): Document
    {
        $loaded = $this->load($payload, $actorId);
        $before = $this->init($loaded->actorId());
        $patches = $this->patchesBetweenDocuments($before, $loaded);
        if ($patches !== []) {
            $patchCallback($patches, ['before' => $before, 'after' => $loaded, 'source' => 'load']);
        }

        return $loaded;
    }

    /**
     * @param array<string,mixed> $change
     */
    public function loadChange(array $change, bool $allowMissingChanges = false, ?string $actorId = null): Document
    {
        $deps = $this->normalizeHeads($change['deps'] ?? []);
        if ($deps !== [] && ! $allowMissingChanges) {
            throw new \InvalidArgumentException('Cannot load change with missing dependencies.');
        }

        return Document::applyChanges($this->init($actorId), [$change]);
    }

    /**
     * @param list<array<string,mixed>> $changes
     */
    public function applyChanges(Document $document, array $changes): Document
    {
        $after = Document::applyChanges($document, $changes);
        $this->emitDocumentPatchCallback($document, $after, 'applyChanges');

        return $after;
    }

    /**
     * @param list<array<string,mixed>> $changes
     */
    public function applyChangesBatch(Document $document, array $changes): Document
    {
        return $this->applyChanges($document, $changes);
    }

    /**
     * @param list<array<string,mixed>> $changes
     */
    public function applyChangesWithPatchCallback(Document $document, array $changes, callable $patchCallback): Document
    {
        $after = Document::applyChanges($document, $changes);
        $patches = $this->patchesBetweenDocuments($document, $after);
        if ($patches !== []) {
            $patchCallback($patches, ['before' => $document, 'after' => $after, 'source' => 'applyChanges']);
        }

        return $after;
    }

    /**
     * @return array{postContent:string, metadata:array<string,mixed>, automerge:Document}
     */
    public function createDocument(string $basePostContent, array $baseMetadata = []): array
    {
        $document = $this->from(
            [
                'postContent' => $basePostContent,
                'metadata' => $baseMetadata,
            ],
            $baseMetadata['actorId'] ?? null
        );

        return [
            'postContent' => $basePostContent,
            'basePostContent' => $basePostContent,
            'metadata' => $baseMetadata,
            'automerge' => $document,
            'lastPostContentEdit' => null,
        ];
    }

    /**
     * @param array{postContent:string, basePostContent?:string, metadata:array<string,mixed>, automerge?:Document, lastPostContentEdit?:array<string,mixed>}|Document $document
     * @param array{postContent?:string, actorId?:string} $edit
     * @return array{postContent:string, basePostContent:string, metadata:array<string,mixed>, automerge:Document, lastPostContentEdit:?array<string,mixed>}|Document
     */
    public function applyLocalEdit(array|Document $document, array $edit): array|Document
    {
        if (! is_string($edit['postContent'] ?? null)) {
            throw new \InvalidArgumentException('applyLocalEdit requires a postContent string.');
        }

        if ($document instanceof Document) {
            return $this->updateText($document, 'postContent', $edit['postContent']);
        }

        $baseDocument = $document['automerge'] ?? null;
        if (! $baseDocument instanceof Document) {
            $baseDocument = $this->from(['postContent' => $document['postContent']], $edit['actorId'] ?? null);
        }

        $actorId = is_string($edit['actorId'] ?? null) ? $edit['actorId'] : null;
        $workingDocument = $actorId === null ? $baseDocument->clone() : $baseDocument->clone($actorId);
        $oldPostContent = $this->postContentFromDocument($workingDocument);
        $nextDocument = $this->updateText($workingDocument, 'postContent', $edit['postContent']);

        return [
            'postContent' => $edit['postContent'],
            'basePostContent' => is_string($document['basePostContent'] ?? null) ? $document['basePostContent'] : $oldPostContent,
            'metadata' => is_array($document['metadata'] ?? null) ? $document['metadata'] : [],
            'automerge' => $nextDocument,
            'lastPostContentEdit' => $this->textEdit($oldPostContent, $edit['postContent']),
        ];
    }

    /**
     * @param array{postContent:string, basePostContent?:string, metadata:array<string,mixed>, automerge?:Document, lastPostContentEdit?:array<string,mixed>}|Document $document
     * @return array{postContent:string, basePostContent:string, metadata:array<string,mixed>, automerge:Document, lastPostContentEdit:?array<string,mixed>}|Document
     */
    public function applyServerPostUpdate(array|Document $document, string $postContent, string $actorId = 'server'): array|Document
    {
        return $this->applyLocalEdit($document, ['actorId' => $actorId, 'postContent' => $postContent]);
    }

    /**
     * @param array{postContent:string, basePostContent?:string, metadata:array<string,mixed>, automerge?:Document, lastPostContentEdit?:array<string,mixed>}|Document $document
     * @return array{postContent:string, basePostContent:string, metadata:array<string,mixed>, document:Document, changes:list<array<string,mixed>>, lastPostContentEdit:?array<string,mixed>}
     */
    public function encodeUpdate(array|Document $document): array
    {
        if ($document instanceof Document) {
            return [
                'postContent' => $this->postContentFromDocument($document),
                'basePostContent' => '',
                'metadata' => [],
                'document' => $document,
                'changes' => $document->getAllChanges(),
                'lastPostContentEdit' => null,
            ];
        }

        $automerge = $document['automerge'] ?? null;
        if (! $automerge instanceof Document) {
            $automerge = $this->from(['postContent' => $document['postContent']]);
        }

        return [
            'postContent' => $this->postContentFromDocument($automerge),
            'basePostContent' => is_string($document['basePostContent'] ?? null) ? $document['basePostContent'] : '',
            'metadata' => is_array($document['metadata'] ?? null) ? $document['metadata'] : [],
            'document' => $automerge,
            'changes' => $automerge->getAllChanges(),
            'lastPostContentEdit' => is_array($document['lastPostContentEdit'] ?? null) ? $document['lastPostContentEdit'] : null,
        ];
    }

    /**
     * @param array{postContent:string, metadata:array<string,mixed>, automerge?:Document}|Document $document
     */
    public function materialize(array|Document $document): string|array
    {
        if ($document instanceof Document) {
            return $document->toArray();
        }

        if (isset($document['automerge']) && $document['automerge'] instanceof Document) {
            $root = $document['automerge']->toArray();
            return is_string($root['postContent'] ?? null) ? $root['postContent'] : '';
        }

        return $document['postContent'];
    }

    /**
     * @param list<string|int> $path
     */
    public function hydrate(Document $document, array $path = []): mixed
    {
        $path = array_values($path);

        return $path === [] ? $document->toArray() : $this->readPath($document, $path);
    }

    /**
     * @param array<string|int,mixed> $hydrated
     * @param list<array<string,mixed>> $patches
     * @return array<string|int,mixed>
     */
    public function applyHydratedPatches(array $hydrated, array $patches): array
    {
        return $this->applyPatchesToArray($hydrated, $patches);
    }

    /**
     * @return list<array{key:string,path:list<string|int>,kind:string,value:mixed}>
     */
    public function iterDocument(Document $document): array
    {
        $entries = [];
        $queue = [
            [
                'path' => [],
                'value' => $document->rootValues(),
            ],
        ];

        while ($queue !== []) {
            $container = array_shift($queue);
            $containerPath = is_array($container['path']) ? array_values($container['path']) : [];
            foreach ($this->iterDocumentChildren($container['value']) as $child) {
                $path = array_merge($containerPath, [$child['pathKey']]);
                $entry = $this->iterDocumentEntry($child['key'], $path, $child['value']);
                $entries[] = $entry;
                if ($entry['kind'] !== 'scalar') {
                    $queue[] = [
                        'path' => $path,
                        'value' => $child['value'],
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @param array{postContent:string, metadata:array<string,mixed>, automerge?:Document}|Document $document
     */
    public function getMetadataStats(array|Document $document): array
    {
        if ($document instanceof Document) {
            $stats = $document->stats();
            return [
                'bytes' => $stats['bytes'],
                'operationCount' => $stats['sequence'],
                'historySize' => count($stats['heads']),
                'textElements' => $stats['textElements'],
            ];
        }

        if (isset($document['automerge']) && $document['automerge'] instanceof Document) {
            return $this->getMetadataStats($document['automerge']);
        }

        return [
            'bytes' => strlen(json_encode($document['metadata'], JSON_THROW_ON_ERROR)),
            'operationCount' => 0,
            'historySize' => 0,
            'textElements' => 0,
        ];
    }

    /**
     * @return array{numChanges:int,numOps:int,cargoPackageName:string,cargoPackageVersion:string,rustcVersion:string}
     */
    public function stats(Document $document): array
    {
        $changes = $document->getAllChanges();
        $numOps = 0;
        foreach ($changes as $change) {
            $numOps += count(is_array($change['ops'] ?? null) ? $change['ops'] : []);
        }

        return [
            'numChanges' => count($changes),
            'numOps' => $numOps,
            'cargoPackageName' => 'wordpress-de/automerge-php-native',
            'cargoPackageVersion' => '0.1.0',
            'rustcVersion' => 'native-php',
        ];
    }

    public function merge(string $basePostContent, mixed $updateA, mixed $updateB): array
    {
        if (is_array($updateA) && is_array($updateB) && isset($updateA['document'], $updateB['document'])) {
            if (is_array($updateA['lastPostContentEdit'] ?? null) && is_array($updateB['lastPostContentEdit'] ?? null)) {
                $mergedPostContent = $this->mergeTextEdits(
                    $basePostContent,
                    $updateA['lastPostContentEdit'],
                    $updateB['lastPostContentEdit']
                );

                if ($mergedPostContent === null) {
                    return [
                        'ok' => false,
                        'conflict' => [
                            'reason' => 'overlapping-post-content-edits',
                            'message' => 'The native PHP port cannot yet merge overlapping post content replacement spans.',
                        ],
                    ];
                }

                return [
                    'ok' => true,
                    'postContent' => $mergedPostContent,
                    'metadata' => is_array($updateA['metadata'] ?? null) ? $updateA['metadata'] : [],
                ];
            }

            if ($updateA['document'] instanceof Document && $updateB['document'] instanceof Document) {
                return $this->merge($basePostContent, $updateA['document'], $updateB['document']);
            }
        }

        if ($updateA instanceof Document && $updateB instanceof Document) {
            $merged = $this->mergeDocuments($updateA, $updateB);
            $root = $merged->toArray();

            return [
                'ok' => true,
                'postContent' => is_string($root['postContent'] ?? null) ? $root['postContent'] : $basePostContent,
                'document' => $merged,
            ];
        }

        return [
            'ok' => false,
            'conflict' => [
                'reason' => 'unsupported-update-shape',
                'message' => 'Native PHP merge currently accepts only Document instances from this port.',
            ],
        ];
    }

    private function postContentFromDocument(Document $document): string
    {
        $root = $document->toArray();

        return is_string($root['postContent'] ?? null) ? $root['postContent'] : '';
    }

    /**
     * @return array{index:int, deleteCount:int, insert:string}
     */
    private function textEdit(string $oldText, string $newText): array
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
            'index' => $prefix,
            'deleteCount' => $oldLength - $prefix - $suffix,
            'insert' => implode('', array_slice($new, $prefix, $newLength - $prefix - $suffix)),
        ];
    }

    /**
     * @param array{index:int,deleteCount:int,insert:string} $editA
     * @param array{index:int,deleteCount:int,insert:string} $editB
     */
    private function mergeTextEdits(string $baseText, array $editA, array $editB): ?string
    {
        if ($editA === $editB) {
            return $this->applyTextEdit($baseText, $editA);
        }

        $aStart = $editA['index'];
        $aEnd = $aStart + $editA['deleteCount'];
        $bStart = $editB['index'];
        $bEnd = $bStart + $editB['deleteCount'];

        if ($aEnd <= $bStart) {
            $afterA = $this->applyTextEdit($baseText, $editA);
            $adjustedB = $editB;
            $adjustedB['index'] += count($this->splitCharacters($editA['insert'])) - $editA['deleteCount'];

            return $this->applyTextEdit($afterA, $adjustedB);
        }

        if ($bEnd <= $aStart) {
            $afterB = $this->applyTextEdit($baseText, $editB);
            $adjustedA = $editA;
            $adjustedA['index'] += count($this->splitCharacters($editB['insert'])) - $editB['deleteCount'];

            return $this->applyTextEdit($afterB, $adjustedA);
        }

        return null;
    }

    /**
     * @param array{index:int,deleteCount:int,insert:string} $edit
     */
    private function applyTextEdit(string $text, array $edit): string
    {
        $characters = $this->splitCharacters($text);
        array_splice(
            $characters,
            $edit['index'],
            $edit['deleteCount'],
            $this->splitCharacters($edit['insert'])
        );

        return implode('', $characters);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function patchesBetweenDocuments(Document $before, Document $after): array
    {
        $beforeRoot = $before->toArray();
        $afterRoot = $after->toArray();
        if ($beforeRoot === []) {
            $patches = [];
            foreach ($afterRoot as $key => $value) {
                $patches = array_merge($patches, $this->assignmentPatchesForDocumentValue($after, (string) $key, $value));
            }

            return $patches;
        }

        return array_values(array_merge(
            $this->diffValues($beforeRoot, $afterRoot, []),
            $this->markPatchesBetweenDocuments($before, $after)
        ));
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
     * @return array{0:int,1:int}
     */
    private function normalizeByteRange(string $text, int $start, int $end): array
    {
        $length = strlen($text);
        $start = max(0, min($start, $length));
        $end = max(0, min($end, $length));

        return [$start, $end];
    }

    private function textDiffMaxD(int $oldLength, int $newLength): int
    {
        return intdiv(max(0, $oldLength) + max(0, $newLength) + 1, 2) + 1;
    }

    /**
     * @param array<int|string,int> $clock
     * @return list<int>
     */
    private function normalizeClock(array $clock): array
    {
        return array_map(
            static fn (int $counter): int => max(0, $counter),
            array_values($clock)
        );
    }

    /**
     * @param list<array{hash:string,actor:int,seq?:int|null,deps?:list<string>}> $changes
     * @return array{nodes:array<string,array{hash:string,actor:int,seq:int|null,deps:list<string>}>,actorCount:int}
     */
    private function normalizeChangeGraph(array $changes): array
    {
        $nodes = [];
        $actorCount = 0;
        foreach ($changes as $change) {
            if (! isset($change['hash']) || ! is_string($change['hash']) || $change['hash'] === '') {
                throw new \InvalidArgumentException('Change graph nodes require a non-empty hash.');
            }

            if (isset($nodes[$change['hash']])) {
                throw new \InvalidArgumentException('Change graph hashes must be unique.');
            }

            $actor = $change['actor'] ?? null;
            if (! is_int($actor) || $actor < 0) {
                throw new \InvalidArgumentException('Change graph nodes require a non-negative actor index.');
            }

            $seq = $change['seq'] ?? null;
            if ($seq !== null && (! is_int($seq) || $seq <= 0)) {
                throw new \InvalidArgumentException('Change graph sequence numbers must be positive.');
            }

            $deps = $change['deps'] ?? [];
            if (! is_array($deps) || array_values($deps) !== $deps) {
                throw new \InvalidArgumentException('Change graph dependencies must be a list.');
            }

            foreach ($deps as $dep) {
                if (! is_string($dep) || $dep === '') {
                    throw new \InvalidArgumentException('Change graph dependencies must be non-empty hashes.');
                }
            }

            $nodes[$change['hash']] = [
                'hash' => $change['hash'],
                'actor' => $actor,
                'seq' => $seq,
                'deps' => $deps,
            ];
            $actorCount = max($actorCount, $actor + 1);
        }

        return [
            'nodes' => $nodes,
            'actorCount' => $actorCount,
        ];
    }

    /**
     * @param array<string,array{hash:string,actor:int,seq:int|null,deps:list<string>}> $nodes
     * @param list<string> $heads
     * @return list<string>
     */
    private function changeGraphAncestors(array $nodes, array $heads): array
    {
        $visited = [];
        $ordered = [];
        $toVisit = array_reverse(array_values($heads));

        while ($toVisit !== []) {
            $hash = array_pop($toVisit);
            if (! is_string($hash) || isset($visited[$hash]) || ! isset($nodes[$hash])) {
                continue;
            }

            $visited[$hash] = true;
            $ordered[] = $hash;
            foreach ($nodes[$hash]['deps'] as $dep) {
                if (! isset($visited[$dep])) {
                    $toVisit[] = $dep;
                }
            }
        }

        return $ordered;
    }

    private function normalizeUnsignedDecimal(int|string $value, string $max): string
    {
        $decimal = $this->normalizeDecimalString($value);
        if (str_starts_with($decimal, '-')) {
            throw new \InvalidArgumentException('Unsigned LEB128 size requires a non-negative integer.');
        }

        if ($this->compareDecimalStrings($decimal, $max) > 0) {
            throw new \InvalidArgumentException('Unsigned LEB128 size is limited to u64 values.');
        }

        return $decimal;
    }

    private function normalizeSignedDecimal(int|string $value): string
    {
        $decimal = $this->normalizeDecimalString($value);
        if (str_starts_with($decimal, '-')) {
            if ($this->compareDecimalStrings(substr($decimal, 1), '9223372036854775808') > 0) {
                throw new \InvalidArgumentException('Signed LEB128 size is limited to i64 values.');
            }
        } elseif ($this->compareDecimalStrings($decimal, '9223372036854775807') > 0) {
            throw new \InvalidArgumentException('Signed LEB128 size is limited to i64 values.');
        }

        return $decimal;
    }

    private function normalizeDecimalString(int|string $value): string
    {
        $decimal = is_int($value) ? (string) $value : trim($value);
        if ($decimal === '') {
            throw new \InvalidArgumentException('Decimal integer cannot be empty.');
        }

        $negative = str_starts_with($decimal, '-');
        if ($negative || str_starts_with($decimal, '+')) {
            $decimal = substr($decimal, 1);
        }

        if ($decimal === '' || ! ctype_digit($decimal)) {
            throw new \InvalidArgumentException('Decimal integer must contain only digits.');
        }

        $decimal = ltrim($decimal, '0');
        if ($decimal === '') {
            return '0';
        }

        return $negative ? '-' . $decimal : $decimal;
    }

    private function compareDecimalStrings(string $left, string $right): int
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');
        $left = $left === '' ? '0' : $left;
        $right = $right === '' ? '0' : $right;

        if (strlen($left) !== strlen($right)) {
            return strlen($left) < strlen($right) ? -1 : 1;
        }

        return $left <=> $right;
    }

    private function decimalBitLength(string $decimal): int
    {
        $decimal = ltrim($decimal, '0');
        if ($decimal === '') {
            return 0;
        }

        $bits = 0;
        while ($decimal !== '0') {
            $decimal = $this->divideDecimalStringByTwo($decimal);
            ++$bits;
        }

        return $bits;
    }

    private function divideDecimalStringByTwo(string $decimal): string
    {
        $carry = 0;
        $quotient = '';
        $length = strlen($decimal);
        for ($index = 0; $index < $length; ++$index) {
            $digit = $carry * 10 + (ord($decimal[$index]) - 48);
            $quotient .= (string) intdiv($digit, 2);
            $carry = $digit % 2;
        }

        $quotient = ltrim($quotient, '0');

        return $quotient === '' ? '0' : $quotient;
    }

    /**
     * @return array{quotient:string,remainder:int}
     */
    private function divideDecimalStringByInt(string $decimal, int $divisor): array
    {
        if ($divisor <= 0) {
            throw new \InvalidArgumentException('Decimal divisor must be positive.');
        }

        $decimal = ltrim($decimal, '0');
        if ($decimal === '') {
            return ['quotient' => '0', 'remainder' => 0];
        }

        $carry = 0;
        $quotient = '';
        $length = strlen($decimal);
        for ($index = 0; $index < $length; ++$index) {
            $value = ($carry * 10) + (ord($decimal[$index]) - 48);
            $quotient .= (string) intdiv($value, $divisor);
            $carry = $value % $divisor;
        }

        $quotient = ltrim($quotient, '0');

        return [
            'quotient' => $quotient === '' ? '0' : $quotient,
            'remainder' => $carry,
        ];
    }

    private function decrementDecimalString(string $decimal): string
    {
        $decimal = ltrim($decimal, '0');
        if ($decimal === '' || $decimal === '0') {
            return '0';
        }

        $digits = str_split($decimal);
        for ($index = count($digits) - 1; $index >= 0; --$index) {
            if ($digits[$index] !== '0') {
                $digits[$index] = (string) ((int) $digits[$index] - 1);
                break;
            }

            $digits[$index] = '9';
        }

        $result = ltrim(implode('', $digits), '0');

        return $result === '' ? '0' : $result;
    }

    private function decimalStringToPhpInt(string $decimal, string $label): int
    {
        $decimal = $this->normalizeDecimalString($decimal);
        if (str_starts_with($decimal, '-') || $this->compareDecimalStrings($decimal, (string) PHP_INT_MAX) > 0) {
            throw new \InvalidArgumentException($label . ' is too large for this PHP runtime.');
        }

        return (int) $decimal;
    }

    private function jsonObjectPrefixLength(string $payload): int
    {
        $length = strlen($payload);
        $offset = 0;
        while ($offset < $length && str_contains(" \t\r\n", $payload[$offset])) {
            ++$offset;
        }

        if ($offset >= $length || $payload[$offset] !== '{') {
            throw new \InvalidArgumentException('Native incremental payload does not start with a JSON object.');
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        for ($index = $offset; $index < $length; ++$index) {
            $char = $payload[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '{' || $char === '[') {
                ++$depth;
                continue;
            }
            if ($char === '}' || $char === ']') {
                --$depth;
                if ($depth === 0) {
                    return $index + 1;
                }
                if ($depth < 0) {
                    throw new \InvalidArgumentException('Native incremental payload has mismatched JSON delimiters.');
                }
            }
        }

        throw new \InvalidArgumentException('Native incremental payload has no complete JSON object prefix.');
    }

    private function addDecimalStrings(string $left, string $right): string
    {
        $left = strrev(ltrim($left, '0') === '' ? '0' : ltrim($left, '0'));
        $right = strrev(ltrim($right, '0') === '' ? '0' : ltrim($right, '0'));
        $length = max(strlen($left), strlen($right));
        $carry = 0;
        $sum = '';

        for ($index = 0; $index < $length; ++$index) {
            $digit = $carry;
            if ($index < strlen($left)) {
                $digit += ord($left[$index]) - 48;
            }
            if ($index < strlen($right)) {
                $digit += ord($right[$index]) - 48;
            }

            $sum .= (string) ($digit % 10);
            $carry = intdiv($digit, 10);
        }

        if ($carry > 0) {
            $sum .= (string) $carry;
        }

        $result = ltrim(strrev($sum), '0');

        return $result === '' ? '0' : $result;
    }

    private function subtractDecimalStrings(string $left, string $right): string
    {
        $left = ltrim($left, '0') === '' ? '0' : ltrim($left, '0');
        $right = ltrim($right, '0') === '' ? '0' : ltrim($right, '0');
        if ($this->compareDecimalStrings($left, $right) < 0) {
            throw new \InvalidArgumentException('Decimal subtraction requires a non-negative result.');
        }

        $left = strrev($left);
        $right = strrev($right);
        $borrow = 0;
        $result = '';

        for ($index = 0; $index < strlen($left); ++$index) {
            $digit = (ord($left[$index]) - 48) - $borrow;
            $other = $index < strlen($right) ? ord($right[$index]) - 48 : 0;
            if ($digit < $other) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result .= (string) ($digit - $other);
        }

        $result = ltrim(strrev($result), '0');

        return $result === '' ? '0' : $result;
    }

    private function multiplyDecimalStringByTwo(string $decimal): string
    {
        $decimal = ltrim($decimal, '0');
        if ($decimal === '') {
            return '0';
        }

        $carry = 0;
        $result = '';
        for ($index = strlen($decimal) - 1; $index >= 0; --$index) {
            $value = ((ord($decimal[$index]) - 48) * 2) + $carry;
            $result .= (string) ($value % 10);
            $carry = intdiv($value, 10);
        }

        if ($carry > 0) {
            $result .= (string) $carry;
        }

        return strrev($result);
    }

    private function decimalPowerOfTwo(int $shift): string
    {
        if ($shift < 0) {
            throw new \InvalidArgumentException('Decimal power shift must be non-negative.');
        }

        $value = '1';
        for ($index = 0; $index < $shift; ++$index) {
            $value = $this->multiplyDecimalStringByTwo($value);
        }

        return $value;
    }

    private function shiftedLebPayloadDecimal(int $payload, int $shift): string
    {
        if ($payload === 0) {
            return '0';
        }

        $value = (string) $payload;
        for ($index = 0; $index < $shift; ++$index) {
            $value = $this->multiplyDecimalStringByTwo($value);
        }

        return $value;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function parseStorageUnsignedLeb128(string $bytes): array
    {
        $value = '0';
        $shift = 0;
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $byte = ord($bytes[$offset]);
            ++$offset;
            $payload = $byte & 0x7f;
            $value = $this->addDecimalStrings($value, $this->shiftedLebPayloadDecimal($payload, $shift));
            $shift += 7;

            if (($byte & 0x80) === 0) {
                if ($shift > 64 && $byte > 1) {
                    throw new \InvalidArgumentException('LEB128 value is too large for u64.');
                }
                if ($shift > 7 && $byte === 0) {
                    throw new \InvalidArgumentException('LEB128 value uses an overlong encoding.');
                }
                if ($this->compareDecimalStrings($value, '18446744073709551615') > 0) {
                    throw new \InvalidArgumentException('LEB128 value is too large for u64.');
                }

                return [$value, $offset];
            }

            if ($shift > 64) {
                throw new \InvalidArgumentException('LEB128 value is too large for u64.');
            }
        }

        throw new \InvalidArgumentException('Truncated LEB128 value.');
    }

    /**
     * @return array{0:string,1:int}
     */
    private function parseStorageSignedLeb128(string $bytes): array
    {
        $raw = '0';
        $shift = 0;
        $offset = 0;
        $previousByte = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $byte = ord($bytes[$offset]);
            ++$offset;
            $payload = $byte & 0x7f;
            $bitsRemaining = max(0, min(7, 64 - $shift));
            if ($bitsRemaining > 0) {
                $mask = (1 << $bitsRemaining) - 1;
                $raw = $this->addDecimalStrings(
                    $raw,
                    $this->shiftedLebPayloadDecimal($payload & $mask, $shift)
                );
            }
            $shift += 7;

            if (($byte & 0x80) === 0) {
                if ($shift > 64 && $byte !== 0 && $byte !== 0x7f) {
                    throw new \InvalidArgumentException('LEB128 value is too large for i64.');
                }
                if (
                    $shift > 7
                    && (
                        ($byte === 0 && ($previousByte & 0x40) === 0)
                        || ($byte === 0x7f && ($previousByte & 0x40) !== 0)
                    )
                ) {
                    throw new \InvalidArgumentException('LEB128 value uses an overlong encoding.');
                }

                if ($shift < 64 && ($byte & 0x40) !== 0) {
                    return ['-' . $this->subtractDecimalStrings($this->decimalPowerOfTwo($shift), $raw), $offset];
                }
                if ($this->compareDecimalStrings($raw, '9223372036854775808') >= 0) {
                    return ['-' . $this->subtractDecimalStrings('18446744073709551616', $raw), $offset];
                }

                return [$raw, $offset];
            }

            if ($shift > 64) {
                throw new \InvalidArgumentException('LEB128 value is too large for i64.');
            }

            $previousByte = $byte;
        }

        throw new \InvalidArgumentException('Truncated LEB128 value.');
    }

    private function storageChunkBodyOffset(string $raw): int
    {
        $offset = 9;
        $this->decodeUnsignedLeb128Int($raw, $offset);

        return $offset;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function storageChangesFromPayload(string $payload): array
    {
        $changes = [];
        $offset = 0;
        $length = strlen($payload);
        while ($offset < $length) {
            if ($offset + 10 > $length) {
                throw new \InvalidArgumentException('Automerge storage payload contains a truncated chunk.');
            }
            if (substr($payload, $offset, 4) !== "\x85\x6f\x4a\x83") {
                throw new \InvalidArgumentException('Invalid Automerge storage magic bytes.');
            }

            $chunkStart = $offset;
            $offset += 8;
            ++$offset;
            $dataLength = $this->decodeUnsignedLeb128Int($payload, $offset);
            $chunkEnd = $offset + $dataLength;
            if ($chunkEnd > $length) {
                throw new \InvalidArgumentException('Automerge storage chunk length exceeds payload bytes.');
            }

            $changes[] = $this->storageChangeFromBytes($this->columnarBytesToList(substr($payload, $chunkStart, $chunkEnd - $chunkStart)));
            $offset = $chunkEnd;
        }

        return $this->storageTopologicalChanges($changes);
    }

    /**
     * @param list<array<string,mixed>> $changes
     * @return list<array<string,mixed>>
     */
    private function storageTopologicalChanges(array $changes): array
    {
        $byHash = [];
        foreach ($changes as $change) {
            if (is_string($change['hash'] ?? null)) {
                $byHash[$change['hash']] = $change;
            }
        }

        $ordered = [];
        $applied = [];
        while ($changes !== []) {
            $progress = false;
            foreach ($changes as $index => $change) {
                $deps = $this->normalizeHeads($change['deps'] ?? []);
                $missing = array_values(array_filter(
                    $deps,
                    static fn (string $dep): bool => isset($byHash[$dep]) && ! isset($applied[$dep])
                ));
                if ($missing !== []) {
                    continue;
                }

                $ordered[] = $change;
                if (is_string($change['hash'] ?? null)) {
                    $applied[$change['hash']] = true;
                }
                unset($changes[$index]);
                $progress = true;
            }

            if (! $progress) {
                throw new \InvalidArgumentException('Automerge storage changes contain unsatisfied dependencies.');
            }

            $changes = array_values($changes);
        }

        return $ordered;
    }

    /**
     * @param array<string,list<string|int>> $objectPaths
     * @param array<string,mixed>            $change
     * @return array{0:Document,1:array<string,list<string|int>>}
     */
    private function storageApplyLoadedChange(Document $document, array $objectPaths, array $change): array
    {
        $key = $this->storageChangeSingleStringColumn($change, 1);
        $action = $this->storageChangeSingleIntegerColumn($change, 4);
        if ($key === null || $action === null) {
            throw new \InvalidArgumentException('Unsupported Automerge storage fixture change columns.');
        }

        if ($action === 0) {
            $document = $this->batchCreateObject($document, $key, []);
            $objectPaths[$this->storageChangeObjectId($change)] = [$key];

            return [$document, $objectPaths];
        }

        if ($action === 1) {
            $objectPath = $objectPaths[$this->storageChangeObjectKey($change)] ?? null;
            $value = $this->storageChangeSingleValueColumn($change, 5);
            if ($objectPath === null || $value === null) {
                throw new \InvalidArgumentException('Unsupported Automerge storage fixture set operation.');
            }

            return [$this->setNested($document, array_merge($objectPath, [$key]), $value), $objectPaths];
        }

        throw new \InvalidArgumentException('Unsupported Automerge storage fixture action.');
    }

    /**
     * @param array<string,mixed> $change
     */
    private function storageChangeObjectId(array $change): string
    {
        return ((int) ($change['startOp'] ?? 0)) . '@' . (string) ($change['actor'] ?? '');
    }

    /**
     * @param array<string,mixed> $change
     */
    private function storageChangeObjectKey(array $change): string
    {
        $counter = $this->storageChangeSingleIntegerColumn($change, 0);
        $actor = (string) ($change['actor'] ?? '');

        return $counter . '@' . $actor;
    }

    /**
     * @param array<string,mixed> $change
     */
    private function storageChangeSingleStringColumn(array $change, int $columnId): ?string
    {
        $bytes = $this->storageChangeColumnBytes($change, $columnId, 'String');
        if (count($bytes) < 2 || $bytes[0] !== 0x7f) {
            return null;
        }

        $length = $bytes[1];
        if ($length < 0 || count($bytes) !== $length + 2) {
            return null;
        }

        return $this->columnarByteListToString(array_slice($bytes, 2));
    }

    /**
     * @param array<string,mixed> $change
     */
    private function storageChangeSingleIntegerColumn(array $change, int $columnId): ?int
    {
        $bytes = $this->storageChangeColumnBytes($change, $columnId, 'Integer');
        if (count($bytes) !== 2 || $bytes[0] !== 0x7f) {
            return null;
        }

        return $bytes[1];
    }

    /**
     * @param array<string,mixed> $change
     */
    private function storageChangeSingleValueColumn(array $change, int $columnId): mixed
    {
        $bytes = $this->storageChangeColumnBytes($change, $columnId, 'Value');
        if ($bytes === []) {
            return null;
        }

        return $this->columnarByteListToString($bytes);
    }

    /**
     * @param array<string,mixed> $change
     * @return list<int>
     */
    private function storageChangeColumnBytes(array $change, int $columnId, string $columnType): array
    {
        $opsData = is_array($change['opsData'] ?? null) ? array_values($change['opsData']) : [];
        foreach (is_array($change['rawColumns'] ?? null) ? $change['rawColumns'] : [] as $column) {
            if (
                is_array($column)
                && ($column['id'] ?? null) === $columnId
                && ($column['type'] ?? null) === $columnType
                && is_array($column['range'] ?? null)
            ) {
                $start = (int) ($column['range'][0] ?? 0);
                $end = (int) ($column['range'][1] ?? $start);

                return array_slice($opsData, $start, max(0, $end - $start));
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function storageChangeReadHashList(string $data, int &$offset): array
    {
        $count = $this->decodeUnsignedLeb128Int($data, $offset);
        $hashes = [];
        for ($index = 0; $index < $count; ++$index) {
            if ($offset + 32 > strlen($data)) {
                throw new \InvalidArgumentException('Automerge change dependency hash is truncated.');
            }

            $hashes[] = bin2hex(substr($data, $offset, 32));
            $offset += 32;
        }

        return $hashes;
    }

    private function storageChangeReadActorId(string $data, int &$offset): string
    {
        $length = $this->decodeUnsignedLeb128Int($data, $offset);
        if ($offset + $length > strlen($data)) {
            throw new \InvalidArgumentException('Automerge change actor ID is truncated.');
        }

        $actor = bin2hex(substr($data, $offset, $length));
        $offset += $length;

        return $actor;
    }

    /**
     * @return list<string>
     */
    private function storageChangeReadActorList(string $data, int &$offset): array
    {
        $count = $this->decodeUnsignedLeb128Int($data, $offset);
        $actors = [];
        for ($index = 0; $index < $count; ++$index) {
            $actors[] = $this->storageChangeReadActorId($data, $offset);
        }

        return $actors;
    }

    /**
     * @return list<array{spec:int,normalized:int,id:int,type:string,deflate:bool,length:int,range:array{0:int,1:int}}>
     */
    private function storageChangeReadRawColumns(string $data, int &$offset): array
    {
        $count = $this->decodeUnsignedLeb128Int($data, $offset);
        $columns = [];
        $dataOffset = 0;
        $lastNormalized = null;

        for ($index = 0; $index < $count; ++$index) {
            $spec = $this->decodeUnsignedLeb128Int($data, $offset);
            $length = $this->decodeUnsignedLeb128Int($data, $offset);
            $normalized = $spec & 0xfffffff7;
            if ($lastNormalized !== null && $normalized < $lastNormalized) {
                throw new \InvalidArgumentException('Automerge change columns are not in normalized order.');
            }

            $columns[] = [
                'spec' => $spec,
                'normalized' => $normalized,
                'id' => $spec >> 4,
                'type' => $this->columnTypeName($spec),
                'deflate' => ($spec & 0x08) !== 0,
                'length' => $length,
                'range' => [$dataOffset, $dataOffset + $length],
            ];
            $dataOffset += $length;
            $lastNormalized = $normalized;
        }

        return $columns;
    }

    /**
     * @param list<array{spec:int,length?:int,range?:array{0:int,1:int}}> $columns
     * @return list<array{spec:int,normalized:int,id:int,type:string,deflate:bool,length:int,range:array{0:int,1:int}}>
     */
    private function storageNormalizeRawColumns(array $columns, int $dataLength): array
    {
        if (! array_is_list($columns)) {
            throw new \InvalidArgumentException('Automerge columns must be a list.');
        }

        $normalizedColumns = [];
        $dataOffset = 0;
        $lastNormalized = null;

        foreach ($columns as $column) {
            if (! is_array($column)) {
                throw new \InvalidArgumentException('Automerge column metadata must be an array.');
            }

            $spec = $column['spec'] ?? null;
            if (! is_int($spec)) {
                throw new \InvalidArgumentException('Automerge column spec must be an integer.');
            }

            $range = $column['range'] ?? null;
            $length = $column['length'] ?? null;
            if ($range !== null) {
                if (! is_array($range) || count($range) !== 2 || ! is_int($range[0] ?? null) || ! is_int($range[1] ?? null)) {
                    throw new \InvalidArgumentException('Automerge column range must contain two integer offsets.');
                }

                if ($range[0] < 0 || $range[1] < $range[0] || $range[1] > $dataLength) {
                    throw new \InvalidArgumentException('Automerge column range is outside the column data.');
                }

                $rangeLength = $range[1] - $range[0];
                if ($length !== null && (! is_int($length) || $length !== $rangeLength)) {
                    throw new \InvalidArgumentException('Automerge column length does not match its range.');
                }

                $length = $rangeLength;
            } elseif (is_int($length) && $length >= 0) {
                $range = [$dataOffset, $dataOffset + $length];
            } else {
                throw new \InvalidArgumentException('Automerge column metadata must include a non-negative length.');
            }

            if ($range[0] !== $dataOffset) {
                throw new \InvalidArgumentException('Automerge column ranges must be contiguous in storage order.');
            }

            $descriptor = $this->storageRawColumnDescriptor($spec, $length, $range[0]);
            if ($lastNormalized !== null && $descriptor['normalized'] < $lastNormalized) {
                throw new \InvalidArgumentException('Automerge columns are not in normalized order.');
            }

            $normalizedColumns[] = $descriptor;
            $dataOffset = $range[1];
            $lastNormalized = $descriptor['normalized'];
        }

        if ($dataOffset !== $dataLength) {
            throw new \InvalidArgumentException('Automerge column lengths do not cover the column data.');
        }

        return $normalizedColumns;
    }

    /**
     * @return array{spec:int,normalized:int,id:int,type:string,deflate:bool,length:int,range:array{0:int,1:int}}
     */
    private function storageRawColumnDescriptor(int $spec, int $length, int $start): array
    {
        if ($length < 0 || $start < 0) {
            throw new \InvalidArgumentException('Automerge column range must be non-negative.');
        }

        $decoded = $this->columnSpecDecode($spec);

        return [
            'spec' => $spec,
            'normalized' => $decoded['normalized'],
            'id' => $decoded['id'],
            'type' => $decoded['type'],
            'deflate' => $decoded['deflate'],
            'length' => $length,
            'range' => [$start, $start + $length],
        ];
    }

    private function encodeUnsignedLeb128Decimal(int|string $value): string
    {
        $decimal = $this->normalizeUnsignedDecimal($value, '18446744073709551615');
        $bytes = '';

        do {
            $division = $this->divideDecimalStringByInt($decimal, 128);
            $byte = $division['remainder'];
            $decimal = $division['quotient'];
            if ($decimal !== '0') {
                $byte |= 0x80;
            }
            $bytes .= chr($byte);
        } while ($decimal !== '0');

        return $bytes;
    }

    private function columnTypeCode(string $type): int
    {
        return match ($type) {
            'Group' => 0,
            'Actor' => 1,
            'Integer' => 2,
            'DeltaInteger' => 3,
            'Boolean' => 4,
            'String' => 5,
            'ValueMetadata' => 6,
            'Value' => 7,
            default => throw new \InvalidArgumentException('Unknown column spec type.'),
        };
    }

    private function columnTypeName(int $code): string
    {
        return match ($code & 0x07) {
            0 => 'Group',
            1 => 'Actor',
            2 => 'Integer',
            3 => 'DeltaInteger',
            4 => 'Boolean',
            5 => 'String',
            6 => 'ValueMetadata',
            7 => 'Value',
        };
    }

    private function assertColumnSpecRaw(int $raw): void
    {
        if ($raw < 0 || $raw > 0xffffffff) {
            throw new \InvalidArgumentException('Column spec raw value must fit in u32.');
        }
    }

    /**
     * @param array{values?:list<mixed>} $tree
     * @return list<mixed>
     */
    private function sequenceTreeValues(array $tree): array
    {
        $values = $tree['values'] ?? [];
        if (! is_array($values) || ! array_is_list($values)) {
            throw new \InvalidArgumentException('SequenceTree must contain a list of values.');
        }

        return array_values($values);
    }

    private function encodeUnsignedLeb128Int(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Unsigned LEB128 encoding requires a non-negative integer.');
        }

        $bytes = '';
        do {
            $byte = $value & 0x7f;
            $value = intdiv($value, 128);
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $bytes .= chr($byte);
        } while ($value !== 0);

        return $bytes;
    }

    private function decodeUnsignedLeb128Int(string $bytes, int &$offset): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $byte = ord($bytes[$offset]);
            ++$offset;
            $result += ($byte & 0x7f) << $shift;
            if (($byte & 0x80) === 0) {
                return $result;
            }

            $shift += 7;
            if ($shift >= PHP_INT_SIZE * 8 - 1) {
                throw new \InvalidArgumentException('Unsigned LEB128 integer is too large for PHP int.');
            }
        }

        throw new \InvalidArgumentException('Truncated unsigned LEB128 integer.');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function appendRleNull(array $state, string &$bytes): array
    {
        return match ($state['type'] ?? 'empty') {
            'empty' => ['type' => 'initialNullRun', 'len' => 1],
            'initialNullRun' => ['type' => 'initialNullRun', 'len' => ((int) $state['len']) + 1],
            'nullRun' => ['type' => 'nullRun', 'len' => ((int) $state['len']) + 1],
            'loneVal' => $this->afterFlushingLiteralRun([(int) $state['value']], $bytes, 'nullRun'),
            'run' => $this->afterFlushingRun((int) $state['value'], (int) $state['len'], $bytes, 'nullRun'),
            'literalRun' => $this->afterFlushingLiteralRun(
                array_merge($state['run'], [(int) $state['last']]),
                $bytes,
                'nullRun'
            ),
            default => throw new \InvalidArgumentException('Invalid RLE encoder state.'),
        };
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function appendRleIntValue(array $state, int $value, string &$bytes): array
    {
        return match ($state['type'] ?? 'empty') {
            'empty' => ['type' => 'loneVal', 'value' => $value],
            'loneVal' => ((int) $state['value']) === $value
                ? ['type' => 'run', 'value' => $value, 'len' => 2]
                : ['type' => 'literalRun', 'last' => $value, 'run' => [(int) $state['value']]],
            'run' => ((int) $state['value']) === $value
                ? ['type' => 'run', 'value' => $value, 'len' => ((int) $state['len']) + 1]
                : $this->afterFlushingRun((int) $state['value'], (int) $state['len'], $bytes, 'loneVal', $value),
            'literalRun' => ((int) $state['last']) === $value
                ? $this->afterFlushingLiteralRun($state['run'], $bytes, 'run', $value)
                : ['type' => 'literalRun', 'last' => $value, 'run' => array_merge($state['run'], [(int) $state['last']])],
            'nullRun', 'initialNullRun' => $this->afterFlushingNullRun((int) $state['len'], $bytes, $value),
            default => throw new \InvalidArgumentException('Invalid RLE encoder state.'),
        };
    }

    /**
     * @param array<string,mixed> $state
     */
    private function flushRleIntState(array $state, string &$bytes): void
    {
        match ($state['type'] ?? 'empty') {
            'empty', 'initialNullRun' => null,
            'nullRun' => $this->flushRleNullRun((int) $state['len'], $bytes),
            'loneVal' => $this->flushRleLiteralRun([(int) $state['value']], $bytes),
            'run' => $this->flushRleRun((int) $state['value'], (int) $state['len'], $bytes),
            'literalRun' => $this->flushRleLiteralRun(array_merge($state['run'], [(int) $state['last']]), $bytes),
            default => throw new \InvalidArgumentException('Invalid RLE encoder state.'),
        };
    }

    /**
     * @param list<int> $run
     * @return array<string,mixed>
     */
    private function afterFlushingLiteralRun(array $run, string &$bytes, string $nextType, ?int $value = null): array
    {
        $this->flushRleLiteralRun($run, $bytes);

        return $nextType === 'run'
            ? ['type' => 'run', 'value' => $value, 'len' => 2]
            : ['type' => $nextType, 'len' => 1];
    }

    /**
     * @return array<string,mixed>
     */
    private function afterFlushingRun(int $value, int $len, string &$bytes, string $nextType, ?int $nextValue = null): array
    {
        $this->flushRleRun($value, $len, $bytes);

        return $nextType === 'loneVal'
            ? ['type' => 'loneVal', 'value' => $nextValue]
            : ['type' => $nextType, 'len' => 1];
    }

    /**
     * @return array<string,mixed>
     */
    private function afterFlushingNullRun(int $len, string &$bytes, int $value): array
    {
        $this->flushRleNullRun($len, $bytes);

        return ['type' => 'loneVal', 'value' => $value];
    }

    private function flushRleRun(int $value, int $len, string &$bytes): void
    {
        $bytes .= $this->encodeSignedLeb128Int($len);
        $bytes .= $this->encodeSignedLeb128Int($value);
    }

    private function flushRleNullRun(int $len, string &$bytes): void
    {
        $bytes .= $this->encodeSignedLeb128Int(0);
        $bytes .= $this->encodeUnsignedLeb128Int($len);
    }

    /**
     * @param list<int> $run
     */
    private function flushRleLiteralRun(array $run, string &$bytes): void
    {
        $bytes .= $this->encodeSignedLeb128Int(-count($run));
        foreach ($run as $value) {
            $bytes .= $this->encodeSignedLeb128Int($value);
        }
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function appendRleStringNull(array $state, string &$bytes): array
    {
        return match ($state['type'] ?? 'empty') {
            'empty' => ['type' => 'initialNullRun', 'len' => 1],
            'initialNullRun' => ['type' => 'initialNullRun', 'len' => ((int) $state['len']) + 1],
            'nullRun' => ['type' => 'nullRun', 'len' => ((int) $state['len']) + 1],
            'loneVal' => $this->afterFlushingStringLiteralRun([(string) $state['value']], $bytes, 'nullRun'),
            'run' => $this->afterFlushingStringRun((string) $state['value'], (int) $state['len'], $bytes, 'nullRun'),
            'literalRun' => $this->afterFlushingStringLiteralRun(
                array_merge($state['run'], [(string) $state['last']]),
                $bytes,
                'nullRun'
            ),
            default => throw new \InvalidArgumentException('Invalid string RLE encoder state.'),
        };
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function appendRleStringValue(array $state, string $value, string &$bytes): array
    {
        return match ($state['type'] ?? 'empty') {
            'empty' => ['type' => 'loneVal', 'value' => $value],
            'loneVal' => ((string) $state['value']) === $value
                ? ['type' => 'run', 'value' => $value, 'len' => 2]
                : ['type' => 'literalRun', 'last' => $value, 'run' => [(string) $state['value']]],
            'run' => ((string) $state['value']) === $value
                ? ['type' => 'run', 'value' => $value, 'len' => ((int) $state['len']) + 1]
                : $this->afterFlushingStringRun((string) $state['value'], (int) $state['len'], $bytes, 'loneVal', $value),
            'literalRun' => ((string) $state['last']) === $value
                ? $this->afterFlushingStringLiteralRun($state['run'], $bytes, 'run', $value)
                : ['type' => 'literalRun', 'last' => $value, 'run' => array_merge($state['run'], [(string) $state['last']])],
            'nullRun', 'initialNullRun' => $this->afterFlushingStringNullRun((int) $state['len'], $bytes, $value),
            default => throw new \InvalidArgumentException('Invalid string RLE encoder state.'),
        };
    }

    /**
     * @param array<string,mixed> $state
     */
    private function flushRleStringState(array $state, string &$bytes): void
    {
        match ($state['type'] ?? 'empty') {
            'empty', 'initialNullRun' => null,
            'nullRun' => $this->flushRleNullRun((int) $state['len'], $bytes),
            'loneVal' => $this->flushRleStringLiteralRun([(string) $state['value']], $bytes),
            'run' => $this->flushRleStringRun((string) $state['value'], (int) $state['len'], $bytes),
            'literalRun' => $this->flushRleStringLiteralRun(array_merge($state['run'], [(string) $state['last']]), $bytes),
            default => throw new \InvalidArgumentException('Invalid string RLE encoder state.'),
        };
    }

    /**
     * @param list<string> $run
     * @return array<string,mixed>
     */
    private function afterFlushingStringLiteralRun(array $run, string &$bytes, string $nextType, ?string $value = null): array
    {
        $this->flushRleStringLiteralRun($run, $bytes);

        return $nextType === 'run'
            ? ['type' => 'run', 'value' => $value, 'len' => 2]
            : ['type' => $nextType, 'len' => 1];
    }

    /**
     * @return array<string,mixed>
     */
    private function afterFlushingStringRun(string $value, int $len, string &$bytes, string $nextType, ?string $nextValue = null): array
    {
        $this->flushRleStringRun($value, $len, $bytes);

        return $nextType === 'loneVal'
            ? ['type' => 'loneVal', 'value' => $nextValue]
            : ['type' => $nextType, 'len' => 1];
    }

    /**
     * @return array<string,mixed>
     */
    private function afterFlushingStringNullRun(int $len, string &$bytes, string $value): array
    {
        $this->flushRleNullRun($len, $bytes);

        return ['type' => 'loneVal', 'value' => $value];
    }

    private function flushRleStringRun(string $value, int $len, string &$bytes): void
    {
        $bytes .= $this->encodeSignedLeb128Int($len);
        $bytes .= $this->encodeColumnarString($value);
    }

    /**
     * @param list<string> $run
     */
    private function flushRleStringLiteralRun(array $run, string &$bytes): void
    {
        $bytes .= $this->encodeSignedLeb128Int(-count($run));
        foreach ($run as $value) {
            $bytes .= $this->encodeColumnarString($value);
        }
    }

    private function encodeColumnarString(string $value): string
    {
        return $this->encodeUnsignedLeb128Int(strlen($value)) . $value;
    }

    private function decodeColumnarString(string $bytes, int &$offset): string
    {
        $length = $this->decodeUnsignedLeb128Int($bytes, $offset);
        if ($offset + $length > strlen($bytes)) {
            throw new \InvalidArgumentException('Truncated columnar string value.');
        }

        $value = substr($bytes, $offset, $length);
        $offset += $length;

        return $value;
    }

    /**
     * @param array<string,mixed> $value
     * @return array{0:int,1:string}
     */
    private function columnarEncodeScalarValue(array $value): array
    {
        $type = $value['type'] ?? null;
        $rawValue = $value['value'] ?? null;

        return match ($type) {
            'null' => [0, ''],
            'boolean' => is_bool($rawValue)
                ? [$rawValue ? 2 : 1, '']
                : throw new \InvalidArgumentException('Boolean scalar values must contain a boolean.'),
            'uint' => $this->columnarEncodeUintScalar($rawValue),
            'int' => $this->columnarEncodeSignedScalar($rawValue, 4, 'Int'),
            'float64' => is_float($rawValue) || is_int($rawValue)
                ? [(8 << 4) | 5, pack('e', (float) $rawValue)]
                : throw new \InvalidArgumentException('Float scalar values must contain a number.'),
            'string' => is_string($rawValue)
                ? [(strlen($rawValue) << 4) | 6, $rawValue]
                : throw new \InvalidArgumentException('String scalar values must contain a string.'),
            'bytes' => $this->columnarEncodeBytesScalar($rawValue, 7),
            'counter' => $this->columnarEncodeSignedScalar($rawValue, 8, 'Counter'),
            'timestamp' => $this->columnarEncodeSignedScalar($rawValue, 9, 'Timestamp'),
            'unknown' => $this->columnarEncodeUnknownScalar($value),
            default => throw new \InvalidArgumentException('Unsupported scalar value column type.'),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function columnarDecodeScalarValue(int $meta, string $raw, int &$offset): array
    {
        $typeCode = $meta & 0x0f;
        $length = $meta >> 4;
        if ($offset + $length > strlen($raw)) {
            throw new \InvalidArgumentException('Scalar value raw column is shorter than its metadata length.');
        }

        $rawSlice = substr($raw, $offset, $length);
        $offset += $length;

        return match ($typeCode) {
            0 => $this->decodeFixedLengthScalar($rawSlice, 0, ['type' => 'null', 'value' => null]),
            1 => $this->decodeFixedLengthScalar($rawSlice, 0, ['type' => 'boolean', 'value' => false]),
            2 => $this->decodeFixedLengthScalar($rawSlice, 0, ['type' => 'boolean', 'value' => true]),
            3 => ['type' => 'uint', 'value' => $this->decodeUnsignedScalarRaw($rawSlice, 'Uint')],
            4 => ['type' => 'int', 'value' => $this->decodeSignedScalarRaw($rawSlice, 'Int')],
            5 => ['type' => 'float64', 'value' => $this->decodeFloatScalarRaw($rawSlice)],
            6 => ['type' => 'string', 'value' => $rawSlice],
            7 => ['type' => 'bytes', 'value' => $this->columnarBytesToList($rawSlice)],
            8 => ['type' => 'counter', 'value' => $this->decodeSignedScalarRaw($rawSlice, 'Counter')],
            9 => ['type' => 'timestamp', 'value' => $this->decodeSignedScalarRaw($rawSlice, 'Timestamp')],
            default => ['type' => 'unknown', 'code' => $typeCode, 'value' => $this->columnarBytesToList($rawSlice)],
        };
    }

    /**
     * @return array{0:int,1:string}
     */
    private function columnarEncodeUintScalar(mixed $value): array
    {
        if (! is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('Uint scalar values must contain a non-negative integer.');
        }

        $raw = $this->encodeUnsignedLeb128Int($value);

        return [(strlen($raw) << 4) | 3, $raw];
    }

    /**
     * @return array{0:int,1:string}
     */
    private function columnarEncodeSignedScalar(mixed $value, int $typeCode, string $label): array
    {
        if (! is_int($value)) {
            throw new \InvalidArgumentException($label . ' scalar values must contain an integer.');
        }

        $raw = $this->encodeSignedLeb128Int($value);

        return [(strlen($raw) << 4) | $typeCode, $raw];
    }

    /**
     * @return array{0:int,1:string}
     */
    private function columnarEncodeBytesScalar(mixed $value, int $typeCode): array
    {
        $raw = $this->columnarByteListToString($value);

        return [(strlen($raw) << 4) | $typeCode, $raw];
    }

    /**
     * @param array<string,mixed> $value
     * @return array{0:int,1:string}
     */
    private function columnarEncodeUnknownScalar(array $value): array
    {
        $code = $value['code'] ?? null;
        if (! is_int($code) || $code < 10 || $code > 15) {
            throw new \InvalidArgumentException('Unknown scalar values must use a type code from 10 through 15.');
        }

        return $this->columnarEncodeBytesScalar($value['value'] ?? null, $code);
    }

    private function decodeUnsignedScalarRaw(string $raw, string $label): int
    {
        $offset = 0;
        $value = $this->decodeUnsignedLeb128Int($raw, $offset);
        if ($offset !== strlen($raw)) {
            throw new \InvalidArgumentException($label . ' scalar value contains extra bytes.');
        }

        return $value;
    }

    private function decodeSignedScalarRaw(string $raw, string $label): int
    {
        $offset = 0;
        $value = $this->decodeSignedLeb128Int($raw, $offset);
        if ($offset !== strlen($raw)) {
            throw new \InvalidArgumentException($label . ' scalar value contains extra bytes.');
        }

        return $value;
    }

    private function decodeFloatScalarRaw(string $raw): float
    {
        if (strlen($raw) !== 8) {
            throw new \InvalidArgumentException('Float scalar value must be exactly 8 bytes.');
        }

        $decoded = unpack('evalue', $raw);

        return (float) $decoded['value'];
    }

    /**
     * @param array<string,mixed> $decoded
     * @return array<string,mixed>
     */
    private function decodeFixedLengthScalar(string $raw, int $expectedLength, array $decoded): array
    {
        if (strlen($raw) !== $expectedLength) {
            throw new \InvalidArgumentException('Scalar metadata length does not match type payload.');
        }

        return $decoded;
    }

    private function columnarByteListToString(mixed $bytes): string
    {
        if (! is_array($bytes)) {
            throw new \InvalidArgumentException('Byte scalar values must contain a byte array.');
        }

        $raw = '';
        foreach (array_values($bytes) as $byte) {
            if (! is_int($byte) || $byte < 0 || $byte > 255) {
                throw new \InvalidArgumentException('Byte scalar arrays must contain byte integers.');
            }

            $raw .= chr($byte);
        }

        return $raw;
    }

    /**
     * @return list<int>
     */
    private function columnarBytesToList(string $bytes): array
    {
        if ($bytes === '') {
            return [];
        }

        return array_values(unpack('C*', $bytes));
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function appendRleUintValue(array $state, int $value, string &$bytes): array
    {
        return match ($state['type'] ?? 'empty') {
            'empty' => ['type' => 'loneVal', 'value' => $value],
            'loneVal' => ((int) $state['value']) === $value
                ? ['type' => 'run', 'value' => $value, 'len' => 2]
                : ['type' => 'literalRun', 'last' => $value, 'run' => [(int) $state['value']]],
            'run' => ((int) $state['value']) === $value
                ? ['type' => 'run', 'value' => $value, 'len' => ((int) $state['len']) + 1]
                : $this->afterFlushingUintRun((int) $state['value'], (int) $state['len'], $bytes, $value),
            'literalRun' => ((int) $state['last']) === $value
                ? $this->afterFlushingUintLiteralRun($state['run'], $bytes, $value)
                : ['type' => 'literalRun', 'last' => $value, 'run' => array_merge($state['run'], [(int) $state['last']])],
            default => throw new \InvalidArgumentException('Invalid unsigned RLE encoder state.'),
        };
    }

    /**
     * @param array<string,mixed> $state
     */
    private function flushRleUintState(array $state, string &$bytes): void
    {
        match ($state['type'] ?? 'empty') {
            'empty' => null,
            'loneVal' => $this->flushRleUintLiteralRun([(int) $state['value']], $bytes),
            'run' => $this->flushRleUintRun((int) $state['value'], (int) $state['len'], $bytes),
            'literalRun' => $this->flushRleUintLiteralRun(array_merge($state['run'], [(int) $state['last']]), $bytes),
            default => throw new \InvalidArgumentException('Invalid unsigned RLE encoder state.'),
        };
    }

    /**
     * @param list<int> $run
     * @return array<string,mixed>
     */
    private function afterFlushingUintLiteralRun(array $run, string &$bytes, int $value): array
    {
        $this->flushRleUintLiteralRun($run, $bytes);

        return ['type' => 'run', 'value' => $value, 'len' => 2];
    }

    /**
     * @return array<string,mixed>
     */
    private function afterFlushingUintRun(int $value, int $len, string &$bytes, int $nextValue): array
    {
        $this->flushRleUintRun($value, $len, $bytes);

        return ['type' => 'loneVal', 'value' => $nextValue];
    }

    private function flushRleUintRun(int $value, int $len, string &$bytes): void
    {
        $bytes .= $this->encodeSignedLeb128Int($len);
        $bytes .= $this->encodeUnsignedLeb128Int($value);
    }

    /**
     * @param list<int> $run
     */
    private function flushRleUintLiteralRun(array $run, string &$bytes): void
    {
        $bytes .= $this->encodeSignedLeb128Int(-count($run));
        foreach ($run as $value) {
            $bytes .= $this->encodeUnsignedLeb128Int($value);
        }
    }

    /**
     * @param mixed $range
     */
    private function columnarRangeSlice(string $bytes, mixed $range, string $column): string
    {
        if (
            ! is_array($range)
            || ! array_key_exists(0, $range)
            || ! array_key_exists(1, $range)
            || ! is_int($range[0])
            || ! is_int($range[1])
            || $range[0] < 0
            || $range[1] < $range[0]
            || $range[1] > strlen($bytes)
        ) {
            throw new \InvalidArgumentException('Invalid columnar ' . $column . ' range.');
        }

        return substr($bytes, $range[0], $range[1] - $range[0]);
    }

    private function encodeSignedLeb128Int(int $value): string
    {
        $bytes = '';
        do {
            $byte = $value & 0x7f;
            $value >>= 7;
            $done = ($value === 0 && ($byte & 0x40) === 0) || ($value === -1 && ($byte & 0x40) !== 0);
            if (! $done) {
                $byte |= 0x80;
            }
            $bytes .= chr($byte);
        } while (! $done);

        return $bytes;
    }

    private function decodeSignedLeb128Int(string $bytes, int &$offset): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $byte = ord($bytes[$offset]);
            ++$offset;
            $result |= ($byte & 0x7f) << $shift;
            $shift += 7;
            if (($byte & 0x80) === 0) {
                if ($shift < PHP_INT_SIZE * 8 && ($byte & 0x40) !== 0) {
                    $result |= -(1 << $shift);
                }

                return $result;
            }

            if ($shift >= PHP_INT_SIZE * 8 - 1) {
                throw new \InvalidArgumentException('Signed LEB128 integer is too large for PHP int.');
            }
        }

        throw new \InvalidArgumentException('Truncated signed LEB128 integer.');
    }

    private function utf16Length(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    private function clusterIndexFromUtf16Index(string $text, int $utf16Index): int
    {
        $utf16Index = max(0, $utf16Index);
        $offset = 0;
        foreach ($this->splitCharacters($text) as $clusterIndex => $cluster) {
            $nextOffset = $offset + $this->utf16Length($cluster);
            if ($utf16Index < $nextOffset) {
                return $clusterIndex;
            }

            if ($utf16Index === $nextOffset) {
                return $clusterIndex + 1;
            }

            $offset = $nextOffset;
        }

        return count($this->splitCharacters($text));
    }

    private function clusterIndexFromEncodedIndex(string $text, int $index, string $encoding): int
    {
        return match ($encoding) {
            'UnicodeCodePoint', 'codepoint', 'code_point', 'unicode-code-point' => $this->clusterIndexFromMeasuredIndex(
                $text,
                max(0, $index),
                static fn (string $cluster): int => mb_strlen($cluster, 'UTF-8')
            ),
            'Utf8CodeUnit', 'utf8', 'utf8-code-unit' => $this->clusterIndexFromMeasuredIndex(
                $text,
                max(0, $index),
                static fn (string $cluster): int => strlen($cluster)
            ),
            'Utf16CodeUnit', 'utf16', 'utf16-code-unit' => $this->clusterIndexFromUtf16Index($text, $index),
            'GraphemeCluster', 'grapheme', 'grapheme-cluster' => min(max(0, $index), count($this->splitCharacters($text))),
            default => throw new \InvalidArgumentException('Unsupported text index encoding.'),
        };
    }

    /**
     * @return array{0:int,1:int}
     */
    private function clusterBoundaryAfterEncodedIndex(string $text, int $index, string $encoding): array
    {
        $index = max(0, $index);

        if (in_array($encoding, ['GraphemeCluster', 'grapheme', 'grapheme-cluster'], true)) {
            $clusterIndex = min($index, count($this->splitCharacters($text)));

            return [$clusterIndex, $clusterIndex];
        }

        $measure = $this->encodedTextMeasure($encoding);

        $offset = 0;
        foreach ($this->splitCharacters($text) as $clusterIndex => $cluster) {
            if ($index <= $offset) {
                return [$clusterIndex, $offset];
            }

            $nextOffset = $offset + $measure($cluster);
            if ($index <= $nextOffset) {
                return [$clusterIndex + 1, $nextOffset];
            }

            $offset = $nextOffset;
        }

        return [count($this->splitCharacters($text)), $offset];
    }

    /**
     * @return callable(string):int
     */
    private function encodedTextMeasure(string $encoding): callable
    {
        return match ($encoding) {
            'GraphemeCluster', 'grapheme', 'grapheme-cluster' => fn (string $cluster): int => count($this->splitCharacters($cluster)),
            'UnicodeCodePoint', 'codepoint', 'code_point', 'unicode-code-point' => static fn (string $cluster): int => mb_strlen($cluster, 'UTF-8'),
            'Utf8CodeUnit', 'utf8', 'utf8-code-unit' => static fn (string $cluster): int => strlen($cluster),
            'Utf16CodeUnit', 'utf16', 'utf16-code-unit' => fn (string $cluster): int => $this->utf16Length($cluster),
            default => throw new \InvalidArgumentException('Unsupported text index encoding.'),
        };
    }

    /**
     * @param callable(string):int $measure
     */
    private function clusterIndexFromMeasuredIndex(string $text, int $targetIndex, callable $measure): int
    {
        $offset = 0;
        foreach ($this->splitCharacters($text) as $clusterIndex => $cluster) {
            $nextOffset = $offset + $measure($cluster);
            if ($targetIndex < $nextOffset) {
                return $clusterIndex;
            }

            if ($targetIndex === $nextOffset) {
                return $clusterIndex + 1;
            }

            $offset = $nextOffset;
        }

        return count($this->splitCharacters($text));
    }

    private function encodedIndexForClusterIndex(string $text, int $clusterIndex, string $encoding): int
    {
        return match ($encoding) {
            'UnicodeCodePoint', 'codepoint', 'code_point', 'unicode-code-point' => $this->measuredIndexForClusterIndex(
                $text,
                max(0, $clusterIndex),
                static fn (string $cluster): int => mb_strlen($cluster, 'UTF-8')
            ),
            'Utf8CodeUnit', 'utf8', 'utf8-code-unit' => $this->measuredIndexForClusterIndex(
                $text,
                max(0, $clusterIndex),
                static fn (string $cluster): int => strlen($cluster)
            ),
            'Utf16CodeUnit', 'utf16', 'utf16-code-unit' => $this->utf16IndexForClusterIndex($text, $clusterIndex),
            'GraphemeCluster', 'grapheme', 'grapheme-cluster' => min(max(0, $clusterIndex), count($this->splitCharacters($text))),
            default => throw new \InvalidArgumentException('Unsupported text index encoding.'),
        };
    }

    /**
     * @param callable(string):int $measure
     */
    private function measuredIndexForClusterIndex(string $text, int $clusterIndex, callable $measure): int
    {
        $offset = 0;
        foreach ($this->splitCharacters($text) as $index => $cluster) {
            if ($index >= $clusterIndex) {
                break;
            }

            $offset += $measure($cluster);
        }

        return $offset;
    }

    private function utf16IndexForClusterIndex(string $text, int $clusterIndex): int
    {
        $clusterIndex = max(0, $clusterIndex);
        $offset = 0;
        foreach ($this->splitCharacters($text) as $index => $cluster) {
            if ($index >= $clusterIndex) {
                break;
            }

            $offset += $this->utf16Length($cluster);
        }

        return $offset;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function clusterRangeForUtf16Range(string $text, int $start, int $end): array
    {
        $start = max(0, $start);
        $end = max($start, $end);
        $clusters = $this->splitCharacters($text);
        if ($start === $end) {
            return [$this->clusterIndexFromUtf16Index($text, $start), 0];
        }

        $offset = 0;
        $first = null;
        $last = null;
        foreach ($clusters as $index => $cluster) {
            $nextOffset = $offset + $this->utf16Length($cluster);
            if ($nextOffset <= $start) {
                $offset = $nextOffset;
                continue;
            }

            if ($offset >= $end) {
                break;
            }

            $first ??= $index;
            $last = $index;
            $offset = $nextOffset;
        }

        if ($first === null || $last === null) {
            return [$this->clusterIndexFromUtf16Index($text, $start), 0];
        }

        return [$first, $last - $first + 1];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function assignmentPatches(string $key, mixed $value): array
    {
        if (is_string($value)) {
            return [
                ['action' => 'put', 'path' => [$key], 'value' => ''],
                ['action' => 'splice', 'path' => [$key, 0], 'value' => $value],
            ];
        }

        if (is_array($value) && array_is_list($value)) {
            $patches = [['action' => 'put', 'path' => [$key], 'value' => []]];
            if ($value !== [] && array_reduce($value, static fn (bool $allStrings, mixed $item): bool => $allStrings && is_string($item), true)) {
                $patches[] = ['action' => 'insert', 'path' => [$key, 0], 'values' => array_fill(0, count($value), '')];
                foreach ($value as $index => $item) {
                    $patches[] = ['action' => 'splice', 'path' => [$key, $index, 0], 'value' => $item];
                }

                return $patches;
            }

            foreach ($value as $index => $item) {
                if (is_string($item)) {
                    $patches[] = ['action' => 'insert', 'path' => [$key, $index], 'values' => ['']];
                    $patches[] = ['action' => 'splice', 'path' => [$key, $index, 0], 'value' => $item];
                    continue;
                }

                $patches[] = ['action' => 'insert', 'path' => [$key, $index], 'values' => [$item]];
            }

            return $patches;
        }

        return [
            ['action' => 'put', 'path' => [$key], 'value' => $value],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function assignmentPatchesForDocumentValue(Document $document, string $key, mixed $value): array
    {
        if (! is_string($value)) {
            return $this->assignmentPatches($key, $value);
        }

        $marks = $document->marksFor([$key]);
        if ($marks === []) {
            return $this->assignmentPatches($key, $value);
        }

        return $this->markedStringAssignmentPatches([$key], $value, $marks);
    }

    /**
     * @param list<string|int> $path
     * @param list<array{name:string,value:mixed,start:int,end:int}> $marks
     * @return list<array<string,mixed>>
     */
    private function markedStringAssignmentPatches(array $path, string $value, array $marks): array
    {
        $patches = [['action' => 'put', 'path' => $path, 'value' => '']];
        $characters = $this->splitCharacters($value);
        if ($characters === []) {
            return $patches;
        }

        $segmentStart = 0;
        $segmentMarks = $this->activeMarksAtOffset($marks, 0);
        $length = count($characters);
        for ($index = 1; $index < $length; ++$index) {
            $active = $this->activeMarksAtOffset($marks, $index);
            if ($active === $segmentMarks) {
                continue;
            }

            $patch = [
                'action' => 'splice',
                'path' => array_merge($path, [$segmentStart]),
                'value' => implode('', array_slice($characters, $segmentStart, $index - $segmentStart)),
            ];
            if ($segmentMarks !== []) {
                $patch['marks'] = $segmentMarks;
            }
            $patches[] = $patch;
            $segmentStart = $index;
            $segmentMarks = $active;
        }

        $patch = [
            'action' => 'splice',
            'path' => array_merge($path, [$segmentStart]),
            'value' => implode('', array_slice($characters, $segmentStart)),
        ];
        if ($segmentMarks !== []) {
            $patch['marks'] = $segmentMarks;
        }
        $patches[] = $patch;

        return $patches;
    }

    /**
     * @param list<string|int> $path
     * @param list<array{name:string,value:mixed,start:int,end:int}> $marks
     * @return list<array<string,mixed>>
     */
    private function markedStringDiffPatches(string $before, string $after, array $path, array $marks): array
    {
        $beforeCharacters = $this->splitCharacters($before);
        $afterCharacters = $this->splitCharacters($after);
        $beforeLength = count($beforeCharacters);
        $afterLength = count($afterCharacters);
        $prefix = 0;

        while ($prefix < $beforeLength && $prefix < $afterLength && $beforeCharacters[$prefix] === $afterCharacters[$prefix]) {
            ++$prefix;
        }

        $suffix = 0;
        while (
            $suffix < ($beforeLength - $prefix)
            && $suffix < ($afterLength - $prefix)
            && $beforeCharacters[$beforeLength - 1 - $suffix] === $afterCharacters[$afterLength - 1 - $suffix]
        ) {
            ++$suffix;
        }

        $deleted = $beforeLength - $prefix - $suffix;
        $inserted = array_slice($afterCharacters, $prefix, $afterLength - $prefix - $suffix);
        if ($deleted !== 0 || $inserted === []) {
            return $this->markedStringAssignmentPatches($path, $after, $marks);
        }

        $patches = [];
        $segmentStart = 0;
        $segmentMarks = $this->activeMarksAtOffset($marks, $prefix);
        $insertedLength = count($inserted);
        for ($index = 1; $index < $insertedLength; ++$index) {
            $active = $this->activeMarksAtOffset($marks, $prefix + $index);
            if ($active === $segmentMarks) {
                continue;
            }

            $patch = [
                'action' => 'splice',
                'path' => array_merge($path, [$prefix + $segmentStart]),
                'value' => implode('', array_slice($inserted, $segmentStart, $index - $segmentStart)),
            ];
            if ($segmentMarks !== []) {
                $patch['marks'] = $segmentMarks;
            }
            $patches[] = $patch;
            $segmentStart = $index;
            $segmentMarks = $active;
        }

        $patch = [
            'action' => 'splice',
            'path' => array_merge($path, [$prefix + $segmentStart]),
            'value' => implode('', array_slice($inserted, $segmentStart)),
        ];
        if ($segmentMarks !== []) {
            $patch['marks'] = $segmentMarks;
        }
        $patches[] = $patch;

        return $patches;
    }

    /**
     * @param list<array{name:string,value:mixed,start:int,end:int}> $marks
     * @return array<string,mixed>
     */
    private function activeMarksAtOffset(array $marks, int $offset): array
    {
        $active = [];
        foreach ($marks as $mark) {
            if ($mark['start'] <= $offset && $offset < $mark['end']) {
                $active[$mark['name']] = $mark['value'];
            }
        }

        ksort($active, SORT_STRING);

        return $active;
    }

    /**
     * @param list<array<string,mixed>> $changes
     * @return list<array<string,mixed>>
     */
    private function patchesForChanges(Document $after, array $changes): array
    {
        $patches = [];
        foreach ($changes as $change) {
            $ops = is_array($change['ops'] ?? null) ? array_values($change['ops']) : [];
            foreach ($ops as $op) {
                if (! is_array($op) || ! is_string($op['action'] ?? null)) {
                    continue;
                }

                if ($op['action'] === 'set' && is_string($op['key'] ?? null)) {
                    $patches = array_merge($patches, $this->assignmentPatchesForDocumentValue($after, $op['key'], $after->toArray()[$op['key']] ?? null));
                    continue;
                }

                if ($op['action'] === 'delete' && is_string($op['key'] ?? null)) {
                    $patches[] = ['action' => 'del', 'path' => [$op['key']]];
                    continue;
                }

                if ($op['action'] === 'setNested' && is_array($op['path'] ?? null)) {
                    $path = array_values($op['path']);
                    $patches[] = ['action' => 'put', 'path' => $path, 'value' => $this->readPath($after, $path)];
                    continue;
                }

                if ($op['action'] === 'deleteNested' && is_array($op['path'] ?? null)) {
                    $patches[] = ['action' => 'del', 'path' => array_values($op['path'])];
                    continue;
                }

                if ($op['action'] === 'mark' && is_array($op['path'] ?? null) && is_array($op['marks'] ?? null)) {
                    $marks = $this->publicPatchMarks(array_values($op['marks']));
                    if ($marks !== []) {
                        $patches[] = ['action' => 'mark', 'path' => array_values($op['path']), 'marks' => $marks];
                    }
                    continue;
                }

                if ($op['action'] === 'unmark' && is_array($op['path'] ?? null) && is_string($op['name'] ?? null)) {
                    $patches[] = [
                        'action' => 'mark',
                        'path' => array_values($op['path']),
                        'marks' => [[
                            'name' => $op['name'],
                            'value' => null,
                            'start' => max(0, (int) ($op['start'] ?? 0)),
                            'end' => max(0, (int) ($op['end'] ?? $op['start'] ?? 0)),
                        ]],
                    ];
                    continue;
                }

                if ($op['action'] === 'putText' && is_string($op['key'] ?? null)) {
                    $patches[] = [
                        'action' => 'putSeq',
                        'path' => [$op['key'], max(0, (int) ($op['index'] ?? 0))],
                        'value' => is_string($op['value'] ?? null) ? $op['value'] : '',
                    ];
                    continue;
                }

                if ($op['action'] === 'splice' && is_string($op['key'] ?? null)) {
                    $index = max(0, (int) ($op['index'] ?? 0));
                    $deleteCount = max(0, (int) ($op['deleteCount'] ?? 0));
                    $insert = is_string($op['insert'] ?? null) ? $op['insert'] : '';
                    if ($deleteCount > 0) {
                        $patches[] = ['action' => 'del', 'path' => [$op['key'], $index], 'length' => $deleteCount];
                    }

                    if ($insert !== '') {
                        $patch = ['action' => 'splice', 'path' => [$op['key'], $index], 'value' => $insert];
                        $marks = $this->marksAt($after, [$op['key']], $index);
                        if ($marks !== []) {
                            $patch['marks'] = $marks;
                        }
                        $patches[] = $patch;
                    }
                }
            }
        }

        return $patches;
    }

    /**
     * @param list<array<string,mixed>> $marks
     * @return list<array{name:string,value:mixed,start:int,end:int}>
     */
    private function publicPatchMarks(array $marks): array
    {
        $public = [];
        foreach ($marks as $mark) {
            if (! is_array($mark) || ! is_string($mark['name'] ?? null)) {
                continue;
            }

            $start = max(0, (int) ($mark['start'] ?? 0));
            $end = max($start, (int) ($mark['end'] ?? $start));
            $public[] = [
                'name' => $mark['name'],
                'value' => $mark['value'] ?? true,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $public;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function markPatchesBetweenDocuments(Document $before, Document $after): array
    {
        $beforeMarks = $this->marksByPathKey($before);
        $afterMarks = $this->marksByPathKey($after);
        $pathKeys = array_values(array_unique(array_merge(array_keys($beforeMarks), array_keys($afterMarks))));
        sort($pathKeys, SORT_STRING);

        $patches = [];
        foreach ($pathKeys as $pathKey) {
            $path = $afterMarks[$pathKey]['path'] ?? $beforeMarks[$pathKey]['path'] ?? null;
            if (! is_array($path)) {
                continue;
            }

            $marks = array_merge(
                $this->addedMarkSegments($beforeMarks[$pathKey]['marks'] ?? [], $afterMarks[$pathKey]['marks'] ?? []),
                $this->removedMarkSegments($beforeMarks[$pathKey]['marks'] ?? [], $afterMarks[$pathKey]['marks'] ?? [])
            );
            if ($marks === []) {
                continue;
            }

            usort(
                $marks,
                static fn (array $left, array $right): int => [$left['start'], $left['end'], $left['name']] <=> [$right['start'], $right['end'], $right['name']]
            );
            $patches[] = ['action' => 'mark', 'path' => array_values($path), 'marks' => $marks];
        }

        return $patches;
    }

    /**
     * @return array<string,array{path:list<string|int>,marks:list<array{name:string,value:mixed,start:int,end:int}>}>
     */
    private function marksByPathKey(Document $document): array
    {
        $marks = [];
        foreach ($document->allMarks() as $entry) {
            $marks[$this->pathKey($entry['path'])] = [
                'path' => $entry['path'],
                'marks' => $entry['marks'],
            ];
        }

        return $marks;
    }

    /**
     * @param list<array{name:string,value:mixed,start:int,end:int}> $before
     * @param list<array{name:string,value:mixed,start:int,end:int}> $after
     * @return list<array{name:string,value:mixed,start:int,end:int}>
     */
    private function addedMarkSegments(array $before, array $after): array
    {
        $added = [];
        foreach ($after as $mark) {
            foreach ($this->subtractMarkIntervals($mark, $this->matchingMarkIntervals($before, $mark)) as $segment) {
                $added[] = [
                    'name' => $mark['name'],
                    'value' => $mark['value'],
                    'start' => $segment[0],
                    'end' => $segment[1],
                ];
            }
        }

        return $added;
    }

    /**
     * @param list<array{name:string,value:mixed,start:int,end:int}> $before
     * @param list<array{name:string,value:mixed,start:int,end:int}> $after
     * @return list<array{name:string,value:null,start:int,end:int}>
     */
    private function removedMarkSegments(array $before, array $after): array
    {
        $removed = [];
        foreach ($before as $mark) {
            foreach ($this->subtractMarkIntervals($mark, $this->matchingMarkIntervals($after, $mark)) as $segment) {
                $removed[] = [
                    'name' => $mark['name'],
                    'value' => null,
                    'start' => $segment[0],
                    'end' => $segment[1],
                ];
            }
        }

        return $removed;
    }

    /**
     * @param list<array{name:string,value:mixed,start:int,end:int}> $marks
     * @return list<array{start:int,end:int}>
     */
    private function matchingMarkIntervals(array $marks, array $target): array
    {
        $matching = [];
        foreach ($marks as $mark) {
            if ($mark['name'] === $target['name'] && $mark['value'] === $target['value']) {
                $matching[] = ['start' => $mark['start'], 'end' => $mark['end']];
            }
        }

        usort($matching, static fn (array $left, array $right): int => [$left['start'], $left['end']] <=> [$right['start'], $right['end']]);

        return $matching;
    }

    /**
     * @param array{name:string,value:mixed,start:int,end:int} $mark
     * @param list<array{start:int,end:int}> $coverage
     * @return list<array{0:int,1:int}>
     */
    private function subtractMarkIntervals(array $mark, array $coverage): array
    {
        $segments = [[$mark['start'], $mark['end']]];
        foreach ($coverage as $cover) {
            $next = [];
            foreach ($segments as $segment) {
                [$start, $end] = $segment;
                $coverStart = max($start, $cover['start']);
                $coverEnd = min($end, $cover['end']);
                if ($coverStart >= $coverEnd) {
                    $next[] = [$start, $end];
                    continue;
                }

                if ($start < $coverStart) {
                    $next[] = [$start, $coverStart];
                }

                if ($coverEnd < $end) {
                    $next[] = [$coverEnd, $end];
                }
            }

            $segments = $next;
            if ($segments === []) {
                break;
            }
        }

        return array_values(array_filter($segments, static fn (array $segment): bool => $segment[0] < $segment[1]));
    }

    /**
     * @param list<array<string,mixed>> $patches
     */
    private function emitPatches(Document $before, Document $after, array $patches): void
    {
        if ($this->patchCallback === null || $patches === []) {
            return;
        }

        ($this->patchCallback)($patches, ['before' => $before, 'after' => $after]);
    }

    private function emitDocumentPatchCallback(Document $before, Document $after, string $source, ?array $patches = null): void
    {
        if (! $this->documentPatchCallbacks->contains($before)) {
            return;
        }

        $patches ??= $this->patchesBetweenDocuments($before, $after);
        if ($patches === []) {
            $this->documentPatchCallbacks[$after] = $this->documentPatchCallbacks[$before];
            return;
        }

        $patchCallback = $this->documentPatchCallbacks[$before];
        $this->documentPatchCallbacks[$after] = $patchCallback;
        $patchCallback($patches, ['before' => $before, 'after' => $after, 'source' => $source]);
    }

    private function mutableClone(Document $document): Document
    {
        return $document->clone(null, false);
    }

    private function preserveFrozen(Document $source, Document $next): Document
    {
        $result = $source->isFrozen() ? $next->withFrozen(true) : $next;
        if ($this->documentDiffCursors->contains($source)) {
            $this->documentDiffCursors[$result] = $this->documentDiffCursors[$source];
        }

        return $result;
    }

    private function isolationEditDocument(IsolatedDocument $document): Document
    {
        $visibleDocument = $document->visibleDocument()->withFrozen(false);
        $visibleDocument->ensureSequenceAtLeast($document->hiddenDocument()->stats()['sequence']);

        return $visibleDocument;
    }

    /**
     * @param array<string,mixed> $block
     * @return array{parents:list<string|ImmutableString>,type:string|ImmutableString,attrs:array<string,mixed>}
     */
    private function normalizeBlock(array $block): array
    {
        $parents = [];
        if (is_array($block['parents'] ?? null)) {
            foreach ($block['parents'] as $parent) {
                if (is_string($parent) || $parent instanceof ImmutableString) {
                    $parents[] = $parent;
                }
            }
        }
        $type = $block['type'] ?? '';
        $normalized = [];
        foreach ($block as $key => $value) {
            if (! is_string($key) || in_array($key, ['parents', 'type', 'attrs'], true)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return array_merge([
            'parents' => $parents,
            'type' => is_string($type) || $type instanceof ImmutableString ? $type : '',
            'attrs' => is_array($block['attrs'] ?? null) ? $block['attrs'] : [],
        ], $normalized);
    }

    /**
     * @param list<string|int> $path
     * @return list<array{name:string,value:mixed,start:int,end:int}>
     */
    private function blockMarks(Document $document, array $path): array
    {
        return array_values(array_filter(
            $document->marksFor(array_values($path)),
            static fn (array $mark): bool => ($mark['name'] ?? null) === self::BLOCK_MARK_NAME
        ));
    }

    /**
     * @param list<string|int> $path
     * @return array<int,array{parents:list<string|ImmutableString>,type:string|ImmutableString,attrs:array<string,mixed>}>
     */
    private function blockValuesByIndex(Document $document, array $path): array
    {
        $blocks = [];
        foreach ($this->blockMarks($document, $path) as $mark) {
            if (is_array($mark['value'] ?? null)) {
                $blocks[(int) $mark['start']] = $this->normalizeBlock($mark['value']);
            }
        }
        ksort($blocks);

        return $blocks;
    }

    /**
     * @param list<string|int> $path
     * @param array<int,array{parents:list<string|ImmutableString>,type:string|ImmutableString,attrs:array<string,mixed>}> $beforeBlocks
     * @param array<int,array{parents:list<string|ImmutableString>,type:string|ImmutableString,attrs:array<string,mixed>}> $afterBlocks
     * @return list<array<string,mixed>>
     */
    private function blockUpdateSpanPatches(array $path, array $beforeBlocks, array $afterBlocks): array
    {
        $patches = [];
        foreach ($afterBlocks as $index => $afterBlock) {
            $beforeBlock = $beforeBlocks[$index] ?? null;
            if ($beforeBlock === null) {
                continue;
            }

            $beforeParents = $beforeBlock['parents'];
            $afterParents = $afterBlock['parents'];
            if (array_slice($afterParents, 0, count($beforeParents)) !== $beforeParents) {
                continue;
            }

            foreach (array_slice($afterParents, count($beforeParents)) as $offset => $parent) {
                $patches[] = [
                    'action' => 'insert',
                    'path' => array_merge($path, [$index, 'parents', count($beforeParents) + $offset]),
                    'values' => [$parent],
                ];
            }
        }

        return $patches;
    }

    /**
     * @param array{defaultExpand?:string,perMarkExpand?:array<string,string>} $options
     */
    private function expandForMark(string $name, array $options): string
    {
        $perMark = is_array($options['perMarkExpand'] ?? null) ? $options['perMarkExpand'] : [];
        $expand = is_string($perMark[$name] ?? null) ? $perMark[$name] : ($options['defaultExpand'] ?? 'both');

        return $this->normalizeExpandMode($expand, 'both');
    }

    private function normalizeExpandMode(mixed $expand, string $default): string
    {
        if (! is_string($expand)) {
            return $default;
        }

        $expand = match ($expand) {
            'before' => 'start',
            'after' => 'end',
            default => $expand,
        };

        return in_array($expand, ['none', 'start', 'end', 'both'], true) ? $expand : $default;
    }

    /**
     * @param list<string|int> $path
     */
    private function readPath(Document $document, array $path): mixed
    {
        $value = $document->toArray();
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param list<string|int> $path
     */
    private function readRawPath(Document $document, array $path): mixed
    {
        $value = $document->rootValues();
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @return list<array{key:string,pathKey:string|int,value:mixed}>
     */
    private function iterDocumentChildren(mixed $value): array
    {
        if ($value instanceof TextValue) {
            return [[
                'key' => '',
                'pathKey' => 0,
                'value' => $value->toString(),
            ]];
        }

        if (! is_array($value)) {
            return [];
        }

        $children = [];
        foreach ($value as $key => $childValue) {
            $children[] = [
                'key' => is_int($key) ? '' : (string) $key,
                'pathKey' => $key,
                'value' => $childValue,
            ];
        }

        return $children;
    }

    /**
     * @param list<string|int> $path
     * @return array{key:string,path:list<string|int>,kind:string,value:mixed}
     */
    private function iterDocumentEntry(string $key, array $path, mixed $value): array
    {
        if ($value instanceof TextValue) {
            return [
                'key' => $key,
                'path' => $path,
                'kind' => 'text',
                'value' => $value->toString(),
            ];
        }

        if (is_array($value)) {
            return [
                'key' => $key,
                'path' => $path,
                'kind' => array_is_list($value) ? 'list' : 'map',
                'value' => $this->materializeIteratorValue($value),
            ];
        }

        return [
            'key' => $key,
            'path' => $path,
            'kind' => 'scalar',
            'value' => $this->materializeIteratorValue($value),
        ];
    }

    private function materializeIteratorValue(mixed $value): mixed
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
                $materialized[$key] = $this->materializeIteratorValue($item);
            }

            return $materialized;
        }

        return $value;
    }

    /**
     * @param list<string|int> $path
     */
    private function assertTextOperationTarget(Document $document, array $path): void
    {
        $value = $this->readPath($document, $path);
        if ($value === null || is_string($value)) {
            return;
        }

        throw new \InvalidArgumentException('Invalid text operation on non-text value.');
    }

    /**
     * @param list<string|int> $path
     */
    private function assertPathContainerCompatibility(Document $document, array $path): void
    {
        if ($path === []) {
            return;
        }

        $value = $document->toArray();
        $lastIndex = count($path) - 1;
        foreach ($path as $offset => $key) {
            if (! is_array($value)) {
                throw new \InvalidArgumentException('Cannot modify a scalar value as a container.');
            }

            if ($offset === 0) {
                if (is_int($key)) {
                    throw new \InvalidArgumentException('Cannot use a list index on a map value.');
                }
            } elseif ($value !== []) {
                $containerIsList = array_is_list($value);
                if ($containerIsList && ! is_int($key)) {
                    throw new \InvalidArgumentException('Cannot use a map key on a list value.');
                }

                if (! $containerIsList && is_int($key)) {
                    throw new \InvalidArgumentException('Cannot use a list index on a map value.');
                }
            }

            if ($offset === $lastIndex || ! array_key_exists($key, $value)) {
                return;
            }

            $value = $value[$key];
        }
    }

    /**
     * @param list<string|int> $path
     */
    private function resolveCursorIndex(Document $document, array $path, int|string $index): int
    {
        if (is_int($index)) {
            return max(0, $index);
        }

        if ($index === 'start') {
            return 0;
        }

        if ($index === 'end') {
            return count($this->splitCharacters($this->cursorTextAtPath($document, $path)));
        }

        $decoded = $this->decodeCursorPayload($index);
        if (is_array($decoded)) {
            return $this->cursorPositionFromPayload($document, array_values($path), $decoded);
        }

        return is_numeric($index) ? max(0, (int) $index) : 0;
    }

    /**
     * @param list<string|int> $path
     * @return array{0:int,1:int}
     */
    private function resolveTextSpliceRange(Document $document, array $path, int|string $index, int $deleteCount): array
    {
        $path = array_values($path);
        $cursorPayload = is_string($index) ? $this->decodeCursorPayload($index) : null;
        $resolvedIndex = $this->resolveCursorIndex($document, $path, $index);

        if (! is_array($cursorPayload)) {
            if ($deleteCount < 0) {
                $deleteLength = abs($deleteCount);

                return [max(0, $resolvedIndex - $deleteLength), $deleteLength];
            }

            return [$resolvedIndex, $deleteCount];
        }

        if ($deleteCount === 0) {
            return [$resolvedIndex, 0];
        }

        $text = $this->cursorTextAtPath($document, $path);
        $utf16Index = $this->utf16IndexForClusterIndex($text, $resolvedIndex);
        if ($deleteCount < 0) {
            $start = max(0, $utf16Index + $deleteCount);
            $end = $utf16Index;
        } else {
            $start = $utf16Index;
            $end = $utf16Index + $deleteCount;
        }

        [$deleteIndex, $deleteLength] = $this->clusterRangeForUtf16Range($text, $start, $end);
        if ($deleteLength === 0) {
            return [$resolvedIndex, 0];
        }

        return [$deleteIndex, $deleteLength];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeCursorPayload(string $cursor): ?array
    {
        $raw = base64_decode($cursor, true);
        if (! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<string|int> $path
     * @param array<string,mixed> $payload
     */
    private function cursorPositionFromPayload(Document $document, array $path, array $payload): int
    {
        $currentText = $this->cursorTextAtPath($document, $path);
        $currentLength = count($this->splitCharacters($currentText));
        $kind = is_string($payload['kind'] ?? null) ? $payload['kind'] : 'index';
        if ($kind === 'start') {
            return 0;
        }

        if ($kind === 'end') {
            return $currentLength;
        }

        $originalText = is_string($payload['text'] ?? null) ? $payload['text'] : '';
        $originalLength = count($this->splitCharacters($originalText));
        $index = max(0, min((int) ($payload['index'] ?? 0), $originalLength));
        $move = is_string($payload['move'] ?? null) ? $payload['move'] : 'after';

        return $this->translateCursorIndex($originalText, $currentText, $index, $move);
    }

    /**
     * @param list<array<string,mixed>> $patches
     * @param list<string|int> $textPath
     * @return list<array<string,mixed>>
     */
    private function encodeTextPatchIndexes(Document $document, array $patches, array $textPath, string $encoding): array
    {
        $text = $this->cursorTextAtPath($document, $textPath);
        $pathLength = count($textPath);

        return array_map(
            function (array $patch) use ($text, $textPath, $pathLength, $encoding): array {
                $path = is_array($patch['path'] ?? null) ? array_values($patch['path']) : [];
                if (array_slice($path, 0, $pathLength) === $textPath && isset($path[$pathLength]) && is_int($path[$pathLength])) {
                    $path[$pathLength] = $this->encodedIndexForClusterIndex($text, $path[$pathLength], $encoding);
                    $patch['path'] = $path;
                }

                if (array_slice($path, 0, $pathLength) === $textPath && is_array($patch['marks'] ?? null)) {
                    $patch['marks'] = array_map(
                        function (array $mark) use ($text, $encoding): array {
                            $mark['start'] = $this->encodedIndexForClusterIndex($text, (int) ($mark['start'] ?? 0), $encoding);
                            $mark['end'] = $this->encodedIndexForClusterIndex($text, (int) ($mark['end'] ?? $mark['start'] ?? 0), $encoding);

                            return $mark;
                        },
                        array_values($patch['marks'])
                    );
                }

                return $patch;
            },
            $patches
        );
    }

    /**
     * @param list<string|int> $path
     */
    private function cursorTextAtPath(Document $document, array $path): string
    {
        $value = $this->readPath($document, array_values($path));

        return is_string($value) ? $value : '';
    }

    private function translateCursorIndex(string $originalText, string $currentText, int $index, string $move): int
    {
        $original = $this->splitCharacters($originalText);
        $current = $this->splitCharacters($currentText);
        $originalLength = count($original);
        $currentLength = count($current);
        $index = max(0, min($index, $originalLength));

        if ($original === $current) {
            return min($index, $currentLength);
        }

        $prefix = 0;
        while (
            $prefix < $originalLength
            && $prefix < $currentLength
            && $original[$prefix] === $current[$prefix]
        ) {
            ++$prefix;
        }

        $suffix = 0;
        while (
            $suffix < ($originalLength - $prefix)
            && $suffix < ($currentLength - $prefix)
            && $original[$originalLength - 1 - $suffix] === $current[$currentLength - 1 - $suffix]
        ) {
            ++$suffix;
        }

        $deleted = $originalLength - $prefix - $suffix;
        $inserted = $currentLength - $prefix - $suffix;
        if ($index < $prefix) {
            return $index;
        }

        if ($index > $prefix + $deleted || ($deleted === 0 && $index >= $prefix)) {
            return max(0, min($currentLength, $index + $inserted - $deleted));
        }

        if ($move === 'before') {
            return max(0, $prefix - 1);
        }

        return min($currentLength, $prefix + $inserted);
    }

    /**
     * @param list<string|int> $path
     */
    private function writePath(Document $document, array $path, mixed $value): Document
    {
        if ($path === []) {
            return $document;
        }

        if (count($path) === 1 && (is_string($path[0]) || is_int($path[0]))) {
            return $this->set($document, (string) $path[0], $value);
        }

        return $this->setNested($document, $path, $value);
    }

    /**
     * @param array<string|int,mixed> $document
     * @param list<string|int> $path
     */
    private function readArrayPath(array $document, array $path): mixed
    {
        $value = $document;
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param array<string|int,mixed> $document
     * @param list<string|int> $path
     * @return array<string|int,mixed>
     */
    private function writeArrayPath(array $document, array $path, mixed $value): array
    {
        if ($path === []) {
            return $document;
        }

        $key = array_shift($path);
        if ($path === []) {
            $document[$key] = $value;
            return $document;
        }

        $child = is_array($document[$key] ?? null) ? $document[$key] : [];
        $document[$key] = $this->writeArrayPath($child, $path, $value);

        return $document;
    }

    /**
     * @param array<string,mixed> $state
     * @return array{sentHeads:list<string>|null,lastSentHeads:list<string>|null,lastSentReadOnly:bool|null,receivedHeads:list<string>|null,sharedHeads:list<string>,requestedHeads:list<string>,needHeads:list<string>,inFlightHashes:list<string>,readOnly:bool,peerReadOnly:bool,theirCapabilities:list<string>|null}
     */
    private function normalizeSyncState(array $state): array
    {
        $sentHeads = $this->normalizeOptionalHeads($state['sentHeads'] ?? $state['lastSentHeads'] ?? null);
        $lastSentReadOnly = array_key_exists('lastSentReadOnly', $state)
            ? (is_bool($state['lastSentReadOnly']) ? $state['lastSentReadOnly'] : null)
            : ($sentHeads === null ? null : (bool) ($state['readOnly'] ?? false));
        $theirCapabilities = array_key_exists('theirCapabilities', $state)
            ? $this->normalizeSyncCapabilities($state['theirCapabilities'])
            : ['syncReset'];

        return [
            'sentHeads' => $sentHeads,
            'lastSentHeads' => $sentHeads,
            'lastSentReadOnly' => $lastSentReadOnly,
            'receivedHeads' => $this->normalizeOptionalHeads($state['receivedHeads'] ?? null),
            'sharedHeads' => $this->normalizeHeads($state['sharedHeads'] ?? []),
            'requestedHeads' => $this->normalizeHeads($state['requestedHeads'] ?? []),
            'needHeads' => $this->normalizeHeads($state['needHeads'] ?? []),
            'inFlightHashes' => $this->normalizeHeads($state['inFlightHashes'] ?? []),
            'readOnly' => (bool) ($state['readOnly'] ?? false),
            'peerReadOnly' => (bool) ($state['peerReadOnly'] ?? false),
            'theirCapabilities' => $theirCapabilities,
        ];
    }

    /**
     * @return list<string>|null
     */
    private function normalizeSyncCapabilities(mixed $capabilities): ?array
    {
        if ($capabilities === null) {
            return null;
        }

        if (! is_array($capabilities)) {
            throw new \InvalidArgumentException('Sync capabilities must be a string list or null.');
        }

        $normalized = [];
        foreach (array_values($capabilities) as $capability) {
            if (! is_string($capability) || $capability === '') {
                throw new \InvalidArgumentException('Sync capabilities must contain non-empty strings.');
            }

            $normalized[$capability] = $capability;
        }

        $normalized = array_values($normalized);
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string,mixed> $patchLog
     * @return array{active:bool,documentHeads:list<string>|null,patches:list<array<string,mixed>>}
     */
    private function normalizePatchLog(array $patchLog): array
    {
        $patches = [];
        foreach (is_array($patchLog['patches'] ?? null) ? $patchLog['patches'] : [] as $patch) {
            if (is_array($patch)) {
                $patches[] = $patch;
            }
        }

        return [
            'active' => (bool) ($patchLog['active'] ?? true),
            'documentHeads' => $this->normalizeOptionalHeads($patchLog['documentHeads'] ?? null),
            'patches' => $patches,
        ];
    }

    /**
     * @param array{active:bool,documentHeads:list<string>|null,patches:list<array<string,mixed>>} $patchLog
     */
    private function assertPatchLogMatchesDocument(Document $document, array $patchLog): void
    {
        if ($patchLog['documentHeads'] !== null && $patchLog['documentHeads'] !== $document->heads()) {
            throw new \RuntimeException('PatchLogMismatch: patch log belongs to another document.');
        }
    }

    /**
     * @return list<string>|null
     */
    private function normalizeOptionalHeads(mixed $heads): ?array
    {
        if ($heads === null) {
            return null;
        }

        return $this->normalizeHeads($heads);
    }

    /**
     * @return list<string>
     */
    private function normalizeHeads(mixed $heads): array
    {
        if (! is_array($heads)) {
            return [];
        }

        $normalized = [];
        foreach ($heads as $head) {
            if (is_string($head)) {
                $normalized[] = $head;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param mixed $have
     * @return list<string>
     */
    private function hashesFromSyncHave(mixed $have): array
    {
        if (! is_array($have)) {
            return [];
        }

        $hashes = [];
        foreach ($have as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($this->normalizeHeads($entry['hashes'] ?? []) as $hash) {
                $hashes[] = $hash;
            }
        }

        return array_values(array_unique($hashes));
    }

    /**
     * @param list<string> $heads
     * @return list<array<string,mixed>>
     */
    private function changesMatchingRequestedHeads(Document $document, array $heads): array
    {
        if ($heads === []) {
            return [];
        }

        $changesByHash = [];
        foreach ($document->getAllChanges() as $change) {
            if (is_string($change['hash'] ?? null)) {
                $changesByHash[$change['hash']] = $change;
            }
        }

        $changes = [];
        $added = [];
        $appendChangeWithDependencies = function (string $hash) use (&$appendChangeWithDependencies, &$changes, &$added, $changesByHash): void {
            if (isset($added[$hash]) || ! isset($changesByHash[$hash])) {
                return;
            }

            $change = $changesByHash[$hash];
            $changes[] = $change;
            $added[$hash] = true;
            foreach (is_array($change['deps'] ?? null) ? $change['deps'] : [] as $dep) {
                if (is_string($dep)) {
                    $appendChangeWithDependencies($dep);
                }
            }
        };

        foreach ($heads as $head) {
            $appendChangeWithDependencies($head);
        }

        return $changes;
    }

    /**
     * @param list<string> $heads
     * @return list<string>
     */
    private function missingHeads(Document $document, array $heads): array
    {
        return array_values(array_filter(
            $this->normalizeHeads($heads),
            static fn (string $head): bool => ! $document->hasHeads([$head])
        ));
    }

    /**
     * @param list<string> $heads
     * @return list<string>
     */
    private function minimalKnownHeads(Document $document, array $heads): array
    {
        $heads = $this->normalizeHeads($heads);
        if (count($heads) < 2) {
            return $heads;
        }

        $depsByHash = [];
        foreach ($document->getAllChanges() as $change) {
            if (is_string($change['hash'] ?? null)) {
                $depsByHash[$change['hash']] = $this->normalizeHeads($change['deps'] ?? []);
            }
        }

        $ancestorOfAnotherHead = [];
        foreach ($heads as $head) {
            $stack = $depsByHash[$head] ?? [];
            while ($stack !== []) {
                $hash = array_pop($stack);
                if (! is_string($hash) || isset($ancestorOfAnotherHead[$hash])) {
                    continue;
                }

                $ancestorOfAnotherHead[$hash] = true;
                foreach ($depsByHash[$hash] ?? [] as $dep) {
                    $stack[] = $dep;
                }
            }
        }

        $minimal = array_values(array_filter(
            $heads,
            static fn (string $head): bool => ! isset($ancestorOfAnotherHead[$head])
        ));
        sort($minimal, SORT_STRING);

        return $minimal;
    }

    /**
     * @param list<string> $heads
     * @return list<array<string,mixed>>
     */
    private function changesWithDependenciesSince(Document $document, array $heads): array
    {
        $selected = $document->getChangesSince($heads);
        if ($selected === [] || $heads === []) {
            return $selected;
        }

        $changesByHash = [];
        foreach ($document->getAllChanges() as $change) {
            if (is_string($change['hash'] ?? null)) {
                $changesByHash[$change['hash']] = $change;
            }
        }

        $needed = [];
        $stack = [];
        foreach ($selected as $change) {
            if (is_string($change['hash'] ?? null)) {
                $stack[] = $change['hash'];
            }
        }

        while ($stack !== []) {
            $hash = array_pop($stack);
            if (! is_string($hash) || isset($needed[$hash])) {
                continue;
            }

            $change = $changesByHash[$hash] ?? null;
            if (! is_array($change)) {
                continue;
            }

            $needed[$hash] = true;
            $deps = is_array($change['deps'] ?? null) ? $change['deps'] : [];
            foreach ($deps as $dep) {
                if (is_string($dep) && ! isset($needed[$dep])) {
                    $stack[] = $dep;
                }
            }
        }

        return array_values(array_filter(
            $document->getAllChanges(),
            static fn (array $change): bool => is_string($change['hash'] ?? null) && isset($needed[$change['hash']])
        ));
    }

    /**
     * @return list<string>
     */
    private function validateDiffHeads(Document $document, mixed $heads, string $label): array
    {
        if (! is_array($heads) || ! array_is_list($heads)) {
            throw new \InvalidArgumentException('invalid ' . $label . ' heads');
        }

        foreach ($heads as $head) {
            if (! is_string($head)) {
                throw new \InvalidArgumentException('invalid ' . $label . ' heads');
            }
        }

        if ($heads !== [] && ! $document->hasHeads($heads)) {
            throw new \InvalidArgumentException('invalid ' . $label . ' heads');
        }

        return array_values($heads);
    }

    /**
     * @param list<string> $beforeHeads
     * @param list<string> $afterHeads
     * @return array<string,bool>
     */
    private function replacementContainerPaths(Document $document, array $beforeHeads, array $afterHeads): array
    {
        $afterView = $document->view($afterHeads);
        if ($beforeHeads !== [] && ! $afterView->hasHeads($beforeHeads)) {
            return [];
        }

        $paths = [];
        foreach ($afterView->getChangesSince($beforeHeads) as $change) {
            foreach (is_array($change['ops'] ?? null) ? $change['ops'] : [] as $op) {
                if (
                    is_array($op)
                    && ($op['action'] ?? null) === 'setNested'
                    && is_array($op['path'] ?? null)
                    && $this->encodedValueIsContainer($op['value'] ?? null)
                ) {
                    $paths[$this->pathKey(array_values($op['path']))] = true;
                }
            }
        }

        return $paths;
    }

    private function encodedValueIsContainer(mixed $encoded): bool
    {
        return is_array($encoded) && ($encoded['type'] ?? null) === 'array' && is_array($encoded['value'] ?? null);
    }

    /**
     * @param list<string|int> $path
     */
    private function pathKey(array $path): string
    {
        return json_encode(array_values($path), JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string|int> $path
     * @return list<array<string,mixed>>
     */
    private function diffValues(mixed $before, mixed $after, array $path, array $replacementPaths = [], array $marksByPathKey = []): array
    {
        if ($before === $after) {
            return [];
        }

        if (! is_array($before) && is_array($after)) {
            $container = $path === [] ? [] : [['action' => 'put', 'path' => $path, 'value' => []]];

            return array_merge($container, $this->diffContainerContents($after, $path, $replacementPaths, $marksByPathKey));
        }

        if ($path === [] && $before === [] && is_array($after) && ! array_is_list($after)) {
            return $this->diffMapValues([], $after, $path, $replacementPaths, $marksByPathKey);
        }

        if (is_string($before) && is_string($after)) {
            $marks = $marksByPathKey[$this->pathKey($path)]['marks'] ?? [];
            if ($marks !== []) {
                return $this->markedStringDiffPatches($before, $after, $path, $marks);
            }
        }

        if (is_string($after)) {
            $marks = $marksByPathKey[$this->pathKey($path)]['marks'] ?? [];
            if ($marks !== []) {
                return $this->markedStringAssignmentPatches($path, $after, $marks);
            }

            $patches = [['action' => 'put', 'path' => $path, 'value' => '']];
            if ($after !== '') {
                $patches[] = ['action' => 'splice', 'path' => array_merge($path, [0]), 'value' => $after];
            }

            return $patches;
        }

        if (is_array($before) && is_array($after) && array_is_list($before) && array_is_list($after)) {
            return $this->diffListValues($before, $after, $path, $marksByPathKey);
        }

        if (is_array($before) && is_array($after) && ! array_is_list($before) && ! array_is_list($after)) {
            return $this->diffMapValues($before, $after, $path, $replacementPaths, $marksByPathKey);
        }

        return [['action' => 'put', 'path' => $path, 'value' => $after]];
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @param list<string|int> $path
     * @return list<array<string,mixed>>
     */
    private function diffMapValues(array $before, array $after, array $path, array $replacementPaths = [], array $marksByPathKey = []): array
    {
        $containerPuts = [];
        $existingPatches = [];
        $newContainerChildren = [];

        foreach ($after as $key => $value) {
            $childPath = array_merge($path, [$key]);
            if (is_array($value) && isset($replacementPaths[$this->pathKey($childPath)])) {
                $containerPuts[] = ['action' => 'put', 'path' => $childPath, 'value' => []];
                $newContainerChildren = array_merge($newContainerChildren, $this->diffContainerContents($value, $childPath, $replacementPaths, $marksByPathKey));
                continue;
            }

            if (! array_key_exists($key, $before)) {
                if (is_array($value)) {
                    $containerPuts[] = ['action' => 'put', 'path' => $childPath, 'value' => []];
                    $newContainerChildren = array_merge($newContainerChildren, $this->diffContainerContents($value, $childPath, $replacementPaths, $marksByPathKey));
                    continue;
                }

                $marks = $marksByPathKey[$this->pathKey($childPath)]['marks'] ?? [];
                if (is_string($value) && $marks !== []) {
                    $existingPatches = array_merge($existingPatches, $this->markedStringAssignmentPatches($childPath, $value, $marks));
                    continue;
                }

                if ($path !== [] && is_string($value)) {
                    $existingPatches[] = ['action' => 'put', 'path' => $childPath, 'value' => ''];
                    if ($value !== '') {
                        $existingPatches[] = ['action' => 'splice', 'path' => array_merge($childPath, [0]), 'value' => $value];
                    }
                    continue;
                }

                $existingPatches[] = ['action' => 'put', 'path' => $childPath, 'value' => $value];
                continue;
            }

            $existingPatches = array_merge($existingPatches, $this->diffValues($before[$key], $value, $childPath, $replacementPaths, $marksByPathKey));
        }

        foreach ($before as $key => $_value) {
            if (! array_key_exists($key, $after)) {
                $existingPatches[] = ['action' => 'del', 'path' => array_merge($path, [$key])];
            }
        }

        return array_values(array_merge($containerPuts, $existingPatches, $newContainerChildren));
    }

    /**
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<string|int> $path
     * @return list<array<string,mixed>>
     */
    private function diffListValues(array $before, array $after, array $path, array $marksByPathKey = []): array
    {
        $beforeLength = count($before);
        $afterLength = count($after);
        $prefix = 0;
        while ($prefix < $beforeLength && $prefix < $afterLength && $before[$prefix] === $after[$prefix]) {
            ++$prefix;
        }

        $suffix = 0;
        while (
            $suffix < ($beforeLength - $prefix)
            && $suffix < ($afterLength - $prefix)
            && $before[$beforeLength - 1 - $suffix] === $after[$afterLength - 1 - $suffix]
        ) {
            ++$suffix;
        }

        $deleted = $beforeLength - $prefix - $suffix;
        $inserted = array_slice($after, $prefix, $afterLength - $prefix - $suffix);
        if ($deleted > 0 && $inserted === []) {
            return [['action' => 'del', 'path' => array_merge($path, [$prefix]), 'length' => $deleted]];
        }

        if ($deleted === 0 && $inserted !== []) {
            $patches = [];
            foreach ($inserted as $offset => $value) {
                $patches = array_merge($patches, $this->insertListValuePatches($path, $prefix + $offset, $value));
            }

            return $patches;
        }

        return [['action' => 'put', 'path' => $path, 'value' => $after]];
    }

    /**
     * @param array<string|int,mixed> $value
     * @param list<string|int> $path
     * @return list<array<string,mixed>>
     */
    private function diffContainerContents(array $value, array $path, array $replacementPaths = [], array $marksByPathKey = []): array
    {
        if (array_is_list($value)) {
            $patches = [];
            foreach ($value as $index => $item) {
                $patches = array_merge($patches, $this->insertListValuePatches($path, $index, $item));
            }

            return $patches;
        }

        $containerPuts = [];
        $existingPatches = [];
        $newContainerChildren = [];
        foreach ($value as $key => $item) {
            $childPath = array_merge($path, [$key]);
            if (is_array($item)) {
                $containerPuts[] = ['action' => 'put', 'path' => $childPath, 'value' => []];
                $newContainerChildren = array_merge($newContainerChildren, $this->diffContainerContents($item, $childPath, $replacementPaths, $marksByPathKey));
                continue;
            }

            if (is_string($item)) {
                $marks = $marksByPathKey[$this->pathKey($childPath)]['marks'] ?? [];
                $existingPatches = array_merge(
                    $existingPatches,
                    $marks === []
                        ? $this->assignmentPatchesForStringPath($childPath, $item)
                        : $this->markedStringAssignmentPatches($childPath, $item, $marks)
                );
                continue;
            }

            $existingPatches[] = ['action' => 'put', 'path' => $childPath, 'value' => $item];
        }

        return array_values(array_merge($containerPuts, $existingPatches, $newContainerChildren));
    }

    /**
     * @param list<string|int> $path
     * @return list<array<string,mixed>>
     */
    private function insertListValuePatches(array $path, int $index, mixed $value): array
    {
        if (is_string($value)) {
            return [
                ['action' => 'insert', 'path' => array_merge($path, [$index]), 'values' => ['']],
                ['action' => 'splice', 'path' => array_merge($path, [$index, 0]), 'value' => $value],
            ];
        }

        return [['action' => 'insert', 'path' => array_merge($path, [$index]), 'values' => [$value]]];
    }

    /**
     * @param list<string|int> $path
     * @return list<array<string,mixed>>
     */
    private function assignmentPatchesForStringPath(array $path, string $value): array
    {
        $patches = [['action' => 'put', 'path' => $path, 'value' => '']];
        if ($value !== '') {
            $patches[] = ['action' => 'splice', 'path' => array_merge($path, [0]), 'value' => $value];
        }

        return $patches;
    }

    /**
     * @param list<array<string,mixed>> $patches
     * @return list<array<string,mixed>>
     */
    private function compactAdjacentListInsertPatches(array $patches): array
    {
        $compacted = [];
        $pending = null;

        $flushPending = static function () use (&$compacted, &$pending): void {
            if ($pending !== null) {
                $compacted[] = $pending;
                $pending = null;
            }
        };

        foreach ($patches as $patch) {
            if (! $this->isCompactableListInsertPatch($patch)) {
                $flushPending();
                $compacted[] = $patch;
                continue;
            }

            $path = $patch['path'];
            $values = array_values($patch['values']);
            $index = array_pop($path);

            if ($pending !== null && $this->isCompactableListInsertPatch($pending)) {
                $pendingPath = $pending['path'];
                $pendingIndex = (int) end($pendingPath);
                if (array_slice($pendingPath, 0, -1) === $path && $pendingIndex + count($pending['values']) === $index) {
                    $pending['values'] = array_merge($pending['values'], $values);
                    continue;
                }
            }

            $flushPending();
            $pending = $patch;
        }

        $flushPending();

        return $compacted;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private function isCompactableListInsertPatch(array $patch): bool
    {
        if (($patch['action'] ?? null) !== 'insert' || ! is_array($patch['path'] ?? null) || ! is_array($patch['values'] ?? null)) {
            return false;
        }

        $path = $patch['path'];
        if ($path === [] || ! is_int($path[array_key_last($path)])) {
            return false;
        }

        return array_is_list($patch['values']);
    }

    private function stringifyListValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<mixed> $values
     */
    private function assertListInputHasNoUndefined(string $key, array $values): void
    {
        foreach ($values as $index => $value) {
            if ($value instanceof UndefinedValue) {
                throw new \InvalidArgumentException('Cannot assign undefined value at /' . $key . ' at index ' . $index . ' in the input');
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function initialRootMap(mixed $value): array
    {
        if ($value instanceof Document) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }

        if (is_string($value)) {
            $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
            if ($characters === false) {
                throw new \InvalidArgumentException('Initial string value must be valid UTF-8.');
            }

            return $characters;
        }

        return [];
    }
}
