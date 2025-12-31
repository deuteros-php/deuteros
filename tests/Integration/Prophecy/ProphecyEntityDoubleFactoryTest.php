<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Prophecy;

use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Integration tests for the Prophecy ProphecyEntityDoubleFactory.
 */
#[CoversClass(ProphecyEntityDoubleFactory::class)]
#[Group('deuteros')]
class ProphecyEntityDoubleFactoryTest extends TestCase {

  use ProphecyTrait;

  /**
   * The factory under test.
   */
  private ProphecyEntityDoubleFactory $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = new ProphecyEntityDoubleFactory($this->getProphet());
  }

  /**
   * Tests creating an entity double with only entity_type specified.
   */
  public function testMinimalEntityDouble(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
    ]);

    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertSame('node', $entity->getEntityTypeId());
    $this->assertSame('node', $entity->bundle());
  }

  /**
   * Tests entity metadata accessors (id, uuid, label, bundle).
   */
  public function testEntityWithMetadata(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'id' => 42,
      'uuid' => 'test-uuid-123',
      'label' => 'Test Article',
    ]);

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
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_title' => 'Test Title',
        'field_count' => 42,
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    // Prophecy mocks don't support ::__get, so use ::get() method.
    $this->assertSame('Test Title', $entity->get('field_title')->value);
    $this->assertSame(42, $entity->get('field_count')->value);
  }

  /**
   * Tests that callback field values receive context and resolve correctly.
   */
  public function testCallbackFieldResolution(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_dynamic' => fn(array $context) => $context['dynamic_value'],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ], [
      'dynamic_value' => 'Resolved from context',
    ]);

    $this->assertSame('Resolved from context', $entity->get('field_dynamic')->value);
  }

  /**
   * Tests context propagation to metadata and field callbacks.
   */
  public function testContextPropagation(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'id' => fn(array $context) => $context['computed_id'],
      'label' => fn(array $context) => "Label: {$context['title']}",
      'fields' => [
        'field_computed' => fn(array $context) => $context['title'] . ' Field',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ], [
      'computed_id' => 100,
      'title' => 'Dynamic',
    ]);

    $this->assertSame(100, $entity->id());
    $this->assertSame('Label: Dynamic', $entity->label());
    $this->assertSame('Dynamic Field', $entity->get('field_computed')->value);
  }

  /**
   * Tests accessing multi-value fields via ::first(), ::get($i), and shorthand.
   */
  public function testMultiValueFieldAccess(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_tags' => [
          ['target_id' => 1],
          ['target_id' => 2],
          ['target_id' => 3],
        ],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

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
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_text' => 'Plain text value',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    // Chain: entity -> field list -> first item -> value property.
    $this->assertSame('Plain text value', $entity->get('field_text')->value);
    $this->assertSame('Plain text value', $entity->get('field_text')->first()->value);
  }

  /**
   * Tests ::hasField() returns correct boolean for defined fields.
   */
  public function testHasField(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_existing' => 'value',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $this->assertTrue($entity->hasField('field_existing'));
    $this->assertFalse($entity->hasField('field_nonexistent'));
  }

  /**
   * Tests ::isEmpty() returns correct boolean based on field value.
   */
  public function testFieldIsEmpty(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_with_value' => 'value',
        'field_null' => NULL,
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $this->assertFalse($entity->get('field_with_value')->isEmpty());
    $this->assertTrue($entity->get('field_null')->isEmpty());
  }

  /**
   * Tests implementing multiple interfaces with method overrides.
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

  /**
   * Tests that "method_overrides" take precedence over default resolvers.
   */
  public function testMethodOverridesPrecedence(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'id' => 1,
      'method_overrides' => [
        // Override the core id() resolver.
        'id' => fn() => 999,
      ],
    ]);

    // The override should take precedence.
    $this->assertSame(999, $entity->id());
  }

  /**
   * Tests that method override callbacks receive context array.
   */
  public function testMethodOverridesReceiveContext(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'interfaces' => [EntityChangedInterface::class],
      'method_overrides' => [
        'getChangedTime' => fn(array $context) => $context['timestamp'],
        'setChangedTime' => fn() => NULL,
      ],
    ], ['timestamp' => 1704153600]);

    $this->assertSame(1704153600, $entity->getChangedTime());
  }

  /**
   * Tests that accessing undefined fields throws "InvalidArgumentException".
   */
  public function testUndefinedFieldThrows(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_defined' => 'value',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Field 'field_undefined' is not defined");

    $entity->get('field_undefined');
  }

  /**
   * Tests that guardrail methods like save() throw LogicException.
   */
  public function testUnsupportedMethodThrows(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("Method 'save' is not supported");
    $this->expectExceptionMessage('Kernel test');

    $entity->save();
  }

  /**
   * Tests that repeated get() calls return the same field list instance.
   */
  public function testFieldListCaching(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_test' => 'value',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $firstAccess = $entity->get('field_test');
    $secondAccess = $entity->get('field_test');

    // Should return the same instance.
    $this->assertSame($firstAccess, $secondAccess);
  }

  /**
   * Tests that mutable entities allow field value updates via set().
   */
  public function testMutableEntityFieldSet(): void {
    $entity = $this->factory->createMutable([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_status' => 'draft',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

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
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_status' => 'draft',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("Cannot modify field 'field_status' on immutable entity double");
    $this->expectExceptionMessage('createMutableEntityDouble()');

    $entity->set('field_status', 'published');
  }

  /**
   * Tests that ::set() returns the entity for method chaining.
   */
  public function testMutableEntitySetReturnsEntity(): void {
    $entity = $this->factory->createMutable([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_status' => 'draft',
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $result = $entity->set('field_status', 'published');

    // Should return the entity for chaining.
    $this->assertSame($entity, $result);
  }

  /**
   * Tests accessing entity reference field "target_id" property.
   */
  public function testEntityReferenceFieldWithTargetId(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_author' => ['target_id' => 42],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $this->assertSame(42, $entity->get('field_author')->target_id);
    $this->assertSame(42, $entity->get('field_author')->first()->target_id);
  }

  /**
   * Tests ::getValue() returns array of all field item values.
   */
  public function testFieldGetValue(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_tags' => [
          ['target_id' => 1],
          ['target_id' => 2],
        ],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $values = $entity->get('field_tags')->getValue();
    $this->assertCount(2, $values);
    $this->assertSame(['target_id' => 1], $values[0]);
    $this->assertSame(['target_id' => 2], $values[1]);
  }

  /**
   * Tests that empty array fields return NULL from ::first() and ::get().
   */
  public function testNullFieldItem(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_empty' => [],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $this->assertNull($entity->get('field_empty')->first());
    $this->assertNull($entity->get('field_empty')->get(0));
    $this->assertTrue($entity->get('field_empty')->isEmpty());
  }

  /**
   * Tests that ;;get() with out-of-range delta returns NULL.
   */
  public function testOutOfRangeDeltaReturnsNull(): void {
    $entity = $this->factory->create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_tags' => [
          ['target_id' => 1],
        ],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ]);

    $this->assertNull($entity->get('field_tags')->get(99));
  }

}
