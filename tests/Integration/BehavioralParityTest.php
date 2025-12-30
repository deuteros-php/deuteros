<?php

declare(strict_types=1);

namespace Deuteros\Tests\Integration;

use Deuteros\Common\EntityDefinition;
use Deuteros\Common\EntityDoubleBuilder;
use Deuteros\Common\FieldDefinition;
use Deuteros\Common\FieldItemDoubleBuilder;
use Deuteros\Common\FieldItemListDoubleBuilder;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\MethodProphecy;

/**
 * Tests that PHPUnit and Prophecy adapters have identical behavior.
 *
 * These tests verify the critical requirement that both adapters produce
 * the same outputs for the same inputs.
 */
#[Group('deuteros')]
class BehavioralParityTest extends TestCase {
  use ProphecyTrait;

  /**
   * Tests that both adapters return identical entity metadata.
   */
  public function testMetadataParity(): void {
    $definition = [
      'entity_type' => 'node',
      'bundle' => 'article',
      'id' => 42,
      'uuid' => 'test-uuid',
      'label' => 'Test Label',
    ];

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    $this->assertSame($mock->getEntityTypeId(), $prophecy->getEntityTypeId());
    $this->assertSame($mock->bundle(), $prophecy->bundle());
    $this->assertSame($mock->id(), $prophecy->id());
    $this->assertSame($mock->uuid(), $prophecy->uuid());
    $this->assertSame($mock->label(), $prophecy->label());
  }

  /**
   * Tests that both adapters return identical field values.
   */
  public function testFieldValueParity(): void {
    $definition = [
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_text' => 'Test Value',
        'field_number' => 123,
        'field_ref' => ['target_id' => 42],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ];

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // Scalar field.
    $this->assertSame(
          $mock->get('field_text')->value,
          $prophecy->get('field_text')->value
      );

    // Number field.
    $this->assertSame(
          $mock->get('field_number')->value,
          $prophecy->get('field_number')->value
      );

    // Reference field.
    $this->assertSame(
          $mock->get('field_ref')->target_id,
          $prophecy->get('field_ref')->target_id
      );
  }

  /**
   * Tests that both adapters resolve callbacks identically.
   */
  public function testCallbackResolutionParity(): void {
    $definition = [
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_dynamic' => fn(array $context) => $context['value'] * 2,
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ];
    $context = ['value' => 21];

    $mock = $this->createMockDouble($definition, $context);
    $prophecy = $this->createProphecyDouble($definition, $context);

    $this->assertSame(42, $mock->get('field_dynamic')->value);
    $this->assertSame(42, $prophecy->get('field_dynamic')->value);
    $this->assertSame(
          $mock->get('field_dynamic')->value,
          $prophecy->get('field_dynamic')->value
      );
  }

  /**
   * Tests that both adapters handle multi-value fields identically.
   */
  public function testMultiValueFieldParity(): void {
    $definition = [
      'entity_type' => 'node',
      'bundle' => 'article',
      'fields' => [
        'field_tags' => [
                  ['target_id' => 1],
                  ['target_id' => 2],
                  ['target_id' => 3],
        ],
      ],
      'interfaces' => [FieldableEntityInterface::class],
    ];

    $mock = $this->createMockDouble($definition);
    $prophecy = $this->createProphecyDouble($definition);

    // First item.
    $this->assertSame(
          $mock->get('field_tags')->first()->target_id,
          $prophecy->get('field_tags')->first()->target_id
      );

    // Delta access.
    for ($i = 0; $i < 3; $i++) {
      $this->assertSame(
            $mock->get('field_tags')->get($i)->target_id,
            $prophecy->get('field_tags')->get($i)->target_id
        );
    }

    // Out of range.
    $this->assertNull($mock->get('field_tags')->get(99));
    $this->assertNull($prophecy->get('field_tags')->get(99));
  }

  /**
   * Tests that both adapters handle method overrides identically.
   */
  public function testMethodOverrideParity(): void {
    $timestamp = 1704067200;
    $definition = [
      'entity_type' => 'node',
      'bundle' => 'article',
      'interfaces' => [EntityChangedInterface::class],
      'method_overrides' => [
        'getChangedTime' => fn(array $context) => $context['time'],
        'setChangedTime' => fn() => NULL,
      ],
    ];
    $context = ['time' => $timestamp];

    $mock = $this->createMockDouble($definition, $context);
    $prophecy = $this->createProphecyDouble($definition, $context);

    $this->assertSame($timestamp, $mock->getChangedTime());
    $this->assertSame($timestamp, $prophecy->getChangedTime());
    $this->assertSame(
          $mock->getChangedTime(),
          $prophecy->getChangedTime()
      );
  }

  /**
   * Creates a PHPUnit mock double.
   *
   * This is a simplified version of the trait method for testing purposes.
   */
  private function createMockDouble(array $definition, array $context = []): EntityInterface {
    $entityDef = EntityDefinition::fromArray(array_merge(
          $definition,
          ['context' => array_merge($definition['context'] ?? [], $context)]
      ));

    $builder = new EntityDoubleBuilder($entityDef, NULL);
    $builder->setFieldListFactory(
          fn(string $fieldName, FieldDefinition $fieldDef, array $ctx) =>
                $this->createMockFieldItemList($fieldName, $fieldDef, $entityDef, $ctx)
      );

    $interfaces = $this->resolveInterfacesForMock($entityDef);

    $mock = count($interfaces) === 1
            ? $this->createMock($interfaces[0])
            : $this->createMockForIntersectionOfInterfaces($interfaces);
    $resolvers = $builder->getResolvers();

    $mock->method('id')->willReturnCallback(fn() => $resolvers['id']($entityDef->context));
    $mock->method('uuid')->willReturnCallback(fn() => $resolvers['uuid']($entityDef->context));
    $mock->method('label')->willReturnCallback(fn() => $resolvers['label']($entityDef->context));
    $mock->method('bundle')->willReturnCallback(fn() => $resolvers['bundle']($entityDef->context));
    $mock->method('getEntityTypeId')->willReturnCallback(fn() => $resolvers['getEntityTypeId']($entityDef->context));

    if ($entityDef->hasInterface(FieldableEntityInterface::class)) {
      $mock->method('hasField')->willReturnCallback(
            fn(string $fieldName) => $resolvers['hasField']($entityDef->context, $fieldName)
        );
      $mock->method('get')->willReturnCallback(
            fn(string $fieldName) => $resolvers['get']($entityDef->context, $fieldName)
        );
      // Note: __get is not declared in FieldableEntityInterface, so magic
      // property access ($entity->field_name) is not supported on interface
      // mocks.
    }

    foreach ($entityDef->methodOverrides as $method => $override) {
      $resolver = $builder->getMethodOverrideResolver($method);
      $mock->method($method)->willReturnCallback(
            fn(mixed ...$args) => $resolver($entityDef->context, ...$args)
        );
    }

    return $mock;
  }

  /**
   * Creates a Prophecy double.
   *
   * This is a simplified version of the trait method for testing purposes.
   */
  private function createProphecyDouble(array $definition, array $context = []): EntityInterface {
    $entityDef = EntityDefinition::fromArray(array_merge(
          $definition,
          ['context' => array_merge($definition['context'] ?? [], $context)]
      ));

    $builder = new EntityDoubleBuilder($entityDef, NULL);
    $builder->setFieldListFactory(
          fn(string $fieldName, FieldDefinition $fieldDef, array $ctx) =>
                $this->createProphecyFieldItemList($fieldName, $fieldDef, $entityDef, $ctx)
      );

    $interfaces = [EntityInterface::class];
    foreach ($entityDef->interfaces as $interface) {
      if (!in_array($interface, $interfaces, TRUE)) {
        $interfaces[] = $interface;
      }
    }

    $primaryInterface = array_shift($interfaces);
    $prophecy = $this->prophesize($primaryInterface);
    foreach ($interfaces as $interface) {
      $prophecy->willImplement($interface);
    }

    $resolvers = $builder->getResolvers();

    $prophecy->id()->will(fn() => $resolvers['id']($entityDef->context));
    $prophecy->uuid()->will(fn() => $resolvers['uuid']($entityDef->context));
    $prophecy->label()->will(fn() => $resolvers['label']($entityDef->context));
    $prophecy->bundle()->will(fn() => $resolvers['bundle']($entityDef->context));
    $prophecy->getEntityTypeId()->will(fn() => $resolvers['getEntityTypeId']($entityDef->context));

    if ($entityDef->hasInterface(FieldableEntityInterface::class)) {
      $prophecy->hasField(Argument::type('string'))->will(
            fn(array $args) => $resolvers['hasField']($entityDef->context, $args[0])
        );
      $prophecy->get(Argument::type('string'))->will(
            fn(array $args) => $resolvers['get']($entityDef->context, $args[0])
        );
      // Note: __get is not declared in FieldableEntityInterface, so magic
      // property access ($entity->field_name) is not supported on interface
      // prophecies.
    }

    foreach ($entityDef->methodOverrides as $method => $override) {
      $resolver = $builder->getMethodOverrideResolver($method);
      $prophecy->$method(Argument::cetera())->will(
            fn(array $args) => $resolver($entityDef->context, ...$args)
        );
    }

    return $prophecy->reveal();
  }

  /**
   * Creates a mock field item list.
   */
  private function createMockFieldItemList(
    string $fieldName,
    FieldDefinition $fieldDef,
    EntityDefinition $entityDef,
    array $context,
  ): FieldItemListInterface {
    $builder = new FieldItemListDoubleBuilder($fieldDef, $fieldName, FALSE);
    $builder->setFieldItemFactory(
          fn(int $delta, mixed $value, array $ctx) =>
                $this->createMockFieldItem($delta, $value, $fieldName, $ctx)
      );

    $mock = $this->createMock(FieldItemListInterface::class);
    $resolvers = $builder->getResolvers();

    $mock->method('first')->willReturnCallback(fn() => $resolvers['first']($context));
    $mock->method('isEmpty')->willReturnCallback(fn() => $resolvers['isEmpty']($context));
    $mock->method('getValue')->willReturnCallback(fn() => $resolvers['getValue']($context));
    $mock->method('get')->willReturnCallback(fn(int $delta) => $resolvers['get']($context, $delta));
    $mock->method('__get')->willReturnCallback(fn(string $property) => $resolvers['__get']($context, $property));

    return $mock;
  }

  /**
   * Creates a prophecy field item list.
   */
  private function createProphecyFieldItemList(
    string $fieldName,
    FieldDefinition $fieldDef,
    EntityDefinition $entityDef,
    array $context,
  ): FieldItemListInterface {
    $builder = new FieldItemListDoubleBuilder($fieldDef, $fieldName, FALSE);
    $builder->setFieldItemFactory(
          fn(int $delta, mixed $value, array $ctx) =>
                $this->createProphecyFieldItem($delta, $value, $fieldName, $ctx)
      );

    $prophecy = $this->prophesize(FieldItemListInterface::class);
    $resolvers = $builder->getResolvers();

    $prophecy->first()->will(fn() => $resolvers['first']($context));
    $prophecy->isEmpty()->will(fn() => $resolvers['isEmpty']($context));
    $prophecy->getValue()->will(fn() => $resolvers['getValue']($context));
    $prophecy->get(Argument::type('int'))->will(fn(array $args) => $resolvers['get']($context, $args[0]));

    // Manually add MethodProphecy for __get since Prophecy's ObjectProphecy
    // intercepts __get calls instead of treating them as method stubs.
    $getMethodProphecy = new MethodProphecy($prophecy, '__get', [Argument::type('string')]);
    $getMethodProphecy->will(fn(array $args) => $resolvers['__get']($context, $args[0]));
    $prophecy->addMethodProphecy($getMethodProphecy);

    return $prophecy->reveal();
  }

  /**
   * Creates a mock field item.
   */
  private function createMockFieldItem(
    int $delta,
    mixed $value,
    string $fieldName,
    array $context,
  ): FieldItemInterface {
    $builder = new FieldItemDoubleBuilder($value, $delta, $fieldName, FALSE);
    $mock = $this->createMock(FieldItemInterface::class);
    $resolvers = $builder->getResolvers();

    $mock->method('__get')->willReturnCallback(fn(string $property) => $resolvers['__get']($context, $property));
    $mock->method('getValue')->willReturnCallback(fn() => $resolvers['getValue']($context));
    $mock->method('isEmpty')->willReturnCallback(fn() => $resolvers['isEmpty']($context));

    return $mock;
  }

  /**
   * Creates a prophecy field item.
   */
  private function createProphecyFieldItem(
    int $delta,
    mixed $value,
    string $fieldName,
    array $context,
  ): FieldItemInterface {
    $builder = new FieldItemDoubleBuilder($value, $delta, $fieldName, FALSE);
    $prophecy = $this->prophesize(FieldItemInterface::class);
    $resolvers = $builder->getResolvers();

    // Manually add MethodProphecy for __get since Prophecy's ObjectProphecy
    // intercepts __get calls instead of treating them as method stubs.
    $getMethodProphecy = new MethodProphecy($prophecy, '__get', [Argument::type('string')]);
    $getMethodProphecy->will(fn(array $args) => $resolvers['__get']($context, $args[0]));
    $prophecy->addMethodProphecy($getMethodProphecy);

    $prophecy->getValue()->will(fn() => $resolvers['getValue']($context));
    $prophecy->isEmpty()->will(fn() => $resolvers['isEmpty']($context));

    return $prophecy->reveal();
  }

  /**
   * Resolves interfaces for mocking, handling deduplication.
   *
   * PHPUnit cannot mock intersection of interfaces that share a common parent
   * (e.g., FieldableEntityInterface and EntityChangedInterface both extend
   * EntityInterface). This method detects and resolves such conflicts.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   *
   * @return array<class-string>
   *   The deduplicated interfaces to mock.
   */
  private function resolveInterfacesForMock(EntityDefinition $definition): array {
    $interfaces = $definition->interfaces;

    if (empty($interfaces)) {
      return [EntityInterface::class];
    }

    // Filter out parent interfaces when child interfaces are also present.
    $filtered = [];
    foreach ($interfaces as $interface) {
      $isParent = FALSE;
      foreach ($interfaces as $other) {
        if ($interface !== $other && is_a($other, $interface, TRUE)) {
          $isParent = TRUE;
          break;
        }
      }
      if (!$isParent) {
        $filtered[] = $interface;
      }
    }

    // Handle case where multiple interfaces extend EntityInterface.
    // PHPUnit cannot mock their intersection due to duplicate methods.
    if (count($filtered) > 1) {
      $entityChildren = [];
      foreach ($filtered as $interface) {
        if (is_a($interface, EntityInterface::class, TRUE)) {
          $entityChildren[] = $interface;
        }
      }

      if (count($entityChildren) > 1) {
        // Prefer FieldableEntityInterface if present.
        $preferred = in_array(FieldableEntityInterface::class, $entityChildren, TRUE)
                    ? FieldableEntityInterface::class
                    : $entityChildren[0];
        $filtered = array_filter($filtered, function ($interface) use ($entityChildren, $preferred) {
            return !in_array($interface, $entityChildren, TRUE) || $interface === $preferred;
        });
        $filtered = array_values($filtered);
      }
    }

    // Ensure EntityInterface functionality is covered.
    $hasEntityChild = FALSE;
    foreach ($filtered as $interface) {
      if (is_a($interface, EntityInterface::class, TRUE)) {
        $hasEntityChild = TRUE;
        break;
      }
    }
    if (!$hasEntityChild) {
      array_unshift($filtered, EntityInterface::class);
    }

    return $filtered;
  }

}
