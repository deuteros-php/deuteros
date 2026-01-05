<?php

declare(strict_types=1);

namespace Deuteros\Entity;

use Deuteros\Common\EntityDoubleFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use PHPUnit\Framework\TestCase;

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
  protected SubjectEntityFactory $subjectEntityFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->subjectEntityFactory = SubjectEntityFactory::fromTest($this);
    $this->subjectEntityFactory->installContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->subjectEntityFactory->uninstallContainer();
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
   *
   * @return \Drupal\Core\Entity\EntityBase
   *   The created entity instance.
   */
  protected function createEntity(string $entityClass, array $values = []): EntityInterface {
    return $this->subjectEntityFactory->create($entityClass, $values);
  }

  /**
   * Gets the entity double factory.
   *
   * Useful for creating entity doubles to use as entity references.
   *
   * @return \Deuteros\Common\EntityDoubleFactoryInterface
   *   The entity double factory.
   */
  protected function getDoubleFactory(): EntityDoubleFactoryInterface {
    return $this->subjectEntityFactory->getDoubleFactory();
  }

}
