<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

final class IsolatedDocument
{
    private Document $hiddenDocument;

    private Document $visibleDocument;

    /** @var list<string> */
    private array $heads;

    /** @var list<string>|null */
    private ?array $diffCursor;

    /**
     * @param list<string> $heads
     * @param list<string>|null $diffCursor
     */
    public function __construct(Document $hiddenDocument, Document $visibleDocument, array $heads, ?array $diffCursor = null)
    {
        $this->hiddenDocument = $hiddenDocument->clone();
        $this->visibleDocument = $visibleDocument->clone();
        $this->heads = $this->normalizeHeads($heads);
        $this->diffCursor = $diffCursor === null ? null : $this->normalizeHeads($diffCursor);
    }

    public function hiddenDocument(): Document
    {
        return $this->hiddenDocument->clone();
    }

    public function visibleDocument(): Document
    {
        return $this->visibleDocument->clone();
    }

    /**
     * @return list<string>
     */
    public function heads(): array
    {
        return $this->heads;
    }

    public function withHiddenDocument(Document $hiddenDocument): self
    {
        return new self($hiddenDocument, $this->visibleDocument, $this->heads, $this->diffCursor);
    }

    public function withVisibleDocument(Document $visibleDocument): self
    {
        return new self($this->hiddenDocument, $visibleDocument, $this->heads, $this->diffCursor);
    }

    /**
     * @return list<string>|null
     */
    public function diffCursor(): ?array
    {
        return $this->diffCursor;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->visibleDocument->toArray();
    }

    /**
     * @param list<string> $heads
     * @return list<string>
     */
    private function normalizeHeads(array $heads): array
    {
        foreach ($heads as $head) {
            if (! is_string($head)) {
                throw new \InvalidArgumentException('Isolated document heads must be strings.');
            }
        }

        return array_values($heads);
    }
}
