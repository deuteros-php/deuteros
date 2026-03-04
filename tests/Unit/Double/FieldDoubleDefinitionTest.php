<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Double;

use Deuteros\Double\FieldDoubleDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the FieldDoubleDefinition value object.
 */
#[CoversClass(FieldDoubleDefinition::class)]
#[Group('deuteros')]
class FieldDoubleDefinitionTest extends TestCase {

  /**
   * Tests storing and retrieving a scalar string value.
   */
  public function testScalarValue(): void {
    $definition = new FieldDoubleDefinition('test value');
    $this->assertSame('test value', $definition->getValue());
  }

  /**
   * Tests that NULL values are stored correctly.
   */
  public function testNullValue(): void {
    $definition = new FieldDoubleDefinition(NULL);
    $this->assertNull($definition->getValue());
  }

  /**
   * Tests multi-value field detection with array of items.
   */
  public function testArrayValue(): void {
    $value = [['target_id' => 1], ['target_id' => 2]];
    $definition = new FieldDoubleDefinition($value);
    $this->assertSame($value, $definition->getValue());
    $this->assertTrue($definition->isMultiValue());
  }

  /**
   * Tests that callable values are correctly identified.
   */
  public function testIsCallable(): void {
    $callable = fn() => 'dynamic';
    $definition = new FieldDoubleDefinition($callable);
    $this->assertTrue($definition->isCallable());
    $this->assertFalse($definition->isMultiValue());
  }

  /**
   * Tests that non-callable values return false from ::isCallable().
   */
  public function testNonCallableIsNotCallable(): void {
    $definition = new FieldDoubleDefinition('static');
    $this->assertFalse($definition->isCallable());
  }

  /**
   * Tests that scalar values are not detected as multi-value.
   */
  public function testScalarIsNotMultiValue(): void {
    $definition = new FieldDoubleDefinition('scalar');
    $this->assertFalse($definition->isMultiValue());
  }

  /**
   * Tests that the default field type is an empty string.
   */
  public function testDefaultTypeIsEmpty(): void {
    $definition = new FieldDoubleDefinition('val');
    $this->assertSame('', $definition->getType());
  }

  /**
   * Tests that an explicit field type is stored and returned.
   */
  public function testExplicitType(): void {
    $definition = new FieldDoubleDefinition('val', 'metatag');
    $this->assertSame('metatag', $definition->getType());
  }

  /**
   * Tests that the default settings is an empty array.
   */
  public function testDefaultSettingsIsEmpty(): void {
    $definition = new FieldDoubleDefinition('val');
    $this->assertNull($definition->getSetting('any_key'));
  }

  /**
   * Tests that a setting value is stored and returned by key.
   */
  public function testGetSettingReturnsValue(): void {
    $definition = new FieldDoubleDefinition('val', '', ['max_length' => 255]);
    $this->assertSame(255, $definition->getSetting('max_length'));
  }

  /**
   * Tests that ::getSetting returns NULL for an unknown key.
   */
  public function testGetSettingReturnsNullForMissingKey(): void {
    $definition = new FieldDoubleDefinition('val', '', ['max_length' => 255]);
    $this->assertNull($definition->getSetting('missing_key'));
  }

  /**
   * Tests that multiple settings are stored independently.
   */
  public function testMultipleSettings(): void {
    $definition = new FieldDoubleDefinition('val', 'string', [
      'max_length' => 128,
      'is_ascii' => TRUE,
    ]);
    $this->assertSame(128, $definition->getSetting('max_length'));
    $this->assertTrue($definition->getSetting('is_ascii'));
  }

}
