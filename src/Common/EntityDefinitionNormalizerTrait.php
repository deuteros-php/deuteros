<?php

declare(strict_types=1);

namespace Deuteros\Common;

/**
 * Normalizes user input arrays into EntityDefinition objects.
 *
 * This trait is used by both PHPUnit and Prophecy adapter traits to provide
 * consistent normalization of entity double definitions.
 *
 * No doubles are created here - only pure PHP normalization.
 */
trait EntityDefinitionNormalizerTrait {

  /**
   * Normalizes a definition array into an EntityDefinition.
   *
   * @param array<string, mixed> $definition
   *   The definition array with snake_case keys.
   * @param array<string, mixed> $context
   *   Additional context to merge.
   * @param bool $mutable
   *   Whether the entity double should be mutable.
   *
   * @return EntityDefinition
   *   The normalized EntityDefinition.
   */
  protected function normalizeDefinition(
    array $definition,
    array $context = [],
    bool $mutable = FALSE,
  ): EntityDefinition {

    // Merge context into definition.
    if ($context !== []) {
      $definition['context'] = array_merge($definition['context'] ?? [], $context);
    }

    // Set mutability.
    $definition['mutable'] = $mutable;

    return EntityDefinition::fromArray($definition);
  }

}
