<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Double;

use Deuteros\Double\UuidGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the UuidGenerator class.
 */
#[CoversClass(UuidGenerator::class)]
#[Group('deuteros')]
class UuidGeneratorTest extends TestCase {

  /**
   * The format of a UUID, as in RFC 4122.
   */
  private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

  /**
   * Tests that ::generate returns a well-formed UUID.
   */
  public function testGenerateReturnsUuid(): void {
    $this->assertMatchesRegularExpression(self::UUID_PATTERN, UuidGenerator::generate());
  }

  /**
   * Tests that numbering starts at one, so the nil UUID is never handed out.
   *
   * The counter is process-wide and other tests have advanced it, so it is
   * rewound for the check and put back afterwards.
   */
  public function testGenerateStartsAtOne(): void {
    $counter = new \ReflectionProperty(UuidGenerator::class, 'counter');
    $advanced = $counter->getValue();
    $counter->setValue(NULL, 0);
    try {
      $this->assertSame('00000001-0000-0000-0000-000000000000', UuidGenerator::generate());
    }
    finally {
      $counter->setValue(NULL, $advanced);
    }
  }

  /**
   * Tests that ::generate never returns the same UUID twice.
   */
  public function testGenerateReturnsUniqueUuids(): void {
    $uuids = [UuidGenerator::generate(), UuidGenerator::generate(), UuidGenerator::generate()];

    $this->assertCount(3, array_unique($uuids));
  }

}
