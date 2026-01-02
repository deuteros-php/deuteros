<?php

declare(strict_types=1);

namespace Deuteros\Tests\Performance;

use Deuteros\Common\EntityDoubleDefinition;
use Deuteros\Common\EntityDoubleDefinitionBuilder;
use Deuteros\Common\EntityDoubleFactoryInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Performance benchmark using PHPUnit mock-based entity doubles.
 */
abstract class DoubleBenchmarkTestBase extends TestCase {

  use NodeOperationsBenchmarkTrait;

  /**
   * The entity double factory.
   */
  protected EntityDoubleFactoryInterface $factory;

  /**
   * {@inheritdoc}
   */
  protected function createBenchmarkNode(): NodeInterface {
    $node = $this->factory->create($this->buildNodeDefinition());
    assert($node instanceof NodeInterface);
    return $node;
  }

  /**
   * Builds the node entity definition for benchmarking.
   *
   * @return \Deuteros\Common\EntityDoubleDefinition
   *   The entity double definition.
   */
  private function buildNodeDefinition(): EntityDoubleDefinition {
    return EntityDoubleDefinitionBuilder::fromInterface('node', NodeInterface::class)
      ->bundle('article')
      ->id(42)
      ->uuid('benchmark-uuid-1234')
      ->label('Benchmark Node')
      ->field('body', ['value' => 'Body text content', 'format' => 'plain'])
      ->field('field_tags', [['target_id' => 1], ['target_id' => 2]])
      ->field('field_author', ['target_id' => 100])
      ->method('getTitle', fn() => 'Benchmark Node')
      ->method('isPublished', fn() => TRUE)
      ->method('getCreatedTime', fn() => 1700000000)
      ->method('isPromoted', fn() => FALSE)
      ->method('isSticky', fn() => FALSE)
      ->method('getOwnerId', fn() => 100)
      ->build();
  }

}
