<?php

declare (strict_types=1);
namespace WordPress\AiClient\Providers\Http\Traits;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
/**
 * Trait for a class that implements WithRequestAuthenticationInterface.
 *
 * @since 0.1.0
 */
trait WithRequestAuthenticationTrait
{
    /**
     * @var RequestAuthenticationInterface|null The request authentication instance.
     */
    private ?RequestAuthenticationInterface $requestAuthentication = null;
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function setRequestAuthentication(RequestAuthenticationInterface $requestAuthentication): void
    {
        $this->requestAuthentication = $requestAuthentication;
    }
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function getRequestAuthentication(): RequestAuthenticationInterface
    {
        if ($this->requestAuthentication === null) {
            throw new RuntimeException('Image generation isn’t available because it hasn’t been set up yet. Please add an API key for an image-generation provider in Connectors, then try again.');
        }
        return $this->requestAuthentication;
    }
}
