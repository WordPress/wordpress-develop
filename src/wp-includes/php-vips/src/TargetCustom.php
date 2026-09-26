<?php

namespace Jcupitt\Vips;

class TargetCustom extends \Jcupitt\Vips\Target
{
    /**
     * A pointer to the underlying VipsTargetCustom. This is the same as the
     * GObject, just cast to VipsTargetCustom to help FFI.
     *
     * @internal
     */
    public \FFI\CData $pointer;
    public function __construct()
    {
        $this->pointer = \Jcupitt\Vips\FFI::vips()->vips_target_custom_new();
        parent::__construct($this->pointer);
    }
    /**
     * Attach a write handler.
     *
     * The interface is similar to fwrite(). The handler is given a
     * bytes-like object to write, and should return the number of bytes
     * written.
     *
     * Unlike fwrite, you should return -1 on error.
     *
     * @throws Exception
     */
    public function onWrite(callable $callback): void
    {
        $this->signalConnect('write', $callback);
    }
    /**
     * Attach a read handler.
     *
     * The interface is similar to fread(). The handler is given a number
     * of bytes to fetch, and should return a bytes-like object containing up
     * to that number of bytes. If there is no more data available, it should
     * return null.
     *
     * Read handlers on VipsTarget are optional. If you do not set one, your
     * target will be treated as unreadable and libvips will be unable to
     * write some file types (just TIFF, as of the time of writing).
     */
    public function onRead(callable $callback): void
    {
        if (\Jcupitt\Vips\FFI::atLeast(8, 13)) {
            $this->signalConnect('read', $callback);
        }
    }
    /**
     * Attach a seek handler.
     *
     * The interface is similar to fseek, so the handler is passed
     * parameters for $offset and $whence with the same meanings.
     * However, the handler MUST return the new seek position. A simple way
     * to do this is to call ftell() and return that result.
     *
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
        if (\Jcupitt\Vips\FFI::atLeast(8, 13)) {
            $this->signalConnect('seek', $callback);
        }
    }
    /**
     * Attach an end handler.
     *
     * This optional handler is called at the end of write. It should do any
     * cleaning up necessary, and return 0 on success and -1 on error.
     *
     * Automatically falls back to onFinish if libvips <8.13.
     *
     * @throws Exception
     */
    public function onEnd(callable $callback): void
    {
        if (\Jcupitt\Vips\FFI::atLeast(8, 13)) {
            $this->signalConnect('end', $callback);
        } else {
            $this->onFinish($callback);
        }
    }
    /**
     * Attach a finish handler.
     *
     * For libvips 8.13 and later, this method is deprecated in favour of
     * onEnd().
     *
     * @see TargetCustom::onEnd()
     * @throws Exception
     */
    public function onFinish(callable $callback): void
    {
        $this->signalConnect('finish', $callback);
    }
}
