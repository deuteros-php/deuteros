<?php

declare(strict_types=1);

namespace Deuteros\Double;

/**
 * Generates the UUIDs of entities that are not given one.
 *
 * Real storage assigns a UUID to every entity it creates, so entity doubles
 * and subject entities get one too when the test passes none. Every UUID
 * Deuteros hands out comes from here, so no two entities in a test process
 * share one.
 *
 * The UUIDs are numbered rather than random, which reads better in failure
 * output: "00000007-0000-0000-0000-000000000000" is the seventh one. The
 * number depends on how many came before, so a test should not hard-code a
 * generated UUID.
 */
final class UuidGenerator {

  /**
   * The number of UUIDs generated so far.
   */
  private static int $counter = 0;

  /**
   * Generates a UUID that no earlier call returned.
   *
   * @return string
   *   The UUID.
   */
  public static function generate(): string {
    self::$counter++;
    return sprintf('%08x-%04x-%04x-%04x-%012x', self::$counter, 0, 0, 0, 0);
  }

}
