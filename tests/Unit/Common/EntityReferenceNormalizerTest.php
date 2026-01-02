<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Common;

use Deuteros\Common\EntityReferenceNormalizer;
use Drupal\Core\Entity\EntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EntityReferenceNormalizer class.
 */
#[CoversClass(EntityReferenceNormalizer::class)]
#[Group('deuteros')]
class EntityReferenceNormalizerTest extends TestCase {

  /**
   * Tests ::containsEntityReferences() with a single EntityInterface.
   */
  public function testContainsEntityReferencesWithEntity(): void {
    $entity = $this->createMock(EntityInterface::class);

    $this->assertTrue(EntityReferenceNormalizer::containsEntityReferences($entity));
  }

  /**
   * Tests ::containsEntityReferences() with an array containing entity key.
   */
  public function testContainsEntityReferencesWithEntityArray(): void {
    $entity = $this->createMock(EntityInterface::class);

    $this->assertTrue(EntityReferenceNormalizer::containsEntityReferences(['entity' => $entity]));
  }

  /**
   * Tests ::containsEntityReferences() with array of entities.
   */
  public function testContainsEntityReferencesWithMultipleEntities(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity2 = $this->createMock(EntityInterface::class);

    $this->assertTrue(EntityReferenceNormalizer::containsEntityReferences([$entity1, $entity2]));
  }

  /**
   * Tests ::containsEntityReferences() with array of entity items.
   */
  public function testContainsEntityReferencesWithMultipleEntityItems(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity2 = $this->createMock(EntityInterface::class);

    $value = [
      ['entity' => $entity1],
      ['entity' => $entity2],
    ];

    $this->assertTrue(EntityReferenceNormalizer::containsEntityReferences($value));
  }

  /**
   * Tests ::containsEntityReferences() returns FALSE for scalar values.
   */
  public function testContainsEntityReferencesWithScalar(): void {
    $this->assertFalse(EntityReferenceNormalizer::containsEntityReferences('string value'));
    $this->assertFalse(EntityReferenceNormalizer::containsEntityReferences(42));
    $this->assertFalse(EntityReferenceNormalizer::containsEntityReferences(NULL));
  }

  /**
   * Tests ::containsEntityReferences() returns FALSE for non-entity arrays.
   */
  public function testContainsEntityReferencesWithNonEntityArray(): void {
    $this->assertFalse(EntityReferenceNormalizer::containsEntityReferences(['target_id' => 1]));
    $this->assertFalse(EntityReferenceNormalizer::containsEntityReferences([['target_id' => 1]]));
  }

  /**
   * Tests ::normalize() with a single entity.
   */
  public function testNormalizeSingleEntity(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(42);

    $result = EntityReferenceNormalizer::normalize($entity);

    $this->assertCount(1, $result);
    $this->assertSame($entity, $result[0]['entity']);
    $this->assertSame(42, $result[0]['target_id']);
  }

  /**
   * Tests ::normalize() with entity in array format.
   */
  public function testNormalizeEntityWithExplicitKey(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(42);

    $result = EntityReferenceNormalizer::normalize(['entity' => $entity]);

    $this->assertCount(1, $result);
    $this->assertSame($entity, $result[0]['entity']);
    $this->assertSame(42, $result[0]['target_id']);
  }

  /**
   * Tests ::normalize() with multiple entities.
   */
  public function testNormalizeMultipleEntities(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity1->method('id')->willReturn(1);
    $entity2 = $this->createMock(EntityInterface::class);
    $entity2->method('id')->willReturn(2);

    $result = EntityReferenceNormalizer::normalize([$entity1, $entity2]);

    $this->assertCount(2, $result);
    $this->assertSame($entity1, $result[0]['entity']);
    $this->assertSame(1, $result[0]['target_id']);
    $this->assertSame($entity2, $result[1]['entity']);
    $this->assertSame(2, $result[1]['target_id']);
  }

  /**
   * Tests ::normalize() auto-populates target_id from entity.
   */
  public function testNormalizeAutoPopulatesTargetId(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(99);

    $result = EntityReferenceNormalizer::normalize($entity);

    $this->assertSame(99, $result[0]['target_id']);
  }

  /**
   * Tests ::normalize() handles NULL entity ID (new entity).
   */
  public function testNormalizeWithNullEntityId(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(NULL);

    $result = EntityReferenceNormalizer::normalize($entity);

    $this->assertCount(1, $result);
    $this->assertSame($entity, $result[0]['entity']);
    $this->assertNull($result[0]['target_id']);
  }

  /**
   * Tests ::normalize() accepts matching explicit target_id.
   */
  public function testNormalizeWithMatchingExplicitTargetId(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(42);

    $result = EntityReferenceNormalizer::normalize(['entity' => $entity, 'target_id' => 42]);

    $this->assertCount(1, $result);
    $this->assertSame(42, $result[0]['target_id']);
  }

  /**
   * Tests ::normalize() throws on target_id mismatch.
   */
  public function testNormalizeThrowsOnTargetIdMismatch(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(42);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Entity reference target_id mismatch: provided '999' but entity has ID '42'");

    EntityReferenceNormalizer::normalize(['entity' => $entity, 'target_id' => 999]);
  }

  /**
   * Tests ::normalize() returns empty array for scalar values.
   */
  public function testNormalizeWithScalarReturnsEmpty(): void {
    $this->assertSame([], EntityReferenceNormalizer::normalize('string'));
    $this->assertSame([], EntityReferenceNormalizer::normalize(42));
    $this->assertSame([], EntityReferenceNormalizer::normalize(NULL));
  }

  /**
   * Tests ::extractEntities() extracts entities keyed by delta.
   */
  public function testExtractEntities(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity2 = $this->createMock(EntityInterface::class);

    $items = [
      ['entity' => $entity1, 'target_id' => 1],
      ['entity' => $entity2, 'target_id' => 2],
    ];

    $result = EntityReferenceNormalizer::extractEntities($items);

    $this->assertCount(2, $result);
    $this->assertSame($entity1, $result[0]);
    $this->assertSame($entity2, $result[1]);
  }

  /**
   * Tests ::extractEntities() returns empty array for non-entity items.
   */
  public function testExtractEntitiesWithNoEntities(): void {
    $items = [
      ['target_id' => 1],
      ['target_id' => 2],
    ];

    $result = EntityReferenceNormalizer::extractEntities($items);

    $this->assertSame([], $result);
  }

  /**
   * Tests ::extractEntities() preserves delta keys.
   */
  public function testExtractEntitiesPreservesDelta(): void {
    $entity = $this->createMock(EntityInterface::class);

    $items = [
      0 => ['target_id' => 1],
      1 => ['entity' => $entity, 'target_id' => 2],
      2 => ['target_id' => 3],
    ];

    $result = EntityReferenceNormalizer::extractEntities($items);

    $this->assertCount(1, $result);
    $this->assertArrayHasKey(1, $result);
    $this->assertSame($entity, $result[1]);
  }

  /**
   * Tests ::normalize() with mixed entity items format.
   */
  public function testNormalizeWithMixedEntityItemsFormat(): void {
    $entity1 = $this->createMock(EntityInterface::class);
    $entity1->method('id')->willReturn(1);
    $entity2 = $this->createMock(EntityInterface::class);
    $entity2->method('id')->willReturn(2);

    $value = [
      $entity1,
      ['entity' => $entity2],
    ];

    $result = EntityReferenceNormalizer::normalize($value);

    $this->assertCount(2, $result);
    $this->assertSame($entity1, $result[0]['entity']);
    $this->assertSame(1, $result[0]['target_id']);
    $this->assertSame($entity2, $result[1]['entity']);
    $this->assertSame(2, $result[1]['target_id']);
  }

}
