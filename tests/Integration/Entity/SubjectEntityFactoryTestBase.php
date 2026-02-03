<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Entity;

use Deuteros\Double\EntityDoubleDefinitionBuilder;
use Deuteros\Entity\SubjectEntityTestBase;
use Deuteros\Tests\Fixtures\FinalContentEntity;
use Deuteros\Tests\Fixtures\TestConfigEntity;
use Deuteros\Tests\Fixtures\TestContentEntity;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Base test class for SubjectEntityFactory integration tests.
 *
 * Contains shared tests that work identically across PHPUnit and Prophecy
 * factory implementations.
 */
abstract class SubjectEntityFactoryTestBase extends SubjectEntityTestBase {

  /**
   * Tests creating a test entity with minimal values.
   */
  public function testCreateMinimalEntity(): void {
    $entity = $this->createEntity(TestContentEntity::class, [
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
    $entity = $this->createEntity(Node::class, [
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
    $entity = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test Title',
      'status' => 1,
    ]);
    assert($entity instanceof Node);

    // Field values should be accessible through field doubles.
    $this->assertSame('Test Title', $entity->get('title')->value);
    $this->assertSame(1, $entity->get('status')->value);
  }

  /**
   * Tests entity reference field with entity double as target.
   */
  public function testEntityReferenceField(): void {
    // Create an author double using the factory.
    $author = $this->getDoubleFactory()->create(
      EntityDoubleDefinitionBuilder::create('user')
        ->id(99)
        ->label('Test Author')
        ->build()
    );

    $entity = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test',
      'uid' => $author,
    ]);
    assert($entity instanceof Node);

    // Entity reference should return the double.
    $this->assertSame($author, $entity->get('uid')->entity);
    $this->assertEquals(99, $entity->get('uid')->target_id);
  }

  /**
   * Tests multi-value field.
   */
  public function testMultiValueField(): void {
    $entity = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test',
      'field_tags' => [
        ['target_id' => 1],
        ['target_id' => 2],
        ['target_id' => 3],
      ],
    ]);
    assert($entity instanceof Node);

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
    $entity1 = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'First',
    ]);
    assert($entity1 instanceof Node);

    $entity2 = $this->createEntity(Node::class, [
      'nid' => 2,
      'type' => 'page',
      'title' => 'Second',
    ]);
    assert($entity2 instanceof Node);

    $this->assertSame('First', $entity1->get('title')->value);
    $this->assertSame('Second', $entity2->get('title')->value);
    $this->assertSame('article', $entity1->bundle());
    $this->assertSame('page', $entity2->bundle());
  }

  /**
   * Tests property-style field access.
   */
  public function testPropertyStyleFieldAccess(): void {
    $entity = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Property Access Test',
    ]);
    assert($entity instanceof Node);

    // Magic __get should work for field access.
    $this->assertSame('Property Access Test', $entity->title->value);
  }

  /**
   * Tests creating a config entity with minimal values.
   */
  public function testCreateConfigEntityMinimal(): void {
    $entity = $this->createEntity(TestConfigEntity::class, [
      'id' => 'test_config_id',
    ]);
    assert($entity instanceof TestConfigEntity);

    $this->assertSame('test_config', $entity->getEntityTypeId());
    $this->assertSame('test_config_id', $entity->id());
  }

  /**
   * Tests creating a config entity with full values.
   */
  public function testCreateConfigEntityWithValues(): void {
    $entity = $this->createEntity(TestConfigEntity::class, [
      'id' => 'my_config',
      'label' => 'My Configuration',
      'uuid' => 'test-uuid-1234',
      'status' => TRUE,
      'description' => 'A test description',
      'weight' => 5,
    ]);
    assert($entity instanceof TestConfigEntity);

    $this->assertSame('my_config', $entity->id());
    $this->assertSame('My Configuration', $entity->label());
    $this->assertTrue($entity->status());
    $this->assertSame('A test description', $entity->description);
    $this->assertSame(5, $entity->weight);
  }

  /**
   * Tests config entity disabled status.
   */
  public function testConfigEntityDisabledStatus(): void {
    $entity = $this->createEntity(TestConfigEntity::class, [
      'id' => 'disabled_config',
      'label' => 'Disabled Config',
      'status' => FALSE,
    ]);
    assert($entity instanceof TestConfigEntity);

    $this->assertFalse($entity->status());
  }

  /**
   * Tests creating multiple config entities.
   */
  public function testMultipleConfigEntities(): void {
    $entity1 = $this->createEntity(TestConfigEntity::class, [
      'id' => 'config_1',
      'label' => 'First Config',
    ]);
    assert($entity1 instanceof TestConfigEntity);

    $entity2 = $this->createEntity(TestConfigEntity::class, [
      'id' => 'config_2',
      'label' => 'Second Config',
    ]);
    assert($entity2 instanceof TestConfigEntity);

    $this->assertSame('config_1', $entity1->id());
    $this->assertSame('config_2', $entity2->id());
    $this->assertSame('First Config', $entity1->label());
    $this->assertSame('Second Config', $entity2->label());
  }

  /**
   * Tests mixing content and config entities in same test.
   */
  public function testMixedEntityTypes(): void {
    $contentEntity = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test Node',
    ]);
    assert($contentEntity instanceof Node);

    $configEntity = $this->createEntity(TestConfigEntity::class, [
      'id' => 'test_config',
      'label' => 'Test Config',
    ]);
    assert($configEntity instanceof TestConfigEntity);

    $this->assertSame('node', $contentEntity->getEntityTypeId());
    $this->assertSame('test_config', $configEntity->getEntityTypeId());
    $this->assertSame('Test Node', $contentEntity->get('title')->value);
    $this->assertSame('Test Config', $configEntity->label());
  }

  /**
   * Tests hasField() returns correct boolean for defined fields.
   */
  public function testHasFieldReturnsCorrectValue(): void {
    $entity = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test',
      'body' => 'Body content',
    ]);
    assert($entity instanceof Node);

    // Defined fields should return true.
    $this->assertTrue($entity->hasField('title'));
    $this->assertTrue($entity->hasField('body'));
    $this->assertTrue($entity->hasField('nid'));
    $this->assertTrue($entity->hasField('type'));

    // Undefined fields should return false.
    $this->assertFalse($entity->hasField('nonexistent_field'));
    $this->assertFalse($entity->hasField('field_that_does_not_exist'));
  }

  /**
   * Tests getFieldDefinition() returns definition for defined fields.
   */
  public function testGetFieldDefinitionReturnsDefinition(): void {
    $entity = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test',
    ]);
    assert($entity instanceof Node);

    // Defined fields should return a field definition.
    $definition = $entity->getFieldDefinition('title');
    $this->assertNotNull($definition);
    $this->assertSame('title', $definition->getName());

    // Undefined fields should return null.
    $this->assertNull($entity->getFieldDefinition('nonexistent_field'));
  }

  /**
   * Tests toUrl() returns a Url instance on subject entities.
   */
  public function testToUrlReturnsUrlInstance(): void {
    $entity = $this->createEntity(Node::class, [
      'nid' => 42,
      'type' => 'article',
      'title' => 'Test Article',
    ], '/node/42');

    // Critical: Entity must still pass instanceof check after URL wrapping.
    $this->assertInstanceOf(Node::class, $entity);

    $url = $entity->toUrl();

    // Critical: The URL must pass instanceof check.
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(Url::class, $url);
    $this->assertSame('/node/42', $url->toString());
  }

  /**
   * Tests toUrl() with final entity class throws clear exception.
   *
   * PHP does not allow extending final classes, so URL stubs cannot pass
   * instanceof checks for the entity class. We throw an exception with
   * a clear message explaining the limitation.
   */
  public function testToUrlWithFinalEntityClassThrows(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("Cannot use URL parameter with final entity class");
    $this->expectExceptionMessage("Remove the 'final' keyword");

    $this->createEntity(FinalContentEntity::class, [
      'id' => 1,
      'type' => 'test_bundle',
    ], '/final/1');
  }

  /**
   * Tests final entity class works without URL parameter.
   */
  public function testFinalEntityClassWithoutUrl(): void {
    $entity = $this->createEntity(FinalContentEntity::class, [
      'id' => 1,
      'type' => 'test_bundle',
    ]);

    // Entity should pass instanceof check when no URL wrapping occurs.
    $this->assertInstanceOf(FinalContentEntity::class, $entity);
    $this->assertSame('final_entity', $entity->getEntityTypeId());
  }

  /**
   * Tests toUrl() with GeneratedUrl returns proper instance.
   */
  public function testToUrlWithGeneratedUrl(): void {
    $entity = $this->createEntity(Node::class, [
      'nid' => 42,
      'type' => 'article',
      'title' => 'Test Article',
    ], '/node/42');

    $url = $entity->toUrl();
    $generatedUrl = $url->toString(TRUE);

    // The GeneratedUrl must pass instanceof check.
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(GeneratedUrl::class, $generatedUrl);
    $this->assertSame('/node/42', $generatedUrl->getGeneratedUrl());
  }

  /**
   * Tests toUrl() respects the "absolute" option.
   */
  public function testToUrlRespectsAbsoluteOption(): void {
    $entity = $this->createEntity(Node::class, [
      'nid' => 42,
      'type' => 'article',
      'title' => 'Test Article',
    ], '/node/42');

    // Relative URL (default).
    $relativeUrl = $entity->toUrl();
    $this->assertSame('/node/42', $relativeUrl->toString());

    // Absolute URL.
    $absoluteUrl = $entity->toUrl('canonical', ['absolute' => TRUE]);
    $this->assertSame('http://example.com/node/42', $absoluteUrl->toString());

    // Verify each call creates a new Url double.
    $this->assertNotSame($relativeUrl, $absoluteUrl);
  }

}
