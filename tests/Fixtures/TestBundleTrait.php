<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

/**
 * Test trait for entity double trait support tests.
 *
 * Provides methods that call entity interface methods to verify that trait
 * methods can properly access the mocked entity double behavior.
 *
 * @phpstan-ignore trait.unused (Used dynamically via eval in EntityDoubleFactory)
 */
trait TestBundleTrait {

  /**
   * Returns the value of the "field_test" field.
   *
   * @return mixed
   *   The field value.
   */
  public function getTestFieldValue(): mixed {
    // @phpstan-ignore property.notFound
    return $this->get('field_test')->value;
  }

  /**
   * Returns the entity ID multiplied by two.
   *
   * @return int
   *   The entity ID times two.
   */
  public function getEntityIdTimesTwo(): int {
    return $this->id() * 2;
  }

  /**
   * Returns a formatted label combining label and ID.
   *
   * @return string
   *   The formatted label.
   */
  public function getFormattedLabel(): string {
    return sprintf('%s (%d)', $this->label(), $this->id());
  }

}
