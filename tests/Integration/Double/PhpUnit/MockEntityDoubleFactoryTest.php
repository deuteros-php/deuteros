<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Double\PhpUnit;

use Deuteros\Double\EntityDoubleDefinitionBuilder;
use Deuteros\Double\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Tests\Integration\EntityDoubleFactoryTestBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Integration tests for the PHPUnit MockEntityDoubleFactory.
 */
#[CoversClass(MockEntityDoubleFactory::class)]
#[Group('deuteros')]
class MockEntityDoubleFactoryTest extends EntityDoubleFactoryTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getClassName(): string {
    return MockEntityDoubleFactory::class;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityFromRawDouble(object $double): EntityInterface {
    assert($double instanceof EntityInterface);
    return $double;
  }

  /**
   * Tests ::createEntityDouble returns a "MockObject".
   */
  public function testCreateEntityDoubleReturnsMockObject(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->build();

    $double = $this->factory->createEntityDouble($definition);

    $this->assertInstanceOf(MockObject::class, $double);
    $this->assertInstanceOf(EntityInterface::class, $double);
  }

  /**
   * Tests custom stubbing on a raw PHPUnit entity double.
   */
  public function testCreateEntityDoubleCustomStubbing(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->interface(FieldableEntityInterface::class)
      ->interface(EntityChangedInterface::class)
      ->build();

    $double = $this->factory->createEntityDouble($definition);
    assert($double instanceof MockObject);
    assert($double instanceof EntityChangedInterface);

    $double->method('getChangedTime')->willReturn(1704067200);

    $this->assertSame(1704067200, $double->getChangedTime());
  }

  /**
   * Tests custom expectations on a raw PHPUnit entity double.
   */
  public function testCreateEntityDoubleCustomExpectation(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->interface(FieldableEntityInterface::class)
      ->interface(EntityChangedInterface::class)
      ->build();

    $double = $this->factory->createEntityDouble($definition);
    assert($double instanceof MockObject);
    assert($double instanceof EntityChangedInterface);

    $double->expects($this->once())
      ->method('getChangedTime')
      ->willReturn(1704067200);

    $this->assertSame(1704067200, $double->getChangedTime());
  }

}
