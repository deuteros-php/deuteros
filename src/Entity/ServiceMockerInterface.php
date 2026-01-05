<?php

declare(strict_types=1);

namespace Deuteros\Entity;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for service mockers.
 *
 * Implementations create mock services using either PHPUnit or Prophecy.
 */
interface ServiceMockerInterface {

  /**
   * Builds a mock container with the required services.
   *
   * @param array<string, array{class: class-string, keys: array<string, string>}> $entityTypeConfigs
   *   Entity type configurations keyed by entity type ID.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The mock container.
   */
  public function buildContainer(array $entityTypeConfigs): ContainerInterface;

}
