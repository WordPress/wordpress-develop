<?php

namespace WordPress\AiClientDependencies\Http\Discovery;

use WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException as RealNotFoundException;
/**
 * Thrown when a discovery does not find any matches.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 *
 * @deprecated since since version 1.0, and will be removed in 2.0. Use {@link \Http\Discovery\Exception\NotFoundException} instead.
 */
final class NotFoundException extends RealNotFoundException
{
}
