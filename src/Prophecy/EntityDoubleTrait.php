<?php

declare(strict_types=1);

namespace Deuteros\Prophecy;

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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Trait for creating entity doubles using Prophecy.
 *
 * DEUTEROS - Drupal Entity Unit Test Extensible Replacement Object Scaffolding.
 *
 * This trait provides value-object entity doubles for Drupal unit tests.
 * It allows testing code that depends on entity and field interfaces without
 * requiring Kernel tests, module enablement, storage, or services.
 *
 * Behavioral parity with PHPUnit adapter is mandatory - both traits produce
 * identical results for the same inputs.
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
 */
trait EntityDoubleTrait {
  use EntityDefinitionNormalizerTrait;
  use ProphecyTrait;

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
    // Determine interfaces to prophesize.
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

    // Create the prophecy with all interfaces.
    $prophecy = $this->prophesizeWithInterfaces($interfaces);

    // Wire up resolvers.
    $this->wireEntityResolvers($prophecy, $builder, $definition);

    return $prophecy->reveal();
  }

  /**
   * Creates a prophecy implementing multiple interfaces.
   *
   * @param list<class-string> $interfaces
   *   The interfaces to implement.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The prophecy.
   */
  private function prophesizeWithInterfaces(array $interfaces): ObjectProphecy {
    // Prophecy can only prophesize one interface at a time, but we can
    // use willImplement() to add additional interfaces.
    $primaryInterface = array_shift($interfaces);
    $prophecy = $this->prophesize($primaryInterface);

    foreach ($interfaces as $interface) {
      $prophecy->willImplement($interface);
    }

    return $prophecy;
  }

  /**
   * Resolves the interfaces to prophesize.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   *
   * @return list<class-string>
   *   The interfaces to prophesize.
   */
  private function resolveInterfaces(EntityDefinition $definition): array {
    // Always include EntityInterface.
    $interfaces = [EntityInterface::class];

    // Add declared interfaces.
    foreach ($definition->interfaces as $interface) {
      if (!in_array($interface, $interfaces, TRUE)) {
        $interfaces[] = $interface;
      }
    }

    return $interfaces;
  }

  /**
   * Wires entity resolvers to the prophecy.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $prophecy
   *   The prophecy object.
   * @param \Deuteros\Common\EntityDoubleBuilder $builder
   *   The entity double builder.
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   */
  private function wireEntityResolvers(
    ObjectProphecy $prophecy,
    EntityDoubleBuilder $builder,
    EntityDefinition $definition,
  ): void {
    $resolvers = $builder->getResolvers();
    $context = $definition->context;

    // Wire core entity methods.
    $prophecy->id()->will(fn() => $resolvers['id']($context));
    $prophecy->uuid()->will(fn() => $resolvers['uuid']($context));
    $prophecy->label()->will(fn() => $resolvers['label']($context));
    $prophecy->bundle()->will(fn() => $resolvers['bundle']($context));
    $prophecy->getEntityTypeId()->will(fn() => $resolvers['getEntityTypeId']($context));

    // Wire fieldable entity methods if applicable.
    if ($definition->hasInterface(FieldableEntityInterface::class)) {
      $prophecy->hasField(Argument::type('string'))->will(
            fn(array $args) => $resolvers['hasField']($context, $args[0])
        );
      $prophecy->get(Argument::type('string'))->will(
            fn(array $args) => $resolvers['get']($context, $args[0])
        );
      // Note: __get is not declared in FieldableEntityInterface, so magic
      // property access ($entity->field_name) is not supported on interface
      // prophecies. Use get() method instead: $entity->get('field_name').
      if ($definition->mutable) {
        $revealed = NULL;
        $prophecy->set(Argument::type('string'), Argument::any(), Argument::any())->will(
          function (array $args) use ($resolvers, $context, &$revealed, $prophecy) {
              $resolvers['set']($context, $args[0], $args[1], $args[2] ?? TRUE);
            if ($revealed === NULL) {
                        $revealed = $prophecy->reveal();
            }
              return $revealed;
          }
          );
      }
      else {
        $prophecy->set(Argument::type('string'), Argument::any(), Argument::any())->will(
          function (array $args) {
              throw new \LogicException(
                  "Cannot modify field '{$args[0]}' on immutable entity double. "
                  . "Use createMutableEntityDouble() if you need to test mutations."
              );
          }
            );
      }
    }

    // Wire method overrides.
    foreach ($definition->methodOverrides as $method => $override) {
      $resolver = $builder->getMethodOverrideResolver($method);
      $prophecy->$method(Argument::cetera())->will(
            fn(array $args) => $resolver($context, ...$args)
        );
    }

    // Wire guardrails for unsupported methods.
    $this->wireGuardrails($prophecy, $definition);
  }

  /**
   * Wires guardrail exceptions to the prophecy.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $prophecy
   *   The prophecy object.
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   */
  private function wireGuardrails(ObjectProphecy $prophecy, EntityDefinition $definition): void {
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
        $prophecy->$method(Argument::cetera())->will(
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

    if ($entityDef->mutable) {
      $revealed = NULL;
      $prophecy->setValue(Argument::any(), Argument::any())->will(
            function (array $args) use ($resolvers, $context, &$revealed, $prophecy) {
                $resolvers['setValue']($context, $args[0], $args[1] ?? TRUE);
              if ($revealed === NULL) {
                    $revealed = $prophecy->reveal();
              }
                return $revealed;
            }
        );

      // Manually add MethodProphecy for __set.
      $setMethodProphecy = new MethodProphecy($prophecy, '__set', [Argument::type('string'), Argument::any()]);
      $setMethodProphecy->will(fn(array $args) => $resolvers['__set']($context, $args[0], $args[1]));
      $prophecy->addMethodProphecy($setMethodProphecy);
    }
    else {
      $prophecy->setValue(Argument::any(), Argument::any())->will(
            function () use ($fieldName) {
                throw new \LogicException(
                    "Cannot modify field '$fieldName' on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }
            );

      // Manually add MethodProphecy for __set.
      $setMethodProphecy = new MethodProphecy($prophecy, '__set', [Argument::type('string'), Argument::any()]);
      $setMethodProphecy->will(function () use ($fieldName) {
          throw new \LogicException(
          "Cannot modify field '$fieldName' on immutable entity double. "
          . "Use createMutableEntityDouble() if you need to test mutations."
          );
      });
      $prophecy->addMethodProphecy($setMethodProphecy);
    }

    return $prophecy->reveal();
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

    $prophecy = $this->prophesize(FieldItemInterface::class);

    $resolvers = $builder->getResolvers();

    // Manually add MethodProphecy for __get since Prophecy's ObjectProphecy
    // intercepts __get calls instead of treating them as method stubs.
    $getMethodProphecy = new MethodProphecy($prophecy, '__get', [Argument::type('string')]);
    $getMethodProphecy->will(fn(array $args) => $resolvers['__get']($context, $args[0]));
    $prophecy->addMethodProphecy($getMethodProphecy);

    $prophecy->getValue()->will(fn() => $resolvers['getValue']($context));
    $prophecy->isEmpty()->will(fn() => $resolvers['isEmpty']($context));

    if ($mutable) {
      $revealed = NULL;
      $prophecy->setValue(Argument::any(), Argument::any())->will(
            function (array $args) use ($resolvers, $context, &$revealed, $prophecy) {
                $resolvers['setValue']($context, $args[0], $args[1] ?? TRUE);
              if ($revealed === NULL) {
                    $revealed = $prophecy->reveal();
              }
                return $revealed;
            }
        );

      // Manually add MethodProphecy for __set.
      $setMethodProphecy = new MethodProphecy($prophecy, '__set', [Argument::type('string'), Argument::any()]);
      $setMethodProphecy->will(fn(array $args) => $resolvers['__set']($context, $args[0], $args[1]));
      $prophecy->addMethodProphecy($setMethodProphecy);
    }
    else {
      $prophecy->setValue(Argument::any(), Argument::any())->will(
            function () use ($delta) {
                throw new \LogicException(
                    "Cannot modify field item at delta $delta on immutable entity double. "
                    . "Use createMutableEntityDouble() if you need to test mutations."
                );
            }
            );

      // Manually add MethodProphecy for __set.
      $setMethodProphecy = new MethodProphecy($prophecy, '__set', [Argument::type('string'), Argument::any()]);
      $setMethodProphecy->will(function (array $args) {
          throw new \LogicException(
          "Cannot modify property '{$args[0]}' on immutable entity double. "
          . "Use createMutableEntityDouble() if you need to test mutations."
          );
      });
      $prophecy->addMethodProphecy($setMethodProphecy);
    }

    return $prophecy->reveal();
  }

}
