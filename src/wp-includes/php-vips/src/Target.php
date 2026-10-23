<?php

namespace Jcupitt\Vips;

class Target extends \Jcupitt\Vips\Connection
{
    /**
     * A pointer to the underlying VipsTarget. This is the same as the
     * GObject, just cast to VipsTarget to help FFI.
     *
     * @internal
     */
    public \FFI\CData $pointer;
    public function __construct(\FFI\CData $pointer)
    {
        $this->pointer = \Jcupitt\Vips\FFI::vips()->cast(\Jcupitt\Vips\FFI::ctypes('VipsTarget'), $pointer);
        parent::__construct($pointer);
    }
    /**
     * Make a new target to write to a file descriptor. For example:
     *
     * ```php
     * $target = Target::newToDescriptor(1);
     * ```
     *
     * Makes a descriptor attached to stdout.
     * You can pass this target to (for example) @see Image::writeToTarget()
     * @throws Exception
     */
    public static function newToDescriptor(int $descriptor): self
    {
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_target_new_to_descriptor($descriptor);
        if ($pointer === null) {
            throw new \Jcupitt\Vips\Exception("can't create output target from descriptor {$descriptor}");
        }
        return new self($pointer);
    }
    /**
     * Make a new target to write to a filename. For example:
     *
     * ```php
     * $target = Target::newToFile("myfile.jpg");
     * ```
     *
     * You can pass this target to (for example) @see Image::writeToTarget()
     * @throws Exception
     */
    public static function newToFile(string $filename): self
    {
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_target_new_to_file($filename);
        if ($pointer === null) {
            throw new \Jcupitt\Vips\Exception("can't create output target from filename {$filename}");
        }
        return new self($pointer);
    }
    /**
     * Make a new target to write to a memory buffer. For example:
     *
     * ```php
     * $target = Target::newToMemory();
     * ```
     *
     * You can pass this target to (for example) @see Image::writeToTarget()
     * @throws Exception
     */
    public static function newToMemory(): self
    {
        $pointer = \Jcupitt\Vips\FFI::vips()->vips_target_new_to_memory();
        if ($pointer === null) {
            throw new \Jcupitt\Vips\Exception("can't create output target from memory");
        }
        return new self($pointer);
    }
}
