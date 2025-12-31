<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\PhpUnit;

use Deuteros\Common\EntityDoubleFactoryInterface;
use Deuteros\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Tests\Integration\EntityDoubleFactoryTestBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for the PHPUnit MockEntityDoubleFactory.
 */
#[CoversClass(MockEntityDoubleFactory::class)]
#[Group('deuteros')]
class MockEntityDoubleFactoryTest extends EntityDoubleFactoryTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createFactory(): EntityDoubleFactoryInterface {
    return new MockEntityDoubleFactory($this);
  }

  /**
   * Tests interface composition with method overrides.
   *
   * Note: PHPUnit cannot mock intersection of interfaces that share a common
   * parent (they have duplicate methods). When FieldableEntityInterface and
   * EntityChangedInterface are both specified, only FieldableEntityInterface
   * is mocked. For full interface composition, use the Prophecy adapter.
   *
   * This test demonstrates that method overrides work even when the interface
   * that declares them is not directly mocked.
   */
  public function testInterfaceComposition(): void {
    // For PHPUnit: use only "EntityChangedInterface" (which extends
    // "EntityInterface") and configure field-related methods via overrides
    // if needed.
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'interfaces' => [
        EntityChangedInterface::class,
      ],
      'method_overrides' => [
        'getChangedTime' => fn() => 1704067200,
        'setChangedTime' => fn() => throw new \LogicException('Read-only'),
      ],
    ]);

    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertInstanceOf(EntityChangedInterface::class, $entity);
    $this->assertSame(1704067200, $entity->getChangedTime());
  }

}
