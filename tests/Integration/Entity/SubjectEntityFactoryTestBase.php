<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Entity;

use Deuteros\Common\EntityDoubleDefinitionBuilder;
use Deuteros\Entity\SubjectEntityFactory;
use Deuteros\Tests\Fixtures\TestContentEntity;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\TestCase;

/**
 * Base test class for SubjectEntityFactory integration tests.
 *
 * Contains shared tests that work identically across PHPUnit and Prophecy
 * factory implementations.
 */
abstract class SubjectEntityFactoryTestBase extends TestCase {

  /**
   * The subject entity factory.
   */
  protected SubjectEntityFactory $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = SubjectEntityFactory::fromTest($this);
    $this->factory->installContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->factory->uninstallContainer();
    parent::tearDown();
  }

  /**
   * Tests creating a test entity with minimal values.
   */
  public function testCreateMinimalEntity(): void {
    $entity = $this->factory->create(TestContentEntity::class, [
      'id' => 1,
      'type' => 'test_bundle',
    ]);

    $this->assertInstanceOf(TestContentEntity::class, $entity);
    $this->assertSame('test_entity', $entity->getEntityTypeId());
  }

  /**
   * Tests creating a Node entity with values.
   */
  public function testCreateNodeEntity(): void {
    $entity = $this->factory->create(Node::class, [
      'nid' => 42,
      'type' => 'article',
      'title' => 'Test Article',
    ]);

    $this->assertInstanceOf(Node::class, $entity);
    $this->assertSame('node', $entity->getEntityTypeId());
    $this->assertSame('article', $entity->bundle());
  }

  /**
   * Tests that field values are accessible via field doubles.
   */
  public function testFieldValuesAccessible(): void {
    $entity = $this->factory->create(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test Title',
      'status' => 1,
    ]);

    // Field values should be accessible through field doubles.
    $this->assertSame('Test Title', $entity->get('title')->value);
    $this->assertSame(1, $entity->get('status')->value);
  }

  /**
   * Tests entity reference field with entity double as target.
   */
  public function testEntityReferenceField(): void {
    // Create an author double using the factory.
    $author = $this->factory->getDoubleFactory()->create(
      EntityDoubleDefinitionBuilder::create('user')
        ->id(99)
        ->label('Test Author')
        ->build()
    );

    $entity = $this->factory->create(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test',
      'uid' => $author,
    ]);

    // Entity reference should return the double.
    $this->assertSame($author, $entity->get('uid')->entity);
    $this->assertEquals(99, $entity->get('uid')->target_id);
  }

  /**
   * Tests multi-value field.
   */
  public function testMultiValueField(): void {
    $entity = $this->factory->create(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test',
      'field_tags' => [
        ['target_id' => 1],
        ['target_id' => 2],
        ['target_id' => 3],
      ],
    ]);

    // Test accessing individual items by delta.
    $fieldTags = $entity->get('field_tags');
    $item0 = $fieldTags->get(0);
    $item1 = $fieldTags->get(1);
    $item2 = $fieldTags->get(2);
    $this->assertNotNull($item0);
    $this->assertNotNull($item1);
    $this->assertNotNull($item2);
    // @phpstan-ignore property.notFound
    $this->assertSame(1, $item0->target_id);
    // @phpstan-ignore property.notFound
    $this->assertSame(2, $item1->target_id);
    // @phpstan-ignore property.notFound
    $this->assertSame(3, $item2->target_id);
  }

  /**
   * Tests creating multiple entities of the same type.
   */
  public function testMultipleEntities(): void {
    $entity1 = $this->factory->create(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'First',
    ]);

    $entity2 = $this->factory->create(Node::class, [
      'nid' => 2,
      'type' => 'page',
      'title' => 'Second',
    ]);

    $this->assertSame('First', $entity1->get('title')->value);
    $this->assertSame('Second', $entity2->get('title')->value);
    $this->assertSame('article', $entity1->bundle());
    $this->assertSame('page', $entity2->bundle());
  }

  /**
   * Tests property-style field access.
   */
  public function testPropertyStyleFieldAccess(): void {
    $entity = $this->factory->create(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Property Access Test',
    ]);

    // Magic __get should work for field access.
    $this->assertSame('Property Access Test', $entity->title->value);
  }

}
