<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Common;

use Deuteros\Common\EntityDoubleDefinition;
use Deuteros\Common\FieldDoubleDefinition;
use Deuteros\Tests\Fixtures\SecondTestTrait;
use Deuteros\Tests\Fixtures\TestBundleTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EntityDoubleDefinition value object.
 */
#[CoversClass(EntityDoubleDefinition::class)]
#[Group('deuteros')]
class EntityDoubleDefinitionTest extends TestCase {

  /**
   * Tests construction with only required "entity_type" parameter.
   *
   * Verifies all optional parameters have sensible defaults.
   */
  public function testMinimalConstruction(): void {
    $definition = new EntityDoubleDefinition(entityType: 'node');

    $this->assertSame('node', $definition->entityType);
    $this->assertSame('node', $definition->bundle);
    $this->assertNull($definition->id);
    $this->assertNull($definition->uuid);
    $this->assertNull($definition->label);
    $this->assertSame([], $definition->fields);
    $this->assertSame([], $definition->interfaces);
    $this->assertSame([], $definition->methods);
    $this->assertSame([], $definition->context);
    $this->assertFalse($definition->mutable);
  }

  /**
   * Tests construction with all parameters specified.
   */
  public function testFullConstruction(): void {
    $fields = ['field_test' => new FieldDoubleDefinition('value')];
    $interfaces = [FieldableEntityInterface::class];
    $methodOverrides = ['getOwnerId' => fn() => 1];
    $context = ['key' => 'value'];

    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      bundle: 'article',
      id: 1,
      uuid: 'test-uuid',
      label: 'Test Node',
      fields: $fields,
      interfaces: $interfaces,
      methods: $methodOverrides,
      context: $context,
      mutable: TRUE,
    );

    $this->assertSame('node', $definition->entityType);
    $this->assertSame('article', $definition->bundle);
    $this->assertSame(1, $definition->id);
    $this->assertSame('test-uuid', $definition->uuid);
    $this->assertSame('Test Node', $definition->label);
    $this->assertSame($fields, $definition->fields);
    $this->assertSame($interfaces, $definition->interfaces);
    $this->assertSame($methodOverrides, $definition->methods);
    $this->assertSame($context, $definition->context);
    $this->assertTrue($definition->mutable);
  }

  /**
   * Tests that bundle defaults to "entity_type" when not specified.
   */
  public function testBundleDefaultsToEntityType(): void {
    $definition = new EntityDoubleDefinition(entityType: 'user');
    $this->assertSame('user', $definition->bundle);
  }

  /**
   * Tests that defining fields without "FieldableEntityInterface" throws.
   */
  public function testFieldsRequireFieldableEntityInterface(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Fields can only be defined when FieldableEntityInterface is listed');

    new EntityDoubleDefinition(
      entityType: 'node',
      fields: ['field_test' => new FieldDoubleDefinition('value')],
    );
  }

  /**
   * Tests ::hasInterface() returns correct boolean for declared interfaces.
   */
  public function testHasInterface(): void {
    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      interfaces: [FieldableEntityInterface::class],
    );

    $this->assertTrue($definition->hasInterface(FieldableEntityInterface::class));
    // @phpstan-ignore argument.type
    $this->assertFalse($definition->hasInterface('NonExistent'));
  }

  /**
   * Tests method override detection and retrieval.
   */
  public function testMethodOverrides(): void {
    $callable = fn() => 1;
    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      methods: ['getOwnerId' => $callable],
    );

    $this->assertTrue($definition->hasMethod('getOwnerId'));
    $this->assertFalse($definition->hasMethod('nonexistent'));
    $this->assertSame($callable, $definition->getMethod('getOwnerId'));
    $this->assertNull($definition->getMethod('nonexistent'));
  }

  /**
   * Tests field definition detection and retrieval.
   */
  public function testFieldAccess(): void {
    $definitioninition = new FieldDoubleDefinition('value');
    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      fields: ['field_test' => $definitioninition],
      interfaces: [FieldableEntityInterface::class],
    );

    $this->assertTrue($definition->hasField('field_test'));
    $this->assertFalse($definition->hasField('nonexistent'));
    $this->assertSame($definitioninition, $definition->getField('field_test'));
    $this->assertNull($definition->getField('nonexistent'));
  }

  /**
   * Tests ::withContext() creates a new instance with merged context.
   */
  public function testWithContext(): void {
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      context: ['a' => 1],
    );

    $new = $original->withContext(['b' => 2]);

    $this->assertSame(['a' => 1], $original->context);
    $this->assertSame(['a' => 1, 'b' => 2], $new->context);
    $this->assertNotSame($original, $new);
  }

  /**
   * Tests ::withContext() returns same instance when context is empty.
   */
  public function testWithContextReturnsSameInstanceWhenEmpty(): void {
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      context: ['a' => 1],
    );

    $new = $original->withContext([]);

    $this->assertSame($original, $new);
  }

  /**
   * Tests ::withMutable() creates a new instance with different mutability.
   */
  public function testWithMutable(): void {
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      mutable: FALSE,
    );

    $mutable = $original->withMutable(TRUE);

    $this->assertFalse($original->mutable);
    $this->assertTrue($mutable->mutable);
    $this->assertNotSame($original, $mutable);
  }

  /**
   * Tests ::withMutable() returns same instance when mutability unchanged.
   */
  public function testWithMutableReturnsSameInstanceWhenUnchanged(): void {
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      mutable: TRUE,
    );

    $new = $original->withMutable(TRUE);

    $this->assertSame($original, $new);
  }

  /**
   * Tests ::getDeclaringInterface() finds the correct interface for a method.
   */
  public function testGetDeclaringInterface(): void {
    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      interfaces: [
        ContentEntityInterface::class,
        EntityChangedInterface::class,
      ],
      primaryInterface: ContentEntityInterface::class,
    );

    // getChangedTime is declared in EntityChangedInterface.
    $this->assertSame(
      EntityChangedInterface::class,
      $definition->getDeclaringInterface('getChangedTime')
    );

    // hasField is declared in FieldableEntityInterface (parent of
    // ContentEntityInterface).
    $interface = $definition->getDeclaringInterface('hasField');
    $this->assertSame(FieldableEntityInterface::class, $interface);
  }

  /**
   * Tests ::getDeclaringInterface() returns null for unknown method.
   */
  public function testGetDeclaringInterfaceReturnsNullForUnknown(): void {
    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      interfaces: [FieldableEntityInterface::class],
    );

    $this->assertNull($definition->getDeclaringInterface('unknownMethod'));
  }

  /**
   * Tests construction with primaryInterface and lenient.
   */
  public function testConstructionWithNewProperties(): void {
    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      primaryInterface: ContentEntityInterface::class,
      lenient: TRUE,
    );

    $this->assertSame(ContentEntityInterface::class, $definition->primaryInterface);
    $this->assertTrue($definition->lenient);
  }

  /**
   * Tests ::withContext() preserves primaryInterface and lenient.
   */
  public function testWithContextPreservesNewProperties(): void {
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      primaryInterface: ContentEntityInterface::class,
      lenient: TRUE,
    );

    $new = $original->withContext(['key' => 'value']);

    $this->assertSame(ContentEntityInterface::class, $new->primaryInterface);
    $this->assertTrue($new->lenient);
  }

  /**
   * Tests ::withMutable() preserves primaryInterface and lenient.
   */
  public function testWithMutablePreservesNewProperties(): void {
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      primaryInterface: ContentEntityInterface::class,
      lenient: TRUE,
    );

    $new = $original->withMutable(TRUE);

    $this->assertSame(ContentEntityInterface::class, $new->primaryInterface);
    $this->assertTrue($new->lenient);
  }

  /**
   * Tests minimal construction has empty traits array.
   */
  public function testMinimalConstructionHasEmptyTraits(): void {
    $definition = new EntityDoubleDefinition(entityType: 'node');

    $this->assertSame([], $definition->traits);
  }

  /**
   * Tests construction with traits stores them correctly.
   */
  public function testConstructionWithTraits(): void {
    $traits = [TestBundleTrait::class, SecondTestTrait::class];

    $definition = new EntityDoubleDefinition(
      entityType: 'node',
      traits: $traits,
    );

    $this->assertSame($traits, $definition->traits);
  }

  /**
   * Tests ::withContext() preserves traits in new instance.
   */
  public function testWithContextPreservesTraits(): void {
    $traits = [TestBundleTrait::class];
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      traits: $traits,
    );

    $new = $original->withContext(['key' => 'value']);

    $this->assertSame($traits, $new->traits);
  }

  /**
   * Tests ::withMutable() preserves traits in new instance.
   */
  public function testWithMutablePreservesTraits(): void {
    $traits = [TestBundleTrait::class, SecondTestTrait::class];
    $original = new EntityDoubleDefinition(
      entityType: 'node',
      traits: $traits,
    );

    $new = $original->withMutable(TRUE);

    $this->assertSame($traits, $new->traits);
  }

}
