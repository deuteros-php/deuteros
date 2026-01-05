<?php

declare(strict_types=1);

namespace Deuteros\Double;

/**
 * Immutable value object representing a field definition.
 *
 * Stores field value which can be a scalar, array, or callable.
 * No behavior beyond holding the value.
 */
final readonly class FieldDoubleDefinition {

  /**
   * Constructs a FieldDoubleDefinition.
   *
   * @param mixed $value
   *   The field value (scalar, array, or callable).
   */
  public function __construct(
    private mixed $value,
  ) {}

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
