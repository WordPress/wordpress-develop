<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

final class Transaction
{
    private Document $base;

    private Document $working;

    private NativePort $port;

    private bool $closed = false;

    /** @var list<string>|null */
    private ?array $historicalHeads;

    private ?Document $readView;

    /**
     * @param list<string>|null $historicalHeads
     */
    public function __construct(Document $document, NativePort $port, ?array $historicalHeads = null)
    {
        $this->base = $document;
        $this->working = $document->clone();
        $this->port = $port;
        $this->historicalHeads = $historicalHeads === null ? null : array_values($historicalHeads);
        $this->readView = $historicalHeads === null ? null : $port->view($document, $this->historicalHeads);
    }

    public function batchCreateObject(string $key, mixed $value): void
    {
        $this->assertOpen();
        $this->working = $this->port->batchCreateObject($this->working, $key, $value);
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertOpen();
        $this->working = $this->historicalHeads === null
            ? $this->port->set($this->working, $key, $value)
            : $this->port->setAtHeads($this->working, $this->historicalHeads, $key, $value);
        $this->readView = null;
    }

    /**
     * @param list<string|int> $path
     */
    public function setNested(array $path, mixed $value): void
    {
        $this->assertOpen();
        $this->working = $this->port->setNested($this->working, $path, $value);
    }

    /**
     * @param list<mixed> $values
     */
    public function insertListElements(string $key, int $index, array $values): void
    {
        $this->assertOpen();
        $this->working = $this->port->insertListElements($this->working, $key, $index, $values);
    }

    public function splice(string $key, int $index, int $deleteCount, string $insert = ''): void
    {
        $this->assertOpen();
        $this->working = $this->port->splice($this->working, $key, $index, $deleteCount, $insert);
        $this->readView = null;
    }

    public function document(): Document
    {
        return $this->readView ?? $this->working;
    }

    /**
     * @return list<string>
     */
    public function getHeads(): array
    {
        return $this->base->heads();
    }

    public function commit(): Document
    {
        $this->assertOpen();
        $this->closed = true;

        return $this->working;
    }

    /**
     * @return array{0:Document,1:?string}
     */
    public function commitWithHash(): array
    {
        $this->assertOpen();
        $this->closed = true;

        $baseChangeCount = count($this->base->getAllChanges());
        $newChanges = array_slice($this->working->getAllChanges(), $baseChangeCount);
        $lastChange = $newChanges === [] ? null : $newChanges[array_key_last($newChanges)];
        $hash = is_array($lastChange) && is_string($lastChange['hash'] ?? null) ? $lastChange['hash'] : null;

        return [$this->working, $hash];
    }

    /**
     * @return array{0:Document,1:list<array<string,mixed>>}
     */
    public function commitWithPatches(): array
    {
        $this->assertOpen();
        $this->closed = true;

        return [
            $this->working,
            $this->port->diff($this->working, $this->base->heads(), $this->working->heads()),
        ];
    }

    public function pendingOps(): int
    {
        $this->assertOpen();

        return $this->countPendingOps();
    }

    public function commitWith(?string $message = null, ?int $time = null): Document
    {
        $this->assertOpen();
        $this->closed = true;
        $this->working->amendLastLocalChange($message, $time);

        return $this->working;
    }

    public function rollback(): Document
    {
        $this->assertOpen();
        $this->closed = true;

        return $this->base;
    }

    /**
     * @return array{0:Document,1:int}
     */
    public function rollbackWithCancelled(): array
    {
        $this->assertOpen();
        $cancelled = $this->countPendingOps();
        $this->closed = true;

        return [$this->base, $cancelled];
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Transaction is already closed.');
        }
    }

    private function countPendingOps(): int
    {
        $baseChangeCount = count($this->base->getAllChanges());
        $pending = 0;
        foreach (array_slice($this->working->getAllChanges(), $baseChangeCount) as $change) {
            $pending += count(is_array($change['ops'] ?? null) ? $change['ops'] : []);
        }

        return $pending;
    }
}
