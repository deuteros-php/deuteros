<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Double;

use Deuteros\Double\FieldDoubleDefinition;
use Deuteros\Double\FieldItemEmptiness;
use Deuteros\Tests\Fixtures\TestFieldItemClass;
use Deuteros\Tests\Fixtures\TestMapFieldItemClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the FieldItemEmptiness rules against those of Drupal field items.
 */
#[CoversClass(FieldItemEmptiness::class)]
#[Group('deuteros')]
class FieldItemEmptinessTest extends TestCase {

  /**
   * Tests the emptiness of items of an unknown field type.
   *
   * @param mixed $value
   *   The item value.
   * @param string|null $mainProperty
   *   The main property of the field item.
   * @param bool $expected
   *   Whether the item is expected to be empty.
   */
  #[DataProvider('providerUnknownType')]
  public function testUnknownType(mixed $value, ?string $mainProperty, bool $expected): void {
    $this->assertSame($expected, (new FieldItemEmptiness(mainProperty: $mainProperty))->isEmpty($value));
  }

  /**
   * Data provider for ::testUnknownType.
   *
   * @return array<string, array{mixed, string|null, bool}>
   *   Item value, main property and expected result, keyed by case name.
   */
  public static function providerUnknownType(): array {
    return [
      'null' => [NULL, 'value', TRUE],
      'empty string' => ['', 'value', TRUE],
      'empty array' => [[], 'value', TRUE],
      'zero' => [0, 'value', FALSE],
      'false' => [FALSE, 'value', FALSE],
      'scalar' => ['text', 'value', FALSE],
      'entity' => [new \stdClass(), 'value', FALSE],
      'null main property' => [['value' => NULL], 'value', TRUE],
      'empty main property' => [['value' => '', 'format' => 'basic_html'], 'value', TRUE],
      'filled main property' => [['value' => 'text', 'format' => NULL], 'value', FALSE],
      'missing main property' => [['title' => 'Example'], 'uri', TRUE],
      'item class main property' => [['uri' => 'https://example.com'], 'uri', FALSE],
      'no main property, all empty' => [['a' => NULL, 'b' => ''], NULL, TRUE],
      'no main property, one filled' => [['a' => NULL, 'b' => 'x'], NULL, FALSE],
      'reference with target ID' => [['target_id' => 1], 'target_id', FALSE],
      'reference with empty string target ID' => [['target_id' => ''], 'target_id', FALSE],
      'reference with null target ID' => [['target_id' => NULL], 'target_id', TRUE],
      'reference with null entity' => [['entity' => NULL], 'value', TRUE],
      'reference with entity' => [['entity' => new \stdClass(), 'target_id' => NULL], 'value', FALSE],
    ];
  }

  /**
   * Tests the emptiness of items of core field types.
   *
   * @param string $fieldType
   *   The field type.
   * @param mixed $value
   *   The item value.
   * @param bool $expected
   *   Whether the item is expected to be empty.
   */
  #[DataProvider('providerCoreType')]
  public function testCoreType(string $fieldType, mixed $value, bool $expected): void {
    $this->assertSame($expected, (new FieldItemEmptiness($fieldType))->isEmpty($value));
  }

  /**
   * Data provider for ::testCoreType.
   *
   * The expected results are those of the ::isEmpty method of the item class
   * of each field type.
   *
   * @return array<string, array{string, mixed, bool}>
   *   Field type, item value and expected result, keyed by case name.
   */
  public static function providerCoreType(): array {
    return [
      'string, empty string' => ['string', '', TRUE],
      'string, filled' => ['string', ['value' => 'text'], FALSE],
      'email, empty string' => ['email', ['value' => ''], TRUE],
      'text, format only' => ['text', ['value' => '', 'format' => 'basic_html'], TRUE],
      'text, filled' => ['text_long', ['value' => '<p>Text</p>', 'format' => NULL], FALSE],
      'text, computed property only' => ['text', ['processed' => '<p>Text</p>'], TRUE],
      'text with summary, summary only' => ['text_with_summary', ['value' => '', 'summary' => 'Summary'], FALSE],
      'text with summary, empty' => ['text_with_summary', ['value' => '', 'summary' => ''], TRUE],
      'date range, end only' => ['daterange', ['value' => '', 'end_value' => '2026-01-01'], FALSE],
      'date range, empty' => ['daterange', ['value' => NULL, 'end_value' => ''], TRUE],
      'link, title only' => ['link', ['uri' => '', 'title' => 'Example'], TRUE],
      'link, uri' => ['link', ['uri' => 'https://example.com'], FALSE],
      'path, langcode only' => ['path', ['alias' => '', 'pid' => NULL, 'langcode' => 'en'], FALSE],
      'path, empty' => ['path', ['alias' => '', 'pid' => NULL], TRUE],
      'integer, zero' => ['integer', 0, FALSE],
      'decimal, zero string' => ['decimal', '0', FALSE],
      'float, empty string' => ['float', '', TRUE],
      'list string, zero string' => ['list_string', ['value' => '0'], FALSE],
      'list integer, false' => ['list_integer', FALSE, TRUE],
      'boolean, false' => ['boolean', FALSE, FALSE],
      'boolean, empty string' => ['boolean', '', FALSE],
      'boolean, null' => ['boolean', ['value' => NULL], TRUE],
      'timestamp, zero' => ['timestamp', 0, FALSE],
      'entity reference, target ID' => ['entity_reference', 5, FALSE],
      'entity reference, null target ID' => ['entity_reference', ['target_id' => NULL], TRUE],
      'image, entity' => ['image', ['entity' => new \stdClass()], FALSE],
      'password, existing only' => ['password', ['value' => NULL, 'existing' => 'hash'], FALSE],
      'password, empty string' => ['password', ['value' => ''], FALSE],
      'password, null' => ['password', ['value' => NULL], TRUE],
      'map, null property' => ['map', ['key' => NULL], FALSE],
      'map, empty' => ['map', [], TRUE],
      'comment, null' => ['comment', NULL, FALSE],
      'layout section, none' => ['layout_section', ['section' => NULL], TRUE],
      'layout section, section' => ['layout_section', ['section' => new \stdClass()], FALSE],
    ];
  }

  /**
   * Tests a field item class that inherits "Map::isEmpty".
   *
   * Like a compound field type storing no "value", an item holding any
   * property that is not NULL is not empty, even if its main property is
   * missing.
   */
  public function testItemClassInheritingMapRule(): void {
    $emptiness = FieldItemEmptiness::fromDefinition(new FieldDoubleDefinition(NULL, 'compound', itemClass: TestMapFieldItemClass::class));

    $this->assertFalse($emptiness->isEmpty(['first' => 'a', 'second' => NULL]));
    $this->assertFalse($emptiness->isEmpty(['first' => '']));
    $this->assertTrue($emptiness->isEmpty(['first' => NULL, 'second' => NULL]));
  }

  /**
   * Tests a field item class without ::isEmpty.
   *
   * The rule follows the main property the class names.
   */
  public function testItemClassWithoutIsEmpty(): void {
    $emptiness = FieldItemEmptiness::fromDefinition(new FieldDoubleDefinition(NULL, itemClass: TestFieldItemClass::class));

    $this->assertTrue($emptiness->isEmpty(['uri' => '', 'title' => 'Example']));
    $this->assertFalse($emptiness->isEmpty(['uri' => 'https://example.com']));
  }

  /**
   * Tests that a callback decides emptiness in place of the rules.
   */
  public function testCallback(): void {
    $received = [];
    $callback = function (array $properties) use (&$received): bool {
      $received[] = $properties;
      return ($properties['value'] ?? '') === '' && ($properties['format'] ?? NULL) === NULL;
    };
    $emptiness = FieldItemEmptiness::fromDefinition(new FieldDoubleDefinition(NULL, 'text', isEmpty: $callback(...)));

    // The "text" rule holds a format alone empty, the callback does not.
    $this->assertFalse($emptiness->isEmpty(['value' => '', 'format' => 'basic_html']));
    $this->assertTrue($emptiness->isEmpty(''));
    $this->assertTrue($emptiness->isEmpty(NULL));
    // A scalar stands for the main property, and NULL for no property.
    $this->assertSame([['value' => '', 'format' => 'basic_html'], ['value' => ''], []], $received);
  }

  /**
   * Tests that a callback receives a scalar reference under "target_id".
   */
  public function testCallbackReceivesScalarReferenceAsTargetId(): void {
    $emptiness = new FieldItemEmptiness('entity_reference', callback: fn(array $properties): bool => !isset($properties['target_id']));

    $this->assertFalse($emptiness->isEmpty(5));
  }

  /**
   * Tests that a callback not returning a bool fails loudly.
   */
  public function testCallbackNotReturningBoolIsRejected(): void {
    $emptiness = new FieldItemEmptiness(callback: fn(array $properties): mixed => 1);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('The "isEmpty" callback of a field must return a bool, int given.');
    $emptiness->isEmpty('value');
  }

}
