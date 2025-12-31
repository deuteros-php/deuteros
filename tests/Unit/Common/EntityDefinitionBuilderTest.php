<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Common;

use Deuteros\Common\EntityDefinitionBuilder;
use Deuteros\Common\FieldDefinition;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EntityDefinitionBuilder.
 */
#[CoversClass(EntityDefinitionBuilder::class)]
#[Group('deuteros')]
class EntityDefinitionBuilderTest extends TestCase {

  /**
   * Tests building a minimal definition with only entity_type.
   */
  public function testMinimalBuilder(): void {
    $definition = EntityDefinitionBuilder::create('node')->build();

    $this->assertSame('node', $definition->entityType);
    $this->assertSame('node', $definition->bundle);
    $this->assertNull($definition->id);
    $this->assertNull($definition->uuid);
    $this->assertNull($definition->label);
    $this->assertSame([], $definition->fields);
    $this->assertSame([], $definition->interfaces);
    $this->assertSame([], $definition->methodOverrides);
    $this->assertSame([], $definition->context);
    $this->assertFalse($definition->mutable);
  }

  /**
   * Tests building a definition with all options.
   */
  public function testFullBuilder(): void {
    $callback = fn() => 1;

    $definition = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->id(42)
      ->uuid('test-uuid')
      ->label('Test Label')
      ->field('field_test', 'value')
      ->interface(EntityChangedInterface::class)
      ->methodOverride('getChangedTime', $callback)
      ->context('key', 'value')
      ->build();

    $this->assertSame('node', $definition->entityType);
    $this->assertSame('article', $definition->bundle);
    $this->assertSame(42, $definition->id);
    $this->assertSame('test-uuid', $definition->uuid);
    $this->assertSame('Test Label', $definition->label);
    $this->assertArrayHasKey('field_test', $definition->fields);
    $this->assertContains(FieldableEntityInterface::class, $definition->interfaces);
    $this->assertContains(EntityChangedInterface::class, $definition->interfaces);
    $this->assertSame($callback, $definition->methodOverrides['getChangedTime']);
    $this->assertSame(['key' => 'value'], $definition->context);
  }

  /**
   * Tests that adding a field auto-adds FieldableEntityInterface.
   */
  public function testFieldAutoAddsFieldableInterface(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->field('field_test', 'value')
      ->build();

    $this->assertContains(FieldableEntityInterface::class, $definition->interfaces);
  }

  /**
   * Tests that adding a field with FieldableEntityInterface already present.
   */
  public function testFieldWithExistingFieldableInterface(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->interface(FieldableEntityInterface::class)
      ->field('field_test', 'value')
      ->build();

    // Should not duplicate the interface.
    $count = array_count_values($definition->interfaces);
    $this->assertSame(1, $count[FieldableEntityInterface::class]);
  }

  /**
   * Tests that interface() deduplicates interfaces.
   */
  public function testInterfaceDeduplication(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->interface(EntityChangedInterface::class)
      ->interface(EntityChangedInterface::class)
      ->build();

    $count = array_count_values($definition->interfaces);
    $this->assertSame(1, $count[EntityChangedInterface::class]);
  }

  /**
   * Tests the interfaces() bulk method.
   */
  public function testInterfacesBulk(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->interfaces([
        FieldableEntityInterface::class,
        EntityChangedInterface::class,
      ])
      ->build();

    $this->assertContains(FieldableEntityInterface::class, $definition->interfaces);
    $this->assertContains(EntityChangedInterface::class, $definition->interfaces);
  }

  /**
   * Tests the fields() bulk method.
   */
  public function testFieldsBulk(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->fields([
        'field_one' => 'value1',
        'field_two' => 'value2',
      ])
      ->build();

    $this->assertArrayHasKey('field_one', $definition->fields);
    $this->assertArrayHasKey('field_two', $definition->fields);
    $this->assertSame('value1', $definition->fields['field_one']->getValue());
    $this->assertSame('value2', $definition->fields['field_two']->getValue());
  }

  /**
   * Tests the methodOverrides() bulk method.
   */
  public function testMethodOverridesBulk(): void {
    $callback1 = fn() => 1;
    $callback2 = fn() => 2;

    $definition = EntityDefinitionBuilder::create('node')
      ->methodOverrides([
        'method1' => $callback1,
        'method2' => $callback2,
      ])
      ->build();

    $this->assertSame($callback1, $definition->methodOverrides['method1']);
    $this->assertSame($callback2, $definition->methodOverrides['method2']);
  }

  /**
   * Tests the withContext() bulk method.
   */
  public function testWithContextBulk(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->context('a', 1)
      ->withContext(['b' => 2, 'c' => 3])
      ->build();

    $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $definition->context);
  }

  /**
   * Tests creating a builder from an existing definition.
   */
  public function testFromExistingDefinition(): void {
    $original = EntityDefinitionBuilder::create('node')
      ->bundle('article')
      ->id(42)
      ->field('field_test', 'original')
      ->build();

    $modified = EntityDefinitionBuilder::from($original)
      ->label('New Label')
      ->field('field_test', 'modified')
      ->build();

    // Original should be unchanged.
    $this->assertNull($original->label);
    $this->assertSame('original', $original->fields['field_test']->getValue());

    // Modified should have new values but preserve unchanged ones.
    $this->assertSame('node', $modified->entityType);
    $this->assertSame('article', $modified->bundle);
    $this->assertSame(42, $modified->id);
    $this->assertSame('New Label', $modified->label);
    $this->assertSame('modified', $modified->fields['field_test']->getValue());
  }

  /**
   * Tests that field values are wrapped in FieldDefinition.
   */
  public function testFieldValuesWrappedInFieldDefinition(): void {
    $definition = EntityDefinitionBuilder::create('node')
      ->field('field_test', 'raw value')
      ->build();

    $this->assertInstanceOf(FieldDefinition::class, $definition->fields['field_test']);
    $this->assertSame('raw value', $definition->fields['field_test']->getValue());
  }

  /**
   * Tests that FieldDefinition values are preserved.
   */
  public function testFieldDefinitionPreserved(): void {
    $fieldDef = new FieldDefinition('wrapped value');

    $definition = EntityDefinitionBuilder::create('node')
      ->field('field_test', $fieldDef)
      ->build();

    $this->assertSame($fieldDef, $definition->fields['field_test']);
  }

  /**
   * Tests callable field values.
   */
  public function testCallableFieldValue(): void {
    $callback = fn(array $context) => $context['value'];

    $definition = EntityDefinitionBuilder::create('node')
      ->field('field_dynamic', $callback)
      ->build();

    $this->assertTrue($definition->fields['field_dynamic']->isCallable());
  }

  /**
   * Tests callable metadata values.
   */
  public function testCallableMetadata(): void {
    $idCallback = fn(array $context) => $context['id'];
    $labelCallback = fn(array $context) => $context['label'];

    $definition = EntityDefinitionBuilder::create('node')
      ->id($idCallback)
      ->label($labelCallback)
      ->build();

    $this->assertSame($idCallback, $definition->id);
    $this->assertSame($labelCallback, $definition->label);
  }

}
