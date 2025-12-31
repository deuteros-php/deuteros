<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\PhpUnit;

use Deuteros\Common\EntityDoubleFactoryInterface;
use Deuteros\PhpUnit\MockEntityDoubleFactory;
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
  protected function createFactory(): EntityDoubleFactoryInterface {
    return new MockEntityDoubleFactory($this);
  }

}
