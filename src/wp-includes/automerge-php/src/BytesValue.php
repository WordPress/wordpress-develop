<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

use JsonSerializable;

final class BytesValue implements JsonSerializable
{
    /** @var list<int> */
    private array $bytes;

    /** @param list<int> $bytes */
    public function __construct(array $bytes)
    {
        $normalized = [];
        foreach ($bytes as $byte) {
            if (! is_int($byte) || $byte < 0 || $byte > 255) {
                throw new \InvalidArgumentException('BytesValue entries must be integers from 0 through 255.');
            }

            $normalized[] = $byte;
        }

        $this->bytes = $normalized;
    }

    /** @return list<int> */
    public function bytes(): array
    {
        return $this->bytes;
    }

    public function copy(): self
    {
        return new self($this->bytes);
    }

    /** @return list<int> */
    public function jsonSerialize(): array
    {
        return $this->bytes;
    }
}
