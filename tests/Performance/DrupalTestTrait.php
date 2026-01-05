<?php

declare(strict_types=1);

namespace Deuteros\Tests\Performance;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Provides common setup for Drupal tests performing node entity operations.
 */
trait DrupalTestTrait {

  use ContentTypeCreationTrait;
  use NodeOperationsBenchmarkTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
