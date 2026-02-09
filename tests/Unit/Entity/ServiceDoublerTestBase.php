<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Entity;

use Deuteros\Entity\ServiceDoublerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
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
