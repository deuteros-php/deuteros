<?php

declare(strict_types=1);

namespace Deuteros\Tests\Performance;

use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Performance benchmark using Prophecy-based entity doubles.
 */
#[Group('deuteros')]
#[Group('performance')]
class ProphecyNodeBenchmarkTest extends BenchmarkTestBase {

  use ProphecyTrait;
  use NodeOperationsBenchmarkTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = new ProphecyEntityDoubleFactory($this->getProphet());
  }

}
