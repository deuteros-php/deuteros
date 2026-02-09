<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Entity\Prophecy;

use Deuteros\Entity\Prophecy\ProphecyServiceDoubler;
use Deuteros\Entity\ServiceDoublerInterface;
use Deuteros\Tests\Unit\Entity\ServiceDoublerTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests ProphecyServiceDoubler.
 */
#[CoversClass(ProphecyServiceDoubler::class)]
#[Group('deuteros')]
class ProphecyServiceDoublerTest extends ServiceDoublerTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function createServiceDoubler(): ServiceDoublerInterface {
    return new ProphecyServiceDoubler($this);
  }

}
