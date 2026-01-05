<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Entity\Prophecy;

use Deuteros\Entity\EntityTestHelper;
use Deuteros\Entity\Prophecy\ProphecyServiceMocker;
use Deuteros\Tests\Integration\Entity\EntityTestHelperTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests EntityTestHelper with Prophecy mock adapter.
 */
#[CoversClass(EntityTestHelper::class)]
#[CoversClass(ProphecyServiceMocker::class)]
#[Group('deuteros')]
class EntityTestHelperProphecyTest extends EntityTestHelperTestBase {

  use ProphecyTrait;

  // All tests are inherited from EntityTestHelperTestBase.
  // ProphecyTrait enables auto-detection of Prophecy in fromTest().
}
