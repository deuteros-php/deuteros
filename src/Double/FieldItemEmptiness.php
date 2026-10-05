<?php

declare(strict_types=1);

namespace Deuteros\Double;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Decides whether a field item value is empty, as Drupal field items do.
 *
 * Drupal has no single rule: "Map::isEmpty" holds an item empty when every
 * property is NULL, and most core field types override it. The rules here
 * mirror those overrides, keyed by field type. A field without a type, or
 * with a type core does not define, is empty when its main property is NULL
 * or an empty string, which is what most field types do.
 */
final readonly class FieldItemEmptiness {

  /**
   * The properties that must all be NULL or "" for an item to be empty.
   *
   * Keyed by field type. A text format alone is not a value.
   */
  private const array BLANK_PROPERTIES = [
    'string' => ['value'],
    'string_long' => ['value'],
    'uuid' => ['value'],
    'uri' => ['value'],
    'file_uri' => ['value'],
    'email' => ['value'],
    'telephone' => ['value'],
    'datetime' => ['value'],
    'daterange' => ['value', 'end_value'],
    'link' => ['uri'],
    'text' => ['value'],
    'text_long' => ['value'],
    'text_with_summary' => ['value', 'summary'],
    'path' => ['alias', 'pid', 'langcode'],
  ];

  /**
   * The field types holding a number, or an allowed value, in "value".
   *
   * Their items are empty when "value" is falsy but not zero, like
   * "NumericItemBase::isEmpty" and "ListItemBase::isEmpty".
   */
  private const array NUMERIC_TYPES = ['integer', 'decimal', 'float', 'list_integer', 'list_float', 'list_string'];

  /**
   * The field types whose items are entity references.
   */
  private const array REFERENCE_TYPES = ['entity_reference', 'file', 'image'];

  /**
   * The core field types that do not override "Map::isEmpty".
   */
  private const array MAP_TYPES = ['boolean', 'timestamp', 'created', 'changed', 'language'];

  /**
   * Constructs a FieldItemEmptiness.
   *
   * @param string $fieldType
   *   The field type, or empty string if unknown.
   * @param string|null $mainProperty
   *   The main property of the field items.
   * @param bool $usesMapRule
   *   Whether the field item class inherits "Map::isEmpty".
   */
  public function __construct(
    private string $fieldType = '',
    private ?string $mainProperty = 'value',
    private bool $usesMapRule = FALSE,
  ) {}

  /**
   * Creates the emptiness rule of a field double.
   *
   * @param \Deuteros\Double\FieldDoubleDefinition $definition
   *   The field double definition.
   *
   * @return self
   *   The emptiness rule.
   */
  public static function fromDefinition(FieldDoubleDefinition $definition): self {
    return new self($definition->getType(), $definition->getMainPropertyName(), self::inheritsMapRule($definition->getItemClass()));
  }

  /**
   * Determines whether a field item value is empty.
   *
   * @param mixed $value
   *   The item value: a scalar standing for the main property, an entity, or
   *   an array of properties.
   *
   * @return bool
   *   TRUE if the item is empty, FALSE otherwise.
   */
  public function isEmpty(mixed $value): bool {
    // "CommentItem::isEmpty": a comment field always holds a status.
    if ($this->fieldType === 'comment') {
      return FALSE;
    }
    if ($value === NULL || $value === []) {
      return TRUE;
    }
    if (is_object($value) || (is_array($value) && array_is_list($value))) {
      return FALSE;
    }

    $properties = is_array($value) ? $value : [$this->getScalarProperty() => $value];
    return match (TRUE) {
      $this->usesMapRule, in_array($this->fieldType, self::MAP_TYPES, TRUE) => self::hasNoValue($properties),
      isset(self::BLANK_PROPERTIES[$this->fieldType]) => self::areBlank($properties, self::BLANK_PROPERTIES[$this->fieldType]),
      in_array($this->fieldType, self::NUMERIC_TYPES, TRUE) => self::isEmptyNumber($properties['value'] ?? NULL),
      in_array($this->fieldType, self::REFERENCE_TYPES, TRUE) => self::isEmptyReference($properties),
      $this->fieldType === 'password' => ($properties['value'] ?? NULL) === NULL && ($properties['existing'] ?? NULL) === NULL,
      // "MapItem::isEmpty": any key is a value, even one holding NULL.
      $this->fieldType === 'map' => FALSE,
      $this->fieldType === 'layout_section' => ($properties['section'] ?? NULL) === NULL,
      default => $this->isEmptyByShape($properties),
    };
  }

  /**
   * Determines whether an item of an unknown field type is empty.
   *
   * An item holding a "target_id" or an "entity" is a reference. Any other is
   * empty when its main property is blank or, without one, every property is.
   *
   * @param array<array-key, mixed> $properties
   *   The item properties.
   *
   * @return bool
   *   TRUE if the item is empty, FALSE otherwise.
   */
  private function isEmptyByShape(array $properties): bool {
    if (array_key_exists('target_id', $properties) || array_key_exists('entity', $properties)) {
      return self::isEmptyReference($properties);
    }
    $names = $this->mainProperty === NULL ? array_keys($properties) : [$this->mainProperty];
    return self::areBlank($properties, $names);
  }

  /**
   * Gets the property a scalar item value stands for.
   *
   * @return string
   *   The property name.
   */
  private function getScalarProperty(): string {
    if (in_array($this->fieldType, self::REFERENCE_TYPES, TRUE)) {
      return 'target_id';
    }
    return $this->mainProperty ?? 'value';
  }

  /**
   * Checks whether a field item class inherits "Map::isEmpty".
   *
   * A field type that does not override ::isEmpty inherits it from "Map",
   * which is not a field item.
   *
   * @param string $itemClass
   *   The field item class, or empty string if unknown.
   *
   * @return bool
   *   TRUE if the class inherits "Map::isEmpty", FALSE otherwise.
   */
  private static function inheritsMapRule(string $itemClass): bool {
    if ($itemClass === '' || !method_exists($itemClass, 'isEmpty')) {
      return FALSE;
    }
    $declaringClass = (new \ReflectionMethod($itemClass, 'isEmpty'))->getDeclaringClass();
    return !$declaringClass->implementsInterface(FieldItemInterface::class);
  }

  /**
   * Checks whether every property is NULL, like "Map::isEmpty".
   *
   * @param array<array-key, mixed> $properties
   *   The item properties.
   *
   * @return bool
   *   TRUE if no property holds a value, FALSE otherwise.
   */
  private static function hasNoValue(array $properties): bool {
    return array_filter($properties, fn(mixed $property): bool => $property !== NULL) === [];
  }

  /**
   * Checks whether the given properties are all NULL or "".
   *
   * @param array<array-key, mixed> $properties
   *   The item properties.
   * @param list<array-key> $names
   *   The names of the properties to check.
   *
   * @return bool
   *   TRUE if every named property is blank, FALSE otherwise.
   */
  private static function areBlank(array $properties, array $names): bool {
    foreach ($names as $name) {
      $property = $properties[$name] ?? NULL;
      if ($property !== NULL && $property !== '') {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Checks whether a numeric value is empty.
   *
   * Mirrors `empty($value) && (string) $value !== '0'`: zero is a value.
   *
   * @param mixed $value
   *   The value of the "value" property.
   *
   * @return bool
   *   TRUE if the value is empty, FALSE otherwise.
   */
  private static function isEmptyNumber(mixed $value): bool {
    return in_array($value, [NULL, FALSE, '', []], TRUE);
  }

  /**
   * Checks whether a reference item is empty, like "EntityReferenceItem".
   *
   * @param array<array-key, mixed> $properties
   *   The item properties.
   *
   * @return bool
   *   TRUE unless the item holds a target ID or an entity.
   */
  private static function isEmptyReference(array $properties): bool {
    return ($properties['target_id'] ?? NULL) === NULL && !is_object($properties['entity'] ?? NULL);
  }

}
