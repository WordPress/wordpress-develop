<?php

namespace WordPress\AiClientDependencies\Http\Client\Promise;

use WordPress\AiClientDependencies\Http\Client\Exception;
use WordPress\AiClientDependencies\Http\Promise\Promise;
final class HttpRejectedPromise implements Promise
{
    /**
     * @var Exception
     */
    private $exception;
    public function __construct(Exception $exception)
    {
        $this->exception = $exception;
    }
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
    {
        if (null === $onRejected) {
            return $this;
        }
        try {
            $result = $onRejected($this->exception);
            if ($result instanceof Promise) {
                return $result;
            }
            return new HttpFulfilledPromise($result);
        } catch (Exception $e) {
            return new self($e);
        }
    }
    public function getState()
    {
        return Promise::REJECTED;
    }
    public function wait($unwrap = \true)
    {
        if ($unwrap) {
            throw $this->exception;
        }
    }
}
