<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration;

use Deuteros\Common\EntityDefinition;
use Deuteros\Common\EntityDefinitionBuilder;
use Deuteros\Common\EntityDoubleFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\TestCase;

/**
 * Base test class for entity double factory integration tests.
 *
 * Contains shared tests that work identically across PHPUnit and Prophecy
 * factory implementations.
 */
abstract class EntityDoubleFactoryTestBase extends TestCase {

  /**
   * The factory under test.
   */
  protected EntityDoubleFactoryInterface $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = $this->createFactory();
  }

  /**
   * Creates the factory instance for this test.
   *
   * @return \Deuteros\Common\EntityDoubleFactoryInterface
   *   The factory to test.
   */
  abstract protected function createFactory(): EntityDoubleFactoryInterface;

  /**
   * Tests creating an entity double with only "entity_type" specified.
   */
  public function testMinimalEntityDouble(): void {
    $entity = $this->factory->create(
      new EntityDefinition('node')
    );

    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertSame('node', $entity->getEntityTypeId());
    $this->assertSame('node', $entity->bundle());
  }

  /**
   * Tests entity metadata accessors (id, uuid, label, bundle).
   */
  public function testEntityWithMetadata(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->id(42)
        ->uuid('test-uuid-123')
        ->label('Test Article')
        ->build()
    );

    $this->assertSame('node', $entity->getEntityTypeId());
    $this->assertSame('article', $entity->bundle());
    $this->assertSame(42, $entity->id());
    $this->assertSame('test-uuid-123', $entity->uuid());
    $this->assertSame('Test Article', $entity->label());
  }

  /**
   * Tests accessing scalar field values via get() method.
   */
  public function testScalarFieldAccess(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_title', 'Test Title')
        ->field('field_count', 42)
        ->build()
    );

    $this->assertSame('Test Title', $entity->get('field_title')->value);
    $this->assertSame(42, $entity->get('field_count')->value);
  }

  /**
   * Tests that callback field values receive context and resolve correctly.
   */
  public function testCallbackFieldResolution(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_dynamic', fn(array $context) => $context['dynamic_value'])
        ->build(),
      ['dynamic_value' => 'Resolved from context'],
    );

    $this->assertSame('Resolved from context', $entity->get('field_dynamic')->value);
  }

  /**
   * Tests context propagation to metadata and field callbacks.
   */
  public function testContextPropagation(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->id(fn(array $context) => $context['computed_id'])
        ->label(fn(array $context) => "Label: {$context['title']}")
        ->field('field_computed', fn(array $context) => $context['title'] . ' Field')
        ->build(),
      [
        'computed_id' => 100,
        'title' => 'Dynamic',
      ],
    );

    $this->assertSame(100, $entity->id());
    $this->assertSame('Label: Dynamic', $entity->label());
    $this->assertSame('Dynamic Field', $entity->get('field_computed')->value);
  }

  /**
   * Tests accessing multi-value fields via ::first(), ::get($i), and shorthand.
   */
  public function testMultiValueFieldAccess(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_tags', [
          ['target_id' => 1],
          ['target_id' => 2],
          ['target_id' => 3],
        ])
        ->build()
    );

    // Access via first().
    $this->assertSame(1, $entity->get('field_tags')->first()->target_id);

    // Access via get(delta).
    $this->assertSame(1, $entity->get('field_tags')->get(0)->target_id);
    $this->assertSame(2, $entity->get('field_tags')->get(1)->target_id);
    $this->assertSame(3, $entity->get('field_tags')->get(2)->target_id);

    // Access via shorthand.
    $this->assertSame(1, $entity->get('field_tags')->target_id);
  }

  /**
   * Tests chained field access: entity -> field list -> item -> property.
   */
  public function testNestedFieldAccess(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_text', 'Plain text value')
        ->build()
    );

    // Chain: entity -> field list -> first item -> value property.
    $this->assertSame('Plain text value', $entity->get('field_text')->value);
    $this->assertSame('Plain text value', $entity->get('field_text')->first()->value);
  }

  /**
   * Tests ::hasField() returns correct boolean for defined fields.
   */
  public function testHasField(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_existing', 'value')
        ->build()
    );

    $this->assertTrue($entity->hasField('field_existing'));
    $this->assertFalse($entity->hasField('field_nonexistent'));
  }

  /**
   * Tests ::isEmpty() returns correct boolean based on field value.
   */
  public function testFieldIsEmpty(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_with_value', 'value')
        ->field('field_null', NULL)
        ->build()
    );

    $this->assertFalse($entity->get('field_with_value')->isEmpty());
    $this->assertTrue($entity->get('field_null')->isEmpty());
  }

  /**
   * Tests that "method_overrides" take precedence over default resolvers.
   */
  public function testMethodOverridesPrecedence(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->id(1)
        ->methodOverride('id', fn() => 999)
        ->build()
    );

    // The override should take precedence.
    $this->assertSame(999, $entity->id());
  }

  /**
   * Tests that method override callbacks receive context array.
   *
   * This is an implementation-agnostic version that doesn't require
   * EntityChangedInterface, since that interface behaves differently
   * in PHPUnit vs Prophecy.
   */
  public function testMethodOverridesReceiveContext(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->methodOverride('id', fn(array $context) => $context['computed_id'])
        ->build(),
      ['computed_id' => 999],
    );

    $this->assertSame(999, $entity->id());
  }

  /**
   * Tests that accessing undefined fields throws "InvalidArgumentException".
   */
  public function testUndefinedFieldThrows(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_defined', 'value')
        ->build()
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Field 'field_undefined' is not defined");

    $entity->get('field_undefined');
  }

  /**
   * Tests that guardrail methods like save() throw LogicException.
   */
  public function testUnsupportedMethodThrows(): void {
    $entity = $this->factory->create(
      new EntityDefinition(entityType: 'node', bundle: 'article')
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("Method 'save' is not supported");
    $this->expectExceptionMessage('Kernel test');

    $entity->save();
  }

  /**
   * Tests that repeated get() calls return the same field list instance.
   */
  public function testFieldListCaching(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_test', 'value')
        ->build()
    );

    $firstAccess = $entity->get('field_test');
    $secondAccess = $entity->get('field_test');

    // Should return the same instance.
    $this->assertSame($firstAccess, $secondAccess);
  }

  /**
   * Tests that mutable entities allow field value updates via set().
   */
  public function testMutableEntityFieldSet(): void {
    $entity = $this->factory->createMutable(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_status', 'draft')
        ->build()
    );

    // Initial value.
    $this->assertSame('draft', $entity->get('field_status')->value);

    // Update the field.
    $entity->set('field_status', 'published');

    // New value should be accessible.
    $this->assertSame('published', $entity->get('field_status')->value);
  }

  /**
   * Tests that immutable entities throw on ::set() attempts.
   */
  public function testImmutableEntityRejectsSet(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_status', 'draft')
        ->build()
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("Cannot modify field 'field_status' on immutable entity double");
    $this->expectExceptionMessage('createMutableEntityDouble()');

    $entity->set('field_status', 'published');
  }

  /**
   * Tests that ::set() returns the entity for method chaining.
   */
  public function testMutableEntitySetReturnsEntity(): void {
    $entity = $this->factory->createMutable(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_status', 'draft')
        ->build()
    );

    $result = $entity->set('field_status', 'published');

    // Should return the entity for chaining.
    $this->assertSame($entity, $result);
  }

  /**
   * Tests accessing entity reference field "target_id" property.
   */
  public function testEntityReferenceFieldWithTargetId(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_author', ['target_id' => 42])
        ->build()
    );

    $this->assertSame(42, $entity->get('field_author')->target_id);
    $this->assertSame(42, $entity->get('field_author')->first()->target_id);
  }

  /**
   * Tests ::getValue() returns array of all field item values.
   */
  public function testFieldGetValue(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_tags', [
          ['target_id' => 1],
          ['target_id' => 2],
        ])
        ->build()
    );

    $values = $entity->get('field_tags')->getValue();
    $this->assertCount(2, $values);
    $this->assertSame(['target_id' => 1], $values[0]);
    $this->assertSame(['target_id' => 2], $values[1]);
  }

  /**
   * Tests that empty array fields return NULL from ::first() and ::get().
   */
  public function testNullFieldItem(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_empty', [])
        ->build()
    );

    $this->assertNull($entity->get('field_empty')->first());
    $this->assertNull($entity->get('field_empty')->get(0));
    $this->assertTrue($entity->get('field_empty')->isEmpty());
  }

  /**
   * Tests that ;;get() with out-of-range delta returns NULL.
   */
  public function testOutOfRangeDeltaReturnsNull(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_tags', [
          ['target_id' => 1],
        ])
        ->build()
    );

    $this->assertNull($entity->get('field_tags')->get(99));
  }

  /**
   * Tests implementing multiple interfaces with method overrides.
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

  /**
   * Tests fromInterface() creates a working double for ContentEntityInterface.
   */
  public function testFromInterfaceWithContentEntity(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::fromInterface('node', ContentEntityInterface::class)
        ->bundle('article')
        ->id(42)
        ->field('field_test', 'value')
        ->build()
    );

    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertInstanceOf(ContentEntityInterface::class, $entity);
    $this->assertInstanceOf(FieldableEntityInterface::class, $entity);
    $this->assertSame('node', $entity->getEntityTypeId());
    $this->assertSame('article', $entity->bundle());
    $this->assertSame(42, $entity->id());
    $this->assertSame('value', $entity->get('field_test')->value);
  }

  /**
   * Tests fromInterface() creates a working double for ConfigEntityInterface.
   */
  public function testFromInterfaceWithConfigEntity(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::fromInterface('view', ConfigEntityInterface::class)
        ->id('frontpage')
        ->label('Frontpage View')
        ->methodOverride('status', fn() => TRUE)
        ->build()
    );

    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertInstanceOf(ConfigEntityInterface::class, $entity);
    $this->assertSame('view', $entity->getEntityTypeId());
    $this->assertSame('frontpage', $entity->id());
    $this->assertSame('Frontpage View', $entity->label());
    $this->assertTrue($entity->status());
  }

  /**
   * Tests lenient mode allows unsupported methods to return null.
   */
  public function testLenientModeAllowsUnsupportedMethods(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::fromInterface('node', ContentEntityInterface::class)
        ->bundle('article')
        ->lenient()
        ->build()
    );

    // In lenient mode, save() should return null instead of throwing.
    // PHPStan: save() returns int per PHPDoc, but in lenient mode we return
    // null. This is intentional - we're testing our mock behavior.
    $result = $entity->save();
    /** @phpstan-ignore method.impossibleType */
    $this->assertNull($result);
  }

  /**
   * Tests that without lenient mode, unsupported methods still throw.
   */
  public function testNonLenientModeThrowsForUnsupportedMethods(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::fromInterface('node', ContentEntityInterface::class)
        ->bundle('article')
        ->build()
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("Method 'save' is not supported");

    $entity->save();
  }

  /**
   * Tests magic __get for field access via property syntax.
   */
  public function testMagicGetFieldAccess(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_title', 'Test Title')
        ->field('field_author', ['target_id' => 42])
        ->build()
    );

    // Magic property access should work.
    $this->assertSame('Test Title', $entity->field_title->value);
    $this->assertSame(42, $entity->field_author->target_id);
  }

  /**
   * Tests magic __set for mutable entities via property syntax.
   */
  public function testMagicSetOnMutableEntity(): void {
    $entity = $this->factory->createMutable(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_status', 'draft')
        ->build()
    );

    // Initial value via magic get.
    $this->assertSame('draft', $entity->field_status->value);

    // Update via magic set.
    $entity->field_status = 'published';

    // New value should be accessible.
    $this->assertSame('published', $entity->field_status->value);
  }

  /**
   * Tests magic __set throws on immutable entities.
   */
  public function testMagicSetOnImmutableEntityThrows(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_status', 'draft')
        ->build()
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("Cannot modify field 'field_status' on immutable entity double");

    $entity->field_status = 'published';
  }

  /**
   * Tests magic __get for undefined field throws.
   */
  public function testMagicGetUndefinedFieldThrows(): void {
    $entity = $this->factory->create(
      EntityDefinitionBuilder::create('node')
        ->bundle('article')
        ->field('field_defined', 'value')
        ->build()
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Field 'field_undefined' is not defined");

    // Access undefined field via magic property.
    $entity->field_undefined;
  }

}
