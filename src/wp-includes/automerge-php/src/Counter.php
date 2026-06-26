<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

use JsonSerializable;

final class Counter implements JsonSerializable
{
    /** @param array<string,int> $increments */
    public function __construct(
        private int $initialValue = 0,
        private ?string $counterId = null,
        private array $increments = []
    ) {
        $this->counterId ??= bin2hex(random_bytes(8));
    }

    public function value(): int
    {
        return $this->initialValue + array_sum($this->increments);
    }

    public function id(): string
    {
        return $this->counterId;
    }

    public function initialValue(): int
    {
        return $this->initialValue;
    }

    /** @return array<string,int> */
    public function increments(): array
    {
        return $this->increments;
    }

    public function incremented(int $amount = 1, ?string $operationId = null): self
    {
        $increments = $this->increments;
        $increments[$operationId ?? bin2hex(random_bytes(8))] = $amount;

        return new self($this->initialValue, $this->counterId, $increments);
    }

    public function merge(self $other): self
    {
        if ($this->counterId !== $other->counterId) {
            return strcmp($this->counterId, $other->counterId) >= 0 ? $this->copy() : $other->copy();
        }

        return new self(
            $this->initialValue,
            $this->counterId,
            $this->increments + $other->increments
        );
    }

    public function copy(): self
    {
        return new self($this->initialValue, $this->counterId, $this->increments);
    }

    public function jsonSerialize(): int
    {
        return $this->value();
    }
}
