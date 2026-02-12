<?php

declare(strict_types=1);

namespace Deuteros\Entity;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Deuteros\Double\EntityDoubleFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base test class for unit testing Drupal entity objects.
 *
 * Provides automatic setup and teardown of "SubjectEntityFactory", simplifying
 * test class boilerplate. Extend this class and use ::createEntity to create
 * subject entities with field doubles.
 *
 * @example
 * ```php
 * class MyNodeTest extends SubjectEntityTestBase {
 *
 *   public function testNodeCreation(): void {
 *     $node = $this->createEntity(Node::class, [
 *       'nid' => 1,
 *       'type' => 'article',
 *       'title' => 'Test Article',
 *     ]);
 *
 *     $this->assertInstanceOf(Node::class, $node);
 *     $this->assertEquals('Test Article', $node->get('title')->value);
 *   }
 *
 * }
 * ```
 */
abstract class SubjectEntityTestBase extends TestCase {

  /**
   * The subject entity factory.
   */
  protected ?SubjectEntityFactory $subjectEntityFactory = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Skip tests when Drupal core is not available (production mode).
    if (!class_exists(ContainerBuilder::class)) {
      $this->markTestSkipped('SubjectEntityFactory requires Drupal core.');
    }

    parent::setUp();
    $this->subjectEntityFactory = SubjectEntityFactory::fromTest($this);
    $this->subjectEntityFactory->installContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->subjectEntityFactory?->uninstallContainer();
    parent::tearDown();
  }

  /**
   * Creates a subject entity instance.
   *
   * Convenience method that delegates to the factory.
   *
   * @param class-string $entityClass
   *   The entity class to instantiate.
   * @param array<string, mixed> $values
   *   Field/property values.
   * @param array<string, string> $fieldTypes
   *   Optional map of field name to field type (e.g.,
   *   `['field_ref' => 'entity_reference']`). Fields not listed
   *   default to "string".
   *
   * @return \Drupal\Core\Entity\EntityBase
   *   The created entity instance.
   */
  protected function createEntity(string $entityClass, array $values = [], array $fieldTypes = []): EntityInterface {
    return $this->subjectEntityFactory()->create($entityClass, $values, $fieldTypes);
  }

  /**
   * Creates a subject entity instance with an auto-incremented ID.
   *
   * Convenience method that delegates to the factory.
   *
   * @param class-string $entityClass
   *   The entity class to instantiate.
   * @param array<string, mixed> $values
   *   Field/property values. The ID key will be set automatically.
   * @param array<string, string> $fieldTypes
   *   Optional map of field name to field type (e.g.,
   *   `['field_ref' => 'entity_reference']`). Fields not listed
   *   default to "string".
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity instance with an assigned ID.
   */
  protected function createEntityWithId(string $entityClass, array $values = [], array $fieldTypes = []): EntityInterface {
    return $this->subjectEntityFactory()->createWithId($entityClass, $values, $fieldTypes);
  }

  /**
   * Gets the entity double factory.
   *
   * Useful for creating entity doubles to use as entity references.
   *
   * @return \Deuteros\Double\EntityDoubleFactoryInterface
   *   The entity double factory.
   */
  protected function getDoubleFactory(): EntityDoubleFactoryInterface {
    return $this->subjectEntityFactory()->getDoubleFactory();
  }

  /**
   * Gets the current container managed by the factory.
   *
   * Useful for adding custom service doubles. Services added this way will be
   * preserved when the container is rebuilt (e.g., when new entity types are
   * registered via ::createEntity).
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The current container.
   */
  protected function getContainer(): ContainerInterface {
    return $this->subjectEntityFactory()->getContainer();
  }

  /**
   * Returns the factory, asserting it is initialized.
   *
   * @return \Deuteros\Entity\SubjectEntityFactory
   *   The factory.
   */
  private function subjectEntityFactory(): SubjectEntityFactory {
    assert($this->subjectEntityFactory !== NULL);
    return $this->subjectEntityFactory;
  }

}
