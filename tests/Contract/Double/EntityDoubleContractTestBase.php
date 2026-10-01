<?php

declare(strict_types=1);

namespace Deuteros\Tests\Contract\Double;

use Deuteros\Double\EntityDoubleDefinitionBuilder;
use Deuteros\Double\EntityDoubleFactory;
use Deuteros\Double\EntityDoubleFactoryInterface;
use Deuteros\Tests\Contract\EntityContractTestTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\TestCase;

/**
 * Runs the entity contract against entity doubles.
 *
 * Subclasses pick the adapter; the Kernel implementation of the contract
 * confirms that real entities behave the same way.
 */
abstract class EntityDoubleContractTestBase extends TestCase {

  use EntityContractTestTrait;

  /**
   * The factory creating the doubles under contract.
   */
  protected EntityDoubleFactoryInterface $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The contract uses real field item classes to name main properties.
    if (!class_exists('Drupal\link\Plugin\Field\FieldType\LinkItem')) {
      $this->markTestSkipped('The entity contract requires Drupal core.');
    }

    $this->factory = EntityDoubleFactory::fromTest($this);
    $this->assertInstanceOf($this->getFactoryClass(), $this->factory);
  }

  /**
   * Returns the class of the factory the adapter provides.
   *
   * @return class-string<\Deuteros\Double\EntityDoubleFactoryInterface>
   *   The factory class.
   */
  abstract protected function getFactoryClass(): string;

  /**
   * {@inheritdoc}
   */
  protected function createContractEntity(array $fields = [], int|string|null $id = NULL, ?string $uuid = NULL, ?string $label = NULL, ?string $url = NULL, bool $mutable = FALSE): FieldableEntityInterface {
    $builder = EntityDoubleDefinitionBuilder::create(self::ENTITY_TYPE_ID)
      ->interface(FieldableEntityInterface::class)
      ->bundle(self::BUNDLE)
      ->id($id)
      ->label($label);
    if ($uuid !== NULL) {
      $builder->uuid($uuid);
    }
    if ($url !== NULL) {
      $builder->url($url);
    }
    foreach ($fields as $fieldName => $field) {
      $builder->field($fieldName, $field->value, $field->type, $field->settings, $field->itemClass);
    }

    $definition = $builder->build();
    $entity = $mutable ? $this->factory->createMutable($definition) : $this->factory->create($definition);
    assert($entity instanceof FieldableEntityInterface);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function createReferencedEntity(?int $id, string $label): EntityInterface {
    return $this->factory->create(
      EntityDoubleDefinitionBuilder::create(self::ENTITY_TYPE_ID)
        ->bundle(self::BUNDLE)
        ->id($id)
        ->label($label)
        ->build()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function resetContractState(): void {
    // Doubles hold no state outside of themselves.
  }

}
