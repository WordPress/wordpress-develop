<?php

namespace Jcupitt\Vips;

class SourceCustom extends \Jcupitt\Vips\Source
{
    /**
     * A pointer to the underlying VipsSourceCustom. This is the same as the
     * GObject, just cast to VipsSourceCustom to help FFI.
     *
     * @internal
     */
    public \FFI\CData $pointer;
    public function __construct()
    {
        $this->pointer = \Jcupitt\Vips\FFI::vips()->vips_source_custom_new();
        parent::__construct($this->pointer);
    }
    /**
     * Attach a read handler.
     *
     * The interface is similar to fread. The handler is given a number
     * of bytes to fetch, and should return a bytes-like object containing up
     * to that number of bytes. If there is no more data available, it should
     * return null.
     */
    public function onRead(callable $callback): void
    {
        $this->signalConnect('read', $callback);
    }
    /**
     * Attach a seek handler.
     *
     * The interface is the same as fseek, so the handler is passed
     * parameters for $offset and $whence with the same meanings.
     * However, the handler MUST return the new seek position. A simple way
     * to do this is to call ftell() and return that result.
     * Seek handlers are optional. If you do not set one, your source will be
     * treated as unseekable and libvips will do extra caching.
     *
     * $whence in particular:
     *  - 0 => start
     *  - 1 => current position
     *  - 2 => end
     */
    public function onSeek(callable $callback): void
    {
        $this->signalConnect('seek', $callback);
    }
}
