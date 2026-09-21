<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Entity;

use Deuteros\Entity\ServiceDoublerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Base test class for service doubler unit tests.
 *
 * Contains shared tests that verify service doubler behavior identically
 * across PHPUnit and Prophecy implementations.
 */
#[Group('deuteros')]
abstract class ServiceDoublerTestBase extends TestCase {

  /**
   * The service doubler under test.
   */
  protected ServiceDoublerInterface $serviceDoubler;

  /**
   * Creates the service doubler for the adapter being tested.
   *
   * @return \Deuteros\Entity\ServiceDoublerInterface
   *   The service doubler.
   */
  abstract protected function createServiceDoubler(): ServiceDoublerInterface;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Skip tests when Drupal core is not available (production mode).
    if (!class_exists(ContainerBuilder::class)) {
      $this->markTestSkipped('Service doubler tests require Drupal core.');
    }

    parent::setUp();
    $this->serviceDoubler = $this->createServiceDoubler();
  }

  /**
   * Tests that field definition mock returns the field name.
   */
  public function testFieldDefinitionMockName(): void {
    $definition = $this->serviceDoubler->createFieldDefinitionMock('title');
    $this->assertSame('title', $definition->getName());
  }

  /**
   * Tests field definition mock storage definition.
   *
   * Verifies that ::getFieldStorageDefinition returns a
   * "FieldStorageDefinitionInterface" with ::getMainPropertyName
   * returning "value".
   */
  public function testFieldDefinitionMockStorageDefinition(): void {
    $definition = $this->serviceDoubler->createFieldDefinitionMock('title');
    $storage = $definition->getFieldStorageDefinition();

    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(FieldStorageDefinitionInterface::class, $storage);
    $this->assertSame('value', $storage->getMainPropertyName());
  }

  /**
   * Tests that a rebuild keeps a default service the test replaced.
   */
  public function testBuildContainerKeepsReplacedDefaultService(): void {
    $container = $this->serviceDoubler->buildContainer([]);
    $fieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $container->set('entity_field.manager', $fieldManager);

    $rebuilt = $this->serviceDoubler->buildContainer([], $container);

    $this->assertSame($fieldManager, $rebuilt->get('entity_field.manager'));
  }

  /**
   * Tests that a rebuild replaces the services describing the entity types.
   */
  public function testBuildContainerReplacesEntityTypeServices(): void {
    $container = $this->serviceDoubler->buildContainer([]);
    $entityTypeManager = $container->get('entity_type.manager');
    $bundleInfo = $container->get('entity_type.bundle.info');

    $rebuilt = $this->serviceDoubler->buildContainer([
      'node' => ['class' => Node::class, 'keys' => ['id' => 'nid', 'bundle' => 'type']],
    ], $container);

    $this->assertNotSame($entityTypeManager, $rebuilt->get('entity_type.manager'));
    $this->assertNotSame($bundleInfo, $rebuilt->get('entity_type.bundle.info'));
    $this->assertSame('node', $rebuilt->get('entity_type.manager')->getDefinition('node')->id());
  }

  /**
   * Tests that the "uuid" service hands out a different UUID on each call.
   *
   * The service is created anew on every container build, so the UUIDs of
   * two builds must not repeat either.
   */
  public function testUuidServiceGeneratesUniqueUuids(): void {
    $first = $this->serviceDoubler->buildContainer([])->get('uuid');
    $second = $this->serviceDoubler->buildContainer([])->get('uuid');

    $uuids = [$first->generate(), $first->generate(), $second->generate()];

    $this->assertCount(3, array_unique($uuids));
  }

  /**
   * Tests that field definition mock is not translatable.
   */
  public function testFieldDefinitionMockIsNotTranslatable(): void {
    $definition = $this->serviceDoubler->createFieldDefinitionMock('title');
    $this->assertFalse($definition->isTranslatable());
  }

  /**
   * Tests ::getFieldMapByFieldType on entity field manager mock.
   */
  public function testEntityFieldManagerGetFieldMapByFieldType(): void {
    $container = $this->serviceDoubler->buildContainer([]);
    $fieldManager = $container->get('entity_field.manager');

    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(EntityFieldManagerInterface::class, $fieldManager);
    $this->assertSame([], $fieldManager->getFieldMapByFieldType('string'));
  }

}
