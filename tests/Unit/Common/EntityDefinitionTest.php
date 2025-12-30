<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Common;

use Deuteros\Common\EntityDefinition;
use Deuteros\Common\FieldDefinition;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Deuteros\Common\EntityDefinition
 */
class EntityDefinitionTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testMinimalConstruction(): void
    {
        $definition = new EntityDefinition(entityType: 'node');

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
     * @covers ::__construct
     */
    public function testFullConstruction(): void
    {
        $fields = ['field_test' => new FieldDefinition('value')];
        $interfaces = [FieldableEntityInterface::class];
        $methodOverrides = ['getOwnerId' => fn() => 1];
        $context = ['key' => 'value'];

        $definition = new EntityDefinition(
            entityType: 'node',
            bundle: 'article',
            id: 1,
            uuid: 'test-uuid',
            label: 'Test Node',
            fields: $fields,
            interfaces: $interfaces,
            methodOverrides: $methodOverrides,
            context: $context,
            mutable: true,
        );

        $this->assertSame('node', $definition->entityType);
        $this->assertSame('article', $definition->bundle);
        $this->assertSame(1, $definition->id);
        $this->assertSame('test-uuid', $definition->uuid);
        $this->assertSame('Test Node', $definition->label);
        $this->assertSame($fields, $definition->fields);
        $this->assertSame($interfaces, $definition->interfaces);
        $this->assertSame($methodOverrides, $definition->methodOverrides);
        $this->assertSame($context, $definition->context);
        $this->assertTrue($definition->mutable);
    }

    /**
     * @covers ::__construct
     */
    public function testBundleDefaultsToEntityType(): void
    {
        $definition = new EntityDefinition(entityType: 'user');
        $this->assertSame('user', $definition->bundle);
    }

    /**
     * @covers ::__construct
     */
    public function testFieldsRequireFieldableEntityInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fields can only be defined when FieldableEntityInterface is listed');

        new EntityDefinition(
            entityType: 'node',
            fields: ['field_test' => new FieldDefinition('value')],
        );
    }

    /**
     * @covers ::fromArray
     */
    public function testFromArrayMinimal(): void
    {
        $definition = EntityDefinition::fromArray(['entity_type' => 'node']);

        $this->assertSame('node', $definition->entityType);
        $this->assertSame('node', $definition->bundle);
    }

    /**
     * @covers ::fromArray
     */
    public function testFromArrayFull(): void
    {
        $definition = EntityDefinition::fromArray([
            'entity_type' => 'node',
            'bundle' => 'article',
            'id' => 1,
            'uuid' => 'test-uuid',
            'label' => 'Test Node',
            'fields' => [
                'field_test' => 'raw value',
            ],
            'interfaces' => [FieldableEntityInterface::class],
            'method_overrides' => [
                'getOwnerId' => fn() => 1,
            ],
            'context' => ['key' => 'value'],
            'mutable' => true,
        ]);

        $this->assertSame('node', $definition->entityType);
        $this->assertSame('article', $definition->bundle);
        $this->assertSame(1, $definition->id);
        $this->assertInstanceOf(FieldDefinition::class, $definition->fields['field_test']);
        $this->assertSame('raw value', $definition->fields['field_test']->getValue());
    }

    /**
     * @covers ::fromArray
     */
    public function testFromArrayConvertsRawFieldsToDefinitions(): void
    {
        $definition = EntityDefinition::fromArray([
            'entity_type' => 'node',
            'fields' => ['field_test' => 'value'],
            'interfaces' => [FieldableEntityInterface::class],
        ]);

        $this->assertInstanceOf(FieldDefinition::class, $definition->fields['field_test']);
    }

    /**
     * @covers ::fromArray
     */
    public function testFromArrayRequiresEntityType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'entity_type' is required");

        EntityDefinition::fromArray([]);
    }

    /**
     * @covers ::fromArray
     */
    public function testFromArrayRejectsEmptyEntityType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EntityDefinition::fromArray(['entity_type' => '']);
    }

    /**
     * @covers ::hasInterface
     */
    public function testHasInterface(): void
    {
        $definition = new EntityDefinition(
            entityType: 'node',
            interfaces: [FieldableEntityInterface::class],
        );

        $this->assertTrue($definition->hasInterface(FieldableEntityInterface::class));
        $this->assertFalse($definition->hasInterface('NonExistent'));
    }

    /**
     * @covers ::hasMethodOverride
     * @covers ::getMethodOverride
     */
    public function testMethodOverrides(): void
    {
        $callable = fn() => 1;
        $definition = new EntityDefinition(
            entityType: 'node',
            methodOverrides: ['getOwnerId' => $callable],
        );

        $this->assertTrue($definition->hasMethodOverride('getOwnerId'));
        $this->assertFalse($definition->hasMethodOverride('nonexistent'));
        $this->assertSame($callable, $definition->getMethodOverride('getOwnerId'));
        $this->assertNull($definition->getMethodOverride('nonexistent'));
    }

    /**
     * @covers ::hasField
     * @covers ::getField
     */
    public function testFieldAccess(): void
    {
        $fieldDef = new FieldDefinition('value');
        $definition = new EntityDefinition(
            entityType: 'node',
            fields: ['field_test' => $fieldDef],
            interfaces: [FieldableEntityInterface::class],
        );

        $this->assertTrue($definition->hasField('field_test'));
        $this->assertFalse($definition->hasField('nonexistent'));
        $this->assertSame($fieldDef, $definition->getField('field_test'));
        $this->assertNull($definition->getField('nonexistent'));
    }

    /**
     * @covers ::withContext
     */
    public function testWithContext(): void
    {
        $original = new EntityDefinition(
            entityType: 'node',
            context: ['a' => 1],
        );

        $new = $original->withContext(['b' => 2]);

        $this->assertSame(['a' => 1], $original->context);
        $this->assertSame(['a' => 1, 'b' => 2], $new->context);
        $this->assertNotSame($original, $new);
    }
}
