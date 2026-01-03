<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

/**
 * Second test trait for multiple trait support tests.
 *
 * Used to verify that multiple traits can be applied to a single entity
 * double.
 *
 * @phpstan-ignore trait.unused (Used dynamically via eval in EntityDoubleFactory)
 */
trait SecondTestTrait {

  /**
   * Returns a static value from the second trait.
   *
   * @return string
   *   A static string value.
   */
  public function getSecondTraitValue(): string {
    return 'from_second_trait';
  }

  /**
   * Returns the entity bundle from the second trait.
   *
   * @return string
   *   The entity bundle.
   */
  public function getBundleFromSecondTrait(): string {
    return $this->bundle();
  }

}
