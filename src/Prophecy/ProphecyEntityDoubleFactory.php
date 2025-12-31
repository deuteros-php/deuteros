<?php

declare(strict_types=1);

namespace Deuteros\Prophecy;

use Deuteros\Common\EntityDefinition;
use Deuteros\Common\EntityDoubleBuilder;
use Deuteros\Common\EntityDoubleFactory;
use Deuteros\Common\FieldItemDoubleBuilder;
use Deuteros\Common\FieldItemListDoubleBuilder;
use Deuteros\Common\GuardrailEnforcer;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Prophecy\Argument;
use Prophecy\Prophet;
use Prophecy\Prophecy\MethodProphecy;

/**
 * Factory for creating entity doubles using Prophecy.
 */
final class ProphecyEntityDoubleFactory extends EntityDoubleFactory {

  /**
   * Constructs a ProphecyEntityDoubleFactory.
   *
   * @param \Prophecy\Prophet $prophet
   *   The Prophecy prophet instance.
   */
  public function __construct(
    private readonly Prophet $prophet,
  ) {}

  /**
   * {@inheritdoc}
   *
   * Prophecy can handle multiple interfaces that share a common parent via
   * willImplement(), so we override the base implementation to keep all
   * declared interfaces.
   */
  protected function resolveInterfaces(EntityDefinition $definition): array {
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
   * {@inheritdoc}
   */
  protected function createDoubleForInterfaces(array $interfaces): object {
    // Prophecy can only prophesize one interface at a time, but we can
    // use willImplement() to add additional interfaces.
    $primaryInterface = array_shift($interfaces);
    $prophecy = $this->prophet->prophesize($primaryInterface);

    foreach ($interfaces as $interface) {
      $prophecy->willImplement($interface);
    }

    return $prophecy;
  }

  /**
   * {@inheritdoc}
   */
  protected function wireEntityResolvers(object $double, EntityDoubleBuilder $builder, EntityDefinition $definition): void {
    /** @var \Prophecy\Prophecy\ObjectProphecy $prophecy */
    $prophecy = $double;
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

      // Note: ::__get is not declared in "FieldableEntityInterface", so magic
      // property access ($entity->field_name) is not supported on interface
      // prophecies. Use ::get() method instead: $entity->get('field_name').
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
      $prophecy->$method(Argument::cetera())->will(fn(array $args) => $resolver($context, ...$args));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function wireGuardrails(object $double, EntityDefinition $definition, array $interfaces): void {
    /** @var \Prophecy\Prophecy\ObjectProphecy $prophecy */
    $prophecy = $double;
    $unsupportedMethods = GuardrailEnforcer::getUnsupportedMethods();

    foreach ($unsupportedMethods as $method => $reason) {
      // Skip if there's an override.
      if ($definition->hasMethodOverride($method)) {
        continue;
      }

      // Check if the method exists on any declared interface.
      $methodExists = FALSE;
      foreach ($interfaces as $interface) {
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
   * {@inheritdoc}
   */
  protected function createFieldListDoubleObject(): object {
    return $this->prophet->prophesize(FieldItemListInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function wireFieldListResolvers(object $double, FieldItemListDoubleBuilder $builder, EntityDefinition $entityDefinition, array $context): void {
    /** @var \Prophecy\Prophecy\ObjectProphecy $prophecy */
    $prophecy = $double;
    $resolvers = $builder->getResolvers();
    $fieldName = $builder->getFieldName();

    $prophecy->first()->will(fn() => $resolvers['first']($context));
    $prophecy->isEmpty()->will(fn() => $resolvers['isEmpty']($context));
    $prophecy->getValue()->will(fn() => $resolvers['getValue']($context));
    $prophecy->get(Argument::type('int'))->will(fn(array $args) => $resolvers['get']($context, $args[0]));

    // Manually add MethodProphecy for ::__get since Prophecy's "ObjectProphecy"
    // intercepts ::__get calls instead of treating them as method stubs.
    $getMethodProphecy = new MethodProphecy($prophecy, '__get', [Argument::type('string')]);
    $getMethodProphecy->will(fn(array $args) => $resolvers['__get']($context, $args[0]));
    $prophecy->addMethodProphecy($getMethodProphecy);

    if ($entityDefinition->mutable) {
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

      // Manually add "MethodProphecy" for ::__set.
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

      // Manually add MethodProphecy for ::__set.
      $setMethodProphecy = new MethodProphecy($prophecy, '__set', [Argument::type('string'), Argument::any()]);
      $setMethodProphecy->will(
        function () use ($fieldName) {
          throw new \LogicException(
            "Cannot modify field '$fieldName' on immutable entity double. "
            . "Use createMutableEntityDouble() if you need to test mutations."
          );
        }
      );
      $prophecy->addMethodProphecy($setMethodProphecy);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createFieldItemDoubleObject(): object {
    return $this->prophet->prophesize(FieldItemInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function wireFieldItemResolvers(object $double, FieldItemDoubleBuilder $builder, bool $mutable, int $delta, string $fieldName, array $context): void {
    /** @var \Prophecy\Prophecy\ObjectProphecy $prophecy */
    $prophecy = $double;
    $resolvers = $builder->getResolvers();

    // Manually add "MethodProphecy" for ::__get since Prophecy's
    // "ObjectProphecy" intercepts ::__get calls instead of treating them as
    // method stubs.
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

      // Manually add "MethodProphecy" for ::__set.
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

      // Manually add "MethodProphecy" for ::__set.
      $setMethodProphecy = new MethodProphecy($prophecy, '__set', [Argument::type('string'), Argument::any()]);
      $setMethodProphecy->will(
        function (array $args) {
          throw new \LogicException(
            "Cannot modify property '{$args[0]}' on immutable entity double. "
            . "Use createMutableEntityDouble() if you need to test mutations."
          );
        }
      );
      $prophecy->addMethodProphecy($setMethodProphecy);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function instantiateDouble(object $double): EntityInterface {
    /** @var \Prophecy\Prophecy\ObjectProphecy $prophecy */
    return $double->reveal();
  }

  /**
   * {@inheritdoc}
   */
  protected function instantiateFieldListDouble(object $double): FieldItemListInterface {
    /** @var \Prophecy\Prophecy\ObjectProphecy $prophecy */
    return $double->reveal();
  }

  /**
   * {@inheritdoc}
   */
  protected function instantiateFieldItemDouble(object $double): FieldItemInterface {
    /** @var \Prophecy\Prophecy\ObjectProphecy $prophecy */
    return $double->reveal();
  }

}
