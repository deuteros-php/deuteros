<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\PhpUnit;

use Deuteros\Common\EntityDoubleFactoryInterface;
use Deuteros\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Tests\Integration\EntityDoubleFactoryTestBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
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
   * Tests implementing multiple interfaces with method overrides.
   *
   * PHPUnit can handle multiple interfaces that share a common parent via
   * dynamically generated combined interfaces.
   */
  public function testInterfaceComposition(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'interfaces' => [
        FieldableEntityInterface::class,
        EntityChangedInterface::class,
      ],
      'method_overrides' => [
        'getChangedTime' => fn() => 1704067200,
        'setChangedTime' => fn() => throw new \LogicException('Read-only'),
      ],
    ]);

    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertInstanceOf(FieldableEntityInterface::class, $entity);
    $this->assertInstanceOf(EntityChangedInterface::class, $entity);
    $this->assertSame(1704067200, $entity->getChangedTime());
  }

}
