<?php

declare(strict_types=1);

namespace Deuteros\Tests\Performance;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

// Skip this file entirely when EntityKernelTestBase is not available.
// This occurs when using production composer (stubs only, no Drupal core).
if (!class_exists(EntityKernelTestBase::class)) {
  // Define a placeholder class so PHPUnit doesn't error on missing class.
  // phpcs:ignore Drupal.Classes.ClassDeclaration
  class KernelNodeBenchmarkTest extends TestCase {

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
 * Performance benchmark using Drupal Kernel test infrastructure.
 *
 * This test measures the overhead of the Drupal service container,
 * entity type manager, and entity creation without database persistence.
 */
#[Group('deuteros')]
#[Group('performance')]
class KernelNodeBenchmarkTest extends EntityKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeOperationsBenchmarkTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['node']);
    $this->createContentType(['type' => 'article']);

    // Create field_tags (entity reference, multi-value).
    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Tags',
    ])->save();

    // Create field_author (entity reference, single).
    FieldStorageConfig::create([
      'field_name' => 'field_author',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => ['target_type' => 'user'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_author',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Author',
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createBenchmarkNode(): NodeInterface {
    // Note: NOT saving - just creating entity object to measure creation
    // overhead without database operations.
    return Node::create([
      'type' => 'article',
      'title' => 'Benchmark Node',
      'uid' => 1,
      'body' => [['value' => 'Body text content', 'format' => 'plain_text']],
      'field_tags' => [['target_id' => 1], ['target_id' => 2]],
      'field_author' => ['target_id' => 100],
    ]);
  }

}
