<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration;

use Deuteros\Common\EntityDoubleDefinition;
use Deuteros\Common\EntityDoubleDefinitionBuilder;
use Deuteros\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
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
    $this->mockFactory = MockEntityDoubleFactory::fromTest($this);
    $this->prophecyFactory = ProphecyEntityDoubleFactory::fromTest($this);
  }

  /**
   * Tests that both adapters return identical entity metadata.
   */
  public function testMetadataParity(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->id(42)
      ->uuid('test-uuid')
      ->label('Test Label')
      ->build();

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // Verify correctness against definition values.
    $this->assertSame('node', $mock->getEntityTypeId());
    $this->assertSame('node', $prophecy->getEntityTypeId());
    $this->assertSame('article', $mock->bundle());
    $this->assertSame('article', $prophecy->bundle());
    $this->assertSame(42, $mock->id());
    $this->assertSame(42, $prophecy->id());
    $this->assertSame('test-uuid', $mock->uuid());
    $this->assertSame('test-uuid', $prophecy->uuid());
    $this->assertSame('Test Label', $mock->label());
    $this->assertSame('Test Label', $prophecy->label());
  }

  /**
   * Tests that both adapters return identical field values.
   */
  public function testFieldValueParity(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_text', 'Test Value')
      ->field('field_number', 123)
      ->field('field_ref', ['target_id' => 42])
      ->build();

    $mock = $this->createMockDouble($definition);
    assert($mock instanceof FieldableEntityInterface);
    $prophecy = $this->createProphecyDouble($definition);
    assert($prophecy instanceof FieldableEntityInterface);

    // Verify correctness against definition values.
    $this->assertSame('Test Value', $mock->get('field_text')->value);
    $this->assertSame('Test Value', $prophecy->get('field_text')->value);
    $this->assertSame(123, $mock->get('field_number')->value);
    $this->assertSame(123, $prophecy->get('field_number')->value);
    // @phpstan-ignore method.impossibleType
    $this->assertSame(42, $mock->get('field_ref')->target_id);
    // @phpstan-ignore method.impossibleType
    $this->assertSame(42, $prophecy->get('field_ref')->target_id);
  }

  /**
   * Tests that both adapters resolve callbacks identically.
   */
  public function testCallbackResolutionParity(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      // @phpstan-ignore-next-line
      ->field('field_dynamic', fn(array $context) => (int) $context['value'] * 2)
      ->build();
    $context = ['value' => 21];

    $mock = $this->createMockDouble($definition, $context);
    assert($mock instanceof FieldableEntityInterface);
    $prophecy = $this->createProphecyDouble($definition, $context);
    assert($prophecy instanceof FieldableEntityInterface);

    // Verify correctness against definition values.
    $this->assertSame(42, $mock->get('field_dynamic')->value);
    $this->assertSame(42, $prophecy->get('field_dynamic')->value);
  }

  /**
   * Tests that both adapters handle multi-value fields identically.
   */
  public function testMultiValueFieldParity(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_tags', [
        ['target_id' => 1],
        ['target_id' => 2],
        ['target_id' => 3],
      ])
      ->build();

    $mock = $this->createMockDouble($definition);
    assert($mock instanceof FieldableEntityInterface);
    $prophecy = $this->createProphecyDouble($definition);
    assert($prophecy instanceof FieldableEntityInterface);

    // Verify correctness against definition values.
    $mockFirst = $mock->get('field_tags')->first();
    $prophecyFirst = $prophecy->get('field_tags')->first();
    assert($mockFirst !== NULL && $prophecyFirst !== NULL);
    // @phpstan-ignore property.notFound
    $this->assertSame(1, $mockFirst->target_id);
    // @phpstan-ignore property.notFound
    $this->assertSame(1, $prophecyFirst->target_id);
    $expectedIds = [1, 2, 3];
    for ($i = 0; $i < 3; $i++) {
      $mockItem = $mock->get('field_tags')->get($i);
      $prophecyItem = $prophecy->get('field_tags')->get($i);
      assert($mockItem !== NULL && $prophecyItem !== NULL);
      // @phpstan-ignore property.notFound
      $this->assertSame($expectedIds[$i], $mockItem->target_id);
      // @phpstan-ignore property.notFound
      $this->assertSame($expectedIds[$i], $prophecyItem->target_id);
    }
    $this->assertNull($mock->get('field_tags')->get(99));
    $this->assertNull($prophecy->get('field_tags')->get(99));
  }

  /**
   * Tests that both adapters handle method overrides identically.
   */
  public function testMethodOverrideParity(): void {
    $timestamp = 1704067200;
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->interface(EntityChangedInterface::class)
      ->method('getChangedTime', fn(array $context) => $context['time'])
      ->method('setChangedTime', fn() => NULL)
      ->build();
    $context = ['time' => $timestamp];

    $mock = $this->createMockDouble($definition, $context);
    assert($mock instanceof EntityChangedInterface);
    $prophecy = $this->createProphecyDouble($definition, $context);
    assert($prophecy instanceof EntityChangedInterface);

    // Verify correctness against definition values.
    $this->assertSame($timestamp, $mock->getChangedTime());
    $this->assertSame($timestamp, $prophecy->getChangedTime());
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
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_text', 'Test Value')
      ->interface(EntityChangedInterface::class)
      ->method('getChangedTime', fn() => $timestamp)
      ->method('setChangedTime', fn() => NULL)
      ->build();

    $mock = $this->createMockDouble($definition);
    assert($mock instanceof FieldableEntityInterface);
    assert($mock instanceof EntityChangedInterface);
    $prophecy = $this->createProphecyDouble($definition);
    assert($prophecy instanceof FieldableEntityInterface);
    assert($prophecy instanceof EntityChangedInterface);

    // Verify correctness against definition values.
    $this->assertSame('Test Value', $mock->get('field_text')->value);
    $this->assertSame('Test Value', $prophecy->get('field_text')->value);
    $this->assertSame($timestamp, $mock->getChangedTime());
    $this->assertSame($timestamp, $prophecy->getChangedTime());
  }

  /**
   * Creates a PHPUnit mock double.
   *
   * @param \Deuteros\Common\EntityDoubleDefinition $definition
   *   The entity double definition.
   * @param array<string, mixed> $context
   *   Optional context data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  private function createMockDouble(
    EntityDoubleDefinition $definition,
    array $context = [],
  ): EntityInterface {
    return $this->mockFactory->create($definition, $context);
  }

  /**
   * Creates a Prophecy double.
   *
   * @param \Deuteros\Common\EntityDoubleDefinition $definition
   *   The entity double definition.
   * @param array<string, mixed> $context
   *   Optional context data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  private function createProphecyDouble(
    EntityDoubleDefinition $definition,
    array $context = [],
  ): EntityInterface {
    return $this->prophecyFactory->create($definition, $context);
  }

  /**
   * Tests fromInterface() parity between adapters.
   */
  public function testFromInterfaceParity(): void {
    $definition = EntityDoubleDefinitionBuilder::fromInterface(
      'node',
      ContentEntityInterface::class
    )
      ->bundle('article')
      ->id(42)
      ->field('field_test', 'value')
      ->build();

    $mock = $this->createMockDouble($definition);
    assert($mock instanceof ContentEntityInterface);
    $prophecy = $this->createProphecyDouble($definition);
    assert($prophecy instanceof ContentEntityInterface);

    // Verify correctness against definition values.
    $this->assertSame('node', $mock->getEntityTypeId());
    $this->assertSame('node', $prophecy->getEntityTypeId());
    $this->assertSame('article', $mock->bundle());
    $this->assertSame('article', $prophecy->bundle());
    $this->assertSame(42, $mock->id());
    $this->assertSame(42, $prophecy->id());
    $this->assertSame('value', $mock->get('field_test')->value);
    $this->assertSame('value', $prophecy->get('field_test')->value);
  }

  /**
   * Tests lenient mode parity between adapters.
   */
  public function testLenientModeParity(): void {
    $definition = EntityDoubleDefinitionBuilder::fromInterface(
      'node',
      ContentEntityInterface::class
    )
      ->bundle('article')
      ->lenient()
      ->build();

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // Both should return null for save() in lenient mode.
    // PHPStan: save() returns int per PHPDoc, but in lenient mode we return
    // null. This is intentional - we're testing our mock behavior.
    /** @phpstan-ignore method.impossibleType */
    $this->assertNull($mock->save());
    /** @phpstan-ignore method.impossibleType */
    $this->assertNull($prophecy->save());

    // Both should return null for delete() in lenient mode.
    $this->assertNull($mock->delete());
    $this->assertNull($prophecy->delete());
  }

  /**
   * Tests that both adapters handle magic __get identically.
   */
  public function testMagicGetParity(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_text', 'Test Value')
      ->field('field_ref', ['target_id' => 42])
      ->build();

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // Verify correctness against definition values.
    // @phpstan-ignore property.notFound, property.nonObject
    $this->assertSame('Test Value', $mock->field_text->value);
    // @phpstan-ignore property.notFound, property.nonObject
    $this->assertSame('Test Value', $prophecy->field_text->value);
    // @phpstan-ignore property.notFound, property.nonObject
    $this->assertSame(42, $mock->field_ref->target_id);
    // @phpstan-ignore property.notFound, property.nonObject
    $this->assertSame(42, $prophecy->field_ref->target_id);
  }

  /**
   * Tests that both adapters handle magic __set on mutable entities.
   */
  public function testMagicSetMutableParity(): void {
    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_status', 'draft')
      ->build();

    $mock = $this->mockFactory->createMutable($definition);
    $prophecy = $this->prophecyFactory->createMutable($definition);

    // Both should support magic set.
    // @phpstan-ignore property.notFound
    $mock->field_status = 'published';
    // @phpstan-ignore property.notFound
    $prophecy->field_status = 'published';

    // Verify correctness of mutated values.
    // @phpstan-ignore property.nonObject
    $this->assertSame('published', $mock->field_status->value);
    // @phpstan-ignore property.nonObject
    $this->assertSame('published', $prophecy->field_status->value);
  }

  /**
   * Tests entity reference parity between adapters.
   */
  public function testEntityReferenceParity(): void {
    $user = $this->mockFactory->create(
      EntityDoubleDefinitionBuilder::create('user')
        ->id(42)
        ->build()
    );

    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_author', $user)
      ->build();

    $mock = $this->createMockDouble($definition);
    assert($mock instanceof FieldableEntityInterface);
    $prophecy = $this->createProphecyDouble($definition);
    assert($prophecy instanceof FieldableEntityInterface);

    // Both should return the same entity reference.
    $this->assertSame($user, $mock->get('field_author')->entity);
    $this->assertSame($user, $prophecy->get('field_author')->entity);

    // Both should auto-populate target_id.
    // @phpstan-ignore method.impossibleType
    $this->assertSame(42, $mock->get('field_author')->target_id);
    // @phpstan-ignore method.impossibleType
    $this->assertSame(42, $prophecy->get('field_author')->target_id);

    // Both should implement EntityReferenceFieldItemListInterface.
    $this->assertInstanceOf(
      EntityReferenceFieldItemListInterface::class,
      $mock->get('field_author')
    );
    $this->assertInstanceOf(
      EntityReferenceFieldItemListInterface::class,
      $prophecy->get('field_author')
    );
  }

  /**
   * Tests referencedEntities parity between adapters.
   */
  public function testReferencedEntitiesParity(): void {
    $tag1 = $this->mockFactory->create(
      EntityDoubleDefinitionBuilder::create('taxonomy_term')
        ->id(1)
        ->build()
    );
    $tag2 = $this->mockFactory->create(
      EntityDoubleDefinitionBuilder::create('taxonomy_term')
        ->id(2)
        ->build()
    );

    $definition = EntityDoubleDefinitionBuilder::create('node')
      ->bundle('article')
      ->field('field_tags', [$tag1, $tag2])
      ->build();

    $mock = $this->createMockDouble($definition);
    assert($mock instanceof FieldableEntityInterface);
    $prophecy = $this->createProphecyDouble($definition);
    assert($prophecy instanceof FieldableEntityInterface);

    $mockFieldList = $mock->get('field_tags');
    assert($mockFieldList instanceof EntityReferenceFieldItemListInterface);
    $prophecyFieldList = $prophecy->get('field_tags');
    assert($prophecyFieldList instanceof EntityReferenceFieldItemListInterface);

    $mockEntities = $mockFieldList->referencedEntities();
    $prophecyEntities = $prophecyFieldList->referencedEntities();

    // Both should return same entities.
    $this->assertSame($tag1, $mockEntities[0]);
    $this->assertSame($tag1, $prophecyEntities[0]);
    $this->assertSame($tag2, $mockEntities[1]);
    $this->assertSame($tag2, $prophecyEntities[1]);
  }

}
