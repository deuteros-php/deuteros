<?php

declare(strict_types=1);

namespace Deuteros\Common;

/**
 * Builds callable resolvers for field item list double methods.
 *
 * Produces framework-agnostic callable resolvers for FieldItemListInterface
 * methods. These resolvers are then wired to PHPUnit mocks or Prophecy
 * doubles by the adapter traits.
 */
final class FieldItemListDoubleBuilder
{
    /**
     * The field definition (mutable for mutable doubles).
     */
    private FieldDefinition $fieldDefinition;

    /**
     * Cached resolved value (for callable fields).
     */
    private mixed $resolvedValue = null;

    /**
     * Whether the value has been resolved.
     */
    private bool $valueResolved = false;

    /**
     * Cached field item doubles keyed by delta.
     *
     * @var array<int, object>
     */
    private array $fieldItemCache = [];

    /**
     * Factory for creating field item doubles.
     *
     * @var callable|null
     */
    private mixed $fieldItemFactory = null;

    /**
     * Callback to update the mutable state.
     *
     * @var callable|null
     */
    private mixed $mutableStateUpdater = null;

    /**
     * Constructs a FieldItemListDoubleBuilder.
     *
     * @param FieldDefinition $fieldDefinition
     *   The field definition.
     * @param string $fieldName
     *   The field name.
     * @param bool $mutable
     *   Whether the parent entity is mutable.
     */
    public function __construct(
        FieldDefinition $fieldDefinition,
        private readonly string $fieldName,
        private readonly bool $mutable = false,
    ) {
        $this->fieldDefinition = $fieldDefinition;
    }

    /**
     * Sets the factory for creating field item doubles.
     *
     * @param callable $factory
     *   A callable that accepts (int $delta, mixed $value) and returns a
     *   field item double.
     */
    public function setFieldItemFactory(callable $factory): void
    {
        $this->fieldItemFactory = $factory;
    }

    /**
     * Sets the callback to update mutable state.
     *
     * @param callable $updater
     *   A callable that accepts (string $fieldName, mixed $value).
     */
    public function setMutableStateUpdater(callable $updater): void
    {
        $this->mutableStateUpdater = $updater;
    }

    /**
     * Gets all field item list method resolvers.
     *
     * @return array<string, callable>
     *   Resolvers keyed by method name.
     */
    public function getResolvers(): array
    {
        return [
            'first' => $this->buildFirstResolver(),
            'isEmpty' => $this->buildIsEmptyResolver(),
            'getValue' => $this->buildGetValueResolver(),
            'get' => $this->buildGetResolver(),
            '__get' => $this->buildMagicGetResolver(),
            'setValue' => $this->buildSetValueResolver(),
            '__set' => $this->buildMagicSetResolver(),
        ];
    }

    /**
     * Builds the first() resolver.
     *
     * @return callable
     */
    private function buildFirstResolver(): callable
    {
        return function (array $context): ?object {
            $values = $this->resolveValues($context);

            if (empty($values)) {
                return null;
            }

            return $this->getFieldItemDouble(0, $values[0], $context);
        };
    }

    /**
     * Builds the isEmpty() resolver.
     *
     * @return callable
     */
    private function buildIsEmptyResolver(): callable
    {
        return function (array $context): bool {
            $values = $this->resolveValues($context);
            return empty($values);
        };
    }

    /**
     * Builds the getValue() resolver.
     *
     * @return callable
     */
    private function buildGetValueResolver(): callable
    {
        return function (array $context): array {
            return $this->resolveValues($context);
        };
    }

    /**
     * Builds the get() resolver.
     *
     * @return callable
     */
    private function buildGetResolver(): callable
    {
        return function (array $context, int $delta): ?object {
            $values = $this->resolveValues($context);

            if (!isset($values[$delta])) {
                return null;
            }

            return $this->getFieldItemDouble($delta, $values[$delta], $context);
        };
    }

    /**
     * Builds the __get() resolver.
     *
     * Proxies common property access to first() item.
     *
     * @return callable
     */
    private function buildMagicGetResolver(): callable
    {
        return function (array $context, string $property): mixed {
            $firstItem = ($this->buildFirstResolver())($context);

            if ($firstItem === null) {
                return null;
            }

            // The field item double should have a __get resolver.
            // We'll call it directly since we control the double.
            return $firstItem->__get($property);
        };
    }

    /**
     * Builds the setValue() resolver.
     *
     * @return callable
     */
    private function buildSetValueResolver(): callable
    {
        return function (array $context, mixed $values, bool $notify = true): object {
            if (!$this->mutable) {
                throw new \LogicException(
                    "Cannot modify field '{$this->fieldName}' on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }

            // Update the mutable state.
            if ($this->mutableStateUpdater !== null) {
                ($this->mutableStateUpdater)($this->fieldName, $values);
            }

            // Reset cached values.
            $this->valueResolved = false;
            $this->resolvedValue = null;
            $this->fieldItemCache = [];

            // Update the field definition.
            $this->fieldDefinition = new FieldDefinition($values);

            // Return $this equivalent.
            return new class {};
        };
    }

    /**
     * Builds the __set() resolver.
     *
     * Proxies 'value' property set to setValue().
     *
     * @return callable
     */
    private function buildMagicSetResolver(): callable
    {
        $setValueResolver = $this->buildSetValueResolver();

        return function (array $context, string $property, mixed $value) use ($setValueResolver): void {
            if ($property === 'value') {
                $setValueResolver($context, $value, true);
            } else {
                throw new \LogicException(
                    "Setting property '$property' on field item list is not supported."
                );
            }
        };
    }

    /**
     * Resolves the field values.
     *
     * Handles callable resolution and caching.
     *
     * @param array<string, mixed> $context
     *   The context for callback resolution.
     *
     * @return array<int, mixed>
     *   The resolved values as an indexed array.
     */
    private function resolveValues(array $context): array
    {
        if ($this->valueResolved) {
            return $this->normalizeToArray($this->resolvedValue);
        }

        $rawValue = $this->fieldDefinition->getValue();

        // Resolve callable.
        if ($this->fieldDefinition->isCallable()) {
            $rawValue = ($rawValue)($context);
        }

        $this->resolvedValue = $rawValue;
        $this->valueResolved = true;

        return $this->normalizeToArray($rawValue);
    }

    /**
     * Normalizes a value to an indexed array of field item values.
     *
     * @param mixed $value
     *   The raw value.
     *
     * @return array<int, mixed>
     *   The normalized array.
     */
    private function normalizeToArray(mixed $value): array
    {
        return match (true) {
            $value === null => [],
            is_array($value) && $this->isIndexedArray($value) => array_values($value),
            default => [$value],
        };
    }

    /**
     * Checks if an array is an indexed (sequential) array.
     *
     * @param array $array
     *   The array to check.
     *
     * @return bool
     *   TRUE if indexed, FALSE if associative.
     */
    private function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        // If any value in the array is itself an array (representing a
        // multi-value field), treat the outer array as indexed.
        $firstKey = array_key_first($array);
        if (is_int($firstKey) && is_array($array[$firstKey])) {
            return true;
        }

        // For simple values, treat as single-item field.
        return false;
    }

    /**
     * Gets a field item double for a specific delta.
     *
     * @param int $delta
     *   The delta.
     * @param mixed $value
     *   The item value.
     * @param array<string, mixed> $context
     *   The context.
     *
     * @return object
     *   The field item double.
     */
    private function getFieldItemDouble(int $delta, mixed $value, array $context): object
    {
        if (isset($this->fieldItemCache[$delta])) {
            return $this->fieldItemCache[$delta];
        }

        if ($this->fieldItemFactory === null) {
            throw new \LogicException(
                "Field item factory not set. Cannot create field item double."
            );
        }

        $fieldItem = ($this->fieldItemFactory)($delta, $value, $context);
        $this->fieldItemCache[$delta] = $fieldItem;

        return $fieldItem;
    }

    /**
     * Gets the field name.
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Gets the field definition.
     *
     * @return FieldDefinition
     */
    public function getFieldDefinition(): FieldDefinition
    {
        return $this->fieldDefinition;
    }
}
