<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Entity;

use Deuteros\Double\EntityDoubleDefinitionBuilder;
use Deuteros\Entity\SubjectEntityFactory;
use Deuteros\Entity\SubjectEntityTestBase;
use Deuteros\Tests\Fixtures\TestConfigEntity;
use Deuteros\Tests\Fixtures\TestContentEntity;
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
   * Tests that custom services are preserved when entity types are registered.
   *
   * When creating entities of different types, the container is rebuilt to
   * register new entity type configurations. Custom services added by the test
   * should be preserved across these rebuilds.
   */
  public function testCustomServicesPreservedAcrossEntityTypeRegistration(): void {
    // Get the container and add a custom service.
    assert($this->subjectEntityFactory !== NULL);
    $container = $this->subjectEntityFactory->getContainer();
    $customService = new \stdClass();
    $customService->value = 'test_value';
    $container->set('my_custom_service', $customService);

    // Verify the service is accessible.
    $this->assertSame($customService, $container->get('my_custom_service'));

    // Create a Node entity - this registers the 'node' entity type.
    $node = $this->createEntity(Node::class, [
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test Node',
    ]);
    $this->assertInstanceOf(Node::class, $node);

    // The custom service should still be accessible after container rebuild.
    $containerAfterNode = $this->subjectEntityFactory->getContainer();
    $retrievedService = $containerAfterNode->get('my_custom_service');
    $this->assertSame($customService, $retrievedService);

    // Create a TestConfigEntity - this registers a different entity type.
    $config = $this->createEntity(TestConfigEntity::class, [
      'id' => 'test_config',
      'label' => 'Test Config',
    ]);
    $this->assertInstanceOf(TestConfigEntity::class, $config);

    // The custom service should still be accessible after second rebuild.
    $containerAfterConfig = $this->subjectEntityFactory->getContainer();
    $this->assertSame($customService, $containerAfterConfig->get('my_custom_service'));
    $this->assertSame('test_value', $containerAfterConfig->get('my_custom_service')->value);
  }

  /**
   * Tests getContainer() throws exception when container not installed.
   */
  public function testGetContainerThrowsWhenNotInstalled(): void {
    // Create a fresh factory without installing the container.
    $factory = SubjectEntityFactory::fromTest($this);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Container not installed. Call installContainer() before getContainer().');
    $factory->getContainer();
  }

}
