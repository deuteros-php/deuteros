<?php

declare(strict_types=1);

namespace Deuteros\Double;

/**
 * Immutable value object representing a field definition.
 *
 * Stores field value which can be a scalar, array, or callable. Optionally
 * stores a field type string used to wire "getFieldDefinition()" on field
 * list doubles, and an optional settings array for field-level configuration.
 */
final readonly class FieldDoubleDefinition {

  /**
   * Constructs a FieldDoubleDefinition.
   *
   * @param mixed $value
   *   The field value (scalar, array, or callable).
   * @param string $type
   *   The field type (e.g., "text", "metatag"). Defaults to empty string,
   *   meaning "getFieldDefinition()" will not be wired.
   * @param array<string, mixed> $settings
   *   Optional field settings keyed by setting name.
   * @param string $itemClass
   *   Optional field item class, e.g. the "FieldItemInterface" implementation
   *   of the field type. Only its static "::mainPropertyName" is read.
   *   Defaults to empty string, meaning the main property name is inferred.
   */
  public function __construct(
    private mixed $value,
    private readonly string $type = '',
    private readonly array $settings = [],
    private readonly string $itemClass = '',
  ) {
    if ($itemClass !== '' && !is_callable([$itemClass, 'mainPropertyName'])) {
      throw new \InvalidArgumentException(sprintf('The field item class "%s" must exist and declare a static ::mainPropertyName() method.', $itemClass));
    }
  }

  /**
   * Gets the field type.
   *
   * @return string
   *   The field type, or empty string if not set.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Gets a field setting by key.
   *
   * @param string $setting
   *   The setting key.
   *
   * @return mixed
   *   The setting value, or NULL if the key is not set.
   */
  public function getSetting(string $setting): mixed {
    return $this->settings[$setting] ?? NULL;
  }

  /**
   * Gets every field setting.
   *
   * @return array<string, mixed>
   *   The settings keyed by setting name.
   */
  public function getSettings(): array {
    return $this->settings;
  }

  /**
   * Gets the field item class.
   *
   * @return string
   *   The field item class, or empty string if not set.
   */
  public function getItemClass(): string {
    return $this->itemClass;
  }

  /**
   * Gets the name of the main property of the field items.
   *
   * With a field item class, the name is what its static
   * "::mainPropertyName" returns. Without one, the name is inferred from the
   * shape of the item doubles: they hold a scalar under "value", and an
   * entity reference under "target_id".
   *
   * @param bool $hasEntityReferences
   *   Whether the field holds entity references.
   *
   * @return string|null
   *   The main property name, or NULL when the item class declares none.
   */
  public function getMainPropertyName(bool $hasEntityReferences = FALSE): ?string {
    if ($this->itemClass === '') {
      return $hasEntityReferences ? 'target_id' : 'value';
    }
    $callable = [$this->itemClass, 'mainPropertyName'];
    assert(is_callable($callable));
    $name = $callable();
    if ($name !== NULL && !is_string($name)) {
      throw new \LogicException(sprintf('"%s::mainPropertyName()" must return a string or NULL.', $this->itemClass));
    }
    return $name;
  }

  /**
   * Gets the field value.
   *
   * @return mixed
   *   The field value.
   */
  public function getValue(): mixed {
    return $this->value;
  }

  /**
   * Checks if the value is a callable.
   *
   * @return bool
   *   TRUE if the value is callable, FALSE otherwise.
   */
  public function isCallable(): bool {
    return is_callable($this->value);
  }

  /**
   * Checks if the value is an array (multi-value field).
   *
   * @return bool
   *   TRUE if the value is an array, FALSE otherwise.
   */
  public function isMultiValue(): bool {
    return is_array($this->value) && !is_callable($this->value);
  }

}
