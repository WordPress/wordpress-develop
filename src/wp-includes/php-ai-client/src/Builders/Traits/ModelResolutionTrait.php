<?php

declare (strict_types=1);
namespace WordPress\AiClient\Builders\Traits;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\ModelResolver;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
/**
 * Provides shared model selection and configuration methods for builders.
 *
 * Builders that generate results from a model (e.g. {@see \WordPress\AiClient\Builders\PromptBuilder}
 * and {@see \WordPress\AiClient\Builders\EmbeddingBuilder}) use this trait to expose a consistent
 * fluent API for choosing a model, provider, preferences, request options, and model configuration.
 * Model selection state and logic live on the {@see ModelResolver} owned by the builder.
 *
 * @since 1.4.0
 */
trait ModelResolutionTrait
{
    /**
     * @var ModelConfig The model configuration.
     */
    protected ModelConfig $modelConfig;
    /**
     * @var ModelResolver The resolver that owns model selection state and logic.
     */
    protected ModelResolver $modelResolver;
    /**
     * Sets the model to use for generation.
     *
     * The model's configuration will be merged with the builder's configuration,
     * with the builder's configuration taking precedence for any overlapping settings.
     *
     * @since 0.1.0
     *
     * @param ModelInterface $model The model to use.
     * @return self
     */
    public function usingModel(ModelInterface $model): self
    {
        $this->modelResolver->setModel($model);
        // Merge model's config with builder's config, with builder's config taking precedence
        $modelConfigArray = $model->getConfig()->toArray();
        $builderConfigArray = $this->modelConfig->toArray();
        $mergedConfigArray = array_merge($modelConfigArray, $builderConfigArray);
        $this->modelConfig = ModelConfig::fromArray($mergedConfigArray);
        return $this;
    }
    /**
     * Sets preferred models to evaluate in order.
     *
     * @since 0.2.0
     *
     * @param string|ModelInterface|array{0:string,1:string} ...$preferredModels The preferred models as model IDs,
     * model instances, or [provider ID, model ID] tuples. For broader compatibility, it is recommended you specify
     * only model IDs or model instances, as that will allow for different providers that expose the same model to be
     * considered.
     * @return self
     *
     * @throws InvalidArgumentException When a preferred model has an invalid type or identifier.
     */
    public function usingModelPreference(...$preferredModels): self
    {
        $this->modelResolver->setModelPreferences(...$preferredModels);
        return $this;
    }
    /**
     * Sets the model configuration.
     *
     * Merges the provided configuration with the builder's configuration,
     * with builder configuration taking precedence.
     *
     * @since 0.1.0
     *
     * @param ModelConfig $config The model configuration to merge.
     * @return self
     */
    public function usingModelConfig(ModelConfig $config): self
    {
        // Convert both configs to arrays
        $builderConfigArray = $this->modelConfig->toArray();
        $providedConfigArray = $config->toArray();
        // Merge arrays with builder config taking precedence
        $mergedArray = array_merge($providedConfigArray, $builderConfigArray);
        // Create new config from merged array
        $this->modelConfig = ModelConfig::fromArray($mergedArray);
        return $this;
    }
    /**
     * Sets the provider to use for generation.
     *
     * @since 0.1.0
     *
     * @param string $providerIdOrClassName The provider ID or class name.
     * @return self
     */
    public function usingProvider(string $providerIdOrClassName): self
    {
        $this->modelResolver->setProvider($providerIdOrClassName);
        return $this;
    }
    /**
     * Sets the request options for HTTP transport.
     *
     * @since 0.3.0
     *
     * @param RequestOptions $requestOptions The request options.
     * @return self
     */
    public function usingRequestOptions(RequestOptions $requestOptions): self
    {
        $this->modelResolver->setRequestOptions($requestOptions);
        return $this;
    }
}
