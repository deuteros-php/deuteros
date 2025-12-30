<?php

declare(strict_types=1);

namespace Deuteros\Common;

/**
 * Builds callable resolvers for entity double methods.
 *
 * Produces framework-agnostic callable resolvers for core entity methods.
 * These resolvers are then wired to PHPUnit mocks or Prophecy doubles
 * by the adapter traits.
 *
 * Method resolution order:
 * 1. methodOverrides from EntityDefinition
 * 2. Core entity/field resolvers from this builder
 * 3. Guardrail failure (handled by adapters)
 */
final class EntityDoubleBuilder
{
    /**
     * Cached field item list doubles.
     *
     * @var array<string, object>
     */
    private array $fieldListCache = [];

    /**
     * The entity definition.
     */
    private EntityDefinition $definition;

    /**
     * The mutable state container (null for immutable doubles).
     */
    private ?MutableStateContainer $mutableState;

    /**
     * Factory for creating field item list doubles.
     *
     * @var callable|null
     */
    private $fieldListFactory;

    /**
     * Constructs an EntityDoubleBuilder.
     *
     * @param EntityDefinition $definition
     *   The entity definition.
     * @param MutableStateContainer|null $mutableState
     *   The mutable state container, or NULL for immutable doubles.
     */
    public function __construct(
        EntityDefinition $definition,
        ?MutableStateContainer $mutableState = null,
    ) {
        $this->definition = $definition;
        $this->mutableState = $mutableState;
    }

    /**
     * Sets the factory for creating field item list doubles.
     *
     * @param callable $factory
     *   A callable that accepts (string $fieldName, FieldDefinition $fieldDef)
     *   and returns a field item list double.
     */
    public function setFieldListFactory(callable $factory): void
    {
        $this->fieldListFactory = $factory;
    }

    /**
     * Gets all core entity method resolvers.
     *
     * @return array<string, callable>
     *   Resolvers keyed by method name.
     */
    public function getResolvers(): array
    {
        return [
            'id' => $this->buildIdResolver(),
            'uuid' => $this->buildUuidResolver(),
            'label' => $this->buildLabelResolver(),
            'bundle' => $this->buildBundleResolver(),
            'getEntityTypeId' => $this->buildEntityTypeIdResolver(),
            'hasField' => $this->buildHasFieldResolver(),
            'get' => $this->buildGetResolver(),
            '__get' => $this->buildMagicGetResolver(),
            'set' => $this->buildSetResolver(),
        ];
    }

    /**
     * Builds the id() resolver.
     *
     * @return callable
     */
    private function buildIdResolver(): callable
    {
        return fn(array $context): mixed => $this->resolveValue(
            $this->definition->id,
            $context
        );
    }

    /**
     * Builds the uuid() resolver.
     *
     * @return callable
     */
    private function buildUuidResolver(): callable
    {
        return fn(array $context): mixed => $this->resolveValue(
            $this->definition->uuid,
            $context
        );
    }

    /**
     * Builds the label() resolver.
     *
     * @return callable
     */
    private function buildLabelResolver(): callable
    {
        return fn(array $context): mixed => $this->resolveValue(
            $this->definition->label,
            $context
        );
    }

    /**
     * Builds the bundle() resolver.
     *
     * @return callable
     */
    private function buildBundleResolver(): callable
    {
        return fn(array $context): string => $this->definition->bundle;
    }

    /**
     * Builds the getEntityTypeId() resolver.
     *
     * @return callable
     */
    private function buildEntityTypeIdResolver(): callable
    {
        return fn(array $context): string => $this->definition->entityType;
    }

    /**
     * Builds the hasField() resolver.
     *
     * @return callable
     */
    private function buildHasFieldResolver(): callable
    {
        return fn(array $context, string $fieldName): bool =>
            $this->definition->hasField($fieldName);
    }

    /**
     * Builds the get() resolver.
     *
     * Returns a FieldItemListInterface double for the requested field.
     *
     * @return callable
     */
    private function buildGetResolver(): callable
    {
        return function (array $context, string $fieldName): object {
            // Return cached field list if available.
            if (isset($this->fieldListCache[$fieldName])) {
                return $this->fieldListCache[$fieldName];
            }

            $fieldDefinition = $this->getFieldDefinitionForAccess($fieldName);

            if ($this->fieldListFactory === null) {
                throw new \LogicException(
                    "Field list factory not set. Cannot create field list double for '$fieldName'."
                );
            }

            // Create and cache the field list double.
            $fieldList = ($this->fieldListFactory)($fieldName, $fieldDefinition, $context);
            $this->fieldListCache[$fieldName] = $fieldList;

            return $fieldList;
        };
    }

    /**
     * Builds the __get() resolver.
     *
     * Proxies to get() for field access.
     *
     * @return callable
     */
    private function buildMagicGetResolver(): callable
    {
        $getResolver = $this->buildGetResolver();
        return fn(array $context, string $fieldName): object =>
            $getResolver($context, $fieldName);
    }

    /**
     * Builds the set() resolver.
     *
     * @return callable
     */
    private function buildSetResolver(): callable
    {
        return function (array $context, string $fieldName, mixed $value, bool $notify = true): object {
            if ($this->mutableState === null) {
                throw new \LogicException(
                    "Cannot modify field '$fieldName' on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }

            if (!$this->definition->hasField($fieldName)) {
                throw new \InvalidArgumentException(
                    "Field '$fieldName' is not defined on this entity double."
                );
            }

            // Store the new value in mutable state.
            $this->mutableState->setFieldValue($fieldName, $value);

            // Clear the field list cache so the new value is picked up.
            unset($this->fieldListCache[$fieldName]);

            // Return $this equivalent - adapter must handle this.
            return new class {};
        };
    }

    /**
     * Gets the field definition for a field access.
     *
     * Checks mutable state first, then falls back to definition.
     *
     * @param string $fieldName
     *   The field name.
     *
     * @return FieldDefinition
     *   The field definition.
     *
     * @throws \InvalidArgumentException
     *   If the field is not defined.
     */
    private function getFieldDefinitionForAccess(string $fieldName): FieldDefinition
    {
        // Check mutable state first.
        if ($this->mutableState !== null && $this->mutableState->hasFieldValue($fieldName)) {
            return new FieldDefinition($this->mutableState->getFieldValue($fieldName));
        }

        $fieldDefinition = $this->definition->getField($fieldName);
        if ($fieldDefinition === null) {
            throw new \InvalidArgumentException(
                "Field '$fieldName' is not defined on this entity double."
            );
        }

        return $fieldDefinition;
    }

    /**
     * Resolves a potentially callable value.
     *
     * @param mixed $value
     *   The value to resolve.
     * @param array<string, mixed> $context
     *   The context for callback resolution.
     * @param mixed ...$args
     *   Additional arguments for the callback.
     *
     * @return mixed
     *   The resolved value.
     */
    private function resolveValue(mixed $value, array $context, mixed ...$args): mixed
    {
        if (is_callable($value)) {
            return $value($context, ...$args);
        }
        return $value;
    }

    /**
     * Gets the entity definition.
     *
     * @return EntityDefinition
     */
    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    /**
     * Gets the mutable state container.
     *
     * @return MutableStateContainer|null
     */
    public function getMutableState(): ?MutableStateContainer
    {
        return $this->mutableState;
    }

    /**
     * Checks if a method has an override in the definition.
     *
     * @param string $method
     *   The method name.
     *
     * @return bool
     *   TRUE if an override exists, FALSE otherwise.
     */
    public function hasMethodOverride(string $method): bool
    {
        return $this->definition->hasMethodOverride($method);
    }

    /**
     * Gets the resolver for a method override.
     *
     * @param string $method
     *   The method name.
     *
     * @return callable
     *   The resolver for the override.
     */
    public function getMethodOverrideResolver(string $method): callable
    {
        $override = $this->definition->getMethodOverride($method);

        if (is_callable($override)) {
            return fn(array $context, mixed ...$args): mixed => $override($context, ...$args);
        }

        // Static value.
        return fn(array $context, mixed ...$args): mixed => $override;
    }
}
