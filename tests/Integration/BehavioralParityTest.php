<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration;

use Deuteros\Common\EntityDefinition;
use Deuteros\Common\EntityDefinitionBuilder;
use Deuteros\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests that PHPUnit and Prophecy adapters have identical behavior.
 *
 * These tests verify the critical requirement that both adapters produce
 * the same outputs for the same inputs.
 */
#[Group('deuteros')]
class BehavioralParityTest extends TestCase {

  use ProphecyTrait;

  /**
   * The PhpUnit Mock double factory.
   *
   * @var \Deuteros\PhpUnit\MockEntityDoubleFactory
   */
  private MockEntityDoubleFactory $mockFactory;

  /**
   * The Prophecy double factory.
   *
   * @var \Deuteros\Prophecy\ProphecyEntityDoubleFactory
   */
  private ProphecyEntityDoubleFactory $prophecyFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->mockFactory = new MockEntityDoubleFactory($this);
    $this->prophecyFactory = new ProphecyEntityDoubleFactory($this->getProphet());
  }

  /**
   * Tests that both adapters return identical entity metadata.
   */
  public function testMetadataParity(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->id(42)
      ->uuid('test-uuid')
      ->label('Test Label')
      ->build();

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    $this->assertSame($mock->getEntityTypeId(), $prophecy->getEntityTypeId());
    $this->assertSame($mock->bundle(), $prophecy->bundle());
    $this->assertSame($mock->id(), $prophecy->id());
    $this->assertSame($mock->uuid(), $prophecy->uuid());
    $this->assertSame($mock->label(), $prophecy->label());
  }

  /**
   * Tests that both adapters return identical field values.
   */
  public function testFieldValueParity(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_text', 'Test Value')
      ->field('field_number', 123)
      ->field('field_ref', ['target_id' => 42])
      ->build();

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // Scalar field.
    $this->assertSame(
      $mock->get('field_text')->value,
      $prophecy->get('field_text')->value
    );

    // Number field.
    $this->assertSame(
      $mock->get('field_number')->value,
      $prophecy->get('field_number')->value
    );

    // Reference field.
    $this->assertSame(
      $mock->get('field_ref')->target_id,
      $prophecy->get('field_ref')->target_id
    );
  }

  /**
   * Tests that both adapters resolve callbacks identically.
   */
  public function testCallbackResolutionParity(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_dynamic', fn(array $context) => $context['value'] * 2)
      ->build();
    $context = ['value' => 21];

    $mock = $this->createMockDouble($definition, $context);
    $prophecy = $this->createProphecyDouble($definition, $context);

    $this->assertSame(42, $mock->get('field_dynamic')->value);
    $this->assertSame(42, $prophecy->get('field_dynamic')->value);
    $this->assertSame(
      $mock->get('field_dynamic')->value,
      $prophecy->get('field_dynamic')->value
    );
  }

  /**
   * Tests that both adapters handle multi-value fields identically.
   */
  public function testMultiValueFieldParity(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_tags', [
        ['target_id' => 1],
        ['target_id' => 2],
        ['target_id' => 3],
      ])
      ->build();

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // First item.
    $this->assertSame(
      $mock->get('field_tags')->first()->target_id,
      $prophecy->get('field_tags')->first()->target_id
    );

    // Delta access.
    for ($i = 0; $i < 3; $i++) {
      $this->assertSame(
        $mock->get('field_tags')->get($i)->target_id,
        $prophecy->get('field_tags')->get($i)->target_id
      );
    }

    // Out of range.
    $this->assertNull($mock->get('field_tags')->get(99));
    $this->assertNull($prophecy->get('field_tags')->get(99));
  }

  /**
   * Tests that both adapters handle method overrides identically.
   */
  public function testMethodOverrideParity(): void {
    $timestamp = 1704067200;
    $definition = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->interface(EntityChangedInterface::class)
      ->methodOverride('getChangedTime', fn(array $context) => $context['time'])
      ->methodOverride('setChangedTime', fn() => NULL)
      ->build();
    $context = ['time' => $timestamp];

    $mock = $this->createMockDouble($definition, $context);
    $prophecy = $this->createProphecyDouble($definition, $context);

    $this->assertSame($timestamp, $mock->getChangedTime());
    $this->assertSame($timestamp, $prophecy->getChangedTime());
    $this->assertSame(
      $mock->getChangedTime(),
      $prophecy->getChangedTime()
    );
  }

  /**
   * Tests that both adapters handle multiple overlapping interfaces.
   *
   * Both FieldableEntityInterface and EntityChangedInterface extend
   * EntityInterface. Both adapters should create doubles that implement all
   * specified interfaces.
   */
  public function testMultiInterfaceParity(): void {
    $timestamp = 1704067200;
    $definition = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_text', 'Test Value')
      ->interface(EntityChangedInterface::class)
      ->methodOverride('getChangedTime', fn() => $timestamp)
      ->methodOverride('setChangedTime', fn() => NULL)
      ->build();

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // Both should implement all interfaces.
    $this->assertInstanceOf(EntityInterface::class, $mock);
    $this->assertInstanceOf(EntityInterface::class, $prophecy);
    $this->assertInstanceOf(FieldableEntityInterface::class, $mock);
    $this->assertInstanceOf(FieldableEntityInterface::class, $prophecy);
    $this->assertInstanceOf(EntityChangedInterface::class, $mock);
    $this->assertInstanceOf(EntityChangedInterface::class, $prophecy);

    // Field access should work identically.
    $this->assertSame(
      $mock->get('field_text')->value,
      $prophecy->get('field_text')->value
    );

    // Method overrides should work identically.
    $this->assertSame($mock->getChangedTime(), $prophecy->getChangedTime());
  }

  /**
   * Creates a PHPUnit mock double.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   * @param array<string, mixed> $context
   *   Optional context data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  private function createMockDouble(
    EntityDefinition $definition,
    array $context = [],
  ): EntityInterface {
    return $this->mockFactory->create($definition, $context);
  }

  /**
   * Creates a Prophecy double.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   * @param array<string, mixed> $context
   *   Optional context data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  private function createProphecyDouble(
    EntityDefinition $definition,
    array $context = [],
  ): EntityInterface {
    return $this->prophecyFactory->create($definition, $context);
  }

}
