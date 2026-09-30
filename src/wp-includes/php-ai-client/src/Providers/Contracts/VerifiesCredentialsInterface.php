<?php

declare (strict_types=1);
namespace WordPress\AiClient\Providers\Contracts;

/**
 * Interface for provider availability checks that can verify the configured credentials.
 *
 * Unlike ProviderAvailabilityInterface::isConfigured(), which reports any failure as the provider not being
 * configured, this tells credentials that the provider rejected apart from failures that do not reflect on them,
 * such as network errors, server errors, or rate limiting.
 *
 * @since n.e.x.t
 */
interface VerifiesCredentialsInterface
{
    /**
     * Verifies the configured credentials by sending a request to the provider.
     *
     * @since n.e.x.t
     *
     * @return bool True if the provider accepted the credentials, false if it rejected them.
     * @throws \Exception If the credentials could not be verified, for example because the provider could not be
     *                    reached, responded with a server error, or rate limited the request.
     */
    public function verifyCredentials(): bool;
}
