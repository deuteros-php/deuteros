<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

/**
 * Stands in for the item class of a field type not overriding ::isEmpty.
 *
 * Like "FieldItemBase", it inherits ::isEmpty from a class that is not a field
 * item, and names "value" as its main property whether it stores one or not.
 */
final class TestMapFieldItemClass extends TestTypedDataMap {

  /**
   * Returns the name of the main property, as a Drupal field item does.
   *
   * @return string
   *   The main property name.
   */
  public static function mainPropertyName(): string {
    return 'value';
  }

}
