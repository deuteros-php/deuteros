<?php

declare(strict_types=1);

namespace Deuteros\Common;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for entity double factories.
 *
 * Provides a common contract for creating entity doubles, allowing
 * implementation-agnostic test code.
 */
interface EntityDoubleFactoryInterface {

  /**
   * Creates an immutable entity double.
   *
   * Field values cannot be changed after creation.
   *
   * @param array<string, mixed> $definition
   *   The entity definition array.
   * @param array<string, mixed> $context
   *   Context data for callback resolution.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  public function create(array $definition, array $context = []): EntityInterface;

  /**
   * Creates a mutable entity double.
   *
   * Field values can be updated via set() methods for assertion purposes.
   *
   * @param array<string, mixed> $definition
   *   The entity definition array.
   * @param array<string, mixed> $context
   *   Context data for callback resolution.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The mutable entity double.
   */
  public function createMutable(array $definition, array $context = []): EntityInterface;

}
