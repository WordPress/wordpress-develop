<?php

namespace Jcupitt\Vips;

abstract class Connection extends \Jcupitt\Vips\VipsObject
{
    /**
     * A pointer to the underlying Connection. This is the same as the
     * GObject, just cast to Connection to help FFI.
     *
     * @internal
     */
    public \FFI\CData $pointer;
    public function __construct(\FFI\CData $pointer)
    {
        $this->pointer = \Jcupitt\Vips\FFI::vips()->cast(\Jcupitt\Vips\FFI::ctypes('VipsConnection'), $pointer);
        parent::__construct($pointer);
    }
    /**
     * Get the filename associated with a connection. Return null if there is no associated file.
     */
    public function filename(): ?string
    {
        return \Jcupitt\Vips\FFI::vips()->vips_connection_filename($this->pointer);
    }
    /**
     * Make a human-readable name for a connection suitable for error messages.
     */
    public function nick(): ?string
    {
        return \Jcupitt\Vips\FFI::vips()->vips_connection_nick($this->pointer);
    }
}
