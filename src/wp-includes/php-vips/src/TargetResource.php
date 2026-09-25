<?php

namespace Jcupitt\Vips;

class TargetResource extends \Jcupitt\Vips\TargetCustom
{
    /**
     * @var resource
     */
    private $resource;
    /**
     * The resource passed in will become "owned" by this class.
     * On destruction of this class, the resource will be closed.
     *
     * @param resource $resource
     */
    public function __construct($resource)
    {
        $this->resource = $resource;
        parent::__construct();
        $this->onWrite(static function (string $buffer) use (&$resource): int {
            return fwrite($resource, $buffer) ?: 0;
        });
        $this->onEnd(static function () use (&$resource): void {
            fclose($resource);
        });
        $meta = stream_get_meta_data($resource);
        // See: https://www.php.net/manual/en/function.fopen.php
        if (substr($meta['mode'], -1) === '+') {
            $this->onRead(static function (int $length) use (&$resource): ?string {
                return fread($resource, $length) ?: null;
            });
        }
        if ($meta['seekable']) {
            $this->onSeek(static function (int $offset, int $whence) use (&$resource): int {
                fseek($resource, $offset, $whence);
                return ftell($resource);
            });
        }
    }
    public function __destruct()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
        parent::__destruct();
    }
}
