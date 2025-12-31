<?php

declare(strict_types=1);

namespace Deuteros\Common;

use Deuteros\PhpUnit\MockEntityDoubleFactory;
use Deuteros\Prophecy\ProphecyEntityDoubleFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use PHPUnit\Framework\TestCase;

/**
 * Abstract factory for creating entity doubles.
 *
 * DEUTEROS - Drupal Entity Unit Test Extensible Replacement Object Scaffolding.
 *
 * This factory provides value-object entity doubles for Drupal unit tests.
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
 * This is a unit-test value object only. Use Kernel tests for behaviors that
 * require runtime services.
 *
 * @example Static values
 * ```php
 * $factory = EntityDoubleFactory::fromTest($this);
 * $entity = $factory->create([
 *     'entity_type' => 'node',
 *     'bundle' => 'article',
 *     'id' => 1,
 *     'fields' => [
 *         'field_title' => 'Test Article',
 *     ],
 *     'interfaces' => [FieldableEntityInterface::class],
 * ]);
 * $this->assertSame('Test Article', $entity->get('field_title')->value);
 * ```
 *
 * @example Callback-based resolution
 * ```php
 * $factory = EntityDoubleFactory::fromTest($this);
 * $entity = $factory->create([
 *     'entity_type' => 'node',
 *     'bundle' => 'article',
 *     'fields' => [
 *         'field_date' => fn($context) => $context['date'],
 *     ],
 *     'interfaces' => [FieldableEntityInterface::class],
 * ], ['date' => '2024-01-01']);
 * $this->assertSame('2024-01-01', $entity->get('field_date')->value);
 * ```
 */
abstract class EntityDoubleFactory implements EntityDoubleFactoryInterface {

  /**
   * Creates the appropriate factory based on the test case's available traits.
   *
   * Detects whether the test uses Prophecy (ProphecyTrait) or PHPUnit mocks
   * and returns the matching factory implementation.
   *
   * @param \PHPUnit\Framework\TestCase $test
   *   The test case instance.
   *
   * @return static
   *   The appropriate factory implementation.
   */
  public static function fromTest(TestCase $test): static {
    // Check if test uses ProphecyTrait (has getProphet() method).
    if (method_exists($test, 'getProphet')) {
      return new ProphecyEntityDoubleFactory($test->getProphet());
    }
    return new MockEntityDoubleFactory($test);
  }

  /**
   * {@inheritdoc}
   */
  public function create(array $definition, array $context = []): EntityInterface {
    return $this->buildEntityDouble($this->normalizeDefinition($definition, $context, FALSE));
  }

  /**
   * {@inheritdoc}
   */
  public function createMutable(array $definition, array $context = []): EntityInterface {
    return $this->buildEntityDouble($this->normalizeDefinition($definition, $context, TRUE));
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
  protected function buildEntityDouble(EntityDefinition $definition): EntityInterface {
    // Determine interfaces to mock.
    $interfaces = $this->resolveInterfaces($definition);

    // Create mutable state container if needed.
    $mutableState = $definition->mutable ? new MutableStateContainer() : NULL;

    // Create the builder.
    $builder = new EntityDoubleBuilder($definition, $mutableState);

    // Set up field list factory.
    $builder->setFieldListFactory(
      fn(string $fieldName, FieldDefinition $fieldDefinition, array $context) =>
        $this->createFieldItemListDouble($fieldName, $fieldDefinition, $definition, $mutableState, $context)
    );

    // Create the double.
    $double = $this->createDoubleForInterfaces($interfaces);

    // Wire up resolvers.
    $this->wireEntityResolvers($double, $builder, $definition);

    // Wire guardrails for unsupported methods.
    $this->wireGuardrails($double, $definition, $interfaces);

    return $this->instantiateDouble($double);
  }

  /**
   * Normalizes a definition array into an EntityDefinition.
   *
   * @param array<string, mixed> $definition
   *   The definition array with snake_case keys.
   * @param array<string, mixed> $context
   *   Additional context to merge.
   * @param bool $mutable
   *   Whether the entity double should be mutable.
   *
   * @return \Deuteros\Common\EntityDefinition
   *   The normalized EntityDefinition.
   */
  protected function normalizeDefinition(
    array $definition,
    array $context = [],
    bool $mutable = FALSE,
  ): EntityDefinition {
    // Merge context into definition.
    if ($context !== []) {
      $definition['context'] = array_merge($definition['context'] ?? [], $context);
    }

    // Set mutability.
    $definition['mutable'] = $mutable;

    return EntityDefinition::fromArray($definition);
  }

  /**
   * Resolves the interfaces to mock.
   *
   * Deduplicates interfaces to avoid redundancy when interfaces extend each
   * other (e.g., if both "FieldableEntityInterface" and "EntityInterface" are
   * declared, only "FieldableEntityInterface" is kept since it already extends
   * "EntityInterface").
   *
   * Also ensures "EntityInterface" is always covered by at least one of the
   * declared interfaces.
   *
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   *
   * @return list<class-string>
   *   The interfaces to mock.
   */
  protected function resolveInterfaces(EntityDefinition $definition): array {
    // Collect all declared interfaces.
    $interfaces = $definition->interfaces;

    // If no interfaces declared, just use EntityInterface.
    if ($interfaces === []) {
      return [EntityInterface::class];
    }

    // Filter out interfaces that are parents of other interfaces in the list.
    // This avoids redundancy (if A extends B and both are declared, keep only
    // A).
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

    // If "EntityInterface" is not covered by any declared interface, add it.
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
   * Creates a field item list double.
   *
   * @param string $fieldName
   *   The field name.
   * @param \Deuteros\Common\FieldDefinition $fieldDefinition
   *   The field definition.
   * @param \Deuteros\Common\EntityDefinition $entityDefinition
   *   The entity definition.
   * @param \Deuteros\Common\MutableStateContainer|null $mutableState
   *   The mutable state container.
   * @param array<string, mixed> $context
   *   The context.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The field item list double.
   */
  protected function createFieldItemListDouble(
    string $fieldName,
    FieldDefinition $fieldDefinition,
    EntityDefinition $entityDefinition,
    ?MutableStateContainer $mutableState,
    array $context,
  ): FieldItemListInterface {
    $builder = new FieldItemListDoubleBuilder($fieldDefinition, $fieldName, $entityDefinition->mutable);

    // Set up field item factory.
    $builder->setFieldItemFactory(
      fn(int $delta, mixed $value, array $ctx) =>
        $this->createFieldItemDouble($delta, $value, $fieldName, $entityDefinition->mutable, $ctx)
    );

    // Set up mutable state updater if applicable.
    if ($mutableState !== NULL) {
      $builder->setMutableStateUpdater(
        fn(string $name, mixed $value) => $mutableState->setFieldValue($name, $value)
      );
    }

    // Create the double.
    $double = $this->createFieldListDoubleObject();

    // Wire up resolvers.
    $this->wireFieldListResolvers($double, $builder, $entityDefinition, $context);

    return $this->instantiateFieldListDouble($double);
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
  protected function createFieldItemDouble(
    int $delta,
    mixed $value,
    string $fieldName,
    bool $mutable,
    array $context,
  ): FieldItemInterface {
    $builder = new FieldItemDoubleBuilder($value, $delta, $fieldName, $mutable);

    // Create the double.
    $double = $this->createFieldItemDoubleObject();

    // Wire up resolvers.
    $this->wireFieldItemResolvers($double, $builder, $mutable, $delta, $fieldName, $context);

    return $this->instantiateFieldItemDouble($double);
  }

  /**
   * Creates a double for the given interfaces.
   *
   * @param list<class-string> $interfaces
   *   The interfaces to implement.
   *
   * @return object
   *   The mock/prophecy object (not revealed).
   */
  abstract protected function createDoubleForInterfaces(array $interfaces): object;

  /**
   * Wires entity method resolvers to the double.
   *
   * @param object $double
   *   The mock/prophecy object.
   * @param \Deuteros\Common\EntityDoubleBuilder $builder
   *   The entity double builder.
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   */
  abstract protected function wireEntityResolvers(
    object $double,
    EntityDoubleBuilder $builder,
    EntityDefinition $definition,
  ): void;

  /**
   * Wires guardrail exceptions to the double.
   *
   * @param object $double
   *   The mock/prophecy object.
   * @param \Deuteros\Common\EntityDefinition $definition
   *   The entity definition.
   * @param list<class-string> $interfaces
   *   The interfaces being mocked.
   */
  abstract protected function wireGuardrails(
    object $double,
    EntityDefinition $definition,
    array $interfaces,
  ): void;

  /**
   * Creates a field item list double object.
   *
   * @return object
   *   The mock/prophecy object (not revealed).
   */
  abstract protected function createFieldListDoubleObject(): object;

  /**
   * Wires field item list method resolvers to the double.
   *
   * @param object $double
   *   The mock/prophecy object.
   * @param \Deuteros\Common\FieldItemListDoubleBuilder $builder
   *   The field item list builder.
   * @param \Deuteros\Common\EntityDefinition $entityDefinition
   *   The entity definition.
   * @param array<string, mixed> $context
   *   The context.
   */
  abstract protected function wireFieldListResolvers(
    object $double,
    FieldItemListDoubleBuilder $builder,
    EntityDefinition $entityDefinition,
    array $context,
  ): void;

  /**
   * Creates a field item double object.
   *
   * @return object
   *   The mock/prophecy object (not revealed).
   */
  abstract protected function createFieldItemDoubleObject(): object;

  /**
   * Wires field item method resolvers to the double.
   *
   * @param object $double
   *   The mock/prophecy object.
   * @param \Deuteros\Common\FieldItemDoubleBuilder $builder
   *   The field item builder.
   * @param bool $mutable
   *   Whether the entity is mutable.
   * @param int $delta
   *   The field item delta.
   * @param string $fieldName
   *   The field name.
   * @param array<string, mixed> $context
   *   The context.
   */
  abstract protected function wireFieldItemResolvers(
    object $double,
    FieldItemDoubleBuilder $builder,
    bool $mutable,
    int $delta,
    string $fieldName,
    array $context,
  ): void;

  /**
   * Reveals the entity double (converts mock/prophecy to usable object).
   *
   * @param object $double
   *   The mock/prophecy object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The revealed entity.
   */
  abstract protected function instantiateDouble(object $double): EntityInterface;

  /**
   * Reveals a field item list double.
   *
   * @param object $double
   *   The mock/prophecy object.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The revealed field item list.
   */
  abstract protected function instantiateFieldListDouble(object $double): FieldItemListInterface;

  /**
   * Reveals a field item double.
   *
   * @param object $double
   *   The mock/prophecy object.
   *
   * @return \Drupal\Core\Field\FieldItemInterface
   *   The revealed field item.
   */
  abstract protected function instantiateFieldItemDouble(object $double): FieldItemInterface;

}
