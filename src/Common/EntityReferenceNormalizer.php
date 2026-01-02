<?php

declare(strict_types=1);

namespace Deuteros\Common;

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
 * - Array with entity and target_id: `['entity' => $user, 'target_id' => 42]`
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

    // Check for ['entity' => EntityInterface].
    if (isset($value['entity']) && $value['entity'] instanceof EntityInterface) {
      return TRUE;
    }

    // Check for array of EntityInterface or array of items with entity key.
    foreach ($value as $item) {
      if ($item instanceof EntityInterface) {
        return TRUE;
      }
      if (is_array($item) && isset($item['entity']) && $item['entity'] instanceof EntityInterface) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Normalizes entity reference field values.
   *
   * Converts shorthand forms to full structure with entity and target_id.
   *
   * @param mixed $value
   *   The raw field value.
   *
   * @return array<int, array{entity: \Drupal\Core\Entity\EntityInterface, target_id: mixed}>
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

    // Single item with entity key: ['entity' => $user].
    if (isset($value['entity']) && $value['entity'] instanceof EntityInterface) {
      return [self::normalizeItem($value['entity'], $value['target_id'] ?? NULL)];
    }

    // Array of items.
    $result = [];
    foreach ($value as $item) {
      if ($item instanceof EntityInterface) {
        $result[] = self::normalizeItem($item);
      }
      elseif (is_array($item) && isset($item['entity']) && $item['entity'] instanceof EntityInterface) {
        $result[] = self::normalizeItem($item['entity'], $item['target_id'] ?? NULL);
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

}
