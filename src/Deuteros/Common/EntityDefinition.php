<?php

declare(strict_types=1);

namespace Deuteros\Common;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Immutable value object representing an entity definition.
 *
 * Stores all configuration needed to create an entity double:
 * - Entity metadata (type, bundle, id, uuid, label)
 * - Field definitions
 * - Interfaces to implement
 * - Method overrides for custom behavior
 * - Context for callback resolution
 *
 * @see \Deuteros\Common\EntityDoubleBuilder
 */
final class EntityDefinition
{
    /**
     * The entity type ID.
     */
    public readonly string $entityType;

    /**
     * The bundle.
     */
    public readonly string $bundle;

    /**
     * The entity ID.
     */
    public readonly mixed $id;

    /**
     * The entity UUID.
     */
    public readonly mixed $uuid;

    /**
     * The entity label.
     */
    public readonly mixed $label;

    /**
     * Field definitions keyed by field name.
     *
     * @var array<string, FieldDefinition>
     */
    public readonly array $fields;

    /**
     * List of interfaces the entity double should implement.
     *
     * @var string[]
     */
    public readonly array $interfaces;

    /**
     * Method overrides keyed by method name.
     *
     * @var array<string, callable|mixed>
     */
    public readonly array $methodOverrides;

    /**
     * Context data for callback resolution.
     *
     * @var array<string, mixed>
     */
    public readonly array $context;

    /**
     * Whether the entity double should be mutable.
     */
    public readonly bool $mutable;

    /**
     * Constructs an EntityDefinition.
     *
     * @param string $entityType
     *   The entity type ID.
     * @param string $bundle
     *   The bundle.
     * @param mixed $id
     *   The entity ID.
     * @param mixed $uuid
     *   The entity UUID.
     * @param mixed $label
     *   The entity label.
     * @param array<string, FieldDefinition> $fields
     *   Field definitions keyed by field name.
     * @param string[] $interfaces
     *   List of interfaces to implement.
     * @param array<string, callable|mixed> $methodOverrides
     *   Method overrides keyed by method name.
     * @param array<string, mixed> $context
     *   Context data for callback resolution.
     * @param bool $mutable
     *   Whether the entity double should be mutable.
     *
     * @throws \InvalidArgumentException
     *   If fields are defined but FieldableEntityInterface is not in interfaces.
     */
    public function __construct(
        string $entityType,
        string $bundle = '',
        mixed $id = null,
        mixed $uuid = null,
        mixed $label = null,
        array $fields = [],
        array $interfaces = [],
        array $methodOverrides = [],
        array $context = [],
        bool $mutable = false,
    ) {
        // Validate that fields are only used with FieldableEntityInterface.
        if (!empty($fields) && !in_array(FieldableEntityInterface::class, $interfaces, true)) {
            throw new \InvalidArgumentException(
                "Fields can only be defined when FieldableEntityInterface is listed in interfaces. "
                . "Add FieldableEntityInterface::class to the 'interfaces' array."
            );
        }

        $this->entityType = $entityType;
        $this->bundle = $bundle !== '' ? $bundle : $entityType;
        $this->id = $id;
        $this->uuid = $uuid;
        $this->label = $label;
        $this->fields = $fields;
        $this->interfaces = $interfaces;
        $this->methodOverrides = $methodOverrides;
        $this->context = $context;
        $this->mutable = $mutable;
    }

    /**
     * Creates an EntityDefinition from an array.
     *
     * Used by traits for convenient definition syntax.
     *
     * @param array<string, mixed> $data
     *   The definition data with snake_case keys:
     *   - entity_type: (required) The entity type ID.
     *   - bundle: The bundle (defaults to entity_type).
     *   - id: The entity ID.
     *   - uuid: The entity UUID.
     *   - label: The entity label.
     *   - fields: Field definitions (raw values, will be converted to FieldDefinition).
     *   - interfaces: List of interfaces to implement.
     *   - method_overrides: Method overrides keyed by method name.
     *   - context: Context data for callback resolution.
     *   - mutable: Whether the entity double should be mutable.
     *
     * @return self
     *   A new EntityDefinition instance.
     *
     * @throws \InvalidArgumentException
     *   If entity_type is missing.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['entity_type']) || !is_string($data['entity_type']) || $data['entity_type'] === '') {
            throw new \InvalidArgumentException("'entity_type' is required and must be a non-empty string.");
        }

        // Convert raw field values to FieldDefinition objects.
        $fields = [];
        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $fieldName => $value) {
                $fields[$fieldName] = $value instanceof FieldDefinition
                    ? $value
                    : new FieldDefinition($value);
            }
        }

        return new self(
            entityType: $data['entity_type'],
            bundle: $data['bundle'] ?? '',
            id: $data['id'] ?? null,
            uuid: $data['uuid'] ?? null,
            label: $data['label'] ?? null,
            fields: $fields,
            interfaces: $data['interfaces'] ?? [],
            methodOverrides: $data['method_overrides'] ?? [],
            context: $data['context'] ?? [],
            mutable: $data['mutable'] ?? false,
        );
    }

    /**
     * Checks if a specific interface is implemented.
     *
     * @param string $interface
     *   The fully qualified interface name.
     *
     * @return bool
     *   TRUE if the interface is listed, FALSE otherwise.
     */
    public function hasInterface(string $interface): bool
    {
        return in_array($interface, $this->interfaces, true);
    }

    /**
     * Checks if a method override exists.
     *
     * @param string $method
     *   The method name.
     *
     * @return bool
     *   TRUE if an override exists, FALSE otherwise.
     */
    public function hasMethodOverride(string $method): bool
    {
        return array_key_exists($method, $this->methodOverrides);
    }

    /**
     * Gets a method override.
     *
     * @param string $method
     *   The method name.
     *
     * @return callable|mixed|null
     *   The override value, or NULL if not defined.
     */
    public function getMethodOverride(string $method): mixed
    {
        return $this->methodOverrides[$method] ?? null;
    }

    /**
     * Checks if a field is defined.
     *
     * @param string $fieldName
     *   The field name.
     *
     * @return bool
     *   TRUE if the field is defined, FALSE otherwise.
     */
    public function hasField(string $fieldName): bool
    {
        return isset($this->fields[$fieldName]);
    }

    /**
     * Gets a field definition.
     *
     * @param string $fieldName
     *   The field name.
     *
     * @return FieldDefinition|null
     *   The field definition, or NULL if not defined.
     */
    public function getField(string $fieldName): ?FieldDefinition
    {
        return $this->fields[$fieldName] ?? null;
    }

    /**
     * Creates a new definition with additional context.
     *
     * @param array<string, mixed> $additionalContext
     *   Additional context to merge.
     *
     * @return self
     *   A new EntityDefinition with merged context.
     */
    public function withContext(array $additionalContext): self
    {
        return new self(
            entityType: $this->entityType,
            bundle: $this->bundle,
            id: $this->id,
            uuid: $this->uuid,
            label: $this->label,
            fields: $this->fields,
            interfaces: $this->interfaces,
            methodOverrides: $this->methodOverrides,
            context: array_merge($this->context, $additionalContext),
            mutable: $this->mutable,
        );
    }
}
