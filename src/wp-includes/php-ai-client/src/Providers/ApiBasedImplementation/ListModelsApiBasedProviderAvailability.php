<?php

declare (strict_types=1);
namespace WordPress\AiClient\Providers\ApiBasedImplementation;

use Exception;
use WordPress\AiClient\Common\Contracts\CachesDataInterface;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Contracts\VerifiesCredentialsInterface;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
/**
 * Class to check availability for an API-based provider via a test request to the endpoint to list models.
 *
 * This class should be used for cloud-based providers that offer a model listing endpoint which requires
 * authentication. A request to this endpoint is used to determine if the provider is properly configured
 * with valid credentials.
 *
 * @since 0.1.0
 */
class ListModelsApiBasedProviderAvailability implements ProviderAvailabilityInterface, VerifiesCredentialsInterface
{
    /**
     * @var ModelMetadataDirectoryInterface The model metadata directory to use for checking availability.
     */
    private ModelMetadataDirectoryInterface $modelMetadataDirectory;
    /**
     * Constructor.
     *
     * @since 0.1.0
     *
     * @param ModelMetadataDirectoryInterface $modelMetadataDirectory The model metadata directory to use for checking
     *                                                                availability.
     */
    public function __construct(ModelMetadataDirectoryInterface $modelMetadataDirectory)
    {
        $this->modelMetadataDirectory = $modelMetadataDirectory;
    }
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function isConfigured(): bool
    {
        try {
            // Attempt to list models to check if the provider is available.
            $this->modelMetadataDirectory->listModelMetadata();
            return \true;
        } catch (Exception $e) {
            // If an exception occurs, the provider is not available.
            return \false;
        }
    }
    /**
     * {@inheritDoc}
     *
     * The cached model list is invalidated first, as it may have been fetched with other credentials.
     *
     * @since n.e.x.t
     */
    public function verifyCredentials(): bool
    {
        if ($this->modelMetadataDirectory instanceof CachesDataInterface) {
            $this->modelMetadataDirectory->invalidateCaches();
        }
        try {
            $this->modelMetadataDirectory->listModelMetadata();
        } catch (ClientException $e) {
            // A request timeout or rate limit says nothing about the credentials.
            if (in_array($e->getCode(), [408, 429], \true)) {
                throw $e;
            }
            return \false;
        }
        return \true;
    }
}
