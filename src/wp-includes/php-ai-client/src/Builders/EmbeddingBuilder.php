<?php

declare (strict_types=1);
namespace WordPress\AiClient\Builders;

use WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface;
use WordPress\AiClient\Builders\Traits\ModelResolutionTrait;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Events\AfterGenerateEmbeddingEvent;
use WordPress\AiClient\Events\BeforeGenerateEmbeddingEvent;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\ModelResolver;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
/**
 * Fluent builder for generating embeddings.
 *
 * Embeddings transform inputs into vector representations rather than generating a conversational
 * response. Each input is embedded independently, and the builder produces one embedding vector per
 * input. Model selection and configuration are shared with {@see PromptBuilder} via the
 * {@see ModelResolutionTrait}.
 *
 * @since 1.4.0
 *
 * @phpstan-import-type MessagePartArrayShape from MessagePart
 *
 * @phpstan-type EmbeddingInput string|MessagePart|File|MessagePartArrayShape
 */
class EmbeddingBuilder
{
    use ModelResolutionTrait;
    /**
     * @var list<MessagePart> The inputs to embed.
     */
    protected array $inputs = [];
    /**
     * @var EventDispatcherInterface|null The event dispatcher for embedding lifecycle events.
     */
    private ?EventDispatcherInterface $eventDispatcher;
    /**
     * Constructor.
     *
     * @since 1.4.0
     *
     * @param ProviderRegistry $registry The provider registry for finding suitable models.
     * @param EmbeddingInput|list<EmbeddingInput>|null $input Optional initial input(s) to embed.
     * @param EventDispatcherInterface|null $eventDispatcher Optional event dispatcher for lifecycle events.
     */
    public function __construct(ProviderRegistry $registry, $input = null, ?EventDispatcherInterface $eventDispatcher = null)
    {
        $this->modelConfig = new ModelConfig();
        $this->modelResolver = new ModelResolver($registry);
        $this->eventDispatcher = $eventDispatcher;
        if ($input === null) {
            return;
        }
        if (is_array($input) && array_is_list($input)) {
            /** @var list<EmbeddingInput> $input */
            $this->withInput(...$input);
            return;
        }
        /** @var EmbeddingInput $input */
        $this->withInput($input);
    }
    /**
     * Creates a deep clone of this builder.
     *
     * Clones the inputs and model configuration. Service objects (registry, event dispatcher) are
     * intentionally NOT cloned as they are shared dependencies.
     *
     * @since 1.4.0
     */
    public function __clone()
    {
        $clonedInputs = [];
        foreach ($this->inputs as $input) {
            $clonedInputs[] = clone $input;
        }
        $this->inputs = $clonedInputs;
        $this->modelConfig = clone $this->modelConfig;
        $this->modelResolver = clone $this->modelResolver;
        // Note: $eventDispatcher is a service object and is intentionally NOT
        // cloned - it should be a shared reference.
    }
    /**
     * Adds one or more inputs to embed.
     *
     * @since 1.4.0
     *
     * @param EmbeddingInput ...$input The inputs to embed, each treated as an independent input.
     * @return self
     * @throws InvalidArgumentException If no inputs are provided or an input is invalid.
     */
    public function withInput(...$input): self
    {
        if ($input === []) {
            throw new InvalidArgumentException('At least one input must be provided.');
        }
        foreach ($input as $singleInput) {
            $this->inputs[] = $this->parseInput($singleInput);
        }
        return $this;
    }
    /**
     * Sets the embedding dimensions.
     *
     * @since 1.4.0
     *
     * @param int $dimensions The embedding dimensions.
     * @return self
     */
    public function usingDimensions(int $dimensions): self
    {
        $this->modelConfig->setDimensions($dimensions);
        return $this;
    }
    /**
     * Checks whether the current inputs and configuration are supported by an available model.
     *
     * @since 1.4.0
     *
     * @return bool True if a suitable embedding model is available.
     */
    public function isSupported(): bool
    {
        $requirements = ModelRequirements::fromEmbeddingData($this->inputs, $this->modelConfig);
        return $this->modelResolver->isSupported($requirements);
    }
    /**
     * Generates an embedding result from the configured inputs.
     *
     * @since 1.4.0
     *
     * @return EmbeddingResult The generated embedding result.
     * @throws InvalidArgumentException If no inputs are configured or model validation fails.
     * @throws RuntimeException If the model doesn't support embedding generation, or returns an
     *                          embedding count that does not match the number of inputs.
     */
    public function generateEmbeddingResult(): EmbeddingResult
    {
        if ($this->inputs === []) {
            throw new InvalidArgumentException('Cannot generate embeddings from empty input. Add content using withInput().');
        }
        $capability = CapabilityEnum::embeddingGeneration();
        $requirements = ModelRequirements::fromEmbeddingData($this->inputs, $this->modelConfig);
        $model = $this->modelResolver->resolve($requirements, $this->modelConfig);
        if (!$model instanceof EmbeddingGenerationModelInterface) {
            throw new RuntimeException(sprintf('Model "%s" does not support embedding generation.', $model->metadata()->getId()));
        }
        $this->dispatchEvent(new BeforeGenerateEmbeddingEvent($this->inputs, $model, $capability));
        $result = $model->generateEmbeddingResult($this->inputs);
        // Embeddings map positionally to inputs, so the counts must match.
        if (count($result->getEmbeddings()) !== count($this->inputs)) {
            throw new RuntimeException(sprintf('Expected %d embedding(s) from the model, but received %d.', count($this->inputs), count($result->getEmbeddings())));
        }
        $this->dispatchEvent(new AfterGenerateEmbeddingEvent($this->inputs, $model, $capability, $result));
        return $result;
    }
    /**
     * Generates a single embedding from the configured input.
     *
     * @since 1.4.0
     *
     * @return Embedding The generated embedding vector.
     * @throws InvalidArgumentException If no inputs are configured, model validation fails, or
     *                                  multiple inputs were provided.
     */
    public function generateEmbedding(): Embedding
    {
        if (count($this->inputs) > 1) {
            throw new InvalidArgumentException('generateEmbedding() returns a single vector; use generateEmbeddings() for multiple inputs.');
        }
        return $this->generateEmbeddingResult()->getEmbedding();
    }
    /**
     * Generates embeddings from the configured inputs.
     *
     * @since 1.4.0
     *
     * @return list<Embedding> The generated embedding vectors.
     * @throws InvalidArgumentException If no inputs are configured or model validation fails.
     * @throws RuntimeException If the model doesn't support embedding generation, or returns an
     *                          embedding count that does not match the number of inputs.
     */
    public function generateEmbeddings(): array
    {
        return $this->generateEmbeddingResult()->getEmbeddings();
    }
    /**
     * Parses a single input into a message part.
     *
     * @since 1.4.0
     *
     * @param mixed $input The input to parse. Accepts a string, MessagePart, File, or
     *                     MessagePartArrayShape.
     * @return MessagePart The parsed message part.
     * @throws InvalidArgumentException If the input type is not supported or is an invalid part type.
     */
    private function parseInput($input): MessagePart
    {
        if ($input instanceof MessagePart) {
            return $this->validatePart($input);
        }
        if (is_string($input)) {
            if (trim($input) === '') {
                throw new InvalidArgumentException('Cannot create an embedding input from an empty string.');
            }
            return new MessagePart($input);
        }
        if ($input instanceof File) {
            return new MessagePart($input);
        }
        if (is_array($input) && MessagePart::isArrayShape($input)) {
            return $this->validatePart(MessagePart::fromArray($input));
        }
        throw new InvalidArgumentException('Embedding input must be a string, MessagePart, File, or MessagePartArrayShape.');
    }
    /**
     * Validates that a message part is a valid embedding input.
     *
     * @since 1.4.0
     *
     * @param MessagePart $part The part to validate.
     * @return MessagePart The validated part.
     * @throws InvalidArgumentException If the part is not a text or file part.
     */
    private function validatePart(MessagePart $part): MessagePart
    {
        $type = $part->getType();
        if (!$type->isText() && !$type->isFile()) {
            throw new InvalidArgumentException('Embedding inputs must be text or file parts.');
        }
        return $part;
    }
    /**
     * Dispatches an event if an event dispatcher is registered.
     *
     * @since 1.4.0
     *
     * @param object $event The event to dispatch.
     * @return void
     */
    private function dispatchEvent(object $event): void
    {
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
