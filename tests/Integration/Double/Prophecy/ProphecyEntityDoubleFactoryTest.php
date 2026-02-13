<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Double\Prophecy;

use Deuteros\Double\EntityDoubleDefinitionBuilder;
use Deuteros\Double\Prophecy\ProphecyEntityDoubleFactory;
use Deuteros\Tests\Integration\EntityDoubleFactoryTestBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

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
  protected function getClassName(): string {
    return ProphecyEntityDoubleFactory::class;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityFromRawDouble(object $double): EntityInterface {
    assert($double instanceof ObjectProphecy);
    $entity = $double->reveal();
    assert($entity instanceof EntityInterface);
    return $entity;
  }

  /**
   * Checks that test and the factory share the same prophet.
   */
  public function testProphet(): void {
    $prophetProperty = new \ReflectionProperty($this->factory, 'prophet');
    $this->assertSame($this->getProphet(), $prophetProperty->getValue($this->factory));
  }

  /**
   * Tests ::createEntityDouble returns an "ObjectProphecy".
   */
  public function testCreateEntityDoubleReturnsObjectProphecy(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->build();

    $double = $this->factory->createEntityDouble($definition);

    $this->assertInstanceOf(ObjectProphecy::class, $double);
  }

  /**
   * Tests custom stubbing on a raw Prophecy entity double.
   */
  public function testCreateEntityDoubleCustomStubbing(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->interface(FieldableEntityInterface::class)
      ->interface(EntityChangedInterface::class)
      ->build();

    $double = $this->factory->createEntityDouble($definition);
    assert($double instanceof ObjectProphecy);

    $double->getChangedTime()->willReturn(1704067200);

    $entity = $double->reveal();
    assert($entity instanceof EntityChangedInterface);
    $this->assertSame(1704067200, $entity->getChangedTime());
  }

  /**
   * Tests custom expectations on a raw Prophecy entity double.
   */
  public function testCreateEntityDoubleCustomExpectation(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->interface(FieldableEntityInterface::class)
      ->interface(EntityChangedInterface::class)
      ->build();

    $double = $this->factory->createEntityDouble($definition);
    assert($double instanceof ObjectProphecy);

    $double->getChangedTime()->willReturn(1704067200)->shouldBeCalled();

    $entity = $double->reveal();
    assert($entity instanceof EntityChangedInterface);
    $this->assertSame(1704067200, $entity->getChangedTime());
  }

}
