<?php

declare(strict_types=1);

namespace Deuteros\Tests\Performance;

use Deuteros\PhpUnit\MockEntityDoubleFactory;
use PHPUnit\Framework\Attributes\Group;

/**
 * Performance benchmark using PHPUnit mock-based entity doubles.
 */
#[Group('deuteros')]
#[Group('performance')]
class PhpUnitNodeDoubleBenchmarkTest extends DoubleBenchmarkTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = MockEntityDoubleFactory::fromTest($this);
  }

}
