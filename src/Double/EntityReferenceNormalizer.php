<?php

declare(strict_types=1);

namespace Deuteros\Double;

use Drupal\Core\Entity\EntityInterface;

/**
 * Normalizes entity reference field values.
 *
 * Handles conversion of entity doubles to proper field item structures,
 * auto-populating target_id and validating ID consistency.
 *
 * This normalizer supports multiple input formats:
 * - Single entity: `$user` (EntityInterface)
 * - Array with entity key: `['entity' => $user]`
 * - Array with NULL entity: `['entity' => NULL]` (empty reference)
 * - Array with entity and target_id: `['entity' => $user, 'target_id' => 42]`
 * - Array with target_id only: `['target_id' => 42]` (without entity)
 * - Array of entities: `[$tag1, $tag2]`
 * - Array of items: `[['entity' => $tag1], ['entity' => $tag2]]`
 *
 * @example Single entity reference
 * ```php
 * $normalized = EntityReferenceNormalizer::normalize($user);
 * // Returns: [['entity' => $user, 'target_id' => 42]]
 * ```
 *
 * @example Multi-value entity references
 * ```php
 * $normalized = EntityReferenceNormalizer::normalize([$tag1, $tag2]);
 * // Returns: [['entity' => $tag1, 'target_id' => 1], ['entity' => $tag2, ...]]
 * ```
 */
final class EntityReferenceNormalizer {

  /**
   * Checks if a value contains entity reference(s).
   *
   * Detects entity references by looking for:
   * - "EntityInterface" instances
   * - Arrays with "entity" key (even if NULL)
   * - Arrays with "target_id" key (without entity)
   *
   * @param mixed $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if entity references detected.
   */
  public static function containsEntityReferences(mixed $value): bool {
    if ($value instanceof EntityInterface) {
      return TRUE;
    }

    if (!is_array($value)) {
      return FALSE;
    }

    // Check for ['entity' => ...] or ['target_id' => ...].
    if (array_key_exists('entity', $value) || array_key_exists('target_id', $value)) {
      return TRUE;
    }

    // Check for array of EntityInterface or items with entity/target_id key.
    foreach ($value as $item) {
      if ($item instanceof EntityInterface) {
        return TRUE;
      }
      if (is_array($item) && (array_key_exists('entity', $item) || array_key_exists('target_id', $item))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Normalizes entity reference field values.
   *
   * Converts shorthand forms to full structure with entity and target_id.
   * Items with `['entity' => NULL]` are skipped (empty reference).
   * Items with only `['target_id' => X]` are passed through unchanged.
   *
   * @param mixed $value
   *   The raw field value.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized array of entity reference items.
   *
   * @throws \InvalidArgumentException
   *   If target_id is provided and doesn't match entity ID.
   */
  public static function normalize(mixed $value): array {
    // Single entity: $user.
    if ($value instanceof EntityInterface) {
      return [self::normalizeItem($value)];
    }

    if (!is_array($value)) {
      return [];
    }

    // Single item with entity key: ['entity' => $user] or ['entity' => NULL].
    if (array_key_exists('entity', $value)) {
      // NULL entity means empty reference - return empty array.
      if ($value['entity'] === NULL) {
        return [];
      }
      if ($value['entity'] instanceof EntityInterface) {
        return [self::normalizeItem($value['entity'], $value['target_id'] ?? NULL)];
      }
      // Invalid entity value - return empty.
      return [];
    }

    // Single item with only target_id: ['target_id' => 42].
    if (array_key_exists('target_id', $value) && !array_key_exists('entity', $value)) {
      /** @var array<string, mixed> $value */
      return [$value];
    }

    // Array of items.
    /** @var array<int, array<string, mixed>> $result */
    $result = [];
    foreach ($value as $item) {
      if ($item instanceof EntityInterface) {
        $result[] = self::normalizeItem($item);
      }
      elseif (is_array($item)) {
        if (array_key_exists('entity', $item)) {
          // Skip NULL entities (empty references).
          if ($item['entity'] === NULL) {
            continue;
          }
          if ($item['entity'] instanceof EntityInterface) {
            $result[] = self::normalizeItem($item['entity'], $item['target_id'] ?? NULL);
          }
        }
        elseif (array_key_exists('target_id', $item)) {
          // Target_id only - pass through.
          /** @var array<string, mixed> $item */
          $result[] = $item;
        }
      }
    }

    return $result;
  }

  /**
   * Normalizes a single entity reference item.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The referenced entity.
   * @param mixed $explicitTargetId
   *   Explicitly provided target_id, if any.
   *
   * @return array{entity: \Drupal\Core\Entity\EntityInterface, target_id: mixed}
   *   The normalized item.
   *
   * @throws \InvalidArgumentException
   *   If explicit target_id doesn't match entity ID.
   */
  private static function normalizeItem(EntityInterface $entity, mixed $explicitTargetId = NULL): array {
    $entityId = $entity->id();

    // Validate ID mismatch.
    if ($explicitTargetId !== NULL && $explicitTargetId !== $entityId) {
      $explicitIdString = is_scalar($explicitTargetId)
        ? (string) $explicitTargetId
        : gettype($explicitTargetId);
      $entityIdString = $entityId === NULL ? 'NULL' : (string) $entityId;
      throw new \InvalidArgumentException(sprintf(
        "Entity reference target_id mismatch: provided '%s' but entity has ID '%s'. "
        . "Either omit target_id (it will be auto-populated) or ensure it matches the entity's ID.",
        $explicitIdString,
        $entityIdString
      ));
    }

    return [
      'entity' => $entity,
      'target_id' => $entityId,
    ];
  }

  /**
   * Extracts entities from normalized items.
   *
   * @param array<int, mixed> $items
   *   Normalized items (or any array of field item values).
   *
   * @return array<int, \Drupal\Core\Entity\EntityInterface>
   *   Entities keyed by delta.
   */
  public static function extractEntities(array $items): array {
    $entities = [];
    foreach ($items as $delta => $item) {
      if (is_array($item) && isset($item['entity']) && $item['entity'] instanceof EntityInterface) {
        $entities[$delta] = $item['entity'];
      }
    }
    return $entities;
  }

  /**
   * Checks if any items have "target_id" but no "entity".
   *
   * Used to detect when `::referencedEntities` would fail because
   * entities were not provided.
   *
   * @param array<int, mixed> $items
   *   Normalized items.
   *
   * @return bool
   *   TRUE if any item has target_id but no entity.
   */
  public static function hasTargetIdOnlyItems(array $items): bool {
    foreach ($items as $item) {
      if (is_array($item) && array_key_exists('target_id', $item) && !array_key_exists('entity', $item)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
