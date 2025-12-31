<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration\Prophecy;

use Deuteros\Common\EntityDoubleFactoryInterface;
use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use Deuteros\Tests\Integration\EntityDoubleFactoryTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Integration tests for the Prophecy ProphecyEntityDoubleFactory.
 */
#[CoversClass(ProphecyEntityDoubleFactory::class)]
#[Group('deuteros')]
class ProphecyEntityDoubleFactoryTest extends EntityDoubleFactoryTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function createFactory(): EntityDoubleFactoryInterface {
    return new ProphecyEntityDoubleFactory($this->getProphet());
  }

}
