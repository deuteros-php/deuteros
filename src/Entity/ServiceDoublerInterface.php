<?php

declare(strict_types=1);

namespace Deuteros\Entity;

use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for service doublers.
 *
 * Implementations create service doubles using either PHPUnit or Prophecy.
 */
interface ServiceDoublerInterface {

  /**
   * Builds a container with doubled services.
   *
   * @param array<string, array{class: class-string, keys: array<string, string>}> $entityTypeConfigs
   *   Entity type configurations keyed by entity type ID.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container with doubled services.
   */
  public function buildContainer(array $entityTypeConfigs): ContainerInterface;

  /**
   * Creates a minimal field definition mock for a field.
   *
   * Used to populate the entity's "$fieldDefinitions" cache so that
   * "hasField()" and "getFieldDefinition()" work correctly.
   *
   * @param string $fieldName
   *   The field name.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   A minimal field definition mock.
   */
  public function createFieldDefinitionMock(string $fieldName): FieldDefinitionInterface;

}
