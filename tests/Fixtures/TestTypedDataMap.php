<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

/**
 * Stands in for "Map", the typed data class field items inherit ::isEmpty from.
 */
class TestTypedDataMap {

  /**
   * Checks whether the data is empty.
   *
   * @return bool
   *   Always TRUE: Deuteros only reflects on where ::isEmpty is declared.
   */
  public function isEmpty(): bool {
    return TRUE;
  }

}
