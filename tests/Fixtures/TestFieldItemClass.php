<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

/**
 * Stands in for the field item class of a field type.
 *
 * Deuteros only reads the static "::mainPropertyName" of a field item class,
 * so the fixture declares nothing else.
 */
final class TestFieldItemClass {

  /**
   * Returns the name of the main property, as a Drupal field item does.
   *
   * @return string
   *   The main property name.
   */
  public static function mainPropertyName(): string {
    return 'uri';
  }

}
