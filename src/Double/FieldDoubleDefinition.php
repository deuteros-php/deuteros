<?php

declare(strict_types=1);

namespace Deuteros\Double;

/**
 * Immutable value object representing a field definition.
 *
 * Stores field value which can be a scalar, array, or callable. Optionally
 * stores a field type string used to wire "getFieldDefinition()" on field
 * list doubles.
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
   */
  public function __construct(
    private mixed $value,
    private readonly string $type = '',
  ) {}

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
