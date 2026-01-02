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
class ProphecyNodeDoubleBenchmarkTest extends DoubleBenchmarkTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = ProphecyEntityDoubleFactory::fromTest($this);
  }

}
