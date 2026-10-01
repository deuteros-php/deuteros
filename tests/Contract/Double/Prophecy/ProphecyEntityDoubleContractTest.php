<?php

declare(strict_types=1);

namespace Deuteros\Tests\Contract\Double\Prophecy;

use Deuteros\Double\Prophecy\ProphecyEntityDoubleFactory;
use Deuteros\Tests\Contract\Double\EntityDoubleContractTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Runs the entity contract against Prophecy entity doubles.
 */
#[CoversClass(ProphecyEntityDoubleFactory::class)]
#[Group('deuteros')]
#[Group('contract')]
class ProphecyEntityDoubleContractTest extends EntityDoubleContractTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function getFactoryClass(): string {
    return ProphecyEntityDoubleFactory::class;
  }

}
