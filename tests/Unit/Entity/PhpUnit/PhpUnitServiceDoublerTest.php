<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Entity\PhpUnit;

use Deuteros\Entity\PhpUnit\PhpUnitServiceDoubler;
use Deuteros\Entity\ServiceDoublerInterface;
use Deuteros\Tests\Unit\Entity\ServiceDoublerTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests PhpUnitServiceDoubler.
 */
#[CoversClass(PhpUnitServiceDoubler::class)]
#[Group('deuteros')]
class PhpUnitServiceDoublerTest extends ServiceDoublerTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createServiceDoubler(): ServiceDoublerInterface {
    return new PhpUnitServiceDoubler($this);
  }

}
