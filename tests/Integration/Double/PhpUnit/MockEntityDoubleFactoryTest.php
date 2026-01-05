<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Double\PhpUnit;

use Deuteros\Double\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Tests\Integration\EntityDoubleFactoryTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for the PHPUnit MockEntityDoubleFactory.
 */
#[CoversClass(MockEntityDoubleFactory::class)]
#[Group('deuteros')]
class MockEntityDoubleFactoryTest extends EntityDoubleFactoryTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getClassName(): string {
    return MockEntityDoubleFactory::class;
  }

}
