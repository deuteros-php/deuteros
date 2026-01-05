<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Entity\PhpUnit;

use Deuteros\Entity\EntityTestHelper;
use Deuteros\Entity\PhpUnit\PhpUnitServiceMocker;
use Deuteros\Tests\Integration\Entity\EntityTestHelperTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests EntityTestHelper with PHPUnit mock adapter.
 */
#[CoversClass(EntityTestHelper::class)]
#[CoversClass(PhpUnitServiceMocker::class)]
#[Group('deuteros')]
class EntityTestHelperPhpUnitTest extends EntityTestHelperTestBase {

  // All tests are inherited from EntityTestHelperTestBase.
  // This class exists to run the base tests with PHPUnit mocks.
}
