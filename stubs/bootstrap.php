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
  require_once __DIR__ . '/stubs.php';
}
