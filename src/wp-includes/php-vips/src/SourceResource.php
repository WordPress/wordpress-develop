<?php

namespace Jcupitt\Vips;

class SourceResource extends \Jcupitt\Vips\SourceCustom
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
        $this->onRead(static function (int $length) use (&$resource): ?string {
            return fread($resource, $length) ?: null;
        });
        if (stream_get_meta_data($resource)['seekable']) {
            $this->onSeek(static function (int $offset, int $whence) use (&$resource): int {
                fseek($resource, $offset, $whence);
                return ftell($resource);
            });
        }
    }
    public function __destruct()
    {
        fclose($this->resource);
        parent::__destruct();
    }
}
