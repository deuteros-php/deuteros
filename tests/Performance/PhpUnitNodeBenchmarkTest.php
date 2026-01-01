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
class PhpUnitNodeBenchmarkTest extends BenchmarkTestBase {

  use NodeOperationsBenchmarkTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = new MockEntityDoubleFactory($this);
  }

}
