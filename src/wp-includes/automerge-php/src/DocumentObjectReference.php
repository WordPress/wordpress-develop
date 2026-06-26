<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

final class DocumentObjectReference
{
    /** @var list<string|int> */
    private array $path;

    /**
     * @param list<string|int> $path
     */
    public function __construct(array $path)
    {
        $this->path = array_values($path);
    }

    /**
     * @return list<string|int>
     */
    public function path(): array
    {
        return $this->path;
    }
}
