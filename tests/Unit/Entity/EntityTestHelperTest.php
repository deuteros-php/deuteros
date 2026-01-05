<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Entity;

use Deuteros\Tests\Fixtures\EntityWithoutAttribute;
use Drupal\node\Entity\Node;
use Deuteros\Entity\EntityTestHelper;
use Drupal\Core\Entity\ContentEntityBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EntityTestHelper for unit testing Drupal entity objects.
 */
#[CoversClass(EntityTestHelper::class)]
#[Group('deuteros')]
class EntityTestHelperTest extends TestCase {

  /**
   * The test helper.
   */
  private EntityTestHelper $helper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->helper = EntityTestHelper::fromTest($this);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->helper->uninstallContainer();
    parent::tearDown();
  }

  /**
   * Tests that ::fromTest creates a helper instance.
   */
  public function testFromTestCreatesHelper(): void {
    // Verify fromTest() returns the expected type at runtime.
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(EntityTestHelper::class, $this->helper);
  }

  /**
   * Tests that ::createEntity throws when container not installed.
   */
  public function testCreateEntityThrowsWithoutContainer(): void {
    // Create fresh helper without calling installContainer.
    $helper = EntityTestHelper::fromTest($this);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Container not installed');
    $this->expectExceptionMessage('installContainer()');

    // Use Node class from Drupal core for this test.
    $helper->createEntity(Node::class, []);
  }

  /**
   * Tests that ::installContainer throws when called twice.
   */
  public function testInstallContainerThrowsTwice(): void {
    $this->helper->installContainer();

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Container already installed');
    $this->expectExceptionMessage('uninstallContainer()');

    $this->helper->installContainer();
  }

  /**
   * Tests that ::createEntity throws for non-ContentEntityBase classes.
   */
  public function testCreateEntityThrowsForInvalidClass(): void {
    $this->helper->installContainer();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('must be a subclass of');
    $this->expectExceptionMessage(ContentEntityBase::class);

    // stdClass is not a ContentEntityBase subclass.
    $this->helper->createEntity(\stdClass::class, []);
  }

  /**
   * Tests that ::createEntity throws for missing ContentEntityType attribute.
   */
  public function testCreateEntityThrowsForMissingAttribute(): void {
    $this->helper->installContainer();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('does not have a #[ContentEntityType] attribute');

    // Use a class that extends ContentEntityBase but lacks the attribute.
    $this->helper->createEntity(EntityWithoutAttribute::class, []);
  }

  /**
   * Tests that ::getDoubleFactory returns the factory.
   */
  public function testGetDoubleFactoryReturnsFactory(): void {
    $factory = $this->helper->getDoubleFactory();
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

    $this->helper->installContainer();
    $this->helper->uninstallContainer();

    // Should not throw when called again.
    $this->helper->uninstallContainer();
  }

}
