<?php

declare(strict_types=1);

namespace Deuteros\Double;

/**
 * Container for mutable field state in entity doubles.
 *
 * Tracks field value changes separately from the immutable
 * EntityDoubleDefinition.
 * Used only for mutable entity doubles where field values can be updated for
 * assertion purposes.
 */
final class MutableStateContainer {

  /**
   * Mutable field values keyed by field name.
   *
   * @var array<string, mixed>
   */
  private array $fieldValues = [];

  /**
   * Checks if a field value has been mutated.
   *
   * @param string $fieldName
   *   The field name.
   *
   * @return bool
   *   TRUE if the field has been mutated, FALSE otherwise.
   */
  public function hasFieldValue(string $fieldName): bool {
    return array_key_exists($fieldName, $this->fieldValues);
  }

  /**
   * Gets a mutated field value.
   *
   * @param string $fieldName
   *   The field name.
   *
   * @return mixed
   *   The mutated field value.
   *
   * @throws \OutOfBoundsException
   *   If the field has not been mutated.
   */
  public function getFieldValue(string $fieldName): mixed {
    if (!$this->hasFieldValue($fieldName)) {
      throw new \OutOfBoundsException("Field '$fieldName' has not been mutated.");
    }
    return $this->fieldValues[$fieldName];
  }

  /**
   * Sets a mutated field value.
   *
   * @param string $fieldName
   *   The field name.
   * @param mixed $value
   *   The new field value.
   */
  public function setFieldValue(string $fieldName, mixed $value): void {
    $this->fieldValues[$fieldName] = $value;
  }

  /**
   * Resets all mutated field values.
   */
  public function reset(): void {
    $this->fieldValues = [];
  }

  /**
   * Gets all mutated field values.
   *
   * @return array<string, mixed>
   *   All mutated field values keyed by field name.
   */
  public function getAll(): array {
    return $this->fieldValues;
  }

}
