<?php

declare(strict_types=1);

namespace Deuteros\Double;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

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
   * Creates a Url double.
   *
   * Creates a Url mock/prophecy with ::toString wired to return the URL string
   * or a GeneratedUrl double when $collect_bubbleable_metadata is TRUE.
   *
   * @param string $url
   *   The URL string.
   * @param array<string, mixed> $context
   *   The context.
   *
   * @return \Drupal\Core\Url
   *   The Url double.
   */
  public function createUrlDouble(string $url, array $context = []): Url;

}
