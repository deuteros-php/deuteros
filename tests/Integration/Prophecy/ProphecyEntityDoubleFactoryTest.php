<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Prophecy;

use Deuteros\Common\EntityDoubleFactoryInterface;
use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use Deuteros\Tests\Integration\EntityDoubleFactoryTestBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Integration tests for the Prophecy ProphecyEntityDoubleFactory.
 */
#[CoversClass(ProphecyEntityDoubleFactory::class)]
#[Group('deuteros')]
class ProphecyEntityDoubleFactoryTest extends EntityDoubleFactoryTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function createFactory(): EntityDoubleFactoryInterface {
    return new ProphecyEntityDoubleFactory($this->getProphet());
  }

  /**
   * Tests implementing multiple interfaces with method overrides.
   *
   * Prophecy can handle multiple interfaces that share a common parent via
   * willImplement(), unlike PHPUnit which cannot mock interface intersections
   * with duplicate methods.
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
