<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Prophecy;

use Deuteros\Common\EntityDefinitionBuilder;
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
   * willImplement().
   */
  public function testInterfaceComposition(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->interface(FieldableEntityInterface::class)
        ->interface(EntityChangedInterface::class)
        ->methodOverride('getChangedTime', fn() => 1704067200)
        ->methodOverride('setChangedTime', fn() => throw new \LogicException('Read-only'))
        ->build()
    );

    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertInstanceOf(FieldableEntityInterface::class, $entity);
    $this->assertInstanceOf(EntityChangedInterface::class, $entity);
    $this->assertSame(1704067200, $entity->getChangedTime());
  }

}
