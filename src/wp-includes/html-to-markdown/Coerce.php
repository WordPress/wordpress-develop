<?php

declare(strict_types=1);

namespace League\HTMLToMarkdown;

/**
 * @internal
 */
final class Coerce
{
    private function __construct()
    {
    }

    /**
     * @param mixed $val
     */
    public static function toString($val): string
    {
        switch (true) {
            case \is_string($val):
                return $val;
            case \is_bool($val):
            case \is_float($val):
            case \is_int($val):
            case $val === null:
                return \strval($val);
            case \is_object($val) && \method_exists($val, '__toString'):
                return $val->__toString();
            default:
                throw new \InvalidArgumentException('Cannot coerce this value to string');
        }
    }
}
