<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

final class BackendView
{
    /** @param array<string,mixed> $materialized */
    public function __construct(private readonly array $materialized)
    {
    }

    /** @return array<string,mixed> */
    public function materialize(): array
    {
        return $this->materialized;
    }
}
