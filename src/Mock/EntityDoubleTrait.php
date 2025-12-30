<?php

declare(strict_types=1);

namespace Deuteros\Mock;

use Deuteros\Common\EntityDefinition;
use Deuteros\Common\EntityDefinitionNormalizerTrait;
use Deuteros\Common\EntityDoubleBuilder;
use Deuteros\Common\FieldDefinition;
use Deuteros\Common\FieldItemDoubleBuilder;
use Deuteros\Common\FieldItemListDoubleBuilder;
use Deuteros\Common\GuardrailEnforcer;
use Deuteros\Common\MutableStateContainer;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Trait for creating entity doubles using PHPUnit native mock objects.
 *
 * DEUTEROS - Drupal Entity Unit Test Extensible Replacement Object Scaffolding.
 *
 * This trait provides value-object entity doubles for Drupal unit tests.
 * It allows testing code that depends on entity and field interfaces without
 * requiring Kernel tests, module enablement, storage, or services.
 *
 * Supported behaviors:
 * - Scalar and callback-based field values
 * - Multi-value field access via get(int $delta)
 * - Custom interfaces via the 'interfaces' array
 * - Method overrides for custom behavior
 * - Context propagation to callbacks
 * - Mutable doubles for testing entity modifications
 *
 * Explicitly unsupported behaviors (will throw):
 * - save(), delete() - requires entity storage
 * - access() - requires access control services
 * - getTranslation() - requires translation services
 * - toUrl() - requires routing services
 * - Entity reference traversal
 *
 * This is a unit-test value object only. Use Kernel tests for behaviors
 * that require runtime services.
 *
 * @example Static values
 * ```php
 * $entity = $this->createEntityDouble([
 *     'entity_type' => 'node',
 *     'bundle' => 'article',
 *     'id' => 1,
 *     'fields' => [
 *         'field_title' => 'Test Article',
 *     ],
 *     'interfaces' => [FieldableEntityInterface::class],
 * ]);
 * $this->assertSame('Test Article', $entity->field_title->value);
 * ```
 *
 * @example Callback-based resolution
 * ```php
 * $entity = $this->createEntityDouble([
 *     'entity_type' => 'node',
 *     'bundle' => 'article',
 *     'fields' => [
 *         'field_date' => fn($context) => $context['date'],
 *     ],
 *     'interfaces' => [FieldableEntityInterface::class],
 * ], ['date' => '2024-01-01']);
 * $this->assertSame('2024-01-01', $entity->field_date->value);
 * ```
 *
 * @mixin TestCase
 */
trait EntityDoubleTrait {
  use EntityDefinitionNormalizerTrait;

  /**
   * Creates an immutable entity double.
   *
   * Field values cannot be changed after creation.
   *
   * @param array<string, mixed> $definition
   *   The entity definition array.
   * @param array<string, mixed> $context
   *   Context data for callback resolution.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  protected function createEntityDouble(
    array $definition,
    array $context = [],
  ): EntityInterface {
    return $this->buildEntityDouble(
          $this->normalizeDefinition($definition, $context, FALSE)
      );
  }

  /**
   * Creates a mutable entity double.
   *
   * Field values can be updated via set() methods for assertion purposes.
   *
   * @param array<string, mixed> $definition
   *   The entity definition array.
   * @param array<string, mixed> $context
   *   Context data for callback resolution.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The mutable entity double.
   */
  protected function createMutableEntityDouble(
    array $definition,
    array $context = [],
  ): EntityInterface {
    return $this->buildEntityDouble(
          $this->normalizeDefinition($definition, $context, TRUE)
      );
  }

  /**
   * Builds an entity double from a normalized definition.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The normalized entity definition.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity double.
   */
  private function buildEntityDouble(EntityDefinition $definition): EntityInterface {
    // Determine interfaces to mock.
    $interfaces = $this->resolveInterfaces($definition);

    // Create mutable state container if needed.
    $mutableState = $definition->mutable ? new MutableStateContainer() : NULL;

    // Create the builder.
    $builder = new EntityDoubleBuilder($definition, $mutableState);

    // Set up field list factory.
    $builder->setFieldListFactory(
          fn(string $fieldName, FieldDefinition $fieldDef, array $context) =>
                $this->createFieldItemListDouble($fieldName, $fieldDef, $definition, $mutableState, $context)
      );

    // Create the mock using mock builder for multiple interfaces.
    /** @var \Drupal\Core\Entity\EntityInterface&MockObject $mock */
    $mock = $this->createMockForInterfaces($interfaces);

    // Wire up resolvers.
    $this->wireEntityResolvers($mock, $builder, $definition);

    return $mock;
  }

  /**
   * Creates a mock implementing multiple interfaces.
   *
   * @param list<class-string> $interfaces
   *   The interfaces to implement.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mock object.
   */
  private function createMockForInterfaces(array $interfaces): MockObject {
    if (count($interfaces) === 1) {
      return $this->createMock($interfaces[0]);
    }

    // PHPUnit 10.1+ supports createMockForIntersectionOfInterfaces.
    // @phpstan-ignore-next-line
    return $this->createMockForIntersectionOfInterfaces($interfaces);
  }

  /**
   * Resolves the interfaces to mock.
   *
   * Deduplicates interfaces to avoid PHPUnit errors when interfaces
   * extend each other (e.g., FieldableEntityInterface extends EntityInterface).
   *
   * When multiple interfaces share a common parent (like EntityInterface),
   * PHPUnit's createMockForIntersectionOfInterfaces fails. In such cases,
   * we prioritize FieldableEntityInterface over other EntityInterface children.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   *
   * @return list<class-string>
   *   The interfaces to mock.
   */
  private function resolveInterfaces(EntityDefinition $definition): array {
    // Collect all declared interfaces.
    $interfaces = $definition->interfaces;

    // If no interfaces declared, just use EntityInterface.
    if (empty($interfaces)) {
      return [EntityInterface::class];
    }

    // Filter out interfaces that are parents of other interfaces in the list.
    // This avoids PHPUnit errors about duplicate methods.
    $filtered = [];
    foreach ($interfaces as $interface) {
      $isParent = FALSE;
      foreach ($interfaces as $other) {
        if ($interface !== $other && is_a($other, $interface, TRUE)) {
          // $interface is a parent of $other, skip it.
          $isParent = TRUE;
          break;
        }
      }
      if (!$isParent) {
        $filtered[] = $interface;
      }
    }

    // PHPUnit can't mock intersection of interfaces that share a common
    // parent interface (they'd have duplicate method signatures).
    // If we have multiple EntityInterface children, keep only
    // FieldableEntityInterface if present, as it's the most feature-rich.
    if (count($filtered) > 1) {
      $entityChildren = [];
      foreach ($filtered as $interface) {
        if (is_a($interface, EntityInterface::class, TRUE)) {
          $entityChildren[] = $interface;
        }
      }

      // If multiple interfaces extend EntityInterface, we can only mock one.
      if (count($entityChildren) > 1) {
        // Prefer FieldableEntityInterface as it's the most comprehensive.
        $preferred = in_array(FieldableEntityInterface::class, $entityChildren, TRUE)
                    ? FieldableEntityInterface::class
                    : $entityChildren[0];

        // Keep only the preferred interface from EntityInterface children.
        $filtered = array_filter($filtered, function ($interface) use ($entityChildren, $preferred) {
            return !in_array($interface, $entityChildren, TRUE) || $interface === $preferred;
        });
        $filtered = array_values($filtered);
      }
    }

    // If EntityInterface is not covered by any declared interface, add it.
    $coversEntity = FALSE;
    foreach ($filtered as $interface) {
      if (is_a($interface, EntityInterface::class, TRUE)) {
        $coversEntity = TRUE;
        break;
      }
    }
    if (!$coversEntity) {
      array_unshift($filtered, EntityInterface::class);
    }

    return $filtered;
  }

  /**
   * Wires entity resolvers to the mock.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject $mock
   *   The mock object.
   * @param \Deuteros\Common\EntityDoubleBuilder $builder
   *   The entity double builder.
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   */
  private function wireEntityResolvers(
    MockObject $mock,
    EntityDoubleBuilder $builder,
    EntityDefinition $definition,
  ): void {
    $resolvers = $builder->getResolvers();
    $context = $definition->context;

    // Helper to wire a method, checking for overrides first.
    $wireMethod = function (string $method, callable $defaultResolver) use ($mock, $builder, $definition, $context) {
      if ($definition->hasMethodOverride($method)) {
        $resolver = $builder->getMethodOverrideResolver($method);
        $mock->method($method)->willReturnCallback(
              fn(mixed ...$args) => $resolver($context, ...$args)
          );
      }
      else {
        $mock->method($method)->willReturnCallback($defaultResolver);
      }
    };

    // Wire core entity methods (checking for overrides).
    $wireMethod('id', fn() => $resolvers['id']($context));
    $wireMethod('uuid', fn() => $resolvers['uuid']($context));
    $wireMethod('label', fn() => $resolvers['label']($context));
    $wireMethod('bundle', fn() => $resolvers['bundle']($context));
    $wireMethod('getEntityTypeId', fn() => $resolvers['getEntityTypeId']($context));

    // Wire fieldable entity methods if applicable.
    if ($definition->hasInterface(FieldableEntityInterface::class)) {
      $wireMethod('hasField', fn(string $fieldName) => $resolvers['hasField']($context, $fieldName));
      $wireMethod('get', fn(string $fieldName) => $resolvers['get']($context, $fieldName));
      // Note: __get is not declared in FieldableEntityInterface, so we can't
      // mock it on interface mocks. Use get() method instead.
      // For property access syntax ($entity->field_name), use Prophecy adapter.
      if (!$definition->hasMethodOverride('set')) {
        if ($definition->mutable) {
          $self = $mock;
          $mock->method('set')->willReturnCallback(
                function (string $fieldName, mixed $value, bool $notify = TRUE) use ($resolvers, $context, $self) {
                    $resolvers['set']($context, $fieldName, $value, $notify);
                    return $self;
                }
            );
        }
        else {
          $mock->method('set')->willReturnCallback(
                  function (string $fieldName) {
                      throw new \LogicException(
                          "Cannot modify field '$fieldName' on immutable entity double. "
                          . "Use createMutableEntityDouble() if you need to test mutations."
                      );
                  }
                      );
        }
      }
      else {
        $resolver = $builder->getMethodOverrideResolver('set');
        $mock->method('set')->willReturnCallback(
                fn(mixed ...$args) => $resolver($context, ...$args)
                  );
      }
    }

    // Wire remaining method overrides (those not already wired above).
    $coreMethodsWired = ['id', 'uuid', 'label', 'bundle', 'getEntityTypeId', 'hasField', 'get', 'set'];
    foreach ($definition->methodOverrides as $method => $override) {
      if (in_array($method, $coreMethodsWired, TRUE)) {
        // Already handled above.
        continue;
      }
      $resolver = $builder->getMethodOverrideResolver($method);
      $mock->method($method)->willReturnCallback(
            fn(mixed ...$args) => $resolver($context, ...$args)
        );
    }

    // Wire guardrails for unsupported methods.
    $this->wireGuardrails($mock, $definition);
  }

  /**
   * Wires guardrail exceptions to the mock.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject $mock
   *   The mock object.
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   */
  private function wireGuardrails(MockObject $mock, EntityDefinition $definition): void {
    $unsupportedMethods = GuardrailEnforcer::getUnsupportedMethods();

    foreach ($unsupportedMethods as $method => $reason) {
      // Skip if there's an override.
      if ($definition->hasMethodOverride($method)) {
        continue;
      }

      // Check if the method exists on any declared interface.
      $methodExists = FALSE;
      foreach ($this->resolveInterfaces($definition) as $interface) {
        if (method_exists($interface, $method)) {
          $methodExists = TRUE;
          break;
        }
      }

      if ($methodExists) {
        $mock->method($method)->willReturnCallback(
              fn() => throw GuardrailEnforcer::createUnsupportedMethodException($method)
          );
      }
    }
  }

  /**
   * Creates a field item list double.
   *
   * @param string $fieldName
   *   The field name.
   * @param \Deuteros\Common\FieldDefinition $fieldDef
   *   The field definition.
   * @param \Deuteros\Common\EntityDefinition $entityDef
   *   The entity definition.
   * @param \Deuteros\Common\MutableStateContainer|null $mutableState
   *   The mutable state container.
   * @param array<string, mixed> $context
   *   The context.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The field item list double.
   */
  private function createFieldItemListDouble(
    string $fieldName,
    FieldDefinition $fieldDef,
    EntityDefinition $entityDef,
    ?MutableStateContainer $mutableState,
    array $context,
  ): FieldItemListInterface {
    $builder = new FieldItemListDoubleBuilder($fieldDef, $fieldName, $entityDef->mutable);

    // Set up field item factory.
    $builder->setFieldItemFactory(
          fn(int $delta, mixed $value, array $ctx) =>
                $this->createFieldItemDouble($delta, $value, $fieldName, $entityDef->mutable, $ctx)
      );

    // Set up mutable state updater if applicable.
    if ($mutableState !== NULL) {
      $builder->setMutableStateUpdater(
            fn(string $name, mixed $value) => $mutableState->setFieldValue($name, $value)
        );
    }

    /** @var \Drupal\Core\Field\FieldItemListInterface&MockObject $mock */
    $mock = $this->createMock(FieldItemListInterface::class);

    $resolvers = $builder->getResolvers();

    $mock->method('first')->willReturnCallback(fn() => $resolvers['first']($context));
    $mock->method('isEmpty')->willReturnCallback(fn() => $resolvers['isEmpty']($context));
    $mock->method('getValue')->willReturnCallback(fn() => $resolvers['getValue']($context));
    $mock->method('get')->willReturnCallback(fn(int $delta) => $resolvers['get']($context, $delta));
    $mock->method('__get')->willReturnCallback(fn(string $property) => $resolvers['__get']($context, $property));

    if ($entityDef->mutable) {
      $self = $mock;
      $mock->method('setValue')->willReturnCallback(
            function (mixed $values, bool $notify = TRUE) use ($resolvers, $context, $self) {
                $resolvers['setValue']($context, $values, $notify);
                return $self;
            }
        );
      $mock->method('__set')->willReturnCallback(
            fn(string $property, mixed $value) => $resolvers['__set']($context, $property, $value)
        );
    }
    else {
      $mock->method('setValue')->willReturnCallback(
            function () use ($fieldName) {
                throw new \LogicException(
                    "Cannot modify field '$fieldName' on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }
            );
      $mock->method('__set')->willReturnCallback(
            function (string $property) use ($fieldName) {
                throw new \LogicException(
                    "Cannot modify field '$fieldName' on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }
            );
    }

    return $mock;
  }

  /**
   * Creates a field item double.
   *
   * @param int $delta
   *   The delta.
   * @param mixed $value
   *   The item value.
   * @param string $fieldName
   *   The field name.
   * @param bool $mutable
   *   Whether the entity is mutable.
   * @param array<string, mixed> $context
   *   The context.
   *
   * @return \Drupal\Core\Field\FieldItemInterface
   *   The field item double.
   */
  private function createFieldItemDouble(
    int $delta,
    mixed $value,
    string $fieldName,
    bool $mutable,
    array $context,
  ): FieldItemInterface {
    $builder = new FieldItemDoubleBuilder($value, $delta, $fieldName, $mutable);

    /** @var \Drupal\Core\Field\FieldItemInterface&MockObject $mock */
    $mock = $this->createMock(FieldItemInterface::class);

    $resolvers = $builder->getResolvers();

    $mock->method('__get')->willReturnCallback(fn(string $property) => $resolvers['__get']($context, $property));
    $mock->method('getValue')->willReturnCallback(fn() => $resolvers['getValue']($context));
    $mock->method('isEmpty')->willReturnCallback(fn() => $resolvers['isEmpty']($context));

    if ($mutable) {
      $self = $mock;
      $mock->method('setValue')->willReturnCallback(
            function (mixed $val, bool $notify = TRUE) use ($resolvers, $context, $self) {
                $resolvers['setValue']($context, $val, $notify);
                return $self;
            }
        );
      $mock->method('__set')->willReturnCallback(
            fn(string $property, mixed $val) => $resolvers['__set']($context, $property, $val)
        );
    }
    else {
      $mock->method('setValue')->willReturnCallback(
            function () use ($delta) {
                throw new \LogicException(
                    "Cannot modify field item at delta $delta on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }
            );
      $mock->method('__set')->willReturnCallback(
            function (string $property) {
                throw new \LogicException(
                    "Cannot modify property '$property' on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }
            );
    }

    return $mock;
  }

}
