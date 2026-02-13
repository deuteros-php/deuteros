<?php

declare(strict_types=1);

namespace Deuteros\Double;

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
   * @param \Deuteros\Double\EntityDoubleDefinition $definition
   *   The entity double definition.
   * @param array<string, mixed> $context
   *   Additional context data to merge with definition context.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  public function create(EntityDoubleDefinition $definition, array $context = []): EntityInterface;

  /**
   * Creates a mutable entity double.
   *
   * Field values can be updated via ::set for assertion purposes.
   *
   * @param \Deuteros\Double\EntityDoubleDefinition $definition
   *   The entity double definition.
   * @param array<string, mixed> $context
   *   Additional context data to merge with definition context.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The mutable entity double.
   */
  public function createMutable(EntityDoubleDefinition $definition, array $context = []): EntityInterface;

  /**
   * Creates a raw immutable entity double for post-creation customization.
   *
   * Returns the framework-specific double object (PHPUnit "MockObject" or
   * Prophecy "ObjectProphecy") instead of a finalized "EntityInterface".
   * This enables adding custom stubs and expectations after creation.
   *
   * PHPUnit: returns a "MockObject" (also implements "EntityInterface").
   * Prophecy: returns an "ObjectProphecy" (call ::reveal to get the
   * entity).
   *
   * @param \Deuteros\Double\EntityDoubleDefinition $definition
   *   The entity double definition. Must not contain traits.
   * @param array<string, mixed> $context
   *   Additional context data to merge with definition context.
   *
   * @return object
   *   The raw double object.
   *
   * @throws \InvalidArgumentException
   *   If the definition contains traits.
   */
  public function createEntityDouble(EntityDoubleDefinition $definition, array $context = []): object;

  /**
   * Creates a raw mutable entity double for post-creation customization.
   *
   * Same as ::createEntityDouble but with mutable field values.
   *
   * @param \Deuteros\Double\EntityDoubleDefinition $definition
   *   The entity double definition. Must not contain traits.
   * @param array<string, mixed> $context
   *   Additional context data to merge with definition context.
   *
   * @return object
   *   The raw double object.
   *
   * @throws \InvalidArgumentException
   *   If the definition contains traits.
   */
  public function createMutableEntityDouble(EntityDoubleDefinition $definition, array $context = []): object;

}
