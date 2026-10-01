<?php

declare(strict_types=1);

namespace Deuteros\Tests\Contract\Double\PhpUnit;

use Deuteros\Double\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Tests\Contract\Double\EntityDoubleContractTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the entity contract against PHPUnit entity doubles.
 */
#[CoversClass(MockEntityDoubleFactory::class)]
#[Group('deuteros')]
#[Group('contract')]
class MockEntityDoubleContractTest extends EntityDoubleContractTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFactoryClass(): string {
    return MockEntityDoubleFactory::class;
  }

}
