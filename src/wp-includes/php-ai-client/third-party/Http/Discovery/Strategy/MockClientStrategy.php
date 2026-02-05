<?php

namespace WordPress\AiClientDependencies\Http\Discovery\Strategy;

use WordPress\AiClientDependencies\Http\Client\HttpAsyncClient;
use WordPress\AiClientDependencies\Http\Client\HttpClient;
use WordPress\AiClientDependencies\Http\Mock\Client as Mock;
/**
 * Find the Mock client.
 *
 * @author Sam Rapaport <me@samrapdev.com>
 */
final class MockClientStrategy implements DiscoveryStrategy
{
    public static function getCandidates($type)
    {
        if (is_a(HttpClient::class, $type, \true) || is_a(HttpAsyncClient::class, $type, \true)) {
            return [['class' => Mock::class, 'condition' => Mock::class]];
        }
        return [];
    }
}
