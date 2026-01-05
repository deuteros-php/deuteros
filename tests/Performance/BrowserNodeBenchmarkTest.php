<?php

declare(strict_types=1);

namespace Deuteros\Tests\Performance;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

// Skip this file entirely when EntityKernelTestBase is not available.
// This occurs when using production composer (stubs only, no Drupal core).
if (!class_exists(EntityKernelTestBase::class)) {
  // Define a placeholder class so PHPUnit doesn't error on missing class.
  // phpcs:ignore Drupal.Classes.ClassDeclaration
  class BrowserNodeBenchmarkTest extends TestCase {

    /**
     * Skip marker test when Drupal core is not available.
     */
    public function testSkipped(): void {
      $this->markTestSkipped('Drupal core is not available.');
    }

  }
  return;
}

/**
 * Performance benchmark using Drupal Browser test infrastructure.
 *
 * This test measures the overhead of the setting up a test using a minimal
 * Drupal installation.
 */
#[Group('deuteros')]
#[Group('performance')]
class BrowserNodeBenchmarkTest extends BrowserTestBase {

  use DrupalTestTrait {
    setUp as traitSetup;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->traitSetup();
  }

}
