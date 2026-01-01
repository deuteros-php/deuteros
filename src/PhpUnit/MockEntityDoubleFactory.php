<?php

declare(strict_types=1);

namespace Deuteros\PhpUnit;

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
use PHPUnit\Framework\TestCase;

/**
 * Factory for creating entity doubles using PHPUnit native mock objects.
 */
final class MockEntityDoubleFactory extends EntityDoubleFactory {

  /**
   * Constructs a MockEntityDoubleFactory.
   *
   * @param \PHPUnit\Framework\TestCase $testCase
   *   The PHPUnit test case instance.
   */
  public function __construct(
    private readonly TestCase $testCase,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function createDoubleForInterfaces(array $interfaces): object {
    // Use runtime interface for __get/__set support.
    $runtimeInterface = $this->getOrCreateRuntimeInterface($interfaces);
    $mock = $this->invokeProtectedMethod('createMock', $runtimeInterface);
    assert(is_object($mock));
    return $mock;
  }

  /**
   * Invokes a protected method on the test case.
   *
   * PHPUnit's mock creation methods are protected, but we need to call them
   * from outside the test case class.
   *
   * @param string $method
   *   The method name.
   * @param mixed ...$args
   *   The method arguments.
   *
   * @return mixed
   *   The method return value.
   */
  private function invokeProtectedMethod(string $method, mixed ...$args): mixed {
    $reflection = new \ReflectionMethod($this->testCase, $method);
    return $reflection->invoke($this->testCase, ...$args);
  }

  /**
   * {@inheritdoc}
   */
  protected function wireEntityResolvers(object $double, EntityDoubleBuilder $builder, EntityDefinition $definition): void {
    /** @var \PHPUnit\Framework\MockObject\MockObject $mock */
    $mock = $double;
    $resolvers = $builder->getResolvers();
    $context = $definition->context;

    // Helper to wire a method, checking for overrides first.
    $wireMethod = function (string $method, callable $defaultResolver) use ($mock, $builder, $definition, $context) {
      if ($definition->hasMethodOverride($method)) {
        $resolver = $builder->getMethodOverrideResolver($method);
        $mock->method($method)->willReturnCallback(fn(mixed ...$args) => $resolver($context, ...$args));
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
        $mock->method('set')->willReturnCallback(fn(mixed ...$args) => $resolver($context, ...$args));
      }
    }

    // Wire magic accessors for property-style field access.
    $wireMethod('__get', fn(string $name) => $resolvers['__get']($context, $name));

    if (!$definition->hasMethodOverride('__set')) {
      if ($definition->mutable) {
        $mock->method('__set')->willReturnCallback(
          function (string $name, mixed $value) use ($resolvers, $context) {
            $resolvers['set']($context, $name, $value, TRUE);
          }
        );
      }
      else {
        $mock->method('__set')->willReturnCallback(
          function (string $name) {
            throw new \LogicException(
              "Cannot modify field '$name' on immutable entity double. "
              . "Use createMutableEntityDouble() if you need to test mutations."
            );
          }
        );
      }
    }

    // Wire remaining method overrides (those not already wired above).
    $coreMethodsWired = ['id', 'uuid', 'label', 'bundle', 'getEntityTypeId', 'hasField', 'get', 'set', '__get', '__set'];
    foreach ($definition->methodOverrides as $method => $override) {
      if (in_array($method, $coreMethodsWired, TRUE)) {
        // Already handled above.
        continue;
      }
      $resolver = $builder->getMethodOverrideResolver($method);
      $mock->method($method)->willReturnCallback(fn(mixed ...$args) => $resolver($context, ...$args));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function wireGuardrails(object $double, EntityDefinition $definition, array $interfaces): void {
    /** @var \PHPUnit\Framework\MockObject\MockObject $mock */
    $mock = $double;
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
        if ($definition->lenient) {
          // In lenient mode, return null instead of throwing.
          $mock->method($method)->willReturnCallback(
            fn() => GuardrailEnforcer::getLenientDefault()
          );
        }
        else {
          $mock->method($method)->willReturnCallback(
            fn() => throw GuardrailEnforcer::createUnsupportedMethodException($method)
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createFieldListDoubleObject(): object {
    $mock = $this->invokeProtectedMethod('createMock', FieldItemListInterface::class);
    assert(is_object($mock));
    return $mock;
  }

  /**
   * {@inheritdoc}
   */
  protected function wireFieldListResolvers(object $double, FieldItemListDoubleBuilder $builder, EntityDefinition $entityDefinition, array $context): void {
    /** @var \PHPUnit\Framework\MockObject\MockObject $mock */
    $mock = $double;
    $resolvers = $builder->getResolvers();
    $fieldName = $builder->getFieldName();

    $mock->method('first')->willReturnCallback(fn() => $resolvers['first']($context));
    $mock->method('isEmpty')->willReturnCallback(fn() => $resolvers['isEmpty']($context));
    $mock->method('getValue')->willReturnCallback(fn() => $resolvers['getValue']($context));
    $mock->method('get')->willReturnCallback(fn(int $delta) => $resolvers['get']($context, $delta));
    $mock->method('__get')->willReturnCallback(fn(string $property) => $resolvers['__get']($context, $property));

    if ($entityDefinition->mutable) {
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
  }

  /**
   * {@inheritdoc}
   */
  protected function createFieldItemDoubleObject(): object {
    $mock = $this->invokeProtectedMethod('createMock', FieldItemInterface::class);
    assert(is_object($mock));
    return $mock;
  }

  /**
   * {@inheritdoc}
   */
  protected function wireFieldItemResolvers(object $double, FieldItemDoubleBuilder $builder, bool $mutable, int $delta, string $fieldName, array $context): void {
    /** @var \PHPUnit\Framework\MockObject\MockObject $mock */
    $mock = $double;
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
  }

  /**
   * {@inheritdoc}
   */
  protected function instantiateDouble(object $double): EntityInterface {
    // PHPUnit mocks are already usable as-is.
    /** @var \Drupal\Core\Entity\EntityInterface $double */
    return $double;
  }

  /**
   * {@inheritdoc}
   */
  protected function instantiateFieldListDouble(object $double): FieldItemListInterface {
    // PHPUnit mocks are already usable as-is.
    /** @var \Drupal\Core\Field\FieldItemListInterface $double */
    return $double;
  }

  /**
   * {@inheritdoc}
   */
  protected function instantiateFieldItemDouble(object $double): FieldItemInterface {
    // PHPUnit mocks are already usable as-is.
    /** @var \Drupal\Core\Field\FieldItemInterface $double */
    return $double;
  }

}
