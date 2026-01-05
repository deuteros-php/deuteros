<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Entity;

use Deuteros\Entity\SubjectEntityFactory;
use Deuteros\Tests\Fixtures\EntityWithoutAttribute;
use Deuteros\Tests\Fixtures\TestConfigEntityChild;
use Deuteros\Tests\Fixtures\TestContentEntityChild;
use Deuteros\Tests\Fixtures\TestContentEntityGrandchild;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityBase;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the SubjectEntityFactory for unit testing Drupal entity objects.
 */
#[CoversClass(SubjectEntityFactory::class)]
#[Group('deuteros')]
class SubjectEntityFactoryTest extends TestCase {

  /**
   * The subject entity factory.
   */
  private ?SubjectEntityFactory $factory = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Skip tests when Drupal core is not available (production mode).
    if (!class_exists(ContainerBuilder::class)) {
      $this->markTestSkipped('SubjectEntityFactory requires Drupal core.');
    }

    parent::setUp();
    $this->factory = SubjectEntityFactory::fromTest($this);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->factory?->uninstallContainer();
    parent::tearDown();
  }

  /**
   * Returns the factory, asserting it is initialized.
   *
   * @return \Deuteros\Entity\SubjectEntityFactory
   *   The factory.
   */
  private function factory(): SubjectEntityFactory {
    assert($this->factory !== NULL);
    return $this->factory;
  }

  /**
   * Tests that ::create throws when container not installed.
   */
  public function testCreateThrowsWithoutContainer(): void {
    // Create fresh factory without calling installContainer.
    $factory = SubjectEntityFactory::fromTest($this);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Container not installed');
    $this->expectExceptionMessage('installContainer()');

    // Use Node class from Drupal core for this test.
    $factory->create(Node::class, []);
  }

  /**
   * Tests that ::installContainer throws when called twice.
   */
  public function testInstallContainerThrowsTwice(): void {
    $this->factory()->installContainer();

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Container already installed');
    $this->expectExceptionMessage('uninstallContainer()');

    $this->factory()->installContainer();
  }

  /**
   * Tests that ::create throws for non-EntityBase classes.
   */
  public function testCreateThrowsForInvalidClass(): void {
    $this->factory()->installContainer();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('must be a subclass of');
    $this->expectExceptionMessage(EntityBase::class);

    // stdClass is not an EntityBase subclass.
    $this->factory()->create(\stdClass::class, []);
  }

  /**
   * Tests that ::create throws for missing entity type attribute.
   */
  public function testCreateThrowsForMissingAttribute(): void {
    $this->factory()->installContainer();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('and its parent classes');
    $this->expectExceptionMessage('#[ContentEntityType] or #[ConfigEntityType]');

    // Use a class that extends ContentEntityBase but lacks the attribute.
    $this->factory()->create(EntityWithoutAttribute::class, []);
  }

  /**
   * Tests that ::create works with a subclass inheriting the attribute.
   */
  public function testCreateWithInheritedAttribute(): void {
    $this->factory()->installContainer();

    $entity = $this->factory()->create(TestContentEntityChild::class, [
      'id' => 1,
      'type' => 'test_bundle',
    ]);

    $this->assertInstanceOf(TestContentEntityChild::class, $entity);
    $this->assertSame('test_entity', $entity->getEntityTypeId());
  }

  /**
   * Tests that ::create works with deep class hierarchy.
   */
  public function testCreateWithDeepInheritedAttribute(): void {
    $this->factory()->installContainer();

    $entity = $this->factory()->create(TestContentEntityGrandchild::class, [
      'id' => 1,
      'type' => 'test_bundle',
    ]);

    $this->assertInstanceOf(TestContentEntityGrandchild::class, $entity);
    $this->assertSame('test_entity', $entity->getEntityTypeId());
  }

  /**
   * Tests that ::create works with a config class hierarchy.
   */
  public function testConfigWithInheritedAttribute(): void {
    $this->factory()->installContainer();

    $entity = $this->factory()->create(TestConfigEntityChild::class, [
      'id' => 1,
    ]);

    $this->assertInstanceOf(TestConfigEntityChild::class, $entity);
    $this->assertSame('test_config', $entity->getEntityTypeId());
  }

  /**
   * Tests that ::getDoubleFactory returns the factory.
   */
  public function testGetDoubleFactoryReturnsFactory(): void {
    $factory = $this->factory()->getDoubleFactory();
    // Verify factory is returned at runtime.
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertNotNull($factory);
  }

  /**
   * Tests that ::uninstallContainer is safe to call multiple times.
   */
  public function testUninstallContainerIdempotent(): void {
    // No assertions - test passes if no exception is thrown.
    $this->expectNotToPerformAssertions();

    $this->factory()->installContainer();
    $this->factory()->uninstallContainer();

    // Should not throw when called again.
    $this->factory()->uninstallContainer();
  }

}
