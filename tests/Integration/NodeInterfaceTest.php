<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration;

use Deuteros\Common\EntityDoubleDefinitionBuilder;
use Deuteros\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests fromInterface() with NodeInterface.
 *
 * This test verifies that the full NodeInterface hierarchy is properly
 * detected and all ancestor interfaces are included.
 */
#[Group('deuteros')]
class NodeInterfaceTest extends TestCase {

  use ProphecyTrait;

  /**
   * Tests fromInterface() detects full NodeInterface hierarchy.
   */
  public function testNodeInterfaceHierarchyDetection(): void {
    $definition = EntityDoubleDefinitionBuilder::fromInterface(
      'node',
      NodeInterface::class
    )->build();

    // NodeInterface itself.
    $this->assertContains(NodeInterface::class, $definition->interfaces);

    // ContentEntityInterface (parent of NodeInterface).
    $this->assertContains(ContentEntityInterface::class, $definition->interfaces);

    // FieldableEntityInterface (parent of ContentEntityInterface).
    $this->assertContains(FieldableEntityInterface::class, $definition->interfaces);

    // EntityInterface (root).
    $this->assertContains(EntityInterface::class, $definition->interfaces);

    // EntityChangedInterface (NodeInterface extends this).
    $this->assertContains(EntityChangedInterface::class, $definition->interfaces);

    // EntityOwnerInterface (NodeInterface extends this).
    $this->assertContains(EntityOwnerInterface::class, $definition->interfaces);

    // EntityPublishedInterface (NodeInterface extends this).
    $this->assertContains(EntityPublishedInterface::class, $definition->interfaces);

    // Traversable should be kept for foreach support.
    $this->assertContains(\Traversable::class, $definition->interfaces);
  }

  /**
   * Tests creating a NodeInterface double with PHPUnit mocks.
   */
  public function testNodeInterfaceWithPhpUnit(): void {
    $factory = MockEntityDoubleFactory::fromTest($this);

    $node = $factory->create(
      EntityDoubleDefinitionBuilder::fromInterface('node', NodeInterface::class)
        ->bundle('article')
        ->id(42)
        ->label('Test Article')
        ->methodOverride('getTitle', fn() => 'Test Article Title')
        ->methodOverride('isPublished', fn() => TRUE)
        ->methodOverride('getCreatedTime', fn() => 1234567890)
        ->field('body', 'Article body content')
        ->build()
    );

    // Core EntityInterface methods.
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('node', $node->getEntityTypeId());
    $this->assertSame('article', $node->bundle());
    $this->assertSame(42, $node->id());

    // NodeInterface-specific methods.
    $this->assertSame('Test Article Title', $node->getTitle());
    $this->assertTrue($node->isPublished());
    $this->assertSame(1234567890, $node->getCreatedTime());

    // Field access.
    $this->assertSame('Article body content', $node->get('body')->value);
  }

  /**
   * Tests creating a NodeInterface double with Prophecy.
   */
  public function testNodeInterfaceWithProphecy(): void {
    $factory = ProphecyEntityDoubleFactory::fromTest($this);

    $node = $factory->create(
      EntityDoubleDefinitionBuilder::fromInterface('node', NodeInterface::class)
        ->bundle('article')
        ->id(42)
        ->label('Test Article')
        ->methodOverride('getTitle', fn() => 'Test Article Title')
        ->methodOverride('isPublished', fn() => TRUE)
        ->methodOverride('getCreatedTime', fn() => 1234567890)
        ->field('body', 'Article body content')
        ->build()
    );

    // Core EntityInterface methods.
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('node', $node->getEntityTypeId());
    $this->assertSame('article', $node->bundle());
    $this->assertSame(42, $node->id());

    // NodeInterface-specific methods.
    $this->assertSame('Test Article Title', $node->getTitle());
    $this->assertTrue($node->isPublished());
    $this->assertSame(1234567890, $node->getCreatedTime());

    // Field access.
    $this->assertSame('Article body content', $node->get('body')->value);
  }

  /**
   * Tests lenient mode with NodeInterface.
   */
  public function testNodeInterfaceLenientMode(): void {
    $factory = MockEntityDoubleFactory::fromTest($this);

    $node = $factory->create(
      EntityDoubleDefinitionBuilder::fromInterface('node', NodeInterface::class)
        ->bundle('article')
        ->lenient()
        ->build()
    );
    $this->assertInstanceOf(NodeInterface::class, $node);

    // Unconfigured NodeInterface methods should return null.
    $this->assertNull($node->getTitle());
    $this->assertNull($node->isPublished());
    $this->assertNull($node->getCreatedTime());
    $this->assertNull($node->isPromoted());
    $this->assertNull($node->isSticky());

    // Unsupported methods should also return null in lenient mode.
    // PHPStan: save() returns int per PHPDoc, but in lenient mode we return
    // null. This is intentional - we're testing our mock behavior.
    /** @phpstan-ignore method.impossibleType */
    $this->assertNull($node->save());
    $this->assertNull($node->delete());
  }

}
