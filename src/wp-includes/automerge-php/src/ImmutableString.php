<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

use JsonSerializable;

class ImmutableString implements JsonSerializable
{
    public bool $isImmutableString = true;

    public function __construct(private readonly string $value)
    {
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
