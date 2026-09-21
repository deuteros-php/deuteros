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
   * If a container is provided, it will be configured with the doubled
   * services; otherwise a new container is created. Passing an existing
   * container keeps the services a test has set on it, its own and the
   * defaults it replaced alike, when the container needs to be rebuilt
   * (e.g., when new entity types are registered). Only the services that
   * describe the registered entity types, "entity_type.manager" and
   * "entity_type.bundle.info", are replaced on a rebuild.
   *
   * @param array<string, array{class: class-string, keys: array<string, string>}> $entityTypeConfigs
   *   Entity type configurations keyed by entity type ID.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface|null $container
   *   Optional container to configure. If NULL, a new container is created.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container with doubled services.
   */
  public function buildContainer(
    array $entityTypeConfigs,
    ?ContainerInterface $container = NULL,
  ): ContainerInterface;

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
