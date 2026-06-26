<?php

declare(strict_types=1);

namespace WordPress\DistributedEditing\Automerge;

use JsonSerializable;

final class BigIntValue implements JsonSerializable
{
    private string $decimal;

    public function __construct(string|int $decimal)
    {
        $value = (string) $decimal;
        if (! preg_match('/^-?(0|[1-9][0-9]*)$/', $value)) {
            throw new \InvalidArgumentException('BigInt values must be decimal integer strings.');
        }

        $negative = str_starts_with($value, '-');
        $digits = $negative ? substr($value, 1) : $value;
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }

        $this->decimal = $negative && $digits !== '0' ? '-' . $digits : $digits;
    }

    public function toString(): string
    {
        return $this->decimal;
    }

    public function jsonSerialize(): string
    {
        return $this->decimal;
    }
}
