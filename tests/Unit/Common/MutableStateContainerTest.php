<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Common;

use Deuteros\Common\MutableStateContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MutableStateContainer::class)]
class MutableStateContainerTest extends TestCase
{
    public function testSetAndCheckFieldValue(): void
    {
        $container = new MutableStateContainer();

        $this->assertFalse($container->hasFieldValue('field_test'));

        $container->setFieldValue('field_test', 'new value');

        $this->assertTrue($container->hasFieldValue('field_test'));
    }

    public function testGetFieldValue(): void
    {
        $container = new MutableStateContainer();
        $container->setFieldValue('field_test', 'new value');

        $this->assertSame('new value', $container->getFieldValue('field_test'));
    }

    public function testGetFieldValueThrowsForUnsetField(): void
    {
        $container = new MutableStateContainer();

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage("Field 'nonexistent' has not been mutated");

        $container->getFieldValue('nonexistent');
    }

    public function testOverwriteFieldValue(): void
    {
        $container = new MutableStateContainer();
        $container->setFieldValue('field_test', 'first');
        $container->setFieldValue('field_test', 'second');

        $this->assertSame('second', $container->getFieldValue('field_test'));
    }

    public function testReset(): void
    {
        $container = new MutableStateContainer();
        $container->setFieldValue('field_a', 'a');
        $container->setFieldValue('field_b', 'b');

        $container->reset();

        $this->assertFalse($container->hasFieldValue('field_a'));
        $this->assertFalse($container->hasFieldValue('field_b'));
    }

    public function testGetAll(): void
    {
        $container = new MutableStateContainer();
        $container->setFieldValue('field_a', 'a');
        $container->setFieldValue('field_b', 'b');

        $this->assertSame([
            'field_a' => 'a',
            'field_b' => 'b',
        ], $container->getAll());
    }

    public function testGetAllEmpty(): void
    {
        $container = new MutableStateContainer();
        $this->assertSame([], $container->getAll());
    }

    public function testNullValueIsStoredCorrectly(): void
    {
        $container = new MutableStateContainer();
        $container->setFieldValue('field_test', null);

        $this->assertTrue($container->hasFieldValue('field_test'));
        $this->assertNull($container->getFieldValue('field_test'));
    }
}
