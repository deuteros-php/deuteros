<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Common;

use Deuteros\Common\FieldDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Deuteros\Common\FieldDefinition
 */
class FieldDefinitionTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getValue
     */
    public function testScalarValue(): void
    {
        $definition = new FieldDefinition('test value');
        $this->assertSame('test value', $definition->getValue());
    }

    /**
     * @covers ::__construct
     * @covers ::getValue
     */
    public function testNullValue(): void
    {
        $definition = new FieldDefinition(null);
        $this->assertNull($definition->getValue());
    }

    /**
     * @covers ::__construct
     * @covers ::getValue
     * @covers ::isMultiValue
     */
    public function testArrayValue(): void
    {
        $value = [['target_id' => 1], ['target_id' => 2]];
        $definition = new FieldDefinition($value);
        $this->assertSame($value, $definition->getValue());
        $this->assertTrue($definition->isMultiValue());
    }

    /**
     * @covers ::isCallable
     */
    public function testIsCallable(): void
    {
        $callable = fn() => 'dynamic';
        $definition = new FieldDefinition($callable);
        $this->assertTrue($definition->isCallable());
        $this->assertFalse($definition->isMultiValue());
    }

    /**
     * @covers ::isCallable
     */
    public function testNonCallableIsNotCallable(): void
    {
        $definition = new FieldDefinition('static');
        $this->assertFalse($definition->isCallable());
    }

    /**
     * @covers ::isMultiValue
     */
    public function testScalarIsNotMultiValue(): void
    {
        $definition = new FieldDefinition('scalar');
        $this->assertFalse($definition->isMultiValue());
    }
}
