<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Entity\PhpUnit;

use Deuteros\Entity\PhpUnit\PhpUnitServiceDoubler;
use Deuteros\Entity\SubjectEntityFactory;
use Deuteros\Tests\Integration\Entity\SubjectEntityFactoryTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SubjectEntityFactory with PHPUnit adapter.
 */
#[CoversClass(SubjectEntityFactory::class)]
#[CoversClass(PhpUnitServiceDoubler::class)]
#[Group('deuteros')]
class SubjectEntityFactoryPhpUnitTest extends SubjectEntityFactoryTestBase {

  // All tests are inherited from SubjectEntityFactoryTestBase.
  // This class exists to run the base tests with PHPUnit doubles.
}
