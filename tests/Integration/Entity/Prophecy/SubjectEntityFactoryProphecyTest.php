<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Entity\Prophecy;

use Deuteros\Entity\Prophecy\ProphecyServiceDoubler;
use Deuteros\Entity\SubjectEntityFactory;
use Deuteros\Tests\Integration\Entity\SubjectEntityFactoryTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests SubjectEntityFactory with Prophecy adapter.
 */
#[CoversClass(SubjectEntityFactory::class)]
#[CoversClass(ProphecyServiceDoubler::class)]
#[Group('deuteros')]
class SubjectEntityFactoryProphecyTest extends SubjectEntityFactoryTestBase {

  use ProphecyTrait;

  // All tests are inherited from SubjectEntityFactoryTestBase.
  // ProphecyTrait enables auto-detection of Prophecy in ::fromTest.
}
