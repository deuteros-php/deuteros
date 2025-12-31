<?php

/**
 * @file
 * Bootstrap file for Drupal interface stubs.
 *
 * This file conditionally loads stub interfaces when Drupal core is not
 * available. When used within a Drupal project, the real interfaces from
 * Drupal core take precedence and these stubs are not loaded.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityInterface;

// Only load stubs if Drupal core is not available.
if (!interface_exists(EntityInterface::class)) {
  $stubDir = __DIR__ . '/Drupal';

  // Load in dependency order (parents first).
  $stubs = [
    // Base interfaces (no dependencies).
    'Core/Entity/EntityInterface.php',
    'Core/Field/FieldItemInterface.php',
    'Core/Field/FieldItemListInterface.php',
    // Interfaces extending EntityInterface.
    'Core/Entity/FieldableEntityInterface.php',
    'Core/Entity/EntityChangedInterface.php',
    'Core/Entity/EntityPublishedInterface.php',
    'Core/Config/Entity/ConfigEntityInterface.php',
    'user/EntityOwnerInterface.php',
    // Interfaces extending FieldableEntityInterface.
    'Core/Entity/ContentEntityInterface.php',
    // NodeInterface (extends multiple).
    'node/NodeInterface.php',
  ];

  foreach ($stubs as $stub) {
    require_once $stubDir . '/' . $stub;
  }
}
