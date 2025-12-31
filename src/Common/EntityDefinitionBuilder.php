<?php

declare(strict_types=1);

namespace Deuteros\Common;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Fluent builder for creating EntityDefinition instances.
 *
 * Provides a type-safe, discoverable API for configuring entity doubles
 * without relying on array keys. Auto-adds FieldableEntityInterface when
 * fields are defined.
 *
 * @example Basic usage
 * ```php
 * $definition = EntityDefinitionBuilder::create('node')
 *   ->bundle('article')
 *   ->id(42)
 *   ->build();
 * ```
 *
 * @example With fields (auto-adds FieldableEntityInterface)
 * ```php
 * $definition = EntityDefinitionBuilder::create('node')
 *   ->bundle('article')
 *   ->field('field_title', 'Test Title')
 *   ->field('field_tags', [['target_id' => 1], ['target_id' => 2]])
 *   ->build();
 * ```
 *
 * @example Initialize from existing definition
 * ```php
 * $modified = EntityDefinitionBuilder::from($existingDefinition)
 *   ->label('New Label')
 *   ->build();
 * ```
 */
final class EntityDefinitionBuilder {

  /**
   * The entity type ID.
   */
  private string $entityType;

  /**
   * The bundle (defaults to entity type if empty).
   */
  private string $bundle = '';

  /**
   * The entity ID.
   */
  private mixed $id = NULL;

  /**
   * The entity UUID.
   */
  private mixed $uuid = NULL;

  /**
   * The entity label.
   */
  private mixed $label = NULL;

  /**
   * Field definitions keyed by field name.
   *
   * @var array<string, \Deuteros\Common\FieldDefinition>
   */
  private array $fields = [];

  /**
   * Interfaces to implement.
   *
   * @var list<class-string>
   */
  private array $interfaces = [];

  /**
   * Method overrides keyed by method name.
   *
   * @var array<string, callable|mixed>
   */
  private array $methodOverrides = [];

  /**
   * Context data for callback resolution.
   *
   * @var array<string, mixed>
   */
  private array $context = [];

  /**
   * Private constructor - use create() or from() factory methods.
   *
   * @param string $entityType
   *   The entity type ID.
   */
  private function __construct(string $entityType) {
    $this->entityType = $entityType;
  }

  /**
   * Creates a new builder for the given entity type.
   *
   * @param string $entityType
   *   The entity type ID (e.g., 'node', 'user', 'taxonomy_term').
   *
   * @return self
   *   A new builder instance.
   */
  public static function create(string $entityType): self {
    return new self($entityType);
  }

  /**
   * Creates a builder initialized from an existing definition.
   *
   * Allows copying and modifying existing definitions.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The existing definition to copy from.
   *
   * @return self
   *   A new builder instance with values from the definition.
   */
  public static function from(EntityDefinition $definition): self {
    $builder = new self($definition->entityType);
    $builder->bundle = $definition->bundle;
    $builder->id = $definition->id;
    $builder->uuid = $definition->uuid;
    $builder->label = $definition->label;
    $builder->fields = $definition->fields;
    $builder->interfaces = $definition->interfaces;
    $builder->methodOverrides = $definition->methodOverrides;
    $builder->context = $definition->context;
    return $builder;
  }

  /**
   * Sets the bundle.
   *
   * @param string $bundle
   *   The bundle name (defaults to entity type if not set).
   *
   * @return $this
   */
  public function bundle(string $bundle): self {
    $this->bundle = $bundle;
    return $this;
  }

  /**
   * Sets the entity ID.
   *
   * @param mixed $id
   *   The entity ID (scalar or callable).
   *
   * @return $this
   */
  public function id(mixed $id): self {
    $this->id = $id;
    return $this;
  }

  /**
   * Sets the entity UUID.
   *
   * @param mixed $uuid
   *   The entity UUID (scalar or callable).
   *
   * @return $this
   */
  public function uuid(mixed $uuid): self {
    $this->uuid = $uuid;
    return $this;
  }

  /**
   * Sets the entity label.
   *
   * @param mixed $label
   *   The entity label (scalar or callable).
   *
   * @return $this
   */
  public function label(mixed $label): self {
    $this->label = $label;
    return $this;
  }

  /**
   * Adds a field with the given value.
   *
   * Automatically adds FieldableEntityInterface if not already present.
   *
   * @param string $fieldName
   *   The field name.
   * @param mixed $value
   *   The field value (scalar, array, or callable).
   *
   * @return $this
   */
  public function field(string $fieldName, mixed $value): self {
    $this->fields[$fieldName] = $value instanceof FieldDefinition
      ? $value
      : new FieldDefinition($value);
    return $this;
  }

  /**
   * Adds multiple fields at once.
   *
   * Automatically adds FieldableEntityInterface if not already present.
   *
   * @param array<string, mixed> $fields
   *   Field values keyed by field name.
   *
   * @return $this
   */
  public function fields(array $fields): self {
    foreach ($fields as $fieldName => $value) {
      $this->field($fieldName, $value);
    }
    return $this;
  }

  /**
   * Adds an interface to implement.
   *
   * Note: FieldableEntityInterface is auto-added when fields are defined.
   * EntityInterface is always included by the factory.
   *
   * @param class-string $interface
   *   The fully qualified interface name.
   *
   * @return $this
   */
  public function interface(string $interface): self {
    if (!in_array($interface, $this->interfaces, TRUE)) {
      $this->interfaces[] = $interface;
    }
    return $this;
  }

  /**
   * Adds multiple interfaces to implement.
   *
   * @param list<class-string> $interfaces
   *   List of fully qualified interface names.
   *
   * @return $this
   */
  public function interfaces(array $interfaces): self {
    foreach ($interfaces as $interface) {
      $this->interface($interface);
    }
    return $this;
  }

  /**
   * Adds a method override.
   *
   * Method overrides take precedence over core resolvers.
   *
   * @param string $method
   *   The method name.
   * @param callable|mixed $resolver
   *   The resolver (callable receiving context array, or static value).
   *
   * @return $this
   */
  public function methodOverride(string $method, mixed $resolver): self {
    $this->methodOverrides[$method] = $resolver;
    return $this;
  }

  /**
   * Adds multiple method overrides at once.
   *
   * @param array<string, callable|mixed> $overrides
   *   Overrides keyed by method name.
   *
   * @return $this
   */
  public function methodOverrides(array $overrides): self {
    foreach ($overrides as $method => $resolver) {
      $this->methodOverride($method, $resolver);
    }
    return $this;
  }

  /**
   * Adds a single context value.
   *
   * Note: Context can also be passed at factory create time, which will
   * be merged with any context set here.
   *
   * @param string $key
   *   The context key.
   * @param mixed $value
   *   The context value.
   *
   * @return $this
   */
  public function context(string $key, mixed $value): self {
    $this->context[$key] = $value;
    return $this;
  }

  /**
   * Adds multiple context values at once.
   *
   * @param array<string, mixed> $context
   *   Context values keyed by name.
   *
   * @return $this
   */
  public function withContext(array $context): self {
    $this->context = array_merge($this->context, $context);
    return $this;
  }

  /**
   * Builds the EntityDefinition.
   *
   * @return \Deuteros\Common\EntityDefinition
   *   The built entity definition.
   *
   * @throws \InvalidArgumentException
   *   If the configuration is invalid.
   */
  public function build(): EntityDefinition {
    $interfaces = $this->interfaces;

    // Auto-add FieldableEntityInterface when fields are defined.
    if ($this->fields !== [] && !in_array(FieldableEntityInterface::class, $interfaces, TRUE)) {
      $interfaces[] = FieldableEntityInterface::class;
    }

    return new EntityDefinition(
      entityType: $this->entityType,
      bundle: $this->bundle,
      id: $this->id,
      uuid: $this->uuid,
      label: $this->label,
      fields: $this->fields,
      interfaces: $interfaces,
      methodOverrides: $this->methodOverrides,
      context: $this->context,
    );
  }

}
