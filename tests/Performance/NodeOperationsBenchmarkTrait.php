<?php

declare(strict_types=1);

namespace Deuteros\Tests\Performance;

use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Provides a benchmark test for node entity operations.
 *
 * This trait defines a data-driven test that runs comprehensive node
 * operations multiple times. Each test implementation provides its own
 * entity creation mechanism via createBenchmarkNode().
 */
trait NodeOperationsBenchmarkTrait {

  /**
   * Data provider that generates empty parameter sets for iteration.
   *
   * @return array<int, array<int, mixed>>
   *   An array of empty parameter arrays.
   */
  public static function iterationProvider(): array {
    $iterationCount = getenv('DEUTEROS_ITERATION_COUNT') ?: 1;
    return array_fill(0, (int) $iterationCount, []);
  }

  /**
   * Benchmark test that creates and operates on a node entity.
   */
  #[DataProvider('iterationProvider')]
  public function testNodeOperations(): void {
    $node = $this->createBenchmarkNode();
    $this->performNodeOperations($node);
    // Minimal assertion to avoid "risky test" warning.
    // @phpstan-ignore-next-line
    $this->assertTrue(TRUE);
  }

  /**
   * Creates a benchmark node entity.
   *
   * Each test implementation provides its own mechanism:
   * - Deuteros tests use entity doubles
   * - Kernel test uses Node::create()
   *
   * @return \Drupal\node\NodeInterface
   *   A node entity for benchmarking.
   */
  abstract protected function createBenchmarkNode(): NodeInterface;

  /**
   * Performs comprehensive operations on a node entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to operate on.
   */
  protected function performNodeOperations(NodeInterface $node): void {
    // Metadata access.
    $node->id();
    $node->uuid();
    $node->bundle();
    $node->label();
    $node->getEntityTypeId();

    // Node-specific methods.
    $node->getTitle();
    $node->isPublished();
    $node->getCreatedTime();
    $node->isPromoted();
    $node->isSticky();
    $node->getOwnerId();

    // Field access patterns.
    $node->get('body');
    $node->get('body')->value;
    $node->get('body')->getValue();
    $node->hasField('body');
    $node->hasField('nonexistent');

    // Multi-value field access.
    $node->get('field_tags')->first();
    $node->get('field_tags')->get(0);
    $node->get('field_tags')->isEmpty();

    // Entity reference access.
    $node->get('field_author')->target_id;
  }

}
